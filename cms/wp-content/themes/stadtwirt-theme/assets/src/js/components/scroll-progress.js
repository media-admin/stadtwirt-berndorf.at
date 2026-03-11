/**
 * Scroll Progress Bar
 *
 * Erwartet ein via PHP (header.php) gerendertes DOM-Element:
 *   <div class="scroll-progress" role="progressbar" aria-valuemin="0"
 *        aria-valuemax="100" aria-valuenow="0" aria-label="Lesefortschritt"></div>
 *
 * Aktualisiert --scroll-progress CSS Custom Property + aria-valuenow.
 * Läuft über requestAnimationFrame → kein Layout-Thrashing.
 */
export default class ScrollProgress {
    constructor() {
        this.bar     = document.querySelector('.scroll-progress');
        this.ticking = false;

        if ( ! this.bar ) return;

        this.init();
    }

    init() {
        window.addEventListener('scroll', () => {
            if ( ! this.ticking ) {
                window.requestAnimationFrame(() => {
                    this.update();
                    this.ticking = false;
                });
                this.ticking = true;
            }
        }, { passive: true });

        // Initialwert setzen
        this.update();
    }

    update() {
        const doc        = document.documentElement;
        const scrollTop  = window.pageYOffset || doc.scrollTop;
        const scrollHeight = doc.scrollHeight - doc.clientHeight;
        const progress   = scrollHeight > 0
            ? Math.min( Math.round( (scrollTop / scrollHeight) * 100 ), 100 )
            : 0;

        // CSS Custom Property → SCSS animiert die Breite
        this.bar.style.setProperty('--scroll-progress', progress + '%');

        // ARIA für Screenreader
        this.bar.setAttribute('aria-valuenow', progress);
    }
}
