<?php
/**
 * Honeypot refill handlers for CF7 REST refill / feedback responses.
 *
 * @package Contact_Form_7_Honeypot
 * @since 3.6.1
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wpcf7_refill_response', 'honeypot4cf7_ajax_refill', 10, 1 );
add_filter( 'wpcf7_feedback_response', 'honeypot4cf7_ajax_refill', 10, 1 );

/**
 * Add fresh honeypot tokens to CF7 refill and feedback API responses.
 *
 * @since 3.6.1
 *
 * @param array|mixed $items Response items.
 * @return array|mixed
 */
function honeypot4cf7_ajax_refill( $items ) {
	if ( ! is_array( $items ) || ! honeypot4cf7_is_app_enabled() ) {
		return $items;
	}

	$tags = wpcf7_scan_form_tags( array( 'type' => 'honeypot' ) );

	if ( empty( $tags ) ) {
		return $items;
	}

	$honeypot4cf7_config = honeypot4cf7_get_config();
	$refill              = array();

	foreach ( $tags as $tag ) {
		$name = $tag->name;

		if ( empty( $name ) ) {
			continue;
		}

		$timecheck_enabled = $tag->get_option( 'timecheck_enabled' )
			? $tag->get_option( 'timecheck_enabled' )
			: $honeypot4cf7_config['timecheck_enabled'];

		$timecheck_value = $tag->get_option( 'timecheck_value' );
		$timecheck_value = $timecheck_value
			? reset( $timecheck_value )
			: $honeypot4cf7_config['timecheck_value'];

		$token = honeypot4cf7_create_token( $name, $timecheck_enabled, $timecheck_value );

		$refill[ $name ] = array(
			'random_hash' => $token['random_hash'],
			'field_name'  => $token['field_name'],
		);
	}

	if ( ! empty( $refill ) ) {
		$items['honeypot'] = $refill;
	}

	return $items;
}
