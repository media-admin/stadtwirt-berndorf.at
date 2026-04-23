<?php
/**
 * MLT_Settings
 *
 * Registriert die Admin-Einstellungsseite mit drei Bereichen:
 *  1. SEO         – GSC-Verifikation, OG-Fallback-Bild
 *  2. Analytics   – Toggle + Provider-Auswahl (GA4 / GTM) + Tracking-ID
 *  3. Report-Mail – Wöchentlicher HTML-Report via SMTP (Agency Core)
 *
 * Der SMTP-Versand läuft ausschließlich über media-lab-agency-core.
 * Kein eigenes SMTP-Formular – nur ein Test-Mail-Button für schnelles Feedback.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Settings {

    // Option-Keys
    const OPT_GSC_VERIFICATION  = 'mlt_gsc_verification';
    const OPT_OG_IMAGE          = 'mlt_og_default_image';
    const OPT_ANALYTICS_ENABLED = 'mlt_analytics_enabled';
    const OPT_ANALYTICS_PROVIDER = 'mlt_analytics_provider';
    const OPT_ANALYTICS_ID      = 'mlt_analytics_id';
    const OPT_REPORT_ENABLED    = 'mlt_report_enabled';
    const OPT_REPORT_EMAIL      = 'mlt_report_email';

    public function __construct() {
        add_action( 'admin_menu',              [ $this, 'register_menu' ] );
        add_action( 'admin_init',              [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts',   [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mlt_test_mail',   [ $this, 'ajax_test_mail' ] );
        add_action( 'mlt_weekly_report',       [ $this, 'send_weekly_report' ] );
        add_action( 'update_option_' . self::OPT_REPORT_ENABLED, [ $this, 'sync_cron' ], 10, 2 );
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

        // Erstes Untermenü = gleiche Seite mit Label "Einstellungen"
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
            self::OPT_OG_IMAGE,
            self::OPT_ANALYTICS_ENABLED,
            self::OPT_ANALYTICS_PROVIDER,
            self::OPT_ANALYTICS_ID,
            self::OPT_REPORT_ENABLED,
            self::OPT_REPORT_EMAIL,
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
    }

    // Sanitizer pro Feld
    public function sanitize_option_gsc_verification( $val ) {
        return sanitize_text_field( $val );
    }
    public function sanitize_option_og_default_image( $val ) {
        return absint( $val );
    }
    public function sanitize_option_analytics_enabled( $val ) {
        return $val ? 1 : 0;
    }
    public function sanitize_option_analytics_provider( $val ) {
        return in_array( $val, [ 'ga4', 'gtm' ], true ) ? $val : 'ga4';
    }
    public function sanitize_option_analytics_id( $val ) {
        return sanitize_text_field( $val );
    }
    public function sanitize_option_report_enabled( $val ) {
        return $val ? 1 : 0;
    }
    public function sanitize_option_report_email( $val ) {
        return sanitize_email( $val );
    }

    // ── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_media-lab-seo' ) return;

        wp_enqueue_media(); // für OG-Bild-Upload
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

        $gsc_code         = get_option( self::OPT_GSC_VERIFICATION, '' );
        $og_image_id      = get_option( self::OPT_OG_IMAGE, 0 );
        $analytics_on     = get_option( self::OPT_ANALYTICS_ENABLED, 0 );
        $analytics_prov   = get_option( self::OPT_ANALYTICS_PROVIDER, 'ga4' );
        $analytics_id     = get_option( self::OPT_ANALYTICS_ID, '' );
        $report_on        = get_option( self::OPT_REPORT_ENABLED, 0 );
        $report_email     = get_option( self::OPT_REPORT_EMAIL, get_option( 'admin_email' ) );

        $og_image_url = $og_image_id ? wp_get_attachment_image_url( $og_image_id, 'medium' ) : '';

        // SMTP-Status aus Agency Core lesen
        $smtp_configured = $this->is_smtp_configured();
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
                                <p class="mlt-hint">Wird als <code>&lt;meta name="google-site-verification"&gt;</code> im <code>&lt;head&gt;</code> ausgegeben. Kein Script, kein Datenschutz-Problem.</p>
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
                            <?php
                            $gsc = MLT_GSC_API::instance();
                            if ( $gsc->is_connected() ) : ?>
                                <div class="mlt-notice mlt-notice--success">
                                    <strong>✓ Verbunden.</strong> GSC-Daten werden im Dashboard angezeigt.
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                        <?php wp_nonce_field( 'mlt_gsc_disconnect' ); ?>
                                        <input type="hidden" name="action" value="mlt_gsc_disconnect">
                                        <button type="submit" class="button button-small mlt-btn-remove" style="margin-left:12px">Verbindung trennen</button>
                                    </form>
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
                                    <input type="url" id="mlt_gsc_property_url" name="mlt_gsc_property_url"
                                        value="<?php echo esc_attr( get_option( 'mlt_gsc_property_url', '' ) ); ?>"
                                        class="regular-text" placeholder="https://www.example.at/" />
                                    <p class="mlt-hint">Exakt wie in GSC eingetragen (mit https:// und trailing slash)</p>
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

                    <!-- ── Analytics Adapter (Reporting) ───────────────── -->
                    <div class="mlt-card mlt-card--full">
                        <div class="mlt-card__header">
                            <span class="mlt-card__icon">📈</span>
                            <h2>Analytics Reporting</h2>
                        </div>
                        <div class="mlt-card__body">
                            <p class="mlt-hint" style="margin-bottom:16px">Für das SEO-Dashboard und den Report-Mailer. Unabhängig vom Tracking oben — hier werden API-Credentials für den Server-seitigen Datenabruf eingetragen.</p>
                            <?php $prov = get_option( 'mlt_analytics_provider', 'ga4' ); ?>
                            <div class="mlt-grid">
                                <?php if ( $prov === 'ga4' ) : ?>
                                <div class="mlt-field">
                                    <label for="mlt_ga4_property_id">GA4 Property ID</label>
                                    <input type="text" id="mlt_ga4_property_id" name="mlt_ga4_property_id"
                                        value="<?php echo esc_attr( get_option( 'mlt_ga4_property_id', '' ) ); ?>"
                                        class="regular-text" placeholder="123456789" />
                                    <p class="mlt-hint">Numerische ID (nicht G-XXXXXXXX)</p>
                                </div>
                                <div class="mlt-field">
                                    <label for="mlt_ga4_service_account_json">Service Account JSON</label>
                                    <textarea id="mlt_ga4_service_account_json" name="mlt_ga4_service_account_json"
                                        rows="3" class="large-text code"
                                        placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'
                                        style="font-size:12px;font-family:monospace"><?php echo esc_textarea( get_option( 'mlt_ga4_service_account_json', '' ) ); ?></textarea>
                                    <p class="mlt-hint">Google Cloud → Service Account → JSON-Key herunterladen</p>
                                </div>
                                <?php else : ?>
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

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
                                    Der Report-Versand erfordert eine aktive SMTP-Konfiguration im Agency Core Plugin.
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-smtp' ) ); ?>">
                                        → SMTP jetzt konfigurieren
                                    </a>
                                </div>
                            <?php else : ?>
                                <div class="mlt-notice mlt-notice--success">
                                    <strong>✓ SMTP aktiv.</strong> Versand läuft über Agency Core.
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-smtp' ) ); ?>">Einstellungen</a>
                                </div>
                            <?php endif; ?>

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
                                <p class="mlt-hint">Jeden Montag um 08:00 Uhr wird ein HTML-Report an die angegebene Adresse gesendet.</p>
                            </div>

                            <div class="mlt-field">
                                <label for="mlt_report_email">Empfänger-E-Mail</label>
                                <input
                                    type="email"
                                    id="mlt_report_email"
                                    name="<?php echo self::OPT_REPORT_EMAIL; ?>"
                                    value="<?php echo esc_attr( $report_email ); ?>"
                                    class="regular-text"
                                    placeholder="kunde@example.at"
                                />
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
                                <p class="mlt-hint">Sendet eine Test-Mail an die oben eingetragene Adresse. Speichern nicht vergessen.</p>
                            </div>

                            <?php if ( $report_on ) :
                                $next = wp_next_scheduled( 'mlt_weekly_report' );
                            ?>
                                <p class="mlt-hint mlt-cron-info">
                                    Nächster automatischer Versand:
                                    <strong><?php echo $next ? wp_date( 'd.m.Y H:i', $next ) : '—'; ?></strong>
                                </p>
                            <?php endif; ?>

                        </div>
                    </div>

                </div><!-- .mlt-grid -->

                <?php submit_button( 'Einstellungen speichern' ); ?>

            </form>
        </div>
        <?php
    }

    // ── SMTP-Status prüfen ────────────────────────────────────────────────────

    private function is_smtp_configured() {
        // Agency Core speichert SMTP-Status als ACF-Option
        if ( function_exists( 'get_field' ) ) {
            $smtp = get_field( 'smtp_settings', 'option' );
            return ! empty( $smtp['enabled'] ) && ! empty( $smtp['host'] );
        }

        // Fallback: wp-config.php Konstanten
        return defined( 'MEDIALAB_SMTP_ENABLED' ) && MEDIALAB_SMTP_ENABLED
            && defined( 'MEDIALAB_SMTP_HOST' ) && MEDIALAB_SMTP_HOST;
    }

    // ── AJAX: Test-Mail senden ────────────────────────────────────────────────

    public function ajax_test_mail() {
        check_ajax_referer( 'mlt_test_mail', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $to      = sanitize_email( $_POST['email'] ?? get_option( 'admin_email' ) );
        $subject = '[' . get_bloginfo( 'name' ) . '] Media Lab SEO Toolkit – Test-Mail';
        $message = $this->build_test_mail_html();
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        // Fehler abfangen
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

    // ── WP-Cron: Report-Scheduling ────────────────────────────────────────────

    public function sync_cron( $old, $new ) {
        if ( $new && ! $old ) {
            // Aktiviert → nächsten Montag 08:00 Uhr planen
            if ( ! wp_next_scheduled( 'mlt_weekly_report' ) ) {
                wp_schedule_event( $this->next_monday_8am(), 'weekly', 'mlt_weekly_report' );
            }
        } elseif ( ! $new && $old ) {
            // Deaktiviert → Cron abmelden
            $ts = wp_next_scheduled( 'mlt_weekly_report' );
            if ( $ts ) wp_unschedule_event( $ts, 'mlt_weekly_report' );
        }
    }

    private function next_monday_8am() {
        $tz   = wp_timezone();
        $now  = new DateTime( 'now', $tz );
        $next = new DateTime( 'next monday 08:00', $tz );
        // Falls heute Montag und noch nicht 08:00 → heute
        if ( $now->format( 'N' ) === '1' && $now->format( 'H' ) < 8 ) {
            $next = new DateTime( 'today 08:00', $tz );
        }
        return $next->getTimestamp();
    }

    // ── Report-Mail senden (Cron-Hook) ────────────────────────────────────────

    public function send_weekly_report() {
        $to = get_option( self::OPT_REPORT_EMAIL, get_option( 'admin_email' ) );
        if ( ! is_email( $to ) ) return;

        $subject = '[' . get_bloginfo( 'name' ) . '] Wöchentlicher SEO-Report';
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        /**
         * Filter: Report-HTML anpassen oder erweitern.
         * 
         * @param string $html    Standard-Report-HTML
         * @param string $to      Empfänger
         */
        $html = apply_filters( 'mlt_weekly_report_html', $this->build_report_html(), $to );

        wp_mail( $to, $subject, $html, $headers );
    }

    private function build_report_html() {
        $site = get_bloginfo( 'name' );
        $url  = get_bloginfo( 'url' );
        $week = wp_date( 'W/Y' );

        return "
        <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:32px 24px;background:#f9fafb'>
            <h2 style='margin:0 0 4px;color:#1a1a2e'>{$site}</h2>
            <p style='color:#6b7280;margin:0 0 32px;font-size:14px'>Wöchentlicher SEO-Report – KW {$week}</p>
            <p style='color:#374151'>Dieser Report wird automatisch von <a href='{$url}'>Media Lab SEO Toolkit</a> gesendet.</p>
            <hr style='border:none;border-top:1px solid #e5e7eb;margin:24px 0'>
            <p style='color:#9ca3af;font-size:12px'>
                Um den Report zu deaktivieren: WordPress Admin → ML Toolkit → Wöchentlicher Report deaktivieren.
            </p>
        </div>";
    }
}
