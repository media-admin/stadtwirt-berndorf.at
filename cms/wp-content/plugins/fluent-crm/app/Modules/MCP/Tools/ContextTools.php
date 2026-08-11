<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\CustomContactField;
use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\App\Services\Stats;

/**
 * `get-crm-context` — compact, permission-scoped discovery surface.
 *
 * Identity and site metadata are always returned. Reference-heavy sections are
 * selected through `include`; omitting it returns only the small default set.
 * Each domain section is built only when the current user holds its matching
 * FluentCRM capability.
 *
 * Reads live — see getContext() for why there is no cache layer.
 */
class ContextTools
{
    const DEFAULT_SECTIONS = ['overview', 'capabilities'];

    const AVAILABLE_SECTIONS = [
        'overview',
        'stats',
        'segments',
        'enums',
        'sender',
        'custom_fields',
        'smart_codes',
        'automation_catalog',
        'safety',
        'rate_hints',
        'capabilities',
        'guidelines',
    ];

    public static function getContext($params = [])
    {
        $params = (array) $params;
        $sections = self::normalizeSections($params);
        if (is_wp_error($sections)) {
            return $sections;
        }

        // Deliberately uncached. This is an operator-facing tool called a
        // handful of times per session by a handful of people, not a public
        // endpoint — the default sections are a few COUNTs and some in-memory
        // registries. A transient layer cost a cache read on every call, a
        // version option, and twelve invalidation hooks, and still served
        // stale tags for up to its TTL. Reading live is both simpler and more
        // correct: a tag created a moment ago shows up immediately.
        $userId      = get_current_user_id();
        $permissions = self::effectivePermissions(PermissionManager::currentUserPermissions(false));
        $access      = self::buildAccessMap();

        return self::buildContext($userId, $permissions, $access, $sections, $params);
    }

    /**
     * Normalize explicit section selection before any queries run.
     *
     * Omitted include uses DEFAULT_SECTIONS, [] returns identity/site only, and
     * `all` expands to every section. Unknown values fail for direct Abilities
     * API callers even when MCP schema validation is not in front of us.
     */
    private static function normalizeSections($params)
    {
        if (!array_key_exists('include', $params)) {
            return self::DEFAULT_SECTIONS;
        }

        if (!is_array($params['include'])) {
            return MCPHelper::error('invalid_param', __('include must be an array of context section names.', 'fluent-crm'));
        }

        $requested = array_values(array_unique(array_map('sanitize_key', $params['include'])));
        if (in_array('all', $requested, true)) {
            return self::AVAILABLE_SECTIONS;
        }

        $unknown = array_values(array_diff($requested, self::AVAILABLE_SECTIONS));
        if ($unknown) {
            return MCPHelper::error('invalid_param', __('include contains unknown context sections.', 'fluent-crm'), [
                'unknown' => $unknown,
                'allowed' => self::AVAILABLE_SECTIONS,
            ]);
        }

        // Return sections in the caller's order so the response remains easy
        // to compare with the request while still de-duplicating names.
        return $requested;
    }

    private static function buildAccessMap()
    {
        $contacts = PermissionManager::currentUserCan('fcrm_read_contacts');
        $emails   = PermissionManager::currentUserCan('fcrm_read_emails');
        $funnels  = PermissionManager::currentUserCan('fcrm_read_funnels');

        return [
            'dashboard' => PermissionManager::currentUserCan('fcrm_view_dashboard'),
            'contacts'  => $contacts,
            'emails'    => $emails,
            'funnels'   => $funnels,
            'settings'  => PermissionManager::currentUserCan('fcrm_manage_settings'),
        ];
    }

