<?php

namespace FluentCrm\App\Modules\MCP\Helpers;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Services\ContactsQuery;
use FluentCrm\App\Services\PermissionManager;

/**
 * Shared utilities for FluentCRM MCP tools.
 *
 * Every tool delegates to existing FluentCRM services for business logic. This
 * helper covers concerns *common* to all tools: identifier resolution
 * (id-or-email, id-or-slug), output formatting, universal-filter translation,
 * pagination, content-type sniffing, and structured WP_Error construction.
 *
 * Naming follows MCP_PLAN.md § 7.
 */
class MCPHelper
{
    // ---------------------------------------------------------------------
    // Identifier resolution
    // ---------------------------------------------------------------------

    /**
     * Resolve a contact from an input array that may carry contact_id or email.
     *
     * @param array $input
     * @return Subscriber|\WP_Error
     */
    public static function resolveContact($input)
    {
        $contactId = isset($input['contact_id']) ? (int) $input['contact_id'] : 0;
        $email     = isset($input['email']) ? sanitize_email($input['email']) : '';

        if ($contactId) {
            $subscriber = Subscriber::find($contactId);
            if (!$subscriber) {
                return self::error('not_found', __('Contact not found', 'fluent-crm'), ['contact_id' => $contactId]);
            }
            return $subscriber;
        }

        if ($email) {
            $subscriber = Subscriber::where('email', $email)->first();
            if (!$subscriber) {
                return self::error('not_found', __('Contact not found', 'fluent-crm'), ['email' => $email]);
            }
            return $subscriber;
        }

        return self::error('invalid_param', __('Provide contact_id or email', 'fluent-crm'));
    }

