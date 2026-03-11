<?php
/**
 * Logo-Slider Block – ACF Render Template
 *
 * Verwendet Swiper.js (bereits als Dependency im Projekt vorhanden).
 *
 * ACF-Felder:
 *   logo_slider_title      Text      Überschrift (optional)
 *   logo_slider_logos      Repeater
 *     └── logo_image       Image     Logo-Bild
 *     └── logo_url         URL       Verlinkung (optional)
 *     └── logo_alt         Text      Alt-Text
 *   logo_slider_speed      Number    Scroll-Geschwindigkeit ms (Standard: 3000)
 *   logo_slider_autoplay   True/False Autoplay (Standard: true)
 *   logo_slider_loop       True/False Loop (Standard: true)
 *   logo_slider_grayscale  True/False Graustufen (Standard: true)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$title     = get_field( 'logo_slider_title' );
$logos     = get_field( 'logo_slider_logos' );
$speed     = (int) ( get_field( 'logo_slider_speed' )   ?: 3000 );
$autoplay  = get_field( 'logo_slider_autoplay' ) !== false;
$loop      = get_field( 'logo_slider_loop' )     !== false;
$grayscale = get_field( 'logo_slider_grayscale' ) !== false;

if ( empty( $logos ) ) return;

// Eindeutige ID für mehrere Slider auf einer Seite
static $slider_count = 0;
$slider_count++;
$slider_id = 'ml-logo-slider-' . $slider_count;

$block_classes = 'ml-block-logo-slider';
if ( $grayscale )                      $block_classes .= ' ml-logo-slider--grayscale';
if ( ! empty( $block['className'] ) )  $block_classes .= ' ' . $block['className'];
if ( ! empty( $block['align'] ) )      $block_classes .= ' align' . $block['align'];

$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

$swiper_config = wp_json_encode( [
    'slidesPerView'  => 'auto',
    'spaceBetween'   => 40,
    'loop'           => $loop,
    'speed'          => $speed,
    'autoplay'       => $autoplay ? [ 'delay' => 0, 'disableOnInteraction' => false ] : false,
    'allowTouchMove' => true,
    'pauseOnMouseEnter' => true,
    'a11y'           => [ 'enabled' => true ],
] );

?>
<div class="<?php echo esc_attr( $block_classes ); ?>"<?php echo $block_id; ?>>

    <?php if ( $title ) : ?>
    <p class="ml-logo-slider__title"><?php echo esc_html( $title ); ?></p>
    <?php endif; ?>

    <div id="<?php echo esc_attr( $slider_id ); ?>"
         class="swiper ml-logo-slider__swiper"
         data-swiper='<?php echo esc_attr( $swiper_config ); ?>'>

        <div class="swiper-wrapper ml-logo-slider__track">
            <?php foreach ( $logos as $item ) :
                $img     = $item['logo_image'];
                $url     = $item['logo_url'] ?? '';
                $img_url = is_array( $img ) ? ( $img['url'] ?? '' ) : (string) $img;
                $alt     = $item['logo_alt'] ?? ( is_array( $img ) ? ( $img['alt'] ?? '' ) : '' );
                $w       = is_array( $img ) ? ( $img['width']  ?? 160 ) : 160;
                $h       = is_array( $img ) ? ( $img['height'] ?? 60  ) : 60;

                if ( ! $img_url ) continue;
            ?>
            <div class="swiper-slide ml-logo-slider__slide">
                <?php if ( $url ) : ?>
                <a href="<?php echo esc_url( $url ); ?>" target="_blank"
                   rel="noopener noreferrer" class="ml-logo-slider__link"
                   tabindex="-1" aria-hidden="true">
                <?php endif; ?>

                    <img src="<?php echo esc_url( $img_url ); ?>"
                         alt="<?php echo esc_attr( $alt ); ?>"
                         class="ml-logo-slider__logo"
                         width="<?php echo (int) $w; ?>"
                         height="<?php echo (int) $h; ?>"
                         loading="lazy"
                         draggable="false">

                <?php if ( $url ) : ?></a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div><!-- .swiper -->
</div>
