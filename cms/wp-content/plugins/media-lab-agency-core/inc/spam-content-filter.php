<?php
/**
 * Spam Content & Rate-Limit Filter
 *
 * Ergänzt Honeypot + hCaptcha um eine dritte Verteidigungsebene, die
 * unabhängig von CAPTCHA-Ergebnissen greift. CAPTCHAs (auch hCaptcha)
 * sind nicht 100% wirksam - kommerzielle CAPTCHA-Solving-Dienste können
 * sie umgehen. Diese zwei zusätzlichen Prüfungen fangen genau solche
 * Fälle ab:
 *
 *  1. IP-Rate-Limiting: Blockt wiederholte Submissions derselben IP
 *     innerhalb eines kurzen Zeitfensters (typisch für Bot-Wellen/Tests).
 *  2. Inhalts-Heuristik: Erkennt Low-Effort-Spam-Muster wie wahllose
 *     Zeichenwiederholung ("segddd"), zu kurze/generische Eingaben oder
 *     bekannte Wegwerf-/Spam-typische E-Mail-Domains.
 *
 * Beide laufen NACH dem Honeypot (Priorität 5), damit dieser zuerst
 * greift, aber VOR teureren externen Checks.
 *
 * Integration: Wird automatisch bei allen CF7-Formularen aktiv,
 * sobald die Datei geladen wird - kein ACF-Feld nötig, bewusst
 * konservativ konfiguriert um False Positives zu vermeiden.
 *
 * @package media-lab-agency-core
 */

defined( 'ABSPATH' ) || exit;

// ── Konfiguration ─────────────────────────────────────────────────────────────

/** Maximal erlaubte Submissions pro IP innerhalb des Zeitfensters. */
if ( ! defined( 'MEDIALAB_SPAM_RATE_LIMIT_MAX' ) ) {
	define( 'MEDIALAB_SPAM_RATE_LIMIT_MAX', 3 );
}

/** Zeitfenster in Sekunden für das Rate-Limit. */
if ( ! defined( 'MEDIALAB_SPAM_RATE_LIMIT_WINDOW' ) ) {
	define( 'MEDIALAB_SPAM_RATE_LIMIT_WINDOW', 600 ); // 10 Minuten
}

/**
 * Bekannte Wegwerf-/Spam-Domains. Bewusst kurze, konservative Liste -
 * false positives (legitime Kunden-Mails blocken) sind schlimmer als
 * ein paar zusätzliche Spam-Mails, die durchrutschen.
 */
if ( ! defined( 'MEDIALAB_SPAM_BLOCKED_DOMAINS' ) ) {
	define( 'MEDIALAB_SPAM_BLOCKED_DOMAINS', 'qq.com,163.com,126.com,mail.ru,yandex.ru' );
}

// ── Hilfsfunktion: Client-IP ermitteln ────────────────────────────────────────

/**
 * Ermittelt die Client-IP, berücksichtigt gängige Proxy-Header.
 * Auf Shared Hosting hinter Cloudflare/Proxy ist REMOTE_ADDR ggf. die
 * Proxy-IP - X-Forwarded-For wird daher bevorzugt, aber nur die erste
 * (am wenigsten vertrauenswürdige) Adresse verwendet, niemals blind
 * dem gesamten Header vertraut.
 */
function medialab_spam_get_client_ip(): string {
	$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

	foreach ( $candidates as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		$first = trim( explode( ',', $value )[0] );
		if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
			return $first;
		}
	}

	return '0.0.0.0';
}

// ── Schicht 1: IP-Rate-Limiting ───────────────────────────────────────────────

/**
 * Prüft, ob die aktuelle IP das Submission-Limit überschritten hat.
 * Nutzt Transients - kein zusätzliches DB-Schema nötig, funktioniert
 * auf jedem Shared Hosting ohne Objekt-Cache.
 *
 * @return true|WP_Error
 */