    private static function buildContext($userId, $permissions, $access, $sections, $params = [])
    {
        $user = get_user_by('ID', $userId);

        $isAdmin = $user && user_can($user, 'manage_options');

        $you = [
            'wp_user_id'  => (int) $userId,
            'name'        => $user ? $user->display_name : null,
            'email'       => $user ? $user->user_email : null,
            'is_admin'    => (bool) $isAdmin,
            'permissions' => $permissions,
            'tool_groups' => array_keys(array_filter([
                'dashboard'   => $access['dashboard'],
                'contacts'    => $access['contacts'],
                'campaigns'   => $access['emails'],
                'automations' => $access['funnels'],
            ])),
        ];

        $proActive = defined('FLUENTCAMPAIGN');

        $site = [
            'site_url'              => site_url(),
            'fluent_crm_version'    => defined('FLUENTCRM_PLUGIN_VERSION') ? FLUENTCRM_PLUGIN_VERSION : null,
            'fluent_campaign_active' => $proActive,
            'timezone'               => fluentCrmGetTimezoneString(),
            'current_time'           => fluentCrmTimestamp(),
        ];

        $sectionCatalog = [];
        foreach (self::AVAILABLE_SECTIONS as $section) {
            if (self::canAccessSection($section, $access)) {
                $sectionCatalog[$section] = ['available' => true];
            } else {
                $sectionCatalog[$section] = self::unavailableSection($section);
            }
        }

        $context = [
            'you'              => $you,
            'site'             => $site,
            'context_sections' => [
                'default'      => self::DEFAULT_SECTIONS,
                'requested'    => $sections,
                'included'     => [],
                'request_with' => 'include',
                'catalog'      => $sectionCatalog,
            ],
        ];

        foreach ($sections as $section) {
            if (!self::canAccessSection($section, $access)) {
                continue;
            }

            self::appendSection($context, $section, $access, $params);
            $context['context_sections']['included'][] = $section;
        }

        return $context;
    }

    /**
     * Resolve the displayed/cache-signature permissions through the same
     * currentUserCan filters used by every ability permission callback.
     */
    private static function effectivePermissions($permissions)
    {
        $effective = [];
        foreach ((array) $permissions as $permission) {
            if (PermissionManager::currentUserCan($permission)) {
                $effective[] = $permission;
            }
        }

        return array_values(array_unique($effective));
    }

    /**
     * Append one authorized section while preserving the existing top-level
     * response keys used by agents (`tags`/`lists`, `default_sender`, etc.).
     */
    private static function appendSection(&$context, $section, $access, $params = [])
    {
        switch ($section) {
            case 'overview':
                $context['overview'] = self::buildOverview($access);
                break;
            case 'stats':
                $context['stats'] = self::buildStats();
                break;
            case 'segments':
                $withCounts = !empty($params['include_segment_counts']);
                $context['tags'] = self::tagsForContext($withCounts);
                $context['lists'] = self::listsForContext($withCounts);
                break;
            case 'enums':
                $context['enums'] = self::buildEnums($access);
                break;
            case 'sender':
                $context['default_sender'] = self::buildDefaultSender();
                break;
            case 'custom_fields':
                $context['custom_fields_schema'] = ['contact' => self::buildCustomFieldSchema()];
                break;
            case 'smart_codes':
                $context['smart_codes'] = self::buildSmartCodes();
                break;
            case 'automation_catalog':
                $context['available_triggers'] = self::formatRefList(
                    apply_filters('fluentcrm_funnel_triggers', []),
                    'trigger_name'
                );
                $context['available_actions'] = self::formatRefList(
                    apply_filters('fluentcrm_funnel_blocks', [], null),
                    'action_name'
                );
                break;
            case 'safety':
                $context['safety_levels'] = self::buildSafetyLevels();
                break;
            case 'rate_hints':
                $context['rate_hints'] = self::buildRateHints();
                break;
            case 'capabilities':
                $context['mcp_capabilities'] = self::buildCapabilities();
                break;
            case 'guidelines':
                $context['guidelines'] = self::buildGuidelines($access);
                break;
        }
    }

