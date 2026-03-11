<?php
/**
 * Analytics Adapter: Google Analytics 4
 *
 * Nutzt die GA4 Data API v1 mit einem Google Service Account (JSON-Key).
 * Kein zweiter OAuth-Flow – der Service Account wird einmalig in der
 * Google Cloud Console erstellt und das JSON-Key in den Einstellungen hinterlegt.
 *
 * Voraussetzungen:
 *   1. Google Cloud Console → Projekt → „Google Analytics Data API" aktivieren
 *   2. Service Account erstellen → JSON-Key herunterladen
 *   3. In GA4: Verwaltung → Property → Kontozugriff → Service-Account-E-Mail
 *      als Betrachter hinzufügen
 *   4. JSON-Key-Inhalt + Property-ID im SEO Dashboard eintragen
 *
 * @package MediaLab_SEO
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_GA4_Adapter {

    // ── Konstanten ─────────────────────────────────────────────────────────

    private const API_BASE      = 'https://analyticsdata.googleapis.com/v1beta/properties/';
    private const TOKEN_URL     = 'https://oauth2.googleapis.com/token';
    private const SCOPE         = 'https://www.googleapis.com/auth/analytics.readonly';
    private const CACHE_TTL     = 3600; // 1 Stunde

    // ── Konfiguration ──────────────────────────────────────────────────────

    private string $property_id;
    private array  $service_account;

    public function __construct( string $property_id, string $service_account_json ) {
        $this->property_id     = preg_replace( '/\D/', '', $property_id ); // nur Ziffern
        $this->service_account = json_decode( $service_account_json, true ) ?? [];
    }

    // ── Interface ──────────────────────────────────────────────────────────

    public function is_configured(): bool {
        return ! empty( $this->property_id )
            && ! empty( $this->service_account['client_email'] )
            && ! empty( $this->service_account['private_key'] );
    }

    public function get_label(): string {
        return 'Google Analytics 4';
    }

    /**
     * Überblick-Daten für einen Zeitraum.
     *
     * @return array { pageviews, sessions, users, bounce_rate }
     */
    public function get_overview( string $start, string $end ): array {
        $cache_key = 'medialab_ga4_overview_' . md5( $start . $end );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $response = $this->run_report( [
            'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
            'metrics'    => [
                [ 'name' => 'screenPageViews' ],
                [ 'name' => 'sessions' ],
                [ 'name' => 'totalUsers' ],
                [ 'name' => 'bounceRate' ],
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0, 'bounce_rate' => 0.0, 'error' => $response->get_error_message() ];
        }

        $row = $response['rows'][0]['metricValues'] ?? [];

        $data = [
            'pageviews'   => (int)   ( $row[0]['value'] ?? 0 ),
            'sessions'    => (int)   ( $row[1]['value'] ?? 0 ),
            'users'       => (int)   ( $row[2]['value'] ?? 0 ),
            'bounce_rate' => round( (float) ( $row[3]['value'] ?? 0 ) * 100, 1 ),
        ];

        set_transient( $cache_key, $data, self::CACHE_TTL );
        return $data;
    }

    /**
     * Top Traffic-Quellen.
     *
     * @return array [ { source, sessions }, ... ]
     */
    public function get_top_sources( int $limit = 5 ): array {
        $cache_key = 'medialab_ga4_sources_' . $limit;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $end   = date( 'Y-m-d' );
        $start = date( 'Y-m-d', strtotime( '-30 days' ) );

        $response = $this->run_report( [
            'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
            'dimensions' => [ [ 'name' => 'sessionDefaultChannelGroup' ] ],
            'metrics'    => [ [ 'name' => 'sessions' ] ],
            'orderBys'   => [ [ 'metric' => [ 'metricName' => 'sessions' ], 'desc' => true ] ],
            'limit'      => $limit,
        ] );

        if ( is_wp_error( $response ) ) return [];

        $rows = array_map( fn( $r ) => [
            'source'   => $r['dimensionValues'][0]['value'] ?? '(unknown)',
            'sessions' => (int) ( $r['metricValues'][0]['value'] ?? 0 ),
        ], $response['rows'] ?? [] );

        set_transient( $cache_key, $rows, self::CACHE_TTL );
        return $rows;
    }

    // ── API-Kommunikation ──────────────────────────────────────────────────

    /**
     * Sendet eine runReport-Anfrage an die GA4 Data API.
     *
     * @return array|WP_Error
     */
    private function run_report( array $payload ): array|WP_Error {
        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) return $token;

        $url      = self::API_BASE . $this->property_id . ':runReport';
        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 20,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "GA4 API Fehler ($code)";
            return new WP_Error( 'ga4_api_error', $msg );
        }

        return $body;
    }

    // ── JWT / Service-Account-Token ────────────────────────────────────────

    /**
     * Erstellt ein kurzlebiges Access-Token via JWT (Service Account).
     *
     * @return string|WP_Error
     */
    private function get_access_token(): string|WP_Error {
        // Cache prüfen
        $cached = get_transient( 'medialab_ga4_access_token' );
        if ( $cached ) return $cached;

        $jwt = $this->create_jwt();
        if ( is_wp_error( $jwt ) ) return $jwt;

        $response = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'ga4_token_error', $body['error_description'] ?? 'Token-Anfrage fehlgeschlagen.' );
        }

        $ttl = max( 30, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
        set_transient( 'medialab_ga4_access_token', $body['access_token'], $ttl );

        return $body['access_token'];
    }

    /**
     * Erstellt ein signiertes JWT für den Service-Account.
     * Nutzt PHP's openssl_sign mit RS256.
     *
     * @return string|WP_Error
     */
    private function create_jwt(): string|WP_Error {
        $now = time();

        $header = $this->base64url_encode( wp_json_encode( [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ] ) );

        $claim = $this->base64url_encode( wp_json_encode( [
            'iss'   => $this->service_account['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ] ) );

        $signing_input = $header . '.' . $claim;

        // Private Key aus dem Service Account JSON
        $private_key = openssl_pkey_get_private( $this->service_account['private_key'] );
        if ( ! $private_key ) {
            return new WP_Error( 'ga4_jwt_error', 'Private Key ungültig oder nicht lesbar.' );
        }

        $signature = '';
        if ( ! openssl_sign( $signing_input, $signature, $private_key, 'SHA256' ) ) {
            return new WP_Error( 'ga4_jwt_error', 'JWT-Signierung fehlgeschlagen.' );
        }

        return $signing_input . '.' . $this->base64url_encode( $signature );
    }

    private function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    // ── Cache leeren ───────────────────────────────────────────────────────

    public static function flush_cache(): void {
        delete_transient( 'medialab_ga4_access_token' );
        foreach ( [ 5, 10 ] as $limit ) {
            delete_transient( 'medialab_ga4_sources_' . $limit );
        }
        // Overview-Cache: Schlüssel sind dynamisch – globaler Flush via Option
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_medialab_ga4_overview_%'"
        );
    }
}
