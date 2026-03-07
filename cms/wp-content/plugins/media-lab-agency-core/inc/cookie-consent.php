<?php
/**
 * Cookie Consent – PHP-Seite
 *
 * Aufgaben:
 *  1. Floating Button via wp_footer ausgeben
 *  2. window.cookieConsent Konfiguration (Texte, Kategorien, Version) via wp_head
 *  3. ACF Field Group registrieren (Agency Core → Einstellungen → Cookie Consent)
 *
 * @package MediaLab_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Cookie_Consent {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'render_floating_button' ), 20 );
        add_action( 'wp_head',   array( $this, 'output_config' ), 2 );
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

    // ─── JS-Konfiguration ─────────────────────────────────────────────────────

    public function output_config(): void {
        // Nicht im Admin-Backend ausgeben
        if ( is_admin() ) return;

        $acf = function_exists( 'get_field' );

        // Sicherer String-Cast: get_field() gibt auf nie gespeicherten Feldern null zurück.
        // wp_json_encode's internem Sanitizer schlägt null fehl → immer string casten.
        $s = function( string $key, string $default ) use ( $acf ): string {
            if ( ! $acf ) return $default;
            $val = get_field( $key, 'option' );
            return ( $val !== null && $val !== false && $val !== '' )
                ? (string) $val
                : $default;
        };

        $texts = array(
            'bannerTitle'  => $s( 'cc_banner_title',  'Wir verwenden Cookies' ),
            'bannerText'   => $s( 'cc_banner_text',   'Wir setzen Cookies ein, um Ihnen die bestm\u00f6gliche Nutzung unserer Website zu erm\u00f6glichen.' ),
            'acceptAll'    => $s( 'cc_accept_all',    'Alle akzeptieren' ),
            'declineAll'   => $s( 'cc_decline_all',   'Ablehnen' ),
            'settings'     => $s( 'cc_settings_btn',  'Einstellungen' ),
            'modalTitle'   => $s( 'cc_modal_title',   'Cookie-Einstellungen' ),
            'modalIntro'   => $s( 'cc_modal_intro',   'Hier k\u00f6nnen Sie Ihre Cookie-Einstellungen jederzeit anpassen.' ),
            'saveSettings' => $s( 'cc_save_btn',      'Auswahl speichern' ),
            'privacyLabel' => $s( 'cc_privacy_label', 'Datenschutzerkl\u00e4rung' ),
            'privacyUrl'   => $s( 'cc_privacy_url',   '/datenschutz' ),
            'alwaysActive' => 'Immer aktiv',
        );

        $categories = array(
            'necessary' => array(
                'label'       => $s( 'cc_cat_necessary_label', 'Notwendig' ),
                'description' => $s( 'cc_cat_necessary_desc',  'Technisch erforderliche Cookies f\u00fcr die Grundfunktionen der Website.' ),
                'required'    => true,
            ),
            'statistics' => array(
                'label'       => $s( 'cc_cat_statistics_label', 'Statistik' ),
                'description' => $s( 'cc_cat_statistics_desc',  'Helfen uns zu verstehen, wie Besucher mit der Website interagieren.' ),
                'required'    => false,
            ),
            'marketing' => array(
                'label'       => $s( 'cc_cat_marketing_label', 'Marketing' ),
                'description' => $s( 'cc_cat_marketing_desc',  'Werden f\u00fcr personalisierte Werbung und Remarketing verwendet.' ),
                'required'    => false,
            ),
            'comfort' => array(
                'label'       => $s( 'cc_cat_comfort_label', 'Komfort' ),
                'description' => $s( 'cc_cat_comfort_desc',  'Erm\u00f6glichen eingebettete Inhalte wie YouTube-Videos oder Google Maps.' ),
                'required'    => false,
            ),
        );

        $version = $s( 'cc_version', '1' );

        $config = array(
            'version'    => $version,
            'texts'      => $texts,
            'categories' => $categories,
        );

        echo '<script id="cookie-consent-config">window.cookieConsent = '
            . wp_json_encode( $config, JSON_UNESCAPED_UNICODE )
            . ';</script>' . "\n";
    }

    // ─── ACF Field Group ──────────────────────────────────────────────────────

    public function register_fields(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

        acf_add_local_field_group( array(
            'key'   => 'group_cookie_consent',
            'title' => 'Cookie Consent',
            'fields' => array(

                // ── Version ──────────────────────────────────────────────────
                array(
                    'key'          => 'field_cc_version',
                    'label'        => 'Consent-Version',
                    'name'         => 'cc_version',
                    'type'         => 'text',
                    'default_value'=> '1',
                    'instructions' => 'Erhöhe diese Zahl wenn du Kategorien oder Texte wesentlich änderst – das erzwingt bei allen Besuchern eine erneute Zustimmung.',
                    'wrapper'      => array( 'width' => '20' ),
                ),

                // ── Datenschutz-URL ───────────────────────────────────────────
                array(
                    'key'          => 'field_cc_privacy_url',
                    'label'        => 'Datenschutz-URL',
                    'name'         => 'cc_privacy_url',
                    'type'         => 'text',
                    'default_value'=> '/datenschutz',
                    'placeholder'  => '/datenschutz',
                    'instructions' => 'Relativer Pfad oder absolute URL zur Datenschutzerklärung.',
                    'wrapper'      => array( 'width' => '40' ),
                ),

                array(
                    'key'          => 'field_cc_privacy_label',
                    'label'        => 'Datenschutz Link-Text',
                    'name'         => 'cc_privacy_label',
                    'type'         => 'text',
                    'default_value'=> 'Datenschutzerklärung',
                    'wrapper'      => array( 'width' => '40' ),
                ),

                // ── Trennlinie: Banner-Texte ──────────────────────────────────
                array(
                    'key' => 'field_cc_tab_banner', 'label' => 'Banner-Texte', 'name' => 'cc_tab_banner',
                    'type' => 'message', 'message' => '<strong style="font-size:13px;">Banner-Texte</strong>',
                    'default_value' => '',
                ),

                array(
                    'key'          => 'field_cc_banner_title',
                    'label'        => 'Titel',
                    'name'         => 'cc_banner_title',
                    'type'         => 'text',
                    'default_value'=> 'Wir verwenden Cookies',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_banner_text',
                    'label'        => 'Text',
                    'name'         => 'cc_banner_text',
                    'type'         => 'textarea',
                    'rows'         => 2,
                    'default_value'=> 'Wir setzen Cookies ein, um Ihnen die bestmögliche Nutzung unserer Website zu ermöglichen.',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_accept_all',
                    'label'        => 'Button „Alle akzeptieren"',
                    'name'         => 'cc_accept_all',
                    'type'         => 'text',
                    'default_value'=> 'Alle akzeptieren',
                    'wrapper'      => array( 'width' => '33' ),
                ),
                array(
                    'key'          => 'field_cc_settings_btn',
                    'label'        => 'Button „Einstellungen"',
                    'name'         => 'cc_settings_btn',
                    'type'         => 'text',
                    'default_value'=> 'Einstellungen',
                    'wrapper'      => array( 'width' => '33' ),
                ),
                array(
                    'key'          => 'field_cc_decline_all',
                    'label'        => 'Button „Ablehnen"',
                    'name'         => 'cc_decline_all',
                    'type'         => 'text',
                    'default_value'=> 'Ablehnen',
                    'wrapper'      => array( 'width' => '34' ),
                ),

                // ── Trennlinie: Modal-Texte ───────────────────────────────────
                array(
                    'key' => 'field_cc_tab_modal', 'label' => 'Einstellungs-Modal', 'name' => 'cc_tab_modal',
                    'type' => 'message', 'message' => '<strong style="font-size:13px;">Einstellungs-Modal</strong>',
                    'default_value' => '',
                ),

                array(
                    'key'          => 'field_cc_modal_title',
                    'label'        => 'Modal Titel',
                    'name'         => 'cc_modal_title',
                    'type'         => 'text',
                    'default_value'=> 'Cookie-Einstellungen',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_modal_intro',
                    'label'        => 'Einleitungstext',
                    'name'         => 'cc_modal_intro',
                    'type'         => 'textarea',
                    'rows'         => 2,
                    'default_value'=> 'Hier können Sie Ihre Cookie-Einstellungen jederzeit anpassen.',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_save_btn',
                    'label'        => 'Button „Auswahl speichern"',
                    'name'         => 'cc_save_btn',
                    'type'         => 'text',
                    'default_value'=> 'Auswahl speichern',
                    'wrapper'      => array( 'width' => '50' ),
                ),

                // ── Trennlinie: Kategorien ────────────────────────────────────
                array(
                    'key' => 'field_cc_tab_cats', 'label' => 'Kategorien', 'name' => 'cc_tab_cats',
                    'type' => 'message', 'message' => '<strong style="font-size:13px;">Kategorien</strong>',
                    'default_value' => '',
                ),

                // Notwendig
                array( 'key' => 'field_cc_cat_necessary_label', 'label' => 'Notwendig – Bezeichnung',   'name' => 'cc_cat_necessary_label', 'type' => 'text', 'default_value' => 'Notwendig',          'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_cat_necessary_desc',  'label' => 'Notwendig – Beschreibung',  'name' => 'cc_cat_necessary_desc',  'type' => 'textarea', 'rows' => 2, 'default_value' => 'Technisch erforderliche Cookies für die Grundfunktionen der Website.', 'wrapper' => array( 'width' => '50' ) ),

                // Statistik
                array( 'key' => 'field_cc_cat_statistics_label', 'label' => 'Statistik – Bezeichnung',  'name' => 'cc_cat_statistics_label', 'type' => 'text', 'default_value' => 'Statistik',         'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_cat_statistics_desc',  'label' => 'Statistik – Beschreibung', 'name' => 'cc_cat_statistics_desc',  'type' => 'textarea', 'rows' => 2, 'default_value' => 'Helfen uns zu verstehen, wie Besucher mit der Website interagieren.',    'wrapper' => array( 'width' => '50' ) ),

                // Marketing
                array( 'key' => 'field_cc_cat_marketing_label', 'label' => 'Marketing – Bezeichnung',  'name' => 'cc_cat_marketing_label', 'type' => 'text', 'default_value' => 'Marketing',          'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_cat_marketing_desc',  'label' => 'Marketing – Beschreibung', 'name' => 'cc_cat_marketing_desc',  'type' => 'textarea', 'rows' => 2, 'default_value' => 'Werden für personalisierte Werbung und Remarketing verwendet.',           'wrapper' => array( 'width' => '50' ) ),

                // Komfort
                array( 'key' => 'field_cc_cat_comfort_label', 'label' => 'Komfort – Bezeichnung',  'name' => 'cc_cat_comfort_label', 'type' => 'text', 'default_value' => 'Komfort',             'wrapper' => array( 'width' => '50' ) ),
                array( 'key' => 'field_cc_cat_comfort_desc',  'label' => 'Komfort – Beschreibung', 'name' => 'cc_cat_comfort_desc',  'type' => 'textarea', 'rows' => 2, 'default_value' => 'Ermöglichen eingebettete Inhalte wie YouTube-Videos oder Google Maps.', 'wrapper' => array( 'width' => '50' ) ),
                // ── Trennlinie: Code-Snippets ──────────────────────────────────────────
                array(
                    'key'     => 'field_cc_tab_snippets',
                    'label'   => 'Code-Snippets',
                    'name'    => 'cc_tab_snippets',
                    'type'    => 'message',
                    'message' => '<strong style="font-size:13px;">Code-Snippets pro Kategorie</strong>'
                              . '<p style="margin:.5rem 0 0;color:#666;font-size:12px;">Trage hier deinen Tracking-Code ein. Er wird nur ausgegeben, wenn der Besucher der jeweiligen Kategorie zugestimmt hat.<br>'
                              . '<strong>Head-Code:</strong> Script-Tags, die in den &lt;head&gt; gehören (GA4, FB Pixel …)<br>'
                              . '<strong>Body-Code:</strong> Noscript-Fallbacks für den &lt;body&gt;</p>',
                    'default_value' => '',
                ),


                // Notwendig
                array(
                    'key'          => 'field_cc_snippet_necessary_head',
                    'label'        => 'Notwendig – Head-Code',
                    'name'         => 'cc_snippet_necessary_head',
                    'type'         => 'textarea',
                    'rows'         => 6,
                    'default_value'=> '',
                    'placeholder'  => '<!-- z.B. Consent-Tracking, DSGVO-konformes Chat-Widget -->',
                    'instructions' => 'Wird <strong>immer</strong> geladen – unabhängig vom Cookie-Consent. Nur wirklich technisch notwendige Snippets eintragen.',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_snippet_necessary_body',
                    'label'        => 'Notwendig – Body-Code',
                    'name'         => 'cc_snippet_necessary_body',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    'default_value'=> '',
                    'placeholder'  => '<!-- Noscript Fallback -->',
                    'instructions' => 'Body-Code der immer geladen wird (Noscript-Fallbacks etc.).',
                    'wrapper'      => array( 'width' => '50' ),
                ),

                // Statistik
                array(
                    'key'          => 'field_cc_snippet_statistics_head',
                    'label'        => 'Statistik – Head-Code',
                    'name'         => 'cc_snippet_statistics_head',
                    'type'         => 'textarea',
                    'rows'         => 6,
                    'default_value'=> '',
                    'placeholder'  => '<!-- Google Analytics 4 -->\n<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag("js", new Date());\n  gtag("config", "G-XXXXXXXXXX");\n</script>',
                    'instructions' => 'Wird im &lt;head&gt; geladen (nach Consent). Script-Tags inklusive.',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_snippet_statistics_body',
                    'label'        => 'Statistik – Body-Code',
                    'name'         => 'cc_snippet_statistics_body',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    'default_value'=> '',
                    'placeholder'  => '<!-- Noscript Fallback -->',
                    'instructions' => 'Wird am Anfang des &lt;body&gt; geladen (nach Consent). Meist Noscript-Tags.',
                    'wrapper'      => array( 'width' => '50' ),
                ),

                // Marketing
                array(
                    'key'          => 'field_cc_snippet_marketing_head',
                    'label'        => 'Marketing – Head-Code',
                    'name'         => 'cc_snippet_marketing_head',
                    'type'         => 'textarea',
                    'rows'         => 6,
                    'default_value'=> '',
                    'placeholder'  => '<!-- Meta Pixel Code -->\n<script>\n  !function(f,b,e,v,n,t,s) { ... }\n</script>',
                    'instructions' => 'z.B. Meta Pixel, Google Ads, LinkedIn Insight Tag',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_snippet_marketing_body',
                    'label'        => 'Marketing – Body-Code',
                    'name'         => 'cc_snippet_marketing_body',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    'default_value'=> '',
                    'placeholder'  => '<!-- Noscript Fallback -->',
                    'instructions' => 'Noscript-Fallback für Marketing-Pixel',
                    'wrapper'      => array( 'width' => '50' ),
                ),

                // Komfort
                array(
                    'key'          => 'field_cc_snippet_comfort_head',
                    'label'        => 'Komfort – Head-Code',
                    'name'         => 'cc_snippet_comfort_head',
                    'type'         => 'textarea',
                    'rows'         => 6,
                    'default_value'=> '',
                    'placeholder'  => '<!-- z.B. YouTube API, Google Maps API -->',
                    'instructions' => 'z.B. YouTube iFrame API, Google Maps JS API',
                    'wrapper'      => array( 'width' => '50' ),
                ),
                array(
                    'key'          => 'field_cc_snippet_comfort_body',
                    'label'        => 'Komfort – Body-Code',
                    'name'         => 'cc_snippet_comfort_body',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    'default_value'=> '',
                    'placeholder'  => '<!-- Noscript Fallback -->',
                    'instructions' => 'Noscript-Fallback für Komfort-Dienste',
                    'wrapper'      => array( 'width' => '50' ),
                ),
            ),
            'location' => array( array( array(
                'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-cookie-consent',
            ))),
            'menu_order'            => 36,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
        ) );
    }
    // ─── Snippet-Config ausgeben ──────────────────────────────────────────────
    // Alle vom Admin eingetragenen Code-Snippets werden als JSON in den Head
    // geschrieben. Das JS injiziert sie basierend auf dem gespeicherten Consent.

    public function output_snippets(): void {
        if ( is_admin() ) return;
        if ( ! function_exists( 'get_field' ) ) return;

        $snippets = array();

        foreach ( array( 'necessary', 'statistics', 'marketing', 'comfort' ) as $cat ) {
            $head = get_field( "cc_snippet_{$cat}_head", 'option' );
            $body = get_field( "cc_snippet_{$cat}_body", 'option' );

            $snippets[ $cat ] = array(
                'head'     => $head ? trim( wp_unslash( $head ) ) : '',
                'body'     => $body ? trim( wp_unslash( $body ) ) : '',
                'required' => $cat === 'necessary',  // Notwendige immer laden
            );
        }

        // Nur ausgeben wenn mindestens ein Snippet konfiguriert ist
        $has_snippets = array_filter( array_map(
            fn( $s ) => $s['head'] !== '' || $s['body'] !== '',
            $snippets
        ));

        if ( empty( $has_snippets ) ) return;

        echo '<script id="cookie-snippets-config">window.cookieSnippets = '
            . wp_json_encode( $snippets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
            . ';</script>' . "\n";
    }


}

new MediaLab_Cookie_Consent();
