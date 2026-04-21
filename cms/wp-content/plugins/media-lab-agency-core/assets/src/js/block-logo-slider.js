/**
 * Logo-Slider Block – Swiper Initialisierung
 *
 * Fixes:
 *   ✅ WCAG 2.2.2: Autoplay pausiert bei Tastaturfokus (focusin/focusout)
 *
 * @since 1.6.0 / WCAG-Patch
 */

( function () {
    'use strict';

    function initLogoSliders() {
        document.querySelectorAll( '.ml-logo-slider__swiper' ).forEach( function ( el ) {
            let config = {};

            try {
                config = JSON.parse( el.dataset.swiper || '{}' );
            } catch ( e ) {
                console.warn( 'Logo-Slider: Ungültige Swiper-Konfiguration', e );
            }

            if ( config.autoplay && config.autoplay.delay === 0 ) {
                config.speed = config.speed || 3000;
                config.loop  = true;
            }

            const swiper = new Swiper( el, config );

            // ✅ WCAG 2.2.2: Pause, Stop, Hide
            // Autoplay stoppt wenn ein Element im Slider Tastaturfokus erhält
            if ( swiper.autoplay && config.autoplay ) {
                el.addEventListener( 'focusin', function () {
                    swiper.autoplay.stop();
                } );

                el.addEventListener( 'focusout', function ( e ) {
                    // Nur neu starten wenn Fokus den Slider komplett verlässt
                    if ( ! el.contains( e.relatedTarget ) ) {
                        swiper.autoplay.start();
                    }
                } );
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initLogoSliders );
    } else {
        initLogoSliders();
    }
} )();
