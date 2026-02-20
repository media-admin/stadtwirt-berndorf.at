<?php
/**
 * ACF Field Type: CF7Apps Text
 *
 * @since 3.2.1
 * @package Contact Form 7 Apps
 */

if ( ! class_exists( 'ACF_Field_CF7Apps_Text' ) && class_exists( 'acf_field' ) ) {
	class ACF_Field_CF7Apps_Text extends acf_field {

		/**
		 * Initialize field
		 *
		 * @since 3.2.1
		 */
		function initialize() {
			$this->name     = 'cf7apps_text';
			$this->label    = __( 'CF7Apps Text', 'cf7apps' );
			$this->category = 'CF7Apps';
			$this->defaults = array(
				'default_value' => '',
				'placeholder'   => '',
				'maxlength'     => '',
				'prepend'       => '',
				'append'        => '',
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
					'type'         => 'text',
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

			// Prepend
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Prepend', 'acf' ),
					'instructions' => __( 'Appears before the input', 'acf' ),
					'type'         => 'text',
					'name'         => 'prepend',
				)
			);

			// Append
			acf_render_field_setting(
				$field,
				array(
					'label'        => __( 'Append', 'acf' ),
					'instructions' => __( 'Appears after the input', 'acf' ),
					'type'         => 'text',
					'name'         => 'append',
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
				'type'            => 'text',
				'id'              => $field['id'],
				'class'           => $field['class'] . ' acf-cf7apps-text',
				'name'            => $field['name'],
				'value'           => $field['value'],
				'placeholder'     => $field['placeholder'],
				'data-cf7-field'  => 'true',
				'data-field-type' => 'text',
			);

			if ( $field['maxlength'] ) {
				$atts['maxlength'] = $field['maxlength'];
			}

			$prepend = $field['prepend'] ? '<div class="acf-input-prepend">' . esc_html( $field['prepend'] ) . '</div>' : '';
			$append  = $field['append'] ? '<div class="acf-input-append">' . esc_html( $field['append'] ) . '</div>' : '';

			?>
			<div class="acf-input-wrap">
				<?php echo $prepend; ?>
				<input <?php echo acf_esc_attrs( $atts ); ?> />
				<?php echo $append; ?>
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
			return esc_html( $value );
		}
	}
}

