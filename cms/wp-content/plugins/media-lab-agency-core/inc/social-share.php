<?php
/**
 * Social Share Buttons
 *
 * DSGVO-konform: Kein externes Script wird beim Seitenaufruf geladen.
 * Daten werden erst beim aktiven Klick des Besuchers übertragen.
 *
 * ─── Verwendungsmöglichkeiten ────────────────────────────────────────────────
 *
 * 1. Shortcode:
 *    [medialab_share]
 *    [medialab_share services="whatsapp,facebook" style="outline" icon_only="true"]
 *
 * 2. PHP-Funktion (Theme-Templates):
 *    <?php medialab_share(); ?>
 *    <?php medialab_share( array( 'style' => 'ghost', 'icon_only' => true ) ); ?>
 *
 * 3. Gutenberg-Block „Share-Buttons" (Kategorie „Design")
 *
 * ─── Style-Varianten ─────────────────────────────────────────────────────────
 *    colored   Farbig / Markenfarben (Standard)
 *    mono      Einfarbig dunkel
 *    outline   Outline in Markenfarben
 *    ghost     Minimalistisch / transparent
 *
 * ─── Verfügbare Services ─────────────────────────────────────────────────────
 *    whatsapp, facebook, twitter, linkedin, xing, pinterest,
 *    telegram, reddit, instagram, tiktok, email, copy
 *
 * @package MediaLab_Core
 * @since   1.9.0
 * @updated 1.9.2  style-Varianten + icon_only + Editor-CSS-Fix
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// INITIALISIERUNG
// =============================================================================

add_action( 'init',                'medialab_social_share_init'        );
add_action( 'wp_enqueue_scripts',  'medialab_social_share_assets'      );
add_action( 'enqueue_block_assets','medialab_social_share_assets'      ); // ← Editor + Frontend
add_action( 'acf/init',            'medialab_social_share_acf'         );
add_filter( 'the_content',         'medialab_social_share_auto_insert', 20 );

function medialab_social_share_init(): void {
    add_shortcode( 'medialab_share', 'medialab_social_share_shortcode' );
}

function medialab_social_share_assets(): void {
    // Doppeltes Registrieren vermeiden (wp_enqueue_scripts + enqueue_block_assets)
    if ( wp_style_is( 'medialab-social-share', 'enqueued' ) ) return;

    wp_enqueue_style(
        'medialab-social-share',
        MEDIALAB_CORE_URL . 'assets/css/social-share.css',
        array(),
        MEDIALAB_CORE_VERSION
    );
}

// =============================================================================
// GLOBALE DEFAULTS AUS ACF
// =============================================================================

/**
 * Gibt die globalen Share-Button-Defaults zurück.
 * Services werden aus dem Repeater in der konfigurierten Reihenfolge gelesen.
 *
 * @return array{services: string, layout: string, show_label: bool, label: string, style: string, icon_only: bool}
 */
function medialab_social_share_get_defaults(): array {
    static $cache = null;
    if ( $cache !== null ) return $cache;

    $acf = function_exists( 'get_field' );

    // Services aus Repeater
    $services = 'whatsapp,facebook,twitter,linkedin';
    if ( $acf ) {
        $rows = get_field( 'ss_default_services', 'option' );
        if ( is_array( $rows ) && ! empty( $rows ) ) {
            $keys = array_filter( array_map(
                fn( $row ) => sanitize_key( $row['ss_service'] ?? '' ),
                $rows
            ) );
            if ( ! empty( $keys ) ) $services = implode( ',', $keys );
        }
    }

    $layout     = $acf ? (string) ( get_field( 'ss_default_layout',     'option' ) ?: 'horizontal' ) : 'horizontal';
    $show_label = $acf ? (bool)   ( get_field( 'ss_default_show_label', 'option' ) ?? true )          : true;
    $label      = $acf ? (string) ( get_field( 'ss_default_label',      'option' ) ?: __( 'Teilen', 'media-lab-core' ) ) : __( 'Teilen', 'media-lab-core' );
    $style      = $acf ? (string) ( get_field( 'ss_default_style',      'option' ) ?: 'colored' )    : 'colored';
    $icon_only  = $acf ? (bool)   ( get_field( 'ss_default_icon_only',  'option' ) ?? false )         : false;

    $cache = compact( 'services', 'layout', 'show_label', 'label', 'style', 'icon_only' );
    return $cache;
}

