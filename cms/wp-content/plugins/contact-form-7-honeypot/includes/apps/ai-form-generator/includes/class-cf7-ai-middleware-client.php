<?php
/**
 * CF7Apps — AI middleware client for CF7 tag generation.
 *
 * @package CF7Apps
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pull CF7 field tags from middleware JSON (uses raw body as last resort).
 *
 * @param array|string $payload Decoded JSON or empty.
 * @param string       $fallback_raw Raw response if JSON has no tags.
 * @return string
 */
function cf7apps_cf7_ai_parse_tags( $payload, $fallback_raw = '' ) {
	if ( ! is_array( $payload ) ) {
		return is_string( $fallback_raw ) ? cf7apps_cf7_sanitize_tags( $fallback_raw ) : '';
	}

	$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : $payload;

	foreach ( array( 'form_code', 'form_template', 'content' ) as $key ) {
		if ( ! empty( $data[ $key ] ) && is_string( $data[ $key ] ) ) {
			$out = cf7apps_cf7_sanitize_tags( $data[ $key ] );
			if ( '' !== $out ) {
				return $out;
			}
		}
	}

	return is_string( $fallback_raw ) ? cf7apps_cf7_sanitize_tags( $fallback_raw ) : '';
}

/**
 * Clean AI text into CF7 mail-tag lines (strip fences, shortcode wrapper quirks).
 *
 * @param string $content Raw model output.
 * @return string
 */
function cf7apps_cf7_sanitize_tags( $content ) {
	$content = preg_replace( '/(\\\\+n|\\\\+r)/', "\n", (string) $content );
	$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
	$content = str_replace( '\\"', '"', $content );
	$content = trim( $content );

	if ( preg_match( '/```(?:php|html|text|plaintext)?\s*\n(.*?)\n```/s', $content, $m ) ) {
		$content = trim( $m[1] );
	}

	$content = preg_replace( '/\[contact-form-7[^\]]*\].*\n?/i', '', $content );
	$content = preg_replace( '/\[radio\*\s+/i', '[radio ', $content );

	return trim( $content );
}

/**
 * HTTP client: provision-free, process-ai, license storage.
 */
class CF7Apps_Cf7Ai_Client {

	const DEFAULT_OPERATION = 'contact-form-7-honeypot';

	const EMBEDDED_PROVISION_SECRET = 'cf7apps-hfcf7-embedded-provision-2026-04-K9mNp2sLqR';

