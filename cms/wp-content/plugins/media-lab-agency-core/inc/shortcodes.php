<?php
/**
 * Shortcodes
 * 
 * All shortcode functionality for Agency Core.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter: Allow themes to modify shortcode output
 * 
 * Themes can hook into this to add their own wrapper classes or markup.
 * 
 * @param string $output Shortcode HTML output
 * @param string $shortcode Shortcode name
 * @param array $atts Shortcode attributes
 * @return string Modified output
 */
function agency_core_shortcode_output_filter($output, $shortcode, $atts) {
    return apply_filters('agency_core_shortcode_output', $output, $shortcode, $atts);
}

/**
 * Helper: Get shortcode wrapper class
 * 
 * Allows themes to add custom classes to shortcode wrappers.
 * 
 * @param string $base_class Base CSS class
 * @param string $shortcode Shortcode name
 * @return string Class names
 */
function agency_core_get_shortcode_class($base_class, $shortcode) {
    $classes = array($base_class);
    
    // Allow themes to add classes
    $additional_classes = apply_filters('agency_core_shortcode_wrapper_class', '', $shortcode);
    
    if (!empty($additional_classes)) {
        $classes[] = $additional_classes;
    }
    
    return implode(' ', $classes);
}




// ============================================
// ACCORDION SHORTCODES
// ============================================

function accordion_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'allow_multiple' => 'false',
    ), $atts);
    
    $allow_multiple = $atts['allow_multiple'] === 'true' ? 'data-allow-multiple="true"' : '';
    
    return '<div class="accordion" ' . $allow_multiple . '>' . do_shortcode($content) . '</div>';
}
add_shortcode('accordion', 'accordion_shortcode');

function accordion_item_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'title' => 'Accordion Item',
        'open' => 'false',
    ), $atts);
    
    $is_open = $atts['open'] === 'true';
    $active_class = $is_open ? 'is-active' : '';
    $expanded = $is_open ? 'true' : 'false';
    $icon = $is_open ? '−' : '+';
    $display = $is_open ? 'style="display:block;"' : '';
    
    return '
    <div class="accordion__item ' . $active_class . '">
        <button class="accordion__trigger" aria-expanded="' . $expanded . '">
            <span class="accordion__title">' . esc_html($atts['title']) . '</span>
            <span class="accordion__icon">' . $icon . '</span>
        </button>
        <div class="accordion__content" ' . $display . '>
            <div class="accordion__content-inner">' . wpautop(do_shortcode($content)) . '</div>
        </div>
    </div>';
}
add_shortcode('accordion_item', 'accordion_item_shortcode');

// ============================================
// HERO SLIDER SHORTCODES
// ============================================

function hero_slider_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'autoplay' => 'false',
        'delay' => '5000',
        'loop' => 'true',
    ), $atts);
    
    $autoplay = esc_attr($atts['autoplay']);
    $delay = esc_attr($atts['delay']);
    $loop = esc_attr($atts['loop']);
    
    $output = '<div class="hero-slider swiper" data-autoplay="' . $autoplay . '" data-delay="' . $delay . '" data-loop="' . $loop . '" style="position:relative;width:100%;max-width:100%;height:600px;overflow:hidden;">';
    $output .= '<div class="swiper-wrapper">';
    $output .= do_shortcode($content);
    $output .= '</div>';
    
    // Navigation mit inline styles
    $output .= '<div class="swiper-button-prev" style="position:absolute;top:50%;left:1rem;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.3);border-radius:50%;z-index:10;cursor:pointer;"></div>';
    $output .= '<div class="swiper-button-next" style="position:absolute;top:50%;right:1rem;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.3);border-radius:50%;z-index:10;cursor:pointer;"></div>';
    
    // Pagination mit inline styles
    $output .= '<div class="swiper-pagination" style="position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);z-index:10;"></div>';
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('hero_slider', 'hero_slider_shortcode');

function hero_slide_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'image' => '',
        'image_mobile' => '',
        'title' => '',
        'subtitle' => '',
        'button_text' => '',
        'button_link' => '',
        'button_target' => '_self',
        'text_align' => 'center',
        'text_color' => 'white',
        'overlay' => 'true',
        'overlay_opacity' => '0.4',
    ), $atts);
    
    $image = esc_url($atts['image']);
    $image_mobile = !empty($atts['image_mobile']) ? esc_url($atts['image_mobile']) : $image;
    $title = esc_html($atts['title']);
    $subtitle = esc_html($atts['subtitle']);
    $button_text = esc_html($atts['button_text']);
    $button_link = esc_url($atts['button_link']);
    $button_target = esc_attr($atts['button_target']);
    $text_color = esc_attr($atts['text_color']);
    $show_overlay = $atts['overlay'] === 'true';
    $overlay_opacity = floatval($atts['overlay_opacity']);
    
    $output = '<div class="hero-slide swiper-slide" style="position:relative;width:100%;height:100%;">';
    
    // Background mit inline styles
    $output .= '<div class="hero-slide__background" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;">';
    if ($image) {
        $output .= '<picture>';
        $output .= '<source media="(min-width: 768px)" srcset="' . $image . '">';
        $output .= '<source media="(max-width: 767px)" srcset="' . $image_mobile . '">';
        $output .= '<img src="' . $image . '" alt="' . $title . '" class="hero-slide__image" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">';
        $output .= '</picture>';
    }
    if ($show_overlay) {
        $output .= '<div class="hero-slide__overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,' . $overlay_opacity . ');z-index:1;"></div>';
    }
    $output .= '</div>';
    
    // Content mit inline styles
    $output .= '<div class="hero-slide__content" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:800px;z-index:2;padding:2rem;text-align:center!important;">';
    $output .= '<div class="hero-slide__inner" style="width:100%;text-align:center!important;color:white!important;">';

    if ($subtitle) {
        $output .= '<div class="hero-slide__subtitle" style="color:white!important;font-size:1rem;margin:0 auto 0.75rem auto;text-transform:uppercase;letter-spacing:0.1em;font-weight:600;text-align:center!important;width:100%;">' . $subtitle . '</div>';
    }

    if ($title) {
        $output .= '<h2 class="hero-slide__title" style="color:white!important;font-size:2.5rem;font-weight:700;margin:0 auto 1rem auto;line-height:1.2;text-shadow:0 2px 4px rgba(0,0,0,0.5);text-align:center!important;width:100%;">' . $title . '</h2>';
    }

    if ($content) {
        $output .= '<div class="hero-slide__text" style="color:white!important;font-size:1rem;margin:0 auto 1.5rem auto;line-height:1.6;text-align:center!important;width:100%;">' . wpautop(do_shortcode($content)) . '</div>';
    }

    if ($button_text && $button_link) {
        $output .= '<div style="text-align:center!important;width:100%;"><a href="' . $button_link . '" target="' . $button_target . '" class="hero-slide__button" style="display:inline-block;padding:0.75rem 1.5rem;background:#667eea;color:white!important;text-decoration:none;border-radius:0.5rem;font-weight:600;">' . $button_text . '</a></div>';
    }

    $output .= '</div></div>'; // inner + content

    $output .= '</div>'; // slide
    
    return $output;
}
add_shortcode('hero_slide', 'hero_slide_shortcode');

// ============================================
// MODAL SHORTCODES
// ============================================

function modal_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'id' => 'modal-' . uniqid(),
        'title' => '',
        'size' => 'normal',
        'show_header' => 'true',
        'show_footer' => 'false',
    ), $atts);
    
    $modal_id = esc_attr($atts['id']);
    $size_class = $atts['size'] !== 'normal' ? ' modal--' . esc_attr($atts['size']) : '';
    $title = esc_html($atts['title']);
    $show_header = $atts['show_header'] === 'true';
    $show_footer = $atts['show_footer'] === 'true';
    
    $body_content = wpautop(do_shortcode($content));
    
    $html = '<div class="modal' . $size_class . '" id="' . $modal_id . '">';
    $html .= '<div class="modal__dialog">';
    
    if ($show_header) {
        $html .= '<div class="modal__header">';
        if ($title) {
            $html .= '<h3 class="modal__title">' . $title . '</h3>';
        }
        $html .= '<button class="modal__close" data-modal-close aria-label="Schließen">&times;</button>';
        $html .= '</div>';
    } else {
        $html .= '<button class="modal__close" data-modal-close aria-label="Schließen" style="position: absolute; top: 10px; right: 10px; z-index: 10;">&times;</button>';
    }
    
    $html .= '<div class="modal__body">' . $body_content . '</div>';
    
    if ($show_footer) {
        $html .= '<div class="modal__footer">';
        $html .= '<button class="button button--secondary" data-modal-close>Schließen</button>';
        $html .= '</div>';
    }
    
    $html .= '</div></div>';
    
    return $html;
}
add_shortcode('modal', 'modal_shortcode');

function modal_trigger_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'target' => '',
        'style' => 'button',
        'color' => 'primary',
    ), $atts);
    
    $target = esc_attr($atts['target']);
    $button_text = $content ? esc_html($content) : 'Modal öffnen';
    
    if ($atts['style'] === 'button') {
        $class = 'button button--' . esc_attr($atts['color']);
        return '<button class="' . $class . '" data-modal-trigger="' . $target . '">' . $button_text . '</button>';
    } 
    elseif ($atts['style'] === 'link') {
        return '<a href="#" data-modal-trigger="' . $target . '" style="color: #667eea; text-decoration: underline;">' . $button_text . '</a>';
    }
    else {
        return '<span data-modal-trigger="' . $target . '" style="cursor: pointer; color: #667eea; text-decoration: underline;">' . $button_text . '</span>';
    }
}
add_shortcode('modal_trigger', 'modal_trigger_shortcode');


// ============================================
// CAROUSEL SHORTCODE
// ============================================

/**
 * Carousel Shortcode
 * 
 * Usage: [carousel category="featured" limit="6" autoplay="true" slides_per_view="3"]
 */
