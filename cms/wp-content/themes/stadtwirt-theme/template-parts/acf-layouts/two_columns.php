<?php
/**
 * ACF Layout: Two Columns
 */

$left = get_sub_field('left_column');
$right = get_sub_field('right_column');
$ratio = get_sub_field('ratio') ?: '50-50';
?>

<section class="two-columns two-columns--<?php echo esc_attr($ratio); ?>">
    <div class="container">
        <div class="two-columns__wrapper">
            <div class="two-columns__left">
                <?php echo wp_kses_post($left); ?>
            </div>
            <div class="two-columns__right">
                <?php echo wp_kses_post($right); ?>
            </div>
        </div>
    </div>
</section>