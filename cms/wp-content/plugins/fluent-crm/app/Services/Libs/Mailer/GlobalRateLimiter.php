<?php

namespace FluentCrm\App\Services\Libs\Mailer;

use FluentCrm\App\Services\Helper;

/**
 * Cross-process global send-rate limiter (evenly spaced).
 *
 * Bulk and automation email funnels through Mailer::send(), which calls
 * throttle() here. That makes this the one authoritative cap on the install's
 * outgoing send rate.
 *
 * Two pacing modes, selected by the `multi_threading_emails` experimental flag
 * — the single oracle for whether parallel sending can happen:
 *
 *   - Flag OFF (default, ~90% of installs): the cron Handler is the only
 *     SUSTAINED sender. MultiThreadHandler is gated off (Scheduler) and the CLI
 *     sender no-ops (Commands::cli_send), and the funnel/contact-job sender
 *     (Handler::processSubscriberEmail) shares the cron Handler's lock so it is
 *     serialized, never concurrent. The TAT lives in a process static and pacing
 *     is a plain in-memory compare + sleep — NO DB read or write. Common path,
 *     cheapest it can be.
 *
 *   - Flag ON: the Handler, MultiThreadHandler and WP-CLI workers run at once in
 *     separate processes that share no memory, so the TAT moves to the DB and is
 *     advanced by atomic compare-and-swap. Same even drip, coordinated globally.
 *
 * Both modes implement the identical GCRA algorithm below; they differ only in
 * where the TAT is stored. The flag is checked once per process (memoized).
 *
 * Two gaps in flag-off mode are DELIBERATELY accepted, not bugs:
 *   (1) Sparse direct sends that don't hold the sender lock — double opt-in and
 *       the public unsubscribe/manage-link emails (ExternalPages) — pace from
 *       their own process static and are NOT coordinated with the bulk loop.
 *       Their real-world volume (signups, manual link requests) sits well under
 *       the `emails_per_second - 3` buffer, which is precisely the headroom that
 *       absorbs them, so aggregate stays under the provider cap.
 *   (2) If multi-threading is toggled OFF while a multi/CLI worker is mid-batch,
 *       that worker keeps pacing via the DB for up to ~one batch (~50s) while a
 *       new cron loop paces in memory — two uncoordinated bulk streams. This is
 *       rare (requires a mid-send flag toggle) and self-heals when the worker
 *       drains; accepted rather than guarded.
 *
 * Callers may opt a send OUT of the cap by passing $preThrottled=true to
 * Mailer::send (e.g. double opt-in, a single transactional email on signup that
 * should not be delayed). The bulk handlers also pass it — but only because they
 * already reserved the slot themselves before marking the row sent.
 *
 * Algorithm (GCRA / leaky bucket): a shared "theoretical arrival time" (TAT)
 * marks the earliest moment the next send may go out. Each send atomically does
 *
 *     slot = max(TAT, now);   TAT = slot + (1 / limit)
 *
 * then sleeps until `slot`. Because the read-modify-write is atomic across
 * processes, consecutive sends — whichever process they come from — are handed
 * timeslots exactly 1/limit apart: an even drip at the configured rate, no
 * bursts, which is what burst-sensitive providers like Amazon SES require.
 *
 * Store (multi-thread mode only): ONE fixed wp_options row holding the TAT
 * (microseconds), advanced by an optimistic compare-and-swap (CAS) — a plain
 * SELECT then a conditional UPDATE
 * that only advances the TAT if no other sender moved it first. The single-row
 * conditional UPDATE is atomic on MySQL/MariaDB (InnoDB row lock) and SQLite
 * (global write serialization) alike, with no session variables, GET_LOCK, or
 * GREATEST — so it is portable AND immune to read/write-split routing (the slot
 * is computed in PHP, never read back from a server-side variable that a replica
 * might not have).
 *
 * A deliberately earlier design used a session variable (@fc_slot) and an
 * object-cache mutex. Both were removed after review: @fc_slot silently fails
 * open when a follow-up SELECT routes to a replica (HyperDB/ProxySQL/RDS Proxy),
 * and a TTL-expiring cache mutex cannot do a safe non-idempotent RMW without
 * fencing. The DB CAS path has neither problem.
 *
 * Fail-open by design: if the store is unavailable the limiter returns at once
 * rather than blocking the queue — but every fail-open is LOGGED (sampled) so a
 * silently-degraded limiter is detectable instead of giving false confidence.
 *
 * Backpressure: the wait is never clamped to "send early". Under N concurrent
 * senders the TAT runs at most ~N×interval ahead of now (each process holds one
 * in-flight reservation), so waits are bounded by real concurrency, and the
 * caller sleeps the full wait. Only a corrupt/runaway TAT (more than
 * GARBAGE_AHEAD_MICRO in the future) is treated as poison and reset to now.
 *
 * Caller responsibilities (see BaseHandler::sendEmails): reserve the slot BEFORE
 * marking the row 'sent' (so a crash mid-wait leaves it recoverable), and
 * refresh the processing lock around the wait (so a long backpressure sleep
 * cannot expire the lock mid-batch and admit a second concurrent sender).
 *
 * Caveats (documented, not guarded): (a) if a caller invokes Mailer::send while
 * holding an open DB transaction, the CAS UPDATE joins it and the row lock is
 * held until that transaction commits — the bundled senders all commit before
 * sending, so this only affects third-party callers. (b) Spacing is only as good
 * as clock sync across app servers sharing one DB; the TAT itself is monotonic,
 * but each process's local `now` is used for the sleep target.
 *
 * Scope: the wp_options table is per-blog, so multisite blogs keep independent
 * caps automatically.
 */
