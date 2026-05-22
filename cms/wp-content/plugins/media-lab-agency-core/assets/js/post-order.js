/**
 * Drag & Drop + Arrow Button Post Order
 *
 * Macht die WP-Admin-Post-Tabelle sortierbar.
 * Methode 1: Drag & Drop via jQuery UI Sortable
 * Methode 2: ▲ / ▼ Pfeil-Buttons (Tastatur- und Touch-freundlich)
 *
 * Reihenfolge wird per AJAX in menu_order gespeichert.
 *
 * @package  media-lab-agency-core
 */
(function ($) {
    'use strict';

    // ── Initialisierung ───────────────────────────────────────────────────────

    $(function () {
        var $tbody = $('#the-list');
        if ( ! $tbody.length ) return;

        // Status-Notice über der Tabelle
        var $notice = $('<div id="medialab-order-notice" role="status" aria-live="polite"></div>');
        $('.wp-list-table').before( $notice );

        var saveTimer = null;

        // ── Zeilen aufbereiten ────────────────────────────────────────────────

        /**
         * Drag-Handle + Pfeil-Buttons in die erste Spalte jeder Zeile einfügen.
         * Wird initial und nach Listenänderungen (z.B. Quick-Edit) aufgerufen.
         */
        function prepareRows() {
            $tbody.find('tr').each(function () {
                var $row = $(this);

                // Nicht doppelt einfügen
                if ( $row.find('.ml-order-controls').length ) return;

                var $firstTd = $row.find('td').first();
                if ( ! $firstTd.length ) return;

                var controls =
                    '<span class="ml-order-controls">' +
                        '<span class="ml-drag-handle" title="' + medialabPostOrder.i18n.drag + '" aria-hidden="true">⠿</span>' +
                        '<span class="ml-arrow-btns">' +
                            '<button type="button" class="ml-btn-up button button-small" aria-label="' + medialabPostOrder.i18n.up + '">▲</button>' +
                            '<button type="button" class="ml-btn-down button button-small" aria-label="' + medialabPostOrder.i18n.down + '">▼</button>' +
                        '</span>' +
                    '</span>';

                $firstTd.prepend( controls );
            });

            updateArrowStates();
        }

        // ── Pfeil-Button Zustände ─────────────────────────────────────────────

        /**
         * ▲ beim ersten Element, ▼ beim letzten Element deaktivieren.
         */
        function updateArrowStates() {
            var $rows = $tbody.find('tr');
            var total = $rows.length;

            $rows.each(function (index) {
                var $row = $(this);
                $row.find('.ml-btn-up').prop('disabled', index === 0);
                $row.find('.ml-btn-down').prop('disabled', index === total - 1);
            });
        }

        // ── Status-Meldung ────────────────────────────────────────────────────

        /**
         * @param {string} message
         * @param {string} type  info | success | error
         */
        function showNotice( message, type ) {
            clearTimeout( saveTimer );

            $notice
                .attr('class', 'notice notice-' + type + ' is-dismissible')
                .html('<p>' + message + '</p>')
                .show();

            if ( type !== 'info' ) {
                saveTimer = setTimeout(function () {
                    $notice.fadeOut(300);
                }, 2500);
            }
        }

        // ── AJAX Speichern ────────────────────────────────────────────────────

        /**
         * Aktuelle Reihenfolge der Zeilen als menu_order per AJAX speichern.
         */
        function saveOrder() {
            showNotice( medialabPostOrder.i18n.saving, 'info' );

            var order = [];
            $tbody.find('tr').each(function () {
                var rawId  = $(this).attr('id') || '';
                var postId = parseInt( rawId.replace('post-', ''), 10 );
                if ( postId ) order.push( postId );
            });

            if ( ! order.length ) return;

            $.ajax({
                url:  medialabPostOrder.ajaxUrl,
                type: 'POST',
                data: {
                    action:    'medialab_update_post_order',
                    nonce:     medialabPostOrder.nonce,
                    order:     order,
                    post_type: medialabPostOrder.postType,
                },
                success: function (res) {
                    if ( res.success ) {
                        showNotice( medialabPostOrder.i18n.saved, 'success' );
                    } else {
                        showNotice( medialabPostOrder.i18n.error, 'error' );
                    }
                },
                error: function () {
                    showNotice( medialabPostOrder.i18n.network, 'error' );
                }
            });
        }

        // ── Drag & Drop ───────────────────────────────────────────────────────

        $tbody.sortable({
            items:               'tr',
            axis:                'y',
            handle:              '.ml-drag-handle',
            placeholder:         'ml-sort-placeholder',
            forcePlaceholderSize: true,
            opacity:             0.85,
            cursor:              'grabbing',

            start: function (e, ui) {
                // Placeholder-Höhe angleichen
                ui.placeholder.height( ui.item.outerHeight() );
                // Spaltenbreiten während Drag fixieren
                ui.item.find('td, th').each(function () {
                    $(this).css('width', $(this).outerWidth() + 'px');
                });
            },

            stop: function (e, ui) {
                // Inline-Breiten nach Drag wieder entfernen
                ui.item.find('td, th').css('width', '');
                updateArrowStates();
                saveOrder();
            }
        }).disableSelection();

        // ── Pfeil-Buttons ─────────────────────────────────────────────────────

        $tbody.on('click', '.ml-btn-up, .ml-btn-down', function () {
            var $btn  = $(this);
            var $row  = $btn.closest('tr');
            var isUp  = $btn.hasClass('ml-btn-up');

            if ( isUp ) {
                var $prev = $row.prev('tr');
                if ( $prev.length ) {
                    $row.insertBefore( $prev );
                }
            } else {
                var $next = $row.next('tr');
                if ( $next.length ) {
                    $row.insertAfter( $next );
                }
            }

            updateArrowStates();

            // Fokus nach Verschieben auf denselben Button zurücksetzen
            // (ermöglicht mehrfaches Drücken ohne Mauseinsatz)
            $row.find( isUp ? '.ml-btn-up' : '.ml-btn-down' ).focus();

            saveOrder();
        });

        // ── Start ─────────────────────────────────────────────────────────────

        prepareRows();

        // Quick-Edit schließt die Zeile neu → Controls neu einfügen
        $(document).on('ajaxComplete', function () {
            prepareRows();
        });

    });

}(jQuery));
