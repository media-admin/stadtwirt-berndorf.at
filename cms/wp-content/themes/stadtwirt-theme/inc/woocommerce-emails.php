<?php
/**
 * WooCommerce E-Mail – globale Konfiguration & Filter
 *
 * Steuert Styling, Absender, Content-Type und projektspezifische Anpassungen
 * für alle WooCommerce-E-Mails via Hooks + Filter.
 *
 * Konfigurierbare Filter (alle mit Beispielen):
 *
 *   // Primärfarbe (überschreibt WC-Einstellung)
 *   add_filter( 'customtheme_email_primary_color', fn() => '#003366' );
 *
 *   // Logo-URL (überschreibt WC-Einstellung)
 *   add_filter( 'customtheme_email_logo_url', fn() => get_template_directory_uri() . '/assets/images/logo.png' );
 *
 *   // Footer-Links
 *   add_filter( 'customtheme_email_footer_links', function( $links ) {
 *       $links['AGB'] = home_url( '/agb/' );
 *       return $links;
 *   });
 *
 *   // Bewertungs-CTA in Abgeschlossen-Mail deaktivieren
 *   add_filter( 'customtheme_email_show_review_cta', '__return_false' );
 *
 *   // HTML-Content-Type erzwingen
 *   add_filter( 'customtheme_email_content_type', fn() => 'text/html' );
 *
 * @package CustomTheme\WooCommerceEmails
 * @since   1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Gesamte Datei nur laden wenn WooCommerce aktiv ist
if ( ! class_exists( 'WooCommerce' ) ) return;

// =============================================================================
// Content-Type: HTML erzwingen
// =============================================================================

add_filter( 'woocommerce_email_content_type', 'customtheme_email_content_type' );

function customtheme_email_content_type( string $content_type ): string {
    return apply_filters( 'customtheme_email_content_type', 'text/html' );
}

// =============================================================================
// Absender-Name + Adresse (aus WP-Einstellungen + Filter)
// =============================================================================

add_filter( 'wp_mail_from_name', 'customtheme_email_from_name' );

function customtheme_email_from_name( string $name ): string {
    // Nur WooCommerce-E-Mails überschreiben
    if ( doing_action( 'woocommerce_email' ) || class_exists( 'WC_Emails' ) ) {
        $wc_name = get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) );
        return apply_filters( 'customtheme_email_from_name', $wc_name ?: $name );
    }
    return $name;
}

add_filter( 'wp_mail_from', 'customtheme_email_from_address' );

function customtheme_email_from_address( string $email ): string {
    if ( doing_action( 'woocommerce_email' ) || class_exists( 'WC_Emails' ) ) {
        $wc_email = get_option( 'woocommerce_email_from_address', $email );
        return apply_filters( 'customtheme_email_from_address', $wc_email ?: $email );
    }
    return $email;
}

// =============================================================================
// CSS-Inline-Styles: WC-Standard durch Theme-Styles ersetzen
// =============================================================================

/**
 * Überschreibt WC-Standard-CSS mit Theme-CSS aus email-styles.php.
 * remove_action() erst nach WC-Init – WC() ist beim Theme-Load noch nicht verfügbar.
 */
add_action( 'woocommerce_init', function(): void {
    $mailer = WC()->mailer();
    if ( $mailer && has_action( 'woocommerce_email_styles', [ $mailer, 'add_inline_styles' ] ) ) {
        remove_action( 'woocommerce_email_styles', [ $mailer, 'add_inline_styles' ] );
    }
} );

add_action( 'woocommerce_email_styles', 'customtheme_email_styles', 10, 2 );

function customtheme_email_styles( WC_Email $email ): void {
    $styles_file = get_template_directory() . '/woocommerce/emails/email-styles.php';
    if ( file_exists( $styles_file ) ) {
        include $styles_file;
    }
}

// =============================================================================
// E-Mail-Klassen-Anpassungen (globales Styling via WC-API)
// =============================================================================

