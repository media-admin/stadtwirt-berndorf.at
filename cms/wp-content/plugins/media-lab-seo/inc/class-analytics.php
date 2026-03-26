<?php
/**
 * MLT_Analytics
 *
 * Bindet GA4 oder GTM ins Frontend ein – ausschließlich Consent-aware.
 *
 * Ablauf:
 *  1. Script-Tag wird mit data-consent-category="analytics" ausgegeben
 *     → kein Tracking ohne aktives Consent-Signal
 *  2. Agency Core Cookie Consent setzt window.mltConsentGranted = true
 *     sobald der Nutzer akzeptiert
 *  3. Dieses Modul lauscht auf das Custom Event 'mlt:consent:analytics'
 *     und aktiviert das Tracking erst dann
 *
 * Das Modul wird von media-lab-toolkit.php nur geladen wenn
 * get_option('mlt_analytics_enabled') === 1 ist.
 * Bei deaktiviertem Toggle wird KEIN Script ins Frontend injiziert.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Analytics {

    private string $provider;
    private string $tracking_id;

    public function __construct() {
        $this->provider    = get_option( 'mlt_analytics_provider', 'ga4' );
        $this->tracking_id = get_option( 'mlt_analytics_id', '' );

        // Nur einbinden wenn eine ID konfiguriert ist
        if ( empty( $this->tracking_id ) ) return;

        // Admin-Bereich ausschließen
        if ( is_admin() ) return;

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    // ── Script einbinden ──────────────────────────────────────────────────────

    public function enqueue() {
        if ( $this->provider === 'gtm' ) {
            $this->enqueue_gtm();
        } else {
            $this->enqueue_ga4();
        }
    }

    // ── Google Analytics 4 ────────────────────────────────────────────────────

    private function enqueue_ga4() {
        $id = esc_js( $this->tracking_id );

        // gtag.js Script registrieren (deferred, ohne autoload)
        wp_register_script(
            'mlt-gtag',
            'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $this->tracking_id ),
            [],
            null,
            [ 'strategy' => 'defer', 'in_footer' => false ]
        );

        // Inline-Init: wartet auf Consent-Signal
        $inline = $this->ga4_consent_script( $id );
        wp_add_inline_script( 'mlt-gtag', $inline, 'before' );

        // Script nur enqueuen wenn Consent bereits gegeben (z.B. Wiederkehrender Besucher)
        // Neuer Besucher: Script wird via JS nach Consent dynamisch geladen
        wp_enqueue_script( 'mlt-gtag' );
    }

    private function ga4_consent_script( string $id ) : string {
        return <<<JS
(function() {
    'use strict';

    // gtag Basis-Setup (Consent Mode v2 – alles initial denied)
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    window.gtag = gtag;

    gtag('consent', 'default', {
        analytics_storage:    'denied',
        ad_storage:           'denied',
        ad_user_data:         'denied',
        ad_personalization:   'denied',
        wait_for_update:      500
    });

    gtag('js', new Date());
    gtag('config', '{$id}', { send_page_view: false });

    // Consent gewährt → Tracking aktivieren
    function enableAnalytics() {
        gtag('consent', 'update', { analytics_storage: 'granted' });
        gtag('event', 'page_view');
    }

    // ── Bridge: Agency Core cookies:changed → mlt:consent:analytics ──────────
    // Agency Core feuert cookies:changed mit detail = { statistics: bool, ... }
    // Dieses Modul mappt statistics:true auf das MLT-eigene Consent-Event
    // und synchronisiert den localStorage-Key für Wiederkehrende Besucher.
    document.addEventListener('cookies:changed', function(e) {
        if ( e.detail && e.detail.statistics === true ) {
            localStorage.setItem('mlt_consent_analytics', '1');
            document.dispatchEvent(new CustomEvent('mlt:consent:analytics', { bubbles: true }));
        } else if ( e.detail && e.detail.statistics === false ) {
            localStorage.removeItem('mlt_consent_analytics');
        }
    });

    // Direktes MLT-Event (z.B. von externen Integrationen)
    document.addEventListener('mlt:consent:analytics', enableAnalytics);

    // Wiederkehrender Besucher: gespeicherter Consent aus localStorage
    // Prüfung über window.CookieConsent API (Agency Core) bevorzugt
    var alreadyConsented = (
        ( window.CookieConsent && window.CookieConsent.hasConsent('statistics') ) ||
        localStorage.getItem('mlt_consent_analytics') === '1'
    );

    if ( alreadyConsented ) {
        enableAnalytics();
    }
})();
JS;
    }

    // ── Google Tag Manager ────────────────────────────────────────────────────

    private function enqueue_gtm() {
        $id = esc_js( $this->tracking_id );

        // GTM läuft vollständig über Inline-Scripts
        // Script-Handle als Anker für wp_add_inline_script
        wp_register_script( 'mlt-gtm', false, [], null, false );
        wp_enqueue_script( 'mlt-gtm' );

        wp_add_inline_script( 'mlt-gtm', $this->gtm_head_script( $id ) );
        add_action( 'wp_body_open', [ $this, 'gtm_noscript' ] );
    }

    private function gtm_head_script( string $id ) : string {
        return <<<JS
(function() {
    'use strict';

    function loadGTM() {
        (function(w,d,s,l,i){
            w[l]=w[l]||[];
            w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});
            var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s), dl=l!='dataLayer'?'&l='+l:'';
            j.async=true;
            j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
            f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{$id}');
    }

    // Consent Mode v2 Default
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    window.gtag = gtag;

    gtag('consent', 'default', {
        analytics_storage:    'denied',
        ad_storage:           'denied',
        ad_user_data:         'denied',
        ad_personalization:   'denied',
        wait_for_update:      500
    });

    var gtmLoaded = false;

    function enableAndLoad() {
        if ( gtmLoaded ) return;
        gtmLoaded = true;
        gtag('consent', 'update', { analytics_storage: 'granted' });
        loadGTM();
    }

    // ── Bridge: Agency Core cookies:changed → mlt:consent:analytics ──────────
    document.addEventListener('cookies:changed', function(e) {
        if ( e.detail && e.detail.statistics === true ) {
            localStorage.setItem('mlt_consent_analytics', '1');
            document.dispatchEvent(new CustomEvent('mlt:consent:analytics', { bubbles: true }));
        } else if ( e.detail && e.detail.statistics === false ) {
            localStorage.removeItem('mlt_consent_analytics');
        }
    });

    // Direktes MLT-Event
    document.addEventListener('mlt:consent:analytics', enableAndLoad);

    // Wiederkehrender Besucher
    var alreadyConsented = (
        ( window.CookieConsent && window.CookieConsent.hasConsent('statistics') ) ||
        localStorage.getItem('mlt_consent_analytics') === '1'
    );

    if ( alreadyConsented ) {
        enableAndLoad();
    }
})();
JS;
    }

    public function gtm_noscript() {
        $id = esc_attr( $this->tracking_id );
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $id . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
    }
}
