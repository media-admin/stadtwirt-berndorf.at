<?php

namespace FluentCrm\App\Modules\MCP\Helpers;

use FluentCrm\App\Services\Helper;

/**
 * Bridge between MCP tools and FluentCRM's advanced contact filter engine —
 * the same condition-group search the admin "Advanced Filter" UI runs through
 * ContactsQuery / Subscriber::build*FilterQuery.
 *
 * Why a bridge instead of passing the raw payload through (pattern borrowed
 * from FluentCart's MCP AdvancedSearch):
 *
 *  1. The engine fails SILENTLY in the widening direction — an unknown
 *     (provider, property) pair, a malformed condition, or a Pro-only
 *     provider without its handler either matches everyone or matches no one
 *     with no signal. An agent can't detect "confidently wrong".
 *  2. The full filter catalog is large (30+ properties across providers,
 *     each with its own operators, options and value formats). Inlining it
 *     into list-contacts' input_schema would bloat every session's context;
 *     instead the get-contact-filter-schema tool serves it on demand and the
 *     list tools carry one lean `advanced_filters` parameter.
 *  3. Agents send a lean {property: "provider.property", operator, value}
 *     triple; the engine-internal wire format (source tuple, extra_value
 *     passthrough) is produced here — and the admin UI's own
 *     source: [provider, property] format is still accepted.
 *
 * The catalog is derived live from Helper::getAdvancedFilterOptions() — the
 * same registry the admin UI reads, including the fluentcrm_advanced_filter_options
 * extensions from Pro / integrations — so the schema can never drift from
 * what actually executes. Operator sets per property mirror the admin UI's
 * resolution rules (resources/v3app .../RichFilters/_FilterItem.vue).
 */
class AdvancedFilters
{
    const MAX_GROUPS = 5;

    const MAX_CONDITIONS_PER_GROUP = 10;

    // Upper bound on values inside one condition so a huge whereIn fan-out
    // can't be built. Exceeding it is an error, not a silent slice — slicing
    // a not_in list would WIDEN the match.
    const MAX_VALUES_PER_CONDITION = 100;

    /**
     * Whether this operator carries no value on this property. is_null /
     * not_null / never always assert without one. exist / not_exist are
     * valueless ONLY when the property hides its value input
     * (disable_values — e.g. FluentCart's commerce_exist / license_exist);
     * on its purchased_items / variation / license selectors the SAME
     * operator pair carries the id list, and stripping it would make the
     * integration handler silently no-op the condition (match everyone in
     * the group).
     */
    private static function isValuelessOperator($operator, array $def)
    {
        if (in_array($operator, ['is_null', 'not_null', 'never'], true)) {
            return true;
        }
        if (in_array($operator, ['exist', 'not_exist'], true)) {
            return !empty($def['disable_values']);
        }
        return false;
    }

    // ---------------------------------------------------------------------
    // Catalog
    // ---------------------------------------------------------------------

    /**
     * Live property map: 'provider.property' => {provider, property, def, locked}.
     *
     * `locked` marks children the registry flags as `disabled` (free plugin
     * advertising Pro-only filters) and providers with no registered
     * fluentcrm_contacts_filter_{provider} handler — either way ContactsQuery
     * would compile the group to `1 = 0` (match nothing) with no explanation,
     * so validation refuses them with a pro_required error instead.
     *
     * Memoized per request, and it earns it: Helper::getAdvancedFilterOptions()
     * measured 2 DB queries per call (integrations hook the registry filter and
     * some of them query), while catalog() is consulted once per condition —
     * up to 50 in one payload. Without the memo a single filtered list call
     * would run ~100 extra queries.
     *
     * @return array<string, array{provider:string, property:string, def:array, locked:bool}>
     */
    public static function catalog()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        // getAdvancedFilterOptions() returns array_values() — the provider key
        // lives in each group's 'value', not the array key.
        foreach ((array) Helper::getAdvancedFilterOptions() as $group) {
            if (!is_array($group)) {
                continue;
            }
            $provider = (string) ($group['value'] ?? '');
            $children = $group['children'] ?? [];
            if ($provider === '' || !is_array($children)) {
                continue;
            }

            $handlerMissing = !has_action('fluentcrm_contacts_filter_' . $provider);

            foreach ($children as $child) {
                if (!is_array($child) || empty($child['value']) || !is_string($child['value'])) {
                    continue;
                }
                $key = $provider . '.' . $child['value'];
                if (isset($cache[$key])) {
                    continue;
                }
                $cache[$key] = [
                    'provider' => $provider,
                    'property' => $child['value'],
                    'def'      => $child,
                    'locked'   => !empty($child['disabled']) || $handlerMissing,
                ];
            }
        }

