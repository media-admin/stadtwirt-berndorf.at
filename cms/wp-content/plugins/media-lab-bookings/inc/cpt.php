<?php
/**
 * Custom Post Types
 *
 * mlb_location  – Standorte / Filialen
 * mlb_booking   – Buchungen
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_CPT {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_statuses' ] );
    }

    // ── Post Types ─────────────────────────────────────────────────────────────

    public static function register() {

        // Standorte
        register_post_type( 'mlb_location', [
            'labels' => [
                'name'               => 'Standorte',
                'singular_name'      => 'Standort',
                'add_new'            => 'Hinzufügen',
                'add_new_item'       => 'Neuen Standort hinzufügen',
                'edit_item'          => 'Standort bearbeiten',
                'new_item'           => 'Neuer Standort',
                'search_items'       => 'Standort suchen',
                'not_found'          => 'Keine Standorte gefunden',
                'not_found_in_trash' => 'Keine Standorte im Papierkorb',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'mlb-bookings',
            'supports'        => [ 'title' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'menu_icon'       => 'dashicons-location',
        ] );

        // Buchungen
        register_post_type( 'mlb_booking', [
            'labels' => [
                'name'               => 'Buchungen',
                'singular_name'      => 'Buchung',
                'add_new'            => 'Hinzufügen',
                'add_new_item'       => 'Neue Buchung hinzufügen',
                'edit_item'          => 'Buchung bearbeiten',
                'new_item'           => 'Neue Buchung',
                'search_items'       => 'Buchung suchen',
                'not_found'          => 'Keine Buchungen gefunden',
                'not_found_in_trash' => 'Keine Buchungen im Papierkorb',
            ],
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'mlb-bookings',
            'supports'        => [ 'title' ],
            'has_archive'     => false,
            'rewrite'         => false,
            'capability_type' => 'post',
            'menu_icon'       => 'dashicons-calendar-alt',
        ] );
    }

    // ── Custom Post Statuses ───────────────────────────────────────────────────

    public static function register_statuses() {

        register_post_status( 'mlb-pending', [
            'label'                     => _x( 'Ausstehend', 'post status', 'media-lab-bookings' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of pending bookings */
            'label_count'               => _n_noop( 'Ausstehend <span class="count">(%s)</span>', 'Ausstehend <span class="count">(%s)</span>', 'media-lab-bookings' ),
        ] );

        register_post_status( 'mlb-confirmed', [
            'label'                     => _x( 'Bestätigt', 'post status', 'media-lab-bookings' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Bestätigt <span class="count">(%s)</span>', 'Bestätigt <span class="count">(%s)</span>', 'media-lab-bookings' ),
        ] );

        register_post_status( 'mlb-cancelled', [
            'label'                     => _x( 'Storniert', 'post status', 'media-lab-bookings' ),
            'public'                    => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Storniert <span class="count">(%s)</span>', 'Storniert <span class="count">(%s)</span>', 'media-lab-bookings' ),
        ] );
    }
}

MLB_CPT::init();
