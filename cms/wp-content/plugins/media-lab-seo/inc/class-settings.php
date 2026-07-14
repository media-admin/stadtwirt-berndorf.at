<?php
/**
 * MLT_Settings
 *
 * Registriert die Admin-Einstellungsseite mit drei Bereichen:
 *  1. SEO         – GSC-Verifikation, Bing-Verifikation, OG-Fallback-Bild, Standard-Zeitraum
 *  2. Analytics   – Toggle + Provider-Auswahl (GA4 / GTM) + Tracking-ID
 *  3. Report-Mail – Wöchentlicher HTML-Report via SMTP (Agency Core)
 *                   → Mehrere Empfänger (dynamische Liste)
 *                   → Konfigurierbarer Versandzeitpunkt (Wochentag + Uhrzeit + Zeitzone)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Settings {

    // Option-Keys
    const OPT_GSC_VERIFICATION   = 'mlt_gsc_verification';
    const OPT_OG_IMAGE           = 'mlt_og_default_image';
    const OPT_ANALYTICS_ENABLED  = 'mlt_analytics_enabled';
    const OPT_ANALYTICS_PROVIDER = 'mlt_analytics_provider';
    const OPT_ANALYTICS_ID       = 'mlt_analytics_id';
    const OPT_REPORT_ENABLED     = 'mlt_report_enabled';
    const OPT_REPORT_EMAIL       = 'mlt_report_email'; // Legacy – bleibt für Migration

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mlt_test_mail', [ $this, 'ajax_test_mail' ] );

        // Cron neu planen wenn Report-Toggle oder Schedule-Optionen geändert werden
        add_action( 'update_option_' . self::OPT_REPORT_ENABLED,  [ $this, 'sync_cron' ], 10, 2 );
        add_action( 'update_option_' . MLT_REPORT_WEEKDAY_KEY,    [ $this, 'reschedule_cron' ] );
        add_action( 'update_option_' . MLT_REPORT_TIME_KEY,       [ $this, 'reschedule_cron' ] );
        add_action( 'update_option_' . MLT_REPORT_TIMEZONE_KEY,   [ $this, 'reschedule_cron' ] );
    }

    // ── Admin-Menü ────────────────────────────────────────────────────────────

    public function register_menu() {
        add_menu_page(
            'SEO Toolkit',
            'SEO Toolkit',
            'manage_options',
            'media-lab-seo',
            [ $this, 'render_page' ],
            'dashicons-chart-line',
            58
        );

        add_submenu_page(
            'media-lab-seo',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'media-lab-seo',
            [ $this, 'render_page' ]
        );
    }

    // ── Settings API ──────────────────────────────────────────────────────────

    public function register_settings() {
        $options = [
            self::OPT_GSC_VERIFICATION,
            'mlt_bing_verification',
            'mlt_default_range',
            self::OPT_OG_IMAGE,
            self::OPT_ANALYTICS_ENABLED,
            self::OPT_ANALYTICS_PROVIDER,
            self::OPT_ANALYTICS_ID,
            self::OPT_REPORT_ENABLED,
            // Schedule-Optionen
            MLT_REPORT_WEEKDAY_KEY,
            MLT_REPORT_TIME_KEY,
            MLT_REPORT_TIMEZONE_KEY,
            // GSC / GA4 / Matomo
            'mlt_gsc_client_id',
            'mlt_gsc_client_secret',
            'mlt_gsc_property_url',
            'mlt_ga4_property_id',
            'mlt_ga4_service_account_json',
            'mlt_matomo_url',
            'mlt_matomo_token',
            'mlt_matomo_site_id',
        ];

        foreach ( $options as $option ) {
            register_setting( 'mlt_settings_group', $option );
        }

        // Empfänger-Liste separat mit eigenem Sanitizer
        register_setting( 'mlt_settings_group', MLT_REPORT_RECIPIENTS_KEY, [
            'sanitize_callback' => [ $this, 'sanitize_recipients' ],
        ] );

        // Legacy OPT_REPORT_EMAIL NICHT registrieren (nur Lesezugriff für Migration)
    }

    public function sanitize_recipients( $input ): array {
        if ( ! is_array( $input ) ) return [];
        return array_values(
            array_filter(
                array_map( 'sanitize_email', $input ),
                'is_email'
            )
        );
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_media-lab-seo' ) return;

        wp_enqueue_media();
        wp_enqueue_style(
            'mlt-admin',
            MLT_URL . 'assets/admin.css',
            [],
            MLT_VERSION
        );
        wp_enqueue_script(
            'mlt-admin',
            MLT_URL . 'assets/admin.js',
            [ 'jquery' ],
            MLT_VERSION,
            true
        );
        wp_localize_script( 'mlt-admin', 'mltAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mlt_test_mail' ),
            'coreUrl' => admin_url( 'admin.php?page=agency-core-smtp' ),
        ] );
    }

    // ── Settings-Seite rendern ────────────────────────────────────────────────

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $gsc_code       = get_option( self::OPT_GSC_VERIFICATION, '' );
        $og_image_id    = get_option( self::OPT_OG_IMAGE, 0 );
        $analytics_on   = get_option( self::OPT_ANALYTICS_ENABLED, 0 );
        $analytics_prov = get_option( self::OPT_ANALYTICS_PROVIDER, 'ga4' );
        $analytics_id   = get_option( self::OPT_ANALYTICS_ID, '' );
        $report_on      = get_option( self::OPT_REPORT_ENABLED, 0 );
        $default_range  = (int) get_option( 'mlt_default_range', 28 );

        // Empfänger: neue Liste, Fallback auf Legacy-Einzel-Feld
        $recipients = mlt_get_report_recipients();

        // Schedule
        $schedule  = mlt_get_report_schedule();
        $weekdays  = [
            1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch',
            4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 0 => 'Sonntag',
        ];
        $timezones = [
            'UTC'                 => 'UTC',
            'Europe/Vienna'       => 'Wien (CET/CEST)',
            'Europe/Berlin'       => 'Berlin (CET/CEST)',
            'Europe/Zurich'       => 'Zürich (CET/CEST)',
            'Europe/London'       => 'London (GMT/BST)',
            'Europe/Paris'        => 'Paris (CET/CEST)',
            'America/New_York'    => 'New York (ET)',
            'America/Chicago'     => 'Chicago (CT)',
            'America/Denver'      => 'Denver (MT)',
            'America/Los_Angeles' => 'Los Angeles (PT)',
        ];

        // Nächsten Versand berechnen
        $next_ts  = wp_next_scheduled( 'mlt_weekly_report' );
        $next_str = $next_ts ? wp_date( 'd.m.Y H:i', $next_ts ) : '—';

        $og_image_url    = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'medium' ) : '';
        $smtp_configured = $this->is_smtp_configured();
        $recipients_key  = MLT_REPORT_RECIPIENTS_KEY;
        ?>
        <div class="wrap mlt-wrap">

            <div class="mlt-header">
                <h1>SEO Toolkit <span class="mlt-version">v<?php echo esc_html( MLT_VERSION ); ?></span></h1>
                <p class="mlt-subtitle">SEO &amp; Analytics für Media Lab Kundenprojekte</p>
            </div>

            <?php settings_errors( 'mlt_settings_group' ); ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'mlt_settings_group' ); ?>

                <div class="mlt-grid">

                    <!-- ── SEO ─────────────────────────────────────────── -->
                    <div class="mlt-card">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">🔍</span>
                            <h2>SEO</h2>
                        </div>
                        <div class="mlt-card__body">

                            <div class="mlt-field">
                                <label for="mlt_gsc_verification">Google Search Console – Verification Code</label>
                                <input
                                    type="text"
                                    id="mlt_gsc_verification"
                                    name="<?php echo self::OPT_GSC_VERIFICATION; ?>"
                                    value="<?php echo esc_attr( $gsc_code ); ?>"
                                    class="regular-text"
                                    placeholder="google-site-verification=ABC123..."
                                />
                                <p class="mlt-hint">Wird als <code>&lt;meta name="google-site-verification"&gt;</code> im <code>&lt;head&gt;</code> ausgegeben.</p>
                            </div>

                            <div class="mlt-field">
                                <label for="mlt_bing_verification">Bing Webmaster Tools – Verification Code</label>
                                <input
                                    type="text"
                                    id="mlt_bing_verification"
                                    name="mlt_bing_verification"
                                    value="<?php echo esc_attr( get_option( 'mlt_bing_verification', '' ) ); ?>"
                                    class="regular-text"
                                    placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
                                />
                                <p class="mlt-hint">
                                    Wird als <code>&lt;meta name="msvalidate.01"&gt;</code> im <code>&lt;head&gt;</code> ausgegeben.
                                    Nur den Wert aus dem <code>content</code>-Attribut eintragen, nicht den ganzen Tag.<br>
                                    <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">→ Bing Webmaster Tools öffnen</a>
                                </p>
                            </div>

                            <div class="mlt-field">
                                <label for="mlt_default_range">Standard-Zeitraum (Dashboard)</label>
                                <select id="mlt_default_range" name="mlt_default_range" style="width:auto;">
                                    <?php foreach ( [ 7 => '7 Tage', 28 => '28 Tage', 90 => '90 Tage', 365 => '365 Tage' ] as $val => $label ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $default_range, $val ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mlt-hint">Zeitraum der beim Öffnen des Dashboards standardmäßig angezeigt wird. Im Dashboard selbst kann jederzeit ein anderer Zeitraum gewählt werden.</p>
                            </div>

                            <div class="mlt-field">
                                <label>Open Graph – Fallback-Bild</label>
                                <div class="mlt-media-field">
                                    <?php if ( $og_image_url ) : ?>
                                        <img src="<?php echo esc_url( $og_image_url ); ?>" alt="" class="mlt-og-preview" />
                                    <?php endif; ?>
                                    <input type="hidden" id="mlt_og_image_id" name="<?php echo self::OPT_OG_IMAGE; ?>" value="<?php echo esc_attr( $og_image_id ); ?>" />
                                    <button type="button" class="button" id="mlt_og_image_btn">
                                        <?php echo $og_image_url ? 'Bild ändern' : 'Bild auswählen'; ?>
                                    </button>
                                    <?php if ( $og_image_url ) : ?>
                                        <button type="button" class="button mlt-btn-remove" id="mlt_og_image_remove">Entfernen</button>
                                    <?php endif; ?>
                                </div>
                                <p class="mlt-hint">Wird verwendet wenn eine Seite kein eigenes Beitragsbild hat.</p>
                            </div>

                        </div>
                    </div>

                    <!-- ── Analytics ───────────────────────────────────── -->
                    <div class="mlt-card">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">📊</span>
                            <h2>Analytics Tracking</h2>
                        </div>
                        <div class="mlt-card__body">

                            <div class="mlt-field mlt-field--toggle">
                                <label class="mlt-toggle" for="mlt_analytics_enabled">
                                    <input
                                        type="checkbox"
                                        id="mlt_analytics_enabled"
                                        name="<?php echo self::OPT_ANALYTICS_ENABLED; ?>"
                                        value="1"
                                        <?php checked( $analytics_on, 1 ); ?>
                                    />
                                    <span class="mlt-toggle__slider"></span>
                                    <span class="mlt-toggle__label">Analytics aktivieren</span>
                                </label>
                                <p class="mlt-hint">Wenn deaktiviert, wird <strong>kein Script</strong> geladen und keine Daten erhoben.</p>
                            </div>

                            <div class="mlt-analytics-fields<?php echo $analytics_on ? '' : ' mlt-hidden'; ?>">

                                <div class="mlt-field">
                                    <label>Provider</label>
                                    <div class="mlt-radio-group">
                                        <label class="mlt-radio">
                                            <input type="radio" name="<?php echo self::OPT_ANALYTICS_PROVIDER; ?>" value="ga4" <?php checked( $analytics_prov, 'ga4' ); ?> />
                                            Google Analytics 4
                                        </label>
                                        <label class="mlt-radio">
                                            <input type="radio" name="<?php echo self::OPT_ANALYTICS_PROVIDER; ?>" value="gtm" <?php checked( $analytics_prov, 'gtm' ); ?> />
                                            Google Tag Manager
                                        </label>
                                    </div>
                                </div>

                                <div class="mlt-field">
                                    <label for="mlt_analytics_id">
                                        <span class="mlt-label-ga4">Measurement ID</span>
                                        <span class="mlt-label-gtm mlt-hidden">Container ID</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="mlt_analytics_id"
                                        name="<?php echo self::OPT_ANALYTICS_ID; ?>"
                                        value="<?php echo esc_attr( $analytics_id ); ?>"
                                        class="regular-text"
                                        placeholder="G-XXXXXXXXXX"
                                    />
                                    <p class="mlt-hint mlt-hint-ga4">Format: <code>G-XXXXXXXXXX</code></p>
                                    <p class="mlt-hint mlt-hint-gtm mlt-hidden">Format: <code>GTM-XXXXXXX</code></p>
                                </div>

                            </div>

                        </div>
                    </div>

                    <!-- ── GSC API ─────────────────────────────────────── -->
                    <div class="mlt-card mlt-card--full" id="gsc">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">🔗</span>
                            <h2>Google Search Console API</h2>
                        </div>
                        <div class="mlt-card__body">
                            <?php $gsc = MLT_GSC_API::instance();
                            if ( $gsc->is_connected() ) : ?>
                                <div class="mlt-notice mlt-notice--success">
                                    <strong>✓ Verbunden.</strong> GSC-Daten werden im Dashboard angezeigt.
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mlt_gsc_disconnect' ), 'mlt_gsc_disconnect' ) ); ?>"
                                        class="button button-small mlt-btn-remove" style="margin-left:12px">Verbindung trennen</a>
                                </div>
                            <?php endif; ?>

                            <div class="mlt-grid" style="margin-top:0">
                                <div class="mlt-field">
                                    <label for="mlt_gsc_client_id">OAuth Client ID</label>
                                    <input type="text" id="mlt_gsc_client_id" name="mlt_gsc_client_id"
                                        value="<?php echo esc_attr( get_option( 'mlt_gsc_client_id', '' ) ); ?>"
                                        class="regular-text" placeholder="123456789-abc...apps.googleusercontent.com" />
                                    <p class="mlt-hint">Google Cloud Console → APIs → OAuth2-Credentials</p>
                                </div>
                                <div class="mlt-field">
                                    <label for="mlt_gsc_client_secret">OAuth Client Secret</label>
                                    <input type="password" id="mlt_gsc_client_secret" name="mlt_gsc_client_secret"
                                        value="<?php echo esc_attr( get_option( 'mlt_gsc_client_secret', '' ) ); ?>"
                                        class="regular-text" placeholder="GOCSPX-…" />
                                </div>
                                <div class="mlt-field">
                                    <label for="mlt_gsc_property_url">GSC Property URL</label>
                                    <input type="text" id="mlt_gsc_property_url" name="mlt_gsc_property_url"
                                        value="<?php echo esc_attr( get_option( 'mlt_gsc_property_url', '' ) ); ?>"
                                        class="regular-text" placeholder="https://www.example.at/ oder sc-domain:example.at" />
                                    <p class="mlt-hint">URL-Prefix: https://www.example.at/ — Domain Property: sc-domain:example.at</p>
                                </div>
                                <div class="mlt-field">
                                    <label>Redirect URI (in Google Cloud eintragen)</label>
                                    <code style="display:block;padding:8px;background:#f3f4f6;border-radius:4px;font-size:12px"><?php echo esc_html( MLT_GSC_API::instance()->get_redirect_uri() ); ?></code>
                                </div>
                            </div>

                            <?php if ( $gsc->is_configured() && ! $gsc->is_connected() ) : ?>
                                <a href="<?php echo esc_url( $gsc->get_auth_url() ); ?>" class="button button-primary">Mit Google verbinden</a>
                                <p class="mlt-hint" style="margin-top:8px">Zuerst speichern, dann verbinden.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── GA4 OAuth API ───────────────────────────────── -->
                    <div class="mlt-card mlt-card--full" id="ga4">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">📈</span>
                            <h2>Google Analytics 4 API</h2>
                        </div>
                        <div class="mlt-card__body">
                            <p class="mlt-hint" style="margin-bottom:16px">Für das SEO-Dashboard und den Report-Mailer. Gleiche OAuth-App wie GSC — kein separates Setup.</p>
                            <?php $ga4 = MLT_GA4_API::instance();
                            if ( $ga4->is_connected() ) : ?>
                                <div class="mlt-notice mlt-notice--success">
                                    <strong>✓ Verbunden.</strong> GA4-Daten werden im Dashboard angezeigt.
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mlt_ga4_disconnect' ), 'mlt_ga4_disconnect' ) ); ?>"
                                        class="button button-small mlt-btn-remove" style="margin-left:12px">Verbindung trennen</a>
                                </div>
                            <?php endif; ?>

                            <div class="mlt-grid" style="margin-top:0">
                                <div class="mlt-field">
                                    <label for="mlt_ga4_property_id">GA4 Property ID</label>
                                    <input type="text" id="mlt_ga4_property_id" name="mlt_ga4_property_id"
                                        value="<?php echo esc_attr( get_option( 'mlt_ga4_property_id', '' ) ); ?>"
                                        class="regular-text" placeholder="123456789" />
                                    <p class="mlt-hint">Numerische ID aus GA → Verwaltung → Property-Details (nicht G-XXXXXXXX)</p>
                                </div>
                                <div class="mlt-field">
                                    <label>OAuth Credentials</label>
                                    <p class="mlt-hint" style="margin:0">Werden von der GSC-Konfiguration oben übernommen.</p>
                                </div>
                                <div class="mlt-field">
                                    <label>Redirect URI (in Google Cloud eintragen)</label>
                                    <code style="display:block;padding:8px;background:#f3f4f6;border-radius:4px;font-size:12px"><?php echo esc_html( MLT_GA4_API::instance()->get_redirect_uri() ); ?></code>
                                </div>
                            </div>

                            <?php if ( $ga4->is_configured() && ! $ga4->is_connected() ) : ?>
                                <a href="<?php echo esc_url( $ga4->get_auth_url() ); ?>" class="button button-primary" style="margin-top:8px">Mit Google verbinden</a>
                                <p class="mlt-hint" style="margin-top:8px">Zuerst Property ID eintragen und speichern, dann verbinden.</p>
                            <?php elseif ( ! $ga4->is_configured() ) : ?>
                                <p class="mlt-hint" style="margin-top:8px">⚠ Bitte zuerst GSC OAuth-Credentials und GA4 Property ID eintragen und speichern.</p>
                            <?php endif; ?>

                            <?php if ( get_option( 'mlt_ga4_service_account_json' ) && ! $ga4->is_connected() ) : ?>
                                <div class="mlt-notice mlt-notice--warning" style="margin-top:16px">
                                    <strong>Service Account (Legacy) gefunden.</strong> Wird als Fallback verwendet.
                                </div>
                                <div class="mlt-field" style="margin-top:12px">
                                    <label for="mlt_ga4_service_account_json">Service Account JSON (Legacy-Fallback)</label>
                                    <textarea id="mlt_ga4_service_account_json" name="mlt_ga4_service_account_json"
                                        rows="3" class="large-text code"
                                        style="font-size:12px;font-family:monospace"><?php echo esc_textarea( get_option( 'mlt_ga4_service_account_json', '' ) ); ?></textarea>
                                    <p class="mlt-hint">Zum Entfernen: Inhalt löschen und speichern.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ── Matomo Reporting ─────────────────────────────── -->
                    <?php if ( get_option( 'mlt_analytics_provider', 'ga4' ) === 'matomo' ) : ?>
                    <div class="mlt-card mlt-card--full">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">📊</span>
                            <h2>Matomo Reporting</h2>
                        </div>
                        <div class="mlt-card__body">
                            <div class="mlt-grid">
                                <div class="mlt-field">
                                    <label for="mlt_matomo_url">Matomo URL</label>
                                    <input type="url" id="mlt_matomo_url" name="mlt_matomo_url"
                                        value="<?php echo esc_attr( get_option( 'mlt_matomo_url', '' ) ); ?>"
                                        class="regular-text" placeholder="https://matomo.example.at" />
                                </div>
                                <div class="mlt-field">
                                    <label for="mlt_matomo_token">API Token</label>
                                    <input type="password" id="mlt_matomo_token" name="mlt_matomo_token"
                                        value="<?php echo esc_attr( get_option( 'mlt_matomo_token', '' ) ); ?>"
                                        class="regular-text" />
                                    <p class="mlt-hint">Matomo → Persönliche Einstellungen → API-Authentifizierungs-Token</p>
                                </div>
                                <div class="mlt-field">
                                    <label for="mlt_matomo_site_id">Site ID</label>
                                    <input type="number" id="mlt_matomo_site_id" name="mlt_matomo_site_id"
                                        value="<?php echo esc_attr( get_option( 'mlt_matomo_site_id', '1' ) ); ?>"
                                        class="small-text" min="1" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ── Report-Mail ──────────────────────────────────── -->
                    <div class="mlt-card mlt-card--full">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">📬</span>
                            <h2>Wöchentlicher Report</h2>
                        </div>
                        <div class="mlt-card__body">

                            <?php if ( ! $smtp_configured ) : ?>
                                <div class="mlt-notice mlt-notice--warning">
                                    <strong>⚠ SMTP nicht konfiguriert.</strong>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-smtp' ) ); ?>">→ SMTP jetzt konfigurieren</a>
                                </div>
                            <?php else : ?>
                                <div class="mlt-notice mlt-notice--success">
                                    <strong>✓ SMTP aktiv.</strong> Versand läuft über Agency Core.
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-smtp' ) ); ?>">Einstellungen</a>
                                </div>
                            <?php endif; ?>

                            <!-- Toggle -->
                            <div class="mlt-field mlt-field--toggle">
                                <label class="mlt-toggle" for="mlt_report_enabled">
                                    <input
                                        type="checkbox"
                                        id="mlt_report_enabled"
                                        name="<?php echo self::OPT_REPORT_ENABLED; ?>"
                                        value="1"
                                        <?php checked( $report_on, 1 ); ?>
                                        <?php disabled( ! $smtp_configured ); ?>
                                    />
                                    <span class="mlt-toggle__slider"></span>
                                    <span class="mlt-toggle__label">Wöchentlichen Report aktivieren</span>
                                </label>
                            </div>

                            <!-- ── Empfänger (dynamische Liste) ─────────── -->
                            <div class="mlt-field">
                                <label>Empfänger</label>
                                <div id="mlt-recipients-list">
                                    <?php
                                    $display = ! empty( $recipients ) ? $recipients : [ '' ];
                                    foreach ( $display as $email ) : ?>
                                    <div class="mlt-recipient-row" style="display:flex;gap:8px;margin-bottom:6px;">
                                        <input
                                            type="email"
                                            name="<?php echo esc_attr( $recipients_key ); ?>[]"
                                            class="regular-text mlt-recipient-input"
                                            placeholder="empfaenger@example.com"
                                            value="<?php echo esc_attr( $email ); ?>"
                                        >
                                        <button type="button" class="button mlt-recipient-remove" title="Entfernen">✕</button>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="mlt-recipient-add" class="button button-secondary" style="margin-top:4px;">
                                    + Empfänger hinzufügen
                                </button>
                                <p class="mlt-hint">Der Report wird an alle eingetragenen Adressen versendet.</p>
                            </div>

                            <!-- ── Versandzeitpunkt ──────────────────────── -->
                            <div class="mlt-field">
                                <label>Versandzeitpunkt</label>
                                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">

                                    <select name="<?php echo MLT_REPORT_WEEKDAY_KEY; ?>" id="mlt_report_weekday" class="regular-text" style="width:auto;">
                                        <?php foreach ( $weekdays as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $schedule['weekday'], $val ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <input
                                        type="time"
                                        name="<?php echo MLT_REPORT_TIME_KEY; ?>"
                                        id="mlt_report_time"
                                        value="<?php echo esc_attr( $schedule['time'] ); ?>"
                                        step="300"
                                        style="width:120px;"
                                    >

                                    <select name="<?php echo MLT_REPORT_TIMEZONE_KEY; ?>" id="mlt_report_timezone" style="width:auto;">
                                        <?php foreach ( $timezones as $tz_key => $tz_label ) : ?>
                                        <option value="<?php echo esc_attr( $tz_key ); ?>" <?php selected( $schedule['timezone'], $tz_key ); ?>>
                                            <?php echo esc_html( $tz_label ); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>

                                </div>
                                <p class="mlt-hint" style="margin-top:8px;padding:8px 12px;background:#f0f6fc;border-left:3px solid #2271b1;">
                                    Nächster geplanter Versand: <strong><?php echo esc_html( $next_str ); ?></strong>
                                    <?php if ( $next_ts ) : ?>
                                        &nbsp;·&nbsp; <em style="color:#6b7280;"><?php echo esc_html( $schedule['timezone'] ); ?></em>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- Test-Mail -->
                            <div class="mlt-field mlt-test-mail">
                                <label>Test-Mail</label>
                                <div class="mlt-test-mail__row">
                                    <button
                                        type="button"
                                        id="mlt_send_test_mail"
                                        class="button button-secondary"
                                        <?php disabled( ! $smtp_configured ); ?>
                                    >
                                        Test-Mail senden
                                    </button>
                                    <span id="mlt_test_mail_result" class="mlt-test-mail__result"></span>
                                </div>
                                <p class="mlt-hint">Sendet einen Test-Report an den ersten eingetragenen Empfänger. Speichern nicht vergessen.</p>
                            </div>

                        </div>
                    </div>

                </div><!-- .mlt-grid -->

                <?php submit_button( 'Einstellungen speichern' ); ?>

            </form>
        </div>

        <!-- JS für dynamische Empfänger-Liste -->
        <script>
        (function($){
            var $list  = $('#mlt-recipients-list');
            var key    = '<?php echo esc_js( $recipients_key ); ?>';
            var rowTpl = '<div class="mlt-recipient-row" style="display:flex;gap:8px;margin-bottom:6px;">'
                + '<input type="email" name="' + key + '[]" class="regular-text mlt-recipient-input" placeholder="empfaenger@example.com" value="">'
                + '<button type="button" class="button mlt-recipient-remove" title="Entfernen">✕</button>'
                + '</div>';

            $('#mlt-recipient-add').on('click', function(){
                $list.append(rowTpl);
                $list.find('.mlt-recipient-input').last().focus();
            });

            $list.on('click', '.mlt-recipient-remove', function(){
                var $rows = $list.find('.mlt-recipient-row');
                if ( $rows.length > 1 ) {
                    $(this).closest('.mlt-recipient-row').remove();
                } else {
                    $(this).closest('.mlt-recipient-row').find('input').val('');
                }
            });
        }(jQuery));
        </script>
        <?php
    }

    // ── SMTP-Status prüfen ────────────────────────────────────────────────────

    private function is_smtp_configured() {
        if ( function_exists( 'get_field' ) ) {
            $smtp = get_field( 'smtp_settings', 'option' );
            if ( ! empty( $smtp['enabled'] ) && ! empty( $smtp['host'] ) ) return true;
        }
        // OAuth-Modus gilt ebenfalls als konfiguriert
        $mode = get_option( MEDIALAB_SMTP_MODE_KEY, 'smtp' );
        if ( $mode !== 'smtp' ) return true;

        return defined( 'MEDIALAB_SMTP_ENABLED' ) && MEDIALAB_SMTP_ENABLED
            && defined( 'MEDIALAB_SMTP_HOST' ) && MEDIALAB_SMTP_HOST;
    }

    // ── AJAX: Test-Mail ───────────────────────────────────────────────────────

    public function ajax_test_mail() {
        check_ajax_referer( 'mlt_test_mail', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Keine Berechtigung.' );

        $recipients = mlt_get_report_recipients();
        $to         = ! empty( $recipients ) ? $recipients[0] : get_option( 'admin_email' );
        $to         = sanitize_email( $_POST['email'] ?? $to );

        $subject = '[' . get_bloginfo( 'name' ) . '] Media Lab SEO Toolkit – Test-Mail';
        $message = $this->build_test_mail_html();
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $error = null;
        add_action( 'wp_mail_failed', function( $e ) use ( &$error ) {
            $error = $e->get_error_message();
        } );

        $sent = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            wp_send_json_success( 'Mail erfolgreich gesendet an ' . esc_html( $to ) );
        } else {
            wp_send_json_error( $error ?: 'Unbekannter Fehler beim Senden.' );
        }
    }

    private function build_test_mail_html() {
        $site = get_bloginfo( 'name' );
        $url  = get_bloginfo( 'url' );
        $time = wp_date( 'd.m.Y H:i:s' );
        return "
        <div style='font-family:sans-serif;max-width:520px;margin:0 auto;padding:32px 24px;background:#f9fafb;border-radius:8px'>
            <h2 style='margin:0 0 8px;color:#1a1a2e'>✓ SMTP funktioniert</h2>
            <p style='color:#6b7280;margin:0 0 24px'>Diese Test-Mail wurde von <strong>Media Lab SEO Toolkit</strong> gesendet.</p>
            <table style='width:100%;border-collapse:collapse'>
                <tr><td style='padding:8px 0;color:#9ca3af;font-size:13px'>Website</td><td style='padding:8px 0;font-size:13px'><a href='{$url}'>{$site}</a></td></tr>
                <tr><td style='padding:8px 0;color:#9ca3af;font-size:13px'>Zeitpunkt</td><td style='padding:8px 0;font-size:13px'>{$time}</td></tr>
                <tr><td style='padding:8px 0;color:#9ca3af;font-size:13px'>Plugin</td><td style='padding:8px 0;font-size:13px'>Media Lab SEO Toolkit v" . MLT_VERSION . "</td></tr>
            </table>
        </div>";
    }

    // ── WP-Cron: Scheduling ───────────────────────────────────────────────────

    public function sync_cron( $old, $new ) {
        if ( $new && ! $old ) {
            if ( ! wp_next_scheduled( 'mlt_weekly_report' ) ) {
                mlt_schedule_report_cron();
            }
        } elseif ( ! $new && $old ) {
            $ts = wp_next_scheduled( 'mlt_weekly_report' );
            if ( $ts ) wp_unschedule_event( $ts, 'mlt_weekly_report' );
        }
    }

    public function reschedule_cron() {
        if ( ! get_option( self::OPT_REPORT_ENABLED ) ) return;
        mlt_schedule_report_cron();
    }
}