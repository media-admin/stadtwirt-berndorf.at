<?php
/**
 * Consent Tracker
 *
 * Protokolliert Cookie-Consent-Entscheidungen server-seitig, anonymisiert,
 * für statistische Auswertung (z.B. Consent-Rate im SEO Toolkit Dashboard).
 *
 * Es wird KEIN Personenbezug gespeichert – weder IP, noch User-Agent, noch
 * Session/Cookie-ID. Nur: Kategorie, Entscheidung (accept/decline), Datum.
 * Das reicht für eine Rate-Berechnung und ist DSGVO-unkritisch, da keine
 * Re-Identifizierung eines einzelnen Besuchers möglich ist.
 *
 * Tabelle: {$wpdb->prefix}mlt_consent_log
 *
 * Lesender Zugriff erfolgt direkt per $wpdb aus anderen Plugins (z.B.
 * Media Lab SEO Toolkit), kein REST-Endpoint nötig – beide Plugins laufen
 * in derselben WordPress-Instanz.
 *
 * @package MediaLab_Core
 * @since   1.15.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Consent_Tracker {

    const TABLE = 'mlt_consent_log';

    /** Erlaubte Kategorien – muss mit Cookie Consent Manager übereinstimmen */
    const CATEGORIES = array( 'necessary', 'statistics', 'marketing', 'comfort' );

    public function __construct() {
        add_action( 'wp_ajax_medialab_log_consent',        array( $this, 'ajax_log_consent' ) );
        add_action( 'wp_ajax_nopriv_medialab_log_consent', array( $this, 'ajax_log_consent' ) );
        add_action( 'wp_enqueue_scripts',                  array( $this, 'enqueue_config' ), 5 );
    }

    // ─── Tabelle anlegen ──────────────────────────────────────────────────────

    public static function create_table(): void {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category VARCHAR(20) NOT NULL,
            decision VARCHAR(10) NOT NULL,
            log_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_date (log_date),
            KEY category (category)
        ) {$charset_collate};";

        dbDelta( $sql );
    }

    // ─── Nonce für anonyme Besucher bereitstellen ────────────────────────────

    public function enqueue_config(): void {
        if ( is_admin() ) return;

        wp_register_script( 'medialab-consent-tracker', false, array(), MEDIALAB_CORE_VERSION, false );
        wp_enqueue_script( 'medialab-consent-tracker' );
        wp_localize_script( 'medialab-consent-tracker', 'medialabConsentTracker', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'medialab_log_consent' ),
        ) );
    }

    // ─── AJAX-Handler ─────────────────────────────────────────────────────────

    public function ajax_log_consent(): void {
        check_ajax_referer( 'medialab_log_consent', 'nonce' );

        $categories = isset( $_POST['categories'] ) ? (array) $_POST['categories'] : array();
        if ( empty( $categories ) ) {
            wp_send_json_error( 'Keine Kategorien übermittelt.' );
        }

        // Einfaches Rate-Limiting: max. 1 Log-Vorgang pro 10 Sekunden pro IP-Hash
        // (IP wird nur transient gehasht für Rate-Limit, NICHT gespeichert)
        $ip_hash       = md5( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt() );
        $rate_limit_key = 'mlt_consent_rl_' . $ip_hash;
        if ( get_transient( $rate_limit_key ) ) {
            wp_send_json_success( 'Rate limited – ignoriert.' );
        }
        set_transient( $rate_limit_key, 1, 10 );

        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $today   = current_time( 'Y-m-d' );
        $logged  = array();

        foreach ( $categories as $category => $decision ) {
            $category = sanitize_key( $category );
            $decision = $decision ? 'accept' : 'decline';

            if ( ! in_array( $category, self::CATEGORIES, true ) ) continue;

            $wpdb->insert(
                $table,
                array(
                    'category'   => $category,
                    'decision'   => $decision,
                    'log_date'   => $today,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s' )
            );

            $logged[] = $category;
        }

        wp_send_json_success( array( 'logged' => $logged ) );
    }
}

new MediaLab_Consent_Tracker();
