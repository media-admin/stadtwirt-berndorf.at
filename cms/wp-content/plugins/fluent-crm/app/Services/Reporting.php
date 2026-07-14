<?php

namespace FluentCrm\App\Services;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Support\Collection;

class Reporting
{
    use ReportingHelperTrait;

    public function getSubscribersGrowth($from = false, $to = false, $tagId = 0, $listId = 0)
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = fluentCrmDb()->table('fc_subscribers')
            ->select($this->prepareSelect($frequency))
            ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('status', 'subscribed');

        if ($tagId) {
            $query->whereIn('id', function ($q) use ($tagId) {
                $q->select('subscriber_id')
                    ->from('fc_subscriber_pivot')
                    ->where('object_id', $tagId)
                    ->where('object_type', 'FluentCrm\App\Models\Tag');
            });
        }

        if ($listId) {
            $query->whereIn('id', function ($q) use ($listId) {
                $q->select('subscriber_id')
                    ->from('fc_subscriber_pivot')
                    ->where('object_id', $listId)
                    ->where('object_type', 'FluentCrm\App\Models\Lists');
            });
        }

        $items = $query->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC')
            ->get();

        return $this->getResult($period, $items);
    }

    public function getEmailOpenStats($from = false, $to = false, $campaignId = 0)
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = fluentCrmDb()->table('fc_campaign_emails')
            ->select($this->prepareSelect($frequency, 'updated_at'))
            ->whereBetween('updated_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('is_open', 1);

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $items = $query->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC')
            ->get();

        return $this->getResult($period, $items);
    }

    public function getEmailClickStats($from = false, $to = false, $campaignId = 0)
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = fluentCrmDb()->table('fc_campaign_url_metrics')
            ->select($this->prepareSelect($frequency))
            ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('type', 'click');

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $items = $query->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC')
            ->get();

        return $this->getResult($period, $items);
    }

    public function getEmailStats($from = false, $to = false, $status = 'sent', $campaignId = 0)
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $query = fluentCrmDb()->table('fc_campaign_emails')
            ->select($this->prepareSelect($frequency, 'scheduled_at'))
            ->whereBetween('scheduled_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('status', $status);

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $items = $query->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC')
            ->get();

        return $this->getResult($period, $items);
    }

    public function getUnsubscribeStats($from = false, $to = false)
    {
        $period = $this->makeDatePeriod(
            $from = $this->makeFromDate($from),
            $to = $this->makeToDate($to),
            $frequency = $this->getFrequency($from, $to)
        );

        list($groupBy, $orderBy) = $this->getGroupAndOrder($frequency);

        $items = fluentCrmDb()->table('fc_subscriber_meta')
            ->select($this->prepareSelect($frequency))
            ->whereBetween('created_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('key', 'unsubscribe_reason')
            ->groupBy($groupBy)
            ->orderBy($orderBy, 'ASC')
            ->get();

        return $this->getResult($period, $items);
    }

    public function funnelStat($funnelId, $sequences = [], $from = false, $to = false)
    {
        if (!$sequences) {
            $sequences = FunnelSequence::where('funnel_id', $funnelId)
                ->orderBy('sequence', 'ASC')
                ->get();
        }

        if (!$sequences || $sequences->isEmpty()) {
            return [];
        }

        $sequenceIds = $sequences->pluck('id')->toArray();

        $totalSubscriberCount = FunnelSubscriber::where('funnel_id', $funnelId)
            ->distinct()
            ->count('subscriber_id');

        $items = FunnelMetric::select([
            'sequence_id',
            'benchmark_currency',
            'benchmark_value',
            fluentCrmDb()->raw('COUNT(sequence_id) AS count'),
        ])
            ->groupBy('sequence_id')
            ->whereIn('sequence_id', $sequenceIds)
            ->get()->keyBy('sequence_id');

        $totalRevenue = FunnelMetric::select([
            fluentCrmDb()->raw('SUM(benchmark_value) AS benchmark_total'),
        ])->whereIn('sequence_id', $sequenceIds)->first();

        if ($totalRevenue && $totalRevenue->benchmark_total) {
            $totalRevenue = $totalRevenue->benchmark_total / 100;
        } else {
            $totalRevenue = 0;
        }

        $formattedReports = [
            [
                'label'               => __('Entrance', 'fluent-crm'),
                'count'               => $totalSubscriberCount,
                'sequence_id'         => 0,
                'type'                => 'root',
                'percent'             => 100,
                'percent_text'        => 100,
                'previous_step_count' => $totalSubscriberCount,
                'drop_count'          => 0,
                'drop_percent'        => 0
            ]
        ];

        $currency = 'USD';
        $prevCount = $totalSubscriberCount;
        foreach ($sequences as $sequence) {
            if (empty($items[$sequence->id])) {
                continue;
            }

            $count = ($items[$sequence->id]->count) ? $items[$sequence->id]->count : 0;
            $dropCount = $prevCount - $count;
            $percent = ($totalSubscriberCount) ? ceil(($count / $totalSubscriberCount) * 100) : 0;

            $report = [
                'label'               => $sequence->title,
                'count'               => intval($count),
                'sequence_id'         => $sequence->id,
                'type'                => $sequence->type,
                'percent'             => $percent > 100 ? 100 : $percent,
                'percent_text'        => $percent,
                'previous_step_count' => $prevCount,
                'drop_count'          => $dropCount,
                'drop_percent'        => ($dropCount && $count && $prevCount) ? floor((1 - ($count / $prevCount)) * 100) : 0
            ];

            if ($sequence->action_name == 'send_custom_email') {
                // Calculate the revenue of this campaign
                $refCampaign = Arr::get($sequence->settings, 'reference_campaign');
                if ($refCampaign) {
                    if ($revenue = fluentcrm_get_campaign_meta($refCampaign, '_campaign_revenue')) {
                        $revs = [];
                        foreach ($revenue->value as $currency => $cents) {
                            if (is_numeric($cents) && $cents && $currency !== 'orderIds') {
                                $money = $cents / 100;
                                $money = number_format($money, (is_int($money)) ? 0 : 2);
                                $revs[] = strtoupper($currency) . ' ' . $money;
                            }
                        }
                        if ($revs) {
                            $report['revenues'] = $revs;
                        }
                    }

                    $report['link_clicks'] = CampaignUrlMetric::where('campaign_id', $refCampaign)
                        ->where('type', 'click')
                        ->count();

                    $report['email_opens'] = CampaignEmail::where('campaign_id', $refCampaign)
                        ->where('is_open', 1)
                        ->count();
                }

            }

            $formattedReports[] = $report;

            if ($items[$sequence->id]->benchmark_value > 0) {
                $currency = $items[$sequence->id]->benchmark_currency;
            }

        }

        return [
            'metrics'                 => $formattedReports,
            'total_revenue'           => $totalRevenue,
            'total_revenue_formatted' => number_format($totalRevenue, 2, '.', ' '),
            'revenue_currency'        => $currency
        ];
    }

    public function getEmailPerformance($from = null, $to = null)
    {
        $from = $this->makeFromDate($from);
        $to = $this->makeToDate($to);

        $sent = fluentCrmDb()->table('fc_campaign_emails')
            ->where('status', 'sent')
            ->whereBetween('scheduled_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->count();

        $bounced = fluentCrmDb()->table('fc_campaign_emails')
            ->where('status', 'bounced')
            ->whereBetween('scheduled_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->count();

        $delivered = max(0, $sent - $bounced);

        $opened = fluentCrmDb()->table('fc_campaign_emails')
            ->where('status', 'sent')
            ->whereBetween('scheduled_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->where('is_open', 1)
            ->count();

        $clicked = fluentCrmDb()->table('fc_campaign_emails')
            ->where('status', 'sent')
            ->whereBetween('scheduled_at', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->whereNotNull('click_counter')
            ->where('click_counter', '>', 0)
            ->count();

        $deliveryBase = $sent + $bounced;
        $rateBase = $delivered;

        $deliveryPercent = $deliveryBase ? round(($delivered / $deliveryBase) * 100, 2) : 0;
        $bouncePercent = $deliveryBase ? round(($bounced / $deliveryBase) * 100, 2) : 0;
        $openPercent = $rateBase ? round(($opened / $rateBase) * 100, 2) : 0;
        $clickPercent = $rateBase ? round(($clicked / $rateBase) * 100, 2) : 0;

        return [
            'totals'      => [
                'sent'      => (int)$sent,
                'delivered' => (int)$delivered,
                'opened'    => (int)$opened,
                'clicked'   => (int)$clicked,
                'bounced'   => (int)$bounced,
            ],
            'percentages' => [
                'delivered' => $deliveryPercent,
                'opened'    => $openPercent,
                'clicked'   => $clickPercent,
                'bounced'   => $bouncePercent,
            ]
        ];
    }
}
