<?php
/**
 * hCaptcha Integration
 *
 * Bindet hCaptcha in Contact Form 7, den WordPress-Login
 * und WooCommerce (Checkout, Registrierung) ein.
 *
 * Konfiguration: Agency Core → Spam-Schutz / E-Mail Obfuskierung
 *
 * Felder:
 *   hcaptcha_enabled          true_false  Globaler Schalter
 *   hcaptcha_site_key         text        Öffentlicher Site-Key
 *   hcaptcha_secret_key       text        Privater Secret-Key
 *   hcaptcha_cf7              true_false  CF7-Formulare schützen
 *   hcaptcha_wp_login         true_false  WP-Login schützen
 *   hcaptcha_woo_checkout     true_false  WooCommerce Checkout schützen
 *   hcaptcha_woo_register     true_false  WooCommerce Registrierung schützen
 *   hcaptcha_theme            select      light | dark
 *   hcaptcha_size             select      normal | compact | invisible
 *
 * @package MediaLabAgencyCore
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Hilfsfunktionen ───────────────────────────────────────────────────────────

/**
 * Gibt den konfigurierten Site-Key zurück.
 */
function medialab_hcaptcha_site_key(): string {
    return (string) get_field( 'hcaptcha_site_key', 'option' );
}

/**
 * Gibt den konfigurierten Secret-Key zurück.
 */
function medialab_hcaptcha_secret_key(): string {
    return (string) get_field( 'hcaptcha_secret_key', 'option' );
}

/**
 * Prüft ob hCaptcha global aktiv und vollständig konfiguriert ist.
 */
function medialab_hcaptcha_active(): bool {
    if ( ! get_field( 'hcaptcha_enabled', 'option' ) ) return false;
    if ( ! medialab_hcaptcha_site_key() )               return false;
    if ( ! medialab_hcaptcha_secret_key() )             return false;
    return true;
}

/**
 * Rendert das hCaptcha-Widget als HTML-String.
 *
 * @param string $id  Optionale HTML-ID für das Widget-Div.
 */
function medialab_hcaptcha_widget( string $id = '' ): string {
    if ( ! medialab_hcaptcha_active() ) return '';

    $theme = (string) ( get_field( 'hcaptcha_theme', 'option' ) ?: 'light' );
    $size  = (string) ( get_field( 'hcaptcha_size',  'option' ) ?: 'normal' );

    $attrs  = 'class="h-captcha"';
    $attrs .= ' data-sitekey="' . esc_attr( medialab_hcaptcha_site_key() ) . '"';
    $attrs .= ' data-theme="'   . esc_attr( $theme ) . '"';
    $attrs .= ' data-size="'    . esc_attr( $size )  . '"';
    if ( $id ) {
        $attrs .= ' id="' . esc_attr( $id ) . '"';
    }

    return '<div ' . $attrs . '></div>';
}

/**
 * Verifiziert die h-captcha-response des Nutzers serverseitig.
 *
 * @return true|WP_Error  true bei Erfolg, WP_Error bei Fehler.
 */
function medialab_hcaptcha_verify(): bool|WP_Error {
    $token = sanitize_text_field( wp_unslash( $_POST['h-captcha-response'] ?? '' ) );

    if ( $token === '' ) {
        return new WP_Error( 'hcaptcha_missing', __( 'Bitte das CAPTCHA ausfüllen.', 'media-lab' ) );
    }

    $response = wp_remote_post( 'https://api.hcaptcha.com/siteverify', array(
        'body'    => array(
            'secret'   => medialab_hcaptcha_secret_key(),
            'response' => $token,
        ),
        'timeout' => 10,
    ) );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'hcaptcha_request_failed', __( 'CAPTCHA-Verifizierung fehlgeschlagen.', 'media-lab' ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['success'] ) ) {
        $codes   = implode( ', ', (array) ( $body['error-codes'] ?? [] ) );
        $message = $codes
            ? sprintf( __( 'CAPTCHA ungültig (%s). Bitte erneut versuchen.', 'media-lab' ), esc_html( $codes ) )
            : __( 'CAPTCHA ungültig. Bitte erneut versuchen.', 'media-lab' );
        return new WP_Error( 'hcaptcha_invalid', $message );
    }

    return true;
}

// ── Script einbinden ─────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', function () {
    if ( ! medialab_hcaptcha_active() ) return;

    // Nur auf Seiten einbinden, auf denen ein Widget sichtbar ist
    $needs_cf7   = get_field( 'hcaptcha_cf7',          'option' );
    $needs_woo   = get_field( 'hcaptcha_woo_checkout',  'option' ) || get_field( 'hcaptcha_woo_register', 'option' );
    $is_checkout = function_exists( 'is_checkout' ) && is_checkout();
    $is_account  = function_exists( 'is_account_page' ) && is_account_page();

    if ( ! $needs_cf7 && ! ( $needs_woo && ( $is_checkout || $is_account ) ) ) return;

    wp_enqueue_script(
        'hcaptcha-api',
        'https://js.hcaptcha.com/1/api.js',
        array(),
        null,
        array( 'strategy' => 'async', 'in_footer' => false )
    );
} );

