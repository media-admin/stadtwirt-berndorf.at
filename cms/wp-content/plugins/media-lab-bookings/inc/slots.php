<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Slots {
    private static $day_map = [
        'mon' => [ 'php' => 'Mon', 'js' => 1 ], 'tue' => [ 'php' => 'Tue', 'js' => 2 ],
        'wed' => [ 'php' => 'Wed', 'js' => 3 ], 'thu' => [ 'php' => 'Thu', 'js' => 4 ],
        'fri' => [ 'php' => 'Fri', 'js' => 5 ], 'sat' => [ 'php' => 'Sat', 'js' => 6 ],
        'sun' => [ 'php' => 'Sun', 'js' => 0 ],
    ];

    public static function get_day_hours( int $location_id, string $php_day_short ): ?array {
        $key = null;
        foreach ( self::$day_map as $k => $v ) { if ( $v['php'] === $php_day_short ) { $key = $k; break; } }
        if ( ! $key ) return null;
        $active = get_field( "mlb_{$key}_active", $location_id );
        if ( ! $active ) return null;
        return [ 'open' => get_field( "mlb_{$key}_open", $location_id ), 'close' => get_field( "mlb_{$key}_close", $location_id ) ];
    }

    public static function get_open_weekdays( int $location_id ): array {
        $open = [];
        foreach ( self::$day_map as $key => $v ) { if ( get_field( "mlb_{$key}_active", $location_id ) ) { $open[] = $v['js']; } }
        return $open;
    }

    public static function generate( int $location_id, string $date ): array {
        // Ganztags-Sperre prüfen
        if ( self::is_day_blocked( $location_id, $date ) ) return [];

        $php_day      = date( 'D', strtotime( $date ) );
        $hours        = self::get_day_hours( $location_id, $php_day );
        if ( ! $hours ) return [];
        $slot_duration = (int) ( get_field( 'mlb_slot_duration',    $location_id ) ?: 60 );
        $last_offset   = (int) ( get_field( 'mlb_last_slot_offset', $location_id ) ?: 0  );
        $max_capacity  = (int) ( get_field( 'mlb_max_capacity',     $location_id ) ?: 1  );
        $open_ts  = strtotime( $date . ' ' . $hours['open'] );
        $close_ts = strtotime( $date . ' ' . $hours['close'] ) - ( $last_offset * 60 );
        $slot_sec = $slot_duration * 60;
        if ( $open_ts >= $close_ts ) return [];
        // Tageslimit prüfen
        $max_per_day = (int) ( get_field( 'mlb_max_per_day', $location_id ) ?: 0 );
        if ( $max_per_day > 0 && self::count_day_bookings( $location_id, $date ) >= $max_per_day ) {
            return []; // Tag ausgebucht
        }

        // Geblockte Zeitfenster laden
        $blocked_ranges = self::get_blocked_time_ranges( $location_id, $date );

        $slots = []; $current = $open_ts;
        while ( $current <= $close_ts ) {
            $time_str  = date( 'H:i', $current );

            // Liegt Slot in einem geblockten Zeitfenster?
            $blocked_reason = '';
            foreach ( $blocked_ranges as $range ) {
                if ( $time_str >= $range['time_from'] && $time_str < $range['time_to'] ) {
                    $blocked_reason = $range['label'] ?: 'Gesperrt';
                    break;
                }
            }

            if ( $blocked_reason ) {
                $slots[] = [ 'time' => $time_str, 'label' => $time_str . ' Uhr', 'available' => false, 'remaining' => 0, 'blocked_reason' => $blocked_reason ];
            } else {
                $booked    = self::count_bookings( $location_id, $date, $time_str );
                $remaining = max( 0, $max_capacity - $booked );
                $slots[]   = [ 'time' => $time_str, 'label' => $time_str . ' Uhr', 'available' => $remaining > 0, 'remaining' => $remaining, 'blocked_reason' => '' ];
            }
            $current  += $slot_sec;
        }
        return $slots;
    }

    public static function count_bookings( int $location_id, string $date, string $time ): int {
        $query = new WP_Query( [
            'post_type' => 'mlb_booking', 'post_status' => [ 'publish', 'mlb-pending', 'mlb-confirmed' ],
            'posts_per_page' => -1, 'fields' => 'ids',
            'meta_query' => [ 'relation' => 'AND',
                [ 'key' => 'mlb_booking_location', 'value' => $location_id ],
                [ 'key' => 'mlb_booking_date',     'value' => $date ],
                [ 'key' => 'mlb_booking_time',     'value' => $time ],
                [ 'key' => 'mlb_booking_status',   'value' => 'mlb-cancelled', 'compare' => '!=' ],
            ],
        ] );
        return (int) $query->found_posts;
    }

    public static function count_day_bookings( int $location_id, string $date ): int {
        $query = new WP_Query( [
            'post_type'      => 'mlb_booking',
            'post_status'    => [ 'publish', 'mlb-pending', 'mlb-confirmed' ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'mlb_booking_location', 'value' => $location_id ],
                [ 'key' => 'mlb_booking_date',     'value' => $date ],
                [ 'key' => 'mlb_booking_status',   'value' => 'mlb-cancelled', 'compare' => '!=' ],
            ],
        ] );
        return (int) $query->found_posts;
    }

    public static function is_date_open( int $location_id, string $date ): bool {
        $php_day = date( 'D', strtotime( $date ) );
        if ( ! self::get_day_hours( $location_id, $php_day ) ) return false;
        if ( self::is_day_blocked( $location_id, $date ) )     return false;
        return true;
    }

    // ── Sperren: Hilfsmethoden ────────────────────────────────────────────────

    private static function get_blocked_rows( int $location_id, int $year = 0 ): array {
        if ( ! function_exists( 'get_field' ) ) return [];
        $rows = get_field( 'mlb_blocked_periods', $location_id );
        if ( ! is_array( $rows ) || empty( $rows ) ) return [];
        if ( ! $year ) $year = (int) date( 'Y' );
        $result = [];
        foreach ( $rows as $row ) {
            $date_from = $row['blocked_date_from'] ?? '';
            if ( empty( $date_from ) ) continue;
            $from_ts = strtotime( $date_from );
            if ( ! $from_ts ) continue;
            $date_to = $row['blocked_date_to'] ?? '';
            $yearly  = ! empty( $row['blocked_yearly'] );
            if ( $yearly ) {
                $date_from = date( $year . '-m-d', $from_ts );
                if ( ! empty( $date_to ) ) {
                    $to_ts     = strtotime( $date_to );
                    $diff_days = $to_ts ? (int) round( ( $to_ts - $from_ts ) / 86400 ) : 0;
                    $nf_ts     = strtotime( $date_from );
                    $date_to   = $diff_days > 0 && $nf_ts ? date( 'Y-m-d', $nf_ts + $diff_days * 86400 ) : '';
                }
            }
            $result[] = [
                'type'      => $row['blocked_type']      ?? 'day',
                'date_from' => $date_from,
                'date_to'   => $date_to,
                'time_from' => $row['blocked_time_from'] ?? '',
                'time_to'   => $row['blocked_time_to']   ?? '',
                'label'     => $row['blocked_label']     ?? '',
                'yearly'    => $yearly,
            ];
        }
        return $result;
    }

    public static function is_day_blocked( int $location_id, string $date ): bool {
        $year = (int) date( 'Y', strtotime( $date ) );
        foreach ( self::get_blocked_rows( $location_id, $year ) as $row ) {
            if ( $row['type'] !== 'day' ) continue;
            $from = $row['date_from'];
            $to   = ! empty( $row['date_to'] ) ? $row['date_to'] : $from;
            if ( $date >= $from && $date <= $to ) return true;
        }
        return false;
    }

    public static function get_blocked_time_ranges( int $location_id, string $date ): array {
        $year    = (int) date( 'Y', strtotime( $date ) );
        $blocked = [];
        foreach ( self::get_blocked_rows( $location_id, $year ) as $row ) {
            if ( $row['type'] !== 'timerange' ) continue;
            if ( empty( $row['time_from'] ) || empty( $row['time_to'] ) ) continue;
            $from = $row['date_from'];
            $to   = ! empty( $row['date_to'] ) ? $row['date_to'] : $from;
            if ( $date >= $from && $date <= $to ) {
                $blocked[] = [ 'time_from' => $row['time_from'], 'time_to' => $row['time_to'], 'label' => $row['label'] ];
            }
        }
        return $blocked;
    }

    public static function get_blocked_dates_for_range( int $location_id, string $date_from, string $date_to ): array {
        $year_from = (int) date( 'Y', strtotime( $date_from ) );
        $year_to   = (int) date( 'Y', strtotime( $date_to   ) );
        $all = [];
        for ( $y = $year_from; $y <= $year_to; $y++ ) {
            foreach ( self::get_blocked_rows( $location_id, $y ) as $row ) {
                if ( $row['type'] !== 'day' ) continue;
                $from = $row['date_from'];
                $to   = ! empty( $row['date_to'] ) ? $row['date_to'] : $from;
                if ( $to < $date_from || $from > $date_to ) continue;
                $all[] = [ 'from' => $from, 'to' => $to, 'label' => $row['label'] ];
            }
        }
        $seen = []; $unique = [];
        foreach ( $all as $r ) {
            $k = $r['from'] . '|' . $r['to'];
            if ( isset( $seen[$k] ) ) continue;
            $seen[$k] = true; $unique[] = $r;
        }
        return $unique;
    }
}
