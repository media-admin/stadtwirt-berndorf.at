<?php
/**
 * Plugin-Einstellungen
 *
 * ACF-Optionsseite unter Bookings → Einstellungen.
 * Aktuell: Wording-Konfiguration (Ersatz für "Buchung" pro Projekt).
 *
 * Verwendung im Code:
 *   mlb_term( 'singular' )  → z.B. "Reservierung"
 *   mlb_term( 'plural' )    → z.B. "Reservierungen"
 *   mlb_term( 'new' )       → z.B. "Neue Reservierung"
 *   mlb_term( 'verb' )      → z.B. "Jetzt reservieren" (Button-Text)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Settings {

    public static function init(): void {
        add_action( 'acf/include_fields', [ __CLASS__, 'register_options_page' ] );
        add_action( 'admin_menu',         [ __CLASS__, 'add_settings_submenu' ], 30 );
    }

    // ── ACF Options Page ──────────────────────────────────────────────────────

    public static function register_options_page(): void {
        if ( ! function_exists( 'acf_add_options_page' ) ) return;

        acf_add_options_sub_page( [
            'page_title'  => 'Bookings Einstellungen',
            'menu_title'  => 'Einstellungen',
            'parent_slug' => 'mlb-bookings',
            'capability'  => 'manage_options',
            'menu_slug'   => 'mlb-settings',
            'autoload'    => true,
        ] );

        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        acf_add_local_field_group( [
            'key'      => 'group_mlb_settings',
            'title'    => 'Bookings Plugin-Einstellungen',
            'location' => [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'mlb-settings' ] ] ],
            'fields'   => [

                // ── Tab: Wording ─────────────────────────────────────────────
                [
                    'key'   => 'field_mlb_settings_tab_wording',
                    'label' => 'Wording',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'     => 'field_mlb_wording_info',
                    'label'   => '',
                    'name'    => '',
                    'type'    => 'message',
                    'message' => '<p>Ersetze den Begriff <strong>„Buchung"</strong> durch einen projektspezifischen Begriff (z.B. „Reservierung", „Termin", „Anfrage"). Leere Felder verwenden den Standard-Begriff.</p>',
                ],
                [
                    'key'           => 'field_mlb_term_singular',
                    'label'         => 'Singular',
                    'name'          => 'mlb_term_singular',
                    'type'          => 'text',
                    'placeholder'   => 'Buchung',
                    'instructions'  => 'Z.B. „Reservierung", „Termin", „Anfrage"',
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_mlb_term_plural',
                    'label'         => 'Plural',
                    'name'          => 'mlb_term_plural',
                    'type'          => 'text',
                    'placeholder'   => 'Buchungen',
                    'instructions'  => 'Z.B. „Reservierungen", „Termine", „Anfragen"',
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_mlb_term_verb',
                    'label'         => 'Verb / Button-Text',
                    'name'          => 'mlb_term_verb',
                    'type'          => 'text',
                    'placeholder'   => 'Buchung anfragen',
                    'instructions'  => 'Z.B. „Jetzt reservieren", „Termin anfragen"',
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_mlb_term_past',
                    'label'         => 'Vergangenheit',
                    'name'          => 'mlb_term_past',
                    'type'          => 'text',
                    'placeholder'   => 'Buchung eingereicht',
                    'instructions'  => 'Z.B. „Reservierung eingegangen", „Termin angefragt"',
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'     => 'field_mlb_term_preview',
                    'label'   => 'Vorschau',
                    'name'    => '',
                    'type'    => 'message',
                    'message' => self::term_preview_html(),
                ],

                // ── Tab: Formular-Standardwerte ──────────────────────────────
                [
                    'key'   => 'field_mlb_settings_tab_defaults',
                    'label' => 'Formular-Standardwerte',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'     => 'field_mlb_defaults_info',
                    'label'   => '',
                    'name'    => '',
                    'type'    => 'message',
                    'message' => 'Diese Werte gelten als globale Standardwerte wenn beim Standort kein eigenes Label hinterlegt ist.',
                ],
                [
                    'key'         => 'field_mlb_default_submit_label',
                    'label'       => 'Standard Button-Text',
                    'name'        => 'mlb_default_submit_label',
                    'type'        => 'text',
                    'placeholder' => 'Buchung anfragen',
                    'wrapper'     => [ 'width' => '50' ],
                ],
                [
                    'key'         => 'field_mlb_default_success_message',
                    'label'       => 'Standard Erfolgsmeldung',
                    'name'        => 'mlb_default_success_message',
                    'type'        => 'text',
                    'placeholder' => 'Ihre Buchung wurde erfolgreich eingereicht. Sie erhalten in Kürze eine Bestätigung per E-Mail.',
                    'wrapper'     => [ 'width' => '50' ],
                ],
            ],
        ] );
    }

    // ── Fallback-Menü falls ACF add_options_sub_page nicht verfügbar ──────────

    public static function add_settings_submenu(): void {
        if ( function_exists( 'acf_add_options_page' ) ) return;
        add_submenu_page( 'mlb-bookings', 'Einstellungen', 'Einstellungen', 'manage_options', 'mlb-settings', [ __CLASS__, 'fallback_page' ] );
    }

    public static function fallback_page(): void {
        echo '<div class="wrap"><h1>Bookings Einstellungen</h1><p>ACF Pro wird benötigt um die Einstellungen zu konfigurieren.</p></div>';
    }

    // ── Vorschau-HTML für Wording-Tab ─────────────────────────────────────────

    private static function term_preview_html(): string {
        $s = mlb_term( 'singular' );
        $p = mlb_term( 'plural' );
        $v = mlb_term( 'verb' );
        $t = mlb_term( 'past' );
        return '<table class="widefat" style="max-width:400px">
            <tr><td>Singular</td><td><strong>' . esc_html( $s ) . '</strong></td></tr>
            <tr><td>Plural</td><td><strong>' . esc_html( $p ) . '</strong></td></tr>
            <tr><td>Button</td><td><strong>' . esc_html( $v ) . '</strong></td></tr>
            <tr><td>Bestätigung</td><td><strong>' . esc_html( $t ) . '</strong></td></tr>
        </table><p style="color:#888;font-size:12px">Speichern zum Aktualisieren der Vorschau.</p>';
    }
}

MLB_Settings::init();

// ── Globale Hilfsfunktion: mlb_term() ─────────────────────────────────────────

/**
 * Gibt den konfigurierten Wording-Begriff zurück.
 *
 * @param string $type  'singular' | 'plural' | 'verb' | 'past'
 * @return string
 */
function mlb_term( string $type = 'singular' ): string {
    $defaults = [
        'singular' => 'Buchung',
        'plural'   => 'Buchungen',
        'verb'     => 'Buchung anfragen',
        'past'     => 'Buchung eingereicht',
    ];

    $field_map = [
        'singular' => 'mlb_term_singular',
        'plural'   => 'mlb_term_plural',
        'verb'     => 'mlb_term_verb',
        'past'     => 'mlb_term_past',
    ];

    if ( ! isset( $field_map[ $type ] ) ) return $defaults['singular'];

    if ( function_exists( 'get_field' ) ) {
        $val = get_field( $field_map[ $type ], 'option' );
        if ( $val && trim( $val ) !== '' ) return trim( $val );
    }

    return $defaults[ $type ];
}
