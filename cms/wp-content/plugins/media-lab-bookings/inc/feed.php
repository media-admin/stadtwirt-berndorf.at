<?php
/**
 * iCal-Feed (Kalender-Abonnement)
 *
 * Öffentliche URL die alle Buchungen als iCal-Feed ausgibt.
 * Kann von Google Calendar, Apple Calendar, Outlook etc. abonniert werden.
 *
 * URL-Format:
 *   /mlb-calendar-feed/                          → alle Standorte, alle Status
 *   /mlb-calendar-feed/?location=42              → nur Standort ID 42
 *   /mlb-calendar-feed/?location=wien-mitte      → nur Standort Slug
 *   /mlb-calendar-feed/?status=confirmed         → nur bestätigte Buchungen
 *   /mlb-calendar-feed/?token=abc123             → mit privatem Token (empfohlen)
 *
 * Feed-URL abrufbar im Backend: Bookings → Übersicht
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Feed {

    const FEED_SLUG = 'mlb-calendar-feed';

    public static function init(): void {
        add_action( 'init',              [ __CLASS__, 'register_rewrite' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_feed' ] );
        add_filter( 'query_vars',        [ __CLASS__, 'add_query_vars' ] );

        // Feed-Token bei Plugin-Aktivierung generieren
        if ( ! get_option( 'mlb_feed_token' ) ) {
            update_option( 'mlb_feed_token', bin2hex( random_bytes( 16 ) ) );
        }
    }

    // ── Rewrite-Regel ─────────────────────────────────────────────────────────

    public static function register_rewrite(): void {
        add_rewrite_rule(
            '^' . self::FEED_SLUG . '/?$',
            'index.php?mlb_feed=1',
            'top'
        );
    }

    public static function add_query_vars( array $vars ): array {
        $vars[] = 'mlb_feed';
        return $vars;
    }

    // ── Feed ausgeben ─────────────────────────────────────────────────────────

    public static function handle_feed(): void {
        if ( ! get_query_var( 'mlb_feed' ) ) return;

        // Optionaler Token-Schutz
        $saved_token = get_option( 'mlb_feed_token', '' );
        $given_token = sanitize_text_field( $_GET['token'] ?? '' );

        // Nur prüfen wenn Token-Schutz aktiv (Option gesetzt)
        if ( get_option( 'mlb_feed_protected', 0 ) && ! hash_equals( $saved_token, $given_token ) ) {
            status_header( 403 );
            exit( 'Ungültiger Token.' );
        }

        // Parameter
        $location_param = sanitize_text_field( $_GET['location'] ?? '' );
        $status_filter  = sanitize_text_field( $_GET['status']   ?? '' );
        $location_id    = 0;

        if ( $location_param ) {
            if ( is_numeric( $location_param ) ) {
                $location_id = (int) $location_param;
            } else {
                $loc = get_page_by_path( $location_param, OBJECT, 'mlb_location' );
                if ( $loc ) $location_id = $loc->ID;
            }
        }

        // Buchungen laden
        $post_statuses = [ 'publish', 'mlb-pending', 'mlb-confirmed', 'mlb-cancelled' ];
        $meta_query    = [];

        if ( $location_id ) {
            $meta_query[] = [ 'key' => 'mlb_booking_location', 'value' => $location_id ];
        }

        if ( $status_filter ) {
            $meta_query[] = [ 'key' => 'mlb_booking_status', 'value' => 'mlb-' . $status_filter ];
        } else {
            // Standard: nur pending + confirmed (keine Stornierungen)
            $meta_query[] = [ 'key' => 'mlb_booking_status', 'value' => 'mlb-cancelled', 'compare' => '!=' ];
        }

        $bookings = get_posts( [
            'post_type'      => 'mlb_booking',
            'post_status'    => $post_statuses,
            'posts_per_page' => -1,
            'meta_query'     => $meta_query ?: null,
            'orderby'        => 'meta_value',
            'meta_key'       => 'mlb_booking_date',
            'order'          => 'ASC',
        ] );

        // iCal ausgeben
        $site_name = get_bloginfo( 'name' );
        $host      = parse_url( home_url(), PHP_URL_HOST );
        $tz        = wp_timezone();
        $tz_name   = $tz->getName();
        $now       = ( new DateTime( 'now', $tz ) )->format( 'Ymd\THis' );

        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="bookings.ics"' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//Media Lab Bookings//{$site_name}//DE\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "METHOD:PUBLISH\r\n";
        echo "X-WR-CALNAME:" . self::escape( $site_name . ' – Buchungen' ) . "\r\n";
        echo "X-WR-TIMEZONE:{$tz_name}\r\n";
        echo "REFRESH-INTERVAL;VALUE=DURATION:PT1H\r\n";

        foreach ( $bookings as $booking ) {
            $bid          = $booking->ID;
            $loc_id       = (int) get_post_meta( $bid, 'mlb_booking_location', true );
            $date         = get_post_meta( $bid, 'mlb_booking_date', true );
            $time         = get_post_meta( $bid, 'mlb_booking_time', true );
            $name         = get_post_meta( $bid, 'mlb_booking_name',    true );
            $service      = get_post_meta( $bid, 'mlb_booking_service', true );
            $persons      = get_post_meta( $bid, 'mlb_booking_persons', true );
            $notes        = get_post_meta( $bid, 'mlb_booking_notes',   true );
            $status       = get_post_meta( $bid, 'mlb_booking_status',  true ) ?: 'mlb-pending';
            $slot_minutes = (int) ( get_field( 'mlb_slot_duration', $loc_id ) ?: 60 );
            $loc_name     = get_the_title( $loc_id );
            $loc_address  = get_field( 'mlb_location_address', $loc_id ) ?: '';

            if ( ! $date || ! $time ) continue;

            $dt_start = new DateTime( $date . ' ' . $time, $tz );
            $dt_end   = clone $dt_start;
            $dt_end->modify( "+{$slot_minutes} minutes" );

            $dtstart  = $dt_start->format( 'Ymd\THis' );
            $dtend    = $dt_end->format( 'Ymd\THis' );

            $summary = $service ? $service . ' – ' . $name : 'Buchung – ' . $name;
            if ( $loc_name ) $summary .= ' (' . $loc_name . ')';

            $desc_parts = [ 'Buchung #' . $bid, 'Kunde: ' . $name ];
            if ( $service ) $desc_parts[] = 'Service: ' . $service;
            if ( $persons ) $desc_parts[] = 'Personen: ' . $persons;
            if ( $notes )   $desc_parts[] = 'Anmerkungen: ' . $notes;
            $description = implode( '\n', $desc_parts );

            $cal_status = $status === 'mlb-cancelled' ? 'CANCELLED' : 'CONFIRMED';
            $location   = self::escape( $loc_name . ( $loc_address ? ', ' . preg_replace( '/\r?\n/', ', ', $loc_address ) : '' ) );
            $uid        = 'mlb-' . $bid . '@' . $host;

            echo "BEGIN:VEVENT\r\n";
            echo "UID:{$uid}\r\n";
            echo "DTSTAMP;TZID={$tz_name}:{$now}\r\n";
            echo "DTSTART;TZID={$tz_name}:{$dtstart}\r\n";
            echo "DTEND;TZID={$tz_name}:{$dtend}\r\n";
            echo "SUMMARY:" . self::escape( $summary ) . "\r\n";
            echo "DESCRIPTION:" . self::escape( $description ) . "\r\n";
            echo "LOCATION:{$location}\r\n";
            echo "STATUS:{$cal_status}\r\n";
            echo "END:VEVENT\r\n";
        }

        echo "END:VCALENDAR\r\n";
        exit;
    }

    // ── Feed-URL für Backend-Anzeige ──────────────────────────────────────────

    public static function get_feed_url( int $location_id = 0, bool $with_token = true ): string {
        $args = [];
        if ( $location_id ) $args['location'] = $location_id;
        if ( $with_token )  $args['token']    = get_option( 'mlb_feed_token', '' );
        return add_query_arg( $args, home_url( '/' . self::FEED_SLUG . '/' ) );
    }

    // ── RFC 5545 Escape ───────────────────────────────────────────────────────

    private static function escape( string $text ): string {
        $text = strip_tags( $text );
        return str_replace( [ '\\', ';', ',', "\n" ], [ '\\\\', '\;', '\,', '\n' ], $text );
    }
}

MLB_Feed::init();
