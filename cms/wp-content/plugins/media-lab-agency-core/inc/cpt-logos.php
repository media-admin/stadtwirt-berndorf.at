<?php
/**
 * Logo CPT – medialab_logo
 *
 * Ermöglicht das zentrale Verwalten von Partner-/Kunden-Logos im WP-Backend.
 * Sortierung per Drag & Drop via menu_order (Simple Post Order Plugin oder ähnlich)
 * bzw. manuell im Bearbeitungsbildschirm.
 *
 * Wird vom Logo-Slider-Block verwendet wenn „Logos aus CPT" aktiviert ist.
 *
 * @package MediaLabAgencyCore
 * @since   1.11.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// CPT REGISTRIERUNG
// =============================================================================

add_action( 'init', 'medialab_register_logo_cpt' );

function medialab_register_logo_cpt(): void {
    register_post_type( 'medialab_logo', [
        'labels' => [
            'name'               => 'Logos',
            'singular_name'      => 'Logo',
            'add_new'            => 'Neu',
            'add_new_item'       => 'Logo hinzufügen',
            'edit_item'          => 'Logo bearbeiten',
            'new_item'           => 'Neues Logo',
            'view_item'          => 'Logo ansehen',
            'search_items'       => 'Logos suchen',
            'not_found'          => 'Keine Logos gefunden',
            'not_found_in_trash' => 'Keine Logos im Papierkorb',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 26,
        'menu_icon'           => 'dashicons-format-image',
        'supports'            => [ 'title', 'page-attributes' ], // page-attributes → menu_order
        'show_in_rest'        => true,
        'rest_base'           => 'logos',
        'rewrite'             => false,
        'has_archive'         => false,
    ] );
}

// =============================================================================
// ACF FELDER
// =============================================================================

add_action( 'acf/init', 'medialab_register_logo_cpt_fields' );

function medialab_register_logo_cpt_fields(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'   => 'group_medialab_logo',
        'title' => 'Logo-Details',
        'fields' => [
            [
                'key'           => 'field_logo_cpt_image',
                'label'         => 'Logo-Bild',
                'name'          => 'logo_cpt_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'required'      => 1,
                'instructions'  => 'SVG, PNG oder WebP empfohlen. Transparenter Hintergrund für Graustufen-Effekt.',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_logo_cpt_name',
                'label'        => 'Firmenname',
                'name'         => 'logo_cpt_name',
                'type'         => 'text',
                'instructions' => 'Wird als Alt-Text verwendet. Leer = Post-Titel.',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_logo_cpt_url',
                'label'       => 'Website-URL',
                'name'        => 'logo_cpt_url',
                'type'        => 'url',
                'placeholder' => 'https://',
                'instructions'=> 'Optionaler Link – öffnet in neuem Tab.',
                'wrapper'     => [ 'width' => '50' ],
            ],
        ],
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'medialab_logo' ] ] ],
        'menu_order'      => 0,
        'position'        => 'normal',
        'style'           => 'default',
        'label_placement' => 'top',
    ] );
}

// =============================================================================
// HELPER: CPT-LOGOS ABRUFEN
// =============================================================================

/**
 * Gibt alle veröffentlichten Logos aus dem CPT zurück.
 *
 * @return array<int, array{image: array, name: string, url: string}>
 */
function medialab_get_logos(): array {
    $posts = get_posts( [
        'post_type'      => 'medialab_logo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ] );

    $logos = [];

    foreach ( $posts as $post ) {
        $image = get_field( 'logo_cpt_image', $post->ID );
        if ( ! $image ) continue;

        $name = get_field( 'logo_cpt_name', $post->ID ) ?: get_the_title( $post );
        $url  = get_field( 'logo_cpt_url',  $post->ID ) ?: '';

        $logos[] = compact( 'image', 'name', 'url' );
    }

    return $logos;
}

// =============================================================================
// ADMIN: Reihenfolge-Hinweis + Spalte "Logo"
// =============================================================================

add_filter( 'manage_medialab_logo_posts_columns', function ( array $cols ): array {
    return array_merge(
        [ 'cb' => $cols['cb'] ?? '' ],
        [ 'logo_preview' => 'Vorschau' ],
        [ 'title'        => 'Firmenname' ],
        [ 'logo_url'     => 'URL' ],
        [ 'date'         => 'Datum' ],
    );
} );

add_action( 'manage_medialab_logo_posts_custom_column', function ( string $col, int $post_id ): void {
    if ( $col === 'logo_preview' ) {
        $img = get_field( 'logo_cpt_image', $post_id );
        if ( $img ) {
            $src = $img['sizes']['thumbnail'] ?? $img['url'];
            echo '<img src="' . esc_url( $src ) . '" alt="" style="max-height:40px;width:auto;">';
        }
    }
    if ( $col === 'logo_url' ) {
        $url = get_field( 'logo_cpt_url', $post_id );
        if ( $url ) {
            echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
               . esc_html( $url ) . '</a>';
        } else {
            echo '<span style="color:#aaa;">—</span>';
        }
    }
}, 10, 2 );
