/**
 * Toggle – 3-State Switch
 *
 * States:   on | off | unavailable
 * Attribute: data-toggle="on|off|unavailable"
 *
 * Verwendung:
 *   import Toggle from './components/toggle';
 *   new Toggle();                  // initialisiert alle .toggle Elemente
 *   new Toggle('.mein-bereich');   // nur innerhalb eines Containers
 *
 * Events:
 *   toggle.change  → CustomEvent mit { detail: { state, previous, element } }
 *
 * Programmatisch:
 *   const t = document.querySelector('.toggle');
 *   Toggle.setState(t, 'on');
 *   Toggle.setState(t, 'unavailable');
 */
export default class Toggle {

    /**
     * @param {string|Element} [scope=document] – Suchbereich für Toggle-Elemente
     * @param {object}         [options]
     * @param {boolean}        [options.allowUnavailableToggle=false]  – Ob 'unavailable' via Klick erreichbar ist
     * @param {function}       [options.onChange]                       – Callback bei Zustandsänderung
     */
    constructor(scope = document, options = {}) {
        this.scope   = typeof scope === 'string' ? document.querySelector(scope) : scope;
        this.options = {
            allowUnavailableToggle: false,
            onChange: null,
            ...options,
        };

        this._init();
    }

    _init() {
        if (!this.scope) return;

        const toggles = this.scope === document
            ? document.querySelectorAll('.toggle:not([data-toggle-init])')
            : this.scope.querySelectorAll('.toggle:not([data-toggle-init])');

        toggles.forEach(el => this._bindToggle(el));
    }

    _bindToggle(el) {
        // Nicht doppelt initialisieren
        el.setAttribute('data-toggle-init', '1');

        // Accessibility-Attribute beim Start setzen
        this._syncAria(el);

        el.addEventListener('click', () => {
            const state = el.getAttribute('data-toggle') || 'off';
            if (state === 'unavailable') return;   // Sicherheitsnetz (pointer-events: none greift bereits)

            this._toggle(el);
        });

        // Keyboard: Space / Enter
        el.addEventListener('keydown', (e) => {
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                const state = el.getAttribute('data-toggle') || 'off';
                if (state !== 'unavailable') this._toggle(el);
            }
        });
    }

    _toggle(el) {
        const current = el.getAttribute('data-toggle') || 'off';
        const next    = current === 'on' ? 'off' : 'on';
        Toggle.setState(el, next, this.options.onChange);
    }

    _syncAria(el) {
        const state = el.getAttribute('data-toggle') || 'off';

        if (state === 'unavailable') {
            el.setAttribute('aria-disabled', 'true');
            el.removeAttribute('aria-pressed');
            el.setAttribute('tabindex', '-1');
        } else {
            el.removeAttribute('aria-disabled');
            el.setAttribute('aria-pressed', state === 'on' ? 'true' : 'false');
            el.setAttribute('role', 'switch');
            if (!el.getAttribute('tabindex')) el.setAttribute('tabindex', '0');
        }
    }

    // ── Statische Methoden (programmatischer Zugriff) ─────────────────────────

    /**
     * Setzt den Zustand eines Toggle-Elements
     * @param {Element}  el       – Das .toggle Element
     * @param {string}   state    – 'on' | 'off' | 'unavailable'
     * @param {function} [cb]     – Optionaler Callback
     */
    static setState(el, state, cb = null) {
        if (!el || !['on', 'off', 'unavailable'].includes(state)) return;

        const previous = el.getAttribute('data-toggle') || 'off';
        if (previous === state) return;

        el.setAttribute('data-toggle', state);

        // Aria synchronisieren
        if (state === 'unavailable') {
            el.setAttribute('aria-disabled', 'true');
            el.removeAttribute('aria-pressed');
            el.setAttribute('tabindex', '-1');
        } else {
            el.removeAttribute('aria-disabled');
            el.setAttribute('aria-pressed', state === 'on' ? 'true' : 'false');
            el.setAttribute('role', 'switch');
            el.setAttribute('tabindex', '0');
        }

        // CustomEvent dispatchen
        el.dispatchEvent(new CustomEvent('toggle.change', {
            bubbles: true,
            detail: { state, previous, element: el },
        }));

        // Callback
        if (typeof cb === 'function') cb({ state, previous, element: el });
    }

    /**
     * Liest den aktuellen Zustand
     * @param  {Element} el
     * @return {string}  'on' | 'off' | 'unavailable'
     */
    static getState(el) {
        return el?.getAttribute('data-toggle') || 'off';
    }
}
