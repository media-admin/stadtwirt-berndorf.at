(function ($) {
    'use strict';

    var cfg = window.medialabRedirects || {};

    $(function () {

        // ── Modal: Redirect aus 404 erstellen ───────────────────
        var $modal      = $('#medialab-redirect-modal');
        var $source     = $('#modal-source');
        var $dest       = $('#modal-destination');
        var $type       = $('#modal-type');
        var $logId      = $('#modal-log-id');

        $(document).on('click', '.medialab-create-redirect', function () {
            $source.val($(this).data('url'));
            $dest.val('');
            $logId.val($(this).data('log-id'));
            $modal.show();
            $dest.focus();
        });

        $('#modal-cancel, .medialab-modal-backdrop').on('click', function () {
            $modal.hide();
        });

        $('#modal-save').on('click', function () {
            var destination = $dest.val().trim();
            if (!destination) {
                $dest.focus();
                return;
            }

            var $btn = $(this).prop('disabled', true).text('Speichern...');

            $.ajax({
                url: cfg.ajaxUrl,
                type: 'POST',
                data: {
                    action:      'medialab_404_to_redirect',
                    nonce:       cfg.nonce,
                    source:      $source.val(),
                    destination: destination,
                    type:        $type.val(),
                    log_id:      $logId.val(),
                },
                success: function (res) {
                    if (res.success) {
                        // Zeile aus 404-Log ausblenden
                        var logId = $logId.val();
                        if (logId) {
                            $('#log-row-' + logId).fadeOut(300, function () { $(this).remove(); });
                        }
                        $modal.hide();
                    } else {
                        alert('Fehler beim Speichern');
                        $btn.prop('disabled', false).text('Redirect speichern');
                    }
                },
                error: function () {
                    alert('Verbindungsfehler');
                    $btn.prop('disabled', false).text('Redirect speichern');
                }
            });
        });

        // ── 404-Log leeren ───────────────────────────────────────
        $('#medialab-clear-404').on('click', function () {
            if (!confirm('Den gesamten 404-Log wirklich leeren?')) return;

            var $btn = $(this).prop('disabled', true).text('Leeren...');

            $.ajax({
                url: cfg.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'medialab_clear_404_log',
                    nonce:  cfg.nonce,
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    alert('Fehler');
                    $btn.prop('disabled', false).text('404-Log leeren');
                }
            });
        });

        // ── ESC schließt Modal ───────────────────────────────────
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') $modal.hide();
        });
    });

}(jQuery));
