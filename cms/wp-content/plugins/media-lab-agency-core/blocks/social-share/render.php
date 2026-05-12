<?php
/**
 * Block-Render: Share-Buttons
 *
 * Felder:
 *   ss_block_override    – Globale Einstellungen überschreiben (bool)
 *   ss_block_services    – Repeater: Kanäle + Reihenfolge
 *   ss_block_style       – Stil colored|mono|outline|ghost
 *   ss_block_icon_only   – Nur Icons (bool)
 *   ss_block_layout      – horizontal|vertical
 *   ss_block_show_label  – Äußeres Label anzeigen (bool)
 *   ss_block_label       – Label-Text
 *
 * @package MediaLabAgencyCore
 * @since   1.9.0
 * @updated 1.9.2  style + icon_only
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'medialab_social_share_render' ) ) return;

$defaults = medialab_social_share_get_defaults();
$override = (bool) get_field( 'ss_block_override' );

if ( $override ) {
    // Services
    $block_rows = get_field( 'ss_block_services' );
    if ( is_array( $block_rows ) && ! empty( $block_rows ) ) {
        $keys     = array_filter( array_map(
            fn( $row ) => sanitize_key( $row['ss_block_service'] ?? '' ),
            $block_rows
        ) );
        $services = ! empty( $keys ) ? implode( ',', $keys ) : $defaults['services'];
    } else {
        $services = $defaults['services'];
    }

    // Stil
    $style_raw = get_field( 'ss_block_style' );
    $style = in_array( $style_raw, [ 'colored', 'mono', 'outline', 'ghost' ], true )
        ? $style_raw
        : $defaults['style'];

    // Icon-only
    $icon_only_raw = get_field( 'ss_block_icon_only' );
    $icon_only = ( $icon_only_raw !== null ) ? (bool) $icon_only_raw : $defaults['icon_only'];

    // Layout
    $layout_raw = get_field( 'ss_block_layout' );
    $layout = in_array( $layout_raw, [ 'horizontal', 'vertical' ], true )
        ? $layout_raw
        : $defaults['layout'];

    // Label
    $show_label_raw = get_field( 'ss_block_show_label' );
    $show_label = ( $show_label_raw !== null ) ? (bool) $show_label_raw : $defaults['show_label'];

    $label_raw  = get_field( 'ss_block_label' );
    $label = ( $label_raw !== null && $label_raw !== '' )
        ? (string) $label_raw
        : $defaults['label'];

} else {
    $services   = $defaults['services'];
    $style      = $defaults['style'];
    $icon_only  = $defaults['icon_only'];
    $layout     = $defaults['layout'];
    $show_label = $defaults['show_label'];
    $label      = $defaults['label'];
}

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'medialab-share-block',
) );

echo '<div ' . $wrapper_attributes . '>';
echo medialab_social_share_render( compact( 'services', 'style', 'icon_only', 'layout', 'show_label', 'label' ) );
echo '</div>';
