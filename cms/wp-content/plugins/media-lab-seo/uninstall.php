<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Optionen entfernen
$options = [
    'mlt_analytics_enabled', 'mlt_analytics_provider', 'mlt_analytics_id',
    'mlt_report_enabled', 'mlt_report_email', 'mlt_gsc_verification',
    'mlt_og_default_image', 'mlt_gsc_client_id', 'mlt_gsc_client_secret',
    'mlt_gsc_property_url', 'mlt_gsc_access_token', 'mlt_gsc_refresh_token',
    'mlt_gsc_token_expiry', 'mlt_ga4_property_id', 'mlt_ga4_service_account_json',
    'mlt_matomo_url', 'mlt_matomo_token', 'mlt_matomo_site_id',
    'mlt_last_report_sent', 'mlt_last_report_status',
];
foreach ( $options as $option ) {
    delete_option( $option );
}

// Transients
delete_transient( 'mlt_gsc_overview' );
delete_transient( 'mlt_gsc_queries_10' );
delete_transient( 'mlt_gsc_pages_10' );
delete_transient( 'mlt_ga4_access_token' );

// Cron
wp_clear_scheduled_hook( 'mlt_weekly_report' );

// DB-Tabellen löschen
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_redirects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_404_log" );
