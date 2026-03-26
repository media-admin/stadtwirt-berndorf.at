<?php

namespace FluentCampaign\App\Services\Integrations\CRM;

use FluentCampaign\App\Services\BaseAdvancedReport;
use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Template;
use FluentCrm\App\Services\ReportingHelperTrait;

class AdvancedReport extends BaseAdvancedReport
{

    public function __construct()
    {
        $this->provider = 'crm';
    }

    /**
     * @param array $filters
     * @return array
     */
    public function getReports($filters = [])
    {
        $statsProvider = new \FluentCrm\App\Services\Stats();
        $widgets = $statsProvider->getCounts();
        foreach ($widgets as $widgetKey => $widget) {
            $widgets[$widgetKey]['label'] = $widget['title'];
            $widgets[$widgetKey]['value'] = $widget['count'];
            unset($widgets[$widgetKey]['title']);
            unset($widgets[$widgetKey]['count']);
        }

        return [
            'enabled' => true,
            'title'   => __('CRM - Advanced Reports', 'fluentcampaign-pro'),
            'widgets' => $widgets,
            'supports' => [
                'product_growth'   => [
                    'title'       => __('Contact Growth', 'fluentcampaign-pro'),
                    'sub_types'   => [
                        [
                            'value' => 'all',
                            'label' => __('Overall', 'fluentcampaign-pro')
                        ],
                        [
                            'value' => 'tag',
                            'label' => __('Tag Wise', 'fluentcampaign-pro')
                        ],
                        [
                            'value' => 'list',
                            'label' => __('List Wise', 'fluentcampaign-pro')
                        ],
                    ],
                    'tags' => $this->getFormattedTags(),
                    'lists' => $this->getFormattedLists()
                ],
                'email_stats' => [
                    'title' => __('Email Sending Stats', 'fluentcampaign-pro')
                ],
                'clicks_stats' => [
                    'title' => __('Link Clicks Stats', 'fluentcampaign-pro')
                ],
                'unsubscribe_stats' => [
                    'title' => __('Unsubscribe Stats', 'fluentcampaign-pro')
                ]
            ]
        ];
    }


    /**
     * @param string $type
     * @param array $filters
     * @return array|void
     */
    public function getReport($type, $filters = [])
    {
        return $this->getStats($filters, $type);
    }

    /**
     * @param array $filters
     * @param string $type
     * @return array
     */
    public function getStats($filters, $type)
    {

        $table = 'fc_subscribers';
        $column = 'created_at';
        $pivotData = [];

        if($type == 'email_stats') {
            $table = 'fc_campaign_emails';
            $column = 'scheduled_at';
        } else if($type == 'clicks_stats') {
            $table = 'fc_campaign_url_metrics';
        } else if($type == 'unsubscribe_stats') {
            $table = 'fc_subscriber_meta';
        }

        $params = $this->getReportParams($filters);

        if(in_array($filters['sub_type'], ['tag', 'list'])) {
            $table = 'fc_subscriber_pivot';
            if($filters['sub_type'] == 'tag') {
                $pivotData['object_type'] = 'FluentCrm\App\Models\Tag';
            } else {
                $pivotData['object_type'] = 'FluentCrm\App\Models\Lists';
            }
            $pivotData['object_id'] = $filters['sub_type_value'];
        }

        $compareItems = false;
        if ($params['compare_range']) {
            $compareItems = $this->getStat($params['compare_range'], $table, $column, $pivotData);
        }

        $currentItems = $this->getStat($params['current_range'], $table, $column, $pivotData);

        $dataSets = [
            $this->formatDataSet($currentItems, __('Current Range', 'fluentcampaign-pro'), $params['current_range'])
        ];

        if($compareItems) {
            $dataSets[] = $this->formatDataSet($compareItems, __('Compare Range', 'fluentcampaign-pro'), $params['compare_range'], 'compare');
        }

        return [
            'data_sets' => $dataSets,
            'compare_range' => $params['compare_range'],
            'current_range' => [
                $params['current_range'][0]->format('Y-m-d'),
                $params['current_range'][1]->format('Y-m-d')
            ]
        ];

    }

    /**
     * @param $range
     * @return array
     */
    protected function getStat($range, $table, $column = 'created_at', $pivotData = null)
    {
        $frequency = $this->getFrequency($range[0], $range[1]);

        $selects = [
            fluentCrmDb()->raw('COUNT(*) as count'),
            fluentCrmDb()->raw('DATE('.$column.') as date'),
        ];

        $format = 'Y-m-d';
        if ($frequency == self::$monthly) {
            $selects[] = fluentCrmDb()->raw('YEAR('.$column.') AS year');
            $selects[] = fluentCrmDb()->raw('MONTH('.$column.') AS month');
            $format = 'M Y';
        }

        $query = fluentCrmDb()->table($table)
            ->select($selects)
            ->whereBetween('created_at', [$range[0]->format('Y-m-d 00:00:01'), $range[1]->format('Y-m-d 23:59:59')])
            ->orderBy('date', 'ASC');

        if($table == 'fc_campaign_emails') {
            $query->where('status', 'sent');
        } else if($table == 'fc_campaign_url_metrics') {
            $query->where('type', 'click');
        } else if($table == 'fc_subscriber_meta') {
            $query->where('key', 'unsubscribe_reason');
        } else if($table == 'fc_subscriber_pivot' && $pivotData) {
            $query->where('object_type', $pivotData['object_type']);
            $query->where('object_id', $pivotData['object_id']);
        }

        if ($frequency == self::$monthly) {
            $query->groupBy('year', 'month');
        } else {
            $query->groupBy('date');
        }

        $items = $query->get();

        $period = $this->makeDatePeriod(
            $range[0],
            $range[1],
            $frequency
        );

        $periodRange = $this->getDateRangeArray($period);

        foreach ($items as $item) {
            $periodRange[date($format, strtotime($item->date))] = $item->count;
        }

        return $periodRange;
    }

    private function getFormattedTags()
    {
        $allTags = Tag::get();
        $formattedTags = [];
        foreach ($allTags as $tag) {
            $formattedTags[] = [
                'id'    => strval($tag->id),
                'title' => $tag->title,
                'slug'  => $tag->slug
            ];
        }

        return $formattedTags;
    }

    private function getFormattedLists()
    {
        $allLists = Lists::get();
        $formattedLists = [];
        foreach ($allLists as $list) {
            $formattedLists[] = [
                'id'    => strval($list->id),
                'title' => $list->title,
                'slug'  => $list->slug
            ];
        }

        return $formattedLists;
    }

}
