<?php
/**
 * Frontend Honeypot for Contact Form 7
 *
 * @package   Contact_Form_7_Honeypot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the honeypot app is enabled in CF7 Apps settings.
 *
 * @return bool
 */
function honeypot4cf7_is_app_enabled() {
	$cf7apps_settings = get_option( 'cf7apps_settings' );

	if ( $cf7apps_settings && isset( $cf7apps_settings['honeypot']['is_enabled'] ) ) {
		// filter_var handles bool/int/string ("0", "false", "off") correctly.
		return (bool) filter_var( $cf7apps_settings['honeypot']['is_enabled'], FILTER_VALIDATE_BOOLEAN );
	}

	return true;
}

/**
 * Whether time-check is enabled for the given option value.
 *
 * @param mixed $timecheck_enabled Option value from tag or config.
 * @return bool
 */
function honeypot4cf7_timecheck_is_enabled( $timecheck_enabled ) {
	if ( empty( $timecheck_enabled ) ) {
		return false;
	}

	if ( is_array( $timecheck_enabled ) ) {
		return isset( $timecheck_enabled[0] ) && 'false' !== $timecheck_enabled[0] && (bool) filter_var( $timecheck_enabled[0], FILTER_VALIDATE_BOOLEAN );
	}

	if ( is_string( $timecheck_enabled ) && 'false' === strtolower( $timecheck_enabled ) ) {
		return false;
	}

	return (bool) filter_var( $timecheck_enabled, FILTER_VALIDATE_BOOLEAN );
}

/**
 * Live time-check enabled state for a honeypot tag (tag override, else global).
 *
 * @param WPCF7_FormTag|WPCF7_Shortcode|null $tag Honeypot form tag.
 * @return bool
 */
function honeypot4cf7_is_timecheck_live_enabled( $tag = null ) {
	$honeypot4cf7_config = honeypot4cf7_get_config();

	if ( $tag && is_object( $tag ) && method_exists( $tag, 'get_option' ) ) {
		$tag_option = $tag->get_option( 'timecheck_enabled' );
		if ( $tag_option ) {
			return honeypot4cf7_timecheck_is_enabled( $tag_option );
		}
	}

	return honeypot4cf7_timecheck_is_enabled( $honeypot4cf7_config['timecheck_enabled'] ?? array( 'false' ) );
}

/**
 * Calendar day key in site timezone (Ymd).
 *
 * @return string
 */
function honeypot4cf7_get_day_key() {
	return wp_date( 'Ymd' );
}

/**
 * Daily transient option name for a honeypot field.
 *
 * @param string $field_name Honeypot field name.
 * @return string
 */
function honeypot4cf7_get_daily_transient_name( $field_name ) {
	return 'cf7apps_hp_' . sanitize_key( $field_name ) . '_' . honeypot4cf7_get_day_key();
}

/**
 * Invalidate the daily honeypot session for a field.
 *
 * Used when time check fails so the same page cannot retry after waiting.
 *
 * @param string $field_name Honeypot field name.
 * @return void
 */
function honeypot4cf7_invalidate_daily_session( $field_name ) {
	delete_transient( honeypot4cf7_get_daily_transient_name( $field_name ) );
}

/**
 * Seconds until next midnight in the site timezone.
 *
 * @return int
 */
function honeypot4cf7_get_seconds_until_day_end() {
	$timezone = wp_timezone();
	$now      = new DateTime( 'now', $timezone );
	$tomorrow = new DateTime( 'tomorrow', $timezone );
	$seconds  = $tomorrow->getTimestamp() - $now->getTimestamp();

	return max( 60, $seconds );
}

/**
 * Get or create the shared daily honeypot token for a field.
 *
 * @param string $field_name        Honeypot field name.
 * @param mixed  $timecheck_enabled Time-check enabled option.
 * @param mixed  $timecheck_value   Time-check seconds.
 * @return array{random_hash:string,field_name:string,transient_name:string,day_key:string}
 */
function honeypot4cf7_get_or_create_daily_token( $field_name, $timecheck_enabled = null, $timecheck_value = null ) {
	$honeypot4cf7_config = honeypot4cf7_get_config();

	if ( null === $timecheck_enabled ) {
		$timecheck_enabled = $honeypot4cf7_config['timecheck_enabled'];
	}

	if ( null === $timecheck_value ) {
		$timecheck_value = $honeypot4cf7_config['timecheck_value'];
	}

	$day_key        = honeypot4cf7_get_day_key();
	$transient_name = honeypot4cf7_get_daily_transient_name( $field_name );
	$stored         = get_transient( $transient_name );

	$time_check_stored = 0;

	if ( honeypot4cf7_timecheck_is_enabled( $timecheck_enabled ) ) {
		$time_check_stored = is_array( $timecheck_value )
			? (int) reset( $timecheck_value )
			: (int) $timecheck_value;
	}

	if (
		is_array( $stored )
		&& ! empty( $stored['expected_hp_name'] )
		&& isset( $stored['day_key'] )
		&& $stored['day_key'] === $day_key
	) {
		return array(
			'random_hash'    => $day_key,
			'field_name'     => sanitize_key( $stored['expected_hp_name'] ),
			'transient_name' => $transient_name,
			'day_key'        => $day_key,
		);
	}

	$dynamic_honeypot_name = sanitize_key( wp_generate_password( 12, false, false ) );
	$transient_attrs       = array(
		'expected_hp_name' => $dynamic_honeypot_name,
		'time_check'       => $time_check_stored,
		'day_key'          => $day_key,
	);

	set_transient( $transient_name, $transient_attrs, honeypot4cf7_get_seconds_until_day_end() );

	return array(
		'random_hash'    => $day_key,
		'field_name'     => $dynamic_honeypot_name,
		'transient_name' => $transient_name,
		'day_key'        => $day_key,
	);
}

