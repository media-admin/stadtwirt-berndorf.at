<?php

namespace FluentCrm\App\Services\Funnel;

use FluentCrm\App\Hooks\Handlers\FunnelHandler;
use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

class FunnelHelper
{
    /**
     * Meta key holding an automation's sticky note.
     *
     * Deliberately stored as its own meta row rather than inside `Funnel::settings`,
     * because saveFunnelSequence() overwrites `settings` wholesale from the client's
     * posted copy — a note saved after page load would be erased by the next step save.
     */
    const STICKY_NOTE_META_KEY = 'sticky_note';

    /**
     * Normalize a sticky note payload for storage.
     *
     * The note is plain text by design: it renders through v-text, so no markup is
     * allowed in or out. Returns null when the note is effectively empty, which the
     * caller should treat as "delete the note".
     *
     * @param mixed $content Raw note body from the request.
     * @return array|null {content: string, updated_by: int, updated_at: string}
     */
    public static function sanitizeStickyNote($content)
    {
        $content = sanitize_textarea_field((string)$content);
        // Collapse whitespace-only submissions ("\n\n") into a real clear.
        if ($content === '' || trim($content) === '') {
            return null;
        }

        // Bound the length so a pasted document can't bloat every funnel list response.
        if (mb_strlen($content) > 2000) {
            $content = mb_substr($content, 0, 2000);
        }

        return [
            'content'    => $content,
            'updated_by' => get_current_user_id(),
            // WP local time, matching Funnel::$updated_at (Model::freshTimestamp) so the
            // note's "edited" time is comparable to the automation's own timestamps.
            'updated_at' => current_time('mysql')
        ];
    }

    /**
     * Read a funnel's sticky note in the shape the editor expects.
     *
     * Tolerates the legacy/simple case of a bare string having been stored, and always
     * returns a display-ready author name so the frontend needs no user lookup.
     *
     * @param Funnel $funnel
     * @return array|null
     */
    public static function getStickyNote($funnel)
    {
        $note = $funnel->getMeta(static::STICKY_NOTE_META_KEY, null);

        if (empty($note)) {
            return null;
        }

        if (is_string($note)) {
            $note = ['content' => $note];
        }

        if (!is_array($note) || empty($note['content'])) {
            return null;
        }

        $userId = (int)Arr::get($note, 'updated_by');
        $author = '';
        if ($userId) {
            $user = get_userdata($userId);
            $author = $user ? $user->display_name : '';
        }

        return [
            'content'      => (string)$note['content'],
            'updated_by'   => $userId,
            'author_name'  => $author,
            'updated_at'   => (string)Arr::get($note, 'updated_at', '')
        ];
    }

    public static function changeFunnelSubSequenceStatus($funnelSubId, $sequenceId, $status = 'complete')
    {
        return FunnelSubscriber::where('id', $funnelSubId)
            ->update([
                'last_sequence_status' => $status,
                'last_sequence_id'     => $sequenceId,
                'last_executed_time'   => current_time('mysql')
            ]);
    }

    public static function getUpdateOptions()
    {
        return [
            [
                'id'    => 'update',
                'title' => __('Update if Exist', 'fluent-crm')
            ],
            [
                'id'    => 'skip_all_if_exist',
                'title' => __('Skip this automation if contact already exist', 'fluent-crm')
            ]
        ];
    }

    public static function prepareUserData($user)
    {
        $subscriber = Helper::getWPMapUserInfo($user);
        $subscriber['source'] = 'web';
        return $subscriber;
    }

    public static function getSubscriber($emailOrUserId)
    {
        $column = 'email';
        if (is_int($emailOrUserId)) {
            $column = 'user_id';
        }

        return Subscriber::where($column, $emailOrUserId)->first();
    }

    public static function createOrUpdateContact($data)
    {
        return FluentCrmApi('contacts')->createOrUpdate($data);
    }

    public static function getUserRoles($keyed = false)
    {
        if (!function_exists('get_editable_roles')) {
            require_once(ABSPATH . '/wp-admin/includes/user.php');
        }

        $roles = \get_editable_roles();
        $formattedRoles = [];
        foreach ($roles as $roleKey => $role) {
            if ($keyed) {
                $formattedRoles[$roleKey] = $role['name'];
            } else {
                $formattedRoles[] = [
                    'id'    => $roleKey,
                    'title' => $role['name']
                ];
            }

        }
        return $formattedRoles;
    }

