<?php
/**
 * WP All Import - Custom Image Download via wp_remote_get()
 *
 * Ersetzt WP All Imports eingebauten, rohen cURL-Downloader für Bilder.
 * Manche CDNs/WAFs blocken den erkennbaren User-Agent
 * "WP All Import (version:X)" stillschweigend (TCP/TLS-Handshake läuft
 * durch, Server antwortet nie - Tarpitting statt 403). Symptom:
 * "cURL error 28/56" trotz funktionierendem Timeout-Fix. Beobachtet bei
 * union-glashuette.com im Janecka-Projekt.
 *
 * Da WP All Import für Bilder nicht WordPress' WP_Http-Klasse nutzt
 * (sondern einen eigenen rohen cURL-Aufruf), lässt sich der User-Agent
 * NICHT über WordPress-Hooks wie http_api_curl oder http_headers_useragent
 * überschreiben - das wurde getestet und funktioniert nicht.
 *
 * Diese Funktion lädt das Bild stattdessen komplett über wp_remote_get()
 * herunter (Standard-WordPress-User-Agent, wird von den meisten
 * CDNs/WAFs nicht blockiert), speichert es in
 * wp-content/uploads/wpallimport/files/ und gibt nur den Dateinamen
 * zurück. WP All Import erkennt Dateien in diesem Ordner automatisch als
 * lokal vorhanden - eine URL-Validierung (die bei rohen Pfaden oder
 * file:// fehlschlägt) entfällt dadurch.
 *
 * ACHTUNG - MANUELLE EINRICHTUNG PRO PROJEKT ERFORDERLICH:
 * Dieser Fix wirkt NICHT automatisch, nur weil das Plugin aktiv ist.
 * Für jedes Projekt, das ihn braucht, müssen im WP-All-Import-Template
 * folgende zwei Schritte manuell gemacht werden:
 *
 *   1. Bild-Feld im Import-Template auf folgende Syntax umstellen:
 *        [custom_file_download({Bildfeld}, "png")]
 *      statt der reinen URL-Zuordnung. "png" ggf. an den tatsächlichen
 *      Bildtyp anpassen, oder "" übergeben (dann wird die Endung
 *      automatisch aus der Quell-URL abgeleitet).
 *
 *   2. In den Image Options des Imports die Checkbox
 *        "Use images currently uploaded in
 *         wp-content/uploads/wpallimport/files/"
 *      aktivieren.
 *
 * Ohne diese beiden Schritte greift der Fix nicht, da WP All Import sonst
 * weiterhin den eingebauten (blockierten) Downloader nutzt.
 *
 * Nur bei Bedarf einsetzen: Wenn der Timeout-Fix allein nicht reicht und
 * der Verdacht auf UA-Blocking besteht (z.B. via curl -A "WP All Import
 * (version:X)" gegen die Bild-URL testen - hängt es, ist UA-Blocking
 * wahrscheinlich; funktioniert normaler curl-Aufruf ohne -A problemlos,
 * bestätigt das den Verdacht zusätzlich).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'PMXI_VERSION' ) && ! function_exists( 'custom_file_download' ) ) {

	/**
	 * Lädt eine Bild-URL per wp_remote_get() herunter, speichert sie in
	 * wp-content/uploads/wpallimport/files/ und gibt den Dateinamen zurück.
	 *
	 * @param string $url Bild-URL.
	 * @param string $ext Ziel-Dateiendung ohne Punkt, z.B. "png". Leer lassen,
	 *                    um die Endung automatisch aus der URL abzuleiten.
	 * @return string|false Dateiname oder false bei Fehler.
	 */
	function custom_file_download( $url, $ext = '' ) {
		if ( empty( $url ) ) {
			return false;
		}

		$timeout = (int) apply_filters( 'mlac_wpai_image_timeout_seconds', 30 );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => $timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'custom_file_download: Fehler bei ' . $url . ' - ' . $response->get_error_message() );
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			error_log( 'custom_file_download: HTTP ' . $code . ' bei ' . $url );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			error_log( 'custom_file_download: Leerer Response-Body bei ' . $url );
			return false;
		}

		$target_dir = WP_CONTENT_DIR . '/uploads/wpallimport/files/';
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$filename    = sanitize_file_name( basename( (string) parse_url( $url, PHP_URL_PATH ) ) );
		$target_path = $target_dir . $filename;

		file_put_contents( $target_path, $body );

		return $filename;
	}
}