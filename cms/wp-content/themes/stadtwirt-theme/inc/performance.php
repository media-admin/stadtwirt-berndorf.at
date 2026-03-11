<?php
/**
 * Performance Optimierungen – Core Web Vitals
 *
 * ┌─────────────────────────────────────────────────────────────────────────┐
 * │  ZIELWERTE (Google „Good"-Schwellenwerte)                               │
 * │                                                                         │
 * │  LCP  Largest Contentful Paint   ≤ 2 500 ms   Hero Preload, fetchprio  │
 * │  CLS  Cumulative Layout Shift    ≤ 0.10        Bild-Dimensionen, Fonts  │
 * │  INP  Interaction to Next Paint  ≤ 200 ms      defer, Heartbeat         │
 * │  FCP  First Contentful Paint     ≤ 1 800 ms    Critical CSS inline      │
 * │  TBT  Total Blocking Time        ≤ 200 ms      (INP-Proxy in Lighthouse)│
 * │                                                                         │
 * │  Lighthouse Score: Performance ≥ 90 | A11y/BP/SEO ≥ 95                 │
 * │  Messen: npm run lighthouse  →  lighthouserc.js                         │
 * └─────────────────────────────────────────────────────────────────────────┘
 *
 * Module:
 *   LCP  – customtheme_preload_lcp_image()          Hero-Bild Preload
 *   LCP  – customtheme_inline_critical_css()         Critical CSS inline
 *   LCP  – customtheme_nonblocking_css()             Haupt-CSS non-blocking
 *   LCP  – customtheme_fetchpriority_first_image()   Erstes Content-Bild
 *   CLS  – customtheme_enforce_image_dimensions()    width/height erzwingen
 *   CLS  – customtheme_add_image_dimensions_to_content()
 *   CLS  – customtheme_fonts_display_swap()          display=swap
 *   CLS  – customtheme_preload_fonts()               Font Preload (Filter)
 *   INP  – customtheme_defer_scripts()               Plugin-Scripts defer
 *   INP  – heartbeat_settings                        60s statt 15s
 *   IMG  – customtheme_webp_picture_element()        WebP <picture> (opt-in)
 *   IMG  – wp_lazy_loading_enabled                   Lazy Loading
 *
 * Konfigurierbare Filter:
 *   customtheme_lcp_image_url          → LCP-Bild manuell steuern
 *   customtheme_lcp_image_srcset       → srcset des LCP-Bildes
 *   customtheme_preload_fonts          → self-hosted Font-URLs
 *   customtheme_enable_picture_webp    → WebP <picture> aktivieren (bool)
 *   customtheme_defer_scripts          → weitere Handles defer-listen
 *   customtheme_exclude_defer_scripts  → Handles von defer ausschließen
 *   customtheme_remove_dns_prefetch    → DNS-Prefetch-URLs entfernen
 *
 * @package CustomTheme
 * @since   1.12.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// LCP – Hero-Image Preload + fetchpriority="high"
// =============================================================================

/**
 * Gibt einen <link rel="preload"> für das LCP-Hero-Bild aus.
 *
 * Das Hero-Bild wird via ACF-Feld oder Featured Image ermittelt.
 * Konfigurierbar per Filter:
 *
 *   add_filter( 'customtheme_lcp_image_url', fn() => 'https://...' );
 *   add_filter( 'customtheme_lcp_image_srcset', fn() => '...' );
 *
 * Für Startseite und alle Seiten mit gesetztem Bild aktiv.
 */
add_action( 'wp_head', 'customtheme_preload_lcp_image', 1 );

