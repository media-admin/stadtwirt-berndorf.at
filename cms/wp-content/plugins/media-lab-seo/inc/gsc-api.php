<?php
/**
 * Google Search Console API
 *
 * OAuth2-Authentifizierung und Datenabruf via GSC Search Analytics API.
 * Tokens werden verschlüsselt in wp_options gespeichert.
 *
 * @package MediaLab_SEO
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Konstanten
// ---------------------------------------------------------------------------

define( 'MEDIALAB_GSC_OAUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth' );
define( 'MEDIALAB_GSC_TOKEN_URL',  'https://oauth2.googleapis.com/token' );
define( 'MEDIALAB_GSC_API_BASE',   'https://searchconsole.googleapis.com/webmasters/v3/sites/' );
define( 'MEDIALAB_GSC_SCOPE',      'https://www.googleapis.com/auth/webmasters.readonly' );
define( 'MEDIALAB_GSC_CACHE_TTL',  3600 ); // Sekunden – Daten werden 1 Stunde gecacht

// ---------------------------------------------------------------------------
// Redirect-URI (WordPress-Admin-Seite)
// ---------------------------------------------------------------------------

function medialab_gsc_redirect_uri(): string {
    return add_query_arg(
        [ 'gsc_oauth' => 'callback' ],
        admin_url( 'admin.php?page=medialab-seo-dashboard' )
    );
}

// ---------------------------------------------------------------------------
// Einstellungen lesen
// ---------------------------------------------------------------------------

function medialab_gsc_get_settings(): array {
    return [
        'client_id'     => get_option( 'medialab_gsc_client_id', '' ),
        'client_secret' => get_option( 'medialab_gsc_client_secret', '' ),
        'property_url'  => get_option( 'medialab_gsc_property_url', '' ),
    ];
}

function medialab_gsc_is_configured(): bool {
    $s = medialab_gsc_get_settings();

    if ( empty( $s['client_id'] ) || empty( $s['client_secret'] ) || empty( $s['property_url'] ) ) {
        return false;
    }

    // Platzhalter-Werte ablehnen – echte Google OAuth Client IDs
    // enden immer auf .apps.googleusercontent.com
    if ( ! str_contains( $s['client_id'], '.apps.googleusercontent.com' ) ) {
        return false;
    }

    return true;
}

function medialab_gsc_is_connected(): bool {
    return ! empty( get_option( 'medialab_gsc_refresh_token', '' ) );
}

// ---------------------------------------------------------------------------
// OAuth2-Flow
// ---------------------------------------------------------------------------

/**
 * Gibt die Google-Autorisierungs-URL zurück.
 */
function medialab_gsc_auth_url(): string {
    $s     = medialab_gsc_get_settings();
    $state = wp_create_nonce( 'medialab_gsc_oauth' );
    update_option( 'medialab_gsc_oauth_state', $state );

    return add_query_arg( [
        'client_id'     => $s['client_id'],
        'redirect_uri'  => medialab_gsc_redirect_uri(),
        'response_type' => 'code',
        'scope'         => MEDIALAB_GSC_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ], MEDIALAB_GSC_OAUTH_URL );
}

/**
 * Verarbeitet den OAuth-Callback von Google.
 * Tauscht den Code gegen Access- + Refresh-Token.
 *
 * @return true|WP_Error
 */
function medialab_gsc_handle_callback(): true|WP_Error {
    // State prüfen
    $state = sanitize_text_field( $_GET['state'] ?? '' );
    if ( ! wp_verify_nonce( $state, 'medialab_gsc_oauth' ) ) {
        return new WP_Error( 'invalid_state', 'Ungültiger OAuth-State.' );
    }

    if ( ! empty( $_GET['error'] ) ) {
        return new WP_Error( 'oauth_denied', 'Zugriff verweigert: ' . sanitize_text_field( $_GET['error'] ) );
    }

    $code = sanitize_text_field( $_GET['code'] ?? '' );
    if ( empty( $code ) ) {
        return new WP_Error( 'missing_code', 'Kein Autorisierungscode erhalten.' );
    }

    $s        = medialab_gsc_get_settings();
    $response = wp_remote_post( MEDIALAB_GSC_TOKEN_URL, [
        'body' => [
            'code'          => $code,
            'client_id'     => $s['client_id'],
            'client_secret' => $s['client_secret'],
            'redirect_uri'  => medialab_gsc_redirect_uri(),
            'grant_type'    => 'authorization_code',
        ],
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['refresh_token'] ) ) {
        $msg = $body['error_description'] ?? $body['error'] ?? 'Unbekannter Fehler beim Token-Austausch.';
        return new WP_Error( 'token_error', $msg );
    }

    // Tokens speichern
    update_option( 'medialab_gsc_refresh_token', $body['refresh_token'] );
    update_option( 'medialab_gsc_access_token',  $body['access_token'] );
    update_option( 'medialab_gsc_token_expiry',  time() + (int) ( $body['expires_in'] ?? 3600 ) );

    // Property-URL automatisch befüllen wenn leer
    if ( empty( get_option( 'medialab_gsc_property_url' ) ) ) {
        $props = medialab_gsc_list_properties();
        if ( ! is_wp_error( $props ) && ! empty( $props[0] ) ) {
            update_option( 'medialab_gsc_property_url', $props[0] );
        }
    }

    return true;
}

