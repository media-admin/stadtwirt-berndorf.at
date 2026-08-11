<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Modules\MCP\Helpers\AdvancedFilters;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Services\ContactsQuery;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

/**
 * Contact-centric MCP tools.
 *
 * Read tools (Phase 2): listContacts, getContact.
 * Write tools (Phase 3): upsertContact, bulkUpsertContacts, deleteContact,
 * applySegmentsToContacts, addContactNote.
 *
 * Each method delegates to existing FluentCRM services (ContactsQuery,
 * Subscriber model, Helper::deleteContacts, etc.) — no business-logic
 * duplication — and shapes the result through MCPHelper formatters.
 */
class ContactTools
{
    /**
     * Ceiling on one bulk row's tags[] / lists[]. A contact carrying more
     * segments than this is a malformed row, not a real one.
     */
    const MAX_SEGMENT_REFS_PER_ROW = 100;

    /**
     * Ceiling on DISTINCT tag+list references across a whole bulk call. Bulk
     * defaults auto_create to true, so without this a single request could
     * mint a segment per distinct value of a mis-mapped column.
     *
     * Matches MCPHelper::MAX_SEGMENT_REFS. Deliberately well below the
     * 500-contact cap: a real import draws its tags from a small controlled
     * vocabulary, so needing more than this many DISTINCT segments in one
     * batch is the signature of a mapping mistake rather than of intent.
     */
    const MAX_BULK_SEGMENT_REFS = 100;

    // -----------------------------------------------------------------
    // Read: list-contacts
    // -----------------------------------------------------------------

    public static function listContacts($params)
    {
        $params = (array) $params;

        // Reject up front if the caller passed an unsupported advanced_filters
        // shape — round-2 review #3.
        $validation = MCPHelper::validateUniversalFilter($params);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $pagination = MCPHelper::paginationFromInput($params);

        $args = MCPHelper::buildContactsQueryArgs($params);
        $args['with'] = ['tags', 'lists'];

        if (!empty($params['include_custom_fields'])) {
            $args['custom_fields'] = true;
        }

        $cq = new ContactsQuery($args);
        MCPHelper::applyDateFilters($cq, $params);
        $paginated = $cq->paginate();

        $out = MCPHelper::formatContactList($paginated, !empty($params['include_custom_fields']));

        // Which of the requested addresses have no contact record at all.
        // Resolved with its own indexed lookup rather than by diffing the
        // current page, so the answer is the same on page 1 and page 3. Note
        // the deliberate semantics: this reports addresses that do not exist
        // in the CRM, NOT ones excluded by the other filters — a contact that
        // exists but lacks the requested tag is filtered out, not missing.
        if (!empty($params['emails'])) {
            $requested = MCPHelper::normalizeEmailList($params['emails']);
            $existing  = array_map('strtolower', Subscriber::whereIn('email', $requested)->pluck('email')->toArray());
            $missing   = array_values(array_diff($requested, $existing));
            if ($missing) {
                $out['not_found'] = $missing;
            }
        }

        // Non-fatal advisories from advanced_filters normalization (values
        // outside a documented option set, etc.). normalize() is memoized,
        // so this re-read costs nothing.
        if (!empty($params['advanced_filters'])) {
            $normalized = AdvancedFilters::normalize($params['advanced_filters']);
            if (!is_wp_error($normalized) && !empty($normalized['warnings'])) {
                $out['warnings'] = $normalized['warnings'];
            }
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // Read: get-contact-filter-schema
    // -----------------------------------------------------------------

    /**
     * Serve the advanced_filters reference on demand — progressive
     * disclosure so the (large) filter catalog never bloats the input
     * schema of every list tool. See AdvancedFilters::schema().
     */
    public static function getFilterSchema($params = [])
    {
        return AdvancedFilters::schema();
    }

    // -----------------------------------------------------------------
    // Read: get-contact
    // -----------------------------------------------------------------

    public static function getContact($params)
    {
        $params = (array) $params;

        $defaultIncludes = ['notes', 'email_history', 'automations'];
        $allowedIncludes = ['notes', 'email_history', 'automations', 'activity', 'purchase_history', 'support_tickets', 'info_widgets'];
        // array_key_exists (not truthiness): omitted => defaults, [] =>
        // metadata only, explicit list => exactly those sections (TRC-005).
        if (array_key_exists('include', $params) && is_array($params['include'])) {
            $include = array_values(array_intersect($params['include'], $allowedIncludes));
        } else {
            $include = $defaultIncludes;
        }

        // Token-bounding controls for the rich collections (OPT-002).
        // Resolve the default BEFORE the enum test: the ternary form read
        // $params['body_format'] on the true branch even when the key was
        // absent, emitting an "Undefined array key" warning on every default
        // call. With display_errors on that notice lands in the response body
        // ahead of the JSON envelope and corrupts the tool result.
        $bodyFormat = $params['body_format'] ?? 'text';
        if (!in_array($bodyFormat, ['text', 'html', 'both'], true)) {
            $bodyFormat = 'text';
        }
        $profileOpts = [
            'include'             => $include,
            'body_format'         => $bodyFormat,
            'note_body_max_chars' => max(80, min(isset($params['note_body_max_chars']) ? (int) $params['note_body_max_chars'] : 800, 20000)),
            'notes_limit'         => isset($params['notes_limit']) ? (int) $params['notes_limit'] : 10,
            'automations_limit'   => isset($params['automations_limit']) ? (int) $params['automations_limit'] : 25,
            'email_history_limit' => max(1, min(isset($params['email_history_limit']) ? (int) $params['email_history_limit'] : 10, 50)),
        ];

        $contactId = isset($params['contact_id']) ? (int) $params['contact_id'] : 0;
        $email     = isset($params['email']) ? sanitize_email($params['email']) : '';

        $with = ['tags', 'lists'];

        $subscriber = null;
        if ($contactId) {
            $subscriber = Subscriber::with($with)->find($contactId);
        } elseif ($email) {
            $subscriber = Subscriber::with($with)->where('email', $email)->first();
        }

        if (!$subscriber) {
            if (!$contactId && !$email) {
                return MCPHelper::error('invalid_param', __('Provide contact_id or email', 'fluent-crm'));
            }
            return MCPHelper::error('not_found', __('Contact not found', 'fluent-crm'), array_filter([
                'contact_id' => $contactId ?: null,
                'email'      => $email ?: null,
            ]));
        }

        $data = MCPHelper::formatContactForMCP($subscriber, $profileOpts);

        // Defaults already inlined by formatContactForMCP — fill the optional ones.
        if (in_array('activity', $include, true)) {
            $data['activity'] = self::buildActivityTimeline($subscriber);
        }

        if (in_array('purchase_history', $include, true)) {
            $data['purchase_history'] = self::buildPurchaseHistory($subscriber);
        }

        if (in_array('support_tickets', $include, true)) {
            $data['support_tickets'] = self::buildSupportTickets($subscriber);
        }

        if (in_array('info_widgets', $include, true)) {
            $data['info_widgets'] = self::buildInfoWidgets($subscriber);
        }

        // Status-related context — surfaced inline so the agent can see why a
        // contact is unsubscribed without an extra call.
        if (in_array($subscriber->status, ['unsubscribed', 'bounced', 'complained', 'spammed'], true)) {
            $data['unsubscribe_reason'] = method_exists($subscriber, 'unsubscribeReason')
                ? $subscriber->unsubscribeReason()
                : null;
        }

        return $data;
    }

    /**
     * Activity timeline = tracked events. Wrapped in an availability marker
     * so an empty result is distinguishable from "the feature is off" —
     * a silent [] reads as "this contact never did anything", which is
     * misleading when event tracking simply isn't enabled (MCP feedback
     * 2026-07, P1 #2 class of issue).
     *
     * The fc_event_tracking table is created by the free plugin's migrations
     * but may not exist on legacy installs that never ran the migration.
     * Probe with SHOW TABLES so we never trigger wpdb's print_error (which
     * leaks HTML into the response body before the JSON envelope, even when
     * the exception is caught).
     */
    private static function buildActivityTimeline($subscriber)
    {
        if (!Helper::isExperimentalEnabled('event_tracking')) {
            return [
                'available' => false,
                'reason'    => 'event_tracking_disabled',
                'note'      => 'Enable Event Tracking under FluentCRM Settings > Experimental Features to record contact activity.',
                'events'    => [],
            ];
        }

        global $wpdb;
        $tableName = $wpdb->prefix . 'fc_event_tracking';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) === $tableName;
        if (!$exists) {
            return [
                'available' => false,
                'reason'    => 'event_tracking_table_missing',
                'events'    => [],
            ];
        }

        try {
            $events = $subscriber->trackingEvents()
                ->orderBy('id', 'DESC')
                ->limit(50)
                ->get();
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'reason'    => 'event_tracking_query_failed',
                'events'    => [],
            ];
        }

        $out = [];
        foreach ($events as $event) {
            $out[] = [
                'id'         => (int) $event->id,
                'event_key'  => $event->event_key,
                'title'      => $event->title,
                'value'      => $event->value,
                'provider'   => $event->provider ?? null,
                'counter'    => isset($event->counter) ? (int) $event->counter : null,
                'created_at' => MCPHelper::toIso8601($event->created_at),
            ];
        }

        return [
            'available' => true,
            'events'    => $out,
        ];
    }

