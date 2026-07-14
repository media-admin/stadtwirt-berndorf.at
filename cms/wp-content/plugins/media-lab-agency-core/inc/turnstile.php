<?php
/**
 * Cloudflare Turnstile Integration
 *
 * Bindet Cloudflare Turnstile CAPTCHA in Contact Form 7, den WordPress-Login,
 * WooCommerce (Checkout, Login, Registrierung) und eigene Formulare ein.
 *
 * DSGVO:
 *   Standard: Berechtigtes Interesse (Art. 6 Abs. 1 lit. f DSGVO).
 *   Turnstile überträgt IP-Adresse an Cloudflare-Server. Cloudflare hat eine
 *   DSGVO-konforme DPA und Standardvertragsklauseln. Zweck: Spam-Schutz.
 *   Optional: Consent-abhängiges Laden – das Widget wird erst gerendert wenn
 *   die konfigurierte Cookie-Consent-Kategorie freigegeben ist.
 *
 * Konfiguration: Agency Core → Spam-Schutz
 *
 * Felder:
 *   turnstile_enabled          true_false  Globaler Schalter
 *   turnstile_site_key         text        Öffentlicher Site-Key (Cloudflare Dashboard)
 *   turnstile_secret_key       text        Privater Secret-Key
 *   turnstile_cf7              true_false  CF7-Formulare schützen (default: an)
 *   turnstile_wp_login         true_false  WP-Login schützen (default: an)
 *   turnstile_woo_checkout     true_false  WooCommerce Checkout (default: an)
 *   turnstile_woo_login        true_false  WooCommerce Login (default: an)
 *   turnstile_woo_register     true_false  WooCommerce Registrierung (default: an)
 *   turnstile_appearance       select      auto | light | dark
 *   turnstile_size             select      normal | compact | flexible
 *   turnstile_dsgvo_mode       select      legitimate_interest | consent
 *   turnstile_consent_category select      necessary | statistics | marketing | comfort
 *
 * Öffentliche API:
 *   medialab_turnstile_render( string $id = '' ): string
 *   medialab_turnstile_verify(): true|WP_Error
 *   medialab_turnstile_active(): bool
 *
 * @package MediaLabAgencyCore
 * @since   1.13.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// Hilfsfunktionen
// =============================================================================

/**
 * Prüft ob Turnstile global aktiv und vollständig konfiguriert ist.
 */
function medialab_turnstile_active(): bool {
    if ( ! function_exists( 'get_field' ) )                  return false;
    if ( ! get_field( 'turnstile_enabled', 'option' ) )      return false;
    if ( ! medialab_turnstile_site_key() )                    return false;
    if ( ! medialab_turnstile_secret_key() )                  return false;
    return true;
}

/**
 * Gibt den konfigurierten Site-Key zurück.
 */
function medialab_turnstile_site_key(): string {
    if ( ! function_exists( 'get_field' ) ) return '';
    return (string) ( get_field( 'turnstile_site_key', 'option' ) ?: '' );
}

/**
 * Gibt den konfigurierten Secret-Key zurück.
 */
function medialab_turnstile_secret_key(): string {
    if ( ! function_exists( 'get_field' ) ) return '';
    return (string) ( get_field( 'turnstile_secret_key', 'option' ) ?: '' );
}

/**
 * Gibt den DSGVO-Modus zurück: 'legitimate_interest' oder 'consent'.
 */
function medialab_turnstile_dsgvo_mode(): string {
    if ( ! function_exists( 'get_field' ) ) return 'legitimate_interest';
    return (string) ( get_field( 'turnstile_dsgvo_mode', 'option' ) ?: 'legitimate_interest' );
}

/**
 * Gibt die Consent-Kategorie zurück die für das Laden von Turnstile benötigt wird.
 * Nur relevant wenn dsgvo_mode === 'consent'.
 */
function medialab_turnstile_consent_category(): string {
    if ( ! function_exists( 'get_field' ) ) return 'necessary';
    return (string) ( get_field( 'turnstile_consent_category', 'option' ) ?: 'necessary' );
}

// =============================================================================
// Widget rendern
// =============================================================================

/**
 * Rendert das Turnstile-Widget als HTML-String.
 *
 * Im Modus 'legitimate_interest': Widget wird direkt gerendert, das Turnstile-JS
 * initialisiert es automatisch beim Laden.
 *
 * Im Modus 'consent': Widget wird mit data-consent-category Attribut gerendert.
 * Das mitgelieferte Inline-JS prüft window.cookieConsent und wartet via
 * MutationObserver auf Consent-Änderungen bevor das Widget initialisiert wird.
 *
 * @param string $id  Optionale HTML-ID für das Container-Div.
 * @return string     HTML des Widgets (oder leer wenn Turnstile nicht aktiv).
 */
