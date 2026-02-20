<?php
/**
 * ACF Layout: Content Section
 */

$content = get_sub_field('content');
?>

<section class="content-section">
    <div class="container">
        <?php echo wp_kses_post($content); ?>
    </div>
</section>