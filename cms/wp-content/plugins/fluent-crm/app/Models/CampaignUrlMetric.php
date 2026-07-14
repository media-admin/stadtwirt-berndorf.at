<?php

namespace FluentCrm\App\Models;

use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

/**
 *  CampaignUrlMetric Model - DB Model for Email URL Metrics
 *
 *  Database Model
 *
 * @package FluentCrm\App\Models
 *
 * @version 1.0.0
 */
class CampaignUrlMetric extends Model
{
    protected $table = 'fc_campaign_url_metrics';

    protected $guarded = ['id'];

    public function campaign()
    {
        return $this->belongsTo(__NAMESPACE__ . '\Campaign', 'campaign_id', 'id')
            ->withoutGlobalScope('type');
    }

    public function subscriber()
    {
        return $this->belongsTo(__NAMESPACE__ . '\Subscriber', 'subscriber_id', 'id');
    }

    public function url_stores()
    {
        return $this->belongsTo(__NAMESPACE__ . '\UrlStores', 'url_id', 'id');
    }

    public static function maybeInsert($data)
    {
        $query = static::where([
            'campaign_id'   => $data['campaign_id'],
            'subscriber_id' => $data['subscriber_id'],
            'type'          => $data['type']
        ])->when(!empty($data['url_id']), function ($query) use ($data) {
            return $query->where('url_id', $data['url_id']);
        });

        if ($instance = $query->first()) {
            $instance->counter += 1;
            $instance->save();
            return $instance;
        }

        return static::create($data);
    }

