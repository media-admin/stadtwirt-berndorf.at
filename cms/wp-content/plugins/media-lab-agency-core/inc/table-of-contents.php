<?php
/**
 * Table of Contents
 *
 * ─── Verwendung ──────────────────────────────────────────────────────────────
 *
 * Shortcode (inline im Content):
 *   [table_of_contents]
 *   [table_of_contents title="Inhalt" depth="3" mode="inline" min_headings="2"]
 *
 * Shortcode (sticky – in einem zweispaltigen Layout-Wrapper):
 *   [table_of_contents mode="sticky"]
 *
 * PHP-Funktion (Theme-Template):
 *   <?php medialab_toc(); ?>
 *   <?php medialab_toc( array( 'mode' => 'sticky', 'depth' => 2 ) ); ?>
 *
 * Gutenberg-Block „Inhaltsverzeichnis" (Kategorie „Design"):
 *   Modus (inline / sticky), Tiefe (H2/H3/H4), Titel, Min-Headings
 *   per Block-Inspector konfigurierbar.
 *
 * ─── Automatische Heading-IDs ────────────────────────────────────────────────
 *   `the_content`-Filter ergänzt fehlende IDs an H2/H3/H4.
 *   Bestehende IDs werden nicht überschrieben.
 *   Nur auf Einzel-Seiten/Posts aktiv (is_singular).
 *
 * ─── Sticky-Modus ────────────────────────────────────────────────────────────
 *   Im sticky-Modus erhält die `.toc`-Wrapper-Klasse `toc--sticky`.
 *   Das CSS setzt `position: sticky; top: var(--toc-top, 2rem)`.
 *   Für sinnvolles Sticky muss der Block in einem zweispaltigen
 *   Layout (z.B. Columns-Block) eingesetzt werden.
 *
 * ─── JS / Scrollspy ──────────────────────────────────────────────────────────
 *   `table-of-contents.js` highlightet den aktiven Eintrag beim Scrollen
 *   und scrollt das ToC wenn es sticky und länger als der Viewport ist.
 *
 * @package MediaLab_Core
 * @since   1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// INIT
// =============================================================================

add_action( 'init',               'medialab_toc_init'           );
add_action( 'wp_enqueue_scripts', 'medialab_toc_assets'         );
add_action( 'acf/init',           'medialab_toc_acf'            );
add_filter( 'the_content',        'medialab_toc_add_heading_ids', 8 ); // vor [shortcode]-Expansion

function medialab_toc_init(): void {
    add_shortcode( 'table_of_contents', 'medialab_toc_shortcode' );
    add_shortcode( 'toc',               'medialab_toc_shortcode' ); // Alias
}

function medialab_toc_assets(): void {
    if ( ! is_singular() ) return;

    wp_enqueue_style(
        'medialab-toc',
        MEDIALAB_CORE_URL . 'assets/css/table-of-contents.css',
        array(),
        MEDIALAB_CORE_VERSION
    );

    wp_enqueue_script(
        'medialab-toc',
        MEDIALAB_CORE_URL . 'assets/js/table-of-contents.js',
        array(),
        MEDIALAB_CORE_VERSION,
        true
    );
}

// =============================================================================
// HEADING-IDs IN CONTENT EINFÜGEN
// =============================================================================

/**
 * Ergänzt fehlende id-Attribute an H2/H3/H4 im Post-Content.
 * Nur auf Einzel-Seiten/Posts aktiv.
 * Bestehende IDs bleiben unberührt.
 */
function medialab_toc_add_heading_ids( string $content ): string {
    if ( ! is_singular() ) return $content;
    if ( empty( $content ) ) return $content;

    $used_ids = array();

    return preg_replace_callback(
        '/<(h[2-4])([^>]*)>(.*?)<\/h[2-4]>/si',
        function ( $m ) use ( &$used_ids ) {
            [ , $tag, $attrs, $inner ] = $m;

            // Bereits eine ID? → unverändert lassen
            if ( preg_match( '/\bid=["\'][^"\']+["\']/i', $attrs ) ) {
                return $m[0];
            }

            // Text → Slug
            $text = wp_strip_all_tags( $inner );
            $slug = medialab_toc_make_id( $text, $used_ids );
            $used_ids[] = $slug;

            return '<' . $tag . $attrs . ' id="' . esc_attr( $slug ) . '">' . $inner . '</' . $tag . '>';
        },
        $content
    );
}