    private static function canAccessSection($section, $access)
    {
        switch ($section) {
            case 'stats':
                return $access['dashboard'];
            case 'segments':
            case 'custom_fields':
                return $access['contacts'];
            case 'sender':
                return $access['emails'] || $access['settings'];
            case 'smart_codes':
                return $access['emails'];
            case 'automation_catalog':
                return $access['funnels'];
            case 'enums':
                return $access['contacts'] || $access['emails'] || $access['funnels'];
            default:
                return true;
        }
    }

    private static function unavailableSection($section)
    {
        $requirements = [
            'stats'              => ['fcrm_view_dashboard'],
            'segments'           => ['fcrm_read_contacts'],
            'custom_fields'      => ['fcrm_read_contacts'],
            'smart_codes'        => ['fcrm_read_emails'],
            'automation_catalog' => ['fcrm_read_funnels'],
            'enums'              => ['fcrm_read_contacts', 'fcrm_read_emails', 'fcrm_read_funnels'],
        ];

        $marker = [
            'available' => false,
            'reason'    => 'missing_capability',
        ];

        if ($section === 'sender') {
            $marker['required_any'] = ['fcrm_read_emails', 'fcrm_manage_settings'];
            return $marker;
        }

        $required = $requirements[$section] ?? [];
        if (count($required) === 1) {
            $marker['required'] = $required[0];
        } elseif ($required) {
            $marker['required_any'] = $required;
        }

        return $marker;
    }

    /**
     * Cheap default discovery: capability-sliced counts, essential enums, and
     * feature availability. It deliberately excludes reference catalogs.
     */
    private static function buildOverview($access)
    {
        $counts = [];
        if ($access['dashboard'] || $access['contacts']) {
            $stats = (new Stats())->getCounts();
            $todayStart = (new \DateTime('today', new \DateTimeZone(fluentCrmGetTimezoneString())))->format('Y-m-d H:i:s');
            $counts['contacts_total'] = Subscriber::count();
            $counts['contacts_subscribed'] = (int) ($stats['total_subscribers']['count'] ?? Subscriber::where('status', 'subscribed')->count());
            $counts['contacts_new_today'] = Subscriber::where('created_at', '>=', $todayStart)->count();
        }

        if ($access['dashboard'] || $access['emails']) {
            $sevenDaysAgo = gmdate('Y-m-d H:i:s', current_time('timestamp') - (7 * DAY_IN_SECONDS));
            $counts['campaigns_sent_last_7d'] = \FluentCrm\App\Models\Campaign::where('status', 'archived')
                ->where('updated_at', '>=', $sevenDaysAgo)
                ->count();
        }

        if ($access['dashboard'] || $access['funnels']) {
            $counts['automations_active'] = \FluentCrm\App\Models\Funnel::where('status', 'published')->count();
            $counts['automations_total'] = \FluentCrm\App\Models\Funnel::count();
        }

        $enums = [];
        if ($access['contacts']) {
            $enums['contact_statuses'] = array_values(fluentcrm_subscriber_statuses());
            $enums['contact_types'] = array_keys(fluentcrm_contact_types());
        }
        if ($access['emails']) {
            $enums['campaign_statuses'] = self::campaignStatuses();
            $enums['one_off_statuses'] = self::oneOffStatuses();
        }

        return [
            'counts'   => $counts,
            'enums'    => $enums,
            'features' => [
                'fluent_campaign_active' => defined('FLUENTCAMPAIGN'),
                'companies_enabled'       => Helper::isCompanyEnabled(),
                'event_tracking_enabled'  => Helper::isExperimentalEnabled('event_tracking'),
            ],
        ];
    }

    /**
     * Marketing-campaign lifecycle statuses (type='campaign').
     *
     * Single source for both enum builders — they previously carried separate
     * copies of this literal, which is how `published` came to be missing from
     * the advertised contract after one-off writes were normalized to it
     * (PR #2026 review finding 1).
     *
     * @return array<int, string>
     */
    public static function campaignStatuses()
    {
        return ['draft', 'scheduled', 'pending-scheduled', 'processing', 'working', 'paused', 'archived'];
    }