function carousel_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'limit' => '-1',
        'autoplay' => 'true',
        'delay' => '3000',
        'loop' => 'true',
        'slides_per_view' => '3',
        'space_between' => '30',
        'breakpoint_mobile' => '1',
        'breakpoint_tablet' => '2',
    ), $atts);
    
    $args = array(
        'post_type' => 'carousel',
        'posts_per_page' => intval($atts['limit']),
        'orderby' => 'meta_value_num',
        'meta_key' => 'display_order',
        'order' => 'ASC',
        'post_status' => 'publish',
    );
    
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'carousel_category',
                'field' => 'slug',
                'terms' => $atts['category'],
            ),
        );
    }
    
    $carousel = new WP_Query($args);
    
    if (!$carousel->have_posts()) {
        return '<p>Keine Carousel Items gefunden.</p>';
    }
    
    $carousel_id = 'carousel-' . uniqid();
    
    $output = '<div class="carousel-container" data-carousel-id="' . $carousel_id . '">';
    $output .= '<div class="carousel swiper" ';
    $output .= 'data-autoplay="' . esc_attr($atts['autoplay']) . '" ';
    $output .= 'data-delay="' . esc_attr($atts['delay']) . '" ';
    $output .= 'data-loop="' . esc_attr($atts['loop']) . '" ';
    $output .= 'data-slides="' . esc_attr($atts['slides_per_view']) . '" ';
    $output .= 'data-space="' . esc_attr($atts['space_between']) . '" ';
    $output .= 'data-mobile="' . esc_attr($atts['breakpoint_mobile']) . '" ';
    $output .= 'data-tablet="' . esc_attr($atts['breakpoint_tablet']) . '">';
    $output .= '<div class="swiper-wrapper">';
    
    while ($carousel->have_posts()) {
        $carousel->the_post();
        
        $title = get_the_title();
        $subtitle = get_field('subtitle');
        $content = get_the_content();
        $link = get_field('link_url');
        $link_target = get_field('link_target') ?: '_self';
        $image = get_field('image');
        $show_overlay = get_field('show_overlay');
        
        $output .= '<div class="swiper-slide carousel-item">';
        
        if ($link) {
            $output .= '<a href="' . esc_url($link) . '" target="' . esc_attr($link_target) . '" class="carousel-item__link">';
        }
        
        if ($image) {
            $output .= '<div class="carousel-item__image">';
            $output .= '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt'] ?: $title) . '" loading="lazy">';
            $output .= '</div>';
        }
        
        if ($show_overlay && ($title || $subtitle || $content)) {
            $output .= '<div class="carousel-item__overlay">';
            $output .= '<div class="carousel-item__content">';
            
            if ($title) {
                $output .= '<h3 class="carousel-item__title">' . esc_html($title) . '</h3>';
            }
            
            if ($subtitle) {
                $output .= '<p class="carousel-item__subtitle">' . esc_html($subtitle) . '</p>';
            }
            
            if ($content) {
                $output .= '<div class="carousel-item__text">' . wp_kses_post($content) . '</div>';
            }
            
            $output .= '</div>';
            $output .= '</div>';
        }
        
        if ($link) {
            $output .= '</a>';
        }
        
        $output .= '</div>';
    }
    
    $output .= '</div>'; // swiper-wrapper
    
    // Navigation
    $output .= '<div class="swiper-button-prev"></div>';
    $output .= '<div class="swiper-button-next"></div>';
    
    // Pagination
    $output .= '<div class="swiper-pagination"></div>';
    
    $output .= '</div>'; // swiper
    $output .= '</div>'; // carousel-container
    
    wp_reset_postdata();
    
    return $output;
}
add_shortcode('carousel', 'carousel_shortcode');


/**
 * Hero Slider Query (loads from hero_slide CPT)
 * 
 * Usage: [hero_slider_query limit="3" autoplay="true" delay="5000" loop="true"]
 */
function hero_slider_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => '3',
        'autoplay' => 'true',
        'delay' => '5000',
        'loop' => 'true',
        'order' => 'ASC',
        'orderby' => 'menu_order',
    ), $atts);
    
    $args = array(
        'post_type' => 'hero_slide',
        'posts_per_page' => intval($atts['limit']),
        'order' => $atts['order'],
        'orderby' => $atts['orderby'],
        'post_status' => 'publish',
    );
    
    $slides = new WP_Query($args);
    
    if (!$slides->have_posts()) {
        return '<p>Keine Hero Slides gefunden.</p>';
    }
    
    $output = '<div class="hero-slider swiper" ';
    $output .= 'data-autoplay="' . esc_attr($atts['autoplay']) . '" ';
    $output .= 'data-delay="' . esc_attr($atts['delay']) . '" ';
    $output .= 'data-loop="' . esc_attr($atts['loop']) . '" ';
    $output .= 'style="position:relative;width:100%;max-width:100%;height:600px;overflow:hidden;">';
    $output .= '<div class="swiper-wrapper">';
    
    while ($slides->have_posts()) {
        $slides->the_post();
        
        $title = get_the_title();
        $subtitle = get_field('subtitle');
        $button_text = get_field('button_text');
        $button_url = get_field('button_url');
        $button_style = get_field('button_style') ?: 'primary';
        
        // Get Images (ACF return_format = 'url', gibt direkt String zurück!)
        $image_desktop = get_field('image_desktop');
        $image_mobile = get_field('image_mobile');
        
        // Fallback: Featured Image
        if (empty($image_desktop)) {
            $image_desktop = get_the_post_thumbnail_url(get_the_ID(), 'full');
        }
        
        // Mobile fallback to desktop
        if (empty($image_mobile)) {
            $image_mobile = $image_desktop;
        }
        
        // Last fallback: Placeholder
        if (empty($image_desktop)) {
            $image_desktop = 'https://via.placeholder.com/1920x1080?text=Hero+Slide';
            $image_mobile = $image_desktop;
        }
        
        $overlay_opacity = get_field('overlay_opacity') ?: 40;
        $text_color = get_field('text_color') ?: 'light';
        
        $output .= '<div class="hero-slide swiper-slide" style="position:relative;width:100%;height:100%;">';
        
        // Background
        $output .= '<div class="hero-slide__background" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:0;">';
        $output .= '<picture>';
        $output .= '<source media="(min-width: 768px)" srcset="' . esc_url($image_desktop) . '">';
        $output .= '<source media="(max-width: 767px)" srcset="' . esc_url($image_mobile) . '">';
        $output .= '<img src="' . esc_url($image_desktop) . '" alt="' . esc_attr($title) . '" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">';
        $output .= '</picture>';
        $output .= '<div class="hero-slide__overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,' . ($overlay_opacity / 100) . ');z-index:1;"></div>';
        $output .= '</div>';
        
        // Content
        $text_color_class = $text_color === 'dark' ? 'color:#333!important;' : 'color:white!important;';
        
        $output .= '<div class="hero-slide__content" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:800px;z-index:2;padding:2rem;text-align:center;">';
        $output .= '<div class="hero-slide__inner" style="' . $text_color_class . '">';
        
        if ($subtitle) {
            $output .= '<div class="hero-slide__subtitle" style="' . $text_color_class . 'font-size:1rem;margin-bottom:0.75rem;text-transform:uppercase;letter-spacing:0.1em;font-weight:600;">' . esc_html($subtitle) . '</div>';
        }
        
        if ($title) {
            $output .= '<h2 class="hero-slide__title" style="' . $text_color_class . 'font-size:2.5rem;font-weight:700;margin-bottom:1rem;line-height:1.2;text-shadow:0 2px 4px rgba(0,0,0,0.5);">' . esc_html($title) . '</h2>';
        }
        
        if (has_excerpt()) {
            $output .= '<div class="hero-slide__text" style="' . $text_color_class . 'font-size:1rem;margin-bottom:1.5rem;line-height:1.6;">' . wpautop(get_the_excerpt()) . '</div>';
        }
        
        if ($button_text && $button_url) {
            $button_bg = $button_style === 'primary' ? '#667eea' : ($button_style === 'secondary' ? '#f59e0b' : 'transparent');
            $button_border = $button_style === 'outline' ? 'border:2px solid white;' : '';
            $output .= '<a href="' . esc_url($button_url) . '" class="hero-slide__button button button--' . esc_attr($button_style) . '" style="display:inline-block;padding:0.75rem 1.5rem;background:' . $button_bg . ';color:white;text-decoration:none;border-radius:0.5rem;font-weight:600;' . $button_border . '">' . esc_html($button_text) . '</a>';
        }
        
        $output .= '</div></div>'; // inner + content
        $output .= '</div>'; // slide
    }
    
    $output .= '</div>'; // swiper-wrapper
    
    // Navigation
    $output .= '<div class="swiper-button-prev" style="position:absolute;top:50%;left:1rem;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.3);border-radius:50%;z-index:10;cursor:pointer;"></div>';
    $output .= '<div class="swiper-button-next" style="position:absolute;top:50%;right:1rem;transform:translateY(-50%);width:50px;height:50px;background:rgba(255,255,255,0.3);border-radius:50%;z-index:10;cursor:pointer;"></div>';
    
    // Pagination
    $output .= '<div class="swiper-pagination" style="position:absolute;bottom:1rem;left:50%;transform:translateX(-50%);z-index:10;"></div>';
    
    $output .= '</div>'; // hero-slider
    
    wp_reset_postdata();
    
    return $output;
}
add_shortcode('hero_slider_query', 'hero_slider_query_shortcode');


// ============================================
// TESTIMONIALS SHORTCODES
// ============================================

function testimonials_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'columns' => '3', // 1, 2, 3, 4
        'style' => 'card', // card, quote, minimal
        'autoplay' => 'false',
        'slider' => 'false', // Slider-Modus
    ), $atts);
    
    $columns = esc_attr($atts['columns']);
    $style = esc_attr($atts['style']);
    $is_slider = $atts['slider'] === 'true';
    
    $container_class = 'testimonials testimonials--' . $style;
    
    if ($is_slider) {
        $slider_id = 'testimonials-' . uniqid();
        $autoplay_attr = $atts['autoplay'] === 'true' ? 'data-autoplay="true"' : '';
        
        return '
        <div class="' . $container_class . ' testimonials--slider swiper" id="' . $slider_id . '" ' . $autoplay_attr . '>
            <div class="swiper-wrapper">' . do_shortcode($content) . '</div>
            <div class="testimonials__navigation">
                <button class="testimonials__button testimonials__button--prev" aria-label="Previous">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button class="testimonials__button testimonials__button--next" aria-label="Next">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
            <div class="testimonials__pagination"></div>
        </div>';
    } else {
        return '
        <div class="' . $container_class . '" data-columns="' . $columns . '">' . do_shortcode($content) . '</div>';
    }
}
add_shortcode('testimonials', 'testimonials_shortcode');

function testimonial_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'name' => '',
        'role' => '',
        'company' => '',
        'image' => '',
        'rating' => '', // 1-5
    ), $atts);
    
    $name = esc_html($atts['name']);
    $role = esc_html($atts['role']);
    $company = esc_html($atts['company']);
    $image = esc_url($atts['image']);
    $rating = intval($atts['rating']);
    $quote = wpautop(do_shortcode($content));
    
    // Rating Stars
    $stars_html = '';
    if ($rating > 0 && $rating <= 5) {
        $stars_html = '<div class="testimonial__rating">';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars_html .= '<span class="star star--filled">★</span>';
            } else {
                $stars_html .= '<span class="star star--empty">☆</span>';
            }
        }
        $stars_html .= '</div>';
    }
    
    // Image HTML
    $image_html = '';
    if ($image) {
        $image_html = '<div class="testimonial__image"><img src="' . $image . '" alt="' . $name . '"></div>';
    }
    
    // Meta (Name, Role, Company)
    $meta_html = '<div class="testimonial__meta">';
    if ($name) {
        $meta_html .= '<div class="testimonial__name">' . $name . '</div>';
    }
    if ($role || $company) {
        $meta_parts = array_filter(array($role, $company));
        $meta_html .= '<div class="testimonial__role">' . implode(' · ', $meta_parts) . '</div>';
    }
    $meta_html .= '</div>';
    
    // Check if inside slider
    $parent_is_slider = false; // This would be set by parent context
    $wrapper_class = $parent_is_slider ? 'swiper-slide' : 'testimonial';
    
    return '
    <div class="' . $wrapper_class . ' testimonial">
        ' . $stars_html . '
        <div class="testimonial__quote">' . $quote . '</div>
        <div class="testimonial__footer">
            ' . $image_html . '
            ' . $meta_html . '
        </div>
    </div>';
}
add_shortcode('testimonial', 'testimonial_shortcode');

// ============================================
// TABS SHORTCODES (IMPROVED)
// ============================================

