/**
 * Accordion Block – Frontend Interaktion
 *
 * Verwendet natives <details>/<summary> Pattern mit ARIA-Erweiterung.
 * Kein Framework, kein Build-Step erforderlich.
 *
 * @since 1.6.0
 */

( function () {
    'use strict';

    function initAccordions() {
        document.querySelectorAll( '.ml-block-accordion' ).forEach( function ( accordion ) {
            const allowMultiple = accordion.dataset.allowMultiple === 'true';
            const items = accordion.querySelectorAll( '.ml-accordion__item' );

            items.forEach( function ( item ) {
                const trigger = item.querySelector( '.ml-accordion__trigger' );
                const body    = item.querySelector( '.ml-accordion__body' );

                if ( ! trigger || ! body ) return;

                // ARIA
                const bodyId = 'ml-accordion-body-' + Math.random().toString( 36 ).slice( 2 );
                body.id = bodyId;
                trigger.setAttribute( 'aria-controls', bodyId );
                trigger.setAttribute( 'aria-expanded', item.hasAttribute( 'open' ) ? 'true' : 'false' );

                trigger.addEventListener( 'click', function () {
                    const isOpen = item.hasAttribute( 'open' );

                    // Andere schließen (wenn allowMultiple = false)
                    if ( ! allowMultiple ) {
                        items.forEach( function ( other ) {
                            if ( other !== item && other.hasAttribute( 'open' ) ) {
                                other.removeAttribute( 'open' );
                                const otherTrigger = other.querySelector( '.ml-accordion__trigger' );
                                if ( otherTrigger ) otherTrigger.setAttribute( 'aria-expanded', 'false' );
                            }
                        } );
                    }

                    if ( isOpen ) {
                        item.removeAttribute( 'open' );
                        trigger.setAttribute( 'aria-expanded', 'false' );
                    } else {
                        item.setAttribute( 'open', '' );
                        trigger.setAttribute( 'aria-expanded', 'true' );
                    }
                } );
            } );
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initAccordions );
    } else {
        initAccordions();
    }
} )();
