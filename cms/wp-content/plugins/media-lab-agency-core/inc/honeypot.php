<?php
/**
 * Honeypot Spam Protection
 *
 * Zwei Schutzschichten ohne externe Requests, Cookies oder personenbezogene Daten.
 * DSGVO-konform: alle Prüfungen laufen ausschließlich serverseitig.
 *
 * Schicht 1 – Honeypot-Feld:
 *   Für echte Nutzer via CSS vollständig unsichtbar (absolute Positionierung
 *   außerhalb des Viewports). Bots, die alle sichtbaren Felder befüllen,
 *   verraten sich durch das gefüllte Honeypot-Feld.
 *   Wichtig: Kein display:none / visibility:hidden – fortgeschrittene Bots
 *   erkennen diese CSS-Properties. Off-Screen-Positionierung ist zuverlässiger.
 *
 * Schicht 2 – Time-Check:
 *   Beim Rendern des Formulars wird ein HMAC-signierter Zeitstempel erzeugt.
 *   Wird das Formular in weniger als MEDIALAB_HP_MIN_TIME Sekunden abgeschickt,
 *   stammt die Einreichung mit hoher Wahrscheinlichkeit von einem Bot.
 *   Abgelaufene Formulare (> max_age, für gecachte Seiten großzügig) werden
 *   ebenfalls abgelehnt und der Nutzer aufgefordert, die Seite neu zu laden.
 *
 * Konfiguration:
 *   Alle Parameter sind in Agency Core → Spam-Schutz konfigurierbar.
 *   Konstanten (z.B. in wp-config.php gesetzt) überschreiben ACF-Werte –
 *   damit bleiben bestehende Projekte, die Konstanten nutzen, kompatibel.
 *
 * Integration:
 *   – CF7:            Automatisch via wpcf7_form_elements + wpcf7_spam Filter.
 *   – Eigene Formulare: medialab_honeypot_render() + medialab_honeypot_check()
 *
 * @package  media-lab-agency-core
 * @since    1.8.6
 */

defined( 'ABSPATH' ) || exit;

// ── Konfiguration ─────────────────────────────────────────────────────────────
// Konstanten als Hard-Override (für wp-config.php), ACF als konfigurierbarer Default.

/** Honeypot-Feldname – klingt für Bots wie ein echtes Pflichtfeld. */
if ( ! defined( 'MEDIALAB_HP_FIELD' ) ) {
    define( 'MEDIALAB_HP_FIELD', '_ml_website' );
}

/** Verstecktes Zeitstempel-Feld. */
if ( ! defined( 'MEDIALAB_HP_TS_FIELD' ) ) {
    define( 'MEDIALAB_HP_TS_FIELD', '_ml_form_ts' );
}

/** Mindest-Ausfüllzeit in Sekunden (echte Nutzer brauchen länger). */
if ( ! defined( 'MEDIALAB_HP_MIN_TIME' ) ) {
    define( 'MEDIALAB_HP_MIN_TIME', null ); // null = ACF-Wert verwenden
}

/**
 * Maximale Formular-Gültigkeitsdauer in Sekunden.
 * 24 h – großzügig gewählt, damit gecachte Seiten nicht sofort ablaufen.
 */
if ( ! defined( 'MEDIALAB_HP_MAX_AGE' ) ) {
    define( 'MEDIALAB_HP_MAX_AGE', null ); // null = ACF-Wert verwenden
}

// ── Laufzeit-Konfiguration ────────────────────────────────────────────────────

/**
 * Gibt die effektive Mindest-Ausfüllzeit zurück.
 * Priorität: Konstante (wenn nicht null) → ACF-Option → Fallback 3 s.
 */
function medialab_hp_min_time(): int {
    if ( MEDIALAB_HP_MIN_TIME !== null ) {
        return (int) MEDIALAB_HP_MIN_TIME;
    }
    if ( function_exists( 'get_field' ) ) {
        $val = get_field( 'honeypot_min_time', 'option' );
        if ( $val !== null && $val !== '' && $val !== false ) {
            return max( 1, (int) $val );
        }
    }
    return 3;
}

/**
 * Gibt das maximale Formular-Alter zurück.
 * Priorität: Konstante (wenn nicht null) → ACF-Option → Fallback 86400 s (24 h).
 */
function medialab_hp_max_age(): int {
    if ( MEDIALAB_HP_MAX_AGE !== null ) {
        return (int) MEDIALAB_HP_MAX_AGE;
    }
    if ( function_exists( 'get_field' ) ) {
        $val = get_field( 'honeypot_max_age', 'option' );
        if ( $val !== null && $val !== '' && $val !== false ) {
            return max( 60, (int) $val );
        }
    }
    return 86400;
}

// ── HTML-Ausgabe ──────────────────────────────────────────────────────────────

