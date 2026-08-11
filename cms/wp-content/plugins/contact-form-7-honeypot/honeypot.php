<?php
/**
 * Plugin Name: CF7 Apps
 * Plugin URI: https://cf7apps.com/
 * Description: Contact Form 7 Apps is a collection of useful modules and extensions for Contact Form 7.
 * Author: CF7 Apps
 * Author URI: https://cf7apps.com/
 * Version: 3.7.2
 * Text Domain: contact-form-7-honeypot
 * Domain Path: /languages/
 * Requires Plugins: contact-form-7
 */


/** 
 * Freemius initialization
 * 
 * @since 3.2.1
 */
if ( ! function_exists( 'cf7h_fs' ) ) {
    // Create a helper function for easy SDK access.
    function cf7h_fs() {
        global $cf7h_fs;

        if ( ! isset( $cf7h_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/freemius/start.php';

            $cf7h_fs = fs_dynamic_init( array(
                'id'                  => '21471',
                'slug'                => 'contact-form-7-honeypot',
                'type'                => 'plugin',
                'public_key'          => 'pk_ad07f3a314c2ac84f528f0a53268c',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'cf7apps',
                    'first-path'     => 'admin.php?page=cf7apps',
                    'account'        => false,
                    'contact'        => false,
                    'support'        => false,
                ),
            ) );
        }

        return $cf7h_fs;
    }

    // Init Freemius.
    cf7h_fs();
    // Signal that SDK was initiated.
    do_action( 'cf7h_fs_loaded' );
}


defined( 'ABSPATH' ) || exit;

define( 'CF7APPS_VERSION', '3.7.1' );
define( 'CF7APPS_PLUGIN', __FILE__ );
define( 'CF7APPS_PLUGIN_BASENAME', plugin_basename( CF7APPS_PLUGIN ) );
define( 'CF7APPS_PLUGIN_NAME', trim( dirname( CF7APPS_PLUGIN_BASENAME ), '/' ) );
define( 'CF7APPS_PLUGIN_DIR', untrailingslashit( dirname( CF7APPS_PLUGIN ) ) );
define( 'CF7APPS_PLUGIN_DIR_URL', untrailingslashit( plugin_dir_url( CF7APPS_PLUGIN ) ) );
define( 'CF7APPS_DEP_PLUGIN', 'contact-form-7/wp-contact-form-7.php' );

require_once CF7APPS_PLUGIN_DIR . '/includes/class-cf7apps.php';

// Legacy Honeypot
require_once CF7APPS_PLUGIN_DIR . '/legacy-honeypot/legacy-honeypot.php';

/**
 * Initialize Contact Form 7 Apps
 * 
 * @since 3.0.0
 */
if( ! function_exists( 'CF7Apps' ) ):
function CF7Apps() {
	/**
	 * Fires before Contact Form 7 Apps is initialized
	 * 
	 * @since 3.0.0
	 */
	do_action( 'cf7apps_before_init' );

	$_class = CF7Apps::instance();

	/**
	 * Fires Contact Form 7 Apps is initialized
	 * 
	 * @since 3.0.0
	 */
	do_action( 'cf7apps_init' );

	return $_class;
}
endif;

CF7Apps();

/**
 * Load plugin translations.
 *
 * The plugin header uses `contact-form-7-honeypot`, while the React admin UI
 * uses the `cf7apps` text domain. Load the same MO file for both domains.
 *
 * Prefer bundled plugin translations over stale copies in
 * wp-content/languages/plugins/ so newly added strings ship with the plugin.
 *
 * @since 3.6.2
 */
function cf7apps_load_textdomain() {
	$locale   = determine_locale();
	$rel_path = dirname( CF7APPS_PLUGIN_BASENAME ) . '/languages';
	$mofile   = CF7APPS_PLUGIN_DIR . '/languages/contact-form-7-honeypot-' . $locale . '.mo';

	load_plugin_textdomain( 'contact-form-7-honeypot', false, $rel_path );

	if ( ! is_readable( $mofile ) ) {
		return;
	}

	// Reload bundled translations last so newer strings win over WP_LANG_DIR.
	unload_textdomain( 'contact-form-7-honeypot' );
	load_textdomain( 'contact-form-7-honeypot', $mofile );

	unload_textdomain( 'cf7apps' );
	load_textdomain( 'cf7apps', $mofile );
}
add_action( 'init', 'cf7apps_load_textdomain' );

/**
 * Prefer plugin-bundled translation files over outdated WP_LANG_DIR copies.
 *
 * @since 3.6.2
 *
 * @param string $file   Path to the translation file WordPress wants to load.
 * @param string $domain Text domain.
 * @param string $locale Locale code.
 * @return string
 */
function cf7apps_prefer_bundled_translation_file( $file, $domain, $locale ) {
	if ( 'contact-form-7-honeypot' !== $domain && 'cf7apps' !== $domain ) {
		return $file;
	}

	$bundled_php = CF7APPS_PLUGIN_DIR . '/languages/contact-form-7-honeypot-' . $locale . '.l10n.php';
	$bundled_mo  = CF7APPS_PLUGIN_DIR . '/languages/contact-form-7-honeypot-' . $locale . '.mo';

	if ( is_string( $file ) && substr( $file, -9 ) === '.l10n.php' && is_readable( $bundled_php ) ) {
		return $bundled_php;
	}

	if ( is_string( $file ) && substr( $file, -3 ) === '.mo' && is_readable( $bundled_mo ) ) {
		return $bundled_mo;
	}

	return $file;
}
add_filter( 'load_translation_file', 'cf7apps_prefer_bundled_translation_file', 10, 3 );