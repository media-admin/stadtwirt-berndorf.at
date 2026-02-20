<?php
/**
 * Admin Functions
 * 
 * Admin-specific functionality for Agency Core.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add custom admin menu
 */
add_action('admin_menu', function() {
    add_menu_page(
        __('Agency Core', 'agency-core'),
        __('Agency Core', 'agency-core'),
        'manage_options',
        'agency-core',
        'agency_core_admin_page',
        'dashicons-admin-generic',
        100
    );
});

/**
 * Admin page callback
 */
function agency_core_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="card">
            <h2>Plugin Information</h2>
            <p><strong>Version:</strong> <?php echo MEDIALAB_CORE_VERSION; ?></p>
            <p><strong>Status:</strong> <span style="color: green;">●</span> Active</p>
        </div>
        
        <div class="card">
            <h2>Registered Custom Post Types</h2>
            <ul>
                <li>✅ Hero Slides</li>
                <li>✅ Team Members</li>
                <li>✅ Projects</li>
                <li>✅ Testimonials</li>
                <li>✅ FAQs</li>
                <li>✅ Google Maps</li>
                <li>✅ Carousel</li>
                <li>✅ Services</li>
            </ul>
        </div>
        
        <div class="card">
            <h2>Available Shortcodes</h2>
            <p>All shortcodes are available and work with any theme.</p>
            <p><a href="<?php echo admin_url('admin.php?page=agency-core-docs'); ?>" class="button">View Documentation</a></p>
        </div>
        
        <div class="card">
            <h2>Theme Compatibility</h2>
            <p>Current Theme: <strong><?php echo wp_get_theme()->get('Name'); ?></strong></p>
            <?php if (defined('MEDIALAB_CORE_VERSION')) : ?>
                <p style="color: green;">✅ Agency Core is active and working correctly.</p>
            <?php else : ?>
                <p style="color: red;">❌ Agency Core is not properly loaded.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Add custom columns to CPT admin lists
 */

// Team columns
add_filter('manage_team_posts_columns', function($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['thumbnail'] = __('Photo', 'agency-core');
    $new_columns['title'] = $columns['title'];
    $new_columns['role'] = __('Role', 'agency-core');
    $new_columns['email'] = __('Email', 'agency-core');
    $new_columns['date'] = $columns['date'];
    return $new_columns;
});

add_action('manage_team_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'thumbnail':
            echo get_the_post_thumbnail($post_id, array(50, 50));
            break;
        case 'role':
            echo esc_html(get_field('role', $post_id));
            break;
        case 'email':
            $email = get_field('email', $post_id);
            echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '—';
            break;
    }
}, 10, 2);

// Project columns
add_filter('manage_project_posts_columns', function($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['thumbnail'] = __('Image', 'agency-core');
    $new_columns['title'] = $columns['title'];
    $new_columns['client'] = __('Client', 'agency-core');
    $new_columns['project_date'] = __('Project Date', 'agency-core');
    $new_columns['taxonomy-project_category'] = __('Categories', 'agency-core');
    $new_columns['date'] = $columns['date'];
    return $new_columns;
});

add_action('manage_project_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'thumbnail':
            echo get_the_post_thumbnail($post_id, array(80, 60));
            break;
        case 'client':
            echo esc_html(get_field('client', $post_id));
            break;
        case 'project_date':
            echo esc_html(get_field('project_date', $post_id));
            break;
    }
}, 10, 2);

// Testimonial columns
add_filter('manage_testimonial_posts_columns', function($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['thumbnail'] = __('Photo', 'agency-core');
    $new_columns['title'] = $columns['title'];
    $new_columns['author_name'] = __('Author', 'agency-core');
    $new_columns['company'] = __('Company', 'agency-core');
    $new_columns['rating'] = __('Rating', 'agency-core');
    $new_columns['date'] = $columns['date'];
    return $new_columns;
});

add_action('manage_testimonial_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'thumbnail':
            $image = get_field('author_image', $post_id);
            echo $image ? '<img src="' . esc_url($image) . '" width="50" height="50" style="border-radius: 50%;">' : '—';
            break;
        case 'author_name':
            echo esc_html(get_field('author_name', $post_id));
            break;
        case 'company':
            echo esc_html(get_field('company', $post_id));
            break;
        case 'rating':
            $rating = get_field('rating', $post_id);
            echo $rating ? str_repeat('⭐', (int)$rating) : '—';
            break;
    }
}, 10, 2);