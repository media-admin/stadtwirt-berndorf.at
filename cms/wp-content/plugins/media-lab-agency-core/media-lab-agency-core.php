<?php
/**
 * Plugin Name: Media Lab Agency Core
 * Plugin URI: https://github.com/media-admin/media-lab-starter-kit
 * Description: Core functionality for Media Lab agency websites. Provides shortcodes, security features, and admin customizations.
 * Version:           1.12.0
 * Author: Media Lab
 * Author URI: https://medialab.at
 * Text Domain: media-lab-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) { exit; }

define('MEDIALAB_CORE_VERSION', '1.12.0');
define('MEDIALAB_CORE_FILE', __FILE__);
define('MEDIALAB_CORE_PATH', plugin_dir_path(__FILE__));
define('MEDIALAB_CORE_URL', plugin_dir_url(__FILE__));
define('MEDIALAB_CORE_BASENAME', plugin_basename(__FILE__));

function medialab_core_init() {
    load_plugin_textdomain('media-lab-core', false, dirname(MEDIALAB_CORE_BASENAME) . '/languages');

    // ── Core ─────────────────────────────────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/shortcodes.php';
    require_once MEDIALAB_CORE_PATH . 'inc/social-share.php';
    require_once MEDIALAB_CORE_PATH . 'inc/security.php';
    require_once MEDIALAB_CORE_PATH . 'inc/admin.php';
    require_once MEDIALAB_CORE_PATH . 'inc/helpers.php';
    require_once MEDIALAB_CORE_PATH . 'inc/ajax-search.php';
    require_once MEDIALAB_CORE_PATH . 'inc/ajax-load-more.php';
    require_once MEDIALAB_CORE_PATH . 'inc/ajax-filters.php';
    require_once MEDIALAB_CORE_PATH . 'inc/svg-support.php';
    require_once MEDIALAB_CORE_PATH . 'inc/activity-log.php';
    require_once MEDIALAB_CORE_PATH . 'inc/hero-image.php';
    require_once MEDIALAB_CORE_PATH . 'inc/notifications-cpt.php';
    require_once MEDIALAB_CORE_PATH . 'inc/notifications-shortcodes.php';
    require_once MEDIALAB_CORE_PATH . 'inc/acf-fields-gmap.php';

    // ── ACF Options + Fields ──────────────────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/acf-settings.php';

    // ── Gutenberg Blocks ──────────────────────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/blocks.php';
    require_once MEDIALAB_CORE_PATH . 'inc/acf-blocks.php';

    // ── Table of Contents — since 1.10.0 ──────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/table-of-contents.php';

    // ── Logo CPT — since 1.11.0 ───────────────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/cpt-logos.php';

    // ── Weitere Komponenten ───────────────────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/multi-language.php';
    require_once MEDIALAB_CORE_PATH . 'inc/post-order.php';
    require_once MEDIALAB_CORE_PATH . 'inc/duplicate-post.php';
    require_once MEDIALAB_CORE_PATH . 'inc/smtp.php';
    require_once MEDIALAB_CORE_PATH . 'inc/email-obfuscation.php';
    require_once MEDIALAB_CORE_PATH . 'inc/white-label.php';
    require_once MEDIALAB_CORE_PATH . 'inc/maintenance.php';
    require_once MEDIALAB_CORE_PATH . 'inc/cookie-consent.php';
    require_once MEDIALAB_CORE_PATH . 'inc/hcaptcha.php';

    // ── Dark Mode Toggle — since 1.12.0 ───────────────────
    require_once MEDIALAB_CORE_PATH . 'inc/dark-mode.php';

    require_once MEDIALAB_CORE_PATH . 'inc/media-replace.php';
}
add_action('plugins_loaded', 'medialab_core_init', 5);

function medialab_core_activate() { flush_rewrite_rules(); }
register_activation_hook(__FILE__, 'medialab_core_activate');

function medialab_core_deactivate() {
    flush_rewrite_rules();
    $ts = wp_next_scheduled('medialab_anonymize_ip_addresses');
    if ($ts) wp_unschedule_event($ts, 'medialab_anonymize_ip_addresses');
}
register_deactivation_hook(__FILE__, 'medialab_core_deactivate');
