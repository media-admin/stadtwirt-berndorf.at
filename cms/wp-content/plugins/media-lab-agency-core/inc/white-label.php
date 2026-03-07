<?php
/**
 * White Label / Agency Branding
 * Vollständiges Backend-Branding: Login-Screen, Admin Bar, Dashboard-Widget,
 * Footer-Text. Menü-Sichtbarkeit nach Rolle konfigurierbar.
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 */
if (!defined('ABSPATH')) { exit; }

// ── Login Screen ────────────────────────────────────────────────────────────

add_action('login_enqueue_scripts', function() {
    $logo_url = function_exists('get_field') ? get_field('white_label_login_logo', 'option') : '';
    $bg_color = function_exists('get_field') ? get_field('white_label_login_bg', 'option') : '#f0f0f1';
    $primary  = function_exists('get_field') ? get_field('white_label_primary_color', 'option') : '#2271b1';

    if (!$logo_url && !$primary) return;
    ?>
    <style>
        body.login {
            background-color: <?php echo esc_attr($bg_color ?: '#f0f0f1'); ?>;
        }
        .login h1 a {
            <?php if ($logo_url): ?>
            background-image: url('<?php echo esc_url($logo_url); ?>');
            background-size: contain;
            width: 200px;
            height: 80px;
            <?php endif; ?>
        }
        .wp-core-ui .button-primary {
            background: <?php echo esc_attr($primary ?: '#2271b1'); ?>;
            border-color: <?php echo esc_attr($primary ?: '#2271b1'); ?>;
        }
        .wp-core-ui .button-primary:hover {
            opacity: .85;
        }
    </style>
    <?php
});

add_filter('login_headerurl', function() {
    return home_url();
});

add_filter('login_headertext', function() {
    return get_bloginfo('name');
});

// ── Admin Bar ───────────────────────────────────────────────────────────────

add_action('wp_before_admin_bar_render', function() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_node('wp-logo');
});

// ── Footer Text ─────────────────────────────────────────────────────────────

add_filter('admin_footer_text', function() {
    $text = function_exists('get_field') ? get_field('white_label_footer_text', 'option') : '';
    if (empty($text)) {
        $text = 'Betrieben von <a href="https://medialab.at" target="_blank">Media Lab</a>';
    }
    return wp_kses_post($text);
});

add_filter('update_footer', '__return_empty_string', 99);

// ── Dashboard Widget ────────────────────────────────────────────────────────

add_action('wp_dashboard_setup', function() {
    $title = function_exists('get_field') ? get_field('white_label_widget_title', 'option') : '';
    if (empty($title)) { $title = 'Ihr Website-Team'; }

    wp_add_dashboard_widget(
        'medialab_agency_widget',
        esc_html($title),
        function() {
            $content = function_exists('get_field') ? get_field('white_label_widget_content', 'option') : '';
            $phone   = function_exists('get_field') ? get_field('white_label_phone', 'option') : '';
            $email   = function_exists('get_field') ? get_field('white_label_email', 'option') : '';

            if ($content) {
                echo wp_kses_post(wpautop($content));
            } else {
                echo '<p>' . esc_html__('Bei Fragen wenden Sie sich an Ihr Website-Team.', 'media-lab-core') . '</p>';
            }
            if ($phone || $email) {
                echo '<hr>';
                if ($phone) printf('<p>📞 <a href="tel:%s">%s</a></p>', esc_attr($phone), esc_html($phone));
                if ($email) printf('<p>✉️ <a href="mailto:%s">%s</a></p>', esc_attr($email), esc_html($email));
            }
        }
    );
});

// ── Ungewünschte Dashboard-Widgets entfernen ────────────────────────────────

add_action('wp_dashboard_setup', function() {
    remove_meta_box('dashboard_primary',       'dashboard', 'side');   // WP News
    remove_meta_box('dashboard_quick_press',   'dashboard', 'side');   // Schnell-Entwurf
    remove_meta_box('dashboard_activity',      'dashboard', 'normal'); // Aktivität (optional)
}, 20);
