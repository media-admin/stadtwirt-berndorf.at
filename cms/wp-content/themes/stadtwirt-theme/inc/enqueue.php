<?php
/**
 * Enqueue Scripts & Styles
 * Stadtwirt Theme
 *
 * Vite Dev/Prod-Strategie (v1.5.1):
 *  - Dev:  Assets vom Vite Dev Server (localhost:3000) → HMR & Live Reload
 *  - Prod: Assets aus dist/ via Vite-Manifest → cache-busted, minifiziert
 *
 * Performance:
 *  - CSS: eine minifizierte Datei
 *  - JS:  ES-Module mit type="module" (defer by default)
 *  - Swiper: lokal gebündelt (kein CDN, DSGVO-sicher)
 *  - Dynamic Imports via Vite Code-Splitting
 *  - Preconnect für Google Fonts (nur wenn tatsächlich genutzt)
 *
 * @package Stadtwirt_Theme
 * @since   1.5.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Theme Version ────────────────────────────────────────────────────────────
if ( ! defined( 'STADTWIRT_THEME_VERSION' ) ) {
    define( 'STADTWIRT_THEME_VERSION', wp_get_theme()->get( 'Version' ) );
}

// ─── Vite Konstanten ──────────────────────────────────────────────────────────
define( 'VITE_DEV_SERVER_URL', 'http://localhost:3000' );
define( 'VITE_HOT_FILE',       get_template_directory() . '/assets/hot' );
define( 'VITE_MANIFEST_FILE',  get_template_directory() . '/assets/dist/.vite/manifest.json' );
define( 'VITE_DIST_URI',       get_template_directory_uri() . '/assets/dist' );


// ─── Vite Hilfsfunktionen ─────────────────────────────────────────────────────

/**
 * Ist der Vite Dev Server aktiv?
 * Die hot-Datei wird durch "npm run dev" erstellt und beim Beenden gelöscht.
 */
function stadtwirt_vite_is_dev(): bool {
    return file_exists( VITE_HOT_FILE );
}

/**
 * Liest das Vite-Manifest und gibt den Eintrag für einen Entry-Point zurück.
 *
 * @param  string $entry  Manifest-Key (z.B. 'src/js/main.js' oder 'style.css')
 * @return array|null
 */
function stadtwirt_vite_manifest_entry( string $entry ): ?array {
    static $manifest = null;

    if ( $manifest === null ) {
        if ( ! file_exists( VITE_MANIFEST_FILE ) ) {
            return null;
        }
        $manifest = json_decode( file_get_contents( VITE_MANIFEST_FILE ), true ) ?? [];
    }

    return $manifest[ $entry ] ?? null;
}


// ─── Assets Enqueuen ──────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'stadtwirt_enqueue_assets' );

function stadtwirt_enqueue_assets(): void {

    if ( stadtwirt_vite_is_dev() ) {
        // ── DEV MODE ──────────────────────────────────────────────────────────
        wp_enqueue_script(
            'vite-client',
            VITE_DEV_SERVER_URL . '/@vite/client',
            [],
            null,
            false
        );

        wp_enqueue_script(
            'stadtwirt-script',
            VITE_DEV_SERVER_URL . '/src/js/main.js',
            [ 'vite-client' ],
            null,
            true
        );

        // CSS wird von Vite automatisch per HMR injiziert.

    } else {
        // ── PRODUCTION MODE ───────────────────────────────────────────────────
        $dist      = get_template_directory()     . '/assets/dist';
        $dist_uri  = get_template_directory_uri() . '/assets/dist';
        $js_entry  = stadtwirt_vite_manifest_entry( 'src/js/main.js' );

        // CSS – Vite baut immer css/style.css (cssCodeSplit: false),
        // verlinkt sie aber nicht im Manifest unter main.js → direkt per Pfad laden.
        $css_file = $dist . '/css/style.css';
        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                'stadtwirt-style',
                $dist_uri . '/css/style.css',
                [],
                filemtime( $css_file )
            );
        }

        // JS – via Manifest (mit Hash im Dateinamen)
        if ( ! empty( $js_entry['file'] ) ) {
            wp_enqueue_script(
                'stadtwirt-script',
                VITE_DIST_URI . '/' . $js_entry['file'],
                [],
                STADTWIRT_THEME_VERSION,
                true
            );
        } elseif ( file_exists( $dist . '/js/main.js' ) ) {
            // Fallback ohne Manifest
            wp_enqueue_script(
                'stadtwirt-script',
                $dist_uri . '/js/main.js',
                [],
                filemtime( $dist . '/js/main.js' ),
                true
            );
        }
    }
}


