<?php
/**
 * ACF Layout: Hero Section
 */

$title = get_sub_field('title');
$subtitle = get_sub_field('subtitle');
$content = get_sub_field('content');
$button_text = get_sub_field('button_text');
$button_url = get_sub_field('button_url');
$background_image = get_sub_field('background_image');
?>

<section class="hero-section" style="background-image: url('<?php echo esc_url($background_image['url']); ?>');">
    <div class="container">
        <div class="hero-section__content">
            <?php if ($subtitle) : ?>
                <p class="hero-section__subtitle"><?php echo esc_html($subtitle); ?></p>
            <?php endif; ?>
            
            <?php if ($title) : ?>
                <h1 class="hero-section__title"><?php echo esc_html($title); ?></h1>
            <?php endif; ?>
            
            <?php if ($content) : ?>
                <div class="hero-section__text"><?php echo wpautop($content); ?></div>
            <?php endif; ?>
            
            <?php if ($button_text && $button_url) : ?>
                <a href="<?php echo esc_url($button_url); ?>" class="hero-section__button button button--primary">
                    <?php echo esc_html($button_text); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>