function customtheme_preload_lcp_image(): void {
    // Nur auf Seiten mit potenziellem Hero-Bild
    if ( ! ( is_front_page() || is_singular() || is_archive() ) ) return;

    $image_url    = '';
    $image_srcset = '';
    $image_sizes  = '';

    // 1. Filter-Hook (erlaubt externe Steuerung, z.B. aus Page-Builder-Templates)
    $image_url = (string) apply_filters( 'customtheme_lcp_image_url', '' );

    // 2. ACF-Hero-Bild (falls ACF aktiv und Feld gesetzt)
    if ( empty( $image_url ) && function_exists( 'get_field' ) ) {
        $hero_image = get_field( 'hero_image' ) ?: get_field( 'hero_background_image' );
        if ( $hero_image ) {
            $image_url = is_array( $hero_image ) ? ( $hero_image['url'] ?? '' ) : $hero_image;
        }
    }

    // 3. Featured Image der aktuellen Seite
    if ( empty( $image_url ) && is_singular() && has_post_thumbnail() ) {
        $thumb = wp_get_attachment_image_src( get_post_thumbnail_id(), 'custom-large' );
        if ( $thumb ) $image_url = $thumb[0];
    }

    if ( empty( $image_url ) ) return;

    // srcset für Responsive Preload
    $image_id = attachment_url_to_postid( $image_url );
    if ( $image_id ) {
        $srcset = wp_get_attachment_image_srcset( $image_id, 'custom-large' );
        $sizes  = wp_get_attachment_image_sizes( $image_id, 'custom-large' );
        if ( $srcset ) {
            $image_srcset = $srcset;
            $image_sizes  = $sizes ?: '100vw';
        }
    }

    $image_url    = apply_filters( 'customtheme_lcp_image_url',    $image_url );
    $image_srcset = apply_filters( 'customtheme_lcp_image_srcset', $image_srcset );

    // MIME-Type ermitteln (für imagesrcset-Hint)
    $ext      = strtolower( pathinfo( $image_url, PATHINFO_EXTENSION ) );
    $mime_map = [ 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif' ];
    $mime     = $mime_map[ $ext ] ?? 'image/jpeg';

    echo '<link rel="preload" as="image"' . "\n";
    echo '      href="'  . esc_url( $image_url ) . '"' . "\n";
    echo '      type="'  . esc_attr( $mime ) . '"' . "\n";
    if ( $image_srcset ) {
        echo '      imagesrcset="' . esc_attr( $image_srcset ) . '"' . "\n";
        echo '      imagesizes="'  . esc_attr( $image_sizes   ) . '"' . "\n";
    }
    echo '      fetchpriority="high">' . "\n";
}

/**
 * Fügt fetchpriority="high" + loading="eager" dem ersten Bild im Content hinzu
 * (für Seiten ohne dediziertes Hero-Bild, z.B. Blog-Posts).
 */
add_filter( 'the_content', 'customtheme_fetchpriority_first_image', 99 );

function customtheme_fetchpriority_first_image( string $content ): string {
    // Nur einmal pro Request ausführen
    static $done = false;
    if ( $done || ! is_singular() ) return $content;

    // Nur wenn kein Hero-Preload aktiv ist
    if ( has_post_thumbnail() ) { $done = true; return $content; }

    // Erstes <img> im Content: fetchpriority + eager
    $content = preg_replace_callback(
        '/<img([^>]+)>/i',
        function( $matches ) use ( &$done ) {
            if ( $done ) return $matches[0];
            $done = true;
            $attrs = $matches[1];
            // Kein doppeltes fetchpriority
            if ( str_contains( $attrs, 'fetchpriority' ) ) return $matches[0];
            $attrs = preg_replace( '/loading=["\'][^"\']*["\']/', '', $attrs );
            return '<img' . $attrs . ' fetchpriority="high" loading="eager">';
        },
        $content,
        1
    );

    return $content;
}

// =============================================================================
// LCP – Critical CSS inline
// =============================================================================

/**
 * Bindet kritisches CSS (above-the-fold) direkt im <head> ein.
 *
 * Datei: assets/dist/css/critical.css
 * Wird vom Build-System generiert (oder manuell gepflegt).
 * Wenn die Datei fehlt: kein Fehler, normales CSS bleibt aktiv.
 *
 * Gleichzeitig: Haupt-CSS wird auf non-blocking geladen (media="print" Trick).
 */
add_action( 'wp_head', 'customtheme_inline_critical_css', 2 );

function customtheme_inline_critical_css(): void {
    $critical_file = get_template_directory() . '/assets/dist/css/critical.css';

    if ( ! file_exists( $critical_file ) ) return;

    $critical_css = file_get_contents( $critical_file );
    if ( empty( $critical_css ) ) return;

    echo '<style id="critical-css">' . "\n";
    // Minimale Inline-Sanitierung (keine PHP-Ausführung möglich in CSS)
    echo wp_strip_all_tags( $critical_css );
    echo "\n" . '</style>' . "\n";
}

/**
 * Wechselt das Haupt-CSS auf non-blocking loading wenn Critical CSS vorhanden.
 * Setzt media="print" + onload-Trick für sofortiges non-render-blocking.
 */
add_filter( 'style_loader_tag', 'customtheme_nonblocking_css', 10, 4 );

function customtheme_nonblocking_css( string $tag, string $handle, string $href, string $media ): string {
    // Nur Haupt-CSS, nur wenn Critical CSS existiert
    if ( $handle !== 'custom-theme-style' ) return $tag;
    if ( ! file_exists( get_template_directory() . '/assets/dist/css/critical.css' ) ) return $tag;

    // Non-blocking pattern: media="print" → onload → media="all"
    return '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css"'
        . ' href="' . esc_url( $href ) . '"'
        . ' media="print" onload="this.media=\'all\'">'
        . '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>' . "\n";
}

// =============================================================================
// CLS – Font-Display swap + Font Preload
// =============================================================================

/**
 * Fügt font-display:swap zu Google Fonts URLs hinzu (falls genutzt).
 */
add_filter( 'style_loader_src', 'customtheme_fonts_display_swap', 10, 2 );

function customtheme_fonts_display_swap( string $src, string $handle ): string {
    if ( str_contains( $src, 'fonts.googleapis.com' ) && ! str_contains( $src, 'display=swap' ) ) {
        $src = add_query_arg( 'display', 'swap', $src );
    }
    return $src;
}

/**
 * Gibt <link rel="preload"> für selbst-gehostete Schriften aus.
 *
 * Konfiguration via Filter:
 *   add_filter( 'customtheme_preload_fonts', function( $fonts ) {
 *       $fonts[] = [
 *           'href' => get_template_directory_uri() . '/assets/fonts/inter-var.woff2',
 *           'type' => 'font/woff2',
 *       ];
 *       return $fonts;
 *   });
 */
add_action( 'wp_head', 'customtheme_preload_fonts', 1 );

function customtheme_preload_fonts(): void {
    $fonts = apply_filters( 'customtheme_preload_fonts', [] );

    foreach ( $fonts as $font ) {
        if ( empty( $font['href'] ) ) continue;
        $type = $font['type'] ?? 'font/woff2';
        echo '<link rel="preload" href="' . esc_url( $font['href'] ) . '"'
            . ' as="font" type="' . esc_attr( $type ) . '" crossorigin>' . "\n";
    }
}

// =============================================================================
// CLS – Bild-Dimensionen erzwingen
// =============================================================================

/**
 * Erzwingt width + height Attribute auf allen wp_get_attachment_image()-Bildern.
 * Verhindert Layout Shift durch unbekannte Bild-Dimensionen.
 */
add_filter( 'wp_get_attachment_image_attributes', 'customtheme_enforce_image_dimensions', 10, 3 );

function customtheme_enforce_image_dimensions( array $attr, WP_Post $attachment, string|array $size ): array {
    // Width + Height bereits gesetzt?
    if ( ! empty( $attr['width'] ) && ! empty( $attr['height'] ) ) return $attr;

    $meta = wp_get_attachment_metadata( $attachment->ID );
    if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) return $attr;

    // Ziel-Größe ermitteln
    if ( is_array( $size ) ) {
        $w = $size[0];
        $h = $size[1];
    } else {
        $sizes = wp_get_registered_image_subsizes();
        if ( isset( $sizes[ $size ] ) ) {
            $w = $sizes[ $size ]['width'];
            $h = $sizes[ $size ]['height'];
        } else {
            $w = $meta['width'];
            $h = $meta['height'];
        }
    }

    if ( $w && $h ) {
        $attr['width']  = (string) $w;
        $attr['height'] = (string) $h;
    }

    return $attr;
}

