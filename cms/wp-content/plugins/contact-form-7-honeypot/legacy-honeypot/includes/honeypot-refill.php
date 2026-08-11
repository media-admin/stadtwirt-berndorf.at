<?php
/**
 * Honeypot refill handlers for CF7 REST refill / feedback responses.
 *
 * @package Contact_Form_7_Honeypot
 * @since 3.6.1
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'wpcf7_refill_response', 'honeypot4cf7_refill_response', 10, 1 );
add_filter( 'wpcf7_feedback_response', 'honeypot4cf7_feedback_response', 10, 2 );
add_filter( 'wpcf7_feedback_response', 'honeypot4cf7_silent_spam_response', 20, 2 );

/**
 * Add fresh honeypot tokens to CF7 refill API responses.
 *
 * @since 3.6.1
 *
 * @param array|mixed $items Response items.
 * @return array|mixed
 */
function honeypot4cf7_refill_response( $items ) {
	return honeypot4cf7_build_refill_payload( $items );
}

/**
 * Add fresh honeypot tokens to CF7 feedback API responses.
 *
 * Skips token generation when the submission was marked as spam.
 *
 * @since 3.6.1
 *
 * @param array       $items   Response items.
 * @param array       $result  Submission result.
 * @return array|mixed
 */
function honeypot4cf7_feedback_response( $items, $result ) {
	if ( is_array( $result ) && isset( $result['status'] ) && 'spam' === $result['status'] ) {
		return $items;
	}

	return honeypot4cf7_build_refill_payload( $items );
}

/**
 * Build honeypot refill payload and attach it to the response.
 *
 * @since 3.6.1
 *
 * @param array|mixed $items Response items.
 * @return array|mixed
 */
function honeypot4cf7_build_refill_payload( $items ) {
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
			'time_token'  => $token['time_token'],
		);
	}

	if ( ! empty( $refill ) ) {
		$items['honeypot'] = $refill;
	}

	return $items;
}

/**
 * Whether silent spam response is enabled in honeypot settings.
 *
 * @since 3.6.2
 *
 * @return bool
 */
function honeypot4cf7_is_silent_spam_response_enabled() {
	$cf7apps_settings = get_option( 'cf7apps_settings' );

	if ( is_array( $cf7apps_settings ) && ! empty( $cf7apps_settings['honeypot']['silent_spam_response'] ) ) {
		return true;
	}

	// Legacy Contact → Honeypot settings page (pre-migration / dual UI).
	$legacy_config = get_option( 'honeypot4cf7_config' );

	return is_array( $legacy_config ) && ! empty( $legacy_config['silent_spam_response'] );
}

/**
 * Whether the current submission spam log includes the honeypot agent.
 *
 * @since 3.6.2
 *
 * @return bool
 */
function honeypot4cf7_spam_caused_by_honeypot() {
	if ( ! class_exists( 'WPCF7_Submission' ) ) {
		return false;
	}

	$submission = WPCF7_Submission::get_instance();

	if ( ! $submission ) {
		return false;
	}

	foreach ( (array) $submission->get_spam_log() as $log ) {
		if ( is_array( $log ) && isset( $log['agent'] ) && 'honeypot' === $log['agent'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Return a success response to honeypot-blocked submissions when enabled.
 *
 * @since 3.6.2
 *
 * @param array $items  Response items.
 * @param array $result Submission result.
 * @return array
 */
function honeypot4cf7_silent_spam_response( $items, $result ) {
	if ( ! is_array( $items ) || ! is_array( $result ) ) {
		return $items;
	}

	if ( ! isset( $result['status'] ) || 'spam' !== $result['status'] ) {
		return $items;
	}

	if ( ! honeypot4cf7_is_silent_spam_response_enabled() || ! honeypot4cf7_spam_caused_by_honeypot() ) {
		return $items;
	}

	$contact_form = WPCF7_ContactForm::get_current();

	if ( ! $contact_form ) {
		return $items;
	}

	$items['status']  = 'mail_sent';
	$items['message'] = $contact_form->message( 'mail_sent_ok' );
	unset( $items['honeypot'] );

	return $items;
}