/**
 * Verbindung trennen – Tokens löschen.
 */
function medialab_gsc_disconnect(): void {
    delete_option( 'medialab_gsc_refresh_token' );
    delete_option( 'medialab_gsc_access_token' );
    delete_option( 'medialab_gsc_token_expiry' );
    // Cache leeren
    delete_transient( 'medialab_gsc_data_28d' );
    delete_transient( 'medialab_gsc_data_prev28d' );
    delete_transient( 'medialab_gsc_top_keywords' );
    delete_transient( 'medialab_gsc_top_pages' );
}

// ---------------------------------------------------------------------------
// Access-Token erneuern
// ---------------------------------------------------------------------------

/**
 * Gibt einen gültigen Access-Token zurück (erneuert ihn bei Bedarf).
 *
 * @return string|WP_Error
 */
function medialab_gsc_get_access_token(): string|WP_Error {
    $expiry = (int) get_option( 'medialab_gsc_token_expiry', 0 );

    // Noch 5 Minuten Puffer
    if ( time() < $expiry - 300 ) {
        return get_option( 'medialab_gsc_access_token', '' );
    }

    $refresh_token = get_option( 'medialab_gsc_refresh_token', '' );
    if ( empty( $refresh_token ) ) {
        return new WP_Error( 'no_refresh_token', 'Kein Refresh-Token vorhanden – bitte neu verbinden.' );
    }

    $s        = medialab_gsc_get_settings();
    $response = wp_remote_post( MEDIALAB_GSC_TOKEN_URL, [
        'body' => [
            'refresh_token' => $refresh_token,
            'client_id'     => $s['client_id'],
            'client_secret' => $s['client_secret'],
            'grant_type'    => 'refresh_token',
        ],
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) return $response;

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['access_token'] ) ) {
        return new WP_Error( 'refresh_failed', 'Token-Erneuerung fehlgeschlagen.' );
    }

    update_option( 'medialab_gsc_access_token', $body['access_token'] );
    update_option( 'medialab_gsc_token_expiry', time() + (int) ( $body['expires_in'] ?? 3600 ) );

    return $body['access_token'];
}

// ---------------------------------------------------------------------------
// API-Anfragen
// ---------------------------------------------------------------------------

/**
 * Sendet eine Search Analytics Query an die GSC API.
 *
 * @param array $payload  GSC-Query-Body
 * @return array|WP_Error
 */
function medialab_gsc_query( array $payload ): array|WP_Error {
    $token = medialab_gsc_get_access_token();
    if ( is_wp_error( $token ) ) return $token;

    $s            = medialab_gsc_get_settings();
    $property_url = rawurlencode( $s['property_url'] );
    $endpoint     = MEDIALAB_GSC_API_BASE . $property_url . '/searchAnalytics/query';

    $response = wp_remote_post( $endpoint, [
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
        $msg = $body['error']['message'] ?? "GSC API Fehler ($code)";
        return new WP_Error( 'gsc_api_error', $msg );
    }

    return $body;
}

/**
 * Listet alle verifizierten GSC-Properties.
 *
 * @return array|WP_Error
 */
function medialab_gsc_list_properties(): array|WP_Error {
    $token = medialab_gsc_get_access_token();
    if ( is_wp_error( $token ) ) return $token;

    $response = wp_remote_get( 'https://searchconsole.googleapis.com/webmasters/v3/sites', [
        'headers' => [ 'Authorization' => 'Bearer ' . $token ],
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) return $response;

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['siteEntry'] ) ) return [];

    return array_map( fn( $s ) => $s['siteUrl'], $body['siteEntry'] );
}

// ---------------------------------------------------------------------------
// Daten-Abruf (mit Transient-Cache)
// ---------------------------------------------------------------------------

/**
 * KPI-Summen für einen Zeitraum.
 * Gibt: impressions, clicks, ctr, position
 *
 * @param string $start  Y-m-d
 * @param string $end    Y-m-d
 * @param string $cache_key
 * @return array|WP_Error
 */
function medialab_gsc_get_totals( string $start, string $end, string $cache_key ): array|WP_Error {
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $result = medialab_gsc_query( [
        'startDate' => $start,
        'endDate'   => $end,
        'rowLimit'  => 1,
    ] );

    if ( is_wp_error( $result ) ) return $result;

    $row = $result['rows'][0] ?? [];
    $data = [
        'impressions' => (int)   ( $row['impressions'] ?? 0 ),
        'clicks'      => (int)   ( $row['clicks']      ?? 0 ),
        'ctr'         => (float) ( $row['ctr']         ?? 0 ) * 100,
        'position'    => (float) ( $row['position']    ?? 0 ),
    ];

    set_transient( $cache_key, $data, MEDIALAB_GSC_CACHE_TTL );
    return $data;
}