/**
 * Erzeugt einen eindeutigen, URL-sicheren ID-Slug.
 *
 * @param  string   $text     Heading-Text
 * @param  string[] $used_ids Bereits verwendete IDs (wird intern verändert)
 */
function medialab_toc_make_id( string $text, array $used_ids ): string {
    $slug = sanitize_title( $text );
    $slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );
    $slug = $slug ?: 'section';

    $base   = $slug;
    $suffix = 2;
    while ( in_array( $slug, $used_ids, true ) ) {
        $slug = $base . '-' . $suffix++;
    }

    return $slug;
}

// =============================================================================
// HEADINGS AUS POST-CONTENT EXTRAHIEREN
// =============================================================================

/**
 * Liest alle H2/H3/H4 (je nach $depth) aus dem Content des aktuellen Posts
 * und gibt ein Array mit [id, text, level] zurück.
 *
 * WICHTIG – kein apply_filters('the_content') hier:
 *   Würde do_blocks() auslösen → ToC-Block rendern → get_headings() → Loop
 *   → PHP Fatal: Allowed memory size exhausted.
 *   Stattdessen: parse_blocks() parst die Block-Struktur ohne zu rendern.
 *   Für Classic-Editor-Content (kein Block-Markup) wird der Raw-Content
 *   direkt per Regex ausgewertet.
 *
 * @param  int  $depth  2 = nur H2, 3 = H2+H3, 4 = H2+H3+H4
 * @return array<int, array{id: string, text: string, level: int}>
 */
function medialab_toc_get_headings( int $depth = 3 ): array {
    $post = get_post();
    if ( ! $post ) return array();

    $headings = array();
    $used_ids = array();
    $content  = $post->post_content;

    // Enthält der Content Gutenberg-Block-Kommentare?
    if ( str_contains( $content, '<!-- wp:' ) ) {
        // ── Gutenberg: Block-Struktur direkt parsen ───────────────────────────
        $blocks = parse_blocks( $content );
        medialab_toc_collect_headings( $blocks, $depth, $headings, $used_ids );
    } else {
        // ── Classic Editor: Raw-HTML per Regex auswerten ──────────────────────
        $pattern = match ( true ) {
            $depth >= 4 => '/<(h[2-4])([^>]*)>(.*?)<\/h[2-4]>/si',
            $depth >= 3 => '/<(h[23])([^>]*)>(.*?)<\/h[23]>/si',
            default     => '/<(h2)([^>]*)>(.*?)<\/h2>/si',
        };

        preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

        foreach ( $matches as $m ) {
            $level = (int) substr( strtolower( $m[1] ), 1 );
            $text  = wp_strip_all_tags( $m[3] );
            if ( $text === '' ) continue;

            $id = preg_match( '/\bid=["\']([^"\']+)["\']/i', $m[2], $id_m )
                ? $id_m[1]
                : medialab_toc_make_id( $text, $used_ids );

            $used_ids[] = $id;
            $headings[] = array( 'id' => $id, 'text' => $text, 'level' => $level );
        }
    }

    return $headings;
}

/**
 * Rekursiv core/heading-Blöcke aus parse_blocks()-Output extrahieren.
 * Durchsucht auch innerBlocks (Columns, Group, Cover …).
 *
 * ID-Priorität:
 *   1. Gutenberg-Anchor-Attribut (attrs.anchor) – vom Editor gesetzt
 *   2. Bestehendes id-Attribut im innerHTML
 *   3. Aus Heading-Text generiert (sanitize_title)
 *
 * @param  array<int, array>  $blocks    parse_blocks()-Ausgabe
 * @param  int                $depth     Max. Heading-Level
 * @param  array<int, array>  $headings  Rückgabe-Array (per Referenz)
 * @param  array<int, string> $used_ids  Bereits vergebene IDs (per Referenz)
 */