    /**
     * Resolve an array of tag identifiers (ids or titles/slugs) to integer IDs.
     * Optionally creates missing tags when $autoCreate is true (caller MUST
     * have re-checked `fcrm_manage_contact_cats` before passing true).
     *
     * @param array $items
     * @param bool  $autoCreate
     * @return array{ids: int[], created: array<int, array{id:int,title:string}>}
     */
    public static function resolveTagIds($items, $autoCreate = false)
    {
        $ids     = [];
        $created = [];

        foreach ((array) $items as $item) {
            if ($item === '' || $item === null) {
                continue;
            }

            if (is_numeric($item)) {
                $tag = Tag::find((int) $item);
                if ($tag) {
                    $ids[] = (int) $tag->id;
                }
                continue;
            }

            $value = sanitize_text_field((string) $item);
            $tag   = Tag::where('title', $value)->orWhere('slug', sanitize_title($value))->first();

            if ($tag) {
                $ids[] = (int) $tag->id;
                continue;
            }

            if ($autoCreate) {
                $tag = Tag::create([
                    'title' => $value,
                    'slug'  => sanitize_title($value),
                ]);
                $ids[]    = (int) $tag->id;
                $created[] = ['id' => (int) $tag->id, 'title' => $tag->title];
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'created' => $created];
    }

    /**
     * Same as resolveTagIds() but for lists.
     *
     * @param array $items
     * @param bool  $autoCreate
     * @return array{ids: int[], created: array<int, array{id:int,title:string}>}
     */
    public static function resolveListIds($items, $autoCreate = false)
    {
        $ids     = [];
        $created = [];

        foreach ((array) $items as $item) {
            if ($item === '' || $item === null) {
                continue;
            }

            if (is_numeric($item)) {
                $list = Lists::find((int) $item);
                if ($list) {
                    $ids[] = (int) $list->id;
                }
                continue;
            }

            $value = sanitize_text_field((string) $item);
            $list  = Lists::where('title', $value)->orWhere('slug', sanitize_title($value))->first();

            if ($list) {
                $ids[] = (int) $list->id;
                continue;
            }

            if ($autoCreate) {
                $list      = Lists::create([
                    'title' => $value,
                    'slug'  => sanitize_title($value),
                ]);
                $ids[]     = (int) $list->id;
                $created[] = ['id' => (int) $list->id, 'title' => $list->title];
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'created' => $created];
    }

    // ---------------------------------------------------------------------
    // Formatting
    // ---------------------------------------------------------------------

    /**
     * Build the rich contact record consumed by get-contact / upsert-contact.
     *
     * @param Subscriber $subscriber
     * @param array      $opts {
     *     @type array $include One or more of: notes, email_history, automations,
     *                          activity, purchase_history, support_tickets,
     *                          ai_summary, info_widgets.
     * }
     * @return array
     */
    public static function formatContactForMCP($subscriber, $opts = [])
    {
        $include = (array) ($opts['include'] ?? []);

        $address = [
            'line_1'      => $subscriber->address_line_1,
            'line_2'      => $subscriber->address_line_2,
            'city'        => $subscriber->city,
            'state'       => $subscriber->state,
            'postal_code' => $subscriber->postal_code,
            'country'     => $subscriber->country,
        ];

        $data = [
            'id'              => (int) $subscriber->id,
            'email'           => $subscriber->email,
            'first_name'      => $subscriber->first_name,
            'last_name'       => $subscriber->last_name,
            'full_name'       => trim((string) $subscriber->full_name),
            'prefix'          => $subscriber->prefix,
            'status'          => $subscriber->status,
            'contact_type'    => $subscriber->contact_type,
            'phone'           => $subscriber->phone,
            'address'         => array_filter($address, function ($v) { return $v !== null && $v !== ''; }),
            'date_of_birth'   => $subscriber->date_of_birth,
            'timezone'        => $subscriber->timezone,
            'source'          => $subscriber->source,
            'avatar'          => $subscriber->avatar,
            'life_time_value' => $subscriber->life_time_value,
            'total_points'    => isset($subscriber->total_points) ? (int) $subscriber->total_points : 0,
            'last_activity'   => self::toIso8601($subscriber->last_activity),
            'created_at'      => self::toIso8601($subscriber->created_at),
        ];

        // Eager-loaded relations: tags, lists.
        $data['tags']  = self::formatTagList($subscriber->tags ?? []);
        $data['lists'] = self::formatListList($subscriber->lists ?? []);

        // Custom fields are inlined for visibility.
        $data['custom_fields'] = (array) $subscriber->custom_fields();

        if ($subscriber->user_id) {
            $data['wp_user'] = [
                'id'       => (int) $subscriber->user_id,
                'edit_url' => admin_url('user-edit.php?user_id=' . (int) $subscriber->user_id),
            ];
            $user = get_user_by('ID', $subscriber->user_id);
            if ($user) {
                $data['wp_user']['roles'] = (array) $user->roles;
            }
        } else {
            $data['wp_user'] = null;
        }

        // Optional includes.
        if (in_array('notes', $include, true)) {
            $data['notes'] = self::formatNotesFor($subscriber);
        }
        if (in_array('email_history', $include, true)) {
            $data['email_history'] = self::formatEmailHistoryFor($subscriber, (int) ($opts['email_history_limit'] ?? 10));
        }
        if (in_array('automations', $include, true)) {
            $data['automations'] = self::formatAutomationsFor($subscriber);
        }

        return $data;
    }

    public static function formatContactSummary($subscriber)
    {
        return [
            'id'            => (int) $subscriber->id,
            'email'         => $subscriber->email,
            'first_name'    => $subscriber->first_name,
            'last_name'     => $subscriber->last_name,
            'full_name'     => trim((string) $subscriber->full_name),
            'status'        => $subscriber->status,
            'contact_type'  => $subscriber->contact_type,
            'tags'          => self::formatTagList($subscriber->tags ?? []),
            'lists'         => self::formatListList($subscriber->lists ?? []),
            'country'       => $subscriber->country,
            'city'          => $subscriber->city,
            'source'        => $subscriber->source,
            'last_activity' => self::toIso8601($subscriber->last_activity),
            'created_at'    => self::toIso8601($subscriber->created_at),
        ];
    }

    public static function formatContactList($paginated, $includeCustomFields = false)
    {
        $items = [];
        foreach ($paginated->items() as $subscriber) {
            $item = self::formatContactSummary($subscriber);
            if ($includeCustomFields) {
                $item['custom_fields'] = (array) $subscriber->custom_fields();
            }
            $items[] = $item;
        }

        return [
            'items'    => $items,
            'total'    => (int) $paginated->total(),
            'page'     => (int) $paginated->currentPage(),
            'per_page' => (int) $paginated->perPage(),
            'pages'    => (int) $paginated->lastPage(),
        ];
    }

    public static function formatTagList($tags)
    {
        $out = [];
        foreach ($tags as $tag) {
            $out[] = [
                'id'    => (int) $tag->id,
                'title' => $tag->title,
                'slug'  => $tag->slug,
            ];
        }
        return $out;
    }

    public static function formatListList($lists)
    {
        $out = [];
        foreach ($lists as $list) {
            $out[] = [
                'id'    => (int) $list->id,
                'title' => $list->title,
                'slug'  => $list->slug,
            ];
        }
        return $out;
    }

    public static function formatNoteForMCP($note)
    {
        $addedBy   = null;
        $createdBy = method_exists($note, 'createdBy') ? $note->createdBy() : null;
        if (is_array($createdBy)) {
            $addedBy = [
                'id'   => (int) $createdBy['ID'],
                'name' => $createdBy['display_name'],
            ];
        }

        return [
            'id'               => (int) $note->id,
            'subscriber_id'    => (int) $note->subscriber_id,
            'type'             => $note->type,
            'title'            => $note->title,
            'description_text' => self::htmlToText((string) $note->description),
            'description_html' => (string) $note->description,
            'added_by'         => $addedBy,
            'created_at'       => self::toIso8601($note->created_at),
        ];
    }

    /**
     * Return up to $limit recent notes for a subscriber.
     */
    public static function formatNotesFor($subscriber, $limit = 50)
    {
        // SubscriberNote already excludes _company_note_ / _system_log_ via a
        // global scope (see Models\SubscriberNote::boot()).
        $notes = SubscriberNote::where('subscriber_id', $subscriber->id)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();

        $formatted = [];
        foreach ($notes as $note) {
            $formatted[] = self::formatNoteForMCP($note);
        }
        return $formatted;
    }

    /**
     * Recent campaign emails sent to / on behalf of a subscriber, paginated to
     * a small set so heavy installs don't drown the response (per MCP_PLAN
     * § 10.7).
     */
    public static function formatEmailHistoryFor($subscriber, $limit = 10)
    {
        $emails = $subscriber->campaignEmails()
            ->orderBy('id', 'DESC')
            ->limit(max(1, (int) $limit))
            ->get();

        $out = [];
        foreach ($emails as $email) {
            $out[] = [
                'id'             => (int) $email->id,
                'subject'        => $email->email_subject,
                'campaign_id'    => $email->campaign_id ? (int) $email->campaign_id : null,
                'campaign_title' => $email->campaign ? $email->campaign->title : null,
                'status'         => $email->status,
                'is_open'        => !empty($email->is_open),
                'is_clicked'     => isset($email->click_counter) ? ((int) $email->click_counter > 0) : false,
                'sent_at'        => self::toIso8601($email->updated_at),
            ];
        }
        return $out;
    }

    public static function formatAutomationsFor($subscriber)
    {
        $automations = $subscriber->funnel_subscribers()->with('funnel')->get();

        $out = [];
        foreach ($automations as $row) {
            if (!$row->funnel) {
                continue;
            }
            $out[] = [
                'funnel_id'         => (int) $row->funnel_id,
                'title'             => $row->funnel->title,
                'status'            => $row->status,
                'last_executed_at'  => self::toIso8601($row->last_executed_time),
                'next_scheduled_at' => self::toIso8601($row->next_execution_time),
                'next_sequence_id'  => $row->next_sequence ? (int) $row->next_sequence : null,
                'enrolled_at'       => self::toIso8601($row->created_at),
            ];
        }
        return $out;
    }

    public static function formatCampaignSummary($campaign, $includeStats = true)
    {
        // Only fill sent_at when the campaign has actually shipped — drafts
        // and pre-send states leave it null (review #30). updated_at is
        // not a reliable proxy: any settings tweak bumps it.
        $sentStatuses = ['archived', 'working', 'paused'];
        $sentAt = in_array($campaign->status, $sentStatuses, true)
            ? self::toIso8601($campaign->updated_at)
            : null;

        $item = [
            'id'              => (int) $campaign->id,
            'title'           => $campaign->title,
            'email_subject'   => $campaign->email_subject,
            'status'          => $campaign->status,
            'design_template' => $campaign->design_template,
            'scheduled_at'    => self::toIso8601($campaign->scheduled_at),
            'sent_at'         => $sentAt,
            'created_at'      => self::toIso8601($campaign->created_at),
        ];

        if ($includeStats) {
            $item['stats'] = self::campaignStatsCompact($campaign);
        }

        return $item;
    }

    /**
     * Compact stats for a single campaign. Mirrors the per-campaign columns
     * the admin list does (sent/views/clicks via fc_campaign_emails) and
     * pulls unsubscribers from fc_campaign_url_metrics where type='unsubscribe'
     * — there is no is_unsubscribed column on fc_campaign_emails.
     *
     * Anonymous-tracking aware: when the campaign is configured for
     * anonymous click/open tracking, the per-contact columns will read 0
     * even when there's real engagement (the data goes to campaign meta
     * instead). We surface tracking_mode + an aggregate fallback so the
     * agent doesn't mis-diagnose anonymous campaigns as having zero
     * engagement (round-4 review P1 #5).
     */
    public static function campaignStatsCompact($campaign)
    {
        $campaignId = (int) $campaign->id;
        $total      = (int) $campaign->recipients_count;

        $clickStatus = method_exists($campaign, 'getClickTrackingStatus') ? $campaign->getClickTrackingStatus(false) : 'yes';
        $openStatus  = method_exists($campaign, 'getOpenTrackingStatus') ? $campaign->getOpenTrackingStatus(false) : 'yes';

        // Single GROUP-BY-style aggregate over the email table.
        $row = fluentCrmDb()->table('fc_campaign_emails')
            ->where('campaign_id', $campaignId)
            ->selectRaw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent")
            ->selectRaw("SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN click_counter IS NOT NULL THEN 1 ELSE 0 END) as clicks")
            ->first();

        $sent   = (int) ($row->sent ?? 0);
        $views  = (int) ($row->views ?? 0);
        $clicks = (int) ($row->clicks ?? 0);

        // For anonymous tracking, per-contact columns are zero — pull the
        // aggregate counts from campaign meta. open_count is a single int;
        // click count is a serialized map of url => clicks.
        if ($openStatus === 'anonymous') {
            $views = (int) fluentcrm_get_campaign_meta($campaignId, '_ano_open_count', true);
        }
        if ($clickStatus === 'anonymous') {
            $rawUrlClicks = fluentcrm_get_campaign_meta($campaignId, '_ano_url_clicks', true);
            if (is_array($rawUrlClicks)) {
                $clicks = (int) array_sum(array_filter($rawUrlClicks, 'is_numeric'));
            }
        }

        $unsubs = (int) fluentCrmDb()->table('fc_campaign_url_metrics')
            ->where('campaign_id', $campaignId)
            ->where('type', 'unsubscribe')
            ->distinct()
            ->count('subscriber_id');

        return [
            'total'         => $total,
            'sent'          => $sent,
            'views'         => $views,
            'clicks'        => $clicks,
            'unsubscribers' => $unsubs,
            'open_rate'     => $sent ? round($views / max(1, $sent) * 100, 2) : 0,
            'click_rate'    => $sent ? round($clicks / max(1, $sent) * 100, 2) : 0,
            // Anonymous mode aggregates engagement into campaign meta rather
            // than per-contact rows — agents must know which they're seeing.
            'tracking_mode' => [
                'opens'  => $openStatus,
                'clicks' => $clickStatus,
            ],
        ];
    }

    // ---------------------------------------------------------------------
    // Filter translation
    // ---------------------------------------------------------------------

    /**
     * Translate the universal MCP filter shape (MCP_PLAN.md § 3.6) into an
     * array of args ContactsQuery accepts.
     */
    public static function buildContactsQueryArgs($filter)
    {
        $filter = (array) $filter;
        $args   = [];

        if (!empty($filter['search'])) {
            $args['search'] = sanitize_text_field((string) $filter['search']);
            $args['custom_fields'] = true;
        }

        if (!empty($filter['tags'])) {
            $resolved = self::resolveTagIds((array) $filter['tags']);
            $args['tags'] = $resolved['ids'];
        }

        if (!empty($filter['lists'])) {
            $resolved = self::resolveListIds((array) $filter['lists']);
            $args['lists'] = $resolved['ids'];
        }

        if (!empty($filter['statuses'])) {
            $args['statuses'] = array_values(array_filter(
                array_map('sanitize_text_field', (array) $filter['statuses'])
            ));
        }

        if (!empty($filter['sms_statuses'])) {
            $args['sms_statuses'] = array_values(array_filter(
                array_map('sanitize_text_field', (array) $filter['sms_statuses'])
            ));
        }

        if (!empty($filter['contact_ids'])) {
            $args['contact_ids'] = array_values(array_filter(array_map('intval', (array) $filter['contact_ids'])));
        }

        // contact_type / created_after / created_before all flow through the
        // advanced_filters pipeline as subscriber/<col> filters. Direct args
        // on ContactsQuery would also work but the advanced path is what
        // FluentCRM uses internally for these columns and reuses the same
        // hooks. Use date-aware operators ('after'/'before') instead of
        // '>='/'<=' — applyGeneralFilterQuery's exact-operator list does
        // NOT include those, and falls through to a LIKE that wraps the
        // value in % (round-4 review B/P1 #4).
        $advanced = self::normalizeAdvancedFilters($filter['advanced_filters'] ?? []);

        if (!empty($filter['contact_type'])) {
            $advanced[] = [[
                'source'   => ['subscriber', 'contact_type'],
                'operator' => '=',
                'value'    => sanitize_text_field((string) $filter['contact_type']),
            ]];
        }

        // Date range filters are applied separately by applyDateFilters()
        // post-construction. Routing them through advanced_filters hits
        // FluentCRM's broken whereTimestamp() phantom method (round-4
        // review P1 #4) which produces nonsensical SQL like
        // `where 'timestamp' = 'created_at'`.

        if (!empty($advanced)) {
            $args['filter_type']        = 'advanced';
            $args['filters_groups_raw'] = $advanced;
        }

        // All fc_subscribers columns. The framework rewrite made orderBy() throw
        // LogicException on column names that don't match ^[a-zA-Z0-9_\.]+$
        // — empty strings, "id ASC", "DROP TABLE", etc. — so an unguarded
        // sort_by would 500 the tool. Schema is stable (migration only adds
        // indexes), so hardcoding the column list avoids a per-request
        // SHOW COLUMNS without restricting agents to the input_schema enum.
        $allowedSortBy = [
            'id', 'user_id', 'hash', 'contact_owner', 'company_id', 'prefix',
            'first_name', 'last_name', 'email', 'timezone', 'address_line_1',
            'address_line_2', 'postal_code', 'city', 'state', 'country', 'ip',
            'latitude', 'longitude', 'total_points', 'life_time_value', 'phone',
            'status', 'contact_type', 'source', 'avatar', 'date_of_birth',
            'created_at', 'last_activity', 'updated_at',
        ];
        $sortBy = sanitize_key((string) ($filter['sort_by'] ?? 'id'));
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'id';
        }
        $args['sort_by'] = $sortBy;
        $sortType = strtoupper(sanitize_text_field((string) ($filter['sort_type'] ?? 'DESC')));
        $args['sort_type'] = in_array($sortType, ['ASC', 'DESC'], true) ? $sortType : 'DESC';

        if (isset($filter['custom_fields']) && $filter['custom_fields']) {
            $args['custom_fields'] = true;
        }

        return $args;
    }

    /**
     * Apply created_after / created_before to a query model directly. Avoids
     * the whereTimestamp phantom-method bug in
     * Subscriber::applyGeneralFilterQuery (round-4 review P1 #4) — using
     * raw `where(... '>=', ...)` SQL instead.
     *
     * Pass either a ContactsQuery instance (we'll grab getModel()) or an
     * Eloquent query directly.
     */
    public static function applyDateFilters($queryOrCq, $filter)
    {
        if (!is_array($filter)) {
            return $queryOrCq;
        }
        $query = method_exists($queryOrCq, 'getModel') ? $queryOrCq->getModel() : $queryOrCq;
        if (!is_object($query)) {
            return $queryOrCq;
        }

        if (!empty($filter['created_after'])) {
            $value = sanitize_text_field((string) $filter['created_after']);
            $query->where('created_at', '>=', $value);
        }
        if (!empty($filter['created_before'])) {
            $value = sanitize_text_field((string) $filter['created_before']);
            $query->where('created_at', '<=', $value);
        }

        return $queryOrCq;
    }

    /**
     * Build a paginated ContactsQuery directly from the universal filter shape.
     * The MCP layer reads $_REQUEST['page'] and `per_page` to drive the
     * underlying paginator (matches `$model->paginate()` behavior).
     */
    public static function buildContactsQuery($filter)
    {
        $args = self::buildContactsQueryArgs($filter);
        return new ContactsQuery($args);
    }

    /**
     * Validate the universal-filter shape before it's used. Returns
     * `true` on success or a WP_Error (`invalid_param`) on failure.
     *
     * Checks enforced (all fail-closed — a bad value never silently widens
     * the result set):
     *   1. `statuses[]` — must be in fluentcrm_subscriber_statuses().
     *   2. `sms_statuses[]` — must be in fluentcrm_subscriber_sms_statuses().
     *   3. `contact_type` — must be a key in fluentcrm_contact_types().
     *   4. `advanced_filters` — items must carry source[provider, property] +
     *      operator, and the (provider, property) pair must be registered in
     *      Helper::getAdvancedFilterOptions(). Otherwise the matching engine
     *      silently falls back to "match everyone".
     *
     * Operator-test report 2026-05-07 #1 — invalid statuses were being
     * silently dropped by buildContactsQueryArgs(), which made the agent
     * think it was targeting a narrow segment while actually hitting all
     * 12,863 contacts. Round-2 review #3 covered the advanced_filters
     * shape; round-4 review P0 #2 covered the (provider, property) pair.
     */
    public static function validateUniversalFilter($filter)
    {
        if (!is_array($filter) || empty($filter)) {
            return true;
        }

        // 1. statuses[]
        if (!empty($filter['statuses']) && is_array($filter['statuses'])) {
            $allowed = fluentcrm_subscriber_statuses();
            $bad = array_values(array_filter(
                array_map('sanitize_text_field', $filter['statuses']),
                function ($s) use ($allowed) {
                    return $s !== '' && !in_array($s, $allowed, true);
                }
            ));
            if (!empty($bad)) {
                return self::error('invalid_param', __('statuses contains values not in the contact-status enum. Refusing — silently ignoring would widen the audience instead of narrowing it.', 'fluent-crm'), [
                    'unknown_statuses' => $bad,
                    'allowed_statuses' => array_values($allowed),
                ]);
            }
        }

        // 2. sms_statuses[]
        if (!empty($filter['sms_statuses']) && is_array($filter['sms_statuses'])) {
            $allowed = fluentcrm_subscriber_sms_statuses();
            $bad = array_values(array_filter(
                array_map('sanitize_text_field', $filter['sms_statuses']),
                function ($s) use ($allowed) {
                    return $s !== '' && !in_array($s, $allowed, true);
                }
            ));
            if (!empty($bad)) {
                return self::error('invalid_param', __('sms_statuses contains values not in the SMS-status enum.', 'fluent-crm'), [
                    'unknown_sms_statuses' => $bad,
                    'allowed_sms_statuses' => array_values($allowed),
                ]);
            }
        }

        // 3. contact_type
        if (!empty($filter['contact_type'])) {
            $allowed = array_keys(fluentcrm_contact_types());
            $value = sanitize_text_field((string) $filter['contact_type']);
            if (!in_array($value, $allowed, true)) {
                return self::error('invalid_param', __('contact_type is not a registered type.', 'fluent-crm'), [
                    'unknown_contact_type' => $value,
                    'allowed_contact_types' => $allowed,
                ]);
            }
        }

        $original = $filter['advanced_filters'] ?? null;
        if (!empty($original) && is_array($original)) {
            $normalized = self::normalizeAdvancedFilters($original);
            // If the input had any items at all but nothing survived
            // normalization, the agent passed an unsupported shape.
            $hadAnyItems = false;
            foreach ($original as $group) {
                if (is_array($group) && count($group) > 0) {
                    $hadAnyItems = true;
                    break;
                }
            }
            if ($hadAnyItems && empty($normalized)) {
                return self::error('invalid_param', __('advanced_filters has no valid items. Each item needs source: [provider, property], operator, and value. For most agent use cases, the simple top-level filters are enough: tags, lists, statuses, search, contact_type, created_after, created_before.', 'fluent-crm'), [
                    'received_advanced_filters' => $original,
                    'expected_item_shape'       => ['source' => ['provider', 'property'], 'operator' => 'string', 'value' => 'mixed'],
                    'simple_alternatives'       => ['tags', 'lists', 'statuses', 'search', 'contact_type', 'created_after', 'created_before'],
                ]);
            }

            // Validate each (provider, property) pair against the FluentCRM
            // registry. Unrecognized pairs would otherwise silently fall
            // back to "match everyone" (round-4 review P0 #2). We surface
            // the valid pairs in the error so the agent can self-correct.
            $known    = self::knownAdvancedFilterPairs();
            $unknown  = [];
            foreach ($normalized as $group) {
                foreach ($group as $item) {
                    $provider = (string) $item['source'][0];
                    $property = (string) $item['source'][1];
                    $providerProps = $known[$provider] ?? null;
                    if ($providerProps === null) {
                        $unknown[] = ['source' => [$provider, $property], 'reason' => 'unknown_provider'];
                        continue;
                    }
                    if (!in_array($property, $providerProps, true)) {
                        $unknown[] = ['source' => [$provider, $property], 'reason' => 'unknown_property'];
                    }
                }
            }
            if (!empty($unknown)) {
                $compactKnown = [];
                foreach ($known as $providerKey => $props) {
                    $compactKnown[$providerKey] = $props;
                }
                return self::error('invalid_param', __('advanced_filters references unknown (provider, property) pairs. The matching engine would silently fall back to "match everyone" — refusing.', 'fluent-crm'), [
                    'unknown_pairs'  => $unknown,
                    'known_pairs'    => $compactKnown,
                    'tip'            => 'For status / engagement / contact_type targeting, use the simple top-level filter fields instead — they are pre-validated.',
                ]);
            }
        }
        return true;
    }

    /**
     * Drop malformed entries from a caller-provided advanced_filters payload
     * so ContactsQuery::formatAdvancedFilters doesn't fatal on a count(null).
     *
     * Each item must be {source: [provider, property], operator, value[,
     * extra_value]}. Items without a 2-tuple `source` and a non-empty
     * `operator` are silently dropped. Empty groups are removed.
     *
     * @param mixed $groups
     * @return array
     */
    public static function normalizeAdvancedFilters($groups)
    {
        if (!is_array($groups)) {
            return [];
        }

        $out = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $cleaned = [];
            foreach ($group as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $source = $item['source'] ?? null;
                if (!is_array($source) || count($source) !== 2 || empty($source[0]) || empty($source[1])) {
                    continue;
                }
                if (empty($item['operator'])) {
                    continue;
                }
                $cleaned[] = $item;
            }
            if ($cleaned) {
                $out[] = $cleaned;
            }
        }
        return $out;
    }

