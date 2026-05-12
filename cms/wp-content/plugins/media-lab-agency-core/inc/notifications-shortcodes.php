<?php
/**
 * Notification Shortcodes
 *
 * [notification type="info" title="Hinweis" dismissible="true"]Text[/notification]
 * [notification_inline type="warning"]Text[/notification_inline]
 * [site_notifications display="shortcode"]
 *
 * Neu in 1.10.0:
 *  - `media_lab_build_notification()` priorisiert `$content` (Gutenberg-HTML)
 *    über `$message` (ACF-Textarea-Fallback)
 *  - Layout-Variante `banner-img` rendert Bild links neben dem Text
 *  - Layout-Variante `compact` rendert einzeilig ohne Gutenberg-Content
 *
 * @package MediaLab_Core
 * @since   1.0.0
 * @updated 1.10.0  Gutenberg-Content + Layout-Varianten
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// HELPER: NOTIFICATION HTML BAUEN
// =============================================================================

/**
 * Baut das Notification-HTML.
 *
 * Inhalt-Hierarchie:
 *   1. $content_html  – Gutenberg-Blöcke (HTML, bereits gefiltert)
 *   2. $message       – ACF-Textarea-Fallback / Kurztext
 *
 * @param string      $type         info|success|warning|error
 * @param string      $message      Kurztext / ACF-Fallback
 * @param string      $title        Überschrift (optional)
 * @param string      $icon         Dashicon-Klasse, 'auto' oder 'none'
 * @param bool        $dismissible  Schließen-Button anzeigen?
 * @param string      $extra_class  Zusätzliche CSS-Klassen am Wrapper
 * @param int|null    $cpt_id       CPT-Post-ID (für dismiss-Memory)
 * @param string      $layout       standard|compact|banner-img
 * @param string      $content_html Gutenberg-HTML (bevorzugt)
 * @param array|null  $image        ACF Image-Array für banner-img
 */
function media_lab_build_notification(
    string $type,
    string $message,
    string $title        = '',
    string $icon         = 'auto',
    bool   $dismissible  = true,
    string $extra_class  = '',
    ?int   $cpt_id       = null,
    string $layout       = 'standard',
    string $content_html = '',
    ?array $image        = null
): string {

    // ── Icon auflösen ─────────────────────────────────────────────────────────
    $icon_map = array(
        'info'    => 'dashicons-info',
        'success' => 'dashicons-yes-alt',
        'warning' => 'dashicons-warning',
        'error'   => 'dashicons-dismiss',
    );

    if ( $icon === 'auto' ) {
        $icon = $icon_map[ $type ] ?? 'dashicons-info';
    }

    // ── Inhalt bestimmen ──────────────────────────────────────────────────────
    // 1. Gutenberg-HTML, 2. ACF-Message-Fallback, 3. Shortcode-Content
    $body_html = ! empty( $content_html )
        ? $content_html
        : ( ! empty( $message ) ? wpautop( do_shortcode( $message ) ) : '' );

    // Für compact-Layout: nur der erste Absatz / erste Zeile
    if ( $layout === 'compact' ) {
        // Nur Text – kein Gutenberg-HTML im compact-Modus
        $compact_text = ! empty( $message )
            ? $message
            : wp_strip_all_tags( $content_html );
        $compact_text = wp_trim_words( $compact_text, 30 );
        $body_html    = esc_html( $compact_text );
    }

    // ── Wrapper ───────────────────────────────────────────────────────────────
    $id               = 'notification-' . uniqid();
    $layout_class     = 'notification--layout-' . sanitize_html_class( $layout );
    $dismissible_class = $dismissible ? ' notification--dismissible' : '';
    $cpt_attr          = $cpt_id ? ' data-notification-id="' . intval( $cpt_id ) . '"' : '';
    $extra             = $extra_class ? ' ' . esc_attr( trim( $extra_class ) ) : '';

    $html  = '<div class="notification notification--' . esc_attr( $type )
           . ' ' . $layout_class
           . $dismissible_class
           . $extra
           . '" id="' . $id . '"'
           . $cpt_attr
           . ' role="alert">';

    // ── Banner-mit-Bild: Bild-Spalte ─────────────────────────────────────────
    if ( $layout === 'banner-img' && ! empty( $image ) ) {
        $img_url = is_array( $image ) ? ( $image['sizes']['thumbnail'] ?? $image['url'] ?? '' ) : '';
        $img_alt = is_array( $image ) ? ( $image['alt'] ?? '' ) : '';
        if ( $img_url ) {
            $html .= '<div class="notification__image">';
            $html .= '<img src="' . esc_url( $img_url ) . '"'
                   . ' alt="' . esc_attr( $img_alt ) . '"'
                   . ' width="80" height="80" loading="lazy">';
            $html .= '</div>';
        }
    }

    // ── Icon ─────────────────────────────────────────────────────────────────
    if ( $icon && $icon !== 'none' && $layout !== 'banner-img' ) {
        $html .= '<div class="notification__icon">'
               . '<span class="dashicons ' . esc_attr( $icon ) . '"></span>'
               . '</div>';
    }

    // ── Content ───────────────────────────────────────────────────────────────
    $html .= '<div class="notification__content">';

    if ( $title ) {
        $html .= '<div class="notification__title">' . esc_html( $title ) . '</div>';
    }

    if ( $body_html ) {
        $html .= '<div class="notification__message notification__message--gutenberg">'
               . wp_kses_post( $body_html )
               . '</div>';
    }

    $html .= '</div>'; // .notification__content

    // ── Schließen-Button ─────────────────────────────────────────────────────
    if ( $dismissible ) {
        $html .= '<button class="notification__dismiss"'
               . ' data-dismiss="' . $id . '"'
               . ' aria-label="' . esc_attr__( 'Schließen', 'media-lab-core' ) . '">'
               . '&times;'
               . '</button>';
    }

    $html .= '</div>'; // .notification

    return $html;
}

