<?php
/**
 * MLT_Analytics_Adapter
 *
 * Pluggbarer Adapter für Analytics-Reporting-Daten (separat vom Tracking).
 * Liefert Seitenaufrufe, Nutzer und Traffic-Quellen für das SEO-Dashboard
 * und den wöchentlichen Report.
 *
 * GA4-Adapter: OAuth User-Login (MLT_GA4_API) als primäre Methode.
 *              Service Account JSON als Fallback für bestehende Setups.
 * Matomo-Adapter: API-Token (unverändert).
 *
 * Eigener Adapter:
 *   add_filter('mlt_analytics_adapter', fn($adapter) => new MyAdapter())
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Interface ─────────────────────────────────────────────────────────────────

interface MLT_Analytics_Adapter_Interface {
    /** Gibt [ 'pageviews' => int, 'sessions' => int, 'users' => int ] zurück */
    public function get_overview( string $start, string $end ) : array;

    /** Gibt Array von [ 'source' => string, 'sessions' => int ] zurück */
    public function get_sources( string $start, string $end, int $limit = 5 ) : array;

    /** Gibt Array von [ 'url' => string, 'pageviews' => int ] zurück */
    public function get_top_pages( string $start, string $end, int $limit = 10 ) : array;

    /** Prüft ob der Adapter konfiguriert und nutzbar ist */
    public function is_available() : bool;
}

// ── GA4 Data API Adapter ──────────────────────────────────────────────────────

class MLT_GA4_Data_Adapter implements MLT_Analytics_Adapter_Interface {

    /**
     * Gibt an, welche Methode aktiv ist.
     * 'oauth'           → MLT_GA4_API (User-Login)
     * 'service_account' → Legacy Service Account JSON
     * 'none'            → nicht konfiguriert
     */
    private string $mode = 'none';

    private ?MLT_GA4_API $oauth_api    = null;
    private string $sa_property_id     = '';
    private string $sa_json            = '';

    public function __construct() {
        // OAuth prüfen (primär)
        $ga4 = MLT_GA4_API::instance();
        if ( $ga4->is_connected() ) {
            $this->mode      = 'oauth';
            $this->oauth_api = $ga4;
            return;
        }

        // Service Account als Fallback (Legacy)
        $sa_id   = get_option( 'mlt_ga4_property_id', '' );
        $sa_json = get_option( 'mlt_ga4_service_account_json', '' );
        if ( $sa_id && $sa_json ) {
            $this->mode          = 'service_account';
            $this->sa_property_id = $sa_id;
            $this->sa_json        = $sa_json;
        }
    }

    public function is_available() : bool {
        return $this->mode !== 'none';
    }

    public function get_overview( string $start, string $end ) : array {
        if ( $this->mode === 'oauth' ) {
            return $this->oauth_api->get_overview( $start, $end );
        }
        if ( $this->mode === 'service_account' ) {
            return $this->sa_run_overview( $start, $end );
        }
        return [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0 ];
    }

    public function get_sources( string $start, string $end, int $limit = 5 ) : array {
        if ( $this->mode === 'oauth' ) {
            return $this->oauth_api->get_sources( $start, $end, $limit );
        }
        if ( $this->mode === 'service_account' ) {
            return $this->sa_run_sources( $start, $end, $limit );
        }
        return [];
    }

    public function get_top_pages( string $start, string $end, int $limit = 10 ) : array {
        if ( $this->mode === 'oauth' ) {
            return $this->oauth_api->get_top_pages( $start, $end, $limit );
        }
        if ( $this->mode === 'service_account' ) {
            return $this->sa_run_top_pages( $start, $end, $limit );
        }
        return [];
    }

    // ── Service Account – Methoden (Legacy-Fallback) ──────────────────────────

    private function sa_run_overview( string $start, string $end ) : array {
        $response = $this->sa_run_report( [
            'dateRanges' => [ [ 'startDate' => $start, 'endDate' => $end ] ],
            'metrics'    => [
                [ 'name' => 'screenPageViews' ],
                [ 'name' => 'sessions' ],
                [ 'name' => 'totalUsers' ],
            ],
        ] );

        if ( empty( $response['rows'][0]['metricValues'] ) ) {
            return [ 'pageviews' => 0, 'sessions' => 0, 'users' => 0 ];
        }

        $vals = $response['rows'][0]['metricValues'];
        return [
            'pageviews' => (int) ( $vals[0]['value'] ?? 0 ),
            'sessions'  => (int) ( $vals[1]['value'] ?? 0 ),
            'users'     => (int) ( $vals[2]['value'] ?? 0 ),
        ];
    }

