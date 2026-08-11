<?php

namespace FluentCrm\App\Services\Libs\Mailer;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Services\Helper;

class CliSendingHandler extends BaseHandler
{
    /**
     * Largest accepted --modulo. The partition predicate is a residual filter,
     * so each claim examines ~modulo × chunk index entries under FOR UPDATE —
     * an absurd modulo (e.g. a mistyped 100000) would turn every 30-row claim
     * into a multi-million-entry locking scan. 100 allows up to 50 CLI workers
     * in the even id space, far beyond any supported deployment.
     */
    const MAX_MODULO = 100;

    protected $runnerTitle = 'CliSendingHandler::handle';

    protected $sendingPerChunk = 30;

    protected $maximumProcessingTime = 50;

    /**
     * @deprecated Unused since modulo partitioning replaced offset claims.
     *             Kept only so old positional constructor calls don't break.
     */
    public $offset = 0;

    public $minPendingRequired = 300;

    /**
     * Queue partition this worker claims: rows where (id % modulo) = remainder.
     * Default 2/0 = even ids (the multi-thread web worker takes odd ids, the
     * primary Handler claims everything). Multiple CLI workers should each get
     * a distinct --modulo/--remainder pair instead of the old --offset spacing.
     */
    protected $modulo = 2;

    protected $remainder = 0;

    protected $optionKey = 'fluentcrm_is_sending_cli_emails';

    public function __construct($optionName = 'fluentcrm_is_sending_cli_emails', $runTime = 50, $offset = 0, $minPendingRequired = 300, $modulo = 2, $remainder = 0)
    {
        $this->optionKey = $optionName;
        $this->maximumProcessingTime = $runTime;
        $this->offset = $offset; // deprecated, ignored
        $this->minPendingRequired = $minPendingRequired;

        $this->modulo = min(self::MAX_MODULO, max(1, (int)$modulo));
        $this->remainder = (int)$remainder;
        if ($this->remainder < 0 || $this->remainder >= $this->modulo) {
            $this->remainder = 0;
        }
    }

    public function handle()
    {
        $this->cleanupStaleCliLockRows();

        $systemCheck = $this->isSystemOk();
        if (is_wp_error($systemCheck)) {
            return $systemCheck;
        }

        Helper::maybeDisableEmojiOnEmail();
        Helper::debugLog('Starting ' . $this->runnerTitle, '', 'extended');

        try {
            $this->handleFailedLog();
            $result = $this->processBatchEmails();

            if (is_wp_error($result)) {
                $this->releaseLock();
                $this->logSentCount();
                return new \WP_Error('wp_error', $result->get_error_message());
            }

            if ($result === 'time_up') {
                $this->releaseLock();
                $this->logSentCount();
                return new \WP_Error('time_up', 'Time Up');
            }

        } catch (\Throwable $e) {
            $this->releaseLock();
            Helper::debugLog('Exception at ' . $this->runnerTitle, $e->getMessage(), 'error');
            return new \WP_Error('exception', $e->getMessage());
        }

        $this->logSentCount();
        $this->releaseLock();
        return true;
    }

