<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) exit;

// Get SVG Icon
function customtheme_get_svg($icon_name) {
    $svg_path = CUSTOMTHEME_DIR . '/assets/src/images/icons/' . $icon_name . '.svg';
    if (file_exists($svg_path)) {
        return file_get_contents($svg_path);
    }
    return '';
}

// Reading Time
function customtheme_reading_time($post_id = null) {
    $post_id = $post_id ?: get_the_ID();
    $content = get_post_field('post_content', $post_id);
    $word_count = str_word_count(strip_tags($content));
    return ceil($word_count / 200);
}

// Excerpt Length
function customtheme_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'customtheme_excerpt_length');


/**
 * Generate Picture Element with WebP support
 * 
 * @param int $image_id Attachment ID
 * @param string $size Image size
 * @param array $args Additional arguments
 * @return string Picture HTML
 */
function customtheme_get_picture($image_id, $size = 'large', $args = array()) {
    if (!$image_id) {
        return '';
    }
    
    $defaults = array(
        'class' => '',
        'alt' => '',
        'loading' => 'lazy',
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Get image URLs
    $image_url = wp_get_attachment_image_url($image_id, $size);
    $image_webp = str_replace(['.jpg', '.jpeg', '.png'], '.webp', $image_url);
    
    // Get srcset
    $srcset = wp_get_attachment_image_srcset($image_id, $size);
    $sizes = wp_get_attachment_image_sizes($image_id, $size);
    
    // Alt text
    $alt = $args['alt'] ?: get_post_meta($image_id, '_wp_attachment_image_alt', true);
    
    ob_start();
    ?>
    <picture>
        <!-- WebP -->
        <source 
            type="image/webp" 
            srcset="<?php echo esc_attr($image_webp); ?>"
            sizes="<?php echo esc_attr($sizes); ?>"
        >
        
        <!-- Fallback -->
        <img 
            src="<?php echo esc_url($image_url); ?>"
            srcset="<?php echo esc_attr($srcset); ?>"
            sizes="<?php echo esc_attr($sizes); ?>"
            alt="<?php echo esc_attr($alt); ?>"
            class="<?php echo esc_attr($args['class']); ?>"
            loading="<?php echo esc_attr($args['loading']); ?>"
        >
    </picture>
    <?php
    
    return ob_get_clean();
}

/**
 * Get Posts by Type with Caching
 */
function customtheme_get_posts($post_type, $args = array()) {
    $cache_key = 'customtheme_posts_' . $post_type . '_' . md5(serialize($args));
    $posts = get_transient($cache_key);
    
    if (false === $posts) {
        $defaults = array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC',
        );
        
        $args = wp_parse_args($args, $defaults);
        $posts = get_posts($args);
        
        set_transient($cache_key, $posts, HOUR_IN_SECONDS);
    }
    
    return $posts;
}

/**
 * Get FAQ Items for Accordion
 */
function customtheme_get_faq_items() {
    $faqs = customtheme_get_posts('faq', array(
        'meta_key' => 'faq_order',
        'orderby' => 'meta_value_num',
    ));
    
    $items = array();
    
    foreach ($faqs as $faq) {
        $items[] = array(
            'title' => get_the_title($faq),
            'content' => apply_filters('the_content', $faq->post_content),
        );
    }
    
    return $items;
}

/**
 * Get Hero Slides
 */
function customtheme_get_hero_slides() {
    $slides = customtheme_get_posts('slide');
    $slides_data = array();
    
    foreach ($slides as $slide) {
        $slides_data[] = array(
            'image' => get_the_post_thumbnail_url($slide->ID, 'customtheme-hero'),
            'title' => get_the_title($slide->ID),
            'subtitle' => get_field('slide_subtitle', $slide->ID),
            'button_text' => get_field('slide_button_text', $slide->ID),
            'button_link' => get_field('slide_button_link', $slide->ID),
        );
    }
    
    return $slides_data;
}

/**
 * Get Projects for Grid
 */
function customtheme_get_projects($limit = -1) {
    return customtheme_get_posts('project', array(
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
}

/**
 * Get Team Members
 */
function customtheme_get_team_members() {
    return customtheme_get_posts('team');
}

/**
 * Clear Custom Post Type Cache on Save
 */
function customtheme_clear_cpt_cache($post_id) {
    $post_type = get_post_type($post_id);
    
    if (in_array($post_type, array('slide', 'faq', 'project', 'team', 'testimonial', 'service'))) {
        delete_transient('customtheme_posts_' . $post_type . '_*');
    }
}
add_action('save_post', 'customtheme_clear_cpt_cache');