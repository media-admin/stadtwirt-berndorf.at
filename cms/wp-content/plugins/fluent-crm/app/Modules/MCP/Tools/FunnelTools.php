<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Services\Funnel\FunnelHelper;

/**
 * Automation (funnel) MCP tools.
 *
 * Read tools (Phase 2): listAutomations, getAutomation.
 * Write tools (Phase 3): updateContactAutomationStatus.
 */
class FunnelTools
{
    // -----------------------------------------------------------------
    // Read: list-automations
    // -----------------------------------------------------------------

    public static function listAutomations($params)
    {
        $params = (array) $params;

        MCPHelper::paginationFromInput($params);

        $search   = sanitize_text_field((string) ($params['search'] ?? ''));
        $statuses = (array) ($params['statuses'] ?? []);
        $statuses = array_values(array_intersect(
            array_map('sanitize_key', $statuses),
            ['draft', 'published']
        ));
        // All fc_funnels columns. The framework rewrite made orderBy() throw
        // LogicException on column names that don't match ^[a-zA-Z0-9_\.]+$
        // — empty strings, "id ASC", "DROP TABLE", etc. — so an unguarded
        // sort_by would 500 the tool. Schema is stable (migration only adds
        // indexes), so hardcoding the column list avoids a per-request
        // SHOW COLUMNS without restricting agents to the input_schema enum.
        $allowedSortBy = [
            'id', 'type', 'title', 'trigger_name', 'status', 'conditions',
            'settings', 'created_by', 'created_at', 'updated_at',
        ];
        $sortBy = sanitize_key((string) ($params['sort_by'] ?? 'id'));
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'id';
        }
        $sortType = strtoupper(sanitize_text_field((string) ($params['sort_type'] ?? 'DESC')));
        $sortType = in_array($sortType, ['ASC', 'DESC'], true) ? $sortType : 'DESC';

        $query = Funnel::withCount('subscribers')->orderBy($sortBy, $sortType);