function medialab_turnstile_render( string $id = '' ): string {
    if ( ! medialab_turnstile_active() ) return '';

    $appearance = (string) ( function_exists( 'get_field' )
        ? ( get_field( 'turnstile_appearance', 'option' ) ?: 'auto' )
        : 'auto' );
    $size = (string) ( function_exists( 'get_field' )
        ? ( get_field( 'turnstile_size', 'option' ) ?: 'normal' )
        : 'normal' );

    $attrs  = 'class="cf-turnstile"';
    $attrs .= ' data-sitekey="'    . esc_attr( medialab_turnstile_site_key() ) . '"';
    $attrs .= ' data-theme="'      . esc_attr( $appearance ) . '"';
    $attrs .= ' data-size="'       . esc_attr( $size ) . '"';
    $attrs .= ' data-response-field-name="cf-turnstile-response"';

    if ( medialab_turnstile_dsgvo_mode() === 'consent' ) {
        $attrs .= ' data-consent-pending="true"';
        $attrs .= ' data-consent-category="' . esc_attr( medialab_turnstile_consent_category() ) . '"';
    }

    if ( $id ) {
        $attrs .= ' id="' . esc_attr( $id ) . '"';
    }

    return '<div ' . $attrs . '></div>';
}

// =============================================================================
// Serverseitige Verifikation
// =============================================================================

/**
 * Verifiziert das Turnstile-Token aus $_POST serverseitig.
 *
 * @return true|WP_Error  true bei Erfolg, WP_Error bei Fehler.
 */
function medialab_turnstile_verify(): bool|WP_Error {
    $token = sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ?? '' ) );

    if ( $token === '' ) {
        return new WP_Error( 'turnstile_missing',
            __( 'Bitte das Sicherheits-Widget ausfüllen.', 'media-lab' )
        );
    }

    $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'body'    => array(
            'secret'   => medialab_turnstile_secret_key(),
            'response' => $token,
            'remoteip' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ),
        'timeout' => 10,
    ) );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'turnstile_request_failed',
            __( 'Sicherheitsüberprüfung fehlgeschlagen. Bitte versuche es erneut.', 'media-lab' )
        );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( empty( $body['success'] ) ) {
        $codes   = implode( ', ', (array) ( $body['error-codes'] ?? [] ) );
        $message = $codes
            ? sprintf( __( 'Sicherheitsüberprüfung ungültig (%s). Bitte erneut versuchen.', 'media-lab' ), esc_html( $codes ) )
            : __( 'Sicherheitsüberprüfung ungültig. Bitte erneut versuchen.', 'media-lab' );
        return new WP_Error( 'turnstile_invalid', $message );
    }

    return true;
}

// =============================================================================
// Scripts einbinden
// =============================================================================

/**
 * Turnstile-JS auf dem Frontend laden.
 * Im Consent-Modus: JS wird trotzdem geladen, das Widget rendert sich aber erst
 * nach Consent (gesteuert durch medialab-turnstile-consent.js).
 */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! medialab_turnstile_active() ) return;

    $needs_cf7      = get_field( 'turnstile_cf7',          'option' );
    $needs_woo      = get_field( 'turnstile_woo_checkout',  'option' )
                   || get_field( 'turnstile_woo_login',     'option' )
                   || get_field( 'turnstile_woo_register',  'option' );
    $is_checkout    = function_exists( 'is_checkout' )    && is_checkout();
    $is_account     = function_exists( 'is_account_page' ) && is_account_page();

    if ( ! $needs_cf7 && ! ( $needs_woo && ( $is_checkout || $is_account ) ) ) return;

    wp_enqueue_script(
        'cloudflare-turnstile',
        'https://challenges.cloudflare.com/turnstile/v0/api.js',
        array(),
        null,
        array( 'strategy' => 'async', 'in_footer' => false )
    );

    // Consent-Modus: Inline-JS das auf window.cookieConsent wartet.
    if ( medialab_turnstile_dsgvo_mode() === 'consent' ) {
        $category = medialab_turnstile_consent_category();
        wp_add_inline_script( 'cloudflare-turnstile', medialab_turnstile_consent_js( $category ) );
    }
} );