function medialab_toc_collect_headings(
    array  $blocks,
    int    $depth,
    array &$headings,
    array &$used_ids
): void {
    foreach ( $blocks as $block ) {

        // ── Überschriften-Block ───────────────────────────────────────────────
        if ( $block['blockName'] === 'core/heading' ) {
            $level = (int) ( $block['attrs']['level'] ?? 2 );
            if ( $level < 2 || $level > $depth ) {
                continue;
            }

            $inner = $block['innerHTML'] ?? '';
            $text  = wp_strip_all_tags( $inner );
            if ( $text === '' ) continue;

            // ID ermitteln
            if ( ! empty( $block['attrs']['anchor'] ) ) {
                // Gutenberg-Anchor hat höchste Priorität
                $id = sanitize_html_class( $block['attrs']['anchor'] );
            } elseif ( preg_match( '/\bid=["\']([^"\']+)["\']/i', $inner, $id_m ) ) {
                $id = $id_m[1];
            } else {
                $id = medialab_toc_make_id( $text, $used_ids );
            }

            $used_ids[] = $id;
            $headings[] = array( 'id' => $id, 'text' => $text, 'level' => $level );
        }

        // ── InnerBlocks rekursiv durchsuchen ──────────────────────────────────
        if ( ! empty( $block['innerBlocks'] ) ) {
            medialab_toc_collect_headings( $block['innerBlocks'], $depth, $headings, $used_ids );
        }
    }
}

// =============================================================================
// RENDER
// =============================================================================

/**
 * Baut das ToC-HTML.
 *
 * @param array{
 *   title:        string,
 *   depth:        int,
 *   mode:         string,
 *   min_headings: int,
 * } $args
 */
function medialab_toc_render( array $args ): string {
    $title        = $args['title']        ?? __( 'Inhaltsverzeichnis', 'media-lab-core' );
    $depth        = max( 2, min( 4, (int) ( $args['depth'] ?? 3 ) ) );
    $mode         = in_array( $args['mode'] ?? '', array( 'inline', 'sticky' ), true )
                    ? $args['mode'] : 'inline';
    $min_headings = max( 1, (int) ( $args['min_headings'] ?? 2 ) );

    $headings = medialab_toc_get_headings( $depth );

    if ( count( $headings ) < $min_headings ) return '';

    // ── Wrapper-Klassen ───────────────────────────────────────────────────────
    $classes = array_filter( array(
        'toc',
        'toc--' . esc_attr( $mode ),
    ) );

    $html  = '<nav class="' . implode( ' ', $classes ) . '" aria-label="' . esc_attr__( 'Inhaltsverzeichnis', 'media-lab-core' ) . '">';

    if ( $title ) {
        $html .= '<div class="toc__header">';
        $html .= '<span class="toc__title">' . esc_html( $title ) . '</span>';
        $html .= '<button class="toc__toggle" aria-expanded="true" aria-label="' . esc_attr__( 'Inhaltsverzeichnis ein-/ausblenden', 'media-lab-core' ) . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>';
        $html .= '</button>';
        $html .= '</div>';
    }

    $html .= '<ol class="toc__list" role="list">';

    $current_level = 2; // H2 = Basis
    $open_lists    = 0;

    foreach ( $headings as $heading ) {
        $lvl = $heading['level'];

        if ( $lvl > $current_level ) {
            // Tiefer → neue verschachtelte Liste öffnen
            while ( $current_level < $lvl ) {
                $html .= '<ol class="toc__sublist" role="list">';
                $open_lists++;
                $current_level++;
            }
        } elseif ( $lvl < $current_level ) {
            // Höher → offene Listen schließen
            while ( $current_level > $lvl && $open_lists > 0 ) {
                $html .= '</ol>';
                $open_lists--;
                $current_level--;
            }
        }

        $html .= '<li class="toc__item toc__item--h' . $lvl . '">';
        $html .= '<a href="#' . esc_attr( $heading['id'] ) . '" class="toc__link">';
        $html .= esc_html( $heading['text'] );
        $html .= '</a>';
        $html .= '</li>';
    }

    // Offene Listen schließen
    while ( $open_lists > 0 ) {
        $html .= '</ol>';
        $open_lists--;
    }

    $html .= '</ol>';
    $html .= '</nav>';

    return $html;
}

