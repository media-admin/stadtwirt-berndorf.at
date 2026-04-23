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
        $view_param  = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'month';
        $view        = in_array( $view_param, [ 'month', 'week', 'day' ] ) ? $view_param : 'month';
        $month       = isset( $_GET['month'] )  ? (int) $_GET['month']  : (int) date( 'n' );
        $year        = isset( $_GET['year'] )   ? (int) $_GET['year']   : (int) date( 'Y' );
        $day_param   = isset( $_GET['day'] )    ? (int) $_GET['day']    : (int) date( 'j' );
        $location_id = isset( $_GET['mlb_filter_location'] ) ? (int) $_GET['mlb_filter_location'] : 0;

        $month = max( 1, min( 12, $month ) );
        $year  = max( 2000, min( 2100, $year ) );

        // Aktuelles Datum für den jeweiligen View
        $current_date = date_create( sprintf( '%04d-%02d-%02d', $year, $month, $day_param ) ) ?: date_create();

        // Standorte für Filter
        $locations = get_posts( [ 'post_type' => 'mlb_location', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );

        $month_names = [ 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
                         7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember' ];

        // Navigation: Ziel-URLs je nach View
        if ( $view === 'week' ) {
            $week_start  = clone $current_date;
            $dow = (int) $current_date->format('N'); // 1=Mo
            $week_start->modify( '-' . ( $dow - 1 ) . ' days' );
            $week_end = clone $week_start;
            $week_end->modify( '+6 days' );
            $prev_dt = clone $week_start; $prev_dt->modify( '-7 days' );
            $next_dt = clone $week_start; $next_dt->modify( '+7 days' );
            $prev_url = add_query_arg( [ 'view' => 'week', 'year' => $prev_dt->format('Y'), 'month' => $prev_dt->format('n'), 'day' => $prev_dt->format('j'), 'mlb_filter_location' => $location_id ] );
            $next_url = add_query_arg( [ 'view' => 'week', 'year' => $next_dt->format('Y'), 'month' => $next_dt->format('n'), 'day' => $next_dt->format('j'), 'mlb_filter_location' => $location_id ] );
            $today_url = add_query_arg( [ 'view' => 'week', 'year' => date('Y'), 'month' => date('n'), 'day' => date('j'), 'mlb_filter_location' => $location_id ] );
            $title = $week_start->format('d.m.') . ' – ' . $week_end->format('d.m.Y');
        } elseif ( $view === 'day' ) {
            $prev_dt = clone $current_date; $prev_dt->modify( '-1 day' );
            $next_dt = clone $current_date; $next_dt->modify( '+1 day' );
            $prev_url = add_query_arg( [ 'view' => 'day', 'year' => $prev_dt->format('Y'), 'month' => $prev_dt->format('n'), 'day' => $prev_dt->format('j'), 'mlb_filter_location' => $location_id ] );
            $next_url = add_query_arg( [ 'view' => 'day', 'year' => $next_dt->format('Y'), 'month' => $next_dt->format('n'), 'day' => $next_dt->format('j'), 'mlb_filter_location' => $location_id ] );
            $today_url = add_query_arg( [ 'view' => 'day', 'year' => date('Y'), 'month' => date('n'), 'day' => date('j'), 'mlb_filter_location' => $location_id ] );
            $day_names = [ 'Mon' => 'Montag', 'Tue' => 'Dienstag', 'Wed' => 'Mittwoch', 'Thu' => 'Donnerstag', 'Fri' => 'Freitag', 'Sat' => 'Samstag', 'Sun' => 'Sonntag' ];
            $title = ( $day_names[ $current_date->format('D') ] ?? '' ) . ', ' . $current_date->format('d.m.Y');
        } else {
            // Monat
            $first_day     = mktime( 0, 0, 0, $month, 1, $year );
            $days_in_month = (int) date( 't', $first_day );
            $prev_month    = $month === 1  ? 12 : $month - 1;
            $prev_year     = $month === 1  ? $year - 1 : $year;
            $next_month    = $month === 12 ? 1  : $month + 1;
            $next_year     = $month === 12 ? $year + 1 : $year;
            $prev_url = add_query_arg( [ 'view' => 'month', 'month' => $prev_month, 'year' => $prev_year, 'mlb_filter_location' => $location_id ] );
            $next_url = add_query_arg( [ 'view' => 'month', 'month' => $next_month, 'year' => $next_year, 'mlb_filter_location' => $location_id ] );
            $today_url = add_query_arg( [ 'view' => 'month', 'month' => date('n'), 'year' => date('Y'), 'mlb_filter_location' => $location_id ] );
            $title = $month_names[ $month ] . ' ' . $year;
        }
        ?>
        <div class="wrap mlb-calendar-wrap">
            <h1>Buchungskalender</h1>

            <div class="mlb-cal-toolbar">
                <div class="mlb-cal-nav">
                    <a href="<?php echo esc_url( $prev_url ); ?>" class="button">‹</a>
                    <span class="mlb-cal-title"><?php echo esc_html( $title ); ?></span>
                    <a href="<?php echo esc_url( $next_url ); ?>" class="button">›</a>
                    <a href="<?php echo esc_url( $today_url ); ?>" class="button button-secondary">Heute</a>
                </div>
                <div class="mlb-cal-view-switch">
                    <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'month', 'month' => $month, 'year' => $year, 'mlb_filter_location' => $location_id ] ) ); ?>"
                       class="button <?php echo $view === 'month' ? 'button-primary' : ''; ?>">Monat</a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'week', 'year' => $year, 'month' => $month, 'day' => $day_param, 'mlb_filter_location' => $location_id ] ) ); ?>"
                       class="button <?php echo $view === 'week' ? 'button-primary' : ''; ?>">Woche</a>
                    <a href="<?php echo esc_url( add_query_arg( [ 'view' => 'day', 'year' => $year, 'month' => $month, 'day' => $day_param, 'mlb_filter_location' => $location_id ] ) ); ?>"
                       class="button <?php echo $view === 'day' ? 'button-primary' : ''; ?>">Tag</a>
                </div>
                <form method="get" class="mlb-cal-filter">
                    <input type="hidden" name="page"  value="mlb-calendar">
                    <input type="hidden" name="view"  value="<?php echo esc_attr( $view ); ?>">
                    <input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>">
                    <input type="hidden" name="year"  value="<?php echo esc_attr( $year ); ?>">
                    <input type="hidden" name="day"   value="<?php echo esc_attr( $day_param ); ?>">
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

            <?php if ( $view === 'month' ) : ?>
            <?php
                $bookings      = self::get_month_bookings( $year, $month, $location_id );
                $start_weekday = (int) date( 'N', mktime( 0, 0, 0, $month, 1, $year ) );
                $days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
                $today         = date( 'Y-m-d' );
            ?>
            <table class="mlb-cal-table">
                <thead><tr><?php foreach ( [ 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ] as $dh ) echo '<th>' . esc_html($dh) . '</th>'; ?></tr></thead>
                <tbody>
                <?php
                $day = 1; $cell_count = 0;
                echo '<tr>';
                for ( $i = 1; $i < $start_weekday; $i++ ) { echo '<td class="mlb-cal-cell mlb-cal-cell--empty"></td>'; $cell_count++; }
                while ( $day <= $days_in_month ) {
                    $date_str     = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                    $day_bookings = $bookings[ $date_str ] ?? [];
                    $is_today     = $date_str === $today;
                    $cell_class   = 'mlb-cal-cell' . ( $is_today ? ' mlb-cal-cell--today' : '' ) . ( ! empty($day_bookings) ? ' mlb-cal-cell--has-bookings' : '' );
                    echo '<td class="' . esc_attr( $cell_class ) . '" data-date="' . esc_attr( $date_str ) . '">';
                    echo '<span class="mlb-cal-day-number">' . (int) $day . '</span>';
                    if ( ! empty( $day_bookings ) ) {
                        echo '<div class="mlb-cal-bookings">';
                        foreach ( $day_bookings as $b ) {
                            $sc = 'mlb-cal-booking mlb-cal-booking--' . str_replace( 'mlb-', '', $b['status'] );
                            echo '<div class="' . esc_attr($sc) . '"><span class="mlb-cal-booking-time">' . esc_html($b['time']) . '</span> <span class="mlb-cal-booking-name">' . esc_html($b['name']) . '</span></div>';
                        }
                        echo '</div>';
                    }
                    echo '</td>';
                    $cell_count++; $day++;
                    if ( $cell_count % 7 === 0 && $day <= $days_in_month ) echo '</tr><tr>';
                }
                $remaining = 7 - ( $cell_count % 7 );
                if ( $remaining < 7 ) for ( $i = 0; $i < $remaining; $i++ ) echo '<td class="mlb-cal-cell mlb-cal-cell--empty"></td>';
                echo '</tr>';
                ?>
                </tbody>
            </table>

            <?php elseif ( $view === 'week' ) : ?>
            <?php
                $bookings  = self::get_week_bookings( $week_start, $week_end, $location_id );
                $today     = date( 'Y-m-d' );
                $day_iter  = clone $week_start;
                $day_names_short = [ 1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So' ];
            ?>
            <table class="mlb-cal-table mlb-cal-table--week">
                <thead><tr><?php
                    $di = clone $week_start;
                    for ( $i = 0; $i < 7; $i++ ) {
                        $ds = $di->format('Y-m-d');
                        $is_t = $ds === $today;
                        echo '<th' . ($is_t ? ' class="mlb-cal-th--today"' : '') . '>';
                        echo esc_html( $day_names_short[ (int)$di->format('N') ] . ' ' . $di->format('d.m.') );
                        echo '</th>';
                        $di->modify('+1 day');
                    }
                ?></tr></thead>
                <tbody><tr><?php
                    for ( $i = 0; $i < 7; $i++ ) {
                        $date_str     = $day_iter->format('Y-m-d');
                        $day_bookings = $bookings[ $date_str ] ?? [];
                        $is_today     = $date_str === $today;
                        $cell_class   = 'mlb-cal-cell mlb-cal-cell--week' . ( $is_today ? ' mlb-cal-cell--today' : '' ) . ( ! empty($day_bookings) ? ' mlb-cal-cell--has-bookings' : '' );
                        echo '<td class="' . esc_attr($cell_class) . '" data-date="' . esc_attr($date_str) . '">';
                        if ( ! empty( $day_bookings ) ) {
                            echo '<div class="mlb-cal-bookings">';
                            foreach ( $day_bookings as $b ) {
                                $sc = 'mlb-cal-booking mlb-cal-booking--' . str_replace( 'mlb-', '', $b['status'] );
                                echo '<div class="' . esc_attr($sc) . '"><span class="mlb-cal-booking-time">' . esc_html($b['time']) . '</span> <span class="mlb-cal-booking-name">' . esc_html($b['name']) . '</span></div>';
                            }
                            echo '</div>';
                        } else {
                            echo '<span style="color:#ccc;font-size:12px">–</span>';
                        }
                        echo '</td>';
                        $day_iter->modify('+1 day');
                    }
                ?></tr></tbody>
            </table>

            <?php elseif ( $view === 'day' ) : ?>
            <?php
                $date_str     = $current_date->format('Y-m-d');
                $day_bookings = self::get_day_bookings( $date_str, $location_id );
                $status_labels = [ 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt', 'mlb-cancelled' => 'Storniert' ];
            ?>
            <div class="mlb-cal-day-view">
                <?php if ( empty( $day_bookings ) ) : ?>
                    <p style="color:#888;padding:24px 0">Keine Buchungen an diesem Tag.</p>
                <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Zeit</th><th>Name</th><th>Standort</th><th>Dienstleistung</th><th>Pers.</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ( $day_bookings as $b ) :
                        $bid    = $b['id'];
                        $status = $b['status'];
                        $sc     = 'mlb-badge mlb-badge--' . str_replace( 'mlb-', '', $status );
                        $loc_id = (int) get_post_meta( $bid, 'mlb_booking_location', true );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $b['time'] ); ?> Uhr</strong></td>
                        <td><?php echo esc_html( $b['name'] ); ?><br><small><?php echo esc_html( get_post_meta($bid,'mlb_booking_email',true) ); ?></small></td>
                        <td><?php echo esc_html( get_the_title( $loc_id ) ); ?></td>
                        <td><?php echo esc_html( get_post_meta($bid,'mlb_booking_service',true) ?: '—' ); ?></td>
                        <td><?php echo esc_html( get_post_meta($bid,'mlb_booking_persons',true) ?: '1' ); ?></td>
                        <td><span class="<?php echo esc_attr($sc); ?>"><?php echo esc_html( $status_labels[$status] ?? $status ); ?></span></td>
                        <td><a href="<?php echo esc_url( admin_url('post.php?post='.$bid.'&action=edit') ); ?>" class="button button-small">Bearbeiten</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Detail-Popup (nur für Monat + Woche) -->
            <div id="mlb-cal-popup" class="mlb-cal-popup" hidden>
                <div class="mlb-cal-popup__inner">
                    <button class="mlb-cal-popup__close" type="button">✕</button>
                    <div class="mlb-cal-popup__content">Lädt…</div>
                </div>
            </div>
        </div>
        <?php
    }

    // ── Buchungen für eine Woche laden ──────────────────────────────────────────

    private static function get_week_bookings( DateTime $week_start, DateTime $week_end, int $location_id ): array {
        $from_ymd  = $week_start->format('Ymd');
        $to_ymd    = $week_end->format('Ymd');
        $from_dash = $week_start->format('Y-m-d');
        $to_dash   = $week_end->format('Y-m-d');

        $args = [
            'post_type'      => 'mlb_booking',
            'post_status'    => self::all_booking_statuses(),
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'relation' => 'OR',
                  [ 'key' => 'mlb_booking_date', 'value' => [ $from_ymd,  $to_ymd  ], 'compare' => 'BETWEEN', 'type' => 'CHAR' ],
                  [ 'key' => 'mlb_booking_date', 'value' => [ $from_dash, $to_dash ], 'compare' => 'BETWEEN', 'type' => 'CHAR' ],
                ],
            ],
        ];
        if ( $location_id ) $args['meta_query'][] = [ 'key' => 'mlb_booking_location', 'value' => $location_id ];

        $query   = new WP_Query( $args );
        $grouped = [];
        foreach ( $query->posts as $post ) {
            $raw    = get_post_meta( $post->ID, 'mlb_booking_date', true );
            $status = get_post_meta( $post->ID, 'mlb_booking_status', true ) ?: 'mlb-pending';
            $time   = get_post_meta( $post->ID, 'mlb_booking_time',   true );
            $name   = get_post_meta( $post->ID, 'mlb_booking_name',   true );
            if ( ! $raw ) continue;
            $date = date( 'Y-m-d', strtotime( $raw ) );
            if ( ! $date || $date === '1970-01-01' ) continue;
            $grouped[ $date ][] = [ 'id' => $post->ID, 'status' => $status, 'time' => $time, 'name' => $name ];
        }
        foreach ( $grouped as &$day ) usort( $day, fn($a,$b) => strcmp($a['time'],$b['time']) );
        return $grouped;
    }

    // ── Buchungen für einen Tag laden ─────────────────────────────────────────

    private static function get_day_bookings( string $date_ymd_or_dash, int $location_id ): array {
        $ymd  = date( 'Ymd',  strtotime( $date_ymd_or_dash ) );
        $dash = date( 'Y-m-d', strtotime( $date_ymd_or_dash ) );

        $args = [
            'post_type'      => 'mlb_booking',
            'post_status'    => self::all_booking_statuses(),
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [ 'relation' => 'OR',
                  [ 'key' => 'mlb_booking_date', 'value' => $ymd  ],
                  [ 'key' => 'mlb_booking_date', 'value' => $dash ],
                ],
            ],
        ];
        if ( $location_id ) $args['meta_query'][] = [ 'key' => 'mlb_booking_location', 'value' => $location_id ];

        $query  = new WP_Query( $args );
        $result = [];
        foreach ( $query->posts as $post ) {
            $status = get_post_meta( $post->ID, 'mlb_booking_status', true ) ?: 'mlb-pending';
            $time   = get_post_meta( $post->ID, 'mlb_booking_time',   true );
            $name   = get_post_meta( $post->ID, 'mlb_booking_name',   true );
            $result[] = [ 'id' => $post->ID, 'status' => $status, 'time' => $time, 'name' => $name ];
        }
        usort( $result, fn($a,$b) => strcmp($a['time'],$b['time']) );
        return $result;
    }

    // ── Buchungen pro Monat laden ─────────────────────────────────────────────

    private static function get_month_bookings( int $year, int $month, int $location_id ): array {
        // Kein BETWEEN in der DB-Query – nested OR+BETWEEN ist in WP_Query unzuverlässig
        // und filtert bestimmte Formate (z.B. Y-m-d) aus. Stattdessen alle Buchungen laden
        // und den Monat PHP-seitig über strtotime() filtern. Das deckt beide Datumsformate
        // (Ymd und Y-m-d) zuverlässig ab.
        $args = [
            'post_type'      => 'mlb_booking',
            'post_status'    => self::all_booking_statuses(),
            'posts_per_page' => -1,
            'meta_query'     => [ [ 'key' => 'mlb_booking_date', 'compare' => 'EXISTS' ] ],
        ];
        if ( $location_id ) {
            $args['meta_query'] = [
                'relation' => 'AND',
                [ 'key' => 'mlb_booking_date',     'compare' => 'EXISTS' ],
                [ 'key' => 'mlb_booking_location', 'value'   => $location_id ],
            ];
        }

        $query   = new WP_Query( $args );
        $grouped = [];

        foreach ( $query->posts as $post ) {
            $date_raw = get_post_meta( $post->ID, 'mlb_booking_date', true );
            if ( ! $date_raw ) continue;

            $ts = strtotime( $date_raw );
            if ( ! $ts || $ts === false ) continue;

            // PHP-seitiger Monatsfilter – funktioniert mit jedem Datumsformat
            if ( (int) date( 'n', $ts ) !== $month || (int) date( 'Y', $ts ) !== $year ) continue;

            $date   = date( 'Y-m-d', $ts ); // Normalisiert auf Y-m-d als Array-Key
            $status = get_post_meta( $post->ID, 'mlb_booking_status', true ) ?: 'mlb-pending';
            $time   = get_post_meta( $post->ID, 'mlb_booking_time',   true );
            $name   = get_post_meta( $post->ID, 'mlb_booking_name',   true );

            $grouped[ $date ][] = [
                'id'     => $post->ID,
                'status' => $status,
                'time'   => $time,
                'name'   => $name,
            ];
        }

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

        // Datum in beide Formate konvertieren (Y-m-d vom Frontend, Ymd ACF-intern)
        $date_ymd = date( 'Ymd', strtotime( $date ) );
        $args = [
            'post_type'      => 'mlb_booking',
            'post_status'    => self::all_booking_statuses(),
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => 'mlb_booking_date', 'value' => $date_ymd ],
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
