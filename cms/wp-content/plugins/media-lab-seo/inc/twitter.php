<?php
/**
 * Twitter Cards
 * 
 * @package MediaLab_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output Twitter Card tags
 */
function medialab_seo_output_twitter() {
    if (get_option('medialab_seo_enabled') !== '1') {
        return;
    }
    
    if (get_option('medialab_seo_twitter_enabled') !== '1') {
        return;
    }
    
    echo "\n<!-- Twitter Card Tags by Media Lab SEO -->\n";
    
    // Card type
    $card_type = has_post_thumbnail() ? 'summary_large_image' : 'summary';
    echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '">' . "\n";
    
    // Site username
    $twitter_username = get_option('medialab_seo_twitter_username');
    if (!empty($twitter_username)) {
        echo '<meta name="twitter:site" content="' . esc_attr($twitter_username) . '">' . "\n";
    }
    
    // Title
    $post_id = get_the_ID();
    $title = is_front_page() ? get_bloginfo('name') : medialab_seo_get_title($post_id);
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    
    // Description
    $description = (is_single() || is_page()) && function_exists('medialab_seo_get_description')
        ? medialab_seo_get_description($post_id)
        : get_bloginfo('description');
    if (!empty($description)) {
        echo '<meta name="twitter:description" content="' . esc_attr(wp_trim_words($description, 30)) . '">' . "\n";
    }
    
    // Image
    $image = function_exists('medialab_seo_get_og_image')
        ? medialab_seo_get_og_image($post_id)
        : (has_post_thumbnail() ? get_the_post_thumbnail_url(null, 'full') : get_option('medialab_seo_default_image'));
    if (!empty($image)) {
        echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
    }
}
add_action('wp_head', 'medialab_seo_output_twitter', 7);
