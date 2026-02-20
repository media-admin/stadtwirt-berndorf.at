<?php
/**
 * Card Component
 */

$title = $args['title'] ?? '';
$description = $args['description'] ?? '';
$image = $args['image'] ?? '';
$link = $args['link'] ?? '#';
?>

<article class="card">
    <?php if ($image) : ?>
        <div class="card__image">
            <?php echo wp_get_attachment_image($image, 'customtheme-card'); ?>
        </div>
    <?php endif; ?>
    
    <div class="card__content">
        <h3 class="card__title">
            <?php echo esc_html($title); ?>
        </h3>
        <p class="card__description">
            <?php echo esc_html($description); ?>
        </p>
        <a href="<?php echo esc_url($link); ?>" class="card__link">
            Mehr erfahren →
        </a>
    </div>
</article>