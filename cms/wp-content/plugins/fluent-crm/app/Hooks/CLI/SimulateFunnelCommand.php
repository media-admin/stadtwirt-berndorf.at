<?php

namespace FluentCrm\App\Hooks\CLI;

use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Funnel\FunnelProcessor;

class SimulateFunnelCommand
{
    /*
     * Fast-forward a subscriber through an automation funnel, skipping wait times.
     * Real actions will fire (tags applied, emails sent, etc.) — only delays are shortened.
     *
     * Usage:
     *   wp fluent_crm simulate_funnel --funnel_id=123 --email=john@example.com
     *   wp fluent_crm simulate_funnel --funnel_id=123 --subscriber_id=456
     *   wp fluent_crm simulate_funnel --funnel_id=123 --email=john@example.com --sleep=1 --max_steps=50
     *   wp fluent_crm simulate_funnel --funnel_id=123 --email=john@example.com --sleep=0
     *
     * --sleep=0 runs one step at a time (step mode). Run the command again to advance to the next step.
     */
    public function handle($args, $assoc_args)
    {
        $funnelId = \WP_CLI\Utils\get_flag_value($assoc_args, 'funnel_id');
        $subscriberId = \WP_CLI\Utils\get_flag_value($assoc_args, 'subscriber_id');
        $email = \WP_CLI\Utils\get_flag_value($assoc_args, 'email');
        $sleepSeconds = intval(\WP_CLI\Utils\get_flag_value($assoc_args, 'sleep', 2));
        $maxSteps = max(1, intval(\WP_CLI\Utils\get_flag_value($assoc_args, 'max_steps', 100)));
        $stepMode = $sleepSeconds === 0;

        if ($sleepSeconds < 0) {
            $sleepSeconds = 0;
        }

        if (!$funnelId) {
            \WP_CLI::error('--funnel_id is required');
        }

        $funnel = Funnel::find(intval($funnelId));
        if (!$funnel) {
            \WP_CLI::error('Funnel not found');
        }

        if ($subscriberId) {
            $subscriber = Subscriber::find(intval($subscriberId));
        } elseif ($email) {
            $subscriber = Subscriber::where('email', sanitize_email($email))->first();
        } else {
            \WP_CLI::error('--subscriber_id or --email is required');
            return;
        }

        if (!$subscriber) {
            \WP_CLI::error('Subscriber not found');
        }

        \WP_CLI::line('---');
        \WP_CLI::line(sprintf('Funnel: %s (#%d) - Status: %s', $funnel->title, $funnel->id, $funnel->status));
        \WP_CLI::line(sprintf('Subscriber: %s (#%d) - Status: %s', $subscriber->email, $subscriber->id, $subscriber->status));
        if ($stepMode) {
            \WP_CLI::line('Mode: step-by-step (--sleep=0)');
        } else {
            \WP_CLI::line(sprintf('Wait times will be reduced to %d second(s)', $sleepSeconds));
        }
        \WP_CLI::line('---');

        // Print funnel step map
        $this->printFunnelSteps($funnel->id);

        if ($funnel->status !== 'published') {
            \WP_CLI::warning('This funnel is not published. Proceeding anyway...');
        }

        // Check existing enrollment
        $funnelSub = FunnelSubscriber::where('funnel_id', $funnel->id)
            ->where('subscriber_id', $subscriber->id)
            ->first();

        if ($funnelSub) {
            if (in_array($funnelSub->status, ['completed', 'cancelled'])) {
                \WP_CLI::line(sprintf('Subscriber already %s this funnel.', $funnelSub->status));
                \WP_CLI::confirm('Re-enroll and start fresh?');
                $this->resetFunnelEnrollment($funnel->id, $subscriber->id, $funnelSub->id);
                $funnelSub = null;
            } elseif (in_array($funnelSub->status, ['active', 'waiting'])) {
                $nextSeq = $funnelSub->next_sequence_id ? FunnelSequence::find($funnelSub->next_sequence_id) : null;
                $nextLabel = $nextSeq ? ($nextSeq->title ?: $nextSeq->action_name) : 'unknown';
                \WP_CLI::line(sprintf('Subscriber is already in this funnel (status: %s, next: %s).', $funnelSub->status, $nextLabel));

                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
                fwrite(STDOUT, 'Resume or Restart? (resume/restart): ');
                $choice = strtolower(trim(fgets(STDIN)));

                if ($choice === 'restart') {
                    $this->resetFunnelEnrollment($funnel->id, $subscriber->id, $funnelSub->id);
                    $funnelSub = null;
                    \WP_CLI::line('Restarting from the beginning...');
                } else {
                    \WP_CLI::line('Resuming from current position...');
                }
            } else {
                \WP_CLI::line(sprintf('Current enrollment status: %s', $funnelSub->status));
            }
        }

        // In auto-advance mode, minimize wait times so we don't actually wait days
        if (!$stepMode) {
            $filterDelay = max(1, $sleepSeconds);
            add_filter('fluent_crm/funnel_seq_delay_in_seconds', function () use ($filterDelay) {
                return $filterDelay;
            }, 99999, 4);
        }

        $processor = new FunnelProcessor();

        $firedHooks = [];

        // Enroll if not already
        if (!$funnelSub) {
            \WP_CLI::line('Enrolling subscriber into funnel...');

            $hooksBefore = $this->snapshotHooks();
            $processor->startSequences($subscriber, $funnel);
            $firedHooks = array_merge($firedHooks, $this->diffHooks($hooksBefore));

            $funnelSub = FunnelSubscriber::where('funnel_id', $funnel->id)
                ->where('subscriber_id', $subscriber->id)
                ->first();

            if (!$funnelSub) {
                \WP_CLI::error('Failed to enroll — funnel may have no sequences');
            }

            $this->showExecutedMetrics($funnel->id, $subscriber->id, 'Enrollment');

            // In step mode, stop after enrollment — next run will resume
            if ($stepMode) {
                $this->showStepModeNextUp($funnelSub);
                $this->askAndShowFiredHooks($firedHooks);
                $this->showFinalStatus($funnel->id, $subscriber->id, $funnelSub->id);
                return;
            }
        }

        // In step mode, process exactly one batch then stop
        if ($stepMode) {
            $lastMetricId = (int) FunnelMetric::where('funnel_id', $funnel->id)
                ->where('subscriber_id', $subscriber->id)
                ->max('id');

            $hooksBefore = $this->snapshotHooks();
            $this->processOneStep($processor, $funnelSub);
            $firedHooks = array_merge($firedHooks, $this->diffHooks($hooksBefore));

            // Show what was processed in this step
            $newMetrics = FunnelMetric::where('funnel_id', $funnel->id)
                ->where('subscriber_id', $subscriber->id)
                ->where('id', '>', $lastMetricId)
                ->orderBy('id', 'ASC')
                ->get();

            if ($newMetrics->count()) {
                \WP_CLI::line(sprintf('Processed %d action(s):', $newMetrics->count()));
                foreach ($newMetrics as $metric) {
                    $seq = FunnelSequence::find($metric->sequence_id);
                    if ($seq) {
                        \WP_CLI::line(sprintf('  > [%s] %s', $seq->action_name, $seq->title ?: ''));
                    }
                }
            }

            $funnelSub = FunnelSubscriber::find($funnelSub->id);
            $this->showStepModeNextUp($funnelSub);
            $this->askAndShowFiredHooks($firedHooks);
            $this->showFinalStatus($funnel->id, $subscriber->id, $funnelSub->id);
            return;
        }

        // Fast-forward remaining steps
        $step = 0;

        while ($step < $maxSteps) {
            $shouldBreak = $this->checkTerminalStatus($funnelSub);
            if ($shouldBreak) {
                break;
            }

            $step++;

            // Show what's about to execute
            $nextSeq = $funnelSub->next_sequence_id ? FunnelSequence::find($funnelSub->next_sequence_id) : null;
            if ($nextSeq) {
                \WP_CLI::line(sprintf('[Step %d] %s: %s', $step, $nextSeq->action_name, $nextSeq->title ?: ''));
            }

            // Force execution time to now
            FunnelSubscriber::where('id', $funnelSub->id)->update([
                'next_execution_time' => current_time('mysql'),
            ]);
            $funnelSub->next_execution_time = current_time('mysql');

            // Process the next step
            $hooksBefore = $this->snapshotHooks();
            $processor->processFunnelAction($funnelSub);
            $firedHooks = array_merge($firedHooks, $this->diffHooks($hooksBefore));

            sleep($sleepSeconds);

            // Reload for next iteration
            $funnelSub = FunnelSubscriber::find($funnelSub->id);
        }

        if ($step >= $maxSteps) {
            \WP_CLI::warning(sprintf('Reached max steps limit (%d). Use --max_steps to increase.', $maxSteps));
        }

        $this->askAndShowFiredHooks($firedHooks);
        $this->showFinalStatus($funnel->id, $subscriber->id, $funnelSub ? $funnelSub->id : null);
    }