        if ($search !== '') {
            global $wpdb;
            $like = '%' . $wpdb->esc_like($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('trigger_name', 'LIKE', $like);
            });
        }

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        $paginated = $query->paginate();

        $items = [];
        $triggerLabels = self::triggerLabelMap();
        foreach ($paginated->items() as $funnel) {
            $items[] = [
                'id'                            => (int) $funnel->id,
                'title'                         => $funnel->title,
                'status'                        => $funnel->status,
                'trigger_name'                  => $funnel->trigger_name,
                'trigger_label'                 => $triggerLabels[$funnel->trigger_name] ?? $funnel->trigger_name,
                'in_progress_subscribers_count' => self::inProgressCount((int) $funnel->id),
                'completed_subscribers_count'   => (int) ($funnel->subscribers_count ?? 0),
                'created_at'                    => MCPHelper::toIso8601($funnel->created_at),
                'updated_at'                    => MCPHelper::toIso8601($funnel->updated_at),
            ];
        }

        return [
            'items'    => $items,
            'total'    => (int) $paginated->total(),
            'page'     => (int) $paginated->currentPage(),
            'per_page' => (int) $paginated->perPage(),
            'pages'    => (int) $paginated->lastPage(),
        ];
    }

    // -----------------------------------------------------------------
    // Read: get-automation
    // -----------------------------------------------------------------

    public static function getAutomation($params)
    {
        $params = (array) $params;
        $funnelId = (int) ($params['funnel_id'] ?? 0);

        if (!$funnelId) {
            return MCPHelper::error('invalid_param', __('funnel_id is required', 'fluent-crm'));
        }

        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            return MCPHelper::error('not_found', __('Automation not found', 'fluent-crm'), ['funnel_id' => $funnelId]);
        }

        $defaultIncludes = ['sequences', 'report'];
        $include = isset($params['include']) && is_array($params['include']) && $params['include']
            ? array_values(array_intersect($params['include'], ['sequences', 'report']))
            : $defaultIncludes;

        $triggerLabels = self::triggerLabelMap();

        $data = [
            'id'                            => (int) $funnel->id,
            'title'                         => $funnel->title,
            'status'                        => $funnel->status,
            'trigger_name'                  => $funnel->trigger_name,
            'trigger_label'                 => $triggerLabels[$funnel->trigger_name] ?? $funnel->trigger_name,
            'trigger_settings'              => is_array($funnel->settings) ? $funnel->settings : [],
            'conditions'                    => is_array($funnel->conditions) ? $funnel->conditions : [],
            'in_progress_subscribers_count' => self::inProgressCount((int) $funnel->id),
            'completed_subscribers_count'   => (int) FunnelSubscriber::where('funnel_id', $funnel->id)
                ->where('status', 'completed')
                ->count(),
            'created_at'                    => MCPHelper::toIso8601($funnel->created_at),
            'updated_at'                    => MCPHelper::toIso8601($funnel->updated_at),
        ];

        if (in_array('sequences', $include, true)) {
            $sequences = FunnelHelper::getFunnelSequences($funnel, true);
            $includeBodies = !empty($params['include_bodies']);
            $data['sequences'] = self::formatSequences($sequences, $includeBodies);
        }

        if (in_array('report', $include, true)) {
            $data['report'] = self::buildStepReport($funnel);
        }

        return $data;
    }

    /**
     * Format funnel sequences for MCP. By default we strip large body
     * fields out of `settings` (action_name=send_custom_email embeds an
     * entire campaign payload including email_body). Pass include_bodies
     * = true to get the full settings tree — review #7 (token bloat).
     */
    private static function formatSequences($sequences, $includeBodies = false)
    {
        $out = [];
        foreach ((array) $sequences as $seq) {
            $row = is_object($seq) ? get_object_vars($seq) : (array) $seq;
            $settings = $row['settings'] ?? [];
            if (!$includeBodies) {
                $settings = self::stripBodyFields($settings);
            }
            $out[] = [
                'id'           => isset($row['id']) ? (int) $row['id'] : null,
                'type'         => $row['type'] ?? null,
                'action_name'  => $row['action_name'] ?? null,
                'settings'     => $settings,
                'delay'        => $row['delay'] ?? 0,
                'delay_unit'   => $row['c_delay_unit'] ?? ($row['delay_unit'] ?? null),
                'parent_id'    => isset($row['parent_id']) ? (int) $row['parent_id'] : null,
            ];
        }
        return $out;
    }

    /**
     * Recursively redact body / html fields. Replaces them with a marker so
     * the agent knows the field exists and can re-fetch with include_bodies.
     *
     * Round-3 review B5: dropped the previous "only if >200 chars" gate —
     * any stored body field gets stripped now, regardless of size, so the
     * include_bodies=false contract is honored consistently. Rare to have a
     * truly tiny body field anyway, and the marker is shorter than most
     * email bodies.
     */
    private static function stripBodyFields($value)
    {
        $bodyKeys = ['email_body', 'body', 'body_html', 'body_text'];
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $k => $v) {
            if (is_string($k) && in_array($k, $bodyKeys, true) && is_string($v)) {
                $len = strlen($v);
                $value[$k] = $len > 0
                    ? '[truncated — re-fetch with include_bodies=true; ' . $len . ' chars]'
                    : '';
            } elseif (is_array($v)) {
                $value[$k] = self::stripBodyFields($v);
            }
        }
        return $value;
    }

    private static function buildStepReport($funnel)
    {
        $stepCounts = FunnelSubscriber::where('funnel_id', $funnel->id)
            ->select(['last_sequence_id'])
            ->selectRaw('COUNT(id) as total')
            ->groupBy('last_sequence_id')
            ->get();

        $steps = [];
        foreach ($stepCounts as $row) {
            if (!$row->last_sequence_id) {
                continue;
            }
            $steps[] = [
                'step_id'  => (int) $row->last_sequence_id,
                'total'    => (int) $row->total,
            ];
        }
        return ['steps' => $steps];
    }

    private static function inProgressCount($funnelId)
    {
        return (int) FunnelSubscriber::where('funnel_id', $funnelId)
            ->whereIn('status', ['active', 'waiting'])
            ->count();
    }

    private static function triggerLabelMap()
    {
        $triggers = apply_filters('fluentcrm_funnel_triggers', []);
        $map = [];
        if (is_array($triggers)) {
            foreach ($triggers as $key => $config) {
                $map[$key] = $config['label'] ?? $key;
            }
        }
        return $map;
    }

    // -----------------------------------------------------------------
    // Read: list-funnel-subscribers (round-4 review P3 #11)
    // -----------------------------------------------------------------

    /**
     * List the contacts currently in a funnel filtered by subscription
     * status. Closes a real workflow gap: "this customer just upgraded —
     * pull them out of trial-onboarding" requires knowing who's in the
     * funnel first, and there was no way to find that without already
     * knowing the contact id.
     */
    public static function listFunnelSubscribers($params)
    {
        $params = (array) $params;
        $funnelId = (int) ($params['funnel_id'] ?? 0);
        if (!$funnelId) {
            return MCPHelper::error('invalid_param', __('funnel_id is required', 'fluent-crm'));
        }

        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            return MCPHelper::error('not_found', __('Automation not found', 'fluent-crm'), ['funnel_id' => $funnelId]);
        }

        $allowedStatuses = ['active', 'waiting', 'completed', 'cancelled', 'skipped'];
        $statuses = (array) ($params['statuses'] ?? ['active']);
        $statuses = array_values(array_intersect(array_map('sanitize_key', $statuses), $allowedStatuses));
        if (!$statuses) {
            $statuses = ['active'];
        }

        MCPHelper::paginationFromInput($params);

        $rows = FunnelSubscriber::with(['subscriber' => function ($q) {
            $q->select(['id', 'email', 'first_name', 'last_name', 'status', 'contact_type']);
        }])
            ->where('funnel_id', $funnelId)
            ->whereIn('status', $statuses)
            ->orderBy('id', 'DESC')
            ->paginate();

        $items = [];
        foreach ($rows->items() as $row) {
            $sub = $row->subscriber;
            if (!$sub) {
                continue;
            }
            $items[] = [
                'funnel_subscriber_id' => (int) $row->id,
                'funnel_status'        => $row->status,
                'next_sequence_id'     => $row->next_sequence_id ? (int) $row->next_sequence_id : null,
                'last_executed_at'     => MCPHelper::toIso8601($row->last_executed_time),
                'next_execution_at'    => MCPHelper::toIso8601($row->next_execution_time),
                'enrolled_at'          => MCPHelper::toIso8601($row->created_at),
                'contact'              => [
                    'id'           => (int) $sub->id,
                    'email'        => $sub->email,
                    'full_name'    => trim((string) ($sub->first_name . ' ' . $sub->last_name)),
                    'status'       => $sub->status,
                    'contact_type' => $sub->contact_type,
                ],
            ];
        }

        return [
            'items'     => $items,
            'total'     => (int) $rows->total(),
            'page'      => (int) $rows->currentPage(),
            'per_page'  => (int) $rows->perPage(),
            'pages'     => (int) $rows->lastPage(),
            'funnel'    => [
                'id'    => (int) $funnel->id,
                'title' => $funnel->title,
            ],
            'filtered_statuses' => $statuses,
        ];
    }

    // -----------------------------------------------------------------
    // Write: update-contact-automation-status
    // -----------------------------------------------------------------

    public static function updateContactAutomationStatus($params)
    {
        $params = (array) $params;
        $funnelId = (int) ($params['funnel_id'] ?? 0);
        $action   = sanitize_key((string) ($params['action'] ?? ''));

        if (!$funnelId) {
            return MCPHelper::error('invalid_param', __('funnel_id is required', 'fluent-crm'));
        }
        if (!in_array($action, ['resume', 'cancel', 'advance_now'], true)) {
            // 'pause' was intentionally dropped — FluentCRM has no native
            // paused funnel-subscriber state, and the previous mapping
            // silently cancelled. Tell the agent what the alternative is.
            if ($action === 'pause') {
                return MCPHelper::error('not_supported', __('pause is not supported — FluentCRM has no paused state for funnel subscribers. Use cancel to stop processing (reversible from the UI), or wait for a real benchmark.', 'fluent-crm'), [
                    'allowed_actions' => ['resume', 'cancel', 'advance_now'],
                ]);
            }
            return MCPHelper::error('invalid_param', __('Invalid action', 'fluent-crm'), [
                'allowed_actions' => ['resume', 'cancel', 'advance_now'],
            ]);
        }

        $contact = MCPHelper::resolveContact($params);
        if (is_wp_error($contact)) {
            return $contact;
        }

        $funnel = Funnel::find($funnelId);
        if (!$funnel) {
            return MCPHelper::error('not_found', __('Automation not found', 'fluent-crm'), ['funnel_id' => $funnelId]);
        }

        $row = FunnelSubscriber::where('funnel_id', $funnelId)
            ->where('subscriber_id', $contact->id)
            ->first();
        if (!$row) {
            return MCPHelper::error('not_found', __('Contact is not enrolled in this automation', 'fluent-crm'));
        }

        $previousStatus = $row->status;

        if ($row->status === 'completed') {
            return MCPHelper::error('not_supported', __('Automation is already completed for this contact', 'fluent-crm'), [
                'status' => $row->status,
            ]);
        }

        if ($action === 'cancel') {
            $row->status = 'cancelled';
            $row->save();
        } elseif ($action === 'resume') {
            $row->status = 'active';
            if (!$row->next_execution_time) {
                $row->next_execution_time = gmdate('Y-m-d H:i:s', current_time('timestamp') + 60);
            }
            $row->save();
        } elseif ($action === 'advance_now') {
            $sequenceId = (int) ($params['advance_to_sequence_id'] ?? 0);
            if (!$sequenceId) {
                return MCPHelper::error('invalid_param', __('advance_to_sequence_id is required for advance_now', 'fluent-crm'));
            }
            $sequence = \FluentCrm\App\Models\FunnelSequence::where('id', $sequenceId)
                ->where('funnel_id', $funnelId)
                ->first();
            if (!$sequence) {
                return MCPHelper::error('not_found', __('Target sequence not found in this automation', 'fluent-crm'));
            }

            // If the contact is waiting on a benchmark, mark the benchmark as
            // skipped so reports stay accurate (matches the controller's path).
            if ($row->status === 'waiting') {
                $benchmarkSeq = \FluentCrm\App\Models\FunnelSequence::find($row->next_sequence_id);
                if ($benchmarkSeq) {
                    \FluentCrm\App\Models\FunnelMetric::updateOrCreate(
                        [
                            'funnel_id'     => $funnelId,
                            'sequence_id'   => $benchmarkSeq->id,
                            'subscriber_id' => $contact->id,
                        ],
                        [
                            'benchmark_value'    => 0,
                            'benchmark_currency' => 'USD',
                            'status'             => 'skipped',
                            'notes'              => __('Skipped via MCP advance_now', 'fluent-crm'),
                        ]
                    );
                    \FluentCrm\App\Services\Funnel\FunnelHelper::changeFunnelSubSequenceStatus($row->id, $benchmarkSeq->id, 'skipped');
                }
            }

            $prev = \FluentCrm\App\Models\FunnelSequence::where('funnel_id', $funnelId)
                ->where('sequence', '<', $sequence->sequence)
                ->orderBy('sequence', 'DESC')
                ->first();

            $row->last_sequence_id = $prev ? $prev->id : 0;
            $row->next_sequence_id = $sequence->id;
            $row->next_sequence    = $sequence->sequence;
            $row->status           = 'active';
            $row->next_execution_time = current_time('mysql');
            $row->save();
        }

        $row = FunnelSubscriber::find($row->id);

        return [
            'ok'              => true,
            'action'          => $action,
            'previous_status' => $previousStatus,
            'current_status'  => $row->status,
            'funnel_subscriber' => [
                'id'                => (int) $row->id,
                'funnel_id'         => (int) $row->funnel_id,
                'subscriber_id'     => (int) $row->subscriber_id,
                'status'            => $row->status,
                'next_sequence_id'  => $row->next_sequence_id ? (int) $row->next_sequence_id : null,
                'next_execution_time' => MCPHelper::toIso8601($row->next_execution_time),
            ],
        ];
    }
}
