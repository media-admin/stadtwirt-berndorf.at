/**
 * SMTP Test-Mail – Admin Script
 * Nonce wird sicher via wp_localize_script übergeben (F-06).
 */
jQuery( function ( $ ) {
    var cfg = window.medialabSmtp || {};

    $( document ).on( 'click', '#medialab-smtp-test', function () {
        var to  = $( '#medialab-smtp-test-to' ).val() || cfg.defaultEmail || '';
        var $r  = $( '#medialab-smtp-test-result' ).text( 'Sende…' ).css( 'color', '#888' );

        $.post(
            cfg.ajaxurl,
            {
                action : 'medialab_send_test_mail',
                nonce  : cfg.nonce,
                to     : to,
            },
            function ( res ) {
                $r.text( res.success ? res.data : 'Fehler: ' + res.data )
                  .css( 'color', res.success ? '#00a32a' : '#d63638' );
            }
        );
    } );
} );
