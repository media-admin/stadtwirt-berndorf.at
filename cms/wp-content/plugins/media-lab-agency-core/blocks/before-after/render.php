<?php
/**
 * Block-Render: Vorher / Nachher
 * Fix v1.11.3: Aspect-Ratio immer berechnen, Container kollabiert nicht mehr.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$img_before = get_field( 'ba_image_before' );
$img_after  = get_field( 'ba_image_after'  );

if ( ( empty( $img_before ) || empty( $img_after ) ) && ! $is_preview ) return;

$src_before = is_array( $img_before ) ? ( $img_before['url'] ?? '' ) : wp_get_attachment_url( (int) $img_before );
$src_after  = is_array( $img_after  ) ? ( $img_after['url']  ?? '' ) : wp_get_attachment_url( (int) $img_after  );
$alt_before = is_array( $img_before ) ? ( $img_before['alt'] ?? '' ) : '';
$alt_after  = is_array( $img_after  ) ? ( $img_after['alt']  ?? '' ) : '';
$w_before   = is_array( $img_before ) ? (int) ( $img_before['width']  ?? 0 ) : 0;
$h_before   = is_array( $img_before ) ? (int) ( $img_before['height'] ?? 0 ) : 0;

$label_before = get_field( 'ba_label_before'   ) ?: __( 'Vorher',  'media-lab-core' );
$label_after  = get_field( 'ba_label_after'    ) ?: __( 'Nachher', 'media-lab-core' );
$start_pos    = max( 0, min( 100, (int) ( get_field( 'ba_start_position' ) ?: 50 ) ) );
$aspect_ratio = get_field( 'ba_aspect_ratio' ) ?: 'auto';

// Padding-top IMMER berechnen
$ratio_map = [ '16:9' => 56.25, '4:3' => 75.0, '1:1' => 100.0, '3:4' => 133.33 ];

if ( isset( $ratio_map[ $aspect_ratio ] ) ) {
    $padding_pct = $ratio_map[ $aspect_ratio ];
} else {
    // auto: aus Bildabmessungen ableiten
    if ( $w_before > 0 && $h_before > 0 ) {
        $padding_pct = round( ( $h_before / $w_before ) * 100, 4 );
    } else {
        $w_a = is_array( $img_after ) ? (int) ( $img_after['width']  ?? 0 ) : 0;
        $h_a = is_array( $img_after ) ? (int) ( $img_after['height'] ?? 0 ) : 0;
        $padding_pct = ( $w_a > 0 && $h_a > 0 ) ? round( ( $h_a / $w_a ) * 100, 4 ) : 56.25;
    }
}

$padding_top = $padding_pct . '%';

$classes  = array_filter( [
    'ml-block-before-after',
    ! empty( $block['className'] ) ? $block['className'] : '',
    ! empty( $block['align'] )     ? 'align' . $block['align'] : '',
] );
$block_id = ! empty( $block['anchor'] ) ? ' id="' . esc_attr( $block['anchor'] ) . '"' : '';

if ( $is_preview && ( ! $src_before || ! $src_after ) ) {
    echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" style="padding:2rem;background:#f0f0f0;text-align:center;">';
    echo '<p style="color:#aaa;font-size:.875rem;">' . esc_html__( 'Vorher/Nachher – bitte beide Bilder in den Block-Einstellungen wählen.', 'media-lab-core' ) . '</p>';
    echo '</div>';
    return;
}
?>
<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $block_id; ?>>
    <div class="ml-ba__container"
         data-start="<?php echo (int) $start_pos; ?>"
         role="group"
         aria-label="<?php echo esc_attr( sprintf( __( 'Bildvergleich: %1$s und %2$s', 'media-lab-core' ), $label_before, $label_after ) ); ?>">

        <!-- Spacer: gibt Container via padding-top seine Höhe (IMMER gerendert) -->
        <div class="ml-ba__ratio" style="padding-top: <?php echo esc_attr( $padding_top ); ?>;"></div>

        <div class="ml-ba__after">
            <?php if ( $src_after ) : ?>
            <img src="<?php echo esc_url( $src_after ); ?>" alt="<?php echo esc_attr( $alt_after ); ?>"
                 class="ml-ba__img" loading="lazy" draggable="false">
            <?php endif; ?>
            <span class="ml-ba__label ml-ba__label--after" aria-hidden="true"><?php echo esc_html( $label_after ); ?></span>
        </div>

        <div class="ml-ba__before" style="width: <?php echo (int) $start_pos; ?>%;">
            <?php if ( $src_before ) : ?>
            <img src="<?php echo esc_url( $src_before ); ?>" alt="<?php echo esc_attr( $alt_before ); ?>"
                 class="ml-ba__img"
                 <?php if ( $w_before && $h_before ) : ?>width="<?php echo $w_before; ?>" height="<?php echo $h_before; ?>"<?php endif; ?>
                 loading="eager" draggable="false">
            <?php endif; ?>
            <span class="ml-ba__label ml-ba__label--before" aria-hidden="true"><?php echo esc_html( $label_before ); ?></span>
        </div>

        <div class="ml-ba__handle"
             style="left: <?php echo (int) $start_pos; ?>%;"
             role="slider"
             aria-label="<?php esc_attr_e( 'Vergleichs-Regler', 'media-lab-core' ); ?>"
             aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="<?php echo (int) $start_pos; ?>"
             tabindex="0">
            <div class="ml-ba__handle-line"></div>
            <div class="ml-ba__handle-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 5l-5 7 5 7M16 5l5 7-5 7"/>
                </svg>
            </div>
        </div>

    </div>
</div>