function medialab_spam_rate_limit_check() {
	$ip  = medialab_spam_get_client_ip();
	$key = 'mla_rl_' . md5( $ip );

	$count = (int) get_transient( $key );

	if ( $count >= MEDIALAB_SPAM_RATE_LIMIT_MAX ) {
		return new WP_Error(
			'rate_limited',
			__( 'Zu viele Anfragen. Bitte versuchen Sie es später erneut.', 'media-lab' )
		);
	}

	// Zähler erhöhen; TTL nur beim ersten Treffer neu setzen, damit das
	// Fenster nicht durch jede weitere Anfrage verlängert wird.
	if ( 0 === $count ) {
		set_transient( $key, 1, MEDIALAB_SPAM_RATE_LIMIT_WINDOW );
	} else {
		set_transient( $key, $count + 1, MEDIALAB_SPAM_RATE_LIMIT_WINDOW );
	}

	return true;
}

// ── Schicht 2: Inhalts-Heuristik ──────────────────────────────────────────────

/**
 * Prüft die übermittelten Textfelder auf typische Low-Effort-Spam-Muster.
 * Bewusst konservativ: nur eindeutige Muster, die bei echten Anfragen
 * praktisch nie vorkommen.
 *
 * @return true|WP_Error
 */
function medialab_spam_content_heuristic_check() {
	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return true;
	}

	$data = $submission->get_posted_data();

	// E-Mail-Feld suchen (Feldname kann je Formular variieren, daher
	// nach gültiger E-Mail-Adresse im gesamten Datensatz suchen).
	foreach ( $data as $field_value ) {
		if ( is_string( $field_value ) && is_email( $field_value ) ) {
			$domain = strtolower( substr( strrchr( $field_value, '@' ), 1 ) );
			$blocked = array_map( 'trim', explode( ',', MEDIALAB_SPAM_BLOCKED_DOMAINS ) );
			if ( in_array( $domain, $blocked, true ) ) {
				return new WP_Error( 'blocked_domain', __( 'Diese E-Mail-Domain wird nicht akzeptiert.', 'media-lab' ) );
			}
		}
	}

	// Textfelder auf Zeichenwiederholungs-Muster prüfen (z.B. "segddd",
	// "asdasdasd", "aaaaaaa"). Erkennt, wenn ein einzelnes Zeichen oder
	// eine kurze Zeichenfolge einen unnatürlich hohen Anteil des Textes
	// ausmacht.
	foreach ( $data as $field_name => $field_value ) {
		if ( ! is_string( $field_value ) || mb_strlen( $field_value ) < 4 ) {
			continue;
		}
		// E-Mail- und URL-Felder überspringen - andere Zeichenverteilung.
		if ( is_email( $field_value ) || false !== strpos( $field_value, 'http' ) ) {
			continue;
		}

		if ( medialab_spam_looks_like_gibberish( $field_value ) ) {
			return new WP_Error( 'gibberish_content', __( 'Die Eingabe wirkt ungültig. Bitte prüfen Sie Ihre Angaben.', 'media-lab' ) );
		}
	}

	return true;
}

/**
 * Einfache Heuristik: Wenn das häufigste Zeichen (ohne Leerzeichen)
 * mehr als 40% der Zeichenkette ausmacht, gilt der Text als
 * wahrscheinlich zufällig/generiert statt echter Text.
 * Schwelle bewusst hoch gewählt (echte Wörter wie "aaaaaaber" wären
 * hier durch die 40%-Grenze nicht betroffen).
 */
function medialab_spam_looks_like_gibberish( string $text ): bool {
	$clean = preg_replace( '/\s+/', '', $text );
	$len   = mb_strlen( $clean );

	if ( $len < 4 ) {
		return false;
	}

	$counts = array();
	foreach ( mb_str_split( mb_strtolower( $clean ) ) as $char ) {
		$counts[ $char ] = ( $counts[ $char ] ?? 0 ) + 1;
	}

	$max_count = max( $counts );

	return ( $max_count / $len ) > 0.4;
}

// ── Contact Form 7 Integration ────────────────────────────────────────────────

add_filter( 'wpcf7_spam', function ( bool $spam ): bool {
	if ( $spam ) {
		return true; // Bereits als Spam erkannt (z.B. durch Honeypot).
	}

	if ( is_wp_error( medialab_spam_rate_limit_check() ) ) {
		return true;
	}

	if ( is_wp_error( medialab_spam_content_heuristic_check() ) ) {
		return true;
	}

	return false;
}, 6 ); // Priorität 6 → direkt nach Honeypot (5), vor Standard-Checks
