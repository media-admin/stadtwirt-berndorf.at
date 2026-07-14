<?php
/**
 * FF7 Apps entries
 *
 * @since 3.1.0
 * @package Contact Form 7 Apps
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CF7Apps_Entries_App' ) && class_exists( 'CF7Apps_App' ) ) :
	/**
	 * CF7Apps_Entries_App class
	 *
	 * @since 3.1.0
	 */
	class CF7Apps_Entries_App extends CF7Apps_App {
		/**
		 * CF7Apps_Entries_App constructor.
		 *
		 * @since 3.1.0
		 */
		public function __construct() {
			$this->id                 = 'cf7-entries';
			$this->priority           = -1;
			$this->title              = __( 'Entries', 'cf7apps' );
			$this->description        = __( 'Access and manage all Contact Form 7 submissions in a centralized database with filtering and export options.', 'cf7apps' );
			$this->icon               = plugin_dir_url( __FILE__ ) . 'assets/images/logo.svg';
			$this->has_admin_settings = true;
			$this->is_pro             = false;
			$this->by_default_enabled = false;
			$this->documentation_url  = 'https://cf7apps.com/docs/general/entries';
			$this->parent_menu        = __( 'General', 'cf7apps' );
			$this->setting_tabs       = array(
				'general' => __( 'General', 'cf7apps' ),
				'entries' => __( 'Entries', 'cf7apps' ),
			);

			include_once plugin_dir_path( __FILE__ ) . 'includes/cf7-form-entries.php';
			add_action( 'admin_init', array( $this, 'create_table' ) );
			add_action( 'wpcf7_mail_sent', array( $this, 'save_form_information' ), 10, 1 );
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			// Automatically remove stored entries when a Contact Form 7 form is permanently deleted.
			add_action( 'before_delete_post', array( $this, 'delete_entries_on_form_delete' ) );
		}

		/**
		 * Create the database table for storing form entries.
		 *
		 * @since 3.1.0
		 */
		public function create_table() {
			// Only attempt creation for admins when the Entries app is enabled.
			if ( ! current_user_can( 'manage_options' ) || ! $this->get_option( 'is_enabled' ) ) {
				return;
			}

			global $wpdb;

			$table_name       = $wpdb->prefix . 'cf7apps_form_entries';
			$charset_collate  = $wpdb->get_charset_collate();
			$installed_version = get_option( 'cf7_entries_version' );
			$required_version  = '1.0.0';

			// Check if the table actually exists in the database.
			$table_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SHOW TABLES LIKE %s',
					$table_name
				)
			);

			// (Re)create the table when:
			// - the stored version differs from the required version, OR
			// - the table is missing (e.g. after a manual drop).
			if ( $installed_version !== $required_version || $table_exists !== $table_name ) {
				$sql = "CREATE TABLE $table_name (
					id INT AUTO_INCREMENT,
					form_id INT NOT NULL,
					form_name VARCHAR(155) NOT NULL,
					email VARCHAR(155) DEFAULT NULL,
					date_time VARCHAR(155) NOT NULL,
					data LONGTEXT NOT NULL,
					PRIMARY KEY  (id)
				) $charset_collate;";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );

				update_option( 'cf7_entries_version', $required_version );
			}
		}

		/**
		 * Save form information when a form is submitted.
		 *
		 * @since 3.1.0
		 * @param WPCF7_ContactForm $form The contact form object.
		 */
		public function save_form_information( $form ) {
			if ( ! $this->get_option( 'is_enabled' ) ) {
				return;
			}

			$submission = WPCF7_Submission::get_instance();

			if ( ! $submission ) { return; }

			$data = array();

			$posted_data = $submission->get_posted_data();
			$form_tags   = $form->scan_form_tags();

			foreach ( $form_tags as $form_tag ) {
				$field_name = is_object( $form_tag ) ? $form_tag->name : ( $form_tag['name'] ?? '' );

				if ( empty( $field_name ) ) {
					continue;
				}

				$do_not_store = $form->scan_form_tags(
					array(
						'name'    => $field_name,
						'feature' => 'do-not-store',
					)
				);

				if ( ! empty( $do_not_store ) || ! array_key_exists( $field_name, $posted_data ) ) {
					continue;
				}

				$data[ $field_name ] = $posted_data[ $field_name ];
			}

			$entry = new CF7Apps_Form_Entries();

			$entry->form_id = $form->id();

			// Use the latest form title from the database to avoid stale names like "Untitled".
			$form_title = get_the_title( $form->id() );
			$entry->form_name = $form_title ? $form_title : $form->name();
			$entry->email     = $this->find_submission_email( $form_tags, $posted_data );
			$entry->date_time = current_time( 'timestamp' );
			$entry->data      = $data;

			$entry->save();
		}

		/**
		 * Find the first valid email from a Contact Form 7 submission.
		 *
		 * @since 3.5.0
		 *
		 * @param array $form_tags   Form tag definitions.
		 * @param array $posted_data Submitted form data.
		 * @return string
		 */
		private function find_submission_email( $form_tags, $posted_data ) {
			// Prefer explicit CF7 email fields first.
			foreach ( $form_tags as $form_tag ) {
				if ( empty( $form_tag['name'] ) ) {
					continue;
				}

				$is_email_tag = ( isset( $form_tag['basetype'] ) && 'email' === $form_tag['basetype'] ) ||
					( isset( $form_tag['type'] ) && 0 === strpos( $form_tag['type'], 'email' ) );

				if ( ! $is_email_tag || ! isset( $posted_data[ $form_tag['name'] ] ) ) {
					continue;
				}

				$email_value = $posted_data[ $form_tag['name'] ];
				if ( is_array( $email_value ) ) {
					$email_value = reset( $email_value );
				}

				if ( is_scalar( $email_value ) ) {
					$email_value = trim( (string) $email_value );
					if ( is_email( $email_value ) ) {
						return sanitize_email( $email_value );
					}
				}
			}

			// Fallback: pick the first valid email from any submitted value.
			foreach ( $posted_data as $posted_value ) {
				if ( is_array( $posted_value ) ) {
					$posted_value = reset( $posted_value );
				}

				if ( is_scalar( $posted_value ) ) {
					$posted_value = trim( (string) $posted_value );
					if ( is_email( $posted_value ) ) {
						return sanitize_email( $posted_value );
					}
				}
			}

			return '';
		}

		/**
		 * Add admin menu item for entries.
		 *
		 * @since 3.1.0
		 */
		public function add_admin_menu() {
			if ( $this->get_option( 'is_enabled' ) ) {
				$rand = rand(1000, 9999);
				add_submenu_page( 'wpcf7', __( 'Entries', 'cf7apps' ), __( 'Entries', 'cf7apps' ), 'manage_options', 'admin.php?page=cf7apps&tab=entries' . $rand . '#/settings/cf7-entries/2', null, 3 );
			}
		}

		/**
		 * Delete all saved entries when a Contact Form 7 form is permanently deleted.
		 *
		 * Runs on the core `before_delete_post` hook.
		 *
		 * @since 3.4.0
		 *
		 * @param int $post_id Post ID being deleted.
		 * @return void
		 */
		public function delete_entries_on_form_delete( $post_id ) {
			// Only act for Contact Form 7 forms.
			if ( get_post_type( $post_id ) !== 'wpcf7_contact_form' ) {
				return;
			}

			// Only run if Entries app is enabled.
			if ( ! $this->get_option( 'is_enabled' ) ) {
				return;
			}

			if ( ! class_exists( 'CF7Apps_Form_Entries' ) ) {
				return;
			}

			CF7Apps_Form_Entries::delete_entries_by_form_id( $post_id );
		}

		/**
		 * Get the app settings.
		 *
		 * @since 3.1.0
		 * @return array
		 */
		public function admin_settings() {
			return array(
				'general' => array(
					'fields' => array(
						'general' => array(
							'title'       => __( 'Entries Settings', 'cf7apps' ),
							'description' => __( '', 'cf7apps' ),

                            'notice'           => array(
                                'type'  => 'notice',
                                'class' => 'info',
								'text'  => sprintf(
									__( 'Stuck? Check our Documentation on %s', 'cf7apps' ),
									'<a href="https://cf7apps.com/docs/general/entries" target="_blank" rel="noopener noreferrer"><u>' . __( 'Entries', 'cf7apps' ) . '</u></a>'
								),
                            ),

							'is_enabled'  => array(
								'title' 	  => __( 'Show Entries', 'cf7apps' ),
								'type'        => 'checkbox',
								'default'     => false,
							),

							'save_settings' => array(
								'type'  => 'save_button',
								'text'  => __( 'Save Settings', 'cf7apps' ),
								'class' => 'button-primary',
							),
						),
						'entries' => array(
							'template' => 'cf7Entries',
						),
					),
				),
			);
		}
	}

	if ( ! function_exists( 'cf7apps_register_cf7entries' ) ) :
		/**
		 * Register the CF7 entries app
		 *
		 * @since 3.1.0
		 * @param array $apps List of registered apps.
		 *
		 * @return array
		 */
		function cf7apps_register_cf7entries( $apps ) {
			$apps[] = 'CF7Apps_Entries_App';
			return $apps;
		}
	endif;

	add_filter( 'cf7apps_apps', 'cf7apps_register_cf7entries' );
endif;