class GlobalRateLimiter
{
    const DB_OPTION = '_fc_email_rate_tat';

    // A TAT more than this far in the future is treated as corrupt (clock jump,
    // poisoned value) and reset to now — never as legitimate backpressure.
    // Kept small so the longest possible single wait, PLUS the wp_mail() that
    // follows it, PLUS the sender's heartbeat gap, all stay under the sender's
    // lock TTL (maximumProcessingTime + 30 = 80s): 20s gap + 15s wait + ~30s
    // send = 65s < 80s. Legitimate backpressure (real concurrency × interval)
    // is only ever a few seconds, far below this cap.
    const GARBAGE_AHEAD_MICRO = 15000000; // 15s

    // Bounded CAS retry budget. Exceeding it means pathological contention; the
    // limiter then fails open (logged) rather than spinning forever.
    const MAX_CAS_ATTEMPTS = 50;

    private static $dbRowReady = false;
    private static $cachedLimit = null;
    private static $failOpenCount = 0;

    // Pacing mode, memoized per process. True once we know parallel sending can
    // occur (multi-threading flag on); null until first checked.
    private static $multiThread = null;

    // In-memory TAT (microseconds) for the single-sender fast path. Process-local
    // by design — only correct when this is the install's only sender.
    private static $lastSlotMicro = 0;

    /**
     * Throttle one outgoing email against the global per-second cap.
     *
     * Self-contained: reads the configured limit and the enable switch itself,
     * so any caller invokes it with zero wiring. The bulk handlers call this
     * directly (before marking a row sent) and pass $preThrottled=true to
     * Mailer::send so the email is not throttled twice.
     *
     * @param array $data The email payload, exposed to the enable filter for
     *                     per-email exemptions (inspect $data['scope'] etc.).
     */
    public static function throttle($data = [])
    {
        if (!apply_filters('fluent_crm/enable_global_rate_limit', true, $data)) {
            return;
        }

        $limit = self::getLimit();
        if ($limit < 1) {
            return;
        }

        // 32-bit PHP cannot hold a microsecond timestamp (~1.7e15 > PHP_INT_MAX
        // 2.1e9); the GCRA math would overflow to garbage. Fail open (logged),
        // for either mode, before any interval math runs.
        if (PHP_INT_SIZE < 8) {
            self::logFailOpen('php_32bit');
            return;
        }

        $intervalMicro = (int)ceil(1000000 / $limit);

        // Two pacing modes (see the class docblock for the accepted flag-off gaps):
        //
        //  - Multi-threading OFF (default, ~90% of installs): the cron loop is the
        //    only sustained sender (MultiThreadHandler gated off, CLI no-op, funnel
        //    sends share its lock), so we pace from a process-local TAT: no DB row,
        //    no query, just an in-memory compare and a sleep. The 2-fewer-queries path.
        //
        //  - Multi-threading ON: the Handler, MultiThreadHandler and CLI workers
        //    run concurrently in separate processes that share no memory, so the
        //    TAT must live in the DB and advance by atomic compare-and-swap.
        if (self::isMultiThreadMode()) {
            self::reserveViaDb($intervalMicro);
        } else {
            self::reserveViaMemory($intervalMicro);
        }
    }

    /**
     * Whether to use the DB (cross-process) path instead of in-memory pacing.
     * Driven by the multi-threading experimental flag: when it is off, the only
     * SUSTAINED senders are gated/serialized (MultiThreadHandler off, CLI no-op,
     * funnel sends share the cron lock), so the in-memory path governs the rate.
     * The flag does NOT cover the two accepted gaps documented on the class:
     * sparse unlocked direct sends, and a mid-send flag toggle. Memoized per
     * process — the experimental settings are read once and don't change
     * mid-request.
     *
     * @return bool
     */
    private static function isMultiThreadMode()
    {
        if (self::$multiThread === null) {
            self::$multiThread = Helper::isExperimentalEnabled('multi_threading_emails');
        }

        return self::$multiThread;
    }