/**
 * Fügt width + height zu <img>-Tags im_content hinzu die diese Attribute fehlen.
 */
add_filter( 'the_content', 'customtheme_add_image_dimensions_to_content', 10 );

function customtheme_add_image_dimensions_to_content( string $content ): string {
    if ( ! str_contains( $content, '<img' ) ) return $content;

    return preg_replace_callback(
        '/<img([^>]+)>/i',
        function ( $matches ) {
            $attrs = $matches[1];

            // Bereits width + height vorhanden?
            if ( preg_match( '/width=["\']/', $attrs ) && preg_match( '/height=["\']/', $attrs ) ) {
                return $matches[0];
            }

            // Src ermitteln
            preg_match( '/src=["\']([^"\']+)["\']/', $attrs, $src_match );
            if ( empty( $src_match[1] ) ) return $matches[0];

            $image_id = attachment_url_to_postid( $src_match[1] );
            if ( ! $image_id ) return $matches[0];

            $meta = wp_get_attachment_metadata( $image_id );
            if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) return $matches[0];

            return '<img' . $attrs
                . ' width="'  . (int) $meta['width']  . '"'
                . ' height="' . (int) $meta['height'] . '">';
        },
        $content
    );
}

// =============================================================================
// INP – Plugin-Scripts defer/async
// =============================================================================

