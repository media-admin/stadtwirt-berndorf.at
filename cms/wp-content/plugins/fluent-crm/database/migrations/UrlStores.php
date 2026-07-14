<?php

namespace FluentCrmMigrations;

class UrlStores
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

        $table = $wpdb->prefix .'fc_url_stores';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `url` TEXT NOT NULL,
                `short` VARCHAR(50) NOT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                UNIQUE KEY `short` (`short`),
                KEY `url` (`url`(191))
            ) $charsetCollate;";
            dbDelta($sql);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
            $indexedColumns = [];
            foreach ($indexes as $index) {
                $indexedColumns[] = $index->Column_name;
            }

            if(!in_array('short', $indexedColumns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $sql = "ALTER TABLE {$table} ADD UNIQUE INDEX `short` (`short`);";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query($sql);
            } else {
                // Upgrade existing non-unique index to unique
                $isUnique = false;
                foreach ($indexes as $index) {
                    if ($index->Column_name === 'short' && $index->Non_unique == 0) {
                        $isUnique = true;
                        break;
                    }
                }
                if (!$isUnique) {
                    // Check if duplicates actually exist before running heavy queries
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $hasDuplicates = $wpdb->get_var("SELECT COUNT(*) FROM (SELECT `short` FROM {$table} GROUP BY `short` HAVING COUNT(*) > 1) AS dupes");

                    if ($hasDuplicates) {
                        $metricsTable = $wpdb->prefix . 'fc_campaign_url_metrics';
                        // Re-point metrics from duplicate rows to the kept (highest id) row before cleanup
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        $repointed = $wpdb->query("UPDATE {$metricsTable} m INNER JOIN {$table} t1 ON m.url_id = t1.id INNER JOIN (SELECT `short`, MAX(id) AS max_id FROM {$table} GROUP BY `short` HAVING COUNT(*) > 1) dups ON t1.`short` = dups.`short` AND t1.id < dups.max_id SET m.url_id = dups.max_id");

                        // Only delete duplicates if re-point succeeded (false = query error)
                        if ($repointed === false) {
                            return;
                        }

                        // Clean up duplicate short values (keep highest id)
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        $cleaned = $wpdb->query("DELETE t1 FROM {$table} t1 INNER JOIN {$table} t2 WHERE t1.`short` = t2.`short` AND t1.id < t2.id");

                        if ($cleaned === false) {
                            return;
                        }
                    }

                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $wpdb->query("ALTER TABLE {$table} DROP INDEX `short`, ADD UNIQUE INDEX `short` (`short`);");
                }
            }

            if(!in_array('url', $indexedColumns)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query("ALTER TABLE {$table} ADD INDEX `url` (`url`(191));");
            }

            // change column type from tinytext to text - for already installed sites
            $column_name = 'url';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $dataType = $wpdb->get_row("describe {$table} {$column_name}");
            if($dataType->Type == 'tinytext') {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $sql = "ALTER TABLE {$table} MODIFY {$column_name} TEXT NOT NULL;";
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $wpdb->query($sql);
            }
        }
    }
}
