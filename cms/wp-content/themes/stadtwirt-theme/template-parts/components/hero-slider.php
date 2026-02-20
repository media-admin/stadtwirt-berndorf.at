<?php
/**
 * Hero Slider Component
 * 
 * @package CustomTheme
 */

$slides = $args['slides'] ?? array();

if (empty($slides)) {
    return;
}
?>

<div class="hero-slider swiper">
    <div class="swiper-wrapper">
        <?php foreach ($slides as $slide) : ?>
            <div class="swiper-slide" style="background-image: url('<?php echo esc_url($slide['image']); ?>');">
                <div class="hero-slider__content container">
                    <?php if (!empty($slide['title'])) : ?>
                        <h1 class="hero-slider__title">
                            <?php echo esc_html($slide['title']); ?>
                        </h1>
                    <?php endif; ?>
                    
                    <?php if (!empty($slide['subtitle'])) : ?>
                        <p class="hero-slider__subtitle">
                            <?php echo esc_html($slide['subtitle']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (!empty($slide['button_text']) && !empty($slide['button_link'])) : ?>
                        <div class="hero-slider__cta">
                            <a href="<?php echo esc_url($slide['button_link']); ?>" class="btn btn-primary">
                                <?php echo esc_html($slide['button_text']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Navigation -->
    <div class="swiper-button-prev"></div>
    <div class="swiper-button-next"></div>
    
    <!-- Pagination -->
    <div class="swiper-pagination"></div>
</div>