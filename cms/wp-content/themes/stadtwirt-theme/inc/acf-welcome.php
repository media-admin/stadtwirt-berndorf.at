<?php
/**
 * ACF Feldgruppe: Welcome Page
 *
 * Registriert alle Felder für das "Welcome Page" Template.
 * Wird via functions.php geladen.
 *
 * Felder:
 *   welcome_bg_image      – Hintergrundbild (optional)
 *   welcome_bg_overlay    – Overlay-Deckkraft 0–80%
 *   welcome_logo          – Firmenlogo (überschreibt Custom Logo)
 *   welcome_content       – Flexibler Content-Bereich (WYSIWYG, optional)
 *   welcome_company_name  – Firmenname
 *   welcome_address       – Adresse (Textarea)
 *   welcome_phone         – Telefonnummer
 *   welcome_email         – E-Mail
 *   welcome_social_links  – Repeater: Plattform + URL
 *
 * @package Custom_Theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// SOCIAL ICON HELPER
// Muss vor dem Template verfügbar sein → daher hier und nicht im Template.
// =============================================================================

if ( ! function_exists( 'customtheme_welcome_social_icon' ) ) {
    /**
     * Gibt ein inline SVG-Icon für eine Social-Media-Plattform zurück.
     * Fallback: leerer String → Plattform-Name als Text wird ausgegeben.
     */
    function customtheme_welcome_social_icon( string $platform ): string {
        $icons = [
            'Instagram' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>',
            'Facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
            'LinkedIn'  => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
            'Xing'      => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.182 4H3.5L7.6 10.94 4 18h2.7l3.6-7.06L6.182 4zm9.4-2H13l-7.2 13.18L10.1 22h2.7l-4.3-6.82L15.582 2z"/></svg>',
            'YouTube'   => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02"/></svg>',
            'TikTok'    => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.34 6.34 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.76a4.85 4.85 0 0 1-1.01-.07z"/></svg>',
            'X'         => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.747l7.73-8.835L1.254 2.25H8.08l4.253 5.622 5.91-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        ];

        // Exakter Match
        if ( isset( $icons[ $platform ] ) ) {
            return $icons[ $platform ];
        }

        // Case-insensitiver Fallback
        foreach ( $icons as $key => $svg ) {
            if ( strcasecmp( $key, $platform ) === 0 ) {
                return $svg;
            }
        }

        return '';
    }
}

add_action( 'acf/init', 'customtheme_register_welcome_page_fields' );

function customtheme_register_welcome_page_fields(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_welcome_page',
        'title'  => 'Welcome Page',
        'fields' => [

            // ── Tab: Hintergrund ──────────────────────────────────────────────

            [
                'key'          => 'field_welcome_tab_bg',
                'label'        => 'Hintergrund',
                'type'         => 'tab',
                'placement'    => 'top',
            ],
            [
                'key'           => 'field_welcome_bg_image',
                'label'         => 'Hintergrundbild',
                'name'          => 'welcome_bg_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'instructions'  => 'Optional. Empfohlen: min. 1920×1080px',
                'required'      => 0,
            ],
            [
                'key'           => 'field_welcome_bg_overlay',
                'label'         => 'Overlay-Deckkraft',
                'name'          => 'welcome_bg_overlay',
                'type'          => 'range',
                'default_value' => 40,
                'min'           => 0,
                'max'           => 80,
                'step'          => 5,
                'append'        => '%',
                'instructions'  => '0 = kein Overlay, 80 = stark abgedunkelt',
                'conditional_logic' => [
                    [
                        [
                            'field'    => 'field_welcome_bg_image',
                            'operator' => '!=empty',
                        ],
                    ],
                ],
            ],

            // ── Tab: Logo & Inhalt ────────────────────────────────────────────

            [
                'key'       => 'field_welcome_tab_content',
                'label'     => 'Logo & Inhalt',
                'type'      => 'tab',
                'placement' => 'top',
            ],
            [
                'key'           => 'field_welcome_logo',
                'label'         => 'Logo',
                'name'          => 'welcome_logo',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'instructions'  => 'Optional. Wenn leer: WordPress Custom Logo wird verwendet.',
                'required'      => 0,
            ],
            [
                'key'           => 'field_welcome_content',
                'label'         => 'Content-Bereich',
                'name'          => 'welcome_content',
                'type'          => 'wysiwyg',
                'toolbar'       => 'basic',
                'media_upload'  => 0,
                'instructions'  => 'Optional. Z.B. Willkommenstext, "Coming Soon"-Botschaft etc.',
                'required'      => 0,
            ],

            // ── Tab: Firmendaten ──────────────────────────────────────────────

            [
                'key'       => 'field_welcome_tab_company',
                'label'     => 'Firmendaten',
                'type'      => 'tab',
                'placement' => 'top',
            ],
            [
                'key'          => 'field_welcome_company_name',
                'label'        => 'Firmenname',
                'name'         => 'welcome_company_name',
                'type'         => 'text',
                'placeholder'  => get_bloginfo( 'name' ),
                'instructions' => 'Leer lassen = WordPress-Seitentitel wird verwendet.',
            ],
            [
                'key'          => 'field_welcome_address',
                'label'        => 'Adresse',
                'name'         => 'welcome_address',
                'type'         => 'textarea',
                'rows'         => 3,
                'placeholder'  => "Musterstraße 1\n1010 Wien",
                'instructions' => 'Zeilenumbrüche werden übernommen.',
            ],
            [
                'key'         => 'field_welcome_phone',
                'label'       => 'Telefon',
                'name'        => 'welcome_phone',
                'type'        => 'text',
                'placeholder' => '+43 1 234 56 78',
            ],
            [
                'key'         => 'field_welcome_email',
                'label'       => 'E-Mail',
                'name'        => 'welcome_email',
                'type'        => 'email',
                'placeholder' => 'office@example.com',
            ],

            // ── Tab: Social Media ─────────────────────────────────────────────

            [
                'key'       => 'field_welcome_tab_social',
                'label'     => 'Social Media',
                'type'      => 'tab',
                'placement' => 'top',
            ],
            [
                'key'          => 'field_welcome_social_links',
                'label'        => 'Social Media Links',
                'name'         => 'welcome_social_links',
                'type'         => 'repeater',
                'layout'       => 'table',
                'button_label' => 'Link hinzufügen',
                'sub_fields'   => [
                    [
                        'key'     => 'field_welcome_social_platform',
                        'label'   => 'Plattform',
                        'name'    => 'platform',
                        'type'    => 'select',
                        'choices' => [
                            'Instagram' => 'Instagram',
                            'Facebook'  => 'Facebook',
                            'LinkedIn'  => 'LinkedIn',
                            'Xing'      => 'Xing',
                            'YouTube'   => 'YouTube',
                            'TikTok'    => 'TikTok',
                            'X'         => 'X (Twitter)',
                        ],
                        'default_value' => 'Instagram',
                        'column_width'  => 30,
                    ],
                    [
                        'key'          => 'field_welcome_social_url',
                        'label'        => 'URL',
                        'name'         => 'url',
                        'type'         => 'url',
                        'placeholder'  => 'https://',
                        'column_width' => 70,
                    ],
                ],
            ],

        ],

        // Nur auf Seiten mit diesem Template anzeigen
        'location' => [
            [
                [
                    'param'    => 'page_template',
                    'operator' => '==',
                    'value'    => 'page-templates/template-welcome.php',
                ],
            ],
        ],

        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'seamless',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
}
