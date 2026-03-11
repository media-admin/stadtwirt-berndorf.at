/**
 * SEO Meta Box JS
 * - Zeichenzähler mit Farbindikator
 * - Live Google-Vorschau
 * - Medien-Upload für OG Image
 */
(function ($) {
    'use strict';

    $(function () {

        // ── Zeichenzähler + Live-Vorschau ────────────────────────

        var TITLE_MAX = 60;
        var DESC_MAX  = 160;

        function updateCounter($input, $num, max) {
            var len = $input.val().length;
            $num.text(len);
            var $wrap = $num.closest('.mlseo-counter');
            $wrap.removeClass('mlseo-counter--ok mlseo-counter--warn mlseo-counter--over');
            if (len === 0)        $wrap.addClass('');
            else if (len <= max)  $wrap.addClass(len >= max * 0.75 ? 'mlseo-counter--warn' : 'mlseo-counter--ok');
            else                  $wrap.addClass('mlseo-counter--over');
        }

        var $titleInput = $('#mlseo_title');
        var $descInput  = $('#mlseo_description');
        var $titleNum   = $('#mlseo-title-num');
        var $descNum    = $('#mlseo-desc-num');

        $titleInput.on('input', function () {
            updateCounter($titleInput, $titleNum, TITLE_MAX);
            var val = $(this).val().trim();
            $('#mlseo-prev-title').text(val || $(this).attr('placeholder') || '');
        });

        $descInput.on('input', function () {
            updateCounter($descInput, $descNum, DESC_MAX);
            var val = $(this).val().trim();
            $('#mlseo-prev-desc').text(val || '');
        });

        // Initial
        updateCounter($titleInput, $titleNum, TITLE_MAX);
        updateCounter($descInput, $descNum, DESC_MAX);

        // noindex Warnung
        $('#mlseo_noindex').on('change', function () {
            $(this).closest('.mlseo-check').toggleClass('mlseo-check--warning', this.checked);
        });

        // ── OG Image Medien-Picker ───────────────────────────────

        var mediaFrame;

        $('#mlseo-og-select').on('click', function (e) {
            e.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title:    'OG Image auswählen',
                button:   { text: 'Auswählen' },
                multiple: false,
                library:  { type: 'image' },
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                $('#mlseo_og_image').val(attachment.id);
                var src = attachment.sizes && attachment.sizes.medium
                    ? attachment.sizes.medium.url
                    : attachment.url;
                $('#mlseo-og-preview').html('<img src="' + src + '" alt="">');
                $('#mlseo-og-remove').removeClass('hidden');
            });

            mediaFrame.open();
        });

        $('#mlseo-og-remove').on('click', function (e) {
            e.preventDefault();
            $('#mlseo_og_image').val('');
            $('#mlseo-og-preview').html('<span class="mlseo-image-placeholder">Kein Bild ausgewählt</span>');
            $(this).addClass('hidden');
        });

    });

}(jQuery));
