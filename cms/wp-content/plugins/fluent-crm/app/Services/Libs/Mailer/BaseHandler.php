<?php


namespace FluentCrm\App\Services\Libs\Mailer;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

abstract class BaseHandler
{
    protected $runnerTitle = '';

    protected $sentCount = 0;

    protected $maximumProcessingTime = 50;

    protected $calledFrom = 'cron';

    protected $startingTimeStamp = null;

    protected $optionKey = 'fluentcrm_is_sending_emails';

    protected $isMultiThread = false;

    protected $sendingChunkNumber = 0;

    protected $lastLockRefreshAt = 0;

    abstract protected function isTimeUp();

    protected function sendEmails($campaignEmails)
    {
        global $wpdb;
        do_action('fluent_crm/sending_emails_starting', $campaignEmails);

        if (defined('FLUENTMAIL')) {
            add_filter('fluentmail_will_log_email', 'fluentcrm_maybe_disable_fsmtp_log', 10, 2);
        }

        $failedIds = [];

        $this->sendingChunkNumber++;

        $sendableStatuses = ['subscribed', 'transactional'];
        $table = $wpdb->prefix . 'fc_campaign_emails';

        foreach ($campaignEmails as $email) {
            // Stop starting new emails once the runtime budget is spent. The
            // rate-limit wait below counts against wall-clock, so check every
            // iteration; any rows we already claimed but don't reach stay
            // 'processing' and are recovered by the stale-row reset.
            if ($this->isTimeUp()) {
                break;
            }

            // Check again if the contact is in subscribed status or not
            // If not then we will cancel the email
            if ($email->subscriber && !in_array($email->subscriber->status, $sendableStatuses, true)) {
                $email->status = 'cancelled';
                $email->save();
                continue;
            }

            $emailData = $email->data();

            // for the same id
            if (Helper::wasProcessedByKeyId('mail_' . $email->id . '_' . $email->email_address)) {
                continue;
            }

            // Wait for this email's global rate-limit slot BEFORE marking it
            // sent, so a crash/timeout during the wait leaves the row in
            // 'processing' (recoverable by the stale-row reset) instead of
            // 'sent'-but-never-delivered. Heartbeat the processing lock first
            // (time-gated, so it costs an in-memory check not a write per email)
            // so a long backpressure sleep can't let the lock expire mid-batch
            // and admit a second concurrent sender.
            $this->maybeRefreshLock();
            GlobalRateLimiter::throttle($emailData);

            // Mark as 'sent' and clear email_body BEFORE sending.
            // This prevents duplicates on crash — if the process dies after this
            // point, the email won't be re-queued. Missing one email is acceptable,
            // sending duplicates is not.
            //
            // The WHERE pins both status='processing' AND the original claim's
            // updated_at. If the rate-limit wait above ran long enough that the
            // stale-row reset reclaimed this row and another sender re-claimed it
            // (even one that is mid-send right now, with status back at
            // 'processing'), that re-claim rewrote updated_at — so our UPDATE
            // matches 0 rows and we skip. This closes the duplicate-send window
            // independently of the lock TTL, so it holds even for slow SMTP
            // transports where a single wp_mail() can hang past the lock. (Same
            // UPDATE, one extra WHERE column — no added query.)
            //
            // Use the RAW updated_at string, not $email->updated_at: the model
            // casts that column to a DateTime, and $wpdb binds a DateTime object
            // as an empty string (it does not call __toString), which would make
            // the WHERE `updated_at = ''`, match 0 rows, and strand EVERY email in
            // 'processing' forever. The raw attribute is the exact stored string.
            $claimToken = Arr::get($email->getAttributes(), 'updated_at');
            $claimed = $wpdb->update($table, [
                'status'       => 'sent',
                'scheduled_at' => current_time('mysql'),
                'email_body'   => '',
                'is_parsed'    => 1,
            ], ['id' => $email->id, 'status' => 'processing', 'updated_at' => $claimToken]);

            if ($claimed === false) {
                Helper::debugLog('DB Error at ' . $this->runnerTitle, $wpdb->last_error, 'error');
                return new \WP_Error('db_error', $wpdb->last_error ?: 'mark-sent update failed');
            }

            if ($claimed === 0) {
                // Row was reclaimed (and likely already sent) by another process
                // during our wait. Do not send it again.
                continue;
            }

            $this->sentCount++;

            // Already throttled above (before mark-sent); skip the in-Mailer
            // reservation so this email isn't rate-limited twice.
            $response = Mailer::send($emailData, $email->subscriber, $email, true);

            // wp_mail() returns false on failure (not WP_Error) in most cases.
            // We must catch both to avoid marking undelivered emails as 'sent'.
            // Note: emails are marked 'sent' BEFORE wp_mail() by design to prevent
            // duplicate sends on crash. This is intentional — losing one email is
            // acceptable, sending duplicates is not.
            if (is_wp_error($response) || $response === false) {
                $failedIds[] = $email->id;
            }
        }

        $this->updateEmailsStatus($failedIds, 'failed');

        if (defined('FLUENTMAIL')) {
            remove_filter('fluentmail_will_log_email', 'fluentcrm_maybe_disable_fsmtp_log', 10);
        }

        do_action('fluentcrm_sending_emails_done', $campaignEmails);

        return true;
    }

