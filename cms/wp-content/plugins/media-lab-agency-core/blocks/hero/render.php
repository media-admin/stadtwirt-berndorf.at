<?php
/**
 * Hero Block – ACF Render Template
 *
 * WCAG-Patches:
 *   ✅ 1.3.1 Info and Relationships: Überschriften-Ebene konfigurierbar (h1/h2)
 *            Verhindert doppelte <h1> wenn WordPress den Seitentitel ebenfalls als h1 ausgibt.
 *            Neues ACF-Feld: hero_heading_level (select: h1 | h2, Standard: h2)
 *
 * ACF-Felder (vollständig):
 *   hero_bg_image, hero_overlay, hero_kicker, hero_title, hero_subtitle
 *   hero_cta_text, hero_cta_url, hero_cta_style
 *   hero_cta2_text, hero_cta2_url
 *   hero_height, hero_content_align
 *   hero_heading_level  ← NEU (select: h1 | h2, Standard: h2)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0 / WCAG-Patch
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$bg_image      = get_field( 'hero_bg_image' );
$overlay       = (int) ( get_field( 'hero_overlay' )   ?: 40 );
$kicker        = get_field( 'hero_kicker' );
$title         = get_field( 'hero_title' )    ?: get_the_title();
$subtitle      = get_field( 'hero_subtitle' );
$cta_text      = get_field( 'hero_cta_text' );
$cta_url       = get_field( 'hero_cta_url' );
$cta_style     = get_field( 'hero_cta_style' ) ?: 'primary';
$cta2_text     = get_field( 'hero_cta2_text' );
$cta2_url      = get_field( 'hero_cta2_url' );
$height        = get_field( 'hero_height' )         ?: 'large';
$content_align = get_field( 'hero_content_align' )  ?: 'center';

// ✅ WCAG 1.3.1: Überschriften-Ebene – Standard h2 um doppeltes <h1> zu vermeiden
$heading_level = get_field( 'hero_heading_level' ) ?: 'h2';
$heading_level = in_array( $heading_level, [ 'h1', 'h2' ], true ) ? $heading_level : 'h2';

$block_classes = 'ml-block-hero ml-hero--' . esc_attr( $height ) . ' ml-hero--' . esc_attr( $content_align );
if ( ! empty( $block['className'] ) ) $block_classes .= ' ' . $block['className'];
if ( ! empty( $block['align'] ) )     $block_classes .= ' align' . $block['align'];

$bg_url   = is_array( $bg_image ) ? ( $bg_image['url'] ?? '' ) : (string) $bg_image;
$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

?>
<section class="<?php echo esc_attr( $block_classes ); ?>"<?php echo $block_id; ?>
         style="<?php echo $bg_url ? 'background-image:url(' . esc_url( $bg_url ) . ')' : ''; ?>">

    <?php if ( $overlay > 0 ) : ?>
    <div class="ml-hero__overlay" style="opacity:<?php echo esc_attr( $overlay / 100 ); ?>"
         aria-hidden="true"></div>
    <?php endif; ?>

    <div class="ml-hero__inner container">
        <div class="ml-hero__content">

            <?php if ( $kicker ) : ?>
            <p class="ml-hero__kicker"><?php echo esc_html( $kicker ); ?></p>
            <?php endif; ?>

            <?php if ( $title ) :
                // ✅ Dynamisches Heading-Level (h1 oder h2)
                printf(
                    '<%1$s class="ml-hero__title">%2$s</%1$s>',
                    esc_attr( $heading_level ),
                    wp_kses_post( $title )
                );
            endif; ?>

            <?php if ( $subtitle ) : ?>
            <p class="ml-hero__subtitle"><?php echo wp_kses_post( $subtitle ); ?></p>
            <?php endif; ?>

            <?php if ( $cta_text || $cta2_text ) : ?>
            <div class="ml-hero__actions">
                <?php if ( $cta_text && $cta_url ) : ?>
                <a href="<?php echo esc_url( $cta_url ); ?>"
                   class="btn btn--<?php echo esc_attr( $cta_style ); ?> ml-hero__cta">
                    <?php echo esc_html( $cta_text ); ?>
                </a>
                <?php endif; ?>

                <?php if ( $cta2_text && $cta2_url ) : ?>
                <a href="<?php echo esc_url( $cta2_url ); ?>"
                   class="btn btn--outline ml-hero__cta ml-hero__cta--secondary">
                    <?php echo esc_html( $cta2_text ); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>
