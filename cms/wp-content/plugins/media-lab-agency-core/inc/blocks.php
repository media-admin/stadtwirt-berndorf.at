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
    $dist_uri = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/dist/';
    $dist_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/dist/';

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
    if ( has_block( 'medialab/logo-slider' ) ) {
        $swiper_js  = get_template_directory_uri() . '/assets/dist/js/chunks/swiper.js';
        $swiper_css = get_template_directory_uri() . '/assets/dist/css/swiper.css';

        wp_enqueue_script(  'swiper', $swiper_js,  [], '11.0.0', true );
        wp_enqueue_style(   'swiper', $swiper_css, [], '11.0.0' );

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
}
