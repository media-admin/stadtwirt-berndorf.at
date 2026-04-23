<?php
/**
 * Status-E-Mails, Erinnerungs-Cron, Stornierung via Link
 *
 * Status-E-Mails:
 *   - Bei Statuswechsel auf mlb-confirmed → Bestätigungsmail an Kunden
 *   - Bei Statuswechsel auf mlb-cancelled → Stornierungsmail an Kunden
 *
 * Erinnerungs-E-Mail:
 *   - WP-Cron-Job wird beim Speichern einer Buchung geplant
 *   - Versand X Stunden vor dem Termin (konfigurierbar per ACF pro Standort)
 *
 * Stornierung via Link:
 *   - Token wird beim Erstellen der Buchung generiert (post_meta)
 *   - Öffentlicher AJAX-Endpunkt setzt Status auf mlb-cancelled
 *   - Link wird in Bestätigungsmail eingefügt via Platzhalter {cancel_url}
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Notifications {

    public static function init(): void {
        // Status-Änderung erkennen:
        // Priorität 5 = VOR ACF-Speicherung: alten Wert aus DB sichern
        // Priorität 20 = NACH ACF-Speicherung: neuen Wert aus DB lesen + vergleichen
        add_action( 'acf/save_post', [ __CLASS__, 'capture_old_status' ], 5  );
        add_action( 'acf/save_post', [ __CLASS__, 'on_acf_save' ],        20 );

        // Erinnerungs-Cron
        add_action( 'mlb_send_reminder',    [ __CLASS__, 'send_reminder' ] );
        add_filter( 'cron_schedules',       [ __CLASS__, 'add_cron_schedule' ] );

        // Stornierung via Link
        add_action( 'wp_ajax_mlb_cancel_booking',        [ __CLASS__, 'ajax_cancel' ] );
        add_action( 'wp_ajax_nopriv_mlb_cancel_booking', [ __CLASS__, 'ajax_cancel' ] );

        // Stornierungstoken beim Erstellen generieren
        add_action( 'mlb_after_save_booking', [ __CLASS__, 'generate_cancel_token' ], 10, 2 );
    }

    // ── ACF Save: alten Status VOR der Speicherung sichern (Priorität 5) ───────

    public static function capture_old_status( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'mlb_booking' ) return;

        // Aktuellen DB-Wert als Vergleichsbasis sichern (bevor ACF überschreibt)
        $current = get_post_meta( $post_id, 'mlb_booking_status', true ) ?: 'mlb-pending';
        update_post_meta( $post_id, '_mlb_previous_status', $current );
    }

    // ── ACF Save: Statuswechsel erkennen NACH der Speicherung (Priorität 20) ──

    public static function on_acf_save( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'mlb_booking' ) return;

        // Neuen Status direkt aus der DB lesen (ACF hat bereits gespeichert)
        $new_status = get_post_meta( $post_id, 'mlb_booking_status', true );
        if ( ! $new_status ) return;

        // WP-Post-Status IMMER synchronisieren – auch wenn kein Statuswechsel stattfand.
        // WordPress überschreibt den WP-Post-Status bei jedem Backend-Save mit 'publish'.
        // Ohne diese Korrektur laufen WP-Post-Status und ACF-Meta auseinander.
        $wp_status = get_post_field( 'post_status', $post_id );
        if ( $wp_status !== $new_status ) {
            // remove_action verhindert eine Endlosschleife (wp_update_post → acf/save_post)
            remove_action( 'acf/save_post', [ __CLASS__, 'on_acf_save' ], 20 );
            wp_update_post( [ 'ID' => $post_id, 'post_status' => $new_status ] );
            add_action( 'acf/save_post', [ __CLASS__, 'on_acf_save' ], 20 );
        }

        // Alten Status aus unserem Snapshot (gesetzt von capture_old_status)
        $old_status = get_post_meta( $post_id, '_mlb_previous_status', true );

        // Mails + Cron nur bei echtem Statuswechsel auslösen
        if ( $old_status === $new_status ) return;

        switch ( $new_status ) {
            case 'mlb-confirmed':
                self::send_status_mail( $post_id, 'confirmed' );
                self::schedule_reminder( $post_id );
                break;

            case 'mlb-cancelled':
                self::send_status_mail( $post_id, 'cancelled' );
                wp_clear_scheduled_hook( 'mlb_send_reminder', [ $post_id ] );
                break;
        }
    }

    // ── Status-E-Mail versenden ───────────────────────────────────────────────

    public static function send_status_mail( int $booking_id, string $type ): void {
        $location_id    = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $customer_email = sanitize_email( get_post_meta( $booking_id, 'mlb_booking_email', true ) );
        $location_email = sanitize_email( get_field( 'mlb_location_email', $location_id ) );

        if ( ! is_email( $customer_email ) ) return;

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        if ( $type === 'confirmed' ) {
            $subject_field  = 'mlb_confirmed_subject';
            $template_field = 'mlb_confirmed_template';
            $default_subject  = 'Ihre Buchung wurde bestätigt';
            $default_template = self::default_confirmed_template();
        } else {
            $subject_field  = 'mlb_cancelled_subject';
            $template_field = 'mlb_cancelled_template';
            $default_subject  = 'Ihre Buchung wurde storniert';
            $default_template = self::default_cancelled_template();
        }

        $raw_subject = get_field( $subject_field, $location_id );
        $subject     = $raw_subject ? wp_strip_all_tags( $raw_subject ) : $default_subject;
        $subject     = MLB_Mail::replace_placeholders_public( $subject, $booking_id );

        $template = get_field( $template_field, $location_id ) ?: $default_template;
        $body     = MLB_Mail::replace_placeholders_public( $template, $booking_id );
        $body     = MLB_Mail::wrap_html_public( $body );

        // iCal-Anhang nur bei Bestätigung
        $attachments = ( $type === 'confirmed' ) ? MLB_ICal::attachment( $booking_id ) : [];

        wp_mail( $customer_email, $subject, $body, $headers, $attachments );

        // Kopie an Filiale
        if ( $location_email ) {
            $admin_subject = sprintf( '[%s] Buchung #%d', $type === 'confirmed' ? 'Bestätigt' : 'Storniert', $booking_id );
            wp_mail( $location_email, $admin_subject, $body, $headers );
        }
    }

    // ── Standard-Templates ────────────────────────────────────────────────────

    private static function default_confirmed_template(): string {
        return '
            <p>Guten Tag {name},</p>
            <p>wir freuen uns, Ihnen mitteilen zu können, dass Ihre Buchung <strong>bestätigt</strong> wurde.</p>
            <h3>Ihre Buchungsdetails</h3>
            <table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">
                <tr style="background:#f9f9f9"><td><strong>Buchungsnummer</strong></td><td>{booking_id}</td></tr>
                <tr><td><strong>Standort</strong></td><td>{location_name}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Datum</strong></td><td>{date}</td></tr>
                <tr><td><strong>Uhrzeit</strong></td><td>{time}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Dienstleistung</strong></td><td>{service}</td></tr>
                <tr><td><strong>Personen</strong></td><td>{persons}</td></tr>
            </table>
            <p>Den Termin finden Sie im Anhang als Kalenderdatei (.ics).</p>
            <p>Möchten Sie den Termin absagen? <a href="{cancel_url}">Hier klicken zum Stornieren</a></p>
            <p>Mit freundlichen Grüßen,<br><strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong></p>';
    }

    private static function default_cancelled_template(): string {
        return '
            <p>Guten Tag {name},</p>
            <p>Ihre Buchung wurde <strong>storniert</strong>.</p>
            <h3>Stornierte Buchung</h3>
            <table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">
                <tr style="background:#f9f9f9"><td><strong>Buchungsnummer</strong></td><td>{booking_id}</td></tr>
                <tr><td><strong>Standort</strong></td><td>{location_name}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Datum</strong></td><td>{date}</td></tr>
                <tr><td><strong>Uhrzeit</strong></td><td>{time}</td></tr>
            </table>
            <p>Bei Fragen stehen wir Ihnen gerne zur Verfügung.</p>
            <p>Mit freundlichen Grüßen,<br><strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong></p>';
    }

    // ── Stornierungstoken generieren ──────────────────────────────────────────

    public static function generate_cancel_token( int $booking_id, array $data ): void {
        $token = bin2hex( random_bytes( 32 ) );
        update_post_meta( $booking_id, '_mlb_cancel_token', $token );
    }

    public static function get_cancel_url( int $booking_id ): string {
        $token = get_post_meta( $booking_id, '_mlb_cancel_token', true );
        if ( ! $token ) return '';

        return add_query_arg( [
            'action'     => 'mlb_cancel_booking',
            'booking_id' => $booking_id,
            'token'      => $token,
        ], admin_url( 'admin-ajax.php' ) );
    }

    // ── AJAX: Stornierung via Link ────────────────────────────────────────────

    public static function ajax_cancel(): void {
        $booking_id = (int) sanitize_text_field( $_GET['booking_id'] ?? 0 );
        $token      = sanitize_text_field( $_GET['token'] ?? '' );

        if ( ! $booking_id || ! $token ) {
            wp_die( 'Ungültiger Stornierungslink.', 400 );
        }

        $saved_token = get_post_meta( $booking_id, '_mlb_cancel_token', true );

        if ( ! hash_equals( $saved_token, $token ) ) {
            wp_die( 'Dieser Stornierungslink ist ungültig oder wurde bereits verwendet.', 403 );
        }

        $current_status = get_post_meta( $booking_id, 'mlb_booking_status', true );
        if ( $current_status === 'mlb-cancelled' ) {
            wp_die( 'Diese Buchung wurde bereits storniert.', 200 );
        }

        // Status setzen
        update_field( 'mlb_booking_status', 'mlb-cancelled', $booking_id );
        wp_update_post( [ 'ID' => $booking_id, 'post_status' => 'mlb-cancelled' ] );

        // Token invalidieren (Einmalverwendung)
        delete_post_meta( $booking_id, '_mlb_cancel_token' );

        // Stornierungsmail versenden
        self::send_status_mail( $booking_id, 'cancelled' );

        // Erinnerungs-Cron entfernen
        wp_clear_scheduled_hook( 'mlb_send_reminder', [ $booking_id ] );

        wp_die(
            '<h2>Buchung storniert</h2><p>Ihre Buchung #' . $booking_id . ' wurde erfolgreich storniert. Sie erhalten eine Bestätigung per E-Mail.</p>',
            'Buchung storniert',
            [ 'response' => 200 ]
        );
    }

    // ── Erinnerungs-Cron planen ───────────────────────────────────────────────

    public static function schedule_reminder( int $booking_id ): void {
        $location_id = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $hours_before = (int) ( get_field( 'mlb_reminder_hours', $location_id ) ?: 24 );

        if ( $hours_before <= 0 ) return;

        $date = get_post_meta( $booking_id, 'mlb_booking_date', true );
        $time = get_post_meta( $booking_id, 'mlb_booking_time', true );

        if ( ! $date || ! $time ) return;

        $tz          = wp_timezone();
        $dt_booking  = new DateTime( $date . ' ' . $time, $tz );
        $dt_reminder = clone $dt_booking;
        $dt_reminder->modify( "-{$hours_before} hours" );

        // Nur planen wenn Erinnerungszeitpunkt in der Zukunft liegt
        if ( $dt_reminder->getTimestamp() <= time() ) return;

        // Bestehenden Hook entfernen um Duplikate zu vermeiden
        wp_clear_scheduled_hook( 'mlb_send_reminder', [ $booking_id ] );
        wp_schedule_single_event( $dt_reminder->getTimestamp(), 'mlb_send_reminder', [ $booking_id ] );
    }

    public static function add_cron_schedule( array $schedules ): array {
        return $schedules; // Kein eigener Rhythmus nötig (single events)
    }

    // ── Erinnerungs-Mail versenden (Cron-Callback) ────────────────────────────

    public static function send_reminder( int $booking_id ): void {
        $status = get_post_meta( $booking_id, 'mlb_booking_status', true );

        // Nur senden wenn Buchung noch aktiv
        if ( in_array( $status, [ 'mlb-cancelled' ], true ) ) return;

        $location_id    = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $customer_email = sanitize_email( get_post_meta( $booking_id, 'mlb_booking_email', true ) );

        if ( ! is_email( $customer_email ) ) return;

        $raw_subject = get_field( 'mlb_reminder_subject', $location_id );
        $subject     = $raw_subject ? wp_strip_all_tags( $raw_subject ) : 'Erinnerung: Ihr Termin morgen';
        $subject     = MLB_Mail::replace_placeholders_public( $subject, $booking_id );

        $template = get_field( 'mlb_reminder_template', $location_id ) ?: self::default_reminder_template();
        $body     = MLB_Mail::replace_placeholders_public( $template, $booking_id );
        $body     = MLB_Mail::wrap_html_public( $body );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];

        $attachments = MLB_ICal::attachment( $booking_id );
        wp_mail( $customer_email, $subject, $body, $headers, $attachments );
    }

    private static function default_reminder_template(): string {
        return '
            <p>Guten Tag {name},</p>
            <p>dies ist eine Erinnerung an Ihren bevorstehenden Termin.</p>
            <h3>Ihre Buchungsdetails</h3>
            <table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">
                <tr style="background:#f9f9f9"><td><strong>Datum</strong></td><td>{date}</td></tr>
                <tr><td><strong>Uhrzeit</strong></td><td>{time}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Standort</strong></td><td>{location_name}</td></tr>
                <tr><td><strong>Adresse</strong></td><td>{location_address}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Dienstleistung</strong></td><td>{service}</td></tr>
            </table>
            <p>Möchten Sie absagen? <a href="{cancel_url}">Hier klicken zum Stornieren</a></p>
            <p>Wir freuen uns auf Sie!<br><strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong></p>';
    }
}

MLB_Notifications::init();
