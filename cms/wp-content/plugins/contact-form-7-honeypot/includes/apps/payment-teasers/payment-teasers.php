<?php
/**
 * Payment gateway PRO teaser cards for the CF7 Apps dashboard.
 *
 * @package CF7Apps
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CF7Apps_Payment_Pro_Teaser' ) && class_exists( 'CF7Apps_App' ) ) :

	/**
	 * Base class for payment PRO teaser apps shown when Pro is inactive.
	 */
	abstract class CF7Apps_Payment_Pro_Teaser extends CF7Apps_App {

		/**
		 * @var string
		 */
		public $upgrade_url = 'https://cf7apps.com/pricing/';

		/**
		 * @var string
		 */
		public $title_image = '';

		/**
		 * @return array
		 */
		public function get_settings() {
			$settings                    = parent::get_settings();
			$settings['is_pro_teaser']   = true;
			$settings['is_enabled']      = false;
			$settings['has_admin_settings'] = false;
			$settings['upgrade_url']     = $this->upgrade_url;

			if ( $this->title_image ) {
				$settings['title_image'] = $this->title_image;
			}

			return $settings;
		}
	}

	class CF7Apps_Stripe_Teaser extends CF7Apps_Payment_Pro_Teaser {

		public function __construct() {
			$this->id                 = 'stripe';
			$this->priority           = 20;
			$this->title              = __( 'Stripe', 'cf7apps' );
			$this->description        = __( 'Accept payments in Contact Form 7 using Stripe, including one-time and subscription payments.', 'cf7apps' );
			$this->is_pro             = true;
			$this->parent_menu        = 'payment';
			$this->documentation_url  = 'https://cf7apps.com/docs/payment/stripe/';
		}
	}

	class CF7Apps_PayPal_Teaser extends CF7Apps_Payment_Pro_Teaser {

		public function __construct() {
			$this->id                 = 'paypal';
			$this->priority           = 21;
			$this->title              = __( 'PayPal', 'cf7apps' );
			$this->description        = __( 'Accept payments in Contact Form 7 using PayPal, including one-time and subscription payments.', 'cf7apps' );
			$this->is_pro             = true;
			$this->parent_menu        = 'payment';
			$this->documentation_url  = 'https://cf7apps.com/docs/payment/paypal/';
		}
	}

	class CF7Apps_Square_Teaser extends CF7Apps_Payment_Pro_Teaser {

		public function __construct() {
			$this->id                 = 'square';
			$this->priority           = 22;
			$this->title              = __( 'Square', 'cf7apps' );
			$this->description        = __( 'Accept payments in Contact Form 7 using Square, including one-time and subscription payments.', 'cf7apps' );
			$this->is_pro             = true;
			$this->parent_menu        = 'payment';
			$this->documentation_url  = 'https://cf7apps.com/docs/payment/square/';
		}
	}

	/**
	 * Register payment PRO teaser cards when Pro is not active.
	 *
	 * @param array $apps Registered app class names.
	 * @return array
	 */
	function cf7apps_register_payment_pro_teasers( $apps ) {
		// Free-only release (HFCF7-563): do not surface Payment Pro teasers in the dashboard.
		return $apps;
	}

	add_filter( 'cf7apps_apps', 'cf7apps_register_payment_pro_teasers', 100 );

endif;