    /**
     * Statuses carried by one-off sends (type='custom_email_campaign').
     *
     * Deliberately separate from campaignStatuses(): a one-off has no
     * lifecycle. The row is written 'published' at creation and never advances,
     * so the value says "this is a one-off", not "this was delivered" — the
     * real delivery state is get-campaign's `one_off_status`. Folding this into
     * campaign_statuses would tell agents a marketing campaign can be
     * 'published', which it never is.
     *
     * These values only match rows when list-campaigns is called with
     * include_one_offs=true.
     *
     * @return array<int, string>
     */
    public static function oneOffStatuses()
    {
        return ['published'];
    }

    private static function buildEnums($access)
    {
        $enums = [];

        if ($access['contacts']) {
            $enums['contact_statuses'] = array_values(fluentcrm_subscriber_statuses());
            $enums['sms_statuses'] = array_values(fluentcrm_subscriber_sms_statuses());
            $enums['contact_types'] = array_keys(fluentcrm_contact_types());
            $enums['note_types'] = ['note', 'call', 'email', 'meeting', 'quote'];
        }

        if ($access['emails']) {
            $enums['campaign_statuses'] = self::campaignStatuses();
            $enums['one_off_statuses'] = self::oneOffStatuses();
            $enums['design_templates'] = array_keys(self::allowedDesignTemplates());
        }

        if ($access['funnels']) {
            $enums['funnel_statuses'] = ['draft', 'published'];
            $enums['funnel_subscriber_statuses'] = ['active', 'waiting', 'completed', 'cancelled', 'skipped'];
        }

        return $enums;
    }

    /**
     * Per-tool safety classification — round-3 review R3.
     *
     * Lets agents branch on a stable code instead of parsing tool
     * descriptions or relying on annotations alone (which only
     * differentiate readonly / destructive in two coarse buckets).
     *
     * Levels:
     *   safe_render          — no DB writes, no sends, no side effects
     *   readonly             — DB reads only
     *   creates_or_mutates_draft — writes data the user can still review/cancel
     *   mutating_with_dry_run    — writes data; dry_run preview available
     *   destructive_send         — actually sends mail to a real recipient
     *   destructive_irrecoverable — deletion / cannot be undone
     */
    private static function buildSafetyLevels()
    {
        return apply_filters('fluent_crm/mcp_safety_levels', [
            'fluent-crm/get-crm-context'                  => 'readonly',
            'fluent-crm/list-contacts'                    => 'readonly',
            'fluent-crm/get-contact-filter-schema'        => 'readonly',
            'fluent-crm/get-contact'                      => 'readonly',
            'fluent-crm/list-campaigns'                   => 'readonly',
            'fluent-crm/get-campaign'                     => 'readonly',
            'fluent-crm/list-automations'                 => 'readonly',
            'fluent-crm/list-funnel-subscribers'          => 'readonly',
            'fluent-crm/get-automation'                   => 'readonly',
            'fluent-crm/list-sequences'                   => 'readonly',
            'fluent-crm/get-sequence'                     => 'readonly',
            'fluent-crm/estimate-dynamic-segment'         => 'readonly',
            'fluent-crm/upsert-contact'                   => 'creates_or_mutates_draft',
            'fluent-crm/bulk-upsert-contacts'             => 'creates_or_mutates_draft',
            'fluent-crm/add-contact-note'                 => 'creates_or_mutates_draft',
            'fluent-crm/upsert-campaign'                  => 'creates_or_mutates_draft',
            'fluent-crm/apply-segments-to-contacts'       => 'mutating_with_dry_run',
            'fluent-crm/manage-sequence-subscribers'      => 'mutating_with_dry_run',
            'fluent-crm/update-contact-automation-status' => 'creates_or_mutates_draft',
            'fluent-crm/manage-tag'                       => 'destructive_irrecoverable',
            'fluent-crm/manage-list'                      => 'destructive_irrecoverable',
            'fluent-crm/delete-contact'                   => 'destructive_irrecoverable',
            'fluent-crm/delete-contact-note'              => 'destructive_irrecoverable',
            'fluent-crm/render-email-preview'             => 'safe_render',
            'fluent-crm/send-test-email'                  => 'destructive_send',
            'fluent-crm/send-email-to-contact'            => 'destructive_send',
            // change-campaign-status: per-action — schedule + delete are
            // destructive in different ways. Annotation already flags it;
            // the description spells out which actions are dangerous.
            'fluent-crm/change-campaign-status'           => 'destructive_send',
        ]);
    }