add_action( 'login_enqueue_scripts', function () {
    if ( ! medialab_hcaptcha_active() ) return;
    if ( ! get_field( 'hcaptcha_wp_login', 'option' ) ) return;

    wp_enqueue_script(
        'hcaptcha-api',
        'https://js.hcaptcha.com/1/api.js',
        array(),
        null,
        false
    );
} );

// ── Contact Form 7 ───────────────────────────────────────────────────────────

/**
 * Widget ans Ende jedes CF7-Formulars anhängen.
 */
add_filter( 'wpcf7_form_elements', function ( string $html ): string {
    if ( ! medialab_hcaptcha_active() ) return $html;
    if ( ! get_field( 'hcaptcha_cf7', 'option' ) ) return $html;

    $widget = '<div class="medialab-hcaptcha-cf7">'
            . medialab_hcaptcha_widget()
            . '</div>';

    // Vor dem Submit-Button einfügen
    return str_replace( '<input type="submit"', $widget . '<input type="submit"', $html );
} );

/**
 * CF7-Submission server-seitig verifizieren.
 */
add_filter( 'wpcf7_validate', function ( $result, $tags ) {
    if ( ! medialab_hcaptcha_active() ) return $result;
    if ( ! get_field( 'hcaptcha_cf7', 'option' ) ) return $result;

    $verify = medialab_hcaptcha_verify();
    if ( is_wp_error( $verify ) ) {
        $result->invalidate( (object) array( 'name' => 'hcaptcha' ), $verify->get_error_message() );
    }

    return $result;
}, 10, 2 );

// ── WordPress Login ───────────────────────────────────────────────────────────

/**
 * Widget im Login-Formular vor dem Submit-Button ausgeben.
 */
add_action( 'login_form', function () {
    if ( ! medialab_hcaptcha_active() ) return;
    if ( ! get_field( 'hcaptcha_wp_login', 'option' ) ) return;

    echo '<div class="medialab-hcaptcha-login" style="margin:12px 0;">'
       . medialab_hcaptcha_widget( 'medialab-hcaptcha-login' )
       . '</div>';
} );

/**
 * Login-Anfrage verifizieren.
 */
add_filter( 'authenticate', function ( $user, string $username, string $password ) {
    if ( ! medialab_hcaptcha_active() ) return $user;
    if ( ! get_field( 'hcaptcha_wp_login', 'option' ) ) return $user;

    // Nur bei echter Formular-Submission (nicht bei XML-RPC o.ä.)
    if ( empty( $_POST['h-captcha-response'] ) && empty( $_POST['log'] ) ) return $user;

    $verify = medialab_hcaptcha_verify();
    if ( is_wp_error( $verify ) ) {
        return new WP_Error( 'hcaptcha_failed', $verify->get_error_message() );
    }

    return $user;
}, 30, 3 );

// ── WooCommerce Checkout ──────────────────────────────────────────────────────

/**
 * Widget im Checkout-Formular vor dem Bestellung-abschicken-Button.
 */
add_action( 'woocommerce_review_order_before_submit', function () {
    if ( ! medialab_hcaptcha_active() ) return;
    if ( ! get_field( 'hcaptcha_woo_checkout', 'option' ) ) return;

    echo '<div class="medialab-hcaptcha-checkout" style="margin-bottom:16px;">'
       . medialab_hcaptcha_widget( 'medialab-hcaptcha-checkout' )
       . '</div>';
} );

/**
 * Checkout-Submission verifizieren.
 */
add_action( 'woocommerce_checkout_process', function () {
    if ( ! medialab_hcaptcha_active() ) return;
    if ( ! get_field( 'hcaptcha_woo_checkout', 'option' ) ) return;

    $verify = medialab_hcaptcha_verify();
    if ( is_wp_error( $verify ) ) {
        wc_add_notice( $verify->get_error_message(), 'error' );
    }
} );

// ── WooCommerce Registrierung ─────────────────────────────────────────────────

/**
 * Widget im Registrierungsformular (Mein Konto).
 */
add_action( 'woocommerce_register_form', function () {
    if ( ! medialab_hcaptcha_active() ) return;
    if ( ! get_field( 'hcaptcha_woo_register', 'option' ) ) return;

    echo '<div class="medialab-hcaptcha-register" style="margin-bottom:16px;">'
       . medialab_hcaptcha_widget( 'medialab-hcaptcha-register' )
       . '</div>';
} );

/**
 * Registrierungs-Submission verifizieren.
 */
add_filter( 'woocommerce_process_registration_errors', function ( $errors ) {
    if ( ! medialab_hcaptcha_active() ) return $errors;
    if ( ! get_field( 'hcaptcha_woo_register', 'option' ) ) return $errors;

    $verify = medialab_hcaptcha_verify();
    if ( is_wp_error( $verify ) ) {
        $errors->add( 'hcaptcha_failed', $verify->get_error_message() );
    }

    return $errors;
} );
