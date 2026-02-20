<?php
/**
 * AJAX Search Handler
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Search Handler
 */
add_action('wp_ajax_agency_search', 'agency_core_ajax_search');
add_action('wp_ajax_nopriv_agency_search', 'agency_core_ajax_search');

function agency_core_ajax_search() {
    // Verify nonce - MUSS MIT functions.php ÜBEREINSTIMMEN!
    check_ajax_referer('agency_search_nonce', 'nonce');
    
    // Get search query
    $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    
    if (empty($search_query) || strlen($search_query) < 2) {
        wp_send_json_error(array(
            'message' => 'Search query too short'
        ));
    }
    
    // Get post types - handle both string and array format
    $post_types = array('post', 'page', 'product'); // Default
    
    if (isset($_POST['post_types'])) {
        $raw = $_POST['post_types'];
        
        // Handle string (e.g., "post,page,product")
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }
        
        if (is_array($raw)) {
            $post_types = array_map('sanitize_text_field', $raw);
            $post_types = array_filter($post_types);
        }
    }
    
    // Get limit
    $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 5;
    
    // Search query
    $args = array(
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        's' => $search_query,
        'orderby' => 'relevance',
        'order' => 'DESC',
    );
    
    $query = new WP_Query($args);
    
    $results = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $result = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => wp_trim_words(get_the_excerpt(), 15),
                'date' => get_the_date('d.m.Y'),
                'post_type' => get_post_type(),
                'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'),
            );
            
            // Allow plugins to extend result data (e.g. WooCommerce)
            $result = apply_filters('media_lab_ajax_search_result', $result, get_the_ID(), get_post_type());
            
            $results[] = $result;
        }
    }
    
    wp_reset_postdata();
    
    if (!empty($results)) {
        wp_send_json_success(array(
            'results' => $results,
            'count' => count($results),
            'query' => $search_query,
        ));
    } else {
        wp_send_json_success(array(
            'results' => array(),
            'count' => 0,
            'message' => 'No results found',
        ));
    }
}