<?php
/**
 * Cookie Consent – PHP-Seite (Multilingual)
 *
 * Aufgaben:
 *  1. Floating Button via wp_footer ausgeben
 *  2. window.cookieConsent Konfiguration (Texte, Kategorien, Version) via wp_head
 *  3. Spracherkennung: Polylang → WPML → get_locale() Fallback
 *  4. ACF Field Group: globale Einstellungen + Mehrsprachigkeit-Repeater
 *
 * Mehrsprachigkeit:
 *  – Wenn „Mehrsprachigkeit aktivieren" AN ist, sucht output_config() im
 *    Repeater cc_languages nach einer Zeile, deren cc_lang_code zur aktuellen
 *    Sprache passt. Fällt zurück auf die erste Zeile, wenn keine exakte
 *    Übereinstimmung gefunden wird.
 *  – Wenn die Option AUS ist, werden die bisherigen Flat-Felder verwendet
 *    (vollständige Rückwärtskompatibilität).
 *  – Code-Snippets (GA4, Meta Pixel …) sind sprachunabhängig und bleiben in
 *    den Flat-Feldern.
 *
 * @package MediaLab_Core
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Cookie_Consent {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_floating_button' ), 20 );
        add_action( 'wp_head',   array( $this, 'output_config' ),   2  );
        add_action( 'wp_head',   array( $this, 'output_snippets' ), 99 );
        add_action( 'acf/init',  array( $this, 'register_fields' ), 25 );
    }

    // ─── Floating Button ──────────────────────────────────────────────────────

    public function render_floating_button(): void {
        ?>
        <button
            id="cookie-settings-btn"
            class="cookie-settings-btn"
            type="button"
            aria-label="<?php esc_attr_e( 'Cookie-Einstellungen', 'medialab-core' ); ?>"
        >🍪</button>
        <?php
    }

    // ─── Sprach-Erkennung ─────────────────────────────────────────────────────

    /**
     * Gibt den 2-Zeichen-Sprachcode der aktuellen Seite zurück.
     * Reihenfolge: Polylang → WPML → WP-Locale-Substring.
     */
    private function get_current_lang(): string {
        // Polylang
        if ( function_exists( 'pll_current_language' ) ) {
            $lang = pll_current_language( 'slug' );
            if ( $lang ) return (string) $lang;
        }
        // WPML
        if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
            return (string) ICL_LANGUAGE_CODE;
        }
        // WP-Locale-Fallback (z.B. 'de_DE' → 'de')
        $locale = get_locale();
        return substr( $locale, 0, 2 ) ?: 'de';
    }

    // ─── Texte auflösen ───────────────────────────────────────────────────────

    /**
     * Liefert alle sprachabhängigen Texte als assoziatives Array.
     * Wählt je nach Konfiguration Repeater-Zeile oder Flat-Felder.
     *
     * @return array<string,string>
     */
    private function resolve_texts(): array {
        $acf = function_exists( 'get_field' );

        // Mehrsprachigkeit aktiv?
        if ( $acf && get_field( 'cc_multilang_enabled', 'option' ) ) {
            $lang = $this->get_current_lang();
            $rows = get_field( 'cc_languages', 'option' );

            if ( is_array( $rows ) && ! empty( $rows ) ) {
                $match    = null;
                $fallback = $rows[0]; // erste Zeile = Standard-Sprache

                foreach ( $rows as $row ) {
                    $code = isset( $row['cc_lang_code'] ) ? trim( (string) $row['cc_lang_code'] ) : '';
                    if ( $code === $lang ) {
                        $match = $row;
                        break;
                    }
                }

                return $this->texts_from_row( $match ?? $fallback );
            }
        }

        // Flat-Felder (Rückwärtskompatibilität / Einsprachig)
        return $this->texts_from_flat( $acf );
    }

    /**
     * Extrahiert Texte aus einer Repeater-Zeile.
     *
     * @param  array<string,mixed> $row
     * @return array<string,string>
     */
    private function texts_from_row( array $row ): array {
        $s = function( string $key, string $default = '' ) use ( $row ): string {
            $val = $row[ $key ] ?? null;
            return ( $val !== null && $val !== false && (string) $val !== '' )
                ? (string) $val
                : $default;
        };

        return array(
            // Banner
            'bannerTitle'        => $s( 'cc_lang_banner_title'   ),
            'bannerText'         => $s( 'cc_lang_banner_text'    ),
            'bannerTextUSA'      => $s( 'cc_lang_banner_text_usa' ),
            // Buttons
            'acceptAll'          => $s( 'cc_lang_accept_all'    ),
            'declineAll'         => $s( 'cc_lang_decline_all'   ),
            'settings'           => $s( 'cc_lang_settings_btn'  ),
            'saveConsent'        => $s( 'cc_lang_save_consent'  ),
            'essentialOnly'      => $s( 'cc_lang_decline_all'   ), // alias
            'openSettings'       => $s( 'cc_lang_settings_btn'  ), // alias
            // Modal
            'modalTitle'         => $s( 'cc_lang_modal_title'   ),
            'modalIntro'         => $s( 'cc_lang_modal_intro'   ),
            'saveSettings'       => $s( 'cc_lang_save_btn'      ),
            // Datenschutz
            'privacyLabel'       => $s( 'cc_lang_privacy_label' ),
            'privacyUrl'         => $s( 'cc_lang_privacy_url', '/' ),
            // Immer aktiv
            'alwaysActive'       => $s( 'cc_lang_always_active' ),
            // Kategorie-Bezeichnungen (werden für categories-Objekt genutzt)
            '_catNecessaryLabel' => $s( 'cc_lang_cat_necessary_label' ),
            '_catNecessaryDesc'  => $s( 'cc_lang_cat_necessary_desc'  ),
            '_catStatisticsLabel'=> $s( 'cc_lang_cat_statistics_label' ),
            '_catStatisticsDesc' => $s( 'cc_lang_cat_statistics_desc'  ),
            '_catMarketingLabel' => $s( 'cc_lang_cat_marketing_label' ),
            '_catMarketingDesc'  => $s( 'cc_lang_cat_marketing_desc'  ),
            '_catComfortLabel'   => $s( 'cc_lang_cat_comfort_label'   ),
            '_catComfortDesc'    => $s( 'cc_lang_cat_comfort_desc'    ),
        );
    }

    /**
     * Liest Texte aus den bisherigen Flat-Feldern (Rückwärtskompatibilität).
     *
     * @param  bool  $acf  ACF verfügbar?
     * @return array<string,string>
     */
    private function texts_from_flat( bool $acf ): array {
        $s = function( string $key, string $default ) use ( $acf ): string {
            if ( ! $acf ) return $default;
            $val = get_field( $key, 'option' );
            return ( $val !== null && $val !== false && $val !== '' )
                ? (string) $val
                : $default;
        };

        return array(
            'bannerTitle'        => $s( 'cc_banner_title',  'Wir verwenden Cookies' ),
            'bannerText'         => $s( 'cc_banner_text',   'Wir setzen Cookies ein, um Ihnen die bestmögliche Nutzung unserer Website zu ermöglichen.' ),
            'bannerTextUSA'      => $s( 'cc_banner_text_usa', '' ),
            'acceptAll'          => $s( 'cc_accept_all',    'Alle akzeptieren' ),
            'declineAll'         => $s( 'cc_decline_all',   'Ablehnen' ),
            'settings'           => $s( 'cc_settings_btn',  'Einstellungen' ),
            'saveConsent'        => $s( 'cc_save_btn',      'Auswahl speichern' ),
            'essentialOnly'      => $s( 'cc_decline_all',   'Nur essenzielle Cookies' ),
            'openSettings'       => $s( 'cc_settings_btn',  'Individuelle Datenschutz-Präferenzen' ),
            'modalTitle'         => $s( 'cc_modal_title',   'Cookie-Einstellungen' ),
            'modalIntro'         => $s( 'cc_modal_intro',   'Hier können Sie Ihre Cookie-Einstellungen jederzeit anpassen.' ),
            'saveSettings'       => $s( 'cc_save_btn',      'Auswahl speichern' ),
            'privacyLabel'       => $s( 'cc_privacy_label', 'Datenschutzerklärung' ),
            'privacyUrl'         => $s( 'cc_privacy_url',   '/datenschutz' ),
            'alwaysActive'       => $s( 'cc_always_active', 'Immer aktiv' ),
            '_catNecessaryLabel' => $s( 'cc_cat_necessary_label', 'Notwendig' ),
            '_catNecessaryDesc'  => $s( 'cc_cat_necessary_desc',  'Technisch erforderliche Cookies für die Grundfunktionen der Website.' ),
            '_catStatisticsLabel'=> $s( 'cc_cat_statistics_label', 'Statistik' ),
            '_catStatisticsDesc' => $s( 'cc_cat_statistics_desc',  'Helfen uns zu verstehen, wie Besucher mit der Website interagieren.' ),
            '_catMarketingLabel' => $s( 'cc_cat_marketing_label', 'Marketing' ),
            '_catMarketingDesc'  => $s( 'cc_cat_marketing_desc',  'Werden für personalisierte Werbung und Remarketing verwendet.' ),
            '_catComfortLabel'   => $s( 'cc_cat_comfort_label', 'Komfort' ),
            '_catComfortDesc'    => $s( 'cc_cat_comfort_desc',  'Ermöglichen eingebettete Inhalte wie YouTube-Videos oder Google Maps.' ),
        );
    }

    // ─── JS-Konfiguration ─────────────────────────────────────────────────────

    public function output_config(): void {
        if ( is_admin() ) return;

        $acf = function_exists( 'get_field' );
        $b   = function( string $key, bool $default ) use ( $acf ): bool {
            if ( ! $acf ) return $default;
            $val = get_field( $key, 'option' );
            return ( $val !== null && $val !== false ) ? (bool) $val : $default;
        };
        $s   = function( string $key, string $default ) use ( $acf ): string {
            if ( ! $acf ) return $default;
            $val = get_field( $key, 'option' );
            return ( $val !== null && $val !== false && $val !== '' ) ? (string) $val : $default;
        };

        // Texte für aktuelle Sprache laden
        $t = $this->resolve_texts();

        // texts-Objekt für JS (keine internen _cat* Schlüssel)
        $texts = array(
            'bannerTitle'   => $t['bannerTitle'],
            'bannerText'    => $t['bannerText'],
            'acceptAll'     => $t['acceptAll'],
            'declineAll'    => $t['declineAll'],
            'settings'      => $t['settings'],
            'modalTitle'    => $t['modalTitle'],
            'modalIntro'    => $t['modalIntro'],
            'saveSettings'  => $t['saveSettings'],
            'privacyLabel'  => $t['privacyLabel'],
            'privacyUrl'    => $t['privacyUrl'],
            'alwaysActive'  => $t['alwaysActive'],
            // Aliasse für JS-Kompatibilität
            'saveConsent'   => $t['saveConsent'],
            'essentialOnly' => $t['essentialOnly'],
            'openSettings'  => $t['openSettings'],
        );

        // bannerTextUSA nur einschließen wenn nicht leer
        if ( ! empty( $t['bannerTextUSA'] ) ) {
            $texts['bannerTextUSA'] = $t['bannerTextUSA'];
        }

        // Kategorien aufbauen (enabled/disabled global, Labels sprachspezifisch)
        $categories = array(
            'necessary' => array(
                'label'       => $t['_catNecessaryLabel'] ?: $s( 'cc_cat_necessary_label', 'Notwendig' ),
                'description' => $t['_catNecessaryDesc']  ?: $s( 'cc_cat_necessary_desc',  'Technisch erforderliche Cookies.' ),
                'required'    => true,
            ),
        );

        if ( $b( 'cc_cat_statistics_enabled', false ) ) {
            $categories['statistics'] = array(
                'label'       => $t['_catStatisticsLabel'] ?: $s( 'cc_cat_statistics_label', 'Statistik' ),
                'description' => $t['_catStatisticsDesc']  ?: $s( 'cc_cat_statistics_desc',  'Helfen uns zu verstehen, wie Besucher mit der Website interagieren.' ),
                'required'    => false,
            );
        }

        if ( $b( 'cc_cat_marketing_enabled', false ) ) {
            $categories['marketing'] = array(
                'label'       => $t['_catMarketingLabel'] ?: $s( 'cc_cat_marketing_label', 'Marketing' ),
                'description' => $t['_catMarketingDesc']  ?: $s( 'cc_cat_marketing_desc',  'Werden für personalisierte Werbung und Remarketing verwendet.' ),
                'required'    => false,
            );
        }

        if ( $b( 'cc_cat_comfort_enabled', false ) ) {
            $categories['comfort'] = array(
                'label'       => $t['_catComfortLabel'] ?: $s( 'cc_cat_comfort_label', 'Komfort' ),
                'description' => $t['_catComfortDesc']  ?: $s( 'cc_cat_comfort_desc',  'Ermöglichen eingebettete Inhalte wie YouTube-Videos oder Google Maps.' ),
                'required'    => false,
            );
        }

        $config = array(
            'version'    => $s( 'cc_version', '1' ),
            'texts'      => $texts,
            'categories' => $categories,
        );

        echo '<script id="cookie-consent-config">window.cookieConsent = '
            . wp_json_encode( $config, JSON_UNESCAPED_UNICODE )
            . ';</script>' . "\n";
    }

    // ─── Code-Snippets ausgeben ───────────────────────────────────────────────

    public function output_snippets(): void {
        if ( is_admin() ) return;
        if ( ! function_exists( 'get_field' ) ) return;

        $acf      = true;
        $b        = fn( string $key, bool $default ): bool => (bool) ( get_field( $key, 'option' ) ?? $default );
        $snippets = array();

        foreach ( array( 'necessary', 'statistics', 'marketing', 'comfort' ) as $cat ) {
            if ( $cat !== 'necessary' && ! $b( "cc_cat_{$cat}_enabled", false ) ) continue;

            $head = get_field( "cc_snippet_{$cat}_head", 'option' );
            $body = get_field( "cc_snippet_{$cat}_body", 'option' );

            $snippets[ $cat ] = array(
                'head'     => $head ? trim( wp_unslash( $head ) ) : '',
                'body'     => $body ? trim( wp_unslash( $body ) ) : '',
                'required' => $cat === 'necessary',
            );
        }

        $has_snippets = array_filter( array_map(
            fn( $s ) => $s['head'] !== '' || $s['body'] !== '',
            $snippets
        ) );

        if ( empty( $has_snippets ) ) return;

        echo '<script id="cookie-snippets-config">window.cookieSnippets = '
            . wp_json_encode( $snippets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            . ';</script>' . "\n";
    }

    // ─── ACF Field Group ──────────────────────────────────────────────────────

    public function register_fields(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        // ── Repeater-Sub-Fields (helper) ──────────────────────────────────────
        $lang_text_fields = $this->build_language_subfields();

        acf_add_local_field_group( array(
            'key'   => 'group_cookie_consent',
            'title' => 'Cookie Consent',
            'fields' => array(

                // ── Version ──────────────────────────────────────────────────
                array(
                    'key'           => 'field_cc_version',
                    'label'         => 'Consent-Version',
                    'name'          => 'cc_version',
                    'type'          => 'text',
                    'default_value' => '1',
                    'instructions'  => 'Erhöhe diese Zahl, wenn du Kategorien oder Texte wesentlich änderst – erzwingt erneute Zustimmung bei allen Besuchern.',
                    'wrapper'       => array( 'width' => '20' ),
                ),

                // ── Datenschutz-URL (global) ──────────────────────────────────
                array(
                    'key'           => 'field_cc_privacy_url',
                    'label'         => 'Datenschutz-URL (Fallback)',
                    'name'          => 'cc_privacy_url',
                    'type'          => 'text',
                    'default_value' => '/datenschutz',
                    'placeholder'   => '/datenschutz',
                    'instructions'  => 'Wird verwendet wenn Mehrsprachigkeit deaktiviert ist oder keine Sprache übereinstimmt.',
                    'wrapper'       => array( 'width' => '40' ),
                ),
                array(
                    'key'           => 'field_cc_privacy_label',
                    'label'         => 'Datenschutz Link-Text (Fallback)',
                    'name'          => 'cc_privacy_label',
                    'type'          => 'text',
                    'default_value' => 'Datenschutzerklärung',
                    'wrapper'       => array( 'width' => '40' ),
                ),

                // ── Immer-Aktiv-Text (global Fallback) ────────────────────────
                array(
                    'key'           => 'field_cc_always_active',
                    'label'         => 'Text „Immer aktiv" (Fallback)',
                    'name'          => 'cc_always_active',
                    'type'          => 'text',
                    'default_value' => 'Immer aktiv',
                    'instructions'  => 'Wird neben der Notwendig-Kategorie im Modal angezeigt.',
                    'wrapper'       => array( 'width' => '100' ),
                ),

                // ════════════════════════════════════════════════════════════════
                // EINSPRACHIGE TEXTE (Rückwärtskompatibilität / Fallback)
                // ════════════════════════════════════════════════════════════════
                array(
                    'key'     => 'field_cc_flat_heading',
                    'label'   => ' ',
                    'name'    => 'cc_flat_heading',
                    'type'    => 'message',
                    'message' => '<strong style="font-size:13px;">Einsprachige Texte (Fallback)</strong>'
                              . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                              . 'Werden verwendet wenn Mehrsprachigkeit deaktiviert ist.</p>',
                    'default_value' => '',
                    'conditional_logic' => array( array( array(
                        'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1',
                    ) ) ),
                ),

                // Banner-Texte
                array( 'key' => 'field_cc_banner_title', 'label' => 'Titel', 'name' => 'cc_banner_title', 'type' => 'text', 'default_value' => 'Wir verwenden Cookies', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_banner_text', 'label' => 'Text', 'name' => 'cc_banner_text', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Wir setzen Cookies ein, um Ihnen die bestmögliche Nutzung unserer Website zu ermöglichen.', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_banner_text_usa', 'label' => 'Zusatztext USA/Drittstaaten (opt.)', 'name' => 'cc_banner_text_usa', 'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'instructions' => 'Wird unterhalb des Haupttexts ausgegeben, wenn nicht leer.', 'wrapper' => array( 'width' => '100' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_accept_all',   'label' => 'Button „Alle akzeptieren"',      'name' => 'cc_accept_all',   'type' => 'text', 'default_value' => 'Alle akzeptieren', 'wrapper' => array( 'width' => '25' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_settings_btn', 'label' => 'Button „Einstellungen"',         'name' => 'cc_settings_btn', 'type' => 'text', 'default_value' => 'Einstellungen',    'wrapper' => array( 'width' => '25' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_decline_all',  'label' => 'Button „Ablehnen"',              'name' => 'cc_decline_all',  'type' => 'text', 'default_value' => 'Ablehnen',          'wrapper' => array( 'width' => '25' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_save_btn',     'label' => 'Button „Auswahl speichern"',     'name' => 'cc_save_btn',     'type' => 'text', 'default_value' => 'Auswahl speichern', 'wrapper' => array( 'width' => '25' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_modal_title',  'label' => 'Modal Titel',       'name' => 'cc_modal_title',  'type' => 'text',     'default_value' => 'Cookie-Einstellungen',                          'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_modal_intro',  'label' => 'Einleitungstext',   'name' => 'cc_modal_intro',  'type' => 'textarea', 'rows' => 2, 'default_value' => 'Hier können Sie Ihre Cookie-Einstellungen jederzeit anpassen.', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_multilang_enabled', 'operator' => '!=', 'value' => '1' ) ) ),
                ),

                // ════════════════════════════════════════════════════════════════
                // MEHRSPRACHIGKEIT
                // ════════════════════════════════════════════════════════════════
                array(
                    'key'     => 'field_cc_multilang_heading',
                    'label'   => ' ',
                    'name'    => 'cc_multilang_heading',
                    'type'    => 'message',
                    'message' => '<strong style="font-size:13px;">Mehrsprachigkeit</strong>'
                              . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                              . 'Aktiviere diese Option, um Banner-Texte und Kategorie-Bezeichnungen je Sprache zu pflegen.<br>'
                              . 'Spracherkennung: Polylang → WPML → WP-Locale-Fallback.</p>',
                    'default_value' => '',
                ),

                array(
                    'key'           => 'field_cc_multilang_enabled',
                    'label'         => 'Mehrsprachigkeit aktivieren',
                    'name'          => 'cc_multilang_enabled',
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'default_value' => 0,
                    'instructions'  => 'Wenn aktiviert, gelten die Übersetzungen unten statt der Fallback-Texte oben.',
                    'wrapper'       => array( 'width' => '100' ),
                ),

                // ── Sprachen-Repeater ─────────────────────────────────────────
                array(
                    'key'               => 'field_cc_languages',
                    'label'             => 'Sprachen',
                    'name'              => 'cc_languages',
                    'type'              => 'repeater',
                    'min'               => 0,
                    'layout'            => 'block',
                    'button_label'      => 'Sprache hinzufügen',
                    'instructions'      => 'Die erste Zeile gilt als Standard-Sprache (Fallback wenn keine Übereinstimmung). Sprachcodes: de, en, fr, it, es, …',
                    'conditional_logic' => array( array( array(
                        'field' => 'field_cc_multilang_enabled', 'operator' => '==', 'value' => '1',
                    ) ) ),
                    'sub_fields'        => $lang_text_fields,
                ),

                // ════════════════════════════════════════════════════════════════
                // KATEGORIEN (global ein/aus + einsprachige Labels)
                // ════════════════════════════════════════════════════════════════
                array(
                    'key' => 'field_cc_tab_cats', 'label' => ' ', 'name' => 'cc_tab_cats',
                    'type' => 'message', 'message' => '<strong style="font-size:13px;">Kategorien (global)</strong>'
                        . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">Aktivierung gilt für alle Sprachen. Labels werden bei aktivierter Mehrsprachigkeit pro Sprache gepflegt.</p>',
                    'default_value' => '',
                ),

                // Notwendig (immer aktiv)
                array( 'key' => 'field_cc_cat_necessary_label', 'label' => 'Notwendig – Bezeichnung (Fallback)',   'name' => 'cc_cat_necessary_label', 'type' => 'text', 'default_value' => 'Notwendig', 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_cat_necessary_desc',  'label' => 'Notwendig – Beschreibung (Fallback)',  'name' => 'cc_cat_necessary_desc',  'type' => 'textarea', 'rows' => 2, 'default_value' => 'Technisch erforderliche Cookies für die Grundfunktionen der Website.', 'wrapper' => array( 'width' => '50' ) ),

                // Statistik
                array( 'key' => 'field_cc_cat_statistics_enabled', 'label' => 'Statistik-Kategorie aktivieren', 'name' => 'cc_cat_statistics_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Nur aktivieren wenn Statistik-Cookies verwendet werden.', 'wrapper' => array( 'width' => '100' ) ),
                array( 'key' => 'field_cc_cat_statistics_label', 'label' => 'Statistik – Bezeichnung (Fallback)', 'name' => 'cc_cat_statistics_label', 'type' => 'text', 'default_value' => 'Statistik', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_cat_statistics_enabled', 'operator' => '==', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_cat_statistics_desc', 'label' => 'Statistik – Beschreibung (Fallback)', 'name' => 'cc_cat_statistics_desc', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Helfen uns zu verstehen, wie Besucher mit der Website interagieren.', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_cat_statistics_enabled', 'operator' => '==', 'value' => '1' ) ) ),
                ),

                // Marketing
                array( 'key' => 'field_cc_cat_marketing_enabled', 'label' => 'Marketing-Kategorie aktivieren', 'name' => 'cc_cat_marketing_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Nur aktivieren wenn Marketing-Cookies verwendet werden.', 'wrapper' => array( 'width' => '100' ) ),
                array( 'key' => 'field_cc_cat_marketing_label', 'label' => 'Marketing – Bezeichnung (Fallback)', 'name' => 'cc_cat_marketing_label', 'type' => 'text', 'default_value' => 'Marketing', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_cat_marketing_enabled', 'operator' => '==', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_cat_marketing_desc', 'label' => 'Marketing – Beschreibung (Fallback)', 'name' => 'cc_cat_marketing_desc', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Werden für personalisierte Werbung und Remarketing verwendet.', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_cat_marketing_enabled', 'operator' => '==', 'value' => '1' ) ) ),
                ),

                // Komfort
                array( 'key' => 'field_cc_cat_comfort_enabled', 'label' => 'Komfort-Kategorie aktivieren', 'name' => 'cc_cat_comfort_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0, 'instructions' => 'Nur aktivieren wenn Komfort-Cookies verwendet werden.', 'wrapper' => array( 'width' => '100' ) ),
                array( 'key' => 'field_cc_cat_comfort_label', 'label' => 'Komfort – Bezeichnung (Fallback)', 'name' => 'cc_cat_comfort_label', 'type' => 'text', 'default_value' => 'Komfort', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_cat_comfort_enabled', 'operator' => '==', 'value' => '1' ) ) ),
                ),
                array( 'key' => 'field_cc_cat_comfort_desc', 'label' => 'Komfort – Beschreibung (Fallback)', 'name' => 'cc_cat_comfort_desc', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Ermöglichen eingebettete Inhalte wie YouTube-Videos oder Google Maps.', 'wrapper' => array( 'width' => '50' ),
                    'conditional_logic' => array( array( array( 'field' => 'field_cc_cat_comfort_enabled', 'operator' => '==', 'value' => '1' ) ) ),
                ),

                // ════════════════════════════════════════════════════════════════
                // CODE-SNIPPETS (sprachunabhängig)
                // ════════════════════════════════════════════════════════════════
                array(
                    'key' => 'field_cc_tab_snippets', 'label' => ' ', 'name' => 'cc_tab_snippets',
                    'type' => 'message',
                    'message' => '<strong style="font-size:13px;">Code-Snippets pro Kategorie</strong>'
                              . '<p style="margin:.5rem 0 0;color:#666;font-size:12px;">Wird nur ausgegeben, wenn der Besucher der jeweiligen Kategorie zugestimmt hat.<br>'
                              . '<strong>Head-Code:</strong> Script-Tags, die in den &lt;head&gt; gehören (GA4, FB Pixel …)<br>'
                              . '<strong>Body-Code:</strong> Noscript-Fallbacks für den &lt;body&gt;</p>',
                    'default_value' => '',
                ),
                array( 'key' => 'field_cc_snippet_necessary_head', 'label' => 'Notwendig – Head-Code',   'name' => 'cc_snippet_necessary_head',   'type' => 'textarea', 'rows' => 6, 'default_value' => '', 'placeholder' => '<!-- z.B. DSGVO-konformes Chat-Widget –>', 'instructions' => 'Wird <strong>immer</strong> geladen – unabhängig vom Consent.', 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_necessary_body', 'label' => 'Notwendig – Body-Code',   'name' => 'cc_snippet_necessary_body',   'type' => 'textarea', 'rows' => 4, 'default_value' => '', 'placeholder' => '<!-- Noscript Fallback –>', 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_statistics_head', 'label' => 'Statistik – Head-Code',  'name' => 'cc_snippet_statistics_head',  'type' => 'textarea', 'rows' => 6, 'default_value' => '', 'placeholder' => "<!-- Google Analytics 4 -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX\"></script>", 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_statistics_body', 'label' => 'Statistik – Body-Code',  'name' => 'cc_snippet_statistics_body',  'type' => 'textarea', 'rows' => 4, 'default_value' => '', 'placeholder' => '<!-- Noscript Fallback –>', 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_marketing_head',  'label' => 'Marketing – Head-Code',  'name' => 'cc_snippet_marketing_head',   'type' => 'textarea', 'rows' => 6, 'default_value' => '', 'placeholder' => "<!-- Meta Pixel Code -->\n<script>!function(f,b,e,v,n,t,s)...</script>", 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_marketing_body',  'label' => 'Marketing – Body-Code',  'name' => 'cc_snippet_marketing_body',   'type' => 'textarea', 'rows' => 4, 'default_value' => '', 'placeholder' => '<!-- Noscript Fallback –>', 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_comfort_head',    'label' => 'Komfort – Head-Code',    'name' => 'cc_snippet_comfort_head',     'type' => 'textarea', 'rows' => 6, 'default_value' => '', 'placeholder' => '<!-- z.B. YouTube API, Google Maps API –>', 'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_snippet_comfort_body',    'label' => 'Komfort – Body-Code',    'name' => 'cc_snippet_comfort_body',     'type' => 'textarea', 'rows' => 4, 'default_value' => '', 'placeholder' => '<!-- Noscript Fallback –>', 'wrapper' => array( 'width' => '50' ) ),

            ),
            'location' => array( array( array(
                'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-cookie-consent',
            ) ) ),
            'menu_order'            => 36,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ) );
    }

    // ─── Repeater Sub-Fields aufbauen ─────────────────────────────────────────

    /**
     * Gibt alle Sub-Fields für den cc_languages Repeater zurück.
     *
     * @return array<int, array<string, mixed>>
     */
    private function build_language_subfields(): array {
        $sep = fn( string $label ) => array(
            'key' => 'field_cc_lang_sep_' . sanitize_key( $label ), 'label' => ' ', 'name' => '',
            'type' => 'message', 'message' => '<strong style="font-size:12px;color:#555;">' . esc_html( $label ) . '</strong>',
            'default_value' => '',
        );

        return array(
            // Sprachcode + Label
            array( 'key' => 'field_cc_lang_code',  'label' => 'Sprachcode',   'name' => 'cc_lang_code',  'type' => 'text', 'required' => 1, 'placeholder' => 'de', 'instructions' => 'z.B. de, en, fr, it, es', 'wrapper' => array( 'width' => '20' ) ),
            array( 'key' => 'field_cc_lang_name',  'label' => 'Bezeichnung',  'name' => 'cc_lang_name',  'type' => 'text', 'required' => 0, 'placeholder' => 'Deutsch', 'instructions' => 'Nur zur internen Orientierung.', 'wrapper' => array( 'width' => '30' ) ),
            array( 'key' => 'field_cc_lang_privacy_url',   'label' => 'Datenschutz-URL',      'name' => 'cc_lang_privacy_url',   'type' => 'text', 'placeholder' => '/datenschutz', 'wrapper' => array( 'width' => '25' ) ),
            array( 'key' => 'field_cc_lang_privacy_label', 'label' => 'Datenschutz Link-Text', 'name' => 'cc_lang_privacy_label', 'type' => 'text', 'placeholder' => 'Datenschutzerklärung', 'wrapper' => array( 'width' => '25' ) ),

            // Banner
            $sep( 'Banner' ),
            array( 'key' => 'field_cc_lang_banner_title',   'label' => 'Titel',                  'name' => 'cc_lang_banner_title',    'type' => 'text',     'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_banner_text',    'label' => 'Text',                   'name' => 'cc_lang_banner_text',     'type' => 'textarea', 'rows' => 3, 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_banner_text_usa','label' => 'Zusatztext USA (opt.)',   'name' => 'cc_lang_banner_text_usa', 'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'instructions' => 'Leer lassen wenn nicht benötigt.', 'wrapper' => array( 'width' => '100' ) ),

            // Buttons
            $sep( 'Buttons' ),
            array( 'key' => 'field_cc_lang_accept_all',  'label' => 'Alle akzeptieren',   'name' => 'cc_lang_accept_all',  'type' => 'text', 'default_value' => '', 'wrapper' => array( 'width' => '25' ) ),
            array( 'key' => 'field_cc_lang_decline_all', 'label' => 'Ablehnen',           'name' => 'cc_lang_decline_all', 'type' => 'text', 'default_value' => '', 'wrapper' => array( 'width' => '25' ) ),
            array( 'key' => 'field_cc_lang_settings_btn','label' => 'Einstellungen',      'name' => 'cc_lang_settings_btn','type' => 'text', 'default_value' => '', 'wrapper' => array( 'width' => '25' ) ),
            array( 'key' => 'field_cc_lang_save_consent','label' => 'Einwilligung speichern', 'name' => 'cc_lang_save_consent','type' => 'text', 'default_value' => '', 'wrapper' => array( 'width' => '25' ) ),

            // Modal
            $sep( 'Einstellungs-Modal' ),
            array( 'key' => 'field_cc_lang_modal_title', 'label' => 'Modal Titel',       'name' => 'cc_lang_modal_title', 'type' => 'text',     'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_modal_intro', 'label' => 'Einleitungstext',   'name' => 'cc_lang_modal_intro', 'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_save_btn',    'label' => 'Auswahl speichern', 'name' => 'cc_lang_save_btn',    'type' => 'text', 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_always_active','label' => '„Immer aktiv"-Text','name' => 'cc_lang_always_active','type' => 'text', 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),

            // Kategorien
            $sep( 'Kategorie-Bezeichnungen' ),
            array( 'key' => 'field_cc_lang_cat_necessary_label',  'label' => 'Notwendig – Bezeichnung',   'name' => 'cc_lang_cat_necessary_label',  'type' => 'text',     'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_necessary_desc',   'label' => 'Notwendig – Beschreibung',  'name' => 'cc_lang_cat_necessary_desc',   'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_statistics_label', 'label' => 'Statistik – Bezeichnung',   'name' => 'cc_lang_cat_statistics_label', 'type' => 'text',     'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_statistics_desc',  'label' => 'Statistik – Beschreibung',  'name' => 'cc_lang_cat_statistics_desc',  'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_marketing_label',  'label' => 'Marketing – Bezeichnung',   'name' => 'cc_lang_cat_marketing_label',  'type' => 'text',     'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_marketing_desc',   'label' => 'Marketing – Beschreibung',  'name' => 'cc_lang_cat_marketing_desc',   'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_comfort_label',    'label' => 'Komfort – Bezeichnung',     'name' => 'cc_lang_cat_comfort_label',    'type' => 'text',     'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
            array( 'key' => 'field_cc_lang_cat_comfort_desc',     'label' => 'Komfort – Beschreibung',    'name' => 'cc_lang_cat_comfort_desc',     'type' => 'textarea', 'rows' => 2, 'default_value' => '', 'wrapper' => array( 'width' => '50' ) ),
        );
    }

}

new MediaLab_Cookie_Consent();
