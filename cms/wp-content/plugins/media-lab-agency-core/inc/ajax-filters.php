<?php
/**
 * AJAX Filters System
 * 
 * Provides advanced filtering for posts, CPTs, and WooCommerce products.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX endpoints
 */
add_action('wp_ajax_ajax_filter_posts', 'agency_core_ajax_filter_posts');
add_action('wp_ajax_nopriv_ajax_filter_posts', 'agency_core_ajax_filter_posts');

/**
 * AJAX Filter Posts Handler
 */
function agency_core_ajax_filter_posts() {
    // Verify nonce
    check_ajax_referer('ajax_filters_nonce', 'nonce');
    
    // Get filter parameters
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 12;
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $template = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : 'card';
    
    // Taxonomy filters
    $tax_filters = isset($_POST['taxonomies']) ? json_decode(stripslashes($_POST['taxonomies']), true) : array();
    
    // Meta filters
    $meta_filters = isset($_POST['meta']) ? json_decode(stripslashes($_POST['meta']), true) : array();
    
    // Search
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    
    // Sort
    $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date';
    $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'DESC';
    
    // Build query args
    $args = array(
        'post_type' => $post_type,
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_status' => 'publish',
        'orderby' => $orderby,
        'order' => $order,
    );
    
    // Add search
    if (!empty($search)) {
        $args['s'] = $search;
    }
    
    // Build tax query
    if (!empty($tax_filters) && is_array($tax_filters)) {
        $tax_query = array('relation' => 'AND');
        
        foreach ($tax_filters as $taxonomy => $terms) {
            if (!empty($terms) && is_array($terms)) {
                $tax_query[] = array(
                    'taxonomy' => sanitize_text_field($taxonomy),
                    'field' => 'slug',
                    'terms' => array_map('sanitize_text_field', $terms),
                    'operator' => 'IN',
                );
            }
        }
        
        if (count($tax_query) > 1) {
            $args['tax_query'] = $tax_query;
        }
    }
    
    // Build meta query
    if (!empty($meta_filters) && is_array($meta_filters)) {
        $meta_query = array('relation' => 'AND');
        
        foreach ($meta_filters as $filter) {
            if (isset($filter['key'])) {
                $meta_item = array(
                    'key' => sanitize_text_field($filter['key']),
                );
                
                // Range filter
                if (isset($filter['min']) && isset($filter['max'])) {
                    $meta_item['value'] = array(
                        floatval($filter['min']),
                        floatval($filter['max'])
                    );
                    $meta_item['compare'] = 'BETWEEN';
                    $meta_item['type'] = 'NUMERIC';
                }
                // Single value
                else if (isset($filter['value'])) {
                    $meta_item['value'] = sanitize_text_field($filter['value']);
                    $meta_item['compare'] = isset($filter['compare']) ? $filter['compare'] : '=';
                }
                
                $meta_query[] = $meta_item;
            }
        }
        
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }
    }
    
    // Execute query
    $query = new WP_Query($args);
    
    // Prepare posts array
    $posts = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            
            $post_data = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'url' => get_permalink(),
                'excerpt' => wp_trim_words(get_the_excerpt(), 20),
                'date' => get_the_date('d.m.Y'),
                'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'medium'),
                'thumbnail_large' => get_the_post_thumbnail_url(get_the_ID(), 'large'),
            );
            
            // Add post-type specific data
            if ($post_type === 'job') {
                $post_data['employment_type'] = get_field('employment_type');
                $post_data['location'] = get_field('location');
                $location_terms = get_the_terms(get_the_ID(), 'job_location');
                if ($location_terms && !is_wp_error($location_terms)) {
                    $post_data['location'] = $location_terms[0]->name;
                }
            }
            
            if ($post_type === 'team') {
                $post_data['role'] = get_field('position');
                $post_data['email'] = get_field('email');
            }
            
            if ($post_type === 'project') {
                $post_data['client'] = get_field('client_name');
                $post_data['project_date'] = get_field('project_date');
            }

            if ($post_type === 'event') {
                $post_data['date_start'] = get_field('event_date_start');
                $post_data['date_end']   = get_field('event_date_end');
                $post_data['location']   = get_field('event_location');
                $post_data['price']      = get_field('event_price');
            }
            
            $posts[] = $post_data;
        }
    }
    
    wp_reset_postdata();
    
    // Prepare response
    $response = array(
        'posts' => $posts,
        'found_posts' => $query->found_posts,
        'max_pages' => $query->max_num_pages,
        'current_page' => $paged,
    );
    
    // Send response with success wrapper
    wp_send_json_success($response);
}