function tabs_shortcode($atts, $content = null) {
    static $tabs_counter = 0;
    $tabs_counter++;
    
    $atts = shortcode_atts(array(
        'style' => 'default', // default, pills, underline
    ), $atts);
    
    $style = esc_attr($atts['style']);
    $unique_id = 'tabs-' . $tabs_counter;
    
    // Parse content to extract tabs
    global $tab_items;
    $tab_items = array();
    
    // Process nested shortcodes
    do_shortcode($content);
    
    if (empty($tab_items)) {
        return '';
    }
    
    // Build navigation
    $navigation = '<div class="tabs__navigation" role="tablist">';
    foreach ($tab_items as $index => $tab) {
        $is_active = $tab['active'] ? ' is-active' : '';
        $tab_id = $unique_id . '-tab-' . $index;
        $icon_html = $tab['icon'] ? '<span class="dashicons ' . esc_attr($tab['icon']) . '"></span> ' : '';
        
        $navigation .= '<button class="tabs__button' . $is_active . '" data-tab="' . $tab_id . '" role="tab" aria-selected="' . ($tab['active'] ? 'true' : 'false') . '">';
        $navigation .= $icon_html . esc_html($tab['title']);
        $navigation .= '</button>';
    }
    $navigation .= '</div>';
    
    // Build content
    $panels = '<div class="tabs__content">';
    foreach ($tab_items as $index => $tab) {
        $is_active = $tab['active'] ? ' is-active' : '';
        $tab_id = $unique_id . '-tab-' . $index;
        
        $panels .= '<div class="tabs__panel' . $is_active . '" id="' . $tab_id . '" role="tabpanel" aria-hidden="' . ($tab['active'] ? 'false' : 'true') . '">';
        $panels .= wpautop(do_shortcode($tab['content']));
        $panels .= '</div>';
    }
    $panels .= '</div>';
    
    // Reset for next tabs group
    $tab_items = array();
    
    // Return complete tabs
    return '<div class="tabs tabs--' . $style . '" data-tabs-id="' . $unique_id . '">' . $navigation . $panels . '</div>';
}
add_shortcode('tabs', 'tabs_shortcode');

function tab_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'title' => 'Tab',
        'icon' => '',
        'active' => 'false',
    ), $atts);
    
    global $tab_items;
    
    if (!isset($tab_items)) {
        $tab_items = array();
    }
    
    // Check if this is the first tab and no active tab is set
    $is_first = empty($tab_items);
    $has_active = false;
    foreach ($tab_items as $item) {
        if ($item['active']) {
            $has_active = true;
            break;
        }
    }
    
    // If no active tab and this is first, or explicitly set active
    $is_active = ($atts['active'] === 'true') || ($is_first && !$has_active);
    
    $tab_items[] = array(
        'title' => $atts['title'],
        'icon' => $atts['icon'],
        'content' => $content,
        'active' => $is_active,
    );
    
    // Return empty string (content is collected in global array)
    return '';
}
add_shortcode('tab', 'tab_shortcode');

// ============================================
// NOTIFICATION SHORTCODES
// ============================================

// ============================================
// STATS/COUNTER SHORTCODES
// ============================================

/**
 * Stats Container (umschließt mehrere stat Items)
 */
function stats_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'columns' => '4',
        'style' => 'default',
    ), $atts);
    
    $columns = intval($atts['columns']);
    $style = esc_attr($atts['style']);
    
    // INLINE STYLES
    $inline_style = 'display:grid!important;gap:2rem!important;margin:2rem 0!important;width:100%!important;';
    
    if ($columns == 2) {
        $inline_style .= 'grid-template-columns:repeat(2,1fr)!important;';
    } elseif ($columns == 3) {
        $inline_style .= 'grid-template-columns:repeat(3,1fr)!important;';
    } elseif ($columns == 4) {
        $inline_style .= 'grid-template-columns:repeat(4,1fr)!important;';
    } else {
        $inline_style .= 'grid-template-columns:repeat(' . $columns . ',1fr)!important;';
    }
    
    return '<div class="stats stats--' . $style . '" data-columns="' . $columns . '" style="' . $inline_style . '">' . do_shortcode($content) . '</div>';
}
add_shortcode('stats', 'stats_shortcode'); // ← Container Shortcode

/**
 * Single Stat Item
 */
function stat_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'number' => '0',
        'prefix' => '',
        'suffix' => '',
        'duration' => '2000',
        'label' => '',
        'icon' => '',
        'color' => '',
    ), $atts);
    
    $number = esc_attr($atts['number']);
    $prefix = esc_html($atts['prefix']);
    $suffix = esc_html($atts['suffix']);
    $duration = esc_attr($atts['duration']);
    $label = esc_html($atts['label']);
    $icon = esc_attr($atts['icon']);
    $color = $atts['color'] ? ' stat--' . esc_attr($atts['color']) : '';
    
    // Icon HTML
    $icon_html = '';
    if ($icon) {
        $icon_html = '<div class="stat__icon"><span class="dashicons ' . $icon . '"></span></div>';
    }
    
    // Description from content
    $description = '';
    if ($content) {
        $description = '<p class="stat__description">' . wp_kses_post($content) . '</p>';
    }
    
    return '
    <div class="stat' . $color . '" data-counter>
        ' . $icon_html . '
        <div class="stat__content">
            <div class="stat__number">
                <span class="stat__prefix">' . $prefix . '</span>
                <span class="stat__value" data-target="' . $number . '" data-duration="' . $duration . '">0</span>
                <span class="stat__suffix">' . $suffix . '</span>
            </div>
            ' . ($label ? '<div class="stat__label">' . $label . '</div>' : '') . '
            ' . $description . '
        </div>
    </div>';
}
add_shortcode('stat', 'stat_shortcode'); // ← Einzelnes Stat Item


// ============================================
// TIMELINE SHORTCODES
// ============================================

function timeline_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'style' => 'default', // default, alternate, centered
    ), $atts);
    
    $style = esc_attr($atts['style']);
    
    return '<div class="timeline timeline--' . $style . '">' . do_shortcode($content) . '</div>';
}
add_shortcode('timeline', 'timeline_shortcode');

function timeline_item_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'date' => '',
        'title' => '',
        'icon' => '', // dashicon class
        'color' => '', // primary, success, error, warning, info
        'image' => '', // optional image URL
    ), $atts);
    
    $date = esc_html($atts['date']);
    $title = esc_html($atts['title']);
    $icon = esc_attr($atts['icon']);
    $color = $atts['color'] ? ' timeline-item--' . esc_attr($atts['color']) : '';
    $image = esc_url($atts['image']);
    
    // Icon HTML
    $icon_html = '';
    if ($icon) {
        $icon_html = '<span class="dashicons ' . $icon . '"></span>';
    } elseif (!$image) {
        // Default icon if none provided
        $icon_html = '<span class="dashicons dashicons-marker"></span>';
    }
    
    // Image HTML
    $image_html = '';
    if ($image) {
        $image_html = '<div class="timeline-item__image"><img src="' . $image . '" alt="' . $title . '"></div>';
    }
    
    return '
    <div class="timeline-item' . $color . '" data-animate="fade-in-up">
        <div class="timeline-item__marker">' . $icon_html . '</div>
        <div class="timeline-item__content">
            ' . ($date ? '<div class="timeline-item__date">' . $date . '</div>' : '') . '
            ' . ($title ? '<h3 class="timeline-item__title">' . $title . '</h3>' : '') . '
            ' . $image_html . '
            <div class="timeline-item__description">' . wpautop(do_shortcode($content)) . '</div>
        </div>
    </div>';
}
add_shortcode('timeline_item', 'timeline_item_shortcode');

// ============================================
// IMAGE COMPARISON SHORTCODE (CLEAN VERSION)
// ============================================

function image_comparison_shortcode($atts) {
    static $comparison_id = 0;
    $comparison_id++;
    
    $atts = shortcode_atts(array(
        'before' => '',
        'after' => '',
        'before_label' => 'Vorher',
        'after_label' => 'Nachher',
        'position' => '50',
        'orientation' => 'horizontal',
    ), $atts);
    
    $before = esc_url($atts['before']);
    $after = esc_url($atts['after']);
    
    if (empty($before) || empty($after)) {
        return '<p><strong>Fehler:</strong> Bitte geben Sie sowohl ein "before" als auch ein "after" Bild an.</p>';
    }
    
    $before_label = esc_html($atts['before_label']);
    $after_label = esc_html($atts['after_label']);
    $position = intval($atts['position']);
    $orientation = esc_attr($atts['orientation']);
    $unique_id = 'comparison-' . $comparison_id;
    
    ob_start();
    ?>
    <div class="image-comparison image-comparison--<?php echo $orientation; ?>" id="<?php echo $unique_id; ?>" data-position="<?php echo $position; ?>">
        <div class="image-comparison__wrapper">
            <div class="image-comparison__before">
                <img src="<?php echo $before; ?>" alt="<?php echo $before_label; ?>">
                <span class="image-comparison__label image-comparison__label--before"><?php echo $before_label; ?></span>
            </div>
            <div class="image-comparison__after">
                <img src="<?php echo $after; ?>" alt="<?php echo $after_label; ?>">
                <span class="image-comparison__label image-comparison__label--after"><?php echo $after_label; ?></span>
            </div>
            <div class="image-comparison__slider">
                <div class="image-comparison__handle">
                    <span class="image-comparison__arrow image-comparison__arrow--left">◀</span>
                    <span class="image-comparison__divider"></span>
                    <span class="image-comparison__arrow image-comparison__arrow--right">▶</span>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('image_comparison', 'image_comparison_shortcode');

// ============================================
// LOGO CAROUSEL SHORTCODE
// ============================================

function logo_carousel_shortcode($atts, $content = null) {
    static $carousel_id = 0;
    $carousel_id++;
    
    $atts = shortcode_atts(array(
        'autoplay' => 'true',
        'speed' => '3000',
        'loop' => 'true',
        'slides_per_view' => 'auto', // auto, 3, 4, 5, 6
        'grayscale' => 'true', // Logos grau, farbig bei hover
        'style' => 'default', // default, card
    ), $atts);
    
    $autoplay = esc_attr($atts['autoplay']);
    $speed = esc_attr($atts['speed']);
    $loop = esc_attr($atts['loop']);
    $slides_per_view = esc_attr($atts['slides_per_view']);
    $grayscale = esc_attr($atts['grayscale']);
    $style = esc_attr($atts['style']);
    $unique_id = 'logo-carousel-' . $carousel_id;
    
    ob_start();
    ?>
    <div class="logo-carousel logo-carousel--<?php echo $style; ?> swiper" 
         id="<?php echo $unique_id; ?>" 
         data-autoplay="<?php echo $autoplay; ?>" 
         data-speed="<?php echo $speed; ?>"
         data-loop="<?php echo $loop; ?>"
         data-slides="<?php echo $slides_per_view; ?>"
         data-grayscale="<?php echo $grayscale; ?>">
        <div class="swiper-wrapper">
            <?php echo do_shortcode($content); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('logo_carousel', 'logo_carousel_shortcode');

function logo_item_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'image' => '',
        'alt' => '',
        'link' => '',
        'target' => '_blank',
    ), $atts);
    
    $image = esc_url($atts['image']);
    $alt = esc_attr($atts['alt']);
    $link = esc_url($atts['link']);
    $target = esc_attr($atts['target']);
    
    if (empty($image)) {
        return '';
    }
    
    ob_start();
    ?>
    <div class="swiper-slide logo-carousel__item">
        <?php if ($link) : ?>
            <a href="<?php echo $link; ?>" target="<?php echo $target; ?>" rel="noopener noreferrer" class="logo-carousel__link">
                <img src="<?php echo $image; ?>" alt="<?php echo $alt; ?>" class="logo-carousel__image">
            </a>
        <?php else : ?>
            <div class="logo-carousel__link">
                <img src="<?php echo $image; ?>" alt="<?php echo $alt; ?>" class="logo-carousel__image">
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('logo_item', 'logo_item_shortcode');

