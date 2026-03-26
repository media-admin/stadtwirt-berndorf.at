<?php
/**
 * MLT_GSC_API
 *
 * Google Search Console API – OAuth2 + Search Analytics Datenabruf.
 *
 * Setup:
 *  1. Google Cloud Console → Projekt → APIs & Dienste → OAuth2-Credentials
 *  2. Authorized Redirect URI: {admin_url}admin.php?page=media-lab-toolkit&mlt_gsc_callback=1
 *  3. Client ID + Secret + Property URL in SEO Toolkit → Einstellungen eintragen
 *  4. "Mit Google verbinden" klicken
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_GSC_API {

    const OPT_CLIENT_ID     = 'mlt_gsc_client_id';
    const OPT_CLIENT_SECRET = 'mlt_gsc_client_secret';
    const OPT_PROPERTY_URL  = 'mlt_gsc_property_url';
    const OPT_ACCESS_TOKEN  = 'mlt_gsc_access_token';
    const OPT_REFRESH_TOKEN = 'mlt_gsc_refresh_token';
    const OPT_TOKEN_EXPIRY  = 'mlt_gsc_token_expiry';

    private static ?self $instance = null;

    public static function instance() : self {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_oauth_callback' ] );
        add_action( 'admin_post_mlt_gsc_disconnect', [ $this, 'handle_disconnect' ] );
    }

    // ── OAuth-Flow ────────────────────────────────────────────────────────────

    public function get_auth_url() : string {
        $client_id    = get_option( self::OPT_CLIENT_ID, '' );
        $redirect_uri = $this->get_redirect_uri();

        return add_query_arg( [
            'client_id'             => rawurlencode( $client_id ),
            'redirect_uri'          => rawurlencode( $redirect_uri ),
            'response_type'         => 'code',
            'scope'                 => rawurlencode( 'https://www.googleapis.com/auth/webmasters.readonly' ),
            'access_type'           => 'offline',
            'prompt'                => 'consent',
        ], 'https://accounts.google.com/o/oauth2/v2/auth' );
    }

    public function handle_oauth_callback() {
        if ( ! isset( $_GET['mlt_gsc_callback'], $_GET['code'] ) ) return;
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
                '<div class="notice notice-error"><p>GSC OAuth-Fehler: %s</p></div>',
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
            wp_redirect( admin_url( 'admin.php?page=media-lab-toolkit&mlt_gsc_connected=1' ) );
            exit;
        }

        add_action( 'admin_notices', fn() => printf(
            '<div class="notice notice-error"><p>GSC: Ungültige Antwort von Google (%s)</p></div>',
            esc_html( $body['error'] ?? 'Unbekannt' )
        ) );
    }

    public function handle_disconnect() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        check_admin_referer( 'mlt_gsc_disconnect' );

        delete_option( self::OPT_ACCESS_TOKEN );
        delete_option( self::OPT_REFRESH_TOKEN );
        delete_option( self::OPT_TOKEN_EXPIRY );
        delete_transient( 'mlt_gsc_overview' );
        delete_transient( 'mlt_gsc_queries' );
        delete_transient( 'mlt_gsc_pages' );

        wp_redirect( admin_url( 'admin.php?page=media-lab-toolkit&mlt_gsc_disconnected=1' ) );
        exit;
    }

    // ── Verbindungsstatus ─────────────────────────────────────────────────────

    public function is_connected() : bool {
        return (bool) get_option( self::OPT_ACCESS_TOKEN );
    }

    public function is_configured() : bool {
        return get_option( self::OPT_CLIENT_ID ) && get_option( self::OPT_CLIENT_SECRET ) && get_option( self::OPT_PROPERTY_URL );
    }

    // ── Access Token holen / refreshen ────────────────────────────────────────

    private function get_access_token() : string {
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
     * Übersicht: Klicks, Impressionen, CTR, Ø Position
     * Zeitraum: letzte 28 Tage
     */
    public function get_overview( bool $force = false ) : array {
        $cache_key = 'mlt_gsc_overview';
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return $cached;
        }

        $data = $this->query_api( [
            'startDate'  => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
            'endDate'    => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
            'dimensions' => [],
            'rowLimit'   => 1,
        ] );

        if ( ! isset( $data['rows'][0] ) ) {
            $result = [ 'clicks' => 0, 'impressions' => 0, 'ctr' => 0, 'position' => 0 ];
        } else {
            $row    = $data['rows'][0];
            $result = [
                'clicks'      => (int)   ( $row['clicks']      ?? 0 ),
                'impressions' => (int)   ( $row['impressions'] ?? 0 ),
                'ctr'         => round( ( $row['ctr']          ?? 0 ) * 100, 1 ),
                'position'    => round( ( $row['position']     ?? 0 ), 1 ),
            ];
        }

        set_transient( $cache_key, $result, HOUR_IN_SECONDS * 6 );
        return $result;
    }

    /**
     * Top Keywords (letzte 28 Tage, max. 10)
     */
    public function get_top_queries( int $limit = 10, bool $force = false ) : array {
        $cache_key = 'mlt_gsc_queries_' . $limit;
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return $cached;
        }

        $data = $this->query_api( [
            'startDate'  => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
            'endDate'    => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
            'dimensions' => [ 'query' ],
            'rowLimit'   => $limit,
            'orderBy'    => [ [ 'fieldName' => 'clicks', 'sortOrder' => 'DESCENDING' ] ],
        ] );

        $rows = [];
        foreach ( $data['rows'] ?? [] as $row ) {
            $rows[] = [
                'query'       => $row['keys'][0] ?? '',
                'clicks'      => (int) ( $row['clicks']      ?? 0 ),
                'impressions' => (int) ( $row['impressions'] ?? 0 ),
                'ctr'         => round( ( $row['ctr']        ?? 0 ) * 100, 1 ),
                'position'    => round( ( $row['position']   ?? 0 ), 1 ),
            ];
        }

        set_transient( $cache_key, $rows, HOUR_IN_SECONDS * 6 );
        return $rows;
    }

    /**
     * Top Seiten (letzte 28 Tage, max. 10)
     */
    public function get_top_pages( int $limit = 10, bool $force = false ) : array {
        $cache_key = 'mlt_gsc_pages_' . $limit;
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( $cached !== false ) return $cached;
        }

        $data = $this->query_api( [
            'startDate'  => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
            'endDate'    => gmdate( 'Y-m-d', strtotime( '-2 days' ) ),
            'dimensions' => [ 'page' ],
            'rowLimit'   => $limit,
            'orderBy'    => [ [ 'fieldName' => 'clicks', 'sortOrder' => 'DESCENDING' ] ],
        ] );

        $rows = [];
        foreach ( $data['rows'] ?? [] as $row ) {
            $rows[] = [
                'url'         => $row['keys'][0] ?? '',
                'clicks'      => (int) ( $row['clicks']      ?? 0 ),
                'impressions' => (int) ( $row['impressions'] ?? 0 ),
                'ctr'         => round( ( $row['ctr']        ?? 0 ) * 100, 1 ),
                'position'    => round( ( $row['position']   ?? 0 ), 1 ),
            ];
        }

        set_transient( $cache_key, $rows, HOUR_IN_SECONDS * 6 );
        return $rows;
    }

    // ── API-Request ───────────────────────────────────────────────────────────

    private function query_api( array $body ) : array {
        $token    = $this->get_access_token();
        $property = get_option( self::OPT_PROPERTY_URL, '' );

        if ( ! $token || ! $property ) return [];

        $property_encoded = rawurlencode( $property );
        $response = wp_remote_post(
            "https://searchconsole.googleapis.com/webmasters/v3/sites/{$property_encoded}/searchAnalytics/query",
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
        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
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
        return substr( hash( 'sha256', $salt . 'mlt_gsc' ), 0, 32 );
    }

    public function get_redirect_uri() : string {
        return admin_url( 'admin.php?page=media-lab-toolkit&mlt_gsc_callback=1' );
    }
}
