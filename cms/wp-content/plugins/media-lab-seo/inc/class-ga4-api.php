<?php
/**
 * MLT_GA4_API
 *
 * Google Analytics 4 – OAuth2 + Data API Datenabruf.
 *
 * Nutzt dieselbe OAuth-App wie GSC (gleiche Client ID + Secret).
 * Eigene Option-Keys für GA4-Tokens und Property ID.
 *
 * Setup:
 *  1. Google Cloud Console → Projekt → APIs aktivieren:
 *     - Google Analytics Data API
 *     - (Google Search Console API – bereits für GSC vorhanden)
 *  2. OAuth2-Credentials → Authorized Redirect URI hinzufügen:
 *     {admin_url}admin.php?page=media-lab-seo&mlt_ga4_callback=1
 *  3. Client ID + Secret (gleiche wie GSC) + GA4 Property ID eintragen
 *  4. „Mit Google verbinden" klicken → beide Scopes werden auf einmal autorisiert
 *
 * Scope: https://www.googleapis.com/auth/analytics.readonly
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_GA4_API {

    // Shared OAuth credentials (same as GSC)
    const OPT_CLIENT_ID     = 'mlt_gsc_client_id';
    const OPT_CLIENT_SECRET = 'mlt_gsc_client_secret';

    // GA4-specific options
    const OPT_PROPERTY_ID   = 'mlt_ga4_property_id';
    const OPT_ACCESS_TOKEN  = 'mlt_ga4_oauth_access_token';
    const OPT_REFRESH_TOKEN = 'mlt_ga4_oauth_refresh_token';
    const OPT_TOKEN_EXPIRY  = 'mlt_ga4_oauth_token_expiry';

    const GA4_SCOPE         = 'https://www.googleapis.com/auth/analytics.readonly';

    private static ?self $instance = null;

    public static function instance() : self {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_init',                   [ $this, 'handle_oauth_callback' ] );
        add_action( 'admin_post_mlt_ga4_disconnect', [ $this, 'handle_disconnect' ] );
    }

    // ── OAuth-Flow ────────────────────────────────────────────────────────────

    public function get_auth_url() : string {
        $client_id    = get_option( self::OPT_CLIENT_ID, '' );
        $redirect_uri = $this->get_redirect_uri();

        return add_query_arg( [
            'client_id'     => rawurlencode( $client_id ),
            'redirect_uri'  => rawurlencode( $redirect_uri ),
            'response_type' => 'code',
            'scope'         => rawurlencode( self::GA4_SCOPE ),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ], 'https://accounts.google.com/o/oauth2/v2/auth' );
    }

    public function handle_oauth_callback() {
        error_log( 'GA4 Callback aufgerufen. GET: ' . print_r( $_GET, true ) );
        
        if ( ! isset( $_GET['mlt_ga4_callback'], $_GET['code'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $code     = sanitize_text_field( $_GET['code'] );
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => get_option( self::OPT_CLIENT_ID ),
                'client_secret' => get_option( self::OPT_CLIENT_SECRET ),
                'redirect_uri'  => $this->get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            add_action( 'admin_notices', fn() => printf(
                '<div class="notice notice-error"><p>GA4 OAuth-Fehler: %s</p></div>',
                esc_html( $response->get_error_message() )
            ) );
            return;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $body['access_token'] ) ) {
            update_option( self::OPT_ACCESS_TOKEN,  $this->encrypt( $body['access_token'] ) );
            update_option( self::OPT_TOKEN_EXPIRY,  time() + (int) ( $body['expires_in'] ?? 3600 ) );
            if ( ! empty( $body['refresh_token'] ) ) {
                update_option( self::OPT_REFRESH_TOKEN, $this->encrypt( $body['refresh_token'] ) );
            }

            do_action( 'medialab_log_event', 'ga4_connected', 'ga4', null, 'Google Analytics 4' );
            wp_redirect( admin_url( 'admin.php?page=media-lab-seo&mlt_ga4_connected=1' ) );
            exit;
        }

        add_action( 'admin_notices', fn() => printf(
            '<div class="notice notice-error"><p>GA4: Ungültige Antwort von Google (%s)</p></div>',
            esc_html( $body['error'] ?? 'Unbekannt' )
        ) );
    }

    public function handle_disconnect() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'mlt_ga4_disconnect' );

        delete_option( self::OPT_ACCESS_TOKEN );
        delete_option( self::OPT_REFRESH_TOKEN );
        delete_option( self::OPT_TOKEN_EXPIRY );
        delete_transient( 'mlt_ga4_overview' );
        delete_transient( 'mlt_ga4_sources' );
        delete_transient( 'mlt_ga4_pages' );

        do_action( 'medialab_log_event', 'ga4_disconnected', 'ga4', null, 'Google Analytics 4' );
        wp_redirect( admin_url( 'admin.php?page=media-lab-seo&mlt_ga4_disconnected=1' ) );
        exit;
    }

    // ── Verbindungsstatus ─────────────────────────────────────────────────────

    public function is_connected() : bool {
        return (bool) get_option( self::OPT_ACCESS_TOKEN );
    }

    public function is_configured() : bool {
        return get_option( self::OPT_CLIENT_ID )
            && get_option( self::OPT_CLIENT_SECRET )
            && get_option( self::OPT_PROPERTY_ID );
    }

    // ── Access Token holen / refreshen ────────────────────────────────────────

    public function get_access_token() : string {
        $expiry = (int) get_option( self::OPT_TOKEN_EXPIRY, 0 );

        // Token noch gültig?
        if ( $expiry > time() + 60 ) {
            return $this->decrypt( get_option( self::OPT_ACCESS_TOKEN, '' ) );
        }

        // Refresh
        $refresh = $this->decrypt( get_option( self::OPT_REFRESH_TOKEN, '' ) );
        if ( ! $refresh ) return '';

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id'     => get_option( self::OPT_CLIENT_ID ),
                'client_secret' => get_option( self::OPT_CLIENT_SECRET ),
                'refresh_token' => $refresh,
                'grant_type'    => 'refresh_token',
            ],
        ] );

        if ( is_wp_error( $response ) ) return '';

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) return '';

        update_option( self::OPT_ACCESS_TOKEN, $this->encrypt( $body['access_token'] ) );
        update_option( self::OPT_TOKEN_EXPIRY, time() + (int) ( $body['expires_in'] ?? 3600 ) );

        return $body['access_token'];
    }

    // ── Datenabruf ────────────────────────────────────────────────────────────

    /**
     * Übersicht: Pageviews, Sessions, Nutzer
     * Zeitraum: frei wählbar
     */
    public function get_overview( string $start, string $end, bool $force = false ) : array {
        $cache_key = 'mlt_ga4_overview_' . md5( $start . $end );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return $cached;
        }

        $response = $this->run_report( [
            'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
            'metrics'    => [
                [ 'name' => 'screenPageViews' ],
                [ 'name' => 'sessions' ],
                [ 'name' => 'totalUsers' ],
            ],
        ] );

        if ( empty( $response['rows'][0]['metricValues'] ) ) {
            $result = [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0 ];
        } else {
            $vals   = $response['rows'][0]['metricValues'];
            $result = [
                'pageviews' => (int) ( $vals[0]['value'] ?? 0 ),
                'sessions'  => (int) ( $vals[1]['value'] ?? 0 ),
                'users'     => (int) ( $vals[2]['value'] ?? 0 ),
            ];
        }

        set_transient( $cache_key, $result, HOUR_IN_SECONDS * 6 );
        return $result;
    }

    /**
     * Traffic-Quellen (letzte N Tage, max. $limit Einträge)
     */
    public function get_sources( string $start, string $end, int $limit = 5, bool $force = false ) : array {
        $cache_key = 'mlt_ga4_sources_' . md5( $start . $end . $limit );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return $cached;
        }

        $response = $this->run_report( [
            'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
            'dimensions' => [ [ 'name' => 'sessionSource' ] ],
            'metrics'    => [ [ 'name' => 'sessions' ] ],
            'limit'      => $limit,
            'orderBys'   => [ [ 'metric' => [ 'metricName' => 'sessions' ], 'desc' => true ] ],
        ] );

        $rows = [];
        foreach ( $response['rows'] ?? [] as $row ) {
            $rows[] = [
                'source'   => $row['dimensionValues'][0]['value'] ?? '(unknown)',
                'sessions' => (int) ( $row['metricValues'][0]['value'] ?? 0 ),
            ];
        }

        set_transient( $cache_key, $rows, HOUR_IN_SECONDS * 6 );
        return $rows;
    }

    /**
     * Top Seiten (nach Pageviews)
     */
    public function get_top_pages( string $start, string $end, int $limit = 10, bool $force = false ) : array {
        $cache_key = 'mlt_ga4_pages_' . md5( $start . $end . $limit );
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return $cached;
        }

        $response = $this->run_report( [
            'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
            'dimensions' => [ [ 'name' => 'pagePath' ] ],
            'metrics'    => [ [ 'name' => 'screenPageViews' ] ],
            'limit'      => $limit,
            'orderBys'   => [ [ 'metric' => [ 'metricName' => 'screenPageViews' ], 'desc' => true ] ],
        ] );

        $rows = [];
        foreach ( $response['rows'] ?? [] as $row ) {
            $rows[] = [
                'url'       => $row['dimensionValues'][0]['value'] ?? '/',
                'pageviews' => (int) ( $row['metricValues'][0]['value'] ?? 0 ),
            ];
        }

        set_transient( $cache_key, $rows, HOUR_IN_SECONDS * 6 );
        return $rows;
    }

    // ── API-Request ───────────────────────────────────────────────────────────

    private function run_report( array $body ) : array {
        $token       = $this->get_access_token();
        $property_id = preg_replace( '/\D/', '', get_option( self::OPT_PROPERTY_ID, '' ) );

        if ( ! $token || ! $property_id ) return [];

        $response = wp_remote_post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$property_id}:runReport",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( $body ),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) return [];

        $data = json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];

        // API-Fehler loggen (z.B. Property nicht gefunden, Scope fehlt)
        if ( ! empty( $data['error'] ) ) {
            do_action( 'medialab_log_event', 'ga4_api_error', 'ga4', null,
                'GA4 API Fehler: ' . ( $data['error']['message'] ?? 'Unbekannt' )
            );
            return [];
        }

        return $data;
    }

    // ── Verschlüsselung für Tokens ────────────────────────────────────────────

    private function encrypt( string $value ) : string {
        if ( ! $value ) return '';
        $key = $this->get_encryption_key();
        $iv  = random_bytes( 16 );
        $enc = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
        return base64_encode( $iv . $enc );
    }

    private function decrypt( string $value ) : string {
        if ( ! $value ) return '';
        try {
            $key  = $this->get_encryption_key();
            $data = base64_decode( $value );
            $iv   = substr( $data, 0, 16 );
            $enc  = substr( $data, 16 );
            return openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv ) ?: '';
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    private function get_encryption_key() : string {
        $salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'mlt-fallback-salt';
        return substr( hash( 'sha256', $salt . 'mlt_ga4' ), 0, 32 );
    }

    public function get_redirect_uri() : string {
        return admin_url( 'admin.php?page=media-lab-seo&mlt_ga4_callback=1' );
    }
}
