<?php
/**
 * SEO Report Mailer
 *
 * Wöchentlicher Versand des SEO-Reports per HTML-Mail.
 * Nutzt WP-Cron für zeitgesteuerte Ausführung.
 *
 * @package MediaLab_SEO
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Cron-Hook registrieren
// ---------------------------------------------------------------------------

add_action( 'medialab_seo_weekly_report', 'medialab_seo_send_report' );

// ---------------------------------------------------------------------------
// Cron planen / neu planen
// ---------------------------------------------------------------------------

/**
 * Plant den wöchentlichen Report-Cron.
 * Wird aufgerufen wenn Einstellungen gespeichert werden.
 */
function medialab_seo_reschedule_report(): void {
    // Bestehenden Event entfernen
    $timestamp = wp_next_scheduled( 'medialab_seo_weekly_report' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'medialab_seo_weekly_report' );
    }

    // Nur planen wenn aktiviert + GSC verbunden
    if ( get_option( 'medialab_report_enabled', '0' ) !== '1' ) return;
    if ( ! medialab_gsc_is_connected() ) return;

    $next = medialab_seo_next_send_timestamp();
    if ( $next ) {
        wp_schedule_event( $next, 'weekly', 'medialab_seo_weekly_report' );
    }
}

/**
 * Berechnet den nächsten Versand-Timestamp basierend auf Tag + Uhrzeit.
 */
function medialab_seo_next_send_timestamp(): int {
    $day  = get_option( 'medialab_report_day',  'monday' );
    $time = get_option( 'medialab_report_time', '08:00' );

    [ $hour, $minute ] = array_map( 'intval', explode( ':', $time ) );

    // Nächsten passenden Wochentag finden
    $days_map = [
        'monday'    => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday'  => 4, 'friday'  => 5, 'saturday'  => 6, 'sunday' => 0,
    ];
    $target_dow = $days_map[ $day ] ?? 1;
    $current_dow = (int) date( 'N' ) % 7; // 0=Sun … 6=Sat (PHP N: 1=Mon,7=Sun)
    $current_dow_js = (int) date( 'w' );  // 0=Sun,1=Mon…6=Sat

    $diff = ( $target_dow - $current_dow_js + 7 ) % 7;
    if ( $diff === 0 ) {
        // Heute – aber nur wenn Uhrzeit noch nicht erreicht
        $today_ts = mktime( $hour, $minute, 0 );
        if ( $today_ts <= time() ) $diff = 7;
    }

    return mktime( $hour, $minute, 0, (int) date( 'n' ), (int) date( 'j' ) + $diff );
}

// ---------------------------------------------------------------------------
// Plugin-Aktivierung / Deaktivierung
// ---------------------------------------------------------------------------

add_action( 'medialab_seo_activated', 'medialab_seo_reschedule_report' );

add_action( 'medialab_seo_deactivated', function () {
    $timestamp = wp_next_scheduled( 'medialab_seo_weekly_report' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'medialab_seo_weekly_report' );
    }
} );

// ---------------------------------------------------------------------------
// Report senden
// ---------------------------------------------------------------------------

/**
 * Sendet den SEO-Report per E-Mail.
 *
 * @return bool  true bei Erfolg
 */
function medialab_seo_send_report(): bool {
    if ( ! medialab_gsc_is_configured() || ! medialab_gsc_is_connected() ) {
        return false;
    }

    $recipient  = get_option( 'medialab_report_recipient', get_option( 'admin_email' ) );
    $from_name  = get_option( 'medialab_report_from_name',  get_bloginfo( 'name' ) );
    $from_email = get_option( 'medialab_report_from_email', get_option( 'admin_email' ) );
    $site       = get_bloginfo( 'name' );

    if ( empty( $recipient ) ) return false;

    // Daten abrufen
    $data = medialab_gsc_get_dashboard_data();

    // HTML-Template rendern
    $html = medialab_seo_report_html( $data, $site );
    if ( empty( $html ) ) return false;

    // Mail-Header
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        sprintf( 'From: %s <%s>', $from_name, $from_email ),
    ];

    // Betreff
    $subject = sprintf(
        'SEO Report: %s · %s – %s',
        $site,
        date_i18n( 'd.m.', strtotime( $data['period']['start'] ?? '-28 days' ) ),
        date_i18n( 'd.m.Y', strtotime( $data['period']['end']  ?? 'today' ) )
    );

    $result = wp_mail( $recipient, $subject, $html, $headers );

    // Log-Eintrag
    update_option( 'medialab_report_last_sent', [
        'time'      => time(),
        'recipient' => $recipient,
        'success'   => $result,
    ] );

    return $result;
}

// ---------------------------------------------------------------------------
// Letzten Versand-Status abrufen (für Dashboard-Anzeige)
// ---------------------------------------------------------------------------

function medialab_seo_report_last_sent(): array {
    return get_option( 'medialab_report_last_sent', [] );
}

function medialab_seo_report_next_send(): string {
    $timestamp = wp_next_scheduled( 'medialab_seo_weekly_report' );
    if ( ! $timestamp ) return 'Nicht geplant';
    return date_i18n( 'D, d.m.Y \u\m H:i', $timestamp );
}