/**
 * Build the HMAC signature for a per-render time token.
 *
 * @param string $field_name       Honeypot field name.
 * @param string $day_key          Calendar day key.
 * @param string $expected_hp_name Dynamic honeypot input name.
 * @param int    $time_start       Unix timestamp.
 * @return string
 */
function honeypot4cf7_build_time_token_signature( $field_name, $day_key, $expected_hp_name, $time_start ) {
	$message = sanitize_key( $field_name ) . '|' . $day_key . '|' . sanitize_key( $expected_hp_name ) . '|' . (string) $time_start;

	return hash_hmac( 'sha256', $message, wp_salt( 'cf7apps_honeypot' ) );
}

/**
 * Create a signed per-render time token.
 *
 * @param string $field_name       Honeypot field name.
 * @param string $day_key          Calendar day key.
 * @param string $expected_hp_name Dynamic honeypot input name.
 * @return array{time_start:int,time_token:string}
 */
function honeypot4cf7_create_time_token( $field_name, $day_key, $expected_hp_name ) {
	$time_start = time();
	$signature  = honeypot4cf7_build_time_token_signature( $field_name, $day_key, $expected_hp_name, $time_start );

	return array(
		'time_start' => $time_start,
		'time_token' => base64_encode( (string) $time_start ) . '.' . $signature,
	);
}

/**
 * Verify a signed per-render time token.
 *
 * @param string $field_name       Honeypot field name.
 * @param string $day_key          Calendar day key.
 * @param string $expected_hp_name Dynamic honeypot input name.
 * @param string $time_token       Submitted time token.
 * @return int|false Unix timestamp on success, false on failure.
 */
function honeypot4cf7_verify_time_token( $field_name, $day_key, $expected_hp_name, $time_token ) {
	if ( ! is_string( $time_token ) || '' === $time_token ) {
		return false;
	}

	$parts = explode( '.', $time_token, 2 );

	if ( 2 !== count( $parts ) ) {
		return false;
	}

	$payload = base64_decode( $parts[0], true );

	if ( false === $payload || ! ctype_digit( $payload ) ) {
		return false;
	}

	$time_start       = (int) $payload;
	$expected_signature = honeypot4cf7_build_time_token_signature( $field_name, $day_key, $expected_hp_name, $time_start );

	if ( ! hash_equals( $expected_signature, $parts[1] ) ) {
		return false;
	}

	return $time_start;
}

/**
 * Create a honeypot token (daily transient + per-render time token).
 *
 * @param string $field_name         Honeypot field name.
 * @param mixed  $timecheck_enabled  Time-check enabled option.
 * @param mixed  $timecheck_value    Time-check seconds.
 * @return array{random_hash:string,field_name:string,transient_name:string,day_key:string,time_start:int,time_token:string}
 */
function honeypot4cf7_create_token( $field_name, $timecheck_enabled = null, $timecheck_value = null ) {
	$token     = honeypot4cf7_get_or_create_daily_token( $field_name, $timecheck_enabled, $timecheck_value );
	$time_data = honeypot4cf7_create_time_token( $field_name, $token['day_key'], $token['field_name'] );

	return array_merge( $token, $time_data );
}

/**
 * Default inline CSS for the honeypot wrapper (visually hidden, not display:none).
 *
 * @return string
 */
function honeypot4cf7_get_default_container_css() {
	return 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
}

/**
 * Enqueue frontend honeypot styles (collapses CF7 <p> wrapper gap).
 */
function honeypot4cf7_enqueue_frontend_styles() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'cf7apps-honeypot-frontend',
		CF7APPS_PLUGIN_DIR_URL . '/legacy-honeypot/includes/css/honeypot-frontend.css',
		array(),
		CF7APPS_VERSION
	);
}
add_action( 'wpcf7_enqueue_scripts', 'honeypot4cf7_enqueue_frontend_styles' );

/**
 * Enqueue honeypot refill script for cached pages.
 */
function honeypot4cf7_enqueue_refill_script() {
	if ( is_admin() || ! honeypot4cf7_is_app_enabled() ) {
		return;
	}

	wp_enqueue_script(
		'cf7apps-honeypot-refill',
		CF7APPS_PLUGIN_DIR_URL . '/legacy-honeypot/includes/js/honeypot-refill.js',
		array( 'contact-form-7' ),
		CF7APPS_VERSION,
		true
	);

	$force_refill = ( defined( 'WP_CACHE' ) && WP_CACHE ) || class_exists( 'Cache_Enabler', false );

	wp_localize_script(
		'cf7apps-honeypot-refill',
		'cf7appsHoneypotRefill',
		array(
			'forceRefillOnInit' => apply_filters( 'honeypot4cf7_force_refill', $force_refill ),
		)
	);
}
add_action( 'wpcf7_enqueue_scripts', 'honeypot4cf7_enqueue_refill_script' );

