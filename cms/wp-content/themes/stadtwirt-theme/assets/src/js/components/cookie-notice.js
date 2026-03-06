/**
 * Cookie Consent Manager
 */
export default class CookieConsent {

    constructor() {
        this.storageKey = 'medialab-cookie-consent';
        this.version    = window.cookieConsent?.version || '1';

        this.categories = window.cookieConsent?.categories || {
            necessary:  { label: 'Notwendig',  description: 'Technisch erforderliche Cookies.', required: true },
            statistics: { label: 'Statistik',  description: 'Helfen uns, die Website zu verbessern.', required: false },
            marketing:  { label: 'Marketing',  description: 'Für personalisierte Werbung.', required: false },
            comfort:    { label: 'Komfort',     description: 'Für eingebettete Inhalte wie YouTube oder Maps.', required: false },
        };

        this.texts = window.cookieConsent?.texts || {
            bannerTitle:  'Wir verwenden Cookies',
            bannerText:   'Wir setzen Cookies ein, um Ihnen die bestmögliche Nutzung unserer Website zu ermöglichen.',
            acceptAll:    'Alle akzeptieren',
            declineAll:   'Ablehnen',
            settings:     'Einstellungen',
            modalTitle:   'Cookie-Einstellungen',
            modalIntro:   'Hier können Sie Ihre Cookie-Einstellungen jederzeit anpassen.',
            saveSettings: 'Auswahl speichern',
            privacyLabel: 'Datenschutzerklärung',
            privacyUrl:   '/datenschutz',
            alwaysActive: 'Immer aktiv',
        };

        this.consent = this._loadConsent();
        this.banner  = null;
        this.modal   = null;

        this._render();
        this._bindButtons();
        this._bindFloatingButton();

        if (this._hasValidConsent()) {
            this._injectSnippets(this.consent);
        } else {
            this._showBanner();
        }
    }

    // ─── Consent ──────────────────────────────────────────────────────────────

    _loadConsent() {
        try {
            const raw = localStorage.getItem(this.storageKey);
            if (!raw) return null;
            const stored = JSON.parse(raw);
            if (stored && stored.version === this.version) return stored.categories;
        } catch(e) {}
        return null;
    }

    _saveConsent(categories) {
        this.consent = categories;
        localStorage.setItem(this.storageKey, JSON.stringify({
            version:   this.version,
            timestamp: Date.now(),
            categories,
        }));
        this._injectSnippets(categories);
        this._dispatch('cookies:changed', categories);
    }

