<?php

namespace FluentCrm\App\Modules\MCP;

use FluentCrm\App\Modules\MCP\Tools\CampaignTools;
use FluentCrm\App\Modules\MCP\Tools\ContactTools;
use FluentCrm\App\Modules\MCP\Tools\ContextTools;
use FluentCrm\App\Modules\MCP\Tools\EmailTools;
use FluentCrm\App\Modules\MCP\Tools\FunnelTools;
use FluentCrm\App\Modules\MCP\Tools\SegmentTools;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\PermissionManager;

/**
 * Single source of truth for every FluentCRM MCP ability.
 *
 * Per MCP_PLAN.md § 12 (token discipline) — descriptions are tight (≤30 tokens),
 * input schemas omit redundant property descriptions, and the universal filter
 * shape is referenced by pointer rather than inlined into each tool.
 *
 * Adding a tool: append an entry to `getDefinitions()`. Adding a Pro tool:
 * push it from `fluentcampaign-pro` via the `fluent_crm/mcp_loaded` action.
 */
class AbilitiesRegistrar
{
    public static function getDefinitions()
    {
        return [
            'fluent-crm/get-crm-context' => [
                'label'       => __('Get CRM Context', 'fluent-crm'),
                'description' => __('Compact permission-scoped discovery. Always returns identity, callable tool groups, site/timezone, and section availability. Omitted include adds overview + capabilities; [] is identity/site only; explicit values return exactly those sections. The default no longer includes tags/lists, sender, custom fields, smart codes, automation catalog, safety, rate hints, or guidelines — request them through include. The segments section is a teaser capped at 50 tags and 50 lists: use list-tags / list-lists to enumerate the full registries.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'include_segment_counts' => [
                            'type'        => 'boolean',
                            'default'     => false,
                            'description' => 'Add subscribers_count to each tag/list in the "segments" section. Off by default — it costs a per-row count over the pivot table. For an exact size of a specific segment, call list-contacts with that tag/list and read `total`.',
                        ],
                        'include' => [
                            'type'        => 'array',
                            'description' => 'Omitted = compact overview + capabilities; [] = identity/site only; explicit = exactly those sections; ["all"] requests every authorized section.',
                            'items'       => [
                                'type' => 'string',
                                'enum' => [
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
                                    'all',
                                ],
                            ],
                        ],
                    ],
                ],
                'execute_callback'    => [ContextTools::class, 'getContext'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_view_dashboard')
                        || PermissionManager::currentUserCan('fcrm_read_contacts')
                        || PermissionManager::currentUserCan('fcrm_read_emails')
                        || PermissionManager::currentUserCan('fcrm_read_funnels')
                        || PermissionManager::currentUserCan('fcrm_manage_settings');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-contacts' => [
                'label'       => __('List Contacts', 'fluent-crm'),
                'description' => __('List/filter contacts with tags + lists inline. To fetch specific known contacts, pass `emails` (batch lookup, one call) rather than calling get-contact repeatedly. `search` matches name/email/custom field values. Filter fields are strictly validated — see get-crm-context.enums for valid status values. For conditions the flat filters cannot express (OR groups, engagement/activity, custom fields, relative dates) pass advanced_filters — call get-contact-filter-schema FIRST.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'                => ['type' => 'string', 'description' => 'Full-text across first_name, last_name, email, and custom field values.'],
                        'emails'                => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Exact-address lookup — the way to fetch several known contacts in ONE call instead of repeating get-contact. Max 100, case-insensitive. Addresses with no contact record come back in `not_found`. Narrows every other filter (composes with tags/lists/statuses AND with advanced_filters). An invalid address is rejected, not skipped.'],
                        'tags'                  => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'Tag ids or slugs/titles. Mixed allowed.'],
                        'lists'                 => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'List ids or slugs/titles. Mixed allowed.'],
                        'statuses'              => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'See get-crm-context.enums.contact_statuses.'],
                        'sms_statuses'          => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'See get-crm-context.enums.sms_statuses.'],
                        'contact_type'          => ['type' => 'string', 'enum' => ['lead', 'customer']],
                        'created_after'         => ['type' => 'string', 'description' => 'Inclusive lower bound. YYYY-MM-DD, "YYYY-MM-DD HH:MM:SS" (site timezone), or full ISO 8601 with offset. Unparseable values are rejected, not ignored.'],
                        'created_before'        => ['type' => 'string', 'description' => 'Inclusive upper bound, same formats. A date-only value means midnight at the START of that day.'],
                        'advanced_filters'      => ['type' => 'array', 'items' => ['type' => ['object', 'array']], 'description' => 'Condition groups {property, operator, value} — outer array = OR groups, inner = AND. Call get-contact-filter-schema FIRST for properties/operators/format. Composes with search/contact_type/created_*, NOT with tags/lists/statuses (express those as segment.* conditions).'],
                        'sort_by'               => ['type' => 'string', 'enum' => ['id', 'email', 'first_name', 'last_name', 'created_at', 'last_activity'], 'default' => 'id'],
                        'sort_type'             => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC'],
                        'page'                  => ['type' => 'integer', 'default' => 1],
                        'per_page'              => ['type' => 'integer', 'default' => 15, 'description' => 'Max 100.'],
                        'include_custom_fields' => ['type' => 'boolean', 'default' => false, 'description' => 'Inline each contact\'s custom field values (heavier).'],
                    ],
                ],
                'execute_callback'    => [ContactTools::class, 'listContacts'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/get-contact-filter-schema' => [
                'label'       => __('Get Contact Filter Schema', 'fluent-crm'),
                'description' => __('The reference for the advanced_filters parameter — the same condition-group engine the admin "Advanced Filter" UI runs. Returns every filterable property (operators, value types, options), the payload format, and a worked example. Call once before passing advanced_filters.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
                'execute_callback'    => [ContactTools::class, 'getFilterSchema'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/get-contact' => [
                'label'       => __('Get Contact', 'fluent-crm'),
                'description' => __('Full contact profile for ONE contact. Provide contact_id OR email — for several contacts at once, call list-contacts with `emails` instead of looping this tool. Omit include for the default sections (notes, email_history, automations); pass [] for identity/fields only; pass an explicit list for exactly those. Optional cross-plugin sections (activity, purchase_history, support_tickets, info_widgets) carry an availability marker when inactive.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'integer', 'description' => 'Provide this OR email.'],
                        'email'      => ['type' => 'string', 'description' => 'Provide this OR contact_id.'],
                        'include'    => [
                            'type'        => 'array',
                            'description' => 'Omitted = default sections; [] = none (metadata only); explicit list = exactly those.',
                            'items'       => ['type' => 'string', 'enum' => ['notes', 'email_history', 'automations', 'activity', 'purchase_history', 'support_tickets', 'info_widgets']],
                        ],
                        'body_format'         => ['type' => 'string', 'enum' => ['text', 'html', 'both'], 'default' => 'text', 'description' => 'Note body representation. Default text — html/both cost more tokens.'],
                        'notes_limit'         => ['type' => 'integer', 'default' => 10, 'description' => 'Max 50.'],
                        'note_body_max_chars' => ['type' => 'integer', 'default' => 800, 'description' => 'Per-note body excerpt cap; longer bodies return truncated=true + original_length. Max 20000.'],
                        'automations_limit'   => ['type' => 'integer', 'default' => 25, 'description' => 'Max 100.'],
                        'email_history_limit' => ['type' => 'integer', 'default' => 10, 'description' => 'Max 50.'],
                    ],
                ],
                'execute_callback'    => [ContactTools::class, 'getContact'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-tags' => [
                'label'       => __('List Tags', 'fluent-crm'),
                'description' => __('The full tag registry, searchable and paginated. Use this rather than get-crm-context whenever you need more than the 50 tags that call returns, or need per-tag size/recency. Tags are NOT FluentCRM "Segments" — that is a separate feature (Contacts > Segments); do not conflate them. To count contacts matching a tag AND other conditions, call list-contacts with that tag and read `total`.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'            => ['type' => 'string', 'description' => 'Matches title, slug, and description.'],
                        'ids'               => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'Resolve specific tags by id, slug, or title in one call. Max 100. Refs matching nothing come back in `not_found` rather than being dropped.'],
                        'include_counts'    => ['type' => 'boolean', 'default' => false, 'description' => 'Add subscribers_count — EVERY attached contact regardless of status, so it counts unsubscribed and bounced contacts too. Off by default; it costs a pass over the contact-tag pivot.'],
                        'include_last_used' => ['type' => 'boolean', 'default' => false, 'description' => 'Add last_used_at: the most recent time this tag was newly applied to a contact — the signal for finding stale tags. Re-applying a tag a contact already has does not move it. null means never applied; null WITH last_used_at_unknown=true means contacts are attached but their rows predate timestamp stamping (do NOT treat that as unused). Off by default; costs a pass over the pivot.'],
                        'sort_by'           => ['type' => 'string', 'enum' => ['title', 'id', 'slug', 'created_at', 'updated_at', 'subscribers_count'], 'default' => 'title', 'description' => 'subscribers_count implies include_counts. last_used_at is computed after paging and cannot be sorted here — sort the returned page yourself.'],
                        'sort_type'         => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'ASC'],
                        'page'              => ['type' => 'integer', 'default' => 1],
                        'per_page'          => ['type' => 'integer', 'default' => 15, 'description' => 'Max 100.'],
                    ],
                ],
                'execute_callback'    => [SegmentTools::class, 'listTags'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-lists' => [
                'label'       => __('List Contact Lists', 'fluent-crm'),
                'description' => __('The full contact-list registry, searchable and paginated. Same parameters and metrics as list-tags. Use rather than get-crm-context when you need more than the 50 lists that call returns.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'            => ['type' => 'string', 'description' => 'Matches title, slug, and description.'],
                        'ids'               => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'Resolve specific lists by id, slug, or title in one call. Max 100. Refs matching nothing come back in `not_found`.'],
                        'include_counts'    => ['type' => 'boolean', 'default' => false, 'description' => 'Add subscribers_count — every attached contact regardless of status. Off by default; costs a pass over the contact-list pivot.'],
                        'include_last_used' => ['type' => 'boolean', 'default' => false, 'description' => 'Add last_used_at: most recent time this list was newly applied to a contact. See list-tags for the null / last_used_at_unknown semantics.'],
                        'sort_by'           => ['type' => 'string', 'enum' => ['title', 'id', 'slug', 'created_at', 'updated_at', 'subscribers_count'], 'default' => 'title'],
                        'sort_type'         => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'ASC'],
                        'page'              => ['type' => 'integer', 'default' => 1],
                        'per_page'          => ['type' => 'integer', 'default' => 15, 'description' => 'Max 100.'],
                    ],
                ],
                'execute_callback'    => [SegmentTools::class, 'listLists'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-campaigns' => [
                'label'       => __('List Campaigns', 'fluent-crm'),
                'description' => __('List campaigns. Stats are opt-in because they add aggregate queries per campaign; pass include_stats=true when engagement data is needed. Excludes one-off email-to-contact records by default — flip include_one_offs for a unified "what was sent recently" view.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'           => ['type' => 'string', 'description' => 'Matches campaign title.'],
                        'statuses'         => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'See get-crm-context.enums.campaign_statuses. With include_one_offs=true this also accepts get-crm-context.enums.one_off_statuses, which is the only way to match one-off rows — their status is descriptive, not a lifecycle stage.'],
                        'sort_by'          => ['type' => 'string', 'enum' => ['id', 'created_at', 'updated_at', 'scheduled_at'], 'default' => 'created_at'],
                        'sort_type'        => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC'],
                        'include_stats'    => ['type' => 'boolean', 'default' => false, 'description' => 'Opt in to per-campaign engagement stats. Omitted/false keeps list scans cheap; true adds aggregate queries per row.'],
                        'include_one_offs' => ['type' => 'boolean', 'default' => false, 'description' => 'Also include the per-recipient custom-email rows created by send-email-to-contact.'],
                        'page'             => ['type' => 'integer', 'default' => 1],
                        'per_page'         => ['type' => 'integer', 'default' => 15, 'description' => 'Max 100.'],
                    ],
                ],
                'execute_callback'    => [CampaignTools::class, 'listCampaigns'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_emails');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/get-campaign' => [
                'label'       => __('Get Campaign', 'fluent-crm'),
                'description' => __('Campaign details. Default include: stats. Optional: subjects (A/B), link_report, recipients_estimate.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'campaign_id' => ['type' => 'integer'],
                        'include'     => [
                            'type'        => 'array',
                            'description' => 'Omitted = ["stats"]; [] = none; explicit list = exactly those.',
                            'items'       => ['type' => 'string', 'enum' => ['stats', 'subjects', 'link_report', 'recipients_estimate']],
                        ],
                        'body_format' => ['type' => 'string', 'enum' => ['text', 'html', 'both'], 'default' => 'both', 'description' => 'Email body representation. Default both (backward compatible); pick text or html to save tokens.'],
                    ],
                    'required' => ['campaign_id'],
                ],
                'execute_callback'    => [CampaignTools::class, 'getCampaign'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_emails');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-automations' => [
                'label'       => __('List Automations', 'fluent-crm'),
                'description' => __('List/filter automations (funnels) with subscriber counts inline.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'    => ['type' => 'string'],
                        'statuses'  => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['draft', 'published']]],
                        'sort_by'   => ['type' => 'string', 'enum' => ['id', 'title', 'status', 'updated_at'], 'default' => 'id'],
                        'sort_type' => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC'],
                        'page'      => ['type' => 'integer', 'default' => 1],
                        'per_page'  => ['type' => 'integer', 'default' => 15],
                        'include_last_enrolled' => ['type' => 'boolean', 'default' => false, 'description' => 'Add last_enrolled_at: the last time a contact entered this funnel, i.e. the last time its trigger fired. Use it to find automations that are still published but effectively retired — status alone cannot tell those apart, and a low in_progress count cannot either (a healthy fast-completing funnel also sits near zero). null means nobody has ever entered. Off by default; it costs a pass over the funnel-subscriber table.'],
                    ],
                ],
                'execute_callback'    => [FunnelTools::class, 'listAutomations'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_funnels');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-funnel-subscribers' => [
                'label'       => __('List Funnel Subscribers', 'fluent-crm'),
                'description' => __('List contacts enrolled in a funnel by status. Use to find candidates for update-contact-automation-status when you only know the funnel.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'funnel_id' => ['type' => 'integer'],
                        'statuses'  => [
                            'type'  => 'array',
                            'items' => ['type' => 'string', 'enum' => ['active', 'waiting', 'completed', 'cancelled', 'skipped']],
                            'description' => 'Defaults to ["active"].',
                        ],
                        'page'      => ['type' => 'integer', 'default' => 1],
                        'per_page'  => ['type' => 'integer', 'default' => 15],
                    ],
                    'required' => ['funnel_id'],
                ],
                'execute_callback'    => [FunnelTools::class, 'listFunnelSubscribers'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_funnels');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/get-automation' => [
                'label'       => __('Get Automation', 'fluent-crm'),
                'description' => __('Funnel details with sequences and per-step report by default. Embedded email bodies in send_custom_email steps are stripped unless include_bodies=true (saves tokens).', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'funnel_id'      => ['type' => 'integer'],
                        'include'        => [
                            'type'        => 'array',
                            'description' => 'Defaults to ["sequences","report"]. Pass [] for metadata only.',
                            'items'       => ['type' => 'string', 'enum' => ['sequences', 'report']],
                        ],
                        'include_bodies' => ['type' => 'boolean', 'default' => false, 'description' => 'Return full email bodies inside send_custom_email step settings. Off by default — large funnels can blow agent context.'],
                    ],
                    'required' => ['funnel_id'],
                ],
                'execute_callback'    => [FunnelTools::class, 'getAutomation'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_funnels');
                },
                'annotations' => ['readonly' => true],
            ],

            // -----------------------------------------------------------------
            // Phase 3 — write tools
            // -----------------------------------------------------------------

            'fluent-crm/upsert-contact' => [
                'label'       => __('Create or Update Contact', 'fluent-crm'),
                'description' => __('Create or update a contact by id or email. status changes fire native hooks. Source stamps "mcp" only on create — preserved on update. new_email renames in place.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'           => ['type' => 'integer', 'description' => 'Provide this OR email for lookup.'],
                        'email'                => ['type' => 'string', 'description' => 'Provide this OR contact_id for lookup. Required for create.'],
                        'new_email'            => ['type' => 'string', 'description' => 'Renames an existing contact in place. Errors if another contact already uses this email.'],
                        'first_name'           => ['type' => 'string'],
                        'last_name'            => ['type' => 'string'],
                        'prefix'               => ['type' => 'string'],
                        'phone'                => ['type' => 'string'],
                        'status'               => ['type' => 'string', 'enum' => array_values(fluentcrm_subscriber_statuses()), 'description' => 'See get-crm-context.enums.contact_statuses.'],
                        'contact_type'         => ['type' => 'string', 'enum' => ['lead', 'customer']],
                        'address'              => [
                            'type'        => 'object',
                            'description' => '{line_1, line_2, city, state, postal_code, country (ISO-2)}. Empty fields ignored; unknown keys rejected.',
                            'properties'  => [
                                'line_1'      => ['type' => 'string'],
                                'line_2'      => ['type' => 'string'],
                                'city'        => ['type' => 'string'],
                                'state'       => ['type' => 'string'],
                                'postal_code' => ['type' => 'string'],
                                'country'     => ['type' => 'string', 'description' => 'ISO-2'],
                            ],
                            'additionalProperties' => false,
                        ],
                        'date_of_birth'        => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                        'timezone'             => ['type' => 'string'],
                        'source'               => ['type' => 'string', 'description' => 'Defaults to "mcp" on create. Omit on updates to preserve existing source.'],
                        'custom_fields'        => ['type' => 'object', 'description' => 'Map of custom field slug → value. See get-crm-context.custom_fields_schema.'],
                        'add_tags'             => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'remove_tags'          => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'add_lists'            => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'remove_lists'         => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'auto_create_tags'     => ['type' => 'boolean', 'default' => false, 'description' => 'Re-checks fcrm_manage_contact_cats. Off by default for safety.'],
                        'auto_create_lists'    => ['type' => 'boolean', 'default' => false],
                        'double_optin'         => ['type' => 'boolean', 'default' => false, 'description' => 'When status=pending, send opt-in email. No-op for other statuses.'],
                        'if_exists'            => ['type' => 'string', 'enum' => ['merge', 'skip', 'error'], 'default' => 'merge', 'description' => 'merge: update existing fields. skip: leave row untouched. error: return contact_exists.'],
                        'status_change_reason' => ['type' => 'string', 'description' => 'When provided AND status changes, auto-creates an audit note ("Status changed via MCP").'],
                    ],
                ],
                'execute_callback'    => [ContactTools::class, 'upsertContact'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contacts');
                },
                // Destructive: an update can overwrite existing fields/status,
                // rename the email, and remove_tags/remove_lists. openWorldHint=true
                // because the optional double_optin path sends an external email.
                'annotations' => ['destructive' => true, 'openWorldHint' => true],
            ],

            'fluent-crm/bulk-upsert-contacts' => [
                'label'       => __('Bulk Create or Update Contacts', 'fluent-crm'),
                'description' => __('Batch create/update up to 500 contacts. Returns per-row {created, updated, skipped, invalid}. auto_create defaults to true here (matches CSV-import expectations) — opposite of upsert-contact.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contacts'          => [
                            'type'        => 'array',
                            'description' => 'Array of contact objects. Allowed per-row keys: email (required), first_name, last_name, prefix, phone, status, contact_type, date_of_birth, timezone, source, address, custom_fields, tags, lists. Any other key (user_id, company_id, ip, timestamps, …) is ignored, not persisted.',
                            'items'       => [
                                'type'       => 'object',
                                'properties' => [
                                    'email'         => ['type' => 'string'],
                                    'first_name'    => ['type' => 'string'],
                                    'last_name'     => ['type' => 'string'],
                                    'prefix'        => ['type' => 'string'],
                                    'phone'         => ['type' => 'string'],
                                    'status'        => ['type' => 'string'],
                                    'contact_type'  => ['type' => 'string', 'enum' => ['lead', 'customer']],
                                    'date_of_birth' => ['type' => 'string'],
                                    'timezone'      => ['type' => 'string'],
                                    'source'        => ['type' => 'string'],
                                    // Bulk-only: deliberately NO `type` here. Declaring
                                    // type:object makes the adapter reject the WHOLE batch on a
                                    // non-object address before the per-row handler runs, which
                                    // breaks bulk partial semantics. Without `type`, WP's schema
                                    // validator skips this property so a non-object passes through
                                    // to the callback, which emits a per-row invalid_address_not_object
                                    // warning and still saves the row. Single-upsert address stays a
                                    // strict object with additionalProperties:false.
                                    'address'       => [
                                        'description' => 'Object with keys line_1, line_2, city, state, postal_code, country (ISO-2). A non-object value or unknown keys produce a per-row warning (invalid_address_not_object / unknown_address_keys) and the row still saves — bulk never rejects a whole batch.',
                                        'properties'  => [
                                            'line_1'      => ['type' => 'string'],
                                            'line_2'      => ['type' => 'string'],
                                            'city'        => ['type' => 'string'],
                                            'state'       => ['type' => 'string'],
                                            'postal_code' => ['type' => 'string'],
                                            'country'     => ['type' => 'string'],
                                        ],
                                    ],
                                    'custom_fields' => ['type' => 'object'],
                                    'tags'          => ['type' => 'array', 'maxItems' => ContactTools::MAX_SEGMENT_REFS_PER_ROW, 'items' => ['type' => ['string', 'integer']]],
                                    'lists'         => ['type' => 'array', 'maxItems' => ContactTools::MAX_SEGMENT_REFS_PER_ROW, 'items' => ['type' => ['string', 'integer']]],
                                ],
                                'required'   => ['email'],
                            ],
                        ],
                        'if_exists'         => ['type' => 'string', 'enum' => ['merge', 'skip', 'error'], 'default' => 'merge'],
                        'double_optin'      => ['type' => 'boolean', 'default' => false],
                        'auto_create_tags'  => ['type' => 'boolean', 'default' => true,  'description' => 'Default true here (bulk-import context). Re-checks fcrm_manage_contact_cats.'],
                        'auto_create_lists' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => ['contacts'],
                ],
                'execute_callback'    => [ContactTools::class, 'bulkUpsertContacts'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contacts');
                },
                'annotations' => ['bulk' => true, 'destructive' => true, 'openWorldHint' => true],
            ],

            'fluent-crm/delete-contact' => [
                'label'       => __('Delete Contact', 'fluent-crm'),
                'description' => __('Hard-delete a contact. Optional delete_emails wipes the email log too. Cannot be undone.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'    => ['type' => 'integer'],
                        'email'         => ['type' => 'string'],
                        'delete_emails' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'execute_callback'    => [ContactTools::class, 'deleteContact'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contacts_delete');
                },
                'annotations' => ['destructive' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/apply-segments-to-contacts' => [
                'label'       => __('Apply Tags/Lists Across Contacts', 'fluent-crm'),
                'description' => __('Add/remove tags and lists across many contacts. Provide contact_ids OR filter, not both. Always dry_run first for filter-based applies. Response includes applied_contact_ids for precise reversal. Cap 5000.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_ids'       => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Explicit ids. Use OR filter, not both.'],
                        'filter'            => ['type' => 'object', 'description' => 'Universal filter — {emails, tags, lists, statuses, contact_type, search, created_after, created_before, advanced_filters}. Same shape as list-contacts; use emails to target a known set of addresses (max 100). For advanced_filters call get-contact-filter-schema first. See get-crm-context.guidelines.'],
                        'add_tags'          => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'remove_tags'       => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'add_lists'         => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'remove_lists'      => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                        'auto_create_tags'  => ['type' => 'boolean', 'default' => false, 'description' => 'Re-checks fcrm_manage_contact_cats. Suppressed during dry_run so previews never leave orphans behind.'],
                        'auto_create_lists' => ['type' => 'boolean', 'default' => false],
                        'dry_run'           => ['type' => 'boolean', 'default' => false, 'description' => 'Preview matched count, batches_required, and tags/lists_would_create without applying. Bypasses the cap (you see real matched_contacts even if > 5000).'],
                    ],
                ],
                'execute_callback'    => [ContactTools::class, 'applySegmentsToContacts'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contacts');
                },
                'annotations' => ['bulk' => true, 'destructive' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/manage-tag' => [
                'label'       => __('Manage Tag', 'fluent-crm'),
                'description' => __('Create, update, delete, or merge tags. delete + merge are destructive (re-pivot or detach subscribers).', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'action'         => ['type' => 'string', 'enum' => ['create', 'update', 'delete', 'merge']],
                        'tag_id'         => ['type' => 'integer', 'description' => 'Required for update/delete.'],
                        'title'          => ['type' => 'string'],
                        'slug'           => ['type' => 'string'],
                        'description'    => ['type' => 'string'],
                        'force'          => ['type' => 'boolean', 'default' => false, 'description' => 'delete only — allow deletion when subscribers are still attached.'],
                        'from_tag_ids'   => ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'integer'], 'description' => 'merge only — max 100 source tags whose subscribers move to to_tag_id and which then get deleted. Actual merge is capped at 5,000 source pivot rows by default; use dry_run for exact preflight counts.'],
                        'to_tag_id'      => ['type' => 'integer', 'description' => 'merge only — destination tag.'],
                        'dry_run'        => ['type' => 'boolean', 'default' => false, 'description' => 'merge only — return source pivot rows, unique subscribers, per-source counts, and cap status without changing memberships or deleting sources.'],
                    ],
                    'required' => ['action'],
                ],
                'execute_callback'    => [SegmentTools::class, 'manageTag'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contact_cats')
                        || PermissionManager::currentUserCan('fcrm_manage_contact_cats_delete');
                },
                'annotations' => ['destructive' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/manage-list' => [
                'label'       => __('Manage List', 'fluent-crm'),
                'description' => __('Create, update, delete, or merge lists. delete + merge are destructive (re-pivot or detach subscribers).', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'action'         => ['type' => 'string', 'enum' => ['create', 'update', 'delete', 'merge']],
                        'list_id'        => ['type' => 'integer'],
                        'title'          => ['type' => 'string'],
                        'slug'           => ['type' => 'string'],
                        'description'    => ['type' => 'string'],
                        'force'          => ['type' => 'boolean', 'default' => false],
                        'from_list_ids'  => ['type' => 'array', 'maxItems' => 100, 'items' => ['type' => 'integer'], 'description' => 'merge only — max 100 source lists. Actual merge is capped at 5,000 source pivot rows by default; use dry_run for exact preflight counts.'],
                        'to_list_id'     => ['type' => 'integer'],
                        'dry_run'        => ['type' => 'boolean', 'default' => false, 'description' => 'merge only — return source pivot rows, unique subscribers, per-source counts, and cap status without changing memberships or deleting sources.'],
                    ],
                    'required' => ['action'],
                ],
                'execute_callback'    => [SegmentTools::class, 'manageList'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contact_cats')
                        || PermissionManager::currentUserCan('fcrm_manage_contact_cats_delete');
                },
                'annotations' => ['destructive' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/delete-contact-note' => [
                'label'       => __('Delete Contact Note', 'fluent-crm'),
                'description' => __('Delete a single subscriber note by id. Find the note id via get-contact include=["notes"]. Other notes and email history are untouched.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'note_id' => ['type' => 'integer'],
                    ],
                    'required' => ['note_id'],
                ],
                'execute_callback'    => [ContactTools::class, 'deleteContactNote'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contacts');
                },
                'annotations' => ['destructive' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/add-contact-note' => [
                'label'       => __('Add Contact Note', 'fluent-crm'),
                'description' => __('Add a note to a contact. Provide contact_id OR email plus title + description. Types: note, call, email, meeting, quote. Description supports HTML.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'  => ['type' => 'integer', 'description' => 'Provide this OR email.'],
                        'email'       => ['type' => 'string', 'description' => 'Provide this OR contact_id.'],
                        'type'        => ['type' => 'string', 'enum' => ['note', 'call', 'email', 'meeting', 'quote'], 'default' => 'note'],
                        'title'       => ['type' => 'string', 'description' => 'Max 192 chars.'],
                        'description' => ['type' => 'string', 'description' => 'HTML or plain. SmartCodes resolve.'],
                        'created_at'  => ['type' => 'string', 'description' => 'ISO 8601, defaults to now (site timezone).'],
                    ],
                    'required' => ['title', 'description'],
                ],
                'execute_callback'    => [ContactTools::class, 'addContactNote'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contacts');
                },
                // Additive, local-only. Not idempotent (a retry adds a duplicate note).
                'annotations' => ['destructive' => false, 'openWorldHint' => false],
            ],

            'fluent-crm/render-email-preview' => [
                'label'       => __('Render Email Preview', 'fluent-crm'),
                'description' => __('Render the final HTML + resolved SmartCodes for a saved campaign or an inline draft, using the exact sender/footer settings a live send would use. Sends nothing, creates no record, enrolls no one — safe to auto-approve. For an actual test delivery use send-test-email.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'campaign_id'          => ['type' => 'integer', 'description' => 'Preview this saved campaign\'s body / subject / settings.'],
                        'subject'              => ['type' => 'string', 'description' => 'Override or supply a subject when not using campaign_id.'],
                        'body'                 => ['type' => 'string', 'description' => 'Override or supply a body when not using campaign_id.'],
                        'pre_header'           => ['type' => 'string'],
                        'design_template'      => [
                            'type'    => 'string',
                            'enum'    => array_keys(ContextTools::allowedDesignTemplates()),
                        ],
                        'from_name'        => ['type' => 'string', 'description' => 'Sender parity with the live send — defaults to the site sender.'],
                        'from_email'       => ['type' => 'string'],
                        'reply_to_name'    => ['type' => 'string'],
                        'reply_to_email'   => ['type' => 'string'],
                        'is_transactional' => ['type' => 'string', 'enum' => ['yes', 'no'], 'default' => 'no', 'description' => 'When "yes", the footer is omitted — same as the live send.'],
                        'disable_footer'   => ['type' => 'string', 'enum' => ['yes', 'no'], 'description' => 'Explicit override of the auto-derived footer behavior.'],
                        'against_contact_id'    => ['type' => 'integer', 'description' => 'Resolve SmartCodes against this contact. Defaults to any subscribed contact.'],
                        'against_contact_email' => ['type' => 'string'],
                        'settings'              => ['type' => 'object', 'description' => 'Optional passthrough merged into the render settings (template_config, footer_settings).'],
                    ],
                ],
                'execute_callback'    => [EmailTools::class, 'renderEmailPreview'],
                'permission_callback' => function () {
                    // Requires BOTH capabilities: the rendered body and
                    // rendered_against expose the resolved contact's PII
                    // (email/name via SmartCodes), so the caller must be
                    // authorized to read contacts too — an email-only manager
                    // must not exfiltrate contact data through a preview.
                    return PermissionManager::currentUserCan('fcrm_read_emails')
                        && PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/send-test-email' => [
                'label'       => __('Send Test Email', 'fluent-crm'),
                'description' => __('Deliver a REAL test copy of a saved campaign or inline draft to to_email (subject prefixed "TEST:"). Always sends an external email — for a no-send render use render-email-preview instead. Does not enroll the recipient, create a campaign record, or log to email_history. Accepts the same sender/footer params as send-email-to-contact so the test matches the live send.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'to_email'             => ['type' => 'string', 'description' => 'Where to send the test. Defaults to the current WP user\'s email.'],
                        'campaign_id'          => ['type' => 'integer', 'description' => 'Send a test copy of this saved campaign\'s body / subject / settings.'],
                        'subject'              => ['type' => 'string', 'description' => 'Override or supply a subject when not using campaign_id.'],
                        'body'                 => ['type' => 'string', 'description' => 'Override or supply a body when not using campaign_id.'],
                        'pre_header'           => ['type' => 'string'],
                        'design_template'      => [
                            'type'    => 'string',
                            'enum'    => array_keys(ContextTools::allowedDesignTemplates()),
                        ],
                        'from_name'        => ['type' => 'string', 'description' => 'Same semantics as send-email-to-contact — defaults to the site sender.'],
                        'from_email'       => ['type' => 'string'],
                        'reply_to_name'    => ['type' => 'string'],
                        'reply_to_email'   => ['type' => 'string'],
                        'is_transactional' => ['type' => 'string', 'enum' => ['yes', 'no'], 'default' => 'no', 'description' => 'When "yes", the footer is omitted — same as the live send.'],
                        'disable_footer'   => ['type' => 'string', 'enum' => ['yes', 'no'], 'description' => 'Explicit override of the auto-derived footer behavior.'],
                        'against_contact_id'   => ['type' => 'integer', 'description' => 'Resolve smartcodes against this contact. Requires contact-read permission. Defaults to a contact matching to_email, then any subscribed contact.'],
                        'against_contact_email' => ['type' => 'string', 'description' => 'Same as against_contact_id, by email. Requires contact-read permission.'],
                    ],
                ],
                'execute_callback'    => [EmailTools::class, 'sendTestEmail'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_emails');
                },
                'annotations' => ['destructive' => false, 'openWorldHint' => true],
            ],

            'fluent-crm/send-email-to-contact' => [
                'label'       => __('Send Email to Contact', 'fluent-crm'),
                'description' => __('Send a one-off email to a subscribed/transactional contact. Routes through normal queue + bounce + FluentSMTP. SmartCodes resolve. Persists a custom_email_campaign record (hidden from list-campaigns by default).', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'       => ['type' => 'integer', 'description' => 'Provide this OR email.'],
                        'email'            => ['type' => 'string', 'description' => 'Provide this OR contact_id.'],
                        'subject'          => ['type' => 'string'],
                        'body'             => ['type' => 'string', 'description' => 'HTML or plain. SmartCodes resolve.'],
                        'pre_header'       => ['type' => 'string'],
                        'title'            => ['type' => 'string', 'description' => 'Internal log title; defaults to "MCP one-off email to {email}".'],
                        'design_template'  => [
                            'type'    => 'string',
                            'enum'    => array_keys(ContextTools::allowedDesignTemplates()),
                            'default' => 'classic',
                        ],
                        'from_name'        => ['type' => 'string', 'description' => 'Defaults to site sender (get-crm-context.default_sender.from_name).'],
                        'from_email'       => ['type' => 'string', 'description' => 'Defaults to site sender. Must be a configured/verified address.'],
                        'reply_to_name'    => ['type' => 'string'],
                        'reply_to_email'   => ['type' => 'string'],
                        'is_transactional' => ['type' => 'string', 'enum' => ['yes', 'no'], 'default' => 'no', 'description' => 'When "yes", also auto-disables the global marketing footer for transactional-mail compliance.'],
                        'disable_footer'   => ['type' => 'string', 'enum' => ['yes', 'no'], 'description' => 'Explicit override of the auto-derived footer behavior.'],
                        'click_tracker'    => ['type' => 'string', 'enum' => ['yes', 'no', 'anonymous']],
                        'open_tracker'     => ['type' => 'string', 'enum' => ['yes', 'no', 'anonymous']],
                        'utm'              => [
                            'type'        => 'object',
                            'description' => 'Optional {status:0|1, source, medium, campaign, term, content}. status defaults to 0. Unknown keys are rejected.',
                            'properties'  => [
                                'status'   => ['type' => ['integer', 'boolean']],
                                'source'   => ['type' => 'string'],
                                'medium'   => ['type' => 'string'],
                                'campaign' => ['type' => 'string'],
                                'term'     => ['type' => 'string'],
                                'content'  => ['type' => 'string'],
                            ],
                            'additionalProperties' => false,
                        ],
                        'settings'         => [
                            'type'        => 'object',
                            'description' => 'Passthrough merged into campaign.settings. Known closed sub-objects mailer_settings/footer_settings reject unknown keys; template_config stays extensible. Caller keys override our defaults.',
                            'properties'  => [
                                'mailer_settings' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'from_name'      => ['type' => 'string'],
                                        'from_email'     => ['type' => 'string'],
                                        'reply_to_name'  => ['type' => 'string'],
                                        'reply_to_email' => ['type' => 'string'],
                                        'is_custom'      => ['type' => 'string', 'enum' => ['yes', 'no']],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'footer_settings' => [
                                    'type'       => 'object',
                                    'properties' => ['disable_footer' => ['type' => 'string', 'enum' => ['yes', 'no']]],
                                    'additionalProperties' => false,
                                ],
                                'template_config' => ['type' => 'object'],
                            ],
                        ],
                    ],
                    'required' => ['subject', 'body'],
                ],
                'execute_callback'    => [EmailTools::class, 'sendEmailToContact'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_emails');
                },
                'annotations' => ['destructive' => false, 'openWorldHint' => true],
            ],

            'fluent-crm/upsert-campaign' => [
                'label'       => __('Create or Update Campaign', 'fluent-crm'),
                'description' => __('Create or update a draft campaign. Never sends — use change-campaign-status to schedule. recipients persists tags + lists ONLY (no statuses/contact_type — apply a temp tag first). Returns estimated_recipients + warnings inline.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'campaign_id'        => ['type' => 'integer'],
                        'title'              => ['type' => 'string'],
                        'email_subject'      => ['type' => 'string'],
                        'email_pre_header'   => ['type' => 'string'],
                        'email_body'         => ['type' => 'string'],
                        'design_template'    => [
                            'type'    => 'string',
                            'enum'    => array_keys(ContextTools::allowedDesignTemplates()),
                            'default' => 'classic',
                        ],
                        'settings'           => [
                            'type'        => 'object',
                            'description' => 'Merged into campaign.settings. Known closed sub-objects mailer_settings/footer_settings reject unknown keys; is_transactional/click_tracker/open_tracker/template_config stay extensible.',
                            'properties'  => [
                                'mailer_settings' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'from_name'      => ['type' => 'string'],
                                        'from_email'     => ['type' => 'string'],
                                        'reply_to_name'  => ['type' => 'string'],
                                        'reply_to_email' => ['type' => 'string'],
                                        'is_custom'      => ['type' => 'string', 'enum' => ['yes', 'no']],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'footer_settings' => [
                                    'type'       => 'object',
                                    'properties' => ['disable_footer' => ['type' => 'string', 'enum' => ['yes', 'no']]],
                                    'additionalProperties' => false,
                                ],
                                'is_transactional' => ['type' => 'string', 'enum' => ['yes', 'no']],
                                'click_tracker'    => ['type' => 'string', 'enum' => ['yes', 'no', 'anonymous']],
                                'open_tracker'     => ['type' => 'string', 'enum' => ['yes', 'no', 'anonymous']],
                                'template_config'  => ['type' => 'object'],
                            ],
                        ],
                        'recipients'         => [
                            'type'        => 'object',
                            'description' => 'Recipient segment. Persists {tags:[id|slug|title], lists:[id|slug|title]} only. Pass any other key (statuses, contact_type, advanced_filters, sending_filter) and the call hard-errors with the temp-tag workaround.',
                            'properties'  => [
                                'tags'  => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                                'lists' => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                            ],
                        ],
                        'exclude_recipients' => [
                            'type'        => 'object',
                            'description' => 'Same shape + restriction as recipients.',
                            'properties'  => [
                                'tags'  => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                                'lists' => ['type' => 'array', 'items' => ['type' => ['string', 'integer']]],
                            ],
                        ],
                        'subjects'           => [
                            'type'        => 'array',
                            'description' => 'A/B subjects. Each: {value: string [, key: string]}. Pass an array with 2+ items to enable A/B; the regular email_subject still acts as the primary line. Optional `key` is a stable identifier used internally — auto-generated if omitted.',
                            'items'       => [
                                'type' => 'object',
                                'properties' => [
                                    'value' => ['type' => 'string'],
                                    'key'   => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'label_ids'          => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'utm'                => [
                            'type'        => 'object',
                            'description' => 'Optional. {status: 0|1 to toggle, source, medium, campaign, term, content}. status defaults to 0 (off). Unknown keys are rejected.',
                            'properties'  => [
                                'status'   => ['type' => ['integer', 'boolean']],
                                'source'   => ['type' => 'string'],
                                'medium'   => ['type' => 'string'],
                                'campaign' => ['type' => 'string'],
                                'term'     => ['type' => 'string'],
                                'content'  => ['type' => 'string'],
                            ],
                            'additionalProperties' => false,
                        ],
                        'if_exists'          => [
                            'type' => 'string',
                            'enum' => ['auto_suffix', 'error'],
                            'default' => 'auto_suffix',
                            'description' => 'On title conflict during create: auto_suffix (Title (2), Title (3)) or hard error.',
                        ],
                    ],
                ],
                'execute_callback'    => [CampaignTools::class, 'upsertCampaign'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_emails');
                },
                // Never sends (use change-campaign-status), so openWorldHint=false.
                // Destructive: update mode overwrites subject/body/settings/recipients.
                'annotations' => ['destructive' => true, 'openWorldHint' => false],
            ],

            'fluent-crm/change-campaign-status' => [
                'label'       => __('Change Campaign Status', 'fluent-crm'),
                'description' => __('State transition. schedule + delete are destructive. pause/resume only valid mid-send (working↔paused). unschedule reverts to draft and clears scheduled_at.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'campaign_id'    => ['type' => 'integer'],
                        'action'         => ['type' => 'string', 'enum' => ['schedule', 'unschedule', 'pause', 'resume', 'duplicate', 'delete']],
                        'scheduled_at'   => ['type' => 'string', 'description' => 'Required when action=schedule and sending_type≠instant. Site timezone (see get-crm-context.site.timezone). Must be in the future.'],
                        'schedule_range' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Required when sending_type=range_schedule. [startISO, endISO].'],
                        'sending_type'   => ['type' => 'string', 'enum' => ['instant', 'schedule', 'range_schedule'], 'description' => 'Defaults to "schedule" if scheduled_at is set, else "instant".'],
                        'new_title'      => ['type' => 'string', 'description' => 'duplicate only — overrides the auto "[Duplicate] X" title.'],
                    ],
                    'required' => ['campaign_id', 'action'],
                ],
                'execute_callback'    => [CampaignTools::class, 'changeCampaignStatus'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_emails');
                },
                'annotations' => ['destructive' => true, 'openWorldHint' => true],
            ],

            'fluent-crm/update-contact-automation-status' => [
                'label'       => __('Update Contact Automation Status', 'fluent-crm'),
                'description' => __('Resume, cancel, or advance_now a contact in a funnel. cancel is destructive (reversible in UI but halts processing). advance_now requires advance_to_sequence_id and skips intermediate benchmarks.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'funnel_id'              => ['type' => 'integer', 'description' => 'Use list-funnel-subscribers to find candidates.'],
                        'contact_id'             => ['type' => 'integer', 'description' => 'Provide this OR email.'],
                        'email'                  => ['type' => 'string', 'description' => 'Provide this OR contact_id.'],
                        'action'                 => ['type' => 'string', 'enum' => ['resume', 'cancel', 'advance_now']],
                        'advance_to_sequence_id' => ['type' => 'integer', 'description' => 'Required when action=advance_now. The sequence id to jump to (find via get-automation include=["sequences"]).'],
                    ],
                    'required' => ['funnel_id', 'action'],
                ],
                'execute_callback'    => [FunnelTools::class, 'updateContactAutomationStatus'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_write_funnels');
                },
                // Destructive: cancel halts progression and advance_now skips
                // intermediate sequences. openWorldHint=true: resuming/advancing a
                // funnel can trigger external sends.
                'annotations' => ['destructive' => true, 'openWorldHint' => true],
            ],
        ];
    }

    public static function register()
    {
        foreach (self::getDefinitions() as $name => $definition) {
            $args = [
                'label'               => $definition['label'],
                'description'         => $definition['description'],
                'category'            => 'fluent-crm',
                'execute_callback'    => self::wrapExecuteCallback($name, $definition['execute_callback']),
                'permission_callback' => $definition['permission_callback'],
                'meta'                => [
                    'show_in_rest' => true,
                    'mcp'          => [
                        'public' => true,
                    ],
                ],
            ];

            if (!empty($definition['input_schema'])) {
                $args['input_schema'] = $definition['input_schema'];
            }

            if (!empty($definition['annotations'])) {
                $args['meta']['annotations'] = $definition['annotations'];
            }

            wp_register_ability($name, $args);
        }
    }

    /**
     * Wraps every tool's execute callback in a try/catch that converts
     * unhandled exceptions (SQL errors, type errors, anything that escapes
     * a tool's own validation) into a structured WP_Error, so the agent gets
     * a definite "this tool failed" signal instead of the adapter's ambiguous
     * surface — without which it retries tools that silently succeeded
     * (fluentcrm-mcp-review.md bug #1).
     *
     * The exception itself never crosses the API boundary. The full record —
     * class, message, file, line, trace — goes to the server side only, via
     * the action below and the CRM system log; the client gets a generic
     * message plus a correlation id to quote to the site administrator.
     */
    private static function wrapExecuteCallback($toolName, $callback)
    {
        return function ($params) use ($toolName, $callback) {
            try {
                return call_user_func($callback, $params);
            } catch (\Throwable $e) {
                // Ties the generic client-facing error to the full server-side
                // record, so an operator can find the real exception without it
                // ever being disclosed to the caller.
                $errorId = substr(md5(uniqid('fcrm_mcp_', true)), 0, 12);

                /**
                 * Allows logging or alerting on unhandled tool exceptions
                 * before the structured error is returned to the agent.
                 *
                 * @since 2.10.0
                 *
                 * @param \Throwable $e         The exception.
                 * @param string     $toolName  Fully-qualified ability name.
                 * @param mixed      $params    The tool's input parameters.
                 * @param string     $errorId   Correlation id returned to the client.
                 */
                do_action('fluent_crm/mcp_tool_exception', $e, $toolName, $params, $errorId);

                // Second sink: the CRM system log, so the detail is retrievable
                // by error_id on sites that have no listener on the action
                // above. No-ops unless debug logging / system logs are enabled.
                Helper::debugLog(
                    sprintf('MCP tool exception [%s]', $errorId),
                    sprintf(
                        "Tool: %s\nException: %s\nMessage: %s\nAt: %s:%d\n\n%s",
                        $toolName,
                        get_class($e),
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getTraceAsString()
                    ),
                    'error'
                );

                // Deliberately omits the message, class, file, and trace: SQL
                // and runtime messages leak table names, query fragments, and
                // filesystem paths, and WP_DEBUG is enabled often enough on
                // reachable staging sites that gating the worst of it on that
                // constant is not protection (review PR #2025).
                return new \WP_Error(
                    'failed',
                    __('The tool failed with an internal error. Quote the error_id to the site administrator to look it up in the CRM logs.', 'fluent-crm'),
                    [
                        'tool'     => $toolName,
                        'error_id' => $errorId,
                    ]
                );
            }
        };
    }
}
