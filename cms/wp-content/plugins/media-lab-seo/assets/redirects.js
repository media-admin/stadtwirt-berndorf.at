/* global mltRedirects, jQuery */
(function ($) {
    'use strict';

    // ── Redirect hinzufügen ─────────────────────────────────────────────────

    $('#mlt_redirect_add').on('click', function () {
        const src    = $('#mlt_src').val().trim();
        const dst    = $('#mlt_dst').val().trim();
        const type   = $('#mlt_type').val();
        const $result = $('.mlt-inline-result');

        if (!src || !dst) {
            $result.attr('class', 'mlt-inline-result error').text('Quelle und Ziel sind Pflichtfelder.');
            return;
        }

        $.post(mltRedirects.ajaxUrl, {
            action: 'mlt_redirect_save',
            nonce:  mltRedirects.nonce,
            source: src,
            target: dst,
            type:   type,
        })
        .done(function (res) {
            if (res.success) {
                $result.attr('class', 'mlt-inline-result success').text('✓ Redirect gespeichert.');
                $('#mlt_src, #mlt_dst').val('');
                setTimeout(() => location.reload(), 600);
            } else {
                $result.attr('class', 'mlt-inline-result error').text('✗ ' + res.data);
            }
        });
    });

    // ── Toggle ──────────────────────────────────────────────────────────────

    $(document).on('click', '.mlt-toggle-redirect', function () {
        const $btn = $(this);
        const id   = $btn.data('id');
        const $row = $('#mlt-redirect-' + id);

        $.post(mltRedirects.ajaxUrl, {
            action: 'mlt_redirect_toggle',
            nonce:  mltRedirects.nonce,
            id:     id,
        })
        .done(function (res) {
            if (res.success) {
                const active = res.data.active;
                $row.toggleClass('mlt-row-inactive', !active);
                $btn.text(active ? 'Deaktivieren' : 'Aktivieren');
            }
        });
    });

    // ── Löschen ─────────────────────────────────────────────────────────────

    $(document).on('click', '.mlt-delete-redirect', function () {
        if (!confirm('Redirect wirklich löschen?')) return;

        const id   = $(this).data('id');
        const $row = $('#mlt-redirect-' + id);

        $.post(mltRedirects.ajaxUrl, {
            action: 'mlt_redirect_delete',
            nonce:  mltRedirects.nonce,
            id:     id,
        })
        .done(function (res) {
            if (res.success) $row.fadeOut(300, function () { $(this).remove(); });
        });
    });

    // ── 404 → Redirect Modal ────────────────────────────────────────────────

    const $modal = $('#mlt-404-modal');

    $(document).on('click', '.mlt-404-to-redirect', function () {
        const $btn = $(this);
        $('#mlt-modal-src').text($btn.data('url'));
        $('#mlt-modal-404-id').val($btn.data('id'));
        $('#mlt-modal-dst').val('');
        $modal.css('display', 'flex');
        setTimeout(() => $('#mlt-modal-dst').focus(), 50);
    });

    $('#mlt-modal-cancel').on('click', function () {
        $modal.hide();
    });

    $('#mlt-modal-save').on('click', function () {
        const dst    = $('#mlt-modal-dst').val().trim();
        const type   = $('#mlt-modal-type').val();
        const src    = $('#mlt-modal-src').text();
        const log_id = $('#mlt-modal-404-id').val();

        if (!dst) {
            alert('Bitte Ziel-URL eingeben.');
            return;
        }

        $.post(mltRedirects.ajaxUrl, {
            action: 'mlt_404_to_redirect',
            nonce:  mltRedirects.nonce,
            source: src,
            target: dst,
            type:   type,
            log_id: log_id,
        })
        .done(function (res) {
            if (res.success) {
                $modal.hide();
                $('#mlt-404-' + log_id).fadeOut(300, function () { $(this).remove(); });
            } else {
                alert('Fehler: ' + res.data);
            }
        });
    });

    // Modal per Escape schließen
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') $modal.hide();
    });

})(jQuery);
