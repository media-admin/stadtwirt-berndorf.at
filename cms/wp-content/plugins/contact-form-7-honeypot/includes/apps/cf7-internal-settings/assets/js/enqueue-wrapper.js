( function( $ ) {
//     // Prevent false "Changes you made may not be saved" warnings
//     // This runs immediately when the script loads, before React Router initializes
//     // The issue: React Router's HashRouter blocks first navigation attempt on CF7 edit page
    ( function() {
        let isFirstNavigation = true;
        
        const preventFirstWarning = function( e ) {
            // Only prevent the warning on the FIRST navigation attempt
            // This matches the user's experience - warning only on first click
            if ( isFirstNavigation ) {
                isFirstNavigation = false;
                e.stopImmediatePropagation();
                e.stopPropagation();
                e.returnValue = '';
                return '';
            }
            // For subsequent navigations, allow normal behavior
        };
        
        // Use capture phase to intercept BEFORE React Router's handlers
        window.addEventListener( 'beforeunload', preventFirstWarning, true );
    } )();

    $( '#contact-form-editor' ).append(
        `<div id="cf7apps-root" class="cf7apps-internal-settings"></div>`
    );

    if ( '1' === cf7appsWrapperObjects.cf7appsRedirectionEnabled || '1' === cf7appsWrapperObjects.cf7appsWebhookEnabled ) {
        $( '#form-panel' ).css( 'position', 'relative' ).append(
            `<a  id="cf7apps-target-btn" style="position:absolute; top: 20px;right: 20px;padding: 0 20px;" href="#cf7apps-root" class="components-button cf7apps-btn tertiary-primary">CF7 Apps Settings</a>`
        );

        $( document ).on( 'click', '#cf7apps-target-btn', function( e ) {
            e.preventDefault();

            // target element
            const target = $( $( this ).attr( 'href' ) );

            if ( target.length ) {
                // scroll to the target element
                $( 'html, body' ).animate(
                    {
                        scrollTop: target.offset().top - 100,
                    },
                    'fast'
                );
            }
        } );
    }
} )( jQuery );