    /**
     * Cached map of registered (provider => [property, ...]) pairs that
     * FluentCRM actually understands. Used to validate caller-supplied
     * advanced_filters before they hit ContactsQuery — without this gate,
     * an unknown (provider, property) pair causes a silent fallback to
     * "match everyone" because the action hook simply doesn't fire and
     * the where-clause never narrows (round-4 review P0 #2).
     *
     * Source of truth: Helper::getAdvancedFilterOptions() — the same
     * registry the admin UI uses.
     */
    public static function knownAdvancedFilterPairs()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        if (method_exists(\FluentCrm\App\Services\Helper::class, 'getAdvancedFilterOptions')) {
            $opts = \FluentCrm\App\Services\Helper::getAdvancedFilterOptions();
            foreach ((array) $opts as $providerKey => $providerCfg) {
                $children = $providerCfg['children'] ?? [];
                $cache[$providerKey] = [];
                foreach ((array) $children as $child) {
                    if (!empty($child['value'])) {
                        $cache[$providerKey][] = (string) $child['value'];
                    }
                }
            }
        }

        // Subscriber/contact_type isn't always exposed in the admin UI but
        // is a real column we use for the contact_type universal filter.
        if (isset($cache['subscriber']) && !in_array('contact_type', $cache['subscriber'], true)) {
            $cache['subscriber'][] = 'contact_type';
        }

