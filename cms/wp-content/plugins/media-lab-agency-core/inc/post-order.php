<?php
/**
 * Drag & Drop Post Order
 *
 * Ermöglicht das Sortieren von Posts, Pages und allen CPTs
 * per Drag & Drop in der WP-Admin-Listenansicht.
 *
 * Sortierung wird in wp_posts.menu_order gespeichert →
 * kompatibel mit orderby=menu_order in WP_Query.
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Post_Order {

    // Post Types die sortierbar sein sollen
    // 'post' und 'page' + alle eigenen CPTs
    private $sortable_types = array(
        'post', 'page',
        'hero_slide', 'team', 'project', 'testimonial',
        'faq', 'gmap', 'carousel', 'service',
        'event', 'job', 'notification',
    );

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_medialab_update_post_order', array($this, 'ajax_update_order'));

        // Standardmäßig nach menu_order sortieren in Admin-Listen
        add_action('pre_get_posts', array($this, 'default_order_in_admin'));

        // menu_order als Standard für Frontend-Queries der CPTs
        add_action('pre_get_posts', array($this, 'default_order_in_frontend'));
    }

    /**
     * Skripte nur auf Post-Listen-Seiten laden
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'edit.php') return;

        $post_type = $_GET['post_type'] ?? 'post';
        if (!in_array($post_type, $this->sortable_types)) return;

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'medialab-post-order',
            MEDIALAB_CORE_URL . 'assets/js/post-order.js',
            array('jquery', 'jquery-ui-sortable'),
            MEDIALAB_CORE_VERSION,
            true
        );
        wp_localize_script('medialab-post-order', 'medialabPostOrder', array(
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('medialab_post_order'),
            'postType'  => $post_type,
            'i18n'      => array(
                'saving'  => __('Speichern...', 'media-lab-core'),
                'saved'   => __('Reihenfolge gespeichert', 'media-lab-core'),
                'error'   => __('Fehler beim Speichern', 'media-lab-core'),
            ),
        ));

        wp_enqueue_style(
            'medialab-post-order',
            MEDIALAB_CORE_URL . 'assets/css/post-order.css',
            array(),
            MEDIALAB_CORE_VERSION
        );
    }

    /**
     * AJAX: Reihenfolge in DB speichern
     */
    public function ajax_update_order() {
        check_ajax_referer('medialab_post_order', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung', 403);
        }

        $order = $_POST['order'] ?? array();
        if (empty($order) || !is_array($order)) {
            wp_send_json_error('Keine Daten');
        }

        global $wpdb;
        foreach ($order as $position => $post_id) {
            $post_id  = absint($post_id);
            $position = absint($position);
            if (!$post_id) continue;

            $wpdb->update(
                $wpdb->posts,
                array('menu_order' => $position),
                array('ID' => $post_id),
                array('%d'),
                array('%d')
            );
        }

        wp_send_json_success('OK');
    }

    /**
     * Admin-Listen standardmäßig nach menu_order sortieren
     */
    public function default_order_in_admin($query) {
        if (!is_admin() || !$query->is_main_query()) return;

        $post_type = $query->get('post_type') ?: 'post';
        if (!in_array($post_type, $this->sortable_types)) return;

        // Nur wenn noch kein explizites Sorting gesetzt
        if (!$query->get('orderby')) {
            $query->set('orderby', 'menu_order');
            $query->set('order', 'ASC');
        }
    }

    /**
     * Frontend-Queries der CPTs auf menu_order umstellen
     */
    public function default_order_in_frontend($query) {
        if (is_admin() || !$query->is_main_query()) return;

        $post_type = $query->get('post_type');
        $custom_types = array_diff($this->sortable_types, array('post', 'page'));

        if (in_array($post_type, $custom_types) && !$query->get('orderby')) {
            $query->set('orderby', 'menu_order');
            $query->set('order', 'ASC');
        }
    }
}

new MediaLab_Post_Order();


// ==========================================================================
// Taxonomy Term Order
// ==========================================================================

class MediaLab_Term_Order {

    private $meta_key = 'medialab_term_order';

    private $supported_taxonomies = array(
        'category', 'post_tag',
        'project_category', 'service_category', 'faq_category',
        'event_category', 'job_category', 'job_type', 'job_location',
        'carousel_category',
    );

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_medialab_update_term_order', array($this, 'ajax_update_order'));

        // Terms nach medialab_term_order sortieren
        add_filter('terms_clauses', array($this, 'order_terms_by_meta'), 10, 3);
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'edit-tags.php') return;

        $taxonomy = $_GET['taxonomy'] ?? '';
        if (!in_array($taxonomy, $this->supported_taxonomies)) return;

        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'medialab-term-order',
            MEDIALAB_CORE_URL . 'assets/js/post-order.js',
            array('jquery', 'jquery-ui-sortable'),
            MEDIALAB_CORE_VERSION,
            true
        );
        wp_localize_script('medialab-term-order', 'medialabPostOrder', array(
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('medialab_term_order'),
            'mode'     => 'term',
            'taxonomy' => $taxonomy,
            'i18n'     => array(
                'saving' => __('Speichern...', 'media-lab-core'),
                'saved'  => __('Reihenfolge gespeichert', 'media-lab-core'),
                'error'  => __('Fehler beim Speichern', 'media-lab-core'),
            ),
        ));

        wp_enqueue_style('medialab-post-order', MEDIALAB_CORE_URL . 'assets/css/post-order.css', array(), MEDIALAB_CORE_VERSION);
    }

    public function ajax_update_order() {
        check_ajax_referer('medialab_term_order', 'nonce');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error('Keine Berechtigung', 403);
        }

        $order = $_POST['order'] ?? array();
        if (empty($order) || !is_array($order)) {
            wp_send_json_error('Keine Daten');
        }

        foreach ($order as $position => $term_id) {
            update_term_meta(absint($term_id), $this->meta_key, absint($position));
        }

        wp_send_json_success('OK');
    }

    public function order_terms_by_meta($clauses, $taxonomies, $args) {
        // Nur wenn explizit nach term_order sortiert werden soll
        // oder wenn es eine unserer Taxonomien ohne explizites orderby ist
        if (!empty($args['orderby']) && $args['orderby'] !== 'medialab_order') {
            return $clauses;
        }

        $relevant = array_intersect((array) $taxonomies, $this->supported_taxonomies);
        if (empty($relevant)) return $clauses;

        global $wpdb;
        $clauses['join']   .= " LEFT JOIN {$wpdb->termmeta} AS tm_order ON (t.term_id = tm_order.term_id AND tm_order.meta_key = 'medialab_term_order')";
        $clauses['orderby'] = 'ORDER BY CAST(IFNULL(tm_order.meta_value, 999999) AS UNSIGNED) ASC, t.name ASC';

        return $clauses;
    }
}

new MediaLab_Term_Order();
