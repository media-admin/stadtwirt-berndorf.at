<?php
/**
 * Custom Post Types
 * 
 * Register all custom post types for the agency core functionality.
 * These CPTs persist across theme changes.
 * 
 * @package Agency_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Team CPT
 */
function agency_core_register_team_cpt() {
    $labels = array(
        'name' => __('Team', 'agency-core'),
        'singular_name' => __('Team Mitglied', 'agency-core'),
        'menu_name' => __('Team', 'agency-core'),
        'add_new' => __('Neu hinzufügen', 'agency-core'),
        'add_new_item' => __('Neues Team Mitglied', 'agency-core'),
        'edit_item' => __('Team Mitglied bearbeiten', 'agency-core'),
        'new_item' => __('New Team Member', 'agency-core'),
        'view_item' => __('View Team Member', 'agency-core'),
        'search_items' => __('Search Team', 'agency-core'),
        'not_found' => __('No team members found', 'agency-core'),
        'not_found_in_trash' => __('No team members found in trash', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public'              => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
        'menu_icon' => 'dashicons-groups',
        'menu_position' => 20,
        'rewrite' => array('slug' => 'team'),
        'capability_type' => 'post',
    );
    
    register_post_type('team', $args);
}
add_action('init', 'agency_core_register_team_cpt');




/**
 * Register Testimonials CPT
 */
function agency_core_register_testimonials_cpt() {
    $labels = array(
        'name' => __('Testimonials', 'agency-core'),
        'singular_name' => __('Testimonial', 'agency-core'),
        'menu_name' => __('Testimonials', 'agency-core'),
        'add_new' => __('Neu hinzufügen', 'agency-core'),
        'add_new_item' => __('Neues Testimonial', 'agency-core'),
        'edit_item' => __('Testimonial bearbeiten', 'agency-core'),
        'new_item' => __('Neues Testimonial', 'agency-core'),
        'view_item' => __('Testimonial', 'agency-core'),
        'search_items' => __('Testimonials durchsuchen', 'agency-core'),
        'not_found' => __('Keine Testimonials gefunden', 'agency-core'),
        'not_found_in_trash' => __('Keine Testimonials im Papierkorb gefunden', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public'              => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'menu_icon' => 'dashicons-testimonial',
        'menu_position' => 22,
        'rewrite' => array('slug' => 'testimonials'),
        'capability_type' => 'post',
    );
    
    register_post_type('testimonial', $args);
}
add_action('init', 'agency_core_register_testimonials_cpt');


/**
 * Register Services CPT
 */
function agency_core_register_services_cpt() {
    $labels = array(
        'name' => __('Leistungen', 'agency-core'),
        'singular_name' => __('Leistung', 'agency-core'),
        'menu_name' => __('Leistungen', 'agency-core'),
        'add_new' => __('Neu hinzufügen', 'agency-core'),
        'add_new_item' => __('Neue Leistung', 'agency-core'),
        'edit_item' => __('Leistung bearbeiten', 'agency-core'),
        'new_item' => __('Neue Leistung', 'agency-core'),
        'view_item' => __('Leistung anzeigen', 'agency-core'),
        'search_items' => __('Leistungen durchsuchen', 'agency-core'),
        'not_found' => __('Keine Leistungen gefunden', 'agency-core'),
        'not_found_in_trash' => __('Keine Leistungen im Papierkorb gefunden', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public'              => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'),
        'menu_icon' => 'dashicons-admin-tools',
        'menu_position' => 23,
        'rewrite' => array('slug' => 'leistungen'),
        'capability_type' => 'post',
    );
    
    register_post_type('service', $args);
}
add_action('init', 'agency_core_register_services_cpt');


/**
 * Register Services Categories
 */
function agency_core_register_service_categories() {
    $labels = array(
        'name' => __('Leistungs-Kategorien', 'agency-core'),
        'singular_name' => __('Leistungs-Kategorie', 'agency-core'),
        'search_items' => __('Kategorien durchsuchen', 'agency-core'),
        'all_items' => __('Alle Kategorien', 'agency-core'),
        'parent_item' => __('Übergeordnete Kategorie', 'agency-core'),
        'parent_item_colon' => __('Übergeordnete Kategorie:', 'agency-core'),
        'edit_item' => __('Kategorie bearbeiten', 'agency-core'),
        'update_item' => __('Kategorie aktualisieren', 'agency-core'),
        'add_new_item' => __('Neue Kategorie hinzufügen', 'agency-core'),
        'new_item_name' => __('Neuer Kategorie-Name', 'agency-core'),
        'menu_name' => __('Service Kategorien', 'agency-core'),
    );
    
    register_taxonomy('service_category', array('service'), array(
        'labels' => $labels,
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'rewrite' => array('slug' => 'leistungs-kategorie'),
    ));
}
add_action('init', 'agency_core_register_service_categories');


/**
 * Register FAQ CPT
 */
function agency_core_register_faq_cpt() {
    $labels = array(
        'name' => __('FAQ', 'agency-core'),
        'singular_name' => __('Frage', 'agency-core'),
        'menu_name' => __('Fragen', 'agency-core'),
        'add_new' => __('Neu hinzufügen', 'agency-core'),
        'add_new_item' => __('Neue Frage', 'agency-core'),
        'edit_item' => __('Frage bearbeiten', 'agency-core'),
        'new_item' => __('Neue Frage', 'agency-core'),
        'view_item' => __('Frage anzeigen', 'agency-core'),
        'search_items' => __('Fragen durchsuchen', 'agency-core'),
        'not_found' => __('Keine Fragen gefunden', 'agency-core'),
        'not_found_in_trash' => __('Keine Fragen im Papierkorb gefunden', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'description' => __('Frequently Asked Questions', 'agency-core'),
        'hierarchical' => false,
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 24,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => false,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
        'supports' => array('title', 'editor', 'page-attributes'),
        'menu_icon' => 'dashicons-editor-help',
        'rewrite' => array('slug' => 'faq'),
    );
    
    register_post_type('faq', $args);
}
add_action('init', 'agency_core_register_faq_cpt');


/**
 * Register FAQ Category Taxonomy
 */
function agency_core_register_faq_category_taxonomy() {
    $labels = array(
        'name' => _x('FAQ Kategorien', 'taxonomy general name', 'agency-core'),
        'singular_name' => _x('FAQ Kategorie', 'taxonomy singular name', 'agency-core'),
        'search_items' => __('FAQ Kategorien durchsuchen', 'agency-core'),
        'all_items' => __('Alle FAQ Kategorien', 'agency-core'),
        'parent_item' => __('Übergeordnete FAQ Kategorie', 'agency-core'),
        'parent_item_colon' => __('Übergeordnete FAQ Kategorie:', 'agency-core'),
        'edit_item' => __('FAQ Kategorie bearbeiten', 'agency-core'),
        'update_item' => __('FAQ Kategorie updaten', 'agency-core'),
        'add_new_item' => __('FAQ Kategorie hinzufügen', 'agency-core'),
        'new_item_name' => __('Neuer FAQ Kategorie Name', 'agency-core'),
        'menu_name' => __('FAQ Kategorien', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => false,
        'show_tagcloud' => false,
        'show_in_rest' => true,
    );
    
    register_taxonomy('faq_category', array('faq'), $args);
}
add_action('init', 'agency_core_register_faq_category_taxonomy');


/**
 * Register Google Maps Post Type
 */
function agency_core_register_maps_cpt() {
    $labels = array(
        'name' => _x('Maps', 'Post Type General Name', 'agency-core'),
        'singular_name' => _x('Map', 'Post Type Singular Name', 'agency-core'),
        'menu_name' => __('Google Maps', 'agency-core'),
        'name_admin_bar' => __('Map', 'agency-core'),
        'all_items' => __('Alle Maps', 'agency-core'),
        'add_new_item' => __('Neue Map hinzufügen', 'agency-core'),
        'add_new' => __('Neue hinzufügen', 'agency-core'),
        'new_item' => __('Neue Map', 'agency-core'),
        'edit_item' => __('Map bearbeiten', 'agency-core'),
        'update_item' => __('Map updaten', 'agency-core'),
        'view_item' => __('Map anzeigen', 'agency-core'),
        'search_items' => __('Map suchen', 'agency-core'),
        'not_found' => __('Nichts gefunden', 'agency-core'),
        'not_found_in_trash' => __('Nichts im Papierkorb gefunden', 'agency-core'),
    );
    
    $args = array(
        'label' => __('Google Map', 'agency-core'),
        'description' => __('Google Maps Locations', 'agency-core'),
        'labels' => $labels,
        'supports' => array('title'),
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 25,
        'menu_icon' => 'dashicons-location-alt',
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => false,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
    );
    
    register_post_type('gmap', $args);
}
add_action('init', 'agency_core_register_maps_cpt');


/**
 * Register Hero Slide Post Type
 */
function agency_core_register_hero_slide_cpt() {
    $labels = array(
        'name' => _x('Hero Slides', 'Post Type General Name', 'agency-core'),
        'singular_name' => _x('Hero Slide', 'Post Type Singular Name', 'agency-core'),
        'menu_name' => __('Hero Slides', 'agency-core'),
        'name_admin_bar' => __('Hero Slide', 'agency-core'),
        'archives' => __('Hero Slide Archive', 'agency-core'),
        'attributes' => __('Hero Slide Attribute', 'agency-core'),
        'parent_item_colon' => __('Übergeordnete Hero Slide:', 'agency-core'),
        'all_items' => __('Alle Hero Slides', 'agency-core'),
        'add_new_item' => __('Neue Hero Slide hinzufügen', 'agency-core'),
        'add_new' => __('Neue Hero Slide', 'agency-core'),
        'new_item' => __('Neue Hero Slide', 'agency-core'),
        'edit_item' => __('Hero Slide bearbeiten', 'agency-core'),
        'update_item' => __('Hero Slide updaten', 'agency-core'),
        'view_item' => __('View Hero Slide anzeigen', 'agency-core'),
        'view_items' => __('Hero Slides anzeigen', 'agency-core'),
        'search_items' => __('Hero Slide durchsuchen', 'agency-core'),
        'not_found' => __('Nichts gefunden', 'agency-core'),
        'not_found_in_trash' => __('Nichts im Papierkorb gefunden', 'agency-core'),
        'featured_image' => __('Featured Image', 'agency-core'),
        'set_featured_image' => __('Featured Image festlegen', 'agency-core'),
        'remove_featured_image' => __('Featured Image entfernen', 'agency-core'),
        'use_featured_image' => __('Als Featured Image verwenden', 'agency-core'),
        'insert_into_item' => __('Zur Hero Slide einfügen', 'agency-core'),
        'uploaded_to_this_item' => __('Zu dieser Hero Slide hochladen', 'agency-core'),
        'items_list' => __('Hero Slides Liste', 'agency-core'),
        'items_list_navigation' => __('Hero Slides Listen-Navigation', 'agency-core'),
        'filter_items_list' => __('Hero Slides Liste filtern', 'agency-core'),
    );
    
    $args = array(
        'label' => __('Hero Slide', 'agency-core'),
        'description' => __('Hero Slider Slides', 'agency-core'),
        'labels' => $labels,
        'supports' => array('title', 'editor', 'thumbnail'),
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 26,
        'menu_icon' => 'dashicons-slides',
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => false,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
    );
    
    register_post_type('hero_slide', $args);
}
add_action('init', 'agency_core_register_hero_slide_cpt');


/**
 * Register Carousel Post Type
 */
function agency_core_register_carousel_cpt() {
    $labels = array(
        'name' => _x('Karussell Elemente', 'Post Type General Name', 'agency-core'),
        'singular_name' => _x('Karussell Element', 'Post Type Singular Name', 'agency-core'),
        'menu_name' => __('Karussells', 'agency-core'),
        'name_admin_bar' => __('Karussell Element', 'agency-core'),
        'archives' => __('Karussell Archiv', 'agency-core'),
        'attributes' => __('Karussell Attribute', 'agency-core'),
        'all_items' => __('Alle Elemente', 'agency-core'),
        'add_new_item' => __('Neues Element hinzufügen', 'agency-core'),
        'add_new' => __('Neues hinzufügen', 'agency-core'),
        'new_item' => __('Neues Element', 'agency-core'),
        'edit_item' => __('Element bearbeiten', 'agency-core'),
        'update_item' => __('Element updaten', 'agency-core'),
        'view_item' => __('Element anzeigen', 'agency-core'),
        'view_items' => __('Elemente anzeigen', 'agency-core'),
        'search_items' => __('Element suchen', 'agency-core'),
        'not_found' => __('Nichts gefunden', 'agency-core'),
        'not_found_in_trash' => __('Nichts im Papierkorb gefunden', 'agency-core'),
    );
    
    $args = array(
        'label' => __('Karussell Element', 'agency-core'),
        'description' => __('Karussell Elemente', 'agency-core'),
        'labels' => $labels,
        'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 27,
        'menu_icon' => 'dashicons-images-alt2',
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => false,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'capability_type' => 'post',
        'show_in_rest' => true,
    );
    
    register_post_type('carousel', $args);
}
add_action('init', 'agency_core_register_carousel_cpt');


/**
 * Register Carousel Category Taxonomy
 */
function agency_core_register_carousel_category_taxonomy() {
    $labels = array(
        'name' => _x('Karussell Kategorien', 'taxonomy general name', 'agency-core'),
        'singular_name' => _x('Karussell Kategorie', 'taxonomy singular name', 'agency-core'),
        'search_items' => __('Kategorien durchsuchen', 'agency-core'),
        'all_items' => __('Alle Kategorien', 'agency-core'),
        'edit_item' => __('Kategorie bearbeiten', 'agency-core'),
        'update_item' => __('Kategorie updaten', 'agency-core'),
        'add_new_item' => __('Neue Kategorie hinzufügen', 'agency-core'),
        'new_item_name' => __('Neuer Kategorie-Name', 'agency-core'),
        'menu_name' => __('Kategorien', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'public' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => false,
        'show_tagcloud' => false,
        'show_in_rest' => true,
    );
    
    register_taxonomy('carousel_category', array('carousel'), $args);
}
add_action('init', 'agency_core_register_carousel_category_taxonomy');


/**
 * Register Jobs Post Type
 */
function agency_core_register_jobs_cpt() {
    $labels = array(
        'name' => __('Jobs', 'agency-core'),
        'singular_name' => __('Job', 'agency-core'),
        'menu_name' => __('Jobs', 'agency-core'),
        'add_new' => __('Add New', 'agency-core'),
        'add_new_item' => __('Add New Job', 'agency-core'),
        'edit_item' => __('Edit Job', 'agency-core'),
        'new_item' => __('New Job', 'agency-core'),
        'view_item' => __('View Job', 'agency-core'),
        'search_items' => __('Search Jobs', 'agency-core'),
        'not_found' => __('No jobs found', 'agency-core'),
        'not_found_in_trash' => __('No jobs found in trash', 'agency-core'),
        'all_items' => __('All Jobs', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-businessperson',
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'revisions'),
        'rewrite' => array('slug' => 'jobs'),
        'show_in_menu' => true,
        'menu_position' => 28,
        'taxonomies' => array('job_category', 'job_type', 'job_location'),
    );
    
    register_post_type('job', $args);
}
add_action('init', 'agency_core_register_jobs_cpt');


/**
 * Register Job Category Taxonomy
 */
function agency_core_register_job_category_taxonomy() {
    $labels = array(
        'name' => __('Job Categories', 'agency-core'),
        'singular_name' => __('Job Category', 'agency-core'),
        'search_items' => __('Search Job Categories', 'agency-core'),
        'all_items' => __('All Job Categories', 'agency-core'),
        'parent_item' => __('Parent Job Category', 'agency-core'),
        'parent_item_colon' => __('Parent Job Category:', 'agency-core'),
        'edit_item' => __('Edit Job Category', 'agency-core'),
        'update_item' => __('Update Job Category', 'agency-core'),
        'add_new_item' => __('Add New Job Category', 'agency-core'),
        'new_item_name' => __('New Job Category Name', 'agency-core'),
        'menu_name' => __('Categories', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => true,

        'public'              => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'exclude_from_search' => true,

        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'job-category'),
    );
    
    register_taxonomy('job_category', 'job', $args);
}
add_action('init', 'agency_core_register_job_category_taxonomy');


/**
 * Register Job Type Taxonomy
 */
function agency_core_register_job_type_taxonomy() {
    $labels = array(
        'name' => __('Job Types', 'agency-core'),
        'singular_name' => __('Job Type', 'agency-core'),
        'search_items' => __('Search Job Types', 'agency-core'),
        'all_items' => __('All Job Types', 'agency-core'),
        'edit_item' => __('Edit Job Type', 'agency-core'),
        'update_item' => __('Update Job Type', 'agency-core'),
        'add_new_item' => __('Add New Job Type', 'agency-core'),
        'new_item_name' => __('New Job Type Name', 'agency-core'),
        'menu_name' => __('Job Types', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'job-type'),
    );
    
    register_taxonomy('job_type', 'job', $args);
}
add_action('init', 'agency_core_register_job_type_taxonomy');


/**
 * Register Job Location Taxonomy
 */
function agency_core_register_job_location_taxonomy() {
    $labels = array(
        'name' => __('Job Locations', 'agency-core'),
        'singular_name' => __('Job Location', 'agency-core'),
        'search_items' => __('Search Job Locations', 'agency-core'),
        'all_items' => __('All Job Locations', 'agency-core'),
        'edit_item' => __('Edit Job Location', 'agency-core'),
        'update_item' => __('Update Job Location', 'agency-core'),
        'add_new_item' => __('Add New Job Location', 'agency-core'),
        'new_item_name' => __('New Job Location Name', 'agency-core'),
        'menu_name' => __('Locations', 'agency-core'),
    );
    
    $args = array(
        'labels' => $labels,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'job-location'),
    );
    
    register_taxonomy('job_location', 'job', $args);



}
add_action('init', 'agency_core_register_job_location_taxonomy');


/**
 * Register Speisekarte Post Type
 */
function agency_core_register_speisekarte_cpt() {
    $labels = [
        'name'                  => 'Speisekarte',
        'singular_name'         => 'Gericht',
        'menu_name'            => 'Speisekarte',
        'add_new'              => 'Neues Gericht',
        'add_new_item'         => 'Neues Gericht hinzufügen',
        'edit_item'            => 'Gericht bearbeiten',
        'new_item'             => 'Neues Gericht',
        'view_item'            => 'Gericht ansehen',
        'search_items'         => 'Gerichte suchen',
        'not_found'            => 'Keine Gerichte gefunden',
        'not_found_in_trash'   => 'Keine Gerichte im Papierkorb',
    ];

    $args = [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => ['slug' => 'speisekarte'],
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-food',
        'supports'            => ['title', 'editor', 'thumbnail'],
        'show_in_rest'        => true,
    ];

    register_post_type('gericht', $args);
}
add_action('init', 'agency_core_register_speisekarte_cpt');