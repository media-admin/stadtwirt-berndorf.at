<?php

namespace FluentCrm\App\Modules\MCP;

use FluentCrm\App\Modules\MCP\Tools\CampaignTools;
use FluentCrm\App\Modules\MCP\Tools\ContactTools;
use FluentCrm\App\Modules\MCP\Tools\ContextTools;
use FluentCrm\App\Modules\MCP\Tools\EmailTools;
use FluentCrm\App\Modules\MCP\Tools\FunnelTools;
use FluentCrm\App\Modules\MCP\Tools\SegmentTools;
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
                'description' => __('Discovery. Returns identity, permissions, stats, top tags/lists, available triggers/actions, all enums, custom fields schema, default sender. Call once per session.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                ],
                'execute_callback'    => [ContextTools::class, 'getContext'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_view_dashboard')
                        || PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-contacts' => [
                'label'       => __('List Contacts', 'fluent-crm'),
                'description' => __('List/filter contacts with tags + lists inline. `search` matches name/email/custom field values. Filter fields are strictly validated — see get-crm-context.enums for valid status values.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'                => ['type' => 'string', 'description' => 'Full-text across first_name, last_name, email, and custom field values.'],
                        'tags'                  => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'Tag ids or slugs/titles. Mixed allowed.'],
                        'lists'                 => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'List ids or slugs/titles. Mixed allowed.'],
                        'statuses'              => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'See get-crm-context.enums.contact_statuses.'],
                        'sms_statuses'          => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'See get-crm-context.enums.sms_statuses.'],
                        'contact_type'          => ['type' => 'string', 'enum' => ['lead', 'customer']],
                        'created_after'         => ['type' => 'string', 'description' => 'YYYY-MM-DD or full ISO 8601. Site timezone.'],
                        'created_before'        => ['type' => 'string', 'description' => 'YYYY-MM-DD or full ISO 8601. Site timezone.'],
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

            'fluent-crm/get-contact' => [
                'label'       => __('Get Contact', 'fluent-crm'),
                'description' => __('Full contact profile. Provide contact_id OR email. Default include: notes, email_history, automations. Optional: activity, purchase_history, support_tickets, ai_summary, info_widgets.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_id'          => ['type' => 'integer', 'description' => 'Provide this OR email.'],
                        'email'               => ['type' => 'string', 'description' => 'Provide this OR contact_id.'],
                        'include'             => [
                            'type'        => 'array',
                            'description' => 'Adds optional sections to the response. The default 3 are always included.',
                            'items'       => ['type' => 'string', 'enum' => ['notes', 'email_history', 'automations', 'activity', 'purchase_history', 'support_tickets', 'ai_summary', 'info_widgets']],
                        ],
                        'generate_ai_summary' => ['type' => 'boolean', 'default' => false, 'description' => 'When true and ai_summary is in include, force a fresh AI call (costs provider tokens).'],
                    ],
                ],
                'execute_callback'    => [ContactTools::class, 'getContact'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_read_contacts');
                },
                'annotations' => ['readonly' => true],
            ],

            'fluent-crm/list-campaigns' => [
                'label'       => __('List Campaigns', 'fluent-crm'),
                'description' => __('List campaigns with stats inline. Excludes one-off email-to-contact records by default — flip include_one_offs for a unified "what was sent recently" view.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search'           => ['type' => 'string', 'description' => 'Matches campaign title.'],
                        'statuses'         => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'See get-crm-context.enums.campaign_statuses.'],
                        'sort_by'          => ['type' => 'string', 'enum' => ['id', 'created_at', 'updated_at', 'scheduled_at'], 'default' => 'created_at'],
                        'sort_type'        => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC'],
                        'include_stats'    => ['type' => 'boolean', 'default' => true, 'description' => 'When true, computes per-campaign stats inline (one extra query per row — turn off for cheap title scans).'],
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
                            'type'  => 'array',
                            'items' => ['type' => 'string', 'enum' => ['stats', 'subjects', 'link_report', 'recipients_estimate']],
                        ],
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
                        'status'               => ['type' => 'string', 'description' => 'See get-crm-context.enums.contact_statuses.'],
                        'contact_type'         => ['type' => 'string', 'enum' => ['lead', 'customer']],
                        'address'              => ['type' => 'object', 'description' => 'Object: {line_1, line_2, city, state, postal_code, country (ISO-2)}. Empty fields are ignored.'],
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
            ],

            'fluent-crm/bulk-upsert-contacts' => [
                'label'       => __('Bulk Create or Update Contacts', 'fluent-crm'),
                'description' => __('Batch create/update up to 500 contacts. Returns per-row {created, updated, skipped, invalid}. auto_create defaults to true here (matches CSV-import expectations) — opposite of upsert-contact.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contacts'          => [
                            'type'        => 'array',
                            'description' => 'Array of contact objects. Each object accepts the same fields as upsert-contact but no add_tags/remove_tags — pass `tags` and `lists` directly.',
                            'items'       => ['type' => 'object'],
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
                'annotations' => ['bulk' => true],
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
                'annotations' => ['destructive' => true],
            ],

            'fluent-crm/apply-segments-to-contacts' => [
                'label'       => __('Apply Tags/Lists Across Contacts', 'fluent-crm'),
                'description' => __('Add/remove tags and lists across many contacts. Provide contact_ids OR filter, not both. Always dry_run first for filter-based applies. Response includes applied_contact_ids for precise reversal. Cap 5000.', 'fluent-crm'),
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'contact_ids'       => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Explicit ids. Use OR filter, not both.'],
                        'filter'            => ['type' => 'object', 'description' => 'Universal filter — {tags, lists, statuses, contact_type, search, created_after, created_before}. See get-crm-context.guidelines.'],
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
                'annotations' => ['bulk' => true],
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
                        'from_tag_ids'   => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'merge only — source tags whose subscribers move to to_tag_id and which then get deleted.'],
                        'to_tag_id'      => ['type' => 'integer', 'description' => 'merge only — destination tag.'],
                    ],
                    'required' => ['action'],
                ],
                'execute_callback'    => [SegmentTools::class, 'manageTag'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contact_cats')
                        || PermissionManager::currentUserCan('fcrm_manage_contact_cats_delete');
                },
                'annotations' => ['destructive' => true],
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
                        'from_list_ids'  => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'to_list_id'     => ['type' => 'integer'],
                    ],
                    'required' => ['action'],
                ],
                'execute_callback'    => [SegmentTools::class, 'manageList'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_contact_cats')
                        || PermissionManager::currentUserCan('fcrm_manage_contact_cats_delete');
                },
                'annotations' => ['destructive' => true],
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
                'annotations' => ['destructive' => true],
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
            ],

            'fluent-crm/send-test-email' => [
                'label'       => __('Send Test Email', 'fluent-crm'),
                'description' => __('Render and send a test copy of an email — does not enroll the recipient, does not create a campaign record, does not log to email_history. Subject is prefixed with "TEST:".', 'fluent-crm'),
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
                        'against_contact_id'   => ['type' => 'integer', 'description' => 'Resolve smartcodes against this contact. Defaults to a contact matching to_email, then any subscribed contact.'],
                        'against_contact_email' => ['type' => 'string'],
                    ],
                ],
                'execute_callback'    => [EmailTools::class, 'sendTestEmail'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_emails');
                },
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
                        'title'            => ['type' => 'string', 'description' => 'Internal log title; defaults to "MCP one-off to {email}".'],
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
                        'utm'              => ['type' => 'object', 'description' => 'Optional {status:0|1, source, medium, campaign, term, content}. status defaults to 0.'],
                        'settings'         => ['type' => 'object', 'description' => 'Free-form passthrough merged into campaign.settings (template_config, footer_settings). Caller keys override our defaults.'],
                    ],
                    'required' => ['subject', 'body'],
                ],
                'execute_callback'    => [EmailTools::class, 'sendEmailToContact'],
                'permission_callback' => function () {
                    return PermissionManager::currentUserCan('fcrm_manage_emails');
                },
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
                            'type' => 'object',
                            'description' => 'Merged into campaign.settings. Shape: {mailer_settings:{from_name,from_email,reply_to_name,reply_to_email,is_custom:yes|no}, is_transactional:yes|no, click_tracker:yes|no|anonymous, open_tracker:yes|no|anonymous, footer_settings:{disable_footer:yes|no}, template_config}.',
                        ],
                        'recipients'         => [
                            'type' => 'object',
                            'description' => 'Recipient segment. Persists {tags:[id|slug|title], lists:[id|slug|title]} only. Pass other keys (statuses, contact_type, advanced_filters) and the call hard-errors with the temp-tag workaround.',
                        ],
                        'exclude_recipients' => [
                            'type' => 'object',
                            'description' => 'Same shape + restriction as recipients.',
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
                            'description' => 'Optional. {status: 0|1 to toggle, source, medium, campaign, term, content}. status defaults to 0 (off).',
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
                'annotations' => ['destructive' => true],
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
     * a tool's own validation) into a structured WP_Error with the actual
     * exception message instead of the adapter's generic "Tool execution
     * failed" surface. Without this, the agent has no signal about what
     * went wrong, which leads to retries against tools that silently
     * succeeded — see fluentcrm-mcp-review.md bug #1.
     */
    private static function wrapExecuteCallback($toolName, $callback)
    {
        return function ($params) use ($toolName, $callback) {
            try {
                return call_user_func($callback, $params);
            } catch (\Throwable $e) {
                /**
                 * Allows logging or alerting on unhandled tool exceptions
                 * before the structured error is returned to the agent.
                 *
                 * @since 2.10.0
                 *
                 * @param \Throwable $e         The exception.
                 * @param string     $toolName  Fully-qualified ability name.
                 * @param mixed      $params    The tool's input parameters.
                 */
                do_action('fluent_crm/mcp_tool_exception', $e, $toolName, $params);

                $details = [
                    'tool'      => $toolName,
                    'exception' => get_class($e),
                ];
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $details['file'] = $e->getFile() . ':' . $e->getLine();
                    $details['trace'] = array_slice(explode("\n", $e->getTraceAsString()), 0, 5);
                }
                return new \WP_Error('failed', $e->getMessage(), $details);
            }
        };
    }
}
