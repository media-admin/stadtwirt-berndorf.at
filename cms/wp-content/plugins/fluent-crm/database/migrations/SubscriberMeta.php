<?php

namespace FluentCrmMigrations;

class SubscriberMeta
{

    /**
     * Migrate the table.
     *
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix .'fc_subscriber_meta';

        $indexPrefix = $wpdb->prefix .'fc_index_';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `subscriber_id` BIGINT UNSIGNED NOT NULL,
                `created_by` BIGINT UNSIGNED NOT NULL,
                `object_type` VARCHAR(50) DEFAULT 'option',
                `key` VARCHAR(192) NOT NULL,
                `value` LONGTEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `{$indexPrefix}_s_meta_id_idx` (`subscriber_id` ASC),
                INDEX `{$indexPrefix}_s_ot_idx` (`object_type` ASC)
            ) $charsetCollate;";

            dbDelta($sql);

            // (key, value) lookup index — added post-create via the guarded service
            // path (not inlined in CREATE TABLE): its 1020-byte utf8mb4 width exceeds
            // the 767-byte InnoDB key limit on MySQL < 5.7.7 / MariaDB < 10.2, and an
            // inline failure would take the whole CREATE TABLE down with it.
            \FluentCrm\App\Services\DbPerformanceService::ensureCriticalIndex('subscriber_meta_key_value_idx');
        } else{

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
            $indexedColumns = [];
            foreach ($indexes as $index) {
                $indexedColumns[] = $index->Column_name;
            }

            if(!in_array('object_type', $indexedColumns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $sql = "ALTER TABLE {$table} ADD INDEX `object_type` (`object_type`);";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query($sql);
            }

            // (key, value) lookup index — owned by DbPerformanceService so the
            // migration and the runtime health-check/repair path share one definition.
            \FluentCrm\App\Services\DbPerformanceService::ensureCriticalIndex('subscriber_meta_key_value_idx');
        }
    }
}
