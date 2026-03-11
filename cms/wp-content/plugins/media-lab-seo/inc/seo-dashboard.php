<?php
/**
 * SEO Dashboard
 *
 * - Admin-Seite:         Media Lab SEO → SEO Dashboard
 * - Dashboard-Widget:    WordPress-Übersicht (Mini-KPIs)
 * - OAuth-Callback:      Verarbeitung des GSC-Auth-Codes
 * - AJAX-Endpunkt:       Cache-Flush via Button
 *
 * @package MediaLab_SEO
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Admin-Menü: Unterseite unter "Media Lab SEO"
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
    add_submenu_page(
        'medialab-seo',
        'SEO Dashboard',
        '📊 Dashboard',
        'manage_options',
        'medialab-seo-dashboard',
        'medialab_seo_dashboard_page'
    );
} );

// ---------------------------------------------------------------------------
// Assets für Dashboard-Seite
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'media-lab-seo_page_medialab-seo-dashboard' ) return;

    wp_enqueue_style(
        'medialab-seo-dashboard',
        MEDIALAB_SEO_URL . 'assets/css/seo-dashboard.css',
        [],
        MEDIALAB_SEO_VERSION
    );

    wp_enqueue_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js',
        [],
        '4.4.2',
        true
    );

    wp_enqueue_script(
        'medialab-seo-dashboard',
        MEDIALAB_SEO_URL . 'assets/js/seo-dashboard.js',
        [ 'jquery', 'chart-js' ],
        MEDIALAB_SEO_VERSION,
        true
    );

    wp_localize_script( 'medialab-seo-dashboard', 'medialabGSC', [
        'nonce'     => wp_create_nonce( 'medialab_gsc_ajax' ),
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'authUrl'   => medialab_gsc_is_configured() ? medialab_gsc_auth_url() : '',
    ] );
} );

// ---------------------------------------------------------------------------
// OAuth-Callback verarbeiten (passiert auf der Dashboard-Seite)
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'medialab-seo-dashboard' ) return;
    if ( ! isset( $_GET['gsc_oauth'] ) || $_GET['gsc_oauth'] !== 'callback' ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    $result = medialab_gsc_handle_callback();

    if ( is_wp_error( $result ) ) {
        add_action( 'admin_notices', fn() =>
            printf( '<div class="notice notice-error"><p><strong>GSC Verbindung fehlgeschlagen:</strong> %s</p></div>',
                esc_html( $result->get_error_message() ) )
        );
    } else {
        wp_redirect( admin_url( 'admin.php?page=medialab-seo-dashboard&gsc_connected=1' ) );
        exit;
    }
} );

// ---------------------------------------------------------------------------
// Disconnect-Action
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
    if ( ! isset( $_GET['gsc_disconnect'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'medialab_gsc_disconnect' );

    medialab_gsc_disconnect();
    wp_redirect( admin_url( 'admin.php?page=medialab-seo-dashboard&gsc_disconnected=1' ) );
    exit;
} );

// ---------------------------------------------------------------------------
// Einstellungen speichern (GSC-Credentials + Report-Optionen)
// ---------------------------------------------------------------------------

add_action( 'admin_init', function () {
    if ( ! isset( $_POST['medialab_gsc_save_settings'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'medialab_gsc_settings' );

    update_option( 'medialab_gsc_client_id',    sanitize_text_field( $_POST['gsc_client_id']    ?? '' ) );
    update_option( 'medialab_gsc_client_secret', sanitize_text_field( $_POST['gsc_client_secret'] ?? '' ) );
    update_option( 'medialab_gsc_property_url',  esc_url_raw( $_POST['gsc_property_url'] ?? '' ) );

    // Report-Einstellungen
    update_option( 'medialab_report_recipient',    sanitize_email( $_POST['report_recipient']    ?? '' ) );
    update_option( 'medialab_report_day',          sanitize_text_field( $_POST['report_day']     ?? 'monday' ) );
    update_option( 'medialab_report_time',         sanitize_text_field( $_POST['report_time']    ?? '08:00' ) );
    update_option( 'medialab_report_enabled',      isset( $_POST['report_enabled'] ) ? '1' : '0' );
    update_option( 'medialab_report_from_name',    sanitize_text_field( $_POST['report_from_name']  ?? '' ) );
    update_option( 'medialab_report_from_email',   sanitize_email( $_POST['report_from_email']  ?? '' ) );

    // GA4
    update_option( 'medialab_ga4_property_id', sanitize_text_field( $_POST['ga4_property_id'] ?? '' ) );
    if ( ! empty( $_POST['ga4_service_account_json'] ) ) {
        $json = stripslashes( $_POST['ga4_service_account_json'] );
        if ( json_decode( $json ) !== null ) {
            update_option( 'medialab_ga4_service_account_json', $json );
        }
    }

    // Matomo
    update_option( 'medialab_matomo_url',     esc_url_raw( $_POST['matomo_url']     ?? '' ) );
    update_option( 'medialab_matomo_site_id', sanitize_text_field( $_POST['matomo_site_id'] ?? '' ) );
    update_option( 'medialab_matomo_token',   sanitize_text_field( $_POST['matomo_token']   ?? '' ) );

    // Adapter-Cache leeren
    MediaLab_GA4_Adapter::flush_cache();
    MediaLab_Matomo_Adapter::flush_cache();

    // Cron neu planen
    medialab_seo_reschedule_report();

    wp_redirect( admin_url( 'admin.php?page=medialab-seo-dashboard&settings_saved=1' ) );
    exit;
} );

// ---------------------------------------------------------------------------
// AJAX: Cache leeren
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_medialab_gsc_flush_cache', function () {
    check_ajax_referer( 'medialab_gsc_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );
    medialab_gsc_flush_cache();
    wp_send_json_success( [ 'message' => 'Cache geleert. Daten werden neu geladen.' ] );
} );

// ---------------------------------------------------------------------------
// AJAX: Report-Test-Mail senden
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_medialab_send_test_report', function () {
    check_ajax_referer( 'medialab_gsc_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

    $result = medialab_seo_send_report();
    if ( $result ) {
        wp_send_json_success( [ 'message' => 'Test-Report wurde gesendet.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'E-Mail konnte nicht gesendet werden.' ] );
    }
} );

// ---------------------------------------------------------------------------
// AJAX: Matomo-Verbindungstest
// ---------------------------------------------------------------------------

add_action( 'wp_ajax_medialab_test_matomo', function () {
    check_ajax_referer( 'medialab_gsc_ajax', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

    $url     = get_option( 'medialab_matomo_url', '' );
    $site_id = get_option( 'medialab_matomo_site_id', '' );
    $token   = get_option( 'medialab_matomo_token', '' );

    if ( empty( $url ) || empty( $site_id ) || empty( $token ) ) {
        wp_send_json_error( [ 'message' => 'Konfiguration unvollständig.' ] );
    }

    $adapter = new MediaLab_Matomo_Adapter( $url, $site_id, $token );
    $result  = $adapter->test_connection();

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    wp_send_json_success( [
        'message' => sprintf( 'Verbunden mit: %s (%s)', $result['site_name'], $result['url'] ),
    ] );
} );

// ---------------------------------------------------------------------------
// Dashboard-Seite Render
// ---------------------------------------------------------------------------

function medialab_seo_dashboard_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $configured = medialab_gsc_is_configured();
    $connected  = medialab_gsc_is_connected();
    $data       = [];

    if ( $configured && $connected ) {
        $data = medialab_gsc_get_dashboard_data();
    }

    $properties    = ( $configured && $connected ) ? medialab_gsc_list_properties() : [];
    $property_url  = get_option( 'medialab_gsc_property_url', '' );
    $client_id     = get_option( 'medialab_gsc_client_id', '' );
    $client_secret = get_option( 'medialab_gsc_client_secret', '' );

    // Report-Einstellungen
    $r_recipient  = get_option( 'medialab_report_recipient', get_option( 'admin_email' ) );
    $r_day        = get_option( 'medialab_report_day', 'monday' );
    $r_time       = get_option( 'medialab_report_time', '08:00' );
    $r_enabled    = get_option( 'medialab_report_enabled', '0' );
    $r_from_name  = get_option( 'medialab_report_from_name', get_bloginfo( 'name' ) );
    $r_from_email = get_option( 'medialab_report_from_email', get_option( 'admin_email' ) );

    $days = [ 'monday' => 'Montag', 'tuesday' => 'Dienstag', 'wednesday' => 'Mittwoch',
              'thursday' => 'Donnerstag', 'friday' => 'Freitag', 'saturday' => 'Samstag', 'sunday' => 'Sonntag' ];

    ?>
    <div class="wrap ml-seo-dashboard">

        <div class="ml-seo-header">
            <div class="ml-seo-header__title">
                <span class="ml-seo-header__icon">📊</span>
                <div>
                    <h1>SEO Dashboard</h1>
                    <p>Google Search Console · <?php echo esc_html( $property_url ?: 'Nicht verbunden' ); ?></p>
                </div>
            </div>
            <?php if ( $connected ) : ?>
            <div class="ml-seo-header__actions">
                <button class="button" id="ml-flush-cache">🔄 Cache leeren</button>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=medialab-seo-dashboard&gsc_disconnect=1' ), 'medialab_gsc_disconnect' ) ); ?>"
                   class="button ml-btn-danger"
                   onclick="return confirm('GSC-Verbindung wirklich trennen?')">Verbindung trennen</a>
            </div>
            <?php endif; ?>
        </div>

        <?php medialab_dashboard_notices(); ?>

        <?php if ( ! $configured || ! $connected ) :
            medialab_render_connect_panel( $configured, $client_id, $client_secret, $property_url );
        else :
            medialab_render_kpis( $data );
            medialab_render_tables( $data );
        endif; ?>

        <hr style="margin:48px 0 32px">

        <?php medialab_render_settings_panel( $client_id, $client_secret, $property_url,
            $r_recipient, $r_day, $r_time, $r_enabled, $r_from_name, $r_from_email, $days ); ?>

    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Hilfsfunktionen: Render-Abschnitte
// ---------------------------------------------------------------------------

function medialab_dashboard_notices(): void {
    if ( isset( $_GET['gsc_connected'] ) )    echo '<div class="notice notice-success is-dismissible"><p>✅ Google Search Console erfolgreich verbunden!</p></div>';
    if ( isset( $_GET['gsc_disconnected'] ) ) echo '<div class="notice notice-info is-dismissible"><p>🔌 Verbindung getrennt.</p></div>';
    if ( isset( $_GET['settings_saved'] ) )   echo '<div class="notice notice-success is-dismissible"><p>✅ Einstellungen gespeichert.</p></div>';
}

function medialab_render_connect_panel( bool $configured, string $client_id, string $client_secret, string $property_url ): void {
    ?>
    <div class="ml-connect-panel">
        <div class="ml-connect-panel__icon">🔗</div>
        <h2>Google Search Console verbinden</h2>
        <p>Verbinde deine GSC-Property, um Klicks, Impressionen, Keywords und Top-Seiten direkt im WordPress-Backend zu sehen.</p>

        <?php if ( $configured ) : ?>
            <a href="<?php echo esc_url( medialab_gsc_auth_url() ); ?>" class="button button-primary button-hero">
                Mit Google verbinden →
            </a>
            <p class="description">Du wirst zu Google weitergeleitet. Nach der Genehmigung kehrst du automatisch zurück.</p>
        <?php else : ?>
            <div class="ml-notice ml-notice--warning">
                <strong>Bitte trage zuerst deine Google API Zugangsdaten ein</strong> (Client ID + Secret). <br>
                Anleitung weiter unten unter <em>„Einstellungen"</em>.
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function medialab_render_kpis( array $data ): void {
    $c = $data['current']  ?? [];
    $p = $data['previous'] ?? [];

    if ( ! empty( $data['error'] ) ) {
        echo '<div class="ml-notice ml-notice--error"><strong>GSC-Fehler:</strong> ' . esc_html( $data['error'] ) . '</div>';
        return;
    }

    $period = sprintf( '%s – %s',
        date_i18n( 'd.m.Y', strtotime( $data['period']['start'] ?? '' ) ),
        date_i18n( 'd.m.Y', strtotime( $data['period']['end']   ?? '' ) )
    );

    $kpis = [
        [
            'label'   => 'Klicks',
            'value'   => number_format( $c['clicks'] ?? 0, 0, ',', '.' ),
            'prev'    => $p['clicks'] ?? 0,
            'curr'    => $c['clicks'] ?? 0,
            'icon'    => '🖱️',
            'format'  => 'integer',
        ],
        [
            'label'   => 'Impressionen',
            'value'   => number_format( $c['impressions'] ?? 0, 0, ',', '.' ),
            'prev'    => $p['impressions'] ?? 0,
            'curr'    => $c['impressions'] ?? 0,
            'icon'    => '👁️',
            'format'  => 'integer',
        ],
        [
            'label'   => 'Ø CTR',
            'value'   => number_format( $c['ctr'] ?? 0, 1, ',', '' ) . ' %',
            'prev'    => $p['ctr'] ?? 0,
            'curr'    => $c['ctr'] ?? 0,
            'icon'    => '📈',
            'format'  => 'percent',
        ],
        [
            'label'   => 'Ø Position',
            'value'   => number_format( $c['position'] ?? 0, 1, ',', '' ),
            'prev'    => $p['position'] ?? 0,
            'curr'    => $c['position'] ?? 0,
            'icon'    => '🏆',
            'format'  => 'position', // kleinerer Wert = besser
        ],
    ];

    echo '<p class="ml-period-label">Zeitraum: <strong>' . esc_html( $period ) . '</strong> (letzte 28 Tage) · im Vergleich zur Vorperiode</p>';
    echo '<div class="ml-kpi-grid">';

    foreach ( $kpis as $kpi ) {
        $diff    = $kpi['curr'] - $kpi['prev'];
        $is_pos  = $kpi['format'] === 'position' ? $diff < 0 : $diff >= 0;
        $pct     = $kpi['prev'] > 0 ? round( abs( $diff ) / $kpi['prev'] * 100, 1 ) : 0;
        $arrow   = $diff > 0 ? '↑' : ( $diff < 0 ? '↓' : '→' );
        $cls     = $diff === 0.0 ? 'neutral' : ( $is_pos ? 'positive' : 'negative' );

        printf(
            '<div class="ml-kpi-card">
                <div class="ml-kpi-card__icon">%s</div>
                <div class="ml-kpi-card__body">
                    <div class="ml-kpi-card__label">%s</div>
                    <div class="ml-kpi-card__value">%s</div>
                    <div class="ml-kpi-card__delta %s">%s %s%%</div>
                </div>
            </div>',
            esc_html( $kpi['icon'] ),
            esc_html( $kpi['label'] ),
            esc_html( $kpi['value'] ),
            esc_attr( $cls ),
            esc_html( $arrow ),
            esc_html( $pct )
        );
    }

    echo '</div>';
}

function medialab_render_tables( array $data ): void {
    $keywords = $data['keywords'] ?? [];
    $pages    = $data['pages']    ?? [];

    echo '<div class="ml-tables-grid">';

    // Keywords-Tabelle
    echo '<div class="ml-table-card"><h3>🔑 Top Keywords</h3>';
    if ( empty( $keywords ) ) {
        echo '<p class="ml-empty">Keine Daten verfügbar.</p>';
    } else {
        echo '<table class="ml-data-table"><thead><tr>
            <th>#</th><th>Keyword</th><th>Klicks</th><th>Impressionen</th><th>CTR</th><th>Position</th>
        </tr></thead><tbody>';
        foreach ( $keywords as $i => $row ) {
            printf( '<tr>
                <td class="ml-rank">%d</td>
                <td class="ml-keyword">%s</td>
                <td><strong>%s</strong></td>
                <td>%s</td>
                <td>%s%%</td>
                <td class="ml-pos">%s</td>
            </tr>',
                $i + 1,
                esc_html( $row['keyword'] ),
                number_format( $row['clicks'], 0, ',', '.' ),
                number_format( $row['impressions'], 0, ',', '.' ),
                number_format( $row['ctr'], 1, ',', '' ),
                number_format( $row['position'], 1, ',', '' )
            );
        }
        echo '</tbody></table>';
    }
    echo '</div>';

    // Seiten-Tabelle
    echo '<div class="ml-table-card"><h3>📄 Top Seiten</h3>';
    if ( empty( $pages ) ) {
        echo '<p class="ml-empty">Keine Daten verfügbar.</p>';
    } else {
        echo '<table class="ml-data-table"><thead><tr>
            <th>#</th><th>URL</th><th>Klicks</th><th>Impressionen</th><th>CTR</th><th>Position</th>
        </tr></thead><tbody>';
        foreach ( $pages as $i => $row ) {
            $short = preg_replace( '#^https?://[^/]+#', '', $row['url'] );
            $short = strlen( $short ) > 45 ? substr( $short, 0, 42 ) . '…' : $short;
            printf( '<tr>
                <td class="ml-rank">%d</td>
                <td><a href="%s" target="_blank" class="ml-url" title="%s">%s</a></td>
                <td><strong>%s</strong></td>
                <td>%s</td>
                <td>%s%%</td>
                <td class="ml-pos">%s</td>
            </tr>',
                $i + 1,
                esc_url( $row['url'] ),
                esc_attr( $row['url'] ),
                esc_html( $short ),
                number_format( $row['clicks'], 0, ',', '.' ),
                number_format( $row['impressions'], 0, ',', '.' ),
                number_format( $row['ctr'], 1, ',', '' ),
                number_format( $row['position'], 1, ',', '' )
            );
        }
        echo '</tbody></table>';
    }
    echo '</div>';

    echo '</div>'; // .ml-tables-grid
}

function medialab_render_settings_panel(
    string $client_id, string $client_secret, string $property_url,
    string $r_recipient, string $r_day, string $r_time, string $r_enabled,
    string $r_from_name, string $r_from_email, array $days
): void {
    ?>
    <div class="ml-settings-panel">
        <h2>⚙️ Einstellungen</h2>

        <form method="post" action="">
            <?php wp_nonce_field( 'medialab_gsc_settings' ); ?>

            <div class="ml-settings-grid">

                <!-- GSC API -->
                <div class="ml-settings-section">
                    <h3>Google Search Console API</h3>
                    <p class="description">
                        Erstelle ein Projekt in der <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>,
                        aktiviere die <em>Search Console API</em> und erstelle OAuth2-Zugangsdaten (Typ: Webanwendung).<br>
                        Autorisierte Redirect-URI: <code><?php echo esc_html( medialab_gsc_redirect_uri() ); ?></code>
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="gsc_client_id">Client ID</label></th>
                            <td><input type="text" id="gsc_client_id" name="gsc_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" placeholder="1234567890-xxx.apps.googleusercontent.com"></td>
                        </tr>
                        <tr>
                            <th><label for="gsc_client_secret">Client Secret</label></th>
                            <td><input type="password" id="gsc_client_secret" name="gsc_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="gsc_property_url">Property URL</label></th>
                            <td>
                                <input type="url" id="gsc_property_url" name="gsc_property_url" value="<?php echo esc_attr( $property_url ); ?>" class="regular-text" placeholder="https://example.at/">
                                <p class="description">Exakt die URL wie in GSC eingetragen (z. B. <code>https://example.at/</code> oder <code>sc-domain:example.at</code>).</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Report-Einstellungen -->
                <div class="ml-settings-section">
                    <h3>Wöchentlicher SEO-Report</h3>
                    <p class="description">Der Report wird automatisch per E-Mail als HTML-Mail versendet.</p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th>Report aktivieren</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="report_enabled" value="1" <?php checked( $r_enabled, '1' ); ?>>
                                    Wöchentlichen Report senden
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="report_recipient">Empfänger</label></th>
                            <td><input type="email" id="report_recipient" name="report_recipient" value="<?php echo esc_attr( $r_recipient ); ?>" class="regular-text" placeholder="kunde@beispiel.at"></td>
                        </tr>
                        <tr>
                            <th><label for="report_from_name">Absender Name</label></th>
                            <td><input type="text" id="report_from_name" name="report_from_name" value="<?php echo esc_attr( $r_from_name ); ?>" class="regular-text" placeholder="Media Lab Agentur"></td>
                        </tr>
                        <tr>
                            <th><label for="report_from_email">Absender E-Mail</label></th>
                            <td><input type="email" id="report_from_email" name="report_from_email" value="<?php echo esc_attr( $r_from_email ); ?>" class="regular-text" placeholder="seo@agentur.at"></td>
                        </tr>
                        <tr>
                            <th><label for="report_day">Versandtag</label></th>
                            <td>
                                <select id="report_day" name="report_day">
                                    <?php foreach ( $days as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $r_day, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="report_time">Uhrzeit (Serverzeit)</label></th>
                            <td><input type="time" id="report_time" name="report_time" value="<?php echo esc_attr( $r_time ); ?>"></td>
                        </tr>
                    </table>

                    <?php if ( medialab_gsc_is_connected() ) : ?>
                        <button type="button" class="button" id="ml-send-test-report">📧 Test-Report jetzt senden</button>
                    <?php endif; ?>
                </div>

            </div>

            <?php
            $active_provider  = medialab_analytics_active_provider();
            $ga4_property_id  = get_option( 'medialab_ga4_property_id', '' );
            $ga4_json_saved   = ! empty( get_option( 'medialab_ga4_service_account_json', '' ) );
            $matomo_url_val   = get_option( 'medialab_matomo_url', '' );
            $matomo_site_id   = get_option( 'medialab_matomo_site_id', '' );
            $matomo_token_val = get_option( 'medialab_matomo_token', '' );
            ?>

            <!-- Analytics-Adapter -->
            <div class="ml-settings-divider"><span>Analytics-Adapter <em>(optional &ndash; ein Anbieter aktiv)</em></span></div>

            <?php if ( $active_provider ) : ?>
            <div class="ml-settings-active-badge">
                &#10003; Aktiver Adapter: <strong><?php echo $active_provider === 'ga4' ? 'Google Analytics 4' : 'Matomo'; ?></strong>
            </div>
            <?php endif; ?>

            <div class="ml-settings-grid">

                <!-- GA4 -->
                <div class="ml-settings-section">
                    <h3>&#128202; Google Analytics 4</h3>
                    <p class="description">
                        Nutzt die <strong>GA4 Data API</strong> mit einem Service Account (kein zweiter OAuth-Flow).<br>
                        Anleitung: Google Cloud Console &rarr; Projekt &rarr; <em>Google Analytics Data API</em> aktivieren &rarr;
                        Service Account erstellen &rarr; JSON-Key herunterladen &rarr; Service-Account-E-Mail in GA4 als
                        <em>Betrachter</em> hinzuf&uuml;gen.
                    </p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="ga4_property_id">Property ID</label></th>
                            <td>
                                <input type="text" id="ga4_property_id" name="ga4_property_id"
                                    value="<?php echo esc_attr( $ga4_property_id ); ?>"
                                    class="regular-text" placeholder="123456789">
                                <p class="description">Nur die numerische ID (nicht G-XXXXXXXX). Zu finden unter GA4 &rarr; Verwaltung &rarr; Property-Einstellungen.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ga4_service_account_json">Service Account JSON</label></th>
                            <td>
                                <?php if ( $ga4_json_saved ) : ?>
                                    <p style="color:#10b981;font-weight:600;margin:0 0 6px">&#10003; JSON gespeichert</p>
                                <?php endif; ?>
                                <textarea id="ga4_service_account_json" name="ga4_service_account_json"
                                    rows="4" class="large-text code"
                                    placeholder='{"type":"service_account","project_id":"...","private_key":"...","client_email":"..."}'
                                    style="font-size:12px;font-family:monospace"></textarea>
                                <p class="description">Leer lassen um das gespeicherte JSON beizubehalten. Nur ausf&uuml;llen zum Aktualisieren.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Matomo -->
                <div class="ml-settings-section">
                    <h3>&#128200; Matomo</h3>
                    <p class="description">
                        Verbindet eine selbst-gehostete Matomo-Instanz via <strong>Reporting API</strong>.<br>
                        Token: Matomo-Backend &rarr; Pers&ouml;nliche Einstellungen &rarr; <em>API-Authentifizierungs-Token</em>.
                    </p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="matomo_url">Matomo URL</label></th>
                            <td><input type="url" id="matomo_url" name="matomo_url"
                                value="<?php echo esc_attr( $matomo_url_val ); ?>"
                                class="regular-text" placeholder="https://matomo.example.at/"></td>
                        </tr>
                        <tr>
                            <th><label for="matomo_site_id">Site ID</label></th>
                            <td>
                                <input type="number" id="matomo_site_id" name="matomo_site_id"
                                    value="<?php echo esc_attr( $matomo_site_id ); ?>"
                                    class="small-text" min="1" placeholder="1">
                                <p class="description">Matomo &rarr; Verwaltung &rarr; Websites &rarr; ID-Spalte.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="matomo_token">API-Token</label></th>
                            <td><input type="password" id="matomo_token" name="matomo_token"
                                value="<?php echo esc_attr( $matomo_token_val ); ?>"
                                class="regular-text"></td>
                        </tr>
                    </table>
                    <?php if ( ! empty( $matomo_url_val ) && ! empty( $matomo_site_id ) && ! empty( $matomo_token_val ) ) : ?>
                        <button type="button" class="button" id="ml-test-matomo">&#128279; Verbindung testen</button>
                        <span id="ml-matomo-test-result" style="margin-left:8px;font-size:13px"></span>
                    <?php endif; ?>
                </div>

            </div>

            <p class="submit">
                <input type="submit" name="medialab_gsc_save_settings" class="button button-primary" value="Einstellungen speichern">
            </p>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// WordPress Dashboard-Widget (Mini-KPIs)
// ---------------------------------------------------------------------------

add_action( 'wp_dashboard_setup', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;

    wp_add_dashboard_widget(
        'medialab_seo_widget',
        '📊 SEO KPIs',
        'medialab_seo_dashboard_widget'
    );
} );

function medialab_seo_dashboard_widget(): void {
    if ( ! medialab_gsc_is_configured() || ! medialab_gsc_is_connected() ) {
        echo '<p>GSC nicht verbunden. <a href="' . esc_url( admin_url( 'admin.php?page=medialab-seo-dashboard' ) ) . '">Jetzt verbinden →</a></p>';
        return;
    }

    $data = medialab_gsc_get_dashboard_data();

    if ( ! empty( $data['error'] ) ) {
        echo '<p>⚠️ ' . esc_html( $data['error'] ) . '</p>';
        return;
    }

    $c = $data['current'] ?? [];

    ?>
    <div class="ml-widget-kpis">
        <div class="ml-widget-kpi">
            <span class="ml-widget-kpi__icon">🖱️</span>
            <div>
                <div class="ml-widget-kpi__value"><?php echo number_format( $c['clicks'] ?? 0, 0, ',', '.' ); ?></div>
                <div class="ml-widget-kpi__label">Klicks</div>
            </div>
        </div>
        <div class="ml-widget-kpi">
            <span class="ml-widget-kpi__icon">👁️</span>
            <div>
                <div class="ml-widget-kpi__value"><?php echo number_format( $c['impressions'] ?? 0, 0, ',', '.' ); ?></div>
                <div class="ml-widget-kpi__label">Impressionen</div>
            </div>
        </div>
        <div class="ml-widget-kpi">
            <span class="ml-widget-kpi__icon">📈</span>
            <div>
                <div class="ml-widget-kpi__value"><?php echo number_format( $c['ctr'] ?? 0, 1, ',', '' ); ?> %</div>
                <div class="ml-widget-kpi__label">CTR</div>
            </div>
        </div>
        <div class="ml-widget-kpi">
            <span class="ml-widget-kpi__icon">🏆</span>
            <div>
                <div class="ml-widget-kpi__value"><?php echo number_format( $c['position'] ?? 0, 1, ',', '' ); ?></div>
                <div class="ml-widget-kpi__label">Ø Position</div>
            </div>
        </div>
    </div>
    <p style="text-align:right;margin:8px 0 0"><a href="<?php echo esc_url( admin_url( 'admin.php?page=medialab-seo-dashboard' ) ); ?>">Vollständiges Dashboard →</a></p>
    <?php
}
