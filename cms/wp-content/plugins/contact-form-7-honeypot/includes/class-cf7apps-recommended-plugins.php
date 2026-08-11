<?php
/**
 * Recommended plugins for the CF7 Apps dashboard sidebar.
 *
 * @package CF7Apps
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CF7Apps_Recommended_Plugins
 */
class CF7Apps_Recommended_Plugins {

	/**
	 * Cached catalog.
	 *
	 * @var array|null
	 */
	private static $catalog = null;

	/**
	 * Plugin catalog keyed by API slug.
	 *
	 * @return array
	 */
	public static function get_catalog() {
		if ( null !== self::$catalog ) {
			return self::$catalog;
		}

		self::$catalog = array(
			'post-smtp'            => array(
				'wordpress_slug' => 'post-smtp',
				'plugin_files'   => array(
					'post-smtp/postman-smtp.php',
					'post-smtp/post-smtp.php',
				),
				'title'          => __( 'Post SMTP', 'cf7apps' ),
				'description'    => __( 'Reliable WordPress email delivery with logs and SMTP integrations.', 'cf7apps' ),
				'icon_url'       => CF7APPS_PLUGIN_DIR_URL . '/assets/images/dashboard/recommended/post-smtp.gif',
				'redirect_url'   => admin_url( 'admin.php?page=postman' ),
			),
			'password-protected' => array(
				'wordpress_slug' => 'password-protected',
				'plugin_files'   => array(
					'password-protected/password-protected.php',
				),
				'title'          => __( 'Password Protected', 'cf7apps' ),
				'description'    => __( 'Password protect your site, posts, and pages.', 'cf7apps' ),
				'icon_url'       => CF7APPS_PLUGIN_DIR_URL . '/assets/images/dashboard/recommended/password-protected.gif',
				'redirect_url'   => admin_url( 'options-general.php?page=password-protected' ),
			),
		);

		return apply_filters( 'cf7apps_recommended_plugins_catalog', self::$catalog );
	}

	/**
	 * Resolve installed plugin basename.
	 *
	 * @param array $entry Catalog entry.
	 * @return string
	 */
	private static function resolve_plugin_file( $entry ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $entry['plugin_files'] as $plugin_file ) {
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				return $plugin_file;
			}
		}

		$all_plugins = get_plugins();
		foreach ( $entry['plugin_files'] as $plugin_file ) {
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				return $plugin_file;
			}
		}

		return $entry['plugin_files'][0];
	}

	/**
	 * Plugin install/active state.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return string active|installed|not_installed
	 */
	private static function get_status( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) && is_plugin_active( $plugin_file ) ) {
			return 'active';
		}

		$all_plugins = get_plugins();
		if ( isset( $all_plugins[ $plugin_file ] ) ) {
			return 'installed';
		}

		return 'not_installed';
	}

	/**
	 * REST payload for the dashboard sidebar.
	 *
	 * @return array
	 */
	public static function get_payload() {
		$payload     = array();
		$can_install = current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' );

		foreach ( self::get_catalog() as $slug => $entry ) {
			$plugin_file = self::resolve_plugin_file( $entry );
			$status      = self::get_status( $plugin_file );

			if ( 'installed' === $status ) {
				$button_label = __( 'Activate', 'cf7apps' );
			} elseif ( 'active' === $status ) {
				$button_label = __( 'Active', 'cf7apps' );
			} else {
				$button_label = __( 'Install & Activate', 'cf7apps' );
			}

			$payload[] = array(
				'slug'         => $slug,
				'title'        => $entry['title'],
				'description'  => $entry['description'],
				'icon_url'     => $entry['icon_url'],
				'status'       => $status,
				'can_install'  => $can_install,
				'button_label' => $button_label,
				'redirect_url' => 'active' === $status ? '' : $entry['redirect_url'],
			);
		}

		return $payload;
	}

	/**
	 * Install and/or activate a recommended plugin.
	 *
	 * @param string $slug API slug.
	 * @return array|WP_Error
	 */
	public static function install_and_activate( $slug ) {
		if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'cf7apps_forbidden',
				__( 'You do not have permission to install plugins.', 'cf7apps' ),
				array( 'status' => 403 )
			);
		}

		$catalog = self::get_catalog();
		if ( ! isset( $catalog[ $slug ] ) ) {
			return new WP_Error(
				'cf7apps_invalid_slug',
				__( 'Unknown plugin.', 'cf7apps' ),
				array( 'status' => 400 )
			);
		}

		$entry       = $catalog[ $slug ];
		$plugin_file = self::resolve_plugin_file( $entry );
		$status      = self::get_status( $plugin_file );

		if ( 'active' === $status ) {
			return self::get_payload();
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( 'installed' === $status ) {
			$result = activate_plugin( $plugin_file );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return self::get_payload();
		}

		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $entry['wordpress_slug'],
				'fields' => array(
					'sections' => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		$skin           = new WP_Ajax_Upgrader_Skin();
		$upgrader       = new Plugin_Upgrader( $skin );
		$install_result = $upgrader->install( $api->download_link );

		if ( is_wp_error( $install_result ) ) {
			return $install_result;
		}

		if ( ! $install_result ) {
			return new WP_Error(
				'cf7apps_install_failed',
				__( 'Plugin install failed.', 'cf7apps' ),
				array( 'status' => 500 )
			);
		}

		$installed_plugin = $upgrader->plugin_info();
		if ( $installed_plugin ) {
			$plugin_file = $installed_plugin;
		} else {
			$plugin_file = self::resolve_plugin_file( $entry );
		}

		$activate_result = activate_plugin( $plugin_file );
		if ( is_wp_error( $activate_result ) ) {
			return $activate_result;
		}

		return self::get_payload();
	}
}
