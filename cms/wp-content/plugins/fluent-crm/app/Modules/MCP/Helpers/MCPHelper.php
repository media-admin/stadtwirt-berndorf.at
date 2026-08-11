<?php

namespace FluentCrm\App\Modules\MCP\Helpers;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Services\ContactsQuery;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\PermissionManager;

/**
 * Shared utilities for FluentCRM MCP tools.
 *
 * Every tool delegates to existing FluentCRM services for business logic. This
 * helper covers concerns *common* to all tools: identifier resolution
 * (id-or-email, id-or-slug), output formatting, universal-filter translation,
 * pagination, content-type sniffing, and structured WP_Error construction.
 *
 * Naming follows MCP_PLAN.md § 7.
 */
class MCPHelper
{
    /**
     * Ceiling on the `emails` lookup array. Matches the 100-row per_page cap,
     * so a single call can return every contact it asked for, and keeps the
     * generated IN() list bounded.
     */
    const MAX_EMAIL_LOOKUP = 100;

    /**
     * Ceiling on a single tags[]/lists[] reference array, so the simple filter
     * shape bounds the same work the advanced one already does via
     * AdvancedFilters::MAX_VALUES_PER_CONDITION.
     */
    const MAX_SEGMENT_REFS = 100;

    /**
     * Per-request memo for resolveSegmentRefs() — see that method.
     */
    private static $segmentRefCache = [];

    // ---------------------------------------------------------------------
    // Identifier resolution
    // ---------------------------------------------------------------------

    /**
     * Resolve a contact from an input array that may carry contact_id or email.
     *
     * @param array $input
     * @return Subscriber|\WP_Error
     */
    public static function resolveContact($input)
    {
        $contactId = isset($input['contact_id']) ? (int) $input['contact_id'] : 0;
        $email     = isset($input['email']) ? sanitize_email($input['email']) : '';

        if ($contactId) {
            $subscriber = Subscriber::find($contactId);
            if (!$subscriber) {
                return self::error('not_found', __('Contact not found', 'fluent-crm'), ['contact_id' => $contactId]);
            }
            return $subscriber;
        }

        if ($email) {
            $subscriber = Subscriber::where('email', $email)->first();
            if (!$subscriber) {
                return self::error('not_found', __('Contact not found', 'fluent-crm'), ['email' => $email]);
            }
            return $subscriber;
        }

        return self::error('invalid_param', __('Provide contact_id or email', 'fluent-crm'));
    }

