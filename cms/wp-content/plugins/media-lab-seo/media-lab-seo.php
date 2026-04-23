<?php
/**
 * Plugin Name: Media Lab SEO Toolkit
 * Plugin URI:  https://github.com/media-admin/media-lab-starter-kit
 * Description: SEO-Toolkit für Media Lab Kundenprojekte. GSC-Integration, Schema.org, Breadcrumbs, Redirect-Manager, Consent-aware Analytics und wöchentlicher Report-Mailer.
 * Version:     1.1.0
 * Author:      Media Lab
 * Author URI:  https://medialab.at
 * Text Domain: media-lab-seo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MLT_VERSION',  '1.1.0' );
define( 'MLT_FILE',     __FILE__ );
define( 'MLT_PATH',     plugin_dir_path( __FILE__ ) );
define( 'MLT_URL',      plugin_dir_url( __FILE__ ) );
define( 'MLT_BASENAME', plugin_basename( __FILE__ ) );

add_action( 'plugins_loaded', 'mlt_check_dependencies', 1 );

function mlt_check_dependencies() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( ! is_plugin_active( 'media-lab-agency-core/media-lab-agency-core.php' ) ) {
        add_action( 'admin_notices', 'mlt_dependency_notice' );
        add_action( 'admin_init',    'mlt_self_deactivate' );
        return;
    }
    mlt_init();
}

function mlt_dependency_notice() {
    echo '<div class="notice notice-error"><p><strong>Media Lab SEO Toolkit</strong> benötigt das Plugin <strong>Media Lab Agency Core</strong>. Bitte zuerst Agency Core aktivieren.</p></div>';
}

function mlt_self_deactivate() {
    deactivate_plugins( MLT_BASENAME );
}

function mlt_init() {
    load_plugin_textdomain( 'media-lab-seo', false, dirname( MLT_BASENAME ) . '/languages' );

    require_once MLT_PATH . 'inc/class-gsc-api.php';
    require_once MLT_PATH . 'inc/class-analytics-adapter.php';
    require_once MLT_PATH . 'inc/class-settings.php';
    require_once MLT_PATH . 'inc/class-seo.php';
    require_once MLT_PATH . 'inc/class-schema.php';
    require_once MLT_PATH . 'inc/class-breadcrumbs.php';
    require_once MLT_PATH . 'inc/class-redirects.php';
    require_once MLT_PATH . 'inc/class-seo-dashboard.php';
    require_once MLT_PATH . 'inc/class-report-template.php';
    require_once MLT_PATH . 'inc/class-report-mailer.php';

    new MLT_Settings();
    new MLT_SEO();
    new MLT_Schema();
    new MLT_Breadcrumbs();
    new MLT_Redirects();
    new MLT_SEO_Dashboard();
    new MLT_Report_Mailer();
    MLT_GSC_API::instance();

    if ( get_option( 'mlt_analytics_enabled' ) ) {
        require_once MLT_PATH . 'inc/class-analytics.php';
        new MLT_Analytics();
    }
}

register_activation_hook( __FILE__, 'mlt_activate' );
function mlt_activate() {
    require_once plugin_dir_path( __FILE__ ) . 'inc/class-redirects.php';
    MLT_Redirects::create_tables();

    add_option( 'mlt_analytics_enabled',   0 );
    add_option( 'mlt_analytics_provider',  'ga4' );
    add_option( 'mlt_analytics_id',        '' );
    add_option( 'mlt_report_enabled',      0 );
    add_option( 'mlt_report_email',        get_option( 'admin_email' ) );
    add_option( 'mlt_gsc_verification',    '' );
    add_option( 'mlt_og_default_image',    0 );
    add_option( 'mlt_gsc_property_url',    '' );
    add_option( 'mlt_ga4_property_id',     '' );
    add_option( 'mlt_matomo_url',          '' );
    add_option( 'mlt_matomo_token',        '' );
    add_option( 'mlt_matomo_site_id',      '1' );

    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'mlt_deactivate' );
function mlt_deactivate() {
    $ts = wp_next_scheduled( 'mlt_weekly_report' );
    if ( $ts ) wp_unschedule_event( $ts, 'mlt_weekly_report' );
    wp_clear_scheduled_hook( 'mlt_weekly_report' );
    flush_rewrite_rules();
}
