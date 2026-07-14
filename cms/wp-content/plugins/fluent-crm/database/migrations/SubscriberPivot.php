<?php

namespace FluentCrmMigrations;

class SubscriberPivot
{

    /**
     * Migrate the table.
     *
     * This table will maintain many-to-many relationships
     * between subscriber & lists and subscriber & tags.
     *
     * @return void
     */
    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix .'fc_subscriber_pivot';

        $subscriberTable = $wpdb->prefix .'fc_subscribers';

        $indexPrefix = $wpdb->prefix .'fc_srp_';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `subscriber_id` BIGINT UNSIGNED NOT NULL,
                `object_id` BIGINT UNSIGNED NOT NULL, /*list_id or tag_id*/
                `object_type` VARCHAR(50) NOT NULL, /*list or tag*/
                `status` VARCHAR(50) NULL,
                `is_public` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `{$indexPrefix}_sp_id_idx` (`subscriber_id` ASC),
                INDEX `{$indexPrefix}_sp_o_id_idx` (`object_id` ASC),
                INDEX `{$indexPrefix}_sp_t_id_idx` (`object_type` ASC),
                UNIQUE KEY `subscriber_object_type_unique` (`subscriber_id`, `object_id`, `object_type`(50))
            ) $charsetCollate;";

            dbDelta($sql);
            return;
        }

        // Existing table — ensure the composite unique key is present so that
        // attachTags/attachLists can rely on INSERT IGNORE for race-safe upsert.
        // The unique key prevents two concurrent attach paths (e.g. WooCommerce
        // + WP Fusion + LearnDash hooks firing on the same enrollment) from
        // both inserting a duplicate (subscriber_id, object_id, object_type)
        // row and both firing the corresponding contact_added_to_* action.
        //
        // The sweep + dedupe + online ADD UNIQUE KEY convergence lives in one
        // place — DbPerformanceService, which is also what the runtime index
        // health-check / repair path uses — so the two can never drift.
        \FluentCrm\App\Services\DbPerformanceService::ensureCriticalIndex('subscriber_object_type_unique');
    }
}
