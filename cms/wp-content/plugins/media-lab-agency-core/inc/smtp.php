<?php
/**
 * SMTP Mailer
 *
 * Konfigurierbar über Agency Core Settings (ACF).
 * Ersetzt den WP-Standard-Mailer (PHP mail()) via PHPMailer-Hook.
 * Enthält Test-Mail-Funktion direkt im Backend.
 */

if (!defined('ABSPATH')) exit;

class MediaLab_SMTP {

    public function __construct() {
        add_action('phpmailer_init',  array($this, 'configure_phpmailer'));
        add_action('wp_mail_failed',  array($this, 'log_mail_error'));
        add_action('wp_ajax_medialab_send_test_mail', array($this, 'ajax_send_test_mail'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_script'));
    }

    /**
     * Nonce sicher via wp_localize_script übergeben (F-06)
     * Ersetzt das fragile Inline-Nonce im ACF Message Field.
     */
    public function enqueue_admin_script( $hook ) {
        // Nur auf der Agency Core Settings Seite laden
        if ( strpos( $hook, 'agency-core-smtp' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'medialab-smtp-test',
            MEDIALAB_CORE_URL . 'assets/js/smtp-test.js',
            array( 'jquery' ),
            MEDIALAB_CORE_VERSION,
            true
        );

        wp_localize_script( 'medialab-smtp-test', 'medialabSmtp', array(
            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'medialab_smtp_test' ),
            'defaultEmail' => sanitize_email( get_option( 'admin_email' ) ),
        ) );
    }

    /**
     * PHPMailer mit SMTP-Einstellungen konfigurieren
     *
     * Credentials-Priorität (F-05):
     *   1. Konstanten in wp-config.php (MEDIALAB_SMTP_HOST, _PORT, _USER, _PASS, _FROM, _FROM_NAME, _ENC)
     *   2. ACF Options (Fallback – Passwort dann in DB im Klartext)
     *
     * Empfehlung: Konstanten in wp-config.php definieren:
     *   define('MEDIALAB_SMTP_HOST', 'smtp.example.com');
     *   define('MEDIALAB_SMTP_PORT', 587);
     *   define('MEDIALAB_SMTP_USER', 'user@example.com');
     *   define('MEDIALAB_SMTP_PASS', 'geheimes-passwort');
     *   define('MEDIALAB_SMTP_ENC',  'tls');   // tls | ssl | ''
     *   define('MEDIALAB_SMTP_FROM', 'noreply@example.com');
     *   define('MEDIALAB_SMTP_FROM_NAME', 'Meine Website');
     */
    public function configure_phpmailer($phpmailer) {
        $opts = $this->get_options();
        if (!$opts['enabled'] || empty($opts['host'])) return;

        $phpmailer->isSMTP();
        $phpmailer->Host       = $opts['host'];
        $phpmailer->Port       = (int) $opts['port'];
        $phpmailer->SMTPAuth   = !empty($opts['username']);
        $phpmailer->Username   = $opts['username'];
        $phpmailer->Password   = $opts['password'];
        $phpmailer->SMTPSecure = $opts['encryption'];
        $phpmailer->From       = $opts['from_email'] ?: get_option('admin_email');
        $phpmailer->FromName   = $opts['from_name']  ?: get_bloginfo('name');

        if ($opts['smtp_debug']) {
            $phpmailer->SMTPDebug = 2;
        }
    }

    /**
     * Mail-Fehler ins Activity Log schreiben
     */
    public function log_mail_error($wp_error) {
        if (function_exists('medialab_log_activity')) {
            medialab_log_activity('mail_error', $wp_error->get_error_message());
        }
        error_log('MediaLab SMTP Error: ' . $wp_error->get_error_message());
    }

    /**
     * AJAX: Test-Mail senden
     */
    public function ajax_send_test_mail() {
        check_ajax_referer('medialab_smtp_test', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Keine Berechtigung');

        $to = sanitize_email($_POST['to'] ?? get_option('admin_email'));

        $result = wp_mail(
            $to,
            'Media Lab SMTP Test-Mail',
            "Diese Test-Mail wurde erfolgreich über den konfigurierten SMTP-Server versendet.\n\n"
            . "Zeitpunkt: " . current_time('d.m.Y H:i:s') . "\n"
            . "Site: " . get_bloginfo('name') . " (" . home_url() . ")"
        );

        if ($result) {
            wp_send_json_success('Test-Mail erfolgreich gesendet an ' . $to);
        } else {
            global $phpmailer;
            $error = isset($phpmailer->ErrorInfo) ? $phpmailer->ErrorInfo : 'Unbekannter Fehler';
            wp_send_json_error('Fehler: ' . $error);
        }
    }

    /**
     * Optionen aus ACF holen (mit Fallback auf wp_options)
     */
    public function get_options() {
        // ACF als Basis (UI-Konfiguration)
        $smtp = array();
        if (function_exists('get_field')) {
            $smtp = get_field('smtp_settings', 'option') ?: array();
        }

        $opts = array(
            'enabled'    => !empty($smtp['enabled']),
            'host'       => $smtp['host']       ?? '',
            'port'       => $smtp['port']       ?? 587,
            'username'   => $smtp['username']   ?? '',
            'password'   => $smtp['password']   ?? '',
            'encryption' => $smtp['encryption'] ?? 'tls',
            'from_email' => $smtp['from_email'] ?? '',
            'from_name'  => $smtp['from_name']  ?? '',
            'smtp_debug' => !empty($smtp['smtp_debug']),
        );

        // wp-config.php Konstanten überschreiben ACF-Werte (F-05)
        // Vorteil: Credentials nie in der Datenbank im Klartext
        if (defined('MEDIALAB_SMTP_HOST')      && MEDIALAB_SMTP_HOST)      $opts['host']       = MEDIALAB_SMTP_HOST;
        if (defined('MEDIALAB_SMTP_PORT')      && MEDIALAB_SMTP_PORT)      $opts['port']       = (int) MEDIALAB_SMTP_PORT;
        if (defined('MEDIALAB_SMTP_USER')      && MEDIALAB_SMTP_USER)      $opts['username']   = MEDIALAB_SMTP_USER;
        if (defined('MEDIALAB_SMTP_PASS')      && MEDIALAB_SMTP_PASS)      $opts['password']   = MEDIALAB_SMTP_PASS;
        if (defined('MEDIALAB_SMTP_ENC')       && MEDIALAB_SMTP_ENC !== '') $opts['encryption'] = MEDIALAB_SMTP_ENC;
        if (defined('MEDIALAB_SMTP_FROM')      && MEDIALAB_SMTP_FROM)      $opts['from_email'] = MEDIALAB_SMTP_FROM;
        if (defined('MEDIALAB_SMTP_FROM_NAME') && MEDIALAB_SMTP_FROM_NAME) $opts['from_name']  = MEDIALAB_SMTP_FROM_NAME;
        if (defined('MEDIALAB_SMTP_ENABLED'))                              $opts['enabled']    = (bool) MEDIALAB_SMTP_ENABLED;

        return $opts;
    }
}

new MediaLab_SMTP();
