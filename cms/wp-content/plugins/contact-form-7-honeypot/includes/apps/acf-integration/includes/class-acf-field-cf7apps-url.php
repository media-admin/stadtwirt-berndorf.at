<?php
/**
 * ACF Field Type: CF7Apps URL
 *
 * @since 3.2.1
 * @package Contact Form 7 Apps
 */

if ( ! class_exists( 'ACF_Field_CF7Apps_URL' ) && class_exists( 'acf_field' ) ) {
	class ACF_Field_CF7Apps_URL extends acf_field {

		/**
		 * Initialize field
		 *
		 * @since 3.2.1
		 */
		function initialize() {
			$this->name     = 'cf7apps_url';
			$this->label    = __( 'CF7Apps URL', 'cf7apps' );
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
					'type'         => 'url',
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
				'type'            => 'url',
				'id'              => $field['id'],
				'class'           => $field['class'] . ' acf-cf7apps-url',
				'name'            => $field['name'],
				'value'           => $field['value'],
				'placeholder'     => $field['placeholder'],
				'data-cf7-field'  => 'true',
				'data-field-type' => 'url',
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
			if ( $value && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$valid = __( 'Please enter a valid URL.', 'cf7apps' );
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
			return esc_url( $value );
		}
	}
}

