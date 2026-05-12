<?php
/**
 * Block-Render: Slider (Swiper, Repeater-basiert)
 *
 * Folien werden über den ACF-Repeater „slider_slides" gepflegt.
 * Jede Folie kann enthalten: Bild, Überschrift, Text, Button.
 *
 * Keine InnerBlocks – damit entfallen alle Editor-Limitierungen
 * von ACF PHP-Blöcken. Folien werden direkt als .swiper-slide
 * in PHP gerendert; das JS muss sie nicht mehr nachträglich wrappen.
 *
 * @package MediaLabAgencyCore
 * @since   1.11.0
 * @updated 1.11.5  Repeater statt InnerBlocks
 */

if ( ! defined( 'ABSPATH' ) ) exit;

static $slider_count = 0;
$slider_count++;
$slider_id = 'ml-slider-' . $slider_count;

// ── Swiper-Konfiguration ───────────────────────────────────────────────────────
$autoplay       = get_field( 'slider_autoplay' );
$autoplay_delay = max( 500, (int) ( get_field( 'slider_autoplay_delay' )  ?: 4000 ) );
$loop           = get_field( 'slider_loop' ) !== false;
$navigation     = get_field( 'slider_navigation' ) !== false;
$pagination     = get_field( 'slider_pagination' ) ?: 'bullets';
$spv            = max( 1, (int) ( get_field( 'slider_slides_per_view'  ) ?: 1    ) );
$space_between  = max( 0, (int) ( get_field( 'slider_space_between'    ) ?: 0    ) );
$effect         = get_field( 'slider_effect' ) ?: 'slide';
$speed          = max( 100, (int) ( get_field( 'slider_speed'          ) ?: 600  ) );
$centered       = (bool) get_field( 'slider_centered' );

$swiper_config = [
    'loop'           => $loop,
    'speed'          => $speed,
    'effect'         => $effect,
    'slidesPerView'  => $spv,
    'spaceBetween'   => $space_between,
    'centeredSlides' => $centered,
    'grabCursor'     => true,
    'a11y'           => [ 'enabled' => true ],
];

if ( $autoplay ) {
    $swiper_config['autoplay'] = [
        'delay'                => $autoplay_delay,
        'disableOnInteraction' => false,
        'pauseOnMouseEnter'    => true,
    ];
}
if ( $navigation )         { $swiper_config['navigation'] = true; }
if ( $pagination !== 'none' ) {
    $swiper_config['pagination'] = [
        'clickable' => true,
        'type'      => $pagination === 'progressbar' ? 'progressbar' : 'bullets',
    ];
}

// ── Folien aus Repeater ────────────────────────────────────────────────────────
$slides = get_field( 'slider_slides' ) ?: [];

// Editor-Placeholder wenn keine Folien
if ( empty( $slides ) ) {
    if ( $is_preview ) {
        echo '<div style="padding:2rem;background:#f0f4f8;border:1px dashed #b0c4d8;text-align:center;border-radius:4px;">';
        echo '<p style="color:#6b7280;font-size:.875rem;margin:0;">';
        esc_html_e( 'Slider: Noch keine Folien. Folien im Block-Inspector (unten) über „Folie hinzufügen" anlegen.', 'media-lab-core' );
        echo '</p></div>';
    }
    return;
}

// ── CSS-Klassen ───────────────────────────────────────────────────────────────
$classes = array_filter( [
    'ml-block-slider',
    'ml-slider--effect-' . sanitize_html_class( $effect ),
    $navigation            ? 'ml-slider--has-nav'        : '',
    $pagination !== 'none' ? 'ml-slider--has-pagination' : '',
    ! empty( $block['className'] ) ? $block['className'] : '',
    ! empty( $block['align'] )     ? 'align' . $block['align'] : '',
] );
$block_id_attr = ! empty( $block['anchor'] )
    ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

$wrapper = get_block_wrapper_attributes( [ 'class' => implode( ' ', $classes ) ] );
?>
<div <?php echo $wrapper; ?><?php echo $block_id_attr; ?>>

    <div id="<?php echo esc_attr( $slider_id ); ?>"
         class="swiper ml-slider__swiper"
         data-swiper="<?php echo esc_attr( wp_json_encode( $swiper_config ) ); ?>">

        <div class="swiper-wrapper ml-slider__wrapper">

            <?php foreach ( $slides as $slide ) :
                $img        = $slide['slide_image']     ?? null;
                $heading    = $slide['slide_heading']   ?? '';
                $text       = $slide['slide_text']      ?? '';
                $btn_label  = $slide['slide_btn_label'] ?? '';
                $btn_url    = $slide['slide_btn_url']   ?? '';
                $btn_target = ! empty( $slide['slide_btn_target'] ) ? '_blank' : '_self';
                $custom_cls = sanitize_html_class( $slide['slide_class'] ?? '' );

                $img_url = '';
                $img_alt = '';
                if ( is_array( $img ) ) {
                    $img_url = $img['sizes']['large'] ?? $img['url'] ?? '';
                    $img_alt = $img['alt'] ?? '';
                } elseif ( is_numeric( $img ) && $img ) {
                    $src     = wp_get_attachment_image_src( (int) $img, 'large' );
                    $img_url = $src ? $src[0] : '';
                    $img_alt = get_post_meta( $img, '_wp_attachment_image_alt', true );
                }

                $has_content = $heading || $text || $btn_label;
            ?>
            <div class="swiper-slide ml-slider__slide<?php echo $custom_cls ? ' ' . $custom_cls : ''; ?>">

                <?php if ( $img_url ) : ?>
                <div class="ml-slider__slide-media">
                    <img src="<?php echo esc_url( $img_url ); ?>"
                         alt="<?php echo esc_attr( $img_alt ); ?>"
                         class="ml-slider__slide-img"
                         loading="lazy"
                         draggable="false">
                </div>
                <?php endif; ?>

                <?php if ( $has_content ) : ?>
                <div class="ml-slider__slide-content">
                    <?php if ( $heading ) : ?>
                    <h3 class="ml-slider__slide-heading"><?php echo esc_html( $heading ); ?></h3>
                    <?php endif; ?>

                    <?php if ( $text ) : ?>
                    <div class="ml-slider__slide-text"><?php echo wp_kses_post( $text ); ?></div>
                    <?php endif; ?>

                    <?php if ( $btn_label && $btn_url ) : ?>
                    <a href="<?php echo esc_url( $btn_url ); ?>"
                       class="btn ml-slider__slide-btn"
                       target="<?php echo esc_attr( $btn_target ); ?>"
                       <?php echo $btn_target === '_blank' ? 'rel="noopener noreferrer"' : ''; ?>>
                        <?php echo esc_html( $btn_label ); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
            <?php endforeach; ?>

        </div><!-- .swiper-wrapper -->

        <?php if ( $navigation ) : ?>
        <button class="swiper-button-prev ml-slider__btn"
                aria-label="<?php esc_attr_e( 'Vorherige Folie', 'media-lab-core' ); ?>"></button>
        <button class="swiper-button-next ml-slider__btn"
                aria-label="<?php esc_attr_e( 'Nächste Folie', 'media-lab-core' ); ?>"></button>
        <?php endif; ?>

        <?php if ( $pagination !== 'none' ) : ?>
        <div class="swiper-pagination ml-slider__pagination"></div>
        <?php endif; ?>

    </div><!-- .swiper -->

</div>
