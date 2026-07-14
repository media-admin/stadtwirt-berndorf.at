<?php

namespace FluentCrm\App\Services\Funnel;

use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\Framework\Support\Arr;

class SequencePoints
{
    private $nextSequence = null;

    private $immediateSequences = [];

    private $lastSequence = null;

    private $requiredBenchMark = null;

    private $funnel;

    private $hasNext = null;

    private $funnelSubscriber;

    private $nextSequenceExecutionTime = false;

    public $hasEndSequence = false;

    public function __construct($funnel, $funnelSubscriber = false)
    {
        $this->funnel = $funnel;
        $this->funnelSubscriber = $funnelSubscriber;
        $this->setupData();
    }

    private function setupData()
    {
        $this->resolveLastSequence();

        $isInChild = false;
        $sequences = $this->queryRemainingSequences($isInChild);

        if (!$sequences || $sequences->isEmpty()) {
            return;
        }

        $inWaitTimes = false;
        $this->classifySequences($sequences, $isInChild, $inWaitTimes);

        // If we're inside a child block and exhausted all child sequences,
        // transition back to the parent-level sequence flow
        if ($isInChild && !$this->nextSequence && !$this->hasEndSequence) {
            $this->handleChildToParentTransition($inWaitTimes);
        }

        if ($this->nextSequence && $this->nextSequenceExecutionTime) {
            $this->nextSequence->execution_date_time = $this->nextSequenceExecutionTime;
        }
    }

    private function resolveLastSequence()
    {
        if ($this->funnelSubscriber && $this->funnelSubscriber->last_sequence_id) {
            $this->lastSequence = FunnelSequence::where('id', $this->funnelSubscriber->last_sequence_id)->first();
        }
    }

    private function queryRemainingSequences(&$isInChild)
    {
        if ($this->lastSequence) {
            return $this->queryFromLastSequence($isInChild);
        }

        return $this->queryFromStart();
    }

    private function queryFromLastSequence(&$isInChild)
    {
        $query = FunnelSequence::orderBy('sequence', 'ASC')
            ->where('funnel_id', $this->funnel->id)
            ->where('sequence', '>', $this->lastSequence->sequence);

        if ($this->lastSequence->parent_id) {
            $isInChild = true;
            $query->where('parent_id', $this->lastSequence->parent_id)
                ->where('condition_type', $this->lastSequence->condition_type);
        }

        $sequences = $query->get();

        // If inside a child block but no more child sequences, escape to parent level
        if ($isInChild && $sequences->isEmpty()) {
            $isInChild = false;
            $sequences = $this->queryParentEscapeSequences();
        }

        return $sequences;
    }

    private function queryParentEscapeSequences()
    {
        $nextSequenceNumber = $this->funnelSubscriber->next_sequence;

        if ($this->funnelSubscriber->next_sequence_item) {
            $nextSequenceNumber = $this->funnelSubscriber->next_sequence_item->sequence;
        }

        if (!$nextSequenceNumber) {
            return collect();
        }

        return FunnelSequence::orderBy('sequence', 'ASC')
            ->where('funnel_id', $this->funnel->id)
            ->where('sequence', '>=', $nextSequenceNumber)
            ->where(function ($q) {
                $q->whereNull('parent_id')
                    ->orWhere('parent_id', '0');
            })
            ->get();
    }

    private function queryFromStart()
    {
        $nextSequenceNumber = $this->funnelSubscriber ? $this->funnelSubscriber->next_sequence : null;

        return FunnelSequence::orderBy('sequence', 'ASC')
            ->where('funnel_id', $this->funnel->id)
            ->when($nextSequenceNumber, function ($q) use ($nextSequenceNumber) {
                $q->where('sequence', '>=', $nextSequenceNumber);
            })
            ->get();
    }

