<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Services\PermissionManager;

/**
 * Tag/list read + management tools.
 *
 * Read: `list-tags` and `list-lists` enumerate the registries with search,
 * pagination, and opt-in size/recency metrics. Before these existed, tags and
 * lists reached an agent only through `get-crm-context`'s `segments` section,
 * which is hard-capped at 50 rows with no paging — so a site with more than 50
 * tags could not be enumerated at all, and `manage-tag`'s merge (up to 100
 * sources) had no matching read surface to plan against.
 *
 * Write: `manage-tag` and `manage-list` cover create, update, delete, and merge
 * operations. Splitting create/update/delete into separate tools would
 * triple the surface; the action enum keeps it compact while the
 * `destructive` annotation tells MCP clients to confirm delete + merge.
 *
 * Merge semantics: re-pivot every subscriber attached to a `from` tag/list
 * onto the `to` target, then delete the `from` rows. Idempotent — running
 * the same merge twice no-ops on the second call (subscribers are already
 * pivoted, sources already deleted).
 *
 * Note the naming: these are deliberately NOT called "segments" on the agent
 * surface. "Segments" is a distinct FluentCRM feature (Contacts > Segments,
 * `sending_filter = 'dynamic_segment'`) supplied by FluentCampaign Pro, and
 * reusing the word here would make an agent conflate the two.
 */
class SegmentTools
{
    /**
     * Bound the ref list `list-tags`/`list-lists` will resolve in one call.
     * Matches the 100-ref ceiling used by list-contacts' `emails`.
     */
    const MAX_LOOKUP_REFS = 100;

    /**
     * Bound synchronous merge work by source pivot rows. A subscriber attached
     * to two source segments accounts for two rows because both must be
     * detached. Operators can deliberately raise this through the documented
     * filter when their environment can sustain a larger synchronous job.
     */
    const DEFAULT_MERGE_MAX_PIVOT_ROWS = 5000;

    /**
     * Bound the SQL IN list even for direct Ability/REST callers that bypass
     * the MCP adapter's input-schema validation.
     */
    const MAX_MERGE_SOURCE_IDS = 100;

    /**
     * Keep model hydration and native hook dispatch bounded per query.
     */
    const MERGE_BATCH_SIZE = 200;

    /**
     * How many follow-up passes actionMerge() makes to catch memberships
     * created while it was running. Bounded so a site attaching to a source
     * faster than the merge drains it cannot keep the request alive; anything
     * still outstanding after the last pass is re-pointed by
     * mergeRepointStragglers() instead of being lost.
     */
    const MERGE_MAX_SWEEPS = 3;

    // -----------------------------------------------------------------
    // Read: list-tags / list-lists
    // -----------------------------------------------------------------

    public static function listTags($params)
    {
        return self::listSegments($params, 'tag');
    }

    public static function listLists($params)
    {
        return self::listSegments($params, 'list');
    }

