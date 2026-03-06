<?php
/**
 * Helper Functions
 * 
 * @package MediaLab_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin version
 */
function medialab_core_version() {
    return MEDIALAB_CORE_VERSION;
}

/**
 * Check if Media Lab Core is active
 * Useful for theme/plugin compatibility checks
 */
function is_medialab_core_active() {
    return true;
}

// =============================================================================
// RATE LIMITING (F-03)
// Schützt öffentliche AJAX-Endpunkte vor Abuse / DDoS.
// Transient-basiert – kein externer Service nötig.
// =============================================================================

if ( ! function_exists( 'medialab_check_rate_limit' ) ) {
    /**
     * Rate-Limiting für öffentliche AJAX-Endpunkte.
     *
     * @param string $action  Eindeutiger Key (z.B. 'search', 'filter', 'load_more')
     * @param int    $max     Max. Anfragen pro Zeitfenster (default: 30)
     * @param int    $window  Zeitfenster in Sekunden (default: 60)
     * @return bool  true = erlaubt, false = blockiert
     */
    function medialab_check_rate_limit( string $action, int $max = 30, int $window = 60 ): bool {
        $ip  = preg_replace( '/[^0-9a-f.:]/i', '', $_SERVER['REMOTE_ADDR'] ?? '' );
        $key = 'rl_' . md5( $action . $ip );

        $hits = (int) get_transient( $key );
        if ( $hits >= $max ) {
            return false;
        }
        set_transient( $key, $hits + 1, $window );
        return true;
    }
}

// =============================================================================
// RESPONSIVE THUMBNAIL HELPER (Performance)
// Ersetzt get_the_post_thumbnail_url() – liefert img-Tag mit srcset + lazy.
// =============================================================================

if ( ! function_exists( 'medialab_get_thumbnail' ) ) {
    /**
     * Gibt ein vollständiges <img>-Tag mit srcset, sizes und loading="lazy" zurück.
     *
     * @param int          $post_id    Post-ID (default: current post)
     * @param string|array $size       Image size (default: 'medium')
     * @param array        $attr       Zusätzliche img-Attribute (class, alt, etc.)
     * @return string  <img>-Tag oder leerer String wenn kein Thumbnail
     */
    function medialab_get_thumbnail( int $post_id = 0, $size = 'medium', array $attr = [] ): string {
        if ( ! $post_id ) {
            $post_id = get_the_ID();
        }

        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) {
            return '';
        }

        // Standardwerte mit lazy loading
        $defaults = array(
            'loading'  => 'lazy',
            'decoding' => 'async',
        );

        $attr = wp_parse_args( $attr, $defaults );

        // Alt-Text aus Attachment-Meta wenn nicht übergeben
        if ( empty( $attr['alt'] ) ) {
            $attr['alt'] = trim( strip_tags( get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) ) )
                        ?: get_the_title( $post_id );
        }

        return wp_get_attachment_image( $thumb_id, $size, false, $attr );
    }
}

if ( ! function_exists( 'medialab_the_thumbnail' ) ) {
    /**
     * Gibt medialab_get_thumbnail() direkt aus.
     */
    function medialab_the_thumbnail( int $post_id = 0, $size = 'medium', array $attr = [] ): void {
        echo medialab_get_thumbnail( $post_id, $size, $attr ); // phpcs:ignore WordPress.Security.EscapeOutput
    }
}
