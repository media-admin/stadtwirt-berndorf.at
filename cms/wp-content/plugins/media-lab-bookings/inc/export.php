<?php
/**
 * CSV-Export
 *
 * Exportiert alle Buchungen (oder gefiltert nach Standort/Status/Datum)
 * als UTF-8 CSV-Datei direkt aus dem Backend.
 *
 * Aufruf: WP-Admin → Bookings → Buchungen → „Als CSV exportieren"
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Export {

    public static function init(): void {
        add_action( 'admin_init',    [ __CLASS__, 'handle_export' ] );
        add_action( 'admin_notices', [ __CLASS__, 'export_button' ] );
    }

    // ── Export-Button in der Buchungs-Listenansicht ───────────────────────────

    public static function export_button(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'edit-mlb_booking' ) return;

        $export_url = add_query_arg( [
            'mlb_export' => 'csv',
            'nonce'      => wp_create_nonce( 'mlb_export_csv' ),
            // aktive Filter weitergeben
            'mlb_filter_location' => sanitize_text_field( $_GET['mlb_filter_location'] ?? '' ),
            'mlb_filter_status'   => sanitize_text_field( $_GET['mlb_filter_status']   ?? '' ),
        ] );
        ?>
        <div class="notice notice-info inline" style="display:flex;align-items:center;gap:16px;padding:10px 16px;">
            <span>Buchungen exportieren:</span>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-secondary">
                📥 Als CSV exportieren
            </a>
        </div>
        <?php
    }

    // ── CSV generieren + ausgeben ─────────────────────────────────────────────

    public static function handle_export(): void {
        if ( empty( $_GET['mlb_export'] ) || $_GET['mlb_export'] !== 'csv' ) return;
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Keine Berechtigung.' );
        if ( ! wp_verify_nonce( sanitize_text_field( $_GET['nonce'] ?? '' ), 'mlb_export_csv' ) ) wp_die( 'Ungültige Anfrage.' );

        // Filter aus GET-Parametern
        $meta_query = [];
        $loc = (int) sanitize_text_field( $_GET['mlb_filter_location'] ?? 0 );
        if ( $loc ) $meta_query[] = [ 'key' => 'mlb_booking_location', 'value' => $loc ];

        $status = sanitize_text_field( $_GET['mlb_filter_status'] ?? '' );
        if ( $status ) $meta_query[] = [ 'key' => 'mlb_booking_status', 'value' => $status ];

        $bookings = get_posts( [
            'post_type'      => 'mlb_booking',
            'post_status'    => [ 'publish', 'mlb-pending', 'mlb-confirmed', 'mlb-cancelled' ],
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => 'mlb_booking_date',
            'order'          => 'ASC',
            'meta_query'     => $meta_query ?: null,
        ] );

        $status_labels = [
            'mlb-pending'   => 'Ausstehend',
            'mlb-confirmed' => 'Bestätigt',
            'mlb-cancelled' => 'Storniert',
        ];

        $filename = 'buchungen-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM für Excel-Kompatibilität
        fputs( $output, "\xEF\xBB\xBF" );

        // Header-Zeile
        fputcsv( $output, [
            'Buchungs-ID',
            'Status',
            'Standort',
            'Datum',
            'Uhrzeit',
            'Name',
            'E-Mail',
            'Telefon',
            'Dienstleistung',
            'Personen',
            'Anmerkungen',
            'Eingegangen am',
        ], ';' );

        foreach ( $bookings as $booking ) {
            $bid         = $booking->ID;
            $loc_id      = (int) get_post_meta( $bid, 'mlb_booking_location', true );
            $raw_status  = get_post_meta( $bid, 'mlb_booking_status', true );
            $date_raw    = get_post_meta( $bid, 'mlb_booking_date', true );

            fputcsv( $output, [
                '#' . $bid,
                $status_labels[ $raw_status ] ?? $raw_status,
                get_the_title( $loc_id ),
                $date_raw ? date_i18n( 'd.m.Y', strtotime( $date_raw ) ) : '',
                get_post_meta( $bid, 'mlb_booking_time',    true ),
                get_post_meta( $bid, 'mlb_booking_name',    true ),
                get_post_meta( $bid, 'mlb_booking_email',   true ),
                get_post_meta( $bid, 'mlb_booking_phone',   true ),
                get_post_meta( $bid, 'mlb_booking_service', true ),
                get_post_meta( $bid, 'mlb_booking_persons', true ),
                get_post_meta( $bid, 'mlb_booking_notes',   true ),
                get_the_date( 'd.m.Y H:i', $booking ),
            ], ';' );
        }

        fclose( $output );
        exit;
    }
}

MLB_Export::init();