    /**
     * Rate / cap hints — round-3 review R4.
     *
     * Surfaces the limits that are otherwise embedded only in tool
     * descriptions ("Cap 5000 per call"). Lets agents pre-validate
     * batch sizes deterministically.
     */
    private static function buildRateHints()
    {
        $cap = (int) apply_filters('fluent_crm/mcp_bulk_cap', 5000, 'apply-segments-to-contacts');
        return apply_filters('fluent_crm/mcp_rate_hints', [
            'fluent-crm/bulk-upsert-contacts'        => ['max_per_call' => 500, 'recommended_batch' => 100],
            'fluent-crm/apply-segments-to-contacts'  => ['max_per_call' => $cap],
            'fluent-crm/manage-sequence-subscribers' => ['max_per_call' => $cap],
            'fluent-crm/send-email-to-contact'       => ['note' => 'Goes through the normal queue + bounce handling — site-level rate limits apply (see settings.email_settings.emails_per_second).'],
        ]);
    }

    /**
     * Versioned capabilities map — round-3 review R9.
     *
     * Lets agents adapt their strategy across MCP versions without trial
     * and error. Bump `version` whenever a capability is added/removed.
     */
    private static function buildCapabilities()
    {
        return apply_filters('fluent_crm/mcp_capabilities', [
            'version' => '1.10.0',
            'supports' => [
                'paginated_tag_list_registry',
                'segment_last_used_at',
                'automation_last_enrolled_at',
                'compact_permission_scoped_context',
                'context_section_selection',
                'bounded_contact_profile',
                'campaign_body_format',
                'dry_run_apply_segments',
                'send_test_email',
                'test_email_sender_parity',
                'render_email_preview',
                'mcp_annotation_hints',
                'contact_cross_plugin_includes',
                'smart_codes_discovery',
                'safety_levels',
                'rate_hints',
                'manage_tags_lists',
                'delete_contact_note',
                'one_off_email_send',
                'campaign_warnings',
                'auto_suffix_title_conflict',
                'list_funnel_subscribers',
                'applied_contact_ids_return',
                'tracking_mode_aware_stats',
                'recipients_strict_validation',
                'advanced_filters_provider_validation',
                'contact_filter_schema_discovery',
            ],
            'deprecated'             => [
                'get-contact include "ai_summary" — removed in 1.6.0; summarize from the raw sections instead',
                'contact field "life_time_value" — removed in 1.6.0 (was never populated); use the purchase_history include',
                'send-test-email "render_only" — removed from the schema in 1.8.0; use the render-email-preview tool (a render_only argument is still honored as a preview for back-compat, and now requires fcrm_read_contacts like the tool it stands in for)',
                'upsert-campaign recipients/exclude_recipients "sending_filter" — rejected as of 1.9.0; it was never persisted, so accepting it was a silent drop. Campaign targeting is tags + lists only',
            ],
            'corrections'            => [
                'get-crm-context segments: tags/lists no longer carry subscribers_count by default (it cost a per-row pivot count). Pass include_segment_counts=true, or read `total` from a filtered list-contacts call for one segment',
                'get-crm-context segments caps tags and lists at 50 rows each with no paging — it is a discovery teaser, not the registry. As of 1.10.0 use list-tags / list-lists to enumerate them all, with search, counts, and last_used_at',
                'get-contact automations[].next_sequence_id returned the step ORDINAL before 1.9.0; it now returns the fc_funnel_sequences row id (usable as update-contact-automation-status.advance_to_sequence_id). The ordinal moved to next_sequence_no',
                'Timestamps returned by every tool now carry the site UTC offset instead of being stamped +00:00 while holding site-local time',
            ],
            'breaking_changes_pending' => [],
        ]);
    }

