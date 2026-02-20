<?php
/**
 * ACF Layout: Call to Action
 */

$title = get_sub_field('title');
$text = get_sub_field('text');
$button_text = get_sub_field('button_text');
$button_url = get_sub_field('button_url');
$background = get_sub_field('background') ?: 'primary';
?>

<section class="cta-section cta-section--<?php echo esc_attr($background); ?>">
    <div class="container">
        <div class="cta-section__content">
            <?php if ($title) : ?>
                <h2 class="cta-section__title"><?php echo esc_html($title); ?></h2>
            <?php endif; ?>
            
            <?php if ($text) : ?>
                <p class="cta-section__text"><?php echo esc_html($text); ?></p>
            <?php endif; ?>
            
            <?php if ($button_text && $button_url) : ?>
                <a href="<?php echo esc_url($button_url); ?>" class="cta-section__button button button--large">
                    <?php echo esc_html($button_text); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>