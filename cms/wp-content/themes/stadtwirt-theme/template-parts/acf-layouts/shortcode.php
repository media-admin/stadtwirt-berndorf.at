<?php
/**
 * ACF Layout: Shortcode
 */

$shortcode = get_sub_field('shortcode');
?>

<section class="shortcode-section">
    <div class="container">
        <?php echo do_shortcode($shortcode); ?>
    </div>
</section>