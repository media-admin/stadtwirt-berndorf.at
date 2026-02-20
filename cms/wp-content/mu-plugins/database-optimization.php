<?php
/**
 * Database Optimization
 * 
 * Automatically cleans up database on schedule
 * 
 * @package CustomTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Schedule weekly cleanup
 */
function customsite_schedule_db_cleanup() {
    if (!wp_next_scheduled('customsite_db_cleanup')) {
        wp_schedule_event(time(), 'weekly', 'customsite_db_cleanup');
    }
}
add_action('wp', 'customsite_schedule_db_cleanup');

/**
 * Database cleanup function
 */
function customsite_run_db_cleanup() {
    global $wpdb;
    
    // Delete old revisions (older than 30 days)
    $wpdb->query("
        DELETE FROM {$wpdb->posts}
        WHERE post_type = 'revision'
        AND post_modified < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    // Delete auto-drafts (older than 7 days)
    $wpdb->query("
        DELETE FROM {$wpdb->posts}
        WHERE post_status = 'auto-draft'
        AND post_modified < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    // Delete trashed comments
    $wpdb->query("
        DELETE FROM {$wpdb->comments}
        WHERE comment_approved = 'trash'
    ");
    
    // Delete expired transients
    $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_timeout_%'
        AND option_value < UNIX_TIMESTAMP()
    ");
    
    // Delete orphaned transients
    $wpdb->query("
        DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_%'
        AND option_name NOT LIKE '_transient_timeout_%'
        AND option_name NOT IN (
            SELECT CONCAT('_transient_', SUBSTRING(option_name, 20))
            FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_timeout_%'
        )
    ");
    
    // Optimize tables
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    foreach ($tables as $table) {
        $wpdb->query("OPTIMIZE TABLE {$table[0]}");
    }
}
add_action('customsite_db_cleanup', 'customsite_run_db_cleanup');