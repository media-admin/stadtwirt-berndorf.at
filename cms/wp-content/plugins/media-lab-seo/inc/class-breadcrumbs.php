<?php
/**
 * MLT_Breadcrumbs
 *
 * Generiert Breadcrumb-Navigation.
 * Shortcode: [mlt_breadcrumbs]
 * Template-Tag: mlt_breadcrumbs()
 *
 * Datenprovider: statische get_items() wird auch von MLT_Schema genutzt.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Breadcrumbs {

    public function __construct() {
        add_shortcode( 'mlt_breadcrumbs', [ $this, 'shortcode' ] );
    }

    // ── Shortcode ─────────────────────────────────────────────────────────────

    public function shortcode( $atts ) {
        $atts = shortcode_atts( [
            'separator' => '/',
            'class'     => '',
        ], $atts, 'mlt_breadcrumbs' );

        return self::render( $atts['separator'], $atts['class'] );
    }

    // ── HTML ausgeben ─────────────────────────────────────────────────────────

    public static function render( $separator = '/', $extra_class = '' ) {
        $items = self::get_items();
        if ( count( $items ) < 2 ) return '';

        $class = trim( 'mlt-breadcrumbs ' . $extra_class );
        $sep   = '<span class="mlt-breadcrumbs__sep" aria-hidden="true">' . esc_html( $separator ) . '</span>';

        $html  = '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Brotkrümel-Navigation', 'media-lab-seo' ) . '">';
        $html .= '<ol class="mlt-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

        $last = count( $items ) - 1;
        foreach ( $items as $i => $item ) {
            $is_last = ( $i === $last );
            $html .= '<li class="mlt-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

            if ( ! $is_last && ! empty( $item['url'] ) ) {
                $html .= '<a class="mlt-breadcrumbs__link" href="' . esc_url( $item['url'] ) . '" itemprop="item">';
                $html .= '<span itemprop="name">' . esc_html( $item['name'] ) . '</span>';
                $html .= '</a>';
            } else {
                $html .= '<span class="mlt-breadcrumbs__current" itemprop="name" aria-current="page">' . esc_html( $item['name'] ) . '</span>';
            }

            $html .= '<meta itemprop="position" content="' . ( $i + 1 ) . '">';
            if ( ! $is_last ) $html .= $sep;
            $html .= '</li>';
        }

        $html .= '</ol></nav>';

        return $html;
    }

    // ── Items generieren (auch für Schema.org genutzt) ────────────────────────

    public static function get_items() {
        $items = [];

        // Startseite immer als erstes Element
        $items[] = [
            'name' => get_bloginfo( 'name' ),
            'url'  => home_url( '/' ),
        ];

        if ( is_home() || is_front_page() ) {
            return $items; // nur Startseite → keine Breadcrumbs nötig
        }

        if ( is_singular() ) {
            $post = get_queried_object();

            // Übergeordnete Seiten (bei hierarchischen Post Types)
            if ( is_page() && $post->post_parent ) {
                $ancestors = array_reverse( get_post_ancestors( $post->ID ) );
                foreach ( $ancestors as $ancestor_id ) {
                    $items[] = [
                        'name' => get_the_title( $ancestor_id ),
                        'url'  => get_permalink( $ancestor_id ),
                    ];
                }
            }

            // Beitrag: Kategorie als Zwischenebene
            if ( is_single() && get_post_type() === 'post' ) {
                $cats = get_the_category( $post->ID );
                if ( $cats ) {
                    $items[] = [
                        'name' => $cats[0]->name,
                        'url'  => get_category_link( $cats[0]->term_id ),
                    ];
                }
            }

            // CPT: Archiv-Seite als Zwischenebene
            if ( is_single() && get_post_type() !== 'post' ) {
                $archive_link = get_post_type_archive_link( get_post_type() );
                $pto          = get_post_type_object( get_post_type() );
                if ( $archive_link && $pto ) {
                    $items[] = [
                        'name' => $pto->labels->name,
                        'url'  => $archive_link,
                    ];
                }
            }

            // Aktuelle Seite/Post (kein Link)
            $items[] = [
                'name' => get_the_title( $post->ID ),
                'url'  => '',
            ];

        } elseif ( is_category() || is_tag() || is_tax() ) {
            $term = get_queried_object();
            if ( $term->parent ) {
                $parent = get_term( $term->parent, $term->taxonomy );
                if ( $parent && ! is_wp_error( $parent ) ) {
                    $items[] = [
                        'name' => $parent->name,
                        'url'  => get_term_link( $parent ),
                    ];
                }
            }
            $items[] = [
                'name' => $term->name,
                'url'  => '',
            ];

        } elseif ( is_author() ) {
            $items[] = [
                'name' => get_the_author_meta( 'display_name', get_queried_object_id() ),
                'url'  => '',
            ];

        } elseif ( is_search() ) {
            $items[] = [
                'name' => sprintf( 'Suchergebnisse für: %s', get_search_query() ),
                'url'  => '',
            ];

        } elseif ( is_404() ) {
            $items[] = [ 'name' => '404 – Seite nicht gefunden', 'url' => '' ];

        } elseif ( is_archive() ) {
            $pto = get_post_type_object( get_post_type() );
            $items[] = [
                'name' => $pto ? $pto->labels->name : __( 'Archiv', 'media-lab-seo' ),
                'url'  => '',
            ];
        }

        /**
         * Filter: Breadcrumb-Items anpassen.
         *
         * @param array $items Array von ['name' => string, 'url' => string]
         */
        return apply_filters( 'mlt_breadcrumb_items', $items );
    }
}

// Template-Tag für direkte Theme-Nutzung
function mlt_breadcrumbs( $separator = '/', $class = '' ) {
    echo MLT_Breadcrumbs::render( $separator, $class ); // phpcs:ignore
}
