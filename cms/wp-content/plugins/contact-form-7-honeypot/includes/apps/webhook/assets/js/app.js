( function( $ ) {
    'use strict';

    $( document ).on( 'wpcf7mailsent', function( event ) {
        var form = event.detail.contactForm;
        var formId = form ? form.id : event.detail.contactFormId || 0;

        // Fetch settings and then send to webhook
        $.post(
            cf7appsWebhook.ajaxurl,
            {
                action: 'cf7apps_fetch_webhook_settings',
                formId: formId
            },
            function( response ) {
                if ( response && response.success && response.data ) {
                    var settings = response.data;
                    if ( settings.is_enabled ) {
                        var url = settings.webhook_url || '';
                        if ( url ) {
                            var method = settings.method || 'POST';
                            var payloadTemplate = settings.payload || '{"form_id":"{{form_id}}","fields":{{fields}}}';

                            var fields = {};
                            if ( event.detail.inputs && event.detail.inputs.length ) {
                                event.detail.inputs.forEach( function( input ) {
                                    fields[ input.name ] = input.value;
                                } );
                            }

                            var payload = payloadTemplate.replace( '{{form_id}}', formId ).replace( '{{fields}}', JSON.stringify( fields ) );

                            try {
                                $.ajax( {
                                    url: url,
                                    method: method,
                                    data: payload,
                                    contentType: 'application/json',
                                } );
                            } catch ( e ) {
                                // swallow errors
                            }
                        }
                    }
                }
            }
        );
    } );

} )( jQuery );
