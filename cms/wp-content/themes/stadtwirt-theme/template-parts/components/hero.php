<?php
/**
 * Hero Component
 */
?>

<section class="hero">
    <div class="hero__background"></div>
    <div class="container">
        <div class="hero__content">
            <h1 class="hero__title">
                <?php echo esc_html(get_field('hero_title') ?: 'Willkommen'); ?>
            </h1>
            <p class="hero__description">
                <?php echo esc_html(get_field('hero_description') ?: 'Beschreibung'); ?>
            </p>
            <a href="<?php echo esc_url(get_field('hero_button_link') ?: '#'); ?>" class="btn btn-primary">
                <?php echo esc_html(get_field('hero_button_text') ?: 'Mehr erfahren'); ?>
            </a>
        </div>
        <div class="hero__image">
            <?php
            $hero_image = get_field('hero_image');
            if ($hero_image) {
                echo wp_get_attachment_image($hero_image, 'customtheme-hero');
            }
            ?>
        </div>
    </div>
</section>