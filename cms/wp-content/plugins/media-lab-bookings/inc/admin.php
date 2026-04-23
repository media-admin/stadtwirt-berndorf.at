<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Admin {
    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
        add_action( 'wp_ajax_mlb_sync_statuses', [ __CLASS__, 'ajax_sync_statuses' ] );
        // Buchungsliste: alle Custom-Statuses anzeigen (WP zeigt sonst nur 'publish')
        add_action( 'pre_get_posts',          [ __CLASS__, 'fix_booking_list_query' ] );
        add_filter( 'manage_mlb_booking_posts_columns',         [ __CLASS__, 'booking_columns' ] );
        add_action( 'manage_mlb_booking_posts_custom_column',   [ __CLASS__, 'booking_column_content' ], 10, 2 );
        add_filter( 'manage_edit-mlb_booking_sortable_columns', [ __CLASS__, 'booking_sortable_columns' ] );
        add_action( 'pre_get_posts',                            [ __CLASS__, 'booking_orderby' ] );
        add_action( 'restrict_manage_posts',                    [ __CLASS__, 'booking_filters' ] );
        add_filter( 'parse_query',                              [ __CLASS__, 'filter_bookings' ] );
        add_filter( 'manage_mlb_location_posts_columns',        [ __CLASS__, 'location_columns' ] );
        add_action( 'manage_mlb_location_posts_custom_column',  [ __CLASS__, 'location_column_content' ], 10, 2 );
        add_filter( 'display_post_states',                      [ __CLASS__, 'booking_post_states' ], 10, 2 );
    }
    public static function register_menu() {
        add_menu_page( 'Media Lab Bookings', 'Bookings', 'edit_posts', 'mlb-bookings', [ __CLASS__, 'dashboard_page' ], 'dashicons-calendar-alt', 26 );
        // Erste Zeile = gleicher Slug wie Toplevel (WordPress-Konvention: umbenennen)
        add_submenu_page( 'mlb-bookings', 'Übersicht',      'Übersicht',       'edit_posts', 'mlb-bookings',                   [ __CLASS__, 'dashboard_page' ] );
        add_submenu_page( 'mlb-bookings', mlb_term('plural'), mlb_term('plural'), 'edit_posts', 'edit.php?post_type=mlb_booking' );
        add_submenu_page( 'mlb-bookings', 'Neue ' . mlb_term('singular'), 'Neue ' . mlb_term('singular'), 'edit_posts', 'post-new.php?post_type=mlb_booking' );
        add_submenu_page( 'mlb-bookings', 'Standorte',      'Standorte',        'edit_posts', 'edit.php?post_type=mlb_location' );
        add_submenu_page( 'mlb-bookings', 'Neuer Standort', 'Neuer Standort',   'edit_posts', 'post-new.php?post_type=mlb_location' );
    }
    // ── Buchungen nach ACF-Meta-Feld zählen (unabhängig vom WP-Post-Status) ────

    // ── Buchungsliste: alle Custom-Statuses einschließen ─────────────────────
    // WordPress zeigt in edit.php standardmäßig nur 'publish'-Posts.
    // Nach dem WP-Status-Sync haben Buchungen Custom-Statuses → default leer.

    public static function fix_booking_list_query( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( $query->get( 'post_type' ) !== 'mlb_booking' ) return;

        // Nur eingreifen wenn kein expliziter WP-Status-Filter gesetzt ist
        // (Die Status-Tabs oben setzen post_status selbst)
        $requested_status = $query->get( 'post_status' );
        if ( $requested_status && $requested_status !== 'any' ) return;

        $query->set( 'post_status', [ 'mlb-pending', 'mlb-confirmed', 'mlb-cancelled', 'publish' ] );
    }

    // ── Buchungen mit falschem WP-Post-Status zählen ────────────────────────

    private static function count_unsynced_bookings(): int {
        global $wpdb;
        // Buchungen wo WP post_status NICHT dem ACF meta mlb_booking_status entspricht
        return (int) $wpdb->get_var( "
            SELECT COUNT(p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type   = 'mlb_booking'
              AND pm.meta_key   = 'mlb_booking_status'
              AND p.post_status != pm.meta_value
              AND p.post_status != 'trash'
        " );
    }

    // ── AJAX: Alle Buchungen synchronisieren ──────────────────────────────────

    public static function ajax_sync_statuses(): void {
        check_ajax_referer( 'mlb_sync_statuses', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Keine Berechtigung.' );

        global $wpdb;
        $bookings = $wpdb->get_results( "
            SELECT p.ID, pm.meta_value as acf_status
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type   = 'mlb_booking'
              AND pm.meta_key   = 'mlb_booking_status'
              AND p.post_status != pm.meta_value
              AND p.post_status != 'trash'
        " );

        $count = 0;
        foreach ( $bookings as $booking ) {
            $valid = [ 'mlb-pending', 'mlb-confirmed', 'mlb-cancelled' ];
            if ( ! in_array( $booking->acf_status, $valid, true ) ) continue;
            $wpdb->update( $wpdb->posts, [ 'post_status' => $booking->acf_status ], [ 'ID' => $booking->ID ] );
            clean_post_cache( $booking->ID );
            $count++;
        }

        wp_send_json_success( $count . ' Buchung(en) erfolgreich synchronisiert.' );
    }

    private static function count_bookings_by_status( string $status ): int {
        $q = new WP_Query( [
            'post_type'      => 'mlb_booking',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [ 'key' => 'mlb_booking_status', 'value' => $status ] ],
        ] );
        return (int) $q->found_posts;
    }

    public static function dashboard_page() {
        $pending   = self::count_bookings_by_status( 'mlb-pending' );
        $confirmed = self::count_bookings_by_status( 'mlb-confirmed' );
        $cancelled = self::count_bookings_by_status( 'mlb-cancelled' );
        $locations = wp_count_posts( 'mlb_location' )->publish ?? 0;
        $url_pending   = admin_url( 'edit.php?post_type=mlb_booking&post_status=mlb-pending' );
        $url_confirmed = admin_url( 'edit.php?post_type=mlb_booking&post_status=mlb-confirmed' );
        $url_cancelled = admin_url( 'edit.php?post_type=mlb_booking&post_status=mlb-cancelled' );
        $url_locations = admin_url( 'edit.php?post_type=mlb_location' );
        $term_plural   = esc_html( mlb_term('plural') );

        echo '<div class="wrap mlb-dashboard"><h1>Media Lab Bookings</h1><div class="mlb-stats">';
        echo '<a href="' . esc_url( $url_pending ) . '" class="mlb-stat mlb-stat--pending"><span class="mlb-stat__count">' . (int)$pending . '</span><span class="mlb-stat__label">' . $term_plural . ' – Ausstehend</span></a>';
        echo '<a href="' . esc_url( $url_confirmed ) . '" class="mlb-stat mlb-stat--confirmed"><span class="mlb-stat__count">' . (int)$confirmed . '</span><span class="mlb-stat__label">' . $term_plural . ' – Bestätigt</span></a>';
        echo '<a href="' . esc_url( $url_cancelled ) . '" class="mlb-stat mlb-stat--cancelled"><span class="mlb-stat__count">' . (int)$cancelled . '</span><span class="mlb-stat__label">' . $term_plural . ' – Storniert</span></a>';
        echo '<a href="' . esc_url( $url_locations ) . '" class="mlb-stat mlb-stat--locations"><span class="mlb-stat__count">' . (int)$locations . '</span><span class="mlb-stat__label">Standorte</span></a>';
        echo '</div>';
        echo '<p>';
        echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=mlb_booking' ) ) . '" class="button button-primary">Alle Buchungen</a> ';
        echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=mlb_booking' ) ) . '" class="button button-primary">+ Neue ' . esc_html( mlb_term('singular') ) . '</a> ';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=mlb-calendar' ) ) . '" class="button">Kalender</a> ';
        echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=mlb_location' ) ) . '" class="button">Standorte</a>';
        echo '</p>';

        // iCal-Feed-URLs
        if ( class_exists( 'MLB_Feed' ) ) {
            $locations = get_posts( [ 'post_type' => 'mlb_location', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
            echo '<h3 style="margin-top:24px">📅 Kalender abonnieren</h3>';
            echo '<p style="color:#666;font-size:13px">Diese URLs in Google Calendar, Apple Calendar oder Outlook als Abonnement hinzufügen:</p>';
            echo '<table class="widefat" style="max-width:700px"><thead><tr><th>Standort</th><th>Feed-URL</th></tr></thead><tbody>';
            echo '<tr><td><strong>Alle Standorte</strong></td><td><code style="font-size:11px;word-break:break-all">' . esc_html( MLB_Feed::get_feed_url() ) . '</code></td></tr>';
            foreach ( $locations as $loc ) {
                echo '<tr><td>' . esc_html( $loc->post_title ) . '</td>';
                echo '<td><code style="font-size:11px;word-break:break-all">' . esc_html( MLB_Feed::get_feed_url( $loc->ID ) ) . '</code></td></tr>';
            }
            echo '</tbody></table>';
            echo '<p style="font-size:12px;color:#888;margin-top:8px">💡 Der <code>token</code>-Parameter schützt den Feed vor unbefugtem Zugriff. URL nicht öffentlich teilen.</p>';
        }
        // Migrations-Tool
        $needs_sync = self::count_unsynced_bookings();
        if ( $needs_sync > 0 ) {
            $nonce = wp_create_nonce( 'mlb_sync_statuses' );
            echo '<div style="margin-top:24px;padding:16px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px">';
            echo '<strong>⚠️ ' . (int) $needs_sync . ' Buchung(en) haben einen veralteten WP-Post-Status.</strong> ';
            echo 'Das führt zu falschen Zählern in der Buchungsliste. ';
            echo '<button type="button" class="button button-secondary" id="mlb-sync-btn" style="margin-left:8px">Jetzt synchronisieren</button>';
            echo '<span id="mlb-sync-result" style="margin-left:12px;color:#666"></span>';
            echo '</div>';
            echo '<script>
                jQuery(document).ready(function($){
                    $("#mlb-sync-btn").on("click", function(){
                        var $btn = $(this);
                        $btn.prop("disabled", true).text("Synchronisiere…");
                        $.post(ajaxurl, {
                            action: "mlb_sync_statuses",
                            nonce:  "' . $nonce . '"
                        }, function(res){
                            if (res.success) {
                                $("#mlb-sync-result").css("color","#155724").text(res.data);
                                $btn.closest("div").delay(2000).fadeOut(500);
                            } else {
                                $("#mlb-sync-result").css("color","#721c24").text(res.data);
                                $btn.prop("disabled", false).text("Jetzt synchronisieren");
                            }
                        });
                    });
                });
            </script>';
        }
        echo '</div>';
    }
    public static function booking_columns( array $cols ): array {
        return [ 'cb' => '<input type="checkbox">', 'title' => 'Buchung', 'mlb_status' => 'Status', 'mlb_location' => 'Standort', 'mlb_date' => 'Datum', 'mlb_time' => 'Uhrzeit', 'mlb_customer' => 'Kunde', 'mlb_service' => 'Dienstleistung', 'mlb_persons' => 'Pers.', 'date' => 'Eingegangen' ];
    }
    public static function booking_column_content( string $col, int $post_id ): void {
        switch ( $col ) {
            case 'mlb_status':
                $status = get_post_meta( $post_id, 'mlb_booking_status', true ) ?: 'mlb-pending';
                $labels = [ 'mlb-pending' => [ 'Ausstehend', 'mlb-badge--pending' ], 'mlb-confirmed' => [ 'Bestätigt', 'mlb-badge--confirmed' ], 'mlb-cancelled' => [ 'Storniert', 'mlb-badge--cancelled' ] ];
                [ $label, $class ] = $labels[ $status ] ?? [ $status, '' ];
                echo '<span class="mlb-badge ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>'; break;
            case 'mlb_location': $loc_id = get_post_meta( $post_id, 'mlb_booking_location', true ); echo $loc_id ? esc_html( get_the_title( (int)$loc_id ) ) : '—'; break;
            case 'mlb_date':     $date = get_post_meta( $post_id, 'mlb_booking_date', true ); echo $date ? esc_html( date_i18n( 'd.m.Y', strtotime( $date ) ) ) : '—'; break;
            case 'mlb_time':     $time = get_post_meta( $post_id, 'mlb_booking_time', true ); echo $time ? esc_html( $time ) . ' Uhr' : '—'; break;
            case 'mlb_customer':
                $name = get_post_meta( $post_id, 'mlb_booking_name', true ); $email = get_post_meta( $post_id, 'mlb_booking_email', true ); $phone = get_post_meta( $post_id, 'mlb_booking_phone', true );
                echo esc_html( $name ); if ( $email ) echo '<br><small><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a></small>'; if ( $phone ) echo '<br><small>' . esc_html($phone) . '</small>'; break;
            case 'mlb_service':  $s = get_post_meta( $post_id, 'mlb_booking_service', true ); echo $s ? esc_html($s) : '—'; break;
            case 'mlb_persons':  $p = get_post_meta( $post_id, 'mlb_booking_persons', true ); echo $p ? esc_html($p) : '1'; break;
        }
    }
    public static function booking_sortable_columns( array $cols ): array { $cols['mlb_date'] = 'mlb_date'; $cols['mlb_status'] = 'mlb_status'; return $cols; }
    public static function booking_orderby( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'mlb_booking' ) return;
        if ( $query->get( 'orderby' ) === 'mlb_date' ) { $query->set( 'meta_key', 'mlb_booking_date' ); $query->set( 'orderby', 'meta_value' ); }
    }
    public static function booking_filters( string $post_type ): void {
        if ( get_current_screen()->post_type !== 'mlb_booking' ) return;
        $locations = get_posts( [ 'post_type' => 'mlb_location', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $selected  = sanitize_text_field( $_GET['mlb_filter_location'] ?? '' );
        echo '<select name="mlb_filter_location"><option value="">Alle Standorte</option>';
        foreach ( $locations as $loc ) { printf( '<option value="%d"%s>%s</option>', $loc->ID, selected( $selected, $loc->ID, false ), esc_html( $loc->post_title ) ); }
        echo '</select>';
        $statuses = [ '' => 'Alle Status', 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt', 'mlb-cancelled' => 'Storniert' ];
        $sel_stat = sanitize_text_field( $_GET['mlb_filter_status'] ?? '' );
        echo '<select name="mlb_filter_status">';
        foreach ( $statuses as $val => $label ) { printf( '<option value="%s"%s>%s</option>', esc_attr($val), selected($sel_stat,$val,false), esc_html($label) ); }
        echo '</select>';
    }
    public static function filter_bookings( \WP_Query $query ): void {
        global $pagenow;
        if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() || ( $query->get('post_type') ?: '' ) !== 'mlb_booking' ) return;
        $meta_query = [];
        $loc = (int) sanitize_text_field( $_GET['mlb_filter_location'] ?? 0 );
        if ( $loc ) $meta_query[] = [ 'key' => 'mlb_booking_location', 'value' => $loc ];
        $status = sanitize_text_field( $_GET['mlb_filter_status'] ?? '' );
        if ( $status ) $meta_query[] = [ 'key' => 'mlb_booking_status', 'value' => $status ];
        if ( $meta_query ) $query->set( 'meta_query', $meta_query );
    }
    public static function location_columns( array $cols ): array {
        return [ 'cb' => '<input type="checkbox">', 'title' => 'Standort', 'mlb_loc_email' => 'Filial-E-Mail', 'mlb_loc_slots' => 'Slot-Dauer', 'mlb_loc_cap' => 'Kapazität/Slot', 'mlb_loc_count' => 'Buchungen gesamt', 'date' => 'Erstellt' ];
    }
    public static function location_column_content( string $col, int $post_id ): void {
        switch( $col ) {
            case 'mlb_loc_email': $e = get_field('mlb_location_email',$post_id); echo $e ? '<a href="mailto:'.esc_attr($e).'">'.esc_html($e).'</a>' : '—'; break;
            case 'mlb_loc_slots': $d = get_field('mlb_slot_duration',$post_id); echo $d ? esc_html($d).' Min.' : '60 Min.'; break;
            case 'mlb_loc_cap':   $c = get_field('mlb_max_capacity',$post_id); echo $c ? esc_html($c).' Buchung(en)' : '1'; break;
            case 'mlb_loc_count': $q = new WP_Query(['post_type'=>'mlb_booking','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'mlb_booking_location','value'=>$post_id]]]); echo (int)$q->found_posts; break;
        }
    }
    public static function booking_post_states( array $states, \WP_Post $post ): array {
        if ( $post->post_type !== 'mlb_booking' ) return $states;
        $labels = [ 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt', 'mlb-cancelled' => 'Storniert' ];
        if ( isset( $labels[ $post->post_status ] ) ) $states[ $post->post_status ] = $labels[ $post->post_status ];
        return $states;
    }
    public static function enqueue_styles(): void {
        wp_add_inline_style( 'wp-admin', '
            .mlb-badge{display:inline-block;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
            .mlb-badge--pending{background:#fff3cd;color:#856404}.mlb-badge--confirmed{background:#d4edda;color:#155724}.mlb-badge--cancelled{background:#f8d7da;color:#721c24}
            .mlb-stats{display:flex;gap:16px;margin:20px 0;flex-wrap:wrap}.mlb-stat{background:#fff;border:1px solid #e2e4e7;border-radius:6px;padding:20px 28px;min-width:120px;text-align:center}
            .mlb-stat__count{display:block;font-size:36px;font-weight:700;line-height:1}.mlb-stat__label{display:block;font-size:12px;color:#666;margin-top:6px;text-transform:uppercase;letter-spacing:.5px}
            .mlb-stat--pending .mlb-stat__count{color:#856404}.mlb-stat--confirmed .mlb-stat__count{color:#155724}.mlb-stat--cancelled .mlb-stat__count{color:#721c24}.mlb-stat--locations .mlb-stat__count{color:#0073aa}
            a.mlb-stat{text-decoration:none;cursor:pointer;transition:box-shadow .15s,transform .1s}
            a.mlb-stat:hover{box-shadow:0 4px 12px rgba(0,0,0,.1);transform:translateY(-1px)}
        ' );
    }
}
MLB_Admin::init();
