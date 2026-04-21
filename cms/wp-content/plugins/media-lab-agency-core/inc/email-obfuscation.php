<?php
/**
 * E-Mail & Link Obfuscation
 *
 * Schützt E-Mail-Adressen und optional alle Links vor Spam-Bots:
 *
 * 1. E-Mail-Adressen im Content → ROT13-kodiert + JS-Decoder
 *    (Funktioniert ohne JS als mailto:-Link sichtbar, mit JS als klickbarer Link)
 *
 * 2. Shortcode [obfuscate_email email="..." label="..."]
 *
 * 3. Optional: alle mailto:-Links im Content automatisch schützen
 *
 * Die Dekodierung passiert clientseitig via kleinstem Inline-JS –
 * kein externes Script nötig.
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Email_Obfuscation {

    private $enabled      = false;
    private $auto_protect = false; // Alle mailto: im Content automatisch schützen

    public function __construct() {
        add_action('init', array($this, 'boot'), 5);
    }

    public function boot() {
        if (!function_exists('get_field')) return;

        $obf = get_field('obfuscation_settings', 'option') ?: array();
        $this->enabled      = !empty($obf['enabled']);
        $this->auto_protect = !empty($obf['auto_protect']);

        if (!$this->enabled) return;

        // Shortcode
        add_shortcode('obfuscate_email', array($this, 'shortcode'));

        // Automatischer Schutz aller mailto:-Links im Content
        if ($this->auto_protect) {
            add_filter('the_content',  array($this, 'protect_content_emails'), 20);
            add_filter('widget_text',  array($this, 'protect_content_emails'), 20);
            add_filter('acf_the_content', array($this, 'protect_content_emails'), 20);
        }

        // Inline-Decoder-Script einmalig im Footer ausgeben
        add_action('wp_footer', array($this, 'output_decoder_script'), 5);
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE: [obfuscate_email email="info@example.com" label="Schreib uns"]
    // ─────────────────────────────────────────────────────────────

    public function shortcode($atts) {
        $atts = shortcode_atts(array(
            'email' => '',
            'label'   => ' ',
            'class' => '',
        ), $atts);

        if (empty($atts['email'])) return '';

        return $this->build_obfuscated_link(
            $atts['email'],
            $atts['label'] ?: $atts['email'],
            $atts['class']
        );
    }

    // ─────────────────────────────────────────────────────────────
    // AUTOMATISCHER CONTENT-SCHUTZ
    // ─────────────────────────────────────────────────────────────

    public function protect_content_emails($content) {
        // mailto:-Links ersetzen
        $content = preg_replace_callback(
            '/<a([^>]*)\bhref=["\']mailto:([^"\']+)["\']([^>]*)>(.*?)<\/a>/is',
            function ($m) {
                $email      = $m[2];
                $inner_html = $m[4];
                // Label: wenn der Linktext == E-Mail-Adresse, ebenfalls schützen
                $label = (trim(strip_tags($inner_html)) === $email)
                    ? null // wird durch JS ersetzt
                    : $inner_html;
                return $this->build_obfuscated_link($email, $label, '');
            },
            $content
        );

        // Nackte E-Mail-Adressen (ohne Link) ersetzen
        $content = preg_replace_callback(
            '/(?<!["\'=>])([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})(?!["\'])/',
            function ($m) {
                return $this->build_obfuscated_link($m[1], $m[1], '');
            },
            $content
        );

        return $content;
    }

    // ─────────────────────────────────────────────────────────────
    // OBFUSKIERTER LINK AUFBAUEN
    // ─────────────────────────────────────────────────────────────

    private function build_obfuscated_link($email, $label, $class) {
        // ROT13 für E-Mail-Adresse und Label
        $encoded_email = str_rot13($email);
        $encoded_label = $label ? str_rot13(is_string($label) ? strip_tags($label) : $email) : null;

        $class_attr = $class ? ' class="' . esc_attr($class) . '"' : '';

        // data-Attribute enthalten ROT13-kodierten Wert
        // JS dekodiert beim Klick/Hover
        return sprintf(
            '<a href="#" data-obf-email="%s" data-obf-label="%s"%s onclick="return false;">%s</a>',
            esc_attr($encoded_email),
            esc_attr($encoded_label ?? $encoded_email),
            $class_attr,
            // Fallback-Text ohne JS: lesbarer Hinweis
            '<noscript>' . esc_html($email) . '</noscript>'
            . '<span class="obf-placeholder" aria-hidden="true">&#9993;</span>'
        );
    }

    // ─────────────────────────────────────────────────────────────
    // DECODER-SCRIPT (einmalig im Footer)
    // ─────────────────────────────────────────────────────────────

    public function output_decoder_script() {
        ?>
        <script>
        /* MediaLab Email Obfuscation Decoder */
        (function(){
            function rot13(s){
                return s.replace(/[a-zA-Z]/g,function(c){
                    return String.fromCharCode(
                        (c<='Z'?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26
                    );
                });
            }
            function decodeLinks(){
                document.querySelectorAll('a[data-obf-email]').forEach(function(el){
                    var email = rot13(el.dataset.obfEmail);
                    var label = rot13(el.dataset.obfLabel || el.dataset.obfEmail);
                    el.href = 'mailto:' + email;
                    el.onclick = null;
                    // Placeholder durch lesbares Label ersetzen
                    var ph = el.querySelector('.obf-placeholder');
                    if(ph) ph.textContent = label;
                });
            }
            if(document.readyState === 'loading'){
                document.addEventListener('DOMContentLoaded', decodeLinks);
            } else {
                decodeLinks();
            }
        })();
        </script>
        <?php
    }
}

new MediaLab_Email_Obfuscation();
