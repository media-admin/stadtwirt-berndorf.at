<?php
/**
 * ACF Feldgruppe: Google Maps CPT (gmap)
 *
 * Vormals manuell im Backend angelegt, jetzt via PHP registriert.
 * Neues Feld: embed_src (Google Maps Embed-URL für iframe-Einbindung)
 *
 * Shortcode-Verwendung:
 *   [google_map id="42"]
 *   [google_map id="42" fullwidth="true"]
 *
 * @package MediaLabAgencyCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/include_fields', function() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'   => 'group_gmap',
        'title' => 'Map Details',
        'fields' => array(

            // ── Embed-URL (neu) ───────────────────────────────────────────────
            array(
                'key'          => 'field_gmap_embed_src',
                'label'        => 'Google Maps Embed-URL',
                'name'         => 'embed_src',
                'type'         => 'url',
                'required'     => 1,
                'instructions' => 'Google Maps öffnen → Teilen → Karte einbetten → den src="…"-Wert aus dem iframe-Code kopieren und hier einfügen.',
                'placeholder'  => 'https://www.google.com/maps/embed?pb=...',
                'wrapper'      => array( 'width' => '' ),
            ),

            // ── Bestehende Felder (weiterhin gespeichert, für den Shortcode
            //    nicht mehr zwingend erforderlich) ───────────────────────────
            array(
                'key'          => 'field_gmap_address',
                'label'        => 'Adresse',
                'name'         => 'address',
                'type'         => 'text',
                'required'     => 0,
                'instructions' => 'Vollständige Adresse (optional, nur zur internen Orientierung)',
                'placeholder'  => 'Musterstraße 123, 1010 Wien',
                'wrapper'      => array( 'width' => '' ),
            ),
            array(
                'key'          => 'field_gmap_marker_title',
                'label'        => 'Marker Titel',
                'name'         => 'marker_title',
                'type'         => 'text',
                'required'     => 0,
                'instructions' => 'Wird als title-Attribut des iframes verwendet (Barrierefreiheit)',
                'placeholder'  => 'Unser Büro',
                'wrapper'      => array( 'width' => '50' ),
            ),
            array(
                'key'          => 'field_gmap_map_height',
                'label'        => 'Höhe (px)',
                'name'         => 'map_height',
                'type'         => 'number',
                'required'     => 0,
                'instructions' => 'Höhe der Karte in Pixeln. Leer lassen = Standard 450px.',
                'default_value'=> 450,
                'min'          => 200,
                'max'          => 1200,
                'step'         => 50,
                'append'       => 'px',
                'wrapper'      => array( 'width' => '50' ),
            ),

            // ── Nicht mehr aktiv genutzte Felder (Daten bleiben erhalten) ────
            array(
                'key'          => 'field_gmap_lat',
                'label'        => 'Latitude (Breitengrad)',
                'name'         => 'latitude',
                'type'         => 'text',
                'required'     => 0,
                'instructions' => 'Nicht mehr für die Kartenanzeige benötigt (wird durch Embed-URL ersetzt)',
                'placeholder'  => '48.2082',
                'wrapper'      => array( 'width' => '50' ),
            ),
            array(
                'key'          => 'field_gmap_lng',
                'label'        => 'Longitude (Längengrad)',
                'name'         => 'longitude',
                'type'         => 'text',
                'required'     => 0,
                'instructions' => 'Nicht mehr für die Kartenanzeige benötigt (wird durch Embed-URL ersetzt)',
                'placeholder'  => '16.3738',
                'wrapper'      => array( 'width' => '50' ),
            ),
            array(
                'key'          => 'field_gmap_zoom',
                'label'        => 'Zoom Level',
                'name'         => 'zoom',
                'type'         => 'number',
                'required'     => 0,
                'instructions' => 'Nicht mehr aktiv (Zoom wird über die Embed-URL gesteuert)',
                'default_value'=> 15,
                'min'          => 1,
                'max'          => 20,
                'step'         => 1,
                'wrapper'      => array( 'width' => '50' ),
            ),
            array(
                'key'          => 'field_gmap_marker_description',
                'label'        => 'Marker Beschreibung',
                'name'         => 'marker_description',
                'type'         => 'textarea',
                'required'     => 0,
                'rows'         => 3,
                'placeholder'  => 'Zusätzliche Infos für den Marker',
                'wrapper'      => array( 'width' => '' ),
            ),
            array(
                'key'           => 'field_gmap_style',
                'label'         => 'Map Style',
                'name'          => 'map_style',
                'type'          => 'select',
                'required'      => 0,
                'instructions'  => 'Nicht mehr aktiv (Style wird über die Embed-URL gesteuert)',
                'choices'       => array(
                    'default' => 'Standard',
                    'silver'  => 'Silber',
                    'dark'    => 'Dark Mode',
                    'retro'   => 'Retro',
                ),
                'default_value' => 'default',
                'return_format' => 'value',
                'wrapper'       => array( 'width' => '50' ),
            ),

        ),
        'location' => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'gmap',
                ),
            ),
        ),
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen'        => array(),
        'active'                => true,
    ) );
} );