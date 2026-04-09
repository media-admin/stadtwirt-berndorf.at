<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Shortcode {
    public static function init() {
        add_shortcode( 'mlb_booking_form', [ __CLASS__, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_assets' ] );
    }
    public static function register_assets() {
        wp_register_style(  'flatpickr',         'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css', [], '4.6.13' );
        wp_register_script( 'flatpickr',         'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js',  [], '4.6.13', true );
        wp_register_script( 'flatpickr-de',      'https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/de.min.js', [ 'flatpickr' ], '4.6.13', true );
        wp_register_style(  'mlb-booking-form',  MLB_URL . 'assets/css/booking-form.css', [ 'flatpickr' ], MLB_VERSION );
        wp_register_script( 'mlb-booking-form',  MLB_URL . 'assets/js/booking-form.js',  [ 'jquery', 'flatpickr', 'flatpickr-de' ], MLB_VERSION, true );
    }
    public static function render( $atts ): string {
        $atts = shortcode_atts( [ 'location' => '', 'title' => '', 'class' => '' ], $atts, 'mlb_booking_form' );
        wp_enqueue_style( 'mlb-booking-form' );
        wp_enqueue_script( 'mlb-booking-form' );

        $preset_location_id = 0;
        if ( ! empty( $atts['location'] ) ) {
            if ( is_numeric( $atts['location'] ) ) { $preset_location_id = (int) $atts['location']; }
            else { $loc = get_page_by_path( $atts['location'], OBJECT, 'mlb_location' ); if ( $loc ) $preset_location_id = $loc->ID; }
        }
        $locations = get_posts( [ 'post_type' => 'mlb_location', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        if ( ! $preset_location_id && count( $locations ) === 1 ) { $preset_location_id = $locations[0]->ID; }

        wp_localize_script( 'mlb-booking-form', 'mlbConfig', [
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'mlb_nonce' ),
            'presetLocationId' => $preset_location_id,
            'i18n'             => [
                'selectLocation' => 'Bitte zuerst einen Standort wählen.',
                'selectDate'     => 'Bitte zuerst ein Datum wählen.',
                'closed'         => 'An diesem Tag ist der Standort geschlossen.',
                'noSlots'        => 'Keine freien Zeitslots verfügbar.',
                'booked'         => 'Ausgebucht',
                'sending'        => 'Wird gesendet…',
                'errorGeneral'   => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                'icalDownload'   => 'Termin in Kalender speichern (.ics)',
            ],
        ] );

        $theme_tpl = locate_template( 'media-lab-bookings/booking-form.php' );
        $template  = $theme_tpl ?: MLB_PATH . 'templates/booking-form.php';
        ob_start();
        include $template;
        return ob_get_clean();
    }
}
MLB_Shortcode::init();

function mlb_label( string $field_name, string $default, int $location_id ): string {
    if ( ! $location_id || ! function_exists( 'get_field' ) ) return $default;
    $val = get_field( $field_name, $location_id );
    return ( $val && trim( $val ) !== '' ) ? esc_html( trim( $val ) ) : $default;
}
