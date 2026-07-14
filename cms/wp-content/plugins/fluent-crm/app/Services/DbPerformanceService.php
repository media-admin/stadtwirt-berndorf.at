<?php

namespace FluentCrm\App\Services;

/**
 * DbPerformanceService
 *
 * Health-checks and repairs the small set of "critical" indexes that the
 * hot-path queries (campaign sending, automation runner, dedupe-protecting
 * unique keys) depend on.
 *
 * Why this exists: index creation runs on plugin activation / DB version bumps.
 * On large or busy tables an ALTER can fail mid-flight (lock-wait timeout,
 * killed request, insufficient privilege, a "Duplicate entry" left behind by
 * un-deduped rows). When that happens the DB version option is still bumped, so
 * the migrator never retries and the index stays missing — silently degrading
 * performance or, for the unique keys, allowing duplicate rows to accumulate.
 *
 * This service is the single source of truth for how each critical index is
 * built (sweep + dedupe + online-or-plain ALTER). The relevant migrations
 * delegate to ensureCriticalIndex() instead of carrying their own copy of that
 * logic, and the runtime path (getIndexHealth/repairIndex) uses the same code
 * to detect drift on load — via a lightweight cached check — and heal it on
 * demand. Migration and runtime therefore can never diverge.
 */
class DbPerformanceService
{
    /**
     * Build the health status for every critical index.
     *
     * @param bool $fromDb When false, serve from the cached snapshot if it is
     *                      complete (covers every known index). When the cache
     *                      is missing any index it is treated as stale and a
     *                      live DB check is performed. Pass true to always hit
     *                      the DB (e.g. right after a repair).
     * @return array<string, array> Index name => meta + 'status' ('yes'|'no').
     */
    public static function getIndexHealth($fromDb = true)
    {
        $indexActions = self::getCriticalIndexLists();

        if (!$fromDb) {
            $existing = fluentcrm_get_option('_db_index_health', []);
            $broken = false;
            if ($existing) {
                foreach ($indexActions as $indexName => $indexData) {
                    if (!isset($existing[$indexName])) {
                        // A known index is absent from the cache — snapshot is
                        // stale (e.g. a new critical index was introduced), so
                        // fall through to a fresh DB check below.
                        $broken = true;
                        continue;
                    }
                    $indexData['name'] = $indexName;
                    $indexData['status'] = $existing[$indexName];
                    $indexActions[$indexName] = $indexData;
                }

                if (!$broken) {
                    return $indexActions;
                }
            }
        }

        global $wpdb;

        // Group required indexes by table so we run a single SHOW INDEX per table.
        $formattedIndex = [];
        foreach ($indexActions as $indexName => $indexData) {
            if (!isset($formattedIndex[$indexData['table']])) {
                $formattedIndex[$indexData['table']] = [];
            }
            $formattedIndex[$indexData['table']][$indexName] = $indexData;
        }

        $indexedResults = [];
        $cachedData = [];

        foreach ($formattedIndex as $tableName => $requiredIndexes) {
            $table = $wpdb->prefix . $tableName;

            // Full per-key structure (uniqueness + ordered columns + prefix
            // lengths), so a same-named index with the WRONG columns/order is
            // reported broken rather than silently accepted as healthy.
            $info = self::getIndexInfo($table);

            foreach ($requiredIndexes as $indexName => $indexData) {
                $status = self::indexIsHealthy($info, $indexName, $indexData) ? 'yes' : 'no';
                $indexData['name'] = $indexName;
                $indexData['status'] = $status;
                $indexedResults[$indexName] = $indexData;
                $cachedData[$indexName] = $status;
            }
        }

        // Preserve the original ordering defined in getCriticalIndexLists().
        $ordered = [];
        foreach ($indexActions as $indexName => $indexData) {
            if (isset($indexedResults[$indexName])) {
                $ordered[$indexName] = $indexedResults[$indexName];
            }
        }

        fluentcrm_update_option('_db_index_health', $cachedData);

        return $ordered;
    }

