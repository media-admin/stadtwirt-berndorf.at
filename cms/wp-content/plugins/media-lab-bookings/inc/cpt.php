<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_CPT {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
    }
    public static function register() {
        register_post_type( 'mlb_location', [
            'labels'          => [ 'name' => 'Standorte', 'singular_name' => 'Standort', 'add_new' => 'Hinzufügen', 'add_new_item' => 'Neuen Standort hinzufügen', 'edit_item' => 'Standort bearbeiten', 'not_found' => 'Keine Standorte gefunden' ],
            'public'          => false, 'show_ui' => true, 'show_in_menu' => false,
            'supports'        => [ 'title' ], 'has_archive' => false, 'rewrite' => false, 'capability_type' => 'post',
        ] );
        register_post_type( 'mlb_booking', [
            'labels'          => [ 'name' => 'Buchungen', 'singular_name' => 'Buchung', 'add_new' => 'Hinzufügen', 'add_new_item' => 'Neue Buchung hinzufügen', 'edit_item' => 'Buchung bearbeiten', 'not_found' => 'Keine Buchungen gefunden' ],
            'public'          => false, 'show_ui' => true, 'show_in_menu' => false,
            'supports'        => [ 'title' ], 'has_archive' => false, 'rewrite' => false, 'capability_type' => 'post',
        ] );
    }
    public static function register_statuses() {
        register_post_status( 'mlb-pending',   [ 'label' => 'Ausstehend', 'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Ausstehend <span class="count">(%s)</span>', 'Ausstehend <span class="count">(%s)</span>', 'media-lab-bookings' ) ] );
        register_post_status( 'mlb-confirmed', [ 'label' => 'Bestätigt',  'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Bestätigt <span class="count">(%s)</span>',  'Bestätigt <span class="count">(%s)</span>',  'media-lab-bookings' ) ] );
        register_post_status( 'mlb-cancelled', [ 'label' => 'Storniert',  'public' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => _n_noop( 'Storniert <span class="count">(%s)</span>',  'Storniert <span class="count">(%s)</span>',  'media-lab-bookings' ) ] );
    }
}
MLB_CPT::init();
