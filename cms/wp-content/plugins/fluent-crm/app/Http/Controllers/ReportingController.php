<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Hooks\Handlers\Scheduler;
use FluentCrm\App\Services\Reporting;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\Framework\Support\Arr;

/**
 *  ReportingController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class ReportingController extends Controller
{
    public function getContactGrowth(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];
        $tagId = intval($request->get('tag_id', 0));
        $listId = intval($request->get('list_id', 0));
        $compareType = sanitize_text_field($request->get('compare_type', ''));
        $compareRange = $request->get('compare_range', []);

        $currentStats = $reporting->getSubscribersGrowth($from, $to, $tagId, $listId);

        // Derive defaults from the SITE-LOCAL clock — created_at is written in site
        // time, so UTC-based day boundaries shift the window near the date line.
        $currentFrom = $from ?: gmdate('Y-m-d', current_time('timestamp') - 30 * DAY_IN_SECONDS);
        $currentTo = $to ?: gmdate('Y-m-d', current_time('timestamp') + DAY_IN_SECONDS);

        $dataSets = [
            [
                'label'           => __('Current Range', 'fluent-crm'),
                'data'            => $currentStats,
                'range'           => [$currentFrom, $currentTo],
                'backgroundColor' => '#335CFF',
                'borderColor'     => '#335CFF',
                'fill'            => true,
            ],
        ];

        if ($compareType && $compareType !== 'no_comparison') {
            $compRange = $this->resolveCompareRange($compareType, $compareRange, $currentFrom, $currentTo);
            if ($compRange) {
                $compareStats = $reporting->getSubscribersGrowth($compRange[0], $compRange[1], $tagId, $listId);
                $dataSets[] = [
                    'label'           => __('Compare Range', 'fluent-crm'),
                    'data'            => $compareStats,
                    'range'           => $compRange,
                    'backgroundColor' => '#1FC16B',
                    'borderColor'     => '#1FC16B',
                    'fill'            => true,
                ];
            }
        }

        return $this->sendSuccess([
            'data_sets'     => $dataSets,
            'current_range' => [$currentFrom, $currentTo],
        ]);
    }

    /**
     * Calculate comparison date range based on type.
     *
     * @param string $type
     * @param array $compareRange
     * @param string $from
     * @param string $to
     * @return array|false
     */
    private function resolveCompareRange($type, $compareRange, $from, $to)
    {
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        $diffDays = (int)(($toTs - $fromTs) / 86400);

        switch ($type) {
            case 'previous_period':
                return [
                    gmdate('Y-m-d', $fromTs - ($diffDays + 1) * 86400),
                    gmdate('Y-m-d', $fromTs - 86400),
                ];
            case 'previous_month':
                $newFrom = gmdate('Y-m-d', strtotime($from . ' -1 month'));
                return [$newFrom, gmdate('Y-m-d', strtotime($newFrom) + $diffDays * 86400)];
            case 'previous_quarter':
                $newFrom = gmdate('Y-m-d', strtotime($from . ' -3 months'));
                return [$newFrom, gmdate('Y-m-d', strtotime($newFrom) + $diffDays * 86400)];
            case 'previous_year':
                $newFrom = gmdate('Y-m-d', strtotime($from . ' -12 months'));
                return [$newFrom, gmdate('Y-m-d', strtotime($newFrom) + $diffDays * 86400)];
            case 'custom':
                if (is_array($compareRange) && count(array_filter($compareRange)) >= 2) {
                    return [
                        sanitize_text_field($compareRange[0]),
                        sanitize_text_field($compareRange[1]),
                    ];
                }
                return false;
            default:
                return false;
        }
    }

    public function getEmailSentStats(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];
        $campaignId = intval($request->get('campaign_id', 0));
        return $this->sendSuccess([
            'stats' => $reporting->getEmailStats($from, $to, 'sent', $campaignId)
        ]);
    }

    public function getEmailOpenStats(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];
        $campaignId = intval($request->get('campaign_id', 0));
        return $this->sendSuccess([
            'stats' => $reporting->getEmailOpenStats($from, $to, $campaignId)
        ]);
    }

    public function getEmailClickStats(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];
        $campaignId = intval($request->get('campaign_id', 0));
        return $this->sendSuccess([
            'stats' => $reporting->getEmailClickStats($from, $to, $campaignId)
        ]);
    }

    public function getEmailUnsubStats(Request $request, Reporting $reporting)
    {
        list($from, $to) = $request->get('date_range') ?: ['', ''];
        return $this->sendSuccess([
            'stats' => $reporting->getUnsubscribeStats($from, $to)
        ]);
    }

    public function getEmailPerformance(Request $request, Reporting $reporting)
    {
        $dateRange = Arr::get($request->all(), 'date_range', []);

        if (!empty($dateRange[0]) && !empty($dateRange[1])) {
            $from = sanitize_text_field($dateRange[0]);
            $to = sanitize_text_field($dateRange[1]);
        } else {
            $days = intval(Arr::get($request->all(), 'days'));

            if ($days > 0) {
                $from = '-' . $days . ' days';
            } elseif ($request->exists('days')) {
                // days=0 means "All Time"
                $from = '2000-01-01';
            } else {
                $from = null; // default: -30 days
            }

            $to = null;
        }

        return $this->sendSuccess([
            'stats' => $reporting->getEmailPerformance($from, $to)
        ]);
    }

    public function getEmails(Request $request)
    {
        $status = sanitize_text_field($request->get('status', ''));
        $search = sanitize_text_field($request->get('search', ''));
        $selectedTypes = array_values(array_filter(array_map('sanitize_text_field', (array)$request->get('types', []))));
        $types = CampaignEmail::expandEmailTypes($selectedTypes);

        // With no WHERE clause an ORDER BY scheduled_at cannot use the
        // (status, scheduled_at) composite index and filesorts the whole table.
        // id DESC gives the same recency ordering served by the primary key;
        // with a status filter the composite index serves scheduled_at directly.
        $emailsQuery = CampaignEmail::with('subscriber', 'campaign');

        if ($status) {
            $emailsQuery->where('status', $status)->orderBy('scheduled_at', 'DESC');
        } else {
            $emailsQuery->orderBy('id', 'DESC');
        }

        $emails = $emailsQuery
            ->when($search, function ($q) use ($search) {
                return $this->applyEmailSearchFilter($q, $search);
            })
            ->when($types, function ($q) use ($types) {
                return $q->whereIn('email_type', $types);
            })
            ->paginate();

        $statuses = null;
        $emailTypes = null;

        if ($request->get('page') == 1 && !$search) {
            $statuses = CampaignEmail::select('status')
                ->selectRaw('count(id) as total')
                ->when($types, function ($q) use ($types) {
                    return $q->whereIn('email_type', $types);
                })
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->map(function ($status) {
                    return $status->total;
                });

            $typeCounts = CampaignEmail::select('email_type')
                ->selectRaw('count(id) as total')
                ->whereNotNull('email_type')
                ->when($status, function ($q) use ($status) {
                    return $q->where('status', $status);
                })
                ->groupBy('email_type')
                ->get()
                ->reduce(function ($carry, $emailType) {
                    $canonicalType = CampaignEmail::normalizeEmailType($emailType->email_type);

                    if (!isset($carry[$canonicalType])) {
                        $carry[$canonicalType] = [
                            'id'    => $canonicalType,
                            'label' => CampaignEmail::resolveEmailTypeLabel($canonicalType),
                            'count' => 0,
                        ];
                    }

                    $carry[$canonicalType]['count'] += (int)$emailType->total;

                    return $carry;
                }, []);

            $orderedTypes = [];
            foreach (array_keys(CampaignEmail::getEmailTypeLabels()) as $canonicalType) {
                if (!empty($typeCounts[$canonicalType])) {
                    $orderedTypes[] = $typeCounts[$canonicalType];
                }
            }

            $emailTypes = array_values($orderedTypes);
        }

        return [
            'emails'    => $emails,
            'statuses'  => $statuses,
            'types'     => $emailTypes
        ];
    }

    /**
     * Apply the search filter to email activity queries.
     *
     * Search is scoped to fields the table actually exposes so users can find
     * rows by subject, source campaign title, recipient email, or related
     * contact email without triggering extra broad scans on large datasets.
     *
     * @param \FluentCrm\Framework\Database\Orm\Builder $query
     * @param string $search
     * @return \FluentCrm\Framework\Database\Orm\Builder
     */
    private function applyEmailSearchFilter($query, $search)
    {
        global $wpdb;

        $escapedSearch = $wpdb->esc_like($search);
        $containsLike = '%' . $escapedSearch . '%';
        $emailLike = strpos($search, '@') !== false ? $escapedSearch . '%' : $containsLike;

        // Match source campaign titles and recipient emails via non-correlated
        // IN subqueries: MySQL materializes each once per statement — keeping
        // the perf win over the old per-row correlated whereHas at 1M+ rows —
        // without loading an unbounded ID list into PHP. fc_campaigns is
        // queried directly (not through the Campaign model) so funnel/sequence
        // email types match too; the model's global 'type' scope would drop them.
        return $query->where(function ($subQuery) use ($containsLike, $emailLike) {
            $subQuery->where('email_subject', 'LIKE', $containsLike)
                ->orWhere('email_address', 'LIKE', $emailLike)
                ->orWhereIn('campaign_id', function ($q) use ($containsLike) {
                    $q->select('id')->from('fc_campaigns')->where('title', 'LIKE', $containsLike);
                })
                ->orWhereIn('subscriber_id', function ($q) use ($emailLike) {
                    $q->select('id')->from('fc_subscribers')->where('email', 'LIKE', $emailLike);
                });
        });
    }

    public function deleteEmails(Request $request)
    {
        $emailIds = $request->get('email_ids');
        CampaignEmail::whereIn('id', $emailIds)
            ->delete();

        return [
            'message' => __('Selected emails have been deleted', 'fluent-crm')
        ];
    }

    public function getContactsByStatus()
    {
        $statuses = fluentCrmDb()->table('fc_subscribers')
            ->select(fluentCrmDb()->raw('status, COUNT(id) as count'))
            ->groupBy('status')
            ->get();

        $defaultOrder = [
            'subscribed',
            'unsubscribed',
            'pending',
            'bounced',
            'complained',
            'spammed',
            'transactional'
        ];
        $defaultStats = array_fill_keys($defaultOrder, 0);
        $total = 0;

        foreach ($statuses as $row) {
            $count = (int)$row->count;
            $status = sanitize_text_field($row->status);
            $total += $count;

            if (array_key_exists($status, $defaultStats)) {
                $defaultStats[$status] = $count;
            }
        }

        $result = [];
        foreach ($defaultOrder as $status) {
            $result[] = [
                'status' => $status,
                'count'  => $defaultStats[$status],
            ];
        }

        return $this->sendSuccess([
            'stats' => $result,
            'total' => $total,
        ]);
    }

    public function getContactsByTags(Request $request)
    {
        $limit = intval($request->get('per_page', 20));

        $tags = Tag::select(['fc_tags.id', 'fc_tags.title'])
            ->selectRaw('COUNT(subscriber_id) as contact_count')
            ->leftJoin('fc_subscriber_pivot', function ($join) {
                $join->on('fc_tags.id', '=', 'fc_subscriber_pivot.object_id')
                    ->where('fc_subscriber_pivot.object_type', '=', 'FluentCrm\App\Models\Tag');
            })
            ->groupBy('fc_tags.id', 'fc_tags.title')
            ->orderByDesc('contact_count')
            ->paginate($limit);

        return $this->sendSuccess([
            'tags' => $tags,
        ]);
    }

    public function getContactsByLists(Request $request)
    {
        $limit = intval($request->get('per_page', 20));

        $lists = Lists::select(['fc_lists.id', 'fc_lists.title'])
            ->selectRaw('COUNT(subscriber_id) as contact_count')
            ->leftJoin('fc_subscriber_pivot', function ($join) {
                $join->on('fc_lists.id', '=', 'fc_subscriber_pivot.object_id')
                    ->where('fc_subscriber_pivot.object_type', '=', 'FluentCrm\App\Models\Lists');
            })
            ->groupBy('fc_lists.id', 'fc_lists.title')
            ->orderByDesc('contact_count')
            ->paginate($limit);

        return $this->sendSuccess([
            'lists' => $lists,
        ]);
    }

    public function getContactsByCountry()
    {
        $countries = fluentCrmDb()->table('fc_subscribers')
            ->select(fluentCrmDb()->raw('UPPER(TRIM(country)) as country_code, COUNT(id) as contact_count'))
            ->whereNotNull('country')
            ->whereRaw("TRIM(country) != ''")
            ->groupBy(fluentCrmDb()->raw('UPPER(TRIM(country))'))
            ->orderByDesc('contact_count')
            ->get();

        $result = [];
        foreach ($countries as $row) {
            $result[] = [
                'country_code' => $row->country_code,
                'contact_count' => (int) $row->contact_count,
            ];
        }

        return $this->sendSuccess([
            'countries' => $result,
        ]);
    }

    public function getCampaignsList(Request $request)
    {
        $limit = intval($request->get('per_page', 15));

        $campaigns = Campaign::where('status', 'archived')
            ->orderBy('updated_at', 'DESC')
            ->paginate($limit);

        foreach ($campaigns as $campaign) {
            $campaign->stats = $campaign->stats();
        }

        return $this->sendSuccess([
            'campaigns' => $campaigns,
        ]);
    }

    public function getAutomationReports(Request $request)
    {
        $limit = intval($request->get('per_page', 15));

        $funnels = Funnel::where('status', 'published')
            ->orderBy('created_at', 'DESC')
            ->paginate($limit);

        // One grouped aggregate for the whole page instead of 4 queries per funnel.
        $funnelIds = [];
        foreach ($funnels as $funnel) {
            $funnelIds[] = $funnel->id;
        }

        $pageAggregates = [];
        if ($funnelIds) {
            $aggregateRows = FunnelSubscriber::whereIn('funnel_id', $funnelIds)
                ->groupBy('funnel_id')
                ->select('funnel_id')
                ->selectRaw('COUNT(DISTINCT subscriber_id) as total_subscribers')
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count")
                ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as in_progress_count")
                ->selectRaw('MAX(last_executed_time) as last_run_at')
                ->get();

            foreach ($aggregateRows as $aggregateRow) {
                $pageAggregates[$aggregateRow->funnel_id] = $aggregateRow;
            }
        }

        foreach ($funnels as $funnel) {
            $aggregate = isset($pageAggregates[$funnel->id]) ? $pageAggregates[$funnel->id] : null;

            $funnel->total_subscribers = $aggregate ? (int)$aggregate->total_subscribers : 0;
            $funnel->completed_count = $aggregate ? (int)$aggregate->completed_count : 0;
            $funnel->in_progress_count = $aggregate ? (int)$aggregate->in_progress_count : 0;
            $funnel->last_run_at = $aggregate ? $aggregate->last_run_at : null;

            // Recent 3 subscribers who entered
            $recentEntries = FunnelSubscriber::where('funnel_id', $funnel->id)
                ->with(['subscriber' => function ($q) {
                    $q->select(['id', 'first_name', 'last_name', 'email', 'avatar']);
                }])
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            $funnel->recent_subscribers = $recentEntries->map(function ($entry) {
                if (!$entry->subscriber) {
                    return null;
                }
                return [
                    'id'    => $entry->subscriber->id,
                    'name'  => trim($entry->subscriber->first_name . ' ' . $entry->subscriber->last_name),
                    'email' => $entry->subscriber->email,
                    'avatar' => $entry->subscriber->avatar,
                    'entered_at' => $entry->created_at,
                ];
            })->filter()->values();
        }

        // The Top-5 ranking and the global overview totals each aggregate over
        // every funnel-subscriber row of published funnels, so they scale with
        // lifetime automation history rather than page size. Cache them for 5
        // minutes (transient — survives across requests without an object cache)
        // instead of rescanning on every interactive report load. TTL-only
        // invalidation by design: hooking invalidation into enrollment writes
        // would touch the hottest write path for a dashboard stat. The per-page
        // funnel stats above stay live.
        $overviewStats = get_transient('fluent_crm_automation_overview_stats');

        if (!is_array($overviewStats) || !isset($overviewStats['top_automations'], $overviewStats['totals'])) {
            // Top 5 automations by total subscribers (most triggered) — one grouped
            // query instead of a COUNT per published funnel.
            $topAutomations = FunnelSubscriber::join('fc_funnels', function ($join) {
                    $join->on('fc_funnel_subscribers.funnel_id', '=', 'fc_funnels.id')
                        ->where('fc_funnels.status', '=', 'published')
                        ->where('fc_funnels.type', '=', 'funnels');
                })
                ->groupBy('fc_funnel_subscribers.funnel_id', 'fc_funnels.title', 'fc_funnels.trigger_name')
                ->select('fc_funnel_subscribers.funnel_id', 'fc_funnels.title', 'fc_funnels.trigger_name')
                ->selectRaw('COUNT(*) as trigger_count')
                ->orderByDesc('trigger_count')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return [
                        'id'            => $row->funnel_id,
                        'title'         => $row->title,
                        'trigger_name'  => $row->trigger_name,
                        'trigger_count' => (int)$row->trigger_count,
                    ];
                })
                ->values()
                ->toArray();

            // Overview totals are GLOBAL (they're presented as such), not sums of the
            // current page. COUNT(DISTINCT funnel_id, subscriber_id) preserves the
            // per-funnel-unique enrollment semantics of the old per-funnel counts.
            // These aggregates have to name the pivot table explicitly (`status`
            // is ambiguous across the join), and raw fragments bypass the query
            // grammar — so the prefix is resolved by hand.
            global $wpdb;
            $funnelSubscribersTable = $wpdb->prefix . 'fc_funnel_subscribers';

            $globalAggregate = FunnelSubscriber::join('fc_funnels', function ($join) {
                    $join->on('fc_funnel_subscribers.funnel_id', '=', 'fc_funnels.id')
                        ->where('fc_funnels.status', '=', 'published')
                        ->where('fc_funnels.type', '=', 'funnels');
                })
                ->selectRaw("COUNT(DISTINCT `{$funnelSubscribersTable}`.`funnel_id`, `{$funnelSubscribersTable}`.`subscriber_id`) as subscribers")
                ->selectRaw("SUM(CASE WHEN `{$funnelSubscribersTable}`.`status` = 'completed' THEN 1 ELSE 0 END) as completed")
                ->selectRaw("SUM(CASE WHEN `{$funnelSubscribersTable}`.`status` = 'active' THEN 1 ELSE 0 END) as in_progress")
                ->first();

            $overviewStats = [
                'top_automations' => $topAutomations,
                'totals'          => [
                    'subscribers' => $globalAggregate ? (int)$globalAggregate->subscribers : 0,
                    'completed'   => $globalAggregate ? (int)$globalAggregate->completed : 0,
                    'in_progress' => $globalAggregate ? (int)$globalAggregate->in_progress : 0,
                ],
            ];

            set_transient('fluent_crm_automation_overview_stats', $overviewStats, 5 * MINUTE_IN_SECONDS);
        }

        $topAutomations = $overviewStats['top_automations'];

        $overview = [
            'total'        => Funnel::where('status', 'published')->count(),
            'subscribers'  => $overviewStats['totals']['subscribers'],
            'completed'    => $overviewStats['totals']['completed'],
            'in_progress'  => $overviewStats['totals']['in_progress'],
        ];

        return $this->sendSuccess([
            'automations'     => $funnels,
            'overview'        => $overview,
            'top_automations' => $topAutomations,
        ]);
    }

    public function getAutomationStepReport(Request $request, Reporting $reporting, $id)
    {
        $id = intval($id);
        $funnel = Funnel::findOrFail($id);

        $stats = $reporting->funnelStat($funnel->id);

        return $this->sendSuccess([
            'funnel' => $funnel,
            'stats'  => $stats,
        ]);
    }

    public function getCampaignOptions(Request $request)
    {
        global $wpdb;

        $search = sanitize_text_field($request->get('search', ''));
        $limit = intval($request->get('per_page', 50));

        $query = Campaign::select(['id', 'title'])
            ->where('status', 'archived');

        if ($search) {
            $query->where('title', 'LIKE', '%' . $wpdb->esc_like($search) . '%');
        }

        $options = $query->orderBy('updated_at', 'DESC')
            ->limit($limit)
            ->get();

        return $this->sendSuccess([
            'options' => $options,
        ]);
    }

    public function getAdvancedReportProviders()
    {
        return [
            /**
             * Determine the advanced report providers for FluentCRM.
             *
             * This filter allows you to modify the list of advanced report providers.
             *
             * @since 1.0.0
             *
             * @param array An array of advanced report providers.
             */
            'providers' => apply_filters('fluent_crm/advanced_report_providers', [])
        ];
    }

    public function getRecentTags(Request $request)
    {
        $limit = intval($request->get('per_page', 5));

        $tags = Tag::select(['fc_tags.id', 'fc_tags.title', 'fc_tags.created_at'])
            ->selectRaw('COUNT(subscriber_id) as contact_count')
            ->leftJoin('fc_subscriber_pivot', function ($join) {
                $join->on('fc_tags.id', '=', 'fc_subscriber_pivot.object_id')
                    ->where('fc_subscriber_pivot.object_type', '=', 'FluentCrm\App\Models\Tag');
            })
            ->groupBy('fc_tags.id', 'fc_tags.title', 'fc_tags.created_at')
            ->orderByDesc('fc_tags.created_at')
            ->limit($limit)
            ->get();

        return $this->sendSuccess([
            'tags' => $tags,
        ]);
    }

    public function ping()
    {
        // Browser-driven cron fallback: while an admin has any CRM page open,
        // the app pings this endpoint ~every 50s. If Action Scheduler (and the
        // WP-Cron fallback) have stalled, take over the every-minute email task
        // here. No-ops in a single option read when scheduling is healthy, and
        // is fully locked/throttled internally — safe across tabs and users.
        Scheduler::maybeProcessFromBrowserPing();

        return [
            'message' => 'pong'
        ];
    }
}
