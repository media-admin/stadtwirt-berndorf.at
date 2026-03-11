<?php
/**
 * Enqueue Scripts & Styles
 * Media Lab Starter Kit – Custom Theme
 *
 * Performance-Strategie (v1.4.0):
 *  - CSS: eine minifizierte Datei, cache-busted via filemtime()
 *  - JS:  ES-Module mit type="module" (defer by default)
 *  - Swiper: lokal gebündelt (kein CDN, DSGVO-sicher)
 *  - Dynamic Imports via Vite Code-Splitting
 *  - Preconnect für Google Fonts (falls genutzt)
 */

if (!defined('ABSPATH')) exit;

// ─── Theme Version ────────────────────────────────────────────────────────────
if (!defined('CUSTOM_THEME_VERSION')) {
    define('CUSTOM_THEME_VERSION', wp_get_theme()->get('Version'));
}

// ─── Assets Enqueuen ─────────────────────────────────────────────────────────
function customtheme_enqueue_assets() {

    $dist     = get_template_directory()     . '/assets/dist';
    $dist_uri = get_template_directory_uri() . '/assets/dist';
    $is_dev   = defined('VITE_DEV_SERVER') && VITE_DEV_SERVER;

    if ($is_dev) {
        // ── Vite Dev Server (HMR) ─────────────────────────────────────────
        wp_enqueue_script('vite-client',
            'http://localhost:3000/@vite/client', [], null, false);
        wp_enqueue_script('custom-theme-script',
            'http://localhost:3000/src/js/main.js',
            [], null, true
        );
    } else {
        // ── Production Build ──────────────────────────────────────────────
        $css_file = $dist . '/css/style.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('custom-theme-style', $dist_uri . '/css/style.css', [], filemtime($css_file));
        }

        $js_file = $dist . '/js/main.js';
        if (file_exists($js_file)) {
            wp_enqueue_script('custom-theme-script', $dist_uri . '/js/main.js', [], filemtime($js_file), true);
        }
    }
}
add_action('wp_enqueue_scripts', 'customtheme_enqueue_assets');

// ─── JS-Config im <head> ──────────────────────────────────────────────────────
// wp_add_inline_script('before') wird bei type="module"-Scripts von WordPress
// manchmal silent ignoriert → stattdessen direkt via wp_head ausgeben.
function customtheme_output_js_config() {
    $config = array(
        'ajaxUrl'          => admin_url('admin-ajax.php'),
        'nonce'            => wp_create_nonce('custom-theme-nonce'),
        'searchNonce'      => wp_create_nonce('agency_search_nonce'),
        'loadMoreNonce'    => wp_create_nonce('agency_load_more_nonce'),
        'filtersNonce'     => wp_create_nonce('ajax_filters_nonce'),
        'googleMapsApiKey' => defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '',
        'themePath'        => get_template_directory_uri(),
        'homeUrl'          => home_url('/'),
        'isDebug'          => defined('WP_DEBUG') && WP_DEBUG,
    );
    echo '<script id="custom-theme-config">window.customTheme = '
        . wp_json_encode($config)
        . ';</script>' . "\n";
}
add_action('wp_head', 'customtheme_output_js_config', 1);

// ─── type="module" für main.js ────────────────────────────────────────────────
// ES-Module sind per Spezifikation immer deferred – kein extra defer nötig.
function customtheme_add_module_type( $tag, $handle, $src ) {
    if (in_array($handle, ['custom-theme-script', 'vite-client'])) {
        return '<script type="module" src="' . $src . '"></script>' . "\n";
    }
    return $tag;
}
add_filter('script_loader_tag', 'customtheme_add_module_type', 10, 3);

// ─── Preconnect / DNS-Prefetch ─────────────────────────────────────────────────
function customtheme_add_preconnect() {
    // Google Fonts – Preconnect nur wenn tatsächlich eine Google Fonts URL
    // enqueued ist (verhindert unnötige Verbindungen wenn self-hosted).
    global $wp_styles;
    $uses_google_fonts = false;
    if ( ! empty( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $handle ) {
            $src = $wp_styles->registered[ $handle ]->src ?? '';
            if ( str_contains( (string) $src, 'fonts.googleapis.com' ) ) {
                $uses_google_fonts = true;
                break;
            }
        }
    }

    if ( $uses_google_fonts ) {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    // Google Maps API (nur wenn API-Key gesetzt)
    if (defined('GOOGLE_MAPS_API_KEY') && GOOGLE_MAPS_API_KEY) {
        echo '<link rel="dns-prefetch" href="https://maps.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://maps.gstatic.com">' . "\n";
    }
}
add_action('wp_head', 'customtheme_add_preconnect', 1);

// ─── Nicht benötigte WordPress-Scripts entfernen ───────────────────────────────
function customtheme_dequeue_unnecessary() {
    // jQuery nur im Frontend dequeuen wenn nicht explizit benötigt
    // (auskommentiert – WooCommerce und viele Plugins benötigen jQuery)
    // wp_dequeue_script('jquery');

    // WordPress Emoji-Scripts (selten benötigt, 16KB+)
    wp_dequeue_style('wp-emoji-release');
}
add_action('wp_enqueue_scripts', 'customtheme_dequeue_unnecessary', 100);

// ─── Emoji-Scripts deaktivieren ───────────────────────────────────────────────
function customtheme_disable_emojis() {
    remove_action('wp_head',             'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles',     'print_emoji_styles');
    remove_action('admin_print_styles',  'print_emoji_styles');
    remove_filter('the_content_feed',    'wp_staticize_emoji');
    remove_filter('comment_text_rss',    'wp_staticize_emoji');
    remove_filter('wp_mail',             'wp_staticize_emoji_for_email');
    // TinyMCE Plugin entfernen
    add_filter('tiny_mce_plugins', function($plugins) {
        return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : array();
    });
    // DNS-Prefetch entfernen
    add_filter('wp_resource_hints', function($urls, $relation_type) {
        if ('dns-prefetch' === $relation_type) {
            $urls = array_filter($urls, function($url) {
                return !str_contains((string) $url, 's.w.org');
            });
        }
        return $urls;
    }, 10, 2);
}
add_action('init', 'customtheme_disable_emojis');

// ─── oEmbed deaktivieren (spart HTTP-Request) ─────────────────────────────────
function customtheme_disable_oembed() {
    // oEmbed-Discovery-Links entfernen
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    // REST-API oEmbed-Route entfernen
    remove_action('rest_api_init', 'wp_oembed_register_route');
    // oEmbed-Filter entfernen
    remove_filter('oembed_dataparse', 'wp_filter_oembed_result');
    // oEmbed-Script dequeuen
    wp_deregister_script('wp-embed');
}
add_action('init', 'customtheme_disable_oembed');

// ─── Unnötige WP Head Tags entfernen ──────────────────────────────────────────
remove_action('wp_head', 'rsd_link');                    // Really Simple Discovery
remove_action('wp_head', 'wlwmanifest_link');            // Windows Live Writer
remove_action('wp_head', 'wp_generator');                // WordPress Version
remove_action('wp_head', 'wp_shortlink_wp_head');        // Shortlink
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head'); // Prev/Next Links
