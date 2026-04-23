<?php
/**
 * Custom Taxonomies Registration
 * 
 * @package MediaLab_Project
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Taxonomies
 */
function medialab_project_register_taxonomies() {
    
    // Team CPT Category
    register_taxonomy('team_category', 'team', array(
        'labels' => array(
            'name' => 'Bereiche',
            'singular_name' => 'Bereich',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // Service Category
    register_taxonomy('service_category', 'service', array(
        'labels' => array(
            'name' => 'Leistungs-Kategorien',
            'singular_name' => 'Leistungs-Kategorie',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
    
    // FAQ Category
    // register_taxonomy('faq_category', 'faq', array(
    //     'labels' => array(
    //         'name' => 'FAQ Kategorien',
    //         'singular_name' => 'FAQ Kategorie',
    //     ),
    //     'hierarchical' => true,
    //     'public' => false,
    //     'show_in_rest' => true,
    //     'show_admin_column' => true,
    // ));
    
    // // Carousel Category
    // register_taxonomy('carousel_category', 'carousel', array(
    //     'labels' => array(
    //         'name' => 'Karussell Kategorien',
    //         'singular_name' => 'Karussell Kategorie',
    //     ),
    //     'hierarchical' => true,
    //     'public' => false,
    //     'show_in_rest' => true,
    //     'show_admin_column' => true,
    // ));
    
    // // Job Category
    // register_taxonomy('job_category', 'job', array(
    //     'labels' => array(
    //         'name' => 'Job Categories',
    //         'singular_name' => 'Job Category',
    //     ),
    //     'hierarchical' => true,
    //     'public' => true,
    //     'show_in_rest' => true,
    //     'show_admin_column' => true,
    // ));
    
    // // Job Type
    // register_taxonomy('job_type', 'job', array(
    //     'labels' => array(
    //         'name' => 'Job Types',
    //         'singular_name' => 'Job Type',
    //     ),
    //     'hierarchical' => true,
    //     'public' => true,
    //     'show_in_rest' => true,
    //     'show_admin_column' => true,
    // ));
    
    // // Job Location
    // register_taxonomy('job_location', 'job', array(
    //     'labels' => array(
    //         'name' => 'Job Locations',
    //         'singular_name' => 'Job Location',
    //     ),
    //     'hierarchical' => true,
    //     'public' => true,
    //     'show_in_rest' => true,
    //     'show_admin_column' => true,
    // ));

    // Kategorie (hierarchisch wie Kategorien)
    register_taxonomy('gericht_kategorie', 'gericht', [
        'hierarchical'      => true,
        'labels'            => [
            'name'              => 'Kategorien',
            'singular_name'     => 'Kategorie',
            'search_items'      => 'Kategorien suchen',
            'all_items'         => 'Alle Kategorien',
            'parent_item'       => 'Übergeordnete Kategorie',
            'parent_item_colon' => 'Übergeordnete Kategorie:',
            'edit_item'         => 'Kategorie bearbeiten',
            'update_item'       => 'Kategorie aktualisieren',
            'add_new_item'      => 'Neue Kategorie',
            'new_item_name'     => 'Neuer Kategoriename',
            'menu_name'         => 'Kategorien',
        ],
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'kategorie'],
        'show_in_rest'      => true,
    ]);

    // Zutaten (für Suche/Filter)
    register_taxonomy('zutat', 'gericht', [
        'hierarchical'      => false,
        'labels'            => [
            'name'                       => 'Zutaten',
            'singular_name'              => 'Zutat',
            'search_items'               => 'Zutaten suchen',
            'popular_items'              => 'Häufige Zutaten',
            'all_items'                  => 'Alle Zutaten',
            'edit_item'                  => 'Zutat bearbeiten',
            'update_item'                => 'Zutat aktualisieren',
            'add_new_item'               => 'Neue Zutat',
            'new_item_name'              => 'Neue Zutat',
            'separate_items_with_commas' => 'Zutaten mit Komma trennen',
            'add_or_remove_items'        => 'Zutaten hinzufügen oder entfernen',
            'choose_from_most_used'      => 'Aus häufig genutzten wählen',
            'menu_name'                  => 'Zutaten',
        ],
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'zutat'],
        'show_in_rest'      => true,
    ]);

    // Kennzeichnungen (vegan, glutenfrei, etc.)
    register_taxonomy('kennzeichnung', 'gericht', [
        'hierarchical'      => false,
        'labels'            => [
            'name'                       => 'Kennzeichnungen',
            'singular_name'              => 'Kennzeichnung',
            'search_items'               => 'Kennzeichnungen suchen',
            'popular_items'              => 'Häufige Kennzeichnungen',
            'all_items'                  => 'Alle Kennzeichnungen',
            'edit_item'                  => 'Kennzeichnung bearbeiten',
            'update_item'                => 'Kennzeichnung aktualisieren',
            'add_new_item'               => 'Neue Kennzeichnung',
            'new_item_name'              => 'Neue Kennzeichnung',
            'separate_items_with_commas' => 'Kennzeichnungen mit Komma trennen',
            'add_or_remove_items'        => 'Kennzeichnungen hinzufügen oder entfernen',
            'choose_from_most_used'      => 'Aus häufig genutzten wählen',
            'menu_name'                  => 'Kennzeichnungen',
        ],
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'kennzeichnung'],
        'show_in_rest'      => true,
        'public'              => true,
        'publicly_queryable'  => false,
        'has_archive'         => false,
        'exclude_from_search' => true,

    ]);
}
add_action('init', 'medialab_project_register_taxonomies');
