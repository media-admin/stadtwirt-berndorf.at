<?php
/**
 * AJAX Handler
 *
 * mlb_get_location_data  – Offene Wochentage + Dienstleistungen für Standort
 * mlb_get_slots          – Verfügbare Zeitslots für Standort + Datum
 * mlb_submit_booking     – Buchung speichern + E-Mails versenden
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Ajax {

    public static function init() {
        $actions = [
            'mlb_get_location_data',
            'mlb_get_slots',
            'mlb_submit_booking',
        ];

        foreach ( $actions as $action ) {
            $method = str_replace( 'mlb_', '', $action );
            add_action( "wp_ajax_{$action}",        [ __CLASS__, $method ] );
            add_action( "wp_ajax_nopriv_{$action}", [ __CLASS__, $method ] );
        }
    }

    // ── 1. Standortdaten (Wochentage + Services) ──────────────────────────────

    public static function get_location_data() {
        check_ajax_referer( 'mlb_nonce', 'nonce' );

        $location_id = (int) sanitize_text_field( $_POST['location_id'] ?? 0 );
        if ( ! $location_id || get_post_type( $location_id ) !== 'mlb_location' ) {
            wp_send_json_error( [ 'message' => 'Ungültiger Standort.' ] );
        }

        // Offene Wochentage für Flatpickr
        $open_weekdays = MLB_Slots::get_open_weekdays( $location_id );

        // Dienstleistungen
        $services_raw = get_field( 'mlb_services', $location_id ) ?: [];
        $services     = [];
        foreach ( $services_raw as $s ) {
            if ( ! empty( $s['service_name'] ) ) {
                $services[] = [
                    'name'     => sanitize_text_field( $s['service_name'] ),
                    'duration' => isset( $s['service_duration'] ) ? (int) $s['service_duration'] : null,
                ];
            }
        }

        wp_send_json_success( [
            'open_weekdays' => $open_weekdays,
            'services'      => $services,
        ] );
    }

    // ── 2. Zeitslots für Datum ────────────────────────────────────────────────

    public static function get_slots() {
        check_ajax_referer( 'mlb_nonce', 'nonce' );

        $location_id = (int) sanitize_text_field( $_POST['location_id'] ?? 0 );
        $date        = sanitize_text_field( $_POST['date'] ?? '' );

        if ( ! $location_id || get_post_type( $location_id ) !== 'mlb_location' ) {
            wp_send_json_error( [ 'message' => 'Ungültiger Standort.' ] );
        }

        if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiges Datum.' ] );
        }

        // Datum in Vergangenheit ablehnen
        if ( strtotime( $date ) < strtotime( 'today' ) ) {
            wp_send_json_error( [ 'message' => 'Datum liegt in der Vergangenheit.' ] );
        }

        if ( ! MLB_Slots::is_date_open( $location_id, $date ) ) {
            wp_send_json_success( [ 'slots' => [], 'message' => 'An diesem Tag ist der Standort geschlossen.' ] );
        }

        $slots = MLB_Slots::generate( $location_id, $date );

        wp_send_json_success( [ 'slots' => $slots ] );
    }

    // ── 3. Buchung einreichen ─────────────────────────────────────────────────

    public static function submit_booking() {
        check_ajax_referer( 'mlb_nonce', 'nonce' );

        // Validierung ──────────────────────────────────────────────────────────
        $required = [ 'location_id', 'date', 'time', 'name', 'email', 'persons' ];
        foreach ( $required as $field ) {
            if ( empty( $_POST[ $field ] ) ) {
                /* translators: %s: field name */
                wp_send_json_error( [ 'message' => sprintf( 'Pflichtfeld fehlt: %s', $field ) ] );
            }
        }

        // DSGVO-Zustimmung serverseitig prüfen
        if ( empty( $_POST['privacy_consent'] ) || $_POST['privacy_consent'] !== '1' ) {
            wp_send_json_error( [ 'message' => 'Bitte stimmen Sie der Datenschutzerklärung zu, um fortzufahren.' ] );
        }

        $location_id = (int) sanitize_text_field( $_POST['location_id'] );
        $date        = sanitize_text_field( $_POST['date'] );
        $time        = sanitize_text_field( $_POST['time'] );
        $name        = sanitize_text_field( $_POST['name'] );
        $email       = sanitize_email( $_POST['email'] );
        $phone       = sanitize_text_field( $_POST['phone']    ?? '' );
        $service     = sanitize_text_field( $_POST['service']  ?? '' );
        $persons     = max( 1, (int) $_POST['persons'] );
        $notes       = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Ungültige E-Mail-Adresse.' ] );
        }

        if ( get_post_type( $location_id ) !== 'mlb_location' ) {
            wp_send_json_error( [ 'message' => 'Ungültiger Standort.' ] );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiges Datumsformat.' ] );
        }

        if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiges Uhrzeitformat.' ] );
        }

        // Datum offen?
        if ( ! MLB_Slots::is_date_open( $location_id, $date ) ) {
            wp_send_json_error( [ 'message' => 'Der Standort ist an diesem Tag geschlossen.' ] );
        }

        // Slot gültig + Kapazität prüfen ──────────────────────────────────────
        $slots          = MLB_Slots::generate( $location_id, $date );
        $slot_times     = array_column( $slots, 'time' );
        $available_slot = null;

        foreach ( $slots as $slot ) {
            if ( $slot['time'] === $time ) {
                $available_slot = $slot;
                break;
            }
        }

        if ( ! $available_slot ) {
            wp_send_json_error( [ 'message' => 'Der gewählte Zeitslot ist nicht verfügbar.' ] );
        }

        if ( ! $available_slot['available'] ) {
            wp_send_json_error( [ 'message' => 'Der gewählte Zeitslot ist ausgebucht.' ] );
        }

        // Buchung speichern ────────────────────────────────────────────────────
        $location_name = get_the_title( $location_id );
        $post_title    = sprintf(
            'Buchung – %s – %s %s Uhr',
            $name,
            date_i18n( 'd.m.Y', strtotime( $date ) ),
            $time
        );

        $booking_id = wp_insert_post( [
            'post_type'   => 'mlb_booking',
            'post_status' => 'mlb-pending',
            'post_title'  => sanitize_text_field( $post_title ),
        ] );

        if ( is_wp_error( $booking_id ) ) {
            wp_send_json_error( [ 'message' => 'Buchung konnte nicht gespeichert werden.' ] );
        }

        // ACF-Felder setzen
        update_field( 'mlb_booking_status',   'mlb-pending', $booking_id );
        update_field( 'mlb_booking_location', $location_id,  $booking_id );
        update_field( 'mlb_booking_date',     $date,         $booking_id );
        update_field( 'mlb_booking_time',     $time,         $booking_id );
        update_field( 'mlb_booking_service',  $service,      $booking_id );
        update_field( 'mlb_booking_persons',  $persons,      $booking_id );
        update_field( 'mlb_booking_name',     $name,         $booking_id );
        update_field( 'mlb_booking_email',    $email,        $booking_id );
        update_field( 'mlb_booking_phone',    $phone,        $booking_id );
        update_field( 'mlb_booking_notes',    $notes,        $booking_id );

        // E-Mails versenden ────────────────────────────────────────────────────
        MLB_Mail::send_confirmation( $booking_id );

        wp_send_json_success( [
            'message'    => 'Ihre Buchung wurde erfolgreich eingereicht. Sie erhalten in Kürze eine Bestätigung per E-Mail.',
            'booking_id' => $booking_id,
        ] );
    }
}

MLB_Ajax::init();
