<?php
/**
 * Template Part: Hero Image
 *
 * Verwendung:
 *   get_template_part('template-parts/hero-image');
 *
 * Mit Override (z.B. in page.php für spezifische Seiten):
 *   set_query_var('hero_args', ['post_id' => 42]);
 *   get_template_part('template-parts/hero-image');
 *
 * @package custom-theme
 */

if (!defined('ABSPATH')) exit;
if (!function_exists('media_lab_get_hero_image')) return;

$hero_args = get_query_var('hero_args', []);
$post_id   = $hero_args['post_id'] ?? null;
$hero      = media_lab_get_hero_image($post_id);

if (!$hero) return;

$desktop_url = $hero['desktop']['url'] ?? '';
$mobile_url  = $hero['mobile']['url']  ?? $desktop_url;
$desktop_alt = $hero['desktop']['alt'] ?? '';
$opacity     = $hero['opacity'] / 100;

// CSS-Klassen
$classes = [
    'hero-image',
    'hero-image--' . $hero['height'],
    'hero-image--align-' . $hero['align'],
    'hero-image--vpos-' . $hero['vpos'],
];
?>

<section class="<?php echo esc_attr(implode(' ', $classes)); ?>"
         aria-label="<?php echo esc_attr($hero['title']); ?>">

    <?php /* ── Bild ──────────────────────────────────────────────────────────── */ ?>
    <?php if ($mobile_url && $mobile_url !== $desktop_url) : ?>
    <picture>
        <source media="(min-width: 768px)" srcset="<?php echo esc_url($desktop_url); ?>">
        <img class="hero-image__img"
             src="<?php echo esc_url($mobile_url); ?>"
             alt="<?php echo esc_attr($desktop_alt); ?>"
             loading="eager"
             fetchpriority="high">
    </picture>
    <?php elseif ($desktop_url) : ?>
    <img class="hero-image__img"
         src="<?php echo esc_url($desktop_url); ?>"
         alt="<?php echo esc_attr($desktop_alt); ?>"
         loading="eager"
         fetchpriority="high">
    <?php endif; ?>

    <?php /* ── Overlay ─────────────────────────────────────────────────────── */ ?>
    <div class="hero-image__overlay"
         style="--hero-opacity: <?php echo esc_attr($opacity); ?>"
         aria-hidden="true"></div>

    <?php /* ── Inhalt ──────────────────────────────────────────────────────── */ ?>
    <!-- <div class="hero-image__content container">
        <div class="hero-image__inner">

            <?php if (!empty($hero['title'])) : ?>
            <h1 class="hero-image__title">
                <?php echo esc_html($hero['title']); ?>
            </h1>
            <?php endif; ?>

            <?php if (!empty($hero['subtitle'])) : ?>
            <p class="hero-image__subtitle">
                <?php echo esc_html($hero['subtitle']); ?>
            </p>
            <?php endif; ?>

            <?php
            $has_btn1 = !empty($hero['btn1_text']);
            $has_btn2 = !empty($hero['btn2_text']);

            if ($has_btn1 || $has_btn2) :
            ?>
            <div class="hero-image__buttons">

                <?php if ($has_btn1) :
                    $btn1_href   = $hero['btn1_url']['url']    ?? '#';
                    $btn1_target = $hero['btn1_url']['target'] ?? '';
                    $btn1_class  = 'btn btn--' . esc_attr($hero['btn1_style']) . ' btn--light';
                ?>
                <a class="<?php echo $btn1_class; ?>"
                   href="<?php echo esc_url($btn1_href); ?>"
                   <?php if ($btn1_target) echo 'target="' . esc_attr($btn1_target) . '" rel="noopener noreferrer"'; ?>>
                    <?php echo esc_html($hero['btn1_text']); ?>
                </a>
                <?php endif; ?>

                <?php if ($has_btn2) :
                    $btn2_href   = $hero['btn2_url']['url']    ?? '#';
                    $btn2_target = $hero['btn2_url']['target'] ?? '';
                    $btn2_class  = 'btn btn--' . esc_attr($hero['btn2_style']) . ' btn--light';
                ?>
                <a class="<?php echo $btn2_class; ?>"
                   href="<?php echo esc_url($btn2_href); ?>"
                   <?php if ($btn2_target) echo 'target="' . esc_attr($btn2_target) . '" rel="noopener noreferrer"'; ?>>
                    <?php echo esc_html($hero['btn2_text']); ?>
                </a>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>
    </div> -->

</section>
