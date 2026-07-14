<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\App\Models\Subject;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;

/**
 * Campaign-centric MCP tools.
 *
 * Read tools (Phase 2): listCampaigns, getCampaign.
 * Write tools (Phase 3): upsertCampaign, changeCampaignStatus.
 *
 * Reads query the Campaign model directly (matching CampaignController's read
 * paths) and shape the result through MCPHelper formatters.
 */
class CampaignTools
{
    // -----------------------------------------------------------------
    // Read: list-campaigns
    // -----------------------------------------------------------------

    public static function listCampaigns($params)
    {
        $params = (array) $params;

        $pagination = MCPHelper::paginationFromInput($params);

        $search = sanitize_text_field((string) ($params['search'] ?? ''));
        $statuses = (array) ($params['statuses'] ?? []);
        $statuses = array_values(array_filter(array_map('sanitize_key', $statuses)));

        // All fc_campaigns columns. The framework rewrite made orderBy() throw
        // LogicException on column names that don't match ^[a-zA-Z0-9_\.]+$
        // — empty strings, "id ASC", "DROP TABLE", etc. — so an unguarded
        // sort_by would 500 the tool. Schema is stable (migration only adds
        // indexes), so hardcoding the column list avoids a per-request
        // SHOW COLUMNS without restricting agents to the input_schema enum.
        $allowedSortBy = [
            'id', 'parent_id', 'type', 'title', 'available_urls', 'slug',
            'status', 'template_id', 'email_subject', 'email_pre_header',
            'email_body', 'recipients_count', 'delay', 'utm_status',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term',
            'utm_content', 'design_template', 'scheduled_at', 'settings',
            'created_by', 'created_at', 'updated_at',
        ];
        $sortBy = sanitize_key((string) ($params['sort_by'] ?? 'created_at'));
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'created_at';
        }
        $sortType = strtoupper(sanitize_text_field((string) ($params['sort_type'] ?? 'DESC')));
        $sortType = in_array($sortType, ['ASC', 'DESC'], true) ? $sortType : 'DESC';
        $includeStats = !isset($params['include_stats']) ? true : (bool) $params['include_stats'];
        // One-off sends (created by send-email-to-contact) live in
        // fc_campaigns with type='custom_email_campaign'. The Campaign
        // model's global scope hides them by default; let agents opt in
        // when they want a unified "what did I send recently" view.
        $includeOneOffs = !empty($params['include_one_offs']);

        if ($includeOneOffs) {
            $query = Campaign::withoutGlobalScope('type')
                ->whereIn('type', ['campaign', 'custom_email_campaign'])
                ->orderBy($sortBy, $sortType);
        } else {
            $query = Campaign::query()->orderBy($sortBy, $sortType);
        }

        if ($search !== '') {
            global $wpdb;
            $query->where('title', 'LIKE', '%' . $wpdb->esc_like($search) . '%');
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        $paginated = $query->paginate();

        $items = [];
        foreach ($paginated->items() as $campaign) {
            $items[] = MCPHelper::formatCampaignSummary($campaign, $includeStats);
        }

        return [
            'items'    => $items,
            'total'    => (int) $paginated->total(),
            'page'     => (int) $paginated->currentPage(),
            'per_page' => (int) $paginated->perPage(),
            'pages'    => (int) $paginated->lastPage(),
        ];
    }

    // -----------------------------------------------------------------
    // Read: get-campaign
    // -----------------------------------------------------------------

    public static function getCampaign($params)
    {
        $params = (array) $params;
        $campaignId = (int) ($params['campaign_id'] ?? 0);

        if (!$campaignId) {
            return MCPHelper::error('invalid_param', __('campaign_id is required', 'fluent-crm'));
        }

        // Bypass the type global scope so one-off sends (created by
        // send-email-to-contact) are reachable too. Without this, an id
        // returned by list-campaigns(include_one_offs=true) couldn't be
        // fetched here — operator-test report 2026-05-07 #4.
        $campaign = Campaign::withoutGlobalScope('type')->find($campaignId);
        if (!$campaign) {
            return MCPHelper::error('not_found', __('Campaign not found', 'fluent-crm'), ['campaign_id' => $campaignId]);
        }

        if ($campaign->type === 'custom_email_campaign') {
            return self::formatOneOffEmail($campaign);
        }

        $defaultIncludes = ['stats'];
        $include = isset($params['include']) && is_array($params['include']) && $params['include']
            ? array_values(array_intersect($params['include'], ['stats', 'subjects', 'link_report', 'recipients_estimate']))
            : $defaultIncludes;

        $body = (string) $campaign->email_body;
        $bodyShape = MCPHelper::dualBodyShape($body);

        $settings = is_array($campaign->settings) ? $campaign->settings : (array) $campaign->settings;

        $recipients = self::recipientsForOutput($settings, 'subscribers');
        $recipientsExcluded = self::recipientsForOutput($settings, 'excludedSubscribers');
        $recipients = [
            'lists'         => $recipients['lists'],
            'tags'          => $recipients['tags'],
            'exclude_lists' => $recipientsExcluded['lists'],
            'exclude_tags'  => $recipientsExcluded['tags'],
        ];

        $sentStatuses = ['archived', 'working', 'paused'];
        $sentAt = in_array($campaign->status, $sentStatuses, true)
            ? MCPHelper::toIso8601($campaign->updated_at)
            : null;

        $data = [
            'id'                => (int) $campaign->id,
            'title'             => $campaign->title,
            'email_subject'     => $campaign->email_subject,
            'email_pre_header'  => $campaign->email_pre_header,
            'status'            => $campaign->status,
            'design_template'   => $campaign->design_template,
            'body_html'         => $bodyShape['body_html'],
            'body_text'         => $bodyShape['body_text'],
            'settings'          => self::settingsForOutput($settings),
            'recipients'        => $recipients,
            'scheduled_at'      => MCPHelper::toIso8601($campaign->scheduled_at),
            'scheduled_at_resolved' => MCPHelper::formatScheduledAtDual($campaign->scheduled_at),
            'sent_at'           => $sentAt,
            'created_at'        => MCPHelper::toIso8601($campaign->created_at),
        ];

        if (in_array('stats', $include, true)) {
            $data['stats'] = MCPHelper::campaignStatsCompact($campaign);
            $data['stats']['revenue'] = self::campaignRevenue($campaign);
        }

        if (in_array('subjects', $include, true)) {
            $data['subjects'] = self::campaignSubjects($campaign);
        }

        if (in_array('link_report', $include, true)) {
            $data['link_report'] = self::campaignLinkReport($campaign);
        }

        if (in_array('recipients_estimate', $include, true)) {
            $data['recipients_estimate'] = self::campaignRecipientsEstimate($campaign);
        }

        return $data;
    }

