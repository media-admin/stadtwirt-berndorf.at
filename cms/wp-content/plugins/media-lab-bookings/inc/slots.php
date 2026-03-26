<?php
/**
 * Slot-Logik
 *
 * - Öffnungszeiten pro Wochentag auslesen
 * - Slots dynamisch generieren (Start bis Ende minus Offset)
 * - Kapazität gegen bestehende Buchungen prüfen
 * - Hilfsmethoden für Frontend (offene Wochentage, Mindestdatum)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Slots {

    // Tag-Kürzel → PHP date('D') → JS-Wochentag (0 = Sonntag)
    private static $day_map = [
        'mon' => [ 'php' => 'Mon', 'js' => 1 ],
        'tue' => [ 'php' => 'Tue', 'js' => 2 ],
        'wed' => [ 'php' => 'Wed', 'js' => 3 ],
        'thu' => [ 'php' => 'Thu', 'js' => 4 ],
        'fri' => [ 'php' => 'Fri', 'js' => 5 ],
        'sat' => [ 'php' => 'Sat', 'js' => 6 ],
        'sun' => [ 'php' => 'Sun', 'js' => 0 ],
    ];

    // ── Öffnungszeiten für einen Wochentag ────────────────────────────────────

    public static function get_day_hours( int $location_id, string $php_day_short ): ?array {
        $key = null;
        foreach ( self::$day_map as $k => $v ) {
            if ( $v['php'] === $php_day_short ) { $key = $k; break; }
        }
        if ( ! $key ) return null;

        $active = get_field( "mlb_{$key}_active", $location_id );
        if ( ! $active ) return null;

        return [
            'open'  => get_field( "mlb_{$key}_open",  $location_id ),
            'close' => get_field( "mlb_{$key}_close", $location_id ),
        ];
    }

    // ── Alle offenen JS-Wochentage (für Flatpickr) ────────────────────────────

    public static function get_open_weekdays( int $location_id ): array {
        $open = [];
        foreach ( self::$day_map as $key => $v ) {
            if ( get_field( "mlb_{$key}_active", $location_id ) ) {
                $open[] = $v['js'];
            }
        }
        return $open;
    }

    // ── Slots für Standort + Datum generieren ─────────────────────────────────

    public static function generate( int $location_id, string $date ): array {
        $php_day = date( 'D', strtotime( $date ) ); // e.g. 'Mon'
        $hours   = self::get_day_hours( $location_id, $php_day );

        if ( ! $hours ) return []; // Geschlossen

        $slot_duration = (int) ( get_field( 'mlb_slot_duration',      $location_id ) ?: 60 );
        $last_offset   = (int) ( get_field( 'mlb_last_slot_offset',   $location_id ) ?: 0  );
        $max_capacity  = (int) ( get_field( 'mlb_max_capacity',       $location_id ) ?: 1  );

        $open_ts  = strtotime( $date . ' ' . $hours['open'] );
        $close_ts = strtotime( $date . ' ' . $hours['close'] ) - ( $last_offset * 60 );
        $slot_sec = $slot_duration * 60;

        if ( $open_ts >= $close_ts ) return [];

        $slots   = [];
        $current = $open_ts;

        while ( $current <= $close_ts ) {
            $time_str = date( 'H:i', $current );
            $booked   = self::count_bookings( $location_id, $date, $time_str );
            $remaining = max( 0, $max_capacity - $booked );

            $slots[] = [
                'time'      => $time_str,
                'label'     => $time_str . ' Uhr',
                'available' => $remaining > 0,
                'remaining' => $remaining,
            ];

            $current += $slot_sec;
        }

        return $slots;
    }

    // ── Bestehende Buchungen für einen Slot zählen ────────────────────────────

    public static function count_bookings( int $location_id, string $date, string $time ): int {
        $query = new WP_Query( [
            'post_type'      => 'mlb_booking',
            'post_status'    => [ 'publish', 'mlb-pending', 'mlb-confirmed' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'mlb_booking_location', 'value' => $location_id, 'compare' => '=' ],
                [ 'key' => 'mlb_booking_date',     'value' => $date,        'compare' => '=' ],
                [ 'key' => 'mlb_booking_time',     'value' => $time,        'compare' => '=' ],
                [ 'key' => 'mlb_booking_status',   'value' => 'mlb-cancelled', 'compare' => '!=' ],
            ],
        ] );
        return (int) $query->found_posts;
    }

    // ── Prüfen ob Datum offen ist ─────────────────────────────────────────────

    public static function is_date_open( int $location_id, string $date ): bool {
        $php_day = date( 'D', strtotime( $date ) );
        return self::get_day_hours( $location_id, $php_day ) !== null;
    }
}
