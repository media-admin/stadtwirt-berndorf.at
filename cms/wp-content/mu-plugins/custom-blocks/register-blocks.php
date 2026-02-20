<?php
/**
 * ACF Block Registrations
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

// Register ACF Blocks
add_action('acf/init', 'custom_blocks_register');

function custom_blocks_register() {
    // Check if ACF exists
    if (!function_exists('acf_register_block_type')) {
        return;
    }
    
    // Accordion Block
    acf_register_block_type(array(
        'name'              => 'accordion',
        'title'             => __('Accordion'),
        'description'       => __('Ein interaktives Accordion-Element'),
        'render_template'   => get_template_directory() . '/template-parts/blocks/accordion.php',
        'category'          => 'custom-blocks',
        'icon'              => 'list-view',
        'keywords'          => array('accordion', 'faq', 'toggle', 'collapse'),
        'supports'          => array(
            'align' => false,
            'mode' => true,
            'jsx' => true,
        ),
        'example'  => array(
            'attributes' => array(
                'mode' => 'preview',
                'data' => array(
                    'accordion_items' => array(
                        array(
                            'title' => 'Beispiel Frage',
                            'content' => 'Dies ist eine Beispiel-Antwort.',
                        ),
                    ),
                ),
            ),
        ),
    ));
    
    // Hero Slider Block (für später)
    acf_register_block_type(array(
        'name'              => 'hero-slider',
        'title'             => __('Hero Slider'),
        'description'       => __('Hero Slider mit Swiper.js'),
        'render_template'   => get_template_directory() . '/template-parts/blocks/hero-slider.php',
        'category'          => 'custom-blocks',
        'icon'              => 'images-alt2',
        'keywords'          => array('slider', 'hero', 'carousel'),
        'supports'          => array(
            'align' => array('wide', 'full'),
        ),
    ));
}

// Eigene Block-Kategorie
add_filter('block_categories_all', 'custom_blocks_category', 10, 2);

function custom_blocks_category($categories, $post) {
    return array_merge(
        array(
            array(
                'slug'  => 'custom-blocks',
                'title' => __('Custom Blocks'),
                'icon'  => 'layout',
            ),
        ),
        $categories
    );
}