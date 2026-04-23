<?php
/**
 * E-Mail Obfuskierung / Spam-Schutz
 *
 * Strategie:
 * 1. Automatischer Schutz aller mailto:-Links und nackten
 *    E-Mail-Adressen im Content → ROT13-kodiert + JS-Decoder
 *
 * 2. Shortcode [obfuscate_email email="..." label="..."]
 *
 * 3. Optional: alle mailto:-Links im Content automatisch schützen
 *
 * FIX (Gutenberg Buttons): Im Auto-Protect-Modus werden bestehende
 * Attribute (class, id, rel, target, …) des <a>-Tags erhalten.
 * Nur href wird ersetzt und data-Attribute werden ergänzt.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Email_Obfuscation {

    private $enabled      = false;
    private $auto_protect = false;

    public function __construct() {
        add_action( 'init', array( $this, 'boot' ), 5 );
    }

    public function boot() {
        if ( ! function_exists( 'get_field' ) ) return;

        $obf = get_field( 'obfuscation_settings', 'option' ) ?: array();
        $this->enabled      = ! empty( $obf['enabled'] );
        $this->auto_protect = ! empty( $obf['auto_protect'] );

        if ( ! $this->enabled ) return;

        // Shortcode
        add_shortcode( 'obfuscate_email', array( $this, 'shortcode' ) );

        // Automatischer Schutz aller mailto:-Links im Content
        if ( $this->auto_protect ) {
            add_filter( 'the_content',    array( $this, 'protect_content_emails' ), 20 );
            add_filter( 'widget_text',    array( $this, 'protect_content_emails' ), 20 );
            add_filter( 'acf_the_content', array( $this, 'protect_content_emails' ), 20 );
        }

        // Inline-Decoder-Script einmalig im Footer
        add_action( 'wp_footer', array( $this, 'output_decoder_script' ), 5 );
    }

    // ─────────────────────────────────────────────────────────────
    // SHORTCODE: [obfuscate_email email="info@example.com" label="Schreib uns"]
    // ─────────────────────────────────────────────────────────────

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'email' => '',
            'label' => '',
            'class' => '',
        ), $atts );

        if ( empty( $atts['email'] ) ) return '';

        return $this->build_obfuscated_link(
            $atts['email'],
            $atts['label'] ?: $atts['email'],
            $atts['class']
        );
    }

    // ─────────────────────────────────────────────────────────────
    // AUTOMATISCHER CONTENT-SCHUTZ
    //
    // FIX: Statt das <a>-Tag neu zu bauen, werden nur die
    // notwendigen Attribute am bestehenden Tag modifiziert:
    //   • href="mailto:..."  →  href="#"
    //   • data-obf-email     wird ergänzt
    //   • data-obf-label     wird ergänzt
    //   • onclick="return false;" wird ergänzt
    //
    // Alle anderen Attribute (class, id, target, rel, …) bleiben
    // unverändert erhalten – Gutenberg-Button-Styling bleibt intakt.
    // ─────────────────────────────────────────────────────────────

    public function protect_content_emails( $content ) {

        // ── 1. mailto:-Links obfuskieren ─────────────────────────
        $content = preg_replace_callback(
            '/<a([^>]*)\bhref=["\']mailto:([^"\'>\s]+)["\']([^>]*)>(.*?)<\/a>/is',
            function ( $m ) {
                $attrs_before = $m[1]; // Attribute VOR dem href
                $email        = $m[2]; // E-Mail-Adresse
                $attrs_after  = $m[3]; // Attribute NACH dem href
                $inner_html   = $m[4]; // Inhalt zwischen <a>…</a>

                $encoded_email = str_rot13( $email );

                // Label: ist der sichtbare Text == E-Mail → auch kodieren
                $label_text   = trim( strip_tags( $inner_html ) );
                $label        = ( $label_text === $email ) ? $email : $label_text;
                $encoded_label = str_rot13( $label );

                // Bestehende data-obf-* Attribute entfernen (falls bereits
                // obfuskiert), damit keine Duplikate entstehen
                $attrs_before = preg_replace( '/\s*data-obf-[a-z]+=["\'][^"\']*["\']/i', '', $attrs_before );
                $attrs_after  = preg_replace( '/\s*data-obf-[a-z]+=["\'][^"\']*["\']/i', '', $attrs_after );

                // onclick="return false;" entfernen falls vorhanden (wird neu gesetzt)
                $attrs_before = preg_replace( '/\s*onclick=["\']return false;?["\']/i', '', $attrs_before );
                $attrs_after  = preg_replace( '/\s*onclick=["\']return false;?["\']/i', '', $attrs_after );

                // Neues <a>-Tag: href="#" + data-Attribute + alle Originalattribute
                return sprintf(
                    '<a%s href="#"%s data-obf-email="%s" data-obf-label="%s" onclick="return false;">%s</a>',
                    $attrs_before,
                    $attrs_after,
                    esc_attr( $encoded_email ),
                    esc_attr( $encoded_label ),
                    $inner_html // Original-Inhalt (inkl. etwaiger Spans/Icons) bleibt
                );
            },
            $content
        );

        // ── 2. Nackte E-Mail-Adressen (ohne Link) obfuskieren ────
        // Nur außerhalb von HTML-Tags und bereits obfuskierten Links
        $content = preg_replace_callback(
            '/(?<!["\'=>@\w])([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})(?!["\'\w])/',
            function ( $m ) {
                // Sicherstellen, dass es kein data-obf-Attribut trifft
                return $this->build_obfuscated_link( $m[1], $m[1], '' );
            },
            $content
        );

        return $content;
    }

    // ─────────────────────────────────────────────────────────────
    // OBFUSKIERTER LINK (für Shortcode und nackte Adressen)
    // ─────────────────────────────────────────────────────────────

    private function build_obfuscated_link( $email, $label, $class ) {
        $encoded_email = str_rot13( $email );
        $encoded_label = str_rot13( is_string( $label ) ? strip_tags( $label ) : $email );

        $class_attr = $class ? ' class="' . esc_attr( $class ) . '"' : '';

        return sprintf(
            '<a href="#" data-obf-email="%s" data-obf-label="%s"%s onclick="return false;">%s</a>',
            esc_attr( $encoded_email ),
            esc_attr( $encoded_label ),
            $class_attr,
            '<noscript>' . esc_html( $email ) . '</noscript>'
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
                    el.href    = 'mailto:' + email;
                    el.onclick = null;
                    // Placeholder durch lesbares Label ersetzen
                    // (nur wenn kein echter Inhalt vorhanden, z.B. bei Buttons)
                    var ph = el.querySelector('.obf-placeholder');
                    if ( ph ) {
                        ph.textContent = label;
                    }
                });
            }
            if ( document.readyState === 'loading' ) {
                document.addEventListener( 'DOMContentLoaded', decodeLinks );
            } else {
                decodeLinks();
            }
        })();
        </script>
        <?php
    }
}

new MediaLab_Email_Obfuscation();
