/**
 * Before / After Block – Drag-Slider
 * Mouse, Touch und Keyboard (← →, Tab). Keine externe Library.
 * @since 1.11.0
 */
( function () {
    'use strict';

    document.querySelectorAll( '.ml-ba__container' ).forEach( function ( container ) {
        const before  = container.querySelector( '.ml-ba__before' );
        const handle  = container.querySelector( '.ml-ba__handle' );
        if ( ! before || ! handle ) return;

        let isDragging = false;

        // ── Position setzen ────────────────────────────────────────────────────
        function setPosition( clientX ) {
            const rect  = container.getBoundingClientRect();
            const raw   = ( clientX - rect.left ) / rect.width;
            const pct   = Math.max( 0, Math.min( 1, raw ) );
            const pctPc = ( pct * 100 ).toFixed( 2 );

            before.style.width  = pctPc + '%';
            handle.style.left   = pctPc + '%';
            handle.setAttribute( 'aria-valuenow', Math.round( pct * 100 ) );
        }

        // ── Mouse ──────────────────────────────────────────────────────────────
        handle.addEventListener( 'mousedown', function ( e ) {
            e.preventDefault();
            isDragging = true;
            handle.classList.add( 'is-drag' );
        } );

        document.addEventListener( 'mousemove', function ( e ) {
            if ( ! isDragging ) return;
            setPosition( e.clientX );
        } );

        document.addEventListener( 'mouseup', function () {
            if ( ! isDragging ) return;
            isDragging = false;
            handle.classList.remove( 'is-drag' );
        } );

        // Klick direkt auf Container (nicht Handle)
        container.addEventListener( 'click', function ( e ) {
            if ( e.target === handle || handle.contains( e.target ) ) return;
            setPosition( e.clientX );
        } );

        // ── Touch ──────────────────────────────────────────────────────────────
        handle.addEventListener( 'touchstart', function ( e ) {
            isDragging = true;
            handle.classList.add( 'is-drag' );
        }, { passive: true } );

        document.addEventListener( 'touchmove', function ( e ) {
            if ( ! isDragging ) return;
            setPosition( e.touches[ 0 ].clientX );
        }, { passive: true } );

        document.addEventListener( 'touchend', function () {
            isDragging = false;
            handle.classList.remove( 'is-drag' );
        } );

        // ── Keyboard (← →) ────────────────────────────────────────────────────
        handle.addEventListener( 'keydown', function ( e ) {
            const current = parseFloat( handle.style.left ) || 50;
            const step    = e.shiftKey ? 10 : 1; // Shift = große Schritte

            if ( e.key === 'ArrowLeft' ) {
                e.preventDefault();
                const pct  = Math.max( 0, current - step );
                before.style.width = pct + '%';
                handle.style.left  = pct + '%';
                handle.setAttribute( 'aria-valuenow', Math.round( pct ) );
            }
            if ( e.key === 'ArrowRight' ) {
                e.preventDefault();
                const pct  = Math.min( 100, current + step );
                before.style.width = pct + '%';
                handle.style.left  = pct + '%';
                handle.setAttribute( 'aria-valuenow', Math.round( pct ) );
            }
        } );
    } );
} )();
