<?php
/**
 * ACF Field Type: CF7Apps Textarea
 *
 * @since 3.2.1
 * @package Contact Form 7 Apps
 */

if ( ! class_exists( 'ACF_Field_CF7Apps_Textarea' ) && class_exists( 'acf_field' ) ) {
	class ACF_Field_CF7Apps_Textarea extends acf_field {

		/**
		 * Initialize field
		 *
		 * @since 3.2.1
		 */
		function initialize() {
			$this->name     = 'cf7apps_textarea';
			$this->label    = __( 'CF7Apps Textarea', 'cf7apps' );
			$this->category = 'CF7Apps';
			$this->defaults = array(
				'default_value' => '',
				'placeholder'   => '',
				'maxlength'     => '',
				'rows'          => '',
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
					'type'         => 'textarea',
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

			// Max length
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Character Limit', 'acf' ),
					'instructions' => __( 'Leave blank for no limit', 'acf' ),
					'type'         => 'number',
					'name'         => 'maxlength',
				)
			);

			// Rows
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Rows', 'acf' ),
					'instructions' => __( 'Sets the textarea height', 'acf' ),
					'type'         => 'number',
					'name'         => 'rows',
					'default_value' => 8,
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
				'id'              => $field['id'],
				'class'           => $field['class'] . ' acf-cf7apps-textarea',
				'name'            => $field['name'],
				'placeholder'     => $field['placeholder'],
				'data-cf7-field'  => 'true',
				'data-field-type' => 'textarea',
			);

			if ( $field['maxlength'] ) {
				$atts['maxlength'] = $field['maxlength'];
			}

			if ( $field['rows'] ) {
				$atts['rows'] = $field['rows'];
			} else {
				$atts['rows'] = 8;
			}

			?>
			<div class="acf-input-wrap">
				<textarea <?php echo acf_esc_attrs( $atts ); ?>><?php echo esc_textarea( $field['value'] ); ?></textarea>
			</div>
			<?php
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
			return esc_textarea( $value );
		}
	}
}