    private function resetFunnelEnrollment($funnelId, $subscriberId, $funnelSubId)
    {
        FunnelMetric::where('funnel_id', $funnelId)
            ->where('subscriber_id', $subscriberId)
            ->delete();
        FunnelSubscriber::where('id', $funnelSubId)->delete();
    }

    private function showExecutedMetrics($funnelId, $subscriberId, $label)
    {
        $metrics = FunnelMetric::where('funnel_id', $funnelId)
            ->where('subscriber_id', $subscriberId)
            ->orderBy('id', 'ASC')
            ->get();

        if ($metrics->count()) {
            \WP_CLI::line(sprintf('%s processed %d step(s):', $label, $metrics->count()));
            foreach ($metrics as $metric) {
                $seq = FunnelSequence::find($metric->sequence_id);
                if ($seq) {
                    \WP_CLI::line(sprintf('  > [%s] %s', $seq->action_name, $seq->title ?: ''));
                }
            }
        }
    }

    private function processOneStep($processor, $funnelSub)
    {
        $funnelSub = FunnelSubscriber::find($funnelSub->id);

        $shouldBreak = $this->checkTerminalStatus($funnelSub);
        if ($shouldBreak) {
            return;
        }

        // Force execution time to now so processFunnelAction picks it up
        FunnelSubscriber::where('id', $funnelSub->id)->update([
            'next_execution_time' => current_time('mysql'),
        ]);
        $funnelSub->next_execution_time = current_time('mysql');

        // Use the real processor — SequencePoints resolves next batch,
        // processSequencePoints executes it
        $processor->processFunnelAction($funnelSub);
    }