    private function sa_run_sources( string $start, string $end, int $limit ) : array {
        $response = $this->sa_run_report( [
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
        return $rows;
    }

    private function sa_run_top_pages( string $start, string $end, int $limit ) : array {
        $response = $this->sa_run_report( [
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
        return $rows;
    }

    private function sa_run_report( array $body ) : array {
        $token = $this->sa_get_access_token();
        if ( ! $token ) return [];

        $property_id = preg_replace( '/\D/', '', $this->sa_property_id );
        $response    = wp_remote_post(
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
        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
    }

    private function sa_get_access_token() : string {
        $cached = get_transient( 'mlt_ga4_sa_access_token' );
        if ( $cached ) return $cached;

        $credentials = json_decode( $this->sa_json, true );
        if ( ! $credentials ) return '';

        $now     = time();
        $payload = base64_encode( json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) )
            . '.' . base64_encode( json_encode( [
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'exp'   => $now + 3600,
                'iat'   => $now,
            ] ) );

        $private_key = $credentials['private_key'] ?? '';
        if ( ! $private_key ) return '';

        $key = openssl_pkey_get_private( $private_key );
        if ( ! $key ) return '';

        $signature = '';
        openssl_sign( $payload, $signature, $key, 'SHA256' );
        $jwt = $payload . '.' . base64_encode( $signature );

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
        ] );

        if ( is_wp_error( $response ) ) return '';

        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $token = $body['access_token'] ?? '';

        if ( $token ) {
            set_transient( 'mlt_ga4_sa_access_token', $token, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
        }

        return $token;
    }
}

// ── Matomo Adapter ────────────────────────────────────────────────────────────

class MLT_Matomo_Adapter implements MLT_Analytics_Adapter_Interface {

    private string $url;
    private string $token;
    private string $site_id;

    public function __construct() {
        $this->url     = rtrim( get_option( 'mlt_matomo_url', '' ), '/' );
        $this->token   = get_option( 'mlt_matomo_token', '' );
        $this->site_id = get_option( 'mlt_matomo_site_id', '1' );
    }

    public function is_available() : bool {
        return ! empty( $this->url ) && ! empty( $this->token );
    }

    public function get_overview( string $start, string $end ) : array {
        $data = $this->call_api( [
            'method'  => 'VisitsSummary.get',
            'date'    => $start . ',' . $end,
            'period'  => 'range',
        ] );

        return [
            'pageviews' => (int) ( $data['nb_pageviews']     ?? 0 ),
            'sessions'  => (int) ( $data['nb_visits']        ?? 0 ),
            'users'     => (int) ( $data['nb_uniq_visitors'] ?? 0 ),
        ];
    }

    public function get_sources( string $start, string $end, int $limit = 5 ) : array {
        $data = $this->call_api( [
            'method'       => 'Referrers.getAll',
            'date'         => $start . ',' . $end,
            'period'       => 'range',
            'filter_limit' => $limit,
        ] );

        $rows = [];
        foreach ( (array) $data as $item ) {
            $rows[] = [
                'source'   => $item['label']     ?? '(unknown)',
                'sessions' => (int) ( $item['nb_visits'] ?? 0 ),
            ];
        }
        return $rows;
    }

    public function get_top_pages( string $start, string $end, int $limit = 10 ) : array {
        $data = $this->call_api( [
            'method'       => 'Actions.getPageUrls',
            'date'         => $start . ',' . $end,
            'period'       => 'range',
            'filter_limit' => $limit,
        ] );

        $rows = [];
        foreach ( (array) $data as $item ) {
            $rows[] = [
                'url'       => $item['label']       ?? '/',
                'pageviews' => (int) ( $item['nb_pageviews'] ?? 0 ),
            ];
        }
        return $rows;
    }

    private function call_api( array $params ) : array {
        $query = http_build_query( array_merge( [
            'module'     => 'API',
            'format'     => 'JSON',
            'idSite'     => $this->site_id,
            'token_auth' => $this->token,
        ], $params ) );

        $response = wp_remote_get( $this->url . '/index.php?' . $query, [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) return [];
        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
    }
}

// ── Adapter Factory ───────────────────────────────────────────────────────────

class MLT_Analytics_Adapter_Factory {

    public static function get() : ?MLT_Analytics_Adapter_Interface {
        $provider = get_option( 'mlt_analytics_provider', 'ga4' );

        $adapter = match ( $provider ) {
            'ga4'    => new MLT_GA4_Data_Adapter(),
            'matomo' => new MLT_Matomo_Adapter(),
            default  => null,
        };

        /**
         * Filter: Eigenen Analytics-Adapter einstecken.
         *
         * @param MLT_Analytics_Adapter_Interface|null $adapter
         */
        $adapter = apply_filters( 'mlt_analytics_adapter', $adapter );

        if ( $adapter && $adapter->is_available() ) return $adapter;
        return null;
    }
}