/**
 * Setzt defer auf nicht-kritische Drittanbieter- und Plugin-Scripts.
 *
 * Liste der Scripts die defer erhalten:
 *   - contact-form-7 / wpcf7 Scripts
 *   - WooCommerce Frontend-Scripts (nicht cart/checkout)
 *   - Eigene Erweiterungen via Filter
 *
 * Scripts die NICHT angefasst werden:
 *   - jQuery (manche Plugins erwarten synchrones jQuery)
 *   - inline Scripts
 *   - Scripts die bereits type="module" haben (auto-defer)
 *
 * Konfiguration via Filter:
 *   add_filter( 'customtheme_defer_scripts', fn($h) => [...$h, 'my-script'] );
 *   add_filter( 'customtheme_exclude_defer_scripts', fn($h) => [...$h, 'critical-script'] );
 */
add_filter( 'script_loader_tag', 'customtheme_defer_scripts', 10, 3 );

function customtheme_defer_scripts( string $tag, string $handle, string $src ): string {
    // Admin nie anfassen
    if ( is_admin() ) return $tag;

    // Scripts mit defer oder type="module" bereits korrekt
    if ( str_contains( $tag, 'defer' ) || str_contains( $tag, 'type="module"' ) ) return $tag;

    // Inline-Scripts nicht anfassen
    if ( empty( $src ) ) return $tag;

    // Defer-Liste (Plugin-Scripts die sicher deferierbar sind)
    $defer_handles = apply_filters( 'customtheme_defer_scripts', [
        // Contact Form 7
        'contact-form-7',
        'wpcf7-swv',
        // WooCommerce (nur non-critical)
        'woocommerce',
        'wc-cart-fragments',
        'wc-add-to-cart',
        'wc-add-to-cart-variation',
        'zoom',
        'flexslider',
        'photoswipe',
        'photoswipe-ui-default',
        // Media Lab Plugins
        'media-lab-notifications',
        'media-lab-cookie-notice',
    ] );

    // Ausschluss-Liste
    $exclude_handles = apply_filters( 'customtheme_exclude_defer_scripts', [
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'wp-util',
        'custom-theme-script', // Unser main.js (type="module" → bereits defer)
    ] );

    if ( in_array( $handle, $exclude_handles, true ) ) return $tag;
    if ( ! in_array( $handle, $defer_handles, true ) ) return $tag;

    // defer hinzufügen
    return str_replace( '<script ', '<script defer ', $tag );
}

// =============================================================================
// Responsive Images – srcset + WebP
// =============================================================================

/**
 * Aktiviert srcset + sizes für alle WordPress-Bilder (Standard seit WP 4.4).
 * Zusätzlich: WebP-Unterstützung für EWWW / Smush / native WordPress (6.1+).
 */

// Sicherstellen dass srcset aktiv ist
add_filter( 'wp_calculate_image_srcset_meta', 'customtheme_ensure_srcset_meta', 10, 4 );

function customtheme_ensure_srcset_meta( ?array $image_meta, array $size_array, string $image_src, int $attachment_id ): ?array {
    // Falls Metadaten fehlen: neu laden
    if ( empty( $image_meta ) ) {
        $image_meta = wp_get_attachment_metadata( $attachment_id );
    }
    return $image_meta;
}

