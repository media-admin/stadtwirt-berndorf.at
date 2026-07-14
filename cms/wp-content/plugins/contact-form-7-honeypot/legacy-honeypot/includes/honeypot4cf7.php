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
		return (bool) $cf7apps_settings['honeypot']['is_enabled'];
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
		return isset( $timecheck_enabled[0] ) && 'false' !== $timecheck_enabled[0];
	}

	return 'false' !== $timecheck_enabled && false !== $timecheck_enabled;
}

/**
 * Create a fresh honeypot token and store its transient.
 *
 * @param string $field_name         Honeypot field name.
 * @param mixed  $timecheck_enabled  Time-check enabled option.
 * @param mixed  $timecheck_value    Time-check seconds.
 * @return array{random_hash:int,field_name:string,transient_name:string}
 */
function honeypot4cf7_create_token( $field_name, $timecheck_enabled = null, $timecheck_value = null ) {
	$honeypot4cf7_config = honeypot4cf7_get_config();

	if ( null === $timecheck_enabled ) {
		$timecheck_enabled = $honeypot4cf7_config['timecheck_enabled'];
	}

	if ( null === $timecheck_value ) {
		$timecheck_value = $honeypot4cf7_config['timecheck_value'];
	}

	$dynamic_honeypot_name = sanitize_key( wp_generate_password( 12, false, false ) );
	$random_hash           = wp_rand( 10000000, 99999999 );
	$transient_name        = $field_name . '-' . $random_hash;
	$transient_attrs       = array(
		'expected_hp_name' => $dynamic_honeypot_name,
		'time_start'       => time(),
	);

	if ( honeypot4cf7_timecheck_is_enabled( $timecheck_enabled ) ) {
		$transient_attrs['time_check'] = is_array( $timecheck_value )
			? (int) reset( $timecheck_value )
			: (int) $timecheck_value;
	}

	set_transient( $transient_name, $transient_attrs, HOUR_IN_SECONDS );

	return array(
		'random_hash'    => $random_hash,
		'field_name'     => $dynamic_honeypot_name,
		'transient_name' => $transient_name,
	);
}

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
		'css'				=> apply_filters( 'wpcf7_honeypot_container_css', 'display: block;
		    width: 0px;
		    height: 0px;
		    padding: 0px;
		    border: 1px solid transparent;
		    display: block;
		    overflow: hidden;
		    '
        ),
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

	$html = '<span id="' . esc_attr( $wrapper_id ) . '" class="wpcf7-form-control-wrap ' . esc_attr( $atts['name'] ) . '-wrap" data-cf7apps-honeypot="' . esc_attr( $atts['name'] ) . '" ' . $el_css . '>';

	$html .= '<input type="hidden" name="' . esc_attr( $atts['name'] ) . '-random-hash" value="' . esc_attr( (string) $token['random_hash'] ) . '">';

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
	    size="40"
	    autocomplete="'. $autocomplete_value . '"
	    tabindex="1000"
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
	if ( ! is_array( $posted_data ) ) {
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

		$random_hash      = isset( $_POST[ $hpid . '-random-hash' ] ) ? (string) wp_unslash( $_POST[ $hpid . '-random-hash' ] ) : '';
		$expected_hp_name = '';
		$dynamic_value    = '';

		if ( '' !== $random_hash ) {
			$transient_data = get_transient( $hpid . '-' . $random_hash );

			if ( is_array( $transient_data ) && ! empty( $transient_data['expected_hp_name'] ) ) {
				$expected_hp_name = sanitize_key( $transient_data['expected_hp_name'] );
			}
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

	$cf7form = WPCF7_ContactForm::get_current();
	$form_tags = $cf7form->scan_form_tags();
	$hp_ids    = array();
	
	foreach ( $form_tags as $tag ) {
		if ( $tag->type == 'honeypot' ) {
			$hp_ids[] = $tag->name;
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
		
		// Validate random hash exists and matches expected format (8-9 digits)
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
		
        $transient_data = get_transient( $hpid . '-' . $random_hash );
        
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

        $timecheck_start = isset( $transient_data['time_start'] ) ? (int) $transient_data['time_start'] : 0;
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

        // Time check only applies when the token was created with time_check (setting/tag enabled).
        $timecheck_active = isset( $transient_data['time_check'] ) && (int) $transient_data['time_check'] > 0;

        if ( $timecheck_active && $timecheck_value <= 0 ) {
            $timecheck_value = isset( $honeypot4cf7_config['timecheck_value'] ) ? (int) $honeypot4cf7_config['timecheck_value'] : 4;
        }
        
        if ( $timecheck_start > 0 ) {
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

                return $spam; // There's no need to go on, this is most likely a bot submission.
            }
        } else {
            // No time check start found in transient
            $spam = true;

            if ( $submission ) {
                $submission->add_spam_log( array(
                    'agent' => 'honeypot',
                    'reason' => sprintf(
                        /* translators: %s: honeypot field ID */
                        __( 'Honeypot detected form submitted without time check. Field ID = %s', 'contact-form-7-honeypot' ),
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

            return $spam; // There's no need to go on, this is most likely a bot submission.
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

    if( ! $cf7apps_settings || ( $cf7apps_settings && ! empty( $cf7apps_settings['honeypot']['is_enabled'] ) && $cf7apps_settings['honeypot']['is_enabled'] ) ) {
        $tag_generator = WPCF7_TagGenerator::get_instance();
	    $tag_generator->add( 'honeypot', __( 'Honeypot', 'contact-form-7-honeypot' ), 'honeypot4cf7_form_tag_generator', array( 'version' => 2 ) );
    }
}

function honeypot4cf7_form_tag_generator( $contact_form, $args = '' ) {
	$args = wp_parse_args( $args, array() );
	$description = __( 'Generate a form-tag for a spam-stopping honeypot field. For more details, see %s.', 'contact-form-7-honeypot' );
	$desc_link = '<a href="https://wordpress.org/plugins/contact-form-7-honeypot/" target="_blank">' . __( 'Honeypot for CF7', 'contact-form-7-honeypot' ) . '</a>';

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
                            <input type="checkbox" name="timecheck_enabled:true" id="<?php echo esc_attr( $args['content'] . '-timecheck-enabled' ); ?>" class="option" />
                            <input type="number" step="1" min="1" placeholder="4" value="" name="timecheck_value" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-timecheck-value' ); ?>" /> <?php esc_html_e('seconds', 'contact-form-7-honeypot'); ?>
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
                <input type="checkbox" data-tag-option="timecheck_enabled:" data-tag-part="option" name="timecheck_enabled:true" value="true" id="<?php echo esc_attr( $args['content'] . '-timecheck-enabled' ); ?>" class="option" />
                <input data-tag-option="timecheck_value:" data-tag-part="option" type="number" step="1" min="1" placeholder="4" value="" name="timecheck_value" class="oneline option" id="<?php echo esc_attr( $args['content'] . '-timecheck-value' ); ?>" /> <?php esc_html_e('seconds', 'contact-form-7-honeypot'); ?>
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

/**
 * Delete transient data after form submission is successful
 * 
 * @since 3.1.0
 */
function honeypot4cf7_delete_transient_data() {
    $cf7form = WPCF7_ContactForm::get_current();
    $form_tags = $cf7form->scan_form_tags();
    $hp_ids = array();

    foreach ( $form_tags as $tag ) {
        if ( $tag->type == 'honeypot' ) {
            $hp_ids[] = $tag->name;
        }
    }

    
    foreach ( $hp_ids as $hpid ) {
        if ( isset( $_POST[ $hpid . '-random-hash' ] ) ) {
            $key = $hpid . '-' . $_POST[ $hpid . '-random-hash' ];
            $data = get_transient( $key );
            delete_transient( $key );

            if ( is_array( $data ) ) {
                $data['time_start'] = time();
                set_transient( $key, $data, 60*60*2 );
            }
        }
    }

    return true;
}

add_action( 'wpcf7_mail_sent', 'honeypot4cf7_delete_transient_data' );