<?php
/**
 * Kalenderansicht
 *
 * Admin-Seite unter Bookings → Kalender.
 * Monatsansicht mit Buchungen pro Tag (farbkodiert nach Status).
 * Navigation vor/zurück per Monat, Filter nach Standort.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Calendar {

    public static function init(): void {
        add_action( 'admin_menu',            [ __CLASS__, 'register_page' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mlb_calendar_day', [ __CLASS__, 'ajax_day_detail' ] );
    }

    // ── Menüeintrag ───────────────────────────────────────────────────────────

    public static function register_page(): void {
        add_submenu_page(
            'mlb-bookings',
            'Kalender',
            'Kalender',
            'edit_posts',
            'mlb-calendar',
            [ __CLASS__, 'render_page' ]
        );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'mlb-calendar' ) === false ) return;
        wp_enqueue_style(  'mlb-calendar', MLB_URL . 'assets/css/calendar.css', [], MLB_VERSION );
        wp_enqueue_script( 'mlb-calendar', MLB_URL . 'assets/js/calendar.js',  [ 'jquery' ], MLB_VERSION, true );
        wp_localize_script( 'mlb-calendar', 'mlbCalendar', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mlb_calendar' ),
        ] );
    }

    // ── Seite rendern ─────────────────────────────────────────────────────────

    public static function render_page(): void {
        $month       = isset( $_GET['month'] )  ? (int) $_GET['month']  : (int) date( 'n' );
        $year        = isset( $_GET['year'] )   ? (int) $_GET['year']   : (int) date( 'Y' );
        $location_id = isset( $_GET['mlb_filter_location'] ) ? (int) $_GET['mlb_filter_location'] : 0;

        // Monats-Grenzen
        $month = max( 1, min( 12, $month ) );
        $year  = max( 2000, min( 2100, $year ) );

        $first_day   = mktime( 0, 0, 0, $month, 1, $year );
        $days_in_month = (int) date( 't', $first_day );
        $start_weekday = (int) date( 'N', $first_day ); // 1=Mo, 7=So

        // Vorheriger / nächster Monat
        $prev_month = $month === 1  ? 12 : $month - 1;
        $prev_year  = $month === 1  ? $year - 1 : $year;
        $next_month = $month === 12 ? 1  : $month + 1;
        $next_year  = $month === 12 ? $year + 1 : $year;

        // Buchungen für diesen Monat laden
        $bookings = self::get_month_bookings( $year, $month, $location_id );

        // Standorte für Filter
        $locations = get_posts( [ 'post_type' => 'mlb_location', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );

        $month_names = [ 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
                         7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember' ];
        ?>
        <div class="wrap mlb-calendar-wrap">
            <h1>Buchungskalender</h1>

            <div class="mlb-cal-toolbar">
                <div class="mlb-cal-nav">
                    <a href="<?php echo esc_url( add_query_arg( [ 'month' => $prev_month, 'year' => $prev_year, 'mlb_filter_location' => $location_id ] ) ); ?>" class="button">‹</a>
                    <span class="mlb-cal-title"><?php echo esc_html( $month_names[ $month ] . ' ' . $year ); ?></span>
                    <a href="<?php echo esc_url( add_query_arg( [ 'month' => $next_month, 'year' => $next_year, 'mlb_filter_location' => $location_id ] ) ); ?>" class="button">›</a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'month' => date('n'), 'year' => date('Y'), 'mlb_filter_location' => $location_id ] ) ); ?>" class="button button-secondary">Heute</a>
                </div>
                <form method="get" class="mlb-cal-filter">
                    <input type="hidden" name="page"  value="mlb-calendar">
                    <input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>">
                    <input type="hidden" name="year"  value="<?php echo esc_attr( $year ); ?>">
                    <select name="mlb_filter_location" onchange="this.form.submit()">
                        <option value="">Alle Standorte</option>
                        <?php foreach ( $locations as $loc ) : ?>
                            <option value="<?php echo esc_attr( $loc->ID ); ?>"<?php selected( $location_id, $loc->ID ); ?>>
                                <?php echo esc_html( $loc->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <div class="mlb-cal-legend">
                <span class="mlb-cal-dot mlb-cal-dot--pending"></span> Ausstehend
                <span class="mlb-cal-dot mlb-cal-dot--confirmed"></span> Bestätigt
                <span class="mlb-cal-dot mlb-cal-dot--cancelled"></span> Storniert
            </div>

            <table class="mlb-cal-table">
                <thead>
                    <tr>
                        <?php foreach ( [ 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ] as $day ) : ?>
                            <th><?php echo esc_html( $day ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                $day        = 1;
                $cell_count = 0;
                $today      = date( 'Y-m-d' );

                echo '<tr>';

                // Leere Zellen vor dem 1. des Monats
                for ( $i = 1; $i < $start_weekday; $i++ ) {
                    echo '<td class="mlb-cal-cell mlb-cal-cell--empty"></td>';
                    $cell_count++;
                }

                while ( $day <= $days_in_month ) {
                    $date_str    = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                    $day_bookings = $bookings[ $date_str ] ?? [];
                    $is_today    = $date_str === $today;
                    $has_bookings = ! empty( $day_bookings );

                    $cell_class = 'mlb-cal-cell';
                    if ( $is_today )    $cell_class .= ' mlb-cal-cell--today';
                    if ( $has_bookings ) $cell_class .= ' mlb-cal-cell--has-bookings';

                    echo '<td class="' . esc_attr( $cell_class ) . '" data-date="' . esc_attr( $date_str ) . '">';
                    echo '<span class="mlb-cal-day-number">' . (int) $day . '</span>';

                    if ( $has_bookings ) {
                        echo '<div class="mlb-cal-bookings">';
                        foreach ( $day_bookings as $b ) {
                            $status_class = 'mlb-cal-booking--' . str_replace( 'mlb-', '', $b['status'] );
                            echo '<div class="mlb-cal-booking ' . esc_attr( $status_class ) . '" data-booking-id="' . esc_attr( $b['id'] ) . '">';
                            echo '<span class="mlb-cal-booking-time">' . esc_html( $b['time'] ) . '</span> ';
                            echo '<span class="mlb-cal-booking-name">' . esc_html( $b['name'] ) . '</span>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }

                    echo '</td>';

                    $cell_count++;
                    $day++;

                    if ( $cell_count % 7 === 0 && $day <= $days_in_month ) {
                        echo '</tr><tr>';
                    }
                }

                // Leere Zellen nach dem letzten Tag
                $remaining = 7 - ( $cell_count % 7 );
                if ( $remaining < 7 ) {
                    for ( $i = 0; $i < $remaining; $i++ ) {
                        echo '<td class="mlb-cal-cell mlb-cal-cell--empty"></td>';
                    }
                }

                echo '</tr>';
                ?>
                </tbody>
            </table>

            <!-- Detail-Popup -->
            <div id="mlb-cal-popup" class="mlb-cal-popup" hidden>
                <div class="mlb-cal-popup__inner">
                    <button class="mlb-cal-popup__close" type="button">✕</button>
                    <div class="mlb-cal-popup__content">Lädt…</div>
                </div>
            </div>
        </div>
        <?php
    }

    // ── Buchungen pro Monat laden ─────────────────────────────────────────────

    private static function get_month_bookings( int $year, int $month, int $location_id ): array {
        $days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );

        // ACF date_picker speichert intern als 'Ymd' (z.B. 20260422).
        // BETWEEN-Werte im selben Format + type=>'CHAR' für korrekten String-Vergleich.
        $date_from = sprintf( '%04d%02d01',   $year, $month );
        $date_to   = sprintf( '%04d%02d%02d', $year, $month, $days_in_month );

        $args = [
            'post_type'      => 'mlb_booking',
            // Alle möglichen WP post_status-Werte explizit angeben.
            // 'any' schließt Custom Statuses mit public=>false aus (exclude_from_search=true).
            'post_status'    => self::all_booking_statuses(),
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'mlb_booking_date', 'value' => [ $date_from, $date_to ], 'compare' => 'BETWEEN', 'type' => 'CHAR' ],
            ],
        ];
        if ( $location_id ) {
            $args['meta_query'][] = [ 'key' => 'mlb_booking_location', 'value' => $location_id ];
        }

        // WP_Query statt get_posts() – get_posts() setzt suppress_filters => true
        // und behandelt post_status => 'any' nicht korrekt für Custom Statuses.
        $query  = new WP_Query( $args );
        $posts  = $query->posts;
        $grouped  = [];

        foreach ( $posts as $post ) {
            $date_raw = get_post_meta( $post->ID, 'mlb_booking_date', true );
            $status   = get_post_meta( $post->ID, 'mlb_booking_status', true ) ?: 'mlb-pending';
            $time     = get_post_meta( $post->ID, 'mlb_booking_time',   true );
            $name     = get_post_meta( $post->ID, 'mlb_booking_name',   true );

            if ( ! $date_raw ) continue;

            // Datum normalisieren: ACF speichert intern als 'Ymd' (20260422),
            // Kalender-Zellen verwenden 'Y-m-d' (2026-04-22) als Array-Key.
            $date = date( 'Y-m-d', strtotime( $date_raw ) );
            if ( ! $date || $date === '1970-01-01' ) continue;

            $grouped[ $date ][] = [
                'id'     => $post->ID,
                'status' => $status,
                'time'   => $time,
                'name'   => $name,
            ];
        }

        // Pro Tag nach Uhrzeit sortieren
        foreach ( $grouped as &$day ) {
            usort( $day, fn( $a, $b ) => strcmp( $a['time'], $b['time'] ) );
        }

        return $grouped;
    }

    // ── AJAX: Tages-Detail-Popup ──────────────────────────────────────────────

    public static function ajax_day_detail(): void {
        check_ajax_referer( 'mlb_calendar', 'nonce' );

        $date        = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $location_id = isset( $_POST['location_id'] ) ? (int) $_POST['location_id'] : 0;

        if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error();
        }

        $args = [
            'post_type'      => 'mlb_booking',
            'post_status'    => self::all_booking_statuses(),
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key' => 'mlb_booking_date', 'value' => $date ],
            ],
        ];
        if ( $location_id ) {
            $args['meta_query'][] = [ 'key' => 'mlb_booking_location', 'value' => $location_id ];
        }

        $query = new WP_Query( $args );
        $posts = $query->posts;

        $status_labels = [ 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt', 'mlb-cancelled' => 'Storniert' ];

        $html  = '<h3>' . esc_html( date_i18n( 'd. F Y', strtotime( $date ) ) ) . '</h3>';
        $html .= '<table class="widefat striped mlb-cal-popup__table">';
        $html .= '<thead><tr><th>Zeit</th><th>Name</th><th>Standort</th><th>Service</th><th>Status</th><th></th></tr></thead><tbody>';

        if ( empty( $posts ) ) {
            $html .= '<tr><td colspan="6">Keine Buchungen an diesem Tag.</td></tr>';
        } else {
            usort( $posts, fn( $a, $b ) => strcmp(
                get_post_meta( $a->ID, 'mlb_booking_time', true ),
                get_post_meta( $b->ID, 'mlb_booking_time', true )
            ) );
            foreach ( $posts as $post ) {
                $bid     = $post->ID;
                $status  = get_post_meta( $bid, 'mlb_booking_status',   true ) ?: 'mlb-pending';
                $loc_id  = (int) get_post_meta( $bid, 'mlb_booking_location', true );
                $s_class = 'mlb-badge mlb-badge--' . str_replace( 'mlb-', '', $status );
                $html .= '<tr>';
                $html .= '<td>' . esc_html( get_post_meta( $bid, 'mlb_booking_time',    true ) ) . ' Uhr</td>';
                $html .= '<td>' . esc_html( get_post_meta( $bid, 'mlb_booking_name',    true ) ) . '<br><small>' . esc_html( get_post_meta( $bid, 'mlb_booking_email', true ) ) . '</small></td>';
                $html .= '<td>' . esc_html( get_the_title( $loc_id ) ) . '</td>';
                $html .= '<td>' . esc_html( get_post_meta( $bid, 'mlb_booking_service', true ) ?: '—' ) . '</td>';
                $html .= '<td><span class="' . esc_attr( $s_class ) . '">' . esc_html( $status_labels[ $status ] ?? $status ) . '</span></td>';
                $html .= '<td><a href="' . esc_url( admin_url( 'post.php?post=' . $bid . '&action=edit' ) ) . '" class="button button-small">Bearbeiten</a></td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        wp_send_json_success( [ 'html' => $html ] );
    }

    // ── Alle möglichen WP-Post-Statuses für Buchungen ────────────────────────
    // 'any' in WP_Query schließt Custom Statuses mit public=>false aus,
    // weil diese exclude_from_search=true erben. Explizite Liste nötig.

    private static function all_booking_statuses(): array {
        return [
            'publish',        // WordPress setzt diesen beim Backend-Speichern
            'mlb-pending',    // Neu via Formular
            'mlb-confirmed',  // Bestätigt (nach notifications.php Sync)
            'mlb-cancelled',  // Storniert
            'draft',
            'private',
        ];
    }
}

MLB_Calendar::init();