        return $cache;
    }

    // ---------------------------------------------------------------------
    // Content handling
    // ---------------------------------------------------------------------

    /**
     * Strip HTML tags, decode entities, collapse whitespace. Keeps anchor URLs
     * inline as `[text](url)` so plain-text consumers don't lose them.
     */
    public static function htmlToText($html)
    {
        if (!$html) {
            return '';
        }

        $text = preg_replace_callback(
            '/<a[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is',
            function ($m) {
                $url   = trim($m[1]);
                $label = trim(wp_strip_all_tags($m[2]));
                if ($label === '' || $label === $url) {
                    return $url;
                }
                return $label . ' (' . $url . ')';
            },
            (string) $html
        );

        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    public static function detectContentType($body)
    {
        $body = (string) $body;
        // Cheap sniff: an early `<` followed by an ASCII letter signals HTML.
        if (preg_match('/<[a-zA-Z]/', substr($body, 0, 200))) {
            return 'html';
        }
        return 'text';
    }

    public static function dualBodyShape($html)
    {
        $html = (string) $html;
        return [
            'body_html' => $html,
            'body_text' => self::htmlToText($html),
        ];
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    /**
     * Normalize page/per_page from input. Mutates `$_REQUEST` so the framework
     * paginator picks up the values — that is FluentCRM's existing pattern.
     */
    public static function paginationFromInput($input, $defaultPerPage = 15, $maxPerPage = 100)
    {
        $page    = max(1, (int) ($input['page'] ?? 1));
        $perPage = (int) ($input['per_page'] ?? $defaultPerPage);
        if ($perPage < 1) {
            $perPage = $defaultPerPage;
        }
        $perPage = min($perPage, $maxPerPage);

        // Match how FluentCRM controllers expect WP_REQUEST to drive paging.
        $_REQUEST['page']     = $page;
        $_REQUEST['per_page'] = $perPage;

        return ['page' => $page, 'per_page' => $perPage];
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /**
     * Return the registered custom-field slugs for contacts. Cached for
     * the request lifetime — fluentcrm_get_custom_contact_fields() is
     * already statically cached but we don't want to repeat the array
     * walk for every bulk row.
     *
     * @return string[]
     */
    public static function knownContactCustomFieldSlugs()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $fields = fluentcrm_get_custom_contact_fields();
        $cache  = [];
        foreach ((array) $fields as $f) {
            if (!empty($f['slug'])) {
                $cache[] = (string) $f['slug'];
            }
        }
        return $cache;
    }

    /**
     * Diff caller-supplied custom-field keys against the registered
     * schema. Unknown keys would otherwise be silently dropped by
     * Subscriber::syncCustomFieldValues — the agent thinks the value
     * persisted but nothing was saved (operator-test report 2026-05-07
     * #6).
     *
     * @param  array $customFields
     * @return array{known: array<string,mixed>, unknown: string[]}
     */
    public static function diffCustomFields($customFields)
    {
        $known   = [];
        $unknown = [];
        if (!is_array($customFields) || empty($customFields)) {
            return ['known' => $known, 'unknown' => $unknown];
        }
        $allowed = self::knownContactCustomFieldSlugs();
        foreach ($customFields as $key => $value) {
            $slug = sanitize_key((string) $key);
            if ($slug === '') {
                continue;
            }
            if (in_array($slug, $allowed, true)) {
                $known[$slug] = $value;
            } else {
                $unknown[] = (string) $key;
            }
        }
        return ['known' => $known, 'unknown' => array_values(array_unique($unknown))];
    }

    /**
     * Parse and validate an agent-supplied scheduled_at into a DateTime in
     * the site timezone. Operator-test report 2026-05-07 #3 — previously
     * the validated DateTime was discarded and the raw input string was
     * passed through to MySQL, which silently dropped the offset (a
     * datetime column has no timezone). On read, toIso8601 then re-parsed
     * the naive string in PHP's default timezone (UTC), producing wrong
     * absolute times.
     *
     * Input convention:
     *   - ISO-8601 with offset → respected as written.
     *   - Bare datetime / date  → interpreted as SITE timezone (matches
     *     FluentCRM's storage convention).
     *
     * The caller stores `$dt->format('Y-m-d H:i:s')` which is now
     * unambiguous because `$dt` carries the site tz.
     */
    public static function validateScheduledAt($iso, $minFutureSeconds = 60)
    {
        if (!$iso) {
            return self::error('invalid_param', __('scheduled_at is required', 'fluent-crm'));
        }

        $siteTz = self::siteTimezoneObject();
        $input  = (string) $iso;

        try {
            // If the string carries an explicit offset / "Z", DateTime keeps
            // it. If it's bare ("2026-05-08 09:00:00"), pass site tz as
            // the second arg so the moment is interpreted correctly.
            if (self::stringHasTimezone($input)) {
                $dt = new \DateTime($input);
            } else {
                $dt = new \DateTime($input, $siteTz);
            }
        } catch (\Exception $e) {
            return self::error('invalid_param', __('scheduled_at must be ISO 8601 (e.g. 2026-05-08T09:00:00+01:00) or a bare datetime in site timezone (2026-05-08 09:00:00).', 'fluent-crm'), [
                'scheduled_at_input' => $input,
                'site_timezone'      => $siteTz->getName(),
            ]);
        }

        // Convert to site tz so storage in `Y-m-d H:i:s` is consistent with
        // the rest of FluentCRM (which uses current_time('mysql')).
        $dt->setTimezone($siteTz);

        if ($dt->getTimestamp() < (time() + $minFutureSeconds)) {
            return self::error('validation_failed', __('scheduled_at must be in the future', 'fluent-crm'), [
                'scheduled_at_input' => $input,
                'parsed_utc'         => gmdate('c', $dt->getTimestamp()),
                'parsed_site_local'  => $dt->format('Y-m-d H:i:s'),
                'now_utc'            => gmdate('c'),
                'site_timezone'      => $siteTz->getName(),
                'now_site_local'     => wp_date('Y-m-d H:i:s', time()),
            ]);
        }

        return $dt;
    }

    /**
     * Heuristic: does the string carry timezone info (Z or ±HH:MM / ±HHMM)
     * after the time component? Date-only strings always count as bare.
     */
    private static function stringHasTimezone($s)
    {
        return (bool) preg_match('/T?\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', trim((string) $s));
    }

    /**
     * Site timezone as a DateTimeZone — the object form callers need for
     * DateTime construction / setTimezone. wp_timezone() ships in WP 5.3+
     * (we target 6.9+).
     */
    public static function siteTimezoneObject()
    {
        return wp_timezone();
    }

    /**
     * Format a stored mysql datetime (assumed in site tz) into the dual
     * shape callers expose to agents — get-campaign / actionSchedule
     * surface this so an operator never has to guess which timezone a
     * scheduled_at value is in.
     *
     * @return array{utc:?string, site_local:?string, site_timezone:string}|null
     */
    public static function formatScheduledAtDual($value)
    {
        if (!$value) {
            return null;
        }
        $siteTz = self::siteTimezoneObject();

        try {
            // Stored values come from current_time('mysql') / our own
            // $dt->format('Y-m-d H:i:s') — both are site-tz strings.
            // ISO inputs from outside are unlikely here but tolerated.
            if ($value instanceof \DateTimeInterface) {
                $dt = (new \DateTime('@' . $value->getTimestamp()))->setTimezone($siteTz);
            } elseif (self::stringHasTimezone((string) $value)) {
                $dt = (new \DateTime((string) $value))->setTimezone($siteTz);
            } else {
                $dt = new \DateTime((string) $value, $siteTz);
            }
        } catch (\Exception $e) {
            return null;
        }

        return [
            'utc'           => gmdate('c', $dt->getTimestamp()),
            'site_local'    => $dt->format('Y-m-d H:i:s'),
            'site_timezone' => self::siteTimezoneName(),
        ];
    }

    /**
     * Friendly site timezone label. wp_timezone() returns a numeric offset
     * like "+00:00" when gmt_offset is 0 and timezone_string is empty;
     * fluentCrmGetTimezoneString() correctly returns "UTC" in that case.
     */
    public static function siteTimezoneName()
    {
        return (string) fluentCrmGetTimezoneString();
    }

    public static function permissionGuard($cap)
    {
        if (PermissionManager::currentUserCan($cap)) {
            return true;
        }
        return self::error('forbidden', __('You do not have permission to perform this action', 'fluent-crm'), ['required' => $cap]);
    }

    // ---------------------------------------------------------------------
    // Errors
    // ---------------------------------------------------------------------

    public static function error($code, $message, $details = [])
    {
        return new \WP_Error($code, $message, $details);
    }

    // ---------------------------------------------------------------------
    // Misc
    // ---------------------------------------------------------------------

    public static function toIso8601($value)
    {
        if (!$value) {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('c');
            }
            return (new \DateTime((string) $value))->format('c');
        } catch (\Exception $e) {
            return null;
        }
    }
}
