<?php
/**
 * Gutenberg Custom Blocks – Zentrale Registrierung
 *
 * Ansatz:
 *   ACF Blocks  – PHP-Rendering, ACF-Felder, kein Build-Step
 *                 Hero, Testimonial, Team-Mitglied, Logo-Leiste, Logo-Slider,
 *                 Social-Share, Inhaltsverzeichnis, Vorher/Nachher
 *
 *   Native Blocks – block.json + JS (Vite-Build), InnerBlocks-fähig
 *                   CTA-Banner, Accordion/FAQ, Icon+Text, Parallax-Sektion,
 *                   Slider (+ Folie als Kind-Block)
 *
 * Migration (seit 1.17.0):
 *   Parallax und Slider waren ACF-PHP-Blöcke – ACF rendert Feldgruppen
 *   IMMER in der Inspector-Sidebar, unabhängig von mode/position. Als
 *   native Blocks liegen Inspector-Controls weiterhin in der Sidebar
 *   (WordPress-Konvention), aber der eigentliche Inhalt (Bild, Text,
 *   Folien) ist jetzt direkt im Editor-Canvas sichtbar und bearbeitbar
 *   statt nur als Platzhalter – inkl. InnerBlocks-Unterstützung, die bei
 *   ACF-PHP-Blöcken nicht zuverlässig funktioniert.
 *
 * Neue Blöcke hinzufügen:
 *   1. Ordner unter blocks/{name}/ anlegen
 *   2. block.json + render.php (ACF) oder edit.js (Native) erstellen
 *   3. In medialab_register_blocks() eintragen
 *
 * Fix (WP 6.3+):
 *   Editor-CSS via enqueue_block_assets → landet korrekt im Editor-Iframe.
 *   Editor-JS  via enqueue_block_editor_assets → läuft im Editor-Kontext.
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Konstanten ────────────────────────────────────────────────────────────────
define( 'MEDIALAB_BLOCKS_DIR', plugin_dir_path( dirname( __FILE__ ) ) . 'blocks/' );
define( 'MEDIALAB_BLOCKS_URI', plugin_dir_url(  dirname( __FILE__ ) ) . 'blocks/' );

// =============================================================================
// Block-Kategorie
// =============================================================================

add_filter( 'block_categories_all', 'medialab_block_categories', 10, 2 );

function medialab_block_categories( array $categories, WP_Block_Editor_Context $context ): array {
    return $categories;
}

// =============================================================================
// ACF-Blocks registrieren
// =============================================================================

add_action( 'acf/init', 'medialab_register_acf_blocks' );

function medialab_register_acf_blocks(): void {
    if ( ! function_exists( 'acf_register_block_type' ) ) return;

    $acf_blocks = [
        'hero',
        'testimonial',
        'team-member',
        'logo-grid',
        'logo-slider',
        'social-share',
        'table-of-contents',
        'before-after',
    ];

    foreach ( $acf_blocks as $block ) {
        $config_file = MEDIALAB_BLOCKS_DIR . $block . '/block.json';
        if ( file_exists( $config_file ) ) {
            register_block_type( $config_file );
        }
    }
}

// =============================================================================
// Native Blocks registrieren
// =============================================================================

add_action( 'init', 'medialab_register_native_blocks' );

function medialab_register_native_blocks(): void {
    $native_blocks = [
        'cta-banner',
        'accordion',
        'icon-text',
        'parallax',
        'slider',
        'slide',
    ];

    foreach ( $native_blocks as $block ) {
        $config_file = MEDIALAB_BLOCKS_DIR . $block . '/block.json';
        if ( file_exists( $config_file ) ) {
            register_block_type( $config_file );
        }
    }
}

// =============================================================================
// type="module" für blocks.js (ES-Module-Format aus Vite 8 / rolldown-Build)
// Ohne dieses Attribut führt der Browser ES-Module-Syntax nicht aus.
// =============================================================================

add_filter( 'script_loader_tag', 'medialab_blocks_script_module_type', 10, 2 );

function medialab_blocks_script_module_type( string $tag, string $handle ): string {
    if ( $handle === 'medialab-blocks' ) {
        return str_replace( '<script ', '<script type="module" ', $tag );
    }
    return $tag;
}

// =============================================================================
// Editor-CSS – enqueue_block_assets
// Seit WP 6.3 müssen Styles für den Editor-Iframe über diesen Hook laufen.
// Der Hook feuert auf Frontend UND im Editor; is_admin() begrenzt auf Editor.
// =============================================================================

add_action( 'enqueue_block_assets', 'medialab_enqueue_block_editor_styles' );

function medialab_enqueue_block_editor_styles(): void {
    if ( ! is_admin() ) return;

    $dist_uri   = plugin_dir_url(  dirname( __FILE__ ) ) . 'assets/dist/';
    $dist_dir   = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/dist/';
    $plugin_uri = plugin_dir_url(  dirname( __FILE__ ) );
    $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );

    // Editor-Override-CSS (Größen-Fixes für neue Blöcke im Iframe)
    $override_css = $plugin_dir . 'assets/css/block-editor-overrides.css';
    if ( file_exists( $override_css ) ) {
        wp_enqueue_style(
            'medialab-block-editor-overrides',
            $plugin_uri . 'assets/css/block-editor-overrides.css',
            [],
            filemtime( $override_css )
        );
    }

    // Editor-CSS für alle Blöcke (im Iframe sichtbar)
    $editor_css = $dist_dir . 'css/blocks-editor.css';
    if ( file_exists( $editor_css ) ) {
        wp_enqueue_style(
            'medialab-blocks-editor',
            $dist_uri . 'css/blocks-editor.css',
            [],
            filemtime( $editor_css )
        );
    }

    // Strukturelles CSS für Parallax + Slider (native Blocks seit 1.16.0) –
    // wird sonst nur im Frontend geladen (has_block()-Gate in
    // medialab_enqueue_block_frontend_assets()), fehlt aber im Editor-Iframe,
    // wodurch z.B. das Parallax-Hintergrundbild dort unsichtbar bleibt
    // (fehlendes position:absolute etc.). Im Editor immer laden, da hier
    // has_block() für einen gerade erst eingefügten Block ohnehin unzuverlässig ist.
    foreach ( [ 'block-parallax', 'block-slider' ] as $handle_suffix ) {
        $css_file = $plugin_dir . "assets/css/{$handle_suffix}.css";
        if ( file_exists( $css_file ) ) {
            wp_enqueue_style(
                "medialab-{$handle_suffix}-editor",
                $plugin_uri . "assets/css/{$handle_suffix}.css",
                [],
                filemtime( $css_file )
            );
        }
    }
}

// =============================================================================
// Editor-JS – enqueue_block_editor_assets
// Scripts laufen im Editor-Kontext (außerhalb des Iframes, aber mit Zugriff
// auf wp.blocks etc. via wp.domReady() in blocks.js).
// =============================================================================

add_action( 'enqueue_block_editor_assets', 'medialab_enqueue_block_editor_scripts' );

function medialab_enqueue_block_editor_scripts(): void {
    $dist_uri = plugin_dir_url(  dirname( __FILE__ ) ) . 'assets/dist/';
    $dist_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/dist/';

    // Native Block JS (wp.domReady()-Wrapper in blocks.js erforderlich)
    $blocks_js = $dist_dir . 'js/blocks.js';
    if ( file_exists( $blocks_js ) ) {
        wp_enqueue_script(
            'medialab-blocks',
            $dist_uri . 'js/blocks.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-dom-ready' ],
            filemtime( $blocks_js ),
            true
        );
    }
}

// =============================================================================
// Frontend-Assets – wp_enqueue_scripts
// =============================================================================

add_action( 'wp_enqueue_scripts', 'medialab_enqueue_block_frontend_assets' );

function medialab_enqueue_block_frontend_assets(): void {
    $dist_uri = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/dist/';
    $dist_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/dist/';

    // Frontend-CSS für alle Blöcke
    $blocks_css = $dist_dir . 'css/blocks.css';
    if ( file_exists( $blocks_css ) ) {
        wp_enqueue_style(
            'medialab-blocks',
            $dist_uri . 'css/blocks.css',
            [],
            filemtime( $blocks_css )
        );
    }

    // Accordion JS (nur wenn Block auf der Seite)
    if ( has_block( 'medialab/accordion' ) ) {
        $accordion_js = $dist_dir . 'js/block-accordion.js';
        if ( file_exists( $accordion_js ) ) {
            wp_enqueue_script(
                'medialab-accordion',
                $dist_uri . 'js/block-accordion.js',
                [],
                filemtime( $accordion_js ),
                true
            );
        }
    }

    // Swiper für Logo-Slider und Slider-Block
    $needs_swiper = has_block( 'medialab/logo-slider' ) || has_block( 'medialab/slider' );
    if ( $needs_swiper ) {
        $swiper_js  = get_template_directory_uri() . '/assets/dist/js/chunks/swiper.js';
        $swiper_css = get_template_directory_uri() . '/assets/dist/css/swiper.css';
        wp_enqueue_script( 'swiper', $swiper_js,  [], '11.0.0', true );
        wp_enqueue_style(  'swiper', $swiper_css, [], '11.0.0' );
    }

    if ( has_block( 'medialab/logo-slider' ) ) {
        $logo_slider_js = $dist_dir . 'js/block-logo-slider.js';
        if ( file_exists( $logo_slider_js ) ) {
            wp_enqueue_script(
                'medialab-logo-slider',
                $dist_uri . 'js/block-logo-slider.js',
                [ 'swiper' ],
                filemtime( $logo_slider_js ),
                true
            );
        }
    }

    // Logo-Grid Block
    if ( has_block( 'medialab/logo-grid' ) ) {
        $plugin_uri = plugin_dir_url( dirname( __FILE__ ) );
        $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
        wp_enqueue_style(
            'medialab-block-logo-grid',
            $plugin_uri . 'assets/css/block-logo-grid.css',
            [],
            file_exists( $plugin_dir . 'assets/css/block-logo-grid.css' )
                ? filemtime( $plugin_dir . 'assets/css/block-logo-grid.css' ) : MEDIALAB_CORE_VERSION
        );
    }

    // Parallax Block
    if ( has_block( 'medialab/parallax' ) ) {
        $plugin_uri = plugin_dir_url( dirname( __FILE__ ) );
        $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
        wp_enqueue_style(
            'medialab-block-parallax',
            $plugin_uri . 'assets/css/block-parallax.css',
            [],
            file_exists( $plugin_dir . 'assets/css/block-parallax.css' )
                ? filemtime( $plugin_dir . 'assets/css/block-parallax.css' ) : MEDIALAB_CORE_VERSION
        );
        wp_enqueue_script(
            'medialab-block-parallax',
            $plugin_uri . 'assets/js/block-parallax.js',
            [],
            file_exists( $plugin_dir . 'assets/js/block-parallax.js' )
                ? filemtime( $plugin_dir . 'assets/js/block-parallax.js' ) : MEDIALAB_CORE_VERSION,
            true
        );
    }

    // Before / After Block
    if ( has_block( 'medialab/before-after' ) ) {
        $plugin_uri = plugin_dir_url( dirname( __FILE__ ) );
        $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
        wp_enqueue_style(
            'medialab-block-before-after',
            $plugin_uri . 'assets/css/block-before-after.css',
            [],
            file_exists( $plugin_dir . 'assets/css/block-before-after.css' )
                ? filemtime( $plugin_dir . 'assets/css/block-before-after.css' ) : MEDIALAB_CORE_VERSION
        );
        wp_enqueue_script(
            'medialab-block-before-after',
            $plugin_uri . 'assets/js/block-before-after.js',
            [],
            file_exists( $plugin_dir . 'assets/js/block-before-after.js' )
                ? filemtime( $plugin_dir . 'assets/js/block-before-after.js' ) : MEDIALAB_CORE_VERSION,
            true
        );
    }

    // Slider Block
    if ( has_block( 'medialab/slider' ) ) {
        $plugin_uri = plugin_dir_url( dirname( __FILE__ ) );
        $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
        wp_enqueue_style(
            'medialab-block-slider',
            $plugin_uri . 'assets/css/block-slider.css',
            [ 'swiper' ],
            file_exists( $plugin_dir . 'assets/css/block-slider.css' )
                ? filemtime( $plugin_dir . 'assets/css/block-slider.css' ) : MEDIALAB_CORE_VERSION
        );
        wp_enqueue_script(
            'medialab-block-slider',
            $plugin_uri . 'assets/js/block-slider.js',
            [ 'swiper' ],
            file_exists( $plugin_dir . 'assets/js/block-slider.js' )
                ? filemtime( $plugin_dir . 'assets/js/block-slider.js' ) : MEDIALAB_CORE_VERSION,
            true
        );
    }
}
