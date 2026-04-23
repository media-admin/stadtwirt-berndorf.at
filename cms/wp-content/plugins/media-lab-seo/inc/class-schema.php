<?php
/**
 * MLT_Schema
 *
 * Gibt JSON-LD strukturierte Daten im <head> aus:
 *  - WebSite (immer)
 *  - Organization (immer)
 *  - Article (bei Posts)
 *  - BreadcrumbList (bei Seiten mit Breadcrumbs)
 *  - LocalBusiness (optional, wenn ACF-Felder gesetzt)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Schema {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 5 );
    }

    public function output_schema() {
        $schemas = [];

        $schemas[] = $this->get_website_schema();
        $schemas[] = $this->get_organization_schema();

        if ( is_single() && get_post_type() === 'post' ) {
            $schemas[] = $this->get_article_schema();
        }

        $breadcrumbs = $this->get_breadcrumb_list();
        if ( $breadcrumbs ) {
            $schemas[] = $breadcrumbs;
        }

        echo "\n<!-- Media Lab SEO Toolkit: Schema.org -->\n";
        foreach ( array_filter( $schemas ) as $schema ) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
            echo "\n</script>\n";
        }
        echo "<!-- /Media Lab SEO Toolkit: Schema.org -->\n\n";
    }

    // ── WebSite ───────────────────────────────────────────────────────────────

    private function get_website_schema() {
        return [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            '@id'      => home_url( '/#website' ),
            'url'      => home_url( '/' ),
            'name'     => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url( '/?s={search_term_string}' ),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    // ── Organization ──────────────────────────────────────────────────────────

    private function get_organization_schema() {
        $name  = get_bloginfo( 'name' );
        $url   = home_url( '/' );
        $logo  = get_option( 'mlt_og_default_image', 0 );
        $logo_url = $logo ? wp_get_attachment_image_url( $logo, 'full' ) : '';

        // ACF: Logo aus Agency Core falls vorhanden
        if ( ! $logo_url && function_exists( 'get_field' ) ) {
            $acf_logo = get_field( 'logo', 'option' );
            if ( $acf_logo ) {
                $logo_url = is_array( $acf_logo ) ? ( $acf_logo['url'] ?? '' ) : $acf_logo;
            }
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => [ 'Organization', 'Brand' ],
            '@id'      => $url . '#organization',
            'url'      => $url,
            'name'     => $name,
        ];

        if ( $logo_url ) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logo_url,
            ];
        }

        // ACF LocalBusiness-Felder (Top Header)
        if ( function_exists( 'get_field' ) ) {
            $phone   = get_field( 'phone', 'option' );
            $email   = get_field( 'email', 'option' );
            $address = get_field( 'address', 'option' );

            if ( $phone )   $schema['telephone']    = $phone;
            if ( $email )   $schema['email']        = $email;
            if ( $address ) $schema['address']      = [ '@type' => 'PostalAddress', 'streetAddress' => $address ];
        }

        return $schema;
    }

    // ── Article ───────────────────────────────────────────────────────────────

    private function get_article_schema() {
        $post    = get_queried_object();
        if ( ! $post ) return null;

        $author  = get_userdata( $post->post_author );
        $image   = get_the_post_thumbnail_url( $post->ID, 'full' );
        if ( ! $image ) {
            $fallback = get_option( 'mlt_og_default_image', 0 );
            $image = $fallback ? wp_get_attachment_image_url( $fallback, 'full' ) : '';
        }

        $schema = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => get_the_title( $post->ID ),
            'description'      => wp_trim_words( wp_strip_all_tags( $post->post_content ), 30, '…' ),
            'url'              => get_permalink( $post->ID ),
            'datePublished'    => get_the_date( 'c', $post->ID ),
            'dateModified'     => get_the_modified_date( 'c', $post->ID ),
            'publisher'        => [ '@id' => home_url( '/#organization' ) ],
        ];

        if ( $image ) {
            $schema['image'] = [ '@type' => 'ImageObject', 'url' => $image ];
        }

        if ( $author ) {
            $schema['author'] = [
                '@type' => 'Person',
                'name'  => $author->display_name,
                'url'   => get_author_posts_url( $author->ID ),
            ];
        }

        return $schema;
    }

    // ── BreadcrumbList ────────────────────────────────────────────────────────

    public function get_breadcrumb_list() {
        $items = MLT_Breadcrumbs::get_items();
        if ( count( $items ) < 2 ) return null;

        $list = [];
        foreach ( $items as $i => $item ) {
            $entry = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
            ];
            if ( ! empty( $item['url'] ) ) {
                $entry['item'] = $item['url'];
            }
            $list[] = $entry;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }
}
