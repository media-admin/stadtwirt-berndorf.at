<?php
/**
 * MLT_Report_Mailer
 *
 * Wöchentlicher SEO-Report-Versand.
 * Holt Daten aus GSC API + Analytics Adapter, baut das HTML-Template
 * und sendet via wp_mail() (SMTP über Agency Core).
 *
 * Cron-Hook: mlt_weekly_report (registriert in class-settings.php)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Report_Mailer {

    public function __construct() {
        // Cron-Hook übernehmen
        add_action( 'mlt_weekly_report', [ $this, 'send' ] );
    }

    public function send() {
        $to = get_option( 'mlt_report_email', get_option( 'admin_email' ) );
        if ( ! is_email( $to ) ) return;

        $data = $this->collect_data();
        $html = MLT_Report_Template::build( $data );

        /**
         * Filter: Report-HTML vor dem Versand anpassen.
         *
         * @param string $html  Fertiges HTML
         * @param array  $data  Rohdaten (gsc_overview, gsc_queries, gsc_pages, analytics, analytics_sources)
         * @param string $to    Empfänger
         */
        $html = apply_filters( 'mlt_weekly_report_html', $html, $data, $to );

        $week    = wp_date( 'W' );
        $year    = wp_date( 'Y' );
        $subject = sprintf( '[%s] SEO Report KW %s/%s', get_bloginfo( 'name' ), $week, $year );

        /**
         * Filter: Subject anpassen.
         */
        $subject = apply_filters( 'mlt_weekly_report_subject', $subject, $week, $year );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $sent = wp_mail( $to, $subject, $html, $headers );

        // Versandzeitpunkt und Status speichern
        update_option( 'mlt_last_report_sent', current_time( 'mysql' ) );
        update_option( 'mlt_last_report_status', $sent ? 'success' : 'failed' );

        return $sent;
    }

    private function collect_data() : array {
        $data = [
            'gsc_overview'      => [],
            'gsc_queries'       => [],
            'gsc_pages'         => [],
            'analytics'         => [],
            'analytics_sources' => [],
        ];

        // GSC-Daten
        $gsc = MLT_GSC_API::instance();
        if ( $gsc->is_connected() && $gsc->is_configured() ) {
            $data['gsc_overview'] = $gsc->get_overview();
            $data['gsc_queries']  = $gsc->get_top_queries( 10 );
            $data['gsc_pages']    = $gsc->get_top_pages( 10 );
        }

        // Analytics-Daten
        $adapter = MLT_Analytics_Adapter_Factory::get();
        if ( $adapter ) {
            $start = gmdate( 'Y-m-d', strtotime( '-28 days' ) );
            $end   = gmdate( 'Y-m-d', strtotime( '-2 days' ) );

            $data['analytics']         = $adapter->get_overview( $start, $end );
            $data['analytics_sources'] = $adapter->get_sources( $start, $end, 5 );
        }

        return $data;
    }
}
