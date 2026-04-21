<?php
/**
 * Social Share Buttons
 *
 * DSGVO-konform: Kein externes Script wird beim Seitenaufruf geladen.
 * Daten werden erst beim aktiven Klick des Besuchers übertragen.
 *
 * Shortcode: [medialab_share]
 *
 * Attribute:
 *   services   – Kommagetrennte Liste der Dienste (Standard: whatsapp,facebook,twitter,linkedin)
 *                Verfügbare Dienste: whatsapp, facebook, twitter, linkedin, xing, pinterest,
 *                                   telegram, reddit, email
 *   layout     – "horizontal" (Standard) oder "vertical"
 *   show_label – "true" (Standard) oder "false"
 *   label      – Überschrift über den Buttons (Standard: "Teilen")
 *
 * Beispiele:
 *   [medialab_share]
 *   [medialab_share services="whatsapp,facebook,email" show_label="false"]
 *   [medialab_share services="facebook,linkedin,xing" layout="vertical" label="Artikel teilen"]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registriert den Shortcode und Enqueue-Hook.
 */
add_action( 'init', 'medialab_social_share_init' );
function medialab_social_share_init() {
    add_shortcode( 'medialab_share', 'medialab_social_share_shortcode' );
}

add_action( 'wp_enqueue_scripts', 'medialab_social_share_assets' );
function medialab_social_share_assets() {
    // CSS wird nur geladen, wenn der Shortcode auf der Seite vorkommt.
    // Da WordPress has_shortcode() den Post-Content braucht, laden wir
    // das Stylesheet global – es ist minimal und hat keinen Performance-Impact.
    wp_enqueue_style(
        'medialab-social-share',
        MEDIALAB_CORE_URL . 'assets/css/social-share.css',
        array(),
        MEDIALAB_CORE_VERSION
    );
}

/**
 * Gibt die Konfiguration aller unterstützten Dienste zurück.
 *
 * @return array
 */
function medialab_social_share_services() {
    return array(
        'whatsapp' => array(
            'label' => 'WhatsApp',
            'color' => '#25D366',
            'url'   => 'https://api.whatsapp.com/send?text={title}%20{url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
            'mobile_only' => false,
        ),
        'facebook' => array(
            'label' => 'Facebook',
            'color' => '#1877F2',
            'url'   => 'https://www.facebook.com/sharer/sharer.php?u={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'mobile_only' => false,
        ),
        'twitter'  => array(
            'label' => 'X / Twitter',
            'color' => '#000000',
            'url'   => 'https://twitter.com/intent/tweet?url={url}&text={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'mobile_only' => false,
        ),
        'linkedin' => array(
            'label' => 'LinkedIn',
            'color' => '#0A66C2',
            'url'   => 'https://www.linkedin.com/shareArticle?mini=true&url={url}&title={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'mobile_only' => false,
        ),
        'xing'     => array(
            'label' => 'Xing',
            'color' => '#006567',
            'url'   => 'https://www.xing.com/spi/shares/new?url={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.188 0c-.517 0-.741.325-.927.66 0 0-7.455 13.224-7.702 13.657.015.024 4.919 9.023 4.919 9.023.17.308.436.66.967.66h3.454c.211 0 .375-.078.463-.22.089-.151.089-.346-.009-.536l-4.879-8.916c-.004-.006-.004-.016 0-.022L22.139.756c.095-.191.097-.387.006-.535C22.056.078 21.894 0 21.686 0h-3.498zM3.648 4.74c-.211 0-.385.074-.473.216-.09.149-.078.339.02.531l2.34 4.05c.004.01.004.016 0 .021L1.86 16.051c-.099.188-.093.381 0 .529.085.142.239.234.45.234h3.461c.518 0 .766-.348.945-.667l3.734-6.609-2.378-4.155c-.172-.315-.434-.659-.962-.659H3.648v.016z"/></svg>',
            'mobile_only' => false,
        ),
        'pinterest' => array(
            'label' => 'Pinterest',
            'color' => '#E60023',
            'url'   => 'https://pinterest.com/pin/create/button/?url={url}&description={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>',
            'mobile_only' => false,
        ),
        'telegram' => array(
            'label' => 'Telegram',
            'color' => '#26A5E4',
            'url'   => 'https://t.me/share/url?url={url}&text={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
            'mobile_only' => false,
        ),
        'reddit'   => array(
            'label' => 'Reddit',
            'color' => '#FF4500',
            'url'   => 'https://www.reddit.com/submit?url={url}&title={title}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>',
            'mobile_only' => false,
        ),
        'email'    => array(
            'label' => 'E-Mail',
            'color' => '#6B7280',
            'url'   => 'mailto:?subject={title}&body={url}',
            'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'mobile_only' => false,
        ),
    );
}

/**
 * Shortcode-Handler.
 *
 * @param  array $atts  Shortcode-Attribute
 * @return string       HTML-Output
 */
function medialab_social_share_shortcode( $atts ) {

    $defaults = array(
        'services'   => 'whatsapp,facebook,twitter,linkedin',
        'layout'     => 'horizontal',
        'show_label' => 'true',
        'label'      => __( 'Teilen', 'media-lab-core' ),
    );

    $atts = shortcode_atts( $defaults, $atts, 'medialab_share' );

    $all_services  = medialab_social_share_services();
    $requested     = array_map( 'trim', explode( ',', $atts['services'] ) );
    $layout        = in_array( $atts['layout'], array( 'horizontal', 'vertical' ), true )
                     ? $atts['layout']
                     : 'horizontal';
    $show_label    = $atts['show_label'] !== 'false';
    $label         = esc_html( $atts['label'] );

    // Aktuelle Seite
    $page_url   = rawurlencode( get_permalink() );
    $page_title = rawurlencode( get_the_title() );

    $html  = '<div class="medialab-share medialab-share--' . esc_attr( $layout ) . '" role="complementary" aria-label="' . esc_attr__( 'Artikel teilen', 'media-lab-core' ) . '">';

    if ( $show_label && $label ) {
        $html .= '<span class="medialab-share__label">' . $label . '</span>';
    }

    $html .= '<ul class="medialab-share__list">';

    foreach ( $requested as $key ) {
        if ( ! isset( $all_services[ $key ] ) ) {
            continue;
        }

        $service    = $all_services[ $key ];
        $share_url  = str_replace(
            array( '{url}', '{title}' ),
            array( $page_url, $page_title ),
            $service['url']
        );

        // mailto öffnet keinen neuen Tab
        $target = ( $key === 'email' ) ? '_self' : '_blank';
        $rel    = ( $key === 'email' ) ? '' : 'rel="noopener noreferrer"';

        $html .= '<li class="medialab-share__item">';
        $html .= '<a href="' . esc_url( $share_url ) . '"'
               . ' class="medialab-share__btn medialab-share__btn--' . esc_attr( $key ) . '"'
               . ' target="' . esc_attr( $target ) . '"'
               . ' ' . $rel
               . ' title="' . esc_attr( sprintf( __( 'Auf %s teilen', 'media-lab-core' ), $service['label'] ) ) . '"'
               . ' style="--share-color: ' . esc_attr( $service['color'] ) . ';"'
               . '>';
        $html .= $service['icon'];
        $html .= '<span class="medialab-share__btn-label">' . esc_html( $service['label'] ) . '</span>';
        $html .= '</a>';
        $html .= '</li>';
    }

    $html .= '</ul>';
    $html .= '</div>';

    return $html;
}