    public static function ifAlreadyInFunnel($funnelId, $subscriberId)
    {
        return FunnelSubscriber::where('funnel_id', $funnelId)
            ->where('subscriber_id', $subscriberId)
            ->first();
    }

    public static function maybeExplodeFullName($data)
    {
        if (empty($data['first_name']) && empty($data['last_name'])) {
            return $data;
        }

        if (empty($data['first_name']) || !empty($data['last_name'])) {
            return $data;
        }

        $fullNameArray = explode(' ', $data['first_name']);
        $data['first_name'] = array_shift($fullNameArray);
        if ($fullNameArray) {
            $data['last_name'] = implode(' ', $fullNameArray);
        }

        return $data;
    }

    public static function syncTags($subscriber, $tags = [])
    {
        if ($tags) {
            $subscriber->attachTags($tags);
        }
    }

    public static function syncLists($subscriber, $lists = [])
    {
        // Syncing
        if ($lists) {
            $subscriber->attachLists($lists);
        }
    }

    public static function getPrimaryContactFieldMaps()
    {
        return [
            'first_name' => [
                'type'  => 'value_options',
                'label' => __('First Name', 'fluent-crm')
            ],
            'last_name'  => [
                'type'  => 'value_options',
                'label' => __('Last Name', 'fluent-crm')
            ],
            'email'      => [
                'type'  => 'value_options',
                'label' => __('Email', 'fluent-crm')
            ]
        ];
    }

    public static function getSecondaryContactFieldMaps()
    {
        $mainFields = [
            'prefix'         => [
                'type'  => 'value_options',
                'label' => __('Name Prefix', 'fluent-crm')
            ],
            'address_line_1' => [
                'type'  => 'value_options',
                'label' => __('Address Line 1', 'fluent-crm')
            ],
            'address_line_2' => [
                'type'  => 'value_options',
                'label' => __('Address Line 2', 'fluent-crm')
            ],
            'postal_code'    => [
                'type'  => 'value_options',
                'label' => __('Postal Code', 'fluent-crm')
            ],
            'city'           => [
                'type'  => 'value_options',
                'label' => __('City', 'fluent-crm')
            ],
            'state'          => [
                'type'  => 'value_options',
                'label' => __('State', 'fluent-crm')
            ],
            'country'        => [
                'type'  => 'value_options',
                'label' => __('country', 'fluent-crm')
            ],
            'phone'          => [
                'type'  => 'value_options',
                'label' => __('Phone', 'fluent-crm')
            ]
        ];

        $customFields = fluentcrm_get_option('contact_custom_fields', []);
        if ($customFields) {
            foreach ($customFields as $item) {
                $mainFields['custom.' . $item['slug']] = [
                    'type'  => 'value_options',
                    'label' => $item['label']
                ];
            }
        }

        return $mainFields;

    }

    public static function removeSubscribersFromFunnel($funnelId, $subscriberIds)
    {
        FunnelSubscriber::where('funnel_id', $funnelId)
            ->whereIn('subscriber_id', $subscriberIds)
            ->delete();

        FunnelMetric::where('funnel_id', $funnelId)
            ->whereIn('subscriber_id', $subscriberIds)
            ->delete();

        return true;
    }