/**
 *
 * Initialize the shortcode
 * 		This lets CF7 know about Mr. Honeypot.
 * 
 */
add_action( 'wpcf7_init', 'honeypot4cf7_add_form_tag', 10 );
function honeypot4cf7_add_form_tag() {
	
	$honeypot4cf7_config = honeypot4cf7_get_config();
	$do_not_store = ( empty( $honeypot4cf7_config['store_honeypot'] ) ) ? true : false;

	// Test if new 4.6+ functions exists
	if ( function_exists( 'wpcf7_add_form_tag' ) ) {
		wpcf7_add_form_tag( 
			'honeypot', 
			'honeypot4cf7_form_tag_handler', 
			array( 
				'name-attr' => true, 
				'do-not-store' => $do_not_store,
				'not-for-mail' => true,
			)
		);
	} else {
		wpcf7_add_shortcode( 'honeypot', 'honeypot4cf7_form_tag_handler', true );
	}
}


/**
 * 
 * Form Tag handler
 * 		This is where we generate the honeypot HTML from the shortcode options
 * 
 */
function honeypot4cf7_form_tag_handler( $tag ) {

	// Global disable must stop rendering so existing forms stop enforcing honeypot (HFCF7-548).
	if ( ! honeypot4cf7_is_app_enabled() ) {
		return '';
	}

	// Test if new 4.6+ functions exists
	$tag = ( class_exists( 'WPCF7_FormTag' ) ) ? new WPCF7_FormTag( $tag ) : new WPCF7_Shortcode( $tag );

	if ( empty( $tag->name ) ) {
		return '';
	}

	$validation_error = wpcf7_get_validation_error( $tag->name );

	$honeypot4cf7_config = honeypot4cf7_get_config();

	$class = wpcf7_form_controls_class( 'text' );
	
	$placeholder = (string) reset( $tag->values );

	$accessibility_message = ( ! empty( $honeypot4cf7_config['accessibility_message'] ) ) ? $honeypot4cf7_config['accessibility_message'] : __( 'Please leave this field empty.', 'contact-form-7-honeypot' );

	$atts = array(
		'class' 			=> $tag->get_class_option( $class ),
		'id'				=> $tag->get_option( 'id', 'id', true ),
		'wrapper_id' 		=> $tag->get_option( 'wrapper-id' ),
		'placeholder' 		=> ( $placeholder ) ? $placeholder : $honeypot4cf7_config['placeholder'],
		'message' 			=> apply_filters( 'wpcf7_honeypot_accessibility_message', $accessibility_message ),
		'name'				=> $tag->name,
		'type'				=> $tag->type,
		'validautocomplete'	=> ( $tag->get_option( 'validautocomplete' ) ) ? $tag->get_option( 'validautocomplete' ) : $honeypot4cf7_config['w3c_valid_autocomplete'],
		'move_inline_css'	=> ( $tag->get_option( 'move-inline-css' ) ) ? $tag->get_option( 'move-inline-css' ) : $honeypot4cf7_config['move_inline_css'],
		'nomessage'			=> ( $tag->get_option( 'nomessage' ) ) ? $tag->get_option( 'nomessage' ) : $honeypot4cf7_config['nomessage'],
		'timecheck_enabled'	=> ( $tag->get_option( 'timecheck_enabled' ) ) ? $tag->get_option( 'timecheck_enabled' ) : $honeypot4cf7_config['timecheck_enabled'],
		'timecheck_value'	=> ( $timecheck_value = $tag->get_option( 'timecheck_value' ) ) ? reset($timecheck_value) : $honeypot4cf7_config['timecheck_value'],
		'validation_error'	=> $validation_error,
		'css'				=> apply_filters( 'wpcf7_honeypot_container_css', honeypot4cf7_get_default_container_css() ),
	);

	$unique_id = uniqid( 'wpcf7-' );
	$wrapper_id = ( ! empty($atts['wrapper_id'] ) ) ? reset( $atts['wrapper_id'] ) : $unique_id . '-wrapper';
	$input_placeholder = ( ! empty( $atts['placeholder'] ) ) ? ' placeholder="' . $atts['placeholder'] . '" ' : '';
	$input_id = ( ! empty( $atts['id'] ) ) ? $atts['id'] : $unique_id . '-field';
	$autocomplete_value = ( $atts['validautocomplete'][0] === 'true' ) ? 'off' : 'new-password';

	// Check if we should move the CSS off the element and into the footer
	if ( ! empty( $atts['move_inline_css'] ) && $atts['move_inline_css'][0] === 'true' ) {
		$hp_css = '#' . $wrapper_id . ' {
		    ' . $atts['css'] . '
		}
		';
		wp_register_style( $unique_id . '-inline', false );
		wp_enqueue_style( $unique_id . '-inline' );
		wp_add_inline_style( $unique_id . '-inline', $hp_css );
		$el_css = '';
	} else {
		$el_css = 'style="' . $atts['css'] . '"';
	}

	$token = honeypot4cf7_create_token(
		$atts['name'],
		$atts['timecheck_enabled'],
		$atts['timecheck_value']
	);

	$html = '<span id="' . esc_attr( $wrapper_id ) . '" class="wpcf7-form-control-wrap cf7apps-honeypot-wrap ' . esc_attr( $atts['name'] ) . '-wrap" data-cf7apps-honeypot="' . esc_attr( $atts['name'] ) . '" ' . $el_css . '>';

	$html .= '<input type="hidden" name="' . esc_attr( $atts['name'] ) . '-random-hash" value="' . esc_attr( (string) $token['random_hash'] ) . '">';

	$html .= '<input type="hidden" name="' . esc_attr( $atts['name'] ) . '-time-token" value="' . esc_attr( $token['time_token'] ) . '">';

	if ( empty( $atts['nomessage'] ) || $atts['nomessage'][0] === 'false' ) {
		$html .= '<label
		    for="' . $input_id . '"
		    class="hp-message"
        >' . $atts['message'] . '</label>';
	}

	$html .= '<input
	    id="' . $input_id . '"
	    ' . $input_placeholder . '
	    class="' . $atts['class'] . '"
	    type="text"
	    name="' . esc_attr( $token['field_name'] ) . '"
	    value=""
	    autocomplete="'. $autocomplete_value . '"
	    tabindex="1000"
	    data-cf7apps-tabindex="1000"
    />';
	$html .= $validation_error . '</span>';

	// Hook for filtering finished Honeypot form element.
	return apply_filters( 'wpcf7_honeypot_html_output' , $html, $atts );
}


