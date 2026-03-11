<?php
/**
 * White Label
 *
 * Customizing des WordPress-Backends für Kunden:
 *   - Login-Screen: Logo, Hintergrund, Primärfarbe, Tab-Titel
 *   - Admin-Bar: Custom Branding
 *   - Dashboard-Widget: Agentur-Info + Kontaktdaten
 *   - Footer-Text
 *   - Agency Core Menü: Rollenbeschränkung (nur für Admins sichtbar)
 */

if (!defined('ABSPATH')) exit;

class MediaLab_White_Label {

    private $opts = array();

    public function __construct() {
        add_action('init', array($this, 'load_options'), 10);
    }

    public function load_options() {
        if (!function_exists('get_field')) return;

        $raw = get_field('white_label', 'option') ?: array();
        $this->opts = wp_parse_args($raw, array(
            'enabled'          => false,
            // Login
            'login_logo'       => '',
            'login_logo_width' => 200,
            'login_bg_color'   => '#1d2327',
            'login_bg_image'   => '',
            'login_primary'    => '#2271b1',
            'login_tab_title'  => '',
            // Branding
            'agency_name'      => '',
            'admin_bar_logo'   => '',
            'footer_text'      => '',
            // Kontakt
            'contact_phone'    => '',
            'contact_email'    => '',
            'contact_url'      => '',
            'contact_text'     => '',
            // Sichtbarkeit
            'hide_menu_roles'  => array(),
        ));

        if (!$this->opts['enabled']) return;

        // Login
        add_action('login_enqueue_scripts',  array($this, 'login_styles'));
        add_filter('login_headerurl',        array($this, 'login_logo_url'));
        add_filter('login_headertext',       array($this, 'login_logo_text'));
        add_filter('document_title_parts',   array($this, 'login_tab_title'));

        // Admin
        add_action('admin_enqueue_scripts',  array($this, 'admin_styles'));
        add_action('admin_bar_menu',         array($this, 'admin_bar_branding'), 1);
        add_filter('admin_footer_text',      array($this, 'footer_text'));

        // Dashboard Widget
        add_action('wp_dashboard_setup',     array($this, 'register_dashboard_widget'));

        // Menü-Sichtbarkeit
        add_action('admin_menu',             array($this, 'restrict_menu_visibility'), 9999);
    }

    // ─────────────────────────────────────────────────────────────
    // LOGIN SCREEN
    // ─────────────────────────────────────────────────────────────

