<?php
/**
 * Breadcrumbs
 *
 * Eigenständige Implementierung – kein Yoast/RankMath nötig.
 * Verwendung: <?php medialab_breadcrumbs(); ?>
 *
 * @package MediaLab_SEO
 */

if ( ! defined('ABSPATH') ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// 1. Hauptfunktion
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists('medialab_breadcrumbs') ) {

    /**
     * Gibt die Breadcrumb-Navigation aus.
     *
     * @param array $args {
     *   @type string $separator    Trennzeichen (default: '›')
     *   @type bool   $show_home    Startseite anzeigen (default: true)
     *   @type string $home_label   Label der Startseite (default: 'Start')
     *   @type bool   $show_current Aktuelle Seite anzeigen (default: true)
     *   @type string $container    HTML-Wrapper-Tag: nav|div|ol (default: 'nav')
     *   @type string $class        CSS-Klasse (default: 'breadcrumbs')
     *   @type bool   $schema       Schema.org JSON-LD (default: true)
     * }
     */
    function medialab_breadcrumbs( array $args = [] ) : void {

        $defaults = [
            'separator'    => '›',
            'show_home'    => true,
            'home_label'   => __( 'Start', 'media-lab-seo' ),
            'show_current' => true,
            'container'    => 'nav',
            'class'        => 'breadcrumbs',
            'schema'       => true,
        ];

        $args = wp_parse_args( $args, $defaults );

        if ( is_front_page() ) return;

        $crumbs = _medialab_breadcrumbs_build( $args );
        if ( empty( $crumbs ) ) return;

        echo _medialab_breadcrumbs_render( $crumbs, $args ); // phpcs:ignore
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. Crumbs aufbauen
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists('_medialab_breadcrumbs_build') ) :
function _medialab_breadcrumbs_build( array $args ) : array {

    $crumbs       = [];
    $blog_page_id = (int) get_option('page_for_posts');

    // Startseite
    if ( $args['show_home'] ) {
        $crumbs[] = [ 'label' => $args['home_label'], 'url' => home_url('/'), 'current' => false ];
    }

    // 404
    if ( is_404() ) {
        $crumbs[] = [ 'label' => __( 'Seite nicht gefunden', 'media-lab-seo' ), 'url' => '', 'current' => true ];
        return $crumbs;
    }

    // Suche
    if ( is_search() ) {
        $crumbs[] = [ 'label' => sprintf( __( 'Suche: %s', 'media-lab-seo' ), get_search_query() ), 'url' => '', 'current' => true ];
        return $crumbs;
    }

    // Einzelne Seite / Beitrag / CPT
    if ( is_singular() ) {

        $post      = get_queried_object();
        $post_type = get_post_type();

        // Blog-Seite als Zwischenebene bei Posts
        if ( $blog_page_id && $post_type === 'post' ) {
            $crumbs[] = [ 'label' => get_the_title( $blog_page_id ), 'url' => get_permalink( $blog_page_id ), 'current' => false ];
        }

        // CPT: Archive-Link
        if ( $post_type !== 'post' && $post_type !== 'page' ) {
            $pto = get_post_type_object( $post_type );
            if ( $pto && $pto->has_archive ) {
                $crumbs[] = [ 'label' => $pto->labels->name, 'url' => get_post_type_archive_link( $post_type ), 'current' => false ];
            }
        }

        // Primäre Kategorie für Posts (tiefster Term)
        if ( $post_type === 'post' ) {
            $terms = get_the_terms( $post->ID, 'category' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                $term = _medialab_breadcrumbs_deepest_term( $terms );
                if ( $term ) {
                    foreach ( array_reverse( get_ancestors( $term->term_id, 'category' ) ) as $id ) {
                        $a = get_term( $id, 'category' );
                        $crumbs[] = [ 'label' => $a->name, 'url' => get_term_link( $a ), 'current' => false ];
                    }
                    $crumbs[] = [ 'label' => $term->name, 'url' => get_term_link( $term ), 'current' => false ];
                }
            }
        }

        // Eltern-Seiten (Pages, hierarchische CPTs)
        if ( $post->post_parent ) {
            foreach ( array_reverse( get_post_ancestors( $post->ID ) ) as $id ) {
                $crumbs[] = [ 'label' => get_the_title( $id ), 'url' => get_permalink( $id ), 'current' => false ];
            }
        }

        // Aktuelle Seite
        if ( $args['show_current'] ) {
            $crumbs[] = [ 'label' => get_the_title(), 'url' => get_permalink(), 'current' => true ];
        }

        return $crumbs;
    }

    // Kategorie / Tag / Taxonomie
    if ( is_category() || is_tag() || is_tax() ) {

        $term     = get_queried_object();
        $taxonomy = $term->taxonomy;

        if ( $taxonomy === 'category' && $blog_page_id ) {
            $crumbs[] = [ 'label' => get_the_title( $blog_page_id ), 'url' => get_permalink( $blog_page_id ), 'current' => false ];
        }

        // Custom Taxonomy: CPT-Archive als Zwischenebene
        if ( $taxonomy !== 'category' && $taxonomy !== 'post_tag' ) {
            $tax_obj    = get_taxonomy( $taxonomy );
            $post_types = $tax_obj->object_type ?? [];
            if ( ! empty( $post_types[0] ) ) {
                $pto = get_post_type_object( $post_types[0] );
                if ( $pto && $pto->has_archive ) {
                    $crumbs[] = [ 'label' => $pto->labels->name, 'url' => get_post_type_archive_link( $post_types[0] ), 'current' => false ];
                }
            }
        }

        foreach ( array_reverse( get_ancestors( $term->term_id, $taxonomy ) ) as $id ) {
            $a = get_term( $id, $taxonomy );
            $crumbs[] = [ 'label' => $a->name, 'url' => get_term_link( $a ), 'current' => false ];
        }

        $crumbs[] = [ 'label' => $term->name, 'url' => get_term_link( $term ), 'current' => true ];
        return $crumbs;
    }

    // CPT Archive
    if ( is_post_type_archive() ) {
        $pto = get_queried_object();
        $crumbs[] = [ 'label' => post_type_archive_title( '', false ), 'url' => get_post_type_archive_link( $pto->name ), 'current' => true ];
        return $crumbs;
    }

    // Datum-Archive
    if ( is_year() || is_month() || is_day() ) {
        if ( $blog_page_id ) {
            $crumbs[] = [ 'label' => get_the_title( $blog_page_id ), 'url' => get_permalink( $blog_page_id ), 'current' => false ];
        }
        if ( is_day() ) {
            $crumbs[] = [ 'label' => get_the_date('Y'), 'url' => get_year_link( get_the_date('Y') ), 'current' => false ];
            $crumbs[] = [ 'label' => get_the_date('F'), 'url' => get_month_link( get_the_date('Y'), get_the_date('m') ), 'current' => false ];
            $crumbs[] = [ 'label' => get_the_date('d'), 'url' => '', 'current' => true ];
        } elseif ( is_month() ) {
            $crumbs[] = [ 'label' => get_the_date('Y'), 'url' => get_year_link( get_the_date('Y') ), 'current' => false ];
            $crumbs[] = [ 'label' => get_the_date('F'), 'url' => '', 'current' => true ];
        } else {
            $crumbs[] = [ 'label' => get_the_date('Y'), 'url' => '', 'current' => true ];
        }
        return $crumbs;
    }

    // Autor-Archiv
    if ( is_author() ) {
        $author = get_queried_object();
        $crumbs[] = [ 'label' => $author->display_name, 'url' => '', 'current' => true ];
        return $crumbs;
    }

    // Blog-Seite (is_home, statische Startseite)
    if ( is_home() && $blog_page_id ) {
        $crumbs[] = [ 'label' => get_the_title( $blog_page_id ), 'url' => '', 'current' => true ];
    }

    return $crumbs;
}
endif;


// ─────────────────────────────────────────────────────────────────────────────
// 3. HTML rendern
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists('_medialab_breadcrumbs_render') ) :
function _medialab_breadcrumbs_render( array $crumbs, array $args ) : string {

    $sep       = esc_html( $args['separator'] );
    $class     = esc_attr( $args['class'] );
    $container = in_array( $args['container'], ['nav', 'div', 'ol', 'aside'], true ) ? $args['container'] : 'nav';
    $aria      = $container === 'nav' ? ' aria-label="' . esc_attr__( 'Breadcrumb', 'media-lab-seo' ) . '"' : '';

    // Schema.org JSON-LD
    $schema_html = '';
    if ( $args['schema'] ) {
        $items = [];
        foreach ( $crumbs as $i => $c ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => wp_strip_all_tags( $c['label'] ),
                'item'     => ! empty( $c['url'] ) ? $c['url'] : get_permalink(),
            ];
        }
        $schema_html = '<script type="application/ld+json">'
            . wp_json_encode( [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ],
                              JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . '</script>' . "\n";
    }

    // HTML
    $html  = $schema_html;
    $html .= '<' . $container . ' class="' . $class . '"' . $aria . '>' . "\n";
    $html .= '<ol class="' . $class . '__list" itemscope itemtype="https://schema.org/BreadcrumbList">' . "\n";

    $last = count( $crumbs ) - 1;

    foreach ( $crumbs as $i => $c ) {

        $is_last    = ( $i === $last );
        $label      = esc_html( $c['label'] );
        $url        = ! empty( $c['url'] ) ? esc_url( $c['url'] ) : '';
        $item_class = $class . '__item' . ( $is_last ? ' ' . $class . '__item--current' : '' );

        $html .= '<li class="' . $item_class . '" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

        if ( $url && ! $is_last ) {
            $html .= '<a class="' . $class . '__link" href="' . $url . '" itemprop="item"><span itemprop="name">' . $label . '</span></a>';
        } else {
            $html .= '<span class="' . $class . '__current" itemprop="name" aria-current="page">' . $label . '</span>';
        }

        $html .= '<meta itemprop="position" content="' . ( $i + 1 ) . '">';

        if ( ! $is_last ) {
            $html .= '<span class="' . $class . '__separator" aria-hidden="true">' . $sep . '</span>';
        }

        $html .= '</li>' . "\n";
    }

    $html .= '</ol>' . "\n";
    $html .= '</' . $container . '>' . "\n";

    return apply_filters( 'medialab_breadcrumbs_html', $html, $crumbs, $args );
}
endif;


// ─────────────────────────────────────────────────────────────────────────────
// 4. Hilfsfunktion: tiefsten Term finden
// ─────────────────────────────────────────────────────────────────────────────

if ( ! function_exists('_medialab_breadcrumbs_deepest_term') ) :
function _medialab_breadcrumbs_deepest_term( array $terms ) : ?WP_Term {

    $deepest = null;
    $max     = -1;

    foreach ( $terms as $term ) {
        $depth = count( get_ancestors( $term->term_id, $term->taxonomy ) );
        if ( $depth > $max ) {
            $max     = $depth;
            $deepest = $term;
        }
    }

    return $deepest;
}
endif;
