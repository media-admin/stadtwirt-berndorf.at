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
 * `get-crm-context` — discovery surface (MCP_PLAN.md § 5.1).
 *
 * The agent calls this once per session to learn:
 *  - who they are (current WP user, FluentCRM permissions)
 *  - what reference data is available (top tags, lists, custom fields)
 *  - which enums are valid (statuses, contact types, design templates, etc.)
 *  - the install's current sender configuration
 *  - guidelines that nudge the agent toward correct usage
 *
 * Cached for 60 seconds per WP user via transient. Invalidation hooks (set up
 * in `MCPInit::init()` lifecycle) clear the cache when underlying reference
 * data changes.
 */
class ContextTools
{
    const CACHE_TTL = 60;

    public static function getContext($params = [])
    {
        $userId = get_current_user_id();
        $cacheKey = 'fluent_crm_mcp_context_' . $userId;

        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $context = self::buildContext($userId);

        set_transient($cacheKey, $context, self::CACHE_TTL);

        return $context;
    }

    private static function buildContext($userId)
    {
        $user = get_user_by('ID', $userId);

        $isAdmin = $user && user_can($user, 'manage_options');

        $you = [
            'wp_user_id'  => (int) $userId,
            'name'        => $user ? $user->display_name : null,
            'email'       => $user ? $user->user_email : null,
            'is_admin'    => (bool) $isAdmin,
            'permissions' => array_values(PermissionManager::currentUserPermissions(false)),
        ];

        $proActive = defined('FLUENTCAMPAIGN');
        $aiState   = self::detectAiProvider();

        $site = [
            'site_url'              => site_url(),
            'fluent_crm_version'    => defined('FLUENTCRM_PLUGIN_VERSION') ? FLUENTCRM_PLUGIN_VERSION : null,
            'fluent_campaign_active' => $proActive,
            'ai_provider_configured' => $aiState['configured'],
            'ai_provider'            => $aiState['provider'],
            'timezone'               => fluentCrmGetTimezoneString(),
            'current_time'           => fluentCrmTimestamp(),
        ];

        $stats = self::buildStats();

        $tags = self::topTagsForContext();
        $lists = self::topListsForContext();

        $availableTriggers = self::formatRefList(apply_filters('fluentcrm_funnel_triggers', []), 'trigger_name');
        $availableActions  = self::formatRefList(apply_filters('fluentcrm_funnel_blocks', [], null), 'action_name');

        $enums = [
            'contact_statuses'        => array_values(fluentcrm_subscriber_statuses()),
            'sms_statuses'            => array_values(fluentcrm_subscriber_sms_statuses()),
            'contact_types'           => array_values(fluentcrm_contact_types()),
            'campaign_statuses'       => ['draft', 'scheduled', 'pending-scheduled', 'processing', 'working', 'paused', 'archived'],
            'design_templates'        => array_keys(self::allowedDesignTemplates()),
            'funnel_statuses'         => ['draft', 'published'],
            'funnel_subscriber_statuses' => ['active', 'waiting', 'completed', 'cancelled', 'skipped'],
            'note_types'              => ['note', 'call', 'email', 'meeting', 'quote'],
        ];

        $defaultSender = self::buildDefaultSender();

        $customFieldsSchema = ['contact' => self::buildCustomFieldSchema()];

        return [
            'you'                  => $you,
            'site'                 => $site,
            'stats'                => $stats,
            'tags'                 => $tags,
            'lists'                => $lists,
            'available_triggers'   => $availableTriggers,
            'available_actions'    => $availableActions,
            'enums'                => $enums,
            'default_sender'       => $defaultSender,
            'custom_fields_schema' => $customFieldsSchema,
            'smart_codes'          => self::buildSmartCodes(),
            'safety_levels'        => self::buildSafetyLevels(),
            'rate_hints'           => self::buildRateHints(),
            'mcp_capabilities'     => self::buildCapabilities(),
            'guidelines'           => self::buildGuidelines(),
        ];
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
            'fluent-crm/send-test-email'                  => 'safe_render',
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
            'version' => '1.4.0',
            'supports' => [
                'dry_run_apply_segments',
                'send_test_email',
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
            ],
            'deprecated'             => [],
            'breaking_changes_pending' => [],
        ]);
    }

    private static function buildStats()
    {
        $stats = (new Stats())->getCounts();

        $todayStart = (new \DateTime('today', new \DateTimeZone(fluentCrmGetTimezoneString())))->format('Y-m-d H:i:s');
        $sevenDaysAgo = gmdate('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS));

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

    private static function topTagsForContext($limit = 50)
    {
        $tags = Tag::withCount('subscribers')
            ->orderByDesc('subscribers_count')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($tags as $tag) {
            $out[] = [
                'id'                => (int) $tag->id,
                'title'             => $tag->title,
                'slug'              => $tag->slug,
                'subscribers_count' => (int) $tag->subscribers_count,
            ];
        }
        return $out;
    }

    private static function topListsForContext($limit = 50)
    {
        $lists = Lists::withCount('subscribers')
            ->orderByDesc('subscribers_count')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($lists as $list) {
            $out[] = [
                'id'                => (int) $list->id,
                'title'             => $list->title,
                'slug'              => $list->slug,
                'subscribers_count' => (int) $list->subscribers_count,
            ];
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

    private static function detectAiProvider()
    {
        $aiSettings = fluentcrm_get_option('ai_settings', []);
        $provider   = '';
        $configured = false;

        if (!empty($aiSettings['active_provider'])) {
            $provider = sanitize_key($aiSettings['active_provider']);
            $providerCfg = $aiSettings[$provider] ?? [];
            $configured  = !empty($providerCfg['api_key']);
        }

        return ['provider' => $provider ?: null, 'configured' => $configured];
    }

    private static function buildGuidelines()
    {
        $default = "Be concise. When sending to a contact, confirm their status is 'subscribed'. " .
            "Use add_tags/remove_tags for delta updates. Drafts are safe — only change-campaign-status " .
            "with action=schedule causes sending. The site timezone is in site.timezone — use it when " .
            "constructing scheduled_at. Use custom_fields_schema to construct valid custom_fields payloads " .
            "on upsert-contact — never invent keys. Filter shape (universal): {search, tags[], lists[], " .
            "statuses[], contact_type, created_after, created_before, sort_by, sort_type}.";

        /**
         * Filter the AI guidelines text returned in get-crm-context.
         *
         * Useful for shop-specific nudges (e.g. "always tag MCP-touched contacts
         * with `mcp-edited`"). Keep terse — the text ships in every session's
         * tool-discovery payload.
         *
         * @since 2.10.0
         *
         * @param string $default
         */
        return apply_filters('fluent_crm/mcp_ai_guidelines', $default);
    }

    /**
     * Invalidate cached context for every user. Hooked from MCPInit on the
     * relevant FluentCRM events.
     */
    public static function invalidateCache()
    {
        global $wpdb;
        $like = $wpdb->esc_like('_transient_fluent_crm_mcp_context_') . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
        $like = $wpdb->esc_like('_transient_timeout_fluent_crm_mcp_context_') . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
    }
}
