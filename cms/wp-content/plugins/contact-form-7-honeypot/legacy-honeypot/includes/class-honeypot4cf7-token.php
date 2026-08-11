<?php
/**
 * Stateless signed honeypot tokens.
 *
 * @package Contact_Form_7_Honeypot
 * @since 3.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether signed token validation is enabled.
 *
 * @return bool
 */
function honeypot4cf7_use_signed_tokens() {
	return (bool) apply_filters( 'honeypot4cf7_use_signed_tokens', true );
}

/**
 * Signing secret for honeypot tokens.
 *
 * @return string
 */
function honeypot4cf7_get_signing_secret() {
	return wp_salt( 'cf7apps_honeypot_v1' );
}

/**
 * Max signed token age in seconds.
 *
 * @return int
 */
function honeypot4cf7_token_max_age() {
	return (int) apply_filters( 'honeypot4cf7_token_max_age', 2 * HOUR_IN_SECONDS );
}

/**
 * Whether a page-cache layer is active and honeypot refill should run on init.
 *
 * @return bool
 */
function honeypot4cf7_should_force_refill() {
	if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
		return true;
	}

	if ( class_exists( 'Cache_Enabler', false ) ) {
		return true;
	}

	if ( class_exists( 'WpFastestCache', false ) ) {
		return true;
	}

	return false;
}

/**
 * Base64url encode.
 *
 * @param string $data Raw data.
 * @return string
 */
function honeypot4cf7_base64url_encode( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * Base64url decode.
 *
 * @param string $data Encoded data.
 * @return string|false
 */
function honeypot4cf7_base64url_decode( $data ) {
	$remainder = strlen( $data ) % 4;

	if ( $remainder ) {
		$data .= str_repeat( '=', 4 - $remainder );
	}

	return base64_decode( strtr( $data, '-_', '+/' ) );
}

/**
 * Build a signed honeypot token string.
 *
 * @param string $field_id     Honeypot field ID.
 * @param string $dynamic_name Dynamic input name.
 * @param int    $issued_at    Issue timestamp.
 * @param int    $time_check   Time-check seconds (0 when disabled).
 * @param int    $nonce        Random hash nonce.
 * @return string
 */
function honeypot4cf7_build_signed_token( $field_id, $dynamic_name, $issued_at, $time_check, $nonce ) {
	$payload = implode(
		'|',
		array(
			sanitize_key( $field_id ),
			sanitize_key( $dynamic_name ),
			(string) (int) $issued_at,
			(string) (int) $time_check,
			(string) (int) $nonce,
		)
	);

	$signature = hash_hmac( 'sha256', $payload, honeypot4cf7_get_signing_secret() );

	return honeypot4cf7_base64url_encode( $payload ) . '.' . honeypot4cf7_base64url_encode( $signature );
}

/**
 * Verify a signed honeypot token.
 *
 * @param string $field_id     Expected honeypot field ID.
 * @param string $token_string Token from POST.
 * @return array|WP_Error Payload array on success.
 */
function honeypot4cf7_verify_signed_token( $field_id, $token_string ) {
	$token_string = trim( (string) $token_string );

	if ( '' === $token_string || false === strpos( $token_string, '.' ) ) {
		return new WP_Error( 'honeypot_invalid_token', __( 'Invalid honeypot token format.', 'contact-form-7-honeypot' ) );
	}

	list( $encoded_payload, $encoded_signature ) = explode( '.', $token_string, 2 );

	$payload   = honeypot4cf7_base64url_decode( $encoded_payload );
	$signature = honeypot4cf7_base64url_decode( $encoded_signature );

	if ( false === $payload || false === $signature ) {
		return new WP_Error( 'honeypot_invalid_token', __( 'Invalid honeypot token encoding.', 'contact-form-7-honeypot' ) );
	}

	$expected_signature = hash_hmac( 'sha256', $payload, honeypot4cf7_get_signing_secret() );

	if ( ! hash_equals( $expected_signature, $signature ) ) {
		return new WP_Error( 'honeypot_invalid_signature', __( 'Invalid honeypot token signature.', 'contact-form-7-honeypot' ) );
	}

	$parts = explode( '|', $payload );

	if ( 5 !== count( $parts ) ) {
		return new WP_Error( 'honeypot_invalid_payload', __( 'Invalid honeypot token payload.', 'contact-form-7-honeypot' ) );
	}

	$payload_data = array(
		'field_id'     => sanitize_key( $parts[0] ),
		'dynamic_name' => sanitize_key( $parts[1] ),
		'issued_at'    => (int) $parts[2],
		'time_check'   => (int) $parts[3],
		'nonce'        => (int) $parts[4],
	);

	if ( $payload_data['field_id'] !== sanitize_key( $field_id ) ) {
		return new WP_Error( 'honeypot_field_mismatch', __( 'Honeypot token field mismatch.', 'contact-form-7-honeypot' ) );
	}

	if ( $payload_data['issued_at'] <= 0 ) {
		return new WP_Error( 'honeypot_missing_timestamp', __( 'Honeypot token missing timestamp.', 'contact-form-7-honeypot' ) );
	}

	$now = time();

	if ( $payload_data['issued_at'] > $now ) {
		return new WP_Error( 'honeypot_future_timestamp', __( 'Honeypot token has a future timestamp.', 'contact-form-7-honeypot' ) );
	}

	$age = $now - $payload_data['issued_at'];

	if ( $age > honeypot4cf7_token_max_age() ) {
		return new WP_Error( 'honeypot_expired_token', __( 'Honeypot token has expired.', 'contact-form-7-honeypot' ) );
	}

	if ( honeypot4cf7_is_token_replay( $payload_data['nonce'] ) ) {
		return new WP_Error( 'honeypot_replay', __( 'Honeypot token replay detected.', 'contact-form-7-honeypot' ) );
	}

	return $payload_data;
}

/**
 * Whether a token nonce was already used.
 *
 * @param int $nonce Token nonce.
 * @return bool
 */
function honeypot4cf7_is_token_replay( $nonce ) {
	return (bool) get_transient( 'cf7apps_hp_used_' . hash( 'sha256', (string) (int) $nonce ) );
}

/**
 * Mark a token nonce as used.
 *
 * @param int $nonce Token nonce.
 * @return void
 */
function honeypot4cf7_mark_token_used( $nonce ) {
	set_transient(
		'cf7apps_hp_used_' . hash( 'sha256', (string) (int) $nonce ),
		1,
		honeypot4cf7_token_max_age()
	);
}

/**
 * Resolve verified token payload from POST for a honeypot field.
 *
 * @param string $hpid Honeypot field ID.
 * @return array|WP_Error|null Payload, error, or null when no token posted.
 */
function honeypot4cf7_get_verified_token_payload_from_post( $hpid ) {
	if ( ! honeypot4cf7_use_signed_tokens() ) {
		return null;
	}

	$token_key = $hpid . '-token';

	if ( ! isset( $_POST[ $token_key ] ) ) {
		return null;
	}

	$token_string = wp_unslash( (string) $_POST[ $token_key ] );

	if ( '' === trim( $token_string ) ) {
		return null;
	}

	return honeypot4cf7_verify_signed_token( $hpid, $token_string );
}
