<?php
/**
 * Gutenberg Custom Blocks – Zentrale Registrierung
 *
 * Ansatz:
 *   ACF Blocks  – PHP-Rendering, ACF-Felder, kein Build-Step
 *                 Hero, Testimonial, Team-Mitglied, Logo-Leiste, Logo-Slider
 *
 *   Native Blocks – block.json + JS (Vite-Build), InnerBlocks-fähig
 *                   CTA-Banner, Accordion/FAQ, Icon+Text
 *
 * Neue Blöcke hinzufügen:
 *   1. Ordner unter blocks/{name}/ anlegen
 *   2. block.json + render.php (ACF) oder edit.js (Native) erstellen
 *   3. In medialab_register_blocks() eintragen
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
    // Eigene Kategorie als erste einfügen (optional – Blöcke landen unter 'design')
    // Aktuelle Konfiguration: alle Blöcke unter vorhandener 'design'-Kategorie
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
        'parallax',
        'before-after',
        'slider',
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
    ];

    foreach ( $native_blocks as $block ) {
        $config_file = MEDIALAB_BLOCKS_DIR . $block . '/block.json';
        if ( file_exists( $config_file ) ) {
            register_block_type( $config_file );
        }
    }
}

// =============================================================================
// Block-Assets enqueuen
// =============================================================================

add_action( 'enqueue_block_editor_assets', 'medialab_enqueue_block_editor_assets' );

function medialab_enqueue_block_editor_assets(): void {
    $dist_uri   = plugin_dir_url(  dirname( __FILE__ ) ) . 'assets/dist/';
    $dist_dir   = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/dist/';
    $plugin_uri = plugin_dir_url(  dirname( __FILE__ ) );
    $plugin_dir = plugin_dir_path( dirname( __FILE__ ) );

    // Editor-Override-CSS (Größen-Fixes für neue Blöcke)
    $override_css = $plugin_dir . 'assets/css/block-editor-overrides.css';
    if ( file_exists( $override_css ) ) {
        wp_enqueue_style(
            'medialab-block-editor-overrides',
            $plugin_uri . 'assets/css/block-editor-overrides.css',
            [ 'wp-edit-blocks' ],
            filemtime( $override_css )
        );
    }

    // Editor-CSS für alle Blöcke
    $editor_css = $dist_dir . 'css/blocks-editor.css';
    if ( file_exists( $editor_css ) ) {
        wp_enqueue_style(
            'medialab-blocks-editor',
            $dist_uri . 'css/blocks-editor.css',
            [ 'wp-edit-blocks' ],
            filemtime( $editor_css )
        );
    }

    // Native Block JS (edit.js Bundle)
    $blocks_js = $dist_dir . 'js/blocks.js';
    if ( file_exists( $blocks_js ) ) {
        wp_enqueue_script(
            'medialab-blocks',
            $dist_uri . 'js/blocks.js',
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ],
            filemtime( $blocks_js ),
            true
        );
    }
}

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

    // Accordion JS (nur wenn Accordion-Block auf der Seite)
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

    // Swiper für Logo-Slider (nur wenn Block auf der Seite)
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
