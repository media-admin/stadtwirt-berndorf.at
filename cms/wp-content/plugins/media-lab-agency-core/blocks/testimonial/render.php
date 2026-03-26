<?php
/**
 * Testimonial Block – ACF Render Template
 *
 * WCAG-Patches:
 *   ✅ 1.4.1 Use of Color: Sterne nutzen gefülltes (★) vs. leeres (☆) Symbol
 *            statt nur Farbe als Unterscheidungsmerkmal
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0 / WCAG-Patch
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$quote  = get_field( 'testimonial_quote' );
$name   = get_field( 'testimonial_name' );
$role   = get_field( 'testimonial_role' );
$image  = get_field( 'testimonial_image' );
$rating = (int) get_field( 'testimonial_rating' );
$style  = get_field( 'testimonial_style' ) ?: 'card';

if ( ! $quote ) return;

$block_classes = 'ml-block-testimonial ml-testimonial--' . esc_attr( $style );
if ( ! empty( $block['className'] ) ) $block_classes .= ' ' . $block['className'];

$image_url = is_array( $image ) ? ( $image['sizes']['thumbnail'] ?? $image['url'] ?? '' ) : (string) $image;
$block_id  = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

?>
<blockquote class="<?php echo esc_attr( $block_classes ); ?>"<?php echo $block_id; ?>>

    <?php if ( $rating > 0 ) : ?>
    <div class="ml-testimonial__stars"
         role="img"
         aria-label="<?php printf( esc_attr__( 'Bewertung: %d von 5 Sternen', 'media-lab-agency-core' ), $rating ); ?>">
        <?php for ( $i = 1; $i <= 5; $i++ ) :
            // ✅ WCAG 1.4.1: gefüllter (★) vs. leerer Stern (☆) – nicht nur Farbe
            $filled = $i <= $rating;
        ?>
        <span class="ml-testimonial__star<?php echo $filled ? ' ml-testimonial__star--filled' : ''; ?>"
              aria-hidden="true"><?php echo $filled ? '★' : '☆'; ?></span>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <p class="ml-testimonial__quote">
        <span class="ml-testimonial__quote-mark" aria-hidden="true">"</span>
        <?php echo wp_kses_post( $quote ); ?>
        <span class="ml-testimonial__quote-mark ml-testimonial__quote-mark--close" aria-hidden="true">"</span>
    </p>

    <footer class="ml-testimonial__author">
        <?php if ( $image_url ) : ?>
        <img src="<?php echo esc_url( $image_url ); ?>"
             alt="<?php echo esc_attr( $name ); ?>"
             class="ml-testimonial__avatar"
             width="48" height="48"
             loading="lazy">
        <?php endif; ?>

        <div class="ml-testimonial__meta">
            <?php if ( $name ) : ?>
            <cite class="ml-testimonial__name"><?php echo esc_html( $name ); ?></cite>
            <?php endif; ?>
            <?php if ( $role ) : ?>
            <span class="ml-testimonial__role"><?php echo esc_html( $role ); ?></span>
            <?php endif; ?>
        </div>
    </footer>

</blockquote>
