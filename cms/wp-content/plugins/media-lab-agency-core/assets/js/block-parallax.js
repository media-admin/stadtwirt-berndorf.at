/**
 * Parallax Block – JS
 * rAF-basierter Scroll-Effekt. prefers-reduced-motion: kein Effekt.
 * @since 1.11.0
 */
( function () {
    'use strict';

    // Kein Parallax wenn reduced-motion bevorzugt wird
    const prefersReduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

    const sections = document.querySelectorAll( '.ml-block-parallax' );
    if ( ! sections.length || prefersReduced ) return;

    let ticking = false;

    function updateParallax() {
        sections.forEach( function ( section ) {
            const bg    = section.querySelector( '.ml-parallax__bg' );
            if ( ! bg ) return;

            const speed = parseFloat( section.dataset.parallaxSpeed ?? 40 ) / 100;
            const rect  = section.getBoundingClientRect();
            const vh    = window.innerHeight;

            // Sichtbar? (mit 20% Buffer)
            if ( rect.bottom < -vh * 0.2 || rect.top > vh * 1.2 ) return;

            // Versatz berechnen: 0 wenn Block mittig im Viewport, ±speed*50px bei Rand
            const center  = rect.top + rect.height / 2 - vh / 2;
            const offset  = -( center * speed * 0.5 );

            bg.style.transform = 'translate3d(0, ' + offset.toFixed( 2 ) + 'px, 0)';
        } );

        ticking = false;
    }

    function onScroll() {
        if ( ! ticking ) {
            requestAnimationFrame( updateParallax );
            ticking = true;
        }
    }

    window.addEventListener( 'scroll', onScroll, { passive: true } );
    window.addEventListener( 'resize', onScroll, { passive: true } );

    // Initial
    updateParallax();
} )();
