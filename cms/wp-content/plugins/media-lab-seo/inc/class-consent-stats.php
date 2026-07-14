<?php
/**
 * MLT_Consent_Stats
 *
 * Liest die Consent-Log-Tabelle von Media Lab Agency Core
 * (wp_mlt_consent_log) read-only aus und berechnet Accept-Raten pro
 * Kategorie für die Anzeige im SEO Dashboard.
 *
 * Keine eigene Tabelle, kein Schreibzugriff – Agency Core ist alleiniger
 * Owner der Consent-Daten (siehe inc/consent-tracker.php dort).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Consent_Stats {

    const CATEGORIES = array(
        'necessary'  => 'Notwendig',
        'statistics' => 'Statistik',
        'marketing'  => 'Marketing',
        'comfort'    => 'Komfort',
    );

    /**
     * Prüft ob die Agency-Core-Tabelle existiert (Plugin könnte älter sein
     * oder Consent Tracker nie initialisiert).
     */
    public static function is_available() : bool {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_consent_log';
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        return $found === $table;
    }

    /**
     * Accept-Rate pro Kategorie für einen Zeitraum.
     *
     * @return array<string, array{accept:int, decline:int, rate:float}>
     */
    public static function get_rates( string $start, string $end ) : array {
        global $wpdb;
        $table = $wpdb->prefix . 'mlt_consent_log';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT category, decision, COUNT(*) as cnt
             FROM {$table}
             WHERE log_date BETWEEN %s AND %s
             GROUP BY category, decision",
            $start, $end
        ), ARRAY_A );

        $result = array();
        foreach ( array_keys( self::CATEGORIES ) as $cat ) {
            $result[ $cat ] = array( 'accept' => 0, 'decline' => 0, 'rate' => 0.0 );
        }

        foreach ( $rows as $row ) {
            $cat = $row['category'];
            if ( ! isset( $result[ $cat ] ) ) continue;
            $result[ $cat ][ $row['decision'] === 'accept' ? 'accept' : 'decline' ] = (int) $row['cnt'];
        }

        foreach ( $result as $cat => &$data ) {
            $total = $data['accept'] + $data['decline'];
            $data['rate'] = $total > 0 ? round( ( $data['accept'] / $total ) * 100, 1 ) : 0.0;
            $data['total'] = $total;
        }

        return $result;
    }

    /**
     * Zwei Zeiträume direkt gegenüberstellen (z.B. aktuelle Woche vs. Vorwoche).
     *
     * @return array{current: array, previous: array}
     */
    public static function get_comparison(
        string $current_start, string $current_end,
        string $previous_start, string $previous_end
    ) : array {
        return array(
            'current'  => self::get_rates( $current_start, $current_end ),
            'previous' => self::get_rates( $previous_start, $previous_end ),
        );
    }
}
