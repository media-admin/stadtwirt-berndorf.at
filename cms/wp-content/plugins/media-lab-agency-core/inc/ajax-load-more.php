<?php
/**
 * AJAX Load More Handler
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX Load More Handlers
 */
add_action('wp_ajax_agency_load_more', 'agency_core_ajax_load_more');
add_action('wp_ajax_nopriv_agency_load_more', 'agency_core_ajax_load_more');

/**
 * AJAX Load More Handler
 */
function agency_core_ajax_load_more() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'agency_load_more_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token'));
        return;
    }
    
    // Get parameters
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 6;
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';
    $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'default';
    
    // Build query args
    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => $orderby,
        'order' => $order,
    );
    
    // Add category filter if specified
    if (!empty($category)) {
        $taxonomy = agency_core_get_taxonomy_for_post_type($post_type);
        
        if ($taxonomy) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => $category,
                ),
            );
        }
    }
    
    // Execute query
    $query = new WP_Query($args);
    
    $posts = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            // Get post data based on template
            $post_data = agency_core_get_post_data($template);
            
            $posts[] = $post_data;
        }
        
        wp_reset_postdata();
    }
    
    // Send response
    wp_send_json_success(array(
        'posts' => $posts,
        'max_pages' => $query->max_num_pages,
        'current_page' => $page,
        'found_posts' => $query->found_posts,
    ));
}

/**
 * Get taxonomy for post type
 */
function agency_core_get_taxonomy_for_post_type($post_type) {
    $taxonomy_map = array(
        'post' => 'category',
        'project' => 'project_category',
        'faq' => 'faq_category',
        'carousel' => 'carousel_category',
    );
    
    return isset($taxonomy_map[$post_type]) ? $taxonomy_map[$post_type] : '';
}

/**
 * Get post data based on template
 */
function agency_core_get_post_data($template) {
    $post_id = get_the_ID();
    
    $base_data = array(
        'id' => $post_id,
        'title' => get_the_title(),
        'excerpt' => get_the_excerpt(),
        'content' => get_the_content(),
        'url' => get_permalink(),
        'date' => get_the_date('d.m.Y'),
        'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
        'thumbnail_large' => get_the_post_thumbnail_url($post_id, 'large'),
    );
    
    // Template-specific data
    switch ($template) {
        case 'team':
            $base_data['role'] = get_field('role');
            $base_data['email'] = get_field('email');
            $base_data['social'] = get_field('social_media');
            break;
            
        case 'project':
            $base_data['client'] = get_field('client');
            $base_data['project_date'] = get_field('project_date');
            $base_data['technologies'] = get_field('technologies');
            break;
            
        case 'testimonial':
            $base_data['author_name'] = get_field('author_name');
            $base_data['company'] = get_field('company');
            $base_data['rating'] = get_field('rating');
            break;
            
        case 'faq':
            $base_data['answer'] = get_field('answer');
            break;
            
        default:
            // Allow plugins to extend post type data (e.g. WooCommerce products)
            break;
    }
    
    // Allow plugins to extend result data
    $base_data = apply_filters('media_lab_load_more_post_data', $base_data, $post_id, get_post_type($post_id));

    return $base_data;
}