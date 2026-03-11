<?php
/**
 * Hero Block – ACF Render Template
 *
 * ACF-Felder:
 *   hero_bg_image       Image    Hintergrundbild (URL)
 *   hero_overlay        Number   Overlay-Deckkraft 0–100 (Standard: 40)
 *   hero_kicker         Text     Kleiner Text oberhalb des Titels (optional)
 *   hero_title          Text     Hauptüberschrift
 *   hero_subtitle       Textarea Untertitel / Beschreibungstext (optional)
 *   hero_cta_text       Text     Button-Beschriftung (optional)
 *   hero_cta_url        URL      Button-Ziel-URL (optional)
 *   hero_cta_style      Select   primary | secondary | outline
 *   hero_cta2_text      Text     Zweiter Button (optional)
 *   hero_cta2_url       URL      Zweiter Button URL (optional)
 *   hero_height         Select   full | large | medium  (Standard: large)
 *   hero_content_align  Select   left | center | right  (Standard: center)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Felder einlesen ────────────────────────────────────────────────────────────
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

// ── Block-Klassen + Attribute ─────────────────────────────────────────────────
$block_classes = 'ml-block-hero ml-hero--' . esc_attr( $height ) . ' ml-hero--' . esc_attr( $content_align );
if ( ! empty( $block['className'] ) ) $block_classes .= ' ' . $block['className'];
if ( ! empty( $block['align'] ) )     $block_classes .= ' align' . $block['align'];

$bg_url = is_array( $bg_image ) ? ( $bg_image['url'] ?? '' ) : (string) $bg_image;

$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

?>
<section class="<?php echo esc_attr( $block_classes ); ?>"<?php echo $block_id; ?>
         style="<?php echo $bg_url ? 'background-image:url(' . esc_url( $bg_url ) . ')' : ''; ?>">

    <?php if ( $overlay > 0 ) : ?>
    <div class="ml-hero__overlay" style="opacity:<?php echo esc_attr( $overlay / 100 ); ?>"></div>
    <?php endif; ?>

    <div class="ml-hero__inner container">
        <div class="ml-hero__content">

            <?php if ( $kicker ) : ?>
            <p class="ml-hero__kicker"><?php echo esc_html( $kicker ); ?></p>
            <?php endif; ?>

            <?php if ( $title ) : ?>
            <h1 class="ml-hero__title"><?php echo wp_kses_post( $title ); ?></h1>
            <?php endif; ?>

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
