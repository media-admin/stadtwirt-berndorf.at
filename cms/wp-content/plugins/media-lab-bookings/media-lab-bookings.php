<?php
/**
 * Plugin Name:  Media Lab Bookings
 * Plugin URI:   https://medialab.agency
 * Description:  Buchungssystem mit Standortverwaltung, Öffnungszeiten, Zeitslots, Kapazitätslimits und standortspezifischen E-Mail-Bestätigungen.
 * Version:      1.4.0
 * Author:       Media Lab Agency
 * Text Domain:  media-lab-bookings
 * Domain Path:  /languages
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MLB_VERSION',  '1.5.2' );
define( 'MLB_PATH',     plugin_dir_path( __FILE__ ) );
define( 'MLB_URL',      plugin_dir_url( __FILE__ ) );
define( 'MLB_BASENAME', plugin_basename( __FILE__ ) );

// ── Includes ──────────────────────────────────────────────────────────────────
$mlb_includes = [
    'inc/settings.php',
    'inc/cpt.php',
    'inc/acf-fields.php',
    'inc/slots.php',
    'inc/ajax.php',
    'inc/ical.php',
    'inc/mail.php',
    'inc/notifications.php',
    'inc/export.php',
    'inc/admin.php',
    'inc/feed.php',
    'inc/calendar.php',
    'inc/dashboard-widget.php',
    'inc/shortcode.php',
];

foreach ( $mlb_includes as $file ) {
    $path = MLB_PATH . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, 'mlb_activate' );
function mlb_activate() {
    MLB_CPT::register();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'mlb_deactivate' );
function mlb_deactivate() {
    flush_rewrite_rules();
}