/**
 * Normalize honeypot values in posted data based on store setting.
 *
 * Dynamic honeypot field names bypass CF7 do-not-store, so map or strip them here.
 *
 * @param array $posted_data Posted form data.
 * @return array
 */
function honeypot4cf7_filter_posted_data( $posted_data ) {
	if ( ! is_array( $posted_data ) || ! honeypot4cf7_is_app_enabled() ) {
		return $posted_data;
	}

	$honeypot4cf7_config = honeypot4cf7_get_config();
	$store_honeypot      = ! empty( $honeypot4cf7_config['store_honeypot'] );
	$tags                = wpcf7_scan_form_tags( array( 'type' => 'honeypot' ) );

	if ( empty( $tags ) ) {
		return $posted_data;
	}

	foreach ( $tags as $tag ) {
		$hpid = $tag->name;

		if ( empty( $hpid ) ) {
			continue;
		}

		unset( $posted_data[ $hpid . '-random-hash' ] );
		unset( $posted_data[ $hpid . '-time-token' ] );

		$expected_hp_name = '';
		$dynamic_value    = '';

		$transient_data = get_transient( honeypot4cf7_get_daily_transient_name( $hpid ) );

		if ( is_array( $transient_data ) && ! empty( $transient_data['expected_hp_name'] ) ) {
			$expected_hp_name = sanitize_key( $transient_data['expected_hp_name'] );
		}

		if ( '' !== $expected_hp_name && isset( $posted_data[ $expected_hp_name ] ) ) {
			$dynamic_value = $posted_data[ $expected_hp_name ];
			unset( $posted_data[ $expected_hp_name ] );
		}

		if ( $store_honeypot ) {
			$posted_data[ $hpid ] = $dynamic_value;
		} else {
			unset( $posted_data[ $hpid ] );
		}
	}

	return $posted_data;
}

add_filter( 'wpcf7_posted_data', 'honeypot4cf7_filter_posted_data', 20, 1 );


/**
 * 
 * Honeypot Spam Check
 * 		Bots beware!
 * 
 */

if ( version_compare(CF7APPS_WPCF7_VERSION, '5.3.0', '>=' ) ) {
	// Newer Spam filter - with log
	add_filter( 'wpcf7_spam', 'honeypot4cf7_spam_check', 10, 2 );
} elseif ( version_compare(CF7APPS_WPCF7_VERSION, '3.0', '>=' ) ) {
	// Older Spam filter - no log
	add_filter( 'wpcf7_spam', 'honeypot4cf7_spam_check', 10, 1 );
} else {
	// Real old - unsupported
	return false;
}