// ============================================
// TEAM CARDS SHORTCODE
// ============================================

function team_cards_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'columns' => '3',
        'style' => 'default',
    ), $atts);
    
    $columns = intval($atts['columns']);
    $style = esc_attr($atts['style']);
    
    // INLINE STYLES
    $inline_style = 'display:grid!important;gap:2rem!important;margin:2rem 0!important;width:100%!important;';
    
    if ($columns == 2) {
        $inline_style .= 'grid-template-columns:repeat(2,1fr)!important;';
    } elseif ($columns == 3) {
        $inline_style .= 'grid-template-columns:repeat(3,1fr)!important;';
    } elseif ($columns == 4) {
        $inline_style .= 'grid-template-columns:repeat(4,1fr)!important;';
    } else {
        $inline_style .= 'grid-template-columns:repeat(' . $columns . ',1fr)!important;';
    }
    
    ob_start();
    ?>
    <div class="team-cards team-cards--<?php echo $style; ?>" 
         data-columns="<?php echo $columns; ?>" 
         style="<?php echo $inline_style; ?>">
        <?php echo do_shortcode($content); ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('team_cards', 'team_cards_shortcode');

function team_member_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'name' => '',
        'role' => '',
        'image' => '',
        'email' => '',
        'phone' => '',
        'linkedin' => '',
        'twitter' => '',
        'facebook' => '',
        'instagram' => '',
    ), $atts);
    
    $name = esc_html($atts['name']);
    $role = esc_html($atts['role']);
    $image = esc_url($atts['image']);
    $email = esc_attr($atts['email']);
    $phone = esc_attr($atts['phone']);
    $linkedin = esc_url($atts['linkedin']);
    $twitter = esc_url($atts['twitter']);
    $facebook = esc_url($atts['facebook']);
    $instagram = esc_url($atts['instagram']);
    $bio = wpautop(do_shortcode($content));
    
    // Social Links
    $social_html = '';
    if ($linkedin || $twitter || $facebook || $instagram || $email) {
        $social_html .= '<div class="team-member__social">';
        
        if ($email) {
            $social_html .= '<a href="mailto:' . $email . '" class="team-member__social-link" aria-label="Email"><span class="dashicons dashicons-email"></span></a>';
        }
        if ($linkedin) {
            $social_html .= '<a href="' . $linkedin . '" target="_blank" rel="noopener noreferrer" class="team-member__social-link" aria-label="LinkedIn"><span class="dashicons dashicons-linkedin"></span></a>';
        }
        if ($twitter) {
            $social_html .= '<a href="' . $twitter . '" target="_blank" rel="noopener noreferrer" class="team-member__social-link" aria-label="Twitter"><span class="dashicons dashicons-twitter"></span></a>';
        }
        if ($facebook) {
            $social_html .= '<a href="' . $facebook . '" target="_blank" rel="noopener noreferrer" class="team-member__social-link" aria-label="Facebook"><span class="dashicons dashicons-facebook"></span></a>';
        }
        if ($instagram) {
            $social_html .= '<a href="' . $instagram . '" target="_blank" rel="noopener noreferrer" class="team-member__social-link" aria-label="Instagram"><span class="dashicons dashicons-instagram"></span></a>';
        }
        
        $social_html .= '</div>';
    }
    
    // Phone
    $phone_html = '';
    if ($phone) {
        $phone_html = '<div class="team-member__phone"><span class="dashicons dashicons-phone"></span> ' . esc_html($phone) . '</div>';
    }
    
    ob_start();
    ?>
    <div class="team-member" data-animate="fade-in-up">
        <?php if ($image) : ?>
            <div class="team-member__image-wrapper">
                <img src="<?php echo $image; ?>" alt="<?php echo $name; ?>" class="team-member__image">
                <?php if ($social_html) : ?>
                    <div class="team-member__overlay">
                        <?php echo $social_html; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="team-member__content">
            <?php if ($name) : ?>
                <h3 class="team-member__name"><?php echo $name; ?></h3>
            <?php endif; ?>
            
            <?php if ($role) : ?>
                <div class="team-member__role"><?php echo $role; ?></div>
            <?php endif; ?>
            
            <?php if ($bio) : ?>
                <div class="team-member__bio"><?php echo $bio; ?></div>
            <?php endif; ?>
            
            <?php echo $phone_html; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('team_member', 'team_member_shortcode');

// ============================================
// VIDEO PLAYER SHORTCODE
// ============================================

function video_player_shortcode($atts, $content = null) {
    static $player_id = 0;
    $player_id++;
    
    $atts = shortcode_atts(array(
        'url' => '',
        'type' => 'youtube', // youtube, vimeo, self-hosted
        'poster' => '', // Thumbnail image
        'title' => '',
        'autoplay' => 'false',
        'controls' => 'true',
        'muted' => 'false',
        'loop' => 'false',
        'aspect_ratio' => '16:9', // 16:9, 4:3, 1:1, 21:9
    ), $atts);
    
    $url = esc_url($atts['url']);
    $type = esc_attr($atts['type']);
    $poster = esc_url($atts['poster']);
    $title = esc_html($atts['title']);
    $autoplay = $atts['autoplay'] === 'true' ? '1' : '0';
    $controls = $atts['controls'] === 'true' ? '1' : '0';
    $muted = $atts['muted'] === 'true' ? '1' : '0';
    $loop = $atts['loop'] === 'true' ? '1' : '0';
    $aspect_ratio = esc_attr($atts['aspect_ratio']);
    $unique_id = 'video-player-' . $player_id;
    
    if (empty($url)) {
        return '<p><strong>Fehler:</strong> Bitte geben Sie eine Video-URL an.</p>';
    }
    
    // Parse video ID for YouTube/Vimeo
    $video_id = '';
    $embed_url = '';
    
    if ($type === 'youtube') {
        // Extract YouTube ID
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';
        
        if ($video_id) {
            $embed_url = 'https://www.youtube.com/embed/' . $video_id . '?autoplay=' . $autoplay . '&controls=' . $controls . '&mute=' . $muted . '&loop=' . $loop;
            if ($loop === '1') {
                $embed_url .= '&playlist=' . $video_id;
            }
        }
    } elseif ($type === 'vimeo') {
        // Extract Vimeo ID
        preg_match('/vimeo\.com\/([0-9]+)/i', $url, $matches);
        $video_id = isset($matches[1]) ? $matches[1] : '';
        
        if ($video_id) {
            $embed_url = 'https://player.vimeo.com/video/' . $video_id . '?autoplay=' . $autoplay . '&controls=' . $controls . '&muted=' . $muted . '&loop=' . $loop;
        }
    }
    
    // Aspect ratio class
    $ratio_class = '';
    switch ($aspect_ratio) {
        case '4:3':
            $ratio_class = 'video-player--4-3';
            break;
        case '1:1':
            $ratio_class = 'video-player--1-1';
            break;
        case '21:9':
            $ratio_class = 'video-player--21-9';
            break;
        default:
            $ratio_class = 'video-player--16-9';
    }
    
    ob_start();
    ?>
    <div class="video-player <?php echo $ratio_class; ?>" id="<?php echo $unique_id; ?>" data-type="<?php echo $type; ?>">
        <?php if ($title) : ?>
            <div class="video-player__header">
                <h3 class="video-player__title"><?php echo $title; ?></h3>
            </div>
        <?php endif; ?>
        
        <div class="video-player__wrapper">
            <?php if ($type === 'self-hosted') : ?>
                <!-- Self-hosted HTML5 Video -->
                <video class="video-player__video" 
                       <?php echo $controls === '1' ? 'controls' : ''; ?>
                       <?php echo $autoplay === '1' ? 'autoplay' : ''; ?>
                       <?php echo $muted === '1' ? 'muted' : ''; ?>
                       <?php echo $loop === '1' ? 'loop' : ''; ?>
                       <?php echo $poster ? 'poster="' . $poster . '"' : ''; ?>
                       playsinline>
                    <source src="<?php echo $url; ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php elseif ($embed_url) : ?>
                <!-- YouTube/Vimeo Embed -->
                <?php if ($poster && $autoplay === '0') : ?>
                    <!-- Custom Thumbnail with Play Button -->
                    <div class="video-player__thumbnail" data-video-url="<?php echo esc_attr($embed_url); ?>">
                        <img src="<?php echo $poster; ?>" alt="<?php echo $title; ?>" class="video-player__poster">
                        <button class="video-player__play-button" aria-label="Play video">
                            <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                                <circle cx="40" cy="40" r="40" fill="rgba(255,255,255,0.9)"/>
                                <path d="M32 25L55 40L32 55V25Z" fill="#667eea"/>
                            </svg>
                        </button>
                    </div>
                <?php else : ?>
                    <!-- Direct Embed -->
                    <iframe class="video-player__iframe"
                            src="<?php echo $embed_url; ?>"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                    </iframe>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($content) : ?>
            <div class="video-player__description">
                <?php echo wpautop(do_shortcode($content)); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('video_player', 'video_player_shortcode');

// ============================================
// FAQ ACCORDION SHORTCODES
// ============================================

/**
 * FAQ Accordion Shortcode
 * 
 * Usage: [faq_accordion category="general" limit="10"]
 */
function faq_accordion_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
        'limit' => '-1',
        'style' => 'default',
    ), $atts);
    
    $args = array(
        'post_type' => 'faq',
        'posts_per_page' => intval($atts['limit']),
        'orderby' => 'meta_value_num',
        'meta_key' => 'display_order',
        'order' => 'ASC',
        'post_status' => 'publish',
    );
    
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'faq_category',
                'field' => 'slug',
                'terms' => $atts['category'],
            ),
        );
    }
    
    $faqs = new WP_Query($args);
    
    if (!$faqs->have_posts()) {
        return '<p>Keine FAQs gefunden.</p>';
    }
    
    $output = '<div class="faq-accordion faq-accordion--' . esc_attr($atts['style']) . '">';
    
    $index = 0;
    while ($faqs->have_posts()) {
        $faqs->the_post();
        $question = get_the_title();
        $answer = get_field('answer');
        
        $output .= '<div class="faq-item">';
        $output .= '<button class="faq-question" aria-expanded="false">';
        $output .= '<span class="faq-question__text">' . esc_html($question) . '</span>';
        $output .= '<span class="faq-question__icon">';
        $output .= '<svg width="20" height="20" viewBox="0 0 20 20" fill="none">';
        $output .= '<path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
        $output .= '</svg>';
        $output .= '</span>';
        $output .= '</button>';
        $output .= '<div class="faq-answer">';
        $output .= '<div class="faq-answer__content">' . wp_kses_post($answer) . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        $index++;
    }
    
    $output .= '</div>';
    
    wp_reset_postdata();
    
    return $output;
}
add_shortcode('faq_accordion', 'faq_accordion_shortcode');