    public static function saveFunnelSequence($funnelId, $data)
    {
        /*
         * Step deletion below is diff-driven: every existing sequence not present in the
         * posted list is deleted — and for email steps that cascades into the funnel
         * campaign and its full send/open/click history. A request whose `sequences`
         * key is missing or malformed (stripped body, client bug, truncating proxy)
         * must therefore ABORT before any write, never be coerced to "empty list".
         * Only an explicit, valid `[]` is an intentional clear.
         */
        if (!array_key_exists('sequences', $data)) {
            throw new \Exception(esc_html__('The sequences payload is missing. Funnel steps were not saved.', 'fluent-crm'));
        }

        $sequences = $data['sequences'];
        if (!is_array($sequences)) {
            $sequences = \json_decode((string)$sequences, true);
        }

        if (!is_array($sequences)) {
            throw new \Exception(esc_html__('The sequences payload is malformed. Funnel steps were not saved.', 'fluent-crm'));
        }

        /*
         * The engine's branch handling supports exactly ONE level of conditionals.
         * The editor blocks nesting client-side, but imported/REST payloads can carry
         * it — and a persisted nested conditional silently skips the remainder of the
         * outer branch at run time. Reject it here instead.
         */
        foreach ($sequences as $sequenceItem) {
            if (Arr::get($sequenceItem, 'type') != 'conditional') {
                continue;
            }
            foreach ((array)Arr::get($sequenceItem, 'children', []) as $branchChildren) {
                foreach ((array)$branchChildren as $childSequence) {
                    if (is_array($childSequence) && Arr::get($childSequence, 'type') == 'conditional') {
                        throw new \Exception(esc_html__('Nested conditional blocks are not supported. Funnel steps were not saved.', 'fluent-crm'));
                    }
                }
            }
        }

        $funnelSettings = \json_decode(Arr::get($data, 'funnel_settings'), true);
        $funnelConditions = \json_decode(Arr::get($data, 'conditions', '[]'), true);

        $funnel = Funnel::findOrFail($funnelId);

        if (is_array($funnelSettings)) {
            $funnel->settings = $funnelSettings;
        }

        if ($funnelTitle = Arr::get($data, 'funnel_title')) {
            $funnel->title = sanitize_text_field($funnelTitle);
        }

        if (is_array($funnelConditions)) {
            $funnel->conditions = $funnelConditions;
        }
        // Only accept known statuses; a missing/arbitrary value must not silently
        // unpublish the automation (a NULL status freezes every in-flight subscriber).
        $requestedStatus = Arr::get($data, 'status');
        if (in_array($requestedStatus, ['draft', 'published'], true)) {
            $funnel->status = $requestedStatus;
        }
        // Always write updated_at so it reflects when sequences were last saved
        $funnel->updated_at = current_time('mysql');
        $funnel->save();

        if ($funnelDescription = Arr::get($data, 'funnel_description')) {
            $funnel->updateMeta('description', $funnelDescription);
        } else {
            $funnel->deleteMeta('description');
        }

        $sequenceIds = [];
        $cDelay = 0;
        $delay = 0;

        $indexCount = 0;

        foreach ($sequences as $index => $sequence) {
            // it's creatable
            $sequence['funnel_id'] = $funnel->id;
            $sequence['status'] = 'published';
            $sequence['conditions'] = [];
            $sequence['sequence'] = $indexCount + 1;
            $sequence['c_delay'] = $cDelay;
            $sequence['delay'] = $delay;
            $delay = 0;

            $actionName = $sequence['action_name'];

            if ($actionName == 'fluentcrm_wait_times') {
                $delay = self::getDelayInSecond($sequence['settings']);
                $cDelay += $delay;
            }

            $sequence = apply_filters('fluentcrm_funnel_sequence_saving_' . $sequence['action_name'], $sequence, $funnel);

            if (Arr::get($sequence, 'type') == 'benchmark') {
                $delay = $sequence['delay'];
            }

            $sequence['id'] = self::createOrUpdateSequence($sequence);
            $sequenceIds[] = $sequence['id'];

            // We have to handle the children if it's conditional block
            if ($sequence['type'] == 'conditional') {
                $childIds = self::saveChildSequences($sequence, $funnel);
                $indexCount += count($childIds);
                $sequenceIds = array_unique(array_merge($sequenceIds, $childIds));
            }

            $indexCount += 1;
        }

        if ($sequenceIds) {
            $deletingSequences = FunnelSequence::whereNotIn('id', $sequenceIds)
                ->where('funnel_id', $funnel->id)
                ->get();
        } else {
            $deletingSequences = FunnelSequence::where('funnel_id', $funnel->id)->get();
        }

        if ($deletingSequences->count()) {
            foreach ($deletingSequences as $deletingSequence) {
                do_action('fluentcrm_funnel_sequence_deleting_' . $deletingSequence->action_name, $deletingSequence, $funnel);
                $deletingSequence->delete();
            }

            // Unstick subscribers waiting on deleted benchmark sequences
            FunnelSubscriber::where('funnel_id', $funnel->id)
                ->where('status', 'waiting')
                ->whereNotIn('next_sequence_id', $sequenceIds)
                ->update([
                    'status'              => 'active',
                    'next_execution_time' => current_time('mysql')
                ]);

            // Clear dangling step pointers for in-flight subscribers (active rows
            // included): after the delete, a next_sequence_id referencing a removed
            // step would make the processor fall back to the stale next_sequence
            // ordinal — which, after renumbering, points at a DIFFERENT step. With
            // the pointer cleared, the engine recomputes the position from
            // last_sequence_id instead.
            FunnelSubscriber::where('funnel_id', $funnel->id)
                ->whereIn('status', ['active', 'waiting'])
                ->whereNotNull('next_sequence_id')
                ->whereNotIn('next_sequence_id', $sequenceIds)
                ->update([
                    'next_sequence_id' => null
                ]);

            // Any active row left with neither a pointer nor an execution time is
            // invisible to the cron query (whereNotNull next_execution_time) and
            // would be stuck forever — schedule it for the next tick.
            FunnelSubscriber::where('funnel_id', $funnel->id)
                ->where('status', 'active')
                ->whereNull('next_sequence_id')
                ->whereNull('next_execution_time')
                ->update([
                    'next_execution_time' => current_time('mysql')
                ]);
        }

        // Sync next_sequence integer after renumbering — active/waiting subscribers
        // may have stale values from before the re-save
        global $wpdb;
        $subscribersTable = $wpdb->prefix . 'fc_funnel_subscribers';
        $sequencesTable = $wpdb->prefix . 'fc_funnel_sequences';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$subscribersTable} fs
             JOIN {$sequencesTable} seq ON fs.next_sequence_id = seq.id
             SET fs.next_sequence = seq.sequence
             WHERE fs.funnel_id = %d
               AND fs.status IN ('active', 'waiting')
               AND (fs.next_sequence IS NULL OR fs.next_sequence != seq.sequence)",
            $funnel->id
        ));

        (new FunnelHandler())->resetFunnelIndexes();

        return $funnel;
    }

    private static function createOrUpdateSequence($sequence)
    {
        if (empty($sequence['id'])) {
            $sequence['created_by'] = get_current_user_id();
            $createdSequence = FunnelSequence::create($sequence);
            do_action('fluent_crm/sequence_created_' . $createdSequence->action_name, $createdSequence);
            return $createdSequence->id;
        }

        $sequence['updated_at'] = current_time('mysql');
        $sequence['settings'] = \maybe_serialize($sequence['settings']);
        $sequence['conditions'] = \maybe_serialize($sequence['conditions']);

        $sequenceId = $sequence['id'];
        $data = Arr::only($sequence, (new FunnelSequence())->getFillable());

        FunnelSequence::where('id', $sequenceId)->update($data);

        return $sequenceId;
    }

    public static function getDelayInSecond($settings)
    {
        $waitType = Arr::get($settings, 'wait_type');

        if (!$waitType && Arr::get($settings, 'is_timestamp_wait') == 'yes') {
            return 1;
        }

        if ($waitType == 'timestamp_wait' || $waitType == 'to_day') {
            return 1;
        }

        $unit = Arr::get($settings, 'wait_time_unit');
        $time = (int) Arr::get($settings, 'wait_time_amount');

        if ($unit == 'months') {
            // Months are not a fixed number of seconds, so anchor to the
            // current time to get a calendar-accurate offset (e.g. +2 months).
            $now = current_time('timestamp');
            $delay = strtotime('+' . $time . ' months', $now) - $now;
        } else {
            $converter = 86400; // default day
            if ($unit == 'hours') {
                $converter = 3600; // hour
            } else if ($unit == 'minutes') {
                $converter = 60;
            }
            $delay = $time * $converter;
        }

        if (!$delay || $delay < 1) {
            $delay = 1;
        }

        return $delay;
    }

    public static function getCurrentDelayInSeconds($settings, $sequence = null, $funnelSubId = null)
    {
        $waitType = Arr::get($settings, 'wait_type');

        /*
         * For Specific Date and Time
         */
        if ((!$waitType && Arr::get($settings, 'is_timestamp_wait') == 'yes') || $waitType == 'timestamp_wait') {
            $timeStamp = current_time('timestamp');
            $waitTimes = strtotime(Arr::get($settings, 'wait_date_time'), $timeStamp) - $timeStamp;
            if ($waitTimes < 1) {
                $waitTimes = 0;
            }
            return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', $waitTimes, $settings, $sequence, $funnelSubId);
        }

        if ($waitType && $waitType == 'to_day') {
            $nextDays = Arr::get($settings, 'to_day', []);
            $timeStampNow = current_time('timestamp');

            $nextDays = array_map(function ($dayName) {
                return substr($dayName, 0, 3);
            }, $nextDays);

            if (empty($nextDays)) { // if no day is selected
                $nextDays = [gmdate('D', $timeStampNow), gmdate('D', strtotime('+1 day', $timeStampNow))];
            }

            $nextTime = Arr::get($settings, 'to_day_time');
            if (!$nextTime) {
                $nextTime = gmdate('H:i', $timeStampNow);
            }

            $date = self::getEarliestDay($nextDays, $nextTime);

            $seconds = strtotime($date) - current_time('timestamp');
            $waitTimes = ($seconds < 1) ? 0 : $seconds;
            return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', $waitTimes, $settings, $sequence, $funnelSubId);
        }


        if ($waitType == 'by_custom_field') {
            if (!$funnelSubId) {
                return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', 60, $settings, $sequence, $funnelSubId);
            }

            $funnelSub = FunnelSubscriber::where('id', $funnelSubId)->first();

            if (!$funnelSub || !$funnelSub->subscriber) {
                return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', 60, $settings, $sequence, $funnelSubId);
            }

            $customFieldKey = Arr::get($settings, 'by_custom_field', '');

            if (!$customFieldKey) {
                return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', 60, $settings, $sequence, $funnelSubId);
            }

            $dateTime = null;

            if ($customFieldKey == '__date_of_birth__') {
                $dateTime = $funnelSub->subscriber->date_of_birth;
                if ($dateTime && !self::isValidYmd($dateTime)) {
                    $dateTime = null;
                }
                if ($dateTime) {
                    // Anchor to the SITE-LOCAL year and only roll forward once the whole
                    // birthday has passed: a contact entering this step ON their birthday
                    // (the common case) must target today, not next year.
                    $localNow = current_time('timestamp');
                    $localYear = (int)gmdate('Y', $localNow);
                    $monthDay = gmdate('m-d', strtotime($dateTime));

                    $dateTime = $localYear . '-' . $monthDay;

                    if (strtotime($dateTime . ' 23:59:59') < $localNow) {
                        $dateTime = ($localYear + 1) . '-' . $monthDay;
                    }
                }
            } else {
                $meta = $funnelSub->subscriber->custom_field_meta()->where('key', $customFieldKey)->first();
                if ($meta) {
                    $dateTime = $meta->value;
                }
            }

            if (!$dateTime) {
                return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', 60, $settings, $sequence, $funnelSubId);
            }

            $timeStamp = strtotime($dateTime);

            $waitTimes = $timeStamp - current_time('timestamp');

            if ($waitTimes < 1) {
                $waitTimes = 60;
            }

            return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', $waitTimes, $settings, $sequence, $funnelSubId);
        }

        $unit = Arr::get($settings, 'wait_time_unit');
        $time = (int) Arr::get($settings, 'wait_time_amount');

        if ($unit == 'months') {
            // Months are not a fixed number of seconds, so anchor to the
            // current time to get a calendar-accurate offset (e.g. +2 months).
            $now = current_time('timestamp');
            $waitTimes = strtotime('+' . $time . ' months', $now) - $now;
        } else {
            $converter = 86400; // default day
            if ($unit == 'hours') {
                $converter = 3600; // hour
            } else if ($unit == 'minutes') {
                $converter = 60;
            }
            $waitTimes = $time * $converter;
        }

        if (!$waitTimes || $waitTimes < 1) {
            $waitTimes = 1;
        }

        return apply_filters('fluent_crm/funnel_seq_delay_in_seconds', $waitTimes, $settings, $sequence, $funnelSubId);
    }

    /*
     * Get the next earliest day provided to $days array as ['Mon', Wed, 'Fri']
     * @param array $days
     * @return string $earliest
     */
    private static function getEarliestDay($days, $time = '')
    {
        $timestamp = current_time('timestamp');
        $timeStampsArray = [];
        for ($i = 0; $i < 8; $i++) {
            $timeStampsArray[] = $timestamp + ($i * 86400);
        }

        $earliest = gmdate('Y-m-d ' . $time . ':s', $timestamp);

        foreach ($timeStampsArray as $timeStampVal) {
            if (in_array(gmdate('D', $timeStampVal), $days)) {
                $earliest = gmdate('Y-m-d ' . $time . ':s', $timeStampVal);
                if (strtotime($earliest) - $timestamp > -60) {
                    return $earliest;
                }
            }
        }

        return $earliest;
    }

    /**
     * Check if a string is a valid calendar date in Y-m-d format (avoids strtotime normalizing invalid dates).
     *
     * @param string $ymd Date string (e.g. 2024-02-31).
     * @return bool
     */
    private static function isValidYmd($ymd)
    {
        if (!is_string($ymd) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $parts)) {
            return false;
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private static function saveChildSequences($sequence, $funnel)
    {
        $sequenceIds = [];
        $indexCount = $sequence['sequence'];
        $childCats = Arr::get($sequence, 'children');
        foreach ($childCats as $category => $blocks) {
            $childDelay = 0;
            $childCDelay = 0;
            foreach ($blocks as $childIndex => $childSequence) {
                $childSequence['funnel_id'] = $funnel->id;
                $childSequence['status'] = 'published';
                $childSequence['parent_id'] = $sequence['id'];
                $childSequence['condition_type'] = $category;
                $childSequence['conditions'] = [];
                $childSequence['sequence'] = $indexCount + 1;
                $childSequence['c_delay'] = $sequence['c_delay'] + $childCDelay;
                $childSequence['delay'] = $sequence['delay'] + $childDelay;

                $childDelay = 0;

                /*
                 * For Delay Calculation
                 */
                $actionName = $childSequence['action_name'];
                if ($actionName == 'fluentcrm_wait_times') {
                    $childDelay = self::getDelayInSecond($childSequence['settings']);
                    $childCDelay += $childDelay;
                }

                $childSequence = apply_filters('fluentcrm_funnel_sequence_saving_' . $childSequence['action_name'], $childSequence, $funnel);
                $sequenceIds[] = self::createOrUpdateSequence($childSequence);
                $indexCount += 1;
            }
        }
        return $sequenceIds;
    }

    public static function getFunnelSequences($funnel, $isFiltered = false)
    {
        $sequences = FunnelSequence::where('funnel_id', $funnel->id)
            ->orderBy('sequence', 'ASC')
            ->get();

        if (!$isFiltered) {
            return $sequences;
        }

        $formattedSequences = [];
        foreach ($sequences as $sequence) {
            $sequenceArray = $sequence->toArray();
            $formattedSequences[] = apply_filters('fluentcrm_funnel_sequence_filtered_' . $sequence->action_name, $sequenceArray, $funnel);
        }

        return $formattedSequences;
    }

    public static function maybeMigrateConditions($funnelId)
    {
        $conditionSequences = FunnelSequence::where('funnel_id', $funnelId)
            ->where('type', 'conditional')
            ->where('action_name', '!=', 'funnel_condition')
            ->get();

        foreach ($conditionSequences as $conditionSequence) {
            self::migrateConditionSequence($conditionSequence);
        }

        return !$conditionSequences->isEmpty();
    }

    public static function migrateConditionSequence($sequence, $dryRun = false)
    {
        $conditionalBlocks = [
            'funnel_condition',
            'funnel_ab_testing'
        ];

        if ($sequence->type != 'conditional' || in_array($sequence->action_name, $conditionalBlocks)) {
            return $sequence;
        }

        $simpleMaps = [
            'fcrm_has_contact_tag'         => [
                'source'        => ['segment', 'tags'],
                'operator'      => 'in',
                'value_access'  => 'tags',
                'default_value' => [],
            ],
            'fcrm_has_contact_list'        => [
                'source'        => ['segment', 'lists'],
                'operator'      => 'in',
                'value_access'  => 'lists',
                'default_value' => [],
            ],
            'fcrm_has_user_role'           => [
                'source'        => ['segment', 'user_role'],
                'operator'      => 'in',
                'value_access'  => 'roles',
                'default_value' => [],
            ],
            'fcrm_woo_is_purchased'        => [
                'source'        => ['woo', 'purchased_items'],
                'operator'      => 'in',
                'value_access'  => 'product_ids',
                'default_value' => [],
            ],
            'fcrm_wishlist_is_in_level'    => [
                'source'        => ['wishlist', 'in_membership'],
                'operator'      => 'in',
                'value_access'  => 'level_ids',
                'default_value' => [],
            ],
            'fcrm_tutor_is_in_course'      => [
                'source'        => ['tutorlms', 'is_in_course'],
                'operator'      => 'in',
                'value_access'  => 'course_ids',
                'default_value' => [],
            ],
            'fcrm_pmpro_is_in_membership'  => [
                'source'        => ['pmpro', 'in_membership'],
                'operator'      => 'in',
                'value_access'  => 'level_ids',
                'default_value' => [],
            ],
            'fcrm_lifter_is_in_course'     => [
                'source'        => ['lifterlms', 'purchased_items'],
                'operator'      => 'in',
                'value_access'  => 'course_ids',
                'default_value' => [],
            ],
            'fcrm_lifter_is_in_membership' => [
                'source'        => ['lifterlms', 'purchased_groups'],
                'operator'      => 'in',
                'value_access'  => 'course_ids',
                'default_value' => [],
            ],
            'fcrm_learndhash_is_in_course' => [
                'source'        => ['learndash', 'purchased_items'],
                'operator'      => 'in',
                'value_access'  => 'course_ids',
                'default_value' => [],
            ],
            'fcrm_learndhash_is_in_group'  => [
                'source'        => ['learndash', 'purchased_groups'],
                'operator'      => 'in',
                'value_access'  => 'group_ids',
                'default_value' => [],
            ],
            'fcrm_edd_is_purchased'        => [
                'source'        => ['edd', 'purchased_items'],
                'operator'      => 'in',
                'value_access'  => 'product_ids',
                'default_value' => [],
            ],
            'fcrm_rcp_is_in_membership'    => [
                'source'        => ['rcp', 'in_membership'],
                'operator'      => 'in',
                'value_access'  => 'level_ids',
                'default_value' => [],
            ]
        ];

        $operatorMaps = [
            'match_all'     => 'in_all',
            'match_none_of' => 'not_in_all',
            'contains'      => 'contains',
            'doNotContains' => 'not_contains',
            'startsWith'    => 'startsWith',
            'endsWith'      => 'endsWith'
        ];

        $conditionName = $sequence->action_name;

        $oldSettings = $sequence->settings;

        if (isset($simpleMaps[$conditionName])) {
            $map = $simpleMaps[$conditionName];
            $sequence->action_name = 'funnel_condition';
            $sequence->settings = [
                'conditions' => [
                    [
                        [
                            'source'   => $map['source'],
                            'operator' => $map['operator'],
                            'value'    => Arr::get($sequence->settings, $map['value_access'], $map['default_value'])
                        ]
                    ]
                ]
            ];
            if (!$dryRun) {
                $sequence->save();
            }
        } else if ($conditionName == 'fcrm_check_user_prop') {
            $sequence->action_name = 'funnel_condition';
            $conditionGroups = $oldSettings['condition_groups'];

            $formattedConditions = [[]];

            if (isset($conditionGroups[0])) {
                $conditions = $conditionGroups[0]['conditions'];
                $conditionType = $conditionGroups[0]['match_type'];

                if ($conditionType != 'match_all') {
                    $formattedConditions = [];
                }

                foreach ($conditions as $condition) {
                    $dataKey = $condition['data_key'];
                    $operator = $condition['operator'];
                    $dataValue = $condition['data_value'];
                    if (!$dataKey || !$operator) {
                        continue;
                    }

                    if (isset($operatorMaps[$operator])) {
                        $operator = $operatorMaps[$operator];
                    }

                    if ($dataKey == 'contact_type' || $dataKey == 'country') {
                        if ($operator == '=') {
                            $operator = 'in';
                        } else {
                            $operator = 'not_in';
                        }
                        if ($dataKey == 'country') {
                            $dataValue = (array)$dataValue;
                        }

                    }

                    $provider = 'subscriber';

                    if (strpos($dataKey, 'custom.') === 0) {
                        $provider = 'custom_fields';
                        $dataKey = str_replace('custom.', '', $dataKey);
                    }

                    $item = [
                        'source'   => [
                            $provider,
                            $dataKey
                        ],
                        'operator' => $operator,
                        'value'    => $dataValue
                    ];

                    if ($conditionType == 'match_all') {
                        $formattedConditions[0][] = $item;
                    } else {
                        $formattedConditions[] = [$item];
                    }
                }
            }
            $sequence->settings = [
                'conditions' => $formattedConditions
            ];
            if (!$dryRun) {
                $sequence->save();
            }
        } else if ($conditionName == 'fcrm_woo_conditions') {
            $sequence->action_name = 'funnel_condition';
            $conditionGroups = $sequence->settings['conditional_groups'];

            $formattedConditions = [[]];

            if (isset($conditionGroups[0])) {
                $conditions = $conditionGroups[0]['conditions'];

                $keyMaps = [
                    'order_total_value'     => [
                        'woo_order',
                        'total_value'
                    ],
                    'order_product_ids'     => [
                        'woo_order',
                        'product_ids'
                    ],
                    'order_cat_purchased'   => [
                        'woo_order',
                        'cat_purchased'
                    ],
                    'order_billing_country' => [
                        'woo_order',
                        'billing_country'
                    ],
                    'order_shipping_method' => [
                        'woo_order',
                        'shipping_method'
                    ],
                    'order_payment_gateway' => [
                        'woo_order',
                        'payment_gateway'
                    ],

                    'customer_total_spend'        => [
                        'woo',
                        'total_order_value'
                    ],
                    'customer_order_count'        => [
                        'woo',
                        'total_spend'
                    ],
                    'customer_guest_user'         => [
                        'woo',
                        'guest_user'
                    ],
                    'customer_billing_country'    => [
                        'woo',
                        'billing_country'
                    ],
                    'customer_cat_purchased'      => [
                        'woo',
                        'purchased_categories'
                    ],
                    'customer_purchased_products' => [
                        'woo',
                        'purchased_items'
                    ],
                ];

                foreach ($conditions as $condition) {
                    $dataKey = $condition['data_key'];
                    $operator = $condition['operator'];
                    $dataValue = $condition['data_value'];

                    if (!$dataKey || !$operator || !isset($keyMaps[$dataKey])) {
                        continue;
                    }

                    if (isset($operatorMaps[$operator])) {
                        $operator = $operatorMaps[$operator];
                    }

                    if ($dataKey == 'order_billing_country' || $dataKey == 'customer_billing_country') {
                        if ($operator != '=') {
                            $operator = 'not_in';
                        } else {
                            $operator = 'in';
                        }

                        $dataValue = (array)$dataValue;
                    }

                    $item = [
                        'source'   => $keyMaps[$dataKey],
                        'operator' => $operator,
                        'value'    => $dataValue
                    ];
                    $formattedConditions[0][] = $item;
                }
            }

            $sequence->settings = [
                'conditions' => $formattedConditions
            ];

            if (!$dryRun) {
                $sequence->save();
            }
        }

        return $sequence;
    }


    public static function createWpUserFromSubscriber($subscriber, $sendWelcomeEmail = false, $password = '', $role = '', $metaData = [])
    {
        if ($userId = $subscriber->getWpUserId()) {
            return $userId;
        }

        if (!$password) {
            $password = wp_generate_password(8);
        }

        $userId = wp_create_user(sanitize_user($subscriber->email), $password, $subscriber->email);
        if (is_wp_error($userId)) {
            return $userId;
        }

        if (!$role) {
            // get default user role of WordPress
            $role = get_option('default_role');
            if (!$role) {
                $role = 'subscriber';
            }
        }


        $user = new \WP_User($userId);
        $user->set_role($role);

        $metaData['first_name'] = $subscriber->first_name;
        $metaData['last_name'] = $subscriber->last_name;

        $userMetas = array_filter($metaData);

        foreach ($userMetas as $metaKey => $metaValue) {
            update_user_meta($userId, $metaKey, $metaValue);
        }

        if ($sendWelcomeEmail) {
            wp_send_new_user_notifications($userId, 'user');
        }

        $subscriber->user_id = $userId;
        $subscriber->save();
        return $userId;
    }


    public static function getCountryShortName($countryName)
    {
        if (!function_exists('getFluentFormCountryList')) {
            return null;
        }

        $countries = getFluentFormCountryList();
        if (isset($countries[strtoupper($countryName)])) {
            return $countryName;
        }

        $countries = array_flip($countries);
        if (isset($countries[$countryName])) {
            return $countries[$countryName];
        }
        return null;
    }

    public static function getFunnelSubscriberStatus($defaultStatus, $funnel, $subscriber)
    {
        if ($defaultStatus == 'active' || $defaultStatus == 'waiting') {
            return $defaultStatus;
        }

        $processableStatuses = ['subscribed', 'transactional'];
        if (in_array($subscriber->status, $processableStatuses, true)) {
            return 'active';
        }

        if (Arr::get($funnel->settings, '__force_run_actions') == 'yes') {
            return 'active';
        }

        return $defaultStatus;
    }
}
