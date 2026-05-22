<?php
/**
 * Drag & Drop + Arrow Button Post Order
 *
 * Sortierung von Posts, Pages und allen CPTs in der WP-Admin-Listenansicht.
 * Unterstützt zwei Eingabemethoden:
 *   1. Drag & Drop via jquery-ui-sortable
 *   2. ▲ / ▼ Pfeil-Buttons (Tastatur- und Touch-freundlich)
 *
 * Reihenfolge wird in wp_posts.menu_order gespeichert →
 * kompatibel mit orderby=menu_order in WP_Query.
 *
 * @package  media-lab-agency-core
 * @since    1.9.1  Drag & Drop
 * @since    1.9.2  Arrow Buttons
 */

defined( 'ABSPATH' ) || exit;

class MediaLab_Post_Order {

    /**
     * Post-Types die sortierbar sein sollen.
     * @var string[]
     */
    private array $sortable_types = [
        'post', 'page',
        'hero_slide', 'team', 'project', 'testimonial',
        'faq', 'gmap', 'carousel', 'service',
        'event', 'job', 'notification',
    ];

    public function __construct() {
        add_action( 'admin_enqueue_scripts',          [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_medialab_update_post_order', [ $this, 'ajax_update_order' ] );
        add_action( 'pre_get_posts',                  [ $this, 'default_order_in_admin' ] );
        add_action( 'pre_get_posts',                  [ $this, 'default_order_in_frontend' ] );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    /**
     * Skripte und Styles nur auf Post-Listen-Seiten des richtigen Post-Types laden.
     */
    public function enqueue_scripts( string $hook ): void {
        if ( 'edit.php' !== $hook ) {
            return;
        }

        $post_type = sanitize_key( $_GET['post_type'] ?? 'post' );

        if ( ! in_array( $post_type, $this->sortable_types, true ) ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-sortable' );

        wp_enqueue_script(
            'medialab-post-order',
            MEDIALAB_CORE_URL . 'assets/js/post-order.js',
            [ 'jquery', 'jquery-ui-sortable' ],
            MEDIALAB_CORE_VERSION,
            true
        );

        wp_localize_script( 'medialab-post-order', 'medialabPostOrder', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'medialab_post_order' ),
            'postType' => $post_type,
            'i18n'     => [
                'saving'  => __( 'Speichern …', 'media-lab' ),
                'saved'   => __( '✓ Reihenfolge gespeichert', 'media-lab' ),
                'error'   => __( 'Fehler beim Speichern', 'media-lab' ),
                'network' => __( 'Netzwerkfehler', 'media-lab' ),
                'up'      => __( 'Nach oben', 'media-lab' ),
                'down'    => __( 'Nach unten', 'media-lab' ),
                'drag'    => __( 'Ziehen zum Sortieren', 'media-lab' ),
            ],
        ] );

        wp_enqueue_style(
            'medialab-post-order',
            MEDIALAB_CORE_URL . 'assets/css/post-order.css',
            [],
            MEDIALAB_CORE_VERSION
        );
    }

    // ── AJAX Handler ──────────────────────────────────────────────────────────

    /**
     * Neue Reihenfolge validieren und als menu_order in die DB schreiben.
     */
    public function ajax_update_order(): void {
        check_ajax_referer( 'medialab_post_order', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
        }

        $order = array_map( 'absint', (array) ( $_POST['order'] ?? [] ) );
        $order = array_filter( $order ); // 0-Werte entfernen

        if ( empty( $order ) ) {
            wp_send_json_error( [ 'message' => 'Keine Einträge.' ], 400 );
        }

        global $wpdb;

        foreach ( $order as $position => $post_id ) {
            $wpdb->update(
                $wpdb->posts,
                [ 'menu_order' => $position ],
                [ 'ID'         => $post_id ],
                [ '%d' ],
                [ '%d' ]
            );

            clean_post_cache( $post_id );
        }

        wp_send_json_success( [
            'message' => __( 'Reihenfolge gespeichert.', 'media-lab' ),
            'count'   => count( $order ),
        ] );
    }

    // ── Query-Hooks ───────────────────────────────────────────────────────────

    /**
     * Admin-Listenansicht standardmäßig nach menu_order sortieren.
     */
    public function default_order_in_admin( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_type = sanitize_key( $_GET['post_type'] ?? 'post' );

        if ( ! in_array( $post_type, $this->sortable_types, true ) ) {
            return;
        }

        if ( ! $query->get( 'orderby' ) ) {
            $query->set( 'orderby', 'menu_order' );
            $query->set( 'order', 'ASC' );
        }
    }

    /**
     * Frontend-Queries der CPTs standardmäßig nach menu_order sortieren.
     */
    public function default_order_in_frontend( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $post_type    = $query->get( 'post_type' );
        $custom_types = array_diff( $this->sortable_types, [ 'post', 'page' ] );

        if ( in_array( $post_type, $custom_types, true ) && ! $query->get( 'orderby' ) ) {
            $query->set( 'orderby', 'menu_order' );
            $query->set( 'order', 'ASC' );
        }
    }
}

new MediaLab_Post_Order();