    private function showStepModeNextUp($funnelSub)
    {
        if (!$funnelSub) {
            return;
        }

        if ($funnelSub->status === 'active' && $funnelSub->next_sequence_id) {
            $upNext = FunnelSequence::find($funnelSub->next_sequence_id);
            \WP_CLI::line(sprintf(
                'Up next: [%s] %s',
                $upNext ? $upNext->action_name : '?',
                $upNext ? ($upNext->title ?: '') : ''
            ));
            \WP_CLI::line('Run the command again to advance.');
        }
    }

    private function checkTerminalStatus($funnelSub)
    {
        if (!$funnelSub) {
            \WP_CLI::error('Funnel subscriber record not found');
            return true;
        }

        if ($funnelSub->status === 'completed') {
            \WP_CLI::success('Funnel completed!');
            return true;
        }

        if ($funnelSub->status === 'cancelled') {
            \WP_CLI::warning('Funnel cancelled (subscriber may not be in a processable status)');
            return true;
        }

        if ($funnelSub->status === 'waiting') {
            $seq = $funnelSub->next_sequence_id ? FunnelSequence::find($funnelSub->next_sequence_id) : null;
            \WP_CLI::warning(sprintf(
                'Blocked on benchmark: %s — cannot auto-advance past goals.',
                $seq ? ($seq->title ?: $seq->action_name) : 'unknown'
            ));
            return true;
        }

        if ($funnelSub->status === 'pending') {
            \WP_CLI::warning('Subscriber is pending (needs double opt-in). Cannot auto-advance.');
            return true;
        }

        if ($funnelSub->status !== 'active' || !$funnelSub->next_execution_time) {
            return true;
        }

        return false;
    }

    private function snapshotHooks()
    {
        global $wp_actions;
        return $wp_actions ?: [];
    }

    private function diffHooks($before)
    {
        global $wp_actions;
        $after = $wp_actions ?: [];
        $fired = [];

        foreach ($after as $hook => $count) {
            $prevCount = isset($before[$hook]) ? $before[$hook] : 0;
            if ($count > $prevCount) {
                // Only include fluentcrm-related hooks
                if (strpos($hook, 'fluentcrm') !== false || strpos($hook, 'fluent_crm') !== false) {
                    $fired[$hook] = $count - $prevCount;
                }
            }
        }

        return $fired;
    }

