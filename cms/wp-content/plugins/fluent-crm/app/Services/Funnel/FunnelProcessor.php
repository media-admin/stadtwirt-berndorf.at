<?php

namespace FluentCrm\App\Services\Funnel;

use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

class FunnelProcessor
{
    private $subscribersCache = [];

    private $sequenceFunnelCache = [];

    private $funnelCache = [];

    public function getSubscriber($id)
    {
        if (isset($this->subscribersCache[$id])) {
            return $this->subscribersCache[$id];
        }
        $subscriber = Subscriber::find($id);
        $this->subscribersCache[$id] = $subscriber;
        return $this->subscribersCache[$id];
    }

    public function setSubscriber($id)
    {
        $subscriber = Subscriber::find($id);
        $this->subscribersCache[$id] = $subscriber;
        return $this->subscribersCache[$id];
    }

    public function getSequence($id)
    {
        if (isset($this->sequenceFunnelCache[$id])) {
            return $this->sequenceFunnelCache[$id];
        }

        $funnelAction = FunnelSequence::find($id);
        $this->sequenceFunnelCache[$id] = $funnelAction;
        return $this->sequenceFunnelCache[$id];
    }

    public function getFunnel($id)
    {
        if (isset($this->funnelCache[$id])) {
            return $this->funnelCache[$id];
        }
        $funnel = Funnel::find($id);
        $this->funnelCache[$id] = $funnel;
        return $this->funnelCache[$id];
    }

    public function setFunnel($id)
    {
        $funnel = Funnel::find($id);
        $this->funnelCache[$id] = $funnel;
        return $this->funnelCache[$id];
    }

    public function startFunnelSequence($funnel, $subscriberData, $funnelSubArgs = [], $subscriber = false)
    {
        if (isset($subscriberData['email'])) {
            $subscriber = Subscriber::where('email', $subscriberData['email'])->first();
        }

        if (!$subscriber) {
            // it's new so let's create new subscriber
            $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);

            if (!$subscriber) {
                return false;
            }
        } elseif (Arr::get($subscriberData, 'status') === 'pending' && $subscriber->status !== 'subscribed') {
            // update status only if it's not subscribed already
            $subscriber->status = 'pending';
            $subscriber->save();
        }

        if ($subscriber->status == 'pending') {
            $subscriber->sendDoubleOptinEmail();
        }

        $args = [
            'status' => ($subscriber->status == 'pending' || $subscriber->status == 'unsubscribed') ? 'pending' : 'draft'
        ];

        if ($funnelSubArgs) {
            $args = wp_parse_args($args, $funnelSubArgs);
        }

