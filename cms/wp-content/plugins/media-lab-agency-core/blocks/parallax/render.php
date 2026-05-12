<?php
/**
 * Block-Render: Parallax-Sektion
 *
 * ACF-Felder (Inspector):
 *   parallax_image           Image      Hintergrundbild (Pflicht)
 *   parallax_speed           Number     Parallax-Intensität 0–100 (Standard: 40)
 *   parallax_overlay_color   Text       CSS-Farbe (Standard: #000000)
 *   parallax_overlay_opacity Number     Deckkraft 0–100 (Standard: 30)
 *   parallax_min_height      Number     Mindesthöhe in px (Standard: 400)
 *   parallax_content_align   Select     Inhalt-Ausrichtung (oben/mitte/unten)
 *   parallax_content_width   Select     Inhalt-Breite (eng/mittel/voll)
 *
 * InnerBlocks: Beliebiger Content wird über dem Bild gerendert.
 * JS: rAF-basierter Parallax-Scroll, prefers-reduced-motion respektiert.
 *
 * @package MediaLabAgencyCore
 * @since   1.11.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$image   = get_field( 'parallax_image' );
$img_url = '';
if ( is_array( $image ) ) {
    $img_url = $image['sizes']['large'] ?? $image['url'] ?? '';
} elseif ( is_numeric( $image ) ) {
    $src     = wp_get_attachment_image_src( (int) $image, 'large' );
    $img_url = $src ? $src[0] : '';
}

if ( empty( $img_url ) && ! $is_preview ) return;

$speed        = max( 0, min( 100, (int) ( get_field( 'parallax_speed' )           ?: 40  ) ) );
$overlay_col  = get_field( 'parallax_overlay_color' )   ?: '#000000';
$overlay_op   = max( 0, min( 100, (int) ( get_field( 'parallax_overlay_opacity' ) ?: 30  ) ) );
$min_height   = max( 100, (int) ( get_field( 'parallax_min_height' )              ?: 400 ) );
$content_align = get_field( 'parallax_content_align' ) ?: 'center';
$content_width = get_field( 'parallax_content_width' ) ?: 'medium';

// Wrapper-Klassen
$classes = array_filter( [
    'ml-block-parallax',
    'ml-parallax--align-' . sanitize_html_class( $content_align ),
    'ml-parallax--width-'  . sanitize_html_class( $content_width ),
    ! empty( $block['className'] ) ? $block['className'] : '',
    ! empty( $block['align'] )     ? 'align' . $block['align'] : '',
] );

$block_id   = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';
$overlay_rgba = $overlay_op > 0
    ? 'background-color: ' . esc_attr( $overlay_col ) . '; opacity: ' . ( $overlay_op / 100 ) . ';'
    : '';

$data_attrs = ' data-parallax-speed="' . esc_attr( $speed ) . '"';

if ( $img_url ) {
    $data_attrs .= ' data-parallax-img="' . esc_url( $img_url ) . '"';
}

// Editor-Placeholder wenn kein Bild gesetzt
if ( $is_preview && ! $img_url ) {
    echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"'
        . ' style="min-height:' . $min_height . 'px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;">';
    echo '<p style="color:#aaa;font-size:.875rem;">'
        . esc_html__( 'Parallax-Sektion – bitte Hintergrundbild im Inspector wählen.', 'media-lab-core' )
        . '</p>';
    echo '</div>';
    return;
}
?>
<section
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    <?php echo $block_id; ?>
    <?php echo $data_attrs; ?>
    style="min-height: <?php echo (int) $min_height; ?>px;"
    aria-label="<?php esc_attr_e( 'Parallax-Sektion', 'media-lab-core' ); ?>">

    <!-- Hintergrundbild (wird per JS via transform verschoben) -->
    <div class="ml-parallax__bg"
         aria-hidden="true"
         style="background-image: url('<?php echo esc_url( $img_url ); ?>');">
    </div>

    <?php if ( $overlay_op > 0 ) : ?>
    <div class="ml-parallax__overlay"
         aria-hidden="true"
         style="<?php echo esc_attr( $overlay_rgba ); ?>">
    </div>
    <?php endif; ?>

    <!-- InnerBlocks-Content -->
    <div class="ml-parallax__content">
        <div class="ml-parallax__inner">
            <?php echo $content; ?>
        </div>
    </div>

</section>
