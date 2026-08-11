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

		/** Free quota window length (legacy fallback only). Middleware uses calendar month renewal. */
		const FREE_CREDITS_RESET_DAYS = 30;

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
			$this->parent_menu        = 'integration';

			$this->run();
		}

		/**
		 * Cached middleware credit snapshot for the CF7 editor modal (HFCF7-541 / HFCF7-546).
		 *
		 * Limit stays hidden until middleware credits are revealed after the first successful generation.
		 * Free builds show a reset date when exhausted (not an Upgrade CTA).
		 *
		 * @return array{limit:?int,remaining:?int,show_limit:bool,exhausted:bool,show_upgrade:bool,reset_at:?int,reset_date:string,source:string}
		 */
		public static function get_usage_state() {
			$app       = self::get_app_settings_row();
			$remaining = array_key_exists( 'middleware_credits_remaining', $app ) && is_numeric( $app['middleware_credits_remaining'] )
				? max( 0, (int) $app['middleware_credits_remaining'] )
				: null;
			$limit     = array_key_exists( 'middleware_credits_limit', $app ) && is_numeric( $app['middleware_credits_limit'] )
				? max( 0, (int) $app['middleware_credits_limit'] )
				: null;
			$revealed  = ! empty( $app['middleware_credits_revealed'] );

			// Show only after first successful generate AND when middleware gave us a remaining count.
			$show_limit = $revealed && null !== $remaining;
			$exhausted  = $show_limit && $remaining < 1;

			// Sites that revealed credits before HFCF7-546 may lack a period anchor.
			if (
				$revealed
				&& empty( $app['middleware_credits_period_started'] )
				&& empty( $app['middleware_credits_reset_at'] )
			) {
				self::ensure_period_started();
				$app = self::get_app_settings_row();
			}

			$reset_at = self::resolve_reset_at( $app );
			$message  = ! empty( $app['middleware_credits_message'] ) && is_string( $app['middleware_credits_message'] )
				? $app['middleware_credits_message']
				: '';

			return array(
				'limit'        => $limit,
				'remaining'    => $remaining,
				'show_limit'   => $show_limit,
				'exhausted'    => $exhausted,
				// Free plugin: Upgrade is Pro-only (HFCF7-546).
				'show_upgrade' => false,
				'reset_at'     => $reset_at,
				'reset_date'   => $reset_at ? self::format_reset_date( $reset_at ) : '',
				// Exhausted copy comes from middleware (not a local hardcoded string).
				'message'      => $message,
				'source'       => 'middleware',
			);
		}

		/**
		 * Persist a free-quota period start when missing (display reset date for Free).
		 */
		private static function ensure_period_started() {
			$all = get_option( 'cf7apps_settings', array() );
			if ( ! is_array( $all ) ) {
				$all = array();
			}
			if ( ! isset( $all['cf7-ai-form-generator'] ) || ! is_array( $all['cf7-ai-form-generator'] ) ) {
				$all['cf7-ai-form-generator'] = array();
			}
			if ( empty( $all['cf7-ai-form-generator']['middleware_credits_period_started'] ) ) {
				$all['cf7-ai-form-generator']['middleware_credits_period_started'] = time();
				update_option( 'cf7apps_settings', $all, false );
			}
		}

		/**
		 * Resolve the next free-quota reset timestamp.
		 *
		 * Prefers an explicit middleware value; otherwise period start + 1 calendar month
		 * (matches middleware "Next Free Renewal").
		 *
		 * @param array $app App settings row.
		 * @return int|null Unix timestamp or null.
		 */
		private static function resolve_reset_at( $app ) {
			if ( ! is_array( $app ) ) {
				return null;
			}

			if ( ! empty( $app['middleware_credits_reset_at'] ) && is_numeric( $app['middleware_credits_reset_at'] ) ) {
				$reset_at = (int) $app['middleware_credits_reset_at'];
				if ( $reset_at > 0 ) {
					return $reset_at;
				}
			}

			if ( ! empty( $app['middleware_credits_period_started'] ) && is_numeric( $app['middleware_credits_period_started'] ) ) {
				return self::compute_reset_from_period_start( (int) $app['middleware_credits_period_started'] );
			}

			return null;
		}

		/**
		 * Compute next free renewal from a period start (middleware calendar-month rule).
		 *
		 * @param int $started Period start unix timestamp.
		 * @return int|null
		 */
		public static function compute_reset_from_period_start( $started ) {
			$started = (int) $started;
			if ( $started < 1 ) {
				return null;
			}

			// Middleware "Next Free Renewal" is the same calendar day next month.
			$reset = strtotime( '+1 month', $started );
			if ( false !== $reset && $reset > 0 ) {
				return (int) $reset;
			}

			return $started + ( self::FREE_CREDITS_RESET_DAYS * DAY_IN_SECONDS );
		}

		/**
		 * Localized date for the free-quota reset.
		 *
		 * @param int $timestamp Unix timestamp.
		 * @return string
		 */
		private static function format_reset_date( $timestamp ) {
			$timestamp = (int) $timestamp;
			if ( $timestamp < 1 ) {
				return '';
			}

			return date_i18n( get_option( 'date_format' ), $timestamp );
		}

		/**
		 * Parse middleware reset timestamps (unix / ISO / date string).
		 *
		 * @param mixed $value Raw value from API.
		 * @return int|null
		 */
		public static function parse_reset_timestamp( $value ) {
			if ( null === $value || '' === $value || false === $value ) {
				return null;
			}

			if ( is_numeric( $value ) ) {
				$ts = (int) $value;
				// Milliseconds heuristic.
				if ( $ts > 20000000000 ) {
					$ts = (int) floor( $ts / 1000 );
				}
				return $ts > 0 ? $ts : null;
			}

			if ( ! is_string( $value ) ) {
				return null;
			}

			$parsed = strtotime( $value );
			return ( false !== $parsed && $parsed > 0 ) ? $parsed : null;
		}

		/**
		 * Persist middleware credit values and optionally reveal the usage bar.
		 *
		 * @param int|null    $remaining      Remaining credits from middleware.
		 * @param int|null    $limit          Total/free credit limit from middleware.
		 * @param bool        $reveal         Whether to reveal the usage bar (after first success).
		 * @param int|null    $reset_at       Optional middleware reset timestamp.
		 * @param string|null $message        Optional middleware status/exhausted message.
		 * @param int|null    $period_started Optional middleware period start (license started_at).
		 * @return array
		 */
		public static function store_middleware_usage( $remaining = null, $limit = null, $reveal = false, $reset_at = null, $message = null, $period_started = null ) {
			$all = get_option( 'cf7apps_settings', array() );
			if ( ! is_array( $all ) ) {
				$all = array();
			}
			if ( ! isset( $all['cf7-ai-form-generator'] ) || ! is_array( $all['cf7-ai-form-generator'] ) ) {
				$all['cf7-ai-form-generator'] = array();
			}

			$row = &$all['cf7-ai-form-generator'];

			if ( null !== $remaining && is_numeric( $remaining ) ) {
				$row['middleware_credits_remaining'] = max( 0, (int) $remaining );
			}
			if ( null !== $limit && is_numeric( $limit ) && (int) $limit > 0 ) {
				$row['middleware_credits_limit'] = max( 0, (int) $limit );
			}
			if ( $reveal ) {
				$row['middleware_credits_revealed'] = true;
			}

			$parsed_started = self::parse_reset_timestamp( $period_started );
			if ( null !== $parsed_started ) {
				// Prefer middleware license start over a local first-generate timestamp.
				$row['middleware_credits_period_started'] = $parsed_started;
			}

			$parsed_reset = self::parse_reset_timestamp( $reset_at );
			if ( null !== $parsed_reset ) {
				$row['middleware_credits_reset_at'] = $parsed_reset;
			} elseif ( null !== $parsed_started ) {
				// Recompute from authoritative middleware start when reset was omitted.
				$computed = self::compute_reset_from_period_start( $parsed_started );
				if ( null !== $computed ) {
					$row['middleware_credits_reset_at'] = $computed;
				}
			}

			if ( is_string( $message ) && '' !== trim( $message ) ) {
				$row['middleware_credits_message'] = sanitize_text_field( $message );
			} elseif (
				null !== $remaining
				&& is_numeric( $remaining )
				&& (int) $remaining > 0
			) {
				// Clear exhausted copy once credits are available again.
				unset( $row['middleware_credits_message'] );
			}

			// Anchor the free window only when middleware did not supply a start.
			if (
				( $reveal || ( null !== $remaining && is_numeric( $remaining ) ) )
				&& empty( $row['middleware_credits_period_started'] )
			) {
				$row['middleware_credits_period_started'] = time();
			}

			// If credits are restored after a prior exhaust, start a fresh local window when middleware
			// did not supply an explicit reset timestamp.
			if (
				null !== $remaining
				&& is_numeric( $remaining )
				&& (int) $remaining > 0
				&& empty( $parsed_reset )
				&& empty( $parsed_started )
				&& ! empty( $row['middleware_credits_period_started'] )
			) {
				$period_end = self::compute_reset_from_period_start( (int) $row['middleware_credits_period_started'] );
				if ( null !== $period_end && time() >= $period_end ) {
					$row['middleware_credits_period_started'] = time();
					unset( $row['middleware_credits_reset_at'] );
				}
			}

			// Drop any previous local-only counter so UI cannot fall back to it.
			unset( $row['successful_generations'] );

			update_option( 'cf7apps_settings', $all, false );

			return self::get_usage_state();
		}

		/**
		 * Mark credits exhausted using middleware signal (HTTP 402).
		 *
		 * @param int|null    $limit          Optional total from middleware error body.
		 * @param int|null    $reset_at       Optional middleware reset timestamp.
		 * @param string|null $message        Optional middleware exhausted message.
		 * @param int|null    $period_started Optional middleware period start.
		 * @return array
		 */
		public static function mark_middleware_exhausted( $limit = null, $reset_at = null, $message = null, $period_started = null ) {
			return self::store_middleware_usage( 0, $limit, true, $reset_at, $message, $period_started );
		}

		/**
		 * @return array
		 */
		private static function get_app_settings_row() {
			$all = get_option( 'cf7apps_settings', array() );
			if ( ! is_array( $all ) ) {
				return array();
			}

			return isset( $all['cf7-ai-form-generator'] ) && is_array( $all['cf7-ai-form-generator'] )
				? $all['cf7-ai-form-generator']
				: array();
		}

		/**
		 * Pull live credits from middleware and refresh the local usage snapshot.
		 *
		 * Fixes stale exhausted UI after middleware limit/remaining is increased.
		 *
		 * @param bool $reveal_if_known Keep the usage bar visible after sync when it was already revealed,
		 *                              or when middleware returns a remaining count.
		 * @return array Usage state (cached local state when the request fails).
		 */
		public static function sync_usage_from_middleware( $reveal_if_known = true ) {
			$credits = CF7Apps_Cf7Ai_Client::fetch_license_credits();
			if ( is_wp_error( $credits ) ) {
				$error_data = $credits->get_error_data();
				if ( 'no_middleware_credits' === $credits->get_error_code() ) {
					$limit          = is_array( $error_data ) ? ( $error_data['credits_limit'] ?? null ) : null;
					$reset_at       = is_array( $error_data ) ? ( $error_data['credits_reset_at'] ?? null ) : null;
					$period_started = is_array( $error_data ) ? ( $error_data['credits_period_started'] ?? null ) : null;
					$message        = is_array( $error_data ) && ! empty( $error_data['credits_message'] )
						? $error_data['credits_message']
						: $credits->get_error_message();
					return self::mark_middleware_exhausted( $limit, $reset_at, $message, $period_started );
				}

				return self::get_usage_state();
			}

			$app      = self::get_app_settings_row();
			$revealed = $reveal_if_known && ! empty( $app['middleware_credits_revealed'] );

			return self::store_middleware_usage(
				$credits['remaining'] ?? null,
				$credits['limit'] ?? null,
				$revealed,
				$credits['reset_at'] ?? null,
				$credits['message'] ?? null,
				$credits['period_started'] ?? null
			);
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
			add_action( 'wp_ajax_cf7_ai_refresh_usage', array( $this, 'ajax_cf7_ai_refresh_usage' ) );
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

			// Re-sync stale local exhausted state with live middleware credits.
			$app_row = self::get_app_settings_row();
			$usage   = ! empty( $app_row['middleware_credits_revealed'] )
				? self::sync_usage_from_middleware( true )
				: self::get_usage_state();

			wp_localize_script(
				'cf7apps-ai-form-generator',
				'cf7AiFormGen',
				array(
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'cf7_ai_form_generator' ),
					'assetsUrl'  => trailingslashit( plugin_dir_url( __FILE__ ) . 'assets/images' ),
					'upgradeUrl' => 'https://cf7apps.com/pricing/',
					'usage'      => $usage,
					'i18n'       => array(
						'button'            => __( 'Generate Form with AI', 'cf7apps' ),
						'modalTitle'        => __( 'Generate Form with AI', 'cf7apps' ),
						'promptLabel'       => __( 'Describe the form you want to create', 'cf7apps' ),
						'promptPlaceholder' => __( 'e.g., Create a customer feedback form with rating scale, comments section, and contact information fields...', 'cf7apps' ),
						'generate'          => __( 'Generate Form with AI', 'cf7apps' ),
						'generating'        => __( 'Generating...', 'cf7apps' ),
						'outputTitle'       => __( 'Generated form code', 'cf7apps' ),
						'copy'              => __( 'Copy', 'cf7apps' ),
						'insert'            => __( 'Insert', 'cf7apps' ),
						'refresh'           => __( 'Reset', 'cf7apps' ),
						'validationError'   => __( 'This field is required.', 'cf7apps' ),
						'copied'            => __( 'Copied to clipboard.', 'cf7apps' ),
						'copyFailed'        => __( 'Could not copy.', 'cf7apps' ),
						'close'             => __( 'Close', 'cf7apps' ),
						/* translators: 1: remaining count from middleware, 2: total limit from middleware */
						'creditsRemaining'      => __( 'You have %1$d of %2$d AI form generations remaining.', 'cf7apps' ),
						/* translators: %d: remaining count from middleware */
						'creditsRemainingOnly'  => __( 'You have %d AI form generations remaining.', 'cf7apps' ),
						/* translators: %s: localized reset date */
						'creditsResetOn'        => __( 'Your limit will reset on %s.', 'cf7apps' ),
						'upgrade'               => __( 'Upgrade', 'cf7apps' ),
					),
					'templates' => $templates_for_js,
				)
			);
		}

		/**
		 * AJAX: refresh usage bar from live middleware credits.
		 */
		public function ajax_cf7_ai_refresh_usage() {
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

			wp_send_json_success(
				array(
					'usage' => self::sync_usage_from_middleware( true ),
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

			$form_result = CF7Apps_Cf7Ai_Client::cf7_tags_from_ai( $combined );
			if ( is_wp_error( $form_result ) ) {
				$payload = array(
					'message' => $form_result->get_error_message(),
				);
			if ( 'no_middleware_credits' === $form_result->get_error_code() ) {
					$error_data = $form_result->get_error_data();
					$limit      = is_array( $error_data ) && isset( $error_data['credits_limit'] )
						? $error_data['credits_limit']
						: null;
					$reset_at   = is_array( $error_data ) && isset( $error_data['credits_reset_at'] )
						? $error_data['credits_reset_at']
						: null;
					$period_started = is_array( $error_data ) && isset( $error_data['credits_period_started'] )
						? $error_data['credits_period_started']
						: null;
					$mw_message = is_array( $error_data ) && ! empty( $error_data['credits_message'] )
						? $error_data['credits_message']
						: $form_result->get_error_message();
					$payload['code']  = 'no_middleware_credits';
					$payload['usage'] = self::mark_middleware_exhausted( $limit, $reset_at, $mw_message, $period_started );
				}
				wp_send_json_error( $payload, 400 );
			}
			
			$form_tags = is_array( $form_result ) ? ( $form_result['form_tags'] ?? '' ) : (string) $form_result;
			$remaining = is_array( $form_result ) && array_key_exists( 'credits_remaining', $form_result )
				? $form_result['credits_remaining']
				: null;
			$limit     = is_array( $form_result ) && array_key_exists( 'credits_limit', $form_result )
				? $form_result['credits_limit']
				: null;
			$reset_at  = is_array( $form_result ) && array_key_exists( 'credits_reset_at', $form_result )
				? $form_result['credits_reset_at']
				: null;
			$period_started = is_array( $form_result ) && array_key_exists( 'credits_period_started', $form_result )
				? $form_result['credits_period_started']
				: null;
			$message   = is_array( $form_result ) && ! empty( $form_result['credits_message'] )
				? $form_result['credits_message']
				: null;

			// Reveal after first success; values come only from middleware (no local counter).
			$usage = self::store_middleware_usage( $remaining, $limit, true, $reset_at, $message, $period_started );

			wp_send_json_success(
				array(
					'form_tags'     => $form_tags,
					'credit_source' => 'middleware',
					'usage'         => $usage,
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
