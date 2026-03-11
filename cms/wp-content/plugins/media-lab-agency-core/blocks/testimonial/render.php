<?php
/**
 * Testimonial Block – ACF Render Template
 *
 * ACF-Felder:
 *   testimonial_quote   Textarea  Zitat-Text
 *   testimonial_name    Text      Name der Person
 *   testimonial_role    Text      Rolle / Unternehmen (optional)
 *   testimonial_image   Image     Porträtfoto (optional)
 *   testimonial_rating  Number    Sterne 1–5 (optional, 0 = ausblenden)
 *   testimonial_style   Select    card | minimal | centered (Standard: card)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
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
    <div class="ml-testimonial__stars" aria-label="<?php echo esc_attr( $rating ); ?> von 5 Sternen">
        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
        <span class="ml-testimonial__star<?php echo $i <= $rating ? ' ml-testimonial__star--filled' : ''; ?>"
              aria-hidden="true">★</span>
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
