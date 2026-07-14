<?php

namespace FluentCrm\App\Services\Funnel\Benchmarks;

use FluentCrm\App\Services\Funnel\BaseBenchMark;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class RemoveFromListBenchmark extends BaseBenchMark
{
    public function __construct()
    {
        $this->triggerName = 'fluentcrm_contact_removed_from_lists';
        $this->actionArgNum = 2;
        $this->priority = 40;

        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'       => __('List Removed', 'fluent-crm'),
            'description' => __('This will run when selected lists have been removed from a contact', 'fluent-crm'),
            'icon'        => 'fc-icon-list_removed',//fluentCrmMix('images/funnel_icons/list_removed.svg'),
            'settings'    => [
                'lists'       => [],
                'select_type' => 'any',
                'type'        => 'optional',
                'can_enter'   => 'yes'
            ]
        ];
    }

    public function getDefaultSettings()
    {
        return [
            'lists'       => [],
            'select_type' => 'any',
            'type'        => 'optional',
            'can_enter'   => 'yes'
        ];
    }

    public function getBlockFields($funnel)
    {
        return [
            'title'     => __('List Removed From Contact', 'fluent-crm'),
            'sub_title' => __('This will run when selected lists have been removed from a contact', 'fluent-crm'),
            'fields'    => [
                'lists'       => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'lists',
                    'is_multiple' => true,
                    'label'       => __('Select Lists', 'fluent-crm'),
                    'placeholder' => __('Select List', 'fluent-crm'),
                    'creatable' => true
                ],
                'select_type' => [
                    'label'      => __('Run When', 'fluent-crm'),
                    'type'       => 'radio',
                    'options'    => [
                        [
                            'id'    => 'any',
                            'title' => __('contact removed from any of the selected Lists', 'fluent-crm')
                        ],
                        [
                            'id'    => 'all',
                            'title' => __('contact removed from all of the selected lists', 'fluent-crm')
                        ]
                    ],
                    'dependency' => [
                        'depends_on' => 'lists',
                        'operator'   => '!=',
                        'value'      => []
                    ]
                ],
                'type'        => $this->benchmarkTypeField(),
                'can_enter'   => $this->canEnterField()
            ]
        ];
    }

    public function handle($benchMark, $originalArgs)
    {
        $listIds = $originalArgs[0];
        $subscriber = $originalArgs[1];
        $settings = $benchMark->settings;

        if (!$this->isListMatched($listIds, $subscriber, $settings)) {
            return; // not matched based on condition
        }

        $funnelProcessor = new FunnelProcessor();
        $funnelProcessor->startFunnelFromSequencePoint($benchMark, $subscriber);
    }

    private function isListMatched($listIds, $subscriber, $settings)
    {
        $benchmarkLists = Arr::get($settings, 'lists', []);
        if (empty($benchmarkLists)) {
            return false;
        }

        $isMatched = array_intersect($benchmarkLists, $listIds);
        if (!$isMatched) {
            return false; // not in our scope
        }

        $matchType = Arr::get($settings, 'select_type');

        if ($matchType == 'all') {
            // Check that ALL benchmark lists have been removed (none remain on subscriber)
            $attachedListIds = $subscriber->lists->pluck('id')->toArray();
            return empty(array_intersect($benchmarkLists, $attachedListIds));
        }

        return $isMatched;
    }

    public function assertCurrentGoalState($asserted, $benchmark, $funnelSubscriber)
    {
        if (!$funnelSubscriber || !$funnelSubscriber->subscriber) {
            return $asserted;
        }

        $subscriber = $funnelSubscriber->subscriber;
        $settings = $benchmark->settings;
        $benchmarkLists = Arr::get($settings, 'lists', []);

        if (empty($benchmarkLists)) {
            return $asserted;
        }

        $attachedListIds = $subscriber->lists->pluck('id')->toArray();
        $matchType = Arr::get($settings, 'select_type');

        if ($matchType == 'all') {
            return empty(array_intersect($benchmarkLists, $attachedListIds));
        }

        // 'any' — at least one benchmark list is NOT in subscriber's lists
        $removed = array_diff($benchmarkLists, $attachedListIds);
        return !empty($removed);
    }

}