        return $cache;
    }

    // ---------------------------------------------------------------------
    // Schema (what get-contact-filter-schema returns)
    // ---------------------------------------------------------------------

    /**
     * The agent-facing reference for the advanced_filters parameter: every
     * filterable property with operators, value kind, options and hints, plus
     * the payload format and a worked example.
     */
    public static function schema()
    {
        $properties = [];
        $locked     = [];

        foreach (self::catalog() as $key => $entry) {
            if ($entry['locked']) {
                $locked[] = $key;
                continue;
            }

            $def  = $entry['def'];
            $kind = self::valueKind($def);

            $prop = [
                'property'  => $key,
                'label'     => (string) ($def['label'] ?? $key),
                'type'      => $kind,
                'operators' => self::resolveOperators($entry),
            ];

            if ($options = self::optionValues($entry)) {
                $prop['options'] = $options;
            }
            if ($hint = self::valueHint($entry, $kind)) {
                $prop['value_hint'] = $hint;
            }
            // Integrations document properties via help and/or value_description.
            $note = trim(implode(' ', array_filter([
                !empty($def['help']) ? wp_strip_all_tags((string) $def['help']) : '',
                !empty($def['value_description']) ? wp_strip_all_tags((string) $def['value_description']) : '',
            ])));
            if ($note !== '') {
                $prop['note'] = $note;
            }

            $properties[] = $prop;
        }

        $out = [
            'entity'     => 'contacts',
            'usage'      => [
                'parameter' => 'advanced_filters',
                'tools'     => ['list-contacts', 'apply-segments-to-contacts (inside filter)'],
                'format'    => 'Array of OR groups; each group is an array of AND conditions {property, operator, value}. [[A,B],[C]] means (A AND B) OR C. A flat array of conditions is one group (all AND-ed). A single condition object is also accepted.',
                'combining' => 'advanced_filters composes with search, contact_type, created_after/before, sorting and paging — but NOT with the simple tags/lists/statuses/sms_statuses parameters (the engine applies one branch or the other). Express those as segment.* conditions instead.',
                'limits'    => sprintf('Up to %1$d OR groups, %2$d conditions per group, %3$d values per condition.', self::MAX_GROUPS, self::MAX_CONDITIONS_PER_GROUP, self::MAX_VALUES_PER_CONDITION),
                'example'   => [
                    'meaning' => 'subscribed leads carrying tag 12 or 15 created in the last 90 days, PLUS anyone who opened campaign 123',
                    'value'   => [
                        [
                            ['property' => 'segment.status', 'operator' => 'in', 'value' => ['subscribed']],
                            ['property' => 'segment.tags', 'operator' => 'in', 'value' => [12, 15]],
                            ['property' => 'subscriber.created_at', 'operator' => 'days_within', 'value' => 90],
                        ],
                        [
                            ['property' => 'activities.campaign_email_activity', 'operator' => 'open', 'value' => 123],
                        ],
                    ],
                ],
            ],
            'properties' => $properties,
        ];

        if ($locked) {
            // Registered but disabled on this site (Pro missing, integration
            // feature off). Passing one of these errors property_unavailable.
            $out['unavailable_properties'] = $locked;
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // Validation + translation to the engine wire format
    // ---------------------------------------------------------------------

    /**
     * Validate an agent payload and translate it into the groups-of-items
     * structure ContactsQuery::formatAdvancedFilters consumes (items carry
     * source: [provider, property], operator, value, and extra_value when
     * given). Returns {groups, warnings} or a WP_Error naming the first
     * offending condition with enough data to self-correct.
     *
     * Memoized per input for the request lifetime. validateUniversalFilter,
     * buildContactsQueryArgs and the warning surfacing in listContacts each
     * normalize the SAME payload, and a normalize with a segment.tags condition
     * measured 2 DB queries (resolving tag refs to ids) — so the memo saves 4
     * queries per filtered call. Threading one result through those three call
     * sites instead would mean changing three signatures for the same effect.
     *
     * @param mixed $raw
     * @return array{groups: array, warnings: string[]}|\WP_Error
     */
    public static function normalize($raw)
    {
        static $memo = [];
        $memoKey = md5(wp_json_encode($raw));
        if (isset($memo[$memoKey])) {
            return $memo[$memoKey];
        }

        return $memo[$memoKey] = self::doNormalize($raw);
    }

    private static function doNormalize($raw)
    {
        if (!is_array($raw)) {
            return self::structureError();
        }

        // Accept three shapes: full groups [[c,c],[c]], a flat condition list
        // [c,c] (one AND group), or a single condition object.
        if (isset($raw['property']) || isset($raw['source'])) {
            $raw = [[$raw]];
        } elseif (self::isConditionList($raw)) {
            $raw = [$raw];
        }

        if (count($raw) > self::MAX_GROUPS) {
            return self::limitError();
        }

        $groups   = [];
        $warnings = [];

        $groupNo = 0;
        foreach ($raw as $group) {
            $groupNo++;
            if (!is_array($group)) {
                return self::structureError();
            }
            if (count($group) > self::MAX_CONDITIONS_PER_GROUP) {
                return self::limitError();
            }

            $engineGroup = [];
            $conditionNo = 0;
            foreach ($group as $condition) {
                $conditionNo++;
                $item = self::normalizeCondition($condition, $groupNo, $conditionNo, $warnings);
                if (is_wp_error($item)) {
                    return $item;
                }
                $engineGroup[] = $item;
            }

            if ($engineGroup) {
                $groups[] = $engineGroup;
            }
        }

        if (!$groups) {
            return MCPHelper::error('invalid_param', __('advanced_filters contained no conditions. Provide at least one {property, operator, value} condition, or omit the parameter.', 'fluent-crm'));
        }

        return ['groups' => $groups, 'warnings' => $warnings];
    }

    /** True when every element looks like a condition object (flat list form). */
    private static function isConditionList(array $raw)
    {
        foreach ($raw as $element) {
            if (!is_array($element) || (!isset($element['property']) && !isset($element['source']))) {
                return false;
            }
        }
        return (bool) $raw;
    }

    /**
     * Validate one condition and build the engine item.
     *
     * @return array|\WP_Error
     */
    private static function normalizeCondition($condition, $groupNo, $conditionNo, array &$warnings)
    {
        if (!is_array($condition)) {
            return self::structureError();
        }

        // Lean format: property "provider.property". Wire format: source tuple.
        $key = null;
        if (isset($condition['property']) && is_string($condition['property']) && strpos($condition['property'], '.') !== false) {
            $key = $condition['property'];
        } elseif (isset($condition['source']) && is_array($condition['source']) && count($condition['source']) === 2) {
            $source = array_values($condition['source']);
            if (is_scalar($source[0]) && is_scalar($source[1])) {
                $key = $source[0] . '.' . $source[1];
            }
        }

        if ($key === null) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: 1: group number, 2: condition number */
                __('The condition in group %1$d position %2$d has no usable property. Each condition needs {property: "provider.property", operator, value} — call get-contact-filter-schema for the reference.', 'fluent-crm'),
                $groupNo,
                $conditionNo
            ), ['group' => $groupNo, 'condition' => $conditionNo]);
        }

        $catalog = self::catalog();
        if (!isset($catalog[$key])) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: 1: property name, 2: group number, 3: condition number */
                __('Unknown advanced filter property "%1$s" (group %2$d, condition %3$d). The engine would silently mismatch — refusing. Call get-contact-filter-schema for valid properties.', 'fluent-crm'),
                $key,
                $groupNo,
                $conditionNo
            ), ['property' => $key, 'valid_properties' => array_keys(array_filter($catalog, function ($e) {
                return !$e['locked'];
            }))]);
        }

        $entry = $catalog[$key];
        if ($entry['locked']) {
            return MCPHelper::error('property_unavailable', sprintf(
                /* translators: %s: property name */
                __('Advanced filter property "%s" is not available on this site — its provider marks it disabled (usually FluentCRM Pro or the integration\'s full version is missing). The engine would silently match nothing, so this call is rejected instead.', 'fluent-crm'),
                $key
            ), ['property' => $key]);
        }

        $operator = isset($condition['operator']) && is_scalar($condition['operator'])
            ? (string) $condition['operator']
            : '';
        $allowed = self::resolveOperators($entry);
        if ($operator === '' || ($allowed !== null && !in_array($operator, $allowed, true))) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: 1: operator, 2: property name, 3: allowed operators */
                __('Operator "%1$s" is not valid for "%2$s". Allowed: %3$s.', 'fluent-crm'),
                $operator,
                $key,
                $allowed === null ? __('(see admin UI)', 'fluent-crm') : implode(', ', $allowed)
            ), ['property' => $key, 'operator' => $operator, 'allowed_operators' => $allowed]);
        }

        $valueless = self::isValuelessOperator($operator, $entry['def']);

        $value = array_key_exists('value', $condition) ? $condition['value'] : null;
        if ($value === null && !$valueless) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: 1: property name, 2: operator */
                __('The condition on "%1$s" (operator %2$s) has no value.', 'fluent-crm'),
                $key,
                $operator
            ), ['property' => $key, 'operator' => $operator]);
        }

        if (!$valueless) {
            $value = self::normalizeValue($entry, $operator, $value, $warnings);
            if (is_wp_error($value)) {
                return $value;
            }
        } else {
            // The engine branches on the operator alone here; a stray value
            // would be ignored, so normalize it away.
            $value = '';
        }

        $item = [
            'source'   => [$entry['provider'], $entry['property']],
            'operator' => $operator,
            'value'    => $value,
        ];

        // extra_value is a per-property second input (e.g. event_tracking
        // value/count filters take the event key here). Passed through
        // verbatim; required when the property declares a selector for it.
        if (array_key_exists('extra_value', $condition) && is_scalar($condition['extra_value'])) {
            $item['extra_value'] = (string) $condition['extra_value'];
        }
        if (($entry['def']['type'] ?? '') === 'composite_optioned_compare' && empty($item['extra_value'])) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: %s: property name */
                __('"%s" also needs extra_value (the event key to compare against) — without it the engine silently skips the condition.', 'fluent-crm'),
                $key
            ), ['property' => $key]);
        }

        return $item;
    }

    /**
     * Coerce/validate the value for the property's kind, mirroring what the
     * engine actually executes (Subscriber::build*FilterQuery + filterParser).
     *
     * @return mixed|\WP_Error
     */
    private static function normalizeValue(array $entry, $operator, $value, array &$warnings)
    {
        $def  = $entry['def'];
        $key  = $entry['provider'] . '.' . $entry['property'];
        $kind = self::valueKind($def);

        if ($kind === 'numeric') {
            if (!is_numeric($value)) {
                return self::valueError($key, __('a number', 'fluent-crm'));
            }
            $num = (float) $value;
            if (!is_finite($num) || abs($num) > 1000000000000) {
                return self::valueError($key, __('a number within a sensible range', 'fluent-crm'));
            }
            return (string) $value;
        }

        if ($kind === 'date') {
            if ($operator === 'days_before' || $operator === 'days_within') {
                if (!is_numeric($value) || (int) $value < 0 || (int) $value > 36500) {
                    return self::valueError($key, __('a number of days between 0 and 36500', 'fluent-crm'));
                }
                return (string) (int) $value;
            }
            // strtotime() maps junk ("GMT", "T", a lone space) to the CURRENT
            // time, which would silently become "before now" = everyone.
            // Require a real date literal.
            if (!is_string($value)
                || !preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $value)
                || strtotime($value) === false) {
                return self::valueError($key, __('a date — YYYY-MM-DD or "YYYY-MM-DD HH:MM:SS", site timezone', 'fluent-crm'));
            }
            return $value;
        }

        // Tag/list conditions: the engine compares pivot object_id, so values
        // must be ids — but agents think in slugs/titles, so resolve those
        // here (same id|slug|title contract as the simple tags/lists params)
        // and fail closed on refs that match nothing.
        if ($entry['provider'] === 'segment' && in_array($entry['property'], ['tags', 'lists'], true)) {
            $kindName = $entry['property'] === 'tags' ? 'tag' : 'list';
            $items    = is_array($value) ? $value : [$value];
            if (count($items) > self::MAX_VALUES_PER_CONDITION) {
                return self::tooManyValuesError($key);
            }
            // One pass yields both the unknown refs and the ids — resolving
            // twice here cost two queries per condition for no gain.
            $resolved = MCPHelper::resolveSegmentRefs($items, $kindName);
            if ($resolved['unknown']) {
                return MCPHelper::error('invalid_param', sprintf(
                    /* translators: %s: property name */
                    __('"%s" contains references that do not match any existing tag/list by id, title, or slug.', 'fluent-crm'),
                    $key
                ), ['property' => $key, 'unknown_refs' => $resolved['unknown']]);
            }
            if (empty($resolved['ids'])) {
                return self::valueError($key, __('at least one tag/list reference', 'fluent-crm'));
            }
            return $resolved['ids'];
        }

        if ($kind === 'enum' || $kind === 'id') {
            // Single-value selections (ajax_selector campaigns/funnels/
            // sequences, contact_type, user_role): the engine reads one
            // scalar; unwrap a 1-element array.
            $isMultiple = !empty($def['is_multiple']);
            if (!$isMultiple || $kind === 'id') {
                if (is_array($value)) {
                    if (count($value) !== 1) {
                        return self::valueError($key, __('a single value', 'fluent-crm'));
                    }
                    $value = reset($value);
                }
                if (!is_scalar($value)) {
                    return self::valueError($key, __('a single value', 'fluent-crm'));
                }
                if ($kind === 'id') {
                    if (!is_numeric($value)) {
                        return self::valueError($key, __('a numeric id', 'fluent-crm'));
                    }
                    return (int) $value;
                }
                $value = (string) $value;
                return self::checkEnumValues($entry, [$value], $warnings) ?: $value;
            }

            // Equality operators read ONE scalar even on multi selections
            // (custom-field selects offer both = and in); an array would hit
            // the engine's sanitize_text_field(array) path and break.
            if ($operator === '=' || $operator === '!=') {
                if (is_array($value)) {
                    if (count($value) !== 1) {
                        return self::valueError($key, __('a single value with the = / != operators (use in / not_in for sets)', 'fluent-crm'));
                    }
                    $value = reset($value);
                }
                if (!is_scalar($value)) {
                    return self::valueError($key, __('a single value', 'fluent-crm'));
                }
                $value = (string) $value;
                return self::checkEnumValues($entry, [$value], $warnings) ?: $value;
            }

            // Multi selections: whereIn semantics.
            if (!is_array($value)) {
                $value = [$value];
            }
            if (count($value) > self::MAX_VALUES_PER_CONDITION) {
                return self::tooManyValuesError($key);
            }
            $flat = [];
            foreach ($value as $entryValue) {
                if (!is_scalar($entryValue)) {
                    return self::valueError($key, __('a value or array of values', 'fluent-crm'));
                }
                $flat[] = (string) $entryValue;
            }
            if (!$flat) {
                return self::valueError($key, __('a non-empty array of values', 'fluent-crm'));
            }
            return self::checkEnumValues($entry, $flat, $warnings) ?: $flat;
        }

        // Cascade selectors (product variations etc.) and unknown add-on
        // widget types: pass a scalar or an array of scalars through
        // unchanged — the integration handler owns the semantics (e.g.
        // FluentCart decodes 'parentId||childId' strings).
        if ($kind === 'cascade' || $kind === 'unknown') {
            if (is_scalar($value)) {
                return $kind === 'cascade' ? [(string) $value] : $value;
            }
            if (is_array($value)) {
                if (count($value) > self::MAX_VALUES_PER_CONDITION) {
                    return self::tooManyValuesError($key);
                }
                $flat = [];
                foreach ($value as $entryValue) {
                    if (!is_scalar($entryValue)) {
                        return self::valueError($key, __('a value or array of scalar values', 'fluent-crm'));
                    }
                    $flat[] = (string) $entryValue;
                }
                if (!$flat) {
                    return self::valueError($key, __('a non-empty array of values', 'fluent-crm'));
                }
                return $flat;
            }
            return self::valueError($key, __('a value or array of scalar values', 'fluent-crm'));
        }

        // text with in/not_in — whereIn semantics on the raw column. Only
        // reachable for properties resolveOperators() opens up (email); the
        // engine's whereIn branch handles the array natively.
        if ($operator === 'in' || $operator === 'not_in') {
            $items = is_array($value) ? array_values($value) : [$value];
            if (count($items) > self::MAX_VALUES_PER_CONDITION) {
                return self::tooManyValuesError($key);
            }
            $flat = [];
            foreach ($items as $item) {
                if (!is_scalar($item)) {
                    return self::valueError($key, __('an array of text values', 'fluent-crm'));
                }
                $item = trim((string) $item);
                if ($item !== '') {
                    $flat[] = $item;
                }
            }
            // Fail closed: whereIn([]) would be dropped by the engine's
            // `if ($searchTerm)` guard, leaving the group unconstrained.
            if (!$flat) {
                return self::valueError($key, __('a non-empty array of text values', 'fluent-crm'));
            }
            return array_values(array_unique($flat));
        }

        // text — engine LIKE/equality on one scalar; for contains/not_contains
        // an array is silently skipped by buildGeneralPropertiesFilterQuery
        // (it `continue`s), so reject it rather than let the condition vanish.
        if (!is_scalar($value)) {
            return self::valueError($key, __('a single text value', 'fluent-crm'));
        }
        return (string) $value;
    }

    /**
     * For enums whose option set is authoritative (contact statuses/types),
     * an off-catalog value is an error — the same fail-closed contract the
     * simple statuses filter enforces. For open catalogs (prefixes, custom
     * field options that may hold historical values) it's a warning: the
     * engine matches literally, so unintended values return zero rows.
     *
     * @return \WP_Error|null null when fine (possibly with warnings pushed)
     */
    private static function checkEnumValues(array $entry, array $values, array &$warnings)
    {
        $known = self::optionValues($entry);
        if (!$known) {
            return null;
        }

        $unknown = array_values(array_diff($values, $known));
        if (!$unknown) {
            return null;
        }

        $key    = $entry['provider'] . '.' . $entry['property'];
        $strict = in_array($entry['property'], ['status', 'contact_type'], true) && $entry['provider'] === 'segment';

        if ($strict) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: %s: property name */
                __('"%s" contains values outside the registered enum.', 'fluent-crm'),
                $key
            ), ['property' => $key, 'unknown_values' => $unknown, 'allowed_values' => $known]);
        }

        $warnings[] = sprintf(
            /* translators: 1: values, 2: property name, 3: known options */
            __('Values "%1$s" on %2$s are not among the documented options (%3$s); they are matched literally, so typos return zero rows.', 'fluent-crm'),
            implode(', ', $unknown),
            $key,
            implode(', ', $known)
        );
        return null;
    }

    // ---------------------------------------------------------------------
    // Per-property metadata (operators / kinds / options / hints)
    // ---------------------------------------------------------------------

    /**
     * The operators a property accepts — the admin UI's resolution rules
     * (custom map first, then by widget type), which the engine executes.
     * Returns null for unknown add-on widget types: those are passed through
     * with only structural checks rather than guessed at.
     *
     * @return string[]|null
     */
    public static function resolveOperators(array $entry)
    {
        $def      = $entry['def'];
        $provider = $entry['provider'];

        if (!empty($def['custom_operators']) && is_array($def['custom_operators'])) {
            return array_map('strval', array_keys($def['custom_operators']));
        }

        $type = $def['type'] ?? 'text';

        switch ($type) {
            case 'extended_text':
                $ops = ['=', '!=', 'contains', 'not_contains', 'startsWith', 'endsWith'];
                break;
            case 'numeric':
            case 'times_numeric':
                $ops = ['>', '<', '=', '!='];
                break;
            case 'single_assert_option':
                $ops = ['='];
                break;
            case 'straight_assert_option':
                $ops = ['=', '!='];
                break;
            case 'dates':
                $ops = ['before', 'after', 'date_equal', 'days_before', 'days_within'];
                if ($provider === 'activities' && in_array($def['value'] ?? '', ['email_opened', 'email_link_clicked'], true)) {
                    $ops[] = 'never';
                }
                break;
            case 'selections':
                if (($def['option_key'] ?? '') === 'countries') {
                    $ops = ['in', 'not_in'];
                } elseif (!empty($def['is_only_in'])) {
                    $ops = ['in'];
                } elseif ($provider === 'custom_fields') {
                    $ops = ['in', 'not_in', '=', '!='];
                } elseif (!empty($def['is_multiple']) && $provider !== 'subscriber' && empty($def['is_singular_value'])) {
                    // Relation-backed multi selects (tags, lists, purchased items).
                    $ops = ['in', 'not_in', 'in_all', 'not_in_all'];
                } else {
                    $ops = ['in', 'not_in'];
                }
                break;
            case 'nullable_text':
                $ops = ['=', '!=', 'contains', 'not_contains', 'is_null', 'not_null'];
                break;
            case 'text':
                $ops = ['=', '!=', 'contains', 'not_contains'];
                break;
            default:
                // Unknown widget type from an add-on — no operator claim.
                return null;
        }

        if ($provider === 'custom_fields' || !empty($def['is_nullable'])) {
            $ops[] = 'is_null';
            $ops[] = 'not_null';
        }

        // Set membership on email. The admin UI renders it as a single-value
        // text input and so never offers in/not_in, but the engine has always
        // handled both for any subscriber column
        // (Subscriber::buildGeneralPropertiesFilterQuery -> whereIn). Agents
        // routinely arrive with a list of addresses; without this they burn
        // one OR group per address against a MAX_GROUPS ceiling of 5. For a
        // plain "contacts for these emails" lookup prefer the `emails`
        // parameter on list-contacts — it composes with tags/lists/statuses,
        // which the advanced branch does not.
        if ($provider === 'subscriber' && ($def['value'] ?? '') === 'email') {
            $ops[] = 'in';
            $ops[] = 'not_in';
        }

        return array_values(array_unique($ops));
    }

    /**
     * Agent-facing value kind. 'id' = a single numeric id (ajax selectors
     * over campaigns/funnels/sequences); 'id_list' = arrays of ids where the
     * engine compares relation object_ids.
     */
    private static function valueKind(array $def)
    {
        $type = $def['type'] ?? 'text';

        if ($type === 'dates') {
            return 'date';
        }
        if ($type === 'numeric' || $type === 'times_numeric') {
            return 'numeric';
        }
        if ($type === 'composite_optioned_compare') {
            return 'composite';
        }
        if ($type === 'cascade_selections') {
            return 'cascade';
        }
        if ($type === 'selections' || $type === 'single_assert_option' || $type === 'straight_assert_option') {
            // Single-pick ajax selectors take one id, not an option string.
            if (($def['component'] ?? '') === 'ajax_selector' && empty($def['is_multiple'])) {
                return 'id';
            }
            return 'enum';
        }
        if (in_array($type, ['text', 'nullable_text', 'extended_text'], true)) {
            return 'text';
        }
        return 'unknown';
    }

    /**
     * Resolve a property's closed option list server-side where the registry
     * names one (the admin UI fetches these by option_key at runtime).
     * Returns [] when the set is open/too large to inline (countries, tags,
     * campaigns...) — valueHint() covers those.
     *
     * @return string[]
     */
    private static function optionValues(array $entry)
    {
        $def = $entry['def'];

        if (!empty($def['options']) && is_array($def['options'])) {
            return array_map('strval', array_keys($def['options']));
        }

        switch ($def['option_key'] ?? '') {
            case 'statuses':
                return array_values(fluentcrm_subscriber_statuses());
            case 'contact_types':
                return array_keys(fluentcrm_contact_types());
            case 'user_roles_options':
                return array_keys((array) wp_roles()->roles);
        }

        return [];
    }

    /** Format guidance for kinds whose values can't be enumerated inline. */
    private static function valueHint(array $entry, $kind)
    {
        $def = $entry['def'];
        $key = $entry['provider'] . '.' . $entry['property'];

        if ($key === 'subscriber.search') {
            return 'Full-text across name, email and address fields — same as the list-contacts search box.';
        }
        if ($key === 'subscriber.country') {
            return 'Array of ISO 3166-1 alpha-2 codes as stored on contacts (e.g. ["US", "NL"]).';
        }
        if ($key === 'subscriber.email') {
            return 'One address for =/!=, a substring for contains, or an array of exact addresses for in/not_in (matching is case-insensitive). To simply fetch the contacts for a set of addresses, prefer the `emails` parameter on list-contacts — it composes with tags/lists/statuses, which advanced_filters does not.';
        }
        if ($entry['provider'] === 'segment' && in_array($entry['property'], ['tags', 'lists'], true)) {
            return 'Array of ids, slugs, or titles (resolved server-side, unknown refs are rejected). in_all = carries ALL of them; is_null/not_null = has none/any at all.';
        }
        if ($kind === 'id') {
            $optionKey = $def['option_key'] ?? '';
            $source = [
                'campaigns'       => 'list-campaigns',
                'funnels'         => 'list-automations',
                'email_sequences' => 'list-sequences',
                'companies'       => 'the companies UI',
            ];
            return 'One numeric id' . (isset($source[$optionKey]) ? ' — find it via ' . $source[$optionKey] : '') . '.';
        }
        if ($kind === 'date') {
            return 'before/after/date_equal take YYYY-MM-DD (site timezone; date_equal matches within that date). days_before = more than N days ago, days_within = within the last N days (both take a plain number).';
        }
        if ($kind === 'composite') {
            return 'value = the compare value; extra_value = the event key it applies to (required).';
        }
        if ($kind === 'cascade') {
            return 'Array of "parentId||childId" strings as the cascade selector emits — e.g. "123||456" = product 123, variation 456. Plain ids without "||" match nothing.';
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Errors
    // ---------------------------------------------------------------------

    private static function structureError()
    {
        return MCPHelper::error('invalid_param', __('advanced_filters must be an array of OR groups, each an array of AND conditions {property, operator, value}. A flat array of conditions is also accepted and treated as one group.', 'fluent-crm'), [
            'hint' => 'Example: [[{"property":"segment.tags","operator":"in","value":[12]}]] — call get-contact-filter-schema for the full reference.',
        ]);
    }

    private static function limitError()
    {
        return MCPHelper::error('invalid_param', sprintf(
            /* translators: 1: max groups, 2: max conditions per group */
            __('advanced_filters allows at most %1$d OR groups with %2$d conditions each.', 'fluent-crm'),
            self::MAX_GROUPS,
            self::MAX_CONDITIONS_PER_GROUP
        ), ['max_groups' => self::MAX_GROUPS, 'max_conditions_per_group' => self::MAX_CONDITIONS_PER_GROUP]);
    }

    private static function tooManyValuesError($property)
    {
        return MCPHelper::error('invalid_param', sprintf(
            /* translators: 1: property name, 2: cap */
            __('The condition on "%1$s" carries more than %2$d values. Split it across calls — trimming it silently would change what matches.', 'fluent-crm'),
            $property,
            self::MAX_VALUES_PER_CONDITION
        ), ['property' => $property, 'max_values' => self::MAX_VALUES_PER_CONDITION]);
    }

    private static function valueError($property, $expected)
    {
        return MCPHelper::error('invalid_param', sprintf(
            /* translators: 1: property name, 2: what the value should be */
            __('The value for "%1$s" must be %2$s.', 'fluent-crm'),
            $property,
            $expected
        ), ['property' => $property]);
    }
}
