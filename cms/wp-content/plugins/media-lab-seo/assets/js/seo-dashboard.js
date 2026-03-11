/**
 * Media Lab SEO Dashboard – Frontend JS
 *
 * - Cache-Flush via AJAX
 * - Test-Report senden via AJAX
 */
(function ($) {
    'use strict';

    // ── Cache leeren ─────────────────────────────────────────────────────────

    $('#ml-flush-cache').on('click', function () {
        const $btn = $(this);
        $btn.prop('disabled', true).text('Wird geleert…');

        $.post(medialabGSC.ajaxUrl, {
            action: 'medialab_gsc_flush_cache',
            nonce:  medialabGSC.nonce,
        })
        .done(function (res) {
            if (res.success) {
                $btn.text('✅ Cache geleert');
                setTimeout(() => location.reload(), 800);
            } else {
                $btn.prop('disabled', false).text('🔄 Cache leeren');
                alert('Fehler: ' + (res.data?.message || 'Unbekannter Fehler'));
            }
        })
        .fail(function () {
            $btn.prop('disabled', false).text('🔄 Cache leeren');
            alert('Verbindungsfehler. Bitte erneut versuchen.');
        });
    });

    // ── Test-Report senden ───────────────────────────────────────────────────

    $('#ml-send-test-report').on('click', function () {
        const $btn = $(this);
        $btn.prop('disabled', true).text('⏳ Wird gesendet…');

        $.post(medialabGSC.ajaxUrl, {
            action: 'medialab_send_test_report',
            nonce:  medialabGSC.nonce,
        })
        .done(function (res) {
            if (res.success) {
                $btn.text('✅ Report gesendet!');
                setTimeout(() => {
                    $btn.prop('disabled', false).text('📧 Test-Report jetzt senden');
                }, 3000);
            } else {
                $btn.prop('disabled', false).text('📧 Test-Report jetzt senden');
                alert('Fehler: ' + (res.data?.message || 'Unbekannter Fehler'));
            }
        })
        .fail(function () {
            $btn.prop('disabled', false).text('📧 Test-Report jetzt senden');
            alert('Verbindungsfehler. Bitte erneut versuchen.');
        });
    });

    // ── Matomo-Verbindungstest ───────────────────────────────────────────────

    $('#ml-test-matomo').on('click', function () {
        const $btn    = $(this);
        const $result = $('#ml-matomo-test-result');
        $btn.prop('disabled', true).text('⏳ Teste…');
        $result.text('');

        $.post(medialabGSC.ajaxUrl, {
            action: 'medialab_test_matomo',
            nonce:  medialabGSC.nonce,
        })
        .done(function (res) {
            $btn.prop('disabled', false).text('🔌 Verbindung testen');
            if (res.success) {
                $result.css('color', '#10b981').text('✅ ' + res.data.message);
            } else {
                $result.css('color', '#ef4444').text('❌ ' + (res.data?.message || 'Fehler'));
            }
        })
        .fail(function () {
            $btn.prop('disabled', false).text('🔌 Verbindung testen');
            $result.css('color', '#ef4444').text('❌ Verbindungsfehler');
        });
    });

})(jQuery);
