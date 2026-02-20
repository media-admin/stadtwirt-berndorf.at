<?php
/**
 * Plugin Name: Stadtwirt Berndorf Plugin
 * Plugin URI: https://github.com/media-admin/media-lab-starter-kit
 * Description: Project-specific CPTs, taxonomies, and ACF fields. Duplicate and customize for each client project.
 * Version: 1.0.0
 * Author: Media Lab
 * Author URI: https://medialab.at
 * Text Domain: stadtwirt
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: media-lab-agency-core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('MEDIALAB_PROJECT_VERSION', '1.0.0');
define('MEDIALAB_PROJECT_FILE', __FILE__);
define('MEDIALAB_PROJECT_PATH', plugin_dir_path(__FILE__));
define('MEDIALAB_PROJECT_URL', plugin_dir_url(__FILE__));
define('MEDIALAB_PROJECT_BASENAME', plugin_basename(__FILE__));

/**
 * Check if Core Plugin is active
 */
function medialab_project_check_dependencies() {
    if (!function_exists('medialab_core_version')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Media Lab Project Starter</strong> requires ';
            echo '<strong>Media Lab Agency Core</strong> plugin to be installed and activated.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Initialize Plugin
 */
function medialab_project_init() {
    // Check dependencies first
    if (!medialab_project_check_dependencies()) {
        return;
    }
    
    // Load text domain
    load_plugin_textdomain('media-lab-project', false, dirname(MEDIALAB_PROJECT_BASENAME) . '/languages');
    
    // Load components
    require_once MEDIALAB_PROJECT_PATH . 'inc/custom-post-types.php';
    require_once MEDIALAB_PROJECT_PATH . 'inc/taxonomies.php';
    require_once MEDIALAB_PROJECT_PATH . 'inc/acf-config.php';
}
add_action('plugins_loaded', 'medialab_project_init', 10);

/**
 * Activation Hook
 */
function medialab_project_activate() {
    // Check dependencies
    if (!medialab_project_check_dependencies()) {
        deactivate_plugins(MEDIALAB_PROJECT_BASENAME);
        wp_die(
            '<strong>Media Lab Project Starter</strong> requires <strong>Media Lab Agency Core</strong> plugin.',
            'Plugin Dependency Check',
            array('back_link' => true)
        );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'medialab_project_activate');

/**
 * Deactivation Hook
 */
function medialab_project_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'medialab_project_deactivate');

// ACF JSON Load Path
add_filter('acf/settings/load_json', function($paths) {
    $paths[] = plugin_dir_path(__FILE__) . 'acf-json';
    return $paths;
});

// ACF JSON Save Path
add_filter('acf/settings/save_json', function($path) {
    return plugin_dir_path(__FILE__) . 'acf-json';
});