add_filter( 'woocommerce_email_classes', 'customtheme_email_classes' );

function customtheme_email_classes( array $email_classes ): array {
    // Alle E-Mail-Klassen bekommen unsere Farbwerte
    foreach ( $email_classes as $class ) {
        if ( ! is_a( $class, 'WC_Email', true ) ) continue;
        // WC liest Farben aus Optionen – die werden im Setup gesetzt
    }
    return $email_classes;
}

// =============================================================================
// WooCommerce E-Mail-Farben aus Theme-Variablen initialisieren
// =============================================================================

/**
 * Setzt WC-E-Mail-Farboptionen auf Theme-Defaults wenn sie noch auf WC-Standard stehen.
 * Nur einmal beim Theme-Aktivieren ausgeführt (via Option-Check).
 */
add_action( 'after_switch_theme', 'customtheme_init_email_colors' );
add_action( 'init',               'customtheme_init_email_colors' );

function customtheme_init_email_colors(): void {
    // Nur wenn noch nicht initialisiert
    if ( get_option( 'customtheme_email_colors_initialized' ) ) return;

    $defaults = [
        'woocommerce_email_background_color'      => '#f7f7f7',
        'woocommerce_email_body_background_color'  => '#ffffff',
        'woocommerce_email_base_color'             => '#ff0000', // Theme Primary
        'woocommerce_email_text_color'             => '#1a1a1a',
    ];

    foreach ( $defaults as $option => $value ) {
        // Nur setzen wenn noch WC-Standard oder leer
        $current = get_option( $option );
        if ( empty( $current ) || in_array( $current, [ '#7f54b3', '#ebe9eb' ], true ) ) {
            update_option( $option, $value );
        }
    }

    update_option( 'customtheme_email_colors_initialized', true );
}

// =============================================================================
// E-Mail-Betreff-Anpassungen (Beispiele, via Filter steuerbar)
// =============================================================================

/**
 * Filter für Betreff-Zeilen – projektspezifisch überschreibbar:
 *
 *   add_filter( 'woocommerce_email_subject_customer_processing_order',
 *       fn( $subject, $order, $email ) => 'Deine Bestellung #' . $order->get_order_number(),
 *   10, 3 );
 */

// Standardmäßig Site-Name im Betreff voranstellen wenn nicht vorhanden
add_filter( 'woocommerce_email_subject_customer_processing_order', 'customtheme_email_subject_prefix', 5, 3 );
add_filter( 'woocommerce_email_subject_customer_completed_order',  'customtheme_email_subject_prefix', 5, 3 );
add_filter( 'woocommerce_email_subject_customer_invoice',          'customtheme_email_subject_prefix', 5, 3 );

function customtheme_email_subject_prefix( string $subject, WC_Order $order, WC_Email $email ): string {
    // Kein Prefix wenn bereits vorhanden oder via Filter deaktiviert
    if ( ! apply_filters( 'customtheme_email_prefix_subject', false ) ) return $subject;
    $site_name = get_bloginfo( 'name' );
    if ( str_contains( $subject, $site_name ) ) return $subject;
    return '[' . $site_name . '] ' . $subject;
}

// =============================================================================
// Admin-Bestellbenachrichtigung: Bestelllink direkt im Text
// =============================================================================

add_action( 'woocommerce_email_before_order_table', 'customtheme_email_admin_order_link', 10, 4 );

function customtheme_email_admin_order_link( WC_Order $order, bool $sent_to_admin, bool $plain_text, WC_Email $email ): void {
    if ( ! $sent_to_admin || $plain_text ) return;

    $admin_url = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
    echo '<p style="margin:0 0 16px;font-size:13px;color:#9b9b9b;">';
    echo '<a href="' . esc_url( $admin_url ) . '" style="color:#9b9b9b;">';
    printf(
        /* translators: %s: order number */
        esc_html__( 'Bestellung #%s im Backend öffnen →', 'customtheme' ),
        esc_html( $order->get_order_number() )
    );
    echo '</a></p>';
}
