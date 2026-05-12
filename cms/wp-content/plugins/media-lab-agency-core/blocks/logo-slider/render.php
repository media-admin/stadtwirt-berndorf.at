<?php
/**
 * Logo-Slider Block – ACF Render Template
 *
 * Seit v1.11.0: Logos können aus dem CPT `medialab_logo` geladen werden.
 * Über das Feld `logo_slider_source` kann zwischen CPT und manuellem
 * Repeater gewählt werden (Rückwärtskompatibilität).
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 * @updated 1.11.0  CPT-Quelle hinzugefügt
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$title     = get_field( 'logo_slider_title' );
$speed     = (int) ( get_field( 'logo_slider_speed' )    ?: 3000 );
$autoplay  = get_field( 'logo_slider_autoplay' ) !== false;
$loop      = get_field( 'logo_slider_loop' )     !== false;
$grayscale = get_field( 'logo_slider_grayscale' ) !== false;
$source    = get_field( 'logo_slider_source' )   ?: 'cpt';

// ── Logos laden ───────────────────────────────────────────────────────────────
$logos = [];

if ( $source === 'cpt' && function_exists( 'medialab_get_logos' ) ) {
    // CPT-Quelle
    foreach ( medialab_get_logos() as $logo ) {
        $img = $logo['image'];
        $logos[] = [
            'logo_image' => $img,
            'logo_alt'   => $logo['name'] ?: ( is_array( $img ) ? ( $img['alt'] ?? '' ) : '' ),
            'logo_url'   => $logo['url'],
        ];
    }
} else {
    // Manueller Repeater (legacy / Fallback)
    $logos = get_field( 'logo_slider_logos' ) ?: [];
}

if ( empty( $logos ) ) {
    if ( $is_preview ) {
        echo '<div style="padding:1.5rem;background:#f9fafb;text-align:center;color:#aaa;font-size:.875rem;">'
            . esc_html( $source === 'cpt'
                ? __( 'Logo-Slider – noch keine Logos im CPT "Logos" vorhanden.', 'media-lab-core' )
                : __( 'Logo-Slider – bitte Logos im Repeater hinzufügen.', 'media-lab-core' )
            )
            . '</div>';
    }
    return;
}

static $slider_count = 0;
$slider_count++;
$slider_id = 'ml-logo-slider-' . $slider_count;

$block_classes = 'ml-block-logo-slider';
if ( $grayscale )                     $block_classes .= ' ml-logo-slider--grayscale';
if ( ! empty( $block['className'] ) ) $block_classes .= ' ' . $block['className'];
if ( ! empty( $block['align'] ) )     $block_classes .= ' align' . $block['align'];
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
                         style="max-height: 80px; max-width: 220px; width: auto; height: auto; object-fit: contain;"
                         loading="lazy"
                         draggable="false">
                <?php if ( $url ) : ?></a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
