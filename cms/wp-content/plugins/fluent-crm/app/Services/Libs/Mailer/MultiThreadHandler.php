<?php

namespace FluentCrm\App\Services\Libs\Mailer;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Support\Collection;

class MultiThreadHandler extends BaseHandler
{

    protected $runnerTitle = 'MultiThreadHandler::handle';

    protected $sendingPerChunk = 20;

    protected $maximumProcessingTime = 50;

    protected $optionKey = 'fluentcrm_is_sending_multi_emails';

    public function __construct()
    {
        /**
         * The mailer chunk size for the multi-thread email handler.
         *
         * @param int $sendingPerChunk Number of campaign emails pulled per batch. Default is 20.
         * @return int
         */
        $sendingPerChunk = (int)apply_filters('fluent_crm/mailer_multi_thread_chunk_size', $this->sendingPerChunk);
        if ($sendingPerChunk > 0) {
            $this->sendingPerChunk = $sendingPerChunk;
        }

        /**
         * The maximum processing window (seconds) for the multi-thread email handler.
         *
         * @param int $maximumProcessingTime Max loop runtime in seconds. Default is 50.
         * @return int
         */
        $maximumProcessingTime = (int)apply_filters('fluent_crm/mailer_multi_thread_max_processing_seconds', $this->maximumProcessingTime);
        if ($maximumProcessingTime > 0) {
            $this->maximumProcessingTime = $maximumProcessingTime;
        }
    }

    public function handle()
    {
        if (!$this->isSystemOk()) {
            return true; // Early return
        }

        Helper::maybeDisableEmojiOnEmail();

        try {
            $this->handleFailedLog();
            $result = $this->processBatchEmails();

            if (is_wp_error($result)) {
                $this->releaseLock();
                $this->logSentCount();
                return true;
            }

            if ($result === 'time_up') {
                $this->releaseLock();
                $this->callBackGround();
                $this->logSentCount();
                return true;
            }
        } catch (\Throwable $e) {
            // \Throwable, not \Exception: a TypeError from a third-party filter
            // in the render pipeline must still fall through to releaseLock()
            // below, or the sender lock sits orphaned for its ~80s TTL.
            Helper::debugLog('Exception at ' . $this->runnerTitle, $e->getMessage(), 'error');
        }

        $this->logSentCount();
        $this->releaseLock();
        return true;
    }

    private function isSystemOk()
    {
        $this->calledFrom = Arr::get($_REQUEST, 'action') == 'fluentcrm-post-multi-thread-send-now' ? 'ajax' : 'cron';

        // Cheap guards first — in-process re-entrancy and the hard kill-switch.
        if (
            did_action('fluent_crm/sending_multi_threading_email') ||
            apply_filters('fluent_crm/disable_email_processing', false)
        ) {
            return false;
        }

        if ($this->memoryExceeded()) {
            Helper::debugLog('Mailer Memory Exceeded at ' . $this->runnerTitle, 'Memory Limit: ' . fluentCrmGetMemoryLimit() . '<br />Current Usage: ' . memory_get_usage(true));
            return false;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit($this->maximumProcessingTime + 30);
        }

        $systemMaxProcessingTime = fluentCrmMaxRunTime();

        if ($this->maximumProcessingTime > $systemMaxProcessingTime) {
            $this->maximumProcessingTime = $systemMaxProcessingTime;
        }

        // Acquire the lock before the willMultiThreadEmail() COUNT(*). The
        // atomic lock is the authoritative guard against concurrent runners, so
        // a losing racer bails here after a single atomic op instead of paying
        // for the count query. (memoryExceeded above runs before this, so it
        // has no lock to release.)
        if (!$this->acquireLock()) {
            return false;
        }

        // The lock is now held, and handle() calls isSystemOk() OUTSIDE its
        // try/catch. Guard every path below so a thrown error (the
        // willMultiThreadEmail() count, the cancel scheduling, or the
        // _last_called write) releases the lock instead of orphaning it until
        // the ~80s TTL.
        try {
            if (!Helper::willMultiThreadEmail(300)) {
                as_schedule_single_action(time() + 1, 'fluent_crm_cancel_multi_thread_mailing', [], 'fluent-crm', true);
                $this->releaseLock();
                return false;
            }

            // Record the start of an actual (lock-winning) send cycle.
            // callBackGround() reads this to keep the loopback continuation alive.
            fluentcrm_update_option($this->optionKey . '_last_called', time());

            $this->isMultiThread = true;
            $this->startingTimeStamp = time();
        } catch (\Throwable $e) {
            $this->releaseLock();
            Helper::debugLog('isSystemOk post-lock failure at ' . $this->runnerTitle, $e->getMessage(), 'error');
            return false;
        }

        return true;
    }

