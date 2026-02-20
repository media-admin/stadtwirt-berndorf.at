<?php
/**
 * ACF Configuration
 * 
 * @package MediaLab_Project
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Set ACF JSON Save Point
 */
add_filter('acf/settings/save_json', function($path) {
    return MEDIALAB_PROJECT_PATH . 'acf-json';
});

/**
 * Add ACF JSON Load Point
 */
add_filter('acf/settings/load_json', function($paths) {
    // Add project plugin path
    $paths[] = MEDIALAB_PROJECT_PATH . 'acf-json';
    
    return $paths;
});

/**
 * ACF Options Pages (optional - customize per project)
 */
if (function_exists('acf_add_options_page')) {
    
    // Main options page
    acf_add_options_page(array(
        'page_title' => 'Theme Settings',
        'menu_title' => 'Theme Settings',
        'menu_slug' => 'theme-settings',
        'capability' => 'edit_posts',
        'icon_url' => 'dashicons-admin-generic',
        'position' => 30,
    ));
    
    // Sub-pages (uncomment if needed)
    /*
    acf_add_options_sub_page(array(
        'page_title' => 'Header Settings',
        'menu_title' => 'Header',
        'parent_slug' => 'theme-settings',
    ));
    
    acf_add_options_sub_page(array(
        'page_title' => 'Footer Settings',
        'menu_title' => 'Footer',
        'parent_slug' => 'theme-settings',
    ));
    */
}