    /**
     * Render a one-off send (`type=custom_email_campaign`) for get-campaign.
     * One-offs don't have a marketing lifecycle — the row's `status` column
     * stays 'draft' even after delivery (the real status lives on the
     * single fc_campaign_emails row). Surface a `one_off_status` field
     * that reflects what actually happened, plus the recipient. Operator-
     * test report 2026-05-07 #4.
     */
    private static function formatOneOffEmail($campaign)
    {
        $body      = (string) $campaign->email_body;
        $bodyShape = MCPHelper::dualBodyShape($body);

        // The one and only campaign-email row for this send.
        $email = \FluentCrm\App\Models\CampaignEmail::withoutGlobalScope('type')
            ->where('campaign_id', $campaign->id)
            ->orderBy('id', 'DESC')
            ->first();

        $oneOffStatus = $email ? (string) $email->status : 'unknown';
        $sentAt       = $email && in_array($email->status, ['sent', 'opened', 'clicked'], true)
            ? MCPHelper::toIso8601($email->updated_at)
            : null;

        $recipient = null;
        if ($email && $email->subscriber_id) {
            $sub = \FluentCrm\App\Models\Subscriber::find($email->subscriber_id);
            if ($sub) {
                $recipient = [
                    'id'    => (int) $sub->id,
                    'email' => $sub->email,
                    'full_name' => trim((string) $sub->full_name),
                ];
            }
        }

        $settings = is_array($campaign->settings) ? $campaign->settings : (array) $campaign->settings;

        return [
            'id'                => (int) $campaign->id,
            'kind'              => 'one_off_email',
            'title'             => $campaign->title,
            'email_subject'     => $campaign->email_subject,
            'email_pre_header'  => $campaign->email_pre_header,
            'one_off_status'    => $oneOffStatus,
            'design_template'   => $campaign->design_template,
            'body_html'         => $bodyShape['body_html'],
            'body_text'         => $bodyShape['body_text'],
            'settings'          => self::settingsForOutput($settings),
            'recipient'         => $recipient,
            'sent_at'           => $sentAt,
            'created_at'        => MCPHelper::toIso8601($campaign->created_at),
            'note'              => __('This is a one-off send (not a marketing campaign). It supports change-campaign-status action=delete only — schedule/pause/resume/duplicate are not applicable.', 'fluent-crm'),
        ];
    }

    /**
     * Reverse the flat [{list, tag}] storage back into the agent-friendly
     * {lists:[{id,title}], tags:[{id,title}]} shape for output. Items with
     * 'all' on either side are treated as wildcards (omitted from the
     * matching collection). Distinct ids only.
     */
    private static function recipientsForOutput($settings, $key)
    {
        $items = $settings[$key] ?? [];
        if (!is_array($items)) {
            return ['lists' => [], 'tags' => []];
        }

        $listIds = [];
        $tagIds  = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $listId = $item['list'] ?? null;
            $tagId  = $item['tag'] ?? null;
            if ($listId !== null && $listId !== '' && $listId !== 'all' && is_numeric($listId)) {
                $listIds[(int) $listId] = true;
            }
            if ($tagId !== null && $tagId !== '' && $tagId !== 'all' && is_numeric($tagId)) {
                $tagIds[(int) $tagId] = true;
            }
        }

        $listsOut = [];
        if ($listIds) {
            foreach (\FluentCrm\App\Models\Lists::whereIn('id', array_keys($listIds))->get() as $list) {
                $listsOut[] = ['id' => (int) $list->id, 'title' => $list->title];
            }
        }
        $tagsOut = [];
        if ($tagIds) {
            foreach (\FluentCrm\App\Models\Tag::whereIn('id', array_keys($tagIds))->get() as $tag) {
                $tagsOut[] = ['id' => (int) $tag->id, 'title' => $tag->title];
            }
        }