	/** Normalize site URL for middleware (scheme, host, path). */
	public static function cf7_site_url( $url ) {
		$url = untrailingslashit( trim( (string) $url ) );
		if ( '' === $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return $url;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'http';
		$host   = strtolower( (string) $parts['host'] );
		$port   = ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? trim( (string) $parts['path'] ) : '';
		$path   = untrailingslashit( $path );
		$out    = $scheme . '://' . $host . $port;
		if ( '' !== $path && '/' !== $path ) {
			$out .= $path;
		}

		return $out;
	}

	/** License key, operation slug, site URL, middleware base URL. */
	public static function mw_config() {
		$license = '';
		if ( defined( 'CF7APPS_AI_MIDDLEWARE_LICENSE_KEY' ) && CF7APPS_AI_MIDDLEWARE_LICENSE_KEY !== '' && CF7APPS_AI_MIDDLEWARE_LICENSE_KEY !== null ) {
			$license = trim( (string) CF7APPS_AI_MIDDLEWARE_LICENSE_KEY );
		}
		if ( '' === $license ) {
			$license = get_option( 'cf7apps_ai_middleware_license_key', '' );
			$license = is_string( $license ) ? trim( $license ) : '';
		}
		if ( '' === $license ) {
			$all = get_option( 'cf7apps_settings', array() );
			$app = isset( $all['cf7-ai-form-generator'] ) && is_array( $all['cf7-ai-form-generator'] ) ? $all['cf7-ai-form-generator'] : array();
			if ( ! empty( $app['middleware_license_key'] ) ) {
				$license = trim( (string) $app['middleware_license_key'] );
			}
		}

		$op = self::DEFAULT_OPERATION;
		if ( defined( 'CF7APPS_AI_MIDDLEWARE_OPERATION' ) && CF7APPS_AI_MIDDLEWARE_OPERATION !== '' && CF7APPS_AI_MIDDLEWARE_OPERATION !== null ) {
			$op = trim( (string) CF7APPS_AI_MIDDLEWARE_OPERATION );
		}
		if ( '' === $op ) {
			$op = self::DEFAULT_OPERATION;
		}
		$op = (string) apply_filters( 'cf7apps_ai_middleware_operation', $op );

		$site = untrailingslashit( home_url( '/' ) );
		if ( '' === $site ) {
			$site = untrailingslashit( site_url( '/' ) );
		}
		$site = self::cf7_site_url( $site );
		$site = (string) apply_filters( 'cf7apps_ai_middleware_site_url', $site );

		$base = defined( 'CF7APPS_AI_MIDDLEWARE_BASE_URL' ) ? (string) CF7APPS_AI_MIDDLEWARE_BASE_URL : '';
		$base = (string) apply_filters( 'cf7apps_ai_middleware_base_url', $base );

		return array(
			'license_key' => $license,
			'operation'   => $op,
			'site_url'    => $site,
			'base_url'    => untrailingslashit( $base ),
		);
	}

	/** Whether middleware base URL is non-empty. */
	public static function mw_base_ok() {
		return '' !== self::mw_config()['base_url'];
	}

	/** Full URL for path under /api/ (handles base with or without trailing /api). */
	public static function mw_url( $base_url, $endpoint ) {
		$base     = untrailingslashit( (string) $base_url );
		$endpoint = trim( (string) $endpoint, '/' );
		if ( '' === $base || '' === $endpoint ) {
			return '';
		}
		$base_lower = strtolower( $base );
		if ( strlen( $base_lower ) >= 4 && substr( $base_lower, -4 ) === '/api' ) {
			$url = $base . '/' . $endpoint;
		} else {
			$url = $base . '/api/' . $endpoint;
		}

		return (string) apply_filters( 'cf7apps_ai_middleware_api_url', $url, $base_url, $endpoint );
	}

	/** Stored or constant license key present. */
	public static function has_license() {
		return '' !== trim( self::mw_config()['license_key'] );
	}

	/** Non-empty getenv / $_ENV value or ''. */
	private static function env_str( $name ) {
		$v = getenv( $name );
		if ( is_string( $v ) && $v !== '' ) {
			return $v;
		}
		if ( isset( $_ENV[ $name ] ) && is_string( $_ENV[ $name ] ) && $_ENV[ $name ] !== '' ) {
			return (string) $_ENV[ $name ];
		}

		return '';
	}

	/** Secret for POST provision-free (must match Laravel). */
	public static function provision_secret() {
		if ( defined( 'CF7APPS_PROVISION_SECRET' ) && CF7APPS_PROVISION_SECRET !== '' && CF7APPS_PROVISION_SECRET !== null ) {
			return (string) apply_filters( 'cf7apps_ai_provision_secret', (string) CF7APPS_PROVISION_SECRET );
		}
		if ( defined( 'CF7APPS_AI_PROVISION_SECRET' ) && CF7APPS_AI_PROVISION_SECRET !== '' && CF7APPS_AI_PROVISION_SECRET !== null ) {
			return (string) apply_filters( 'cf7apps_ai_provision_secret', (string) CF7APPS_AI_PROVISION_SECRET );
		}
		foreach ( array( 'CF7APPS_PROVISION_SECRET', 'CF7APPS_AI_PROVISION_SECRET' ) as $key ) {
			$v = self::env_str( $key );
			if ( $v !== '' ) {
				return (string) apply_filters( 'cf7apps_ai_provision_secret', $v );
			}
		}
		$opt = get_option( 'cf7apps_ai_provision_secret', '' );
		$opt = is_string( $opt ) ? trim( $opt ) : '';
		if ( '' !== $opt ) {
			return (string) apply_filters( 'cf7apps_ai_provision_secret', $opt );
		}

		return (string) apply_filters( 'cf7apps_ai_provision_secret', self::EMBEDDED_PROVISION_SECRET );
	}

	/** Admin email + display name for Laravel user on first provision. */
	public static function wp_owner() {
		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

		$admin_name = '';
		$user       = wp_get_current_user();
		if ( $user && $user->ID ) {
			$admin_name = trim( (string) $user->display_name );
		}
		if ( '' === $admin_name ) {
			$admin_name = trim( (string) get_bloginfo( 'name' ) );
		}
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && mb_strlen( $admin_name, 'UTF-8' ) > 255 ) {
			$admin_name = mb_substr( $admin_name, 0, 255, 'UTF-8' );
		} elseif ( strlen( $admin_name ) > 255 ) {
			$admin_name = substr( $admin_name, 0, 255 );
		}

		return array(
			'admin_email' => $admin_email,
			'admin_name'  => $admin_name,
		);
	}

