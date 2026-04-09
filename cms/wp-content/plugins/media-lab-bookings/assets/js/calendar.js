/**
 * Media Lab Bookings – Kalender JS
 */
( function ( $, cfg ) {
    'use strict';
    if ( ! cfg ) return;

    var $popup    = $( '#mlb-cal-popup' );
    var $content  = $popup.find( '.mlb-cal-popup__content' );
    var currentLocationId = new URLSearchParams( window.location.search ).get( 'mlb_filter_location' ) || 0;

    // Klick auf Zelle mit Buchungen
    $( document ).on( 'click', '.mlb-cal-cell--has-bookings', function () {
        var date = $( this ).data( 'date' );
        if ( ! date ) return;

        $content.html( '<p style="text-align:center;padding:20px">Lädt…</p>' );
        $popup.prop( 'hidden', false );

        $.post( cfg.ajaxUrl, {
            action      : 'mlb_calendar_day',
            nonce       : cfg.nonce,
            date        : date,
            location_id : currentLocationId,
        }, function ( res ) {
            if ( res.success ) {
                $content.html( res.data.html );
            } else {
                $content.html( '<p>Fehler beim Laden.</p>' );
            }
        } );
    } );

    // Popup schließen
    $( document ).on( 'click', '.mlb-cal-popup__close', function () {
        $popup.prop( 'hidden', true );
    } );

    // Klick außerhalb schließt Popup
    $popup.on( 'click', function ( e ) {
        if ( $( e.target ).is( $popup ) ) $popup.prop( 'hidden', true );
    } );

    // ESC schließt Popup
    $( document ).on( 'keydown', function ( e ) {
        if ( e.key === 'Escape' ) $popup.prop( 'hidden', true );
    } );

} )( jQuery, window.mlbCalendar || null );