function honeypot4cf7_spam_check( $spam, $submission = null ) {

	if ( $spam ) {
		return $spam;
	}

	// Respect global Honeypot App toggle for existing forms (HFCF7-548).
	if ( ! honeypot4cf7_is_app_enabled() ) {
		return $spam;
	}

	$cf7form = WPCF7_ContactForm::get_current();
	$form_tags = $cf7form->scan_form_tags();
	$hp_ids    = array();
	$hp_tags   = array();
	
	foreach ( $form_tags as $tag ) {
		if ( $tag->type == 'honeypot' ) {
			$hp_ids[] = $tag->name;
			$hp_tags[ $tag->name ] = $tag;
		}
	}

	// Check if form has Honeypot fields, if not, exit
	if ( empty( $hp_ids ) ) {
		return $spam;
	}

	foreach ( $hp_ids as $hpid ) {
		$honeypot4cf7_config = honeypot4cf7_get_config();
        $cf7apps_settings = get_option( 'cf7apps_settings' );

		$random_hash = isset( $_POST[ $hpid . '-random-hash' ] ) ? $_POST[ $hpid . '-random-hash' ] : '';
		$day_key     = honeypot4cf7_get_day_key();
		
		// Validate random hash exists and matches expected format (8-9 digits).
		if ( empty( $random_hash ) || ! preg_match( '/^\d{8,9}$/', $random_hash ) ) {
			$spam = true;
			if ( $submission ) {
				$submission->add_spam_log( array(
					'agent' => 'honeypot',
					'reason' => sprintf(
						/* translators: %s: honeypot field ID */
						__( 'Honeypot detected invalid or missing random hash. Field ID = %s', 'contact-form-7-honeypot' ),
						$hpid
					),
				) );
			}

			if( $cf7apps_settings ) {
				// Backward compatibility for CF7APPS settings
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				cf7apps_save_app_settings( 'honeypot', array(
					'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
				) );
			}
			else {
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
			}

			return $spam;
		}

		if ( (string) $random_hash !== $day_key ) {
			$spam = true;
			if ( $submission ) {
				$submission->add_spam_log( array(
					'agent' => 'honeypot',
					'reason' => sprintf(
						/* translators: %s: honeypot field ID */
						__( 'Honeypot detected stale day hash (cached page). Field ID = %s', 'contact-form-7-honeypot' ),
						$hpid
					),
				) );
			}

			if( $cf7apps_settings ) {
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				cf7apps_save_app_settings( 'honeypot', array(
					'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
				) );
			}
			else {
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
			}

			return $spam;
		}
		
        $transient_data = get_transient( honeypot4cf7_get_daily_transient_name( $hpid ) );
        
        // Validate transient data exists and is valid array
        if ( ! is_array( $transient_data ) || empty( $transient_data ) ) {
            // No transient data found - likely spam or expired
            $spam = true;
            if ( $submission ) {
                $submission->add_spam_log( array(
                    'agent' => 'honeypot',
                    'reason' => sprintf(
                        /* translators: %s: honeypot field ID */
                        __( 'Honeypot detected form submitted without valid time check data. Field ID = %s', 'contact-form-7-honeypot' ),
                        $hpid
                    ),
                ) );
            }

			if( $cf7apps_settings ) {
				// Backward compatibility for CF7APPS settings
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				cf7apps_save_app_settings( 'honeypot', array(
					'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
				) );
			}
			else {
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
			}

            return $spam;
        }

        $timecheck_value = isset( $transient_data['time_check'] ) ? (int) $transient_data['time_check'] : 0;
		$expected_hp_name = isset( $transient_data['expected_hp_name'] ) ? sanitize_key( $transient_data['expected_hp_name'] ) : '';

		if ( empty( $expected_hp_name ) ) {
			$spam = true;
			if ( $submission ) {
				$submission->add_spam_log( array(
					'agent' => 'honeypot',
					'reason' => sprintf(
						/* translators: %s: honeypot field ID */
						__( 'Honeypot missing dynamic field metadata. Field ID = %s', 'contact-form-7-honeypot' ),
						$hpid
					),
				) );
			}

			if( $cf7apps_settings ) {
				// Backward compatibility for CF7APPS settings
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				cf7apps_save_app_settings( 'honeypot', array(
					'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
				) );
			}
			else {
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
			}

			return $spam;
		}

		if ( ! array_key_exists( $expected_hp_name, $_POST ) ) {
			$spam = true;
			if ( $submission ) {
				$submission->add_spam_log( array(
					'agent' => 'honeypot',
					'reason' => sprintf(
						/* translators: 1: honeypot field ID 2: expected dynamic field name */
						__( 'Honeypot expected dynamic field "%2$s" was missing. Field ID = %1$s', 'contact-form-7-honeypot' ),
						$hpid,
						$expected_hp_name
					),
				) );
			}

			if( $cf7apps_settings ) {
				// Backward compatibility for CF7APPS settings
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				cf7apps_save_app_settings( 'honeypot', array(
					'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
				) );
			}
			else {
				$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
				
				update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
			}

			return $spam;
		}

		$value = isset( $_POST[ $expected_hp_name ] ) ? trim( wp_unslash( (string) $_POST[ $expected_hp_name ] ) ) : '';

		if ( '' === $value && isset( $_POST[ $hpid ] ) ) {
			$value = trim( wp_unslash( (string) $_POST[ $hpid ] ) );
		}

		if ( '' === $value && $submission ) {
			$posted_data = $submission->get_posted_data();

			if ( isset( $posted_data[ $expected_hp_name ] ) ) {
				$value = trim( (string) $posted_data[ $expected_hp_name ] );
			}

			if ( '' === $value && isset( $posted_data[ $hpid ] ) ) {
				$value = trim( (string) $posted_data[ $hpid ] );
			}
		}

        // Time check only when live global/tag setting still allows it (not just stale transient).
        $timecheck_active = isset( $transient_data['time_check'] ) && (int) $transient_data['time_check'] > 0;
		$live_tag         = isset( $hp_tags[ $hpid ] ) ? $hp_tags[ $hpid ] : null;
		if ( $timecheck_active && ! honeypot4cf7_is_timecheck_live_enabled( $live_tag ) ) {
			$timecheck_active = false;
		}

        if ( $timecheck_active && $timecheck_value <= 0 ) {
            $timecheck_value = isset( $honeypot4cf7_config['timecheck_value'] ) ? (int) $honeypot4cf7_config['timecheck_value'] : 4;
        }

        if ( $timecheck_active ) {
			$time_token = isset( $_POST[ $hpid . '-time-token' ] ) ? wp_unslash( (string) $_POST[ $hpid . '-time-token' ] ) : '';
			$timecheck_start = honeypot4cf7_verify_time_token( $hpid, $day_key, $expected_hp_name, $time_token );

			if ( false === $timecheck_start ) {
				$spam = true;

				if ( $submission ) {
					$submission->add_spam_log( array(
						'agent' => 'honeypot',
						'reason' => sprintf(
							/* translators: %s: honeypot field ID */
							__( 'Honeypot detected invalid or missing time token. Field ID = %s', 'contact-form-7-honeypot' ),
							$hpid
						),
					) );
				}

				if( $cf7apps_settings ) {
					$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
					
					cf7apps_save_app_settings( 'honeypot', array(
						'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
					) );
				}
				else {
					$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
					
					update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
				}

				honeypot4cf7_invalidate_daily_session( $hpid );

				return $spam;
			}
        
            $submission_time = time();
            $submission_interval = $submission_time - $timecheck_start;

            // Validate timestamp is not in the future (bots submitting future timestamps)
            if ( $timecheck_start > $submission_time ) {
                $spam = true;
                if ( $submission ) {
                    $submission->add_spam_log( array(
                        'agent' => 'honeypot',
                        'reason' => sprintf(
                            /* translators: 1: honeypot field ID 2: future timestamp */
                            __( 'Honeypot detected future timestamp (timestamp: %2$s). Field ID = %1$s', 'contact-form-7-honeypot' ), 
                            $hpid,
                            date( 'Y-m-d H:i:s', $timecheck_start )
                        ),
                    ) );
                }

				if( $cf7apps_settings ) {
					// Backward compatibility for CF7APPS settings
					$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
					
					cf7apps_save_app_settings( 'honeypot', array(
						'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
					) );
				}
				else {
					$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
					
					update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
				}

                honeypot4cf7_invalidate_daily_session( $hpid );

                return $spam;
            }

            // Validate timestamp is not too old (prevent replay attacks - max 2 hours)
            $max_age = 60 * 60 * 2; // 2 hours
            if ( $submission_interval > $max_age ) {
                $spam = true;
                if ( $submission ) {
                    $submission->add_spam_log( array(
                        'agent' => 'honeypot',
                        'reason' => sprintf(
                            /* translators: 1: honeypot field ID 2: age in hours */
                            __( 'Honeypot detected stale timestamp (replay attack, age: %2$.1f hours). Field ID = %1$s', 'contact-form-7-honeypot' ), 
                            $hpid,
                            $submission_interval / 3600
                        ),
                    ) );
                }

				if( $cf7apps_settings ) {
					// Backward compatibility for CF7APPS settings
					$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
					
					cf7apps_save_app_settings( 'honeypot', array(
						'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
					) );
				}
				else {
					$honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
					
					update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
				}

                honeypot4cf7_invalidate_daily_session( $hpid );

                return $spam;
            }

            // Check if form was submitted too fast
            if ( $timecheck_active && $submission_interval < $timecheck_value ) {
                // Fast Bots!
                $spam = true;

                if ( $submission ) {
                    $submission->add_spam_log( array(
                        'agent' => 'honeypot',
                        'reason' => sprintf(
                            /* translators: 1: submission interval integer 2: honeypot field ID 3: required time */
                            __( 'Honeypot detected form submitted too fast (%1$s seconds, required: %3$s seconds). Field ID = %2$s', 'contact-form-7-honeypot' ), 
                            $submission_interval,
                            $hpid,
                            $timecheck_value
                        ),
                    ) );
                }

                if( $cf7apps_settings ) {
                    // Backward compatibility for CF7APPS settings
                    $honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
                    
                    cf7apps_save_app_settings( 'honeypot', array(
                        'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
                    ) );
                }
                else {
                    $honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
                    
                    update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
                }

                honeypot4cf7_invalidate_daily_session( $hpid );

                return $spam; // There's no need to go on, this is most likely a bot submission.
            }
        }

		// SPAM CHECK #2: Now we check the honeypot!
		if ( $value != '' ) {
			// Chatty Bots!
			$spam = true;
			
			if ( $submission ) {
				$submission->add_spam_log( array(
					'agent' => 'honeypot',
					'reason' => sprintf(
						/* translators: 1: honeypot field ID 2: dynamic field name */
						__( 'Something is stuck in the honey. Field ID = %1$s (dynamic field: %2$s)', 'contact-form-7-honeypot' ), 
						$hpid,
						$expected_hp_name
					),
				) );
			}

			if( $cf7apps_settings ) {
                // Backward compatibility for CF7APPS settings
                $honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
                
                cf7apps_save_app_settings( 'honeypot', array(
                    'honeypot_count'    => $honeypot4cf7_config['honeypot_count'],
                ) );

                return $spam; // There's no need to go on, we've got flies in the honey
            }
            else {
                $honeypot4cf7_config['honeypot_count'] = ( isset( $honeypot4cf7_config['honeypot_count'] ) ) ? $honeypot4cf7_config['honeypot_count'] + 1 : 1;
                
                update_option( 'honeypot4cf7_config', $honeypot4cf7_config );
            }
			
			return $spam; // There's no need to go on, we've got flies in the honey.
		}

	}

	return $spam;
}


