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

    /**
     * The fc_campaign_emails id currently inside Mailer::send(). The send loop
     * is strictly sequential, so when wp_mail_failed fires there is exactly one
     * candidate row — this lets the failure hook update by primary key instead
     * of guessing by email address. Null whenever no CRM send is in flight, so
     * failures from OTHER plugins' emails in the same request are ignored.
     */
    protected $currentSendingEmailId = null;

    protected $currentSendingEmailAddress = null;

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

        $reachedCount = 0;

        foreach ($campaignEmails as $email) {
            // Stop starting new emails once the runtime budget is spent. The
            // rate-limit wait below counts against wall-clock, so check every
            // iteration. Rows this batch claimed but never reached are handed
            // straight back to 'pending' (claim-token guarded) — during a
            // healthy chained send the stale-row reset defers to the fresh
            // sender lock, so without this they would strand in 'processing'
            // until the whole queue drained.
            if ($this->isTimeUp()) {
                $this->releaseUnreachedClaims($campaignEmails, $reachedCount);
                break;
            }

            $reachedCount++;

            // Check again if the contact is in subscribed status or not
            // If not then we will cancel the email
            if ($email->subscriber && !in_array($email->subscriber->status, $sendableStatuses, true)) {
                $email->status = 'cancelled';
                // Campaign rows carry no body since the bulk-insert change, but
                // funnel/sequence rows store per-row parsed bodies — clear on
                // cancel so they don't hold LONGTEXT forever.
                $email->email_body = '';
                $email->save();
                continue;
            }

            // Duplicate guard BEFORE the body render — data() is the expensive
            // step (block parse, smartcodes, click-tracking rewrite); a skipped
            // email must not pay for it. Pure in-memory key check, no data deps.
            if (Helper::wasProcessedByKeyId('mail_' . $email->id . '_' . $email->email_address)) {
                continue;
            }

            $emailData = $email->data();

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
            // reservation so this email isn't rate-limited twice. The id is
            // pinned around the call (and cleared in finally, so a Throwable
            // from a mail-layer filter can't leave a stale id that would let a
            // later unrelated failure in this process mark this row failed).
            $this->currentSendingEmailId = $email->id;
            $this->currentSendingEmailAddress = $email->email_address;
            try {
                $response = Mailer::send($emailData, $email->subscriber, $email, true);
            } finally {
                $this->currentSendingEmailId = null;
                $this->currentSendingEmailAddress = null;
            }

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

    /**
     * Lock and return up to $limit due queue ids for ONE status value, in
     * scheduled_at order, optionally restricted to a (id % modulo) partition.
     *
     * Single status on purpose: with `status IN ('pending','scheduled')` the
     * (status, scheduled_at) index covers the WHERE but cannot deliver the
     * ORDER BY across the two status ranges, so MySQL filesorts EVERY due row
     * — and because the claim is a locking read, it locks the entire backlog
     * on each claim, serializing all senders and defeating the id partitions.
     * One status = one index range read in key order with early exit at
     * LIMIT, so the locked set stays around modulo × limit index entries no
     * matter how large the backlog is. Callers claim 'pending' first, then
     * top up from 'scheduled' — the SAME order everywhere, so two claimers
     * can never each hold one status range while waiting on the other.
     *
     * Must run inside the caller's open transaction, before the UPDATE that
     * flips the claimed rows to 'processing'.
     *
     * @param string $status    Single queue status: 'pending' or 'scheduled'.
     * @param string $until     Due cutoff (MySQL datetime, WP timezone).
     * @param int    $limit     Maximum ids to lock.
     * @param string $order     'ASC' (oldest first) or 'DESC' (newest first).
     * @param int    $modulo    Id-partition size; 1 = no partition clause.
     * @param int    $remainder Partition slot: rows where id % modulo = remainder.
     * @return array Locked ids.
     */
    protected function lockClaimableIds($status, $until, $limit, $order = 'ASC', $modulo = 1, $remainder = 0)
    {
        global $wpdb;

        if ($limit <= 0) {
            return [];
        }

        $table = $wpdb->prefix . 'fc_campaign_emails';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT id FROM {$table} WHERE status = %s AND scheduled_at <= %s";
        $params = [$status, $until];

        if ($modulo > 1) {
            $sql .= " AND (id %% %d) = %d";
            $params[] = $modulo;
            $params[] = $remainder;
        }

        $sql .= " ORDER BY scheduled_at {$order} LIMIT %d FOR UPDATE";
        $params[] = $limit;

        return wp_list_pluck($wpdb->get_results($wpdb->prepare($sql, $params)), 'id');
    }

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

    /**
     * Hand claimed-but-unreached rows back to the queue on a time-up break.
     *
     * Only rows the time budget cut off are reset — rows the loop reached and
     * deliberately skipped (cancelled contact, duplicate guard, lost claim)
     * keep their state. The UPDATE reuses the mark-sent claim-token guard
     * (status='processing' AND updated_at = <claim time>) so a row another
     * sender re-claimed in the meantime is never touched. Rows claimed in one
     * batch share a single claim timestamp, so this is one UPDATE in practice.
     *
     * @param \FluentCrm\Framework\Support\Collection|array $campaignEmails The full claimed batch.
     * @param int $reachedCount How many rows the send loop actually entered.
     */
    protected function releaseUnreachedClaims($campaignEmails, $reachedCount)
    {
        global $wpdb;

        $idsByToken = [];
        $index = 0;
        foreach ($campaignEmails as $email) {
            if ($index++ < $reachedCount) {
                continue;
            }
            // Raw attribute, not the model cast — see the mark-sent claim-token
            // note in sendEmails() for why.
            $token = Arr::get($email->getAttributes(), 'updated_at');
            if (!$token) {
                // No raw claim token (shouldn't happen for a claimed row) —
                // leave it to the stale-reset crash net instead of building a
                // WHERE updated_at = '' that silently matches nothing.
                Helper::debugLog('releaseUnreachedClaims', 'missing claim token for row ' . $email->id, 'extended');
                continue;
            }
            $idsByToken[(string)$token][] = (int)$email->id;
        }

        if (!$idsByToken) {
            return;
        }

        $table = $wpdb->prefix . 'fc_campaign_emails';

        foreach ($idsByToken as $token => $ids) {
            $idsPlaceholder = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status = 'pending' WHERE id IN ($idsPlaceholder) AND status = 'processing' AND updated_at = %s",
                array_merge($ids, [$token])
            ));
        }
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
            // Only handle failures from THIS handler's in-flight send. The old
            // address-match approach ran a full-table-scan UPDATE per failure
            // (email_address is deliberately unindexed on this multi-million-row
            // table) — degrading the sender to one scan per email during an SMTP
            // outage — and could mark the wrong row when a contact had emails in
            // flight from two campaigns, or when another plugin's email to the
            // same address failed in the same request.
            if (!$this->currentSendingEmailId) {
                return;
            }

            // Recipient cross-check as a VETO only, never a selector: plugins
            // can fire their own wp_mail() from inside the mail stack
            // (loggers, failure notifiers), and their failure would otherwise
            // mark OUR delivered row failed — inviting duplicate resends.
            // When the error payload names recipients and none of them is the
            // pinned address, the failure is not ours. Missing/unparseable
            // recipient data keeps the current behavior: a failure during our
            // send is almost always ours.
            if ($this->currentSendingEmailAddress && is_wp_error($error)) {
                $errorData = (array)$error->get_error_data();

                if (!empty($errorData['to'])) {
                    $sawRecipient = false;
                    $isOurs = false;

                    foreach ((array)$errorData['to'] as $recipient) {
                        if (!is_string($recipient) || $recipient === '') {
                            continue;
                        }
                        $sawRecipient = true;
                        // Substring match so "Name <a@b.c>" and comma-joined
                        // recipient strings can't cause a false veto.
                        if (stripos($recipient, $this->currentSendingEmailAddress) !== false) {
                            $isOurs = true;
                            break;
                        }
                    }

                    if ($sawRecipient && !$isOurs) {
                        return;
                    }
                }
            }

            // This builder update also bumps updated_at — fine while 'failed'
            // rows never re-enter claim flows (claim tokens only guard
            // 'processing' rows); revisit if failed ever becomes claimable.
            CampaignEmail::where('id', $this->currentSendingEmailId)
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

        $acquired = Helper::acquireDbLock($this->optionKey, $lockTimeout);

        if ($acquired) {
            /**
             * A bulk sending session is starting: one lock-winning sender run,
             * up to ~50s spanning many claimed batches. Transport plugins can
             * hold a reusable connection open for this window — FluentSMTP
             * uses it for SMTP keep-alive and HTTP connection reuse — and must
             * clean up on the matching session_ended action.
             *
             * @param string $optionKey The sender lock key (identifies the cron,
             *                          multi-thread, or CLI sender).
             */
            do_action('fluent_crm/email_sender_session_started', $this->optionKey);
        }

        return $acquired;
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

        /**
         * The bulk sending session ended — transport plugins should close any
         * connection they held open for it. May fire without a matching
         * session_started (releaseLock is safe to call unheld) and can fire
         * more than once per run: listeners must be idempotent.
         *
         * @param string $optionKey The sender lock key.
         */
        do_action('fluent_crm/email_sender_session_ended', $this->optionKey);
    }
}
