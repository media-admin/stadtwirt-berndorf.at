<?php
/**
 * Notification Shortcodes
 * 
 * [notification type="info" title="Hinweis" dismissible="true"]Text[/notification]
 * [notification_inline type="warning"]Text[/notification_inline]
 * [site_notifications display="shortcode"]
 */

if (!defined('ABSPATH')) exit;

/**
 * Helper: Baut Notification HTML
 */
function media_lab_build_notification($type, $message, $title = '', $icon = 'auto', $dismissible = true, $extra_class = '', $cpt_id = null) {
    $icons = array(
        'info'    => 'dashicons-info',
        'success' => 'dashicons-yes-alt',
        'warning' => 'dashicons-warning',
        'error'   => 'dashicons-dismiss',
    );

    if ($icon === 'auto') {
        $icon = isset($icons[$type]) ? $icons[$type] : 'dashicons-info';
    }

    $id               = 'notification-' . uniqid();
    $dismissible_class = $dismissible ? ' notification--dismissible' : '';

    $cpt_id = isset($cpt_id) ? ' data-notification-id="' . intval($cpt_id) . '"' : '';
    $html  = '<div class="notification notification--' . esc_attr($type) . $dismissible_class . esc_attr($extra_class) . '" id="' . $id . '"' . $cpt_id . ' role="alert">';

    if ($icon && $icon !== 'none') {
        $html .= '<div class="notification__icon"><span class="dashicons ' . esc_attr($icon) . '"></span></div>';
    }

    $html .= '<div class="notification__content">';
    if ($title) {
        $html .= '<div class="notification__title">' . esc_html($title) . '</div>';
    }
    $html .= '<div class="notification__message">' . wpautop(do_shortcode($message)) . '</div>';
    $html .= '</div>';

    if ($dismissible) {
        $html .= '<button class="notification__dismiss" data-dismiss="' . $id . '" aria-label="Schließen">&times;</button>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * [notification] - Inline Shortcode (hardcoded)
 */
add_shortcode('notification', function($atts, $content = null) {
    $atts = shortcode_atts(array(
        'type'        => 'info',
        'title'       => '',
        'dismissible' => 'true',
        'icon'        => 'auto',
    ), $atts);

    return media_lab_build_notification(
        $atts['type'],
        $content,
        $atts['title'],
        $atts['icon'],
        $atts['dismissible'] === 'true'
    );
});

/**
 * [notification_inline] - Kompakte Inline-Version
 */
add_shortcode('notification_inline', function($atts, $content = null) {
    $atts = shortcode_atts(array(
        'type' => 'info',
        'icon' => 'auto',
    ), $atts);

    $icons = array(
        'info'    => 'dashicons-info',
        'success' => 'dashicons-yes-alt',
        'warning' => 'dashicons-warning',
        'error'   => 'dashicons-dismiss',
    );

    $icon = $atts['icon'] === 'auto'
        ? ($icons[$atts['type']] ?? 'dashicons-info')
        : $atts['icon'];

    $html  = '<div class="notification notification--inline notification--' . esc_attr($atts['type']) . '" role="alert">';
    if ($icon && $icon !== 'none') {
        $html .= '<span class="dashicons ' . esc_attr($icon) . '"></span> ';
    }
    $html .= '<span>' . esc_html($content) . '</span>';
    $html .= '</div>';

    return $html;
});

/**
 * [site_notifications display="shortcode"] - Zieht aus CPT
 */
add_shortcode('site_notifications', function($atts) {
    $atts = shortcode_atts(array(
        'display' => 'shortcode',
    ), $atts);

    $notifications = media_lab_get_active_notifications($atts['display']);

    if (empty($notifications)) return '';

    $html = '';
    foreach ($notifications as $n) {
        $html .= media_lab_build_notification(
            $n['type'],
            $n['message'],
            $n['title'],
            $n['icon'],
            $n['dismissible'],
            '',
            $n['id']
        );
    }

    return $html;
});

/**
 * Siteweite Banner - automatisch im Header ausgeben
 */
add_action('wp_body_open', function() {
    $banners = media_lab_get_active_notifications('banner');
    if (empty($banners)) return;

    echo '<div class="site-notifications-banner">';
    foreach ($banners as $n) {
        echo media_lab_build_notification(
            $n['type'],
            $n['message'],
            $n['title'],
            $n['icon'],
            $n['dismissible'],
            ' notification--banner'
        );
    }
    echo '</div>';
});

/**
 * Popup + Toast - Daten per JS ausgeben
 */
add_action('wp_footer', function() {
    $popups = media_lab_get_active_notifications('popup');
    $toasts = media_lab_get_active_notifications('toast');

    if (empty($popups) && empty($toasts)) return;

    $data = array(
        'popups' => $popups,
        'toasts' => $toasts,
    );

    echo '<script>window.mediaLabNotifications = ' . json_encode($data) . ';</script>';
});