/**
 * 
 * Tag generator & handler
 * 		Adds Honeypot to the CF7 form editor
 * 
 */
add_action( 'wpcf7_admin_init', 'honeypot4cf7_generate_form_tag', 10, 0 );

function honeypot4cf7_generate_form_tag() {
    $cf7apps_settings = get_option( 'cf7apps_settings' );

    if ( ! $cf7apps_settings || honeypot4cf7_is_app_enabled() ) {
        $tag_generator = WPCF7_TagGenerator::get_instance();
	    $tag_generator->add( 'honeypot', __( 'Honeypot', 'contact-form-7-honeypot' ), 'honeypot4cf7_form_tag_generator', array( 'version' => 2 ) );
    }
}

function honeypot4cf7_form_tag_generator( $contact_form, $args = '' ) {
	$args = wp_parse_args( $args, array() );
	$description = __( 'Generate a form-tag for a spam-stopping honeypot field. For more details, see %s.', 'contact-form-7-honeypot' );
	$desc_link = '<a href="https://wordpress.org/plugins/contact-form-7-honeypot/" target="_blank">' . __( 'Honeypot for CF7', 'contact-form-7-honeypot' ) . '</a>';

	// Inherit global Time Check settings so newly inserted tags reflect effective config.
	$honeypot4cf7_config     = honeypot4cf7_get_config();
	$timecheck_enabled_global = honeypot4cf7_timecheck_is_enabled( $honeypot4cf7_config['timecheck_enabled'] ?? array( 'false' ) );
	$timecheck_value_global   = isset( $honeypot4cf7_config['timecheck_value'] ) ? (int) $honeypot4cf7_config['timecheck_value'] : 0;
	if ( $timecheck_value_global <= 0 ) {
		$timecheck_value_global = 4;
	}
	$timecheck_value_attr = $timecheck_enabled_global ? (string) $timecheck_value_global : '';

    if ( version_compare( WPCF7_VERSION, '6.0', '<' ) ) {
        ?>
        <div class="control-box">
            <fieldset>
                <legend>
                    <?php
                    // Point to the CF7 Apps Honeypot settings screen instead of the legacy page.
                    $honeypotcf7_config_url = esc_url( admin_url( 'admin.php?page=cf7apps#/settings/honeypot' ) );
                    $honeypotcf7_settings_link = "<a href='$honeypotcf7_config_url'>" . __( 'Honeypot Settings', 'contact-form-7-honeypot' ) . '</a>';
                    printf(
                        /* translators: %s: Link to Honeypot settings page */
                        esc_html( __( 'Generate a form-tag for a spam-stopping honeypot field. Check out %s for more settings/info.', 'contact-form-7-honeypot' ) ),
                        $honeypotcf7_settings_link
                    );
                    ?>
                </legend>

                <table class="form-table form-table--honeypotcf7"><tbody>
                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php esc_html_e( 'Name', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /><br>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><em><?php esc_html_e( 'For better security, change "honeypot" to something more appealing to a bot, such as text including "email" or "website".', 'contact-form-7-honeypot' ); ?></em></td>
                    </tr>

                    <tr style="background:#efefef;">
                        <td colspan="2" style="text-transform:uppercase;text-align:center;font-weight:bold;padding-top:5px;padding-bottom:5px;">
                            <?php esc_html_e( 'Optional Settings', 'contact-form-7-honeypot' ); ?>
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-id' ); ?>"><?php esc_html_e( 'ID', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="id" class="idvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-id' ); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-class' ); ?>"><?php esc_html_e( 'Class', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr( $args['content'] . '-class' ); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-wrapper-id' ); ?>"><?php esc_html_e( 'Wrapper ID', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="wrapper-id" class="wrapper-id-value oneline option" id="<?php echo esc_attr( $args['content'] . '-wrapper-id' ); ?>" /><br>
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php echo esc_html( __( 'Placeholder', 'contact-form-7-honeypot' ) ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="values" class="oneline" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-validautocomplete' ); ?>"><?php esc_html_e( 'Use Standard Autocomplete Value', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="validautocomplete:true" id="<?php echo esc_attr( $args['content'] . '-validautocomplete' ); ?>" class="validautocompletevalue option" />
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-move-inline-css' ); ?>"><?php esc_html_e( 'Move inline CSS', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="move-inline-css:true" id="<?php echo esc_attr( $args['content'] . '-move-inline-css' ); ?>" class="move-inline-css-value option" />
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-nomessage' ); ?>"><?php esc_html_e( 'Disable Accessibility Label', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="nomessage:true" id="<?php echo esc_attr( $args['content'] . '-nomessage' ); ?>" class="messagekillvalue option" />
                        </td>
                    </tr>

                    <tr>
                        <th style="width:50%;" scope="row">
                            <label for="<?php echo esc_attr( $args['content'] . '-timecheck-enabled' ); ?>"><?php esc_html_e( 'Enable Time Check', 'contact-form-7-honeypot' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="timecheck_enabled:true" id="<?php echo esc_attr( $args['content'] . '-timecheck-enabled' ); ?>" class="option"<?php checked( $timecheck_enabled_global ); ?> />
                            <input type="number" step="1" min="1" placeholder="4" value="<?php echo esc_attr( $timecheck_value_attr ); ?>" name="timecheck_value" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-timecheck-value' ); ?>" /> <?php esc_html_e('seconds', 'contact-form-7-honeypot'); ?>
                        </td>
                    </tr>

                </tbody></table>
            </fieldset>
        </div>

        <div class="insert-box">
            <input type="text" name="honeypot" class="tag code" readonly="readonly" onfocus="this.select()" />

            <div class="submitbox">
                <input type="button" class="button button-primary insert-tag" value="<?php esc_attr_e( 'Insert Tag', 'contact-form-7-honeypot' ); ?>" />
            </div>

            <br class="clear" />
        </div>
        <?php
    } else {
        $tag = new WPCF7_TagGeneratorGenerator( $args['content'] );
        ?>
        <header class="description-box">
            <h3>Honeypot</h3>
            <p>
	            <?php
	            // Point to the CF7 Apps Honeypot settings screen instead of the legacy page.
	            $honeypotcf7_config_url = esc_url( admin_url( 'admin.php?page=cf7apps#/settings/honeypot' ) );
	            $honeypotcf7_settings_link = "<a href='$honeypotcf7_config_url'>" . __( 'Honeypot Settings', 'contact-form-7-honeypot' ) . '</a>';
	            printf(
	            /* translators: %s: Link to Honeypot settings page */
		            esc_html( __( 'Generate a form-tag for a spam-stopping honeypot field. Check out %s for more settings/info.', 'contact-form-7-honeypot' ) ),
		            $honeypotcf7_settings_link
	            );
	            ?>
            </p>

        </header>

        <div class="control-box">

        <div style="border-left: 4px solid #3399ff; background: #e6f4ff; padding: 1px 14px; margin-bottom: 10px; border-radius: 5px;margin-top: 30px;">
                <p style="margin: 7px auto;font-weight: 600;">
	                <?php
	                printf(
		                '%s <a href="%s" target="_blank">%s</a>',
		                esc_html__( 'Need help setting this up? Check out our', 'cf7apps' ),
		                esc_url( 'https://cf7apps.com/docs/spam-protection/contact-form-7-honeypot/' ),
		                esc_html__( 'Documentation', 'cf7apps' )
	                );
	                ?>
                </p>
            </div>
            <?php
            $tag->print(
                'field_type',
                array(
                    'select_options' => array(
                        'honeypot' => __( 'Honeypot', 'contact-form-7-honeypot' ),
                    )
                )
            );

            $tag->print( 'field_name' );

            $tag->print( 'id_attr' );

            $tag->print( 'class_attr' );

            ?>

            <fieldset>
                <legend id="<?php echo esc_attr( $args['content'] ); ?>-wrapper-id-legend">
                    <?php esc_html_e( 'Wrapper ID', 'contact-form-7-honeypot' ); ?>
                </legend>
                <input type="text" data-tag-option="wrapper-id:" data-tag-part="option" name="wrapper-id" class="wrapper-id-value oneline option" id="<?php echo esc_attr( $args['content'] . '-wrapper-id' ); ?>" />
            </fieldset>

            <fieldset>
                <legend id="<?php echo esc_attr( $args['content'] ); ?>-values-legend">
			        <?php esc_html_e( 'Placeholder', 'contact-form-7-honeypot' ); ?>
                </legend>
                <input type="text" data-tag-part="value" name="values" class="oneline" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" />
            </fieldset>

            <fieldset>
                <legend id="<?php echo esc_attr( $args['content'] ); ?>-validautocomplete-legend">
			        <?php esc_html_e( 'Use Standard Autocomplete Value', 'contact-form-7-honeypot' ); ?>
                </legend>
                <input type="checkbox" data-tag-option="validautocomplete:" data-tag-part="option" value="true" name="validautocomplete:true" id="<?php echo esc_attr( $args['content'] . '-validautocomplete' ); ?>" class="validautocompletevalue option" />
            </fieldset>

            <fieldset>
                <legend id="<?php echo esc_attr( $args['content'] ); ?>-move-inline-css-legend">
			        <?php esc_html_e( 'Move inline CSS', 'contact-form-7-honeypot' ); ?>
                </legend>
                <input type="checkbox" data-tag-option="move-inline-css:" data-tag-part="option" value="true" name="move-inline-css:true" id="<?php echo esc_attr( $args['content'] . '-move-inline-css' ); ?>" class="move-inline-css-value option" />
            </fieldset>

            <fieldset>
                <legend id="<?php echo esc_attr( $args['content'] ); ?>-disable-accessibility-label-legend">
			        <?php esc_html_e( 'disable Accessibility Label', 'contact-form-7-honeypot' ); ?>
                </legend>
                <input type="checkbox" data-tag-option="nomessage:" data-tag-part="option" value="true" name="nomessage:true" id="<?php echo esc_attr( $args['content'] . '-move-inline-css' ); ?>" class="move-inline-css-value option" />
            </fieldset>

            <fieldset>
                <legend id="<?php echo esc_attr( $args['content'] ); ?>-timecheck-enabled-legend">
			        <?php esc_html_e( 'Enable Time Check', 'contact-form-7-honeypot' ); ?>
                </legend>
                <input type="checkbox" data-tag-option="timecheck_enabled:" data-tag-part="option" name="timecheck_enabled:true" value="true" id="<?php echo esc_attr( $args['content'] . '-timecheck-enabled' ); ?>" class="option"<?php checked( $timecheck_enabled_global ); ?> />
                <input data-tag-option="timecheck_value:" data-tag-part="option" type="number" step="1" min="1" placeholder="4" value="<?php echo esc_attr( $timecheck_value_attr ); ?>" name="timecheck_value" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-timecheck-value' ); ?>" /> <?php esc_html_e('seconds', 'contact-form-7-honeypot'); ?>
            </fieldset>
        </div>

        <footer class="insert-box">
            <?php
                $tag->print( 'insert_box_content' );
            ?>
        </footer>
        <?php
    }
}
