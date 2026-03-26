/**
 * Cookie Consent Manager
 *
 * Banner-Layout: zweispaltig (Text links, 4 Buttons rechts gestapelt)
 * Buttons:
 *   1. Ich akzeptiere alle         → alles an
 *   2. Einwilligung speichern      → aktuellen Modal-Stand speichern
 *                                     (Standard: nur Notwendige wenn Modal noch nie geöffnet)
 *   3. Nur essenzielle Cookies     → alles optional aus
 *   4. Individuelle Präferenzen    → Modal öffnen
 *
 * Modal: Toggles pro Kategorie, unverändert
 *
 * @since 1.6.1
 */

export default class CookieConsent {

    constructor() {
        this.storageKey = 'medialab-cookie-consent';
        this.version    = window.cookieConsent?.version || '1';

        this.categories = window.cookieConsent?.categories || {
            necessary:  { label: 'Notwendig',  description: 'Technisch erforderliche Cookies für die Grundfunktionen der Website.', required: true },
            statistics: { label: 'Statistik',   description: 'Helfen uns zu verstehen, wie Besucher die Website nutzen.', required: false },
            marketing:  { label: 'Marketing',   description: 'Werden verwendet, um Besuchern relevante Werbung zu zeigen.', required: false },
            comfort:    { label: 'Komfort',     description: 'Ermöglichen eingebettete Inhalte wie YouTube-Videos oder Google Maps.', required: false },
        };

        this.texts = window.cookieConsent?.texts || {
            bannerText:        'Wir benötigen Ihre Einwilligung, bevor Sie unsere Website weiter besuchen können.\n\nWenn Sie unter 16 Jahre alt sind und Ihre Einwilligung zu optionalen Services geben möchten, müssen Sie Ihre Erziehungsberechtigten um Erlaubnis bitten.\n\nWir verwenden Cookies und andere Technologien auf unserer Website. Einige von ihnen sind essenziell, während andere uns helfen, diese Website und Ihre Erfahrung zu verbessern. Personenbezogene Daten können verarbeitet werden (z. B. IP-Adressen), z. B. für personalisierte Anzeigen und Inhalte oder die Messung von Anzeigen und Inhalten. Weitere Informationen über die Verwendung Ihrer Daten finden Sie in unserer <a href="{privacyUrl}" class="cookie-banner__link">Datenschutzerklärung</a>. Es besteht keine Verpflichtung, der Verarbeitung Ihrer Daten zuzustimmen, um dieses Angebot zu nutzen. Sie können Ihre Auswahl jederzeit unter <a href="#" class="cookie-banner__link" data-cc="open-settings">Einstellungen</a> widerrufen oder anpassen.',
            bannerTextUSA:     'Einige Services verarbeiten personenbezogene Daten in den USA. Mit Ihrer Einwilligung zur Nutzung dieser Services willigen Sie auch in die Verarbeitung Ihrer Daten in den USA gemäß Art. 49 (1) lit. a DSGVO ein. Der EuGH stuft die USA als ein Land mit unzureichendem Datenschutz nach EU-Standards ein. Es besteht beispielsweise die Gefahr, dass US-Behörden personenbezogene Daten in Überwachungsprogrammen verarbeiten, ohne dass für Europäerinnen und Europäer eine Klagemöglichkeit besteht.',
            acceptAll:         'Ich akzeptiere alle',
            saveConsent:       'Einwilligung speichern',
            essentialOnly:     'Nur essenzielle Cookies akzeptieren',
            openSettings:      'Individuelle Datenschutz-Präferenzen',
            modalTitle:        'Cookie-Einstellungen',
            modalIntro:        'Hier können Sie Ihre Cookie-Einstellungen jederzeit anpassen.',
            saveSettings:      'Auswahl speichern',
            privacyLabel:      'Datenschutzerklärung',
            privacyUrl:        window.cookieConsent?.privacyUrl || '/datenschutz',
            alwaysActive:      'Immer aktiv',
        };

        this.consent = this._loadConsent();
        this.banner  = null;
        this.modal   = null;

        this._render();
        this._bindFloatingButton();

        if ( ! this._hasValidConsent() ) {
            this._showBanner();
        }
    }

    // ── Consent laden / speichern ─────────────────────────────────────────────

    _loadConsent() {
        try {
            const stored = JSON.parse( localStorage.getItem( this.storageKey ) || 'null' );
            if ( stored && stored.version === this.version ) return stored.categories;
        } catch ( e ) {}
        return null;
    }

