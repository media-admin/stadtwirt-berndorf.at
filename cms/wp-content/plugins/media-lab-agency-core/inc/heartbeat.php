<?php
if (!defined('ABSPATH')) exit;

/**
 * Heartbeat Monitoring
 * Ersetzt Pull-basierte Uptime-Checks durch Push-basierte Heartbeats
 * (Better Stack / Healthchecks.io), um Fehlalarme durch externe Prober zu vermeiden.
 */

add_action('init', 'medialab_heartbeat_maybe_generate_token');
function medialab_heartbeat_maybe_generate_token() {
    if (!get_option('medialab_heartbeat_token')) {
        update_option('medialab_heartbeat_token', wp_generate_password(32, false));
    }
}

add_action('rest_api_init', 'medialab_heartbeat_register_route');
function medialab_heartbeat_register_route() {
    register_rest_route('medialab/v1', '/heartbeat', [
        'methods'             => 'GET',
        'callback'            => 'medialab_heartbeat_handle',
        'permission_callback' => 'medialab_heartbeat_check_token',
    ]);
}

function medialab_heartbeat_check_token($request) {
    $stored = get_option('medialab_heartbeat_token');
    $given  = $request->get_param('token');
    return $stored && $given && hash_equals($stored, (string) $given);
}

function medialab_heartbeat_handle($request) {
    if (!get_option('medialab_heartbeat_enabled')) {
        return new WP_REST_Response(['status' => 'disabled'], 200);
    }

    $ping_url = get_option('medialab_heartbeat_url');
    if (!$ping_url) {
        return new WP_REST_Response(['status' => 'not_configured'], 200);
    }

    // Mini Health-Check: DB-Verbindung testen
    global $wpdb;
    $db_ok = ($wpdb->get_var("SELECT 1") === '1');

    if ($db_ok) {
        wp_remote_get($ping_url, ['timeout' => 5, 'blocking' => false]);
        update_option('medialab_heartbeat_last_ping', time());
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    // Explizit als fehlgeschlagen melden statt zu schweigen
    wp_remote_get($ping_url . '/fail', ['timeout' => 5, 'blocking' => false]);
    return new WP_REST_Response(['status' => 'db_unhealthy'], 200);
}