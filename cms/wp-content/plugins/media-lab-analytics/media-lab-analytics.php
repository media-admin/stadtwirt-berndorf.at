<?php
/**
 * Plugin Name: Media Lab Analytics
 * Plugin URI: https://github.com/media-admin/media-lab-starter-kit
 * Description: Centralized analytics and tracking management. Google Analytics 4, GTM, Facebook Pixel, and custom event tracking.
 * Version: 1.0.0
 * Author: Media Lab
 * Author URI: https://medialab.at
 * Text Domain: media-lab-analytics
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('MEDIALAB_ANALYTICS_VERSION', '1.0.0');
define('MEDIALAB_ANALYTICS_FILE', __FILE__);
define('MEDIALAB_ANALYTICS_PATH', plugin_dir_path(__FILE__));
define('MEDIALAB_ANALYTICS_URL', plugin_dir_url(__FILE__));
define('MEDIALAB_ANALYTICS_BASENAME', plugin_basename(__FILE__));

/**
 * Initialize Plugin
 */
function medialab_analytics_init() {
    // Load components
    require_once MEDIALAB_ANALYTICS_PATH . 'inc/settings.php';
    require_once MEDIALAB_ANALYTICS_PATH . 'inc/tracking.php';
    require_once MEDIALAB_ANALYTICS_PATH . 'inc/events.php';
}
add_action('plugins_loaded', 'medialab_analytics_init');

/**
 * Activation Hook
 */
function medialab_analytics_activate() {
    // Set default options
    add_option('medialab_analytics_ga4_id', '');
    add_option('medialab_analytics_gtm_id', '');
    add_option('medialab_analytics_fb_pixel_id', '');
    add_option('medialab_analytics_enabled', '1');
}
register_activation_hook(__FILE__, 'medialab_analytics_activate');