    _hasValidConsent() {
        return this.consent !== null;
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    _render() {
        // Banner
        const bannerEl = document.createElement('div');
        bannerEl.className = 'cookie-banner';
        bannerEl.setAttribute('role', 'dialog');
        bannerEl.setAttribute('aria-modal', 'true');
        bannerEl.setAttribute('hidden', '');
        bannerEl.innerHTML = `
            <div class="cookie-banner__inner">
                <div class="cookie-banner__body">
                    <p class="cookie-banner__title">${this.texts.bannerTitle}</p>
                    <p class="cookie-banner__text">
                        ${this.texts.bannerText}
                        <a href="${this.texts.privacyUrl}" class="cookie-banner__link"
                           target="_blank" rel="noopener">${this.texts.privacyLabel}</a>
                    </p>
                </div>
                <div class="cookie-banner__actions">
                    <button type="button" class="btn btn--primary btn--sm" id="cc-accept-all">
                        ${this.texts.acceptAll}
                    </button>
                    <button type="button" class="btn btn--outline btn--sm" id="cc-open-settings">
                        ${this.texts.settings}
                    </button>
                    <button type="button" class="btn btn--ghost btn--sm" id="cc-decline-all">
                        ${this.texts.declineAll}
                    </button>
                </div>
            </div>`;
        this.banner = bannerEl;

        // Modal – Toggles
        const togglesHtml = Object.entries(this.categories).map(([key, cat]) => {
            const checked = cat.required || (this.consent?.[key] ?? false);
            return `
                <div class="cookie-modal__category">
                    <div class="cookie-modal__category-header">
                        <div class="cookie-modal__category-info">
                            <span class="cookie-modal__category-name">${cat.label}</span>
                            <span class="cookie-modal__category-desc">${cat.description}</span>
                        </div>
                        ${cat.required
                            ? `<span class="cookie-modal__always-active">${this.texts.alwaysActive}</span>`
                            : `<label class="cookie-toggle">
                                   <input type="checkbox" data-category="${key}" ${checked ? 'checked' : ''}>
                                   <span class="cookie-toggle__track">
                                       <span class="cookie-toggle__thumb"></span>
                                   </span>
                               </label>`
                        }
                    </div>
                </div>`;
        }).join('');

        const modalEl = document.createElement('div');
        modalEl.className = 'cookie-modal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('hidden', '');
        modalEl.innerHTML = `
            <div class="cookie-modal__backdrop" id="cc-backdrop"></div>
            <div class="cookie-modal__box">
                <div class="cookie-modal__header">
                    <h2 class="cookie-modal__title">${this.texts.modalTitle}</h2>
                    <button type="button" class="cookie-modal__close" id="cc-close-modal" aria-label="Schließen">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <p class="cookie-modal__intro">${this.texts.modalIntro}</p>
                <div class="cookie-modal__categories">${togglesHtml}</div>
                <div class="cookie-modal__footer">
                    <button type="button" class="btn btn--primary btn--sm" id="cc-save-settings">
                        ${this.texts.saveSettings}
                    </button>
                    <button type="button" class="btn btn--outline btn--sm" id="cc-accept-all-modal">
                        ${this.texts.acceptAll}
                    </button>
                </div>
            </div>`;
        this.modal = modalEl;

        document.body.appendChild(this.banner);
        document.body.appendChild(this.modal);
    }

    // ─── Events: explizit per Button ─────────────────────────────────────────

    _bindButtons() {
        // Banner
        this.banner.querySelector('#cc-accept-all')
            ?.addEventListener('click', () => this._acceptAll());
        this.banner.querySelector('#cc-open-settings')
            ?.addEventListener('click', () => this._openModal());
        this.banner.querySelector('#cc-decline-all')
            ?.addEventListener('click', () => this._declineAll());

        // Modal
        this.modal.querySelector('#cc-close-modal')
            ?.addEventListener('click', () => this._closeModal());
        this.modal.querySelector('#cc-backdrop')
            ?.addEventListener('click', () => this._closeModal());
        this.modal.querySelector('#cc-save-settings')
            ?.addEventListener('click', () => this._saveFromModal());
        this.modal.querySelector('#cc-accept-all-modal')
            ?.addEventListener('click', () => this._acceptAll());

        // ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal.hidden) this._closeModal();
        });
    }

    _bindFloatingButton() {
        const btn = document.getElementById('cookie-settings-btn');
        if (btn) btn.addEventListener('click', () => this._openModal());
    }

    // ─── Actions ──────────────────────────────────────────────────────────────

    _acceptAll() {
        const all = {};
        Object.keys(this.categories).forEach(k => { all[k] = true; });
        this._saveConsent(all);
        this._hideBanner();
        this._closeModal();
        this._dispatch('cookies:accepted', all);
    }

    _declineAll() {
        const minimal = {};
        Object.keys(this.categories).forEach(k => { minimal[k] = !!this.categories[k].required; });
        this._saveConsent(minimal);
        this._hideBanner();
        this._dispatch('cookies:declined', minimal);
    }

    _saveFromModal() {
        const selected = {};
        Object.keys(this.categories).forEach(k => {
            if (this.categories[k].required) {
                selected[k] = true;
            } else {
                const cb = this.modal.querySelector(`[data-category="${k}"]`);
                selected[k] = cb ? cb.checked : false;
            }
        });
        this._saveConsent(selected);
        this._closeModal();
        this._hideBanner();
    }

    // ─── Banner ───────────────────────────────────────────────────────────────

    _showBanner() {
        this.banner.removeAttribute('hidden');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => { this.banner.classList.add('is-visible'); });
        });
    }

    _hideBanner() {
        this.banner.classList.remove('is-visible');
        setTimeout(() => { this.banner.setAttribute('hidden', ''); }, 350);
    }

    // ─── Modal ────────────────────────────────────────────────────────────────

    _openModal() {
        if (this.consent) {
            Object.keys(this.categories).forEach(k => {
                const cb = this.modal.querySelector(`[data-category="${k}"]`);
                if (cb) cb.checked = !!this.consent[k];
            });
        }
        this.modal.removeAttribute('hidden');
        document.body.classList.add('cookie-modal-open');
        requestAnimationFrame(() => {
            requestAnimationFrame(() => { this.modal.classList.add('is-visible'); });
        });
        setTimeout(() => { this.modal.querySelector('button')?.focus(); }, 50);
    }

    _closeModal() {
        this.modal.classList.remove('is-visible');
        document.body.classList.remove('cookie-modal-open');
        setTimeout(() => { this.modal.setAttribute('hidden', ''); }, 300);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    _dispatch(name, detail) {
        document.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));
    }

    // ─── Snippet-Injektion ────────────────────────────────────────────────────
    // Liest window.cookieSnippets und injiziert freigegebene Snippets ins DOM.
    // Wird beim Seitenload (falls Consent bereits gespeichert) und bei
    // cookies:changed aufgerufen.

    _injectSnippets(categories) {
        const snippets = window.cookieSnippets;
        if (!snippets) return;

        Object.entries(snippets).forEach(([category, code]) => {
            // Notwendige Snippets immer laden, Rest nur bei Consent
            if (!code.required && !categories[category]) return;

            // Head-Code
            if (code.head && !document.getElementById(`cc-snippet-${category}-head`)) {
                const container = document.createElement('div');
                container.id = `cc-snippet-${category}-head`;
                container.innerHTML = code.head;

                // Script-Tags müssen neu erstellt werden – innerHTML führt sie nicht aus
                container.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    [...oldScript.attributes].forEach(attr =>
                        newScript.setAttribute(attr.name, attr.value)
                    );
                    newScript.textContent = oldScript.textContent;
                    oldScript.replaceWith(newScript);
                });

                document.head.appendChild(container);
            }

            // Body-Code (Noscript-Fallbacks)
            if (code.body && !document.getElementById(`cc-snippet-${category}-body`)) {
                const container = document.createElement('div');
                container.id = `cc-snippet-${category}-body`;
                container.innerHTML = code.body;
                document.body.insertAdjacentElement('afterbegin', container);
            }
        });
    }



    // ─── Public API ───────────────────────────────────────────────────────────

    hasConsent(category) {
        return this.consent?.[category] === true;
    }

    openSettings() {
        this._openModal();
    }
}
