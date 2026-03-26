<?php
/**
 * Shortcode Overrides – Stadtwirt
 *
 * Überschreibt Shortcodes aus dem Media Lab Agency Core Plugin
 * mit projektspezifischen Versionen.
 *
 * Prinzip: remove_shortcode() + add_shortcode() via init (Priority 20),
 * damit das Plugin seinen Shortcode zuerst registriert (Priority 10)
 * und wir ihn danach gezielt ersetzen können.
 *
 * @package Stadtwirt_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}


// =============================================================================
// testimonials_query – Stadtwirt-Version
//
// Unterschiede zur SK-Standardversion:
//   - 'layout' Parameter: carousel → slider=true, grid → slider=false
//   - orderby: menu_order ASC (statt date DESC)
//   - author_image: get_field('author_image') mit Fallback auf Featured Image
//   - HTML-Struktur: horizontal (Image links, Body rechts)
// =============================================================================

function stadtwirt_testimonials_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number'        => 3,
        'columns'       => 3,
        'style'         => 'card',
        'featured_only' => 'false',
        'slider'        => 'false',
        'layout'        => '',
    ), $atts);

    // layout-Parameter überschreibt slider
    if ($atts['layout'] === 'carousel') {
        $atts['slider'] = 'true';
    } elseif ($atts['layout'] === 'grid') {
        $atts['slider'] = 'false';
    }

    $number        = intval($atts['number']);
    $columns       = esc_attr($atts['columns']);
    $style         = esc_attr($atts['style']);
    $featured_only = $atts['featured_only'] === 'true';
    $slider        = $atts['slider'] === 'true';

    $args = array(
        'post_type'      => 'testimonial',
        'posts_per_page' => $number,
        'order'          => 'ASC',
        'orderby'        => 'menu_order',
        'post_status'    => 'publish',
    );

    if ($featured_only) {
        $args['meta_query'] = array(
            array(
                'key'     => 'featured',
                'value'   => '1',
                'compare' => '=',
            ),
        );
    }

    $testimonials_query = new WP_Query($args);

    if (!$testimonials_query->have_posts()) {
        return '<p>Keine Testimonials gefunden.</p>';
    }

    $wrapper_class = $slider
        ? 'testimonials testimonials--slider testimonials--' . $style . ' swiper'
        : 'testimonials testimonials--' . $style;
    $item_class = $slider ? 'swiper-slide testimonial' : 'testimonial';

    ob_start();
    ?>
    <div class="<?php echo $wrapper_class; ?>" data-columns="<?php echo $columns; ?>" <?php if ($slider) echo 'data-autoplay="true"'; ?>>
        <div class="<?php echo $slider ? 'swiper-wrapper' : ''; ?>">
            <?php while ($testimonials_query->have_posts()) : $testimonials_query->the_post(); ?>
                <?php
                $company            = get_field('company');
                $role               = get_field('role');
                $rating             = get_field('rating');
                $author_image_field = get_field('author_image');
                $thumbnail_img      = '';

                if ($author_image_field) {
                    // ACF Image-Feld (URL-Format oder Array)
                    $img_url       = is_array($author_image_field) ? $author_image_field['url'] : $author_image_field;
                    $thumbnail_img = '<img src="' . esc_url($img_url) . '" alt="' . esc_attr(get_the_title()) . '" class="testimonial__avatar-img">';
                } elseif (has_post_thumbnail()) {
                    // Fallback: Featured Image
                    $thumbnail_img = get_the_post_thumbnail(get_the_ID(), 'thumbnail', ['class' => 'testimonial__avatar-img']);
                }
                ?>

                <div class="<?php echo $item_class; ?>">
                    <?php if ($thumbnail_img) : ?>
                        <div class="testimonial__image">
                            <?php echo $thumbnail_img; ?>
                        </div>
                    <?php endif; ?>

                    <div class="testimonial__body">
                        <div class="testimonial__header">
                            <div class="testimonial__meta">
                                <div class="testimonial__name"><?php the_title(); ?></div>
                                <?php if ($role || $company) : ?>
                                    <div class="testimonial__role">
                                        <?php
                                        $meta_parts = array_filter(array($role, $company));
                                        echo implode(' · ', $meta_parts);
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($rating) : ?>
                                <div class="testimonial__rating">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <span class="star <?php echo $i <= $rating ? 'star--filled' : 'star--empty'; ?>">
                                            <?php echo $i <= $rating ? '★' : '☆'; ?>
                                        </span>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="testimonial__quote">
                            <?php the_content(); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ($slider) : ?>
            <div class="testimonials__navigation">
                <button class="testimonials__button testimonials__button--prev" aria-label="Vorheriges Testimonial">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button class="testimonials__button testimonials__button--next" aria-label="Nächstes Testimonial">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
            <div class="testimonials__pagination"></div>
        <?php endif; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

add_action('init', function() {
    remove_shortcode('testimonials_query');
    add_shortcode('testimonials_query', 'stadtwirt_testimonials_query_shortcode');
}, 20);