/**
 * Top-Keywords (letzte 28 Tage, sortiert nach Klicks).
 *
 * @param int $limit
 * @return array|WP_Error
 */
function medialab_gsc_get_top_keywords( int $limit = 10 ): array|WP_Error {
    $cache_key = 'medialab_gsc_top_keywords_' . $limit;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $end   = date( 'Y-m-d', strtotime( '-3 days' ) );  // GSC hat ~3 Tage Verzögerung
    $start = date( 'Y-m-d', strtotime( '-31 days' ) );

    $result = medialab_gsc_query( [
        'startDate'  => $start,
        'endDate'    => $end,
        'dimensions' => [ 'query' ],
        'rowLimit'   => $limit,
        'orderBy'    => [ [ 'fieldName' => 'clicks', 'sortOrder' => 'DESCENDING' ] ],
    ] );

    if ( is_wp_error( $result ) ) return $result;

    $rows = array_map( fn( $r ) => [
        'keyword'     => $r['keys'][0],
        'clicks'      => (int)   $r['clicks'],
        'impressions' => (int)   $r['impressions'],
        'ctr'         => round( (float) $r['ctr'] * 100, 1 ),
        'position'    => round( (float) $r['position'], 1 ),
    ], $result['rows'] ?? [] );

    set_transient( $cache_key, $rows, MEDIALAB_GSC_CACHE_TTL );
    return $rows;
}

/**
 * Top-Seiten (letzte 28 Tage, sortiert nach Klicks).
 *
 * @param int $limit
 * @return array|WP_Error
 */
function medialab_gsc_get_top_pages( int $limit = 10 ): array|WP_Error {
    $cache_key = 'medialab_gsc_top_pages_' . $limit;
    $cached    = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $end   = date( 'Y-m-d', strtotime( '-3 days' ) );
    $start = date( 'Y-m-d', strtotime( '-31 days' ) );

    $result = medialab_gsc_query( [
        'startDate'  => $start,
        'endDate'    => $end,
        'dimensions' => [ 'page' ],
        'rowLimit'   => $limit,
        'orderBy'    => [ [ 'fieldName' => 'clicks', 'sortOrder' => 'DESCENDING' ] ],
    ] );

    if ( is_wp_error( $result ) ) return $result;

    $rows = array_map( fn( $r ) => [
        'url'         => $r['keys'][0],
        'clicks'      => (int)   $r['clicks'],
        'impressions' => (int)   $r['impressions'],
        'ctr'         => round( (float) $r['ctr'] * 100, 1 ),
        'position'    => round( (float) $r['position'], 1 ),
    ], $result['rows'] ?? [] );

    set_transient( $cache_key, $rows, MEDIALAB_GSC_CACHE_TTL );
    return $rows;
}

/**
 * Aggregierte Daten für die letzten 28 Tage + Vorperiode.
 * Wird von Dashboard und Mailer gemeinsam genutzt.
 *
 * @return array  ['current' => [...], 'previous' => [...], 'keywords' => [...], 'pages' => [...]]
 */
function medialab_gsc_get_dashboard_data(): array {
    $now   = strtotime( '-3 days' );   // GSC-Verzögerung
    $end   = date( 'Y-m-d', $now );
    $start = date( 'Y-m-d', strtotime( '-30 days', $now ) );

    $prev_end   = date( 'Y-m-d', strtotime( '-31 days', $now ) );
    $prev_start = date( 'Y-m-d', strtotime( '-61 days', $now ) );

    $current  = medialab_gsc_get_totals( $start, $end, 'medialab_gsc_current' );
    $previous = medialab_gsc_get_totals( $prev_start, $prev_end, 'medialab_gsc_previous' );
    $keywords = medialab_gsc_get_top_keywords( 10 );
    $pages    = medialab_gsc_get_top_pages( 10 );

    return [
        'current'    => is_wp_error( $current )  ? [] : $current,
        'previous'   => is_wp_error( $previous ) ? [] : $previous,
        'keywords'   => is_wp_error( $keywords ) ? [] : $keywords,
        'pages'      => is_wp_error( $pages )    ? [] : $pages,
        'period'     => [ 'start' => $start, 'end' => $end ],
        'error'      => is_wp_error( $current ) ? $current->get_error_message() : null,
    ];
}

// ---------------------------------------------------------------------------
// Cache manuell leeren
// ---------------------------------------------------------------------------

function medialab_gsc_flush_cache(): void {
    foreach ( [ 'medialab_gsc_current', 'medialab_gsc_previous',
                'medialab_gsc_top_keywords_10', 'medialab_gsc_top_pages_10' ] as $key ) {
        delete_transient( $key );
    }
}
