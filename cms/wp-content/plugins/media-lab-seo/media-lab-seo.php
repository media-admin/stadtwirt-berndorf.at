<?php
/**
 * Plugin Name: Media Lab SEO Toolkit
 * Plugin URI: https://github.com/media-admin/media-lab-starter-kit
 * Description: Comprehensive SEO solution. Schema.org markup, Open Graph, Twitter Cards, breadcrumbs, and meta management.
 * Version: 1.0.0
 * Author: Media Lab
 * Author URI: https://medialab.at
 * Text Domain: media-lab-seo
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('MEDIALAB_SEO_VERSION', '1.0.0');
define('MEDIALAB_SEO_FILE', __FILE__);
define('MEDIALAB_SEO_PATH', plugin_dir_path(__FILE__));
define('MEDIALAB_SEO_URL', plugin_dir_url(__FILE__));
define('MEDIALAB_SEO_BASENAME', plugin_basename(__FILE__));

/**
 * Initialize Plugin
 */
function medialab_seo_init() {
    // Load components
    require_once MEDIALAB_SEO_PATH . 'inc/settings.php';
    require_once MEDIALAB_SEO_PATH . 'inc/schema.php';
    require_once MEDIALAB_SEO_PATH . 'inc/opengraph.php';
    require_once MEDIALAB_SEO_PATH . 'inc/twitter.php';
    require_once MEDIALAB_SEO_PATH . 'inc/meta.php';
    require_once MEDIALAB_SEO_PATH . 'inc/breadcrumbs.php';
}
add_action('plugins_loaded', 'medialab_seo_init');

/**
 * Activation Hook
 */
function medialab_seo_activate() {
    // Set default options
    add_option('medialab_seo_enabled', '1');
    add_option('medialab_seo_schema_enabled', '1');
    add_option('medialab_seo_og_enabled', '1');
    add_option('medialab_seo_twitter_enabled', '1');
    add_option('medialab_seo_site_name', get_bloginfo('name'));
    add_option('medialab_seo_twitter_username', '');
    
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'medialab_seo_activate');

/**
 * Deactivation Hook
 */
function medialab_seo_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'medialab_seo_deactivate');