    /**
     * In-memory even pacing for the single-sender case. The TAT is a process
     * static; correct ONLY because no other process sends concurrently (see
     * isMultiThreadMode). No DB read/write — this is what saves the two
     * wp_options queries per send on single-threaded installs.
     *
     * @param int $intervalMicro Spacing between sends in microseconds (1e6/limit).
     */
    private static function reserveViaMemory($intervalMicro)
    {
        $nowMicro = (int)round(microtime(true) * 1000000);

        $slot = max(self::$lastSlotMicro, $nowMicro);

        // A backward clock step (NTP correction) could strand $lastSlotMicro far
        // ahead of now and turn the next wait into a multi-minute stall. Treat an
        // absurd gap as poison and reset to now — same guard the DB path uses.
        if ($slot - $nowMicro > self::GARBAGE_AHEAD_MICRO) {
            $slot = $nowMicro;
        }

        self::$lastSlotMicro = $slot + $intervalMicro;

        $waitMicro = $slot - (int)round(microtime(true) * 1000000);
        if ($waitMicro > 0) {
            usleep($waitMicro);
        }
    }

    /**
     * DB-backed pacing for the multi-sender case: reserve the next slot via the
     * cross-process compare-and-swap, then sleep the full wait until it arrives.
     *
     * @param int $intervalMicro Spacing between sends in microseconds (1e6/limit).
     */
    private static function reserveViaDb($intervalMicro)
    {
        $slot = self::reserveTat($intervalMicro);
        if ($slot === null) {
            return; // fail open (already logged)
        }

        // Sleep the FULL wait — never send early. The wait is bounded by real
        // concurrency (TAT runs at most ~N×interval ahead), so this is genuine
        // backpressure, not an unbounded stall.
        $waitMicro = $slot - (int)round(microtime(true) * 1000000);
        if ($waitMicro > 0) {
            usleep($waitMicro);
        }
    }

    /**
     * The global per-second send cap shared by every process, derived from the
     * email settings with the buffer + floor the senders have always used.
     * Memoized per process; a settings change is picked up by the next process.
     *
     * @return int
     */
    public static function getLimit()
    {
        if (self::$cachedLimit !== null) {
            return self::$cachedLimit;
        }

        $emailSettings = fluentcrmGetGlobalSettings('email_settings', []);

        if (!empty($emailSettings['emails_per_second'])) {
            $limit = (int)$emailSettings['emails_per_second'] - 3; // 3 is buffer
        } else {
            $limit = 14;
        }

        if (!$limit || $limit < 4) {
            $limit = 4;
        }

        self::$cachedLimit = (int)apply_filters('fluent_crm/global_email_limit_per_second', $limit, $emailSettings);

        return self::$cachedLimit;
    }

    /**
     * Atomically advance the shared TAT and return this caller's slot
     * (microseconds), using an optimistic compare-and-swap loop.
     *
     * @return int|null Slot timestamp in microseconds, or null to fail open.
     */
    private static function reserveTat($intervalMicro)
    {
        global $wpdb;

        self::ensureDbRow();

        for ($attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; $attempt++) {
            $nowMicro = (int)round(microtime(true) * 1000000);

            $currentRaw = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                self::DB_OPTION
            ));

            if ($currentRaw === null) {
                self::logFailOpen('row_missing');
                return null;
            }

            $tat = (int)$currentRaw;

            // Corrupt/runaway TAT guard: a value absurdly far in the future
            // (clock jump, poisoned write) is reset to now, never honored as a
            // multi-minute sleep.
            if ($tat > $nowMicro + self::GARBAGE_AHEAD_MICRO) {
                $tat = $nowMicro;
            }

            $slot = max($tat, $nowMicro);
            $newTat = (string)($slot + $intervalMicro);

            // Advance only if nobody moved the TAT since our read. The new value
            // is always strictly greater than the old (interval >= 1), so a
            // successful advance always changes the row — rows-changed semantics
            // cannot mask a real win as a false loss.
            $affected = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                $newTat, self::DB_OPTION, $currentRaw
            ));

            if ($affected === false) {
                self::logFailOpen('db_error');
                return null;
            }

            if ($affected > 0) {
                return $slot; // won the slot
            }

            // Lost the race (another sender advanced the TAT). Brief jittered
            // backoff to avoid a thundering retry, then re-read and try again.
            usleep(500 + (($attempt * 211) % 1500));
        }

        self::logFailOpen('cas_contention');
        return null;
    }

    /**
     * Ensure the single TAT row exists (idempotent, once per process).
     * INSERT IGNORE is translated to INSERT OR IGNORE by the WP SQLite plugin.
     */
    private static function ensureDbRow()
    {
        if (self::$dbRowReady) {
            return;
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '0', 'no')",
            self::DB_OPTION
        ));

        self::$dbRowReady = true;
    }

    /**
     * Record a fail-open event so a silently-degraded limiter is detectable. A
     * limiter that is secretly off is worse than none — it gives false
     * confidence — so this logs (sampled, to avoid flooding) whenever the cap is
     * NOT enforced for a send.
     *
     * @param string $reason
     */
    private static function logFailOpen($reason)
    {
        self::$failOpenCount++;

        // First few, then every 100th, to surface the problem without flooding.
        if (self::$failOpenCount <= 3 || self::$failOpenCount % 100 === 0) {
            Helper::debugLog(
                'GlobalRateLimiter fail-open',
                'reason: ' . $reason . ' (occurrence ' . self::$failOpenCount . ') — per-second rate limit NOT enforced for this send',
                'extended'
            );
        }
    }
}
