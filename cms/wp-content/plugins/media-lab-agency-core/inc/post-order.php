<?php
/**
 * Post & Term Order
 * Drag & Drop Sortierung für Posts (via menu_order) und Taxonomien (via term_meta).
 * Kein externes Plugin nötig – nutzt jQuery UI Sortable (in WP enthalten).
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 */
if (!defined('ABSPATH')) { exit; }

// =============================================================================
// POST ORDER
// menu_order für alle öffentlichen CPTs aktivieren
// =============================================================================

add_action('init', function() {
    $post_types = get_post_types(['show_ui' => true, 'public' => true], 'names');
    foreach ($post_types as $post_type) {
        if (in_array($post_type, ['attachment', 'page'])) continue;
        add_post_type_support($post_type, 'page-attributes');
    }
}, 20);

// Frontend: nach menu_order sortieren
add_action('pre_get_posts', function(WP_Query $query) {
    if (is_admin() || !$query->is_main_query()) return;
    if ($query->get('orderby') === '' && $query->get('post_type') !== 'post') {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
});

// AJAX: Post-Reihenfolge speichern
add_action('wp_ajax_medialab_save_post_order', function() {
    check_ajax_referer('medialab_post_order', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }
    $order = isset($_POST['order']) ? (array)$_POST['order'] : [];
    foreach ($order as $menu_order => $post_id) {
        wp_update_post(['ID' => (int)$post_id, 'menu_order' => (int)$menu_order]);
    }
    wp_send_json_success();
});

// =============================================================================
// TERM ORDER
// Reihenfolge in term_meta 'term_order' speichern
// =============================================================================

/**
 * Term-Reihenfolge auslesen – Hilfsfunktion für Templates.
 * Nutzung in get_terms(): 'orderby' => 'meta_value_num', 'meta_key' => 'term_order'
 * Oder: speisekarte_get_terms_ordered($taxonomy, $args)
 */
function medialab_get_terms_ordered(string $taxonomy, array $args = []): array {
    $defaults = [
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
        'meta_key'   => 'term_order',
        'meta_query' => [[
            'key'     => 'term_order',
            'compare' => 'EXISTS',
        ]],
    ];

    $terms = get_terms(wp_parse_args($args, $defaults));

    // Fallback: Terms ohne term_order hinten anhängen (alphabetisch)
    if (!is_wp_error($terms)) {
        $unordered = get_terms(array_merge(
            wp_parse_args($args, ['taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'name']),
            [
                'meta_query' => [[
                    'key'     => 'term_order',
                    'compare' => 'NOT EXISTS',
                ]],
                'exclude' => array_column(is_array($terms) ? $terms : [], 'term_id'),
            ]
        ));
        if (!is_wp_error($unordered) && !empty($unordered)) {
            $terms = array_merge(is_array($terms) ? $terms : [], $unordered);
        }
    }

    return is_array($terms) ? $terms : [];
}

// AJAX: Term-Reihenfolge speichern
add_action('wp_ajax_medialab_save_term_order', function() {
    check_ajax_referer('medialab_term_order', 'nonce');
    if (!current_user_can('manage_categories')) {
        wp_send_json_error('Unauthorized');
    }
    $order = isset($_POST['order']) ? (array)$_POST['order'] : [];
    foreach ($order as $position => $term_id) {
        update_term_meta((int)$term_id, 'term_order', (int)$position);
    }
    wp_send_json_success();
});

// =============================================================================
// ADMIN: Drag & Drop UI für Post-Listen und Term-Listen
// =============================================================================

add_action('admin_enqueue_scripts', function(string $hook) {
    if (!in_array($hook, ['edit.php', 'edit-tags.php'])) return;

    wp_enqueue_script('jquery-ui-sortable');

    $nonce_post = wp_create_nonce('medialab_post_order');
    $nonce_term = wp_create_nonce('medialab_term_order');

    wp_add_inline_script('jquery-ui-sortable', '
    jQuery(function($) {

        // ── POST LIST ───────────────────────────────────────────────────────
        var postList = $("#the-list");
        if (postList.length && postList.find("tr[id^=post-]").length) {
            postList.sortable({
                items: "tr",
                axis: "y",
                cursor: "grab",
                placeholder: "medialab-sort-placeholder",
                helper: function(e, tr) {
                    tr.children().each(function() { $(this).width($(this).width()); });
                    return tr;
                },
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                    ui.placeholder.html("<td colspan=\"20\"></td>");
                },
                stop: function() {
                    var order = {};
                    postList.find("tr[id^=post-]").each(function(i) {
                        var id = $(this).attr("id").replace("post-", "");
                        order[i] = id;
                    });
                    $.post(ajaxurl, {
                        action: "medialab_save_post_order",
                        nonce: "' . $nonce_post . '",
                        order: order
                    });
                }
            });
            postList.find("tr").css("cursor", "grab");
        }

        // ── TERM LIST ───────────────────────────────────────────────────────
        var termList = $("#the-list");
        if (termList.length && termList.find("tr[id^=tag-]").length) {
            termList.sortable({
                items: "tr",
                axis: "y",
                cursor: "grab",
                placeholder: "medialab-sort-placeholder",
                helper: function(e, tr) {
                    tr.children().each(function() { $(this).width($(this).width()); });
                    return tr;
                },
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.height());
                    ui.placeholder.html("<td colspan=\"20\"></td>");
                },
                stop: function() {
                    var order = {};
                    termList.find("tr[id^=tag-]").each(function(i) {
                        var id = $(this).attr("id").replace("tag-", "");
                        order[i] = id;
                    });
                    $.post(ajaxurl, {
                        action: "medialab_save_term_order",
                        nonce: "' . $nonce_term . '",
                        order: order
                    });
                }
            });
            termList.find("tr").css("cursor", "grab");
        }

    });
    ');

    wp_add_inline_style('list-tables', '
        .medialab-sort-placeholder td { background: #f0f6fc; }
        #the-list tr:hover { background: #f6f7f7; }
        #the-list tr { cursor: grab; }
        #the-list tr:active { cursor: grabbing; }
    ');
});