    _saveConsent( categories ) {
        this.consent = categories;
        localStorage.setItem( this.storageKey, JSON.stringify( {
            version:    this.version,
            timestamp:  Date.now(),
            categories,
        } ) );
        this._dispatch( 'cookies:changed', categories );
    }

    _hasValidConsent() {
        return this.consent !== null;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    _render() {
        const privacyUrl  = this.texts.privacyUrl;
        const bannerText  = this.texts.bannerText.replace( '{privacyUrl}', privacyUrl );

        // ── Banner ────────────────────────────────────────────────────────────
        this.banner = this._el( `
            <div class="cookie-banner" role="dialog" aria-modal="true"
                 aria-label="Cookie-Einstellungen" hidden>
                <div class="cookie-banner__inner">

                    <!-- Linke Spalte: Text -->
                    <div class="cookie-banner__body">
                        <div class="cookie-banner__text">
                            ${bannerText.split('\n').filter( l => l.trim() ).map( p => `<p>${p}</p>` ).join('')}
                        </div>
                        ${this.texts.bannerTextUSA ? `<p class="cookie-banner__text cookie-banner__text--usa">${this.texts.bannerTextUSA}</p>` : ''}
                    </div>

                    <!-- Rechte Spalte: 4 Buttons gestapelt -->
                    <div class="cookie-banner__actions">
                        <button class="btn btn--primary" data-cc="accept-all">
                            ${this.texts.acceptAll}
                        </button>
                        <button class="btn btn--outline" data-cc="save-consent">
                            ${this.texts.saveConsent}
                        </button>
                        <button class="btn btn--outline" data-cc="essential-only">
                            ${this.texts.essentialOnly}
                        </button>
                        <button class="btn btn--ghost cookie-banner__link-btn" data-cc="open-settings">
                            ${this.texts.openSettings}
                        </button>
                    </div>

                </div>
            </div>
        ` );

        // ── Modal ─────────────────────────────────────────────────────────────
        const togglesHtml = Object.entries( this.categories ).map( ( [ key, cat ] ) => {
            const checked = cat.required || ( this.consent?.[ key ] ?? false );
            return `
                <div class="cookie-modal__category">
                    <div class="cookie-modal__category-header">
                        <div class="cookie-modal__category-info">
                            <span class="cookie-modal__category-name">${cat.label}</span>
                            <span class="cookie-modal__category-desc">${cat.description}</span>
                        </div>
                        ${ cat.required
                            ? `<span class="cookie-modal__always-active">${this.texts.alwaysActive}</span>`
                            : `<label class="cookie-toggle" aria-label="${cat.label}">
                                   <input type="checkbox" data-category="${key}" ${checked ? 'checked' : ''}>
                                   <span class="cookie-toggle__track">
                                       <span class="cookie-toggle__thumb"></span>
                                   </span>
                               </label>`
                        }
                    </div>
                </div>`;
        } ).join( '' );

        this.modal = this._el( `
            <div class="cookie-modal" role="dialog" aria-modal="true"
                 aria-label="${this.texts.modalTitle}" hidden>
                <div class="cookie-modal__backdrop" data-cc="close-modal"></div>
                <div class="cookie-modal__box">
                    <div class="cookie-modal__header">
                        <h2 class="cookie-modal__title">${this.texts.modalTitle}</h2>
                        <button class="cookie-modal__close" data-cc="close-modal" aria-label="Schließen">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="cookie-modal__intro">${this.texts.modalIntro}</p>
                    <div class="cookie-modal__categories">${togglesHtml}</div>
                    <div class="cookie-modal__footer">
                        <button class="btn btn--primary btn--sm" data-cc="save-settings">
                            ${this.texts.saveSettings}
                        </button>
                        <button class="btn btn--outline btn--sm" data-cc="accept-all">
                            ${this.texts.acceptAll}
                        </button>
                    </div>
                </div>
            </div>
        ` );

        document.body.appendChild( this.banner );
        document.body.appendChild( this.modal );

        // Event-Delegation
        [ this.banner, this.modal ].forEach( el => {
            el.addEventListener( 'click', ( e ) => {
                const action = e.target.closest( '[data-cc]' )?.dataset.cc;
                if ( ! action ) return;
                ( {
                    'accept-all':    () => this._acceptAll(),
                    'save-consent':  () => this._saveCurrentConsent(),
                    'essential-only':() => this._essentialOnly(),
                    'open-settings': () => this._openModal(),
                    'close-modal':   () => this._closeModal(),
                    'save-settings': () => this._saveFromModal(),
                } )[ action ]?.();
            } );
        } );

        document.addEventListener( 'keydown', ( e ) => {
            if ( e.key === 'Escape' && ! this.modal.hidden ) this._closeModal();
        } );
    }

    // ── Floating Button ───────────────────────────────────────────────────────

    _bindFloatingButton() {
        const btn = document.getElementById( 'cookie-settings-btn' );
        if ( btn ) btn.addEventListener( 'click', () => this._openModal() );
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    // Button 1: Ich akzeptiere alle
    _acceptAll() {
        const all = {};
        Object.keys( this.categories ).forEach( k => all[ k ] = true );
        this._saveConsent( all );
        this._hideBanner();
        this._closeModal();
        this._dispatch( 'cookies:accepted', all );
    }

    // Button 2: Einwilligung speichern
    // Speichert was aktuell im Modal eingestellt ist.
    // Wurde das Modal noch nie geöffnet → nur Notwendige werden gespeichert.
    _saveCurrentConsent() {
        const selected = {};
        Object.keys( this.categories ).forEach( k => {
            if ( this.categories[ k ].required ) {
                selected[ k ] = true;
            } else {
                const cb = this.modal.querySelector( `[data-category="${k}"]` );
                // cb.checked ist false wenn Modal noch nie geöffnet (Checkboxen default-aus)
                selected[ k ] = cb ? cb.checked : false;
            }
        } );
        this._saveConsent( selected );
        this._hideBanner();
        this._dispatch( 'cookies:changed', selected );
    }

    // Button 3: Nur essenzielle
    _essentialOnly() {
        const minimal = {};
        Object.keys( this.categories ).forEach( k => minimal[ k ] = !! this.categories[ k ].required );
        this._saveConsent( minimal );
        this._hideBanner();
        this._dispatch( 'cookies:declined', minimal );
    }

    // Modal: Auswahl speichern
    _saveFromModal() {
        const selected = {};
        Object.keys( this.categories ).forEach( k => {
            if ( this.categories[ k ].required ) {
                selected[ k ] = true;
            } else {
                const cb = this.modal.querySelector( `[data-category="${k}"]` );
                selected[ k ] = cb ? cb.checked : false;
            }
        } );
        this._saveConsent( selected );
        this._closeModal();
        this._hideBanner();
    }

    // ── Banner ────────────────────────────────────────────────────────────────

    _showBanner() {
        this.banner.hidden = false;
        requestAnimationFrame( () =>
            requestAnimationFrame( () => this.banner.classList.add( 'is-visible' ) )
        );
    }

    _hideBanner() {
        this.banner.classList.remove( 'is-visible' );
        setTimeout( () => { this.banner.hidden = true; }, 350 );
    }

    // ── Modal ─────────────────────────────────────────────────────────────────

    _openModal() {
        // Checkboxen auf gespeicherten Stand synchronisieren
        if ( this.consent ) {
            Object.keys( this.categories ).forEach( k => {
                const cb = this.modal.querySelector( `[data-category="${k}"]` );
                if ( cb ) cb.checked = !! this.consent[ k ];
            } );
        }
        this.modal.hidden = false;
        document.body.classList.add( 'cookie-modal-open' );
        requestAnimationFrame( () =>
            requestAnimationFrame( () => this.modal.classList.add( 'is-visible' ) )
        );
        setTimeout( () => this.modal.querySelector( 'button' )?.focus(), 50 );
    }

    _closeModal() {
        this.modal.classList.remove( 'is-visible' );
        document.body.classList.remove( 'cookie-modal-open' );
        setTimeout( () => { this.modal.hidden = true; }, 300 );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    _el( html ) {
        const d = document.createElement( 'div' );
        d.innerHTML = html.trim();
        return d.firstElementChild;
    }

    _dispatch( name, detail ) {
        document.dispatchEvent( new CustomEvent( name, { detail, bubbles: true } ) );
    }

    // ── Public API ────────────────────────────────────────────────────────────

    hasConsent( category ) {
        return this.consent?.[ category ] === true;
    }

    openSettings() {
        this._openModal();
    }
}

