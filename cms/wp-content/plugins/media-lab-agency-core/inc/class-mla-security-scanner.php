<?php
/**
 * Media Lab Agency Core - Security Scanner Module
 *
 * Malware/Webshell-Scanner für WP-Admin, kein SSH nötig.
 * Prüft:
 *  - Verdächtige Code-Muster in wp-content/
 *  - WP-Core-Dateiintegrität (via api.wordpress.org checksums)
 *  - Plugin-Dateiintegrität (via api.wordpress.org checksums, nur .org-Plugins)
 *  - Verwaiste/verdächtige Verzeichnisse im Webroot (z.B. OLD/, BACKUP_*, tmp_*)
 *  - Kürzlich veränderte Dateien
 *  - Unbekannte/unerwartete Administrator-Accounts
 *  - Setup-Härtung: DISALLOW_FILE_EDIT, PHP-Sperre in uploads/,
 *    WP_DEBUG-Sichtbarkeit, XML-RPC, Directory-Listing, edit_files-Capability
 *
 * Einbindung in Media Lab Agency Core:
 *   require_once __DIR__ . '/inc/class-mla-security-scanner.php';
 *   MLA_Security_Scanner::instance();
 *
 * Verzeichnis: inc/class-mla-security-scanner.php
 *
 * @package Media_Lab_Agency_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MLA_Security_Scanner {

	const OPTION_RESULTS      = 'mla_security_scan_results';
	const OPTION_WHITELIST    = 'mla_security_scan_whitelist';
	const OPTION_NOTIFY_EMAIL = 'mla_security_notify_email';
	const CRON_HOOK           = 'mla_security_weekly_scan';
	const CAPABILITY          = 'manage_options';

	private static $instance = null;

	/**
	 * Verdächtige Code-Muster. Bewusst konservativ gehalten, um False
	 * Positives zu minimieren – Kombinationen sind verräterischer als
	 * einzelne Funktionsaufrufe (die auch in legitimem Code vorkommen).
	 */
	private $suspicious_patterns = array(
		'eval_base64'        => '/eval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13|convert_uu(decode)?)\s*\(/i',
		'assert_dynamic'     => '/assert\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)/i',
		'system_from_input'  => '/(system|exec|shell_exec|passthru|proc_open|popen)\s*\(\s*\$_(POST|GET|REQUEST|COOKIE)/i',
		'preg_replace_eval'  => '/preg_replace\s*\(\s*[\'"].*\/e[\'"]/i',
		'create_func_dyn'    => '/create_function\s*\(\s*\$_(POST|GET|REQUEST)/i',
		'obfuscated_varvar'  => '/\$\{\s*[\'"]_[A-Z]+[\'"]\s*\}\s*\[/i',
		'file_put_from_post' => '/file_put_contents\s*\(\s*\$_(POST|GET|REQUEST)/i',
		'globals_injection'  => '/\$GLOBALS\s*\[\s*\$_(POST|GET|REQUEST)/i',
		'chr_concat_chain'   => '/(chr\s*\(\s*\d+\s*\)\s*\.\s*){6,}/i',
		'base64_long_blob'   => '/base64_decode\s*\(\s*[\'"][A-Za-z0-9+\/=]{300,}[\'"]\s*\)/i',
	);

	/**
	 * Verzeichnisnamen-Muster, die typischerweise auf vergessene/verwaiste
	 * Alt-Installationen oder Backups im Webroot hindeuten (genau das
	 * Muster, das den OLD/-Vorfall verursacht hat).
	 */
	private $suspicious_dir_patterns = array(
		'/^old$/i',
		'/^backup/i',
		'/^bak_/i',
		'/^tmp_[a-f0-9]{10,}$/i',
		'/^wp-?backup/i',
		'/_old$/i',
		'/^_?archive/i',
		'/^copy of /i',
		'/^staging_old/i',
	);

	/** Dateiendungen, die überhaupt gescannt werden (Performance). */
	private $scan_extensions = array( 'php', 'phtml', 'php5', 'php7', 'phar' );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_mla_run_security_scan', array( $this, 'handle_manual_scan' ) );
		add_action( 'admin_post_mla_whitelist_finding', array( $this, 'handle_whitelist_finding' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_scan_and_notify' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule_cron' ) );
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	public function register_menu() {
		add_menu_page(
			'Security Scan',
			'Security Scan',
			self::CAPABILITY,
			'mla-security-scan',
			array( $this, 'render_admin_page' ),
			'dashicons-shield-alt',
			80
		);
	}

	/* ---------------------------------------------------------------
	 * Scan-Logik
	 * ------------------------------------------------------------- */

	/**
	 * Führt den kompletten Scan aus und speichert das Ergebnis.
	 *
	 * @return array Ergebnis-Array.
	 */
	public function run_full_scan() {
		$start = microtime( true );

		$results = array(
			'timestamp'          => current_time( 'mysql' ),
			'pattern_findings'   => $this->scan_suspicious_patterns( trailingslashit( WP_CONTENT_DIR ) ),
			'core_integrity'     => $this->check_core_integrity(),
			'plugin_integrity'   => $this->check_plugin_integrity(),
			'suspicious_dirs'    => $this->detect_suspicious_directories( trailingslashit( ABSPATH ) ),
			'recent_files'       => $this->list_recently_modified_files( trailingslashit( ABSPATH ), 7 ),
			'admin_users'        => $this->audit_admin_users(),
			'health_checks'      => $this->run_health_checks(),
			'duration_seconds'   => 0, // wird unten gesetzt
		);

		$results['duration_seconds'] = round( microtime( true ) - $start, 2 );

		update_option( self::OPTION_RESULTS, $results, false );

		return $results;
	}

	/**
	 * Durchsucht rekursiv ein Verzeichnis nach verdächtigen Code-Mustern.
	 * Whitelistete Dateien (per Hash) werden übersprungen.
	 */
	public function scan_suspicious_patterns( $base_dir ) {
		$findings   = array();
		$whitelist  = get_option( self::OPTION_WHITELIST, array() );
		$base_dir   = untrailingslashit( $base_dir );

		if ( ! is_dir( $base_dir ) ) {
			return $findings;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( Exception $e ) {
			return array( array( 'error' => 'Konnte Verzeichnis nicht öffnen: ' . $e->getMessage() ) );
		}

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $this->scan_extensions, true ) ) {
				continue;
			}

			// Große Dateien überspringen (Performance) - >5MB ist untypisch für PHP.
			if ( $file->getSize() > 5 * 1024 * 1024 ) {
				continue;
			}

			$path = $file->getPathname();
			$hash = md5_file( $path );

			if ( isset( $whitelist[ $hash ] ) ) {
				continue;
			}

			$content = @file_get_contents( $path );
			if ( false === $content ) {
				continue;
			}

			foreach ( $this->suspicious_patterns as $label => $pattern ) {
				if ( preg_match( $pattern, $content ) ) {
					$findings[] = array(
						'file'    => str_replace( ABSPATH, '', $path ),
						'pattern' => $label,
						'hash'    => $hash,
						'size'    => $file->getSize(),
						'mtime'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
					);
					break; // ein Fund pro Datei reicht für die Liste
				}
			}
		}

		return $findings;
	}

	/**
	 * Vergleicht WP-Core-Dateien gegen die offiziellen Checksummen von
	 * api.wordpress.org. Funktioniert komplett ohne SSH.
	 */
	public function check_core_integrity() {
		global $wp_version;

		$locale = get_locale();
		$url    = sprintf(
			'https://api.wordpress.org/core/checksums/1.0/?version=%s&locale=%s',
			rawurlencode( $wp_version ),
			rawurlencode( $locale )
		);

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Checksummen-API nicht erreichbar: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['checksums'] ) ) {
			return array( 'error' => 'Keine Checksummen für Version ' . $wp_version . ' gefunden.' );
		}

		$mismatches = array();

		foreach ( $body['checksums'] as $rel_path => $expected_md5 ) {
			// wp-content wird bewusst übersprungen - gehört nicht zum Core-Check.
			if ( 0 === strpos( $rel_path, 'wp-content/' ) ) {
				continue;
			}

			// version.php wird bei lokalisierten Core-Paketen (z.B. de_AT,
			// de_DE) offiziell um $wp_local_package ergänzt - siehe
			// get_locale() im WP-Core selbst. Das ist ein bekannter,
			// harmloser Unterschied zur (englischen) Checksumme und kein
			// Sicherheitsproblem. Bei rein englischen Installationen greift
			// die Prüfung trotzdem, da dort kein Unterschied besteht.
			if ( 'wp-includes/version.php' === $rel_path ) {
				continue;
			}

			$full_path = ABSPATH . $rel_path;

			if ( ! file_exists( $full_path ) ) {
				$mismatches[] = array(
					'file'   => $rel_path,
					'status' => 'fehlt',
				);
				continue;
			}

			$actual_md5 = md5_file( $full_path );

			if ( $actual_md5 !== $expected_md5 ) {
				$mismatches[] = array(
					'file'   => $rel_path,
					'status' => 'verändert',
				);
			}
		}

		return array(
			'wp_version' => $wp_version,
			'checked'    => count( $body['checksums'] ),
			'mismatches' => $mismatches,
		);
	}

	/**
	 * Prüft installierte Plugins gegen die offiziellen Checksummen von
	 * WordPress.org (nur für Plugins, die aus dem offiziellen Repo
	 * stammen - eigene/premium Plugins wie das Agency Core Plugin
	 * selbst werden automatisch übersprungen).
	 */
	public function check_plugin_integrity() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$results     = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$slug = strtok( $plugin_file, '/' );

			// Prüfen ob Plugin überhaupt im .org-Repo existiert / Version holen.
			$url = sprintf(
				'https://downloads.wordpress.org/plugin-checksums/%s/%s.json',
				rawurlencode( $slug ),
				rawurlencode( $plugin_data['Version'] )
			);

			$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				// Kein .org-Plugin oder keine Checksummen verfügbar - z.B.
				// media-lab-agency-core selbst. Nicht als Fehler werten.
				$results[ $slug ] = array(
					'name'   => $plugin_data['Name'],
					'status' => 'übersprungen (kein offizielles .org-Plugin oder keine Checksummen verfügbar)',
				);
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $body['files'] ) ) {
				continue;
			}

			$plugin_dir = WP_PLUGIN_DIR . '/' . $slug . '/';
			$mismatches = array();

			foreach ( $body['files'] as $rel_path => $meta ) {
				if ( empty( $meta['md5'] ) ) {
					continue;
				}

				$full_path = $plugin_dir . $rel_path;

				if ( ! file_exists( $full_path ) ) {
					$mismatches[] = array( 'file' => $slug . '/' . $rel_path, 'status' => 'fehlt' );
					continue;
				}

				if ( md5_file( $full_path ) !== $meta['md5'] ) {
					$mismatches[] = array( 'file' => $slug . '/' . $rel_path, 'status' => 'verändert' );
				}
			}

			if ( ! empty( $mismatches ) ) {
				$results[ $slug ] = array(
					'name'       => $plugin_data['Name'],
					'status'     => 'ABWEICHUNGEN GEFUNDEN',
					'mismatches' => $mismatches,
				);
			}
		}

		return $results;
	}

	/**
	 * Findet Verzeichnisse direkt im Webroot, deren Name auf vergessene
	 * Alt-Installationen, Backups oder temporäre Upload-Ordner hindeutet.
	 */
	public function detect_suspicious_directories( $base_dir ) {
		$base_dir = untrailingslashit( $base_dir );
		$findings = array();

		if ( ! is_dir( $base_dir ) ) {
			return $findings;
		}

		$this->scan_dirs_recursive( $base_dir, $base_dir, $findings, 0, 3 );

		return $findings;
	}

	private function scan_dirs_recursive( $current_dir, $base_dir, &$findings, $depth, $max_depth ) {
		if ( $depth > $max_depth ) {
			return;
		}

		$entries = @scandir( $current_dir );
		if ( false === $entries ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$full_path = $current_dir . '/' . $entry;

			if ( ! is_dir( $full_path ) ) {
				continue;
			}

			// node_modules etc. überspringen für Performance.
			if ( in_array( $entry, array( 'node_modules', '.git', 'cache' ), true ) ) {
				continue;
			}

			// WP-Core-Verzeichnisse überspringen: werden bereits über
			// check_core_integrity() per Checksum geprüft und enthalten
			// legitime Core-Ordnernamen (z.B. wp-includes/blocks/archives),
			// die sonst die Namens-Heuristik fälschlich triggern.
			if ( 0 === $depth && in_array( $entry, array( 'wp-admin', 'wp-includes' ), true ) ) {
				continue;
			}

			foreach ( $this->suspicious_dir_patterns as $pattern ) {
				if ( preg_match( $pattern, $entry ) ) {
					$findings[] = array(
						'path'        => str_replace( $base_dir, '', $full_path ),
						'matched'     => $entry,
						'php_files'   => $this->count_php_files( $full_path ),
						'is_in_root'  => ( $depth === 0 ),
					);
					break;
				}
			}

			$this->scan_dirs_recursive( $full_path, $base_dir, $findings, $depth + 1, $max_depth );
		}
	}

	private function count_php_files( $dir ) {
		$count = 0;
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( $f->isFile() && 'php' === strtolower( pathinfo( $f->getFilename(), PATHINFO_EXTENSION ) ) ) {
					$count++;
				}
			}
		} catch ( Exception $e ) {
			return 0;
		}
		return $count;
	}

	/**
	 * Listet PHP-Dateien, die in den letzten $days Tagen verändert wurden.
	 * Nützlich um Funde des Scans mit einer breiteren Zeitfenster-Suche
	 * abzugleichen (der Hoster-Scan ist laut eigener Aussage nicht
	 * vollständig).
	 */
	public function list_recently_modified_files( $base_dir, $days = 7 ) {
		$base_dir  = untrailingslashit( $base_dir );
		$threshold = time() - ( $days * DAY_IN_SECONDS );
		$results   = array();

		if ( ! is_dir( $base_dir ) ) {
			return $results;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS )
			);
		} catch ( Exception $e ) {
			return $results;
		}

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $this->scan_extensions, true ) ) {
				continue;
			}
			if ( $file->getMTime() >= $threshold ) {
				$results[] = array(
					'file'  => str_replace( $base_dir, '', $file->getPathname() ),
					'mtime' => date( 'Y-m-d H:i:s', $file->getMTime() ),
				);
			}
		}

		usort( $results, function ( $a, $b ) {
			return strcmp( $b['mtime'], $a['mtime'] );
		} );

		// Nur die neuesten 200 zurückgeben, um die Ergebnisliste handhabbar zu halten.
		return array_slice( $results, 0, 200 );
	}

	/**
	 * Listet alle Benutzer mit Administrator-Rolle, damit unbekannte,
	 * vom Angreifer angelegte Accounts auffallen.
	 */
	public function audit_admin_users() {
		$admins = get_users( array( 'role' => 'administrator' ) );
		$out    = array();

		foreach ( $admins as $admin ) {
			$out[] = array(
				'login'        => $admin->user_login,
				'email'        => $admin->user_email,
				'registered'   => $admin->user_registered,
				'last_login'   => get_user_meta( $admin->ID, 'mla_last_login', true ) ?: 'unbekannt',
			);
		}

		return $out;
	}

	/* ---------------------------------------------------------------
	 * Setup-Health-Check
	 *
	 * Prüft grundlegende Hardening-Einstellungen, die typischerweise
	 * bei einem Go-Live vergessen werden und die genau die Art von
	 * Vorfall begünstigen, wie er bei ib-mosbacher.at auftrat (Upload
	 * einer Webshell über die Adminoberfläche, danach freie PHP-
	 * Ausführung im Uploads-Ordner).
	 * ------------------------------------------------------------- */

	public function run_health_checks() {
		$checks = array(
			$this->check_disallow_file_edit(),
			$this->check_uploads_php_blocked(),
			$this->check_wp_debug_not_public(),
			$this->check_xmlrpc(),
			$this->check_directory_listing(),
			$this->check_file_editor_capability(),
			$this->check_subdirectory_htaccess_fix(),
		);

		// null-Einträge entfernen (z.B. Subdirectory-Check, wenn das
		// Projekt gar kein site_url/home_url-Mismatch hat).
		return array_values( array_filter( $checks ) );
	}

	/**
	 * Prüft, ob DISALLOW_FILE_EDIT in wp-config.php gesetzt ist.
	 * Verhindert den Theme-/Plugin-Editor im Adminbereich - einer der
	 * häufigsten Wege, wie eine Webshell über einen kompromittierten
	 * Admin-Account platziert wird.
	 */
	private function check_disallow_file_edit() {
		$ok = defined( 'DISALLOW_FILE_EDIT' ) && true === DISALLOW_FILE_EDIT;

		return array(
			'id'          => 'disallow_file_edit',
			'label'       => 'DISALLOW_FILE_EDIT gesetzt',
			'status'      => $ok ? 'ok' : 'fail',
			'description' => $ok
				? 'Theme-/Plugin-Editor im Adminbereich ist deaktiviert.'
				: 'Theme-/Plugin-Editor ist aktiv. Ein kompromittierter Admin-Account kann darüber direkt PHP-Code einschleusen.',
			'fix' => "Zeile in wp-config.php ergänzen (vor \"That's all, stop editing!\"):\ndefine( 'DISALLOW_FILE_EDIT', true );",
		);
	}

	/**
	 * Erkennt, ob der Webserver nginx ist (z.B. über SERVER_SOFTWARE).
	 * Wird von allen Health-Checks genutzt, deren Standard-Fix auf
	 * .htaccess basiert und dadurch auf nginx wirkungslos/nicht prüfbar
	 * ist - dort geben wir 'warn' statt 'fail' zurück (keine roten X,
	 * keine Alarm-Mail für etwas, das der Kunde serverseitig im
	 * nginx-Vhost lösen muss und das automatisiert nicht verifizierbar
	 * ist).
	 */
	private function is_nginx() {
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
		return false !== strpos( $server_software, 'nginx' );
	}

	/**
	 * Prüft, ob PHP-Ausführung im uploads-Verzeichnis blockiert ist.
	 * Erkennt sowohl eine vorhandene .htaccess-Regel (Apache/LiteSpeed)
	 * als auch den Umstand, dass .htaccess auf nginx wirkungslos ist -
	 * dort muss die Regel im nginx-Vhost liegen, was wir nur als
	 * Hinweis ausgeben können, nicht automatisch prüfen.
	 */
	private function check_uploads_php_blocked() {
		$upload_dir = wp_get_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] );
		$htaccess   = $base_dir . '.htaccess';

		if ( $this->is_nginx() ) {
			return array(
				'id'          => 'uploads_php_blocked',
				'label'       => 'PHP-Ausführung in uploads/ blockiert',
				'status'      => 'warn',
				'description' => 'Server läuft unter nginx - .htaccess wird dort nicht ausgewertet. Diese Prüfung kann automatisch nicht zuverlässig erfolgen.',
				'fix'         => "Im nginx-Vhost sicherstellen, dass PHP-Dateien unterhalb von wp-content/uploads/ nicht ausgeführt werden, z.B.:\nlocation ~* /wp-content/uploads/.*\\.php$ {\n    deny all;\n}",
			);
		}

		$has_rule = false;
		if ( file_exists( $htaccess ) ) {
			$content = @file_get_contents( $htaccess );
			if ( false !== $content ) {
				// Verschiedene gängige Schreibweisen abdecken.
				$has_rule = (bool) preg_match( '/(php_flag\s+engine\s+off|deny\s+from\s+all.*\.php|<FilesMatch[^>]*\.php[^>]*>.*Require\s+all\s+denied|SetHandler\s+none|RemoveHandler\s+\.php)/is', $content );
			}
		}

		return array(
			'id'          => 'uploads_php_blocked',
			'label'       => 'PHP-Ausführung in uploads/ blockiert',
			'status'      => $has_rule ? 'ok' : 'fail',
			'description' => $has_rule
				? 'uploads/.htaccess enthält eine Regel, die PHP-Ausführung blockiert.'
				: 'Keine PHP-Sperre in uploads/.htaccess gefunden. Hochgeladene Webshells (z.B. getarnt als Bild) könnten sonst direkt ausgeführt werden.',
			'fix' => 'Datei ' . str_replace( ABSPATH, '', $htaccess ) . " anlegen/ergänzen mit:\n<Files *.php>\n    Require all denied\n</Files>",
		);
	}

	/**
	 * WP_DEBUG sollte in Produktion nicht aktiv sein bzw. Fehlerausgabe
	 * nicht öffentlich sichtbar sein (Informationsleck: Pfade, Versionen).
	 */
	private function check_wp_debug_not_public() {
		$debug_on     = defined( 'WP_DEBUG' ) && true === WP_DEBUG;
		$display_on   = defined( 'WP_DEBUG_DISPLAY' ) ? ( true === WP_DEBUG_DISPLAY ) : $debug_on;
		$problematic  = $debug_on && $display_on;

		return array(
			'id'          => 'wp_debug_not_public',
			'label'       => 'WP_DEBUG nicht öffentlich sichtbar',
			'status'      => $problematic ? 'fail' : 'ok',
			'description' => $problematic
				? 'WP_DEBUG_DISPLAY ist aktiv - PHP-Fehler inkl. Pfaden/Versionsinfos können öffentlich sichtbar sein.'
				: 'Debug-Ausgabe ist nicht öffentlich sichtbar.',
			'fix' => "In wp-config.php:\ndefine( 'WP_DEBUG', false );\n// Falls Debugging nötig ist, stattdessen in ein Log schreiben:\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );",
		);
	}

	/**
	 * XML-RPC ist eine häufig für Brute-Force (system.multicall) und
	 * DDoS-Pingback-Missbrauch genutzte Schnittstelle, wird bei den
	 * meisten Kundenprojekten nicht benötigt.
	 */
	private function check_xmlrpc() {
		$response = wp_remote_post( home_url( 'xmlrpc.php' ), array(
			'timeout' => 8,
			'body'    => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>',
			'headers' => array( 'Content-Type' => 'text/xml' ),
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'id'          => 'xmlrpc_disabled',
				'label'       => 'XML-RPC deaktiviert/eingeschränkt',
				'status'      => 'warn',
				'description' => 'xmlrpc.php konnte nicht geprüft werden: ' . $response->get_error_message(),
				'fix'         => '',
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$responsive = ( 200 === $code && false !== strpos( $body, 'methodResponse' ) );

		return array(
			'id'          => 'xmlrpc_disabled',
			'label'       => 'XML-RPC deaktiviert/eingeschränkt',
			'status'      => $responsive ? 'warn' : 'ok',
			'description' => $responsive
				? 'xmlrpc.php antwortet aktiv auf Methodenaufrufe. Falls nicht benötigt (kein Jetpack, keine Remote-Publishing-App), sollte der Zugriff gesperrt werden.'
				: 'xmlrpc.php ist nicht aktiv erreichbar oder liefert keine Methodenliste.',
			'fix' => "Falls nicht benötigt, Zugriff auf xmlrpc.php sperren.\n\nApache (.htaccess):\n<Files xmlrpc.php>\n    Require all denied\n</Files>\n\nnginx (im Vhost):\nlocation = /xmlrpc.php {\n    deny all;\n}",
		);
	}

	/**
	 * Directory-Listing sollte in wp-content/uploads deaktiviert sein,
	 * damit Angreifer nach erfolgreichem Upload nicht einfach den
	 * Ordnerinhalt durchsuchen können.
	 */
	private function check_directory_listing() {
		$upload_dir = wp_get_upload_dir();
		$response   = wp_remote_get( trailingslashit( $upload_dir['baseurl'] ), array( 'timeout' => 8 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'id'          => 'directory_listing_disabled',
				'label'       => 'Directory-Listing in uploads/ deaktiviert',
				'status'      => 'warn',
				'description' => 'Konnte nicht geprüft werden: ' . $response->get_error_message(),
				'fix'         => '',
			);
		}

		$body        = wp_remote_retrieve_body( $response );
		$looks_listing = (bool) preg_match( '/Index of \/|<title>Index of/i', $body );

		return array(
			'id'          => 'directory_listing_disabled',
			'label'       => 'Directory-Listing in uploads/ deaktiviert',
			'status'      => $looks_listing ? 'fail' : 'ok',
			'description' => $looks_listing
				? 'Der Uploads-Ordner zeigt eine Verzeichnisliste an, wenn direkt aufgerufen.'
				: 'Kein offenes Directory-Listing erkennbar.',
			'fix' => "Apache (.htaccess) im Webroot oder uploads/:\nOptions -Indexes\n\nnginx (im Vhost):\nautoindex off;",
		);
	}

	/**
	 * Prüft, ob die WP-Capability 'edit_files' irgendeiner anderen
	 * Rolle als Administrator zugewiesen wurde (z.B. durch ein Plugin
	 * versehentlich freigegeben).
	 */
	private function check_file_editor_capability() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = wp_roles();
		}

		$unexpected = array();
		foreach ( $wp_roles->roles as $role_slug => $role_data ) {
			if ( 'administrator' === $role_slug ) {
				continue;
			}
			if ( ! empty( $role_data['capabilities']['edit_files'] ) ) {
				$unexpected[] = $role_data['name'];
			}
		}

		return array(
			'id'          => 'file_edit_capability_restricted',
			'label'       => "Capability 'edit_files' nur bei Administrator",
			'status'      => empty( $unexpected ) ? 'ok' : 'fail',
			'description' => empty( $unexpected )
				? 'Keine anderen Rollen mit Datei-Bearbeitungsrechten gefunden.'
				: 'Folgende Rollen haben zusätzlich edit_files-Rechte: ' . implode( ', ', $unexpected ),
			'fix' => 'Rolle(n) prüfen und Capability über ein Rollen-Management-Plugin oder Code entfernen.',
		);
	}

	/**
	 * Prüft bei Subdirectory-Setups (site_url ≠ home_url, z.B. WP-Core
	 * liegt in /cms/, ausgeliefert wird aber von der Root-Domain), ob
	 * die nötige Rewrite-Regel im Root-.htaccess vorhanden ist. Ohne
	 * sie laufen root-relative Referenzen auf wp-content/wp-includes
	 * (z.B. aus DB-Migrationen oder hartkodierten Plugin-Pfaden) ins
	 * Leere, da diese Ordner am Root physisch nicht existieren.
	 *
	 * Gibt null zurück, wenn das Projekt kein Subdirectory-Setup nutzt
	 * - der Check ist dann schlicht nicht relevant und wird aus der
	 * Checkliste ausgeblendet statt fälschlich als "ok" zu erscheinen.
	 */
	private function check_subdirectory_htaccess_fix() {
		$site_path = (string) wp_parse_url( site_url(), PHP_URL_PATH );
		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );

		$site_path = '/' . trim( $site_path, '/' );
		$home_path = '/' . trim( $home_path, '/' );

		if ( $site_path === $home_path ) {
			return null;
		}

		// ABSPATH ist der Subdirectory-Ordner (z.B. .../public_html/cms/),
		// das Docroot liegt eine Ebene höher.
		$root_dir      = dirname( untrailingslashit( ABSPATH ) );
		$root_htaccess = $root_dir . '/.htaccess';
		$display_path  = str_replace( ABSPATH, '', $root_htaccess );

		if ( $this->is_nginx() ) {
			return array(
				'id'          => 'subdirectory_htaccess_fix',
				'label'       => 'Subdirectory-Fix für wp-content/wp-includes',
				'status'      => 'warn',
				'description' => sprintf(
					'site_url (%s) weicht von home_url (%s) ab - Server läuft unter nginx, .htaccess wird dort nicht ausgewertet. Diese Prüfung kann automatisch nicht zuverlässig erfolgen.',
					$site_path,
					$home_path
				),
				'fix' => "Im nginx-Vhost sicherstellen, dass root-relative Aufrufe von wp-content/ und wp-includes/ auf das cms/-Unterverzeichnis umgeleitet werden, z.B.:\nlocation ^~ /wp-content/ {\n    rewrite ^/wp-content/(.*)\$ /cms/wp-content/\$1 last;\n}\nlocation ^~ /wp-includes/ {\n    rewrite ^/wp-includes/(.*)\$ /cms/wp-includes/\$1 last;\n}\n\n(Genaue Syntax hängt vom bestehenden nginx-Vhost ab - im Zweifel mit dem Hosting-Provider abstimmen.)",
			);
		}

		$fix_snippet = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^wp-content/(.*)\$ /cms/wp-content/\$1 [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^wp-includes/(.*)\$ /cms/wp-includes/\$1 [L]\n</IfModule>\n\n(Muss VOR dem \"# BEGIN WordPress\"-Block stehen. Vollständiges Snippet: stubs/htaccess-subdirectory-staging.snippet im Starter Kit.)";

		if ( ! file_exists( $root_htaccess ) ) {
			return array(
				'id'          => 'subdirectory_htaccess_fix',
				'label'       => 'Subdirectory-Fix für wp-content/wp-includes',
				'status'      => 'fail',
				'description' => sprintf(
					'site_url (%s) weicht von home_url (%s) ab, aber kein Root-.htaccess unter %s gefunden.',
					$site_path,
					$home_path,
					$display_path
				),
				'fix' => $fix_snippet,
			);
		}

		$content = @file_get_contents( $root_htaccess );
		$has_fix = false !== $content && false !== strpos( $content, 'Media Lab Subdirectory-Fix' );

		return array(
			'id'          => 'subdirectory_htaccess_fix',
			'label'       => 'Subdirectory-Fix für wp-content/wp-includes',
			'status'      => $has_fix ? 'ok' : 'fail',
			'description' => $has_fix
				? sprintf( 'site_url (%s) weicht von home_url (%s) ab, Rewrite-Regel ist im Root-.htaccess vorhanden.', $site_path, $home_path )
				: sprintf(
					'site_url (%s) weicht von home_url (%s) ab, aber die Rewrite-Regel fehlt in %s. Root-relative Links auf wp-content/wp-includes laufen dadurch ins Leere.',
					$site_path,
					$home_path,
					$display_path
				),
			'fix' => $fix_snippet,
		);
	}

	/* ---------------------------------------------------------------
	 * Cron / Benachrichtigung
	 * ------------------------------------------------------------- */

	public function run_scan_and_notify() {
		$results = $this->run_full_scan();

		$failed_health_checks = array_filter( $results['health_checks'] ?? array(), function ( $c ) {
			return 'fail' === $c['status'];
		} );

		$has_findings = ! empty( $results['pattern_findings'] )
			|| ! empty( $results['core_integrity']['mismatches'] )
			|| ! empty( $results['suspicious_dirs'] )
			|| ! empty( $failed_health_checks );

		if ( ! $has_findings ) {
			return;
		}

		$to = get_option( self::OPTION_NOTIFY_EMAIL ) ?: get_option( 'admin_email' );

		$subject = sprintf( '[Security Scan] Auffälligkeiten auf %s gefunden', home_url() );

		$body  = "Der automatische Security-Scan hat mögliche Auffälligkeiten gefunden:\n\n";
		$body .= sprintf( "- Verdächtige Code-Muster: %d\n", count( $results['pattern_findings'] ) );
		$body .= sprintf( "- WP-Core-Abweichungen: %d\n", count( $results['core_integrity']['mismatches'] ?? array() ) );
		$body .= sprintf( "- Verdächtige Verzeichnisse: %d\n", count( $results['suspicious_dirs'] ) );
		$body .= sprintf( "- Fehlgeschlagene Hardening-Checks: %d\n", count( $failed_health_checks ) );
		if ( ! empty( $failed_health_checks ) ) {
			foreach ( $failed_health_checks as $c ) {
				$body .= '    - ' . $c['label'] . "\n";
			}
		}
		$body .= "\nDetails im WP-Admin unter Security Scan:\n";
		$body .= admin_url( 'admin.php?page=mla-security-scan' ) . "\n";

		wp_mail( $to, $subject, $body );
	}

	/* ---------------------------------------------------------------
	 * Admin-UI
	 * ------------------------------------------------------------- */

	public function handle_manual_scan() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'mla_run_security_scan' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$this->run_full_scan();
		wp_safe_redirect( admin_url( 'admin.php?page=mla-security-scan&scanned=1' ) );
		exit;
	}

	public function handle_whitelist_finding() {
		if ( ! current_user_can( self::CAPABILITY ) || ! check_admin_referer( 'mla_whitelist_finding' ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$hash = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
		$file = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';

		if ( $hash ) {
			$whitelist         = get_option( self::OPTION_WHITELIST, array() );
			$whitelist[ $hash ] = array(
				'file' => $file,
				'by'   => wp_get_current_user()->user_login,
				'date' => current_time( 'mysql' ),
			);
			update_option( self::OPTION_WHITELIST, $whitelist, false );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mla-security-scan&whitelisted=1' ) );
		exit;
	}

	public function render_admin_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		$results = get_option( self::OPTION_RESULTS, array() );
		?>
		<div class="wrap">
			<h1>Security Scan</h1>
			<p>Scannt Core, Plugins und Dateisystem auf Malware-typische Muster. Läuft direkt im WP-Admin, kein SSH-Zugriff nötig.</p>

			<?php if ( $this->is_nginx() ) : ?>
				<p style="background:#f0f6fc;border-left:4px solid #72aee6;padding:8px 12px;">
					ℹ️ Dieser Server läuft unter <strong>nginx</strong>. Prüfungen, deren Standard-Fix auf
					<code>.htaccess</code> basiert, kann diese Seite dort nicht automatisch verifizieren
					(nginx wertet <code>.htaccess</code>-Dateien nicht aus) - sie erscheinen daher als
					⚠️ Hinweis statt als ❌ Fehler und lösen keine Alarm-E-Mail aus. Die passende Konfiguration
					muss stattdessen im nginx-Vhost erfolgen, siehe jeweilige Anleitung.
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
				<input type="hidden" name="action" value="mla_run_security_scan">
				<?php wp_nonce_field( 'mla_run_security_scan' ); ?>
				<button type="submit" class="button button-primary">Scan jetzt ausführen</button>
				<?php if ( ! empty( $results['timestamp'] ) ) : ?>
					<span style="margin-left:10px;color:#666;">
						Letzter Scan: <?php echo esc_html( $results['timestamp'] ); ?>
						(<?php echo esc_html( $results['duration_seconds'] ?? '?' ); ?>s)
					</span>
				<?php endif; ?>
			</form>

			<?php if ( empty( $results ) ) : ?>
				<p>Noch kein Scan durchgeführt.</p>
				<?php return; ?>
			<?php endif; ?>

			<h2>Setup-Härtung (Checkliste)</h2>
			<table class="widefat striped">
				<thead><tr><th style="width:30px;"></th><th>Prüfung</th><th>Status</th><th>Behebung</th></tr></thead>
				<tbody>
				<?php foreach ( $results['health_checks'] ?? array() as $check ) : ?>
					<?php
					$icon  = '✅';
					$color = 'green';
					if ( 'fail' === $check['status'] ) {
						$icon  = '❌';
						$color = '#a00';
					} elseif ( 'warn' === $check['status'] ) {
						$icon  = '⚠️';
						$color = '#996800';
					}
					?>
					<tr>
						<td style="font-size:16px;"><?php echo esc_html( $icon ); ?></td>
						<td><strong><?php echo esc_html( $check['label'] ); ?></strong></td>
						<td style="color:<?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $check['description'] ); ?></td>
						<td>
							<?php if ( ! empty( $check['fix'] ) && 'ok' !== $check['status'] ) : ?>
								<details>
									<summary>Anleitung</summary>
									<pre style="white-space:pre-wrap;background:#f6f7f7;padding:8px;font-size:11px;"><?php echo esc_html( $check['fix'] ); ?></pre>
								</details>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px;">Verdächtige Verzeichnisse (z.B. alte/vergessene Installationen)</h2>
			<?php if ( empty( $results['suspicious_dirs'] ) ) : ?>
				<p style="color:green;">Keine Funde.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>Pfad</th><th>Muster</th><th>PHP-Dateien darin</th></tr></thead>
					<tbody>
					<?php foreach ( $results['suspicious_dirs'] as $d ) : ?>
						<tr>
							<td><code><?php echo esc_html( $d['path'] ); ?></code></td>
							<td><?php echo esc_html( $d['matched'] ); ?></td>
							<td><?php echo esc_html( $d['php_files'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><em>Empfehlung: Prüfen und bei nicht mehr benötigten Alt-Installationen komplett löschen — sie gehören nicht in den Webroot.</em></p>
			<?php endif; ?>

			<h2 style="margin-top:30px;">Verdächtige Code-Muster</h2>
			<?php if ( empty( $results['pattern_findings'] ) ) : ?>
				<p style="color:green;">Keine Funde.</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>Datei</th><th>Muster</th><th>Geändert</th><th>Aktion</th></tr></thead>
					<tbody>
					<?php foreach ( $results['pattern_findings'] as $f ) : ?>
						<tr>
							<td><code><?php echo esc_html( $f['file'] ); ?></code></td>
							<td><?php echo esc_html( $f['pattern'] ); ?></td>
							<td><?php echo esc_html( $f['mtime'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="mla_whitelist_finding">
									<input type="hidden" name="hash" value="<?php echo esc_attr( $f['hash'] ); ?>">
									<input type="hidden" name="file" value="<?php echo esc_attr( $f['file'] ); ?>">
									<?php wp_nonce_field( 'mla_whitelist_finding' ); ?>
									<button type="submit" class="button button-small" onclick="return confirm('Nur bestätigen, wenn manuell verifiziert wurde, dass diese Datei sicher ist.');">Als False Positive markieren</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:30px;">WP-Core-Integrität</h2>
			<?php if ( isset( $results['core_integrity']['error'] ) ) : ?>
				<p style="color:#a00;"><?php echo esc_html( $results['core_integrity']['error'] ); ?></p>
			<?php elseif ( empty( $results['core_integrity']['mismatches'] ) ) : ?>
				<p style="color:green;">Alle <?php echo esc_html( $results['core_integrity']['checked'] ?? '' ); ?> Core-Dateien unverändert (Version <?php echo esc_html( $results['core_integrity']['wp_version'] ?? '' ); ?>).</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th>Datei</th><th>Status</th></tr></thead>
					<tbody>
					<?php foreach ( $results['core_integrity']['mismatches'] as $m ) : ?>
						<tr><td><code><?php echo esc_html( $m['file'] ); ?></code></td><td><?php echo esc_html( $m['status'] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2 style="margin-top:30px;">Plugin-Integrität</h2>
			<?php
			$plugin_issues = array_filter( $results['plugin_integrity'] ?? array(), function ( $p ) {
				return isset( $p['status'] ) && 'ABWEICHUNGEN GEFUNDEN' === $p['status'];
			} );
			?>
			<?php if ( empty( $plugin_issues ) ) : ?>
				<p style="color:green;">Keine Abweichungen bei offiziellen .org-Plugins gefunden. (Eigene/Premium-Plugins ohne öffentliche Checksummen werden übersprungen.)</p>
			<?php else : ?>
				<?php foreach ( $plugin_issues as $slug => $p ) : ?>
					<h4><?php echo esc_html( $p['name'] ); ?></h4>
					<ul>
						<?php foreach ( $p['mismatches'] as $m ) : ?>
							<li><code><?php echo esc_html( $m['file'] ); ?></code> — <?php echo esc_html( $m['status'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			<?php endif; ?>

			<h2 style="margin-top:30px;">Administrator-Accounts</h2>
			<table class="widefat striped">
				<thead><tr><th>Login</th><th>E-Mail</th><th>Registriert</th></tr></thead>
				<tbody>
				<?php foreach ( $results['admin_users'] as $u ) : ?>
					<tr>
						<td><?php echo esc_html( $u['login'] ); ?></td>
						<td><?php echo esc_html( $u['email'] ); ?></td>
						<td><?php echo esc_html( $u['registered'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><em>Prüfen: sind das ausschließlich bekannte, aktive Admins? Unbekannte Accounts sofort löschen.</em></p>

			<h2 style="margin-top:30px;">Kürzlich veränderte PHP-Dateien (7 Tage)</h2>
			<details>
				<summary>Anzeigen (<?php echo esc_html( count( $results['recent_files'] ?? array() ) ); ?> Dateien)</summary>
				<ul style="max-height:300px;overflow:auto;">
					<?php foreach ( $results['recent_files'] ?? array() as $f ) : ?>
						<li><code><?php echo esc_html( $f['file'] ); ?></code> — <?php echo esc_html( $f['mtime'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
		<?php
	}
}