// ============================================
// TEAM QUERY SHORTCODE
// ============================================

function team_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number' => 3,
        'columns' => 3,
        'order' => 'ASC',
        'orderby' => 'menu_order',
        'style' => 'default',
    ), $atts);
    
    $number = intval($atts['number']);
    $columns = intval($atts['columns']);
    $order = esc_attr($atts['order']);
    $orderby = esc_attr($atts['orderby']);
    $style = esc_attr($atts['style']);
    
    // Query arguments
    $args = array(
        'post_type' => 'team',
        'posts_per_page' => $number,
        'order' => $order,
        'orderby' => $orderby,
        'post_status' => 'publish',
    );
    
    // If ordering by custom field (display_order)
    if ($orderby === 'display_order') {
        $args['orderby'] = 'meta_value_num';
        $args['meta_key'] = 'display_order';
    }
    
    $team_query = new WP_Query($args);
    
    if (!$team_query->have_posts()) {
        return '<p>Keine Team-Mitglieder gefunden.</p>';
    }
    
    // INLINE STYLES für garantiertes Grid
    $inline_style = 'display:grid!important;gap:2rem!important;margin:2rem 0!important;width:100%!important;';
    
    if ($columns == 2) {
        $inline_style .= 'grid-template-columns:repeat(2,1fr)!important;';
    } elseif ($columns == 3) {
        $inline_style .= 'grid-template-columns:repeat(3,1fr)!important;';
    } elseif ($columns == 4) {
        $inline_style .= 'grid-template-columns:repeat(4,1fr)!important;';
    } else {
        $inline_style .= 'grid-template-columns:repeat(' . $columns . ',1fr)!important;';
    }
    
    ob_start();
    ?>
    <div class="team-cards team-cards--<?php echo $style; ?>" 
         data-columns="<?php echo $columns; ?>" 
         style="<?php echo $inline_style; ?>">
        <?php while ($team_query->have_posts()) : $team_query->the_post(); ?>
            <?php
            $role = get_field('role');
            $email = get_field('email');
            $phone = get_field('phone');
            $social = get_field('social_media');
            $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            ?>
            
            <div class="team-member" data-animate="fade-in-up">
                <?php if ($thumbnail) : ?>
                    <div class="team-member__image-wrapper">
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php the_title(); ?>" class="team-member__image">
                        
                        <?php if ($social || $email) : ?>
                            <div class="team-member__overlay">
                                <div class="team-member__social">
                                    <?php if ($email) : ?>
                                        <a href="mailto:<?php echo esc_attr($email); ?>" class="team-member__social-link" aria-label="Email">
                                            <span class="dashicons dashicons-email"></span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($social && !empty($social['linkedin'])) : ?>
                                        <a href="<?php echo esc_url($social['linkedin']); ?>" target="_blank" rel="noopener noreferrer" class="team-member__social-link" aria-label="LinkedIn">
                                            <span class="dashicons dashicons-linkedin"></span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($social && !empty($social['twitter'])) : ?>
                                        <a href="<?php echo esc_url($social['twitter']); ?>" target="_blank" rel="noopener noreferrer" class="team-member__social-link" aria-label="Twitter">
                                            <span class="dashicons dashicons-twitter"></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="team-member__content">
                    <h3 class="team-member__name"><?php the_title(); ?></h3>
                    
                    <?php if ($role) : ?>
                        <div class="team-member__role"><?php echo esc_html($role); ?></div>
                    <?php endif; ?>
                    
                    <?php if (has_excerpt()) : ?>
                        <div class="team-member__bio">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($phone) : ?>
                        <div class="team-member__phone">
                            <span class="dashicons dashicons-phone"></span> <?php echo esc_html($phone); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('team_query', 'team_query_shortcode');

// ============================================
// PROJECTS QUERY SHORTCODE
// ============================================

function projects_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number' => 6,
        'columns' => 3,
        'category' => '',
        'order' => 'DESC',
        'orderby' => 'date',
    ), $atts);
    
    $number = intval($atts['number']);
    $columns = esc_attr($atts['columns']);
    $category = sanitize_text_field($atts['category']);
    $order = esc_attr($atts['order']);
    $orderby = esc_attr($atts['orderby']);
    
    $args = array(
        'post_type' => 'project',
        'posts_per_page' => $number,
        'order' => $order,
        'orderby' => $orderby,
        'post_status' => 'publish',
    );
    
    // Filter by category
    if (!empty($category)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'project_category',
                'field' => 'slug',
                'terms' => $category,
            ),
        );
    }
    
    $projects_query = new WP_Query($args);
    
    if (!$projects_query->have_posts()) {
        return '<p>Keine Projekte gefunden.</p>';
    }
    
    ob_start();
    ?>
    <div class="projects-grid" data-columns="<?php echo esc_attr($columns); ?>">
        <?php while ($projects_query->have_posts()) : $projects_query->the_post(); ?>
            <?php
            $client = get_field('client_name');
            $year = get_field('project_year');
            $url = get_field('project_url');
            $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'large');
            $categories = get_the_terms(get_the_ID(), 'project_category');
            ?>
            
            <div class="project-card" data-animate="fade-in-up">
                <?php if ($thumbnail) : ?>
                    <div class="project-card__image">
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php the_title(); ?>">
                        <div class="project-card__overlay">
                            <a href="<?php the_permalink(); ?>" class="project-card__link">
                                <span class="dashicons dashicons-visibility"></span>
                                Details ansehen
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="project-card__content">
                    <?php if ($categories) : ?>
                        <div class="project-card__categories">
                            <?php foreach ($categories as $cat) : ?>
                                <span class="project-card__category"><?php echo esc_html($cat->name); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="project-card__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    
                    <?php if ($client || $year) : ?>
                        <div class="project-card__meta">
                            <?php if ($client) : ?>
                                <span><?php echo esc_html($client); ?></span>
                            <?php endif; ?>
                            
                            <?php if ($client && $year) : ?>
                                <span>·</span>
                            <?php endif; ?>
                            
                            <?php if ($year) : ?>
                                <span><?php echo esc_html($year); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (has_excerpt()) : ?>
                        <div class="project-card__excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($url) : ?>
                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer" class="project-card__url">
                            <span class="dashicons dashicons-external"></span> Live ansehen
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('projects_query', 'projects_query_shortcode');

// ============================================
// TESTIMONIALS QUERY SHORTCODE
// ============================================

function testimonials_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number' => 3,
        'columns' => 3,
        'style' => 'card',
        'featured_only' => 'false',
        'slider' => 'false',
    ), $atts);
    
    $number = intval($atts['number']);
    $columns = esc_attr($atts['columns']);
    $style = esc_attr($atts['style']);
    $featured_only = $atts['featured_only'] === 'true';
    $slider = $atts['slider'] === 'true';
    
    $args = array(
        'post_type' => 'testimonials',
        'posts_per_page' => $number,
        'order' => 'DESC',
        'orderby' => 'date',
        'post_status' => 'publish',
    );
    
    // Filter featured only
    if ($featured_only) {
        $args['meta_query'] = array(
            array(
                'key' => 'featured',
                'value' => '1',
                'compare' => '=',
            ),
        );
    }
    
    $testimonials_query = new WP_Query($args);
    
    if (!$testimonials_query->have_posts()) {
        return '<p>Keine Testimonials gefunden.</p>';
    }
    
    $wrapper_class = $slider ? 'testimonials testimonials--slider testimonials--' . $style . ' swiper' : 'testimonials testimonials--' . $style;
    $item_class = $slider ? 'swiper-slide testimonial' : 'testimonial';
    
    ob_start();
    ?>
    <div class="<?php echo $wrapper_class; ?>" data-columns="<?php echo $columns; ?>" <?php if ($slider) echo 'data-autoplay="true"'; ?>>
        <div class="<?php echo $slider ? 'swiper-wrapper' : ''; ?>">
            <?php while ($testimonials_query->have_posts()) : $testimonials_query->the_post(); ?>
                <?php
                $company = get_field('company');
                $role = get_field('role');
                $rating = get_field('rating');
                $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                ?>
                
                <div class="<?php echo $item_class; ?>" data-animate="fade-in-up">
                    <?php if ($rating) : ?>
                        <div class="testimonial__rating">
                            <?php for ($i = 1; $i <= 5; $i++) : ?>
                                <span class="star <?php echo $i <= $rating ? 'star--filled' : 'star--empty'; ?>">
                                    <?php echo $i <= $rating ? '★' : '☆'; ?>
                                </span>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="testimonial__quote">
                        <?php the_content(); ?>
                    </div>
                    
                    <div class="testimonial__footer">
                        <?php if ($thumbnail) : ?>
                            <div class="testimonial__image">
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php the_title(); ?>">
                            </div>
                        <?php endif; ?>
                        
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
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <?php if ($slider) : ?>
            <div class="testimonials__navigation">
                <button class="testimonials__button testimonials__button--prev" aria-label="Previous">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button class="testimonials__button testimonials__button--next" aria-label="Next">
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
add_shortcode('testimonials_query', 'testimonials_query_shortcode');

// ============================================
// SERVICES QUERY SHORTCODE
// ============================================

function services_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number' => -1,
        'columns' => 3,
        'order' => 'ASC',
        'orderby' => 'menu_order',
    ), $atts);
    
    $number = intval($atts['number']);
    $columns = esc_attr($atts['columns']);
    $order = esc_attr($atts['order']);
    $orderby = esc_attr($atts['orderby']);
    
    $args = array(
        'post_type' => 'services',
        'posts_per_page' => $number,
        'order' => $order,
        'orderby' => $orderby,
        'post_status' => 'publish',
    );
    
    $services_query = new WP_Query($args);
    
    if (!$services_query->have_posts()) {
        return '<p>Keine Services gefunden.</p>';
    }
    
    ob_start();
    ?>
    <div class="services-grid" data-columns="<?php echo $columns; ?>">
        <?php while ($services_query->have_posts()) : $services_query->the_post(); ?>
            <?php
            $icon = get_field('icon');
            $price = get_field('price');
            $features = get_field('features');
            $cta = get_field('cta');
            $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'medium');
            ?>
            
            <div class="service-card" data-animate="fade-in-up">
                <?php if ($icon) : ?>
                    <div class="service-card__icon">
                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                    </div>
                <?php endif; ?>
                
                <h3 class="service-card__title"><?php the_title(); ?></h3>
                
                <?php if ($price) : ?>
                    <div class="service-card__price"><?php echo esc_html($price); ?></div>
                <?php endif; ?>
                
                <?php if (has_excerpt()) : ?>
                    <div class="service-card__excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($features) : ?>
                    <ul class="service-card__features">
                        <?php foreach ($features as $feature) : ?>
                            <li><?php echo esc_html($feature['text']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if ($cta && !empty($cta['link'])) : ?>
                    <a href="<?php echo esc_url($cta['link']['url']); ?>" 
                       class="service-card__cta button button--primary"
                       <?php echo !empty($cta['link']['target']) ? 'target="' . esc_attr($cta['link']['target']) . '"' : ''; ?>>
                        <?php echo esc_html($cta['text']); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php the_permalink(); ?>" class="service-card__cta button button--primary">
                        Mehr erfahren
                    </a>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('services_query', 'services_query_shortcode');

// ============================================
// SPOILER/READ-MORE SHORTCODE
// ============================================

function spoiler_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'open_text' => 'Mehr anzeigen',
        'close_text' => 'Weniger anzeigen',
        'open' => 'false',
        'style' => 'default',
        'icon' => 'true',
    ), $atts);
    
    $open_text = esc_html($atts['open_text']);
    $close_text = esc_html($atts['close_text']);
    $is_open = $atts['open'] === 'true';
    $style = esc_attr($atts['style']);
    $show_icon = $atts['icon'] === 'true';
    
    $unique_id = 'spoiler-' . uniqid();
    $open_class = $is_open ? ' is-open' : '';
    $display = $is_open ? 'block' : 'none';
    $button_text = $is_open ? $close_text : $open_text;
    
    $output = '<div class="spoiler spoiler--' . $style . $open_class . '" id="' . $unique_id . '">';
    $output .= '<button class="spoiler__toggle" ';
    $output .= 'data-open-text="' . esc_attr($open_text) . '" ';
    $output .= 'data-close-text="' . esc_attr($close_text) . '" ';
    $output .= 'aria-expanded="' . ($is_open ? 'true' : 'false') . '">';
    $output .= '<span class="spoiler__button-text">' . $button_text . '</span>';
    
    if ($show_icon) {
        $output .= '<span class="spoiler__icon">';
        $output .= '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">';
        $output .= '<path d="M8 4l4 4-4 4V4z"/>';
        $output .= '</svg>';
        $output .= '</span>';
    }
    
    $output .= '</button>';
    $output .= '<div class="spoiler__content" style="display: ' . $display . ';">';
    $output .= wpautop(do_shortcode($content));
    $output .= '</div>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('spoiler', 'spoiler_shortcode');

