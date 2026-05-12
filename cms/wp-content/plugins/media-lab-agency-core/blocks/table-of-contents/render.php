<?php
/**
 * Block-Render: Inhaltsverzeichnis
 *
 * Felder:
 *   toc_title        – Titel (string)
 *   toc_mode         – inline | sticky
 *   toc_depth        – 2 | 3 | 4
 *   toc_min_headings – Mindestanzahl Überschriften (int)
 *
 * Fixes v1.10.1:
 *   - is_singular() ist false bei REST-Requests (Editor-Preview) →
 *     Heading-ID-Filter wird in medialab_toc_add_heading_ids() nicht ausgeführt.
 *     render.php ruft deshalb medialab_toc_get_headings() direkt auf, was intern
 *     apply_filters('the_content', ...) nutzt. Das funktioniert auch ohne is_singular().
 *   - Im Editor-Kontext (REST_REQUEST) wird immer ein Placeholder gerendert,
 *     auch wenn der Block-Inhalt noch leer ist.
 *
 * @package MediaLabAgencyCore
 * @since   1.10.0
 * @updated 1.10.1  REST-Kontext-Fix, Editor-Placeholder verbessert
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'medialab_toc_render' ) ) return;

$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;

$args = array(
    'title'        => (string) ( get_field( 'toc_title' ) ?? __( 'Inhaltsverzeichnis', 'media-lab-core' ) ),
    'mode'         => get_field( 'toc_mode' )  ?: 'inline',
    'depth'        => (int) ( get_field( 'toc_depth' ) ?: 3 ),
    'min_headings' => (int) ( get_field( 'toc_min_headings' ) ?: 2 ),
);

// Im Editor-Kontext min_headings auf 1 senken damit der Preview
// schon bei einer einzigen Überschrift etwas zeigt.
if ( $is_editor ) {
    $args['min_headings'] = 1;
}

$toc_html = medialab_toc_render( $args );

$wrapper_attributes = get_block_wrapper_attributes( array(
    'class' => 'medialab-toc-block',
) );

// ── Editor-Placeholder (wenn kein Inhalt gerendert werden konnte) ─────────────
if ( empty( $toc_html ) ) {
    echo '<div ' . $wrapper_attributes . '>';
    echo '<div class="toc toc--inline" style="opacity:.6;">';
    echo '<div class="toc__header">';
    echo '<span class="toc__title">' . esc_html( $args['title'] ?: __( 'Inhaltsverzeichnis', 'media-lab-core' ) ) . '</span>';
    echo '</div>';
    echo '<p style="font-size:.8125rem;color:#9ca3af;margin:.5rem 0 0;padding:0 .35rem;">';
    echo esc_html__( 'Wird automatisch aus den H2/H3/H4-Überschriften des Beitrags generiert. Im Frontend sichtbar sobald der Beitrag Überschriften enthält.', 'media-lab-core' );
    echo '</p>';
    echo '</div>';
    echo '</div>';
    return;
}

echo '<div ' . $wrapper_attributes . '>';
echo $toc_html; // phpcs:ignore WordPress.Security.EscapeOutput
echo '</div>';
