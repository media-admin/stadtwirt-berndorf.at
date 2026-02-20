<?php
/**
 * Media Lab Theme - Custom Theme
 * 
 * Presentation layer only. Business logic in plugins.
 * 
 * @package Custom_Theme
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Theme version
define('CUSTOM_THEME_VERSION', '1.0.0');

/**
 * Check Required Plugins
 */
function customtheme_check_required_plugins() {
    $required_plugins = array(
        'media-lab-agency-core' => 'Media Lab Agency Core',
        'media-lab-project-starter' => 'Media Lab Project Starter',
    );
    
    $missing_plugins = array();
    
    foreach ($required_plugins as $plugin_slug => $plugin_name) {
        if (!is_plugin_active($plugin_slug . '/' . $plugin_slug . '.php')) {
            $missing_plugins[] = $plugin_name;
        }
    }
    
    if (!empty($missing_plugins)) {
        add_action('admin_notices', function() use ($missing_plugins) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Custom Theme:</strong> The following plugins are recommended: ';
            echo implode(', ', $missing_plugins);
            echo '</p></div>';
        });
    }
}
add_action('after_setup_theme', 'customtheme_check_required_plugins');

/**
 * Theme Setup
 */
function customtheme_setup() {
    // Theme support
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('custom-logo');
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    add_theme_support('responsive-embeds');
    add_theme_support('editor-styles');
    
    // Navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'custom-theme'),
        'footer' => __('Footer Menu', 'custom-theme'),
    ));
    
    // Image sizes
    add_image_size('custom-thumbnail', 400, 300, true);
    add_image_size('custom-medium', 800, 600, true);
    add_image_size('custom-large', 1200, 900, true);
}
add_action('after_setup_theme', 'customtheme_setup');

/**
 * Load Theme Components
 */
require_once get_template_directory() . '/inc/enqueue.php';

// Optional components (only if files exist)
$optional_components = array(
    'walker-nav-menu.php',
    'helpers.php',
    'woocommerce.php',
);

foreach ($optional_components as $component) {
    $file = get_template_directory() . '/inc/' . $component;
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Theme Customizations
 */

// Customize excerpt length
add_filter('excerpt_length', function($length) {
    return 20;
});

// Customize excerpt more
add_filter('excerpt_more', function($more) {
    return '...';
});

/**
 * WooCommerce Support (if WooCommerce is active)
 */
if (class_exists('WooCommerce')) {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