/**
 * Fügt <picture> mit WebP-Source hinzu wenn ein WebP-Pendant existiert.
 * Funktioniert mit EWWW Image Optimizer und nativem WP WebP (6.1+).
 *
 * Nur aktiv wenn filter customtheme_enable_picture_webp = true (Default: false)
 * da dies Template-seitiges Markup voraussetzt.
 */
add_filter( 'wp_get_attachment_image', 'customtheme_webp_picture_element', 10, 5 );

function customtheme_webp_picture_element( string $html, int $attachment_id, string|array $size, bool $icon, array $attr ): string {
    if ( ! apply_filters( 'customtheme_enable_picture_webp', false ) ) return $html;

    $image_src = wp_get_attachment_image_url( $attachment_id, $size );
    if ( ! $image_src ) return $html;

    // WebP-Pfad ableiten
    $upload_dir = wp_upload_dir();
    $base_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_src );
    $webp_path  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $base_path );
    $webp_url   = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $image_src );

    if ( ! file_exists( $webp_path ) ) return $html;

    $webp_srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
    $webp_srcset_url = $webp_srcset
        ? preg_replace( '/\.(jpe?g|png)/i', '.webp', $webp_srcset )
        : $webp_url;

    $sizes = wp_get_attachment_image_sizes( $attachment_id, $size );

    return '<picture>'
        . '<source type="image/webp"'
        . ( $webp_srcset_url ? ' srcset="' . esc_attr( $webp_srcset_url ) . '"' : ' srcset="' . esc_url( $webp_url ) . '"' )
        . ( $sizes ? ' sizes="' . esc_attr( $sizes ) . '"' : '' )
        . '>'
        . $html
        . '</picture>';
}

/**
 * Setzt loading="lazy" auf alle Bilder außer dem LCP-Bild.
 * LCP-Bild bekommt loading="eager" (via customtheme_fetchpriority_first_image).
 */
add_filter( 'wp_lazy_loading_enabled', '__return_true' );

// Lazy Loading auch für Thumbnails in Loops
add_filter( 'wp_get_attachment_image_attributes', 'customtheme_lazy_load_thumbnails', 20, 3 );

function customtheme_lazy_load_thumbnails( array $attr, WP_Post $attachment, string|array $size ): array {
    // Im Admin nicht lazy
    if ( is_admin() ) return $attr;

    // Wenn fetchpriority=high gesetzt → kein lazy
    if ( isset( $attr['fetchpriority'] ) && $attr['fetchpriority'] === 'high' ) {
        $attr['loading'] = 'eager';
        return $attr;
    }

    if ( ! isset( $attr['loading'] ) ) {
        $attr['loading'] = 'lazy';
    }

    return $attr;
}

/**
 * Größere maximale Bildbreite für srcset (Standard: 1600px → 2560px für Retina).
 */
add_filter( 'max_srcset_image_width', fn() => 2560 );

// =============================================================================
// Ressourcen-Hints
// =============================================================================

/**
 * Entfernt unnötige Resource Hints die WP automatisch hinzufügt.
 * Verhindert unnötige DNS-Lookups die INP/FID negativ beeinflussen.
 */
add_filter( 'wp_resource_hints', 'customtheme_clean_resource_hints', 10, 2 );

function customtheme_clean_resource_hints( array $hints, string $relation_type ): array {
    // s.w.org (WordPress-Emoji) bereits durch disable_emojis entfernt
    // Hier: weitere unerwünschte prefetches entfernen
    if ( $relation_type === 'dns-prefetch' ) {
        $remove = apply_filters( 'customtheme_remove_dns_prefetch', [
            '//s.w.org',
        ] );
        $hints = array_filter( $hints, fn( $hint ) =>
            ! in_array( is_array( $hint ) ? ( $hint['href'] ?? '' ) : $hint, $remove, true )
        );
    }
    return array_values( $hints );
}

// =============================================================================
// WordPress-seitige Performance-Optimierungen
// =============================================================================

// Heartbeat-API im Frontend reduzieren (spart JS-Polling-Overhead → besser INP)
add_filter( 'heartbeat_settings', function( array $settings ): array {
    if ( ! is_admin() ) {
        $settings['interval'] = 60; // Standard: 15s → 60s
    }
    return $settings;
} );

// REST-API-Links im Frontend-Head entfernen (nicht benötigt, spart ~200 Bytes)
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
