<?php
/**
 * Dark Mode Toggle – Frontend-Steuerung
 *
 * Liest die ACF-Option `dark_mode_enabled` aus Agency Core →
 * Logo / Globale Einstellungen.
 *
 * Wenn deaktiviert:
 *  1. Frühes Inline-Script im <head> (Prio 1) erzwingt Light Mode
 *     bevor das Theme-Bundle lädt → kein Flash of Dark Content
 *  2. Inline-CSS versteckt den Toggle-Button (.theme-toggle)
 *
 * Wenn aktiviert: kein Eingriff nötig – ThemeSwitcher läuft normal.
 *
 * @package MediaLabAgencyCore
 * @since   1.12.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', function (): void {
    // Kein Eingriff wenn ACF nicht verfügbar
    if ( ! function_exists( 'get_field' ) ) return;

    $enabled = get_field( 'dark_mode_enabled', 'option' );

    // Standard: aktiviert (null / leeres Feld = nicht gesetzt → Standard 1)
    if ( $enabled === null || $enabled === false || $enabled === '' ) {
        $enabled = true; // Default-Wert aus ACF-Feld
    }

    if ( $enabled ) return; // Nichts tun wenn aktiv

    // ── Dark Mode deaktiviert ──────────────────────────────────────────────────

    // 1. Frühes Script: erzwingt Light Mode vor dem Bundle
    //    – löscht gespeicherte User-Präferenz
    //    – setzt data-theme="light" auf <html>
    echo '<script id="ml-dark-mode-disable">'
       . '(function(){'
       . 'try{localStorage.removeItem("theme-preference");}catch(e){}'
       . 'document.documentElement.setAttribute("data-theme","light");'
       . '})();'
       . '</script>' . "\n";

    // 2. CSS: Toggle-Button verstecken
    echo '<style id="ml-dark-mode-hide">'
       . '.theme-toggle{display:none!important;}'
       . '</style>' . "\n";

}, 1 ); // Priorität 1 = sehr früh, vor allen anderen wp_head Hooks
