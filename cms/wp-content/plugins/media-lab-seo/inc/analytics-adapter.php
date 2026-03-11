<?php
/**
 * Analytics Adapter
 *
 * Pluggbare Schnittstelle für Pageview/Nutzer-Daten.
 * Aktuell: Stub-Implementierung (gibt Platzhalter zurück).
 *
 * Adapter einbinden:
 *   add_filter( 'medialab_analytics_adapter', fn() => new MyGA4Adapter() );
 *
 * Ein Adapter muss folgendes Interface implementieren:
 *
 *   interface MediaLab_Analytics_Adapter {
 *       public function is_configured(): bool;
 *       public function get_overview( string $start, string $end ): array;
 *       // Rückgabe: [ 'pageviews' => int, 'sessions' => int, 'users' => int, 'bounce_rate' => float ]
 *
 *       public function get_top_sources( int $limit ): array;
 *       // Rückgabe: [ ['source' => string, 'sessions' => int], ... ]
 *   }
 *
 * @package MediaLab_SEO
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Stub-Adapter (Standard-Implementierung – keine Daten)
// ---------------------------------------------------------------------------

class MediaLab_Analytics_Stub {

    public function is_configured(): bool {
        return false;
    }

    public function get_overview( string $start, string $end ): array {
        return [
            'pageviews'   => 0,
            'sessions'    => 0,
            'users'       => 0,
            'bounce_rate' => 0.0,
        ];
    }

    public function get_top_sources( int $limit = 5 ): array {
        return [];
    }

    public function get_label(): string {
        return '';
    }
}

// ---------------------------------------------------------------------------
// Aktiven Adapter abrufen (Auto-Detection)
// ---------------------------------------------------------------------------

/**
 * Gibt den aktiven Analytics-Adapter zurück.
 *
 * Priorität:
 *   1. Externer Filter 'medialab_analytics_adapter'
 *   2. GA4  – wenn Property ID + Service Account JSON konfiguriert
 *   3. Matomo – wenn URL + Site ID + Token konfiguriert
 *   4. Stub  – keine Konfiguration vorhanden
 */
function medialab_analytics_get_adapter(): MediaLab_Analytics_Stub {
    // 1. Externer Filter hat Vorrang
    $adapter = apply_filters( 'medialab_analytics_adapter', null );
    if ( $adapter !== null && method_exists( $adapter, 'is_configured' ) ) {
        return $adapter;
    }

    // 2. GA4
    $ga4_property = get_option( 'medialab_ga4_property_id', '' );
    $ga4_json     = get_option( 'medialab_ga4_service_account_json', '' );
    if ( ! empty( $ga4_property ) && ! empty( $ga4_json ) ) {
        $ga4 = new MediaLab_GA4_Adapter( $ga4_property, $ga4_json );
        if ( $ga4->is_configured() ) return $ga4;
    }

    // 3. Matomo
    $matomo_url     = get_option( 'medialab_matomo_url', '' );
    $matomo_site_id = get_option( 'medialab_matomo_site_id', '' );
    $matomo_token   = get_option( 'medialab_matomo_token', '' );
    if ( ! empty( $matomo_url ) && ! empty( $matomo_site_id ) && ! empty( $matomo_token ) ) {
        $matomo = new MediaLab_Matomo_Adapter( $matomo_url, $matomo_site_id, $matomo_token );
        if ( $matomo->is_configured() ) return $matomo;
    }

    // 4. Fallback
    return new MediaLab_Analytics_Stub();
}

/**
 * Gibt den konfigurierten Adapter-Namen zurück ('ga4', 'matomo' oder '').
 */
function medialab_analytics_active_provider(): string {
    $ga4_property = get_option( 'medialab_ga4_property_id', '' );
    $ga4_json     = get_option( 'medialab_ga4_service_account_json', '' );
    if ( ! empty( $ga4_property ) && ! empty( $ga4_json ) ) return 'ga4';

    $matomo_url   = get_option( 'medialab_matomo_url', '' );
    $matomo_sid   = get_option( 'medialab_matomo_site_id', '' );
    $matomo_token = get_option( 'medialab_matomo_token', '' );
    if ( ! empty( $matomo_url ) && ! empty( $matomo_sid ) && ! empty( $matomo_token ) ) return 'matomo';

    return '';
}

// ---------------------------------------------------------------------------
// Convenience-Wrapper
// ---------------------------------------------------------------------------

function medialab_analytics_is_configured(): bool {
    return medialab_analytics_get_adapter()->is_configured();
}

function medialab_analytics_get_overview( string $start, string $end ): array {
    return medialab_analytics_get_adapter()->get_overview( $start, $end );
}

function medialab_analytics_get_top_sources( int $limit = 5 ): array {
    return medialab_analytics_get_adapter()->get_top_sources( $limit );
}

function medialab_analytics_get_label(): string {
    return medialab_analytics_get_adapter()->get_label();
}
