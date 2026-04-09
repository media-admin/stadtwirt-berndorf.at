<?php
/**
 * WP-Dashboard-Widget
 *
 * Zeigt die nächsten X anstehenden Buchungen direkt auf der WP-Startseite.
 * Anzahl konfigurierbar (Standard: 5).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Dashboard_Widget {

    public static function init(): void {
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'register' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
    }

    public static function register(): void {
        wp_add_dashboard_widget(
            'mlb_upcoming_bookings',
            'Nächste Buchungen',
            [ __CLASS__, 'render' ],
            [ __CLASS__, 'configure' ]
        );
    }

    public static function enqueue_styles( string $hook ): void {
        if ( $hook !== 'index.php' ) return;
        wp_add_inline_style( 'wp-admin', '
            #mlb_upcoming_bookings .mlb-widget-empty { color: #888; font-style: italic; padding: 8px 0; }
            #mlb_upcoming_bookings .mlb-widget-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            #mlb_upcoming_bookings .mlb-widget-table td { padding: 8px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
            #mlb_upcoming_bookings .mlb-widget-table tr:last-child td { border-bottom: none; }
            #mlb_upcoming_bookings .mlb-widget-date { font-weight: 700; white-space: nowrap; color: #1d2327; }
            #mlb_upcoming_bookings .mlb-widget-time { color: #555; font-size: 12px; white-space: nowrap; }
            #mlb_upcoming_bookings .mlb-widget-name { font-weight: 600; }
            #mlb_upcoming_bookings .mlb-widget-loc  { color: #888; font-size: 12px; }
            #mlb_upcoming_bookings .mlb-widget-footer { margin-top: 12px; padding-top: 10px; border-top: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #888; }
            .mlb-badge { display:inline-block;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px; }
            .mlb-badge--pending   { background:#fff3cd;color:#856404; }
            .mlb-badge--confirmed { background:#d4edda;color:#155724; }
            .mlb-badge--cancelled { background:#f8d7da;color:#721c24; }
        ' );
    }

    // ── Widget rendern ────────────────────────────────────────────────────────

    public static function render(): void {
        $limit  = (int) ( get_option( 'mlb_widget_limit', 5 ) );
        $today  = date( 'Y-m-d' );

        $bookings = get_posts( [
            'post_type'      => 'mlb_booking',
            'post_status'    => [ 'publish', 'mlb-pending', 'mlb-confirmed' ],
            'posts_per_page' => $limit,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'mlb_booking_date',   'value' => $today, 'compare' => '>=' ],
                [ 'key' => 'mlb_booking_status', 'value' => 'mlb-cancelled', 'compare' => '!=' ],
            ],
            'orderby'  => 'meta_value',
            'meta_key' => 'mlb_booking_date',
            'order'    => 'ASC',
        ] );

        if ( empty( $bookings ) ) {
            echo '<p class="mlb-widget-empty">Keine bevorstehenden Buchungen.</p>';
        } else {
            $status_labels = [ 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt' ];
            echo '<table class="mlb-widget-table">';
            foreach ( $bookings as $booking ) {
                $bid     = $booking->ID;
                $status  = get_post_meta( $bid, 'mlb_booking_status',   true ) ?: 'mlb-pending';
                $loc_id  = (int) get_post_meta( $bid, 'mlb_booking_location', true );
                $date    = get_post_meta( $bid, 'mlb_booking_date', true );
                $date_f  = $date ? date_i18n( 'd.m.Y', strtotime( $date ) ) : '—';
                $is_today = $date === $today;
                $s_class = 'mlb-badge mlb-badge--' . str_replace( 'mlb-', '', $status );

                echo '<tr>';
                echo '<td><span class="mlb-widget-date">' . ( $is_today ? '<span style="color:#d40000">Heute</span>' : esc_html( $date_f ) ) . '</span><br>';
                echo '<span class="mlb-widget-time">' . esc_html( get_post_meta( $bid, 'mlb_booking_time', true ) ) . ' Uhr</span></td>';
                echo '<td><span class="mlb-widget-name">' . esc_html( get_post_meta( $bid, 'mlb_booking_name', true ) ) . '</span><br>';
                echo '<span class="mlb-widget-loc">' . esc_html( get_the_title( $loc_id ) ) . '</span></td>';
                echo '<td><span class="' . esc_attr( $s_class ) . '">' . esc_html( $status_labels[ $status ] ?? $status ) . '</span></td>';
                echo '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $bid . '&action=edit' ) ) . '" class="button button-small">→</a></td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        $total_pending = wp_count_posts( 'mlb_booking' )->{'mlb-pending'} ?? 0;

        echo '<div class="mlb-widget-footer">';
        echo '<span>' . (int) $total_pending . ' ausstehend</span>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=mlb-calendar' ) ) . '">Kalender →</a>';
        echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=mlb_booking' ) ) . '">Alle Buchungen →</a>';
        echo '</div>';
    }

    // ── Widget konfigurieren (Anzahl) ─────────────────────────────────────────

    public static function configure(): void {
        if ( isset( $_POST['mlb_widget_limit'] ) ) {
            update_option( 'mlb_widget_limit', max( 1, min( 20, (int) $_POST['mlb_widget_limit'] ) ) );
        }
        $limit = (int) get_option( 'mlb_widget_limit', 5 );
        echo '<label>Anzahl Buchungen anzeigen: <input type="number" name="mlb_widget_limit" value="' . esc_attr( $limit ) . '" min="1" max="20" style="width:60px"></label>';
    }
}

MLB_Dashboard_Widget::init();