    /**
     * Paginated tag/list registry with optional size + recency metrics.
     *
     * Both metrics are opt-in because both cost a pass over fc_subscriber_pivot,
     * which is the largest table on most installs:
     *
     *  - `include_counts` uses withCount() so the aggregate happens in SQL
     *    before the LIMIT, which is what makes sort_by=subscribers_count
     *    possible at all.
     *  - `include_last_used` runs ONE grouped query over just the ids on the
     *    current page (the same bounded pattern FunnelTools::listAutomations
     *    uses for its subscriber counts) rather than a correlated subquery per
     *    row. The pivot is indexed on object_id alone, not (object_id,
     *    created_at), so MAX(created_at) still walks each tag's rows — keeping
     *    it to one page's worth is the difference between bounded and brutal.
     *
     * The consequence of computing last_used_at after the LIMIT is that it
     * cannot be a sort key; `sort_by` deliberately omits it. With per_page=100
     * an agent sorts a page itself, and most installs hold well under a page
     * of tags.
     *
     * @param array  $params Tool arguments.
     * @param string $kind   'tag' or 'list'.
     * @return array
     */
    private static function listSegments($params, $kind)
    {
        $params = (array) $params;

        MCPHelper::paginationFromInput($params);

        $modelClass = $kind === 'list' ? Lists::class : Tag::class;
        $query      = $modelClass::query();

        // Reuse the model's own scope: it escapes LIKE wildcards via
        // $wpdb->esc_like() and spans title/slug/description, so search here
        // behaves exactly like the admin list it mirrors.
        $search = sanitize_text_field((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $query->searchBy($search);
        }

        // Direct ref lookup. Unresolvable refs are REPORTED, not ignored — an
        // agent that asked for 5 tags and silently got 4 would draw wrong
        // conclusions about which ones exist. Same contract as list-contacts'
        // `emails`/`not_found`.
        $notFoundRefs = [];
        if (!empty($params['ids'])) {
            $refs = (array) $params['ids'];
            if (count($refs) > self::MAX_LOOKUP_REFS) {
                return MCPHelper::error('invalid_param', sprintf(
                    /* translators: 1: maximum refs per call, 2: number received */
                    __('ids accepts at most %1$d refs per call; received %2$d. Page through with search instead.', 'fluent-crm'),
                    self::MAX_LOOKUP_REFS,
                    count($refs)
                ), ['max_refs' => self::MAX_LOOKUP_REFS, 'received' => count($refs)]);
            }

            $resolved     = self::resolveRefs($refs, $kind);
            $notFoundRefs = $resolved['not_found'];

            // Fail CLOSED. An empty IN list would drop the filter entirely and
            // return the whole registry — the opposite of what was asked for.
            $query->whereIn('id', $resolved['ids'] ?: [0]);
        }

        $allowedSortBy = ['id', 'title', 'slug', 'created_at', 'updated_at', 'subscribers_count'];
        $sortBy = sanitize_key((string) ($params['sort_by'] ?? 'title'));
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'title';
        }
        $sortType = strtoupper(sanitize_text_field((string) ($params['sort_type'] ?? 'ASC')));
        $sortType = in_array($sortType, ['ASC', 'DESC'], true) ? $sortType : 'ASC';

        // Sorting by size implies wanting the size — otherwise the agent gets
        // rows ordered by a number the response never shows.
        $withCounts = !empty($params['include_counts']) || $sortBy === 'subscribers_count';
        if ($withCounts) {
            $query->withCount('subscribers');
        }

        $paginated = $query->orderBy($sortBy, $sortType)->paginate();
        $rows      = $paginated->items();

        $lastUsed = [];
        if (!empty($params['include_last_used'])) {
            $lastUsed = self::lastUsedMap(array_map(function ($row) {
                return (int) $row->id;
            }, $rows), $kind);
        }

        $items = [];
        foreach ($rows as $row) {
            $item = self::format($row);

            if ($kind === 'list') {
                $item['is_public'] = !empty($row->is_public);
            }

            $item['created_at'] = MCPHelper::toIso8601($row->created_at);

            if ($withCounts) {
                $item['subscribers_count'] = (int) $row->subscribers_count;
            }

            if (!empty($params['include_last_used'])) {
                $stats = $lastUsed[(int) $row->id] ?? ['last_used_at' => null, 'pivot_rows' => 0];
                $item['last_used_at'] = MCPHelper::toIso8601($stats['last_used_at']);

                // Distinguish "nobody has this tag" from "contacts have it but
                // the pivot rows predate timestamp stamping". Both surface as a
                // null last_used_at, and a cleanup agent that treats the second
                // case as "unused" would delete a live tag with thousands of
                // contacts on it. Emitted only when it applies.
                if (!$item['last_used_at'] && $stats['pivot_rows'] > 0) {
                    $item['last_used_at_unknown'] = true;
                }
            }

            $items[] = $item;
        }

        $out = [
            'object_type' => $kind,
            'items'       => $items,
            'total'       => (int) $paginated->total(),
            'page'        => (int) $paginated->currentPage(),
            'per_page'    => (int) $paginated->perPage(),
            'pages'       => (int) $paginated->lastPage(),
        ];

        if (!empty($params['ids'])) {
            $out['not_found'] = $notFoundRefs;
        }

        return $out;
    }

    /**
     * Resolve a mixed list of ids / slugs / titles to row ids, reporting the
     * refs that matched nothing.
     *
     * @return array{ids: int[], not_found: string[]}
     */
    private static function resolveRefs($refs, $kind)
    {
        $modelClass = $kind === 'list' ? Lists::class : Tag::class;

        $numericIds = [];
        $textRefs   = [];
        foreach ($refs as $ref) {
            if ($ref === '' || $ref === null || is_array($ref)) {
                continue;
            }
            if (is_numeric($ref)) {
                $numericIds[] = (int) $ref;
            } else {
                $textRefs[] = sanitize_text_field((string) $ref);
            }
        }

        if (!$numericIds && !$textRefs) {
            return ['ids' => [], 'not_found' => []];
        }

        // One query for the whole batch — a per-ref find() would be N round
        // trips for what is a single indexed lookup.
        $slugs = array_map('sanitize_title', $textRefs);
        $rows  = $modelClass::query()
            ->where(function ($q) use ($numericIds, $textRefs, $slugs) {
                if ($numericIds) {
                    $q->orWhereIn('id', $numericIds);
                }
                if ($textRefs) {
                    $q->orWhereIn('title', $textRefs);
                    $q->orWhereIn('slug', $slugs);
                }
            })
            ->get();

        $ids       = [];
        $byId      = [];
        $byTitle   = [];
        $bySlug    = [];
        foreach ($rows as $row) {
            $ids[]                              = (int) $row->id;
            $byId[(int) $row->id]               = true;
            $byTitle[strtolower($row->title)]   = true;
            $bySlug[(string) $row->slug]        = true;
        }

        $notFound = [];
        foreach ($numericIds as $id) {
            if (!isset($byId[$id])) {
                $notFound[] = (string) $id;
            }
        }
        foreach ($textRefs as $i => $ref) {
            if (!isset($byTitle[strtolower($ref)]) && !isset($bySlug[$slugs[$i]])) {
                $notFound[] = $ref;
            }
        }

        return [
            'ids'       => array_values(array_unique($ids)),
            'not_found' => array_values(array_unique($notFound)),
        ];
    }

