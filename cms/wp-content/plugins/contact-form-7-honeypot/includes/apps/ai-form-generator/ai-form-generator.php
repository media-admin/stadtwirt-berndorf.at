<?php
/**
 * CF7Apps app: AI Form Generator (CF7 editor + middleware).
 *
 * @package CF7Apps
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CF7APPS_AI_MIDDLEWARE_BASE_URL' ) ) {
	define( 'CF7APPS_AI_MIDDLEWARE_BASE_URL', 'https://ai-api.wpexperts.io/' );
}

require_once __DIR__ . '/includes/class-cf7-ai-middleware-client.php';

if ( ! defined( 'CF7APPS_PROVISION_SECRET' ) ) {
	$prov = getenv( 'CF7APPS_PROVISION_SECRET' );
	if ( ! is_string( $prov ) || '' === $prov ) {
		$prov = isset( $_ENV['CF7APPS_PROVISION_SECRET'] ) ? (string) $_ENV['CF7APPS_PROVISION_SECRET'] : '';
	}
	if ( is_string( $prov ) && '' !== $prov ) {
		define( 'CF7APPS_PROVISION_SECRET', $prov );
	}
}

if ( ! defined( 'CF7APPS_AI_PROVISION_SECRET' ) ) {
	define(
		'CF7APPS_AI_PROVISION_SECRET',
		defined( 'CF7APPS_PROVISION_SECRET' )
			? CF7APPS_PROVISION_SECRET
			: CF7Apps_Cf7Ai_Client::EMBEDDED_PROVISION_SECRET
	);
}

if ( ! class_exists( 'CF7Apps_AI_Form_Generator' ) && class_exists( 'CF7Apps_App' ) ) :

	class CF7Apps_AI_Form_Generator extends CF7Apps_App {

		/** Register app metadata and hooks. */
		public function __construct() {
			$this->id                 = 'cf7-ai-form-generator';
			$this->priority           = 12;
			$this->title              = __( 'AI Form Generator', 'cf7apps' );
			$this->description        = __( 'Generate Contact Form 7 field tags from a short description or templates, powered by AI.', 'cf7apps' );
			$this->icon               = plugin_dir_url( __FILE__ ) . 'assets/images/ai-sparkle.svg';
			$this->has_admin_settings = true;
			$this->is_pro             = false;
			$this->by_default_enabled = false;
			$this->documentation_url  = 'https://cf7apps.com/docs/integration/cf7-ai-form-generator';
			$this->parent_menu        = __( 'Integration', 'cf7apps' );

			$this->run();
		}

		/** Settings schema for CF7Apps React admin. */
		public function admin_settings() {
			$doc_href = $this->documentation_url;
			$doc_link = '<a href="' . esc_url( $doc_href ) . '" target="_blank" rel="noopener noreferrer"><u>' . esc_html__( 'AI Form Generator', 'cf7apps' ) . '</u></a>';

			return array(
				'general' => array(
					'fields' => array(
						'notice' => array(
							'type'  => 'notice',
							'class' => 'info',
							'text'  => sprintf(
								/* translators: %s: documentation link HTML */
								__( 'Stuck? Check our Documentation on %s', 'cf7apps' ),
								$doc_link
							),
						),
						'is_enabled' => array(
							'title'   => __( 'Enable AI Form Generator', 'cf7apps' ),
							'type'    => 'checkbox',
							'default' => false,
							'help'    => __( 'Enable AI-powered form suggestions for Contact Form 7, allowing users to quickly generate complex form templates with ease.', 'cf7apps' ),
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

		/** AJAX + CF7 screen assets. */
		private function run() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_cf7_editor_assets' ) );
			add_action( 'wp_ajax_cf7_ai_generate_form', array( $this, 'ajax_cf7_ai_generate_form' ) );
		}

		/** App enabled in cf7apps_settings. */
		public static function is_integration_enabled() {
			$options = get_option( 'cf7apps_settings', array() );
			if ( empty( $options['cf7-ai-form-generator'] ) ) {
				return false;
			}
			$enabled = $options['cf7-ai-form-generator']['is_enabled'] ?? false;

			return (bool) $enabled || '1' === $enabled || 1 === $enabled;
		}

		/** Built-in template cards (title, blurb, prompt). */
		private static function get_template_definitions() {
			return array(
				'job_application'     => array(
					'title'       => __( 'Job Application Form', 'cf7apps' ),
					'description' => __( 'For my company\'s career page', 'cf7apps' ),
					'prompt'      => __( 'Create a job application form for my company\'s career page.', 'cf7apps' ),
				),
				'appointment_booking' => array(
					'title'       => __( 'Appointment Booking Form', 'cf7apps' ),
					'description' => __( 'For my clinic or healthcare service', 'cf7apps' ),
					'prompt'      => __( 'Create an appointment booking form for my clinic or healthcare service.', 'cf7apps' ),
				),
				'feedback_rating'     => array(
					'title'       => __( 'Feedback & Rating Form', 'cf7apps' ),
					'description' => __( 'For customers after a purchase', 'cf7apps' ),
					'prompt'      => __( 'Create a customer feedback and rating form for after a purchase.', 'cf7apps' ),
				),
				'real_estate_inquiry' => array(
					'title'       => __( 'Contact & Inquiry Form', 'cf7apps' ),
					'description' => __( 'For real estate property listings', 'cf7apps' ),
					'prompt'      => __( 'Create a contact and inquiry form for real estate property listings.', 'cf7apps' ),
				),
			);
		}

		/** Modal CSS/JS on CF7 form edit screens. */
		public function enqueue_cf7_editor_assets( $hook_suffix ) {
			if ( ! in_array( $hook_suffix, array( 'toplevel_page_wpcf7', 'contact_page_wpcf7', 'contact_page_wpcf7-new' ), true ) ) {
				return;
			}

			if ( ! self::is_integration_enabled() ) {
				return;
			}

			if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
				return;
			}

			if ( ! CF7Apps_Cf7Ai_Client::mw_base_ok() ) {
				return;
			}

			$base = plugin_dir_url( __FILE__ );

			wp_enqueue_style(
				'cf7apps-ai-form-generator',
				$base . 'assets/css/cf7-ai-form-generator-admin.css',
				array(),
				defined( 'CF7APPS_VERSION' ) ? CF7APPS_VERSION : '1.0.0'
			);

			wp_enqueue_script(
				'cf7apps-ai-form-generator',
				$base . 'assets/js/cf7-ai-form-generator-admin.js',
				array( 'jquery' ),
				defined( 'CF7APPS_VERSION' ) ? CF7APPS_VERSION : '1.0.0',
				true
			);

			$templates_for_js = array();
			foreach ( self::get_template_definitions() as $id => $def ) {
				$templates_for_js[] = array(
					'id'          => $id,
					'title'       => $def['title'],
					'description' => $def['description'],
					'prompt'      => $def['prompt'],
				);
			}

			wp_localize_script(
				'cf7apps-ai-form-generator',
				'cf7AiFormGen',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'cf7_ai_form_generator' ),
					'assetsUrl'  => trailingslashit( plugin_dir_url( __FILE__ ) . 'assets/images' ),
					'upgradeUrl' => 'https://cf7apps.com/pricing/',
					'i18n'       => array(
						'button'            => __( 'Generate Form with AI', 'cf7apps' ),
						'modalTitle'        => __( 'Generate Form with AI', 'cf7apps' ),
						'promptLabel'       => __( 'Describe the form you want to create', 'cf7apps' ),
						'promptPlaceholder' => __( 'e.g., Create a customer feedback form with rating scale, comments section, and contact information fields...', 'cf7apps' ),
						'generate'          => __( 'Generate Form with AI', 'cf7apps' ),
						'outputTitle'       => __( 'Generated form code', 'cf7apps' ),
						'copy'              => __( 'Copy', 'cf7apps' ),
						'insert'            => __( 'Insert', 'cf7apps' ),
						'refresh'           => __( 'Reset', 'cf7apps' ),
						'validationError'   => __( 'This field is required.', 'cf7apps' ),
						'copied'            => __( 'Copied to clipboard.', 'cf7apps' ),
						'copyFailed'        => __( 'Could not copy.', 'cf7apps' ),
						'close'             => __( 'Close', 'cf7apps' ),
					),
					'templates' => $templates_for_js,
				)
			);
		}

		/** AJAX: prompt → CF7 tags via CF7Apps_Cf7Ai_Client. */
		public function ajax_cf7_ai_generate_form() {
			check_ajax_referer( 'cf7_ai_form_generator', 'nonce' );

			if ( ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Permission denied.', 'cf7apps' ),
					),
					403
				);
			}

			if ( ! self::is_integration_enabled() ) {
				wp_send_json_error(
					array(
						'message' => __( 'AI Form Generator is disabled.', 'cf7apps' ),
					),
					400
				);
			}

			if ( ! CF7Apps_Cf7Ai_Client::mw_base_ok() ) {
				wp_send_json_error(
					array(
						'message' => __( 'AI middleware base URL is not set. Define CF7APPS_AI_MIDDLEWARE_BASE_URL in wp-config.php.', 'cf7apps' ),
					),
					400
				);
			}

			$prompt   = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
			$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';

			$combined = trim( $prompt );
			if ( '' === $combined && $template ) {
				foreach ( self::get_template_definitions() as $id => $def ) {
					if ( $id === $template ) {
						$combined = $def['prompt'];
						break;
					}
				}
			}

			if ( '' === trim( $combined ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Please enter form details or select a template to continue.', 'cf7apps' ),
					),
					400
				);
			}

			$form_tags = CF7Apps_Cf7Ai_Client::cf7_tags_from_ai( $combined );
			if ( is_wp_error( $form_tags ) ) {
				$payload = array(
					'message' => $form_tags->get_error_message(),
				);
				if ( 'no_middleware_credits' === $form_tags->get_error_code() ) {
					$payload['code']        = 'no_middleware_credits';
					$payload['upgrade_url'] = 'https://cf7apps.com/pricing/';
				}
				wp_send_json_error( $payload, 400 );
			}

			wp_send_json_success(
				array(
					'form_tags'     => $form_tags,
					'credit_source' => 'middleware',
					'upgrade_url'   => 'https://cf7apps.com/pricing/',
				)
			);
		}

		/** Register with cf7apps_apps. */
		public static function initialize_module( $apps ) {
			$apps[] = __CLASS__;
			return $apps;
		}
	}

	add_filter( 'cf7apps_apps', array( CF7Apps_AI_Form_Generator::class, 'initialize_module' ) );

endif;
