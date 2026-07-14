<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Services\PermissionManager;

/**
 * Tag/list management — round-3 review item #11.
 *
 * `manage-tag` and `manage-list` cover create, update, delete, and merge
 * operations. Splitting create/update/delete into separate tools would
 * triple the surface; the action enum keeps it compact while the
 * `destructive` annotation tells MCP clients to confirm delete + merge.
 *
 * Merge semantics: re-pivot every subscriber attached to a `from` tag/list
 * onto the `to` target, then delete the `from` rows. Idempotent — running
 * the same merge twice no-ops on the second call (subscribers are already
 * pivoted, sources already deleted).
 */
class SegmentTools
{
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

        $row->save();

        return [
            'ok'      => true,
            'action'  => 'update',
            'kind'    => $kind,
            $kind     => self::format($row),
            'changes' => $changes,
        ];
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
        $row->delete();
        do_action(self::deletedHook($kind), $deletedId);

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

        // Re-pivot subscribers attached to the `from` set onto the `to` target.
        $attachedCount = 0;
        foreach ($fromRows as $fromRow) {
            $count = self::attachedSubscriberCount($fromRow, $kind);
            $attachedCount += $count;
        }

        $repivoted = 0;
        foreach ($fromRows as $fromRow) {
            $subscribers = self::attachedSubscribers($fromRow, $kind);
            foreach ($subscribers as $sub) {
                if ($kind === 'list') {
                    $sub->attachLists([$to->id]);
                    $sub->detachLists([$fromRow->id]);
                } else {
                    $sub->attachTags([$to->id]);
                    $sub->detachTags([$fromRow->id]);
                }
                $repivoted++;
            }
        }

        // Delete the source rows.
        foreach ($fromRows as $fromRow) {
            $fromId = (int) $fromRow->id;
            $fromRow->delete();
            do_action(self::deletedHook($kind), $fromId);
        }

        return [
            'ok'                 => true,
            'action'             => 'merge',
            'kind'               => $kind,
            'merged_from'        => $foundFromIds,
            'merged_into'        => self::format($to),
            'subscribers_repivoted' => $repivoted,
            'subscribers_seen'      => $attachedCount,
            'missing_from_ids'      => $missingFromIds,
            'note'                  => __('Each subscriber attached to a "from" target is now attached to "to" and the "from" rows are deleted. Re-running this merge with the same ids is a safe no-op.', 'fluent-crm'),
        ];
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

    private static function attachedSubscribers($row, $kind)
    {
        return $kind === 'list'
            ? $row->subscribers()->get()
            : $row->subscribers()->get();
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