    /**
     * Housekeeping for custom --option_key lock rows.
     *
     * The fixed sender locks reuse one wp_options row forever, but a script
     * that generates a fresh --option_key per run leaves a dead row behind
     * each time. Sweep released ('') or day-old custom lock rows on every CLI
     * run. Safe because acquireDbLock() re-creates rows on demand (INSERT
     * IGNORE): deleting an idle sibling's row costs it one re-insert on its
     * next acquire; rows with a fresh timestamp (a live worker) are kept.
     */
    private function cleanupStaleCliLockRows()
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE 'fluentcrm\\_is\\_sending\\_%%'
              AND option_name NOT IN ('fluentcrm_is_sending_emails', 'fluentcrm_is_sending_multi_emails', 'fluentcrm_is_sending_cli_emails')
              AND (option_value = '' OR option_value < %d)",
            time() - DAY_IN_SECONDS
        ));
    }

    private function isSystemOk()
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return new \WP_Error('not_cli', 'This is not a CLI request');
        }

        $this->calledFrom = 'CLI';

        if (
            did_action('fluent_crm/sending_cli_threading_email') ||
            apply_filters('fluent_crm/disable_email_processing', false)
        ) {
            return new \WP_Error('disabled', 'Email Processing is disabled');
        }

        if ($this->memoryExceeded()) {
            Helper::debugLog('Mailer Memory Exceeded at ' . $this->runnerTitle, 'Memory Limit: ' . fluentCrmGetMemoryLimit() . '<br />Current Usage: ' . memory_get_usage(true));
            return new \WP_Error('memory_exceeded', 'Memory Exceeded at ' . $this->runnerTitle);
        }

        if (Helper::getUpcomingEmailCount($this->minPendingRequired) < $this->minPendingRequired) {
            return new \WP_Error('not_enough', 'Pending emails are not enough to process');
        }

        $this->isMultiThread = true;
        $this->startingTimeStamp = time();

        if (!$this->acquireLock()) {
            Helper::debugLog('already Processing', 'CliSendingHandler::handle', 'extended');
            return new \WP_Error('already_processing', 'Already Processing');
        }

        return true;
    }

    protected function getNextBatchEmails()
    {
        // Capped at the threshold: this runs before EVERY batch, and a full
        // COUNT(*) walked the whole pending slice (millions of index entries
        // on big queues) just to answer "at least minPendingRequired left?".
        // Below the cap the count is exact, so the exit message stays right.
        $remaining = Helper::getUpcomingEmailCount($this->minPendingRequired);
        if ($remaining < $this->minPendingRequired) {
            \WP_CLI::line(sprintf('only %d emails left. Exiting....', $remaining));
            return [];
        }

        if ($this->memoryExceeded()) {
            Helper::debugLog('Mailer Memory Exceeded at ' . $this->runnerTitle, 'Memory Limit: ' . fluentCrmGetMemoryLimit() . '<br />Current Usage: ' . memory_get_usage(true));
            return [];
        }

        if ($this->sentCount) {
            \WP_CLI::line(sprintf('Sent %1d emails. -> %2d', $this->sentCount, $this->sendingChunkNumber));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fc_campaign_emails';
        $currentTime = current_time('mysql');

        // Use transaction-based atomic claiming like Handler to prevent duplicates.
        // Claims only this worker's modulo partition (see the property docs) —
        // replaces the old OFFSET skip, whose FOR UPDATE scan locked every
        // skipped row and overlapped other DESC workers' ranges. One locking
        // SELECT per status ('pending' first) — see BaseHandler::lockClaimableIds()
        // for why the two statuses must not share one IN() claim.
        $wpdb->query('START TRANSACTION');

        $ids = $this->lockClaimableIds('pending', $currentTime, $this->sendingPerChunk, 'DESC', $this->modulo, $this->remainder);
        if (count($ids) < $this->sendingPerChunk) {
            $ids = array_merge($ids, $this->lockClaimableIds('scheduled', $currentTime, $this->sendingPerChunk - count($ids), 'DESC', $this->modulo, $this->remainder));
        }

        if ($ids) {
            $idsPlaceholder = implode(',', array_fill(0, count($ids), '%d'));
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status = 'processing', updated_at = %s WHERE id IN ($idsPlaceholder) AND status IN ('pending', 'scheduled')",
                array_merge([$currentTime], $ids)
            ));

            if ($result === false || $wpdb->rows_affected === 0) {
                $wpdb->query('ROLLBACK');
                return [];
            }
        }

        $wpdb->query('COMMIT');

        if (!$ids) {
            return [];
        }

        $this->refreshLock();

        return CampaignEmail::whereIn('id', $ids)
            ->where('status', 'processing')
            ->with(['campaign', 'subscriber'])
            ->get();
    }

    public function setRunnerTitle($title)
    {
        $this->runnerTitle = $title;
        return $this;
    }

    protected function isTimeUp()
    {
        return (time() - $this->startingTimeStamp) >= $this->maximumProcessingTime;
    }
}