	/** Call provision-free once if no license key; returns true or WP_Error. */
	public static function ensure_license() {
		if ( self::has_license() ) {
			return true;
		}

		$secret = self::provision_secret();
		if ( '' === trim( $secret ) ) {
			return new WP_Error(
				'cf7apps_ai_license_or_secret_required',
				__( 'The middleware requires a license key, and this site could not obtain one (empty provision secret after filters). Set CF7APPS_AI_MIDDLEWARE_LICENSE_KEY or fix CF7APPS_AI_PROVISION_SECRET.', 'cf7apps' )
			);
		}

		$config = self::mw_config();
		if ( '' === $config['base_url'] ) {
			return new WP_Error(
				'cf7apps_ai_no_base',
				__( 'AI middleware base URL is not set. Define CF7APPS_AI_MIDDLEWARE_BASE_URL in wp-config.php.', 'cf7apps' )
			);
		}

		$site_url = self::cf7_site_url( $config['site_url'] );

		$url      = self::mw_url( $config['base_url'], 'licenses/provision-free' );
		$identity = self::wp_owner();
		$body     = array(
			'site_url'    => $site_url,
			'plugin_slug' => $config['operation'],
		);
		if ( '' !== $identity['admin_email'] ) {
			$body['admin_email'] = $identity['admin_email'];
		}
		if ( '' !== $identity['admin_name'] ) {
			$body['admin_name'] = $identity['admin_name'];
		}

		$args = apply_filters(
			'cf7apps_ai_provision_request_args',
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'               => 'application/json; charset=utf-8',
					'Accept'                     => 'application/json',
					'X-CF7Apps-Provision-Secret' => $secret,
				),
				'body'    => wp_json_encode( $body ),
			),
			$body
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'cf7apps_ai_provision_http',
				sprintf(
					/* translators: %s error message */
					__( 'Could not provision license: %s', 'cf7apps' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		$license_from_response = self::license_from_json( $json );

		if ( $code >= 200 && $code < 300 && '' !== $license_from_response ) {
			self::save_license( $license_from_response );

			return true;
		}

		$msg = self::api_msg( $json, __( 'License provisioning failed.', 'cf7apps' ) );

		return new WP_Error( 'cf7apps_ai_provision_api', $msg, array( 'status' => $code, 'raw' => $raw ) );
	}

	/** License key from provision-free JSON data. */
	private static function license_from_json( $json ) {
		if ( is_array( $json ) && ! empty( $json['data']['license_key'] ) && is_string( $json['data']['license_key'] ) ) {
			return trim( $json['data']['license_key'] );
		}

		return '';
	}

	/** User-facing string from API error JSON. */
	private static function api_msg( $json, $default ) {
		if ( ! is_array( $json ) ) {
			return $default;
		}
		if ( ! empty( $json['message'] ) && is_string( $json['message'] ) ) {
			return $json['message'];
		}
		if ( ! empty( $json['errors'] ) && is_array( $json['errors'] ) ) {
			$parts = array();
			foreach ( $json['errors'] as $messages ) {
				if ( is_array( $messages ) ) {
					foreach ( $messages as $m ) {
						if ( is_string( $m ) ) {
							$parts[] = $m;
						}
					}
				} elseif ( is_string( $messages ) ) {
					$parts[] = $messages;
				}
			}
			if ( $parts ) {
				return implode( ' ', $parts );
			}
		}

		return $default;
	}

	/** Persist key to option + cf7apps_settings. */
	public static function save_license( $license_key ) {
		$license_key = sanitize_text_field( $license_key );
		if ( '' === $license_key ) {
			return false;
		}

		update_option( 'cf7apps_ai_middleware_license_key', $license_key, false );

		$all = get_option( 'cf7apps_settings', array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		if ( ! isset( $all['cf7-ai-form-generator'] ) || ! is_array( $all['cf7-ai-form-generator'] ) ) {
			$all['cf7-ai-form-generator'] = array();
		}
		$all['cf7-ai-form-generator']['middleware_license_key'] = $license_key;

		return (bool) update_option( 'cf7apps_settings', $all, false );
	}

	/** Run process-ai; returns CF7 tags or WP_Error. */
	public static function cf7_tags_from_ai( $user_prompt ) {
		$config = self::mw_config();

		if ( '' === $config['base_url'] ) {
			return new WP_Error(
				'cf7apps_ai_no_base',
				__( 'AI middleware base URL is not set. Define CF7APPS_AI_MIDDLEWARE_BASE_URL in wp-config.php.', 'cf7apps' )
			);
		}

		$ensured = self::ensure_license();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		$config = self::mw_config();
		if ( '' === trim( $config['license_key'] ) ) {
			return new WP_Error(
				'cf7apps_ai_no_license',
				__( 'No license key is available for the middleware. Check provision-free response or set CF7APPS_AI_MIDDLEWARE_LICENSE_KEY.', 'cf7apps' )
			);
		}

		$url = self::mw_url( $config['base_url'], 'process-ai' );

		$body = array(
			'site_url'  => untrailingslashit( $config['site_url'] ),
			'prompt'    => $user_prompt,
			'operation' => $config['operation'],
		);

		$body = apply_filters( 'cf7apps_ai_middleware_request_body', $body, $user_prompt );

		$args = apply_filters(
			'cf7apps_ai_middleware_request_args',
			array(
				'timeout' => 90,
				'headers' => array(
					'Content-Type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
					'X-License-Key' => $config['license_key'],
				),
				'body'    => wp_json_encode( $body ),
			),
			$body
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'cf7apps_ai_http',
				sprintf(
					/* translators: %s error message */
					__( 'Could not reach AI server: %s', 'cf7apps' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( ! is_array( $json ) ) {
			return new WP_Error(
				'cf7apps_ai_bad_response',
				__( 'AI server returned an invalid response.', 'cf7apps' )
			);
		}

		if ( $code >= 400 || ( isset( $json['status'] ) && 'failed' === $json['status'] ) ) {
			$msg      = $json['message'] ?? $json['error'] ?? __( 'AI request failed.', 'cf7apps' );
			$err_code = ( 402 === (int) $code ) ? 'no_middleware_credits' : 'cf7apps_ai_api';

			return new WP_Error(
				$err_code,
				is_string( $msg ) ? $msg : __( 'AI request failed.', 'cf7apps' ),
				array( 'status' => $code )
			);
		}

		$tags = cf7apps_cf7_ai_parse_tags( $json, $raw );

		if ( '' === trim( $tags ) ) {
			return new WP_Error(
				'cf7apps_ai_empty',
				__( 'AI returned no form code. Try a clearer description or check middleware logs.', 'cf7apps' )
			);
		}

		return $tags;
	}
}