/**
 * Turnstile-JS auf der Login-Seite laden.
 */
add_action( 'login_enqueue_scripts', function () {
    if ( ! medialab_turnstile_active() ) return;
    if ( ! get_field( 'turnstile_wp_login', 'option' ) ) return;

    wp_enqueue_script(
        'cloudflare-turnstile',
        'https://challenges.cloudflare.com/turnstile/v0/api.js',
        array(),
        null,
        false
    );

    if ( medialab_turnstile_dsgvo_mode() === 'consent' ) {
        wp_add_inline_script(
            'cloudflare-turnstile',
            medialab_turnstile_consent_js( medialab_turnstile_consent_category() )
        );
    }
} );

/**
 * Erzeugt das Inline-JS für den Consent-Modus.
 *
 * Das Script prüft window.cookieConsent auf die konfigurierte Kategorie und
 * rendert ausstehende Widgets via turnstile.render() sobald Consent erteilt
 * wird. Kompatibel mit dem Media Lab Cookie Consent System.
 *
 * @param string $category  Kategorie aus window.cookieConsent.categories.
 * @return string           Inline-JS (ohne <script>-Tags).
 */
function medialab_turnstile_consent_js( string $category ): string {
    $cat = esc_js( $category );
    return <<<JS
(function() {
    function mlRenderPendingTurnstiles() {
        var consent = window.cookieConsent;
        if (!consent || !consent.categories) return;

        var cat = consent.categories['{$cat}'];
        // 'necessary' ist immer aktiv; andere Kategorien prüfen ob sie nicht required sind
        var granted = cat && (cat.required === true || cat.accepted === true);
        if (!granted) return;

        var widgets = document.querySelectorAll('.cf-turnstile[data-consent-pending="true"]');
        widgets.forEach(function(el) {
            el.removeAttribute('data-consent-pending');
            if (window.turnstile && typeof window.turnstile.render === 'function') {
                window.turnstile.render(el);
            }
        });
    }

    // Direkt prüfen (für den Fall dass Consent bereits erteilt wurde)
    document.addEventListener('DOMContentLoaded', mlRenderPendingTurnstiles);

    // Auf Consent-Änderungen reagieren (Media Lab Cookie Consent Event)
    window.addEventListener('mlConsentUpdated', mlRenderPendingTurnstiles);

    // Fallback: MutationObserver auf window.cookieConsent
    if (typeof window.cookieConsent === 'undefined') {
        Object.defineProperty(window, 'cookieConsent', {
            configurable: true,
            set: function(val) {
                Object.defineProperty(window, 'cookieConsent', { value: val, writable: true, configurable: true });
                mlRenderPendingTurnstiles();
            }
        });
    }
})();
JS;
}

// =============================================================================
// Admin-Notice: Konflikt hCaptcha + Turnstile
// =============================================================================

add_action( 'admin_notices', function () {
    if ( ! function_exists( 'get_field' ) ) return;

    $hcaptcha_on  = (bool) get_field( 'hcaptcha_enabled',  'option' );
    $turnstile_on = (bool) get_field( 'turnstile_enabled', 'option' );

    if ( ! $hcaptcha_on || ! $turnstile_on ) return;

    // Prüfen ob mindestens ein Scope für beide aktiv ist
    $scopes = [ 'cf7', 'wp_login', 'woo_checkout', 'woo_register' ];
    $conflict = false;
    foreach ( $scopes as $scope ) {
        $hc_field = ( $scope === 'woo_login' ) ? false : get_field( "hcaptcha_{$scope}", 'option' );
        $ts_field = get_field( "turnstile_{$scope}", 'option' );
        if ( $hc_field && $ts_field ) {
            $conflict = true;
            break;
        }
    }

    if ( ! $conflict ) return;

    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '<strong>Media Lab Agency Core:</strong> ';
    echo 'hCaptcha und Cloudflare Turnstile sind gleichzeitig für denselben Scope aktiviert. ';
    echo 'Es wird empfohlen, nur einen CAPTCHA-Dienst pro Formular zu verwenden. ';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=agency-core-spam' ) ) . '">Spam-Schutz Einstellungen öffnen</a>.';
    echo '</p></div>';
} );

// =============================================================================
// Contact Form 7
// =============================================================================

/**
 * Widget ans Ende jedes CF7-Formulars anhängen (vor Submit-Button).
 */