    protected function processBatchEmails()
    {
        if ($this->isTimeUp()) {
            return 'time_up';
        }

        $emails = $this->getNextBatchEmails();

        if (!$emails || $emails->isEmpty()) {
            return 'empty';
        }

        $this->refreshLock();
        $result = $this->sendEmails($emails);

        if (is_wp_error($result)) {
            return $result;
        }

        usleep(10000); // 0.01 seconds sleep

        return $this->processBatchEmails();
    }

    abstract protected function getNextBatchEmails();

    protected function logSentCount()
    {
        if ($this->sentCount) {
            Helper::debugLog(sprintf($this->runnerTitle . ': Sent %d', $this->sentCount), sprintf('%d seconds via %s', time() - $this->startingTimeStamp, $this->calledFrom));
        }
    }

    /**
     * Memory exceeded
     *
     * Ensures the batch process never exceeds 90% of the maximum WordPress memory.
     *
     * Based on WP_Background_Process::memory_exceeded()
     *
     * @return bool
     */
    protected function memoryExceeded()
    {
        $memory_limit = fluentCrmGetMemoryLimit() * 0.70;
        $current_memory = memory_get_usage(true);

        $memory_exceeded = $current_memory >= $memory_limit;

        return apply_filters('fluentcrm_memory_exceeded', $memory_exceeded, $this);
    }

    protected function updateEmailsStatus($ids, $status)
    {
        if (!$ids) {
            return false;
        }

        global $wpdb;
        $whereIn = implode(',', array_fill(0, count($ids), '%d'));
        $query = "UPDATE {$wpdb->prefix}fc_campaign_emails SET status = %s WHERE id IN ($whereIn)";
        $wpdb->query($wpdb->prepare($query, array_merge([$status], $ids)));

        return true;
    }

    protected function handleFailedLog()
    {
        add_action('wp_mail_failed', function ($error) {
            $data = $error->get_error_data();
            $to = Arr::get($data, 'to');
            if ($to) {
                if (is_array($to)) {
                    $to = $to[0];
                }
            }

            if (!$to || !\is_string($to) || !is_email($to)) {
                return;
            }

            CampaignEmail::where('email_address', $to)
                ->limit(1)
                ->whereIn('status', ['processing', 'sent', 'failed'])
                ->orderBy('updated_at', 'DESC')
                ->update([
                    'status' => 'failed',
                    'note'   => $error->get_error_message()
                ]);
        });
    }


    /**
     * Atomically acquire the processing lock.
     *
     * Replaces the old isProcessing() + processing() two-step pattern
     * which had a TOCTOU race condition — two processes could both read
     * "not processing" and both start sending emails.
     *
     * @return bool True if the lock was acquired, false if another process holds it.
     */
    protected function acquireLock()
    {
        // Single atomic conditional UPDATE on wp_options (Helper::acquireDbLock)
        // on every environment. We no longer take a wp_cache_add() fast path when
        // an external object cache is active: that primitive is only atomic if the
        // drop-in implements it against the shared backend, and some do not —
        // notably LiteSpeed Object Cache, whose add() checks only the per-process
        // in-memory array and then writes unconditionally. Under it, concurrent
        // senders all acquired the lock and ran at once, overshooting the provider
        // rate limit. The DB row lock has no such gap. See Helper::acquireDbLock().
        $lockTimeout = $this->maximumProcessingTime + 30;

        return Helper::acquireDbLock($this->optionKey, $lockTimeout);
    }

    /**
     * Refresh the lock timestamp (heartbeat) to prevent stuck-lock detection.
     *
     * Writes to the same wp_options row acquireLock() claims, so the heartbeat
     * and the stale-detection read share one source of truth.
     */
    protected function refreshLock()
    {
        Helper::refreshDbLock($this->optionKey);
        $this->lastLockRefreshAt = microtime(true);
    }

    /**
     * Heartbeat the lock only when it's getting close to its TTL, instead of on
     * every email. The per-email send loop calls this so a long rate-limit wait
     * can't let the lock expire mid-batch — but in normal sending (sub-second
     * waits, batch done in seconds) it never actually writes: the guard is just
     * an in-memory timestamp compare. It fires ~once per (TTL/3) only when a
     * batch runs long under backpressure.
     */
    protected function maybeRefreshLock()
    {
        // Refresh at TTL/4 so the gap between heartbeats, plus one email's
        // max wait (GlobalRateLimiter caps a single wait at 15s) plus its
        // wp_mail() send, stays under the lock TTL (maximumProcessingTime + 30):
        // 20s gap + 15s wait + ~30s send = 65s < 80s. That keeps the lock alive
        // across a long backpressure sleep without writing on every email.
        $interval = max(8, (int)(($this->maximumProcessingTime + 30) / 4));

        if ((microtime(true) - $this->lastLockRefreshAt) >= $interval) {
            $this->refreshLock();
        }
    }

    /**
     * Release the processing lock so another process can acquire it.
     */
    protected function releaseLock()
    {
        Helper::releaseDbLock($this->optionKey);
    }
}