    public function getLinksReport($campaign)
    {
        if (is_numeric($campaign)) {
            $campaign = Campaign::withoutGlobalScopes()->find($campaign);
        }

        if (!$campaign) {
            return [];
        }

        $settings = $campaign->settings;

        $clickTracker = $settings['click_tracker'] ?? true;

        // is anonimous tracking enabled?
        if ($clickTracker === 'anonymous') {
            // get from meta
            $links = fluentcrm_get_campaign_meta($campaign->id, '_ano_url_clicks', true);
            $formattedLinks = [];
            if ($links && is_array($links)) {
                $index = 1;
                foreach ($links as $link => $count) {
                    $formattedLinks[] = [
                        'id'    => $index,
                        'url'   => esc_url_raw($link),
                        'total' => $count
                    ];
                    $index++;
                }
            }

            // sort by total desc
            usort($formattedLinks, function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            return $this->maybeTransformSmartLinks($formattedLinks);
        }

        if ($clickTracker === false) {
            return [];
        }

        $stats = static::select(
            fluentCrmDb()->raw('count(*) as total'),
            'fc_url_stores.url',
            'fc_url_stores.id'
        )
            ->where('fc_campaign_url_metrics.campaign_id', $campaign->id)
            ->where('fc_campaign_url_metrics.type', 'click')
            ->groupBy('fc_campaign_url_metrics.url_id')
            ->join('fc_url_stores', 'fc_url_stores.id', '=', 'fc_campaign_url_metrics.url_id')
            ->orderBy('total', 'DESC')
            ->get()->toArray();

        $formatedLinks = [];

        foreach ($stats as $stat) {
            $url = str_replace(['&amp;'], ['&'], $stat['url']);
            $url = esc_url_raw($url);

            if (isset($formatedLinks[$url])) {
                $formatedLinks[$url]['total'] += $stat['total'];
                continue;
            }

            $formatedLinks[$url] = [
                'id'    => $stat['id'],
                'url'   => $url,
                'total' => $stat['total']
            ];
        }

        $sortedLinks = array_values($formatedLinks);
        usort($sortedLinks, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return $this->maybeTransformSmartLinks($sortedLinks);
    }

    public function getCampaignAnalytics($campaign)
    {
        if (is_numeric($campaign)) {
            $campaign = Campaign::withoutGlobalScopes()->find($campaign);
        }

        if (!$campaign) {
            return [];
        }

        $unsubscribeCount = CampaignUrlMetric::where('campaign_id', $campaign->id)
            ->where('type', 'unsubscribe')
            ->distinct()
            ->count('subscriber_id');

        $formattedStatus = [];
        if ($campaign->getOpenTrackingStatus(false) === 'anonymous') {
            $openCount = fluentcrm_get_campaign_meta($campaign->id, '_ano_open_count', true);
            if (!$openCount) {
                $openCount = 0;
            }
        } else {
            $openCount = fluentCrmDb()->table('fc_campaign_emails')
                ->where('campaign_id', $campaign->id)
                ->where(function ($q) {
                    $q->where('is_open', 1)
                        ->orWhereNotNull('click_counter');
                })
                ->count();
        }

        if ($campaign->getClickTrackingStatus(false) === 'anonymous') {
            $clicks = fluentcrm_get_campaign_meta($campaign->id, '_ano_url_clicks', true);
            $clickCount = 0;
            if ($clicks && is_array($clicks)) {
                $clickCount = array_sum($clicks);
            }
        } else {
            $clickCount = fluentCrmDb()->table('fc_campaign_emails')
                ->where('campaign_id', $campaign->id)
                ->whereNotNull('click_counter')
                ->count();
        }

        if ($openCount) {
            $formattedStatus['open'] = [
                'total'      => $openCount,
                /* translators: %d: number of opens */
                'label'      => sprintf(__('Open Rate (%d)', 'fluent-crm'), $openCount),
                'type'       => 'open',
                'is_percent' => true,
                'icon_class' => 'dashicons dashicons-buddicons-pm'
            ];
        }

        if ($clickCount) {
            $formattedStatus['click'] = [
                'total'      => $clickCount,
                /* translators: %d: number of clicks */
                'label'      => sprintf(__('Click Rate (%d)', 'fluent-crm'), $clickCount),
                'type'       => 'click',
                'is_percent' => true,
                'icon_class' => 'el-icon el-icon-position'
            ];
        }

        if ($openCount && $clickCount) {
            $formattedStatus['ctor'] = [
                'total'      => number_format(($clickCount / $openCount) * 100, 2) . '%',
                'label'      => __('Click To Open Rate', 'fluent-crm'),
                'type'       => 'ctor',
                'icon_class' => 'el-icon el-icon-chat-dot-square'
            ];
        }

        if ($unsubscribeCount) {
            $formattedStatus['unsubscribe'] = [
                'total'      => $unsubscribeCount,
                /* translators: %d: number of unsubscribes */
                'label'      => sprintf(__('Unsubscribe (%d)', 'fluent-crm'), $unsubscribeCount),
                'type'       => 'unsubscribe',
                'is_percent' => true,
                'icon_class' => 'el-icon el-icon-warning-outline'
            ];
        }

        $revenue = fluentcrm_get_campaign_meta($campaign->id, '_campaign_revenue');

        if ($revenue && $revenue->value) {
            $data = (array)$revenue->value;
            foreach ($data as $currency => $cents) {
                if ($cents && $currency !== 'orderIds') {
                    $formattedStatus['revenue'] = [
                        'label'      => __('Revenue', 'fluent-crm') . ' (' . $currency . ')',
                        'type'       => 'revenue',
                        'total'      => number_format($cents / 100, 2),
                        'icon_class' => 'el-icon el-icon-money'
                    ];
                }
            }
        }

        return $formattedStatus;
    }

    public function getSubjectStats($campaign)
    {
        $subjects = $campaign->subjects()->get();

        if ($subjects->isEmpty()) {
            return [];
        }

        $subjectCounts = (new CampaignEmail)->getSubjectCount($campaign->id);

        $totalClicks = 0;
        $totalOpens = 0;

        foreach ($subjectCounts as $subjectCount) {
            $metric = $this->getSubjectMetric(
                $subjectCount->email_subject_id, $campaign->id
            );
            $totalClicks += $metric['total_clicks'];
            $totalOpens += $metric['total_opens'];
            $subjectCount->metric = $metric;
        }

        return [
            'subjects'     => $subjectCounts,
            'total_clicks' => $totalClicks,
            'total_opens'  => $totalOpens
        ];
    }

    private function getSubjectMetric($subjectId, $campaignId)
    {
        $clickMetrics = $this->getClickMetrics($campaignId, $subjectId);

        $openCount = (new CampaignEmail)->getOpenCount($subjectId);

        $clickTotal = array_sum($clickMetrics->pluck('total')->toArray());

        return [
            'clicks'       => $clickMetrics,
            'total_clicks' => $clickTotal,
            'total_opens'  => $openCount
        ];
    }

    public function getClickMetrics($campaignId, $subjectId)
    {
        return static::select(
            fluentCrmDb()->raw('count(*) as total'),
            'fc_url_stores.url'
        )
            ->where('fc_campaign_url_metrics.campaign_id', $campaignId)
            ->where('fc_campaign_url_metrics.type', 'click')
            ->where('fc_campaign_emails.email_subject_id', $subjectId)
            ->groupBy('fc_campaign_url_metrics.url_id')
            ->join('fc_url_stores', 'fc_url_stores.id', '=', 'fc_campaign_url_metrics.url_id')
            ->join('fc_campaign_emails', 'fc_campaign_emails.subscriber_id', '=', 'fc_campaign_url_metrics.subscriber_id')
            ->orderBy('total', 'DESC')
            ->get();
    }

    private function maybeTransformSmartLinks($links)
    {
        if (!apply_filters('fluent_crm/has_smartlink', false)) {
            return $links;
        }

        foreach ($links as $index => $link) {
            $url = $link['url'];
            if (strpos($url, 'route=smart_url&slug=') !== false) {
                // this is a smart-link
                $smartLink = apply_filters('fluent_crm/smartlink_by_short_url', null, $url);
                if ($smartLink) {
                    $links[$index]['destination'] = $smartLink->target_url;
                    $links[$index]['title'] = $smartLink->title;
                }
            }
        }

        return $links;
    }
}