    private function askAndShowFiredHooks($firedHooks)
    {
        if (empty($firedHooks)) {
            return;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fwrite(STDOUT, sprintf('Show fired hooks? (%d hooks) (yes/no): ', count($firedHooks)));
        $answer = strtolower(trim(fgets(STDIN)));

        if ($answer !== 'yes' && $answer !== 'y') {
            return;
        }

        \WP_CLI::line('Fired hooks:');
        foreach ($firedHooks as $hook => $count) {
            $suffix = $count > 1 ? sprintf(' (x%d)', $count) : '';
            \WP_CLI::line(sprintf('  > %s%s', $hook, $suffix));
        }
    }

    private function showFinalStatus($funnelId, $subscriberId, $funnelSubId)
    {
        $funnelSub = $funnelSubId ? FunnelSubscriber::find($funnelSubId) : null;
        $totalMetrics = FunnelMetric::where('funnel_id', $funnelId)
            ->where('subscriber_id', $subscriberId)
            ->count();

        \WP_CLI::line('---');
        \WP_CLI::line(sprintf('Final status: %s', $funnelSub ? $funnelSub->status : 'unknown'));
        \WP_CLI::line(sprintf('Total actions executed: %d', $totalMetrics));
    }

    private function printFunnelSteps($funnelId)
    {
        $sequences = FunnelSequence::where('funnel_id', $funnelId)
            ->orderBy('sequence', 'ASC')
            ->get();

        if ($sequences->isEmpty()) {
            \WP_CLI::line('No steps in this funnel.');
            \WP_CLI::line('---');
            return;
        }

        // Group children by parent_id and condition_type
        $topLevel = [];
        $children = []; // $children[$parentId][$conditionType][]
        foreach ($sequences as $seq) {
            if (!$seq->parent_id) {
                $topLevel[] = $seq;
            } else {
                $children[$seq->parent_id][$seq->condition_type][] = $seq;
            }
        }

        \WP_CLI::line('Funnel steps:');
        $this->printSequenceList($topLevel, $children, '  ');
        \WP_CLI::line('---');
    }

    private function printSequenceList($sequences, $children, $indent)
    {
        $count = count($sequences);
        foreach ($sequences as $i => $seq) {
            $label = $this->formatSequenceLabel($seq);
            $isLast = ($i === $count - 1);
            $connector = $isLast ? '└─' : '├─';
            \WP_CLI::line($indent . $connector . ' ' . $label);

            // If conditional/ab-test, print branches
            if ($seq->type === 'conditional' && isset($children[$seq->id])) {
                $childIndent = $indent . ($isLast ? '   ' : '│  ');
                $branches = $children[$seq->id];

                if (isset($branches['yes'])) {
                    \WP_CLI::line($childIndent . '├─ [YES]:');
                    $this->printSequenceList($branches['yes'], $children, $childIndent . '│  ');
                }
                if (isset($branches['no'])) {
                    \WP_CLI::line($childIndent . '└─ [NO]:');
                    $this->printSequenceList($branches['no'], $children, $childIndent . '   ');
                }
            }
        }
    }

    private function formatSequenceLabel($seq)
    {
        $type = $seq->type ?: 'action';
        $title = $seq->title ?: $seq->action_name;

        if ($seq->action_name === 'fluentcrm_wait_times') {
            $wait = $this->formatWaitTime($seq->settings);
            return sprintf('(%s) %s — %s', $type, $title, $wait);
        }

        if ($seq->action_name === 'end_this_funnel') {
            return sprintf('(%s) End Funnel', $type);
        }

        return sprintf('(%s) %s', $type, $title);
    }

    private function formatWaitTime($settings)
    {
        if (!is_array($settings)) {
            return '';
        }

        $waitType = $settings['wait_type'] ?? '';

        if ($waitType === 'timestamp_wait') {
            return 'until ' . ($settings['wait_date_time'] ?? '?');
        }

        if ($waitType === 'to_day') {
            $day = $settings['wait_day_of_week'] ?? '?';
            $time = $settings['wait_time_of_day'] ?? '';
            return sprintf('next %s%s', $day, $time ? ' at ' . $time : '');
        }

        if ($waitType === 'by_custom_field') {
            return 'until custom field date';
        }

        $amount = $settings['wait_time_amount'] ?? '?';
        $unit = $settings['wait_time_unit'] ?? 'days';
        return sprintf('%s %s', $amount, $unit);
    }
}
