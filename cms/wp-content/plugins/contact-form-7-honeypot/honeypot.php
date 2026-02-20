<?php
/**
 * Plugin Name: CF7 Apps
 * Plugin URI: https://cf7apps.com/
 * Description: Contact Form 7 Apps is a collection of useful modules and extensions for Contact Form 7.
 * Author: CF7 Apps
 * Author URI: https://cf7apps.com/
 * Version: 3.4.0
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

define( 'CF7APPS_VERSION', '3.4.0' );
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