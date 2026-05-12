/**
 * Media Lab – Table of Contents
 *
 * Features:
 *  - Toggle (Titel-Button faltet die Liste ein/aus)
 *  - Scrollspy: aktiver Eintrag wird per IntersectionObserver hervorgehoben
 *  - Smooth Scroll mit Offset (für fixe Header)
 *  - Sticky-Scroll: wenn ToC länger als Viewport, scrollt es mit dem aktiven
 *    Eintrag mit
 *
 * Kein globales Event-Listener-Registrieren wenn kein ToC auf der Seite.
 *
 * @since 1.10.0
 */

(function () {
    'use strict';

    // Nichts tun wenn kein ToC auf der Seite
    const tocs = document.querySelectorAll('.toc');
    if (!tocs.length) return;

    // ── Smooth Scroll Offset ───────────────────────────────────────────────────
    // Berücksichtigt fixe/sticky Header. Wird beim ersten Klick berechnet.
    function getScrollOffset() {
        // Sticky-Header-Erkennung: höchstes Element mit position:sticky/fixed
        let offset = 24; // Grundabstand in px
        document.querySelectorAll('header, [data-sticky], .site-header, .header-sticky').forEach(el => {
            const style = window.getComputedStyle(el);
            if (style.position === 'sticky' || style.position === 'fixed') {
                offset = Math.max(offset, el.offsetHeight + 16);
            }
        });
        return offset;
    }

    // ── Smooth Scroll ─────────────────────────────────────────────────────────
    tocs.forEach(toc => {
        toc.addEventListener('click', e => {
            const link = e.target.closest('.toc__link');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href || !href.startsWith('#')) return;

            const target = document.getElementById(href.slice(1));
            if (!target) return;

            e.preventDefault();

            const offset   = getScrollOffset();
            const targetY  = target.getBoundingClientRect().top + window.scrollY - offset;

            window.scrollTo({ top: targetY, behavior: 'smooth' });

            // URL-Hash setzen ohne Sprung
            history.pushState(null, '', href);
        });
    });

    // ── Toggle (ein-/ausklappen) ───────────────────────────────────────────────
    tocs.forEach(toc => {
        const toggle = toc.querySelector('.toc__toggle');
        const list   = toc.querySelector('.toc__list');
        if (!toggle || !list) return;

        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            list.classList.toggle('is-collapsed', expanded);
        });
    });

    // ── Scrollspy (IntersectionObserver) ──────────────────────────────────────

    // Alle verlinkten Headings sammeln (aus allen ToCs auf der Seite)
    const allLinks = Array.from(document.querySelectorAll('.toc__link'));
    if (!allLinks.length) return;

    const targetIds  = allLinks
        .map(l => (l.getAttribute('href') || '').slice(1))
        .filter(Boolean);

    const headingEls = targetIds.map(id => document.getElementById(id)).filter(Boolean);

    if (!headingEls.length) return;

    // Aktiven Eintrag setzen (in allen ToC-Instanzen)
    function setActive(id) {
        allLinks.forEach(link => {
            const item = link.closest('.toc__item');
            if (!item) return;
            const href = link.getAttribute('href') || '';
            const isActive = href === '#' + id;
            item.classList.toggle('is-active', isActive);

            // Sticky-ToC: aktiven Eintrag ins sichtbare Scroll-Fenster bringen
            if (isActive) {
                const toc = link.closest('.toc--sticky');
                if (toc) scrollTocToActiveItem(toc, item);
            }
        });
    }

    function scrollTocToActiveItem(toc, item) {
        const tocRect  = toc.getBoundingClientRect();
        const itemRect = item.getBoundingClientRect();

        // Außerhalb des sichtbaren ToC-Bereichs?
        if (itemRect.top < tocRect.top + 32 || itemRect.bottom > tocRect.bottom - 32) {
            toc.scrollBy({
                top:      itemRect.top - tocRect.top - tocRect.height / 2 + itemRect.height / 2,
                behavior: 'smooth',
            });
        }
    }

    // Observer mit Rootmargin für sticky Header
    const observerOptions = {
        rootMargin: '-20% 0px -70% 0px',
        threshold:  0,
    };

    let lastActiveId = null;

    const observer = new IntersectionObserver(entries => {
        // Alle sichtbaren Headings finden und den obersten nehmen
        const visible = entries
            .filter(e => e.isIntersecting)
            .map(e => e.target.id);

        if (visible.length) {
            // Reihenfolge im DOM bestimmt welcher "aktiv" ist
            const domOrder = headingEls.map(el => el.id);
            const topVisible = domOrder.find(id => visible.includes(id));
            if (topVisible && topVisible !== lastActiveId) {
                lastActiveId = topVisible;
                setActive(topVisible);
            }
        }
    }, observerOptions);

    headingEls.forEach(el => observer.observe(el));

    // Beim ersten Laden: passenden Eintrag aktivieren wenn URL-Hash gesetzt
    if (window.location.hash) {
        const id = window.location.hash.slice(1);
        if (targetIds.includes(id)) {
            setTimeout(() => setActive(id), 100);
        }
    }

})();
