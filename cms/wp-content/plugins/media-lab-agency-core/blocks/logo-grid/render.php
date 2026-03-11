<?php
/**
 * Logo-Grid Block – ACF Render Template
 *
 * ACF-Felder:
 *   logo_grid_title     Text      Überschrift (optional)
 *   logo_grid_columns   Select    3 | 4 | 5 | 6 (Standard: 4)
 *   logo_grid_logos     Repeater
 *     └── logo_image    Image     Logo-Bild
 *     └── logo_url      URL       Verlinkung (optional)
 *     └── logo_alt      Text      Alt-Text (fallback: Dateiname)
 *   logo_grid_grayscale True/False Logos graustufen (Standard: true)
 *
 * @package MediaLabAgencyCore
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$title     = get_field( 'logo_grid_title' );
$columns   = (int) ( get_field( 'logo_grid_columns' ) ?: 4 );
$logos     = get_field( 'logo_grid_logos' );
$grayscale = get_field( 'logo_grid_grayscale' ) !== false;

if ( empty( $logos ) ) return;

$block_classes = 'ml-block-logo-grid ml-logo-grid--cols-' . $columns;
if ( $grayscale )                      $block_classes .= ' ml-logo-grid--grayscale';
if ( ! empty( $block['className'] ) )  $block_classes .= ' ' . $block['className'];
if ( ! empty( $block['align'] ) )      $block_classes .= ' align' . $block['align'];

$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

?>
<div class="<?php echo esc_attr( $block_classes ); ?>"<?php echo $block_id; ?>>

    <?php if ( $title ) : ?>
    <p class="ml-logo-grid__title"><?php echo esc_html( $title ); ?></p>
    <?php endif; ?>

    <ul class="ml-logo-grid__list" role="list">
        <?php foreach ( $logos as $item ) :
            $img = $item['logo_image'];
            $url = $item['logo_url'] ?? '';
            $img_url = is_array( $img ) ? ( $img['url'] ?? '' ) : (string) $img;
            $alt     = $item['logo_alt'] ?? ( is_array( $img ) ? ( $img['alt'] ?? '' ) : '' );
            $w       = is_array( $img ) ? ( $img['width']  ?? 200 ) : 200;
            $h       = is_array( $img ) ? ( $img['height'] ?? 80  ) : 80;

            if ( ! $img_url ) continue;
        ?>
        <li class="ml-logo-grid__item">
            <?php if ( $url ) : ?>
            <a href="<?php echo esc_url( $url ); ?>" target="_blank"
               rel="noopener noreferrer" class="ml-logo-grid__link">
            <?php endif; ?>

                <img src="<?php echo esc_url( $img_url ); ?>"
                     alt="<?php echo esc_attr( $alt ); ?>"
                     class="ml-logo-grid__logo"
                     width="<?php echo (int) $w; ?>"
                     height="<?php echo (int) $h; ?>"
                     loading="lazy">

            <?php if ( $url ) : ?></a><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>

</div>
