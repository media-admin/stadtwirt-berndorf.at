<?php
if (!defined('ABSPATH')) exit;

/**
 * Heartbeat Monitoring
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

/**
 * Liest Config-Werte primär über ACF (options_-Präfix), fällt auf
 * direkte wp_options zurück (z.B. media-lab.at, per WP-CLI gesetzt, kein ACF).
 */
function medialab_heartbeat_get_setting($name) {
    if (function_exists('get_field')) {
        $value = get_field($name, 'option');
        if ($value !== null && $value !== false && $value !== '') {
            return $value;
        }
    }
    return get_option($name);
}

function medialab_heartbeat_handle($request) {
    $enabled = medialab_heartbeat_get_setting('medialab_heartbeat_enabled');
    if (!$enabled) {
        return new WP_REST_Response(['status' => 'disabled'], 200);
    }

    $ping_url = medialab_heartbeat_get_setting('medialab_heartbeat_url');
    if (!$ping_url) {
        return new WP_REST_Response(['status' => 'not_configured'], 200);
    }

    global $wpdb;
    $db_ok = ($wpdb->get_var("SELECT 1") === '1');

    if ($db_ok) {
        wp_remote_get($ping_url, ['timeout' => 5, 'blocking' => false]);
        update_option('medialab_heartbeat_last_ping', time());
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    wp_remote_get($ping_url . '/fail', ['timeout' => 5, 'blocking' => false]);
    return new WP_REST_Response(['status' => 'db_unhealthy'], 200);
}