        return ['lists' => $listsOut, 'tags' => $tagsOut];
    }

    private static function settingsForOutput($settings)
    {
        return [
            'mailer_settings'  => $settings['mailer_settings'] ?? new \stdClass(),
            'is_transactional' => $settings['is_transactional'] ?? 'no',
            'click_tracker'    => $settings['click_tracker'] ?? 'yes',
            'open_tracker'     => $settings['open_tracker'] ?? 'yes',
        ];
    }

    /**
     * Revenue meta is shaped like ['orderIds' => [1,2,3], 'usd' => 12450,
     * 'eur' => 600] — currency keys are interleaved with the
     * 'orderIds' tracking array. Iterating naïvely and grabbing the first
     * key produced `currency: "orderIds"` and a wrong amount (round-4
     * review P1 #7). Skip the meta key, sum numeric values across all
     * currency entries, and surface the order count.
     */
    private static function campaignRevenue($campaign)
    {
        $revenue = fluentcrm_get_campaign_meta($campaign->id, '_campaign_revenue', true);
        if (!is_array($revenue)) {
            return ['total' => 0, 'currency' => '', 'orders_count' => 0, 'by_currency' => []];
        }

        $orderCount = isset($revenue['orderIds']) && is_array($revenue['orderIds'])
            ? count($revenue['orderIds'])
            : 0;

        $byCurrency = [];
        foreach ($revenue as $key => $value) {
            if ($key === 'orderIds' || !is_numeric($value)) {
                continue;
            }
            $byCurrency[strtoupper((string) $key)] = (float) $value;
        }

        // Pick a primary currency for the headline number (first one set
        // wins; multi-currency installs surface the rest in by_currency).
        $primaryCurrency = '';
        $primaryTotal    = 0.0;
        if ($byCurrency) {
            $primaryCurrency = (string) array_key_first($byCurrency);
            $primaryTotal    = (float) $byCurrency[$primaryCurrency];
        }

        return [
            'total'        => $primaryTotal,
            'currency'     => $primaryCurrency,
            'orders_count' => $orderCount,
            'by_currency'  => $byCurrency,
        ];
    }

    /**
     * A/B subjects live in fc_meta keyed by object_id (NOT campaign_id —
     * that column doesn't exist on fc_meta). The Subject model's global
     * scope handles the object_type filter.
     *
     * Storage shape (per Campaign::syncSubjects): `key` is a stable
     * identifier, `value` is the subject string itself. Operator-test
     * report 2026-05-07 #7 — the read path previously assumed `value`
     * was a serialized {email_subject, weight} array (which it isn't),
     * so the response always came back empty even after a successful
     * upsert.
     */
    private static function campaignSubjects($campaign)
    {
        $subjects = Subject::where('object_id', $campaign->id)->get();
        $out = [];
        foreach ($subjects as $subject) {
            $out[] = [
                'id'      => (int) $subject->id,
                'key'     => (string) $subject->key,
                'value'   => (string) $subject->value,
            ];
        }
        return $out;
    }

    /**
     * Link report — delegate to CampaignUrlMetric::getLinksReport, which
     * joins fc_campaign_url_metrics → fc_url_stores correctly (the metrics
     * table only stores url_id; the URL string lives in fc_url_stores).
     */
    private static function campaignLinkReport($campaign)
    {
        $metric = new CampaignUrlMetric();
        $links  = method_exists($metric, 'getLinksReport') ? $metric->getLinksReport($campaign) : [];

        $formatted = [];
        foreach ((array) $links as $link) {
            $formatted[] = [
                'url'          => $link['url'] ?? '',
                'total_clicks' => (int) ($link['total'] ?? 0),
            ];
        }

        return [
            'links'        => $formatted,
            'click_status' => method_exists($campaign, 'getClickTrackingStatus') ? $campaign->getClickTrackingStatus(false) : 'yes',
            'open_status'  => method_exists($campaign, 'getOpenTrackingStatus') ? $campaign->getOpenTrackingStatus(false) : 'yes',
        ];
    }

    /**
     * Find the first available title shaped like "Title (2)", "Title (3)", …
     * given a base title that already exists. Mirrors what CampaignController
     * ::ensureUniqueDefaultTitle does for the "Untitled" placeholder, but
     * for any agent-supplied title.
     */
    private static function nextAvailableTitle($baseTitle)
    {
        $count = 2;
        while ($count < 1000) {
            $candidate = $baseTitle . ' (' . $count . ')';
            if (!Campaign::where('title', $candidate)->exists()) {
                return $candidate;
            }
            $count++;
        }
        // Astronomical fallback — append a timestamp to guarantee uniqueness.
        return $baseTitle . ' (' . time() . ')';
    }

    /**
     * Estimate recipients for a campaign using its stored segment settings.
     *
     * Routes through the same `Campaign::getSubscriberIdsCountBySegmentSettings`
     * helper as `upsert-campaign.estimated_recipients` and the underlying
     * estimator behind `estimate-dynamic-segment` (via ContactsQuery's filter
     * translation) — so all three callers converge on the same number for the
     * same filter (review bug #4).
     */
    private static function campaignRecipientsEstimate($campaign)
    {
        if (!in_array($campaign->status, ['draft', 'scheduled', 'pending-scheduled'], true)) {
            return null;
        }

        $settings = is_array($campaign->settings) ? $campaign->settings : (array) maybe_unserialize($campaign->settings);

        $start = microtime(true);
        $count = self::estimateRecipientsFromSettings($settings);
        $execMs = (int) round((microtime(true) - $start) * 1000);

        return [
            'count'             => $count !== null ? (int) $count : 0,
            'execution_time_ms' => $execMs,
        ];
    }

    // -----------------------------------------------------------------
    // Write: upsert-campaign
    // -----------------------------------------------------------------

    public static function upsertCampaign($params)
    {
        $params = (array) $params;
        $campaignId = isset($params['campaign_id']) ? (int) $params['campaign_id'] : 0;

        $isNew = !$campaignId;
        $campaign = null;

        if (!$isNew) {
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                return MCPHelper::error('not_found', __('Campaign not found', 'fluent-crm'), ['campaign_id' => $campaignId]);
            }
            $editableStatuses = ['draft', 'scheduled', 'pending-scheduled'];
            if (!in_array($campaign->status, $editableStatuses, true)) {
                return MCPHelper::error('not_supported', __('Cannot edit campaign in current status', 'fluent-crm'), [
                    'status'           => $campaign->status,
                    'allowed_statuses' => $editableStatuses,
                ]);
            }
        }

        // Title is required for create.
        $title = isset($params['title']) ? sanitize_text_field((string) $params['title']) : '';
        if ($isNew && $title === '') {
            return MCPHelper::error('invalid_param', __('title is required when creating a campaign', 'fluent-crm'));
        }

        // Pre-flight validation. Must run before Campaign::create() so a
        // bad recipients shape / design_template / unsupported keys never
        // leaves an orphaned draft row behind (operator-test report
        // 2026-05-07 #2).
        $validation = self::validateUpsertInput($params);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $titleConflictWarning = null;
        if ($isNew) {
            // Title uniqueness: by default we auto-suffix on conflict so
            // an agent retrying after a transient error doesn't get stuck.
            // Round 2 #10: when we suffix, surface a warning so the agent
            // can tell the user "I created a copy named X". Strict mode
            // (if_exists='error') keeps the hard-error behavior.
            $ifExists = isset($params['if_exists']) ? sanitize_key((string) $params['if_exists']) : 'auto_suffix';
            $originalTitle = $title;
            if ($title !== '' && Campaign::where('title', $title)->exists()) {
                if ($ifExists === 'error') {
                    return MCPHelper::error('invalid_param', __('A campaign with that title already exists', 'fluent-crm'), [
                        'title' => $title,
                    ]);
                }
                $existingId = (int) Campaign::where('title', $title)->value('id');
                $title = self::nextAvailableTitle($title);
                $titleConflictWarning = sprintf(
                    /* translators: 1: requested title, 2: existing campaign id, 3: actual title used */
                    __('A campaign with title "%1$s" already exists (id %2$d). Created this one as "%3$s" instead. Pass if_exists="error" to make conflicts a hard failure.', 'fluent-crm'),
                    $originalTitle,
                    $existingId,
                    $title
                );
            }
            $campaign = Campaign::create(['title' => $title]);
            do_action('fluent_crm/campaign_created', $campaign);

            // If we auto-suffixed, sync the new title back into $params so
            // the passthru loop below doesn't overwrite the suffix with the
            // original (review B4 round 3). Without this, the warning was
            // lying — it said "Created as X (2)" but the saved title was
            // still "X" because passthru clobbered it.
            if ($titleConflictWarning) {
                $params['title'] = $title;
            }
        }

        // Build the update payload.
        $updateData = [];
        $passthru = ['title', 'email_subject', 'email_pre_header', 'email_body', 'design_template'];
        foreach ($passthru as $field) {
            if (array_key_exists($field, $params) && $params[$field] !== null) {
                $updateData[$field] = $params[$field];
            }
        }

        // design_template was already validated by validateUpsertInput()
        // above; sanitize_key here is just to normalize for storage.
        if (isset($updateData['design_template']) && $updateData['design_template'] !== '') {
            $updateData['design_template'] = sanitize_key((string) $updateData['design_template']);
        }

        if (!empty($params['utm']) && is_array($params['utm'])) {
            foreach ($params['utm'] as $key => $value) {
                $col = 'utm_' . sanitize_key($key);
                $updateData[$col] = sanitize_text_field((string) $value);
            }
        }

        // Settings — merge into existing.
        $settings = $campaign->settings ?: [];
        if (is_string($settings)) {
            $settings = (array) maybe_unserialize($settings);
        }

        if (!empty($params['settings']) && is_array($params['settings'])) {
            $settings = array_replace_recursive((array) $settings, $params['settings']);
        }

        // Recipients — universal filter shape lands in `settings.subscribers`.
        // Shape was already validated by validateUpsertInput(). Campaigns
        // can ONLY persist tags + lists, so anything else would silently
        // disappear (round-4 review P0 #1 — 3.5x audience inflation).
        if (!empty($params['recipients']) && is_array($params['recipients'])) {
            $settings['subscribers'] = self::filterToCampaignSegment($params['recipients']);
        }
        if (!empty($params['exclude_recipients']) && is_array($params['exclude_recipients'])) {
            $settings['excludedSubscribers'] = self::filterToCampaignSegment($params['exclude_recipients']);
        }

        // If the agent passed a top-level design_template, propagate it into
        // settings.template_config so the two stay in sync. Only fall the
        // other direction (template_config -> top-level) when the agent did
        // NOT specify design_template — otherwise the boot's default
        // template_config (set to 'simple' on this install) would clobber
        // the agent's choice (round 2 #29).
        if (isset($updateData['design_template']) && $updateData['design_template'] !== '') {
            if (!isset($settings['template_config']) || !is_array($settings['template_config'])) {
                $settings['template_config'] = [];
            }
            $settings['template_config']['design_template'] = $updateData['design_template'];
        } elseif (!empty($settings['template_config']['design_template'])) {
            $updateData['design_template'] = $settings['template_config']['design_template'];
        }

        $updateData['settings'] = $settings;
        $updateData = \FluentCrm\App\Services\Sanitize::campaign($updateData);

        $campaign->fill($updateData)->save();

        // Subjects (A/B): syncSubjects requires {key, value}; the agent
        // can pass {value} alone (key auto-generated). Items missing a
        // value are dropped by syncSubjects, so prepare a clean payload
        // here. Operator-test report 2026-05-07 #7.
        if (!empty($params['subjects']) && is_array($params['subjects']) && method_exists($campaign, 'syncSubjects')) {
            $prepared = [];
            foreach (array_values($params['subjects']) as $i => $s) {
                if (!is_array($s) || empty($s['value'])) {
                    continue;
                }
                $value = sanitize_text_field((string) $s['value']);
                $key   = !empty($s['key']) ? sanitize_text_field((string) $s['key']) : substr(md5($value . '|' . $i), 0, 16);
                $item  = ['key' => $key, 'value' => $value];
                if (!empty($s['id'])) {
                    $item['id'] = (int) $s['id'];
                }
                $prepared[] = $item;
            }
            if ($prepared) {
                $campaign->syncSubjects($prepared);
            }
        }

        if (!empty($params['label_ids']) && is_array($params['label_ids']) && method_exists($campaign, 'attachLabels')) {
            $campaign->attachLabels(array_map('intval', $params['label_ids']));
        }

        do_action('fluent_crm/campaign_data_updated', $campaign, $params);

        // Estimate recipients if a segment was provided so the agent sees the
        // count in the same call.
        $estimated = null;
        $warnings = [];
        if (!empty($settings['subscribers']) || !empty($settings['excludedSubscribers'])) {
            $estimated = self::estimateRecipientsFromSettings($settings);
        }

        if (empty($settings['excludedSubscribers'])) {
            $warnings[] = __('No exclude_recipients set — campaign may include unsubscribed segments', 'fluent-crm');
        }
        if (empty($params['email_pre_header']) && empty($campaign->email_pre_header)) {
            $warnings[] = __('email_pre_header not set — better deliverability with a preheader', 'fluent-crm');
        }
        if ($titleConflictWarning) {
            $warnings[] = $titleConflictWarning;
        }

        $campaign = Campaign::find($campaign->id);

        return [
            'ok'                   => true,
            'action'               => $isNew ? 'created' : 'updated',
            'campaign'             => self::getCampaign(['campaign_id' => (int) $campaign->id, 'include' => ['stats']]),
            'estimated_recipients' => $estimated,
            'warnings'             => $warnings,
        ];
    }

    /**
     * Pre-flight validation for upsert-campaign — must run BEFORE any
     * Campaign row is created so a bad payload never leaves an orphan
     * draft (operator-test report 2026-05-07 #2). Centralizes:
     *
     *   - design_template enum (free includes only, no visual_builder)
     *   - recipients shape (lists/tags/sending_filter only)
     *   - exclude_recipients shape (same constraint)
     *
     * @return true|\WP_Error
     */
    private static function validateUpsertInput($params)
    {
        if (isset($params['design_template']) && $params['design_template'] !== null && $params['design_template'] !== '') {
            $designTemplate = sanitize_key((string) $params['design_template']);
            $allowed = array_keys(\FluentCrm\App\Modules\MCP\Tools\ContextTools::allowedDesignTemplates());
            if (!in_array($designTemplate, $allowed, true)) {
                return MCPHelper::error('invalid_param', __('design_template not allowed via MCP', 'fluent-crm'), [
                    'design_template' => $designTemplate,
                    'allowed'         => $allowed,
                ]);
            }
        }

        if (!empty($params['recipients']) && is_array($params['recipients'])) {
            $reject = self::rejectUnsupportedRecipientKeys($params['recipients'], 'recipients');
            if (is_wp_error($reject)) {
                return $reject;
            }
        }

        if (!empty($params['exclude_recipients']) && is_array($params['exclude_recipients'])) {
            $reject = self::rejectUnsupportedRecipientKeys($params['exclude_recipients'], 'exclude_recipients');
            if (is_wp_error($reject)) {
                return $reject;
            }
        }

        return true;
    }

    /**
     * Campaigns can target lists + tags only. The `recipients` object on
     * upsert-campaign accepts the universal filter shape but anything
     * other than {tags, lists, sending_filter} is silently dropped on
     * persistence — and previously that drop didn't even produce a
     * warning. Hard-error now so the agent can take the documented
     * workaround (apply a temporary tag, target by tag, remove after
     * send).
     *
     * Round-4 review P0 #1 — single most damaging bug, 3.5x audience
     * inflation in one test.
     *
     * @param array  $recipients
     * @param string $paramName Either 'recipients' or 'exclude_recipients'
     * @return true|\WP_Error
     */
    private static function rejectUnsupportedRecipientKeys($recipients, $paramName)
    {
        $supported = ['lists', 'tags', 'sending_filter'];
        $unsupported = array_values(array_diff(array_keys($recipients), $supported));
        if (!empty($unsupported)) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: 1: parameter name, 2: comma-separated unsupported keys */
                __('%1$s only persists lists + tags on a campaign — these keys would be silently dropped: %2$s. To target a status/engagement/contact-type segment, apply a temporary tag via apply-segments-to-contacts (use dry_run first), use that tag in recipients, then remove it after the send completes.', 'fluent-crm'),
                $paramName,
                implode(', ', $unsupported)
            ), [
                'unsupported_keys' => $unsupported,
                'supported_keys'   => $supported,
                'workaround'       => '1) apply-segments-to-contacts(filter={...}, add_tags=["temp-X"], dry_run=true); 2) re-run without dry_run; 3) upsert-campaign(recipients={tags:["temp-X"]}); 4) after change-campaign-status(action=schedule), apply-segments-to-contacts(filter={tags:["temp-X"]}, remove_tags=["temp-X"]); 5) manage-tag(action=delete, tag_id=X)',
            ]);
        }
        return true;
    }

    /**
     * Translate the universal filter shape to the flat
     * [{list, tag}] array that Campaign settings.subscribers expects
     * (see Campaign::getSubscribeIdsByListModel — its loop reads
     * `$item['list']` and `$item['tag']` directly).
     *
     * Semantics match ContactsQuery:
     *   - tags only        → [{list:'all', tag:N}, ...]            (tag IN [...])
     *   - lists only       → [{list:N, tag:'all'}, ...]            (list IN [...])
     *   - lists AND tags   → cross product so every (list, tag) pair lands
     *                        in queryGroups, ANDed within a pair, ORed across
     *
     * Storage shape: array of {list, tag} pairs at the top level
     * (NOT a nested {lists:[], tags:[]} object — that was the round-1 bug).
     */
    private static function filterToCampaignSegment($filter)
    {
        $tagIds = !empty($filter['tags'])
            ? MCPHelper::resolveTagIds((array) $filter['tags'])['ids']
            : [];
        $listIds = !empty($filter['lists'])
            ? MCPHelper::resolveListIds((array) $filter['lists'])['ids']
            : [];

        $items = [];

        if (!$tagIds && !$listIds) {
            return $items;
        }

        if ($tagIds && $listIds) {
            foreach ($listIds as $listId) {
                foreach ($tagIds as $tagId) {
                    $items[] = ['list' => (string) $listId, 'tag' => (string) $tagId];
                }
            }
        } elseif ($tagIds) {
            foreach ($tagIds as $tagId) {
                $items[] = ['list' => 'all', 'tag' => (string) $tagId];
            }
        } else {
            foreach ($listIds as $listId) {
                $items[] = ['list' => (string) $listId, 'tag' => 'all'];
            }
        }

        return $items;
    }

    private static function estimateRecipientsFromSettings($settings)
    {
        try {
            $count = (new Campaign())->getSubscriberIdsCountBySegmentSettings([
                'subscribers'         => $settings['subscribers'] ?? [],
                'excludedSubscribers' => $settings['excludedSubscribers'] ?? [],
                'sending_filter'      => $settings['sending_filter'] ?? 'list_tag',
                'dynamic_segment'     => $settings['dynamic_segment'] ?? null,
                'advanced_filters'    => $settings['advanced_filters'] ?? [],
            ]);
            return (int) $count;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Write: change-campaign-status
    // -----------------------------------------------------------------

    public static function changeCampaignStatus($params)
    {
        $params = (array) $params;
        $campaignId = (int) ($params['campaign_id'] ?? 0);
        $action = sanitize_key((string) ($params['action'] ?? ''));

        if (!$campaignId) {
            return MCPHelper::error('invalid_param', __('campaign_id is required', 'fluent-crm'));
        }
        if (!in_array($action, ['schedule', 'unschedule', 'pause', 'resume', 'duplicate', 'delete'], true)) {
            return MCPHelper::error('invalid_param', __('Invalid action', 'fluent-crm'));
        }

        // Bypass the type scope so one-off sends are reachable. Allowed
        // actions on one-offs are restricted below — operator-test
        // report 2026-05-07 #4.
        $campaign = Campaign::withoutGlobalScope('type')->find($campaignId);
        if (!$campaign) {
            return MCPHelper::error('not_found', __('Campaign not found', 'fluent-crm'), ['campaign_id' => $campaignId]);
        }

        if ($campaign->type === 'custom_email_campaign' && $action !== 'delete') {
            return MCPHelper::error('not_supported', __('One-off email sends only support action=delete. They do not have a marketing-campaign lifecycle (schedule/pause/resume/duplicate).', 'fluent-crm'), [
                'campaign_id'      => $campaignId,
                'campaign_type'    => 'one_off',
                'allowed_actions'  => ['delete'],
            ]);
        }

        $previousStatus = $campaign->status;

        switch ($action) {
            case 'schedule':
                return self::actionSchedule($campaign, $params, $previousStatus);
            case 'unschedule':
                return self::actionUnschedule($campaign, $previousStatus);
            case 'pause':
                return self::actionPause($campaign, $previousStatus);
            case 'resume':
                return self::actionResume($campaign, $previousStatus);
            case 'duplicate':
                return self::actionDuplicate($campaign, $params);
            case 'delete':
                return self::actionDelete($campaign);
        }

        return MCPHelper::error('invalid_param', __('Unhandled action', 'fluent-crm'));
    }

    private static function actionSchedule($campaign, $params, $previousStatus)
    {
        if ($campaign->status !== 'draft') {
            return MCPHelper::error('not_supported', __('Only draft campaigns can be scheduled', 'fluent-crm'), [
                'status' => $campaign->status,
            ]);
        }

        $sendingType = $params['sending_type'] ?? ($params['scheduled_at'] ?? null ? 'schedule' : 'instant');

        if ($sendingType === 'instant') {
            $scheduleAt = null;
        } elseif ($sendingType === 'range_schedule') {
            $range = $params['schedule_range'] ?? [];
            if (!is_array($range) || count($range) !== 2) {
                return MCPHelper::error('invalid_param', __('schedule_range must be [start, end]', 'fluent-crm'));
            }
            // Each end gets the same tz-aware parse as a single scheduled_at.
            $rangeStart = MCPHelper::validateScheduledAt(sanitize_text_field($range[0]));
            if (is_wp_error($rangeStart)) {
                return $rangeStart;
            }
            $rangeEnd = MCPHelper::validateScheduledAt(sanitize_text_field($range[1]));
            if (is_wp_error($rangeEnd)) {
                return $rangeEnd;
            }
            if ($rangeEnd->getTimestamp() <= $rangeStart->getTimestamp()) {
                return MCPHelper::error('invalid_param', __('schedule_range end must be after the start time', 'fluent-crm'), [
                    'start' => $rangeStart->format('Y-m-d H:i:s'),
                    'end'   => $rangeEnd->format('Y-m-d H:i:s'),
                ]);
            }
            $scheduleAt = [
                $rangeStart->format('Y-m-d H:i:s'),
                $rangeEnd->format('Y-m-d H:i:s'),
            ];
        } else {
            $rawInput = sanitize_text_field((string) ($params['scheduled_at'] ?? ''));
            if (!$rawInput) {
                return MCPHelper::error('invalid_param', __('scheduled_at is required for sending_type=schedule', 'fluent-crm'));
            }
            $dt = MCPHelper::validateScheduledAt($rawInput);
            if (is_wp_error($dt)) {
                return $dt;
            }
            // Store as a site-tz mysql string so MySQL has no ambiguity and
            // the read path doesn't have to guess which tz the row is in
            // (operator-test report 2026-05-07 #3).
            $scheduleAt = $dt->format('Y-m-d H:i:s');
        }

        // Auto-commit recipients if not yet set so we can read recipients_count.
        if (!$campaign->recipients_count) {
            $settings = is_array($campaign->settings) ? $campaign->settings : (array) maybe_unserialize($campaign->settings);
            if (!empty($settings['subscribers']) || !empty($settings['excludedSubscribers'])) {
                $count = (new Campaign())->getSubscriberIdsCountBySegmentSettings($settings);
                $campaign->recipients_count = (int) $count;
                $campaign->save();
            }
        }

        if (!$campaign->recipients_count) {
            return MCPHelper::error('validation_failed', __('No recipients found for this campaign', 'fluent-crm'));
        }

        // Now apply the same transition the controller does.
        $settings = is_array($campaign->settings) ? $campaign->settings : (array) maybe_unserialize($campaign->settings);

        if ($scheduleAt === null) {
            $settings['sending_type'] = 'instant';
            $update = [
                'status'           => 'processing',
                'updated_at'       => fluentCrmTimestamp(),
                'scheduled_at'     => fluentCrmTimestamp(),
                'recipients_count' => 0,
                'settings'         => $settings,
            ];
        } elseif (is_array($scheduleAt)) {
            $settings['sending_type']  = 'range_schedule';
            $settings['schedule_range'] = [strtotime($scheduleAt[0]), strtotime($scheduleAt[1])];
            $update = [
                'status'           => 'pending-scheduled',
                'updated_at'       => fluentCrmTimestamp(),
                'scheduled_at'     => $scheduleAt[0],
                'recipients_count' => 0,
                'settings'         => $settings,
            ];
        } else {
            $settings['sending_type'] = 'schedule';
            $update = [
                'status'           => 'pending-scheduled',
                'updated_at'       => fluentCrmTimestamp(),
                'scheduled_at'     => $scheduleAt,
                'recipients_count' => 0,
                'settings'         => $settings,
            ];
        }

        // Only seed trackers from site defaults when the campaign hasn't
        // explicitly set them already (review #31). The previous
        // unconditional override clobbered an agent's
        // settings.click_tracker='yes' to the site default 'anonymous'.
        if (!isset($update['settings']['click_tracker']) || $update['settings']['click_tracker'] === '') {
            $update['settings']['click_tracker'] = fluentcrmTrackClicking();
        }
        if (!isset($update['settings']['open_tracker']) || $update['settings']['open_tracker'] === '') {
            $update['settings']['open_tracker'] = fluentcrmTrackEmailOpen();
        }
        $update['settings'] = maybe_serialize($update['settings']);

        $updated = Campaign::where('id', $campaign->id)->where('status', 'draft')->update($update);
        if (!$updated) {
            return MCPHelper::error('failed', __('Could not transition campaign — status changed concurrently', 'fluent-crm'));
        }

        // Wipe pre-processed emails only after the draft-status transition wins.
        \FluentCrm\App\Models\CampaignEmail::where('campaign_id', $campaign->id)->delete();
        fluentcrm_update_campaign_meta($campaign->id, '_recipient_processed', 0);
        fluentcrm_update_campaign_meta($campaign->id, '_last_recipient_id', 0);

        $campaign = Campaign::find($campaign->id);
        fluentcrm_update_campaign_meta($campaign->id, '_campaign_sent_by', get_current_user_id());

        if ($scheduleAt) {
            do_action('fluent_crm/campaign_scheduled', $campaign, $campaign->scheduled_at);
        } else {
            do_action('fluent_crm/campaign_set_send_now', $campaign);
        }

        return [
            'ok'                       => true,
            'action'                   => 'schedule',
            'campaign'                 => self::getCampaign(['campaign_id' => (int) $campaign->id]),
            'previous_status'          => $previousStatus,
            'current_status'           => $campaign->status,
            'scheduled_at'             => MCPHelper::toIso8601($campaign->scheduled_at),
            'scheduled_at_resolved'    => MCPHelper::formatScheduledAtDual($campaign->scheduled_at),
        ];
    }

    private static function actionUnschedule($campaign, $previousStatus)
    {
        if (!in_array($campaign->status, ['scheduled', 'pending-scheduled', 'processing'], true)) {
            return MCPHelper::error('not_supported', __('Campaign is not in a schedulable state', 'fluent-crm'), [
                'status' => $campaign->status,
            ]);
        }

        $campaign->status = 'draft';
        // Clear the stale schedule timestamp so UIs (and get-campaign) don't
        // misreport "scheduled for ..." after the campaign has been
        // un-scheduled (review #32).
        $campaign->scheduled_at = null;
        $campaign->save();

        \FluentCrm\App\Models\CampaignEmail::where('campaign_id', $campaign->id)->delete();
        \FluentCrm\App\Models\CampaignEmail::withoutGlobalScope('type')
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', ['scheduled', 'scheduling'])
            ->delete();

        return [
            'ok'              => true,
            'action'          => 'unschedule',
            'campaign'        => self::getCampaign(['campaign_id' => (int) $campaign->id]),
            'previous_status' => $previousStatus,
            'current_status'  => $campaign->status,
        ];
    }

    private static function actionPause($campaign, $previousStatus)
    {
        if ($campaign->status !== 'working') {
            return MCPHelper::error('not_supported', __('Only working campaigns can be paused', 'fluent-crm'), [
                'status' => $campaign->status,
            ]);
        }
        $campaign->status = 'paused';
        $campaign->save();
        \FluentCrm\App\Models\CampaignEmail::where('campaign_id', $campaign->id)
            ->whereIn('status', ['scheduled', 'pending', 'scheduling'])
            ->update(['status' => 'paused']);

        return [
            'ok'              => true,
            'action'          => 'pause',
            'campaign'        => self::getCampaign(['campaign_id' => (int) $campaign->id]),
            'previous_status' => $previousStatus,
            'current_status'  => 'paused',
        ];
    }

    private static function actionResume($campaign, $previousStatus)
    {
        if ($campaign->status !== 'paused') {
            return MCPHelper::error('not_supported', __('Only paused campaigns can be resumed', 'fluent-crm'), [
                'status' => $campaign->status,
            ]);
        }
        $campaign->status = 'working';
        $campaign->save();
        \FluentCrm\App\Models\CampaignEmail::where('campaign_id', $campaign->id)
            ->where('status', 'paused')
            ->update([
                'status'       => 'scheduled',
                'scheduled_at' => current_time('mysql'),
            ]);

        return [
            'ok'              => true,
            'action'          => 'resume',
            'campaign'        => self::getCampaign(['campaign_id' => (int) $campaign->id]),
            'previous_status' => $previousStatus,
            'current_status'  => 'working',
        ];
    }

    private static function actionDuplicate($campaign, $params)
    {
        $newTitle = isset($params['new_title']) && $params['new_title'] !== ''
            ? sanitize_text_field((string) $params['new_title'])
            : __('[Duplicate] ', 'fluent-crm') . $campaign->title;

        $newCampaign = [
            'title'            => $newTitle,
            'slug'             => $campaign->slug . '-' . time(),
            'email_body'       => $campaign->email_body,
            'status'           => 'draft',
            'template_id'      => $campaign->template_id,
            'email_subject'    => $campaign->email_subject,
            'email_pre_header' => $campaign->email_pre_header,
            'utm_status'       => $campaign->utm_status,
            'utm_source'       => $campaign->utm_source,
            'utm_medium'       => $campaign->utm_medium,
            'utm_campaign'     => $campaign->utm_campaign,
            'utm_term'         => $campaign->utm_term,
            'utm_content'      => $campaign->utm_content,
            'design_template'  => $campaign->design_template,
            'created_by'       => get_current_user_id(),
            'settings'         => $campaign->settings,
        ];

        $copy = Campaign::create($newCampaign);

        if (method_exists($campaign, 'getFormattedLabels')) {
            $labelIds = $campaign->getFormattedLabels()->pluck('id')->toArray();
            if ($labelIds && method_exists($copy, 'attachLabels')) {
                $copy->attachLabels($labelIds);
            }
        }
        if (method_exists($copy, 'duplicateSubjects')) {
            $copy->duplicateSubjects($campaign);
        }

        do_action('fluent_crm/campaign_duplicated', $copy, $campaign);

        return [
            'ok'                   => true,
            'action'               => 'duplicate',
            'campaign'             => self::getCampaign(['campaign_id' => (int) $copy->id]),
            'duplicated_from_id'   => (int) $campaign->id,
        ];
    }

    private static function actionDelete($campaign)
    {
        if (!\FluentCrm\App\Services\PermissionManager::currentUserCan('fcrm_manage_email_delete')) {
            return MCPHelper::error('forbidden', __('Deleting campaigns requires fcrm_manage_email_delete', 'fluent-crm'));
        }

        $campaignId = (int) $campaign->id;
        if (method_exists($campaign, 'deleteCampaignData')) {
            $campaign->deleteCampaignData();
        }
        $campaign->delete();
        do_action('fluent_crm/campaign_deleted', $campaignId);

        return [
            'ok'         => true,
            'action'     => 'delete',
            'deleted_id' => $campaignId,
        ];
    }
}