// =============================================================================
// SHORTCODES
// =============================================================================

/**
 * [notification] – Hardcoded (kein CPT)
 */
add_shortcode( 'notification', function ( $atts, $content = null ) {
    $atts = shortcode_atts( array(
        'type'        => 'info',
        'title'       => '',
        'dismissible' => 'true',
        'icon'        => 'auto',
        'layout'      => 'standard',
    ), $atts );

    return media_lab_build_notification(
        $atts['type'],
        do_shortcode( $content ?? '' ),
        $atts['title'],
        $atts['icon'],
        $atts['dismissible'] === 'true',
        '',
        null,
        $atts['layout']
    );
} );

/**
 * [notification_inline] – Kompakt, einzeilig
 */
add_shortcode( 'notification_inline', function ( $atts, $content = null ) {
    $atts = shortcode_atts( array(
        'type' => 'info',
        'icon' => 'auto',
    ), $atts );

    $icons = array(
        'info'    => 'dashicons-info',
        'success' => 'dashicons-yes-alt',
        'warning' => 'dashicons-warning',
        'error'   => 'dashicons-dismiss',
    );

    $icon = $atts['icon'] === 'auto'
        ? ( $icons[ $atts['type'] ] ?? 'dashicons-info' )
        : $atts['icon'];

    $html  = '<div class="notification notification--inline notification--' . esc_attr( $atts['type'] ) . '" role="alert">';
    if ( $icon && $icon !== 'none' ) {
        $html .= '<span class="dashicons ' . esc_attr( $icon ) . '"></span> ';
    }
    $html .= '<span>' . wp_kses_post( do_shortcode( $content ?? '' ) ) . '</span>';
    $html .= '</div>';

    return $html;
} );

/**
 * [site_notifications display="shortcode"] – Zieht aus CPT
 */
add_shortcode( 'site_notifications', function ( $atts ) {
    $atts = shortcode_atts( array( 'display' => 'shortcode' ), $atts );

    $notifications = media_lab_get_active_notifications( $atts['display'] );
    if ( empty( $notifications ) ) return '';

    $html = '';
    foreach ( $notifications as $n ) {
        $html .= media_lab_build_notification(
            $n['type'],
            $n['message'],
            $n['title'],
            $n['icon'],
            $n['dismissible'],
            '',
            $n['id'],
            $n['layout'],
            $n['content'],
            $n['image']
        );
    }

    return $html;
} );

// =============================================================================
// AUTO-OUTPUT: BANNER + POPUP/TOAST
// =============================================================================

/**
 * Siteweiter Banner – automatisch nach wp_body_open
 */
add_action( 'wp_body_open', function () {
    $banners = media_lab_get_active_notifications( 'banner' );
    if ( empty( $banners ) ) return;

    echo '<div class="site-notifications-banner">';
    foreach ( $banners as $n ) {
        echo media_lab_build_notification(  // phpcs:ignore WordPress.Security.EscapeOutput
            $n['type'],
            $n['message'],
            $n['title'],
            $n['icon'],
            $n['dismissible'],
            ' notification--banner',
            $n['id'],
            $n['layout'],
            $n['content'],
            $n['image']
        );
    }
    echo '</div>';
} );

/**
 * Popup + Toast – Daten für JS bereitstellen
 */
add_action( 'wp_footer', function () {
    $popups = media_lab_get_active_notifications( 'popup' );
    $toasts = media_lab_get_active_notifications( 'toast' );

    if ( empty( $popups ) && empty( $toasts ) ) return;

    // content_html bereits als HTML – für JS als JSON-escaped String
    echo '<script>window.mediaLabNotifications = '
        . wp_json_encode( array( 'popups' => $popups, 'toasts' => $toasts ), JSON_UNESCAPED_UNICODE )
        . ';</script>' . "\n";
} );