// =============================================================================
// SHORTCODE
// =============================================================================

function medialab_toc_shortcode( array $atts ): string {
    $atts = shortcode_atts(
        array(
            'title'        => __( 'Inhaltsverzeichnis', 'media-lab-core' ),
            'depth'        => 3,
            'mode'         => 'inline',
            'min_headings' => 2,
        ),
        $atts,
        'table_of_contents'
    );

    $atts['depth']        = (int) $atts['depth'];
    $atts['min_headings'] = (int) $atts['min_headings'];

    return medialab_toc_render( $atts );
}

// =============================================================================
// PHP-TEMPLATE-FUNKTION
// =============================================================================

/**
 * Gibt das Inhaltsverzeichnis direkt aus (für Theme-Templates).
 *
 * @param array $args  title, depth, mode, min_headings
 *
 * @example
 *   medialab_toc();
 *   medialab_toc( array( 'mode' => 'sticky', 'depth' => 2 ) );
 */
function medialab_toc( array $args = array() ): void {
    $args = array_merge(
        array(
            'title'        => __( 'Inhaltsverzeichnis', 'media-lab-core' ),
            'depth'        => 3,
            'mode'         => 'inline',
            'min_headings' => 2,
        ),
        $args
    );
    echo medialab_toc_render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput
}

// =============================================================================
// ACF BLOCK FELDER
// =============================================================================

function medialab_toc_acf(): void {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'   => 'group_block_toc',
        'title' => 'Inhaltsverzeichnis Block',
        'fields' => array(

            array(
                'key'           => 'field_toc_title',
                'label'         => 'Titel',
                'name'          => 'toc_title',
                'type'          => 'text',
                'default_value' => __( 'Inhaltsverzeichnis', 'media-lab-core' ),
                'placeholder'   => __( 'Inhaltsverzeichnis', 'media-lab-core' ),
                'instructions'  => 'Leer lassen = kein Titel',
                'wrapper'       => array( 'width' => '50' ),
            ),

            array(
                'key'           => 'field_toc_mode',
                'label'         => 'Anzeigemodus',
                'name'          => 'toc_mode',
                'type'          => 'radio',
                'choices'       => array(
                    'inline' => 'Inline (im Content-Fluss)',
                    'sticky' => 'Sticky (haftet beim Scrollen – in Sidebar einsetzen)',
                ),
                'default_value' => 'inline',
                'layout'        => 'horizontal',
                'instructions'  => 'Sticky: Block in einem zweispaltigen Layout (z.B. Columns-Block) platzieren.',
                'wrapper'       => array( 'width' => '100' ),
            ),

            array(
                'key'           => 'field_toc_depth',
                'label'         => 'Tiefe',
                'name'          => 'toc_depth',
                'type'          => 'radio',
                'choices'       => array(
                    '2' => 'Nur H2',
                    '3' => 'H2 + H3',
                    '4' => 'H2 + H3 + H4',
                ),
                'default_value' => '3',
                'layout'        => 'horizontal',
                'wrapper'       => array( 'width' => '50' ),
            ),

            array(
                'key'           => 'field_toc_min_headings',
                'label'         => 'Mindestanzahl Überschriften',
                'name'          => 'toc_min_headings',
                'type'          => 'number',
                'default_value' => 2,
                'min'           => 1,
                'max'           => 10,
                'instructions'  => 'Weniger Überschriften im Content = Block wird ausgeblendet.',
                'wrapper'       => array( 'width' => '50' ),
            ),

        ),
        'location' => array( array( array(
            'param' => 'block', 'operator' => '==', 'value' => 'medialab/table-of-contents',
        ) ) ),
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
    ) );
}