function read_more_shortcode($atts, $content = null) {
    $defaults = array(
        'open_text' => 'Weiterlesen',
        'close_text' => 'Weniger anzeigen',
        'open' => 'false',
        'style' => 'minimal',
        'icon' => 'true',
    );
    
    $atts = shortcode_atts($defaults, $atts);
    
    return spoiler_shortcode($atts, $content);
}
add_shortcode('read_more', 'read_more_shortcode');

// ============================================
// PRICING TABLES SHORTCODE
// ============================================

function pricing_tables_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'columns' => '3',
        'style' => 'default',
    ), $atts);
    
    $columns = intval($atts['columns']);
    $style = esc_attr($atts['style']);
    
    // INLINE STYLES
    $inline_style = 'display:grid!important;gap:2rem!important;margin:4rem 0!important;width:100%!important;align-items:stretch!important;';
    
    if ($columns == 2) {
        $inline_style .= 'grid-template-columns:repeat(2,1fr)!important;';
    } elseif ($columns == 3) {
        $inline_style .= 'grid-template-columns:repeat(3,1fr)!important;';
    } elseif ($columns == 4) {
        $inline_style .= 'grid-template-columns:repeat(4,1fr)!important;';
    } else {
        $inline_style .= 'grid-template-columns:repeat(' . $columns . ',1fr)!important;';
    }
    
    return '<div class="pricing-tables pricing-tables--' . $style . '" data-columns="' . $columns . '" style="' . $inline_style . '">' . do_shortcode($content) . '</div>';
}
add_shortcode('pricing_tables', 'pricing_tables_shortcode');

function pricing_table_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'title' => '',
        'price' => '',
        'currency' => '€',
        'period' => 'pro Monat',
        'featured' => 'false',
        'button_text' => 'Jetzt starten',
        'button_link' => '#',
        'button_target' => '_self',
        'badge' => '', // e.g. "Beliebt", "Empfohlen"
        'description' => '',
    ), $atts);
    
    $title = esc_html($atts['title']);
    $price = esc_html($atts['price']);
    $currency = esc_html($atts['currency']);
    $period = esc_html($atts['period']);
    $featured = $atts['featured'] === 'true';
    $button_text = esc_html($atts['button_text']);
    $button_link = esc_url($atts['button_link']);
    $button_target = esc_attr($atts['button_target']);
    $badge = esc_html($atts['badge']);
    $description = esc_html($atts['description']);
    
    $featured_class = $featured ? ' pricing-table--featured' : '';
    
    ob_start();
    ?>
    <div class="pricing-table<?php echo $featured_class; ?>" data-animate="fade-in-up">
        <?php if ($badge) : ?>
            <div class="pricing-table__badge"><?php echo $badge; ?></div>
        <?php endif; ?>
        
        <div class="pricing-table__header">
            <?php if ($title) : ?>
                <h3 class="pricing-table__title"><?php echo $title; ?></h3>
            <?php endif; ?>
            
            <?php if ($description) : ?>
                <p class="pricing-table__description"><?php echo $description; ?></p>
            <?php endif; ?>
        </div>
        
        <div class="pricing-table__price">
            <span class="pricing-table__currency"><?php echo $currency; ?></span>
            <span class="pricing-table__amount"><?php echo $price; ?></span>
            <?php if ($period) : ?>
                <span class="pricing-table__period"><?php echo $period; ?></span>
            <?php endif; ?>
        </div>
        
        <div class="pricing-table__features">
            <?php echo wpautop(do_shortcode($content)); ?>
        </div>
        
        <div class="pricing-table__footer">
            <a href="<?php echo $button_link; ?>" 
               class="pricing-table__button button button--primary" 
               target="<?php echo $button_target; ?>">
                <?php echo $button_text; ?>
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pricing_table', 'pricing_table_shortcode');

// Helper shortcode for feature lists
function pricing_feature_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'icon' => 'check', // check, cross, info
        'highlight' => 'false',
    ), $atts);
    
    $icon = esc_attr($atts['icon']);
    $highlight = $atts['highlight'] === 'true' ? ' pricing-feature--highlight' : '';
    
    $icon_html = '';
    switch ($icon) {
        case 'check':
            $icon_html = '<span class="pricing-feature__icon pricing-feature__icon--check">✓</span>';
            break;
        case 'cross':
            $icon_html = '<span class="pricing-feature__icon pricing-feature__icon--cross">✗</span>';
            break;
        case 'info':
            $icon_html = '<span class="pricing-feature__icon pricing-feature__icon--info">i</span>';
            break;
    }
    
    return '<div class="pricing-feature' . $highlight . '">' . $icon_html . '<span>' . esc_html($content) . '</span></div>';
}
add_shortcode('pricing_feature', 'pricing_feature_shortcode');

// ============================================
// WOOCOMMERCE SHORTCODES
// ============================================

/**
 * Products Grid Shortcode
 */
// ============================================
// AJAX SEARCH SHORTCODE
// ============================================

/**
 * AJAX Search Shortcode
 * 
 * Usage: [ajax_search post_types="post,page,product" limit="10" search_page="/search/"]
 */
function ajax_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'placeholder' => 'Suchen...',
        'limit' => 5,
        'post_types' => 'post,page',
        'search_page' => home_url('/'),
    ), $atts);
    
    $unique_id = 'search-' . uniqid();
    
    ob_start();
    ?>
    <div class="ajax-search" 
         id="<?php echo esc_attr($unique_id); ?>"
         data-limit="<?php echo esc_attr($atts['limit']); ?>"
         data-post-types="<?php echo esc_attr($atts['post_types']); ?>"
         data-search-page="<?php echo esc_url($atts['search_page']); ?>">
        
        <form class="ajax-search__form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
            <div class="ajax-search__input-wrapper">
                <!-- Search Icon -->
                <span class="ajax-search__icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </span>
                
                <!-- Input Field -->
                <input 
                    type="search" 
                    name="s"
                    class="ajax-search__input" 
                    placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                    autocomplete="off"
                    value="<?php echo get_search_query(); ?>"
                >
                
                <!-- Submit Button -->
                <button type="submit" class="ajax-search__submit" aria-label="Suche starten">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
                
                <!-- Loading Indicator -->
                <div class="ajax-search__loading" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                </div>
            </div>
            
            <!-- AJAX Results (Live Dropdown) -->
            <div class="ajax-search__results" style="display: none;"></div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ajax_search', 'ajax_search_shortcode');

// ============================================
// LOAD MORE SHORTCODES
// ============================================

/**
 * Posts with Load More Button
 * 
 * Usage: [posts_load_more post_type="post" posts_per_page="6" category="news" template="card"]
 */
/**
 * Posts with Load More Button
 */
function posts_load_more_shortcode($atts, $content = null) {
    // Default attributes
    $defaults = array(
        'post_type' => 'post',
        'posts_per_page' => '6',
        'category' => '',
        'orderby' => 'date',
        'order' => 'DESC',
        'template' => 'card',
        'button_text' => 'Mehr laden',
        'loading_text' => 'Lädt...',
        'columns' => '3',
    );
    
    // Merge with provided attributes
    $atts = shortcode_atts($defaults, $atts, 'posts_load_more');
    
    // Ensure all required keys exist (WordPress sometimes doesn't parse all attributes)
    foreach ($defaults as $key => $default_value) {
        if (!isset($atts[$key]) || $atts[$key] === '') {
            $atts[$key] = $default_value;
        }
    }
    
    // Sanitize
    $atts['post_type'] = sanitize_text_field($atts['post_type']);
    $atts['posts_per_page'] = intval($atts['posts_per_page']);
    $atts['category'] = sanitize_text_field($atts['category']);
    $atts['orderby'] = sanitize_text_field($atts['orderby']);
    $atts['order'] = sanitize_text_field($atts['order']);
    $atts['template'] = sanitize_text_field($atts['template']);
    $atts['button_text'] = sanitize_text_field($atts['button_text']);
    $atts['loading_text'] = sanitize_text_field($atts['loading_text']);
    $atts['columns'] = sanitize_text_field($atts['columns']);
    
    $container_id = 'load-more-' . uniqid();
    $grid_id = $container_id . '-grid';
    
    // Initial query
    $args = array(
        'post_type' => $atts['post_type'],
        'posts_per_page' => $atts['posts_per_page'],
        'post_status' => 'publish',
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
    );
    
    if (!empty($atts['category'])) {
        $taxonomy = agency_core_get_taxonomy_for_post_type($atts['post_type']);
        
        if ($taxonomy) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $atts['category'],
                ),
            );
        }
    }
    
    $query = new WP_Query($args);
    
    ob_start();
    ?>
    <div class="posts-load-more" id="<?php echo esc_attr($container_id); ?>">
        <div class="posts-load-more__grid posts-load-more__grid--columns-<?php echo esc_attr($atts['columns']); ?>" id="<?php echo esc_attr($grid_id); ?>">
            <?php
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    posts_load_more_render_template($atts['template']);
                }
            } else {
                echo '<p>Keine Beiträge gefunden.</p>';
            }
            ?>
        </div>
        
        <?php if ($query->max_num_pages > 1) : ?>
            <div class="posts-load-more__button-wrapper">
                <button 
                    class="posts-load-more__button"
                    data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
                    data-posts-per-page="<?php echo esc_attr($atts['posts_per_page']); ?>"
                    data-category="<?php echo esc_attr($atts['category']); ?>"
                    data-orderby="<?php echo esc_attr($atts['orderby']); ?>"
                    data-order="<?php echo esc_attr($atts['order']); ?>"
                    data-template="<?php echo esc_attr($atts['template']); ?>"
                    data-max-pages="<?php echo esc_attr($query->max_num_pages); ?>"
                    data-current-page="1"
                    data-container="#<?php echo esc_attr($grid_id); ?>"
                    data-button-text="<?php echo esc_attr($atts['button_text']); ?>"
                    data-loading-text="<?php echo esc_attr($atts['loading_text']); ?>"
                >
                    <span class="posts-load-more__button-text"><?php echo esc_html($atts['button_text']); ?></span>
                    <span class="posts-load-more__button-icon">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M10 4v12m6-6H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </div>
        <?php else: ?>
            <p class="posts-load-more__no-more">Alle Beiträge werden angezeigt.</p>
        <?php endif; ?>
    </div>
    <?php
    
    wp_reset_postdata();
    
    return ob_get_clean();
}
add_shortcode('posts_load_more', 'posts_load_more_shortcode');

