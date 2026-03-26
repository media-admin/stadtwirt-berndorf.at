<?php
/**
 * Admin-Oberfläche
 *
 * - Toplevel-Menü "Bookings" (Dashicons calendar-alt)
 * - Buchungen-Listenansicht: Spalten, Farbstatus, Sortierung, Filter
 * - Standorte-Listenansicht: Spalten mit Slot-Zusammenfassung
 * - Admin-Stile
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );

        // Buchungen – Listenansicht
        add_filter( 'manage_mlb_booking_posts_columns',       [ __CLASS__, 'booking_columns' ] );
        add_action( 'manage_mlb_booking_posts_custom_column', [ __CLASS__, 'booking_column_content' ], 10, 2 );
        add_filter( 'manage_edit-mlb_booking_sortable_columns', [ __CLASS__, 'booking_sortable_columns' ] );
        add_action( 'pre_get_posts',                          [ __CLASS__, 'booking_orderby' ] );
        add_action( 'restrict_manage_posts',                  [ __CLASS__, 'booking_filters' ] );
        add_filter( 'parse_query',                            [ __CLASS__, 'filter_bookings' ] );

        // Standorte – Listenansicht
        add_filter( 'manage_mlb_location_posts_columns',       [ __CLASS__, 'location_columns' ] );
        add_action( 'manage_mlb_location_posts_custom_column', [ __CLASS__, 'location_column_content' ], 10, 2 );

        // Status-Label in Titel-Zeile
        add_filter( 'display_post_states', [ __CLASS__, 'booking_post_states' ], 10, 2 );
    }

    // ── Menü ─────────────────────────────────────────────────────────────────

    public static function register_menu() {
        add_menu_page(
            'Media Lab Bookings',
            'Bookings',
            'edit_posts',
            'mlb-bookings',
            [ __CLASS__, 'dashboard_page' ],
            'dashicons-calendar-alt',
            26
        );

        add_submenu_page(
            'mlb-bookings',
            'Übersicht',
            'Übersicht',
            'edit_posts',
            'mlb-bookings',
            [ __CLASS__, 'dashboard_page' ]
        );

        add_submenu_page(
            'mlb-bookings',
            'Alle Buchungen',
            'Buchungen',
            'edit_posts',
            'edit.php?post_type=mlb_booking'
        );

        add_submenu_page(
            'mlb-bookings',
            'Standorte',
            'Standorte',
            'edit_posts',
            'edit.php?post_type=mlb_location'
        );
    }

    // ── Dashboard-Seite ───────────────────────────────────────────────────────

    public static function dashboard_page() {
        $pending   = wp_count_posts( 'mlb_booking' )->{'mlb-pending'}   ?? 0;
        $confirmed = wp_count_posts( 'mlb_booking' )->{'mlb-confirmed'} ?? 0;
        $cancelled = wp_count_posts( 'mlb_booking' )->{'mlb-cancelled'} ?? 0;
        $locations = wp_count_posts( 'mlb_location' )->publish           ?? 0;
        ?>
        <div class="wrap mlb-dashboard">
            <h1>Media Lab Bookings</h1>
            <div class="mlb-stats">
                <div class="mlb-stat mlb-stat--pending">
                    <span class="mlb-stat__count"><?php echo (int) $pending; ?></span>
                    <span class="mlb-stat__label">Ausstehend</span>
                </div>
                <div class="mlb-stat mlb-stat--confirmed">
                    <span class="mlb-stat__count"><?php echo (int) $confirmed; ?></span>
                    <span class="mlb-stat__label">Bestätigt</span>
                </div>
                <div class="mlb-stat mlb-stat--cancelled">
                    <span class="mlb-stat__count"><?php echo (int) $cancelled; ?></span>
                    <span class="mlb-stat__label">Storniert</span>
                </div>
                <div class="mlb-stat mlb-stat--locations">
                    <span class="mlb-stat__count"><?php echo (int) $locations; ?></span>
                    <span class="mlb-stat__label">Standorte</span>
                </div>
            </div>
            <p>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mlb_booking' ) ); ?>" class="button button-primary">Alle Buchungen</a>
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mlb_location' ) ); ?>" class="button">Standorte verwalten</a>
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mlb_location' ) ); ?>" class="button">Neuer Standort</a>
            </p>
        </div>
        <?php
    }

    // ── Buchungen: Spalten ────────────────────────────────────────────────────

    public static function booking_columns( array $cols ): array {
        return [
            'cb'              => '<input type="checkbox">',
            'title'           => 'Buchung',
            'mlb_status'      => 'Status',
            'mlb_location'    => 'Standort',
            'mlb_date'        => 'Datum',
            'mlb_time'        => 'Uhrzeit',
            'mlb_customer'    => 'Kunde',
            'mlb_service'     => 'Dienstleistung',
            'mlb_persons'     => 'Pers.',
            'date'            => 'Eingegangen',
        ];
    }

    public static function booking_column_content( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'mlb_status':
                $status = get_post_meta( $post_id, 'mlb_booking_status', true ) ?: 'mlb-pending';
                $labels = [
                    'mlb-pending'   => [ 'Ausstehend', 'mlb-badge--pending' ],
                    'mlb-confirmed' => [ 'Bestätigt',  'mlb-badge--confirmed' ],
                    'mlb-cancelled' => [ 'Storniert',  'mlb-badge--cancelled' ],
                ];
                [ $label, $class ] = $labels[ $status ] ?? [ $status, '' ];
                echo '<span class="mlb-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
                break;

            case 'mlb_location':
                $loc_id = get_post_meta( $post_id, 'mlb_booking_location', true );
                echo $loc_id ? esc_html( get_the_title( (int) $loc_id ) ) : '—';
                break;

            case 'mlb_date':
                $date = get_post_meta( $post_id, 'mlb_booking_date', true );
                echo $date ? esc_html( date_i18n( 'd.m.Y', strtotime( $date ) ) ) : '—';
                break;

            case 'mlb_time':
                $time = get_post_meta( $post_id, 'mlb_booking_time', true );
                echo $time ? esc_html( $time ) . ' Uhr' : '—';
                break;

            case 'mlb_customer':
                $name  = get_post_meta( $post_id, 'mlb_booking_name',  true );
                $email = get_post_meta( $post_id, 'mlb_booking_email', true );
                $phone = get_post_meta( $post_id, 'mlb_booking_phone', true );
                echo esc_html( $name );
                if ( $email ) echo '<br><small><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></small>';
                if ( $phone ) echo '<br><small>' . esc_html( $phone ) . '</small>';
                break;

            case 'mlb_service':
                $service = get_post_meta( $post_id, 'mlb_booking_service', true );
                echo $service ? esc_html( $service ) : '—';
                break;

            case 'mlb_persons':
                $p = get_post_meta( $post_id, 'mlb_booking_persons', true );
                echo $p ? esc_html( $p ) : '1';
                break;
        }
    }

    public static function booking_sortable_columns( array $cols ): array {
        $cols['mlb_date']   = 'mlb_date';
        $cols['mlb_status'] = 'mlb_status';
        return $cols;
    }

    public static function booking_orderby( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'mlb_booking' ) return;

        $orderby = $query->get( 'orderby' );
        if ( 'mlb_date' === $orderby ) {
            $query->set( 'meta_key', 'mlb_booking_date' );
            $query->set( 'orderby',  'meta_value' );
        }
    }

    // ── Buchungen: Filter (Standort + Status) ─────────────────────────────────

    public static function booking_filters( string $post_type ): void {
        if ( 'mlb_booking' !== get_current_screen()->post_type ) return;

        // Standort-Filter
        $locations = get_posts( [ 'post_type' => 'mlb_location', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $selected  = sanitize_text_field( $_GET['mlb_filter_location'] ?? '' );
        echo '<select name="mlb_filter_location"><option value="">Alle Standorte</option>';
        foreach ( $locations as $loc ) {
            printf(
                '<option value="%d"%s>%s</option>',
                $loc->ID,
                selected( $selected, $loc->ID, false ),
                esc_html( $loc->post_title )
            );
        }
        echo '</select>';

        // Status-Filter
        $statuses  = [ '' => 'Alle Status', 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt', 'mlb-cancelled' => 'Storniert' ];
        $sel_stat  = sanitize_text_field( $_GET['mlb_filter_status'] ?? '' );
        echo '<select name="mlb_filter_status">';
        foreach ( $statuses as $val => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $sel_stat, $val, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    public static function filter_bookings( \WP_Query $query ): void {
        global $pagenow;
        if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) return;
        if ( ( $query->get( 'post_type' ) ?: '' ) !== 'mlb_booking' ) return;

        $meta_query = [];

        $loc = (int) sanitize_text_field( $_GET['mlb_filter_location'] ?? 0 );
        if ( $loc ) {
            $meta_query[] = [ 'key' => 'mlb_booking_location', 'value' => $loc ];
        }

        $status = sanitize_text_field( $_GET['mlb_filter_status'] ?? '' );
        if ( $status ) {
            $meta_query[] = [ 'key' => 'mlb_booking_status', 'value' => $status ];
        }

        if ( $meta_query ) $query->set( 'meta_query', $meta_query );
    }

    // ── Standorte: Spalten ────────────────────────────────────────────────────

    public static function location_columns( array $cols ): array {
        return [
            'cb'             => '<input type="checkbox">',
            'title'          => 'Standort',
            'mlb_loc_email'  => 'Filial-E-Mail',
            'mlb_loc_slots'  => 'Slot-Dauer',
            'mlb_loc_cap'    => 'Kapazität/Slot',
            'mlb_loc_count'  => 'Buchungen gesamt',
            'date'           => 'Erstellt',
        ];
    }

    public static function location_column_content( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'mlb_loc_email':
                $email = get_field( 'mlb_location_email', $post_id );
                echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—';
                break;
            case 'mlb_loc_slots':
                $d = get_field( 'mlb_slot_duration', $post_id );
                echo $d ? esc_html( $d ) . ' Min.' : '60 Min.';
                break;
            case 'mlb_loc_cap':
                $c = get_field( 'mlb_max_capacity', $post_id );
                echo $c ? esc_html( $c ) . ' Buchung(en)' : '1';
                break;
            case 'mlb_loc_count':
                $count = new WP_Query( [
                    'post_type'      => 'mlb_booking',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [ [ 'key' => 'mlb_booking_location', 'value' => $post_id ] ],
                ] );
                echo (int) $count->found_posts;
                break;
        }
    }

    // ── Post-Status Labels ────────────────────────────────────────────────────

    public static function booking_post_states( array $states, \WP_Post $post ): array {
        if ( 'mlb_booking' !== $post->post_type ) return $states;
        $status_labels = [
            'mlb-pending'   => 'Ausstehend',
            'mlb-confirmed' => 'Bestätigt',
            'mlb-cancelled' => 'Storniert',
        ];
        if ( isset( $status_labels[ $post->post_status ] ) ) {
            $states[ $post->post_status ] = $status_labels[ $post->post_status ];
        }
        return $states;
    }

    // ── Admin-Styles ──────────────────────────────────────────────────────────

    public static function enqueue_styles( string $hook ): void {
        wp_add_inline_style( 'wp-admin', '
            /* MLB Badges */
            .mlb-badge { display:inline-block; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
            .mlb-badge--pending   { background:#fff3cd; color:#856404; }
            .mlb-badge--confirmed { background:#d4edda; color:#155724; }
            .mlb-badge--cancelled { background:#f8d7da; color:#721c24; }

            /* MLB Dashboard Stats */
            .mlb-stats { display:flex; gap:16px; margin:20px 0; flex-wrap:wrap; }
            .mlb-stat  { background:#fff; border:1px solid #e2e4e7; border-radius:6px; padding:20px 28px; min-width:120px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.05); }
            .mlb-stat__count { display:block; font-size:36px; font-weight:700; line-height:1; }
            .mlb-stat__label { display:block; font-size:12px; color:#666; margin-top:6px; text-transform:uppercase; letter-spacing:.5px; }
            .mlb-stat--pending   .mlb-stat__count { color:#856404; }
            .mlb-stat--confirmed .mlb-stat__count { color:#155724; }
            .mlb-stat--cancelled .mlb-stat__count { color:#721c24; }
            .mlb-stat--locations .mlb-stat__count { color:#0073aa; }

            /* Buchungen-Tabelle */
            .column-mlb_status   { width:100px; }
            .column-mlb_time     { width:80px; }
            .column-mlb_persons  { width:60px; text-align:center !important; }
        ' );
    }
}

MLB_Admin::init();
