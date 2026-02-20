( function( $ ) {

    $( document ).ready( function() {
        document.addEventListener( 'wpcf7mailsent', function( e ) {
            let formId = e.detail.contactFormId;

            $.post( cf7appsRedirection.ajaxurl, { action: 'cf7apps_fetch_settings', formId }, function( response ) {
                if ( response.success ) {
                    const { redirectTo, newTab, isEnabled } = response.data;

                    if ( isEnabled ) {
                        if ( newTab ) {
                            window.open( redirectTo, '_blank' );
                        } else {
                            window.location.href = redirectTo;
                        }
                    }
                }
            } );
        } );
    } );
} )( jQuery );