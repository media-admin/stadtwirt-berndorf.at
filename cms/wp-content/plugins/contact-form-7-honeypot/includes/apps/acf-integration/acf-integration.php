<?php
/**
 * CF7 Apps ACF Integration
 *
 * @since 3.2.1
 * @package Contact Form 7 Apps
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CF7Apps_ACF_Integration_App' ) && class_exists( 'CF7Apps_App' ) ) :
	/**
	 * CF7Apps_ACF_Integration_App class
	 *
	 * @since 3.2.1
	 */
	class CF7Apps_ACF_Integration_App extends CF7Apps_App {
		/**
		 * CF7Apps_ACF_Integration_App constructor.
		 *
		 * @since 3.2.1
		 */
		public function __construct() {
			$this->id                 = 'acf-integration';
			$this->priority           = 10;
			$this->title              = __( 'Advanced Custom Fields', 'cf7apps' );
			$this->description        = __( 'Connect Advanced Custom Fields with Contact Form 7. Create CF7Apps field types in ACF and use them directly in your CF7 forms.', 'cf7apps' );
			$this->icon               = plugin_dir_url( __FILE__ ) . 'assets/images/logo.png';
			$this->has_admin_settings = true;
			$this->is_pro             = false;
			$this->by_default_enabled = false;
			$this->documentation_url  = 'https://cf7apps.com/docs/integrations/advanced-custom-field/';
			// Use singular "Integration" so both Webhook and ACF apps
			// share the same parent menu and appear under one accordion.
			$this->parent_menu        = __( 'Integration', 'cf7apps' );

			// Load CF7 integration class (doesn't require ACF to be loaded)
			$plugin_path = plugin_dir_path( __FILE__ );
			require_once $plugin_path . 'includes/class-cf7-acf-integration.php';

			add_action( 'acf/include_field_types', array( $this, 'load_acf_field_classes' ), 5 );
			add_action( 'acf/include_field_types', array( $this, 'register_acf_field_types' ), 10 );

			// Only register CF7 integration hooks if integration is enabled
			if ( $this->get_option( 'is_enabled' ) ) {
				// Initialize CF7 integration - use earlier priority to ensure it loads before CF7 processes forms
				add_action( 'plugins_loaded', array( $this, 'init_cf7_integration' ), 10 );
				add_action( 'init', array( $this, 'init_cf7_integration' ), 5 );
			}

			// Check if ACF is active and show notice if not
			add_action( 'admin_notices', array( $this, 'check_acf_dependency' ) );
		}

		/**
		 * Load ACF field classes (only when ACF is ready)
		 *
		 * @since 3.2.1
		 */
		public function load_acf_field_classes() {
			// Only load if ACF is available
			if ( ! class_exists( 'acf_field' ) ) {
				return;
			}

			$plugin_path = plugin_dir_path( __FILE__ );

			// Load ACF field classes
			require_once $plugin_path . 'includes/class-acf-field-cf7apps-text.php';
			require_once $plugin_path . 'includes/class-acf-field-cf7apps-number.php';
			require_once $plugin_path . 'includes/class-acf-field-cf7apps-url.php';
			require_once $plugin_path . 'includes/class-acf-field-cf7apps-email.php';
			require_once $plugin_path . 'includes/class-acf-field-cf7apps-textarea.php';
		}

		/**
		 * Register ACF field types
		 *
		 * @since 3.2.1
		 */
		public function register_acf_field_types() {
			if ( ! function_exists( 'acf_register_field_type' ) ) {
				return;
			}

			// Register field types if classes exist
			if ( class_exists( 'ACF_Field_CF7Apps_Text' ) ) {
				acf_register_field_type( 'ACF_Field_CF7Apps_Text' );
			}
			if ( class_exists( 'ACF_Field_CF7Apps_Number' ) ) {
				acf_register_field_type( 'ACF_Field_CF7Apps_Number' );
			}
			if ( class_exists( 'ACF_Field_CF7Apps_URL' ) ) {
				acf_register_field_type( 'ACF_Field_CF7Apps_URL' );
			}
			if ( class_exists( 'ACF_Field_CF7Apps_Email' ) ) {
				acf_register_field_type( 'ACF_Field_CF7Apps_Email' );
			}
			if ( class_exists( 'ACF_Field_CF7Apps_Textarea' ) ) {
				acf_register_field_type( 'ACF_Field_CF7Apps_Textarea' );
			}
		}

		/**
		 * Initialize CF7 integration
		 *
		 * @since 3.2.1
		 */
		public function init_cf7_integration() {
			if ( class_exists( 'CF7Apps_ACF_Integration' ) ) {
				CF7Apps_ACF_Integration::get_instance();
			}
		}

		/**
		 * Check ACF dependency and show notice
		 *
		 * @since 3.2.1
		 */
		public function check_acf_dependency() {
			// Only show on CF7 Apps admin pages
			if ( ! isset( $_GET['page'] ) || 'cf7apps' !== $_GET['page'] ) {
				return;
			}

			if ( ! class_exists( 'ACF' ) && $this->get_option( 'is_enabled' ) ) {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'CF7 Apps ACF Integration:', 'cf7apps' ); ?></strong>
						<?php esc_html_e( 'This integration requires the Advanced Custom Fields plugin to be active.', 'cf7apps' ); ?>
					</p>
				</div>
				<?php
			}
		}

		/**
		 * Get Settings
		 *
		 * @since 3.2.1
		 * @return array
		 */
		public function get_settings() {
			$settings = parent::get_settings();

			// Add ACF dependency check
			$settings['requires_acf'] = true;
			$settings['acf_available'] = class_exists( 'ACF' );

			return $settings;
		}

		/**
		 * Get the app settings.
		 *
		 * @since 3.2.1
		 * @return array
		 */
		public function admin_settings() {
			$is_acf_active = class_exists( 'ACF' );

			return array(
				'general' => array(
					'fields' => array(
						'notice'      => ! $is_acf_active ? array(
							'type'  => 'notice',
							'class' => 'warning',
							'text'  => sprintf(
								/* translators: %s: Install plugin link */
								__( 'This integration requires the Advanced Custom Fields plugin to be active. %s', 'cf7apps' ),
								'<a href="' . esc_url( admin_url( 'plugin-install.php?s=advanced-custom-fields&tab=search&type=term' ) ) . '" style="text-decoration: underline; font-weight: bold;">' . __( 'Install ACF Plugin', 'cf7apps' ) . '</a>'
							),
						) : array(
							'type'  => 'notice',
							'class' => 'info',
							'text'  => sprintf(
								/* translators: %s: Documentation URL */
								__( 'Stuck? Check our Documentation on %s', 'cf7apps' ),
								'<a href="https://cf7apps.com/docs/integrations/advanced-custom-field/" target="_blank"><u>' . __( 'ACF Integration', 'cf7apps' ) . '</u></a>'
							),
						),

						'is_enabled'  => array(
							'title'   => __( 'Enable Advanced Custom Fields', 'cf7apps' ),
							'type'    => 'checkbox',
							'default' => false,
							'help'    => __( 'When enabled, the ACF integration bridge is created between Contact Form 7 and Advanced Custom Fields.', 'cf7apps' ),
							'disabled' => ! $is_acf_active,
						),

						'save_settings' => array(
							'type'  => 'save_button',
							'text'  => __( 'Save Settings', 'cf7apps' ),
							'class' => 'button-primary',
						),
					),
				),
			);
		}
	}

	if ( ! function_exists( 'cf7apps_register_acf_integration' ) ) :
		/**
		 * Register ACF Integration App
		 *
		 * @since 3.2.1
		 * @param array $apps Array of app class names.
		 * @return array
		 */
		function cf7apps_register_acf_integration( $apps ) {
			$apps[] = 'CF7Apps_ACF_Integration_App';

			return $apps;
		}
	endif;

	add_filter( 'cf7apps_apps', 'cf7apps_register_acf_integration' );
endif;

