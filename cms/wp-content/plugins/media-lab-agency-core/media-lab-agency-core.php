<?php
/**
 * Plugin Name: Media Lab Agency Core
 * Plugin URI: https://github.com/media-admin/media-lab-starter-kit
 * Description: Core functionality for Media Lab agency websites. Provides shortcodes, security features, and admin customizations.
 * Version:           1.6.0
 * Author: Media Lab
 * Author URI: https://medialab.at
 * Text Domain: media-lab-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin Constants
define('MEDIALAB_CORE_VERSION', '1.5.4');
define('MEDIALAB_CORE_FILE', __FILE__);
define('MEDIALAB_CORE_PATH', plugin_dir_path(__FILE__));
define('MEDIALAB_CORE_URL', plugin_dir_url(__FILE__));
define('MEDIALAB_CORE_BASENAME', plugin_basename(__FILE__));

/**
 * Initialize Plugin
 */
function medialab_core_init() {
    // Load text domain
    load_plugin_textdomain('media-lab-core', false, dirname(MEDIALAB_CORE_BASENAME) . '/languages');

    // Load core components (each file only once)
    require_once MEDIALAB_CORE_PATH . 'inc/shortcodes.php';
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

    // ACF: Options Page + all field groups (Top Header, Multi-Language)
    require_once MEDIALAB_CORE_PATH . 'inc/acf-settings.php';

    // Gutenberg Custom Blocks
    require_once MEDIALAB_CORE_PATH . 'inc/blocks.php';

    // Multi-Language (checks ACF toggle internally before activating)
    require_once MEDIALAB_CORE_PATH . 'inc/multi-language.php';

    // Drag & Drop Post Order
    require_once MEDIALAB_CORE_PATH . 'inc/post-order.php';

    // Duplicate Post / Term
    require_once MEDIALAB_CORE_PATH . 'inc/duplicate-post.php';

    // SMTP Mailer
    require_once MEDIALAB_CORE_PATH . 'inc/smtp.php';

    // E-Mail Obfuskierung / Spam-Schutz
    require_once MEDIALAB_CORE_PATH . 'inc/email-obfuscation.php';

    // White Label / Agentur-Branding
    require_once MEDIALAB_CORE_PATH . 'inc/white-label.php';

    // Maintenance Mode
    require_once MEDIALAB_CORE_PATH . 'inc/maintenance.php';

    // Cookie Consent
    require_once MEDIALAB_CORE_PATH . 'inc/cookie-consent.php';
    require_once MEDIALAB_CORE_PATH . 'inc/hcaptcha.php';

    // Media Replace
    require_once MEDIALAB_CORE_PATH . 'inc/media-replace.php';
}
add_action('plugins_loaded', 'medialab_core_init', 5);

/**
 * Activation Hook
 */
function medialab_core_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'medialab_core_activate');

/**
 * Deactivation Hook
 */
function medialab_core_deactivate() {
    flush_rewrite_rules();
    // Cron-Job für IP-Anonymisierung entfernen
    $timestamp = wp_next_scheduled('medialab_anonymize_ip_addresses');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'medialab_anonymize_ip_addresses');
    }
}
register_deactivation_hook(__FILE__, 'medialab_core_deactivate');