// =============================================================================
// AUTO-INSERT (nach Post-Content)
// =============================================================================

function medialab_social_share_auto_insert( string $content ): string {
    if ( ! function_exists( 'get_field' ) )         return $content;
    if ( ! in_the_loop() || ! is_main_query() )     return $content;
    if ( ! get_field( 'ss_auto_insert', 'option' ) ) return $content;

    $post_types = get_field( 'ss_auto_insert_post_types', 'option' );
    if ( ! is_array( $post_types ) || ! in_array( get_post_type(), $post_types, true ) ) {
        return $content;
    }

    $content .= medialab_social_share_render( medialab_social_share_get_defaults() );
    return $content;
}

// =============================================================================
// SHORTCODE
// =============================================================================

function medialab_social_share_shortcode( array $atts ): string {
    $defaults = medialab_social_share_get_defaults();

    $atts = shortcode_atts(
        array(
            'services'   => $defaults['services'],
            'layout'     => $defaults['layout'],
            'show_label' => $defaults['show_label'] ? 'true' : 'false',
            'label'      => $defaults['label'],
            'style'      => $defaults['style'],
            'icon_only'  => $defaults['icon_only'] ? 'true' : 'false',
        ),
        $atts,
        'medialab_share'
    );

    $atts['show_label'] = $atts['show_label'] !== 'false';
    $atts['icon_only']  = $atts['icon_only']  === 'true';
    return medialab_social_share_render( $atts );
}

// =============================================================================
// PHP-TEMPLATE-FUNKTION
// =============================================================================

/**
 * Gibt Share-Buttons direkt aus (für Theme-Templates).
 *
 * @param array $args  services, layout, show_label, label, style, icon_only
 *
 * @example
 *   medialab_share();
 *   medialab_share( array( 'style' => 'outline', 'icon_only' => true ) );
 */
function medialab_share( array $args = array() ): void {
    $merged = array_merge( medialab_social_share_get_defaults(), $args );
    if ( isset( $merged['show_label'] ) && is_string( $merged['show_label'] ) ) {
        $merged['show_label'] = $merged['show_label'] !== 'false';
    }
    if ( isset( $merged['icon_only'] ) && is_string( $merged['icon_only'] ) ) {
        $merged['icon_only'] = $merged['icon_only'] === 'true';
    }
    echo medialab_social_share_render( $merged ); // phpcs:ignore WordPress.Security.EscapeOutput
}

// =============================================================================
// RENDER-ENGINE
// =============================================================================

/** Erlaubte Style-Werte */
const MEDIALAB_SHARE_STYLES = [ 'colored', 'mono', 'outline', 'ghost' ];

/**
 * Erzeugt das Share-Button-HTML.
 *
 * @param array $args  services, layout, show_label, label, style, icon_only
 */
