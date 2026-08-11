<?php

namespace FluentCrm\App\Models;

use FluentCrm\App\Services\ContactsQuery;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\App\Services\Libs\Parser\Parser;

/**
 *  Campaign Model - DB Model for Campaigns
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.0.0
 */
class Campaign extends Model
{
    protected $table = 'fc_campaigns';

    protected $guarded = ['id'];

    protected static $type = 'campaign';

    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $defaultTemplate = $model->design_template ? $model->design_template : Helper::getDefaultEmailTemplate();
            $model->email_body = $model->email_body ?: '';
            $model->status = $model->status ?: 'draft';
            $model->type = static::$type;
            $model->design_template = $defaultTemplate;
            $model->slug = $model->slug ?: sanitize_title($model->title, '', 'preview');
            $model->created_by = $model->created_by ?: get_current_user_id();
            $model->settings = $model->settings ?: [
                'mailer_settings'     => [
                    'from_name'      => '',
                    'from_email'     => '',
                    'reply_to_name'  => '',
                    'reply_to_email' => '',
                    'is_custom'      => 'no'
                ],
                'subscribers'         => [
                    [
                        'list' => 'all',
                        'tag'  => 'all'
                    ]
                ],
                'excludedSubscribers' => [
                    [
                        'list' => null,
                        'tag'  => null
                    ]
                ],
                'sending_filter'      => 'list_tag',
                'dynamic_segment'     => [
                    'id'   => '',
                    'slug' => ''
                ],
                'advanced_filters'    => [[]],
                'template_config'     => Helper::getTemplateConfig($defaultTemplate),
                'sending_type'        => 'instant',
                'is_transactional'    => 'no'
            ];
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', '=', static::$type);
        });
    }

    public function setSlugAttribute($slug)
    {
        $this->attributes['slug'] = \sanitize_title($slug, '', 'preview');
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($settings)
    {
        $settings = \maybe_unserialize($settings);
        $settings = is_array($settings) ? $settings : [];
        $templateConfig = Arr::get($settings, 'template_config', []);

        $defaultConfig = Helper::getTemplateConfig($this->design_template, false);
        $templateConfig = wp_parse_args($templateConfig, $defaultConfig);
        $templateConfig['design_template'] = $this->design_template;
        $footerDefaults = [
            'disable_footer' => 'no',
            'custom_footer'  => 'no',
            'footer_content' => '',
            'font_size'      => 13,
            'font_color'     => '#202020',
            'background_color' => 'transparent',
            'footer_padding' => 20
        ];

        $footerSettings = Arr::get($settings, 'footer_settings', []);
        $footerSettings = is_array($footerSettings) ? $footerSettings : [];

        // Backward compatibility: older imports may only carry disable_footer in template_config.
        if (!isset($footerSettings['disable_footer'])) {
            $legacyDisable = Arr::get($templateConfig, 'disable_footer');
            if ($legacyDisable === 'yes' || $legacyDisable === 'no') {
                $footerSettings['disable_footer'] = $legacyDisable;
            }
        }

        if (!isset($footerSettings['custom_footer'])) {
            $legacyFooterContent = Arr::get($footerSettings, 'footer_content', '');
            if (is_string($legacyFooterContent) && trim(wp_strip_all_tags($legacyFooterContent))) {
                $footerSettings['custom_footer'] = 'yes';
            }
        }

        $footerSettings = wp_parse_args($footerSettings, $footerDefaults);
        $footerSettings['disable_footer'] = ($footerSettings['disable_footer'] === 'yes') ? 'yes' : 'no';
        $footerSettings['custom_footer'] = ($footerSettings['custom_footer'] === 'yes') ? 'yes' : 'no';
        $settings['footer_settings'] = $footerSettings;
        $templateConfig['disable_footer'] = $footerSettings['disable_footer'];
        $settings['template_config'] = $templateConfig;

        $mailerDefaults = [
            'from_name'      => '',
            'from_email'     => '',
            'reply_to_name'  => '',
            'reply_to_email' => '',
            'is_custom'      => 'no'
        ];

        $mailerSettings = Arr::get($settings, 'mailer_settings', []);
        $mailerSettings = wp_parse_args($mailerSettings, $mailerDefaults);
        $settings['mailer_settings'] = $mailerSettings;

        return $settings;
    }

    public function getRecipientsCountAttribute($recipientsCount)
    {
        return (int)$recipientsCount;
    }

    public function getRenderedBodyAttribute()
    {
        return (new Template)->render($this->body);
    }

    // Now using a single subject, get the first one
    public function getSubjectAttribute()
    {
        if ($firstSubject = $this->subjects()->first()) {
            return $firstSubject->value;
        }
        return $this->email_subject;
    }

    public function syncSubjects($subjects)
    {
        $validSubjectIds = [];
        foreach ($subjects as $subject) {
            if (empty($subject['value']) || empty($subject['key'])) {
                continue;
            }
            if (empty($subject['id'])) {
                $data = Arr::only($subject, ['key', 'value']);
                $data['object_id'] = $this->id;
                $inserted = Subject::create($data);
                $validSubjectIds[] = $inserted->id;
            } else {
                $subjectItem = Subject::where('id', intval($subject['id']))
                    ->where('object_id', $this->id)
                    ->first();

                if ($subjectItem) {
                    $subjectItem->fill(Arr::only($subject, ['key', 'value']))->save();
                    $validSubjectIds[] = $subjectItem->id;
                }
            }
        }

        if ($validSubjectIds) {
            // remove old subjects
            Subject::whereNotIn('id', $validSubjectIds)
                ->where('object_id', $this->id)
                ->delete();
        } else {
            Subject::where('object_id', $this->id)
                ->delete();
        }

        return $this->subjects();
    }

    public function duplicateSubjects(Campaign $campaign)
    {

        $subjects = $campaign->subjects;
        if (!$subjects) {
            return;
        }

        $formattedSubjects = [];
        foreach ($subjects as $subject) {
            $formattedSubjects[] = [
                'key'   => $subject->key,
                'value' => $subject['value']
            ];
        }
        if ($formattedSubjects) {
            $this->syncSubjects($formattedSubjects);
        }
    }

    public function scopeOfType($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 0);
    }


    /**
     * @return \FluentCrm\Framework\Database\Orm\Relations\BelongsTo
     */
    public function template()
    {
        return $this->belongsTo(__NAMESPACE__ . '\Template', 'template_id', 'ID');
    }

    /**
     * One2Many: Campaign has many emails
     *
     * @return \FluentCrm\Framework\Database\Orm\Relations\hasMany
     */
    public function emails()
    {
        return $this->hasMany(
            __NAMESPACE__ . '\CampaignEmail', 'campaign_id', 'id'
        );
    }

    /**
     * TODO: emails should be filtered by status (draft, queue e.t.c.)
     * One2Many: Campaign has many emails
     * @return \FluentCrm\Framework\Database\Orm\Relations\hasMany
     */
    public function campaign_emails()
    {
        return $this->hasMany(
            __NAMESPACE__ . '\CampaignEmail', 'campaign_id', 'id'
        )->where('email_type', 'campaign');
    }

    /**
     * One2Many: Campaign has many subjects
     * @return \FluentCrm\Framework\Database\Orm\Relations\hasMany
     */
    public function subjects()
    {
        return $this->hasMany(__NAMESPACE__ . '\Subject', 'object_id', 'id');
    }

    /**
     * Add one or more subscribers to the campaign by list with filtering
     * @param $settings
     * @param bool $limit
     * @param int $offset
     * @return array
     */
    public function subscribeBySegment($settings, $limit = false, $offset = 0)
    {
        $model = $this->getSubscribersModel($settings);

        $totalCount = $model->count();

        if ($limit) {
            $model->limit($limit);
        }

        if ($offset) {
            $model->offset($offset);
        }

        $result = $this->subscribe($model, [], true);

        return [
            'result'           => ($result) ? $result : 0,
            'total_subscribed' => count($result),
            'total_items'      => $totalCount
        ];
    }

    public function getSubscribersModel($settings = false)
    {
        if (!$settings) {
            $settings = $this->settings;
        }

        $filterType = Arr::get($settings, 'sending_filter', 'list_tag');

        if ($filterType == 'list_tag') {
            $subscriberModel = $this->getSubscribeIdsByListModel($settings['subscribers'], 'subscribed');
            if ($excludeItems = Arr::get($settings, 'excludedSubscribers')) {
                $formattedExcludedItems = [];
                foreach ($excludeItems as $item) {
                    if (empty($item['list']) && empty($item['tag'])) {
                        continue;
                    }
                    $formattedExcludedItems[] = $item;
                }

                if ($formattedExcludedItems) {
                    $excludedModel = $this->getSubscribeIdsByListModel($formattedExcludedItems, 'subscribed');
                    $excludedModel->select('id');
                    $subscriberModel->whereNotIn('id', $excludedModel->getQuery());
                }
            }

            return $subscriberModel;
        }

        if ($filterType == 'dynamic_segment') {
            $segmentSettings = Arr::get($settings, 'dynamic_segment', []);
            $segmentSettings['offset'] = 0;
            $segmentSettings['limit'] = false;

            /**
             * Filter the dynamic segment details based on the segment slug.
             *
             * This filter allows you to modify the details of a dynamic segment.
             *
             * @param array  The details of the dynamic segment.
             * @param int $segmentSettings ['id']      The ID of the segment.
             * @param array {
             *     Additional context for the segment.
             *
             * @type bool  Whether to include the model in the context.
             * }
             *
             * @return array Modified segment details.
             * @since 2.5.93
             *
             */
            $segmentDetails = apply_filters('fluentcrm_dynamic_segment_' . $segmentSettings['slug'], [], $segmentSettings['id'], [
                'model' => true
            ]);

            if (!empty($segmentDetails['model'])) {
                $model = $segmentDetails['model'];
                $model->where('status', 'subscribed');
                return $model;
            }

            return null;
        }

        if ($filterType == 'advanced_filters') {
            $query = new ContactsQuery([
                'with'               => [],
                'filter_type'        => 'advanced',
                'contact_status'     => 'subscribed',
                'filters_groups_raw' => $settings['advanced_filters']
            ]);

            return $query->getModel();
        }

        return null;
    }

    public function getSubscriberIdsBySegmentSettings($settings, $limit = false, $offset = 0)
    {
        $model = $this->getSubscribersModel($settings);

        if (!$model) {
            return [
                'subscriber_ids' => [],
                'total_count'    => 0
            ];
        }

        $totalCount = $model->count();

        if ($limit) {
            $model->limit($limit);
        }

        if ($offset) {
            $model->offset($offset);
        }

        return [
            'subscriber_ids' => $model->get()->pluck('id')->toArray(),
            'total_count'    => $totalCount
        ];
    }

    public function getSubscriberIdsCountBySegmentSettings($settings, $status = 'subscribed')
    {
        $model = $this->getSubscribersModel($settings);
        if ($model) {
            return $model->count();
        }

        return 0;
    }


    /**
     * @param $query
     * @param $ids
     * @param $table
     * @param $objectType
     * @return mixed
     */
    private function getSubQueryForLisTorTagFilter($query, $ids, $table, $objectType)
    {
        $prefix = 'fc_';

        return $query->from($prefix . $table)
            ->join(
                $prefix . 'subscriber_pivot',
                $prefix . 'subscriber_pivot.object_id',
                '=',
                $prefix . $table . '.id'
            )
            ->where($prefix . 'subscriber_pivot.object_type', $objectType)
            ->whereIn($prefix . $table . '.id', $ids)
            ->groupBy($prefix . 'subscriber_pivot.subscriber_id')
            ->select($prefix . 'subscriber_pivot.subscriber_id');
    }

    /**
     * Get subscribers ids to by list with tag filtering
     * @param array $items
     * @param string $status contact status
     * @param int|boolean $limit limit
     * @param int $offset contact offset
     * @return array
     */
    public function getSubscribeIdsByList($items, $status = 'subscribed', $limit = false, $offset = 0)
    {
        $model = $this->getSubscribeIdsByListModel($items, $status, $limit, $offset);
        $results = $model->get();
        $ids = [];

        foreach ($results as $result) {
            $ids[] = $result->id;
        }

        return $ids;
    }

    /**
     * Get subscribers count to by list with tag filtering
     * @param array $items
     * @param string $status contact status
     * @param int|boolean $limit limit
     * @param int $offset contact offset
     * @return int
     */
    public function getSubscribeIdsByListCount($items, $status = 'subscribed', $limit = false, $offset = 0)
    {
        $model = $this->getSubscribeIdsByListModel($items, $status, $limit, $offset);
        return $model->count();
    }

    public function getSubscribeIdsByListModel($items, $status = 'subscribed', $limit = false, $offset = 0)
    {

        $query = Subscriber::where('status', $status);

        $queryGroups = [];

        $willSkip = false;

        $hasListFilter = false;
        $tagIds = [];
        foreach ($items as $item) {
            $listId = Arr::get($item, 'list');
            $tagId = Arr::get($item, 'tag');

            // The UI submits 'all' for the unused side, but REST/MCP/partial payloads can
            // send ''/null. Interpret a single empty side as 'all' (the evident intent);
            // a row with BOTH sides empty carries no information and is skipped.
            if (!$listId && !$tagId) {
                continue;
            }
            if (!$listId) {
                $listId = 'all';
            }
            if (!$tagId) {
                $tagId = 'all';
            }

            if ($listId == 'all' && $tagId == 'all') {
                $willSkip = true;
            } else if ($listId == 'all') {
                $queryGroups[] = ['tag_id' => $tagId];
                $tagIds[] = $tagId;
            } else if ($tagId == 'all') {
                $hasListFilter = true;
                $queryGroups[] = ['list_id' => $listId];
            } else {
                $hasListFilter = true;
                $tagIds[] = $tagId;
                $queryGroups[] = [
                    'list_id' => $listId,
                    'tag_id'  => $tagId
                ];
            }
        }

        if (!$willSkip && !$hasListFilter && !$queryGroups && !$tagIds) {
            // Fail closed: no valid targeting row at all (empty or fully-invalid payload).
            // Degrading to the unconstrained base query would target every subscriber on
            // the include path — or exclude every subscriber on the exclude path.
            $query->whereRaw('1 = 0');
        } else if (!$willSkip && !$hasListFilter && $tagIds) {
            $query->filterByTags($tagIds);
        } else if (!$willSkip && $queryGroups) {
            $query->where(function ($innerQuery) use ($queryGroups) {
                $type = 'where';
                foreach ($queryGroups as $queryGroup) {
                    $innerQuery->{$type}(function ($q) use ($queryGroup, $innerQuery) {
                        foreach ($queryGroup as $type => $id) {
                            if ($type == 'tag_id') {
                                $q->whereIn('id', function ($query) use ($id) {
                                    return $this->getSubQueryForLisTorTagFilter($query, [$id], 'tags', 'FluentCrm\App\Models\Tag');
                                });
                            } else if ($type == 'list_id') {
                                $q->whereIn('id', function ($query) use ($id) {
                                    return $this->getSubQueryForLisTorTagFilter($query, [$id], 'lists', 'FluentCrm\App\Models\Lists');
                                });
                            }
                        }
                    });

                    $type = 'orWhere';
                }
            });
        }

        if ($limit) {
            $query->limit($limit)->offset($offset);
        }

        return $query;

    }

    /**
     * Add one or more subscribers to the campaign
     * @param array $subscriberIds
     * @param array $emailArgs extra campaign_email args
     * @param bool $isModel if the $subscriberIds is collection or not
     * @return array
     */
    public function subscribe($subscriberIds, $emailArgs = [], $isModel = false)
    {
        $mailHeaders = Helper::getMailHeadersFromSettings(Arr::get($this->settings, 'mailer_settings', []));

        if ($isModel) {
            $subscribers = $subscriberIds;
        } else {
            $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();
        }

        $validStatuses = ['subscribed', 'transactional'];

        // Rows are collected and written with ONE multi-row INSERT per chunk
        // instead of a model create() per row — materialization of large
        // campaigns was insert-bound. The hash map lets us recover the new ids
        // afterward (email_hash is generated here and indexed).
        $rows = [];
        $subscribersByHash = [];

        foreach ($subscribers as $subscriber) {
            if (!in_array($subscriber->status, $validStatuses)) {
                continue; // We don't want to send emails to non-subscribed members
            }

            $time = fluentCrmTimestamp();
            $email = [
                'campaign_id'   => $this->id,
                // Default new queue rows to 'pending' (claimable by the senders), NOT
                // the campaign's own status: a caller adding recipients while the
                // campaign is 'working' would otherwise create rows in a status the
                // senders never pick up — a silent never-send. Callers that need a
                // different row status (e.g. the materializer's 'scheduling') pass it
                // explicitly via $emailArgs.
                'status'        => 'pending',
                'subscriber_id' => $subscriber->id,
                'email_address' => $subscriber->email,
                'email_headers' => $mailHeaders,
                'email_hash'    => Helper::generateEmailHash(),
                // Multi-row INSERT needs uniform columns across rows, so the
                // A/B subject id is always present (null when no subject wins).
                'email_subject_id' => null,
                'created_at'    => $time,
                'updated_at'    => $time
            ];

            $subjectItem = $this->guessEmailSubject();
            $emailSubject = $this->email_subject;

            if ($subjectItem && !empty($subjectItem->value)) {
                $emailSubject = $subjectItem->value;
                $email['email_subject_id'] = $subjectItem->id;
            }

            /**
             * Filter the campaign email subject text.
             *
             * This filter allows you to modify the email subject text for a campaign.
             *
             * @param string $emailSubject The original email subject text.
             * @param object $subscriber The subscriber object.
             *
             * @return string The filtered email subject text.
             */
            $email['email_subject'] = apply_filters('fluent_crm/parse_campaign_email_text', $emailSubject, $subscriber);

            // When a campaign row exists, the queue row carries NO body copy:
            // send-time rendering (CampaignEmail::getEmailBody) and the admin
            // preview both read from $campaign->email_body, so the per-row
            // LONGTEXT copy was write-then-erase churn (~10GB for a 100KB email
            // to 100k contacts) that nothing ever read. Callers that need a
            // per-row body either have no campaign row (falsy id, keeps the
            // copy) or pass email_body via $emailArgs, which overrides below
            // (funnel/sequence/SMS models override subscribe() entirely).
            $email['email_body'] = $this->id ? '' : $this->email_body;

            if ($emailArgs) {
                $email = wp_parse_args($emailArgs, $email);
            }

            // The raw multi-row INSERT bypasses model mutators, so apply the
            // email_headers serialization (setEmailHeadersAttribute) here.
            if (isset($email['email_headers']) && !is_string($email['email_headers'])) {
                $email['email_headers'] = maybe_serialize($email['email_headers']);
            }

            $subscriber->campaign_id = $this->id;
            $subscribersByHash[$email['email_hash']] = $subscriber;

            $rows[] = $email;
        }

        if (!$rows) {
            return [];
        }

        // One bulk INSERT per chunk. Safe to bypass the ORM here: CampaignEmail
        // registers no creating/created hooks, timestamps are set explicitly in
        // each row, and email_type falls back to the column default exactly as
        // create() did.
        CampaignEmail::insert($rows);

        // Recover the new ids with one indexed lookup on the hashes generated
        // above — preserves this method's contract of returning the inserted
        // ids and the in-memory $subscriber->email_id side effect.
        $updateIds = [];
        $insertedRows = CampaignEmail::whereIn('email_hash', array_keys($subscribersByHash))
            ->get(['id', 'email_hash']);

        foreach ($insertedRows as $insertedRow) {
            $updateIds[] = $insertedRow->id;
            if (isset($subscribersByHash[$insertedRow->email_hash])) {
                $subscribersByHash[$insertedRow->email_hash]->email_id = $insertedRow->id;
            }
        }

        // Increment by the rows actually inserted in this chunk instead of running a
        // growing-range COUNT(*) over fc_campaign_emails after every chunk (~5,000
        // full counts across a 100k materialization). The increment is DB-side
        // atomic: concurrent subscribe() callers on separate model instances
        // (funnel enrollments into a reference campaign, overlapping chunk
        // workers) would lose updates with a read-modify-write attribute save.
        // maybeDeleteDuplicates() additionally reconciles the total against the
        // queue when a materialization completes.
        if ($updateIds) {
            fluentCrmDb()->table('fc_campaigns')->where('id', $this->id)
                ->increment('recipients_count', count($updateIds));

            // Keep the in-memory model consistent without marking the column
            // dirty, so a later unrelated save() can't write a stale total back.
            $this->recipients_count = absint($this->recipients_count) + count($updateIds);
            $this->syncOriginalAttribute('recipients_count');
        }

        return $updateIds;
    }

    /**
     * Remove one or more subscribers from the campaign
     * @param array $subscriberIds
     * @return bool
     */
    public function unsubscribe($subscriberIds)
    {
        $result = $this->emails()->whereIn('subscriber_id', $subscriberIds)->delete();

        $this->recipients_count = $this->emails()->count();

        $this->save();

        return $result;
    }

    /**
     * Guess the subject by probability formula
     * @return Model Object or null
     */
    public function guessEmailSubject()
    {
        // Cache subjects per campaign to avoid repeated DB queries during batch processing.
        // The weighted random selection still runs per call for proper A/B distribution.
        static $subjectsCache = [];

        if (isset($subjectsCache[$this->id])) {
            $subjects = $subjectsCache[$this->id];
        } else {
            $subjects = $this->subjects()->get();
            $subjectsCache[$this->id] = $subjects;
        }

        if ($subjects->isEmpty()) {
            return null;
        }

        $priorities = $subjects->pluck('key')->toArray();
        $count = count($priorities);
        $total = array_sum($priorities);

        // All weights zero: fall back to a uniform pick.
        if ($total <= 0) {
            return $subjects[wp_rand(0, $count - 1)];
        }

        // wp_rand(1, total) with cumulative comparison: a 0-weight subject can never
        // be selected and each weight unit maps to exactly one outcome — the old
        // wp_rand(0, total) both allowed 0-weight picks and skewed by ±1.
        $num = wp_rand(1, $total);

        $i = $n = 0;
        while ($i < $count) {
            $n += $priorities[$i];
            if ($n >= $num) break;
            $i++;
        }

        return isset($subjects[$i]) ? $subjects[$i] : null;
    }

    public function getParsedText($text, $subscriber)
    {
        return Parser::parse($text, $subscriber);
    }

    public function filterDuplicateSubscribers($subscriberIds, $subscribers)
    {
        $existingIds = CampaignEmail::where('campaign_id', $this->id)
            ->whereIn('subscriber_id', $subscribers->pluck('id')->toArray())
            ->get()->pluck('subscriber_id')->toArray();

        return $subscribers->filter(function ($subscriber) use ($existingIds) {
            return !in_array($subscriber->id, $existingIds);
        });
    }

    public function archive()
    {
        $this->status = 0;
        $this->save();
        return $this;
    }

    public function getUtmParams()
    {
        if ($this->utm_status) {
            return array_filter([
                'utm_source'   => $this->utm_source,
                'utm_medium'   => $this->utm_medium,
                'utm_campaign' => $this->utm_campaign,
                'utm_term'     => $this->utm_term,
                'utm_content'  => $this->utm_content
            ]);
        }

        return [];
    }

    public function stats()
    {
        $totalEmails = CampaignEmail::where('campaign_id', $this->id)
            ->count();

        $totalSent = CampaignEmail::where('campaign_id', $this->id)
            ->where('status', 'sent')
            ->count();

        if ($this->getOpenTrackingStatus(false) === 'anonymous') {
            $views = fluentcrm_get_campaign_meta($this->id, '_ano_open_count', true);
            if (!$views) {
                $views = 0;
            }
        } else {
            $views = CampaignEmail::where('campaign_id', $this->id)
                ->where('is_open', 1)
                ->count();
        }

        if ($this->getClickTrackingStatus(false) === 'anonymous') {
            $clickItems = fluentcrm_get_campaign_meta($this->id, '_ano_url_clicks', true);
            $clicks = 0;
            if ($clickItems && is_array($clickItems)) {
                $clicks = array_sum($clickItems);
            }
        } else {
            $clicks = CampaignEmail::where('campaign_id', $this->id)
                ->whereNotNull('click_counter')
                ->count();
        }

        $unSubscribed = CampaignUrlMetric::where('campaign_id', $this->id)
            ->where('type', 'unsubscribe')
            ->distinct()
            ->count('subscriber_id');

        $revenue = fluentcrm_get_campaign_meta($this->id, '_campaign_revenue');

        $stats = [
            'total'         => $totalEmails,
            'sent'          => $totalSent,
            'clicks'        => $clicks,
            'views'         => $views,
            'unsubscribers' => $unSubscribed
        ];

        if ($revenue && $revenue->value) {
            $data = (array)$revenue->value;
            foreach ($data as $currency => $cents) {
                if ($cents && $currency !== 'orderIds') {
                    $stats['revenue'] = [
                        'label'    => __('Revenue', 'fluent-crm') . ' (' . $currency . ')',
                        'total'    => number_format($cents / 100, 2),
                        'currency' => $currency
                    ];
                }
            }
        }

        return $stats;

    }

    public function getEmailCount()
    {
        return fluentCrmDb()->table('fc_campaign_emails')
            ->where('campaign_id', $this->id)
            ->count();
    }

    /**
     * Delete duplicate queue rows for this campaign — same subscriber, keep the
     * lowest id — regardless of row status. Safe to call at any point of
     * materialization; the pre-check short-circuits on one indexed probe when
     * there are no duplicates (the common case).
     */
    public function deleteDuplicateEmails()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fc_campaign_emails';

        // Quick check: do any duplicates exist? Most campaigns won't have any.
        // Exclude NULL subscriber_ids — SQL NULL != NULL so the self-join can't match them.
        $hasDuplicates = $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$table} WHERE campaign_id = %d AND subscriber_id IS NOT NULL GROUP BY subscriber_id HAVING COUNT(*) > 1 LIMIT 1",
            $this->id
        ));

        if ($hasDuplicates) {
            // Delete duplicates, keeping the row with the lowest id per subscriber.
            $wpdb->query($wpdb->prepare(
                "DELETE e1 FROM {$table} e1
                 INNER JOIN {$table} e2
                 ON e1.campaign_id = e2.campaign_id
                    AND e1.subscriber_id = e2.subscriber_id
                    AND e1.id > e2.id
                 WHERE e1.campaign_id = %d
                    AND e1.subscriber_id IS NOT NULL",
                $this->id
            ));
        }

        return $this;
    }

    public function maybeDeleteDuplicates()
    {
        $this->deleteDuplicateEmails();

        // Authoritative recount, deliberately NOT gated on duplicates: this is the
        // completion-time reconciliation that guarantees the persisted total
        // matches the queue even if incremental accounting drifted for any reason.
        $emailCount = $this->getEmailCount();
        if ($emailCount != $this->recipients_count) {
            // Targeted write: a whole-model save() would also persist any other
            // dirty attribute the caller happens to hold (e.g. status), racing
            // concurrent status writes like an admin un-schedule.
            $this->recipients_count = $emailCount;
            fluentCrmDb()->table('fc_campaigns')->where('id', $this->id)
                ->update(['recipients_count' => $emailCount]);
            $this->syncOriginalAttribute('recipients_count');
        }

        return $this;
    }

    public function getHash()
    {
        $hash = fluentcrm_get_campaign_meta($this->id, '_campaign_hash', true);

        if ($hash) {
            return $hash;
        }

        $hash = md5(wp_rand(100, 10000) . '_' . $this->id . '_' . $this->title . '_' . time() . '_' . wp_generate_uuid4());
        $hash = str_replace('e', 'd', $hash);
        fluentcrm_update_campaign_meta($this->id, '_campaign_hash', $hash);

        return $hash;
    }

    public function deleteCampaignData()
    {
        CampaignEmail::where('campaign_id', $this->id)->delete();
        CampaignUrlMetric::where('campaign_id', $this->id)->delete();

        Meta::where('object_id', $this->id)
            ->where('object_type', 'FluentCrm\App\Models\Campaign')
            ->delete();

        return $this;
    }

    public function rangedScheduleDates()
    {
        $settings = $this->settings;

        if (Arr::get($settings, 'sending_type') != 'range_schedule') {
            return null;
        }


        $ranges = Arr::get($settings, 'schedule_range', ['', '']);

        if (!$ranges) {
            return null;
        }

        return [
            'start' => gmdate('Y-m-d H:i:s', $ranges[0]),
            'end'   => gmdate('Y-m-d H:i:s', $ranges[1])
        ];
    }

    public function getEmailScheduleAt()
    {
        $settings = $this->settings;

        if (Arr::get($settings, 'sending_type') != 'range_schedule') {
            return $this->scheduled_at;
        }

        // this is a range selector
        $ranges = Arr::get($settings, 'schedule_range', [$this->scheduled_at, $this->scheduled_at]);

        $timeStamp = random_int($ranges[0], $ranges[1]);

        if ($timeStamp < current_time('timestamp')) {
            $timeStamp = current_time('timestamp') + 60;
        }

        return gmdate('Y-m-d H:i:s', $timeStamp);
    }

    public function getShareableUrl()
    {
        $shareId = fluentcrm_get_campaign_meta($this->id, '_campaign_share_id', true);
        if (!$shareId) {
            $shareId = md5($this->getHash() . '_' . $this->id . '_' . time());
            fluentcrm_update_campaign_meta($this->id, '_campaign_share_id', $shareId);
        }

        return add_query_arg([
            FLUENTCRM_EXTERNAL_URL_PARAM => 1,
            'route'                      => 'email_preview',
            'fc_newsletter'              => $shareId
        ], site_url());
    }

    public function labelsTerm()
    {
        return $this->belongsToMany(Label::class, 'fc_term_relations', 'object_id', 'term_id')
            ->wherePivot('object_type', __CLASS__);
    }

    public function labels()
    {
        $labelIds = TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->pluck('term_id')
            ->toArray();
        return Label::whereIn('id', $labelIds)->get();
    }

    public function getFormattedLabels()
    {
        $labels = $this->labels();
        return $labels->map(function ($label) {
            return [
                'id'    => $label->id,
                'slug'  => $label->slug,
                'title' => $label->title,
                'color' => $label->settings['color'] ?? ''
            ];
        });
    }

    public function attachLabels($labelIds)
    {
        $labelIds = is_array($labelIds) ? $labelIds : [$labelIds];

        if (empty($labelIds)) {
            return $this;
        }

        $existingLabelIds = TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->pluck('term_id')
            ->toArray();

        $newLabelIds = array_diff($labelIds, $existingLabelIds);

        if (!empty($newLabelIds)) {
            foreach ($newLabelIds as $labelId) {
                TermRelation::create([
                    'object_id'   => $this->id,
                    'object_type' => __CLASS__,
                    'term_id'     => $labelId
                ]);
            }
        }

        return $this;
    }

    public function detachLabels($labelIds)
    {
        $labelIds = is_array($labelIds) ? $labelIds : [$labelIds];

        if (empty($labelIds)) {
            return $this;
        }

        TermRelation::where('object_id', $this->id)
            ->where('object_type', __CLASS__)
            ->whereIn('term_id', $labelIds)
            ->delete();

        return $this;
    }

    public function getOpenTrackingStatus($globalFallback = true)
    {
        $settings = $this->settings;
        if (isset($settings['open_tracker'])) {
            $status = $settings['open_tracker'];
            return $status;
        }

        if ($globalFallback) {
            $status = fluentcrmTrackEmailOpen();
            return $status;
        }

        return null;
    }

    public function getClickTrackingStatus($globalFallback = true)
    {
        $settings = $this->settings;
        if (isset($settings['click_tracker'])) {
            $status = $settings['click_tracker'];
            return $status;
        }

        if ($globalFallback) {
            $status = fluentcrmTrackClicking();
            return $status;
        }

        return null;
    }

}