    /**
     * Single-pass classification of sequences into immediate, next, benchmark, and end categories.
     *
     * @param \Illuminate\Support\Collection $sequences
     * @param bool $isInChild Whether we're inside a conditional child block
     * @param bool &$inWaitTimes Tracks whether a wait-time action has been encountered
     */
    private function classifySequences($sequences, $isInChild, &$inWaitTimes)
    {
        $firstSequence = $sequences[0];
        $conditionalBlock = false;
        $hasEndSequence = false;
        $immediateSequences = [];

        foreach ($sequences as $sequence) {
            if ($this->requiredBenchMark || $conditionalBlock || $hasEndSequence) {
                continue;
            }

            // Skip orphaned child sequences when processing at parent level
            if (!$isInChild && $sequence->parent_id) {
                continue;
            }

            if ($sequence->action_name == 'fluentcrm_wait_times' && !$inWaitTimes) {
                $inWaitTimes = true;
                $funnelSubId = $this->funnelSubscriber ? $this->funnelSubscriber->id : null;
                $seconds = FunnelHelper::getCurrentDelayInSeconds($sequence->settings, $sequence, $funnelSubId);
                $this->nextSequenceExecutionTime = gmdate('Y-m-d H:i:s', current_time('timestamp') + $seconds);
            }

            if ($sequence->type == 'benchmark') {
                if (Arr::get($sequence->settings, 'type') == 'required') {
                    if (!$this->funnelSubscriber || !apply_filters('fluent_crm/benchmark_already_asserted_' . $sequence->action_name, false, $sequence, $this->funnelSubscriber)) {
                        $this->requiredBenchMark = $sequence;
                    }
                }
                continue;
            }

            if ($sequence->type == 'conditional') {
                $conditionalBlock = $sequence;
            }

            if ($sequence->c_delay == $firstSequence->c_delay) {
                $immediateSequences[] = $sequence;
                if ($sequence->action_name == 'end_this_funnel') {
                    $hasEndSequence = true;
                }
            } else {
                if (!$this->nextSequence || $sequence->c_delay < $this->nextSequence->c_delay) {
                    $this->hasNext = true;
                    $this->nextSequence = $sequence;
                }
            }
        }

        if ($conditionalBlock) {
            $this->hasNext = true;
        }

        $this->immediateSequences = array_merge($this->immediateSequences, $immediateSequences);
        $this->hasEndSequence = $hasEndSequence;
    }

    /**
     * When a child block is exhausted, find the next top-level sequences after the parent conditional.
     */
    private function handleChildToParentTransition($inWaitTimes)
    {
        if (!$this->lastSequence) {
            return;
        }

        $parentSequence = FunnelSequence::where('id', $this->lastSequence->parent_id)->first();

        if (!$parentSequence) {
            return;
        }

        $sequences = FunnelSequence::where('funnel_id', $this->funnel->id)
            ->where('sequence', '>', $parentSequence->sequence)
            ->where(function ($q) {
                $q->whereNull('parent_id')
                    ->orWhere('parent_id', '0');
            })
            ->orderBy('sequence', 'ASC')
            ->get();

        if ($sequences->isEmpty()) {
            return;
        }

        // If a wait time was already applied inside the child block,
        // schedule the first parent sequence with that delay
        if ($inWaitTimes) {
            $this->hasNext = true;
            $this->nextSequence = $sequences[0];
            if ($this->nextSequenceExecutionTime) {
                $this->nextSequence->execution_date_time = $this->nextSequenceExecutionTime;
            }
            return;
        }

        // Classify the parent-level sequences using the same logic
        $this->classifySequences($sequences, false, $inWaitTimes);
    }

    public function getCurrentSequences()
    {
        return $this->immediateSequences;
    }

    public function getNextSequence()
    {
        return $this->nextSequence;
    }

    public function hasNext()
    {
        return $this->hasNext || !!$this->nextSequence;
    }

    public function getRequiredBenchmark()
    {
        return $this->requiredBenchMark;
    }

    public function hasSequences()
    {
        return !!$this->requiredBenchMark || !!$this->immediateSequences;
    }
}
