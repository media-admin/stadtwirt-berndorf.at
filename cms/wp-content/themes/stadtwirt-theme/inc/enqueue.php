<?php
/**
 * Enqueue scripts and styles
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue theme assets
 */
function customtheme_enqueue_assets() {
    // Swiper CSS from CDN
    wp_enqueue_style(
        'swiper-cdn',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        array(),
        '11.0.0'
    );
    
    // Main theme CSS
    wp_enqueue_style(
        'custom-theme-style',
        get_template_directory_uri() . '/assets/dist/css/style.css',
        array(),
        filemtime(get_template_directory() . '/assets/dist/css/style.css')
    );
    
    // Swiper JS from CDN
    wp_enqueue_script(
        'swiper-cdn',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        array(),
        '11.0.0',
        true
    );
    
    // Main theme JS
    wp_enqueue_script(
        'custom-theme-script',
        get_template_directory_uri() . '/assets/dist/js/main.js',
        array(),
        filemtime(get_template_directory() . '/assets/dist/js/main.js'),
        true
    );
    
    // Localize script INLINE (loads BEFORE deferred script!)
    wp_add_inline_script('custom-theme-script', 
        'window.customTheme = ' . json_encode(array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom-theme-nonce'),
            'searchNonce' => wp_create_nonce('agency_search_nonce'),
            'loadMoreNonce' => wp_create_nonce('agency_load_more_nonce'),
            'filtersNonce' => wp_create_nonce('ajax_filters_nonce'),
            'googleMapsApiKey' => defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '',
            'themePath' => get_template_directory_uri(),
            'homeUrl' => home_url('/'),
        )) . ';',
        'before'
    );
    
    // Add defer attribute to main script (safe now with inline script)
    add_filter('script_loader_tag', 'customtheme_add_defer_attribute', 10, 2);
}
add_action('wp_enqueue_scripts', 'customtheme_enqueue_assets');

/**
 * Add defer attribute to scripts
 */
function customtheme_add_defer_attribute($tag, $handle) {
    if ('custom-theme-script' === $handle) {
        return str_replace(' src', ' defer src', $tag);
    }
    return $tag;
}