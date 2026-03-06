<?php
/**
 * Open Graph Tags
 * 
 * @package MediaLab_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output Open Graph tags
 */
function medialab_seo_output_opengraph() {
    if (get_option('medialab_seo_enabled') !== '1') {
        return;
    }
    
    if (get_option('medialab_seo_og_enabled') !== '1') {
        return;
    }
    
    echo "\n<!-- Open Graph Tags by Media Lab SEO -->\n";
    
    // Site Name
    $site_name = get_option('medialab_seo_site_name', get_bloginfo('name'));
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
    
    // Type
    $type = is_single() ? 'article' : 'website';
    echo '<meta property="og:type" content="' . esc_attr($type) . '">' . "\n";
    
    // URL
    $url = is_front_page() ? home_url('/') : get_permalink();
    echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
    
    // Title
    $post_id = get_the_ID();
    $title = is_front_page() ? get_bloginfo('name') : medialab_seo_get_title($post_id);
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    
    // Description
    $description = (is_single() || is_page()) && function_exists('medialab_seo_get_description')
        ? medialab_seo_get_description($post_id)
        : get_bloginfo('description');
    if (!empty($description)) {
        echo '<meta property="og:description" content="' . esc_attr(wp_trim_words($description, 30)) . '">' . "\n";
    }
    
    // Image
    $image = function_exists('medialab_seo_get_og_image')
        ? medialab_seo_get_og_image($post_id)
        : (has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'full') : get_option('medialab_seo_default_image'));
    if (!empty($image)) {
        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
    }
    
    // Locale
    echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";
}
add_action('wp_head', 'medialab_seo_output_opengraph', 6);
