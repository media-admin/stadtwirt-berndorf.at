<?php
/**
 * Events Custom Post Type
 */

if (!defined('ABSPATH')) exit;

add_action('init', function() {

    // CPT: Event
    register_post_type('event', array(
        'labels' => array(
            'name'               => 'Events',
            'singular_name'      => 'Event',
            'add_new'            => 'Neu',
            'add_new_item'       => 'Neues Event',
            'edit_item'          => 'Event bearbeiten',
            'view_item'          => 'Event ansehen',
            'search_items'       => 'Events suchen',
            'not_found'          => 'Keine Events gefunden',
            'not_found_in_trash' => 'Keine Events im Papierkorb',
        ),
        'public'            => true,
        'has_archive'       => true,
        'show_in_rest'      => true,
        'menu_icon'         => 'dashicons-calendar-alt',
        'menu_position'     => 6,
        'supports'          => array('title', 'editor', 'thumbnail', 'excerpt'),
        'rewrite'           => array('slug' => 'events'),
    ));

    // Taxonomy: Event Category
    register_taxonomy('event_category', 'event', array(
        'labels' => array(
            'name'          => 'Event-Kategorien',
            'singular_name' => 'Event-Kategorie',
            'add_new_item'  => 'Neue Kategorie',
            'edit_item'     => 'Kategorie bearbeiten',
        ),
        'hierarchical'  => true,
        'public'        => true,
        'show_in_rest'  => true,
        'rewrite'       => array('slug' => 'event-category'),
    ));

});

// Flush rewrite rules on activation
register_activation_hook(MEDIA_LAB_EVENTS_PATH . 'media-lab-events.php', function() {
    flush_rewrite_rules();
});
