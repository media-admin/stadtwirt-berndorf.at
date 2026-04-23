/* global mltAdmin, wp */
(function ($) {
    'use strict';

    // ── Analytics Toggle ────────────────────────────────────────────────────

    const $analyticsCheck = $('#mlt_analytics_enabled');
    const $analyticsFields = $('.mlt-analytics-fields');

    $analyticsCheck.on('change', function () {
        $analyticsFields.toggleClass('mlt-hidden', !this.checked);
    });

    // ── Provider Radio (GA4 / GTM) ──────────────────────────────────────────

    function syncProvider() {
        const val = $('input[name="mlt_analytics_provider"]:checked').val();
        const isGtm = val === 'gtm';

        $('#mlt_analytics_id').attr('placeholder', isGtm ? 'GTM-XXXXXXX' : 'G-XXXXXXXXXX');
        $('.mlt-label-ga4, .mlt-hint-ga4').toggleClass('mlt-hidden', isGtm);
        $('.mlt-label-gtm, .mlt-hint-gtm').toggleClass('mlt-hidden', !isGtm);
    }

    $('input[name="mlt_analytics_provider"]').on('change', syncProvider);
    syncProvider(); // initial

    // ── OG-Bild-Upload ──────────────────────────────────────────────────────

    let mediaFrame;

    $('#mlt_og_image_btn').on('click', function (e) {
        e.preventDefault();

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: 'Open Graph Fallback-Bild auswählen',
            button: { text: 'Bild verwenden' },
            multiple: false,
            library: { type: 'image' },
        });

        mediaFrame.on('select', function () {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $('#mlt_og_image_id').val(attachment.id);

            const url = attachment.sizes?.medium?.url || attachment.url;
            let $preview = $('.mlt-og-preview');

            if (!$preview.length) {
                $preview = $('<img class="mlt-og-preview" alt="" />');
                $('.mlt-media-field').prepend($preview);
            }

            $preview.attr('src', url);
            $('#mlt_og_image_btn').text('Bild ändern');

            if (!$('#mlt_og_image_remove').length) {
                $('#mlt_og_image_btn').after(
                    '<button type="button" class="button mlt-btn-remove" id="mlt_og_image_remove">Entfernen</button>'
                );
                bindRemoveBtn();
            }
        });

        mediaFrame.open();
    });

    function bindRemoveBtn() {
        $(document).on('click', '#mlt_og_image_remove', function (e) {
            e.preventDefault();
            $('#mlt_og_image_id').val('');
            $('.mlt-og-preview').remove();
            $('#mlt_og_image_btn').text('Bild auswählen');
            $(this).remove();
        });
    }

    bindRemoveBtn();

    // ── Test-Mail ────────────────────────────────────────────────────────────

    $('#mlt_send_test_mail').on('click', function () {
        const $btn    = $(this);
        const $result = $('#mlt_test_mail_result');
        const email   = $('#mlt_report_email').val().trim();

        if (!email) {
            $result.attr('class', 'mlt-test-mail__result error').text('Bitte zuerst eine E-Mail-Adresse eintragen.');
            return;
        }

        $btn.prop('disabled', true);
        $result.attr('class', 'mlt-test-mail__result loading').text('Sende …');

        $.post(mltAdmin.ajaxUrl, {
            action: 'mlt_test_mail',
            nonce:  mltAdmin.nonce,
            email:  email,
        })
        .done(function (res) {
            if (res.success) {
                $result.attr('class', 'mlt-test-mail__result success').text('✓ ' + res.data);
            } else {
                $result.attr('class', 'mlt-test-mail__result error').text('✗ ' + res.data);
            }
        })
        .fail(function () {
            $result.attr('class', 'mlt-test-mail__result error').text('✗ Verbindungsfehler.');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