    /**
     * Orders across every purchase-history integration active on this
     * install — the same provider registry + per-provider filter chain the
     * contact-profile "Purchase History" tab uses (FluentCart, WooCommerce,
     * EDD, Paymattic, PMPro, plus Pro's SureCart/Voxel). The previous
     * implementation asked `fluentcrm_commerce_provider` (Pro Woo/EDD
     * commerce sync only, summary rows not orders), so FluentCart installs
     * always got a silent [] — MCP feedback 2026-07, P1 #2.
     *
     * When no provider plugin is active the response says so explicitly:
     * an empty array here reads as "never bought anything", which is
     * actively misleading when the store lives on another install.
     */
    private static function buildPurchaseHistory($subscriber)
    {
        $providers = Helper::getPurchaseHistoryProviders();

        if (!$providers) {
            return [
                'available' => false,
                'reason'    => 'no_purchase_history_provider_active',
                'note'      => 'No commerce plugin with a FluentCRM purchase-history integration is active on this install. If orders live in a separate store install, query that store\'s own API/MCP.',
                'providers' => [],
            ];
        }

        $out = [];
        foreach ($providers as $key => $provider) {
            $key = sanitize_key($key);
            if (!$key) {
                continue;
            }

            $data = apply_filters('fluent_crm/purchase_history_' . $key, [
                'data'  => [],
                'total' => 0,
            ], $subscriber);

            // Producers return rows under 'data'; the REST controller's
            // legacy seed used 'orders' — accept either for third-party
            // providers built against that shape.
            $orders = Arr::get($data, 'data', []) ?: Arr::get($data, 'orders', []);

            $out[$key] = [
                'name'   => Arr::get((array) $provider, 'name', $key),
                'total'  => (int) Arr::get($data, 'total', 0),
                'orders' => self::normalizeIntegrationRows($orders),
            ];
        }

        return [
            'available' => true,
            'providers' => $out,
        ];
    }

    /**
     * Tickets across every helpdesk integration, via the same provider
     * registry the contact-profile "Support Tickets" tab uses. The previous
     * implementation applied `fluentcrm_get_support_tickets`, a filter no
     * plugin has ever hooked (Fluent Support hooks the per-provider
     * `fluentcrm-get_support_tickets_{provider}`) — MCP feedback 2026-07,
     * P1 #2.
     */
    private static function buildSupportTickets($subscriber)
    {
        $providers = apply_filters('fluentcrm-support_tickets_providers', []);

        if (!$providers || !is_array($providers)) {
            return [
                'available' => false,
                'reason'    => 'no_support_ticket_provider_active',
                'note'      => 'No helpdesk plugin with a FluentCRM ticket integration (e.g. Fluent Support) is active on this install.',
                'providers' => [],
            ];
        }

        $out = [];
        foreach ($providers as $key => $provider) {
            $key = sanitize_key($key);
            if (!$key) {
                continue;
            }

            $data = apply_filters('fluentcrm-get_support_tickets_' . $key, [
                'data'  => [],
                'total' => 0,
            ], $subscriber);

            $out[$key] = [
                'name'    => Arr::get((array) $provider, 'name', $key),
                'total'   => (int) Arr::get($data, 'total', 0),
                'tickets' => self::normalizeIntegrationRows(Arr::get($data, 'data', [])),
            ];
        }

        return [
            'available' => true,
            'providers' => $out,
        ];
    }

    /**
     * Sidebar widgets integrations push onto the contact profile (LMS
     * enrollments, membership levels, form submissions, event tracking,
     * FluentCart summary, etc.). Canonical filter is
     * `fluent_crm/subscriber_info_widgets` — the previous implementation
     * applied `fluent_crm/contact_info_widgets`, which nothing hooks.
     *
     * Widget content arrives as admin HTML; it is flattened to plain text
     * for the agent. An empty list is ambiguous by design of the filter
     * (no producers vs. no data for this contact), so the note says so.
     */
    private static function buildInfoWidgets($subscriber)
    {
        $widgets = apply_filters('fluent_crm/subscriber_info_widgets', [], $subscriber);

        $out = [];
        if (is_array($widgets)) {
            foreach ($widgets as $key => $widget) {
                $widget = (array) $widget;
                $entry = array_filter([
                    'key'     => is_string($key) ? $key : null,
                    'title'   => self::htmlToText((string) Arr::get($widget, 'title', '')),
                    'content' => self::htmlToText((string) Arr::get($widget, 'content', '')),
                ]);
                if ($entry) {
                    $out[] = $entry;
                }
            }
        }

        $result = ['widgets' => $out];
        if (!$out) {
            $result['note'] = 'No integration pushed widget data for this contact — either no widget-producing plugin is active, or none had data to report.';
        }
        return $result;
    }