/**
 * Render template for load more
 */
function posts_load_more_render_template($template) {
    switch ($template) {
        case 'card':
            posts_load_more_template_card();
            break;
            
        case 'team':
            posts_load_more_template_team();
            break;
            
        case 'project':
            posts_load_more_template_project();
            break;
            
        case 'list':
            posts_load_more_template_list();
            break;
            
        default:
            posts_load_more_template_card();
            break;
    }
}

/**
 * Card Template
 */
function posts_load_more_template_card() {
    ?>
    <article class="post-card" data-post-id="<?php echo get_the_ID(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="post-card__thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('medium'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="post-card__content">
            <h3 class="post-card__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <div class="post-card__meta">
                <span class="post-card__date"><?php echo get_the_date('d.m.Y'); ?></span>
            </div>
            
            <div class="post-card__excerpt">
                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
            </div>
            
            <a href="<?php the_permalink(); ?>" class="post-card__link">
                Mehr lesen
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                    <path d="M6 12l4-4-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
        </div>
    </article>
    <?php
}

/**
 * Team Template
 */
function posts_load_more_template_team() {
    $role = get_field('role');
    $email = get_field('email');
    ?>
    <div class="team-card" data-post-id="<?php echo get_the_ID(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="team-card__image">
                <?php the_post_thumbnail('medium'); ?>
            </div>
        <?php endif; ?>
        
        <div class="team-card__content">
            <h3 class="team-card__name"><?php the_title(); ?></h3>
            
            <?php if ($role) : ?>
                <p class="team-card__role"><?php echo esc_html($role); ?></p>
            <?php endif; ?>
            
            <?php if ($email) : ?>
                <a href="mailto:<?php echo esc_attr($email); ?>" class="team-card__email">
                    <?php echo esc_html($email); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Project Template
 */
function posts_load_more_template_project() {
    $client = get_field('client');
    $project_date = get_field('project_date');
    ?>
    <article class="project-card" data-post-id="<?php echo get_the_ID(); ?>">
        <?php if (has_post_thumbnail()) : ?>
            <div class="project-card__image">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('large'); ?>
                </a>
            </div>
        <?php endif; ?>
        
        <div class="project-card__content">
            <h3 class="project-card__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <?php if ($client) : ?>
                <p class="project-card__client">Client: <?php echo esc_html($client); ?></p>
            <?php endif; ?>
            
            <?php if ($project_date) : ?>
                <p class="project-card__date"><?php echo esc_html($project_date); ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

/**
 * List Template
 */
function posts_load_more_template_list() {
    ?>
    <article class="post-list-item" data-post-id="<?php echo get_the_ID(); ?>">
        <div class="post-list-item__content">
            <h3 class="post-list-item__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            
            <div class="post-list-item__meta">
                <span class="post-list-item__date"><?php echo get_the_date('d.m.Y'); ?></span>
            </div>
            
            <div class="post-list-item__excerpt">
                <?php echo wp_trim_words(get_the_excerpt(), 30); ?>
            </div>
        </div>
        
        <?php if (has_post_thumbnail()) : ?>
            <div class="post-list-item__thumbnail">
                <a href="<?php the_permalink(); ?>">
                    <?php the_post_thumbnail('thumbnail'); ?>
                </a>
            </div>
        <?php endif; ?>
    </article>
    <?php
}

// ============================================
// GOOGLE MAPS SHORTCODE (DSGVO)
// ============================================

/**
 * DSGVO-konforme Google Maps
 * 
 * Usage: [google_map id="123" height="400px"]
 * Usage: [google_map address="Stephansplatz 1, 1010 Wien" lat="48.2082" lng="16.3738"]
 */
function google_map_shortcode($atts) {
    static $map_counter = 0;
    $map_counter++;
    
    $atts = shortcode_atts(array(
        'id' => '',
        'address' => '',
        'lat' => '',
        'lng' => '',
        'zoom' => '15',
        'height' => '400px',
        'marker_title' => '',
        'style' => 'default',
    ), $atts);
    
    // Load from CPT if ID provided
    if (!empty($atts['id'])) {
        $post_id = intval($atts['id']);
        $atts['address'] = get_field('address', $post_id);
        $atts['lat'] = get_field('latitude', $post_id);
        $atts['lng'] = get_field('longitude', $post_id);
        $atts['zoom'] = get_field('zoom', $post_id) ?: '15';
        $atts['marker_title'] = get_field('marker_title', $post_id) ?: get_the_title($post_id);
        $atts['style'] = get_field('map_style', $post_id) ?: 'default';
    }
    
    // Validate required fields
    if (empty($atts['lat']) || empty($atts['lng'])) {
        return '<p>⚠️ Google Maps: Lat/Lng fehlt</p>';
    }
    
    $map_id = 'gmap-' . $map_counter;
    
    ob_start();
    ?>
    <div class="google-map-wrapper" 
         data-map-id="<?php echo esc_attr($map_id); ?>"
         data-lat="<?php echo esc_attr($atts['lat']); ?>"
         data-lng="<?php echo esc_attr($atts['lng']); ?>"
         data-zoom="<?php echo esc_attr($atts['zoom']); ?>"
         data-marker-title="<?php echo esc_attr($atts['marker_title']); ?>"
         data-style="<?php echo esc_attr($atts['style']); ?>"
         style="height: <?php echo esc_attr($atts['height']); ?>;">
        
        <!-- DSGVO Overlay (before consent) -->
        <div class="google-map-overlay">
            <div class="google-map-overlay__content">
                <div class="google-map-overlay__icon">
                    <svg width="64" height="64" viewBox="0 0 64 64" fill="none">
                        <path d="M32 4C20.96 4 12 12.96 12 24c0 13.5 20 36 20 36s20-22.5 20-36c0-11.04-8.96-20-20-20zm0 27c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z" fill="currentColor"/>
                    </svg>
                </div>
                
                <h3 class="google-map-overlay__title">Google Maps</h3>
                
                <p class="google-map-overlay__text">
                    Um die Karte anzuzeigen, benötigen wir Ihre Zustimmung. 
                    Durch das Laden der Karte akzeptieren Sie die 
                    <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Datenschutzerklärung von Google</a>.
                </p>
                
                <?php if (!empty($atts['address'])) : ?>
                    <p class="google-map-overlay__address">
                        <strong>Adresse:</strong><br>
                        <?php echo nl2br(esc_html($atts['address'])); ?>
                    </p>
                <?php endif; ?>
                
                <button class="google-map-overlay__button" data-action="load-map">
                    Karte laden
                </button>
                
                <p class="google-map-overlay__privacy">
                    <small>
                        Es werden Cookies von Google gesetzt. 
                        <a href="/datenschutz">Mehr erfahren</a>
                    </small>
                </p>
            </div>
        </div>
        
        <!-- Map Container (loaded after consent) -->
        <div id="<?php echo esc_attr($map_id); ?>" class="google-map-canvas"></div>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode('google_map', 'google_map_shortcode');

// ============================================
// JOBS QUERY SHORTCODE
// ============================================

/**
 * Jobs Query Shortcode
 * 
 * Usage: [jobs_query number="6" columns="2" category="development"]
 */
function jobs_query_shortcode($atts) {
    $atts = shortcode_atts(array(
        'number' => 6,
        'columns' => 2,
        'category' => '',
        'type' => '',
        'location' => '',
        'featured' => '',
        'order' => 'DESC',
        'orderby' => 'date',
    ), $atts);
    
    $args = array(
        'post_type' => 'job',
        'posts_per_page' => intval($atts['number']),
        'post_status' => 'publish',
        'order' => $atts['order'],
        'orderby' => $atts['orderby'],
    );
    
    // Tax Query
    $tax_query = array();
    
    if (!empty($atts['category'])) {
        $tax_query[] = array(
            'taxonomy' => 'job_category',
            'field' => 'slug',
            'terms' => explode(',', $atts['category']),
        );
    }
    
    if (!empty($atts['type'])) {
        $tax_query[] = array(
            'taxonomy' => 'job_type',
            'field' => 'slug',
            'terms' => explode(',', $atts['type']),
        );
    }
    
    if (!empty($atts['location'])) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
            'field' => 'slug',
            'terms' => explode(',', $atts['location']),
        );
    }
    
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }
    
    // Meta Query for Featured
    if ($atts['featured'] === 'true' || $atts['featured'] === '1') {
        $args['meta_query'] = array(
            array(
                'key' => 'featured',
                'value' => '1',
                'compare' => '=',
            ),
        );
    }
    
    $jobs = new WP_Query($args);
    
    if (!$jobs->have_posts()) {
        return '<p>Keine Jobs gefunden.</p>';
    }
    
    $columns = intval($atts['columns']);
    
    ob_start();
    ?>
    <div class="jobs-grid" data-columns="<?php echo esc_attr($columns); ?>">
        <?php while ($jobs->have_posts()) : $jobs->the_post(); ?>
            <?php
            $employment_type = get_field('employment_type');
            $experience_level = get_field('experience_level');
            $remote_work = get_field('remote_work');
            $salary_min = get_field('salary_min');
            $salary_max = get_field('salary_max');
            $salary_display = get_field('salary_display');
            $location_terms = get_the_terms(get_the_ID(), 'job_location');
            $type_terms = get_the_terms(get_the_ID(), 'job_type');
            $featured = get_field('featured');
            $urgent = get_field('urgent');
            $start_date = get_field('start_date');
            ?>
            
            <article class="job-card <?php echo $featured ? 'job-card--featured' : ''; ?> <?php echo $urgent ? 'job-card--urgent' : ''; ?>">
                
                <?php if ($featured || $urgent) : ?>
                    <div class="job-card__badges">
                        <?php if ($featured) : ?>
                            <span class="job-card__badge job-card__badge--featured">Featured</span>
                        <?php endif; ?>
                        <?php if ($urgent) : ?>
                            <span class="job-card__badge job-card__badge--urgent">Dringend</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="job-card__header">
                    <h3 class="job-card__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    
                    <?php if ($location_terms && !is_wp_error($location_terms)) : ?>
                        <div class="job-card__location">
                            <span class="dashicons dashicons-location"></span>
                            <?php echo esc_html($location_terms[0]->name); ?>
                            <?php if ($remote_work === 'remote') : ?>
                                <span class="job-card__remote-badge">Remote</span>
                            <?php elseif ($remote_work === 'hybrid') : ?>
                                <span class="job-card__remote-badge">Hybrid</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="job-card__meta">
                    <?php if ($employment_type) : ?>
                        <span class="job-card__meta-item">
                            <span class="dashicons dashicons-clock"></span>
                            <?php
                            $types = array(
                                'full-time' => 'Vollzeit',
                                'part-time' => 'Teilzeit',
                                'contract' => 'Vertrag',
                                'temporary' => 'Befristet',
                                'internship' => 'Praktikum',
                                'freelance' => 'Freelance',
                            );
                            echo esc_html($types[$employment_type] ?? $employment_type);
                            ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($experience_level) : ?>
                        <span class="job-card__meta-item">
                            <span class="dashicons dashicons-groups"></span>
                            <?php
                            $levels = array(
                                'entry' => 'Einsteiger',
                                'junior' => 'Junior',
                                'mid' => 'Mid-Level',
                                'senior' => 'Senior',
                                'lead' => 'Lead',
                            );
                            echo esc_html($levels[$experience_level] ?? $experience_level);
                            ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($start_date) : ?>
                        <span class="job-card__meta-item">
                            <span class="dashicons dashicons-calendar"></span>
                            <?php echo esc_html($start_date); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <?php if (has_excerpt()) : ?>
                    <div class="job-card__excerpt">
                        <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($salary_display && ($salary_min || $salary_max)) : ?>
                    <div class="job-card__salary">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php
                        if ($salary_min && $salary_max) {
                            echo number_format($salary_min, 0, ',', '.') . ' - ' . number_format($salary_max, 0, ',', '.') . ' €';
                        } elseif ($salary_min) {
                            echo 'ab ' . number_format($salary_min, 0, ',', '.') . ' €';
                        } else {
                            echo 'Nach Vereinbarung';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($type_terms && !is_wp_error($type_terms)) : ?>
                    <div class="job-card__tags">
                        <?php foreach ($type_terms as $term) : ?>
                            <span class="job-card__tag"><?php echo esc_html($term->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="job-card__footer">
                    <a href="<?php the_permalink(); ?>" class="job-card__button button button--primary">
                        Details ansehen
                    </a>
                </div>
            </article>
            
        <?php endwhile; ?>
    </div>
    <?php
    
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('jobs_query', 'jobs_query_shortcode');

// ============================================
// AJAX FILTERS SHORTCODES
// ============================================

/**
 * AJAX Filters Container
 * 
 * Usage: [ajax_filters post_type="job" posts_per_page="12" template="card" columns="3"]
 */
function ajax_filters_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'post_type' => 'post',
        'posts_per_page' => '12',
        'template' => 'card',
        'columns' => '3',
        'show_count' => 'true',
        'show_sort' => 'true',
        'show_reset' => 'true',
    ), $atts);
    
    $unique_id = 'ajax-filters-' . uniqid();
    
    ob_start();
    ?>
    <div class="ajax-filters" 
         id="<?php echo esc_attr($unique_id); ?>"
         data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
         data-posts-per-page="<?php echo esc_attr($atts['posts_per_page']); ?>"
         data-template="<?php echo esc_attr($atts['template']); ?>"
         data-grid-columns="<?php echo esc_attr($atts['columns']); ?>">
        
        <!-- Filter Sidebar -->
        <div class="ajax-filters__sidebar">
            <div class="ajax-filters__header">
                <h3 class="ajax-filters__title">Filter</h3>
                
                <?php if ($atts['show_reset'] === 'true') : ?>
                    <button class="ajax-filters__reset" style="display:none;">
                        Zurücksetzen
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Filter Forms (from nested shortcodes) -->
            <div class="ajax-filters__forms">
                <?php echo do_shortcode($content); ?>
            </div>
            
            <!-- Active Filters -->
            <div class="ajax-filters__active" style="display:none;">
                <h4>Aktive Filter:</h4>
                <div class="ajax-filters__active-list"></div>
            </div>
        </div>
        
        <!-- Results Area -->
        <div class="ajax-filters__results">
            <!-- Toolbar -->
            <div class="ajax-filters__toolbar">
                <?php if ($atts['show_count'] === 'true') : ?>
                    <div class="ajax-filters__count">
                        <span class="ajax-filters__count-number">0</span> Ergebnisse
                    </div>
                <?php endif; ?>
                
                <?php if ($atts['show_sort'] === 'true') : ?>
                    <div class="ajax-filters__sort">
                        <label for="<?php echo esc_attr($unique_id); ?>-sort">Sortieren:</label>
                        <select id="<?php echo esc_attr($unique_id); ?>-sort" class="ajax-filters__sort-select">
                            <option value="date-desc">Neueste zuerst</option>
                            <option value="date-asc">Älteste zuerst</option>
                            <option value="title-asc">A-Z</option>
                            <option value="title-desc">Z-A</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Loading Overlay -->
            <div class="ajax-filters__loading" style="display:none;">
                <div class="ajax-filters__spinner"></div>
            </div>
            
            <!-- Results Grid -->
            <div class="ajax-filters__grid ajax-filters__grid--columns-<?php echo esc_attr($atts['columns']); ?>">
                <!-- Results loaded via AJAX -->
            </div>
            
            <!-- Pagination -->
            <div class="ajax-filters__pagination"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('ajax_filters', 'ajax_filters_shortcode');

/**
 * Taxonomy Filter
 * 
 * Usage: [filter_taxonomy taxonomy="job_category" type="checkbox" label="Kategorie"]
 */
function filter_taxonomy_shortcode($atts) {
    $atts = shortcode_atts(array(
        'taxonomy' => '',
        'type' => 'checkbox', // checkbox, radio, dropdown, buttons
        'label' => 'Filter',
        'show_count' => 'true',
        'operator' => 'IN', // IN, AND, NOT IN
    ), $atts);
    
    if (empty($atts['taxonomy'])) {
        return '';
    }
    
    $taxonomy = sanitize_text_field($atts['taxonomy']);
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
    ));
    
    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }
    
    $type = sanitize_text_field($atts['type']);
    $input_type = ($type === 'radio') ? 'radio' : 'checkbox';
    $input_name = ($type === 'radio') ? 'tax_' . $taxonomy : 'tax_' . $taxonomy . '[]';
    
    ob_start();
    ?>
    <div class="ajax-filter ajax-filter--taxonomy ajax-filter--<?php echo esc_attr($type); ?>" 
         data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
         data-operator="<?php echo esc_attr($atts['operator']); ?>">
        
        <h4 class="ajax-filter__label"><?php echo esc_html($atts['label']); ?></h4>
        
        <?php if ($type === 'dropdown') : ?>
            <select class="ajax-filter__select" data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                <option value="">Alle</option>
                <?php foreach ($terms as $term) : ?>
                    <option value="<?php echo esc_attr($term->slug); ?>">
                        <?php echo esc_html($term->name); ?>
                        <?php if ($atts['show_count'] === 'true') : ?>
                            (<?php echo $term->count; ?>)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
        <?php elseif ($type === 'buttons') : ?>
            <div class="ajax-filter__buttons">
                <?php foreach ($terms as $term) : ?>
                    <button type="button" 
                            class="ajax-filter__button" 
                            data-value="<?php echo esc_attr($term->slug); ?>"
                            data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                        <?php echo esc_html($term->name); ?>
                        <?php if ($atts['show_count'] === 'true') : ?>
                            <span class="ajax-filter__count"><?php echo $term->count; ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
        <?php else : ?>
            <div class="ajax-filter__options">
                <?php foreach ($terms as $term) : ?>
                    <label class="ajax-filter__option">
                        <input type="<?php echo esc_attr($input_type); ?>" 
                               name="<?php echo esc_attr($input_name); ?>" 
                               value="<?php echo esc_attr($term->slug); ?>"
                               data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                        <span class="ajax-filter__option-label">
                            <?php echo esc_html($term->name); ?>
                            <?php if ($atts['show_count'] === 'true') : ?>
                                <span class="ajax-filter__count">(<?php echo $term->count; ?>)</span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('filter_taxonomy', 'filter_taxonomy_shortcode');

