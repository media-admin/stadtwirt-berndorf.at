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

/**
 * Cookie Consent Manager
 *
 * Banner-Layout: zweispaltig (Text links, 4 Buttons rechts gestapelt)
 * Buttons:
 *   1. Ich akzeptiere alle
 *   2. Einwilligung speichern  (aktuellen Modal-Stand speichern)
 *   3. Nur essenzielle Cookies
 *   4. Individuelle Datenschutz-Präferenzen → öffnet Modal
 *
 * WICHTIG: export default class – main.js importiert als Default und
 *          ruft `new CookieConsent()` auf. Keine Auto-Instanz im Modul.
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

        // Defaults – werden durch PHP-Config (window.cookieConsent.texts) überschrieben.
        // Merge statt OR-Operator: fehlende Keys fallen auf Defaults zurück.
        const _textDefaults = {
            bannerText:    'Wir benötigen Ihre Einwilligung, bevor Sie unsere Website weiter besuchen können.\n\nWir verwenden Cookies und andere Technologien auf unserer Website. Einige von ihnen sind essenziell, während andere uns helfen, diese Website und Ihre Erfahrung zu verbessern. Personenbezogene Daten können verarbeitet werden (z. B. IP-Adressen). Weitere Informationen über die Verwendung Ihrer Daten finden Sie in unserer <a href="{privacyUrl}" class="cookie-banner__link">Datenschutzerklärung</a>. Sie können Ihre Auswahl jederzeit unter <a href="#" class="cookie-banner__link js-cookie-settings">Einstellungen</a> widerrufen oder anpassen.',
            bannerTextUSA: 'Einige Services verarbeiten personenbezogene Daten in den USA. Mit Ihrer Einwilligung willigen Sie auch in die Verarbeitung Ihrer Daten in den USA gemäß Art. 49 (1) lit. a DSGVO ein.',
            acceptAll:     'Ich akzeptiere alle',
            saveConsent:   'Einwilligung speichern',
            essentialOnly: 'Nur essenzielle Cookies akzeptieren',
            openSettings:  'Individuelle Datenschutz-Präferenzen',
            modalTitle:    'Cookie-Einstellungen',
            modalIntro:    'Hier können Sie Ihre Cookie-Einstellungen jederzeit anpassen.',
            saveSettings:  'Auswahl speichern',
            privacyUrl:    '/datenschutz',
            alwaysActive:  'Immer aktiv',
        };
        this.texts = { ..._textDefaults, ...( window.cookieConsent?.texts || {} ) };
        // privacyUrl kann auch direkt auf window.cookieConsent gesetzt sein
        if ( window.cookieConsent?.privacyUrl ) this.texts.privacyUrl = window.cookieConsent.privacyUrl;

        this.consent = this._loadConsent();
        this.banner  = null;
        this.modal   = null;

        this._render();
        this._bindFloatingButton();

        if ( ! this._hasValidConsent() ) {
            this._showBanner();
        }

        // Globale API
        window.CookieConsent = this;
    }

    // ── Consent ───────────────────────────────────────────────────────────────

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
        const paragraphs  = bannerText.split( '\n' ).filter( l => l.trim() ).map( p => `<p>${p}</p>` ).join( '' );

        // ── Banner ────────────────────────────────────────────────────────────
        this.banner = this._el( `
            <div class="cookie-banner" role="dialog" aria-modal="true" aria-label="Cookie-Einstellungen">
                <div class="cookie-banner__inner">
                    <div class="cookie-banner__body">
                        <div class="cookie-banner__text">${paragraphs}</div>
                        ${this.texts.bannerTextUSA
                            ? `<p class="cookie-banner__text cookie-banner__text--usa">${this.texts.bannerTextUSA}</p>`
                            : '' }
                    </div>
                    <div class="cookie-banner__actions">
                        <button class="btn btn--primary" data-cc="accept-all">${this.texts.acceptAll}</button>
                        <button class="btn btn--outline" data-cc="save-consent">${this.texts.saveConsent}</button>
                        <button class="btn btn--outline" data-cc="essential-only">${this.texts.essentialOnly}</button>
                        <button class="btn btn--ghost cookie-banner__link-btn" data-cc="open-settings">${this.texts.openSettings}</button>
                    </div>
                </div>
            </div>
        ` );

        // Banner initial unsichtbar (transform, kein hidden – vermeidet display-Konflikte)
        this.banner.setAttribute( 'aria-hidden', 'true' );
        this.banner.style.visibility = 'hidden';

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
                                   <span class="cookie-toggle__track"><span class="cookie-toggle__thumb"></span></span>
                               </label>` }
                    </div>
                </div>`;
        } ).join( '' );

        this.modal = this._el( `
            <div class="cookie-modal" role="dialog" aria-modal="true" aria-label="${this.texts.modalTitle}" aria-hidden="true">
                <div class="cookie-modal__backdrop" data-cc="close-modal"></div>
                <div class="cookie-modal__box">
                    <div class="cookie-modal__header">
                        <h2 class="cookie-modal__title">${this.texts.modalTitle}</h2>
                        <button class="cookie-modal__close" data-cc="close-modal" aria-label="Schließen">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 6 6 18M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="cookie-modal__intro">${this.texts.modalIntro}</p>
                    <div class="cookie-modal__categories">${togglesHtml}</div>
                    <div class="cookie-modal__footer">
                        <button class="btn btn--primary btn--sm" data-cc="save-settings">${this.texts.saveSettings}</button>
                        <button class="btn btn--outline btn--sm" data-cc="accept-all">${this.texts.acceptAll}</button>
                    </div>
                </div>
            </div>
        ` );

        // Modal initial komplett versteckt + pointer-events:none
        this.modal.style.display         = 'none';
        this.modal.style.pointerEvents   = 'none';

        document.body.appendChild( this.banner );
        document.body.appendChild( this.modal );

        // Event-Delegation
        document.body.addEventListener( 'click', ( e ) => {
            const action = e.target.closest( '[data-cc]' )?.dataset.cc;
            if ( action ) {
                ( {
                    'accept-all':    () => this._acceptAll(),
                    'save-consent':  () => this._saveCurrentConsent(),
                    'essential-only':() => this._essentialOnly(),
                    'open-settings': () => this._openModal(),
                    'close-modal':   () => this._closeModal(),
                    'save-settings': () => this._saveFromModal(),
                } )[ action ]?.();
            }
            // „Einstellungen"-Link im Bannertext
            if ( e.target.classList.contains( 'js-cookie-settings' ) ) {
                e.preventDefault();
                this._openModal();
            }
        } );

        document.addEventListener( 'keydown', ( e ) => {
            if ( e.key === 'Escape' && ! this.modal.style.display === 'none' ) this._closeModal();
        } );
    }

    // ── Floating Button ───────────────────────────────────────────────────────

    _bindFloatingButton() {
        const btn = document.getElementById( 'cookie-settings-btn' );
        if ( btn ) btn.addEventListener( 'click', () => this._openModal() );
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    _acceptAll() {
        const all = {};
        Object.keys( this.categories ).forEach( k => all[ k ] = true );
        this._saveConsent( all );
        this._hideBanner();
        this._closeModal();
        this._dispatch( 'cookies:accepted', all );
    }

    _saveCurrentConsent() {
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
        this._hideBanner();
    }

    _essentialOnly() {
        const minimal = {};
        Object.keys( this.categories ).forEach( k => minimal[ k ] = !! this.categories[ k ].required );
        this._saveConsent( minimal );
        this._hideBanner();
        this._dispatch( 'cookies:declined', minimal );
    }

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
        this.banner.style.visibility = 'visible';
        this.banner.removeAttribute( 'aria-hidden' );
        requestAnimationFrame( () =>
            requestAnimationFrame( () => this.banner.classList.add( 'is-visible' ) )
        );
    }

    _hideBanner() {
        this.banner.classList.remove( 'is-visible' );
        setTimeout( () => {
            this.banner.style.visibility = 'hidden';
            this.banner.setAttribute( 'aria-hidden', 'true' );
        }, 350 );
    }

    // ── Modal ─────────────────────────────────────────────────────────────────

    _openModal() {
        if ( this.consent ) {
            Object.keys( this.categories ).forEach( k => {
                const cb = this.modal.querySelector( `[data-category="${k}"]` );
                if ( cb ) cb.checked = !! this.consent[ k ];
            } );
        }
        this.modal.style.display       = 'flex';
        this.modal.style.pointerEvents = '';
        this.modal.removeAttribute( 'aria-hidden' );
        document.body.classList.add( 'cookie-modal-open' );
        requestAnimationFrame( () =>
            requestAnimationFrame( () => this.modal.classList.add( 'is-visible' ) )
        );
        setTimeout( () => this.modal.querySelector( 'button' )?.focus(), 50 );
    }

    _closeModal() {
        this.modal.classList.remove( 'is-visible' );
        document.body.classList.remove( 'cookie-modal-open' );
        setTimeout( () => {
            this.modal.style.display       = 'none';
            this.modal.style.pointerEvents = 'none';
            this.modal.setAttribute( 'aria-hidden', 'true' );
        }, 300 );
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

    // ── In cookie-notice.js einfügen ──────────────────────────────────────────────
    // Position: direkt nach der openSettings() Methode (ganz am Ende der Klasse,
    // vor der schließenden })

    // ── Public API (Ergänzung) ────────────────────────────────────────────────────

    /**
     * Akzeptiert eine einzelne Kategorie programmatisch.
     * Bestehende Consent-Werte anderer Kategorien bleiben erhalten.
     * Feuert cookies:changed → _google-maps.js lädt die Karte sofort nach.
     *
     * @param {string} category – z.B. 'comfort', 'statistics', 'marketing'
     */
    acceptCategory( category ) {
        if ( ! this.categories[ category ] ) {
            console.warn( `[CookieConsent] Unbekannte Kategorie: "${category}"` );
            return;
        }

        // Aktuellen Consent laden oder leeres Objekt
        const current = this.consent || {};

        // Neue Consent-Map aufbauen: notwendig immer true,
        // Ziel-Kategorie auf true, Rest unverändert
        const updated = {};
        Object.keys( this.categories ).forEach( key => {
            if ( this.categories[ key ].required ) {
                updated[ key ] = true;
            } else {
                updated[ key ] = key === category ? true : ( current[ key ] ?? false );
            }
        } );

        this._saveConsent( updated );
        // _saveConsent feuert cookies:changed → GoogleMapConsent._handleConsentChange lädt die Karte
    }

}
