<?php
/**
 * MLT_SEO
 *
 * Gibt SEO-relevante Meta-Tags im <head> aus:
 *  - Google Search Console Verification
 *  - Canonical URL
 *  - Open Graph (og:title, og:description, og:image, og:url, og:type)
 *  - Twitter Cards
 *
 * Kein Tracking, kein Script → kein Datenschutz-Problem.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_SEO {

    public function __construct() {
        // Priorität 1 → vor Theme-Output, nach wp_head-Init
        add_action( 'wp_head', [ $this, 'output_meta_tags' ], 1 );

        // Canonical: WordPress-eigene entfernen, eigene ausgeben
        remove_action( 'wp_head', 'rel_canonical' );
        add_action( 'wp_head', [ $this, 'output_canonical' ], 1 );
    }

    // ── Haupt-Ausgabe ─────────────────────────────────────────────────────────

    public function output_meta_tags() {
        $gsc = get_option( 'mlt_gsc_verification', '' );
        $data = $this->collect_meta_data();

        echo "\n<!-- Media Lab SEO Toolkit: SEO -->\n";

        // Google Search Console
        if ( $gsc ) {
            $content = sanitize_text_field( $gsc );
            // Nutzer kann entweder den vollen Tag oder nur den Content-Wert eintragen
            if ( strpos( $content, 'google-site-verification=' ) === 0 ) {
                $content = str_replace( 'google-site-verification=', '', $content );
            }
            echo '<meta name="google-site-verification" content="' . esc_attr( $content ) . '">' . "\n";
        }

        // Open Graph
        echo '<meta property="og:type"        content="' . esc_attr( $data['og_type'] ) . '">' . "\n";
        echo '<meta property="og:url"         content="' . esc_url( $data['url'] ) . '">' . "\n";
        echo '<meta property="og:title"       content="' . esc_attr( $data['title'] ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $data['description'] ) . '">' . "\n";
        echo '<meta property="og:locale"      content="' . esc_attr( get_locale() ) . '">' . "\n";
        echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";

        if ( $data['image'] ) {
            echo '<meta property="og:image"       content="' . esc_url( $data['image'] ) . '">' . "\n";
            echo '<meta property="og:image:width"  content="' . esc_attr( $data['image_w'] ) . '">' . "\n";
            echo '<meta property="og:image:height" content="' . esc_attr( $data['image_h'] ) . '">' . "\n";
            echo '<meta property="og:image:alt"    content="' . esc_attr( $data['image_alt'] ) . '">' . "\n";
        }

        // Twitter Cards
        echo '<meta name="twitter:card"        content="' . esc_attr( $data['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
        echo '<meta name="twitter:title"       content="' . esc_attr( $data['title'] ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $data['description'] ) . '">' . "\n";

        if ( $data['image'] ) {
            echo '<meta name="twitter:image" content="' . esc_url( $data['image'] ) . '">' . "\n";
        }

        echo "<!-- /Media Lab SEO Toolkit: SEO -->\n\n";
    }

    // ── Canonical URL ─────────────────────────────────────────────────────────

    public function output_canonical() {
        $url = $this->get_canonical_url();
        if ( $url ) {
            echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
        }
    }

    // ── Meta-Daten sammeln ────────────────────────────────────────────────────

    private function collect_meta_data() {
        $data = [
            'title'       => '',
            'description' => '',
            'url'         => '',
            'og_type'     => 'website',
            'image'       => '',
            'image_w'     => 1200,
            'image_h'     => 630,
            'image_alt'   => '',
        ];

        // URL
        $data['url'] = $this->get_canonical_url();

        // Titel
        $data['title'] = $this->get_title();

        // Description
        $data['description'] = $this->get_description();

        // OG Type
        if ( is_single() ) {
            $data['og_type'] = 'article';
        }

        // Bild
        [ $data['image'], $data['image_w'], $data['image_h'], $data['image_alt'] ] = $this->get_image();

        return $data;
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private function get_canonical_url() {
        if ( is_singular() ) {
            return get_permalink();
        }
        if ( is_home() || is_front_page() ) {
            return home_url( '/' );
        }
        if ( is_category() || is_tag() || is_tax() ) {
            return get_term_link( get_queried_object() );
        }
        if ( is_author() ) {
            return get_author_posts_url( get_queried_object_id() );
        }
        if ( is_archive() ) {
            return get_post_type_archive_link( get_post_type() );
        }
        return '';
    }

    private function get_title() {
        // Yoast / Rank Math haben eigene Ausgabe → wp_get_document_title() als Fallback
        if ( is_singular() ) {
            return get_the_title() . ' – ' . get_bloginfo( 'name' );
        }
        return wp_get_document_title();
    }

    private function get_description() {
        if ( is_singular() ) {
            $post = get_queried_object();

            // Manuelles Excerpt bevorzugen
            if ( $post && has_excerpt( $post->ID ) ) {
                return wp_trim_words( get_the_excerpt( $post->ID ), 30, '…' );
            }

            // Fallback: erstes Absatz des Inhalts
            if ( $post ) {
                return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' );
            }
        }

        if ( is_home() || is_front_page() ) {
            return get_bloginfo( 'description' );
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $desc = term_description();
            if ( $desc ) return wp_trim_words( wp_strip_all_tags( $desc ), 30, '…' );
        }

        return get_bloginfo( 'description' );
    }

    private function get_image() {
        $image   = '';
        $width   = 1200;
        $height  = 630;
        $alt     = '';

        // 1. Beitragsbild der aktuellen Seite/Post
        if ( is_singular() && has_post_thumbnail() ) {
            $id  = get_post_thumbnail_id();
            $src = wp_get_attachment_image_src( $id, 'full' );
            if ( $src ) {
                $image  = $src[0];
                $width  = $src[1];
                $height = $src[2];
                $alt    = get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title();
            }
        }

        // 2. Fallback: OG-Bild aus den Plugin-Einstellungen
        if ( ! $image ) {
            $fallback_id = get_option( 'mlt_og_default_image', 0 );
            if ( $fallback_id ) {
                $src = wp_get_attachment_image_src( $fallback_id, 'full' );
                if ( $src ) {
                    $image  = $src[0];
                    $width  = $src[1];
                    $height = $src[2];
                    $alt    = get_post_meta( $fallback_id, '_wp_attachment_image_alt', true ) ?: get_bloginfo( 'name' );
                }
            }
        }

        return [ $image, $width, $height, $alt ];
    }
}
