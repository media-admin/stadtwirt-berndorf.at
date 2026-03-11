/**
 * Logo-Slider Block – Swiper Initialisierung
 *
 * Liest Konfiguration aus data-swiper Attribut.
 * Swiper wird als separate Dependency geladen (via wp_enqueue_script).
 *
 * @since 1.6.0
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

            // Autoplay mit delay: 0 → CSS-Animation-ähnlicher Loop
            if ( config.autoplay && config.autoplay.delay === 0 ) {
                config.autoplay.delay = 0;
                config.speed          = config.speed || 3000;
                config.loop           = true;
            }

            new Swiper( el, config );
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initLogoSliders );
    } else {
        initLogoSliders();
    }
} )();
