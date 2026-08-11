<?php

namespace FluentCrm\App\Services;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignEmail;

class CampaignProcessor
{
    protected $campaignId = false;

    protected $initialStatus = 'scheduling';

    /**
     * Random per-acquire owner token stored inside the lock value so refresh
     * and release only ever touch a lock this run still owns (a stalled run
     * whose lock aged out must not stomp or delete its successor's claim).
     */
    private $lockToken = '';

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

        // The lock must cover everything up to and including the finalize:
        // the moment it frees, a cancel + re-schedule can start a NEW
        // materialization run for this campaign, and this run's flip/finalize
        // must never interleave with that one's inserts. The finally also
        // guarantees a thrown QueryException releases the lock immediately
        // instead of leaving the campaign stuck in 'processing' for the
        // lock's full TTL.
        try {
            return $this->materializeUnderLock($campaign, $perChunk, $runTime, $startTime);
        } finally {
            $this->releaseProcessingLock();
        }
    }

    /**
     * The materialization work that runs while the processing lock is held:
     * chunked row inserts, the guarded scheduling->scheduled flip, and the
     * campaign finalize/sweep. Extracted so processEmails() owns the lock
     * lifecycle via try/finally.
     *
     * @return Campaign|false
     */
    private function materializeUnderLock($campaign, $perChunk, $runTime, $startTime)
    {
        $subscribersModel = $this->getSubscribersChunk($campaign, $perChunk);
        if (!$subscribersModel) {
            $this->revertUnresolvableCampaign($campaign);
            return false;
        }

        $result = $this->subscribe($campaign, $subscribersModel);

        $willRun = !!$result;

        while ($willRun && ((microtime(true) - $startTime) < $runTime) && !fluentCrmIsMemoryExceeded()) {
            usleep(10000); // 10 milliseconds sleep
            $campaign = Campaign::withoutGlobalScope('type')->find($this->campaignId);

            // The campaign can be un-scheduled (status -> 'draft', rows deleted) or
            // even deleted while this run is mid-materialization. Stop inserting as
            // soon as we observe that, and sweep the 'scheduling' rows this run
            // created after the cancel's own delete — they must never be released
            // for sending. (Also prevents a fatal on a null re-fetch below.)
            if (!$campaign || $campaign->status != 'processing') {
                $this->sweepSchedulingRows();
                return $campaign;
            }

            $willRun = !!$result;

            if ($willRun) {
                if (!$this->refreshProcessingLock()) {
                    // We stalled past the lock TTL and a successor claimed the
                    // campaign — it owns materialization now. Stop inserting
                    // immediately: rows already created are consistent (the
                    // shared cursor advanced with them), the successor
                    // continues from that cursor, and the finally's guarded
                    // release will no-op against the successor's lock.
                    return false;
                }
                $subscribersModel = $this->getSubscribersChunk($campaign, $perChunk);
                if (!$subscribersModel) {
                    $this->revertUnresolvableCampaign($campaign);
                    return false;
                }

                $result = $this->subscribe($campaign, $subscribersModel);
            }
        }

        // One final status read decides how this run ends. unSchedule() writes
        // status='draft' BEFORE deleting the campaign's rows, so once we observe a
        // non-'processing' status here, anything still in 'scheduling' is residue
        // inserted after that delete — sweep it instead of releasing it. This also
        // covers the exit where the loop timed out in the same iteration it
        // inserted its last chunk (the mid-loop check never ran again).
        $campaign = Campaign::withoutGlobalScope('type')->find($this->campaignId);

        if (!$campaign || $campaign->status != 'processing') {
            $this->sweepSchedulingRows();
            return $campaign;
        }

        if (!$result) { // All rows materialized — release them for sending.
            global $wpdb;

            // Dedup BEFORE the flip, while every row is still 'scheduling' and
            // invisible to the senders: a TTL-steal overlap (a successor
            // re-inserting a stalled worker's in-flight chunk) can leave
            // duplicate rows, and removing them here means duplicates never
            // become claimable at all. maybeDeleteDuplicates() after the
            // finalize below re-checks as a second net for rows that commit in
            // the gap between this call and the flip; both short-circuit on
            // one indexed probe when the campaign is clean.
            $campaign->deleteDuplicateEmails();

            // Atomic guarded flip: the 'scheduling' -> 'scheduled' flip is the single
            // gate that makes rows sendable, so the campaign-status check is folded
            // into the UPDATE itself — a concurrent un-schedule cannot slip between
            // a separate check and the flip. Whichever side wins, nothing leaks: if
            // the flip commits first, the cancel's subsequent deletes remove the
            // flipped rows; if the cancel's status write commits first, the subquery
            // sees 'draft' and zero rows flip. Subquery instead of UPDATE...JOIN so
            // it also works on the WP SQLite plugin. Must run BEFORE the campaign is
            // finalized to 'scheduled' below, while the condition still holds.
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}fc_campaign_emails
                SET status = 'scheduled'
                WHERE campaign_id = %d AND status = 'scheduling'
                AND (SELECT status FROM {$wpdb->prefix}fc_campaigns WHERE id = %d) = 'processing'",
                $this->campaignId,
                $this->campaignId
            ));

            // Guarded finalize: matches 0 rows if a cancel landed after the re-fetch
            // above, so a 'draft' can never be overwritten back to 'scheduled'.
            $finalized = Campaign::withoutGlobalScope('type')
                ->where('id', $this->campaignId)
                ->where('status', 'processing')
                ->update(['status' => 'scheduled']);

            if ($finalized) {
                fluentcrm_update_campaign_meta($this->campaignId, '_last_recipient_id', 0);
                $campaign->status = 'scheduled'; // keep the returned model accurate
                // Mark it clean: the guarded finalize above already wrote this
                // status. Left dirty, any later save() on this model would
                // replay 'scheduled' over a concurrent un-schedule's 'draft'.
                $campaign->syncOriginalAttribute('status');
                $campaign->maybeDeleteDuplicates();
            } else {
                // Cancelled in the gap between the re-fetch and the finalize. The
                // cancel's own deletes clean up anything the flip touched; sweep any
                // remaining residue and re-fetch so the caller sees the real status.
                $this->sweepSchedulingRows();
                $campaign = Campaign::withoutGlobalScope('type')->find($this->campaignId);
            }
        }

        return $campaign;
    }

    /**
     * The campaign's recipient model could not be resolved — e.g. a dynamic segment
     * whose provider is no longer registered (Pro deactivated, segment deleted).
     * Leaving the campaign in `processing` would permanently occupy one of the two
     * scheduler discovery slots and eventually halt ALL sending, and the UI refuses
     * to unschedule past-due `processing` campaigns. Park it back in `draft` so the
     * admin can see, fix, and reschedule it.
     */
    private function revertUnresolvableCampaign($campaign)
    {
        if ($campaign->status != 'processing') {
            return;
        }

        $campaign->status = 'draft';
        $campaign->save();

        // Rows materialized before the revert would otherwise linger as
        // 'scheduling' on a draft campaign forever (never claimable, never
        // cleaned unless the campaign is re-scheduled).
        $this->sweepSchedulingRows();

        fluentcrm_update_campaign_meta(
            $campaign->id,
            '_processing_error',
            'Recipient selection could not be resolved (segment provider missing or deleted). The campaign was moved back to draft.'
        );

        do_action('fluent_crm/campaign_processing_failed', $campaign, 'recipients_unresolvable');
    }

    /**
     * Delete this campaign's leftover 'scheduling' rows after a mid-run cancel
     * or an unresolvable-recipients revert.
     *
     * 'scheduling' rows are invisible to the senders (they claim only
     * 'pending'/'scheduled' rows), so this never races the send loop — it only
     * removes residue that unSchedule()'s own deletes could not see because
     * the rows were inserted after they ran.
     */
    private function sweepSchedulingRows()
    {
        CampaignEmail::where('campaign_id', $this->campaignId)
            ->where('status', 'scheduling')
            ->delete();
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

        $this->advanceRecipientCursor($campaign->id, (int)$subscribers->max('id'));

        return $result;
    }

    /**
     * Advance the shared `_last_recipient_id` cursor — monotonically.
     *
     * A worker that stalled past the lock TTL mid-chunk can wake after a
     * successor has already materialized further; an unconditional write here
     * would roll the cursor BACKWARDS and make the successor re-materialize
     * everything after the rollback point. The conditional UPDATE only ever
     * moves the cursor forward (numeric coercion via `+ 0` works on MySQL and
     * the WP SQLite plugin alike). The finalize-time reset to 0 intentionally
     * bypasses this via the plain meta helper.
     */
    private function advanceRecipientCursor($campaignId, $lastId)
    {
        global $wpdb;

        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}fc_meta SET `value` = %s
            WHERE `object_id` = %d AND `object_type` = %s AND `key` = '_last_recipient_id' AND (`value` + 0) < %d",
            (string)$lastId, $campaignId, 'FluentCrm\App\Models\Campaign', $lastId
        ));

        if (!$affected && !fluentcrm_get_campaign_meta($campaignId, '_last_recipient_id')) {
            // First chunk of a fresh materialization — the row doesn't exist yet.
            // (The processing lock serializes normal runs, so create is safe here.)
            fluentcrm_update_campaign_meta($campaignId, '_last_recipient_id', $lastId);
        }
    }

    /**
     * Per-campaign materialization lock key in wp_options. In-family with the
     * scheduler's `_fluentcrm_lock_campaign_chain_<id>` chain lock.
     */
    private function processingLockKey()
    {
        return '_fluentcrm_lock_campaign_processing_' . (int)$this->campaignId;
    }

    /**
     * Atomically acquire the processing lock for this campaign.
     *
     * Lives in wp_options (Helper::acquireDbLock), NOT fc_meta: wp_options has
     * a UNIQUE key on option_name, so the INSERT IGNORE + conditional-UPDATE
     * claim is race-free even when the row does not exist yet. fc_meta has no
     * unique constraint on (object_type, object_id, key), so a first-acquire
     * there needs a check-then-create step in which two concurrent workers can
     * duplicate the row — or overwrite each other's fresh claim — and both
     * start materializing the same campaign (duplicate fc_campaign_emails
     * rows). Old `_processing_emails` fc_meta rows from earlier versions are
     * inert and get removed with the rest of the campaign's meta on delete.
     *
     * @return bool True if lock acquired, false if another process holds it.
     */
    private function acquireProcessingLock()
    {
        // 90s TTL: refreshed once per chunk inside the materialization loop,
        // so only a crashed or hung run ever ages out. The per-acquire token
        // makes refresh and release ownership-guarded (see the property doc).
        $this->lockToken = md5(uniqid((string)wp_rand(), true));

        return Helper::acquireDbLock($this->processingLockKey(), 90, $this->lockToken);
    }

    /**
     * Refresh the lock timestamp to prevent stale-lock detection.
     *
     * @return bool False when this run no longer owns the lock — a successor
     *              claimed it after we stalled past the TTL.
     */
    private function refreshProcessingLock()
    {
        return Helper::refreshDbLock($this->processingLockKey(), $this->lockToken);
    }

    /**
     * Release the processing lock. Deletes the row (rather than blanking it)
     * so per-campaign keys never accumulate in wp_options; the next acquire
     * re-creates it on demand. Token-guarded: deletes nothing if a successor
     * owns the lock now.
     */
    private function releaseProcessingLock()
    {
        Helper::deleteDbLock($this->processingLockKey(), $this->lockToken);
    }

    public function getSchedulingMethod()
    {
        return $this->initialStatus;
    }
}