add_filter( 'wpcf7_form_elements', function ( string $html ): string {
    if ( ! medialab_turnstile_active() ) return $html;
    if ( ! get_field( 'turnstile_cf7', 'option' ) ) return $html;

    $widget = '<div class="medialab-turnstile-cf7">'
            . medialab_turnstile_render()
            . '</div>';

    return str_replace( '<input type="submit"', $widget . '<input type="submit"', $html );
} );

/**
 * CF7-Submission server-seitig verifizieren.
 */
add_filter( 'wpcf7_validate', function ( $result, $tags ) {
    if ( ! medialab_turnstile_active() ) return $result;
    if ( ! get_field( 'turnstile_cf7', 'option' ) ) return $result;

    $verify = medialab_turnstile_verify();
    if ( is_wp_error( $verify ) ) {
        $result->invalidate( (object) array( 'name' => 'turnstile' ), $verify->get_error_message() );
    }

    return $result;
}, 10, 2 );

// =============================================================================
// WordPress Login
// =============================================================================

add_action( 'login_form', function () {
    if ( ! medialab_turnstile_active() ) return;
    if ( ! get_field( 'turnstile_wp_login', 'option' ) ) return;

    echo '<div class="medialab-turnstile-login" style="margin:12px 0;">'
       . medialab_turnstile_render( 'medialab-turnstile-login' )
       . '</div>';
} );

add_filter( 'authenticate', function ( $user, string $username, string $password ) {
    if ( ! medialab_turnstile_active() ) return $user;
    if ( ! get_field( 'turnstile_wp_login', 'option' ) ) return $user;

    if ( empty( $_POST['cf-turnstile-response'] ) && empty( $_POST['log'] ) ) return $user;

    $verify = medialab_turnstile_verify();
    if ( is_wp_error( $verify ) ) {
        return new WP_Error( 'turnstile_failed', $verify->get_error_message() );
    }

    return $user;
}, 30, 3 );

// =============================================================================
// WooCommerce Checkout
// =============================================================================

add_action( 'woocommerce_review_order_before_submit', function () {
    if ( ! medialab_turnstile_active() ) return;
    if ( ! get_field( 'turnstile_woo_checkout', 'option' ) ) return;

    echo '<div class="medialab-turnstile-checkout" style="margin-bottom:16px;">'
       . medialab_turnstile_render( 'medialab-turnstile-checkout' )
       . '</div>';
} );

add_action( 'woocommerce_checkout_process', function () {
    if ( ! medialab_turnstile_active() ) return;
    if ( ! get_field( 'turnstile_woo_checkout', 'option' ) ) return;

    $verify = medialab_turnstile_verify();
    if ( is_wp_error( $verify ) ) {
        wc_add_notice( $verify->get_error_message(), 'error' );
    }
} );

// =============================================================================
// WooCommerce Login (Mein Konto)
// =============================================================================

add_action( 'woocommerce_login_form', function () {
    if ( ! medialab_turnstile_active() ) return;
    if ( ! get_field( 'turnstile_woo_login', 'option' ) ) return;

    echo '<div class="medialab-turnstile-woo-login" style="margin-bottom:16px;">'
       . medialab_turnstile_render( 'medialab-turnstile-woo-login' )
       . '</div>';
} );

add_filter( 'woocommerce_process_login_errors', function ( $validation_error, $username, $password ) {
    if ( ! medialab_turnstile_active() ) return $validation_error;
    if ( ! get_field( 'turnstile_woo_login', 'option' ) ) return $validation_error;

    $verify = medialab_turnstile_verify();
    if ( is_wp_error( $verify ) ) {
        $validation_error->add( 'turnstile_failed', $verify->get_error_message() );
    }

    return $validation_error;
}, 10, 3 );

// =============================================================================
// WooCommerce Registrierung
// =============================================================================

add_action( 'woocommerce_register_form', function () {
    if ( ! medialab_turnstile_active() ) return;
    if ( ! get_field( 'turnstile_woo_register', 'option' ) ) return;

    echo '<div class="medialab-turnstile-register" style="margin-bottom:16px;">'
       . medialab_turnstile_render( 'medialab-turnstile-register' )
       . '</div>';
} );

add_filter( 'woocommerce_process_registration_errors', function ( $errors ) {
    if ( ! medialab_turnstile_active() ) return $errors;
    if ( ! get_field( 'turnstile_woo_register', 'option' ) ) return $errors;

    $verify = medialab_turnstile_verify();
    if ( is_wp_error( $verify ) ) {
        $errors->add( 'turnstile_failed', $verify->get_error_message() );
    }

    return $errors;
} );
