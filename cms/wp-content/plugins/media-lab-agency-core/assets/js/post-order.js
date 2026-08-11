/**
 * Drag & Drop Post Order
 *
 * Unterstützt zwei Modi:
 *   'post' – Admin-Listenansicht für Posts/CPTs (edit.php)
 *   'term' – Admin-Listenansicht für Taxonomy-Terms (edit-tags.php)
 *
 * Der Modus wird vom PHP via wp_localize_script als medialabPostOrder.mode übergeben.
 *
 * @since 1.16.0
 */
(function ($) {
	'use strict';

	$(function () {
		var $tbody = $('#the-list');
		if ( ! $tbody.length ) return;

		var mode = medialabPostOrder.mode; // 'post' | 'term'

		// ── Feedback-Notice ──────────────────────────────────────────────────

		var $notice = $('<div id="medialab-order-notice" style="display:none;"></div>');
		$('#wpbody-content').prepend($notice);

		function showNotice(msg, type) {
			$notice
				.attr('class', 'notice notice-' + type + ' is-dismissible')
				.html('<p>' + msg + '</p>')
				.show();

			// WP-Standard Dismiss-Handler aktivieren
			$(document).trigger('wp-updates-notice-added', [$notice]);
		}

		// ── Drag Handles einfügen ────────────────────────────────────────────
		//
		// Der Handle bekommt eine eigene, schmale <td> als erste Zelle jeder
		// Zeile – NICHT in eine bestehende Spalte hinein (z. B. column-thumb),
		// da dort bereits Inhalt (Thumbnail) sitzt und die Spaltenbreite fix
		// und schmal ist, was zu Überlappung/Umbruch führen würde. Kopf- und
		// Fußzeile bekommen passend eine leere Spalte vorangestellt, damit die
		// Spaltenanzahl übereinstimmt.

		$tbody.find('tr').each(function () {
			$(this).prepend(
				'<td class="medialab-drag-handle-cell">' +
				'<span class="medialab-drag-handle dashicons dashicons-menu" ' +
				'title="Ziehen zum Sortieren"></span>' +
				'</td>'
			);
		});

		$tbody.closest('table').find('> thead > tr, > tfoot > tr').each(function () {
			if ($(this).find('.medialab-drag-handle-th').length) return;
			$(this).prepend('<th class="manage-column medialab-drag-handle-th" scope="col"></th>');
		});

		// ── Sortable initialisieren ───────────────────────────────────────────

		$tbody.sortable({
			items:               'tr',
			axis:                'y',
			handle:              '.medialab-drag-handle',
			cursor:              'grabbing',
			placeholder:         'medialab-sort-placeholder',
			forcePlaceholderSize: true,

			helper: function (e, ui) {
				// Spaltenbreiten beim Drag beibehalten
				ui.children().each(function () {
					$(this).width($(this).width());
				});
				return ui;
			},

			start: function (e, ui) {
				ui.placeholder.html(
					'<td colspan="' + ui.item.find('td').length + '"></td>'
				);
			},

			stop: function () {
				saveOrder();
			}
		});


		// ── ID-Extraktion (Post oder Term) ────────────────────────────────────

		function getIds() {
			var ids = [];

			$tbody.find('tr').each(function () {
				var rowId = $(this).attr('id');
				if ( ! rowId ) return;

				var parsed;
				if ( mode === 'term' ) {
					// WP Term-Listenansicht: id="tag-{term_id}"
					parsed = parseInt(rowId.replace('tag-', ''), 10);
				} else {
					// WP Post-Listenansicht: id="post-{post_id}"
					parsed = parseInt(rowId.replace('post-', ''), 10);
				}

				if ( ! isNaN(parsed) && parsed > 0 ) {
					ids.push(parsed);
				}
			});

			return ids;
		}

		// ── AJAX-Speicherung ──────────────────────────────────────────────────

		function saveOrder() {
			showNotice(medialabPostOrder.i18n.saving, 'info');

			var ids  = getIds();
			var data = {
				nonce: medialabPostOrder.nonce,
				order: ids,
			};

			if ( mode === 'term' ) {
				data.action   = 'medialab_update_term_order';
				data.taxonomy = medialabPostOrder.taxonomy;
			} else {
				data.action    = 'medialab_update_post_order';
				data.post_type = medialabPostOrder.postType;
			}

			$.post(medialabPostOrder.ajaxUrl, data, function (response) {
				if ( response.success ) {
					showNotice(medialabPostOrder.i18n.saved, 'success');
				} else {
					showNotice(medialabPostOrder.i18n.error, 'error');
				}
			}).fail(function () {
				showNotice(medialabPostOrder.i18n.error, 'error');
			});
		}
	});

}(jQuery));
