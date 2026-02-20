<?php
/**
 * Hero Image - ACF Fields + Helper
 */

if (!defined('ABSPATH')) exit;

add_action('acf/init', function() {
    if (!function_exists('acf_add_local_field_group')) return;

    // ─── Options Page (globales Fallback) ────────────────────
    if (function_exists('acf_add_options_sub_page')) {
        acf_add_options_sub_page(array(
            'page_title'  => 'Hero Image',
            'menu_title'  => 'Hero Image',
            'parent_slug' => 'agency-core',
            'capability'  => 'manage_options',
            'slug'        => 'hero-image-settings',
        ));
    }

    // ─── Globale Fallback-Felder ──────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_hero_global',
        'title'  => 'Hero Image – Globale Einstellungen',
        'fields' => array(
            array(
                'key'           => 'field_hero_fallback_desktop',
                'label'         => 'Fallback Desktop',
                'name'          => 'hero_fallback_desktop',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Wird angezeigt wenn kein Hero Image gesetzt ist.',
            ),
            array(
                'key'           => 'field_hero_fallback_mobile',
                'label'         => 'Fallback Mobile (optional)',
                'name'          => 'hero_fallback_mobile',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Leer lassen = Desktop-Bild wird verwendet.',
            ),
            array(
                'key'           => 'field_hero_overlay_opacity',
                'label'         => 'Overlay Deckkraft',
                'name'          => 'hero_overlay_opacity',
                'type'          => 'range',
                'min'           => 0,
                'max'           => 90,
                'step'          => 5,
                'default_value' => 40,
                'instructions'  => 'Dunkel-Overlay über dem Bild (0 = kein Overlay, 90 = sehr dunkel)',
            ),
        ),
        'location' => array(array(array(
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'hero-image-settings',
        ))),
    ));

    // ─── Post-spezifische Felder ──────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_hero_image',
        'title'  => 'Hero Image',
        'fields' => array(
            array(
                'key'           => 'field_hero_image_desktop',
                'label'         => 'Hero Image Desktop',
                'name'          => 'hero_image_desktop',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Empfohlen: 1920×600px. Leer lassen = globales Fallback.',
            ),
            array(
                'key'           => 'field_hero_image_mobile',
                'label'         => 'Hero Image Mobile (optional)',
                'name'          => 'hero_image_mobile',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Empfohlen: 768×500px. Leer lassen = Desktop-Bild wird verwendet.',
            ),
            array(
                'key'           => 'field_hero_image_title',
                'label'         => 'Hero Titel (überschreiben)',
                'name'          => 'hero_image_title',
                'type'          => 'text',
                'instructions'  => 'Leer lassen = Post/Page Titel wird verwendet.',
            ),
            array(
                'key'           => 'field_hero_image_show',
                'label'         => 'Hero anzeigen',
                'name'          => 'hero_image_show',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
            ),
        ),
        'location' => array(
            array(array(
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'page',
            )),
            array(array(
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'post',
            )),
            array(array(
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'event',
            )),
            array(array(
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'job',
            )),
        ),
        'menu_order' => 5,
        'position'   => 'side',
    ));
});


/**
 * Helper: Hero Image Daten holen
 */
function media_lab_get_hero_image($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();

    $show = get_field('hero_image_show', $post_id);
    if ($show === false) return null; // explizit deaktiviert

    $desktop = get_field('hero_image_desktop', $post_id);
    $mobile  = get_field('hero_image_mobile', $post_id);
    $title   = get_field('hero_image_title', $post_id) ?: get_the_title($post_id);

    // Fallback auf globale Einstellungen
    if (!$desktop) {
        $desktop = get_field('hero_fallback_desktop', 'option');
    }
    if (!$mobile) {
        $mobile = get_field('hero_fallback_mobile', 'option') ?: $desktop;
    }

    if (!$desktop) return null;

    $opacity = get_field('hero_overlay_opacity', 'option') ?? 40;

    return array(
        'desktop' => $desktop,
        'mobile'  => $mobile ?: $desktop,
        'title'   => $title,
        'opacity' => $opacity,
    );
}
