<?php
/**
 * CF7 Integration Class
 * Handles integration between ACF CF7Apps fields and Contact Form 7
 *
 * @since 3.2.1
 * @package Contact Form 7 Apps
 */

if ( ! class_exists( 'CF7Apps_ACF_Integration' ) ) {
	class CF7Apps_ACF_Integration {

		/**
		 * Instance of this class
		 *
		 * @since 3.2.1
		 * @var CF7Apps_ACF_Integration
		 */
		private static $instance = null;

		/**
		 * Track if form tag generator has been registered
		 *
		 * @since 3.2.1
		 * @var bool
		 */
		private static $tag_generator_registered = false;

		/**
		 * Get instance of this class
		 *
		 * @since 3.2.1
		 * @return CF7Apps_ACF_Integration
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 3.2.1
		 */
		private function __construct() {
			// Register CF7 form tags immediately if CF7 is loaded
			if ( function_exists( 'wpcf7_add_form_tag' ) ) {
				$this->register_form_tags();
			}

			// Register CF7 form tags - must be early (both admin and frontend)
			add_action( 'wpcf7_init', array( $this, 'register_form_tags' ), 5 );
			add_action( 'wpcf7_contact_form', array( $this, 'register_form_tags' ), 5 );
			
			// Register on plugins_loaded to ensure it's available early
			add_action( 'plugins_loaded', array( $this, 'register_form_tags' ), 5 );

			// Also register on init as fallback (for both admin and frontend)
			add_action( 'init', array( $this, 'register_form_tags' ), 10 );
			add_action( 'template_redirect', array( $this, 'register_form_tags' ), 5 );

			// Add CF7 form tag generator - use same priority as other CF7 Apps (10) to ensure inline display
			add_action( 'wpcf7_admin_init', array( $this, 'add_form_tag_generator' ), 10 );
			add_action( 'admin_init', array( $this, 'add_form_tag_generator' ), 10 );

			// Add validation
			add_filter( 'wpcf7_validate_acf_field', array( $this, 'validate_field' ), 10, 2 );

			// Ensure tag is recognized during form scanning
			add_filter( 'wpcf7_contact_form_properties', array( $this, 'ensure_tag_registration' ), 10, 2 );

			// Add admin scripts - use multiple hooks to ensure it loads
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 20 );

			// Add inline script for tag generator
			add_action( 'admin_footer', array( $this, 'add_tag_generator_script' ), 20 );
		}

		/**
		 * Add form tag generator to CF7 admin
		 *
		 * @since 3.2.1
		 */
		public function add_form_tag_generator() {
			// Prevent duplicate registration
			if ( self::$tag_generator_registered ) {
				return;
			}

			// Check if CF7 is loaded
			if ( ! defined( 'WPCF7_VERSION' ) ) {
				return;
			}

			// Check if ACF integration is enabled
			$cf7apps_settings = get_option( 'cf7apps_settings' );
			if ( ! $cf7apps_settings || empty( $cf7apps_settings['acf-integration']['is_enabled'] ) || ! $cf7apps_settings['acf-integration']['is_enabled'] ) {
				return;
			}

			// Check if ACF is available
			if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf_get_field' ) ) {
				return;
			}

			// Use the newer WPCF7_TagGenerator API (like Honeypot)
			if ( class_exists( 'WPCF7_TagGenerator' ) ) {
				$tag_generator = WPCF7_TagGenerator::get_instance();
				if ( method_exists( $tag_generator, 'add' ) ) {
					$tag_generator->add(
						'acf_field',
						__( 'ACF Field', 'cf7apps' ),
						array( $this, 'form_tag_generator' ),
						array( 'version' => 2 )
					);
					self::$tag_generator_registered = true;
					return;
				}
			}

			// Fallback to older API
			if ( function_exists( 'wpcf7_add_form_tag_generator' ) ) {
				wpcf7_add_form_tag_generator(
					'acf_field',
					__( 'ACF Field', 'cf7apps' ),
					array( $this, 'form_tag_generator' ),
					array( 'acf-field' )
				);
				self::$tag_generator_registered = true;
			}
		}

		/**
		 * Form tag generator callback
		 *
		 * @since 3.2.1
		 * @param WPCF7_ContactForm $contact_form Contact form object.
		 * @param array              $args         Arguments.
		 */
		public function form_tag_generator( $contact_form, $args = '' ) {
			$args    = wp_parse_args( $args, array() );
			$type    = 'acf_field';
			$id_attr = isset( $args['content'] ) ? $args['content'] : 'tag-generator-panel-acf_field';

			?>
			<header class="description-box">
				<h3><?php echo esc_html( __( 'ACF Field', 'cf7apps' ) ); ?></h3>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							__( 'Create form-tags using ACF fields to automatically populate and manage dynamic form data. Visit the <a href="%s">ACF Integration settings.</a>', 'cf7apps' ),
							esc_url( admin_url( 'admin.php?page=cf7apps#/settings/acf-integration' ) )
						)
					);
					?>
				</p>
			</header>

			<div class="control-box" id="<?php echo esc_attr( $id_attr ); ?>">
				<table class="form-table">
					<tbody>
						<th scope="row" colspan="2">
								<div style="border-left: 4px solid #3399ff; background: #e6f4ff; padding: 1px 14px; margin-bottom: 10px; border-radius: 5px;">
									<p style="margin: 7px auto;"><?php 
										printf( 
											'%s <a href="%s" target="_blank">%s</a>',
											esc_html__( 'Need help setting this up? Check out our', 'cf7apps' ),
											esc_url( 'https://cf7apps.com/docs/integrations/advanced-custom-field/' ),
											esc_html__( 'Documentation', 'cf7apps' )
										); 
									?></p>
								</div>
							</th>
						</tr>
						<tr>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $id_attr . '-name' ); ?>">
									<?php echo esc_html( __( 'Field name', 'contact-form-7' ) ); ?>
									<span class="required" style="color: #dc3232;">*</span>
								</label>
							</th>
							<td>
								<input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $id_attr . '-name' ); ?>" required />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $id_attr . '-acf-field' ); ?>">
									<?php echo esc_html( __( 'ACF Field', 'cf7apps' ) ); ?>
									<span class="required" style="color: #dc3232;">*</span>
								</label>
							</th>
							<td>
								<select name="acf-field" class="tg-name oneline" id="<?php echo esc_attr( $id_attr . '-acf-field' ); ?>" required>
									<option value=""><?php echo esc_html( __( '-- Select ACF Field (Required) --', 'cf7apps' ) ); ?></option>
									<?php echo $this->get_acf_fields_options(); ?>
								</select>
								<p class="description acf-field-warning" id="<?php echo esc_attr( $id_attr . '-acf-field-warning' ); ?>" style="color: #dc3232; font-weight: bold; margin-top: 8px;">
									<?php echo esc_html( __( '⚠️ Please select an ACF field before inserting the tag.', 'cf7apps' ) ); ?>
								</p>
								<p class="description" style="margin-top: 8px;">
									<?php echo esc_html( __( 'The field key will be automatically added when you select a field above.', 'cf7apps' ) ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $id_attr . '-field-key-display' ); ?>">
									<?php echo esc_html( __( 'Field Key', 'cf7apps' ) ); ?>
								</label>
							</th>
							<td>
								<input type="text" id="<?php echo esc_attr( $id_attr . '-field-key-display' ); ?>" class="large-text" readonly="readonly" placeholder="<?php echo esc_attr( __( 'Select a field above to see the key', 'cf7apps' ) ); ?>" />
								<p class="description">
									<?php echo esc_html( __( 'This field key is automatically populated when you select an ACF field above.', 'cf7apps' ) ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<footer class="insert-box">
				<div style="display: flex; gap: 10px; align-items: center;">
					<input type="text" name="<?php echo esc_attr( $type ); ?>" class="tag code" readonly="readonly" onfocus="this.select()" id="<?php echo esc_attr( $id_attr . '-tag-input' ); ?>" style="flex: 1; min-width: 88%;" />
					<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" style="flex-shrink: 0;" />
				</div>
				<br class="clear" />
			</footer>
			<?php
		}

		/**
		 * Get ACF fields options for dropdown
		 *
		 * @since 3.2.1
		 * @return string
		 */
		private function get_acf_fields_options() {
			$options = '';

			if ( ! function_exists( 'acf_get_field_groups' ) ) {
				return $options;
			}

			$field_groups = acf_get_field_groups();

			foreach ( $field_groups as $group ) {
				$fields = acf_get_fields( $group );

				if ( $fields ) {
						foreach ( $fields as $field ) {
							// Only show CF7Apps field types
							if ( in_array( $field['type'], array( 'cf7apps_text', 'cf7apps_number', 'cf7apps_url', 'cf7apps_email', 'cf7apps_textarea' ), true ) ) {
								$field_label      = $field['label'] ? $field['label'] : $field['name'];
								$group_title     = isset( $group['title'] ) ? $group['title'] : __( 'Field Group', 'cf7apps' );
								$field_type_label = str_replace( 'cf7apps_', '', $field['type'] );
								$field_type_label = ucfirst( $field_type_label );
								
								// Check if ACF field is required
								$is_acf_required = isset( $field['required'] ) && $field['required'] ? '1' : '0';

								// Show: Field Label (Type) - Group Name [Field Key]
								$options .= sprintf(
									'<option value="%s" data-field-key="%s" data-required="%s">%s (%s) - %s [%s]</option>',
									esc_attr( $field['key'] ),
									esc_attr( $field['key'] ),
									esc_attr( $is_acf_required ),
									esc_html( $field_label ),
									esc_html( $field_type_label ),
									esc_html( $group_title ),
									esc_html( $field['key'] )
								);
							}
						}
				}
			}

			return $options;
		}

		/**
		 * Register CF7 form tags
		 *
		 * @since 3.2.1
		 */
		public function register_form_tags() {
			if ( ! function_exists( 'wpcf7_add_form_tag' ) ) {
				return;
			}

			// Register the tag - ensure it's only registered once
			static $registered = false;
			if ( $registered ) {
				return;
			}

			// Register the base tag
			wpcf7_add_form_tag(
				'acf_field',
				array( $this, 'form_tag_handler' ),
				array(
					'name-attr'         => true,
					'selectable-values' => true,
				)
			);

			// CF7 automatically handles acf_field* as required version, but ensure it's recognized
			// The asterisk is handled automatically by CF7's parser, so we don't need to register it separately

			$registered = true;
		}

		/**
		 * Ensure tag registration when form properties are loaded
		 *
		 * @since 3.2.1
		 * @param array              $properties    Form properties.
		 * @param WPCF7_ContactForm $contact_form Contact form object.
		 * @return array
		 */
		public function ensure_tag_registration( $properties, $contact_form ) {
			$this->register_form_tags();
			return $properties;
		}

		/**
		 * Form tag handler
		 *
		 * @since 3.2.1
		 * @param array|WPCF7_FormTag $tag Form tag.
		 * @return string
		 */
		public function form_tag_handler( $tag ) {
			// Convert to WPCF7_FormTag if it's an array
			if ( is_array( $tag ) ) {
				$tag = new WPCF7_FormTag( $tag );
			} elseif ( ! ( $tag instanceof WPCF7_FormTag ) ) {
				$tag = new WPCF7_FormTag( $tag );
			}


			if ( empty( $tag->name ) ) {
				return '<span class="wpcf7-form-control-wrap"><span class="wpcf7-not-valid-tip">' . esc_html__( 'ACF Field: Field name is missing.', 'cf7apps' ) . '</span></span>';
			}

			$validation_error = wpcf7_get_validation_error( $tag->name );

			$class = wpcf7_form_controls_class( $tag->type );

			// Add validation error class if there's an error
			if ( $validation_error ) {
				$class .= ' wpcf7-not-valid';
			}

			$atts              = array();
			$atts['class']     = $tag->get_class_option( $class );
			$atts['id']        = $tag->get_id_option();
			$atts['tabindex']  = $tag->get_option( 'tabindex', 'signed_int', true );

			// Get ACF field key from tag values or options
			$acf_field_key = '';

			// Method 1: Try to get from values first (quoted value in tag like [acf_field name "acf-field:field_key"])
			if ( ! empty( $tag->values ) ) {
				foreach ( $tag->values as $value ) {
					// Remove quotes if present
					$clean_value = trim( $value, '"\'' );

					// Check if it looks like a field key (starts with "field_")
					if ( strpos( $clean_value, 'field_' ) === 0 ) {
						$acf_field_key = $clean_value;
						break;
					}
					// Check for acf-field:field_XXX format in values
					if ( strpos( $clean_value, 'acf-field:' ) === 0 ) {
						$acf_field_key = trim( str_replace( 'acf-field:', '', $clean_value ), '"\'' );
						break;
					}
				}
			}

			// Method 2: Check all options for field key
			if ( empty( $acf_field_key ) && ! empty( $tag->options ) ) {
				foreach ( $tag->options as $option ) {
					// Check if option contains field key
					if ( strpos( $option, 'field_' ) === 0 ) {
						$acf_field_key = trim( $option, '"\':' );
						break;
					}
					// Check for acf-field:field_XXX format
					if ( strpos( $option, 'acf-field:' ) === 0 ) {
						$acf_field_key = trim( str_replace( 'acf-field:', '', $option ), '"\'' );
						break;
					}
					// Check for field:field_XXX format
					if ( strpos( $option, 'field:' ) === 0 ) {
						$acf_field_key = trim( str_replace( 'field:', '', $option ), '"\'' );
						break;
					}
				}
			}

			// Method 3: Try to get from 'acf-field' option
			if ( empty( $acf_field_key ) ) {
				$acf_field_key = $tag->get_option( 'acf-field', '', true );
				if ( $acf_field_key ) {
					$acf_field_key = trim( $acf_field_key, '"\':' );
				}
			}

			// Method 4: Try to get from 'field' option
			if ( empty( $acf_field_key ) ) {
				$acf_field_key = $tag->get_option( 'field', '', true );
				if ( $acf_field_key ) {
					$acf_field_key = trim( $acf_field_key, '"\':' );
				}
			}

			if ( empty( $acf_field_key ) ) {
				$error_msg  = __( 'ACF field key is missing from the tag.', 'cf7apps' );
				$error_msg .= '<br><strong>' . __( 'How to fix:', 'cf7apps' ) . '</strong>';
				$error_msg .= '<br>1. ' . __( 'Delete this tag from your form', 'cf7apps' );
				$error_msg .= '<br>2. ' . __( 'Click "Insert Tag" → "ACF Field"', 'cf7apps' );
				$error_msg .= '<br>3. ' . __( 'Enter field name and SELECT an ACF field from the dropdown', 'cf7apps' );
				$error_msg .= '<br>4. ' . __( 'The field key will be automatically added', 'cf7apps' );
				$error_msg .= '<br><br><strong>' . __( 'Current tag:', 'cf7apps' ) . '</strong> <code>' . esc_html( '[' . $tag->type . ( $tag->is_required() ? '*' : '' ) . ' ' . $tag->name . ']' ) . '</code>';
				$error_msg .= '<br><strong>' . __( 'Should be:', 'cf7apps' ) . '</strong> <code>[acf_field* ' . esc_html( $tag->name ) . ' acf-field:field_XXXXX]</code>';

				return '<div class="wpcf7-form-control-wrap ' . esc_attr( $tag->name ) . '" style="padding: 10px; border: 2px solid #dc3232; background: #fff; margin: 10px 0;">' . $error_msg . '</div>';
			}

			// Check if ACF is available
			if ( ! function_exists( 'acf_get_field' ) ) {
				return '<span class="wpcf7-form-control-wrap ' . esc_attr( $tag->name ) . '"><span class="wpcf7-not-valid-tip">' . esc_html__( 'Advanced Custom Fields plugin is required for this field to work.', 'cf7apps' ) . '</span></span>';
			}

			$acf_field = acf_get_field( $acf_field_key );

			if ( ! $acf_field ) {
				return '<span class="wpcf7-form-control-wrap ' . esc_attr( $tag->name ) . '"><span class="wpcf7-not-valid-tip">' . esc_html__( 'ACF field not found.', 'cf7apps' ) . '</span></span>';
			}

			// Get field value
			$value = '';
			if ( isset( $_POST[ $tag->name ] ) ) {
				$value = wp_unslash( $_POST[ $tag->name ] );
			} elseif ( isset( $acf_field['default_value'] ) && $acf_field['default_value'] ) {
				$value = $acf_field['default_value'];
			}

			// Build input based on field type
			$input = $this->build_field_input( $acf_field, $tag, $value, $atts );

			$html = sprintf(
				'<span class="wpcf7-form-control-wrap %1$s">%2$s%3$s</span>',
				esc_attr( $tag->name ),
				$input,
				$validation_error
			);

			return $html;
		}

		/**
		 * Build field input based on ACF field type
		 *
		 * @since 3.2.1
		 * @param array              $acf_field ACF field array.
		 * @param WPCF7_FormTag      $tag       CF7 form tag.
		 * @param string             $value     Field value.
		 * @param array              $atts      Attributes.
		 * @return string
		 */
		private function build_field_input( $acf_field, $tag, $value, $atts ) {
			$field_type = $acf_field['type'];
			$name       = $tag->name;
			
			// Check ACF field required status, not CF7 tag asterisk
			// ACF stores required as 1, '1', true, or 1 (int)
			$is_acf_required = false;
			if ( isset( $acf_field['required'] ) ) {
				$required_value = $acf_field['required'];
				$is_acf_required = ( $required_value === 1 || $required_value === '1' || $required_value === true || $required_value === 'true' );
			}
			$required = $is_acf_required ? ' required' : '';

			$atts['name']        = $name;
			$atts['value']       = $value;
			$atts['placeholder'] = isset( $acf_field['placeholder'] ) ? $acf_field['placeholder'] : '';

			if ( $is_acf_required ) {
				$atts['aria-required'] = 'true';
				$atts['required']     = 'required';
			}

			switch ( $field_type ) {
				case 'cf7apps_text':
					$atts['type'] = 'text';
					if ( isset( $acf_field['maxlength'] ) && $acf_field['maxlength'] ) {
						$atts['maxlength'] = $acf_field['maxlength'];
					}
					$input = '<input ' . wpcf7_format_atts( $atts ) . ' />';
					break;

				case 'cf7apps_number':
					$atts['type'] = 'number';
					if ( isset( $acf_field['min'] ) && $acf_field['min'] !== '' ) {
						$atts['min'] = $acf_field['min'];
					}
					if ( isset( $acf_field['max'] ) && $acf_field['max'] !== '' ) {
						$atts['max'] = $acf_field['max'];
					}
					if ( isset( $acf_field['step'] ) && $acf_field['step'] !== '' ) {
						$atts['step'] = $acf_field['step'];
					}
					$input = '<input ' . wpcf7_format_atts( $atts ) . ' />';
					break;

				case 'cf7apps_url':
					$atts['type'] = 'url';
					$input        = '<input ' . wpcf7_format_atts( $atts ) . ' />';
					break;

				case 'cf7apps_email':
					$atts['type'] = 'email';
					$input        = '<input ' . wpcf7_format_atts( $atts ) . ' />';
					break;

				case 'cf7apps_textarea':
					$rows         = isset( $acf_field['rows'] ) && $acf_field['rows'] ? $acf_field['rows'] : 8;
					$atts['rows'] = $rows;
					if ( isset( $acf_field['maxlength'] ) && $acf_field['maxlength'] ) {
						$atts['maxlength'] = $acf_field['maxlength'];
					}
					unset( $atts['value'] );
					$input = '<textarea ' . wpcf7_format_atts( $atts ) . '>' . esc_textarea( $value ) . '</textarea>';
					break;

				default:
					$input = '<input type="text" ' . wpcf7_format_atts( $atts ) . ' />';
			}

			return $input;
		}

		/**
		 * Validate field
		 *
		 * @since 3.2.1
		 * @param WPCF7_Validation $result Validation result.
		 * @param array|WPCF7_FormTag $tag Form tag.
		 * @return WPCF7_Validation
		 */
		public function validate_field( $result, $tag ) {
			$tag   = new WPCF7_FormTag( $tag );
			$name  = $tag->name;
			$value = isset( $_POST[ $name ] ) ? trim( wp_unslash( strtr( (string) $_POST[ $name ], "\n", " " ) ) ) : '';

			// Get ACF field for validation
			$acf_field_key = '';
			
			// Method 1: Try to get from values first (quoted value in tag like [acf_field name "acf-field:field_key"])
			if ( ! empty( $tag->values ) ) {
				foreach ( $tag->values as $value_item ) {
					$clean_value = trim( $value_item, '"\'' );
					
					// Check if it looks like a field key (starts with "field_")
					if ( strpos( $clean_value, 'field_' ) === 0 ) {
						$acf_field_key = $clean_value;
						break;
					}
					// Check for acf-field:field_XXX format in values
					if ( strpos( $clean_value, 'acf-field:' ) === 0 ) {
						$acf_field_key = trim( str_replace( 'acf-field:', '', $clean_value ), '"\'' );
						break;
					}
				}
			}

			// Method 2: Try options if not found in values
			if ( empty( $acf_field_key ) && ! empty( $tag->options ) ) {
				foreach ( $tag->options as $option ) {
					// Check for acf-field:field_XXX format
					if ( strpos( $option, 'acf-field:' ) === 0 ) {
						$acf_field_key = trim( str_replace( 'acf-field:', '', $option ), '"\'' );
						break;
					}
					// Check if option contains field key
					if ( strpos( $option, 'field_' ) === 0 ) {
						$acf_field_key = trim( $option, '"\':' );
						break;
					}
				}
			}
			
			// Method 3: Try to get from 'acf-field' option
			if ( empty( $acf_field_key ) ) {
				$acf_field_key = $tag->get_option( 'acf-field', '', true );
				if ( $acf_field_key ) {
					$acf_field_key = trim( $acf_field_key, '"\':' );
				}
			}
			
			// Method 4: Try to get from 'field' option
			if ( empty( $acf_field_key ) ) {
				$acf_field_key = $tag->get_option( 'field', '', true );
				if ( $acf_field_key ) {
					$acf_field_key = trim( $acf_field_key, '"\':' );
				}
			}

			if ( empty( $acf_field_key ) ) {
				return $result;
			}

			$acf_field = acf_get_field( $acf_field_key );

			if ( ! $acf_field ) {
				return $result;
			}

			// Required validation - check ACF field required status, not CF7 tag asterisk
			// ACF stores required as 1, '1', true, or 1 (int)
			$is_acf_required = false;
			if ( isset( $acf_field['required'] ) ) {
				$required_value = $acf_field['required'];
				$is_acf_required = ( $required_value === 1 || $required_value === '1' || $required_value === true || $required_value === 'true' );
			}
			
			if ( $is_acf_required && empty( $value ) ) {
				$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
				return $result;
			}

			// Type-specific validation
			if ( ! empty( $value ) ) {
				switch ( $acf_field['type'] ) {
					case 'cf7apps_email':
						if ( ! is_email( $value ) ) {
							$result->invalidate( $tag, __( 'Please enter a valid email address.', 'cf7apps' ) );
						}
						break;

					case 'cf7apps_url':
						if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
							$result->invalidate( $tag, __( 'Please enter a valid URL.', 'cf7apps' ) );
						}
						break;

					case 'cf7apps_number':
						if ( ! is_numeric( $value ) ) {
							$result->invalidate( $tag, __( 'Please enter a valid number.', 'cf7apps' ) );
						} else {
							$num_value = floatval( $value );
							if ( isset( $acf_field['min'] ) && $acf_field['min'] !== '' && $num_value < floatval( $acf_field['min'] ) ) {
								$result->invalidate( $tag, sprintf( __( 'Please enter a number greater than or equal to %s.', 'cf7apps' ), $acf_field['min'] ) );
							}
							if ( isset( $acf_field['max'] ) && $acf_field['max'] !== '' && $num_value > floatval( $acf_field['max'] ) ) {
								$result->invalidate( $tag, sprintf( __( 'Please enter a number less than or equal to %s.', 'cf7apps' ), $acf_field['max'] ) );
							}
						}
						break;

					case 'cf7apps_text':
					case 'cf7apps_textarea':
						if ( isset( $acf_field['maxlength'] ) && $acf_field['maxlength'] && strlen( $value ) > intval( $acf_field['maxlength'] ) ) {
							$result->invalidate( $tag, sprintf( __( 'Please enter no more than %d characters.', 'cf7apps' ), $acf_field['maxlength'] ) );
						}
						break;
				}
			}

			return $result;
		}

		/**
		 * Enqueue admin scripts
		 *
		 * @since 3.2.1
		 * @param string $hook Current admin page hook.
		 */
		public function enqueue_admin_scripts( $hook = '' ) {
			// Check if we're on a CF7 page
			$is_cf7_page = false;
			$screen      = get_current_screen();

			// Method 1: Check screen ID
			if ( $screen ) {
				$screen_id = $screen->id;
				if ( strpos( $screen_id, 'wpcf7' ) !== false ||
					strpos( $screen_id, 'contact' ) !== false ||
					$screen_id === 'toplevel_page_wpcf7' ||
					$screen_id === 'contact_page_wpcf7' ) {
					$is_cf7_page = true;
				}
			}

			// Method 2: Check hook parameter
			$cf7_hooks = array( 'toplevel_page_wpcf7', 'contact_page_wpcf7', 'contact_page_wpcf7-new' );
			if ( in_array( $hook, $cf7_hooks, true ) ) {
				$is_cf7_page = true;
			}

			// Method 3: Check URL parameters
			global $pagenow;
			if ( $pagenow === 'admin.php' && isset( $_GET['page'] ) ) {
				$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				if ( strpos( $page, 'wpcf7' ) !== false ) {
					$is_cf7_page = true;
				}
			}

			if ( ! $is_cf7_page ) {
				return;
			}

			// Enqueue gradient CSS for blue tag generator button styling
			$gradient_css_url = CF7APPS_PLUGIN_DIR_URL . 'includes/apps/cf7-internal-settings/assets/css/gradient.css';
			wp_enqueue_style( 'cf7apps-gradient', $gradient_css_url, array(), CF7APPS_VERSION );
			
			// Add inline CSS to ensure ACF Field button displays inline with other tag generators
			$inline_css = '
				.tag-generator-list [data-tag="acf_field"] {
					display: inline-block !important;
					margin: 0 4px 4px 0 !important;
					vertical-align: top !important;
				}
			';
			wp_add_inline_style( 'cf7apps-gradient', $inline_css );

			wp_enqueue_script( 'jquery' );

			// Enqueue tag generator script
			$script_url  = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/cf7-acf-tag-generator.js';
			$script_path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/cf7-acf-tag-generator.js';

			// Verify file exists
			if ( ! file_exists( $script_path ) ) {
				return;
			}

			$version = filemtime( $script_path );

			wp_enqueue_script(
				'cf7apps-acf-tag-generator',
				$script_url,
				array( 'jquery' ),
				$version,
				true
			);
		}

		/**
		 * Add inline script for tag generator (fallback)
		 *
		 * @since 3.2.1
		 */
		public function add_tag_generator_script() {
			$screen = get_current_screen();

			// Check if we're on a CF7 page
			$is_cf7_page = false;
			if ( $screen ) {
				if ( strpos( $screen->id, 'wpcf7' ) !== false || strpos( $screen->id, 'contact' ) !== false ) {
					$is_cf7_page = true;
				}
			}

			// Also check by page parameter
			if ( ! $is_cf7_page && isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'wpcf7' ) !== false ) {
				$is_cf7_page = true;
			}

			if ( ! $is_cf7_page ) {
				return;
			}
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				function initACFTagGenerator() {
					var $form = $('#tag-generator-panel-acf_field');
					if ($form.length === 0) return;

					var $nameInput = $form.find('input[name="name"]');
					var $acfFieldSelect = $form.find('select[name="acf-field"]');
					var $fieldKeyDisplay = $form.find('#tag-generator-panel-acf_field-field-key-display');
					var $warningMessage = $form.find('.acf-field-warning');
					var $tagInput = $form.find('#tag-generator-panel-acf_field-tag-input');
					if ($tagInput.length === 0) {
						$tagInput = $form.closest('.tag-generator-panel').find('input.tag.code');
					}

					function updateTag() {
						var name = $nameInput.val();
						var acfField = $acfFieldSelect.val();

						if (acfField) {
							$fieldKeyDisplay.val(acfField);
							// Hide warning message when field is selected
							$warningMessage.hide();
						} else {
							$fieldKeyDisplay.val('');
							// Show warning message when no field is selected
							$warningMessage.show();
						}

						if (name && acfField) {
							// Wrap the option in quotes for proper CF7 parsing
							// Don't add asterisk - required validation will be handled on frontend based on ACF field settings
							var tag = '[acf_field ' + name + ' "acf-field:' + acfField + '"]';
							$tagInput.val(tag);
							$form.closest('.tag-generator-panel').find('.insert-tag').prop('disabled', false).removeClass('button-disabled');
						} else {
							$tagInput.val('');
							if (!acfField) {
								$form.closest('.tag-generator-panel').find('.insert-tag').prop('disabled', true).addClass('button-disabled');
							} else {
								$form.closest('.tag-generator-panel').find('.insert-tag').prop('disabled', false).removeClass('button-disabled');
							}
						}
					}

					$form.closest('.tag-generator-panel').on('click', '.insert-tag', function(e) {
						var acfField = $acfFieldSelect.val();
						var name = $nameInput.val();

						if (!acfField) {
							e.preventDefault();
							e.stopPropagation();
							alert('<?php echo esc_js( __( 'Please select an ACF field from the dropdown before inserting the tag.', 'cf7apps' ) ); ?>');
							$acfFieldSelect.focus();
							return false;
						}

						if (!name) {
							e.preventDefault();
							e.stopPropagation();
							alert('<?php echo esc_js( __( 'Please enter a field name before inserting the tag.', 'cf7apps' ) ); ?>');
							$nameInput.focus();
							return false;
						}

						// Wrap the option in quotes for proper CF7 parsing
						// Don't add asterisk - required validation will be handled on frontend based on ACF field settings
						var fullTag = '[acf_field ' + name + ' "acf-field:' + acfField + '"]';
						$tagInput.val(fullTag);
						$tagInput.attr('value', fullTag);
						$tagInput.trigger('change').trigger('input');
					});

					$nameInput.off('keyup change').on('keyup change', updateTag);
					$acfFieldSelect.off('change').on('change', updateTag);

					updateTag();
					
					// Set initial warning visibility
					if ($acfFieldSelect.val()) {
						$warningMessage.hide();
					} else {
						$warningMessage.show();
					}
				}

				initACFTagGenerator();

				$(document).on('click', '.tag-generator-list [data-tag="acf_field"]', function() {
					setTimeout(initACFTagGenerator, 100);
				});
			});
			</script>
			<?php
		}
	}
}