    /**
     * Whether any critical index is currently missing.
     *
     * @param bool $fromDb See getIndexHealth(). Defaults to the cached check so
     *                     it is cheap to call on every app load.
     * @return bool
     */
    public static function hasBrokenIndex($fromDb = false)
    {
        foreach (self::getIndexHealth($fromDb) as $indexData) {
            if ($indexData['status'] !== 'yes') {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure a single critical index exists (and, for unique keys, is actually
     * unique), creating it with the appropriate cleanup if not.
     *
     * This is the single source of truth for how each critical index is built.
     * It is intentionally low-level — it touches only $wpdb (no option store, no
     * WP_Error), so it is safe to call from the migration classes during plugin
     * activation, before the framework is fully booted. repairIndex() wraps this
     * with verification and health-cache refresh for the runtime/AJAX path.
     *
     * For the uniqueness constraints the table is swept + deduped first; without
     * it the ADD UNIQUE KEY fails with "Duplicate entry" on any table that
     * accumulated dupes while the constraint was absent.
     *
     * @param string $indexName One of the keys from getCriticalIndexLists().
     * @return bool True if the index is known and the ensure ran (it may still
     *              no-op when already present); false for an unknown index.
     */
    public static function ensureCriticalIndex($indexName)
    {
        global $wpdb;

        $indexLists = self::getCriticalIndexLists();
        if (!isset($indexLists[$indexName])) {
            return false;
        }

        $index = $indexLists[$indexName];
        $table = $wpdb->prefix . $index['table'];

        if ($index['type'] === 'unique') {
            self::ensureUniqueKey($table, $indexName, $index);
        } else {
            self::ensurePlainIndex($table, $indexName, $index['columns']);
        }

        return true;
    }

    /**
     * Repair a single critical index from the runtime/AJAX path.
     *
     * Delegates the actual DDL to ensureCriticalIndex(), then verifies against
     * the live DB and refreshes the health cache so the caller and any later
     * cached read both see the true post-repair state.
     *
     * @param string $indexName One of the keys from getCriticalIndexLists().
     * @return array|\WP_Error The refreshed index record on success, WP_Error
     *                         if the index is unknown or still missing after
     *                         the repair attempt.
     */
    public static function repairIndex($indexName)
    {
        if (!self::ensureCriticalIndex($indexName)) {
            return new \WP_Error(
                'invalid_index',
                __('The provided index is not a known critical index.', 'fluent-crm')
            );
        }

        global $wpdb;
        // Capture the ALTER's error before the verification queries below reset
        // $wpdb->last_error, so a privilege/lock failure can still be surfaced.
        $repairError = $wpdb->last_error;

        // Verify against the live DB (and refresh the cache).
        $health = self::getIndexHealth(true);

        if (!isset($health[$indexName]) || $health[$indexName]['status'] !== 'yes') {
            return new \WP_Error(
                'repair_failed',
                sprintf(
                    /* translators: %s: index name */
                    __('Could not create the "%s" index. Please check your database user privileges or repair it manually.', 'fluent-crm'),
                    $indexName
                ),
                ['db_error' => $repairError]
            );
        }

        return $health[$indexName];
    }

    /**
     * Repair every missing critical index in one pass.
     *
     * @return array{repaired: string[], failed: string[], health: array}
     */
    public static function repairBrokenIndexes()
    {
        $health = self::getIndexHealth(true);

        $repaired = [];
        $failed = [];
        foreach ($health as $indexName => $indexData) {
            if ($indexData['status'] === 'yes') {
                continue;
            }

            $result = self::repairIndex($indexName);
            if (is_wp_error($result)) {
                $failed[] = $indexName;
            } else {
                $repaired[] = $indexName;
            }
        }

        return [
            'repaired' => $repaired,
            'failed'   => $failed,
            'health'   => self::getIndexHealth(false), // freshly cached by the calls above
        ];
    }

    /**
     * Ensure a plain (non-unique) composite index exists with exactly the
     * expected columns. A same-named index whose column list/order/prefix has
     * drifted is dropped and recreated, so the performance assumption it backs
     * actually holds — name-presence alone is not enough.
     *
     * @param string $table     Fully-prefixed table name.
     * @param string $indexName Key name to create.
     * @param array  $columns   Expected column spec — see getCriticalIndexLists().
     * @return void
     */
    private static function ensurePlainIndex($table, $indexName, array $columns)
    {
        $info = self::getIndexInfo($table);
        $columnSql = self::buildColumnSql($columns);

        if (isset($info[$indexName])) {
            if (self::indexColumnsMatch($info[$indexName]['columns'], $columns)) {
                return; // present with the right columns — nothing to do.
            }
            // Same name, wrong columns — replace it in one statement.
            self::alterWithOnlineFallback($table, "DROP INDEX `{$indexName}`, ADD INDEX `{$indexName}` ({$columnSql})");
            return;
        }

        self::alterWithOnlineFallback($table, "ADD INDEX `{$indexName}` ({$columnSql})");
    }

    /**
     * Add a UNIQUE KEY, cleaning the table first so the ALTER can't fail with
     * "Duplicate entry".
     *
     * This is the canonical convergence logic the owning migrations delegate to:
     * a key of the same name that exists but is non-unique OR has drifted columns
     * is dropped and re-added; offending rows are swept/deduped before the ALTER
     * runs. The cleanup differs per table, so each has its own dedicated method
     * ('cleanup' names which one). If cleanup fails (lock-wait timeout, missing
     * privilege) we skip the ALTER rather than let it throw — the caller's
     * verification step reports the index as still broken and a later attempt
     * can retry.
     *
     * @param string $table     Fully-prefixed table name.
     * @param string $indexName Unique key name to create.
     * @param array  $index     Index descriptor from getCriticalIndexLists().
     * @return void
     */
    private static function ensureUniqueKey($table, $indexName, array $index)
    {
        $info = self::getIndexInfo($table);
        $existing = isset($info[$indexName]) ? $info[$indexName] : null;

        // Already a correct unique key (unique AND the right columns) — done.
        if ($existing
            && $existing['non_unique'] === 0
            && self::indexColumnsMatch($existing['columns'], $index['columns'])) {
            return;
        }

        // Run the table-specific cleanup. Each method sweeps garbage rows and
        // removes duplicates with the survivor rule appropriate for that table.
        $clean = false;
        switch ($index['cleanup']) {
            case 'subscriber_pivot':
                $clean = self::cleanupSubscriberPivot($table);
                break;
            case 'funnel_subscribers':
                $clean = self::cleanupFunnelSubscribers($table);
                break;
            case 'funnel_metrics':
                $clean = self::cleanupFunnelMetrics($table);
                break;
        }

        if (!$clean) {
            return; // cleanup failed — don't risk a "Duplicate entry" ALTER.
        }

        $columnSql = self::buildColumnSql($index['columns']);

        if ($existing) {
            // Same name but non-unique or wrong columns — replace it in one statement.
            self::alterWithOnlineFallback($table, "DROP INDEX `{$indexName}`, ADD UNIQUE KEY `{$indexName}` ({$columnSql})");
        } else {
            self::alterWithOnlineFallback($table, "ADD UNIQUE KEY `{$indexName}` ({$columnSql})");
        }
    }

    /**
     * Run an index ALTER, preferring a non-blocking online build but always
     * falling back to a plain ALTER so the index is created on any server.
     *
     * Why both: ALGORITHM=INPLACE, LOCK=NONE lets the table stay readable AND
     * writable during the build, which avoids stalling live traffic (e.g. the
     * email pipeline on a multi-million-row fc_campaign_emails). But it is not
     * universally available — the ALGORITHM/LOCK syntax did not exist before
     * MySQL 5.6 / MariaDB 10.0 (where it is a hard parse error), and some
     * engines/operations can't satisfy LOCK=NONE. FluentCRM runs on WordPress
     * hosts we don't control, and these keys — the unique ones especially — are
     * a correctness dependency for the app (INSERT IGNORE / firstOrCreate rely
     * on them). So we try online first, then retry plain: a brief write-block on
     * an old server is an acceptable price for a key that actually gets created.
     *
     * @param string $table      Fully-prefixed table name.
     * @param string $operations The ALTER body, e.g. "ADD UNIQUE KEY `x` (...)"
     *                           or "DROP INDEX `x`, ADD UNIQUE KEY `x` (...)".
     * @return int|bool Result of the successful query, or false if both failed.
     */
    private static function alterWithOnlineFallback($table, $operations)
    {
        global $wpdb;

        // Attempt the non-blocking online build first.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query("ALTER TABLE {$table} {$operations}, ALGORITHM=INPLACE, LOCK=NONE");
        if ($result !== false) {
            return $result;
        }

        // Online DDL unavailable (old server, FK/engine constraint, etc.) — retry
        // with a plain ALTER that works everywhere, even if it briefly blocks
        // writes. If this also fails (real duplicate, no privilege) the caller's
        // verification reports the index missing and a later run can retry.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->query("ALTER TABLE {$table} {$operations}");
    }

    /**
     * Cleanup for fc_subscriber_pivot ahead of the subscriber_object_type_unique
     * key, which enforces uniqueness on (subscriber_id, object_id, object_type).
     *
     * Sweeps rows where subscriber_id or object_id is NULL or 0 — these are
     * never a valid list/tag relationship (they come from past writer bugs that
     * lacked a real id) and would otherwise survive into the unique key and
     * block legitimate future writes. The schema declares both columns
     * BIGINT UNSIGNED NOT NULL, but we still guard IS NULL so the repair is
     * correct even on an older live table whose definition has drifted.
     *
     * Then keeps the lowest id per (subscriber_id, object_id, object_type) group
     * so the survivor has the earliest created_at ("we already had this
     * attachment"). (SubscriberPivot::migrate() delegates here.)
     *
     * @param string $table Fully-prefixed fc_subscriber_pivot table name.
     * @return bool True if the table is safe to receive the unique key.
     */
    private static function cleanupSubscriberPivot($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DELETE FROM {$table} WHERE subscriber_id IS NULL OR subscriber_id = 0 OR object_id IS NULL OR object_id = 0");

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hasDuplicates = (int) $wpdb->get_var("SELECT COUNT(*) FROM (
            SELECT subscriber_id, object_id, object_type
            FROM {$table}
            GROUP BY subscriber_id, object_id, object_type
            HAVING COUNT(*) > 1
        ) dups");

        if ($hasDuplicates <= 0) {
            return true;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query("DELETE sp FROM {$table} sp
            INNER JOIN (
                SELECT subscriber_id, object_id, object_type, MIN(id) as keep_id
                FROM {$table}
                GROUP BY subscriber_id, object_id, object_type
                HAVING COUNT(*) > 1
            ) dups ON sp.subscriber_id = dups.subscriber_id
                AND sp.object_id = dups.object_id
                AND sp.object_type = dups.object_type
                AND sp.id != dups.keep_id");

        return $deleted !== false;
    }

    /**
     * Cleanup for fc_funnel_subscribers ahead of the funnel_subscriber_idx key.
     *
     * Sweeps rows with NULL/0 funnel_id or subscriber_id, then dedupes keeping
     * the row that has progressed FURTHEST through the funnel rather than the
     * newest row. This is a state table, not a pure pivot: a naive MAX(id)
     * survivor can discard a row that already executed sequences in favour of a
     * newer, less advanced duplicate, which then re-fires sequences the contact
     * already received. Survivor rule: highest IFNULL(last_sequence_id, 0) wins,
     * tiebroken by MAX(id). The IFNULL is essential — last_sequence_id is NULL
     * for freshly enrolled contacts, and SQL's three-valued logic would
     * otherwise match no survivor for all-NULL groups, leaving the dupes in
     * place and failing the ALTER. (FunnelSubscribers::migrate() delegates here.)
     *
     * @param string $table Fully-prefixed fc_funnel_subscribers table name.
     * @return bool True if the table is safe to receive the unique key.
     */
    private static function cleanupFunnelSubscribers($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DELETE FROM {$table} WHERE funnel_id IS NULL OR funnel_id = 0 OR subscriber_id IS NULL OR subscriber_id = 0");

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hasDuplicates = (int) $wpdb->get_var("SELECT COUNT(*) FROM (
            SELECT funnel_id, subscriber_id
            FROM {$table}
            GROUP BY funnel_id, subscriber_id
            HAVING COUNT(*) > 1
        ) dups");

        if ($hasDuplicates <= 0) {
            return true;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query("DELETE fs FROM {$table} fs
            INNER JOIN (
                SELECT t1.funnel_id, t1.subscriber_id, MAX(t1.id) as keep_id
                FROM {$table} t1
                INNER JOIN (
                    SELECT funnel_id, subscriber_id, MAX(IFNULL(last_sequence_id, 0)) as max_seq
                    FROM {$table}
                    GROUP BY funnel_id, subscriber_id
                    HAVING COUNT(*) > 1
                ) dups ON t1.funnel_id = dups.funnel_id
                    AND t1.subscriber_id = dups.subscriber_id
                    AND IFNULL(t1.last_sequence_id, 0) = dups.max_seq
                GROUP BY t1.funnel_id, t1.subscriber_id
            ) survivors ON fs.funnel_id = survivors.funnel_id
                AND fs.subscriber_id = survivors.subscriber_id
                AND fs.id != survivors.keep_id");

        return $deleted !== false;
    }

    /**
     * Cleanup for fc_funnel_metrics ahead of the funnel_seq_subscriber_unique
     * key, which enforces uniqueness on (funnel_id, sequence_id, subscriber_id).
     *
     * Sweeps rows where any of the three key columns is NULL or 0 — a metric is
     * only meaningful when tied to a real funnel, sequence and subscriber, so
     * such rows are invalid leftovers. They are also collapsed into a single
     * GROUP BY bucket by the dedupe below, so removing them up front keeps the
     * survivor selection clean. (All three are BIGINT UNSIGNED NULL in the
     * schema, so both NULL and 0 are reachable.)
     *
     * Then keeps the latest entry (MAX id) per (funnel_id, sequence_id,
     * subscriber_id) group. (FunnelMetrics::migrate() delegates here; this adds
     * the NULL/0 pre-sweep the old inline migration lacked.)
     *
     * @param string $table Fully-prefixed fc_funnel_metrics table name.
     * @return bool True if the table is safe to receive the unique key.
     */
    private static function cleanupFunnelMetrics($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DELETE FROM {$table} WHERE funnel_id IS NULL OR funnel_id = 0 OR sequence_id IS NULL OR sequence_id = 0 OR subscriber_id IS NULL OR subscriber_id = 0");

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hasDuplicates = (int) $wpdb->get_var("SELECT COUNT(*) FROM (
            SELECT funnel_id, sequence_id, subscriber_id
            FROM {$table}
            GROUP BY funnel_id, sequence_id, subscriber_id
            HAVING COUNT(*) > 1
        ) dups");

        if ($hasDuplicates <= 0) {
            return true;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query("DELETE fm FROM {$table} fm
            INNER JOIN (
                SELECT funnel_id, sequence_id, subscriber_id, MAX(id) AS keep_id
                FROM {$table}
                GROUP BY funnel_id, sequence_id, subscriber_id
                HAVING COUNT(*) > 1
            ) dups ON fm.funnel_id = dups.funnel_id
                AND fm.sequence_id = dups.sequence_id
                AND fm.subscriber_id = dups.subscriber_id
                AND fm.id != dups.keep_id");

        return $deleted !== false;
    }

    /**
     * Read every index on a table into a structured map keyed by index name.
     *
     * SHOW INDEX emits one row per indexed column; we fold them into:
     *   [ Key_name => [
     *       'non_unique' => 0|1,
     *       'columns'    => [ ['name' => ..., 'sub_part' => int|null], ... ]  // ordered by Seq_in_index
     *   ] ]
     * so callers can verify the full column sequence and prefix lengths, not
     * just the index name.
     *
     * @param string $table Fully-prefixed table name.
     * @return array<string, array{non_unique:int, columns:array}>
     */
    private static function getIndexInfo($table)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SHOW INDEX FROM {$table}");
        if (!$rows) {
            return [];
        }

        $info = [];
        foreach ($rows as $row) {
            $name = $row->Key_name;
            if (!isset($info[$name])) {
                $info[$name] = ['non_unique' => (int) $row->Non_unique, 'columns' => []];
            }
            // Seq_in_index is 1-based; key by it so out-of-order rows still sort.
            $info[$name]['columns'][(int) $row->Seq_in_index] = [
                'name'     => $row->Column_name,
                'sub_part' => ($row->Sub_part === null) ? null : (int) $row->Sub_part,
            ];
        }

        foreach ($info as &$data) {
            ksort($data['columns']);
            $data['columns'] = array_values($data['columns']);
        }
        unset($data);

        return $info;
    }

    /**
     * Whether a required index is present, of the right kind, AND backed by the
     * exact expected columns. This is the health gate that closes the
     * "same-named index with the wrong columns reads as healthy" hole.
     *
     * @param array  $info      Output of getIndexInfo().
     * @param string $indexName Required index name.
     * @param array  $index     Index descriptor from getCriticalIndexLists().
     * @return bool
     */
    private static function indexIsHealthy($info, $indexName, array $index)
    {
        if (!isset($info[$indexName])) {
            return false;
        }

        $actual = $info[$indexName];

        // A same-named non-unique index does not enforce uniqueness.
        if ($index['type'] === 'unique' && $actual['non_unique'] !== 0) {
            return false;
        }

        return self::indexColumnsMatch($actual['columns'], $index['columns']);
    }

    /**
     * Compare an index's actual column sequence (from getIndexInfo) against the
     * expected one (from getCriticalIndexLists). Column names compare
     * case-insensitively; order matters.
     *
     * Prefix handling: when a prefix length is expected (e.g. object_type(50)),
     * a full-column index (Sub_part = NULL) is accepted because MySQL stores a
     * prefix as a full-column index when the prefix length equals the column
     * length — which is exactly what object_type(50) becomes on the current
     * VARCHAR(50) schema, and a full column is never weaker than the prefix.
     * A prefix where a full column is expected, or a different prefix length,
     * is a mismatch.
     *
     * @param array $actualColumns   Ordered [['name'=>, 'sub_part'=>], ...].
     * @param array $expectedColumns Ordered [['name'=>, 'sub_part'=>?], ...].
     * @return bool
     */
    private static function indexColumnsMatch($actualColumns, array $expectedColumns)
    {
        if (count($actualColumns) !== count($expectedColumns)) {
            return false;
        }

        foreach ($expectedColumns as $i => $expected) {
            if (!isset($actualColumns[$i])) {
                return false;
            }
            $actual = $actualColumns[$i];

            if (strcasecmp($actual['name'], $expected['name']) !== 0) {
                return false;
            }

            $expectedSub = isset($expected['sub_part']) ? (int) $expected['sub_part'] : null;
            $actualSub = isset($actual['sub_part']) ? $actual['sub_part'] : null;

            if ($expectedSub === null) {
                // Full column expected — a prefix index is weaker.
                if ($actualSub !== null) {
                    return false;
                }
            } else {
                // Prefix expected — accept the exact prefix, or a full-column
                // index (NULL) which equals/exceeds the prefix.
                if ($actualSub !== null && $actualSub !== $expectedSub) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Build the SQL column spec — e.g. "`subscriber_id`, `object_id`,
     * `object_type`(50)" — for the ADD [UNIQUE] INDEX statement from the
     * structured column definition.
     *
     * @param array $columns Ordered [['name'=>, 'sub_part'=>?], ...].
     * @return string
     */
    private static function buildColumnSql(array $columns)
    {
        $parts = [];
        foreach ($columns as $col) {
            $sql = '`' . $col['name'] . '`';
            if (!empty($col['sub_part'])) {
                $sql .= '(' . (int) $col['sub_part'] . ')';
            }
            $parts[] = $sql;
        }

        return implode(', ', $parts);
    }

    /**
     * The canonical list of performance- and integrity-critical indexes.
     *
     * Each entry is self-describing so repairIndex() can heal it without
     * touching the migration classes:
     *   - type    : 'index' | 'unique'
     *   - table   : unprefixed table name
     *   - title   : human label for the UI
     *   - columns : ordered column definition — [['name' => 'col', 'sub_part' =>
     *               50?], ...]. Used both to build the ADD statement
     *               (buildColumnSql) and to verify the live index matches
     *               (indexColumnsMatch), so health and creation share one spec.
     *   - cleanup : (unique only) which dedicated cleanup method makes the table
     *               safe for the key — 'subscriber_pivot' | 'funnel_subscribers'
     *               | 'funnel_metrics'.
     *
     * Every index is created via alterWithOnlineFallback(), which prefers a
     * non-blocking online ALTER and falls back to a plain one, so no per-index
     * online flag is needed.
     *
     * @return array<string, array>
     */
    private static function getCriticalIndexLists()
    {
        global $wpdb;
        // The campaign-emails composite index is created with the table prefix
        // baked into its name (see CampaignEmails migration), so we match that.
        $campaignEmailIndexPrefix = $wpdb->prefix . 'fc_cam_';

        $indexActions = [
            'subscriber_object_type_unique'          => [
                'type'    => 'unique',
                'table'   => 'fc_subscriber_pivot',
                'title'   => __('Subscriber Tags / Lists Relationship Uniqueness', 'fluent-crm'),
                'columns' => [
                    ['name' => 'subscriber_id'],
                    ['name' => 'object_id'],
                    ['name' => 'object_type', 'sub_part' => 50],
                ],
                'cleanup' => 'subscriber_pivot',
            ],
            $campaignEmailIndexPrefix . 'cid_status' => [
                'type'    => 'index',
                'table'   => 'fc_campaign_emails',
                'title'   => __('Campaign Emails Sending Index', 'fluent-crm'),
                'columns' => [
                    ['name' => 'campaign_id'],
                    ['name' => 'status'],
                ],
            ],
            'funnel_subscriber_idx'                  => [
                'type'    => 'unique',
                'table'   => 'fc_funnel_subscribers',
                'title'   => __('Per Automation Subscriber Uniqueness', 'fluent-crm'),
                'columns' => [
                    ['name' => 'funnel_id'],
                    ['name' => 'subscriber_id'],
                ],
                'cleanup' => 'funnel_subscribers',
            ],
            'status_next_exec_idx'                   => [
                'type'    => 'index',
                'table'   => 'fc_funnel_subscribers',
                'title'   => __('Automation Runner Performance Indexing', 'fluent-crm'),
                'columns' => [
                    ['name' => 'status'],
                    ['name' => 'next_execution_time'],
                ],
            ],
            'type_action_name_idx'                   => [
                'type'    => 'index',
                'table'   => 'fc_funnel_sequences',
                'title'   => __('Automation Goal Quick Lookup Indexing', 'fluent-crm'),
                'columns' => [
                    ['name' => 'type'],
                    ['name' => 'action_name'],
                ],
            ],
            'funnel_seq_subscriber_unique'           => [
                'type'    => 'unique',
                'table'   => 'fc_funnel_metrics',
                'title'   => __('Automation Subscriber Metrics Uniqueness', 'fluent-crm'),
                'columns' => [
                    ['name' => 'funnel_id'],
                    ['name' => 'sequence_id'],
                    ['name' => 'subscriber_id'],
                ],
                'cleanup' => 'funnel_metrics',
            ],
        ];

        return $indexActions;
    }
}