    /**
     * Last-application timestamp + attached row count for the given tag/list
     * ids, in one grouped query.
     *
     * `last_used_at` reads the pivot's created_at, which attachTags()/
     * attachLists() stamp at attach time. Because those use INSERT IGNORE
     * against a unique key, re-attaching a tag a contact already has does NOT
     * bump the timestamp — so this is "the most recent time this tag was newly
     * applied to some contact", which is exactly the stale-tag signal, not
     * "the last time it was touched".
     *
     * @param int[]  $ids  Ids on the current page.
     * @param string $kind 'tag' or 'list'.
     * @return array<int, array{last_used_at: ?string, pivot_rows: int}>
     */
    private static function lastUsedMap($ids, $kind)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if (!$ids) {
            return [];
        }

        global $wpdb;
        $pivotTable   = $wpdb->prefix . 'fc_subscriber_pivot';
        $objectType   = self::segmentObjectType($kind);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // object_type discriminator is mandatory: fc_subscriber_pivot is shared
        // by tags, lists, AND companies, so omitting it would blend a tag's
        // rows with the list row that happens to share its id.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, COUNT(*) AS pivot_rows, MAX(created_at) AS last_used_at
             FROM {$pivotTable}
             WHERE object_type = %s AND object_id IN ({$placeholders})
             GROUP BY object_id",
            array_merge([$objectType], $ids)
        ));

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->object_id] = [
                'last_used_at' => $row->last_used_at,
                'pivot_rows'   => (int) $row->pivot_rows,
            ];
        }

        return $map;
    }

    // -----------------------------------------------------------------
    // manage-tag
    // -----------------------------------------------------------------

    public static function manageTag($params)
    {
        return self::manageSegment($params, 'tag');
    }

    // -----------------------------------------------------------------
    // manage-list
    // -----------------------------------------------------------------

    public static function manageList($params)
    {
        return self::manageSegment($params, 'list');
    }

    private static function manageSegment($params, $kind)
    {
        $params = (array) $params;
        $action = sanitize_key((string) ($params['action'] ?? ''));

        if (!in_array($action, ['create', 'update', 'delete', 'merge'], true)) {
            return MCPHelper::error('invalid_param', __('action must be one of: create, update, delete, merge', 'fluent-crm'));
        }

        // Permission gate. create/update need _cats; delete needs _cats_delete.
        $needsDeleteCap = in_array($action, ['delete', 'merge'], true);
        $cap = $needsDeleteCap ? 'fcrm_manage_contact_cats_delete' : 'fcrm_manage_contact_cats';
        if (!PermissionManager::currentUserCan($cap)) {
            return MCPHelper::error('forbidden', sprintf(
                /* translators: %s: required capability */
                __('This action requires the %s capability.', 'fluent-crm'),
                $cap
            ), ['required' => $cap]);
        }

        switch ($action) {
            case 'create':
                return self::actionCreate($params, $kind);
            case 'update':
                return self::actionUpdate($params, $kind);
            case 'delete':
                return self::actionDelete($params, $kind);
            case 'merge':
                return self::actionMerge($params, $kind);
        }

        return MCPHelper::error('invalid_param', __('Unhandled action', 'fluent-crm'));
    }

    private static function actionCreate($params, $kind)
    {
        $title = trim((string) ($params['title'] ?? ''));
        if ($title === '') {
            return MCPHelper::error('invalid_param', __('title is required for create', 'fluent-crm'));
        }
        $slug = isset($params['slug']) && $params['slug'] !== ''
            ? sanitize_title((string) $params['slug'])
            : sanitize_title($title);
        $description = sanitize_textarea_field((string) ($params['description'] ?? ''));

        $existing = self::lookupByTitleOrSlug($title, $slug, $kind);
        if ($existing) {
            return MCPHelper::error('contact_exists', sprintf(
                /* translators: 1: kind (tag/list), 2: matched id */
                __('A %1$s with that title or slug already exists (id %2$d). Use update or merge to change it.', 'fluent-crm'),
                $kind,
                (int) $existing->id
            ), ['existing_id' => (int) $existing->id]);
        }

        $modelClass = $kind === 'list' ? Lists::class : Tag::class;
        $row = $modelClass::create([
            'title'       => sanitize_text_field($title),
            'slug'        => $slug,
            'description' => $description,
        ]);

        do_action(self::createdHook($kind), $row);

        // A new row invalidates memoized "unknown ref" verdicts.
        MCPHelper::flushSegmentRefCache();

        return [
            'ok'      => true,
            'action'  => 'create',
            'kind'    => $kind,
            $kind     => self::format($row),
            'note'    => sprintf(
                /* translators: 1: kind, 2: title */
                __('%1$s "%2$s" created. No subscribers are attached yet.', 'fluent-crm'),
                ucfirst($kind),
                $row->title
            ),
        ];
    }

    private static function actionUpdate($params, $kind)
    {
        $id = (int) ($params[$kind . '_id'] ?? 0);
        $row = $id ? self::find($id, $kind) : null;
        if (!$row) {
            return MCPHelper::error('not_found', sprintf(__('%s not found', 'fluent-crm'), ucfirst($kind)), [$kind . '_id' => $id]);
        }

        $changes = [];
        if (isset($params['title']) && $params['title'] !== '' && $params['title'] !== $row->title) {
            $changes['title'] = ['from' => $row->title, 'to' => sanitize_text_field((string) $params['title'])];
            $row->title = $changes['title']['to'];
        }
        if (isset($params['slug']) && $params['slug'] !== '') {
            $newSlug = sanitize_title((string) $params['slug']);
            if ($newSlug !== $row->slug) {
                $changes['slug'] = ['from' => $row->slug, 'to' => $newSlug];
                $row->slug = $newSlug;
            }
        }
        if (array_key_exists('description', $params)) {
            $newDesc = sanitize_textarea_field((string) $params['description']);
            if ($newDesc !== $row->description) {
                $changes['description'] = ['from' => $row->description, 'to' => $newDesc];
                $row->description = $newDesc;
            }
        }

        if (empty($changes)) {
            return [
                'ok'     => true,
                'action' => 'update',
                'kind'   => $kind,
                $kind    => self::format($row),
                'note'   => __('No changes — provided fields matched the current values.', 'fluent-crm'),
            ];
        }

        // Identity conflict: title/slug resolution is first-match everywhere
        // (MCPHelper::resolveSegmentRefs, lookupByTitleOrSlug), so letting an
        // update take a title or slug that another row already holds makes
        // every later reference to it ambiguous. actionCreate() already
        // refuses this; update did not, which left the same inconsistent state
        // reachable through the back door (review PR #2025).
        if (isset($changes['title']) || isset($changes['slug'])) {
            $conflict = self::identityConflict($row, $kind);
            if (is_wp_error($conflict)) {
                return $conflict;
            }
        }

        $row->save();

        // A renamed row invalidates memoized title/slug lookups.
        MCPHelper::flushSegmentRefCache();

        return [
            'ok'      => true,
            'action'  => 'update',
            'kind'    => $kind,
            $kind     => self::format($row),
            'changes' => $changes,
        ];
    }

    /**
     * Refuse a title or slug that already belongs to a DIFFERENT tag/list.
     *
     * Enforced in application code rather than with a unique index: the
     * fc_tags / fc_lists schemas allow duplicates today and existing installs
     * may already hold some, so adding the constraint would fail the upgrade
     * on exactly the sites that need it most. Core's create paths take the
     * same check-then-create approach (Helper::createNewTags()).
     *
     * @param object $row  Tag/Lists model carrying the PROPOSED title + slug
     * @param string $kind 'tag' or 'list'
     * @return true|\WP_Error
     */
    private static function identityConflict($row, $kind)
    {
        $modelClass = $kind === 'list' ? Lists::class : Tag::class;

        $clash = $modelClass::where('id', '!=', $row->id)
            ->where(function ($query) use ($row) {
                $query->where('title', $row->title)->orWhere('slug', $row->slug);
            })
            ->first();

        if (!$clash) {
            return true;
        }

        return MCPHelper::error('contact_exists', sprintf(
            /* translators: 1: kind (tag/list), 2: conflicting id */
            __('Another %1$s (id %2$d) already uses that title or slug. Pick a different one, or merge the two.', 'fluent-crm'),
            $kind,
            (int) $clash->id
        ), [
            'conflict_id'    => (int) $clash->id,
            'conflict_title' => $clash->title,
            'conflict_slug'  => $clash->slug,
        ]);
    }

    private static function actionDelete($params, $kind)
    {
        $id = (int) ($params[$kind . '_id'] ?? 0);
        $row = $id ? self::find($id, $kind) : null;
        if (!$row) {
            return MCPHelper::error('not_found', sprintf(__('%s not found', 'fluent-crm'), ucfirst($kind)), [$kind . '_id' => $id]);
        }

        $force = !empty($params['force']);
        $attachedCount = self::attachedSubscriberCount($row, $kind);

        if ($attachedCount > 0 && !$force) {
            return MCPHelper::error('not_supported', sprintf(
                /* translators: 1: kind, 2: count */
                __('%1$s has %2$d subscribers attached. Pass force=true to delete anyway, or merge into another %1$s first.', 'fluent-crm'),
                ucfirst($kind),
                $attachedCount
            ), [
                'attached_subscribers' => $attachedCount,
                'force_required'       => true,
            ]);
        }

        $deletedId    = (int) $row->id;
        $deletedTitle = (string) $row->title;

        // The count above and the delete below are separate statements, and
        // deleting fires Cleanup::deleteTagAssets(), which hard-deletes every
        // pivot row for this segment. A contact attached in between would be
        // dropped from a segment the caller was told was empty, without ever
        // passing force=true. A force delete accepts that loss by definition;
        // a non-force one must not, so re-assert emptiness as the first read
        // of the transaction that performs the delete (review PR #2025).
        fluentCrmDb()->beginTransaction();

        try {
            if (!$force) {
                $recount = self::attachedSubscriberCount($row, $kind);
                if ($recount > 0) {
                    fluentCrmDb()->rollBack();

                    return MCPHelper::error('not_supported', sprintf(
                        /* translators: 1: kind, 2: count */
                        __('%1$s gained %2$d subscriber(s) while the delete was being processed. Nothing was deleted — re-check and retry, or pass force=true.', 'fluent-crm'),
                        ucfirst($kind),
                        $recount
                    ), [
                        'attached_subscribers' => $recount,
                        'force_required'       => true,
                        'raced'                => true,
                    ]);
                }
            }

            $row->delete();
        } catch (\Throwable $e) {
            fluentCrmDb()->rollBack();
            throw $e;
        }

        fluentCrmDb()->commit();

        // Fired after commit so listeners never act on a delete that rolled
        // back. Cleanup::deleteTagAssets() runs here; for the non-force path
        // the assertion above means it has nothing left to purge.
        do_action(self::deletedHook($kind), $deletedId);

        // A deleted row invalidates memoized id/title/slug resolutions.
        MCPHelper::flushSegmentRefCache();

        return [
            'ok'                 => true,
            'action'             => 'delete',
            'kind'               => $kind,
            'deleted_id'         => $deletedId,
            'deleted_title'      => $deletedTitle,
            'detached_subscribers' => $attachedCount,
            'note'               => $attachedCount > 0
                ? __('Deleted with subscribers attached — pivot rows are orphaned and cleaned up by the cleanup hook.', 'fluent-crm')
                : __('Deleted. No subscribers were attached.', 'fluent-crm'),
        ];
    }

    private static function actionMerge($params, $kind)
    {
        $fromIds = isset($params['from_' . $kind . '_ids']) ? (array) $params['from_' . $kind . '_ids'] : [];
        $fromIds = array_values(array_unique(array_filter(array_map('intval', $fromIds))));
        $toId    = (int) ($params['to_' . $kind . '_id'] ?? 0);

        if (!$toId) {
            return MCPHelper::error('invalid_param', __('to_*_id is required for merge', 'fluent-crm'));
        }
        if (!$fromIds) {
            return MCPHelper::error('invalid_param', __('from_*_ids must be a non-empty array of ids', 'fluent-crm'));
        }
        if (count($fromIds) > self::MAX_MERGE_SOURCE_IDS) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: %d: maximum source tag/list ids per merge */
                __('from_*_ids accepts at most %d unique ids per merge.', 'fluent-crm'),
                self::MAX_MERGE_SOURCE_IDS
            ), [
                'max_source_ids' => self::MAX_MERGE_SOURCE_IDS,
                'received'       => count($fromIds),
            ]);
        }
        if (in_array($toId, $fromIds, true)) {
            return MCPHelper::error('invalid_param', __('to_*_id cannot also be in from_*_ids', 'fluent-crm'));
        }

        $to = self::find($toId, $kind);
        if (!$to) {
            return MCPHelper::error('not_found', sprintf(__('Target %s not found', 'fluent-crm'), $kind), ['to_id' => $toId]);
        }

        $modelClass = $kind === 'list' ? Lists::class : Tag::class;
        $fromRows = $modelClass::whereIn('id', $fromIds)->get();
        $foundFromIds = $fromRows->pluck('id')->map('intval')->toArray();
        $missingFromIds = array_values(array_diff($fromIds, $foundFromIds));

        $maxPivotRows = max(1, (int) apply_filters(
            'fluent_crm/mcp_segment_merge_max_pivot_rows',
            self::DEFAULT_MERGE_MAX_PIVOT_ROWS,
            $kind
        ));

        $preflight = self::mergePreflight($foundFromIds, $kind, $maxPivotRows);
        $preflight['found_from_ids'] = $foundFromIds;
        $preflight['missing_from_ids'] = $missingFromIds;

        if (!empty($params['dry_run'])) {
            return [
                'ok'               => true,
                'dry_run'          => true,
                'action'           => 'merge',
                'kind'             => $kind,
                'would_merge_from' => $foundFromIds,
                'would_merge_into' => self::format($to),
                'preflight'        => $preflight,
                'over_cap'         => $preflight['source_pivot_rows'] > $maxPivotRows,
                'note'             => __('Dry run only — no subscriber memberships or source rows were changed. Actual merges are synchronous and are not wrapped in a database transaction.', 'fluent-crm'),
            ];
        }

        if ($preflight['source_pivot_rows'] > $maxPivotRows) {
            $overBy = $preflight['source_pivot_rows'] - $maxPivotRows;

            return MCPHelper::error('limit_exceeded', sprintf(
                /* translators: 1: source pivot rows, 2: unique subscribers, 3: cap, 4: rows over cap, 5: segment kind */
                __('Merge would process %1$d source pivot rows across %2$d unique subscribers; the synchronous cap is %3$d (%4$d rows over). Split from_%5$s_ids into smaller requests whose source_pivot_rows stay within the cap. If one source alone exceeds the cap, use the wp-admin/manual bulk workflow or deliberately raise fluent_crm/mcp_segment_merge_max_pivot_rows.', 'fluent-crm'),
                $preflight['source_pivot_rows'],
                $preflight['unique_subscribers'],
                $maxPivotRows,
                $overBy,
                $kind
            ), $preflight + [
                'over_by' => $overBy,
                'suggested_action' => sprintf(
                    'Split from_%s_ids into smaller requests; dry_run each group first.',
                    $kind
                ),
            ]);
        }

        // Fetch only the bounded work set, then preserve the native
        // attach/detach methods so integration hooks still fire. A subscriber
        // attached to several source segments is loaded once, attached to the
        // target once, and detached from all sources in one call.
        $subscriberWork = self::mergeSubscriberWork($foundFromIds, $kind);
        $totals = self::mergeRepivot($subscriberWork, $foundFromIds, $to, $kind);

        $repivoted        = $totals['repivoted'];
        $uniqueRepivoted  = $totals['unique'];
        $batchesProcessed = $totals['batches'];

        // Converging sweeps. The work set above is a point-in-time read, and
        // segment attachment is one of the highest-frequency writes in the CRM
        // (forms, funnel actions, imports, purchase hooks, other MCP calls).
        // Anything attached to a source AFTER that read is invisible to the
        // loop, and the source delete below would take its pivot row with it —
        // Cleanup::deleteTagAssets() hard-deletes every pivot row for the
        // deleted segment — leaving the contact in neither segment with no
        // error raised. Re-read until nothing new appears, bounded so a site
        // attaching faster than we drain cannot spin here (review PR #2025).
        $sweeps = 0;
        for ($i = 0; $i < self::MERGE_MAX_SWEEPS; $i++) {
            $fresh = self::mergeSubscriberWork($foundFromIds, $kind);
            if (!$fresh) {
                break;
            }
            $sweeps++;
            $sweptTotals = self::mergeRepivot($fresh, $foundFromIds, $to, $kind);
            $repivoted        += $sweptTotals['repivoted'];
            $uniqueRepivoted  += $sweptTotals['unique'];
            $batchesProcessed += $sweptTotals['batches'];
        }

        // Final phase is atomic. Anything attached during the last sweep still
        // holds a source pivot row, so re-point those onto the target before
        // deleting the sources rather than letting the cleanup handler drop
        // them. Deletes are deferred-hook style: the rows go inside the
        // transaction, the actions fire only once it commits.
        fluentCrmDb()->beginTransaction();

        $deferredDeletedIds = [];
        $stragglers = 0;

        try {
            $stragglers = self::mergeRepointStragglers($foundFromIds, (int) $to->id, $kind);

            foreach ($fromRows as $fromRow) {
                $fromId = (int) $fromRow->id;
                $fromRow->delete();
                $deferredDeletedIds[] = $fromId;
            }
        } catch (\Throwable $e) {
            fluentCrmDb()->rollBack();
            throw $e;
        }

        fluentCrmDb()->commit();

        foreach ($deferredDeletedIds as $fromId) {
            do_action(self::deletedHook($kind), $fromId);
        }

        // The merged-away rows invalidate memoized id/title/slug resolutions.
        MCPHelper::flushSegmentRefCache();

        return [
            'ok'                 => true,
            'action'             => 'merge',
            'kind'               => $kind,
            'merged_from'        => $foundFromIds,
            'merged_into'        => self::format($to),
            'subscribers_repivoted' => $repivoted,
            'subscribers_seen'      => $preflight['source_pivot_rows'],
            'unique_subscribers_repivoted' => $uniqueRepivoted,
            'batches_processed'     => $batchesProcessed,
            'concurrent_sweeps'     => $sweeps,
            'stragglers_repivoted'  => $stragglers,
            'preflight'             => $preflight,
            'missing_from_ids'      => $missingFromIds,
            'note'                  => __('Each subscriber attached to a "from" target is now attached to "to" and the "from" rows are deleted. Re-running this merge with the same ids is a safe no-op. Memberships created while the merge was running are picked up by follow-up sweeps, and those still outstanding when the final phase begins are moved to the target directly — preserving the membership but not firing the per-contact attach hooks (see stragglers_repivoted). A write landing inside that final transaction is not covered: FluentCRM has no lock shared with the attach paths, so avoid merging a segment that is actively being written to.', 'fluent-crm'),
        ];
    }

    /**
     * Re-pivot one work set onto the merge target.
     *
     * Uses the native attach/detach methods rather than bulk pivot SQL so the
     * per-contact integration hooks (fluentcrm_contact_added_to_tags and
     * friends) still fire — automations subscribe to those, and a bulk insert
     * would silently stop them mid-merge.
     *
     * @param array  $work      subscriber_id => count of source pivot rows
     * @param array  $sourceIds
     * @param object $to        Merge target row
     * @param string $kind      'tag' or 'list'
     * @return array{repivoted:int, unique:int, batches:int}
     */
    private static function mergeRepivot($work, $sourceIds, $to, $kind)
    {
        $repivoted = 0;
        $unique    = 0;
        $batches   = 0;

        foreach (array_chunk(array_keys($work), self::MERGE_BATCH_SIZE) as $subscriberIds) {
            $batches++;
            $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();
            foreach ($subscribers as $sub) {
                if ($kind === 'list') {
                    $sub->attachLists([$to->id]);
                    $sub->detachLists($sourceIds);
                } else {
                    $sub->attachTags([$to->id]);
                    $sub->detachTags($sourceIds);
                }
                $repivoted += $work[(int) $sub->id] ?? 0;
                $unique++;
            }
        }

        return ['repivoted' => $repivoted, 'unique' => $unique, 'batches' => $batches];
    }

    /**
     * Move any source pivot rows still present onto the merge target.
     *
     * These arrived after the last sweep read, so they never went through
     * attachTags()/attachLists() and their integration hooks do not fire. That
     * is the deliberate trade: the alternative is deleting the source rows and
     * letting Cleanup::deleteTagAssets() take the membership with them, which
     * drops the contact from both segments silently.
     *
     * @param array  $sourceIds
     * @param int    $toId
     * @param string $kind
     * @return int Memberships that would otherwise have been lost.
     */
    private static function mergeRepointStragglers($sourceIds, $toId, $kind)
    {
        if (!$sourceIds) {
            return 0;
        }

        global $wpdb;
        $pivotTable   = $wpdb->prefix . 'fc_subscriber_pivot';
        $objectType   = self::segmentObjectType($kind);
        $placeholders = implode(',', array_fill(0, count($sourceIds), '%d'));

        $subscriberIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT subscriber_id FROM {$pivotTable}
             WHERE object_type = %s AND object_id IN ({$placeholders})",
            array_merge([$objectType], $sourceIds)
        ));

        $subscriberIds = array_values(array_filter(array_map('intval', (array) $subscriberIds)));
        if (!$subscriberIds) {
            return 0;
        }

        // Subscribers already carrying the target membership only need their
        // leftover source rows dropped — re-pointing would duplicate the pair.
        $alreadyOnTarget = [];
        foreach (array_chunk($subscriberIds, self::MERGE_BATCH_SIZE) as $chunk) {
            $chunkPlaceholders = implode(',', array_fill(0, count($chunk), '%d'));
            $found = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT subscriber_id FROM {$pivotTable}
                 WHERE object_type = %s AND object_id = %d AND subscriber_id IN ({$chunkPlaceholders})",
                array_merge([$objectType, $toId], $chunk)
            ));
            foreach ((array) $found as $id) {
                $alreadyOnTarget[(int) $id] = true;
            }
        }

        // Clear every remaining source row in one statement, then grant the
        // target membership to those who did not already have it.
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$pivotTable} WHERE object_type = %s AND object_id IN ({$placeholders})",
            array_merge([$objectType], $sourceIds)
        ));

        $needTarget = array_values(array_filter($subscriberIds, function ($id) use ($alreadyOnTarget) {
            return !isset($alreadyOnTarget[(int) $id]);
        }));

        if (!$needTarget) {
            return 0;
        }

        // Mirrors Subscriber::attachTags()'s insert shape so the rescued rows
        // are indistinguishable from natively attached ones.
        $now = current_time('mysql');
        foreach (array_chunk($needTarget, self::MERGE_BATCH_SIZE) as $chunk) {
            $values = [];
            $args   = [];
            foreach ($chunk as $subscriberId) {
                $values[] = '(%d, %d, %s, %s, %s)';
                array_push($args, (int) $subscriberId, $toId, $objectType, $now, $now);
            }
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$pivotTable} (subscriber_id, object_id, object_type, created_at, updated_at) VALUES " . implode(',', $values),
                $args
            ));
        }

        return count($needTarget);
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    private static function find($id, $kind)
    {
        return $kind === 'list' ? Lists::find($id) : Tag::find($id);
    }

    private static function lookupByTitleOrSlug($title, $slug, $kind)
    {
        $modelClass = $kind === 'list' ? Lists::class : Tag::class;
        return $modelClass::where('title', $title)->orWhere('slug', $slug)->first();
    }

    private static function format($row)
    {
        return [
            'id'          => (int) $row->id,
            'title'       => $row->title,
            'slug'        => $row->slug,
            'description' => $row->description ?? '',
        ];
    }

    private static function attachedSubscriberCount($row, $kind)
    {
        return (int) ($kind === 'list'
            ? $row->subscribers()->count()
            : $row->subscribers()->count());
    }

    /**
     * Return fixed-cost aggregate counts before a merge writes anything.
     */
    private static function mergePreflight($sourceIds, $kind, $maxPivotRows)
    {
        $sourceCounts = [];
        foreach ($sourceIds as $sourceId) {
            $sourceCounts[(int) $sourceId] = 0;
        }

        if (!$sourceIds) {
            return [
                'source_pivot_rows'     => 0,
                'unique_subscribers'    => 0,
                'per_source'            => $sourceCounts,
                'max_source_pivot_rows' => $maxPivotRows,
                'batches_required'      => 0,
            ];
        }

        global $wpdb;
        $pivotTable = $wpdb->prefix . 'fc_subscriber_pivot';
        $objectType = self::segmentObjectType($kind);
        $placeholders = implode(',', array_fill(0, count($sourceIds), '%d'));
        $whereArgs = array_merge([$objectType], $sourceIds);

        $aggregate = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS source_pivot_rows, COUNT(DISTINCT subscriber_id) AS unique_subscribers
             FROM {$pivotTable}
             WHERE object_type = %s AND object_id IN ({$placeholders})",
            $whereArgs
        ));

        $perSourceRows = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, COUNT(*) AS source_pivot_rows
             FROM {$pivotTable}
             WHERE object_type = %s AND object_id IN ({$placeholders})
             GROUP BY object_id",
            $whereArgs
        ));

        foreach ($perSourceRows as $row) {
            $sourceCounts[(int) $row->object_id] = (int) $row->source_pivot_rows;
        }

        $sourcePivotRows = (int) ($aggregate->source_pivot_rows ?? 0);
        $uniqueSubscribers = (int) ($aggregate->unique_subscribers ?? 0);

        return [
            'source_pivot_rows'     => $sourcePivotRows,
            'unique_subscribers'    => $uniqueSubscribers,
            'per_source'            => $sourceCounts,
            'max_source_pivot_rows' => $maxPivotRows,
            'batches_required'      => $uniqueSubscribers
                ? (int) ceil($uniqueSubscribers / self::MERGE_BATCH_SIZE)
                : 0,
        ];
    }

    /**
     * Map subscriber id to its number of source pivot rows. This preserves the
     * legacy subscribers_repivoted/source-row count while hydrating every
     * subscriber at most once.
     */
    private static function mergeSubscriberWork($sourceIds, $kind)
    {
        if (!$sourceIds) {
            return [];
        }

        global $wpdb;
        $pivotTable = $wpdb->prefix . 'fc_subscriber_pivot';
        $objectType = self::segmentObjectType($kind);
        $placeholders = implode(',', array_fill(0, count($sourceIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT subscriber_id, COUNT(*) AS source_pivot_rows
             FROM {$pivotTable}
             WHERE object_type = %s AND object_id IN ({$placeholders})
             GROUP BY subscriber_id
             ORDER BY subscriber_id ASC",
            array_merge([$objectType], $sourceIds)
        ));

        $work = [];
        foreach ($rows as $row) {
            $work[(int) $row->subscriber_id] = (int) $row->source_pivot_rows;
        }

        return $work;
    }

    private static function segmentObjectType($kind)
    {
        return $kind === 'list' ? Lists::class : Tag::class;
    }

    private static function createdHook($kind)
    {
        return $kind === 'list' ? 'fluent_crm/list_created' : 'fluent_crm/tag_created';
    }

    private static function deletedHook($kind)
    {
        return $kind === 'list' ? 'fluent_crm/list_deleted' : 'fluent_crm/tag_deleted';
    }
}
