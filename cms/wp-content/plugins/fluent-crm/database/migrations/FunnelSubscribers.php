<?php

namespace FluentCrmMigrations;

class FunnelSubscribers
{
    /**
     * Migrate the table.
     *
     * @param bool $isForced
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix .'fc_funnel_subscribers';

        $indexPrefix = $wpdb->prefix .'fc_fsx_';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `funnel_id` BIGINT UNSIGNED NULL,
                `starting_sequence_id` BIGINT UNSIGNED NULL,
                `next_sequence` BIGINT UNSIGNED NULL,
                `subscriber_id` BIGINT UNSIGNED NULL,
                `last_sequence_id` BIGINT UNSIGNED NULL,
                `next_sequence_id` BIGINT UNSIGNED NULL,
                `last_sequence_status` VARCHAR(50) DEFAULT 'pending',
                `status` VARCHAR(50) DEFAULT 'active',
                `type` VARCHAR(50) DEFAULT 'funnel',
                `last_executed_time` TIMESTAMP NULL,
                `next_execution_time` TIMESTAMP NULL,
                `notes` TEXT NULL,
                `source_trigger_name` VARCHAR(192) NULL,
                `source_ref_id` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `{$indexPrefix}_fidx` (`funnel_id` ASC),
                INDEX `{$indexPrefix}_fsq_idx` (`subscriber_id` ASC),
                KEY `status` (`status`),
                KEY `type` (`type`),
                KEY `next_execution_time` (`next_execution_time`),
                KEY `next_sequence` (`next_sequence`),
                UNIQUE KEY `funnel_subscriber_idx` (`funnel_id`, `subscriber_id`),
                KEY `status_next_exec_idx` (`status`, `next_execution_time`)
            ) $charsetCollate;";
            dbDelta($sql);
        } else {

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
            $indexedColumns = [];
            foreach ($indexes as $index) {
                $indexedColumns[] = $index->Column_name;
            }

            if(!in_array('status', $indexedColumns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $sql = "ALTER TABLE {$table} ADD INDEX `status` (`status`),
                        ADD INDEX `type` (`type`),
                        ADD INDEX `next_execution_time` (`next_execution_time`),
                        ADD INDEX `next_sequence` (`next_sequence`);";

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query($sql);
            }

            // Two critical indexes on this table are owned by DbPerformanceService
            // so the migration and the runtime index health-check / repair path
            // share one definition and can never drift:
            //   - funnel_subscriber_idx: UNIQUE (funnel_id, subscriber_id). Its
            //     sweep + progress-aware dedupe + drop/re-add convergence (an old
            //     non-unique index of the same name OR a missing key both resolve
            //     to "unique key present") lives in the service.
            //   - status_next_exec_idx: composite index for the cron heartbeat
            //     query (runs every 60 seconds).
            \FluentCrm\App\Services\DbPerformanceService::ensureCriticalIndex('funnel_subscriber_idx');
            \FluentCrm\App\Services\DbPerformanceService::ensureCriticalIndex('status_next_exec_idx');

        }
    }
}