// ─── JS-Config im <head> ──────────────────────────────────────────────────────
add_action( 'wp_head', 'stadtwirt_output_js_config', 1 );

function stadtwirt_output_js_config(): void {
    $config = [
        'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
        'nonce'            => wp_create_nonce( 'stadtwirt-nonce' ),
        'searchNonce'      => wp_create_nonce( 'agency_search_nonce' ),
        'loadMoreNonce'    => wp_create_nonce( 'agency_load_more_nonce' ),
        'filtersNonce'     => wp_create_nonce( 'ajax_filters_nonce' ),
        'googleMapsApiKey' => defined( 'GOOGLE_MAPS_API_KEY' ) ? GOOGLE_MAPS_API_KEY : '',
        'themePath'        => get_template_directory_uri(),
        'homeUrl'          => home_url( '/' ),
        'isDebug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
        'isDev'            => stadtwirt_vite_is_dev(),
    ];
    echo '<script id="stadtwirt-config">window.stadtwirtTheme = '
        . wp_json_encode( $config )
        . ';</script>' . "\n";
}


// ─── type="module" für Vite-Scripts ───────────────────────────────────────────
add_filter( 'script_loader_tag', 'stadtwirt_add_module_type', 10, 3 );

function stadtwirt_add_module_type( string $tag, string $handle, string $src ): string {
    $module_handles = [ 'vite-client', 'stadtwirt-script' ];

    if ( ! in_array( $handle, $module_handles, true ) ) {
        return $tag;
    }

    $tag = str_replace( ' type="text/javascript"', '', $tag );
    $tag = str_replace( " type='text/javascript'", '', $tag );
    $tag = str_replace( '<script ', '<script type="module" ', $tag );

    return $tag;
}


// ─── Preconnect / DNS-Prefetch ────────────────────────────────────────────────
add_action( 'wp_head', 'stadtwirt_add_preconnect', 1 );

function stadtwirt_add_preconnect(): void {
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

    if ( defined( 'GOOGLE_MAPS_API_KEY' ) && GOOGLE_MAPS_API_KEY ) {
        echo '<link rel="dns-prefetch" href="https://maps.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="https://maps.gstatic.com">' . "\n";
    }

    if ( stadtwirt_vite_is_dev() ) {
        echo '<link rel="preconnect" href="' . esc_url( VITE_DEV_SERVER_URL ) . '">' . "\n";
    }
}


// ─── Nicht benötigte WordPress-Scripts entfernen ──────────────────────────────
add_action( 'wp_enqueue_scripts', 'stadtwirt_dequeue_unnecessary', 100 );

function stadtwirt_dequeue_unnecessary(): void {
    wp_dequeue_style( 'wp-emoji-release' );
}


// ─── Emoji-Scripts deaktivieren ───────────────────────────────────────────────
add_action( 'init', 'stadtwirt_disable_emojis' );

function stadtwirt_disable_emojis(): void {
    remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles',     'print_emoji_styles' );
    remove_action( 'admin_print_styles',  'print_emoji_styles' );
    remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
    remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );

    add_filter( 'tiny_mce_plugins', function( $plugins ) {
        return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
    } );

    add_filter( 'wp_resource_hints', function( $urls, $relation_type ) {
        if ( 'dns-prefetch' === $relation_type ) {
            $urls = array_filter( $urls, function( $url ) {
                return ! str_contains( (string) $url, 's.w.org' );
            } );
        }
        return $urls;
    }, 10, 2 );
}


// ─── oEmbed deaktivieren ──────────────────────────────────────────────────────
add_action( 'init', 'stadtwirt_disable_oembed' );

function stadtwirt_disable_oembed(): void {
    remove_action( 'wp_head',       'wp_oembed_add_discovery_links' );
    remove_action( 'rest_api_init', 'wp_oembed_register_route' );
    remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result' );
    wp_deregister_script( 'wp-embed' );
}


// ─── Unnötige WP Head Tags entfernen ─────────────────────────────────────────
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
