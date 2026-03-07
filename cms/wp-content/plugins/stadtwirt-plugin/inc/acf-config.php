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

// Theme Settings Options Page wurde in Agency Core (media-lab-agency-core) integriert.