    protected function getNextBatchEmails()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fc_campaign_emails';
        $currentTime = current_time('mysql');

        $wpdb->query('START TRANSACTION');

        // Claim ODD ids only, newest first. (The cron Handler claims everything
        // ASC; the CLI sender defaults to even ids DESC.) Modulo partitioning
        // replaces the old OFFSET-250 skip: OFFSET inside SELECT ... FOR UPDATE
        // locked every scanned row (~270 per claim), and two handlers scanning
        // the index in opposite directions toward each other was a
        // deadlock-shaped pattern near queue drain. Secondary partitions stay
        // disjoint as long as CLI workers partition within the even id space
        // (see the cli_send doc-block) — and the unpartitioned primary Handler
        // can still drain the entire queue alone if this worker dies, so no
        // rows can strand. One locking SELECT per status ('pending' first) —
        // see BaseHandler::lockClaimableIds() for why.
        $ids = $this->lockClaimableIds('pending', $currentTime, $this->sendingPerChunk, 'DESC', 2, 1);
        if (count($ids) < $this->sendingPerChunk) {
            $ids = array_merge($ids, $this->lockClaimableIds('scheduled', $currentTime, $this->sendingPerChunk - count($ids), 'DESC', 2, 1));
        }

        if ($ids) {
            $idsPlaceholder = implode(',', array_fill(0, count($ids), '%d'));
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status = 'processing', updated_at = %s WHERE id IN ($idsPlaceholder) AND status IN ('pending', 'scheduled')",
                array_merge([$currentTime], $ids)
            ));

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new Collection([]);
            }
        }

        $wpdb->query('COMMIT');

        if (!$ids) {
            return new Collection([]);
        }

        // Only return rows we actually claimed (status = processing)
        return CampaignEmail::whereIn('id', $ids)
            ->where('status', 'processing')
            ->with('campaign', 'subscriber')
            ->get();
    }

    protected function isTimeUp()
    {
        return (time() - $this->startingTimeStamp) >= $this->maximumProcessingTime;
    }

    private function callBackGround()
    {
        if ($this->memoryExceeded()) {
            Helper::debugLog('Memory Exceeded at MultiThreadHandler::callBackGround', 'Memory Limit: ' . fluentCrmGetMemoryLimit() . '<br />Current Usage: ' . memory_get_usage(true));
            return;
        }

        $nextCron = as_next_scheduled_action('fluent_crm_send_multi_thread_emails');
        $willRun = !$nextCron || $nextCron == 1 || ($nextCron - time()) >= 5 || ($nextCron - time()) < -70;


        if (!$willRun) {
            $lastCalled = (int)fluentcrm_get_option($this->optionKey . '_last_called');
            if ($lastCalled && (time() - $lastCalled) < 50) {
                $willRun = true;
            }
        }

        if ($willRun) {
            $url = add_query_arg([
                'action' => 'fluentcrm-post-multi-thread-send-now',
                'time'   => time()
            ], admin_url('admin-ajax.php'));

            Handler::fireNonBlockingRequest($url, [
                'campaign_id' => null,
                'retry'       => 1
            ]);
        }
    }
}