    public function login_styles() {
        $logo       = $this->opts['login_logo'];
        $logo_url   = $logo ? wp_get_attachment_image_url($logo, 'full') : '';
        $logo_width = (int) ($this->opts['login_logo_width'] ?: 200);
        $bg_color   = sanitize_hex_color($this->opts['login_bg_color'] ?: '#1d2327');
        $bg_image   = $this->opts['login_bg_image']
            ? wp_get_attachment_image_url($this->opts['login_bg_image'], 'full')
            : '';
        $primary    = sanitize_hex_color($this->opts['login_primary'] ?: '#2271b1');
        $primary_dark = $this->darken_hex($primary, 15);
        ?>
        <style>
        body.login {
            background-color: <?php echo esc_attr($bg_color); ?>;
            <?php if ($bg_image) : ?>
            background-image: url('<?php echo esc_url($bg_image); ?>');
            background-size: cover;
            background-position: center;
            <?php endif; ?>
        }
        body.login #login {
            background: rgba(255,255,255,0.97);
            border-radius: 10px;
            padding: 32px 40px 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
        }
        <?php if ($logo_url) : ?>
        body.login #login h1 a {
            background-image: url('<?php echo esc_url($logo_url); ?>') !important;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            width: <?php echo $logo_width; ?>px !important;
            height: 80px !important;
        }
        <?php endif; ?>
        body.login .button-primary,
        body.login input[type="submit"] {
            background: <?php echo esc_attr($primary); ?> !important;
            border-color: <?php echo esc_attr($primary_dark); ?> !important;
            box-shadow: 0 2px 8px <?php echo esc_attr($primary); ?>66 !important;
            border-radius: 5px !important;
            padding: 10px 20px !important;
            height: auto !important;
        }
        body.login .button-primary:hover {
            background: <?php echo esc_attr($primary_dark); ?> !important;
        }
        body.login input[type="text"],
        body.login input[type="password"],
        body.login input[type="email"] {
            border-radius: 5px !important;
            border-color: #ddd !important;
            box-shadow: none !important;
            padding: 10px 12px !important;
            height: auto !important;
        }
        body.login input[type="text"]:focus,
        body.login input[type="password"]:focus {
            border-color: <?php echo esc_attr($primary); ?> !important;
            box-shadow: 0 0 0 2px <?php echo esc_attr($primary); ?>33 !important;
        }
        body.login #nav a,
        body.login #backtoblog a { color: <?php echo esc_attr($primary); ?>; }
        body.login .privacy-policy-page-link a { color: <?php echo esc_attr($primary); ?>; }
        </style>
        <?php
    }

    public function login_logo_url() {
        return !empty($this->opts['contact_url']) ? esc_url($this->opts['contact_url']) : home_url('/');
    }

    public function login_logo_text() {
        return !empty($this->opts['agency_name']) ? esc_attr($this->opts['agency_name']) : get_bloginfo('name');
    }

    public function login_tab_title($parts) {
        if (!empty($this->opts['login_tab_title']) && isset($parts['tagline'])) {
            $parts['tagline'] = $this->opts['login_tab_title'];
        }
        return $parts;
    }

    // ─────────────────────────────────────────────────────────────
    // ADMIN STYLES
    // ─────────────────────────────────────────────────────────────

    public function admin_styles() {
        $primary = sanitize_hex_color($this->opts['login_primary'] ?: '#2271b1');
        ?>
        <style>
        /* WP-Logo in Admin-Bar verstecken wenn eigenes Logo vorhanden */
        <?php if (!empty($this->opts['admin_bar_logo'])) : ?>
        #wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon::before { display: none; }
        #wpadminbar #wp-admin-bar-wp-logo > .ab-item {
            background-image: url('<?php echo esc_url(wp_get_attachment_image_url($this->opts['admin_bar_logo'], 'thumbnail')); ?>');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            width: 28px;
        }
        <?php endif; ?>
        </style>
        <?php
    }

    // ─────────────────────────────────────────────────────────────
    // ADMIN BAR BRANDING
    // ─────────────────────────────────────────────────────────────

    public function admin_bar_branding($wp_admin_bar) {
        if (empty($this->opts['agency_name'])) return;

        // WP-Logo-Node umbenennen
        $node = $wp_admin_bar->get_node('wp-logo');
        if ($node) {
            $node->title = '<span class="ab-icon" aria-hidden="true"></span>'
                         . '<span class="screen-reader-text">' . esc_html($this->opts['agency_name']) . '</span>';
            $wp_admin_bar->add_node((array) $node);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // FOOTER TEXT
    // ─────────────────────────────────────────────────────────────

    public function footer_text() {
        $text = $this->opts['footer_text'];
        if (!$text) {
            $name = $this->opts['agency_name'] ?: get_bloginfo('name');
            $text = 'Dieses CMS wurde realisiert von <strong>' . esc_html($name) . '</strong>';
            if ($this->opts['contact_url']) {
                $text = 'Dieses CMS wurde realisiert von <a href="' . esc_url($this->opts['contact_url']) . '" target="_blank"><strong>' . esc_html($name) . '</strong></a>';
            }
        }
        return wp_kses_post($text);
    }

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD WIDGET
    // ─────────────────────────────────────────────────────────────

    public function register_dashboard_widget() {
        $name = $this->opts['agency_name'] ?: 'Ihre Web-Agentur';
        wp_add_dashboard_widget(
            'medialab_agency_widget',
            '🏢 ' . esc_html($name),
            array($this, 'render_dashboard_widget')
        );

        // Widget nach oben verschieben
        global $wp_meta_boxes;
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['medialab_agency_widget'] ?? null;
        if ($widget) {
            unset($wp_meta_boxes['dashboard']['normal']['core']['medialab_agency_widget']);
            array_unshift($wp_meta_boxes['dashboard']['normal']['core'], $widget);
        }
    }

    public function render_dashboard_widget() {
        $name  = $this->opts['agency_name'];
        $phone = $this->opts['contact_phone'];
        $email = $this->opts['contact_email'];
        $url   = $this->opts['contact_url'];
        $text  = $this->opts['contact_text'];
        ?>
        <div style="font-size:13px;line-height:1.7;">
            <?php if ($text) : ?>
                <p style="margin-top:0;"><?php echo wp_kses_post($text); ?></p>
            <?php endif; ?>

            <?php if ($phone || $email || $url) : ?>
            <table style="width:100%;border-collapse:collapse;">
                <?php if ($phone) : ?>
                <tr>
                    <td style="padding:4px 0;color:#888;width:30%;">📞 Telefon</td>
                    <td><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if ($email) : ?>
                <tr>
                    <td style="padding:4px 0;color:#888;">✉️ E-Mail</td>
                    <td><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if ($url) : ?>
                <tr>
                    <td style="padding:4px 0;color:#888;">🌐 Website</td>
                    <td><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener"><?php echo esc_html(preg_replace('#^https?://#', '', $url)); ?></a></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────
    // MENÜ-SICHTBARKEIT
    // ─────────────────────────────────────────────────────────────

    public function restrict_menu_visibility() {
        $allowed_roles = $this->opts['hide_menu_roles'] ?? array();
        if (empty($allowed_roles)) return;

        // Prüfen ob aktueller User eine der erlaubten Rollen hat
        $user = wp_get_current_user();
        $has_role = !empty(array_intersect((array) $user->roles, (array) $allowed_roles));

        if (!$has_role) {
            remove_menu_page('agency-core');
        }
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────────────────

    private function darken_hex($hex, $amount = 10) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = max(0, hexdec(substr($hex,0,2)) - $amount);
        $g = max(0, hexdec(substr($hex,2,2)) - $amount);
        $b = max(0, hexdec(substr($hex,4,2)) - $amount);
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}

new MediaLab_White_Label();
