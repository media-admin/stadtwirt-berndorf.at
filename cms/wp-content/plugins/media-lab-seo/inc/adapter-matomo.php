<?php
/**
 * Analytics Adapter: Matomo
 *
 * Nutzt die Matomo Reporting API (HTTP API).
 * Funktioniert mit jeder selbst-gehosteten Matomo-Instanz.
 *
 * Voraussetzungen:
 *   1. Matomo-Backend → Persönliche Einstellungen → API-Authentifizierungs-Token
 *   2. Site ID der gewünschten Website (steht in Matomo unter Verwaltung → Websites)
 *   3. Matomo-URL + Site ID + Token im SEO Dashboard eintragen
 *
 * @package MediaLab_SEO
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Matomo_Adapter {

    // ── Konstanten ─────────────────────────────────────────────────────────

    private const CACHE_TTL = 3600; // 1 Stunde

    // ── Konfiguration ──────────────────────────────────────────────────────

    private string $matomo_url;
    private string $site_id;
    private string $token;

    public function __construct( string $matomo_url, string $site_id, string $token ) {
        $this->matomo_url = trailingslashit( esc_url_raw( $matomo_url ) );
        $this->site_id    = sanitize_text_field( $site_id );
        $this->token      = sanitize_text_field( $token );
    }

    // ── Interface ──────────────────────────────────────────────────────────

    public function is_configured(): bool {
        return ! empty( $this->matomo_url )
            && ! empty( $this->site_id )
            && ! empty( $this->token );
    }

    public function get_label(): string {
        return 'Matomo';
    }

    /**
     * Überblick-Daten für einen Zeitraum.
     *
     * @return array { pageviews, sessions, users, bounce_rate }
     */
    public function get_overview( string $start, string $end ): array {
        $cache_key = 'medialab_matomo_overview_' . md5( $start . $end );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        // Matomo-Datumsformat: YYYY-MM-DD,YYYY-MM-DD
        $period = $start . ',' . $end;

        $result = $this->api_call( 'VisitsSummary.get', [
            'period' => 'range',
            'date'   => $period,
        ] );

        if ( is_wp_error( $result ) ) {
            return [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0, 'bounce_rate' => 0.0, 'error' => $result->get_error_message() ];
        }

        $data = [
            'pageviews'   => (int)   ( $result['nb_pageviews']     ?? $result['nb_actions'] ?? 0 ),
            'sessions'    => (int)   ( $result['nb_visits']        ?? 0 ),
            'users'       => (int)   ( $result['nb_uniq_visitors'] ?? 0 ),
            'bounce_rate' => (float) str_replace( '%', '', $result['bounce_rate'] ?? '0' ),
        ];

        set_transient( $cache_key, $data, self::CACHE_TTL );
        return $data;
    }

    /**
     * Top Traffic-Quellen (Referer-Typen).
     *
     * @return array [ { source, sessions }, ... ]
     */
    public function get_top_sources( int $limit = 5 ): array {
        $cache_key = 'medialab_matomo_sources_' . $limit;
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        $end   = date( 'Y-m-d' );
        $start = date( 'Y-m-d', strtotime( '-30 days' ) );

        $result = $this->api_call( 'Referrers.getReferrerType', [
            'period'   => 'range',
            'date'     => $start . ',' . $end,
            'filter_limit' => $limit,
        ] );

        if ( is_wp_error( $result ) || ! is_array( $result ) ) return [];

        $rows = array_map( fn( $r ) => [
            'source'   => $r['label'] ?? '(unbekannt)',
            'sessions' => (int) ( $r['nb_visits'] ?? 0 ),
        ], array_slice( $result, 0, $limit ) );

        set_transient( $cache_key, $rows, self::CACHE_TTL );
        return $rows;
    }

    // ── API-Kommunikation ──────────────────────────────────────────────────

    /**
     * Ruft die Matomo Reporting API auf.
     *
     * @param string $method  API-Methode, z.B. 'VisitsSummary.get'
     * @param array  $params  Zusätzliche Parameter
     * @return array|WP_Error
     */
    private function api_call( string $method, array $params = [] ): array|WP_Error {
        $url = add_query_arg( array_merge( [
            'module'     => 'API',
            'method'     => $method,
            'idSite'     => $this->site_id,
            'token_auth' => $this->token,
            'format'     => 'json',
        ], $params ), $this->matomo_url . 'index.php' );

        $response = wp_remote_get( $url, [
            'timeout'   => 20,
            'sslverify' => apply_filters( 'medialab_matomo_sslverify', true ),
        ] );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            return new WP_Error( 'matomo_error', "Matomo API Fehler ($code)" );
        }

        // Matomo gibt Fehler auch im Body zurück
        if ( isset( $body['result'] ) && $body['result'] === 'error' ) {
            return new WP_Error( 'matomo_api_error', $body['message'] ?? 'Unbekannter Matomo-Fehler' );
        }

        return $body ?? [];
    }

    // ── Verbindungstest ────────────────────────────────────────────────────

    /**
     * Testet die Verbindung – gibt Site-Infos zurück.
     *
     * @return array|WP_Error  ['site_name' => string, 'url' => string]
     */
    public function test_connection(): array|WP_Error {
        $result = $this->api_call( 'SitesManager.getSiteFromId' );

        if ( is_wp_error( $result ) ) return $result;
        if ( empty( $result['name'] ) ) return new WP_Error( 'matomo_site_error', 'Site nicht gefunden – Site ID prüfen.' );

        return [
            'site_name' => $result['name'],
            'url'       => $result['main_url'] ?? '',
        ];
    }

    // ── Cache leeren ───────────────────────────────────────────────────────

    public static function flush_cache(): void {
        foreach ( [ 5, 10 ] as $limit ) {
            delete_transient( 'medialab_matomo_sources_' . $limit );
        }
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_medialab_matomo_overview_%'"
        );
    }
}
