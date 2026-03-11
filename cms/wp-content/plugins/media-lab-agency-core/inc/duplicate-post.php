<?php
/**
 * Duplicate Post / Page / CPT / Taxonomy Term
 *
 * - "Duplizieren" Link in Admin-Listenansichten (Posts, Pages, CPTs)
 * - Kopiert: Titel, Inhalt, Excerpt, Meta-Felder (inkl. ACF), Taxonomien, Menu Order
 * - Status des Duplikats: immer "draft"
 * - Taxonomy Terms: eigener Link in Term-Listen
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Duplicate_Post {

    private $supported_post_types = array(
        'post', 'page',
        'hero_slide', 'team', 'project', 'testimonial',
        'faq', 'gmap', 'carousel', 'service',
        'event', 'job', 'notification',
    );

    private $supported_taxonomies = array(
        'category', 'post_tag',
        'project_category', 'service_category', 'faq_category',
        'event_category', 'job_category', 'job_type', 'job_location',
        'carousel_category',
    );

    public function __construct() {
        // Row Actions für Posts/Pages/CPTs
        add_filter('post_row_actions',    array($this, 'add_row_action'), 10, 2);
        add_filter('page_row_actions',    array($this, 'add_row_action'), 10, 2);

        // Row Actions für Taxonomy Terms
        foreach ($this->supported_taxonomies as $taxonomy) {
            add_filter("{$taxonomy}_row_actions", array($this, 'add_term_row_action'), 10, 2);
        }

        // AJAX / GET Handler
        add_action('admin_action_medialab_duplicate_post', array($this, 'duplicate_post'));
        add_action('admin_action_medialab_duplicate_term', array($this, 'duplicate_term'));

        // Admin Notice nach Duplizieren
        add_action('admin_notices', array($this, 'admin_notice'));
    }

    // ─────────────────────────────────────────────────────────────
    // ROW ACTIONS
    // ─────────────────────────────────────────────────────────────

    public function add_row_action($actions, $post) {
        if (!in_array($post->post_type, $this->supported_post_types)) return $actions;
        if (!current_user_can('edit_posts')) return $actions;

        $url = wp_nonce_url(
            admin_url('admin.php?action=medialab_duplicate_post&post=' . $post->ID),
            'medialab_duplicate_' . $post->ID
        );

        $actions['medialab_duplicate'] = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url($url),
            esc_attr__('Diesen Eintrag duplizieren', 'media-lab-core'),
            __('Duplizieren', 'media-lab-core')
        );

        return $actions;
    }

    public function add_term_row_action($actions, $term) {
        if (!current_user_can('manage_categories')) return $actions;

        $url = wp_nonce_url(
            admin_url('admin.php?action=medialab_duplicate_term&term=' . $term->term_id . '&taxonomy=' . $term->taxonomy),
            'medialab_duplicate_term_' . $term->term_id
        );

        $actions['medialab_duplicate'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            __('Duplizieren', 'media-lab-core')
        );

        return $actions;
    }

    // ─────────────────────────────────────────────────────────────
    // DUPLICATE POST
    // ─────────────────────────────────────────────────────────────

    public function duplicate_post() {
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$post_id) wp_die('Ungültige Post-ID');

        check_admin_referer('medialab_duplicate_' . $post_id);

        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Keine Berechtigung');
        }

        $post = get_post($post_id);
        if (!$post) wp_die('Post nicht gefunden');

        // Neuen Post anlegen
        $new_post_id = wp_insert_post(array(
            'post_title'     => $post->post_title . ' ' . __('(Kopie)', 'media-lab-core'),
            'post_content'   => $post->post_content,
            'post_excerpt'   => $post->post_excerpt,
            'post_status'    => 'draft',
            'post_type'      => $post->post_type,
            'post_author'    => get_current_user_id(),
            'post_parent'    => $post->post_parent,
            'menu_order'     => $post->menu_order,
            'comment_status' => $post->comment_status,
            'ping_status'    => $post->ping_status,
        ), true);

        if (is_wp_error($new_post_id)) {
            wp_die($new_post_id->get_error_message());
        }

        // Meta-Felder kopieren (inkl. ACF)
        $this->copy_post_meta($post_id, $new_post_id);

        // Taxonomien kopieren
        $this->copy_taxonomies($post_id, $new_post_id, $post->post_type);

        // Featured Image kopieren
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($new_post_id, $thumbnail_id);
        }

        // Zurück zur Liste mit Erfolgsmeldung
        $redirect = add_query_arg(
            array(
                'post_type'           => $post->post_type !== 'post' ? $post->post_type : false,
                'medialab_duplicated' => 1,
                'new_post_id'         => $new_post_id,
            ),
            admin_url('edit.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // DUPLICATE TERM
    // ─────────────────────────────────────────────────────────────

    public function duplicate_term() {
        $term_id  = isset($_GET['term'])     ? absint($_GET['term'])         : 0;
        $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';

        if (!$term_id || !$taxonomy) wp_die('Ungültige Parameter');

        check_admin_referer('medialab_duplicate_term_' . $term_id);

        if (!current_user_can('manage_categories')) wp_die('Keine Berechtigung');

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) wp_die('Term nicht gefunden');

        // Eindeutigen Slug generieren
        $new_slug = $term->slug . '-kopie';
        $counter  = 1;
        while (get_term_by('slug', $new_slug, $taxonomy)) {
            $new_slug = $term->slug . '-kopie-' . $counter;
            $counter++;
        }

        $new_term = wp_insert_term(
            $term->name . ' ' . __('(Kopie)', 'media-lab-core'),
            $taxonomy,
            array(
                'description' => $term->description,
                'parent'      => $term->parent,
                'slug'        => $new_slug,
            )
        );

        if (is_wp_error($new_term)) wp_die($new_term->get_error_message());

        // Term Meta kopieren
        $new_term_id = $new_term['term_id'];
        $meta        = get_term_meta($term_id);
        if ($meta) {
            foreach ($meta as $key => $values) {
                foreach ($values as $value) {
                    add_term_meta($new_term_id, $key, maybe_unserialize($value));
                }
            }
        }

        wp_safe_redirect(add_query_arg(
            array(
                'taxonomy'            => $taxonomy,
                'medialab_duplicated' => 1,
            ),
            admin_url('edit-tags.php')
        ));
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function copy_post_meta($old_id, $new_id) {
        global $wpdb;

        // Interne WP-Meta-Keys überspringen
        $skip = array('_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date');

        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                $old_id
            )
        );

        foreach ($meta_rows as $row) {
            if (in_array($row->meta_key, $skip)) continue;
            add_post_meta($new_id, $row->meta_key, maybe_unserialize($row->meta_value));
        }
    }

    private function copy_taxonomies($old_id, $new_id, $post_type) {
        $taxonomies = get_object_taxonomies($post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($old_id, $taxonomy, array('fields' => 'ids'));
            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($new_id, $terms, $taxonomy);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ADMIN NOTICE
    // ─────────────────────────────────────────────────────────────

    public function admin_notice() {
        if (empty($_GET['medialab_duplicated'])) return;

        $new_id = !empty($_GET['new_post_id']) ? absint($_GET['new_post_id']) : 0;

        if ($new_id) {
            $edit_link = get_edit_post_link($new_id);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Eintrag wurde dupliziert.', 'media-lab-core'),
                esc_url($edit_link),
                esc_html__('Kopie bearbeiten →', 'media-lab-core')
            );
        } else {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__('Term wurde dupliziert.', 'media-lab-core')
            );
        }
    }
}

new MediaLab_Duplicate_Post();
