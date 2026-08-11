<?php
/**
 * WP All Import - Image Download Timeout Fix
 *
 * Erhöht den Timeout für den Bilder-Download von WP All Import über den
 * dedizierten Filter 'pmxi_image_download_timeout' (30s statt Default 5s).
 * Verhindert "cURL error 28: Operation timed out after 5001 milliseconds"
 * bei langsamen externen Bildquellen.
 *
 * Wirkt ausschließlich auf den Bilder-Download von WP All Import, kein
 * Effekt auf andere HTTP-Requests der Website.
 *
 * Siehe auch: inc/integrations/wp-all-import-custom-download.php für den
 * zusätzlichen Fix bei User-Agent-basiertem Blocking durch CDNs/WAFs.
 *
 * Override falls 30s nicht reichen:
 *   add_filter( 'mlac_wpai_image_timeout_seconds', fn() => 60 );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'PMXI_VERSION' ) && ! function_exists( 'mlac_wpai_image_timeout_value' ) ) {

	/**
	 * Liefert den Timeout-Wert in Sekunden für den WP-All-Import-Bilder-Download.
	 * Standard: 30s (statt WP-All-Import-Default 5s).
	 * Projektspezifisch überschreibbar via 'mlac_wpai_image_timeout_seconds'.
	 *
	 * @return int
	 */
	function mlac_wpai_image_timeout_value() {
		return (int) apply_filters( 'mlac_wpai_image_timeout_seconds', 30 );
	}
	add_filter( 'pmxi_image_download_timeout', 'mlac_wpai_image_timeout_value' );
}