/**
 * Meta Field Range Filter
 * 
 * Usage: [filter_range key="salary_min" min="0" max="100000" step="1000" label="Gehalt"]
 */
function filter_range_shortcode($atts) {
    $atts = shortcode_atts(array(
        'key' => '',
        'min' => '0',
        'max' => '100',
        'step' => '1',
        'label' => 'Range',
        'prefix' => '',
        'suffix' => '',
    ), $atts);
    
    if (empty($atts['key'])) {
        return '';
    }
    
    $unique_id = 'range-' . uniqid();
    
    ob_start();
    ?>
    <div class="ajax-filter ajax-filter--range" data-meta-key="<?php echo esc_attr($atts['key']); ?>">
        <h4 class="ajax-filter__label"><?php echo esc_html($atts['label']); ?></h4>
        
        <div class="ajax-filter__range-wrapper">
            <input type="range" 
                   id="<?php echo esc_attr($unique_id); ?>-min"
                   class="ajax-filter__range-input ajax-filter__range-min" 
                   min="<?php echo esc_attr($atts['min']); ?>" 
                   max="<?php echo esc_attr($atts['max']); ?>" 
                   step="<?php echo esc_attr($atts['step']); ?>"
                   value="<?php echo esc_attr($atts['min']); ?>"
                   data-meta-key="<?php echo esc_attr($atts['key']); ?>">
            
            <input type="range" 
                   id="<?php echo esc_attr($unique_id); ?>-max"
                   class="ajax-filter__range-input ajax-filter__range-max" 
                   min="<?php echo esc_attr($atts['min']); ?>" 
                   max="<?php echo esc_attr($atts['max']); ?>" 
                   step="<?php echo esc_attr($atts['step']); ?>"
                   value="<?php echo esc_attr($atts['max']); ?>"
                   data-meta-key="<?php echo esc_attr($atts['key']); ?>">
            
            <div class="ajax-filter__range-values">
                <span class="ajax-filter__range-value">
                    <?php echo esc_html($atts['prefix']); ?>
                    <span class="ajax-filter__range-min-value"><?php echo esc_html($atts['min']); ?></span>
                    <?php echo esc_html($atts['suffix']); ?>
                </span>
                <span class="ajax-filter__range-separator">-</span>
                <span class="ajax-filter__range-value">
                    <?php echo esc_html($atts['prefix']); ?>
                    <span class="ajax-filter__range-max-value"><?php echo esc_html($atts['max']); ?></span>
                    <?php echo esc_html($atts['suffix']); ?>
                </span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('filter_range', 'filter_range_shortcode');

/**
 * Search Filter
 * 
 * Usage: [filter_search placeholder="Suche..." label="Suche"]
 */
function filter_search_shortcode($atts) {
    $atts = shortcode_atts(array(
        'placeholder' => 'Suche...',
        'label' => 'Suche',
    ), $atts);
    
    ob_start();
    ?>
    <div class="ajax-filter ajax-filter--search">
        <h4 class="ajax-filter__label"><?php echo esc_html($atts['label']); ?></h4>
        
        <div class="ajax-filter__search-wrapper">
            <input type="text" 
                   class="ajax-filter__search-input" 
                   placeholder="<?php echo esc_attr($atts['placeholder']); ?>">
            <button type="button" class="ajax-filter__search-button">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('filter_search', 'filter_search_shortcode');
