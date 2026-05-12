<?php
/**
 * ACF Block Field Groups – PHP-Registrierung
 *
 * Registriert alle Feldgruppen für die 5 ACF-basierten Gutenberg Blocks.
 * Kein ACF-JSON, kein manuelles Backend-Setup erforderlich.
 *
 * Blöcke:
 *   medialab/hero         → group_block_hero
 *   medialab/testimonial  → group_block_testimonial
 *   medialab/team-member  → group_block_team_member
 *   medialab/logo-grid    → group_block_logo_grid
 *   medialab/logo-slider  → group_block_logo_slider
 *
 * Neues Feld (WCAG 1.3.1):
 *   hero_heading_level – wählt ob der Hero-Titel als H1 oder H2 ausgegeben wird
 *
 * @package MediaLabAgencyCore
 * @since   1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/init', 'medialab_register_acf_block_fields' );

function medialab_register_acf_block_fields(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    // =========================================================================
    // HERO BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'      => 'group_block_hero',
        'title'    => 'Hero Block',
        'fields'   => [
            [
                'key'           => 'field_hero_bg_image',
                'label'         => 'Hintergrundbild',
                'name'          => 'hero_bg_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'instructions'  => 'Empfohlen: min. 1920×1080px',
            ],
            [
                'key'           => 'field_hero_overlay',
                'label'         => 'Overlay-Deckkraft',
                'name'          => 'hero_overlay',
                'type'          => 'number',
                'default_value' => 40,
                'min'           => 0,
                'max'           => 100,
                'step'          => 5,
                'append'        => '%',
                'instructions'  => '0 = kein Overlay, 100 = vollständig schwarz',
            ],
            [
                'key'          => 'field_hero_kicker',
                'label'        => 'Kicker',
                'name'         => 'hero_kicker',
                'type'         => 'text',
                'placeholder'  => 'z.B. Willkommen',
                'instructions' => 'Kleiner Text oberhalb des Titels (optional)',
            ],
            [
                'key'          => 'field_hero_title',
                'label'        => 'Titel',
                'name'         => 'hero_title',
                'type'         => 'text',
                'instructions' => 'Hauptüberschrift. Leer lassen = Seitentitel wird verwendet.',
            ],
            [
                'key'   => 'field_hero_subtitle',
                'label' => 'Untertitel',
                'name'  => 'hero_subtitle',
                'type'  => 'textarea',
                'rows'  => 3,
            ],
            // ── WCAG 1.3.1: Überschriften-Ebene ─────────────────────────────
            [
                'key'           => 'field_hero_heading_level',
                'label'         => 'Überschriften-Ebene',
                'name'          => 'hero_heading_level',
                'type'          => 'select',
                'choices'       => [
                    'h2' => 'H2 – Abschnittsüberschrift (Standard)',
                    'h1' => 'H1 – Seitenhauptüberschrift',
                ],
                'default_value' => 'h2',
                'instructions'  => 'H1 nur wählen wenn kein anderes H1 auf der Seite existiert (WCAG 1.3.1)',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_hero_height',
                'label'         => 'Höhe',
                'name'          => 'hero_height',
                'type'          => 'select',
                'choices'       => [
                    'large'  => 'Groß (70vh)',
                    'full'   => 'Vollbild (100vh)',
                    'medium' => 'Mittel (50vh)',
                ],
                'default_value' => 'large',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_hero_content_align',
                'label'         => 'Ausrichtung',
                'name'          => 'hero_content_align',
                'type'          => 'select',
                'choices'       => [
                    'center' => 'Zentriert',
                    'left'   => 'Links',
                    'right'  => 'Rechts',
                ],
                'default_value' => 'center',
                'wrapper'       => [ 'width' => '50' ],
            ],
            // ── CTA Button 1 ─────────────────────────────────────────────────
            [
                'key'   => 'field_hero_cta_tab',
                'label' => 'CTA-Button 1',
                'type'  => 'tab',
            ],
            [
                'key'         => 'field_hero_cta_text',
                'label'       => 'Button-Text',
                'name'        => 'hero_cta_text',
                'type'        => 'text',
                'placeholder' => 'z.B. Jetzt starten',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_hero_cta_url',
                'label'       => 'Button-URL',
                'name'        => 'hero_cta_url',
                'type'        => 'url',
                'placeholder' => 'https://',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_hero_cta_style',
                'label'         => 'Button-Stil',
                'name'          => 'hero_cta_style',
                'type'          => 'select',
                'choices'       => [
                    'primary'   => 'Primary',
                    'secondary' => 'Secondary',
                    'outline'   => 'Outline',
                ],
                'default_value' => 'primary',
                'wrapper'       => [ 'width' => '50' ],
            ],
            // ── CTA Button 2 ─────────────────────────────────────────────────
            [
                'key'   => 'field_hero_cta2_tab',
                'label' => 'CTA-Button 2 (optional)',
                'type'  => 'tab',
            ],
            [
                'key'         => 'field_hero_cta2_text',
                'label'       => 'Button-Text',
                'name'        => 'hero_cta2_text',
                'type'        => 'text',
                'placeholder' => 'z.B. Mehr erfahren',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_hero_cta2_url',
                'label'       => 'Button-URL',
                'name'        => 'hero_cta2_url',
                'type'        => 'url',
                'placeholder' => 'https://',
                'wrapper'     => [ 'width' => '50' ],
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/hero',
        ] ] ],
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen'        => '',
    ] );

    // =========================================================================
    // TESTIMONIAL BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_testimonial',
        'title'  => 'Testimonial Block',
        'fields' => [
            [
                'key'          => 'field_testimonial_quote',
                'label'        => 'Zitat',
                'name'         => 'testimonial_quote',
                'type'         => 'textarea',
                'rows'         => 4,
                'required'     => 1,
                'instructions' => 'Der Zitat-Text (ohne Anführungszeichen)',
            ],
            [
                'key'     => 'field_testimonial_name',
                'label'   => 'Name',
                'name'    => 'testimonial_name',
                'type'    => 'text',
                'wrapper' => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_testimonial_role',
                'label'        => 'Rolle / Unternehmen',
                'name'         => 'testimonial_role',
                'type'         => 'text',
                'placeholder'  => 'z.B. CEO, Muster GmbH',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_testimonial_image',
                'label'         => 'Porträtfoto',
                'name'          => 'testimonial_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
                'instructions'  => 'Quadratisch, min. 96×96px (optional)',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_testimonial_rating',
                'label'        => 'Sterne-Bewertung',
                'name'         => 'testimonial_rating',
                'type'         => 'number',
                'min'          => 0,
                'max'          => 5,
                'step'         => 1,
                'instructions' => '1–5 Sterne. 0 oder leer = Sterne ausblenden.',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_testimonial_style',
                'label'         => 'Stil',
                'name'          => 'testimonial_style',
                'type'          => 'select',
                'choices'       => [
                    'card'     => 'Card (mit Rahmen)',
                    'minimal'  => 'Minimal (linker Balken)',
                    'centered' => 'Zentriert',
                ],
                'default_value' => 'card',
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/testimonial',
        ] ], [ [
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'testimonial',
        ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );

    // =========================================================================
    // TEAM-MITGLIED BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_team_member',
        'title'  => 'Team-Mitglied Block',
        'fields' => [
            [
                'key'           => 'field_team_image',
                'label'         => 'Foto',
                'name'          => 'team_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Quadratisch, min. 400×400px empfohlen',
            ],
            [
                'key'     => 'field_team_name',
                'label'   => 'Name',
                'name'    => 'team_name',
                'type'    => 'text',
                'wrapper' => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_team_role',
                'label'       => 'Rolle',
                'name'        => 'team_role',
                'type'        => 'text',
                'placeholder' => 'z.B. Geschäftsführer',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'   => 'field_team_bio',
                'label' => 'Kurzbeschreibung',
                'name'  => 'team_bio',
                'type'  => 'textarea',
                'rows'  => 3,
            ],
            // ── Social Links ─────────────────────────────────────────────────
            [
                'key'   => 'field_team_social_tab',
                'label' => 'Social Links',
                'type'  => 'tab',
            ],
            [
                'key'         => 'field_team_email',
                'label'       => 'E-Mail',
                'name'        => 'team_email',
                'type'        => 'email',
                'placeholder' => 'name@example.com',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_team_linkedin',
                'label'       => 'LinkedIn',
                'name'        => 'team_linkedin',
                'type'        => 'url',
                'placeholder' => 'https://linkedin.com/in/…',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_team_xing',
                'label'       => 'Xing',
                'name'        => 'team_xing',
                'type'        => 'url',
                'placeholder' => 'https://xing.com/profile/…',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_team_instagram',
                'label'       => 'Instagram',
                'name'        => 'team_instagram',
                'type'        => 'url',
                'placeholder' => 'https://instagram.com/…',
                'wrapper'     => [ 'width' => '50' ],
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/team-member',
        ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );

    // =========================================================================
    // LOGO-LEISTE BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_logo_grid',
        'title'  => 'Logo-Leiste Block',
        'fields' => [
            [
                'key'           => 'field_logo_grid_source',
                'label'         => 'Logo-Quelle',
                'name'          => 'logo_grid_source',
                'type'          => 'radio',
                'choices'       => [
                    'cpt'    => 'Logos aus CPT (Menü: Logos)',
                    'manual' => 'Manuell (Repeater unten)',
                ],
                'default_value' => 'cpt',
                'layout'        => 'horizontal',
                'instructions'  => 'CPT empfohlen – Logos zentral unter „Logos" verwalten.',
                'wrapper'       => [ 'width' => '100' ],
            ],
            [
                'key'         => 'field_logo_grid_title',
                'label'       => 'Überschrift',
                'name'        => 'logo_grid_title',
                'type'        => 'text',
                'placeholder' => 'z.B. Unsere Partner',
                'instructions'=> 'Kleiner Label-Text über den Logos (optional)',
            ],
            [
                'key'           => 'field_logo_grid_columns',
                'label'         => 'Spalten',
                'name'          => 'logo_grid_columns',
                'type'          => 'select',
                'choices'       => [
                    '3' => '3 Spalten',
                    '4' => '4 Spalten',
                    '5' => '5 Spalten',
                    '6' => '6 Spalten',
                ],
                'default_value' => '4',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_logo_grid_grayscale',
                'label'         => 'Graustufen',
                'name'          => 'logo_grid_grayscale',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Ja',
                'ui_off_text'   => 'Nein',
                'instructions'  => 'Logos grau darstellen, bei Hover farbig',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_logo_grid_logos',
                'label'        => 'Logos',
                'name'         => 'logo_grid_logos',
                'type'         => 'repeater',
                'min'          => 0,
                'layout'       => 'block',
                'button_label' => 'Logo hinzufügen',
                'instructions' => 'Wird nur verwendet wenn Quelle „Manuell" gewählt ist.',
                'conditional_logic' => [ [ [
                    'field'    => 'field_logo_grid_source',
                    'operator' => '==',
                    'value'    => 'manual',
                ] ] ],
                'sub_fields' => [
                    [
                        'key'           => 'field_logo_grid_image',
                        'label'         => 'Logo',
                        'name'          => 'logo_image',
                        'type'          => 'image',
                        'return_format' => 'array',
                        'preview_size'  => 'thumbnail',
                        'required'      => 0,
                        'wrapper'       => [ 'width' => '33' ],
                    ],
                    [
                        'key'          => 'field_logo_grid_alt',
                        'label'        => 'Alt-Text / Firmenname',
                        'name'         => 'logo_alt',
                        'type'         => 'text',
                        'placeholder'  => 'z.B. Muster GmbH',
                        'instructions' => 'Wichtig für Barrierefreiheit (WCAG 1.1.1)',
                        'wrapper'      => [ 'width' => '33' ],
                    ],
                    [
                        'key'         => 'field_logo_grid_url',
                        'label'       => 'Link (optional)',
                        'name'        => 'logo_url',
                        'type'        => 'url',
                        'placeholder' => 'https://',
                        'wrapper'     => [ 'width' => '34' ],
                    ],
                ],
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/logo-grid',
        ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );

    // =========================================================================
    // LOGO-SLIDER BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_logo_slider',
        'title'  => 'Logo-Slider Block',
        'fields' => [
            [
                'key'           => 'field_logo_slider_source',
                'label'         => 'Logo-Quelle',
                'name'          => 'logo_slider_source',
                'type'          => 'radio',
                'choices'       => [
                    'cpt'    => 'Logos aus CPT (Menü: Logos)',
                    'manual' => 'Manuell (Repeater im Block)',
                ],
                'default_value' => 'cpt',
                'layout'        => 'horizontal',
                'instructions'  => 'CPT empfohlen – Logos zentral unter „Logos" verwalten.',
                'wrapper'       => [ 'width' => '100' ],
            ],
            [
                'key'         => 'field_logo_slider_title',
                'label'       => 'Überschrift',
                'name'        => 'logo_slider_title',
                'type'        => 'text',
                'placeholder' => 'z.B. Das vertrauen uns',
                'instructions'=> 'Kleiner Label-Text über dem Slider (optional)',
            ],
            [
                'key'           => 'field_logo_slider_autoplay',
                'label'         => 'Autoplay',
                'name'          => 'logo_slider_autoplay',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'An',
                'ui_off_text'   => 'Aus',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_logo_slider_loop',
                'label'         => 'Loop',
                'name'          => 'logo_slider_loop',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'An',
                'ui_off_text'   => 'Aus',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_logo_slider_grayscale',
                'label'         => 'Graustufen',
                'name'          => 'logo_slider_grayscale',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
                'ui_on_text'    => 'Ja',
                'ui_off_text'   => 'Nein',
                'wrapper'       => [ 'width' => '34' ],
            ],
            [
                'key'           => 'field_logo_slider_speed',
                'label'         => 'Geschwindigkeit',
                'name'          => 'logo_slider_speed',
                'type'          => 'number',
                'default_value' => 3000,
                'min'           => 500,
                'max'           => 10000,
                'step'          => 500,
                'append'        => 'ms',
                'instructions'  => 'Scroll-Geschwindigkeit in Millisekunden',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'        => 'field_logo_slider_logos',
                'label'      => 'Logos',
                'name'       => 'logo_slider_logos',
                'type'       => 'repeater',
                'min'        => 1,
                'layout'     => 'block',
                'button_label' => 'Logo hinzufügen',
                'sub_fields' => [
                    [
                        'key'           => 'field_logo_slider_image',
                        'label'         => 'Logo',
                        'name'          => 'logo_image',
                        'type'          => 'image',
                        'return_format' => 'array',
                        'preview_size'  => 'thumbnail',
                        'required'      => 1,
                        'wrapper'       => [ 'width' => '33' ],
                    ],
                    [
                        'key'          => 'field_logo_slider_alt',
                        'label'        => 'Alt-Text / Firmenname',
                        'name'         => 'logo_alt',
                        'type'         => 'text',
                        'placeholder'  => 'z.B. Muster GmbH',
                        'instructions' => 'Wichtig für Barrierefreiheit (WCAG 1.1.1)',
                        'wrapper'      => [ 'width' => '33' ],
                    ],
                    [
                        'key'         => 'field_logo_slider_url',
                        'label'       => 'Link (optional)',
                        'name'        => 'logo_url',
                        'type'        => 'url',
                        'placeholder' => 'https://',
                        'wrapper'     => [ 'width' => '34' ],
                    ],
                ],
            ],
        ],
        'location' => [ [ [
            'param'    => 'block',
            'operator' => '==',
            'value'    => 'medialab/logo-slider',
        ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );

    // =========================================================================
    // PARALLAX BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_parallax',
        'title'  => 'Parallax-Sektion',
        'fields' => [
            [
                'key'           => 'field_parallax_image',
                'label'         => 'Hintergrundbild',
                'name'          => 'parallax_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'required'      => 1,
                'instructions'  => 'Empfohlen: min. 1920×1080px, JPG/WebP.',
                'wrapper'       => [ 'width' => '100' ],
            ],
            [
                'key'           => 'field_parallax_speed',
                'label'         => 'Parallax-Intensität',
                'name'          => 'parallax_speed',
                'type'          => 'number',
                'default_value' => 40,
                'min'           => 0,
                'max'           => 100,
                'step'          => 5,
                'append'        => '%',
                'instructions'  => '0 = kein Effekt, 100 = maximaler Versatz',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_parallax_min_height',
                'label'         => 'Mindesthöhe',
                'name'          => 'parallax_min_height',
                'type'          => 'number',
                'default_value' => 400,
                'min'           => 100,
                'step'          => 50,
                'append'        => 'px',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_parallax_content_align',
                'label'         => 'Inhalt-Ausrichtung',
                'name'          => 'parallax_content_align',
                'type'          => 'radio',
                'choices'       => [ 'top' => 'Oben', 'center' => 'Mitte', 'bottom' => 'Unten' ],
                'default_value' => 'center',
                'layout'        => 'horizontal',
                'wrapper'       => [ 'width' => '34' ],
            ],
            [
                'key'           => 'field_parallax_content_width',
                'label'         => 'Inhalt-Breite',
                'name'          => 'parallax_content_width',
                'type'          => 'radio',
                'choices'       => [ 'narrow' => 'Eng (640px)', 'medium' => 'Mittel (960px)', 'full' => 'Voll' ],
                'default_value' => 'medium',
                'layout'        => 'horizontal',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_parallax_overlay_color',
                'label'         => 'Overlay-Farbe',
                'name'          => 'parallax_overlay_color',
                'type'          => 'color_picker',
                'default_value' => '#000000',
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_parallax_overlay_opacity',
                'label'         => 'Overlay-Deckkraft',
                'name'          => 'parallax_overlay_opacity',
                'type'          => 'number',
                'default_value' => 30,
                'min'           => 0,
                'max'           => 100,
                'step'          => 5,
                'append'        => '%',
                'instructions'  => '0 = kein Overlay',
                'wrapper'       => [ 'width' => '25' ],
            ],
        ],
        'location'   => [ [ [ 'param' => 'block', 'operator' => '==', 'value' => 'medialab/parallax' ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );

    // =========================================================================
    // BEFORE / AFTER BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_before_after',
        'title'  => 'Vorher / Nachher',
        'fields' => [
            [
                'key'           => 'field_ba_image_before',
                'label'         => 'Vorher-Bild',
                'name'          => 'ba_image_before',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'required'      => 1,
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_ba_image_after',
                'label'         => 'Nachher-Bild',
                'name'          => 'ba_image_after',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'required'      => 1,
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_ba_label_before',
                'label'         => 'Label „Vorher"',
                'name'          => 'ba_label_before',
                'type'          => 'text',
                'default_value' => 'Vorher',
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_ba_label_after',
                'label'         => 'Label „Nachher"',
                'name'          => 'ba_label_after',
                'type'          => 'text',
                'default_value' => 'Nachher',
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_ba_start_position',
                'label'         => 'Startposition',
                'name'          => 'ba_start_position',
                'type'          => 'number',
                'default_value' => 50,
                'min'           => 0,
                'max'           => 100,
                'step'          => 5,
                'append'        => '%',
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_ba_aspect_ratio',
                'label'         => 'Seitenverhältnis',
                'name'          => 'ba_aspect_ratio',
                'type'          => 'select',
                'choices'       => [
                    'auto' => 'Auto (Bildhöhe)',
                    '16:9' => '16:9 (Querformat)',
                    '4:3'  => '4:3 (Standard)',
                    '1:1'  => '1:1 (Quadrat)',
                    '3:4'  => '3:4 (Hochformat)',
                ],
                'default_value' => 'auto',
                'wrapper'       => [ 'width' => '25' ],
            ],
        ],
        'location'   => [ [ [ 'param' => 'block', 'operator' => '==', 'value' => 'medialab/before-after' ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );

    // =========================================================================
    // SLIDER BLOCK
    // =========================================================================
    acf_add_local_field_group( [
        'key'    => 'group_block_slider',
        'title'  => 'Slider-Einstellungen',
        'fields' => [

            // ── Folien (Repeater) ─────────────────────────────────────────────
            [
                'key'          => 'field_slider_slides',
                'label'        => 'Folien',
                'name'         => 'slider_slides',
                'type'         => 'repeater',
                'min'          => 0,
                'layout'       => 'block',
                'button_label' => 'Folie hinzufügen',
                'instructions' => 'Reihenfolge per Drag & Drop ändern.',
                'wrapper'      => [ 'width' => '100' ],
                'sub_fields'   => [
                    [
                        'key'           => 'field_slide_image',
                        'label'         => 'Bild',
                        'name'          => 'slide_image',
                        'type'          => 'image',
                        'return_format' => 'array',
                        'preview_size'  => 'medium',
                        'instructions'  => 'Hauptbild der Folie (optional)',
                        'wrapper'       => [ 'width' => '50' ],
                    ],
                    [
                        'key'         => 'field_slide_heading',
                        'label'       => 'Überschrift',
                        'name'        => 'slide_heading',
                        'type'        => 'text',
                        'placeholder' => 'Folientitel',
                        'wrapper'     => [ 'width' => '50' ],
                    ],
                    [
                        'key'         => 'field_slide_text',
                        'label'       => 'Text',
                        'name'        => 'slide_text',
                        'type'        => 'wysiwyg',
                        'tabs'        => 'all',
                        'toolbar'     => 'basic',
                        'media_upload'=> 0,
                        'wrapper'     => [ 'width' => '100' ],
                    ],
                    [
                        'key'         => 'field_slide_btn_label',
                        'label'       => 'Button-Text',
                        'name'        => 'slide_btn_label',
                        'type'        => 'text',
                        'placeholder' => 'Mehr erfahren',
                        'wrapper'     => [ 'width' => '33' ],
                    ],
                    [
                        'key'         => 'field_slide_btn_url',
                        'label'       => 'Button-URL',
                        'name'        => 'slide_btn_url',
                        'type'        => 'url',
                        'placeholder' => 'https://',
                        'wrapper'     => [ 'width' => '34' ],
                    ],
                    [
                        'key'           => 'field_slide_btn_target',
                        'label'         => 'In neuem Tab',
                        'name'          => 'slide_btn_target',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'wrapper'       => [ 'width' => '16' ],
                    ],
                    [
                        'key'         => 'field_slide_class',
                        'label'       => 'CSS-Klasse (opt.)',
                        'name'        => 'slide_class',
                        'type'        => 'text',
                        'placeholder' => 'custom-slide',
                        'wrapper'     => [ 'width' => '17' ],
                    ],
                ],
            ],

            // ── Swiper-Optionen ───────────────────────────────────────────────
            [
                'key'           => 'field_slider_autoplay',
                'label'         => 'Autoplay',
                'name'          => 'slider_autoplay',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_slider_autoplay_delay',
                'label'         => 'Autoplay-Delay',
                'name'          => 'slider_autoplay_delay',
                'type'          => 'number',
                'default_value' => 4000,
                'min'           => 500,
                'step'          => 500,
                'append'        => 'ms',
                'wrapper'       => [ 'width' => '25' ],
                'conditional_logic' => [ [ [ 'field' => 'field_slider_autoplay', 'operator' => '==', 'value' => '1' ] ] ],
            ],
            [
                'key'           => 'field_slider_loop',
                'label'         => 'Endlos-Loop',
                'name'          => 'slider_loop',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_slider_navigation',
                'label'         => 'Pfeile',
                'name'          => 'slider_navigation',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'wrapper'       => [ 'width' => '25' ],
            ],
            [
                'key'           => 'field_slider_pagination',
                'label'         => 'Seitenzahlen',
                'name'          => 'slider_pagination',
                'type'          => 'select',
                'choices'       => [ 'bullets' => 'Punkte', 'progressbar' => 'Leiste', 'none' => 'Aus' ],
                'default_value' => 'bullets',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_slider_effect',
                'label'         => 'Übergangseffekt',
                'name'          => 'slider_effect',
                'type'          => 'select',
                'choices'       => [ 'slide' => 'Schieben', 'fade' => 'Überblenden', 'coverflow' => 'Coverflow' ],
                'default_value' => 'slide',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_slider_speed',
                'label'         => 'Transition',
                'name'          => 'slider_speed',
                'type'          => 'number',
                'default_value' => 600,
                'min'           => 100,
                'step'          => 100,
                'append'        => 'ms',
                'wrapper'       => [ 'width' => '34' ],
            ],
            [
                'key'           => 'field_slider_slides_per_view',
                'label'         => 'Sichtbare Folien',
                'name'          => 'slider_slides_per_view',
                'type'          => 'number',
                'default_value' => 1,
                'min'           => 1,
                'max'           => 6,
                'instructions'  => '1 = Vollbreite. >1 = mehrere Folien nebeneinander.',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_slider_space_between',
                'label'         => 'Abstand zwischen Folien',
                'name'          => 'slider_space_between',
                'type'          => 'number',
                'default_value' => 0,
                'min'           => 0,
                'step'          => 4,
                'append'        => 'px',
                'wrapper'       => [ 'width' => '33' ],
            ],
            [
                'key'           => 'field_slider_centered',
                'label'         => 'Aktive Folie zentrieren',
                'name'          => 'slider_centered',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => [ 'width' => '34' ],
            ],
        ],
        'location'   => [ [ [ 'param' => 'block', 'operator' => '==', 'value' => 'medialab/slider' ] ] ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ] );
}
