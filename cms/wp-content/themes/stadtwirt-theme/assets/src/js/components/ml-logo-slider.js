/**
 * ML Logo-Slider – Theme-Komponente
 *
 * Initialisiert alle .ml-logo-slider__swiper Elemente.
 * Import-Pattern identisch zu anderen Theme-Slidern.
 *
 * @since 1.11.0
 * @updated 1.11.4  Korrektes Import-Pattern
 */

import Swiper from 'swiper';
import { Autoplay } from 'swiper/modules';

export default class MLLogoSlider {
    constructor() {
        this.sliders = document.querySelectorAll( '.ml-logo-slider__swiper' );
        if ( ! this.sliders.length ) return;
        this.init();
    }

    init() {
        this.sliders.forEach( el => {
            if ( el.swiper ) return;

            let config = {};
            try {
                config = JSON.parse( el.dataset.swiper || '{}' );
            } catch ( e ) {
                console.warn( '[ml-logo-slider] Ungültige Config:', e );
                return;
            }

            config.modules = [ Autoplay ];

            try {
                new Swiper( el, config );
            } catch ( err ) {
                console.error( '[ml-logo-slider] Init-Fehler:', err );
            }
        } );
    }
}
