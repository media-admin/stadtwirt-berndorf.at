<?php

namespace FluentCrm\App\Services;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignEmail;

class CampaignProcessor
{
    protected $campaignId = false;

    protected $initialStatus = 'scheduling';

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;

        // Always create rows as 'scheduling' — they become 'scheduled' (sendable)
        // only after ALL rows are created. This ensures clean phase separation:
        // processing completes fully, then sending begins with accurate totals.
    }

    /*
     * By Default, this function will process emails in chunks of 30 (customizable) and run for max 30 seconds per processing cycle
     * @param int $perChunk
     * @param int $runTime
     * @return Campaign|false
     */
    public function processEmails($perChunk = 0, $runTime = 30)
    {
        if ($runTime > 30) {
            $runTime = fluentCrmMaxRunTime() - 5;
        }

        $startTime = microtime(true);
        $campaign = Campaign::withoutGlobalScope('type')->find($this->campaignId);

        if (!$campaign) {
            return false;
        }

        if ($campaign->status != 'processing') {
            return $campaign;
        }

        if (fluentCrmIsMemoryExceeded()) {
            return false;
        }

        if (!$perChunk || $perChunk <= 0) {
            /**
             * Filter the number of subscribers processed per request while processing Campaign Emails.
             *
             * This filter allows you to modify the number of subscribers that are processed
             * in each request when processing campaigns.
             *
             * @since 2.7.0
             *
             * @param int The number of subscribers to process per request. Default is 30.
             */
            $perChunk = (int)apply_filters('fluent_crm/process_subscribers_per_request', 30);
        }

        if (!$this->acquireProcessingLock()) {
            // Return false (not $campaign) so the caller doesn't fire
            // another AJAX chain while the lock is held by another process.
            return false;
        }

        $subscribersModel = $this->getSubscribersChunk($campaign, $perChunk);
        if (!$subscribersModel) {
            $this->releaseProcessingLock();
            return false;
        }

        $result = $this->subscribe($campaign, $subscribersModel);

        $willRun = !!$result;

        while ($willRun && ((microtime(true) - $startTime) < $runTime) && !fluentCrmIsMemoryExceeded()) {
            usleep(10000); // 10 milliseconds sleep
            $campaign = Campaign::withoutGlobalScope('type')->find($campaign->id);
            $willRun = !!$result;

            if ($willRun) {
                $this->refreshProcessingLock();
                $subscribersModel = $this->getSubscribersChunk($campaign, $perChunk);
                if (!$subscribersModel) {
                    $this->releaseProcessingLock();
                    return false;
                }

                $result = $this->subscribe($campaign, $subscribersModel);
            }
        }

        $this->releaseProcessingLock();

        if (!$result) { // All Done. Let's make it scheduled
            $campaign = Campaign::withoutGlobalScope('type')->find($this->campaignId);

            if ($campaign->status == 'processing') {
                $campaign->status = 'scheduled';
                $campaign->save();
                fluentcrm_update_campaign_meta($campaign->id, '_last_recipient_id', 0);
            }

            CampaignEmail::where('campaign_id', $campaign->id)
                ->where('status', 'scheduling')
                ->update([
                    'status' => 'scheduled'
                ]);

            $campaign->maybeDeleteDuplicates();
        }

        return $campaign;
    }

    /**
     * Get the next stable subscriber chunk for campaign email materialization.
     */
    private function getSubscribersChunk($campaign, $perChunk)
    {
        $subscribersModel = $campaign->getSubscribersModel($campaign->settings);
        if (!$subscribersModel) {
            return false;
        }

        $lastRecipientId = absint(fluentcrm_get_campaign_meta($campaign->id, '_last_recipient_id', true));

        return $subscribersModel
            ->where('fc_subscribers.id', '>', $lastRecipientId)
            ->reorder('fc_subscribers.id', 'ASC')
            ->limit($perChunk);
    }

    /**
     * Materialize a subscriber chunk and advance the cursor after rows are created.
     */
    private function subscribe($campaign, $subscribersModel)
    {
        $subscribers = $subscribersModel->get();
        if ($subscribers->isEmpty()) {
            return [];
        }

        $result = $campaign->subscribe($subscribers, [
            'status'       => $this->initialStatus,
            'scheduled_at' => $campaign->getEmailScheduleAt(),
        ], true);

        fluentcrm_update_campaign_meta($campaign->id, '_last_recipient_id', $subscribers->max('id'));

        return $result;
    }

    /**
     * Atomically acquire the processing lock for this campaign.
     *
     * Uses fc_meta table with the '_processing_emails' key. The lock row
     * is created on first acquire and reused. A single UPDATE with a WHERE
     * clause prevents TOCTOU race conditions.
     *
     * @return bool True if lock acquired, false if another process holds it.
     */
    private function acquireProcessingLock()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fc_meta';
        $now = (string)time();
        $lockTimeout = 90;
        $key = '_processing_emails';
        $objectType = 'FluentCrm\App\Models\Campaign';

        // Try atomic UPDATE on existing row: claim if free (0/empty) or expired
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET `value` = %s WHERE `object_id` = %d AND `object_type` = %s AND `key` = %s AND (`value` = '' OR `value` = '0' OR `value` < %d)",
            $now, $this->campaignId, $objectType, $key, time() - $lockTimeout
        ));

        if ($affected > 0) {
            return true;
        }

        // Row might not exist yet — create it then retry the atomic UPDATE
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE `object_id` = %d AND `object_type` = %s AND `key` = %s",
            $this->campaignId, $objectType, $key
        ));

        if (!$exists) {
            // Create the row with value '0' (unlocked), then retry atomic claim
            fluentcrm_update_campaign_meta($this->campaignId, $key, '0');

            $affected = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET `value` = %s WHERE `object_id` = %d AND `object_type` = %s AND `key` = %s AND (`value` = '' OR `value` = '0' OR `value` < %d)",
                $now, $this->campaignId, $objectType, $key, time() - $lockTimeout
            ));

            return $affected > 0;
        }

        // Row exists but lock is held by another process
        return false;
    }

    /**
     * Refresh the lock timestamp to prevent stale-lock detection.
     */
    private function refreshProcessingLock()
    {
        fluentcrm_update_campaign_meta($this->campaignId, '_processing_emails', (string)time());
    }

    /**
     * Release the processing lock.
     */
    private function releaseProcessingLock()
    {
        fluentcrm_update_campaign_meta($this->campaignId, '_processing_emails', 0);
    }

    public function getSchedulingMethod()
    {
        return $this->initialStatus;
    }
}