    private static function buildStats()
    {
        $stats = (new Stats())->getCounts();

        $todayStart = (new \DateTime('today', new \DateTimeZone(fluentCrmGetTimezoneString())))->format('Y-m-d H:i:s');
        // Site-local like $todayStart — the compared columns store site time, so a
        // UTC time() base skews the 7-day window by the site offset.
        $sevenDaysAgo = gmdate('Y-m-d H:i:s', current_time('timestamp') - (7 * DAY_IN_SECONDS));

        return [
            'contacts_total'         => Subscriber::count(),
            'contacts_subscribed'    => (int) ($stats['total_subscribers']['count'] ?? Subscriber::where('status', 'subscribed')->count()),
            'contacts_new_today'     => Subscriber::where('created_at', '>=', $todayStart)->count(),
            'campaigns_sent_last_7d' => \FluentCrm\App\Models\Campaign::where('status', 'archived')
                ->where('updated_at', '>=', $sevenDaysAgo)
                ->count(),
            'automations_active'     => \FluentCrm\App\Models\Funnel::where('status', 'published')->count(),
            'automations_total'      => \FluentCrm\App\Models\Funnel::count(),
        ];
    }

    /**
     * The tag registry an agent needs to resolve names to ids.
     *
     * subscribers_count is OPT-IN: withCount() runs a correlated subquery
     * against fc_subscriber_pivot for every row, which is by far the most
     * expensive thing get-crm-context can do — and the count is not what the
     * registry is for. Agents use this to map "newsletter" to an id; when they
     * actually want sizes they can ask, or get an exact one from list-contacts
     * total. Without counts this is a single indexed SELECT over ~50 rows.
     */
    private static function tagsForContext($withCounts = false, $limit = 50)
    {
        return self::segmentRegistry(Tag::query(), $withCounts, $limit);
    }

    private static function listsForContext($withCounts = false, $limit = 50)
    {
        return self::segmentRegistry(Lists::query(), $withCounts, $limit);
    }