function medialab_social_share_render( array $args ): string {
    $all_services = medialab_social_share_services();

    $requested  = array_filter( array_map( 'trim', explode( ',', $args['services'] ?? '' ) ) );
    $layout     = in_array( $args['layout'] ?? '', [ 'horizontal', 'vertical' ], true )
                  ? $args['layout'] : 'horizontal';
    $show_label = (bool) ( $args['show_label'] ?? true );
    $label      = esc_html( $args['label'] ?? __( 'Teilen', 'media-lab-core' ) );
    $style      = in_array( $args['style'] ?? '', MEDIALAB_SHARE_STYLES, true )
                  ? $args['style'] : 'colored';
    $icon_only  = (bool) ( $args['icon_only'] ?? false );

    $page_url   = rawurlencode( (string) get_permalink() );
    $page_title = rawurlencode( (string) get_the_title() );

    // Wrapper-Klassen
    $wrapper_classes = implode( ' ', array_filter( [
        'medialab-share',
        'medialab-share--' . esc_attr( $layout ),
        'medialab-share--style-' . esc_attr( $style ),
        $icon_only ? 'medialab-share--icon-only' : '',
    ] ) );

    $html  = '<div class="' . $wrapper_classes . '"'
           . ' role="complementary"'
           . ' aria-label="' . esc_attr__( 'Artikel teilen', 'media-lab-core' ) . '">';

    if ( ! $icon_only && $show_label && $label ) {
        $html .= '<span class="medialab-share__label">' . $label . '</span>';
    }

    $html .= '<ul class="medialab-share__list">';

    $has_copy = false;

    foreach ( $requested as $key ) {
        if ( ! isset( $all_services[ $key ] ) ) continue;

        $service = $all_services[ $key ];

        // ── Copy-Link ─────────────────────────────────────────────────────────
        if ( $key === 'copy' ) {
            $has_copy = true;
            $html .= '<li class="medialab-share__item">';
            $html .= '<button'
                   . ' class="medialab-share__btn medialab-share__btn--copy"'
                   . ' type="button"'
                   . ' data-copy-url="' . esc_attr( rawurldecode( $page_url ) ) . '"'
                   . ' title="' . esc_attr__( 'Link kopieren', 'media-lab-core' ) . '"'
                   . ' style="--share-color: ' . esc_attr( $service['color'] ) . ';"'
                   . '>';
            $html .= $service['icon'];
            $html .= '<span class="medialab-share__btn-label">' . esc_html( $service['label'] ) . '</span>';
            $html .= '</button></li>';
            continue;
        }

        // ── Link-Button ───────────────────────────────────────────────────────
        $share_url = str_replace(
            [ '{url}', '{title}' ],
            [ $page_url, $page_title ],
            $service['url']
        );

        $target = ( $key === 'email' ) ? '_self' : '_blank';
        $rel    = ( $key === 'email' ) ? '' : ' rel="noopener noreferrer"';

        $html .= '<li class="medialab-share__item">';
        $html .= '<a href="' . esc_url( $share_url ) . '"'
               . ' class="medialab-share__btn medialab-share__btn--' . esc_attr( $key ) . '"'
               . ' target="' . esc_attr( $target ) . '"'
               . $rel
               . ' title="' . esc_attr( sprintf( __( 'Auf %s teilen', 'media-lab-core' ), $service['label'] ) ) . '"'
               . ' style="--share-color: ' . esc_attr( $service['color'] ) . ';"'
               . '>';
        $html .= $service['icon'];
        $html .= '<span class="medialab-share__btn-label">' . esc_html( $service['label'] ) . '</span>';
        $html .= '</a></li>';
    }

    $html .= '</ul></div>';

    if ( $has_copy ) {
        $html .= '<script>'
               . '(function(){'
               . 'document.addEventListener("click",function(e){'
               . 'var btn=e.target.closest(".medialab-share__btn--copy");'
               . 'if(!btn)return;'
               . 'var url=btn.dataset.copyUrl||location.href;'
               . 'navigator.clipboard&&navigator.clipboard.writeText(url).then(function(){'
               . 'var lbl=btn.querySelector(".medialab-share__btn-label");'
               . 'if(!lbl)return;'
               . 'var orig=lbl.textContent;lbl.textContent="\u2713 Kopiert!";'
               . 'setTimeout(function(){lbl.textContent=orig;},2000);'
               . '});'
               . '});'
               . '})();'
               . '</script>';
    }

    return $html;
}

// =============================================================================
// SERVICE-DEFINITIONEN
// =============================================================================

