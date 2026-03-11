/**
 * Back to Top Button
 *
 * Erwartet ein via PHP (footer.php) gerendertes DOM-Element:
 *   <button class="back-to-top" aria-label="Zurück nach oben">…</button>
 *
 * Fügt .is-visible hinzu wenn scrollY > THRESHOLD.
 * Scrollt smooth nach oben beim Klick.
 */
export default class BackToTop {
    constructor() {
        this.button         = document.querySelector('.back-to-top');
        this.scrollThreshold = 300;
        this.ticking        = false;

        if ( ! this.button ) return;

        this.init();
    }

    init() {
        this.bindEvents();
        this.update(); // Initialzustand prüfen (z.B. bei deep-link)
    }

    bindEvents() {
        window.addEventListener('scroll', () => {
            if ( ! this.ticking ) {
                window.requestAnimationFrame(() => {
                    this.update();
                    this.ticking = false;
                });
                this.ticking = true;
            }
        }, { passive: true });

        this.button.addEventListener('click', () => this.scrollToTop());

        // Keyboard: Enter + Space
        this.button.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.scrollToTop();
            }
        });
    }

    update() {
        const scrolled = window.pageYOffset || document.documentElement.scrollTop;
        this.button.classList.toggle('is-visible', scrolled > this.scrollThreshold);
    }

    scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        // Focus zurück auf erstes fokussierbares Element (Accessibility)
        const firstFocusable = document.querySelector('a, button, input, [tabindex]');
        if ( firstFocusable ) firstFocusable.focus({ preventScroll: true });
    }
}