    /**
     * Flatten the admin-list HTML rows the integration filters return
     * (badge <span>s, action-link <svg>s) into plain-text cells an agent
     * can read. Pure-UI cells (action buttons) are dropped entirely.
     */
    private static function normalizeIntegrationRows($rows, $limit = 10)
    {
        if (is_object($rows) && method_exists($rows, 'toArray')) {
            $rows = $rows->toArray();
        }
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach (array_slice(array_values($rows), 0, $limit) as $row) {
            $row = (array) $row;
            $item = [];
            foreach ($row as $key => $value) {
                $key = sanitize_key($key);
                if (in_array($key, ['action', 'actions'], true)) {
                    continue;
                }
                if (!is_scalar($value) && $value !== null) {
                    continue;
                }
                $item[$key] = self::htmlToText((string) $value);
            }
            if ($item) {
                $normalized[] = $item;
            }
        }
        return $normalized;
    }

    /**
     * Strip markup but keep adjacent values readable — a space is injected
     * at tag boundaries first so "<span>#12</span><span>May 5</span>"
     * becomes "#12 May 5" instead of "#12May 5".
     */
    private static function htmlToText($html)
    {
        if ($html === '') {
            return '';
        }
        $text = wp_strip_all_tags(str_replace('<', ' <', $html));
        // Currency signs etc. arrive as entities ("&#36;100.00") — decode so
        // the agent reads "$100.00".
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * The explicit top-level contact columns the MCP write contract accepts.
     * Everything else in the Subscriber model's fillable list — user_id,
     * company_id, ip, latitude/longitude, sms_status, whatsapp_status,
     * life_time_value, total_points, created_at/updated_at — is NOT an MCP
     * input and must be dropped, never overposted (SEC-001). Single and bulk
     * upsert share this list so their write contract can never diverge.
     *
     * @return string[]
     */
    private static function contactWritePassthru()
    {
        return ['first_name', 'last_name', 'prefix', 'phone', 'status', 'contact_type', 'date_of_birth', 'timezone', 'source'];
    }

    // -----------------------------------------------------------------
    // Write: upsert-contact
    // -----------------------------------------------------------------

    public static function upsertContact($params)
    {
        $params = (array) $params;

        $contactId = isset($params['contact_id']) ? (int) $params['contact_id'] : 0;
        $email     = isset($params['email']) ? sanitize_email($params['email']) : '';
        $newEmail  = isset($params['new_email']) ? sanitize_email($params['new_email']) : '';

        if (!$contactId && !$email) {
            return MCPHelper::error('invalid_param', __('Provide contact_id or email', 'fluent-crm'));
        }

        $existing = null;
        if ($contactId) {
            $existing = Subscriber::find($contactId);
            if (!$existing) {
                return MCPHelper::error('not_found', __('Contact not found', 'fluent-crm'), ['contact_id' => $contactId]);
            }
            // Lookup-by-id with email mismatch is fine — id wins.
            $email = $existing->email;
        } else {
            $existing = Subscriber::where('email', $email)->first();
        }

        $ifExists = $params['if_exists'] ?? 'merge';
        if ($existing && $ifExists === 'skip') {
            return [
                'ok'      => true,
                'action'  => 'skipped',
                'contact' => MCPHelper::formatContactForMCP($existing, ['include' => ['notes', 'email_history', 'automations']]),
                'changes' => null,
            ];
        }
        if ($existing && $ifExists === 'error') {
            return MCPHelper::error('contact_exists', __('A contact with this email already exists', 'fluent-crm'), [
                'id' => (int) $existing->id,
            ]);
        }

        // Re-check the escalating capability if the agent asked us to create
        // missing tags/lists — defense in depth, even though the
        // permission_callback already enforced the base cap.
        $autoCreateTags  = !empty($params['auto_create_tags']);
        $autoCreateLists = !empty($params['auto_create_lists']);
        if (($autoCreateTags || $autoCreateLists)
            && !\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contact_cats')) {
            return MCPHelper::error('forbidden', __('Creating new tags/lists requires fcrm_manage_contact_cats', 'fluent-crm'));
        }

        // =================================================================
        // VALIDATION PHASE — every check that can reject the request runs
        // BEFORE any write. Nothing above this point mutates data, and nothing
        // below the WRITE PHASE marker runs until all of these pass. This
        // guarantees a late failure can never leave an orphaned tag or a
        // half-renamed contact behind (TRC-001).
        // =================================================================

        // Invalid status is a hard error, not a silent drop. A single upsert
        // that returned success while ignoring the requested status would make
        // the agent believe the state changed (TRC-002). Bulk keeps a per-row
        // warning instead because it is a partial-batch API.
        if (array_key_exists('status', $params) && $params['status'] !== null && $params['status'] !== ''
            && !in_array($params['status'], fluentcrm_subscriber_statuses(), true)) {
            return MCPHelper::error('invalid_param', __('status is not a recognized contact status.', 'fluent-crm'), [
                'status'  => $params['status'],
                'allowed' => array_values(fluentcrm_subscriber_statuses()),
            ]);
        }

        // Validate custom-field slugs against the registered schema up-front.
        // Unknown keys would otherwise be silently dropped (operator-test
        // report 2026-05-07 #6) — fail closed so the agent can correct the
        // slug or read the schema. Keep the known subset for the payload.
        $customValues = null;
        if (!empty($params['custom_fields']) && is_array($params['custom_fields'])) {
            $diff = MCPHelper::diffCustomFields($params['custom_fields']);
            if (!empty($diff['unknown'])) {
                return MCPHelper::error('invalid_param', __('custom_fields contains slugs not in the contact custom-field schema. Refusing — silent-dropping makes the agent think the value persisted.', 'fluent-crm'), [
                    'unknown_custom_field_slugs' => $diff['unknown'],
                    'allowed_custom_field_slugs' => MCPHelper::knownContactCustomFieldSlugs(),
                    'tip' => 'Call get-crm-context and read enums.custom_fields_schema (or call options for the live registry) before retrying.',
                ]);
            }
            $customValues = $diff['known'];
        }

        // Reject unrecognized address keys before any write (TRC-006). A typo
        // like address.zip would otherwise be silently dropped by
        // applyAddressShape. Present-but-non-object also hard-errors.
        $addressError = MCPHelper::validateClosedShape($params, 'address', self::addressAllowedKeys());
        if (is_wp_error($addressError)) {
            return $addressError;
        }

        // Email-rename target must be free. Read-only pre-check; the rename
        // itself happens in the WRITE PHASE. Ruling this out here means a clash
        // cannot strand auto-created tags.
        if ($existing && $newEmail && $newEmail !== $existing->email) {
            $clash = Subscriber::where('email', $newEmail)->where('id', '!=', $existing->id)->first();
            if ($clash) {
                return MCPHelper::error('contact_exists', __('Another contact already uses the new_email — refusing to merge silently. Resolve manually or pick a different new_email.', 'fluent-crm'), [
                    'new_email'   => $newEmail,
                    'conflict_id' => (int) $clash->id,
                    'subject_id'  => (int) $existing->id,
                ]);
            }
        }

        // =================================================================
        // WRITE PHASE — every mutation lives below this marker.
        // =================================================================

        // Capture the pre-rename / pre-update snapshot fields BEFORE any
        // mutation. The rename block below sets $existing->email to the new
        // value, so reading $existing->email after that point would return the
        // new email — operator-test report 2026-05-07 #9. The full snapshot
        // also feeds diffFields() so fields_updated correctly reports 'email'.
        $previousStatus   = $existing ? $existing->status : null;
        $previousEmail    = $existing ? $existing->email : null;
        $previousSnapshot = $existing ? self::snapshotCompareFields($existing) : null;

        // One unit of work. Previously the rename and any auto-created
        // tags/lists committed before the main upsert ran, so a failure after
        // that point returned an error to a caller whose contact had already
        // been renamed to an email the response said was never applied, and
        // left orphan segments behind (review PR #2025).
        fluentCrmDb()->beginTransaction();

        // Side effects that are not database writes are collected here and
        // fired only after the commit succeeds, so a rollback cannot leave a
        // listener — or a real opt-in email — acting on state that no longer
        // exists. Residual worth knowing: attachTags()/attachLists() fire
        // core's own contact_added_to_* hooks from inside the model, so those
        // still run pre-commit; deferring them would mean changing core.
        $deferredEffects = [];

        try {
            // Resolve add/remove segment payloads. Auto-create (a write) runs
            // here only after all validation above has passed.
            $addTags     = MCPHelper::resolveTagIds($params['add_tags'] ?? [], $autoCreateTags);
            $removeTags  = MCPHelper::resolveTagIds($params['remove_tags'] ?? [], false);
            $addLists    = MCPHelper::resolveListIds($params['add_lists'] ?? [], $autoCreateLists);
            $removeLists = MCPHelper::resolveListIds($params['remove_lists'] ?? [], false);

            // Email rename: rename in-place on the existing row BEFORE
            // delegating to createOrUpdate, which looks up by email — passing
            // it the new_email would not find a row and would create a new
            // contact (review B1 round 3). The clash was already ruled out in
            // the validation phase.
            if ($existing && $newEmail && $newEmail !== $existing->email) {
                $oldEmail = $existing->email;
                $existing->email = $newEmail;
                $existing->save();

                $renamed = $existing;
                $deferredEffects[] = function () use ($renamed, $oldEmail) {
                    do_action('fluent_crm/contact_email_changed', $renamed, $oldEmail);
                };
            }

            // Build the upsert payload from an explicit allowlist — never the
            // raw params — so model-fillable-but-not-MCP fields cannot be
            // overposted (SEC-001). Lookup email is the post-rename value.
            $payload = [
                'email' => $existing && $newEmail ? $newEmail : ($email ?: ($existing->email ?? null)),
            ];

            foreach (self::contactWritePassthru() as $field) {
                if (array_key_exists($field, $params) && $params[$field] !== null && $params[$field] !== '') {
                    $payload[$field] = $params[$field];
                }
            }

            self::applyAddressShape($payload, $params['address'] ?? null);

            if ($customValues !== null) {
                $payload['custom_values'] = $customValues;
            }

            // Only stamp source='mcp' on creation. On update, omit the field
            // entirely so the model preserves whatever signup source the
            // contact already has ("web", "checkout", "import", etc.). The
            // agent can still pass an explicit `source` to override this.
            if (!$existing && (!isset($payload['source']) || $payload['source'] === '')) {
                $payload['source'] = 'mcp';
            } elseif ($existing && (!isset($payload['source']) || $payload['source'] === '')) {
                unset($payload['source']);
            }

            // Force the status write only when the caller explicitly passed a
            // (valid) status — an upsert that just edits fields must not count
            // as consent to overwrite a suppressed contact. An explicit status
            // IS the caller's decision and forced updates are honored
            // unconditionally. (Status was validated in the VALIDATION PHASE;
            // an invalid value cannot reach here.)
            $forceUpdate = !empty($payload['status']);
            $contact = FluentCrmApi('contacts')->createOrUpdate($payload, $forceUpdate, false);

            if (!$contact) {
                // Discards the rename and any auto-created segments above, so
                // the reported failure is the whole truth.
                fluentCrmDb()->rollBack();
                return MCPHelper::error('failed', __('Could not create or update the contact', 'fluent-crm'));
            }

            $action = !empty($contact->wasRecentlyCreated) ? 'created' : 'updated';

            // Apply delta segment changes.
            $tagsAdded = [];
            $tagsRemoved = [];
            $listsAdded = [];
            $listsRemoved = [];

            if (!empty($addTags['ids'])) {
                $contact->attachTags($addTags['ids']);
                foreach ($addTags['ids'] as $id) {
                    $tagsAdded[] = ['id' => (int) $id];
                }
            }
            if (!empty($removeTags['ids'])) {
                $contact->detachTags($removeTags['ids']);
                foreach ($removeTags['ids'] as $id) {
                    $tagsRemoved[] = ['id' => (int) $id];
                }
            }
            if (!empty($addLists['ids'])) {
                $contact->attachLists($addLists['ids']);
                foreach ($addLists['ids'] as $id) {
                    $listsAdded[] = ['id' => (int) $id];
                }
            }
            if (!empty($removeLists['ids'])) {
                $contact->detachLists($removeLists['ids']);
                foreach ($removeLists['ids'] as $id) {
                    $listsRemoved[] = ['id' => (int) $id];
                }
            }

            // Status-change reason: drop a system-style note for audit.
            if (!empty($params['status_change_reason']) && $previousStatus && $previousStatus !== $contact->status) {
                \FluentCrm\App\Models\SubscriberNote::create([
                    'subscriber_id' => $contact->id,
                    'type'          => 'note',
                    'title'         => __('Status changed via MCP', 'fluent-crm'),
                    'description'   => sanitize_text_field((string) $params['status_change_reason']),
                ]);
            }

            // Optional double opt-in trigger for newly-pending contacts.
            // Deferred: this delivers a real email, and an email promising a
            // confirmation link for a contact the rollback removed is not
            // something we can take back.
            if ($contact->status === 'pending' && !empty($params['double_optin'])) {
                $optinContact = $contact;
                $deferredEffects[] = function () use ($optinContact) {
                    $optinContact->sendDoubleOptinEmail();
                };
            }
        } catch (\Throwable $e) {
            fluentCrmDb()->rollBack();
            // Rethrow so AbilitiesRegistrar::wrapExecuteCallback() logs the
            // detail server-side and returns the generic error.
            throw $e;
        }

        fluentCrmDb()->commit();

        foreach ($deferredEffects as $effect) {
            $effect();
        }

        $contact = Subscriber::with(['tags', 'lists'])->find($contact->id);

        return [
            'ok'      => true,
            'action'  => $action,
            'contact' => MCPHelper::formatContactForMCP($contact, ['include' => ['notes', 'email_history', 'automations']]),
            'changes' => [
                'fields_updated'   => self::diffFields($previousSnapshot, $contact),
                'tags_added'       => $tagsAdded,
                'tags_removed'     => $tagsRemoved,
                'lists_added'      => $listsAdded,
                'lists_removed'    => $listsRemoved,
                'previous_status'  => $previousStatus,
                'current_status'   => $contact->status,
                'previous_email'   => $previousEmail,
                'current_email'    => $contact->email,
                'tags_created'     => $addTags['created'],
                'lists_created'    => $addLists['created'],
            ],
        ];
    }

    // -----------------------------------------------------------------
    // Write: delete-contact-note (round 3 review #11)
    // -----------------------------------------------------------------

    public static function deleteContactNote($params)
    {
        $params = (array) $params;
        $noteId = (int) ($params['note_id'] ?? 0);
        if (!$noteId) {
            return MCPHelper::error('invalid_param', __('note_id is required', 'fluent-crm'));
        }

        $note = \FluentCrm\App\Models\SubscriberNote::find($noteId);
        if (!$note) {
            return MCPHelper::error('not_found', __('Note not found', 'fluent-crm'), ['note_id' => $noteId]);
        }

        $deletedId        = (int) $note->id;
        $subscriberId     = (int) $note->subscriber_id;
        $title            = (string) $note->title;
        $note->delete();

        do_action('fluent_crm/note_deleted', $deletedId, $subscriberId);

        return [
            'ok'             => true,
            'action'         => 'deleted',
            'deleted_id'     => $deletedId,
            'subscriber_id'  => $subscriberId,
            'deleted_title'  => $title,
            'note'           => __('Note row removed. The contact\'s other notes and email history are unaffected.', 'fluent-crm'),
        ];
    }

    /**
     * Compute the would-create list for a dry-run preview. Skips numeric
     * inputs (those are id lookups, not creation candidates — review B3
     * round 3) and only flags string names that have no existing match.
     */
    private static function wouldCreateNames($items, $kind = 'tag')
    {
        $out = [];
        foreach ((array) $items as $item) {
            if (is_numeric($item) || $item === '' || $item === null) {
                continue;
            }
            $name = sanitize_text_field((string) $item);
            $slug = sanitize_title($name);
            if ($kind === 'list') {
                $hit = \FluentCrm\App\Models\Lists::where('title', $name)->orWhere('slug', $slug)->first();
            } else {
                $hit = \FluentCrm\App\Models\Tag::where('title', $name)->orWhere('slug', $slug)->first();
            }
            if (!$hit) {
                $out[] = $name;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * The address keys the MCP contact-write contract accepts. Shared by the
     * strict single-upsert validation and the bulk per-row warning so both
     * paths agree on what "unknown" means (TRC-006).
     *
     * @return string[]
     */
    private static function addressAllowedKeys()
    {
        return ['line_1', 'line_2', 'city', 'state', 'postal_code', 'country'];
    }

    /**
     * Map the agent-facing {line_1, line_2, city, state, postal_code,
     * country} shape onto the column-named payload that Subscriber
     * createOrUpdate consumes. Mutates $payload by reference. Shared
     * between upsert-contact and bulk-upsert-contacts so both stay in
     * lock-step (operator-test report 2026-05-07 #5).
     */
    private static function applyAddressShape(array &$payload, $address)
    {
        if (empty($address) || !is_array($address)) {
            return;
        }
        $map = [
            'line_1'      => 'address_line_1',
            'line_2'      => 'address_line_2',
            'city'        => 'city',
            'state'       => 'state',
            'postal_code' => 'postal_code',
            'country'     => 'country',
        ];
        foreach ($map as $key => $col) {
            if (isset($address[$key]) && $address[$key] !== '') {
                $payload[$col] = $address[$key];
            }
        }
    }

    /**
     * Snapshot the diff-relevant columns of a Subscriber before any
     * in-place mutation (rename, save). diffFields() compares against
     * this snapshot so fields_updated stays correct even after the row
     * has been written.
     *
     * @return array<string,string>
     */
    private static function snapshotCompareFields($subscriber)
    {
        $snapshot = [];
        foreach (self::compareFieldNames() as $field) {
            $snapshot[$field] = (string) ($subscriber->{$field} ?? '');
        }
        return $snapshot;
    }

    private static function compareFieldNames()
    {
        return ['email', 'first_name', 'last_name', 'prefix', 'phone', 'status', 'contact_type', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country', 'date_of_birth', 'timezone', 'source'];
    }

    /**
     * @param array<string,string>|null $before  Snapshot from snapshotCompareFields()
     * @param object                    $after   Subscriber model post-save
     */
    private static function diffFields($before, $after)
    {
        if (!$before) {
            return ['*'];
        }
        $changed = [];
        foreach (self::compareFieldNames() as $field) {
            if (($before[$field] ?? '') !== (string) ($after->{$field} ?? '')) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    // -----------------------------------------------------------------
    // Write: bulk-upsert-contacts
    // -----------------------------------------------------------------

    public static function bulkUpsertContacts($params)
    {
        $params = (array) $params;
        $contacts = (array) ($params['contacts'] ?? []);
        if (!$contacts) {
            return MCPHelper::error('invalid_param', __('contacts is required', 'fluent-crm'));
        }

        $maxBatch = (int) apply_filters('fluent_crm/mcp_bulk_cap', 500, 'bulk-upsert-contacts');
        if (count($contacts) > $maxBatch) {
            return MCPHelper::error('cap_reached', __('Too many contacts in a single call', 'fluent-crm'), [
                'max'    => $maxBatch,
                'matched' => count($contacts),
            ]);
        }

        $autoCreateTags  = isset($params['auto_create_tags']) ? (bool) $params['auto_create_tags'] : true;
        $autoCreateLists = isset($params['auto_create_lists']) ? (bool) $params['auto_create_lists'] : true;
        $ifExists        = $params['if_exists'] ?? 'merge';
        $doubleOptin     = !empty($params['double_optin']);

        if (($autoCreateTags || $autoCreateLists)
            && !\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contact_cats')) {
            return MCPHelper::error('forbidden', __('Creating new tags/lists requires fcrm_manage_contact_cats', 'fluent-crm'));
        }

        $created = $updated = $skipped = $invalid = $warnings = [];

        // Pre-resolve every valid address in one bounded query. This loop used
        // to issue an indexed SELECT per row, so a full batch cost up to
        // $maxBatch round trips before any write happened (review PR #2025).
        // Keyed on the lowercased address because the email column's collation
        // is case-insensitive — "A@x.com" and "a@x.com" are the same contact,
        // and keying on the raw value would miss the match the DB would make.
        $lookupEmails = [];
        foreach ($contacts as $row) {
            if (is_array($row) && !empty($row['email']) && is_email($row['email'])) {
                $lookupEmails[strtolower(sanitize_email($row['email']))] = true;
            }
        }

        $existingByEmail = [];
        if ($lookupEmails) {
            foreach (Subscriber::whereIn('email', array_keys($lookupEmails))->get() as $found) {
                $existingByEmail[strtolower($found->email)] = $found;
            }
        }

        // Same treatment for segments. Resolving each row's tags/lists inside
        // the loop cost ~3 queries per row even when every tag already existed,
        // and auto-create bypasses the resolver's memo entirely — so a 500-row
        // batch issued well over a thousand avoidable queries. Resolve the
        // UNION of every row's refs once (creating any missing ones here), then
        // assign per row from the returned map with no further queries.
        // The 500-contact cap does not bound segment work on its own: refs are
        // per row, and auto-create defaults to TRUE here. An agent that maps a
        // high-cardinality column (company, notes) into `tags` would silently
        // mint a tag per distinct value, and junk tags are painful to undo —
        // they surface in every tag picker, automation, and segment condition
        // across the admin. Cap per row and in aggregate (review PR #2025).
        $tagRefUnion  = [];
        $listRefUnion = [];
        foreach ($contacts as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Over-sized rows are rejected in the loop below; skip their refs
            // here so a bad row cannot create tags before being rejected.
            if (count((array) ($row['tags'] ?? [])) > self::MAX_SEGMENT_REFS_PER_ROW
                || count((array) ($row['lists'] ?? [])) > self::MAX_SEGMENT_REFS_PER_ROW) {
                continue;
            }
            foreach ((array) ($row['tags'] ?? []) as $ref) {
                if (!is_array($ref) && !is_object($ref) && $ref !== '' && $ref !== null) {
                    $tagRefUnion[MCPHelper::segmentRefKey($ref)] = $ref;
                }
            }
            foreach ((array) ($row['lists'] ?? []) as $ref) {
                if (!is_array($ref) && !is_object($ref) && $ref !== '' && $ref !== null) {
                    $listRefUnion[MCPHelper::segmentRefKey($ref)] = $ref;
                }
            }
        }

        // Refuse the whole call rather than partially applying it: this many
        // distinct segments across one batch is a mapping mistake, not intent,
        // and with auto-create on the damage would already be done by the time
        // the per-row results were read.
        $distinctRefs = count($tagRefUnion) + count($listRefUnion);
        if ($distinctRefs > self::MAX_BULK_SEGMENT_REFS) {
            return MCPHelper::error('cap_reached', sprintf(
                /* translators: 1: distinct references received, 2: the cap */
                __('This batch references %1$d distinct tags/lists; the limit is %2$d per call. That usually means a high-cardinality field was mapped into tags or lists. Split the batch, or set auto_create_tags/auto_create_lists to false and pre-create the segments you actually want.', 'fluent-crm'),
                $distinctRefs,
                self::MAX_BULK_SEGMENT_REFS
            ), [
                'distinct_segment_refs' => $distinctRefs,
                'max_segment_refs'      => self::MAX_BULK_SEGMENT_REFS,
            ]);
        }

        $tagMap  = $tagRefUnion
            ? MCPHelper::resolveSegmentRefs(array_values($tagRefUnion), 'tag', $autoCreateTags)['map']
            : [];
        $listMap = $listRefUnion
            ? MCPHelper::resolveSegmentRefs(array_values($listRefUnion), 'list', $autoCreateLists)['map']
            : [];

        foreach ($contacts as $row) {
            if (!is_array($row) || empty($row['email']) || !is_email($row['email'])) {
                $invalid[] = ['email' => $row['email'] ?? null, 'reason' => 'invalid_email'];
                continue;
            }

            if (count((array) ($row['tags'] ?? [])) > self::MAX_SEGMENT_REFS_PER_ROW
                || count((array) ($row['lists'] ?? [])) > self::MAX_SEGMENT_REFS_PER_ROW) {
                $invalid[] = [
                    'email'  => $row['email'],
                    'reason' => 'too_many_segment_refs',
                    'max'    => self::MAX_SEGMENT_REFS_PER_ROW,
                ];
                continue;
            }

            $emailKey = strtolower(sanitize_email($row['email']));
            $existing = isset($existingByEmail[$emailKey]) ? $existingByEmail[$emailKey] : null;
            if ($existing && $ifExists === 'skip') {
                $skipped[] = ['id' => (int) $existing->id, 'email' => $existing->email];
                continue;
            }
            if ($existing && $ifExists === 'error') {
                $invalid[] = ['email' => $row['email'], 'reason' => 'contact_exists', 'id' => (int) $existing->id];
                continue;
            }

            // Per-row segments, read from the maps resolved above — no queries.
            $tagIds  = ['ids' => self::mapSegmentRefs($row['tags'] ?? [], $tagMap)];
            $listIds = ['ids' => self::mapSegmentRefs($row['lists'] ?? [], $listMap)];

            // Build from the shared allowlist, never the raw row — otherwise
            // model-fillable-but-not-MCP fields (user_id, company_id, ip,
            // sms_status, life_time_value, timestamps, …) would be overposted
            // through the untyped bulk object (SEC-001). Mirrors single upsert.
            $payload = [
                'email' => sanitize_email($row['email']),
            ];
            foreach (self::contactWritePassthru() as $field) {
                if (array_key_exists($field, $row) && $row[$field] !== null && $row[$field] !== '') {
                    $payload[$field] = $row[$field];
                }
            }
            $payload['tags']  = $tagIds['ids'];
            $payload['lists'] = $listIds['ids'];

            // Per-row TRC-006: warn on unknown/invalid address keys but still
            // save the row without them (bulk partial semantics — never fail
            // sibling rows). additionalProperties:false is deliberately NOT set
            // on the bulk address schema so the adapter can't reject the whole
            // call before this per-row handling runs.
            if (array_key_exists('address', $row) && $row['address'] !== null) {
                if (!is_array($row['address'])) {
                    $warnings[] = ['email' => $row['email'], 'reason' => 'invalid_address_not_object'];
                } else {
                    $unknownAddr = MCPHelper::unknownKeys($row['address'], self::addressAllowedKeys(), 'address');
                    if ($unknownAddr) {
                        $warnings[] = [
                            'email'              => $row['email'],
                            'reason'             => 'unknown_address_keys',
                            'unknown_properties' => $unknownAddr,
                        ];
                    }
                }
            }

            // Same address-shape mapping as single upsert. Without this,
            // bulk silently dropped the {line_1,...,country} object —
            // operator-test report 2026-05-07 #5.
            self::applyAddressShape($payload, $row['address'] ?? null);

            // Same rule as upsert-contact: stamp source='mcp_bulk' only on
            // creation. On update, preserve the original source unless the
            // caller passed one explicitly.
            if (!$existing && (!isset($payload['source']) || $payload['source'] === '')) {
                $payload['source'] = 'mcp_bulk';
            } elseif ($existing && (!isset($payload['source']) || $payload['source'] === '')) {
                unset($payload['source']);
            }

            if (!empty($row['custom_fields']) && is_array($row['custom_fields'])) {
                // Same diff-against-schema gate as single upsert, but
                // surface unknown slugs as a per-row warning so one bad
                // row doesn't fail the whole batch (operator-test report
                // 2026-05-07 #6). Known keys still persist.
                $diff = MCPHelper::diffCustomFields($row['custom_fields']);
                if (!empty($diff['unknown'])) {
                    $warnings[] = [
                        'email'                       => $row['email'],
                        'reason'                      => 'unknown_custom_field_slugs',
                        'unknown_custom_field_slugs'  => $diff['unknown'],
                    ];
                }
                $payload['custom_values'] = $diff['known'];
            }

            // Same status semantics as the single upsert: unknown status strings are
            // dropped (with a per-row warning), and only an explicitly supplied valid
            // status may overwrite an existing (non-deliverability) suppressed status.
            if (!empty($payload['status']) && !in_array($payload['status'], fluentcrm_subscriber_statuses())) {
                $warnings[] = [
                    'email'  => $row['email'],
                    'reason' => 'unknown_status_ignored',
                    'status' => $payload['status'],
                ];
                unset($payload['status']);
            }

            $contact = FluentCrmApi('contacts')->createOrUpdate($payload, !empty($payload['status']), false);
            if (!$contact) {
                $invalid[] = ['email' => $row['email'], 'reason' => 'failed_to_save'];
                continue;
            }

            // Keep the map current so an address repeated later in the same
            // batch is still seen as existing — the per-row lookup this
            // replaced re-read the table every iteration and behaved that way.
            $existingByEmail[strtolower($contact->email)] = $contact;

            if ($doubleOptin && $contact->status === 'pending') {
                $contact->sendDoubleOptinEmail();
            }

            $entry = [
                'id'     => (int) $contact->id,
                'email'  => $contact->email,
                'status' => $contact->status,
            ];
            if (!empty($contact->wasRecentlyCreated)) {
                $created[] = $entry;
            } else {
                $updated[] = $entry;
            }
        }

        return [
            'ok'      => true,
            'summary' => [
                'created'  => count($created),
                'updated'  => count($updated),
                'skipped'  => count($skipped),
                'invalid'  => count($invalid),
                'warnings' => count($warnings),
            ],
            'created'  => $created,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'invalid'  => $invalid,
            'warnings' => $warnings,
        ];
    }

    /**
     * Translate one row's tag/list refs into ids using a map produced by
     * MCPHelper::resolveSegmentRefs(). Refs absent from the map resolved to
     * nothing (and were not auto-created), so they are dropped — matching the
     * per-row resolver this replaced, which also skipped unmatched refs.
     *
     * @param mixed $refs
     * @param array $map ref key => id
     * @return int[]
     */
    private static function mapSegmentRefs($refs, $map)
    {
        $ids = [];

        foreach ((array) $refs as $ref) {
            if (is_array($ref) || is_object($ref) || $ref === '' || $ref === null) {
                continue;
            }
            $key = MCPHelper::segmentRefKey($ref);
            if (isset($map[$key])) {
                $ids[] = (int) $map[$key];
            }
        }

        return array_values(array_unique($ids));
    }

    // -----------------------------------------------------------------
    // Write: delete-contact
    // -----------------------------------------------------------------

    public static function deleteContact($params)
    {
        $resolved = MCPHelper::resolveContact((array) $params);
        if (is_wp_error($resolved)) {
            return $resolved;
        }
        $contact = $resolved;
        $deletedId    = (int) $contact->id;
        $deletedEmail = (string) $contact->email;
        $deleteEmails = !isset($params['delete_emails']) ? true : (bool) $params['delete_emails'];

        if ($deleteEmails) {
            \FluentCrm\App\Models\CampaignEmail::where('subscriber_id', $deletedId)->delete();
        }

        $ok = \FluentCrm\App\Services\Helper::deleteContacts([$deletedId]);
        if (!$ok) {
            return MCPHelper::error('failed', __('Could not delete the contact', 'fluent-crm'));
        }

        return [
            'ok'             => true,
            'deleted_id'     => $deletedId,
            'deleted_email'  => $deletedEmail,
            'emails_purged'  => (bool) $deleteEmails,
        ];
    }

    // -----------------------------------------------------------------
    // Write: apply-segments-to-contacts
    // -----------------------------------------------------------------

    public static function applySegmentsToContacts($params)
    {
        $params = (array) $params;

        $autoCreateTags  = !empty($params['auto_create_tags']);
        $autoCreateLists = !empty($params['auto_create_lists']);
        if (($autoCreateTags || $autoCreateLists)
            && !\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_contact_cats')) {
            return MCPHelper::error('forbidden', __('Creating new tags/lists requires fcrm_manage_contact_cats', 'fluent-crm'));
        }

        $contactIds = isset($params['contact_ids']) ? array_filter(array_map('intval', (array) $params['contact_ids'])) : [];
        $filter     = $params['filter'] ?? null;
        $dryRun     = !empty($params['dry_run']);

        if (!$contactIds && empty($filter)) {
            return MCPHelper::error('invalid_param', __('Provide contact_ids or filter', 'fluent-crm'));
        }

        if ($contactIds && !empty($filter)) {
            return MCPHelper::error('invalid_param', __('Provide contact_ids OR filter, not both', 'fluent-crm'));
        }

        $cap = (int) apply_filters('fluent_crm/mcp_bulk_cap', 5000, 'apply-segments-to-contacts');

        if (!$contactIds) {
            $validation = MCPHelper::validateUniversalFilter((array) $filter);
            if (is_wp_error($validation)) {
                return $validation;
            }
            $args = MCPHelper::buildContactsQueryArgs((array) $filter);
            $args['with'] = []; // we just need ids
            $cq = new ContactsQuery($args);
            MCPHelper::applyDateFilters($cq, (array) $filter);
            $query = $cq->getModel();

            $matched = (int) $query->count();
            // During a dry run, expose the matched count even when it
            // exceeds the cap — knowing the size is the whole point of a
            // preview. The agent can then batch.
            if ($matched > $cap && !$dryRun) {
                return MCPHelper::error('cap_reached', __('Too many contacts match the filter', 'fluent-crm'), [
                    'max'     => $cap,
                    'matched' => $matched,
                ]);
            }

            $contactIds = array_map('intval', $query->limit($cap)->pluck('id')->toArray());
            // Stash the true matched count so dry_run can echo it (the
            // pluck call above only returns up to $cap rows).
            $matchedTotal = $matched;
        } else {
            if (count($contactIds) > $cap && !$dryRun) {
                return MCPHelper::error('cap_reached', __('Too many contact_ids in a single call', 'fluent-crm'), [
                    'max'     => $cap,
                    'matched' => count($contactIds),
                ]);
            }
            $matchedTotal = count($contactIds);
        }

        // Resolve segment refs. Auto-create is suppressed during dry runs so
        // a preview never leaves orphan tags/lists behind.
        $addTags    = MCPHelper::resolveTagIds((array) ($params['add_tags'] ?? []), $autoCreateTags && !$dryRun);
        $removeTags = MCPHelper::resolveTagIds((array) ($params['remove_tags'] ?? []), false);
        $addLists   = MCPHelper::resolveListIds((array) ($params['add_lists'] ?? []), $autoCreateLists && !$dryRun);
        $removeLists = MCPHelper::resolveListIds((array) ($params['remove_lists'] ?? []), false);

        // Compute the would-create set: name strings the agent supplied that
        // don't resolve to an existing tag/list. Numeric inputs are id
        // lookups, never creation candidates (review B3 round 3).
        $tagsWouldCreate  = self::wouldCreateNames((array) ($params['add_tags'] ?? []), 'tag');
        $listsWouldCreate = self::wouldCreateNames((array) ($params['add_lists'] ?? []), 'list');

        // The "at least one" guard considers what would actually happen — if
        // dry_run with names that would create, that IS work, so don't bail.
        $hasAnyWork = $addTags['ids'] || $removeTags['ids']
            || $addLists['ids'] || $removeLists['ids']
            || ($dryRun && ($tagsWouldCreate || $listsWouldCreate));
        if (!$hasAnyWork) {
            return MCPHelper::error('invalid_param', __('Provide at least one of add_tags, remove_tags, add_lists, remove_lists', 'fluent-crm'));
        }

        if ($dryRun) {
            $formatRefs = function ($ids) {
                $out = [];
                foreach ($ids as $id) {
                    $out[] = ['id' => (int) $id];
                }
                return $out;
            };
            $exceedsCap = $matchedTotal > $cap;
            return [
                'ok'                  => true,
                'dry_run'             => true,
                'matched_contacts'    => $matchedTotal,
                'cap'                 => $cap,
                'exceeds_cap'         => $exceedsCap,
                'batches_required'    => $exceedsCap ? (int) ceil($matchedTotal / max(1, $cap)) : 1,
                'applied_to_contacts' => 0,
                'tags_added'          => $formatRefs($addTags['ids']),
                'tags_removed'        => $formatRefs($removeTags['ids']),
                'lists_added'         => $formatRefs($addLists['ids']),
                'lists_removed'       => $formatRefs($removeLists['ids']),
                'tags_would_create'   => $tagsWouldCreate,
                'lists_would_create'  => $listsWouldCreate,
                'note'                => $exceedsCap
                    ? __('Dry run — match exceeds the per-call cap. Apply by passing contact_ids in batches.', 'fluent-crm')
                    : __('Dry run — nothing was applied. Re-run without dry_run=true to commit.', 'fluent-crm'),
            ];
        }

        // Process in chunks so attach/detach don't load thousands of rows at
        // once. Each Subscriber attach/detach already de-dupes internally.
        // Track the actual touched ids (review P2 #10) so an agent can
        // reverse precisely without re-running the original filter — which
        // may match a different set after time passes.
        $chunkSize = 200;
        $applied = 0;
        $appliedIds = [];
        foreach (array_chunk($contactIds, $chunkSize) as $batchIds) {
            $subscribers = Subscriber::whereIn('id', $batchIds)->get();
            foreach ($subscribers as $sub) {
                if ($addTags['ids']) {
                    $sub->attachTags($addTags['ids']);
                }
                if ($removeTags['ids']) {
                    $sub->detachTags($removeTags['ids']);
                }
                if ($addLists['ids']) {
                    $sub->attachLists($addLists['ids']);
                }
                if ($removeLists['ids']) {
                    $sub->detachLists($removeLists['ids']);
                }
                $applied++;
                $appliedIds[] = (int) $sub->id;
            }
        }

        $formatRefs = function ($ids) {
            $out = [];
            foreach ($ids as $id) {
                $out[] = ['id' => (int) $id];
            }
            return $out;
        };

        return [
            'ok'                  => true,
            'matched_contacts'    => count($contactIds),
            'applied_to_contacts' => $applied,
            'applied_contact_ids' => $appliedIds,
            'tags_added'          => $formatRefs($addTags['ids']),
            'tags_removed'        => $formatRefs($removeTags['ids']),
            'lists_added'         => $formatRefs($addLists['ids']),
            'lists_removed'       => $formatRefs($removeLists['ids']),
            'tags_created'        => $addTags['created'],
            'lists_created'       => $addLists['created'],
            'reverse_with'        => __('To reverse: re-call apply-segments-to-contacts with contact_ids=applied_contact_ids and add_*/remove_* swapped.', 'fluent-crm'),
        ];
    }

    // -----------------------------------------------------------------
    // Write: add-contact-note
    // -----------------------------------------------------------------

    public static function addContactNote($params)
    {
        $params = (array) $params;

        $resolved = MCPHelper::resolveContact($params);
        if (is_wp_error($resolved)) {
            return $resolved;
        }
        $subscriber = $resolved;

        $title       = trim((string) ($params['title'] ?? ''));
        $description = (string) ($params['description'] ?? '');
        $allowedTypes = ['note', 'call', 'email', 'meeting', 'quote'];
        $type         = sanitize_key($params['type'] ?? 'note');
        // Unknown note type is a hard error, not a silent coercion to 'note'
        // (TRC-002) — silently rewriting the caller's type misreports what was
        // saved.
        if (array_key_exists('type', $params) && $params['type'] !== '' && !in_array($type, $allowedTypes, true)) {
            return MCPHelper::error('invalid_param', __('type is not a recognized note type.', 'fluent-crm'), [
                'type'    => $params['type'],
                'allowed' => $allowedTypes,
            ]);
        }

        if ($title === '' || $description === '') {
            return MCPHelper::error('invalid_param', __('title and description are required', 'fluent-crm'));
        }

        $noteData = [
            'subscriber_id' => $subscriber->id,
            'type'          => $type,
            'title'         => $title,
            'description'   => $description,
            'created_at'    => !empty($params['created_at']) ? sanitize_text_field($params['created_at']) : current_time('mysql'),
        ];

        // Run through the same filter the controller does so smartcodes resolve.
        $noteData['description'] = apply_filters('fluent_crm/parse_campaign_email_text', $noteData['description'], $subscriber);
        $noteData = \FluentCrm\App\Services\Sanitize::contactNote($noteData);

        $note = \FluentCrm\App\Models\SubscriberNote::create(wp_unslash($noteData));

        do_action('fluent_crm/note_added', $note, $subscriber, $noteData);

        return [
            'ok'   => true,
            'note' => MCPHelper::formatNoteForMCP($note),
        ];
    }
}