function medialab_social_share_services(): array {
    return array(
        'whatsapp' => array(
            'label' => 'WhatsApp', 'color' => '#25D366',
            'url'   => 'https://api.whatsapp.com/send?text={title}%20{url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
        ),
        'facebook' => array(
            'label' => 'Facebook', 'color' => '#1877F2',
            'url'   => 'https://www.facebook.com/sharer/sharer.php?u={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        ),
        'twitter'  => array(
            'label' => 'X / Twitter', 'color' => '#000000',
            'url'   => 'https://twitter.com/intent/tweet?url={url}&text={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        ),
        'linkedin' => array(
            'label' => 'LinkedIn', 'color' => '#0A66C2',
            'url'   => 'https://www.linkedin.com/shareArticle?mini=true&url={url}&title={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
        ),
        'xing'     => array(
            'label' => 'Xing', 'color' => '#006567',
            'url'   => 'https://www.xing.com/spi/shares/new?url={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.188 0c-.517 0-.741.325-.927.66 0 0-7.455 13.224-7.702 13.657.015.024 4.919 9.023 4.919 9.023.17.308.436.66.967.66h3.454c.211 0 .375-.078.463-.22.089-.151.089-.346-.009-.536l-4.879-8.916c-.004-.006-.004-.016 0-.022L22.139.756c.095-.191.097-.387.006-.535C22.056.078 21.894 0 21.686 0h-3.498zM3.648 4.74c-.211 0-.385.074-.473.216-.09.149-.078.339.02.531l2.34 4.05c.004.01.004.016 0 .021L1.86 16.051c-.099.188-.093.381 0 .529.085.142.239.234.45.234h3.461c.518 0 .766-.348.945-.667l3.734-6.609-2.378-4.155c-.172-.315-.434-.659-.962-.659H3.648v.016z"/></svg>',
        ),
        'pinterest' => array(
            'label' => 'Pinterest', 'color' => '#E60023',
            'url'   => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>',
        ),
        'telegram' => array(
            'label' => 'Telegram', 'color' => '#26A5E4',
            'url'   => 'https://t.me/share/url?url={url}&text={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
        ),
        'reddit'   => array(
            'label' => 'Reddit', 'color' => '#FF4500',
            'url'   => 'https://www.reddit.com/submit?url={url}&title={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>',
        ),
        'instagram' => array(
            'label' => 'Instagram', 'color' => '#E1306C',
            'url'   => 'https://www.instagram.com/',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
        ),
        'tiktok'   => array(
            'label' => 'TikTok', 'color' => '#010101',
            'url'   => 'https://www.tiktok.com/share?url={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.75a4.85 4.85 0 01-1.01-.06z"/></svg>',
        ),
        'email'    => array(
            'label' => 'E-Mail', 'color' => '#6B7280',
            'url'   => 'mailto:?subject={title}&body={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
        ),
        'copy'     => array(
            'label' => 'Link kopieren', 'color' => '#4B5563',
            'url'   => '',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>',
        ),
    );
}

// =============================================================================
// ACF OPTIONS PAGE + FIELD GROUPS
// =============================================================================

function medialab_social_share_acf(): void {
    if ( ! function_exists( 'acf_add_options_sub_page' ) ) return;

    acf_add_options_sub_page( array(
        'page_title'  => 'Share-Buttons',
        'menu_title'  => 'Share-Buttons',
        'parent_slug' => 'agency-core',
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-social-share',
        'position'    => false,
        'redirect'    => false,
    ) );

    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    $service_choices = array(
        'whatsapp'  => 'WhatsApp',
        'facebook'  => 'Facebook',
        'twitter'   => 'X / Twitter',
        'linkedin'  => 'LinkedIn',
        'xing'      => 'Xing',
        'pinterest' => 'Pinterest',
        'telegram'  => 'Telegram',
        'reddit'    => 'Reddit',
        'instagram' => 'Instagram',
        'tiktok'    => 'TikTok',
        'email'     => 'E-Mail',
        'copy'      => 'Link kopieren',
    );

    $style_choices = array(
        'colored' => 'Farbig (Markenfarben)',
        'mono'    => 'Einfarbig',
        'outline' => 'Outline',
        'ghost'   => 'Ghost / Minimalistisch',
    );

    $post_type_choices = array();
    foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
        $post_type_choices[ $pt->name ] = $pt->label;
    }

    // ── Globale Einstellungen ─────────────────────────────────────────────────
    acf_add_local_field_group( array(
        'key'    => 'group_social_share_settings',
        'title'  => 'Share-Buttons – Globale Einstellungen',
        'fields' => array(

            // Kanäle (Repeater → Drag & Drop Reihenfolge)
            array(
                'key'          => 'field_ss_default_services',
                'label'        => 'Aktivierte Kanäle',
                'name'         => 'ss_default_services',
                'type'         => 'repeater',
                'min'          => 0,
                'layout'       => 'table',
                'button_label' => 'Kanal hinzufügen',
                'instructions' => 'Reihenfolge per Drag & Drop ändern. Jeden Kanal nur einmal eintragen.',
                'wrapper'      => array( 'width' => '100' ),
                'sub_fields'   => array(
                    array(
                        'key'           => 'field_ss_service_key',
                        'label'         => 'Kanal',
                        'name'          => 'ss_service',
                        'type'          => 'select',
                        'choices'       => $service_choices,
                        'default_value' => 'whatsapp',
                        'allow_null'    => 0,
                        'wrapper'       => array( 'width' => '100' ),
                    ),
                ),
            ),

            // Stil + Icon-only
            array(
                'key'           => 'field_ss_default_style',
                'label'         => 'Button-Stil',
                'name'          => 'ss_default_style',
                'type'          => 'select',
                'choices'       => $style_choices,
                'default_value' => 'colored',
                'wrapper'       => array( 'width' => '33' ),
            ),
            array(
                'key'           => 'field_ss_default_icon_only',
                'label'         => 'Nur Icons (Labels ausblenden)',
                'name'          => 'ss_default_icon_only',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'instructions'  => 'Labels sind weiterhin für Screen-Reader sichtbar.',
                'wrapper'       => array( 'width' => '33' ),
            ),

            // Layout
            array(
                'key'           => 'field_ss_default_layout',
                'label'         => 'Layout',
                'name'          => 'ss_default_layout',
                'type'          => 'radio',
                'choices'       => array(
                    'horizontal' => 'Horizontal',
                    'vertical'   => 'Vertikal',
                ),
                'default_value' => 'horizontal',
                'layout'        => 'horizontal',
                'wrapper'       => array( 'width' => '34' ),
            ),

            // Label
            array(
                'key'           => 'field_ss_default_show_label',
                'label'         => 'Äußeres Label anzeigen',
                'name'          => 'ss_default_show_label',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'wrapper'       => array( 'width' => '25' ),
            ),
            array(
                'key'           => 'field_ss_default_label',
                'label'         => 'Label-Text',
                'name'          => 'ss_default_label',
                'type'          => 'text',
                'default_value' => 'Teilen',
                'wrapper'       => array( 'width' => '25' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_default_show_label', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

            // Auto-Insert
            array(
                'key' => 'field_ss_auto_insert_heading', 'label' => ' ', 'name' => 'ss_auto_insert_heading',
                'type' => 'message', 'message' => '<strong style="font-size:13px;">Automatische Einbindung</strong>', 'default_value' => '',
            ),
            array(
                'key'           => 'field_ss_auto_insert',
                'label'         => 'Automatisch nach Beitrags-Inhalt einblenden',
                'name'          => 'ss_auto_insert',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => array( 'width' => '100' ),
            ),
            array(
                'key'           => 'field_ss_auto_insert_post_types',
                'label'         => 'Post-Types',
                'name'          => 'ss_auto_insert_post_types',
                'type'          => 'checkbox',
                'choices'       => $post_type_choices,
                'default_value' => array( 'post' ),
                'layout'        => 'horizontal',
                'wrapper'       => array( 'width' => '100' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_auto_insert', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

            // Hinweis Instagram
            array(
                'key' => 'field_ss_instagram_note', 'label' => ' ', 'name' => 'ss_instagram_note',
                'type' => 'message',
                'message' => '<p style="color:#856404;background:#fff3cd;border:1px solid #ffc107;padding:8px 12px;border-radius:4px;font-size:12px;margin:0;">'
                           . '<strong>Hinweis Instagram:</strong> Keine offizielle Web-Share-URL. Auf Mobile öffnet der Button die Instagram-App, auf Desktop instagram.com.</p>',
                'default_value' => '',
            ),

        ),
        'location' => array( array( array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-social-share',
        ) ) ),
        'menu_order' => 0, 'position' => 'normal', 'style' => 'default',
        'label_placement' => 'top', 'instruction_placement' => 'label',
    ) );

    // ── Block-Felder ──────────────────────────────────────────────────────────
    acf_add_local_field_group( array(
        'key'    => 'group_block_social_share',
        'title'  => 'Share-Buttons Block',
        'fields' => array(

            array(
                'key'           => 'field_ss_block_override',
                'label'         => 'Globale Einstellungen überschreiben',
                'name'          => 'ss_block_override',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => array( 'width' => '100' ),
            ),

            // Kanäle (Repeater)
            array(
                'key'          => 'field_ss_block_services',
                'label'        => 'Kanäle (Reihenfolge per Drag & Drop)',
                'name'         => 'ss_block_services',
                'type'         => 'repeater',
                'min'          => 0,
                'layout'       => 'table',
                'button_label' => 'Kanal hinzufügen',
                'instructions' => 'Leer = globale Einstellungen.',
                'wrapper'      => array( 'width' => '100' ),
                'sub_fields'   => array(
                    array(
                        'key' => 'field_ss_block_service_key', 'label' => 'Kanal',
                        'name' => 'ss_block_service', 'type' => 'select',
                        'choices' => $service_choices, 'default_value' => 'whatsapp',
                        'allow_null' => 0, 'wrapper' => array( 'width' => '100' ),
                    ),
                ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_block_override', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

            // Stil
            array(
                'key'           => 'field_ss_block_style',
                'label'         => 'Button-Stil',
                'name'          => 'ss_block_style',
                'type'          => 'select',
                'choices'       => $style_choices,
                'default_value' => '',
                'allow_null'    => 1,
                'instructions'  => 'Leer = globale Einstellung',
                'wrapper'       => array( 'width' => '34' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_block_override', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

            // Icon-only
            array(
                'key'           => 'field_ss_block_icon_only',
                'label'         => 'Nur Icons',
                'name'          => 'ss_block_icon_only',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'wrapper'       => array( 'width' => '33' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_block_override', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

            // Layout
            array(
                'key'           => 'field_ss_block_layout',
                'label'         => 'Layout',
                'name'          => 'ss_block_layout',
                'type'          => 'radio',
                'choices'       => array( 'horizontal' => 'Horizontal', 'vertical' => 'Vertikal' ),
                'default_value' => '',
                'layout'        => 'horizontal',
                'wrapper'       => array( 'width' => '33' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_block_override', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

            // Label
            array(
                'key' => 'field_ss_block_show_label', 'label' => 'Äußeres Label', 'name' => 'ss_block_show_label',
                'type' => 'true_false', 'ui' => 1, 'default_value' => 1,
                'wrapper' => array( 'width' => '25' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_block_override', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),
            array(
                'key' => 'field_ss_block_label', 'label' => 'Label-Text', 'name' => 'ss_block_label',
                'type' => 'text', 'default_value' => '', 'placeholder' => 'Teilen',
                'wrapper' => array( 'width' => '25' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_ss_block_override', 'operator' => '==', 'value' => '1',
                ) ) ),
            ),

        ),
        'location' => array( array( array(
            'param' => 'block', 'operator' => '==', 'value' => 'medialab/social-share',
        ) ) ),
        'menu_order' => 0,
    ) );
}