        $this->startSequences($subscriber, $funnel, $args);
    }

    public function startSequences($subscriber, $funnel, $funnelSubArgs = [])
    {
        // Check if already in funnel to prevent duplicate entries
        $existing = FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id);
        if ($existing) {
            return;
        }

        $data = [
            'funnel_id'     => $funnel->id,
            'subscriber_id' => $subscriber->id,
            'status'        => 'draft'
        ];

        if ($funnelSubArgs) {
            $data = wp_parse_args($funnelSubArgs, $data);
        }

        $data['status'] = FunnelHelper::getFunnelSubscriberStatus($data['status'], $funnel, $subscriber);

        // Unique index on (funnel_id, subscriber_id) prevents duplicates at DB level
        try {
            $funnelSubscriber = FunnelSubscriber::create($data);
        } catch (\Exception $e) {
            return;
        }

        $sequencePoints = (new SequencePoints($funnel, $funnelSubscriber));

        if (!$sequencePoints->hasSequences()) {
            FunnelSubscriber::where('id', $funnelSubscriber->id)->delete();
            return;
        }

        do_action('fluent_crm/automation_funnel_start', $funnel, $subscriber);

        if ($funnelSubscriber->status != 'pending') {
            $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
        }
    }

    public function processSequencePoints(SequencePoints $sequencePoints, $subscriber, $funnelSubscriber)
    {
        if (!$sequencePoints->hasSequences()) {
            $this->completeFunnelSequence($funnelSubscriber);
            return;
        }

        // Execute immediate sequences first (they may include conditionals
        // that set their own next state via initChildSequences)
        $hasConditional = false;
        foreach ($sequencePoints->getCurrentSequences() as $sequence) {
            if ($sequence->type == 'conditional') {
                $hasConditional = true;
            }
            $this->processSequence($subscriber, $sequence, $funnelSubscriber->id);
            if ($sequence->action_name == 'end_this_funnel') {
                $this->completeFunnelSequence($funnelSubscriber);
                $this->setFunnel($funnelSubscriber->funnel_id);
                return;
            }
        }

        // If a conditional was processed, initChildSequences() already
        // handled the subscriber's next state — skip status update
        if ($hasConditional) {
            return;
        }

        $nextSequence = $sequencePoints->getNextSequence();
        $requiredBenchMark = $sequencePoints->getRequiredBenchmark();

        if ($nextSequence && $requiredBenchMark) {
            if ($nextSequence->sequence < $requiredBenchMark->sequence) {
                $requiredBenchMark = false;
            }
        }

        if ($requiredBenchMark) {
            FunnelSubscriber::where('id', $funnelSubscriber->id)
                ->update([
                    'next_sequence'       => $requiredBenchMark->sequence,
                    'next_sequence_id'    => $requiredBenchMark->id,
                    'next_execution_time' => NULL,
                    'status'              => 'waiting'
                ]);
        } else if (!$sequencePoints->hasNext()) {
            $this->completeFunnelSequence($funnelSubscriber);
        } else if ($nextSequence) {
            $nextDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') + $nextSequence->delay);

            if ($nextSequence->execution_date_time) {
                $nextDateTime = $nextSequence->execution_date_time;
            }

            FunnelSubscriber::where('id', $funnelSubscriber->id)
                ->update([
                    'next_sequence'       => $nextSequence->sequence,
                    'next_sequence_id'    => $nextSequence->id,
                    'next_execution_time' => $nextDateTime,
                    'status'              => 'active'
                ]);
        }
    }


    public function processSequence($subscriber, $sequence, $funnelSubscriberId)
    {
        $funnelMetric = $this->recordFunnelMetric($subscriber, $sequence);

        // Mark complete BEFORE execution by design: prevents double-execution if the process
        // crashes mid-action. The FunnelMetric + wasRecentlyCreated check ensures each action
        // runs at most once per subscriber per sequence. Failed actions are not retried.
        FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'complete');

        if ($sequence->type == 'conditional' && $sequence->action_name != 'funnel_condition') {
            $sequence = FunnelHelper::migrateConditionSequence($sequence);
        }

        if ($funnelMetric->wasRecentlyCreated) {
            try {
                do_action('fluentcrm_funnel_sequence_handle_' . $sequence->action_name, $subscriber, $sequence, $funnelSubscriberId, $funnelMetric, $this);
            } catch (\Throwable $e) {
                Helper::debugLog('Funnel sequence error for ' . $sequence->funnel_id . ' => Funnel Sub ID: ' . $funnelSubscriberId, $e->getMessage());
            }
        }
    }

    public function resumeFunnelSubscriber($funnel, $subscriber, $funnelSubscriber)
    {
        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);
        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }

    public function completeFunnelSequence($funnelSubscriber)
    {
        FunnelSubscriber::where('id', $funnelSubscriber->id)
            ->update([
                'status' => 'completed'
            ]);

        $funnel = $this->getFunnel($funnelSubscriber->funnel_id);
        $subscriber = $this->getSubscriber($funnelSubscriber->subscriber_id);

        if ($funnel && $subscriber) {
            do_action('fluent_crm/automation_funnel_completed', $funnel, $subscriber);
        }
    }

    public function followUpSequenceActions()
    {
        update_option('_fc_last_funnel_processor_ran', time(), 'no');

        /**
         * Apply a filter to retrieve the subscriber statuses for the funnel.
         *
         * This filter allows customization of the subscriber statuses used in the funnel processing.
         * By default, it includes only the 'active' status.
         *
         * @param array $statuses The default subscriber statuses, which is ['active'].
         * @return array Filtered subscriber statuses.
         * @since 1.0.0
         *
        */
        $statuses = apply_filters('fluent_crm/funnel_subscriber_statuses', ['active']);

        /* Funnel processor batch limit
         * This is the maximum number of funnel subscribers that will be processed in a single run.
         * By default, it is set to 200. FluentCRM wants to process 200 funnel subscribers each time
         * @param int $batchLimit The batch limit for the funnel processor.
         * @return int Filtered batch limit.
         * @since 3.0.0
         */
        $batchLimit = (int)apply_filters('fluent_crm/funnel_processor_batch_limit', 200);
        if ($batchLimit < 1) {
            $batchLimit = 1;
        }

        $jobs = FunnelSubscriber::join('fc_funnels', function ($join) {
                $join->on('fc_funnel_subscribers.funnel_id', '=', 'fc_funnels.id')
                    ->where('fc_funnels.status', '=', 'published')
                    ->where('fc_funnels.type', '=', 'funnels');
            })
            ->whereIn('fc_funnel_subscribers.status', $statuses)
            ->where('fc_funnel_subscribers.next_execution_time', '<=', current_time('mysql'))
            ->whereNotNull('fc_funnel_subscribers.next_execution_time')
            ->orderBy('fc_funnel_subscribers.next_execution_time', 'ASC')
            ->limit($batchLimit)
            ->select('fc_funnel_subscribers.*')
            ->get();

        $startingAt = time();

        $completed = 0;

        /* Funnel processor max processing seconds
         * This is the maximum number of seconds that the funnel processor will run for.
         * By default, it is set to 55 seconds.
         * This is a safety mechanism to prevent the funnel processor from running for too long
         * @since 3.0.0
         */
        $maxProcessingSeconds = (int)apply_filters('fluent_crm/funnel_processor_max_processing_seconds', 55);
        if ($maxProcessingSeconds < 1) {
            $maxProcessingSeconds = 1;
        }

        foreach ($jobs as $job) {
            if ((time() - $startingAt) > $maxProcessingSeconds) {
                break;
            }

            $completed++;

            $this->processFunnelAction($job);
        }

        Helper::debugLog('Automation followUpSequenceActions', 'Completed Jobs Count: ' . $completed);
    }

    public function processFunnelAction($funnelSubscriber)
    {
        $subscriber = $this->getSubscriber($funnelSubscriber->subscriber_id);
        $funnel = $this->getFunnel($funnelSubscriber->funnel_id);

        if (!$subscriber || !$funnel) {
            FunnelSubscriber::where('id', $funnelSubscriber->id)->update([
                'status' => 'skipped'
            ]);
            return false;
        }

        if (!in_array($subscriber->status, ['subscribed', 'transactional'])) {
            $forceRun = Arr::get($funnel->settings, '__force_run_actions') === 'yes';
            if (!$forceRun) {
                FunnelSubscriber::where('id', $funnelSubscriber->id)->update([
                    'status' => 'cancelled'
                ]);
                return false;
            }
        }

        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);

        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }

    public function startFunnelFromSequencePoint($startSequence, $subscriber, $args = [], $metricArgs = [])
    {
        if (!$subscriber) {
            return false;
        }

        $funnelSubscriber = FunnelHelper::ifAlreadyInFunnel($startSequence->funnel_id, $subscriber->id);

        if (!$funnelSubscriber && $startSequence->type == 'benchmark') {
            // it's new starting point for a goal type sequence
            // so if the can start is set to no then we will skip this
            if (Arr::get($startSequence->settings, 'can_enter') == 'no') {
                return false;
            }
        }

        if ($funnelSubscriber && ($funnelSubscriber->status == 'completed' || $funnelSubscriber->status == 'cancelled')) {
            return false; // It's already completed or cancelled. We don't need to start again
        }


        $this->recordFunnelMetric($subscriber, $startSequence, $metricArgs);

        if (!$funnelSubscriber) {

            $processableStatuses = ['subscribed', 'transactional'];

            // we have to create a funnel subscriber
            $data = [
                'funnel_id'            => $startSequence->funnel_id,
                'subscriber_id'        => $subscriber->id,
                'status'               => (in_array($subscriber->status, $processableStatuses, true)) ? 'active' : 'pending',
                'starting_sequence_id' => $startSequence->id,
                'last_sequence_status' => 'completed',
                'next_sequence'        => $startSequence->sequence + 1,
                'last_sequence_id'     => $startSequence->id,
                'last_executed_time'   => current_time('mysql'),
                'source_trigger_name'  => $startSequence->action_name
            ];

            if ($args) {
                $data = wp_parse_args($args, $data);
            }

            if ($data['status'] != 'active') {
                $data['status'] = FunnelHelper::getFunnelSubscriberStatus($data['status'], $this->getFunnel($startSequence->funnel_id), $subscriber);
            }

            // Unique index on (funnel_id, subscriber_id) prevents duplicates at DB level
            try {
                $funnelSubscriber = FunnelSubscriber::create($data);
            } catch (\Exception $e) {
                return;
            }
        } else {
            // We already have funnel subscriber. Now we have to update that
            $lastSequence = $funnelSubscriber->last_sequence;

            if (!$lastSequence || ($lastSequence->sequence <= $startSequence->sequence)) {
                $nextSequence = FunnelSequence::where('sequence', '>', $startSequence->sequence)
                    ->where('funnel_id', $startSequence->funnel_id)
                    ->orderBy('sequence', 'ASC')
                    ->first();

                if (!$nextSequence) {
                    $this->completeFunnelSequence($funnelSubscriber);
                    return;
                }

                $funnelSubscriber->last_sequence_id = $startSequence->id;
                $funnelSubscriber->next_sequence_id = $nextSequence->id;
                $funnelSubscriber->next_sequence = $nextSequence->sequence;
                if ($funnelSubscriber->status == 'waiting') {
                    $funnelSubscriber->status = 'active';
                }
                $funnelSubscriber->next_execution_time = current_time('mysql');  // we are auto advancing the funnel
                $funnelSubscriber->save();
            } else {
                // this already advanced than our target
                // We have to check if we have to fire immediately
                if ($funnelSubscriber->next_sequence - 1 == $startSequence->sequence) {
                    // we are just make the time with current time if that had a timer for the target sequence
                    $funnelSubscriber->next_execution_time = current_time('mysql');
                    $funnelSubscriber->save();
                } else {
                    return; // It will work as it is; This funnel don't need any help
                }
            }
        }

        if ($funnelSubscriber->status == 'pending') {
            return; // We need double-optin from this user.
        }

        $funnel = $this->getFunnel($startSequence->funnel_id);

        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);

        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }

    public function recordFunnelMetric($subscriber, $sequence, $metricArgs = [])
    {
        $lookupData = [
            'funnel_id'     => $sequence->funnel_id,
            'sequence_id'   => $sequence->id,
            'subscriber_id' => $subscriber->id
        ];

        $extraData = $metricArgs ?: [];

        try {
            return FunnelMetric::firstOrCreate($lookupData, $extraData);
        } catch (\Exception $e) {
            // Race condition: another process inserted between the SELECT and INSERT
            $existing = FunnelMetric::where($lookupData)->first();

            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    public function initChildSequences($parent, $isMatched, $subscriber, $funnelSubscriberId, $funnelMetric)
    {
        $conditionType = 'no';
        if ($isMatched) {
            $conditionType = 'yes';
        }
        // find the corresponding sequence
        $sequences = FunnelSequence::where('funnel_id', $parent->funnel_id)
            ->where('parent_id', $parent->id)
            ->orderBy('sequence', 'ASC')
            ->where('condition_type', $conditionType)
            ->get();

        $waitTimes = 0;
        if (!$sequences->isEmpty()) {
            $immediateSequences = [];
            $firstSequence = $sequences[0];
            $nextSequence = false;

            foreach ($sequences as $sequence) {
                if ($sequence->c_delay == $firstSequence->c_delay) {
                    $immediateSequences[] = $sequence;
                } else {
                    if (!$nextSequence) {
                        $nextSequence = $sequence;
                    }
                    if ($sequence->c_delay < $nextSequence->c_delay) {
                        $nextSequence = $sequence;
                    }
                }
            }

            foreach ($immediateSequences as $immediateSequence) {
                $this->processSequence($subscriber, $immediateSequence, $funnelSubscriberId);
                if ($immediateSequence->action_name == 'end_this_funnel') {
                    $funnelSub = FunnelSubscriber::where('id', $funnelSubscriberId)->first();
                    if ($funnelSub) {
                        $this->completeFunnelSequence($funnelSub);
                    }
                    $this->setFunnel($immediateSequence->funnel_id);
                    return;
                }

                if ($immediateSequence->action_name == 'fluentcrm_wait_times') {
                    $waitTimes = FunnelHelper::getCurrentDelayInSeconds($immediateSequence->settings, $immediateSequence, $funnelSubscriberId);
                }
            }

            if ($nextSequence) {

                $waitDateTimes = gmdate('Y-m-d H:i:s', current_time('timestamp') + $nextSequence->delay);

                if ($waitTimes) {
                    $waitDateTimes = gmdate('Y-m-d H:i:s', current_time('timestamp') + $waitTimes);
                }

                return FunnelSubscriber::where('id', $funnelSubscriberId)
                    ->update([
                        'next_sequence'       => $nextSequence->sequence,
                        'next_sequence_id'    => $nextSequence->id,
                        'next_execution_time' => $waitDateTimes,
                        'status'              => 'active'
                    ]);
            }
        }

        $funnelSubscriber = FunnelSubscriber::where('id', $funnelSubscriberId)->first();
        if (!$funnelSubscriber) {
            return false;
        }

        $funnelSubscriber->last_sequence_id = $parent->id;
        FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $parent->id);
        $funnel = $this->getFunnel($parent->funnel_id);

        // we don't have next sequence so we have to loop back to the parent
        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);

        if ($waitTimes && $currentNextSequences = $sequencePoints->getCurrentSequences()) {
            $nextSequence = $currentNextSequences[0];
            return FunnelSubscriber::where('id', $funnelSubscriberId)
                ->update([
                    'next_sequence'       => $nextSequence->sequence,
                    'next_sequence_id'    => $nextSequence->id,
                    'next_execution_time' => gmdate('Y-m-d H:i:s', current_time('timestamp') + $waitTimes),
                    'status'              => 'active'
                ]);
        }

        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }
}
