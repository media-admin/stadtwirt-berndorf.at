<?php
/**
 * Testimonial Block – ACF Render Template
 *
 * Layout: Avatar links (90×90), Body rechts (Name + Rolle + Sterne oben, Zitat unten).
 *
 * WCAG-Fixes:
 *   ✅ 1.4.1  Use of Color: Sterne nutzen ★ (gefüllt) vs. ☆ (leer) –
 *             nicht nur Farbe als Unterscheidungsmerkmal
 *   ✅ ARIA:  role="img" + aria-label mit i18n-String auf dem Rating-Container
 *
 * ACF-Felder:
 *   testimonial_quote   Textarea  Zitat-Text (Pflichtfeld)
 *   testimonial_name    Text      Name der Person
 *   testimonial_role    Text      Rolle / Unternehmen (optional)
 *   testimonial_image   Image     Porträtfoto (optional)
 *   testimonial_rating  Number    Sterne 1–5 (0 / leer = ausblenden)
 *   testimonial_style   Select    card | minimal | centered (Standard: card)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 * @updated 1.7.0 – horizontales Layout, WCAG-Sterne-Fix (Backport aus Stadtwirt)
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

    <?php if ( $image_url ) : ?>
    <div class="ml-testimonial__image">
        <img src="<?php echo esc_url( $image_url ); ?>"
             alt="<?php echo esc_attr( $name ); ?>"
             class="ml-testimonial__avatar-img"
             width="90" height="90"
             loading="lazy">
    </div>
    <?php endif; ?>

    <div class="ml-testimonial__body">

        <div class="ml-testimonial__header">

            <div class="ml-testimonial__meta">
                <?php if ( $name ) : ?>
                <cite class="ml-testimonial__name"><?php echo esc_html( $name ); ?></cite>
                <?php endif; ?>
                <?php if ( $role ) : ?>
                <span class="ml-testimonial__role"><?php echo esc_html( $role ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( $rating > 0 ) : ?>
            <div class="ml-testimonial__rating"
                 role="img"
                 aria-label="<?php printf( esc_attr__( 'Bewertung: %d von 5 Sternen', 'media-lab-agency-core' ), $rating ); ?>">
                <?php for ( $i = 1; $i <= 5; $i++ ) :
                    $filled = $i <= $rating;
                ?>
                <span class="ml-testimonial__star<?php echo $filled ? ' ml-testimonial__star--filled' : ' ml-testimonial__star--empty'; ?>"
                      aria-hidden="true"><?php echo $filled ? '★' : '☆'; ?></span>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        </div>

        <div class="ml-testimonial__quote">
            <?php echo wp_kses_post( $quote ); ?>
        </div>

    </div>

</blockquote>
