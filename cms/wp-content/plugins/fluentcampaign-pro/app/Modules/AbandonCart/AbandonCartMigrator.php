<?php

namespace FluentCampaign\App\Modules\AbandonCart;

class AbandonCartMigrator
{
    /**
     * On-Demand Action Links Migrator.
     *
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = false)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix .'fc_abandoned_carts';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `checkout_key` VARCHAR(192),
                `cart_hash` VARCHAR(192),
                `is_optout` TINYINT(1) DEFAULT 0,
                `full_name` VARCHAR(192),
                `email` VARCHAR(192),
                `provider` VARCHAR(100) DEFAULT 'woo',
                `user_id` BIGINT UNSIGNED NULL,
                `click_counts` BIGINT UNSIGNED DEFAULT 0,
                `contact_id` BIGINT UNSIGNED NULL,
                `order_id` BIGINT UNSIGNED NULL,
                `automation_id` BIGINT UNSIGNED NULL,
                `checkout_page_id` BIGINT UNSIGNED NULL,
                `status` VARCHAR(30) DEFAULT 'draft',
                `subtotal` DECIMAL(10,2),
                `shipping` DECIMAL(10,2),
                `tax` DECIMAL(10,2),
                `discounts` DECIMAL(10,2),
                `fees` DECIMAL(10,2),
                `total` DECIMAL(10,2),
                `currency` VARCHAR(50),
                `cart` LONGTEXT,
                `note` TEXT,
                `abandoned_at` TIMESTAMP NULL,
                `recovered_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                KEY `status` (`status`),
                KEY `checkout_key` (`checkout_key`)
            ) $charsetCollate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}