    /**
     * Batch-resolve tag/list references (ids, titles, or slugs) to integer IDs
     * in a bounded number of queries, reporting resolved ids, newly created
     * rows, and references that matched nothing — all from a SINGLE pass.
     *
     * Replaces the per-item lookups this helper used to do twice over: the
     * validation phase called unresolvedSegmentRefs() and then filter
     * construction called resolveTagIds(), so every reference cost at least
     * two queries and one 100-value advanced condition could fire 200+ before
     * the paginated query even ran (review PR #2025). Callers should take ids
     * and unknown refs from one call rather than re-resolving.
     *
     * Creation delegates to Helper::createNewTags()/createNewLists() — core's
     * check-then-create helpers. They re-check by title and return the
     * existing row rather than inserting a duplicate, which is what keeps
     * tag/list identity single-valued without a DB unique index, and they
     * generate a collision-free slug ("name-1", "name-2") that the inline
     * Tag::create() here previously did not.
     *
     * @param mixed  $items      ids, titles, or slugs (mixed freely)
     * @param string $kind       'tag' or 'list'
     * @param bool   $autoCreate Create refs that match nothing. Caller MUST
     *                           have re-checked `fcrm_manage_contact_cats`.
     * @return array{ids: int[], created: array<int, array{id:int,title:string}>, unknown: array}
     */
    public static function resolveSegmentRefs($items, $kind = 'tag', $autoCreate = false)
    {
        $isList     = $kind === 'list';
        $modelClass = $isList ? Lists::class : Tag::class;

        // Validation and query construction resolve the same refs in separate
        // passes that cannot share a return value — validateUniversalFilter()
        // runs long before buildContactsQueryArgs() — so memoize the read-only
        // verdict for the request, the same way core's Helper::$termTitleCache
        // absorbs repeated title lookups during a bulk import. Creating rows
        // is never served from cache and invalidates it.
        $cacheKey = null;
        if (!$autoCreate) {
            $refs = [];
            foreach ((array) $items as $item) {
                if (!is_array($item) && !is_object($item)) {
                    $refs[] = (string) $item;
                }
            }
            sort($refs);
            $cacheKey = $kind . '|' . implode("\0", $refs);
            if (isset(self::$segmentRefCache[$cacheKey])) {
                return self::$segmentRefCache[$cacheKey];
            }
        }

        // Split the input once. Numeric refs are looked up by id; everything
        // else is matched against title OR slug, mirroring the id|title|slug
        // contract the tools advertise.
        $numericRefs = [];
        $nameRefs    = [];

        foreach ((array) $items as $item) {
            if ($item === '' || $item === null || is_array($item) || is_object($item)) {
                continue;
            }

            if (is_numeric($item)) {
                $numericRefs[(int) $item] = $item;
                continue;
            }

            $value = sanitize_text_field((string) $item);
            if ($value === '') {
                continue;
            }
            // Keyed by value so duplicate refs cost one lookup, not two.
            $nameRefs[$value] = sanitize_title($value);
        }

        $ids     = [];
        $created = [];
        $unknown = [];
        // ref (as normalized by segmentRefKey()) => resolved id. Lets a caller
        // resolve the union of many rows' refs in one call and then assign the
        // right ids per row without going back to the database.
        $map = [];

        // Pass 1 — all numeric ids in one query.
        if ($numericRefs) {
            $foundIds = [];
            foreach ($modelClass::whereIn('id', array_keys($numericRefs))->get() as $row) {
                $foundIds[] = (int) $row->id;
            }
            foreach ($numericRefs as $id => $original) {
                if (in_array($id, $foundIds, true)) {
                    $ids[] = $id;
                    $map[self::segmentRefKey($original)] = $id;
                } else {
                    $unknown[] = $original;
                }
            }
        }

        // Pass 2 — all title/slug refs in one query. Grouped so the OR cannot
        // escape into any condition a caller chains onto the builder later.
        if ($nameRefs) {
            $titles = array_keys($nameRefs);
            $slugs  = array_values($nameRefs);

            $rows = $modelClass::where(function ($query) use ($titles, $slugs) {
                $query->whereIn('title', $titles)->orWhereIn('slug', $slugs);
            })->get();

            // Index the result both ways. Titles are lowercased because the
            // column collation is case-insensitive: the SQL matched "VIP"
            // against a stored "vip", so a case-sensitive PHP comparison here
            // would report a resolved ref as unknown.
            $byTitle = [];
            $bySlug  = [];
            foreach ($rows as $row) {
                $byTitle[strtolower((string) $row->title)] = (int) $row->id;
                $bySlug[(string) $row->slug]               = (int) $row->id;
            }

            $missing = [];
            foreach ($nameRefs as $value => $slug) {
                $lower = strtolower((string) $value);
                if (isset($byTitle[$lower])) {
                    $ids[] = $byTitle[$lower];
                    $map[self::segmentRefKey($value)] = $byTitle[$lower];
                } elseif (isset($bySlug[$slug])) {
                    $ids[] = $bySlug[$slug];
                    $map[self::segmentRefKey($value)] = $bySlug[$slug];
                } elseif ($autoCreate) {
                    $missing[] = $value;
                } else {
                    $unknown[] = $value;
                }
            }

            // Pass 3 — create only what is genuinely missing. Core's helper
            // re-checks by title, so a row created by a concurrent request
            // between pass 2 and here comes back as the existing row instead
            // of becoming a duplicate.
            if ($missing) {
                $newIds = $isList
                    ? Helper::createNewLists($missing)
                    : Helper::createNewTags($missing);

                $newIds = array_values(array_filter(array_map('intval', (array) $newIds)));

                if ($newIds) {
                    // Read the titles back instead of pairing them with
                    // $missing positionally: the core helper omits entries
                    // whose insert failed, which would shift every later title
                    // by one. It also sanitizes and de-collides, so the stored
                    // title is the truthful one to report.
                    foreach ($modelClass::whereIn('id', $newIds)->get() as $row) {
                        $ids[]     = (int) $row->id;
                        $created[] = ['id' => (int) $row->id, 'title' => $row->title];
                        $map[self::segmentRefKey($row->title)] = (int) $row->id;
                        $map[self::segmentRefKey($row->slug)]  = (int) $row->id;
                    }
                }
            }
        }

        $result = [
            'ids'     => array_values(array_unique($ids)),
            'created' => $created,
            'unknown' => array_values(array_unique($unknown)),
            'map'     => $map,
        ];

        if ($created) {
            // New rows invalidate every memoized "unknown" verdict.
            self::$segmentRefCache = [];
        } elseif ($cacheKey !== null) {
            self::$segmentRefCache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Drop the resolveSegmentRefs() memo. Tools that create, rename, or delete
     * tags/lists must call this so a later resolve in the same request does not
     * answer from a stale snapshot.
     */
    public static function flushSegmentRefCache()
    {
        self::$segmentRefCache = [];
    }

    /**
     * Normalize one tag/list reference into the key used by
     * resolveSegmentRefs()'s `map`. Lowercased because the title and slug
     * columns collate case-insensitively, so "VIP" and "vip" must land on the
     * same entry. Callers doing a union resolve must normalize their per-row
     * refs through this same function.
     *
     * @param mixed $ref
     * @return string
     */
    public static function segmentRefKey($ref)
    {
        if (is_numeric($ref)) {
            return (string) (int) $ref;
        }

        return strtolower(sanitize_text_field((string) $ref));
    }

    /**
     * Refuse an oversized tags[]/lists[] array rather than silently truncating,
     * so the caller cannot read a partial filter as a complete one.
     *
     * @param string $property 'tags' or 'lists'
     * @param int    $received
     * @return \WP_Error
     */
    private static function tooManySegmentRefsError($property, $received)
    {
        return self::error('invalid_param', sprintf(
            /* translators: 1: property name, 2: maximum references */
            __('"%1$s" accepts at most %2$d references per call.', 'fluent-crm'),
            $property,
            self::MAX_SEGMENT_REFS
        ), [
            'property' => $property,
            'max_refs' => self::MAX_SEGMENT_REFS,
            'received' => $received,
        ]);
    }

    /**
     * Resolve an array of tag identifiers (ids or titles/slugs) to integer IDs.
     * Optionally creates missing tags when $autoCreate is true (caller MUST
     * have re-checked `fcrm_manage_contact_cats` before passing true).
     *
     * Thin wrapper over resolveSegmentRefs(); prefer that directly when you
     * also need the unresolved refs, so validation and resolution share one
     * set of queries.
     *
     * @param array $items
     * @param bool  $autoCreate
     * @return array{ids: int[], created: array<int, array{id:int,title:string}>, unknown: array}
     */
    public static function resolveTagIds($items, $autoCreate = false)
    {
        return self::resolveSegmentRefs($items, 'tag', $autoCreate);
    }

    /**
     * Same as resolveTagIds() but for lists.
     *
     * @param array $items
     * @param bool  $autoCreate
     * @return array{ids: int[], created: array<int, array{id:int,title:string}>, unknown: array}
     */
    public static function resolveListIds($items, $autoCreate = false)
    {
        return self::resolveSegmentRefs($items, 'list', $autoCreate);
    }

    /**
     * Return the subset of tag/list references that resolve to nothing —
     * numeric ids with no row, and strings matching neither title nor slug.
     * Used by validateUniversalFilter() to fail closed: resolveSegmentRefs()
     * silently drops these, and an empty resolved set reads as "no filter"
     * downstream.
     *
     * @param array  $items
     * @param string $kind 'tag' or 'list'
     * @return array Unresolved references, as passed by the caller.
     */
    public static function unresolvedSegmentRefs($items, $kind = 'tag')
    {
        $resolved = self::resolveSegmentRefs($items, $kind, false);
        return $resolved['unknown'];
    }

    /**
     * Normalize an agent-supplied email list into deduped, lowercased
     * addresses for a whereIn lookup.
     *
     * Lowercasing is deliberate: the email column's default collation is
     * case-insensitive, so the SQL match works either way, but callers diff
     * the request against returned addresses to report misses — comparing
     * "A@X.com" to the stored "a@x.com" would invent a false miss.
     *
     * @param mixed $items
     * @return string[]
     */
    public static function normalizeEmailList($items)
    {
        $emails = [];
        foreach ((array) $items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $email = sanitize_email(trim((string) $item));
            if ($email && is_email($email)) {
                $emails[] = strtolower($email);
            }
        }
        return array_values(array_unique($emails));
    }

    // ---------------------------------------------------------------------
    // Formatting
    // ---------------------------------------------------------------------

    /**
     * Build the rich contact record consumed by get-contact / upsert-contact.
     *
     * @param Subscriber $subscriber
     * @param array      $opts {
     *     @type array $include One or more of: notes, email_history, automations,
     *                          activity, purchase_history, support_tickets,
     *                          info_widgets.
     * }
     * @return array
     */
    public static function formatContactForMCP($subscriber, $opts = [])
    {
        $include = (array) ($opts['include'] ?? []);

        $address = [
            'line_1'      => $subscriber->address_line_1,
            'line_2'      => $subscriber->address_line_2,
            'city'        => $subscriber->city,
            'state'       => $subscriber->state,
            'postal_code' => $subscriber->postal_code,
            'country'     => $subscriber->country,
        ];

        $data = [
            'id'              => (int) $subscriber->id,
            'email'           => $subscriber->email,
            'first_name'      => $subscriber->first_name,
            'last_name'       => $subscriber->last_name,
            'full_name'       => trim((string) $subscriber->full_name),
            'prefix'          => $subscriber->prefix,
            'status'          => $subscriber->status,
            'contact_type'    => $subscriber->contact_type,
            'phone'           => $subscriber->phone,
            'address'         => array_filter($address, function ($v) { return $v !== null && $v !== ''; }),
            'date_of_birth'   => $subscriber->date_of_birth,
            'timezone'        => $subscriber->timezone,
            'source'          => $subscriber->source,
            'avatar'          => $subscriber->avatar,
            // life_time_value intentionally omitted — the fc_subscribers column
            // is never written by free/Pro/FluentCart, so it always reads "0",
            // which an agent treats as a real measurement (MCP feedback
            // 2026-07, P2). Real order values surface via the
            // purchase_history include instead.
            'total_points'    => isset($subscriber->total_points) ? (int) $subscriber->total_points : 0,
            'last_activity'   => self::toIso8601($subscriber->last_activity),
            'created_at'      => self::toIso8601($subscriber->created_at),
        ];

        // Eager-loaded relations: tags, lists.
        $data['tags']  = self::formatTagList($subscriber->tags ?? []);
        $data['lists'] = self::formatListList($subscriber->lists ?? []);

        // Custom fields are inlined for visibility.
        $data['custom_fields'] = self::customFieldsFor($subscriber);

        if ($subscriber->user_id) {
            $data['wp_user'] = [
                'id'       => (int) $subscriber->user_id,
                'edit_url' => admin_url('user-edit.php?user_id=' . (int) $subscriber->user_id),
            ];
            $user = get_user_by('ID', $subscriber->user_id);
            if ($user) {
                $data['wp_user']['roles'] = (array) $user->roles;
            }
        } else {
            $data['wp_user'] = null;
        }

        // Optional includes. Per-collection limits and note body format come
        // from $opts so get-contact can bound token cost (OPT-002).
        if (in_array('notes', $include, true)) {
            $data['notes'] = self::formatNotesFor($subscriber, $opts);
        }
        if (in_array('email_history', $include, true)) {
            $data['email_history'] = self::formatEmailHistoryFor($subscriber, (int) ($opts['email_history_limit'] ?? 10));
        }
        if (in_array('automations', $include, true)) {
            $data['automations'] = self::formatAutomationsFor($subscriber, (int) ($opts['automations_limit'] ?? 25));
        }

        return $data;
    }

    public static function formatContactSummary($subscriber)
    {
        return [
            'id'            => (int) $subscriber->id,
            'email'         => $subscriber->email,
            'first_name'    => $subscriber->first_name,
            'last_name'     => $subscriber->last_name,
            'full_name'     => trim((string) $subscriber->full_name),
            'status'        => $subscriber->status,
            'contact_type'  => $subscriber->contact_type,
            'tags'          => self::formatTagList($subscriber->tags ?? []),
            'lists'         => self::formatListList($subscriber->lists ?? []),
            'country'       => $subscriber->country,
            'city'          => $subscriber->city,
            'source'        => $subscriber->source,
            'last_activity' => self::toIso8601($subscriber->last_activity),
            'created_at'    => self::toIso8601($subscriber->created_at),
        ];
    }

    /**
     * Custom-field values for one contact, preferring the batch-loaded set.
     *
     * ContactsQuery::returnSubscribers() already resolves the custom fields for
     * an ENTIRE page in one meta query and assigns them onto each row as a
     * plain `custom_fields` attribute. Calling the custom_fields() *method*
     * instead re-queries per row and throws that batching away — a full extra
     * query per contact on every list page (100 of them at max per_page).
     *
     * Read the attribute bag directly rather than `$subscriber->custom_fields`:
     * when the value was NOT preloaded, the ORM sees a `custom_fields()` method,
     * treats the key as a relation, and throws because the method returns an
     * array instead of a Relation.
     *
     * @return array
     */
    public static function customFieldsFor($subscriber)
    {
        $attributes = method_exists($subscriber, 'getAttributes') ? $subscriber->getAttributes() : [];
        if (isset($attributes['custom_fields']) && is_array($attributes['custom_fields'])) {
            return $attributes['custom_fields'];
        }

        return (array) $subscriber->custom_fields();
    }

    public static function formatContactList($paginated, $includeCustomFields = false)
    {
        $items = [];
        foreach ($paginated->items() as $subscriber) {
            $item = self::formatContactSummary($subscriber);
            if ($includeCustomFields) {
                $item['custom_fields'] = self::customFieldsFor($subscriber);
            }
            $items[] = $item;
        }

        return [
            'items'    => $items,
            'total'    => (int) $paginated->total(),
            'page'     => (int) $paginated->currentPage(),
            'per_page' => (int) $paginated->perPage(),
            'pages'    => (int) $paginated->lastPage(),
        ];
    }

    public static function formatTagList($tags)
    {
        $out = [];
        foreach ($tags as $tag) {
            $out[] = [
                'id'    => (int) $tag->id,
                'title' => $tag->title,
                'slug'  => $tag->slug,
            ];
        }
        return $out;
    }

    public static function formatListList($lists)
    {
        $out = [];
        foreach ($lists as $list) {
            $out[] = [
                'id'    => (int) $list->id,
                'title' => $list->title,
                'slug'  => $list->slug,
            ];
        }
        return $out;
    }

    /**
     * Shape a rich-text body for MCP output with bounded token cost.
     *
     * @param string $html     Raw stored HTML/rich text.
     * @param string $format   'text' (default), 'html', or 'both'.
     * @param int    $maxChars When > 0, clip each representation to this many
     *                         characters and emit truncated + original_length
     *                         so the agent knows content was cut and can ask
     *                         for more via an explicit param (OPT-002).
     * @return array Keyed fields ready to merge into the output object.
     */
    public static function bodyShapeFor($html, $format = 'text', $maxChars = 0)
    {
        $html     = (string) $html;
        $format   = in_array($format, ['text', 'html', 'both'], true) ? $format : 'text';
        $maxChars = (int) $maxChars;

        $out            = [];
        $truncated      = false;
        $originalLength = 0;

        if ($format === 'text' || $format === 'both') {
            $text = self::htmlToText($html);
            $len  = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
            if ($maxChars > 0 && $len > $maxChars) {
                $text           = function_exists('mb_substr') ? mb_substr($text, 0, $maxChars) : substr($text, 0, $maxChars);
                $truncated      = true;
                $originalLength = max($originalLength, $len);
            }
            $out['description_text'] = $text;
        }

        if ($format === 'html' || $format === 'both') {
            $htmlOut = $html;
            $len     = function_exists('mb_strlen') ? mb_strlen($htmlOut) : strlen($htmlOut);
            if ($maxChars > 0 && $len > $maxChars) {
                $htmlOut        = function_exists('mb_substr') ? mb_substr($htmlOut, 0, $maxChars) : substr($htmlOut, 0, $maxChars);
                $truncated      = true;
                $originalLength = max($originalLength, $len);
            }
            $out['description_html'] = $htmlOut;
        }

        $out['truncated'] = $truncated;
        if ($truncated) {
            $out['original_length'] = $originalLength;
        }

        return $out;
    }

    /**
     * @param object $note
     * @param array  $opts { @type string $body_format; @type int $note_body_max_chars }
     */
    public static function formatNoteForMCP($note, $opts = [])
    {
        $addedBy   = null;
        $createdBy = method_exists($note, 'createdBy') ? $note->createdBy() : null;
        if (is_array($createdBy)) {
            $addedBy = [
                'id'   => (int) $createdBy['ID'],
                'name' => $createdBy['display_name'],
            ];
        }

        $format   = isset($opts['body_format']) ? (string) $opts['body_format'] : 'text';
        $maxChars = isset($opts['note_body_max_chars']) ? (int) $opts['note_body_max_chars'] : 800;

        return array_merge([
            'id'            => (int) $note->id,
            'subscriber_id' => (int) $note->subscriber_id,
            'type'          => $note->type,
            'title'         => $note->title,
        ], self::bodyShapeFor($note->description, $format, $maxChars), [
            'added_by'   => $addedBy,
            'created_at' => self::toIso8601($note->created_at),
        ]);
    }

    /**
     * Return recent notes for a subscriber, bounded for token cost (OPT-002).
     *
     * @param object $subscriber
     * @param array  $opts { @type int $notes_limit (default 10, max 50);
     *                       @type string $body_format; @type int $note_body_max_chars }
     */
    public static function formatNotesFor($subscriber, $opts = [])
    {
        $limit = isset($opts['notes_limit']) ? (int) $opts['notes_limit'] : 10;
        $limit = max(1, min($limit, 50));

        // SubscriberNote already excludes _company_note_ / _system_log_ via a
        // global scope (see Models\SubscriberNote::boot()).
        $notes = SubscriberNote::where('subscriber_id', $subscriber->id)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();

        $formatted = [];
        foreach ($notes as $note) {
            $formatted[] = self::formatNoteForMCP($note, $opts);
        }
        return $formatted;
    }

    /**
     * Recent campaign emails sent to / on behalf of a subscriber, paginated to
     * a small set so heavy installs don't drown the response (per MCP_PLAN
     * § 10.7).
     */
    public static function formatEmailHistoryFor($subscriber, $limit = 10)
    {
        $emails = $subscriber->campaignEmails()
            ->orderBy('id', 'DESC')
            ->limit(max(1, (int) $limit))
            ->get();

        $out = [];
        foreach ($emails as $email) {
            $out[] = [
                'id'             => (int) $email->id,
                'subject'        => $email->email_subject,
                'campaign_id'    => $email->campaign_id ? (int) $email->campaign_id : null,
                'campaign_title' => $email->campaign ? $email->campaign->title : null,
                'status'         => $email->status,
                'is_open'        => !empty($email->is_open),
                'is_clicked'     => isset($email->click_counter) ? ((int) $email->click_counter > 0) : false,
                'sent_at'        => self::toIso8601($email->updated_at),
            ];
        }
        return $out;
    }

    /**
     * Recent automation enrollments for a subscriber, capped for token cost —
     * the collection was previously unbounded (OPT-002).
     *
     * @param object $subscriber
     * @param int    $limit default 25, max 100
     */
    public static function formatAutomationsFor($subscriber, $limit = 25)
    {
        $limit       = max(1, min((int) $limit, 100));
        $automations = $subscriber->funnel_subscribers()->with('funnel')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($automations as $row) {
            if (!$row->funnel) {
                continue;
            }
            $out[] = [
                'funnel_id'         => (int) $row->funnel_id,
                'title'             => $row->funnel->title,
                'status'            => $row->status,
                'last_executed_at'  => self::toIso8601($row->last_executed_time),
                'next_scheduled_at' => self::toIso8601($row->next_execution_time),
                // fc_funnel_subscribers carries BOTH `next_sequence` (the step's
                // ordinal position) and `next_sequence_id` (the fc_funnel_sequences
                // row id). This used to emit the ordinal under the id's name, so an
                // agent feeding it straight into update-contact-automation-status
                // (advance_to_sequence_id) targeted the wrong step or got not_found.
                'next_sequence_id'  => $row->next_sequence_id ? (int) $row->next_sequence_id : null,
                'next_sequence_no'  => $row->next_sequence ? (int) $row->next_sequence : null,
                'enrolled_at'       => self::toIso8601($row->created_at),
            ];
        }
        return $out;
    }

    public static function formatCampaignSummary($campaign, $includeStats = true)
    {
        // Only fill sent_at when the campaign has actually shipped — drafts
        // and pre-send states leave it null (review #30). updated_at is
        // not a reliable proxy: any settings tweak bumps it.
        $sentStatuses = ['archived', 'working', 'paused'];
        $sentAt = in_array($campaign->status, $sentStatuses, true)
            ? self::toIso8601($campaign->updated_at)
            : null;

        $item = [
            'id'              => (int) $campaign->id,
            'title'           => $campaign->title,
            'email_subject'   => $campaign->email_subject,
            'status'          => $campaign->status,
            'design_template' => $campaign->design_template,
            'scheduled_at'    => self::toIso8601($campaign->scheduled_at),
            'sent_at'         => $sentAt,
            'created_at'      => self::toIso8601($campaign->created_at),
        ];

        // One-off sends (include_one_offs=true) have no marketing lifecycle:
        // `status` is written once at creation and never advances, so it can
        // never satisfy $sentStatuses above and `sent_at` stays null even for a
        // delivered email. Flag the row so an agent reads `status`/`sent_at` as
        // "not applicable" rather than "not sent", and knows to call
        // get-campaign for the real delivery state (`one_off_status`). Derived
        // from the already-loaded type column — no extra query, so this stays
        // safe in the default no-stats list path.
        if ($campaign->type === 'custom_email_campaign') {
            $item['kind'] = 'one_off_email';
            $item['note'] = __('One-off send: status/sent_at are not lifecycle values. Call get-campaign for one_off_status and the real sent_at.', 'fluent-crm');
            $item['title'] = self::oneOffTitleForOutput($campaign->title);
        }

        if ($includeStats) {
            $item['stats'] = self::campaignStatsCompact($campaign);
        }

        return $item;
    }

    /**
     * Redact a one-off campaign title for callers without contact-read.
     *
     * One-off titles embed the recipient address by design (see
     * Helper::oneOffEmailTitle) — that is contact PII, but list-campaigns and
     * get-campaign are gated on `fcrm_read_emails` alone. Without this, an
     * email-only manager could enumerate contact addresses by listing one-offs,
     * defeating the same capability separation render-email-preview enforces
     * deliberately (AbilitiesRegistrar: it demands BOTH caps for exactly this
     * reason). PR #2026 review finding 2.
     *
     * The whole title is replaced rather than pattern-stripping the address:
     * send-email-to-contact accepts an arbitrary `title`, so an agent-supplied
     * one can carry PII in any shape a regex would miss.
     *
     * @param string $title
     * @return string
     */
    public static function oneOffTitleForOutput($title)
    {
        if (PermissionManager::currentUserCan('fcrm_read_contacts')) {
            return (string) $title;
        }

        return __('One-off email (title hidden — requires contact read permission)', 'fluent-crm');
    }

    /**
     * Compact stats for a single campaign. Mirrors the per-campaign columns
     * the admin list does (sent/views/clicks via fc_campaign_emails) and
     * pulls unsubscribers from fc_campaign_url_metrics where type='unsubscribe'
     * — there is no is_unsubscribed column on fc_campaign_emails.
     *
     * Anonymous-tracking aware: when the campaign is configured for
     * anonymous click/open tracking, the per-contact columns will read 0
     * even when there's real engagement (the data goes to campaign meta
     * instead). We surface tracking_mode + an aggregate fallback so the
     * agent doesn't mis-diagnose anonymous campaigns as having zero
     * engagement (round-4 review P1 #5).
     */
    public static function campaignStatsCompact($campaign)
    {
        $campaignId = (int) $campaign->id;
        $total      = (int) $campaign->recipients_count;

        $clickStatus = method_exists($campaign, 'getClickTrackingStatus') ? $campaign->getClickTrackingStatus(false) : 'yes';
        $openStatus  = method_exists($campaign, 'getOpenTrackingStatus') ? $campaign->getOpenTrackingStatus(false) : 'yes';

        // Single GROUP-BY-style aggregate over the email table.
        $row = fluentCrmDb()->table('fc_campaign_emails')
            ->where('campaign_id', $campaignId)
            ->selectRaw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent")
            ->selectRaw("SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) as views")
            ->selectRaw("SUM(CASE WHEN click_counter IS NOT NULL THEN 1 ELSE 0 END) as clicks")
            ->first();

        $sent   = (int) ($row->sent ?? 0);
        $views  = (int) ($row->views ?? 0);
        $clicks = (int) ($row->clicks ?? 0);

        // For anonymous tracking, per-contact columns are zero — pull the
        // aggregate counts from campaign meta. open_count is a single int;
        // click count is a serialized map of url => clicks.
        if ($openStatus === 'anonymous') {
            $views = (int) fluentcrm_get_campaign_meta($campaignId, '_ano_open_count', true);
        }
        if ($clickStatus === 'anonymous') {
            $rawUrlClicks = fluentcrm_get_campaign_meta($campaignId, '_ano_url_clicks', true);
            if (is_array($rawUrlClicks)) {
                $clicks = (int) array_sum(array_filter($rawUrlClicks, 'is_numeric'));
            }
        }

        $unsubs = (int) fluentCrmDb()->table('fc_campaign_url_metrics')
            ->where('campaign_id', $campaignId)
            ->where('type', 'unsubscribe')
            ->distinct()
            ->count('subscriber_id');

        return [
            'total'         => $total,
            'sent'          => $sent,
            'views'         => $views,
            'clicks'        => $clicks,
            'unsubscribers' => $unsubs,
            'open_rate'     => $sent ? round($views / max(1, $sent) * 100, 2) : 0,
            'click_rate'    => $sent ? round($clicks / max(1, $sent) * 100, 2) : 0,
            // Anonymous mode aggregates engagement into campaign meta rather
            // than per-contact rows — agents must know which they're seeing.
            'tracking_mode' => [
                'opens'  => $openStatus,
                'clicks' => $clickStatus,
            ],
        ];
    }

    // ---------------------------------------------------------------------
    // Filter translation
    // ---------------------------------------------------------------------

    /**
     * Translate the universal MCP filter shape (MCP_PLAN.md § 3.6) into an
     * array of args ContactsQuery accepts.
     */
    public static function buildContactsQueryArgs($filter)
    {
        $filter = (array) $filter;
        $args   = [];

        if (!empty($filter['search'])) {
            $args['search'] = sanitize_text_field((string) $filter['search']);
            $args['custom_fields'] = true;
        }

        if (!empty($filter['tags'])) {
            $resolved = self::resolveTagIds((array) $filter['tags']);
            $args['tags'] = $resolved['ids'];
        }

        if (!empty($filter['lists'])) {
            $resolved = self::resolveListIds((array) $filter['lists']);
            $args['lists'] = $resolved['ids'];
        }

        if (!empty($filter['statuses'])) {
            $args['statuses'] = array_values(array_filter(
                array_map('sanitize_text_field', (array) $filter['statuses'])
            ));
        }

        if (!empty($filter['sms_statuses'])) {
            $args['sms_statuses'] = array_values(array_filter(
                array_map('sanitize_text_field', (array) $filter['sms_statuses'])
            ));
        }

        if (!empty($filter['contact_ids'])) {
            $args['contact_ids'] = array_values(array_filter(array_map('intval', (array) $filter['contact_ids'])));
        }

        // Direct multi-address lookup. validateUniversalFilter() rejects
        // malformed addresses up front, so this normalize cannot come back
        // empty for a non-empty input. If a caller ever skips that gate, fail
        // CLOSED the same way the advanced_filters branch below does: an empty
        // emails arg reads as "no email filter" to ContactsQuery and would
        // widen to the entire contact base instead of matching nothing.
        if (!empty($filter['emails'])) {
            $emails = self::normalizeEmailList($filter['emails']);
            if ($emails) {
                $args['emails'] = $emails;
            } else {
                $args['contact_ids'] = [0];
            }
        }

        // contact_type is a direct ContactsQuery arg (plain where on the
        // column, applied outside the simple/advanced branch). It must NOT be
        // routed through advanced_filters: that flips filter_type to
        // 'advanced', and ContactsQuery's advanced branch skips the simple
        // tags/lists/statuses/sms_statuses args entirely — so a call like
        // {contact_type: 'lead', tags: [...]} silently dropped the tag filter
        // and returned every lead (list-contacts "tags ignored" bug). It also
        // OR'ed with caller-supplied advanced groups, widening instead of
        // narrowing.
        if (!empty($filter['contact_type'])) {
            $args['contact_type'] = sanitize_text_field((string) $filter['contact_type']);
        }

        // Strictly validated + translated by AdvancedFilters (property →
        // source tuple, tag/list refs resolved to ids). Callers surface the
        // WP_Error via validateUniversalFilter first; if one ever skips that
        // gate, fail CLOSED — pinning contact_ids to the never-existing id 0
        // matches nothing, whereas dropping the filter would match everyone.
        $advanced = [];
        if (!empty($filter['advanced_filters'])) {
            $normalized = AdvancedFilters::normalize($filter['advanced_filters']);
            if (is_wp_error($normalized)) {
                $args['contact_ids'] = [0];
            } else {
                $advanced = $normalized['groups'];
            }
        }

        // Date range filters are applied separately by applyDateFilters()
        // post-construction. Routing them through advanced_filters hits
        // FluentCRM's broken whereTimestamp() phantom method (round-4
        // review P1 #4) which produces nonsensical SQL like
        // `where 'timestamp' = 'created_at'`.

        if (!empty($advanced)) {
            $args['filter_type']        = 'advanced';
            $args['filters_groups_raw'] = $advanced;
        }

        // All fc_subscribers columns. The framework rewrite made orderBy() throw
        // LogicException on column names that don't match ^[a-zA-Z0-9_\.]+$
        // — empty strings, "id ASC", "DROP TABLE", etc. — so an unguarded
        // sort_by would 500 the tool. Schema is stable (migration only adds
        // indexes), so hardcoding the column list avoids a per-request
        // SHOW COLUMNS without restricting agents to the input_schema enum.
        $allowedSortBy = [
            'id', 'user_id', 'hash', 'contact_owner', 'company_id', 'prefix',
            'first_name', 'last_name', 'email', 'timezone', 'address_line_1',
            'address_line_2', 'postal_code', 'city', 'state', 'country', 'ip',
            'latitude', 'longitude', 'total_points', 'life_time_value', 'phone',
            'status', 'contact_type', 'source', 'avatar', 'date_of_birth',
            'created_at', 'last_activity', 'updated_at',
        ];
        $sortBy = sanitize_key((string) ($filter['sort_by'] ?? 'id'));
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'id';
        }
        $args['sort_by'] = $sortBy;
        $sortType = strtoupper(sanitize_text_field((string) ($filter['sort_type'] ?? 'DESC')));
        $args['sort_type'] = in_array($sortType, ['ASC', 'DESC'], true) ? $sortType : 'DESC';

        if (isset($filter['custom_fields']) && $filter['custom_fields']) {
            $args['custom_fields'] = true;
        }

        return $args;
    }

    /**
     * Normalize an agent-supplied date boundary (created_after / created_before)
     * into the naive site-local `Y-m-d H:i:s` string the fc_subscribers datetime
     * columns actually hold. Returns the string, null when absent, or a WP_Error.
     *
     * Passing the raw value straight into the comparison was wrong three ways:
     *
     *   1. Unparseable input reached MySQL verbatim ("Incorrect TIMESTAMP
     *      value: 'garbage'"), surfacing to the agent as an opaque tool failure
     *      instead of a correctable invalid_param — and wpdb echoes an HTML
     *      error block ahead of the JSON envelope on WP_DEBUG_DISPLAY sites.
     *   2. Offset-carrying ISO-8601 — which is exactly what every tool RETURNS,
     *      so it is the obvious round-trip for an agent that just read a
     *      created_at — is only understood in datetime literals by MySQL
     *      8.0.19+. Below that the comparison errors or silently misbehaves.
     *   3. Even where MySQL does parse the offset, the value still has to be
     *      converted to site time to line up with how the column was written.
     *
     * The advanced_filters path already validates dates this strictly
     * (AdvancedFilters::normalizeValue); this brings the simple filters level.
     *
     * @return string|null|\WP_Error
     */
    public static function normalizeDateBoundary($value, $paramName)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Match the WHOLE string, both with and without an offset. Testing only
        // for a trailing offset (stringHasTimezone) is not enough: it is a
        // heuristic for "does this valid datetime carry a zone", so a relative
        // phrase with a time glued on — "yesterday 09:30:00Z", "next monday
        // 09:30+06:00" — passed the check and DateTime happily resolved it into
        // a real moment. An agent typo would silently become a moving relative
        // filter that returns different rows on every call.
        $isoWithTz = (bool) preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $raw);
        $isoNaive  = (bool) preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $raw);

        if (!$isoWithTz && !$isoNaive) {
            return self::dateBoundaryError($paramName, $raw);
        }
        $hasTz = $isoWithTz;

        // The shape can be right while the moment is not, and DateTime silently
        // ROLLS OVER rather than failing — every one of these is a value the
        // agent never sees corrected:
        //   "2024-02-30"          -> March 1   (bad calendar day)
        //   "0000-00-00"          -> year -1   (MySQL zero date; as a
        //                                       created_after bound it matches
        //                                       every contact ever)
        //   "2024-01-01 24:00:00" -> January 2 (hour out of range)
        //   "2024-01-01 23:59:60" -> January 2 (leap-second style input)
        preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $d);
        if (!checkdate((int) $d[2], (int) $d[3], (int) $d[1])) {
            return self::dateBoundaryError($paramName, $raw);
        }

        $h = $i = $s = 0;
        if (preg_match('/[ T](\d{2}):(\d{2})(?::(\d{2}))?/', $raw, $t)) {
            $h = (int) $t[1];
            $i = (int) $t[2];
            $s = isset($t[3]) ? (int) $t[3] : 0;
            if ($h > 23 || $i > 59 || $s > 59) {
                return self::dateBoundaryError($paramName, $raw);
            }
        }

        $siteTz = self::siteTimezoneObject();
        try {
            $dt = $hasTz ? new \DateTime($raw) : new \DateTime($raw, $siteTz);
        } catch (\Exception $e) {
            return self::dateBoundaryError($paramName, $raw);
        }

        // A naive wall-clock time that does not EXIST in the site timezone —
        // the DST spring-forward gap, e.g. 02:30 on a Europe/Berlin March
        // Sunday — is silently advanced an hour by DateTime. Round-trip the
        // components to catch it: the agent gets a correctable error instead of
        // a boundary quietly shifted out from under it.
        if (!$hasTz) {
            $expected = sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int) $d[1], (int) $d[2], (int) $d[3], $h, $i, $s);
            if ($dt->format('Y-m-d H:i:s') !== $expected) {
                return self::dateBoundaryError($paramName, $raw);
            }
        }

        $dt->setTimezone($siteTz);

        return $dt->format('Y-m-d H:i:s');
    }

    private static function dateBoundaryError($paramName, $raw)
    {
        return self::error('invalid_param', sprintf(
            /* translators: %s: parameter name */
            __('%s must be a date: YYYY-MM-DD, "YYYY-MM-DD HH:MM:SS" (site timezone), or a full ISO 8601 timestamp with an offset. Refusing — an unparseable value would reach the database verbatim and fail the whole call.', 'fluent-crm'),
            $paramName
        ), [
            'param'         => $paramName,
            'received'      => $raw,
            'site_timezone' => self::siteTimezoneName(),
        ]);
    }

    /**
     * Apply created_after / created_before to a query model directly. Avoids
     * the whereTimestamp phantom-method bug in
     * Subscriber::applyGeneralFilterQuery (round-4 review P1 #4) — using
     * raw `where(... '>=', ...)` SQL instead.
     *
     * Pass either a ContactsQuery instance (we'll grab getModel()) or an
     * Eloquent query directly.
     */
    public static function applyDateFilters($queryOrCq, $filter)
    {
        if (!is_array($filter)) {
            return $queryOrCq;
        }
        $query = method_exists($queryOrCq, 'getModel') ? $queryOrCq->getModel() : $queryOrCq;
        if (!is_object($query)) {
            return $queryOrCq;
        }

        $map = ['created_after' => '>=', 'created_before' => '<='];
        foreach ($map as $key => $operator) {
            if (empty($filter[$key])) {
                continue;
            }
            $value = self::normalizeDateBoundary($filter[$key], $key);
            // Callers surface this as a WP_Error via validateUniversalFilter
            // first. If one ever skips that gate, fail CLOSED — dropping a date
            // bound would WIDEN the match, which is how an agent ends up
            // mailing an audience it never selected.
            if (is_wp_error($value)) {
                $query->whereRaw('1 = 0');
                continue;
            }
            if ($value !== null) {
                $query->where('created_at', $operator, $value);
            }
        }

        return $queryOrCq;
    }

    /**
     * Build a ContactsQuery directly from the universal filter shape.
     * Callers that paginate must run paginationFromInput() first — it injects
     * page/per_page where the framework paginator resolves them.
     */
    public static function buildContactsQuery($filter)
    {
        $args = self::buildContactsQueryArgs($filter);
        return new ContactsQuery($args);
    }

    /**
     * Validate the universal-filter shape before it's used. Returns
     * `true` on success or a WP_Error (`invalid_param`) on failure.
     *
     * Checks enforced (all fail-closed — a bad value never silently widens
     * the result set):
     *   1. `statuses[]` — must be in fluentcrm_subscriber_statuses().
     *   2. `sms_statuses[]` — must be in fluentcrm_subscriber_sms_statuses().
     *   3. `contact_type` — must be a key in fluentcrm_contact_types().
     *   4. `tags[]` / `lists[]` — every ref must resolve to an existing
     *      tag/list; an unmatched ref would collapse to "no filter" and
     *      return the entire contact base.
     *   5. `advanced_filters` — fully validated per condition (property,
     *      operator, value shape) by AdvancedFilters::normalize() against
     *      the live filter catalog; see get-contact-filter-schema. Also
     *      mutually exclusive with tags/lists/statuses/sms_statuses —
     *      ContactsQuery applies one branch or the other, never both.
     *
     * Operator-test report 2026-05-07 #1 — invalid statuses were being
     * silently dropped by buildContactsQueryArgs(), which made the agent
     * think it was targeting a narrow segment while actually hitting all
     * 12,863 contacts. Round-2 review #3 covered the advanced_filters
     * shape; round-4 review P0 #2 covered the (provider, property) pair.
     */
    public static function validateUniversalFilter($filter)
    {
        if (!is_array($filter) || empty($filter)) {
            return true;
        }

        // 1. statuses[]
        if (!empty($filter['statuses']) && is_array($filter['statuses'])) {
            $allowed = fluentcrm_subscriber_statuses();
            $bad = array_values(array_filter(
                array_map('sanitize_text_field', $filter['statuses']),
                function ($s) use ($allowed) {
                    return $s !== '' && !in_array($s, $allowed, true);
                }
            ));
            if (!empty($bad)) {
                return self::error('invalid_param', __('statuses contains values not in the contact-status enum. Refusing — silently ignoring would widen the audience instead of narrowing it.', 'fluent-crm'), [
                    'unknown_statuses' => $bad,
                    'allowed_statuses' => array_values($allowed),
                ]);
            }
        }

        // 2. sms_statuses[]
        if (!empty($filter['sms_statuses']) && is_array($filter['sms_statuses'])) {
            $allowed = fluentcrm_subscriber_sms_statuses();
            $bad = array_values(array_filter(
                array_map('sanitize_text_field', $filter['sms_statuses']),
                function ($s) use ($allowed) {
                    return $s !== '' && !in_array($s, $allowed, true);
                }
            ));
            if (!empty($bad)) {
                return self::error('invalid_param', __('sms_statuses contains values not in the SMS-status enum.', 'fluent-crm'), [
                    'unknown_sms_statuses' => $bad,
                    'allowed_sms_statuses' => array_values($allowed),
                ]);
            }
        }

        // 3. contact_type
        if (!empty($filter['contact_type'])) {
            $allowed = array_keys(fluentcrm_contact_types());
            $value = sanitize_text_field((string) $filter['contact_type']);
            if (!in_array($value, $allowed, true)) {
                return self::error('invalid_param', __('contact_type is not a registered type.', 'fluent-crm'), [
                    'unknown_contact_type' => $value,
                    'allowed_contact_types' => $allowed,
                ]);
            }
        }

        // 4. tags[] / lists[] — every reference must resolve to an existing
        // tag/list (by id, title, or slug). resolveTagIds() drops unmatched
        // refs, and to ContactsQuery an empty tags arg means "no tag filter
        // at all" — so a typo'd slug silently returned the ENTIRE contact
        // base instead of zero rows. Same widening class as the statuses
        // check above.
        if (!empty($filter['tags']) && is_array($filter['tags'])) {
            if (count($filter['tags']) > self::MAX_SEGMENT_REFS) {
                return self::tooManySegmentRefsError('tags', count($filter['tags']));
            }
            $unknown = self::unresolvedSegmentRefs($filter['tags'], 'tag');
            if ($unknown) {
                return self::error('invalid_param', __('tags contains references that do not match any existing tag by id, title, or slug. Refusing — an unmatched tag filter would silently widen to the full contact list.', 'fluent-crm'), [
                    'unknown_tags' => $unknown,
                    'tip'          => 'Call get-crm-context (top tags) or list-tags for the registry.',
                ]);
            }
        }
        if (!empty($filter['lists']) && is_array($filter['lists'])) {
            if (count($filter['lists']) > self::MAX_SEGMENT_REFS) {
                return self::tooManySegmentRefsError('lists', count($filter['lists']));
            }
            $unknown = self::unresolvedSegmentRefs($filter['lists'], 'list');
            if ($unknown) {
                return self::error('invalid_param', __('lists contains references that do not match any existing list by id, title, or slug. Refusing — an unmatched list filter would silently widen to the full contact list.', 'fluent-crm'), [
                    'unknown_lists' => $unknown,
                    'tip'           => 'Call get-crm-context (top lists) or list-lists for the registry.',
                ]);
            }
        }

        // 5b. emails[] — same widening class as tags/statuses above. Malformed
        // addresses drop out of normalizeEmailList(), and an empty emails arg
        // means "no email filter at all" to ContactsQuery, so one typo'd entry
        // would return the whole contact base instead of a short list. Refuse
        // and name the offenders so the caller can correct them.
        if (!empty($filter['emails'])) {
            if (!is_array($filter['emails'])) {
                return self::error('invalid_param', __('emails must be an array of email addresses.', 'fluent-crm'));
            }

            if (count($filter['emails']) > self::MAX_EMAIL_LOOKUP) {
                return self::error('invalid_param', sprintf(
                    /* translators: %d: maximum number of addresses */
                    __('emails accepts at most %d addresses per call. Split the batch across calls.', 'fluent-crm'),
                    self::MAX_EMAIL_LOOKUP
                ), ['max_emails' => self::MAX_EMAIL_LOOKUP, 'given' => count($filter['emails'])]);
            }

            $bad = array_values(array_filter($filter['emails'], function ($item) {
                return !is_scalar($item) || !self::normalizeEmailList([$item]);
            }));
            if ($bad) {
                return self::error('invalid_param', __('emails contains entries that are not valid email addresses. Refusing — dropping them would widen the query to every contact instead of narrowing it.', 'fluent-crm'), [
                    'invalid_emails' => array_map(function ($item) {
                        return is_scalar($item) ? (string) $item : gettype($item);
                    }, $bad),
                ]);
            }
        }

        // 6. created_after / created_before — an unparseable value would reach
        // MySQL verbatim and fail the call with an opaque database error rather
        // than a correctable invalid_param. See normalizeDateBoundary().
        foreach (['created_after', 'created_before'] as $dateKey) {
            if (empty($filter[$dateKey])) {
                continue;
            }
            $normalized = self::normalizeDateBoundary($filter[$dateKey], $dateKey);
            if (is_wp_error($normalized)) {
                return $normalized;
            }
        }

        $original = $filter['advanced_filters'] ?? null;
        if (!empty($original) && is_array($original)) {
            // 5. advanced_filters is mutually exclusive with the simple
            // segment filters: ContactsQuery applies EITHER the advanced
            // groups OR tags/lists/statuses/sms_statuses, never both, so the
            // simple ones would be silently ignored and widen the result.
            $conflicting = array_values(array_filter(
                ['tags', 'lists', 'statuses', 'sms_statuses'],
                function ($key) use ($filter) {
                    return !empty($filter[$key]);
                }
            ));
            if ($conflicting) {
                return self::error('invalid_param', __('advanced_filters cannot be combined with tags/lists/statuses/sms_statuses — the query engine applies one or the other, so the simple filters would be silently ignored. Express the constraint inside advanced_filters instead (e.g. {property: "segment.tags", operator: "in", value: [tag ids or slugs]}) or drop advanced_filters. search, contact_type, and created_after/before DO combine with advanced_filters.', 'fluent-crm'), [
                    'conflicting_fields' => $conflicting,
                    'tip'                => 'Call get-contact-filter-schema for the full advanced_filters reference.',
                ]);
            }

            // Full per-condition validation (property, operator, value shape)
            // against the live filter catalog — the engine itself fails
            // silently in the widening direction on anything malformed.
            $normalized = AdvancedFilters::normalize($original);
            if (is_wp_error($normalized)) {
                return $normalized;
            }
        }
        return true;
    }

    // ---------------------------------------------------------------------
    // Content handling
    // ---------------------------------------------------------------------

    /**
     * Strip HTML tags, decode entities, collapse whitespace. Keeps anchor URLs
     * inline as `[text](url)` so plain-text consumers don't lose them.
     */
    public static function htmlToText($html)
    {
        if (!$html) {
            return '';
        }

        $text = preg_replace_callback(
            '/<a[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is',
            function ($m) {
                $url   = trim($m[1]);
                $label = trim(wp_strip_all_tags($m[2]));
                if ($label === '' || $label === $url) {
                    return $url;
                }
                return $label . ' (' . $url . ')';
            },
            (string) $html
        );

        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    public static function detectContentType($body)
    {
        $body = (string) $body;
        // Cheap sniff: an early `<` followed by an ASCII letter signals HTML.
        if (preg_match('/<[a-zA-Z]/', substr($body, 0, 200))) {
            return 'html';
        }
        return 'text';
    }

    public static function dualBodyShape($html, $format = 'both')
    {
        $html   = (string) $html;
        $format = in_array($format, ['text', 'html', 'both'], true) ? $format : 'both';
        $out    = [];
        if ($format === 'html' || $format === 'both') {
            $out['body_html'] = $html;
        }
        if ($format === 'text' || $format === 'both') {
            $out['body_text'] = self::htmlToText($html);
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // TRC-006 — strict nested-input validation
    // ---------------------------------------------------------------------

    /**
     * The UTM parameter keys the MCP write contract accepts. Shared by
     * send-email-to-contact and upsert-campaign so their UTM handling can never
     * drift. 'status' is the 0|1 on/off toggle; the rest map to utm_<key>.
     *
     * @return string[]
     */
    public static function utmAllowedKeys()
    {
        return ['status', 'source', 'medium', 'campaign', 'term', 'content'];
    }

    /**
     * Full dotted paths of keys in $object not present in $allowed — names
     * exactly which hallucinated nested keys were rejected.
     *
     * @return string[] e.g. ['address.zip', 'utm.foo']
     */
    public static function unknownKeys($object, array $allowed, $pathPrefix)
    {
        if (!is_array($object)) {
            return [];
        }
        $unknown = [];
        foreach (array_keys($object) as $key) {
            if (!in_array($key, $allowed, true)) {
                $unknown[] = $pathPrefix . '.' . $key;
            }
        }
        return $unknown;
    }

    /**
     * invalid_param error for unrecognized nested keys. The MCP adapter drops
     * WP_Error data over tools/call, so the human-readable MESSAGE embeds both
     * the unknown paths and the allowed keys; details.* still serves direct
     * Ability/REST callers.
     */
    public static function unknownPropertiesError($unknownPaths, array $allowed, $shapeLabel)
    {
        return self::error('invalid_param', sprintf(
            /* translators: 1: shape label, 2: unknown paths, 3: allowed keys */
            __('%1$s contains unrecognized keys: %2$s. Allowed keys: %3$s. Refusing — unknown keys would be silently dropped otherwise.', 'fluent-crm'),
            $shapeLabel,
            implode(', ', $unknownPaths),
            implode(', ', $allowed)
        ), [
            'unknown_properties' => array_values($unknownPaths),
            'allowed'            => array_values($allowed),
        ]);
    }

    /**
     * Validate a closed-shape nested member of $params before any write: when
     * present it must be an object/array with no keys outside $allowed. Returns
     * a WP_Error (naming full paths in the message) or null when valid/absent.
     * A present-but-non-object value hard-errors too, because a direct REST
     * caller bypasses the adapter's schema type check (TRC-006).
     *
     * @return \WP_Error|null
     */
    public static function validateClosedShape($params, $key, array $allowed)
    {
        if (!array_key_exists($key, $params) || $params[$key] === null) {
            return null;
        }
        if (!is_array($params[$key])) {
            return self::error('invalid_param', sprintf(
                /* translators: %s: parameter name */
                __('%s must be an object.', 'fluent-crm'),
                $key
            ), ['param' => $key]);
        }
        $unknown = self::unknownKeys($params[$key], $allowed, $key);
        if ($unknown) {
            return self::unknownPropertiesError($unknown, $allowed, $key);
        }
        return null;
    }

    /**
     * invalid_param error: a value has the wrong type. Names the full path.
     */
    public static function invalidTypeError($path, $expected)
    {
        return self::error('invalid_param', sprintf(
            /* translators: 1: full param path, 2: expected type description */
            __('%1$s must be %2$s.', 'fluent-crm'),
            $path,
            $expected
        ), ['param' => $path]);
    }

    /**
     * invalid_param error: a value is outside its enum. Names the full path.
     */
    public static function enumError($path, array $allowed)
    {
        return self::error('invalid_param', sprintf(
            /* translators: 1: full param path, 2: comma-separated allowed values */
            __('%1$s must be one of: %2$s.', 'fluent-crm'),
            $path,
            implode(', ', $allowed)
        ), ['param' => $path, 'allowed' => $allowed]);
    }

    /**
     * Validate a utm payload for a write tool. Beyond unknown keys, enforces
     * value TYPES so a direct Ability/REST caller (which bypasses the adapter
     * schema) cannot smuggle an array into a utm_* column: status is bool or
     * 0|1, the rest are scalar strings. Returns WP_Error or null (TRC-006).
     *
     * @return \WP_Error|null
     */
    public static function validateUtm($params)
    {
        if (!array_key_exists('utm', $params) || $params['utm'] === null) {
            return null;
        }
        if (!is_array($params['utm'])) {
            return self::invalidTypeError('utm', 'an object');
        }
        $utm = $params['utm'];
        $unknown = self::unknownKeys($utm, self::utmAllowedKeys(), 'utm');
        if ($unknown) {
            return self::unknownPropertiesError($unknown, self::utmAllowedKeys(), 'utm');
        }
        if (array_key_exists('status', $utm) && $utm['status'] !== null) {
            $s = $utm['status'];
            $ok = is_bool($s)
                || (is_int($s) && ($s === 0 || $s === 1))
                || (is_string($s) && in_array($s, ['0', '1'], true));
            if (!$ok) {
                return self::invalidTypeError('utm.status', 'a boolean or 0|1');
            }
        }
        foreach (['source', 'medium', 'campaign', 'term', 'content'] as $key) {
            if (array_key_exists($key, $utm) && $utm[$key] !== null && !is_scalar($utm[$key])) {
                return self::invalidTypeError('utm.' . $key, 'a string');
            }
        }
        return null;
    }

    /**
     * Validate AND sanitize the closed sub-objects of an email/campaign settings
     * payload. Beyond unknown-key rejection, enforces inner types/enums for
     * direct Ability/REST callers (the adapter schema only guards MCP calls) and
     * returns the SANITIZED settings both write tools merge — never the raw
     * params. mailer text fields are sanitize_text_field'd; emails validated via
     * sanitize_email/is_email; is_custom + disable_footer are yes|no;
     * template_config must be an object. Top-level settings + template_config
     * keys stay extensible (TRC-006).
     *
     * @return array|\WP_Error sanitized settings (possibly empty) or error
     */
    public static function sanitizeSettingsShape($settings)
    {
        if ($settings === null) {
            return [];
        }
        if (!is_array($settings)) {
            return self::invalidTypeError('settings', 'an object');
        }

        if (array_key_exists('mailer_settings', $settings) && $settings['mailer_settings'] !== null) {
            $ms = $settings['mailer_settings'];
            if (!is_array($ms)) {
                return self::invalidTypeError('settings.mailer_settings', 'an object');
            }
            $allowed = ['from_name', 'from_email', 'reply_to_name', 'reply_to_email', 'is_custom'];
            $unknown = self::unknownKeys($ms, $allowed, 'settings.mailer_settings');
            if ($unknown) {
                return self::unknownPropertiesError($unknown, $allowed, 'settings.mailer_settings');
            }
            foreach (['from_name', 'reply_to_name'] as $f) {
                if (array_key_exists($f, $ms) && $ms[$f] !== null) {
                    if (!is_scalar($ms[$f])) {
                        return self::invalidTypeError('settings.mailer_settings.' . $f, 'a string');
                    }
                    $ms[$f] = sanitize_text_field((string) $ms[$f]);
                }
            }
            foreach (['from_email', 'reply_to_email'] as $f) {
                if (array_key_exists($f, $ms) && $ms[$f] !== null && $ms[$f] !== '') {
                    if (!is_scalar($ms[$f])) {
                        return self::invalidTypeError('settings.mailer_settings.' . $f, 'a string');
                    }
                    $email = sanitize_email((string) $ms[$f]);
                    if (!is_email($email)) {
                        return self::error('invalid_param', sprintf(
                            /* translators: %s: full param path */
                            __('%s must be a valid email address.', 'fluent-crm'),
                            'settings.mailer_settings.' . $f
                        ), ['param' => 'settings.mailer_settings.' . $f]);
                    }
                    $ms[$f] = $email;
                }
            }
            if (array_key_exists('is_custom', $ms) && $ms['is_custom'] !== null
                && !in_array($ms['is_custom'], ['yes', 'no'], true)) {
                return self::enumError('settings.mailer_settings.is_custom', ['yes', 'no']);
            }
            $settings['mailer_settings'] = $ms;
        }

        if (array_key_exists('footer_settings', $settings) && $settings['footer_settings'] !== null) {
            $fs = $settings['footer_settings'];
            if (!is_array($fs)) {
                return self::invalidTypeError('settings.footer_settings', 'an object');
            }
            $allowed = ['disable_footer'];
            $unknown = self::unknownKeys($fs, $allowed, 'settings.footer_settings');
            if ($unknown) {
                return self::unknownPropertiesError($unknown, $allowed, 'settings.footer_settings');
            }
            if (array_key_exists('disable_footer', $fs) && $fs['disable_footer'] !== null
                && !in_array($fs['disable_footer'], ['yes', 'no'], true)) {
                return self::enumError('settings.footer_settings.disable_footer', ['yes', 'no']);
            }
            $settings['footer_settings'] = $fs;
        }

        if (array_key_exists('template_config', $settings) && $settings['template_config'] !== null
            && !is_array($settings['template_config'])) {
            return self::invalidTypeError('settings.template_config', 'an object');
        }

        return $settings;
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    /**
     * Normalize page/per_page from input and inject them where the framework
     * paginator actually looks for them.
     */
    public static function paginationFromInput($input, $defaultPerPage = 15, $maxPerPage = 100)
    {
        $page    = max(1, (int) ($input['page'] ?? 1));
        $perPage = (int) ($input['per_page'] ?? $defaultPerPage);
        if ($perPage < 1) {
            $perPage = $defaultPerPage;
        }
        $perPage = min($perPage, $maxPerPage);

        // Model::getPerPage() reads $_REQUEST['per_page'] live at paginate time.
        $_REQUEST['page']     = $page;
        $_REQUEST['per_page'] = $perPage;

        // Paginator::resolveCurrentPage(), however, reads `page` from the app
        // Request object — which snapshots $_GET/$_POST at boot. MCP tool
        // arguments arrive nested inside the request body, so `page` never
        // reaches that snapshot and every call resolved to page 1 regardless
        // of input ($_REQUEST mutation alone doesn't help). Set it on the
        // live Request instance too; all() first forces the lazy JSON-body
        // merge so it cannot overwrite the value afterwards.
        $request = FluentCrm('request');
        $request->all();
        $request->set('page', $page)->set('per_page', $perPage);

        return ['page' => $page, 'per_page' => $perPage];
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /**
     * The registered custom-field slugs for contacts.
     *
     * Not memoized: fluentcrm_get_custom_contact_fields() already holds its own
     * static, so this does no DB work, and the remaining slug walk measured
     * 0.08ms across 500 calls — a cache here would only add a stale-value path
     * for no gain.
     *
     * @return string[]
     */
    public static function knownContactCustomFieldSlugs()
    {
        $slugs = [];
        foreach ((array) fluentcrm_get_custom_contact_fields() as $field) {
            if (!empty($field['slug'])) {
                $slugs[] = (string) $field['slug'];
            }
        }
        return $slugs;
    }

    /**
     * Diff caller-supplied custom-field keys against the registered
     * schema. Unknown keys would otherwise be silently dropped by
     * Subscriber::syncCustomFieldValues — the agent thinks the value
     * persisted but nothing was saved (operator-test report 2026-05-07
     * #6).
     *
     * @param  array $customFields
     * @return array{known: array<string,mixed>, unknown: string[]}
     */
    public static function diffCustomFields($customFields)
    {
        $known   = [];
        $unknown = [];
        if (!is_array($customFields) || empty($customFields)) {
            return ['known' => $known, 'unknown' => $unknown];
        }
        $allowed = self::knownContactCustomFieldSlugs();
        foreach ($customFields as $key => $value) {
            $slug = sanitize_key((string) $key);
            if ($slug === '') {
                continue;
            }
            if (in_array($slug, $allowed, true)) {
                $known[$slug] = $value;
            } else {
                $unknown[] = (string) $key;
            }
        }
        return ['known' => $known, 'unknown' => array_values(array_unique($unknown))];
    }

    /**
     * Parse and validate an agent-supplied scheduled_at into a DateTime in
     * the site timezone. Operator-test report 2026-05-07 #3 — previously
     * the validated DateTime was discarded and the raw input string was
     * passed through to MySQL, which silently dropped the offset (a
     * datetime column has no timezone). On read, toIso8601 then re-parsed
     * the naive string in PHP's default timezone (UTC), producing wrong
     * absolute times.
     *
     * Input convention:
     *   - ISO-8601 with offset → respected as written.
     *   - Bare datetime / date  → interpreted as SITE timezone (matches
     *     FluentCRM's storage convention).
     *
     * The caller stores `$dt->format('Y-m-d H:i:s')` which is now
     * unambiguous because `$dt` carries the site tz.
     */
    public static function validateScheduledAt($iso, $minFutureSeconds = 60)
    {
        if (!$iso) {
            return self::error('invalid_param', __('scheduled_at is required', 'fluent-crm'));
        }

        $siteTz = self::siteTimezoneObject();
        $input  = (string) $iso;

        try {
            // If the string carries an explicit offset / "Z", DateTime keeps
            // it. If it's bare ("2026-05-08 09:00:00"), pass site tz as
            // the second arg so the moment is interpreted correctly.
            if (self::stringHasTimezone($input)) {
                $dt = new \DateTime($input);
            } else {
                $dt = new \DateTime($input, $siteTz);
            }
        } catch (\Exception $e) {
            return self::error('invalid_param', __('scheduled_at must be ISO 8601 (e.g. 2026-05-08T09:00:00+01:00) or a bare datetime in site timezone (2026-05-08 09:00:00).', 'fluent-crm'), [
                'scheduled_at_input' => $input,
                'site_timezone'      => $siteTz->getName(),
            ]);
        }

        // Convert to site tz so storage in `Y-m-d H:i:s` is consistent with
        // the rest of FluentCRM (which uses current_time('mysql')).
        $dt->setTimezone($siteTz);

        if ($dt->getTimestamp() < (time() + $minFutureSeconds)) {
            return self::error('validation_failed', __('scheduled_at must be in the future', 'fluent-crm'), [
                'scheduled_at_input' => $input,
                'parsed_utc'         => gmdate('c', $dt->getTimestamp()),
                'parsed_site_local'  => $dt->format('Y-m-d H:i:s'),
                'now_utc'            => gmdate('c'),
                'site_timezone'      => $siteTz->getName(),
                'now_site_local'     => wp_date('Y-m-d H:i:s', time()),
            ]);
        }

        return $dt;
    }

    /**
     * Heuristic: does the string carry timezone info (Z or ±HH:MM / ±HHMM)
     * after the time component? Date-only strings always count as bare.
     */
    private static function stringHasTimezone($s)
    {
        return (bool) preg_match('/T?\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', trim((string) $s));
    }

    /**
     * Site timezone as a DateTimeZone — the object form callers need for
     * DateTime construction / setTimezone. wp_timezone() ships in WP 5.3+
     * (we target 6.9+).
     */
    public static function siteTimezoneObject()
    {
        return wp_timezone();
    }

    /**
     * Format a stored mysql datetime (assumed in site tz) into the dual
     * shape callers expose to agents — get-campaign / actionSchedule
     * surface this so an operator never has to guess which timezone a
     * scheduled_at value is in.
     *
     * @return array{utc:?string, site_local:?string, site_timezone:string}|null
     */
    public static function formatScheduledAtDual($value)
    {
        if (!$value) {
            return null;
        }
        $siteTz = self::siteTimezoneObject();

        try {
            // Stored values come from current_time('mysql') / our own
            // $dt->format('Y-m-d H:i:s') — both are site-tz strings.
            // ISO inputs from outside are unlikely here but tolerated.
            if ($value instanceof \DateTimeInterface) {
                $dt = (new \DateTime('@' . $value->getTimestamp()))->setTimezone($siteTz);
            } elseif (self::stringHasTimezone((string) $value)) {
                $dt = (new \DateTime((string) $value))->setTimezone($siteTz);
            } else {
                $dt = new \DateTime((string) $value, $siteTz);
            }
        } catch (\Exception $e) {
            return null;
        }

        return [
            'utc'           => gmdate('c', $dt->getTimestamp()),
            'site_local'    => $dt->format('Y-m-d H:i:s'),
            'site_timezone' => self::siteTimezoneName(),
        ];
    }

    /**
     * Friendly site timezone label. wp_timezone() returns a numeric offset
     * like "+00:00" when gmt_offset is 0 and timezone_string is empty;
     * fluentCrmGetTimezoneString() correctly returns "UTC" in that case.
     */
    public static function siteTimezoneName()
    {
        return (string) fluentCrmGetTimezoneString();
    }

    public static function permissionGuard($cap)
    {
        if (PermissionManager::currentUserCan($cap)) {
            return true;
        }
        return self::error('forbidden', __('You do not have permission to perform this action', 'fluent-crm'), ['required' => $cap]);
    }

    // ---------------------------------------------------------------------
    // Errors
    // ---------------------------------------------------------------------

    public static function error($code, $message, $details = [])
    {
        return new \WP_Error($code, $message, $details);
    }

    // ---------------------------------------------------------------------
    // Misc
    // ---------------------------------------------------------------------

    /**
     * Format a stored datetime as ISO 8601 with a TRUTHFUL offset.
     *
     * FluentCRM stores naive `Y-m-d H:i:s` strings in SITE time (models use
     * freshTimestamp() / current_time('mysql')), but WordPress forces PHP's
     * default timezone to UTC. Parsing those strings without an explicit
     * timezone therefore stamped every created_at / last_activity / sent_at /
     * enrolled_at with `+00:00`, handing the agent a moment that is wrong by
     * the site's UTC offset — and any "in the last 24 hours" reasoning with it.
     * scheduled_at already dodged this via formatScheduledAtDual(); this closes
     * the same gap everywhere else.
     *
     * Strings that carry their own offset (or "Z") are self-describing and are
     * respected as written.
     */
    public static function toIso8601($value)
    {
        if (!$value) {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('c');
            }
            $value = (string) $value;
            $dt = self::stringHasTimezone($value)
                ? new \DateTime($value)
                : new \DateTime($value, self::siteTimezoneObject());

            return $dt->format('c');
        } catch (\Exception $e) {
            return null;
        }
    }
}
