/**
 * Slider Block – Swiper Init
 *
 * Wrapp jeden direkten Child von .swiper-wrapper in .swiper-slide,
 * dann Swiper mit der data-swiper-Konfiguration initialisieren.
 *
 * Timing: window load (nicht DOMContentLoaded) damit Swiper-Bundle
 * vom Theme-Vite-Build garantiert verfügbar ist.
 *
 * @since 1.11.0
 * @updated 1.11.2  Robustere Init, Swiper-Scope-Fix
 */
( function () {
    'use strict';

    function initSliders() {
        // Swiper ist im Theme-Bundle – als globale Variable oder im window-Scope
        var SwiperClass = window.Swiper || ( typeof Swiper !== 'undefined' ? Swiper : null );

        if ( ! SwiperClass ) {
            // Zweiter Versuch: Swiper könnte asynchron geladen werden
            setTimeout( function () {
                SwiperClass = window.Swiper || ( typeof Swiper !== 'undefined' ? Swiper : null );
                if ( SwiperClass ) {
                    runInit( SwiperClass );
                } else {
                    console.warn( '[ml-slider] Swiper nicht verfügbar.' );
                }
            }, 300 );
            return;
        }

        runInit( SwiperClass );
    }

    function runInit( SwiperClass ) {
        document.querySelectorAll( '.ml-slider__swiper' ).forEach( function ( el ) {
            // Bereits initialisiert?
            if ( el.swiper ) return;

            var wrapper = el.querySelector( '.ml-slider__wrapper' );
            var configAttr = el.dataset.swiper;
            if ( ! wrapper || ! configAttr ) return;

            // ── InnerBlocks-Kinder zu Swiper-Slides machen ───────────────────
            // Direkte Children die noch kein .swiper-slide sind, einwickeln.
            Array.from( wrapper.children ).forEach( function ( child ) {
                if ( child.classList.contains( 'swiper-slide' ) ) return;
                var slide = document.createElement( 'div' );
                slide.className = 'swiper-slide';
                wrapper.insertBefore( slide, child );
                slide.appendChild( child );
            } );

            // ── Mindestens 1 Slide vorhanden? ────────────────────────────────
            if ( ! wrapper.querySelector( '.swiper-slide' ) ) return;

            // ── Config parsen ────────────────────────────────────────────────
            var config = {};
            try {
                config = JSON.parse( configAttr );
            } catch ( e ) {
                console.warn( '[ml-slider] Ungültige Swiper-Config:', e );
                return;
            }

            // ── Navigation: String-Selektoren durch DOM-Elemente ersetzen ────
            // Verhindert, dass Swiper die Buttons außerhalb des Containers sucht
            var parent = el.closest( '.ml-block-slider' );
            if ( config.navigation && parent ) {
                var prevBtn = parent.querySelector( '.swiper-button-prev' );
                var nextBtn = parent.querySelector( '.swiper-button-next' );
                if ( prevBtn && nextBtn ) {
                    config.navigation = { prevEl: prevBtn, nextEl: nextBtn };
                } else {
                    // Buttons nicht gefunden → Navigation deaktivieren
                    delete config.navigation;
                }
            }

            // ── Pagination ────────────────────────────────────────────────────
            if ( config.pagination && parent ) {
                var pag = parent.querySelector( '.swiper-pagination' );
                if ( pag ) {
                    config.pagination = Object.assign( {}, config.pagination, { el: pag } );
                } else {
                    delete config.pagination;
                }
            }

            // ── Swiper initialisieren ────────────────────────────────────────
            try {
                new SwiperClass( el, config );
            } catch ( err ) {
                console.error( '[ml-slider] Swiper Init-Fehler:', err );
            }
        } );
    }

    // ── Timing: window load sicherstellt dass Theme-Bundle vollständig ist ──
    if ( document.readyState === 'complete' ) {
        initSliders();
    } else {
        window.addEventListener( 'load', initSliders );
    }

} )();
