<?php
/**
 * Duplicate Post / Term
 * Fügt "Duplizieren"-Link in alle Post- und Term-Listenansichten ein.
 * Kopiert Titel, Content, Meta (inkl. ACF), Taxonomien, Featured Image.
 * Duplikat wird immer als Entwurf erstellt.
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 */
if (!defined('ABSPATH')) { exit; }

// ── Posts ──────────────────────────────────────────────────────────────────

add_filter('post_row_actions',    'medialab_duplicate_post_link', 10, 2);
add_filter('page_row_actions',    'medialab_duplicate_post_link', 10, 2);

function medialab_duplicate_post_link(array $actions, WP_Post $post): array {
    if (!current_user_can('edit_posts')) { return $actions; }
    $url = wp_nonce_url(
        admin_url('admin-post.php?action=medialab_duplicate_post&post_id=' . $post->ID),
        'medialab_duplicate_' . $post->ID
    );
    $actions['duplicate'] = '<a href="' . esc_url($url) . '">' . __('Duplizieren', 'media-lab-core') . '</a>';
    return $actions;
}

add_action('admin_post_medialab_duplicate_post', function() {
    $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
    if (!$post_id || !check_admin_referer('medialab_duplicate_' . $post_id)) {
        wp_die('Fehler beim Duplizieren.');
    }
    if (!current_user_can('edit_post', $post_id)) { wp_die('Unauthorized'); }

    $post = get_post($post_id);
    if (!$post) { wp_die('Post nicht gefunden.'); }

    $new_id = wp_insert_post([
        'post_title'   => $post->post_title . ' (Kopie)',
        'post_content' => $post->post_content,
        'post_excerpt' => $post->post_excerpt,
        'post_status'  => 'draft',
        'post_type'    => $post->post_type,
        'post_author'  => get_current_user_id(),
        'menu_order'   => $post->menu_order,
        'post_parent'  => $post->post_parent,
    ]);

    if (is_wp_error($new_id)) { wp_die($new_id->get_error_message()); }

    // Post meta (inkl. ACF)
    $meta = get_post_meta($post_id);
    foreach ($meta as $key => $values) {
        if ($key === '_wp_old_slug') continue;
        foreach ($values as $value) {
            add_post_meta($new_id, $key, maybe_unserialize($value));
        }
    }

    // Taxonomien
    $taxonomies = get_object_taxonomies($post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        if (!is_wp_error($terms)) {
            wp_set_object_terms($new_id, $terms, $taxonomy);
        }
    }

    // Featured Image
    $thumb_id = get_post_thumbnail_id($post_id);
    if ($thumb_id) { set_post_thumbnail($new_id, $thumb_id); }

    wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new_id));
    exit;
});

// ── Terms ──────────────────────────────────────────────────────────────────

add_filter('tag_row_actions', 'medialab_duplicate_term_link', 10, 2);

function medialab_duplicate_term_link(array $actions, WP_Term $term): array {
    if (!current_user_can('manage_categories')) { return $actions; }
    $url = wp_nonce_url(
        admin_url('admin-post.php?action=medialab_duplicate_term&term_id=' . $term->term_id . '&taxonomy=' . $term->taxonomy),
        'medialab_duplicate_term_' . $term->term_id
    );
    $actions['duplicate'] = '<a href="' . esc_url($url) . '">' . __('Duplizieren', 'media-lab-core') . '</a>';
    return $actions;
}

add_action('admin_post_medialab_duplicate_term', function() {
    $term_id  = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : '';
    if (!$term_id || !$taxonomy || !check_admin_referer('medialab_duplicate_term_' . $term_id)) {
        wp_die('Fehler.');
    }

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) { wp_die('Term nicht gefunden.'); }

    $new = wp_insert_term($term->name . ' (Kopie)', $taxonomy, [
        'description' => $term->description,
        'parent'      => $term->parent,
        'slug'        => $term->slug . '-kopie',
    ]);

    if (!is_wp_error($new)) {
        $meta = get_term_meta($term_id);
        foreach ($meta as $key => $values) {
            foreach ($values as $value) {
                add_term_meta($new['term_id'], $key, maybe_unserialize($value));
            }
        }
    }

    wp_safe_redirect(admin_url('edit-tags.php?taxonomy=' . $taxonomy));
    exit;
});
