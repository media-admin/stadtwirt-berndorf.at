<?php
/**
 * E-Mail Obfuscation / Spam Protection
 * ROT13-basierter Spam-Schutz für E-Mail-Adressen im Content.
 * Schützt automatisch alle mailto:-Links sowie [obfuscate_email]-Shortcode.
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 */
if (!defined('ABSPATH')) { exit; }

// Auto-Schutz aller mailto:-Links im Content (the_content Filter)
add_filter('the_content', 'medialab_obfuscate_emails_in_content');
add_filter('widget_text', 'medialab_obfuscate_emails_in_content');

function medialab_obfuscate_emails_in_content(string $content): string {
    return preg_replace_callback(
        '/(<a[^>]+href=["\'])mailto:([^"\']+)(["\'][^>]*>)([^<]*)(<\/a>)/i',
        function(array $m): string {
            $encoded_email = medialab_encode_email($m[2]);
            $encoded_label = strpos($m[4], '@') !== false ? medialab_encode_email($m[4]) : $m[4];
            return sprintf(
                '<a href="#" data-ea="%s" onclick="this.href=\'mailto:\'+atob(this.dataset.ea);return false;" rel="nofollow">%s</a>',
                esc_attr(base64_encode($m[2])),
                $encoded_label
            );
        },
        $content
    );
}

function medialab_encode_email(string $email): string {
    $output = '';
    for ($i = 0; $i < strlen($email); $i++) {
        $output .= '&#' . ord($email[$i]) . ';';
    }
    return $output;
}

// Shortcode: [obfuscate_email address="hallo@example.com" label="Schreib uns"]
add_shortcode('obfuscate_email', function(array $atts): string {
    $atts = shortcode_atts(['address' => '', 'label' => ''], $atts);
    if (empty($atts['address'])) { return ''; }
    $email = sanitize_email($atts['address']);
    $label = !empty($atts['label']) ? esc_html($atts['label']) : medialab_encode_email($email);
    return sprintf(
        '<a href="#" data-ea="%s" onclick="this.href=\'mailto:\'+atob(this.dataset.ea);return false;" rel="nofollow">%s</a>',
        esc_attr(base64_encode($email)),
        $label
    );
});
