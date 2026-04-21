<?php
/**
 * Hero Image – ACF Fields + Helper
 *
 * @package MediaLab_AgencyCore
 */

if (!defined('ABSPATH')) exit;

add_action('acf/init', function() {
    if (!function_exists('acf_add_local_field_group')) return;

    // =========================================================================
    // 1. Globale Fallback-Felder (Options-Seite)
    // =========================================================================
    acf_add_local_field_group(array(
        'key'    => 'group_hero_global',
        'title'  => 'Hero Image – Globale Einstellungen',
        'fields' => array(

            // ── Fallback-Bilder ───────────────────────────────────────────────
            array(
                'key'           => 'field_hero_fallback_desktop',
                'label'         => 'Fallback Desktop',
                'name'          => 'hero_fallback_desktop',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Wird angezeigt wenn kein seitenspezifisches Hero Image gesetzt ist. Empfohlen: 1920×600px.',
            ),
            array(
                'key'           => 'field_hero_fallback_mobile',
                'label'         => 'Fallback Mobile (optional)',
                'name'          => 'hero_fallback_mobile',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Leer lassen = Desktop-Bild wird für alle Bildschirmgrößen verwendet. Empfohlen: 768×500px.',
            ),

            // ── Overlay ───────────────────────────────────────────────────────
            array(
                'key'           => 'field_hero_overlay_opacity',
                'label'         => 'Overlay Deckkraft (global)',
                'name'          => 'hero_overlay_opacity',
                'type'          => 'range',
                'min'           => 0,
                'max'           => 90,
                'step'          => 5,
                'default_value' => 40,
                'instructions'  => 'Dunkel-Overlay über dem Bild (0 = kein Overlay, 90 = sehr dunkel). Kann pro Seite überschrieben werden.',
                'append'        => '%',
            ),

            // ── Standardhöhe ──────────────────────────────────────────────────
            array(
                'key'           => 'field_hero_default_height',
                'label'         => 'Standard-Höhe',
                'name'          => 'hero_default_height',
                'type'          => 'select',
                'choices'       => array(
                    'md' => 'Mittel (300–600px)',
                    'sm' => 'Klein (200–400px)',
                    'lg' => 'Groß (400–700px)',
                    'xl' => 'Sehr groß (500–800px)',
                ),
                'default_value' => 'md',
                'instructions'  => 'Standard-Höhe für alle Hero-Sektionen (kann pro Seite überschrieben werden).',
            ),

            // ── Standard-Ausrichtung ──────────────────────────────────────────
            array(
                'key'           => 'field_hero_default_align',
                'label'         => 'Standard-Ausrichtung',
                'name'          => 'hero_default_align',
                'type'          => 'select',
                'choices'       => array(
                    'left'   => 'Links',
                    'center' => 'Zentriert',
                    'right'  => 'Rechts',
                ),
                'default_value' => 'left',
            ),

        ),
        'location' => array(array(array(
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'agency-core-hero',
        ))),
        'menu_order' => 15,
    ));


    // =========================================================================
    // 2. Post-spezifische Felder (Seiten, Beiträge, CPTs)
    // =========================================================================
    acf_add_local_field_group(array(
        'key'    => 'group_hero_image',
        'title'  => 'Hero Image',
        'fields' => array(

            // ── Anzeige-Steuerung ─────────────────────────────────────────────
            array(
                'key'           => 'field_hero_image_show',
                'label'         => 'Hero anzeigen',
                'name'          => 'hero_image_show',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
            ),

            // ── Bilder ────────────────────────────────────────────────────────
            array(
                'key'           => 'field_hero_image_desktop',
                'label'         => 'Hero Image Desktop',
                'name'          => 'hero_image_desktop',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Empfohlen: 1920×600px. Leer lassen = globales Fallback wird verwendet.',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),
            array(
                'key'           => 'field_hero_image_mobile',
                'label'         => 'Hero Image Mobile (optional)',
                'name'          => 'hero_image_mobile',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Empfohlen: 768×500px. Leer lassen = Desktop-Bild wird verwendet.',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),

            // ── Inhalt ────────────────────────────────────────────────────────
            array(
                'key'          => 'field_hero_image_title',
                'label'        => 'Hero Titel (überschreiben)',
                'name'         => 'hero_image_title',
                'type'         => 'text',
                'instructions' => 'Leer lassen = Post/Page-Titel wird verwendet.',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),
            array(
                'key'          => 'field_hero_image_subtitle',
                'label'        => 'Untertitel / Teaser',
                'name'         => 'hero_image_subtitle',
                'type'         => 'textarea',
                'rows'         => 2,
                'instructions' => 'Kurzer Text unter dem Titel. Leer lassen = kein Untertitel.',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),

            // ── Button 1 ──────────────────────────────────────────────────────
            array(
                'key'          => 'field_hero_btn1_text',
                'label'        => 'Button 1 – Text',
                'name'         => 'hero_btn1_text',
                'type'         => 'text',
                'instructions' => 'Leer lassen = kein Button.',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),
            array(
                'key'          => 'field_hero_btn1_url',
                'label'        => 'Button 1 – URL',
                'name'         => 'hero_btn1_url',
                'type'         => 'link',
                'return_format'=> 'array',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_btn1_text',
                    'operator' => '!=empty',
                ))),
            ),
            array(
                'key'           => 'field_hero_btn1_style',
                'label'         => 'Button 1 – Stil',
                'name'          => 'hero_btn1_style',
                'type'          => 'select',
                'choices'       => array(
                    'primary' => 'Primary (gefüllt)',
                    'outline' => 'Outline (umrandet)',
                    'ghost'   => 'Ghost (transparent)',
                ),
                'default_value' => 'primary',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_btn1_text',
                    'operator' => '!=empty',
                ))),
            ),

            // ── Button 2 ──────────────────────────────────────────────────────
            array(
                'key'          => 'field_hero_btn2_text',
                'label'        => 'Button 2 – Text (optional)',
                'name'         => 'hero_btn2_text',
                'type'         => 'text',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_btn1_text',
                    'operator' => '!=empty',
                ))),
            ),
            array(
                'key'          => 'field_hero_btn2_url',
                'label'        => 'Button 2 – URL',
                'name'         => 'hero_btn2_url',
                'type'         => 'link',
                'return_format'=> 'array',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_btn2_text',
                    'operator' => '!=empty',
                ))),
            ),
            array(
                'key'           => 'field_hero_btn2_style',
                'label'         => 'Button 2 – Stil',
                'name'          => 'hero_btn2_style',
                'type'          => 'select',
                'choices'       => array(
                    'outline' => 'Outline (umrandet)',
                    'primary' => 'Primary (gefüllt)',
                    'ghost'   => 'Ghost (transparent)',
                ),
                'default_value' => 'outline',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_btn2_text',
                    'operator' => '!=empty',
                ))),
            ),

            // ── Layout-Optionen ───────────────────────────────────────────────
            array(
                'key'           => 'field_hero_image_align',
                'label'         => 'Textausrichtung',
                'name'          => 'hero_image_align',
                'type'          => 'select',
                'choices'       => array(
                    ''       => 'Global (Standard)',
                    'left'   => 'Links',
                    'center' => 'Zentriert',
                    'right'  => 'Rechts',
                ),
                'default_value' => '',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),
            array(
                'key'           => 'field_hero_image_height',
                'label'         => 'Höhe',
                'name'          => 'hero_image_height',
                'type'          => 'select',
                'choices'       => array(
                    ''   => 'Global (Standard)',
                    'sm' => 'Klein',
                    'md' => 'Mittel',
                    'lg' => 'Groß',
                    'xl' => 'Sehr groß',
                ),
                'default_value' => '',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),
            array(
                'key'           => 'field_hero_image_vpos',
                'label'         => 'Textposition (vertikal)',
                'name'          => 'hero_image_vpos',
                'type'          => 'select',
                'choices'       => array(
                    'bottom' => 'Unten (Standard)',
                    'middle' => 'Mitte',
                    'top'    => 'Oben',
                ),
                'default_value' => 'bottom',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),
            array(
                'key'           => 'field_hero_image_opacity',
                'label'         => 'Overlay Deckkraft (überschreiben)',
                'name'          => 'hero_image_opacity',
                'type'          => 'range',
                'min'           => 0,
                'max'           => 90,
                'step'          => 5,
                'default_value' => '',
                'instructions'  => 'Leer lassen = globaler Wert aus den Hero-Einstellungen.',
                'append'        => '%',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_hero_image_show',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),

        ),
        'location' => array(
            array(array('param' => 'post_type', 'operator' => '==', 'value' => 'page')),
            array(array('param' => 'post_type', 'operator' => '==', 'value' => 'post')),
            array(array('param' => 'post_type', 'operator' => '==', 'value' => 'event')),
            array(array('param' => 'post_type', 'operator' => '==', 'value' => 'job')),
        ),
        'menu_order' => 5,
        'position'   => 'side',
    ));
});