/**
 * Gibt die Honeypot-Felder als HTML-String zurück.
 *
 * Enthält:
 *  1. Ein für echte Nutzer via CSS unsichtbares Text-Feld (Honeypot-Falle).
 *  2. Ein verstecktes Zeitstempel-Feld mit HMAC-Signatur.
 *
 * Das Markup setzt aria-hidden="true" und tabindex="-1", damit
 * Screen Reader und Tab-Navigation das Feld vollständig ignorieren.
 * Das CSS-Hiding (.ml-hp) muss im Theme-Stylesheet vorhanden sein.
 *
 * @return string HTML der Honeypot-Felder.
 */
function medialab_honeypot_render(): string {
    $ts    = time();
    $token = wp_hash( $ts . '|' . wp_salt( 'nonce' ) . '|' . get_bloginfo( 'url' ) );

    return sprintf(
        '<div class="ml-hp" aria-hidden="true" tabindex="-1">' .
            '<label for="%1$s">Website</label>' .
            '<input type="text" name="%1$s" id="%1$s" value="" autocomplete="off" tabindex="-1" aria-hidden="true">' .
            '<input type="hidden" name="%2$s" value="%3$s|%4$s">' .
        '</div>',
        esc_attr( MEDIALAB_HP_FIELD ),
        esc_attr( MEDIALAB_HP_TS_FIELD ),
        esc_attr( (string) $ts ),
        esc_attr( $token )
    );
}

// ── Serverseitige Prüfung ─────────────────────────────────────────────────────

/**
 * Prüft die Honeypot-Felder aus $_POST.
 *
 * Gibt true zurück wenn die Einreichung legitim wirkt.
 * Gibt WP_Error zurück wenn Spam erkannt oder die Einreichung ungültig ist.
 *
 * Fehlercodes:
 *   hp_missing       – Honeypot-Feld fehlt im POST (direkte AJAX-Anfrage)
 *   hp_filled        – Honeypot-Feld ist befüllt → Spam
 *   hp_ts_missing    – Zeitstempel-Feld fehlt
 *   hp_ts_malformed  – Zeitstempel-Format ungültig
 *   hp_ts_invalid    – HMAC-Signatur stimmt nicht
 *   hp_too_fast      – Unter medialab_hp_min_time() Sekunden abgeschickt
 *   hp_expired       – Formular älter als medialab_hp_max_age() Sekunden
 *
 * @return true|\WP_Error
 */
function medialab_honeypot_check(): true|\WP_Error {

    // 1. Honeypot-Feld muss vorhanden UND leer sein.
    if ( ! array_key_exists( MEDIALAB_HP_FIELD, $_POST ) ) {
        return new \WP_Error( 'hp_missing', 'Invalid form submission.' );
    }

    if ( '' !== (string) $_POST[ MEDIALAB_HP_FIELD ] ) {
        return new \WP_Error( 'hp_filled', 'Spam detected.' );
    }

    // 2. Zeitstempel-Feld: Format, Signatur und Zeitfenster prüfen.
    $raw = sanitize_text_field( wp_unslash( $_POST[ MEDIALAB_HP_TS_FIELD ] ?? '' ) );

    if ( '' === $raw ) {
        return new \WP_Error( 'hp_ts_missing', 'Invalid form submission.' );
    }

    $parts = explode( '|', $raw, 2 );

    if ( 2 !== count( $parts ) ) {
        return new \WP_Error( 'hp_ts_malformed', 'Invalid form submission.' );
    }

    [ $ts_str, $token ] = $parts;
    $ts = (int) $ts_str;

    // HMAC-Signatur timing-safe verifizieren.
    $expected = wp_hash( $ts . '|' . wp_salt( 'nonce' ) . '|' . get_bloginfo( 'url' ) );
    if ( ! hash_equals( $expected, $token ) ) {
        return new \WP_Error( 'hp_ts_invalid', 'Invalid form submission.' );
    }

    $elapsed = time() - $ts;

    if ( $elapsed < medialab_hp_min_time() ) {
        return new \WP_Error(
            'hp_too_fast',
            __( 'Das Formular wurde zu schnell abgeschickt. Bitte versuche es erneut.', 'media-lab' )
        );
    }

    if ( $elapsed > medialab_hp_max_age() ) {
        return new \WP_Error(
            'hp_expired',
            __( 'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.', 'media-lab' )
        );
    }

    return true;
}

// ── Contact Form 7 Integration ────────────────────────────────────────────────

/**
 * Honeypot-Felder ans Ende jedes CF7-Formulars anhängen.
 */
add_filter( 'wpcf7_form_elements', function ( string $html ): string {
    return $html . medialab_honeypot_render();
} );

/**
 * CF7-Submission auf Spam prüfen.
 */
add_filter( 'wpcf7_spam', function ( bool $spam ): bool {
    if ( $spam ) {
        return true;
    }
    return is_wp_error( medialab_honeypot_check() );
}, 5 );