    private static function segmentRegistry($query, $withCounts, $limit)
    {
        // Ordering follows the data we actually have: by size when counted,
        // alphabetically otherwise (deterministic and readable, and cheap
        // because title is what the agent matches on anyway).
        if ($withCounts) {
            $query->withCount('subscribers')->orderByDesc('subscribers_count');
        } else {
            $query->orderBy('title', 'ASC');
        }

        $out = [];
        foreach ($query->limit($limit)->get() as $row) {
            $item = [
                'id'    => (int) $row->id,
                'title' => $row->title,
                'slug'  => $row->slug,
            ];
            if ($withCounts) {
                $item['subscribers_count'] = (int) $row->subscribers_count;
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function formatRefList($items, $keyField)
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $key => $item) {
            $name = is_string($key) ? $key : ($item[$keyField] ?? null);
            if (!$name) {
                continue;
            }
            $out[] = [
                'key'    => $name,
                'label'  => $item['label'] ?? $item['title'] ?? $name,
                'is_pro' => !empty($item['is_pro']),
            ];
        }
        return $out;
    }

    /**
     * Design templates the MCP tools allow agents to select. The
     * visual_builder template is intentionally excluded — it's an
     * interactive Gutenberg editor experience, not something an agent
     * should be authoring against. Use `mcp_allowed_design_templates`
     * to publish it deliberately if a custom workflow needs it.
     */
    public static function allowedDesignTemplates()
    {
        $defaults = [
            'plain'       => __('Plain', 'fluent-crm'),
            'classic'     => __('Classic', 'fluent-crm'),
            'raw_html'    => __('Raw HTML', 'fluent-crm'),
            'raw_classic' => __('Raw Classic', 'fluent-crm'),
        ];

        if (method_exists(Helper::class, 'getEmailDesignTemplates')) {
            $all = Helper::getEmailDesignTemplates();
            if (is_array($all) && $all) {
                $excluded = ['visual_builder'];
                $filtered = array_diff_key($all, array_flip($excluded));
                if ($filtered) {
                    $defaults = $filtered;
                }
            }
        }

        /**
         * Filter the design templates surfaced to MCP agents. Useful for
         * adding custom templates a site has registered, or allow-listing
         * visual_builder if the operator really wants agents to use it.
         *
         * @since 2.10.0
         *
         * @param array $templates Map of slug => label.
         */
        return apply_filters('fluent_crm/mcp_allowed_design_templates', $defaults);
    }

    private static function buildDefaultSender()
    {
        $emailSettings = Helper::getGlobalEmailSettings();
        return [
            'from_name'      => $emailSettings['from_name'] ?? '',
            'from_email'     => $emailSettings['from_email'] ?? '',
            'reply_to_name'  => $emailSettings['reply_to_name'] ?? '',
            'reply_to_email' => $emailSettings['reply_to_email'] ?? '',
        ];
    }

    private static function buildCustomFieldSchema()
    {
        $model  = new CustomContactField();
        $global = $model->getGlobalFields();
        $fields = is_array($global) ? ($global['fields'] ?? []) : [];

        $out = [];
        foreach ((array) $fields as $field) {
            $entry = [
                'key'   => $field['slug'] ?? null,
                'label' => $field['label'] ?? null,
                'type'  => $field['type'] ?? null,
            ];
            if (!empty($field['options'])) {
                $entry['options'] = array_values((array) $field['options']);
            }
            if ($entry['key']) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    /**
     * Flatten Helper::getGlobalSmartCodes() into a compact, agent-friendly
     * shape — review #19. Preserves group structure so the agent can find
     * codes by source (contact / custom fields / general / extensions).
     */
    private static function buildSmartCodes()
    {
        if (!method_exists(Helper::class, 'getGlobalSmartCodes')) {
            return [];
        }

        $groups = Helper::getGlobalSmartCodes();
        if (!is_array($groups)) {
            return [];
        }

        $out = [];
        foreach ($groups as $group) {
            $codes = [];
            $shortcodes = $group['shortcodes'] ?? [];
            if (is_array($shortcodes)) {
                foreach ($shortcodes as $code => $label) {
                    $codes[] = ['code' => (string) $code, 'label' => (string) $label];
                }
            }
            $out[] = [
                'key'   => $group['key'] ?? null,
                'title' => $group['title'] ?? null,
                'codes' => $codes,
            ];
        }
        return $out;
    }

    private static function buildGuidelines($access)
    {
        $guidelines = ['Be concise.'];

        if ($access['contacts']) {
            $guidelines[] = 'Use add_tags/remove_tags for contact delta updates. Request include=["custom_fields"] before constructing custom_fields payloads — never invent keys.';
            $guidelines[] = "For OR groups, engagement/activity, custom-field, or relative-date conditions, call get-contact-filter-schema before using advanced_filters.";
        }

        if ($access['emails']) {
            $guidelines[] = "Before sending to a contact, confirm their status is 'subscribed'. Drafts are reviewable; change-campaign-status action=schedule starts delivery.";
            $guidelines[] = 'Use site.timezone when constructing scheduled_at.';
        }

        if ($access['funnels']) {
            $guidelines[] = 'Inspect an automation and subscriber state before changing contact automation status.';
        }

        $default = implode(' ', $guidelines);

        /**
         * Filter the AI guidelines text returned in get-crm-context.
         *
         * Useful for shop-specific nudges (e.g. "always tag MCP-touched contacts
         * with `mcp-edited`"). Keep terse because agents may request it often.
         *
         * @since 2.10.0
         *
         * @param string $default
         */
        return apply_filters('fluent_crm/mcp_ai_guidelines', $default);
    }

}
