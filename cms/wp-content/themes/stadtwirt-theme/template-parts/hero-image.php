<?php
/**
 * Template Part: Hero Image
 * 
 * Usage: get_template_part('template-parts/hero-image');
 */

$hero = media_lab_get_hero_image();

if (!$hero) return;

$opacity     = $hero['opacity'] / 100;
$desktop_url = $hero['desktop']['url'] ?? '';
$mobile_url  = $hero['mobile']['url'] ?? $desktop_url;
$desktop_alt = $hero['desktop']['alt'] ?? '';
?>

<section class="hero-image" aria-label="<?php echo esc_attr($hero['title']); ?>">

    <?php if ($mobile_url !== $desktop_url) : ?>
    <picture>
        <source media="(min-width: 768px)" srcset="<?php echo esc_url($desktop_url); ?>">
        <img class="hero-image__img"
             src="<?php echo esc_url($mobile_url); ?>"
             alt="<?php echo esc_attr($desktop_alt); ?>"
             loading="eager"
             fetchpriority="high">
    </picture>
    <?php else : ?>
    <img class="hero-image__img"
         src="<?php echo esc_url($desktop_url); ?>"
         alt="<?php echo esc_attr($desktop_alt); ?>"
         loading="eager"
         fetchpriority="high">
    <?php endif; ?>

    <div class="hero-image__overlay" style="--hero-opacity: <?php echo esc_attr($opacity); ?>"></div>

    <div class="hero-image__content container">
        <h1 class="hero-image__title"><?php echo esc_html($hero['title']); ?></h1>
    </div>

</section>
