/**
 * Drag & Drop Order – Posts, Pages, CPTs & Taxonomy Terms
 */
(function ($) {
    'use strict';

    $(function () {
        var $tbody = $('#the-list');
        if (!$tbody.length) return;

        var cfg  = window.medialabPostOrder || {};
        var mode = cfg.mode || 'post'; // 'post' oder 'term'

        var $notice = $('<div id="medialab-order-notice" style="display:none;"></div>');
        $('#wpbody-content').prepend($notice);

        function showNotice(msg, type) {
            $notice
                .attr('class', 'notice notice-' + type + ' is-dismissible')
                .html('<p>' + msg + '</p>')
                .show();
        }

        $tbody.sortable({
            items: 'tr',
            axis: 'y',
            handle: '.medialab-drag-handle',
            placeholder: 'medialab-sort-placeholder',
            forcePlaceholderSize: true,
            opacity: 0.8,
            cursor: 'grabbing',

            start: function (e, ui) {
                ui.placeholder.height(ui.item.height());
                ui.item.find('td, th').each(function () {
                    $(this).width($(this).width());
                });
            },

            stop: function () {
                saveOrder();
            }
        });

        // Handle einfügen – Posts: td.column-title, Terms: td.name
        $tbody.find('tr').each(function () {
            var $titleCol = $(this).find('td.column-title, td.column-name, td.name').first();
            if (!$titleCol.length) $titleCol = $(this).find('td').first();

            var $strong = $titleCol.find('strong').first();
            var $handle = $('<span class="medialab-drag-handle" title="Ziehen zum Sortieren">⠿</span>');

            if ($strong.length) {
                $strong.before($handle);
            } else {
                $titleCol.prepend($handle);
            }
        });

        function saveOrder() {
            showNotice(cfg.i18n.saving, 'info');

            var order = [];
            $tbody.find('tr').each(function () {
                var id = $(this).attr('id'); // post-123 oder tag-123
                if (id) {
                    var numId = parseInt(id.replace(/^[a-z]+-/, ''), 10);
                    if (numId) order.push(numId);
                }
            });

            var action = mode === 'term'
                ? 'medialab_update_term_order'
                : 'medialab_update_post_order';

            $.ajax({
                url: cfg.ajaxUrl,
                type: 'POST',
                data: {
                    action:    action,
                    nonce:     cfg.nonce,
                    order:     order,
                    post_type: cfg.postType  || '',
                    taxonomy:  cfg.taxonomy  || '',
                },
                success: function (res) {
                    showNotice(
                        res.success ? cfg.i18n.saved : cfg.i18n.error,
                        res.success ? 'success' : 'error'
                    );
                },
                error: function () {
                    showNotice(cfg.i18n.error, 'error');
                }
            });
        }
    });

}(jQuery));