// =============================================================================
// Helper: Hero Image Daten holen
// =============================================================================

/**
 * Normalisiert einen ACF-Bildfeld-Wert immer zu einem Array mit 'url', 'alt' etc.
 *
 * ACF kann Bildfelder als Array ODER als Integer-ID zurückgeben – je nachdem ob
 * die Felddefinition aus der DB (mit ggf. return_format=id) oder die programmatisch
 * registrierte (return_format=array) bevorzugt wird. Dieser Helper macht beides
 * verlässlich handhabbar.
 *
 * @param  mixed $value  Rückgabewert von get_field() – Array, int oder false/null
 * @return array|null    Normalisiertes Array mit mindestens 'url' und 'alt', oder null
 */
function _medialab_resolve_image($value): ?array {
    if (!$value) return null;

    // Bereits ein vollständiges Array → direkt verwenden
    if (is_array($value) && !empty($value['url'])) {
        return $value;
    }

    // ACF hat die ID zurückgegeben (oder ein Array ohne URL) → über wp_get_attachment_image_src auflösen
    $id = is_array($value) ? ($value['ID'] ?? ($value['id'] ?? 0)) : (int) $value;
    if ($id <= 0) return null;

    $src = wp_get_attachment_image_src($id, 'full');
    if (!$src) return null;

    return [
        'ID'     => $id,
        'id'     => $id,
        'url'    => $src[0],
        'width'  => $src[1],
        'height' => $src[2],
        'alt'    => get_post_meta($id, '_wp_attachment_image_alt', true) ?: '',
    ];
}

