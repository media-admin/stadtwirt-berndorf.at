<?php
/**
 * ACF Layout: Text Section
 */

$title = get_sub_field('title');
$content = get_sub_field('content');
$width = get_sub_field('width') ?: 'normal';
?>

<section class="text-section text-section--<?php echo esc_attr($width); ?>">
    <div class="container">
        <?php if ($title) : ?>
            <h2 class="text-section__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        
        <div class="text-section__content">
            <?php echo wp_kses_post($content); ?>
        </div>
    </div>
</section>