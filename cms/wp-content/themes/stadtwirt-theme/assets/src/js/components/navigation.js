/**
 * Navigation – Header (Mobile + Desktop) + Footer
 * Unterstützt 4 Ebenen.
 */
export default class Navigation {

    constructor() {
        this.mobileToggle  = document.querySelector('.mobile-menu-toggle');
        this.mobileMenu    = document.querySelector('.mobile-menu');
        this.mobileOverlay = document.querySelector('.mobile-menu-overlay');
        this.mobileClose   = document.querySelector('.mobile-menu__close'); // NEU

        // Alle menu-item-has-children in Mobile + Footer
        this.mobileItems = document.querySelectorAll(
            '.mobile-menu .menu-item-has-children'
        );
        this.footerItems = document.querySelectorAll(
            '.footer-nav .menu-item-has-children'
        );

        this._init();
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    _init() {
        this._initMobileMenu();
        this._initMobileSubmenus();
        this._initFooterSubmenus();
        this._initDesktopViewportCheck();
        this._addToggleIcons();
    }

    // ─── Toggle-Icon in Mobile-Menü einfügen ─────────────────────────────────
    // PHP rendert das Menü – JS fügt das +-Icon ein damit Links klickbar bleiben

    _addToggleIcons() {
        this.mobileItems.forEach(item => {
            const link = item.querySelector(':scope > a');
            if (!link || link.querySelector('.nav-toggle-icon')) return;

            const icon = document.createElement('span');
            icon.className       = 'nav-toggle-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent     = '+';
            link.appendChild(icon);
        });
    }

    // ─── Mobile Menu (Fullscreen) ─────────────────────────────────────────────

    _initMobileMenu() {
        if (!this.mobileToggle || !this.mobileMenu) return;

        this.mobileToggle.addEventListener('click', () => this._toggleMobileMenu());

        // NEU: Schließen-Button im Mobile Menu
        if (this.mobileClose) {
            this.mobileClose.addEventListener('click', () => this._closeMobileMenu());
        }

        if (this.mobileOverlay) {
            this.mobileOverlay.addEventListener('click', () => this._closeMobileMenu());
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this._closeMobileMenu();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) this._closeMobileMenu();
        });
    }

    _toggleMobileMenu() {
        this.mobileMenu.classList.contains('is-active')
            ? this._closeMobileMenu()
            : this._openMobileMenu();
    }

    _openMobileMenu() {
        this.mobileToggle?.classList.add('is-active');
        this.mobileMenu.classList.add('is-active');
        this.mobileOverlay?.classList.add('is-active');
        document.body.style.overflow = 'hidden';
    }

    _closeMobileMenu() {
        this.mobileToggle?.classList.remove('is-active');
        this.mobileMenu.classList.remove('is-active');
        this.mobileOverlay?.classList.remove('is-active');
        document.body.style.overflow = '';

        // Alle Submenüs schließen
        this.mobileItems.forEach(item => item.classList.remove('is-open'));
    }

    // ─── Mobile Submenüs (alle 4 Ebenen) ─────────────────────────────────────

    _initMobileSubmenus() {
        this.mobileItems.forEach(item => {
            const link = item.querySelector(':scope > a');
            if (!link) return;

            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                const hasRealLink = href && href !== '#' && href !== '';

                if (hasRealLink && !item.classList.contains('is-open')) {
                    e.preventDefault();
                    this._toggleSubmenu(item, this.mobileItems);
                } else if (!hasRealLink) {
                    e.preventDefault();
                    this._toggleSubmenu(item, this.mobileItems);
                }
            });
        });
    }

    _toggleSubmenu(item, allItems) {
        const isOpen = item.classList.contains('is-open');

        const parent   = item.parentElement;
        const siblings = parent.querySelectorAll(':scope > .menu-item-has-children');
        siblings.forEach(s => {
            if (s !== item) {
                s.classList.remove('is-open');
                s.querySelectorAll('.menu-item-has-children').forEach(child => {
                    child.classList.remove('is-open');
                });
            }
        });

        item.classList.toggle('is-open', !isOpen);
    }

    // ─── Footer Submenüs (Accordion auf Mobile) ───────────────────────────────

    _initFooterSubmenus() {
        this.footerItems.forEach(item => {
            const link = item.querySelector(':scope > a');
            if (!link) return;

            link.addEventListener('click', (e) => {
                if (window.innerWidth >= 768) return;

                e.preventDefault();
                this._toggleSubmenu(item, this.footerItems);
            });
        });
    }

    // ─── Desktop: Flyout-Viewport-Kollision erkennen ──────────────────────────

    _initDesktopViewportCheck() {
        const allSubmenus = document.querySelectorAll(
            '.primary-menu .sub-menu, .footer-nav .sub-menu'
        );

        const checkOverflow = () => {
            allSubmenus.forEach(menu => {
                menu.classList.remove('opens-left');
                const rect = menu.getBoundingClientRect();
                if (rect.right > window.innerWidth - 8) {
                    menu.classList.add('opens-left');
                }
            });
        };

        document.querySelectorAll(
            '.primary-menu .menu-item-has-children, .footer-nav .menu-item-has-children'
        ).forEach(item => {
            item.addEventListener('mouseenter', checkOverflow, { once: false });
        });
    }
}