/**
 * Gibt alle Hero-Daten für einen Post zurück.
 *
 * @param  int|null $post_id  Post-ID (null = current post)
 * @return array|null         Daten-Array oder null wenn Hero deaktiviert/kein Bild
 */
function media_lab_get_hero_image(?int $post_id = null) : ?array {

    // get_queried_object_id() ist zuverlässig auch in header.php (außerhalb Loop),
    // get_the_ID() gibt dort oft 0 zurück, was alle get_field()-Calls bricht.
    if (!$post_id) {
        $post_id = get_queried_object_id();
    }
    if (!$post_id) return null;

    // Explizit deaktiviert?
    // ACF gibt für eine noch nie gespeicherte true_false-Field 'false' zurück –
    // NICHT den default_value (1). false/null bedeutet: Feld unberührt → anzeigen.
    // Nur ausblenden wenn der User das Feld explizit auf 0 gesetzt hat.
    $show = get_field('hero_image_show', $post_id);
    if ($show === 0 || $show === '0') return null;

    // Bilder – _medialab_resolve_image() normalisiert Array/ID/false sicher zu Array oder null
    $desktop = _medialab_resolve_image(get_field('hero_image_desktop', $post_id))
            ?? _medialab_resolve_image(get_field('hero_fallback_desktop', 'option'));
    $mobile  = _medialab_resolve_image(get_field('hero_image_mobile', $post_id))
            ?? _medialab_resolve_image(get_field('hero_fallback_mobile', 'option'))
            ?? $desktop;

    if (!$desktop) return null;

    // Texte
    $title    = get_field('hero_image_title', $post_id)    ?: get_the_title($post_id);
    $subtitle = get_field('hero_image_subtitle', $post_id) ?: '';

    // Buttons
    $btn1_text  = get_field('hero_btn1_text', $post_id)  ?: '';
    $btn1_url   = get_field('hero_btn1_url', $post_id)   ?: [];
    $btn1_style = get_field('hero_btn1_style', $post_id) ?: 'primary';
    $btn2_text  = get_field('hero_btn2_text', $post_id)  ?: '';
    $btn2_url   = get_field('hero_btn2_url', $post_id)   ?: [];
    $btn2_style = get_field('hero_btn2_style', $post_id) ?: 'outline';

    // Layout
    $align  = get_field('hero_image_align', $post_id)
           ?: get_field('hero_default_align', 'option')
           ?: 'left';
    $height = get_field('hero_image_height', $post_id)
           ?: get_field('hero_default_height', 'option')
           ?: 'md';
    $vpos   = get_field('hero_image_vpos', $post_id) ?: 'bottom';

    // Opacity: per-post → global
    $opacity_raw = get_field('hero_image_opacity', $post_id);
    $opacity = ($opacity_raw !== '' && $opacity_raw !== null && $opacity_raw !== false)
        ? (int) $opacity_raw
        : (int) (get_field('hero_overlay_opacity', 'option') ?? 40);

    return [
        'desktop'    => $desktop,
        'mobile'     => $mobile,
        'title'      => $title,
        'subtitle'   => $subtitle,
        'btn1_text'  => $btn1_text,
        'btn1_url'   => $btn1_url,
        'btn1_style' => $btn1_style,
        'btn2_text'  => $btn2_text,
        'btn2_url'   => $btn2_url,
        'btn2_style' => $btn2_style,
        'align'      => in_array($align, ['left', 'center', 'right'], true) ? $align : 'left',
        'height'     => in_array($height, ['sm', 'md', 'lg', 'xl'], true) ? $height : 'md',
        'vpos'       => in_array($vpos, ['top', 'middle', 'bottom'], true) ? $vpos : 'bottom',
        'opacity'    => max(0, min(90, $opacity)),
    ];
}
