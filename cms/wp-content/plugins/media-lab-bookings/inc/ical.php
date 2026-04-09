<?php
/**
 * iCal Generator
 *
 * Erstellt RFC 5545-konforme .ics-Dateien für Buchungen.
 * Wird verwendet für:
 *   - E-Mail-Anhang in Bestätigungs- und Status-Mails
 *   - Download-Link nach Formular-Submit (AJAX-Endpunkt)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_ICal {

    // ── .ics-String für eine Buchung generieren ───────────────────────────────

    public static function generate( int $booking_id ): string {
        $location_id  = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $date         = get_post_meta( $booking_id, 'mlb_booking_date', true ); // Y-m-d
        $time         = get_post_meta( $booking_id, 'mlb_booking_time', true ); // H:i
        $name         = get_post_meta( $booking_id, 'mlb_booking_name',    true );
        $service      = get_post_meta( $booking_id, 'mlb_booking_service', true );
        $persons      = get_post_meta( $booking_id, 'mlb_booking_persons', true );
        $notes        = get_post_meta( $booking_id, 'mlb_booking_notes',   true );
        $slot_minutes = (int) ( get_field( 'mlb_slot_duration', $location_id ) ?: 60 );

        $location_name    = get_the_title( $location_id );
        $location_address = get_field( 'mlb_location_address', $location_id ) ?: '';

        // Timestamps
        $tz        = wp_timezone();
        $dt_start  = new DateTime( $date . ' ' . $time, $tz );
        $dt_end    = clone $dt_start;
        $dt_end->modify( "+{$slot_minutes} minutes" );
        $dt_stamp  = new DateTime( 'now', $tz );

        $dtstart  = $dt_start->format( 'Ymd\THis' );
        $dtend    = $dt_end->format( 'Ymd\THis' );
        $dtstamp  = $dt_stamp->format( 'Ymd\THis' );
        $tzid     = $tz->getName();

        // Summary + Description aufbauen
        $summary = $service
            ? $service . ' – ' . $location_name
            : 'Buchung – ' . $location_name;

        $description_parts = [
            'Buchungsnummer: #' . $booking_id,
            'Name: ' . $name,
        ];
        if ( $service ) $description_parts[] = 'Dienstleistung: ' . $service;
        if ( $persons ) $description_parts[] = 'Personen: ' . $persons;
        if ( $notes )   $description_parts[] = 'Anmerkungen: ' . $notes;

        $description = implode( '\n', $description_parts );
        $location    = self::escape( $location_name . ( $location_address ? ', ' . preg_replace( '/\r?\n/', ', ', $location_address ) : '' ) );
        $uid         = 'mlb-' . $booking_id . '-' . md5( $date . $time . $location_id ) . '@' . parse_url( home_url(), PHP_URL_HOST );

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Media Lab Bookings//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:'         . $uid,
            'DTSTAMP;TZID=' . $tzid . ':' . $dtstamp,
            'DTSTART;TZID=' . $tzid . ':' . $dtstart,
            'DTEND;TZID='   . $tzid . ':' . $dtend,
            'SUMMARY:'     . self::escape( $summary ),
            'DESCRIPTION:' . self::escape( $description ),
            'LOCATION:'    . $location,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode( "\r\n", $lines ) . "\r\n";
    }

    // ── Als Datei-Anhang Array (für wp_mail) ──────────────────────────────────

    public static function attachment( int $booking_id ): array {
        $ics_content = self::generate( $booking_id );
        $filename    = 'buchung-' . $booking_id . '.ics';
        $tmp_path    = trailingslashit( sys_get_temp_dir() ) . $filename;

        file_put_contents( $tmp_path, $ics_content );

        return [ $tmp_path ];
    }

    // ── Download-URL für Frontend ─────────────────────────────────────────────

    public static function download_url( int $booking_id ): string {
        return add_query_arg( [
            'action'     => 'mlb_download_ical',
            'booking_id' => $booking_id,
            'nonce'      => wp_create_nonce( 'mlb_ical_' . $booking_id ),
        ], admin_url( 'admin-ajax.php' ) );
    }

    // ── AJAX: iCal herunterladen ──────────────────────────────────────────────

    public static function ajax_download(): void {
        $booking_id = (int) sanitize_text_field( $_GET['booking_id'] ?? 0 );
        $nonce      = sanitize_text_field( $_GET['nonce'] ?? '' );

        if ( ! $booking_id || ! wp_verify_nonce( $nonce, 'mlb_ical_' . $booking_id ) ) {
            wp_die( 'Ungültiger Link.', 403 );
        }

        if ( get_post_type( $booking_id ) !== 'mlb_booking' ) {
            wp_die( 'Buchung nicht gefunden.', 404 );
        }

        $ics      = self::generate( $booking_id );
        $filename = 'buchung-' . $booking_id . '.ics';

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        echo $ics;
        exit;
    }

    // ── Sonderzeichen escapen (RFC 5545) ──────────────────────────────────────

    private static function escape( string $text ): string {
        $text = strip_tags( $text );
        $text = str_replace( [ '\\', ';', ',', "\n" ], [ '\\\\', '\;', '\,', '\n' ], $text );
        return $text;
    }
}

// AJAX-Endpunkt registrieren (für eingeloggte + nicht eingeloggte Nutzer)
add_action( 'wp_ajax_mlb_download_ical',        [ 'MLB_ICal', 'ajax_download' ] );
add_action( 'wp_ajax_nopriv_mlb_download_ical', [ 'MLB_ICal', 'ajax_download' ] );
