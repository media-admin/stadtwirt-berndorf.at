<?php
/**
 * Drag & Drop Post Order
 * Ermöglicht Sortierung aller Post Types und Taxonomien via Drag & Drop
 * in der Admin-Listenansicht. Speichert Reihenfolge in menu_order.
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 */
if (!defined('ABSPATH')) { exit; }

// menu_order für alle Post Types aktivieren
add_action('init', function() {
    $post_types = get_post_types(['show_ui' => true], 'names');
    foreach ($post_types as $post_type) {
        if ($post_type === 'attachment') continue;
        add_post_type_support($post_type, 'page-attributes');
    }
});

// Nach menu_order sortieren wenn kein anderer Order gesetzt
add_action('pre_get_posts', function(WP_Query $query) {
    if (is_admin() || !$query->is_main_query()) return;
    if ($query->get('orderby') === '') {
        $query->set('orderby', 'menu_order');
        $query->set('order', 'ASC');
    }
});

// AJAX: Reihenfolge speichern
add_action('wp_ajax_medialab_save_post_order', function() {
    check_ajax_referer('medialab_post_order', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Unauthorized');
    }

    $order = isset($_POST['order']) ? (array)$_POST['order'] : [];
    foreach ($order as $menu_order => $post_id) {
        wp_update_post([
            'ID'         => (int)$post_id,
            'menu_order' => (int)$menu_order,
        ]);
    }
    wp_send_json_success();
});

// Admin: Drag & Drop Script in Listenansicht einbinden
add_action('admin_enqueue_scripts', function(string $hook) {
    if (!in_array($hook, ['edit.php', 'edit-tags.php'])) return;

    wp_enqueue_script('jquery-ui-sortable');
    wp_add_inline_script('jquery-ui-sortable', '
        jQuery(function($) {
            var tbody = $("#the-list");
            if (!tbody.length) return;
            tbody.sortable({
                items: "tr",
                axis: "y",
                helper: function(e, tr) {
                    tr.children().each(function() {
                        $(this).width($(this).width());
                    });
                    return tr;
                },
                stop: function() {
                    var order = {};
                    tbody.find("tr").each(function(i) {
                        var id = $(this).attr("id").replace("post-","");
                        order[i] = id;
                    });
                    $.post(ajaxurl, {
                        action: "medialab_save_post_order",
                        nonce: "' . wp_create_nonce('medialab_post_order') . '",
                        order: order
                    });
                }
            });
            tbody.find("tr").css("cursor", "grab");
        });
    ');

    wp_add_inline_style('list-tables', '#the-list tr:hover { background: #f0f6fc; }');
});
