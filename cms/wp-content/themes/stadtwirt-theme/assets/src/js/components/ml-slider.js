/**
 * ML Slider – Theme-Komponente
 *
 * Initialisiert alle .ml-slider__swiper Elemente.
 * Import-Pattern identisch zu hero-slider.js und carousel.js im Theme.
 *
 * Da die Folien bereits PHP-seitig in .swiper-slide gewickelt sind,
 * muss dieses Script sie NICHT mehr nachträglich wrappen.
 *
 * @since 1.11.0
 * @updated 1.11.4  Korrektes Import-Pattern, kein JS-Wrapping mehr nötig
 */

import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, EffectFade, EffectCoverflow } from 'swiper/modules';

export default class MLSlider {
    constructor() {
        this.sliders = document.querySelectorAll( '.ml-slider__swiper' );
        if ( ! this.sliders.length ) return;
        this.init();
    }

    init() {
        this.sliders.forEach( el => {
            if ( el.swiper ) return; // bereits initialisiert

            let config = {};
            try {
                config = JSON.parse( el.dataset.swiper || '{}' );
            } catch ( e ) {
                console.warn( '[ml-slider] Ungültige Config:', e );
                return;
            }

            // Module je nach Konfiguration laden
            const modules = [ Navigation, Pagination, Autoplay ];
            if ( config.effect === 'fade' )       modules.push( EffectFade );
            if ( config.effect === 'coverflow' )  modules.push( EffectCoverflow );
            config.modules = modules;

            // Navigation: DOM-Referenzen aus PHP-Markup
            const parent = el.closest( '.ml-block-slider' );
            if ( config.navigation && parent ) {
                const prev = parent.querySelector( '.swiper-button-prev' );
                const next = parent.querySelector( '.swiper-button-next' );
                if ( prev && next ) {
                    config.navigation = { prevEl: prev, nextEl: next };
                } else {
                    config.navigation = false;
                }
            }

            // Pagination: DOM-Referenz
            if ( config.pagination && parent ) {
                const pag = parent.querySelector( '.swiper-pagination' );
                if ( pag ) {
                    config.pagination = { ...config.pagination, el: pag };
                } else {
                    delete config.pagination;
                }
            }

            try {
                new Swiper( el, config );
            } catch ( err ) {
                console.error( '[ml-slider] Init-Fehler:', err );
            }
        } );
    }
}
