<?php
/**
 * ACF Field Type: CF7Apps Email
 *
 * @since 3.2.1
 * @package Contact Form 7 Apps
 */

if ( ! class_exists( 'ACF_Field_CF7Apps_Email' ) && class_exists( 'acf_field' ) ) {
	class ACF_Field_CF7Apps_Email extends acf_field {

		/**
		 * Initialize field
		 *
		 * @since 3.2.1
		 */
		function initialize() {
			$this->name     = 'cf7apps_email';
			$this->label    = __( 'CF7Apps Email', 'cf7apps' );
			$this->category = 'CF7Apps';
			$this->defaults = array(
				'default_value' => '',
				'placeholder'   => '',
			);
		}

		/**
		 * Render field settings
		 *
		 * @since 3.2.1
		 * @param array $field Field array.
		 */
		function render_field_settings( $field ) {
			// Default value
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Default Value', 'acf' ),
					'instructions' => __( 'Appears when creating a new post', 'acf' ),
					'type'         => 'email',
					'name'         => 'default_value',
				)
			);

			// Placeholder
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Placeholder Text', 'acf' ),
					'instructions' => __( 'Appears within the input', 'acf' ),
					'type'         => 'text',
					'name'         => 'placeholder',
				)
			);
		}

		/**
		 * Render field
		 *
		 * @since 3.2.1
		 * @param array $field Field array.
		 */
		function render_field( $field ) {
			$atts = array(
				'type'            => 'email',
				'id'              => $field['id'],
				'class'           => $field['class'] . ' acf-cf7apps-email',
				'name'            => $field['name'],
				'value'           => $field['value'],
				'placeholder'     => $field['placeholder'],
				'data-cf7-field'  => 'true',
				'data-field-type' => 'email',
			);

			?>
			<div class="acf-input-wrap">
				<input <?php echo acf_esc_attrs( $atts ); ?> />
			</div>
			<?php
		}

		/**
		 * Validate value
		 *
		 * @since 3.2.1
		 * @param bool   $valid  Is valid.
		 * @param mixed  $value  Field value.
		 * @param array  $field  Field array.
		 * @param string $input  Input name.
		 * @return bool|string
		 */
		function validate_value( $valid, $value, $field, $input ) {
			if ( $value && ! is_email( $value ) ) {
				$valid = __( 'Please enter a valid email address.', 'cf7apps' );
			}
			return $valid;
		}

		/**
		 * Format value for display
		 *
		 * @since 3.2.1
		 * @param mixed  $value   Field value.
		 * @param int    $post_id Post ID.
		 * @param array  $field   Field array.
		 * @return string
		 */
		function format_value( $value, $post_id, $field ) {
			return sanitize_email( $value );
		}
	}
}

