/**
 * Dark Mode / Light Mode Switcher
 *
 * Priorität:
 *  1. Explizite User-Wahl (localStorage)
 *  2. System-Präferenz (prefers-color-scheme)
 *  3. Fallback: light
 */

export default class ThemeSwitcher {
  constructor() {
    this.storageKey = 'theme-preference';
    this.theme = this.getTheme();
    this.init();
  }

  init() {
    // Theme ist bereits via Inline-Script im <head> gesetzt –
    // hier nur Toggle-Button und Event Listener aufbauen.
    this.createToggle();
    this.updateToggleIcon();

    // Toggle-Klick
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-theme-toggle]')) {
        this.toggle();
      }
    });

    // System-Präferenz ändert sich (z.B. OS wechselt in Dark Mode)
    // Nur reagieren wenn der User noch keine eigene Wahl getroffen hat
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem(this.storageKey)) {
        this.applyTheme(e.matches ? 'dark' : 'light');
      }
    });
  }

  getTheme() {
    // 1. Explizite User-Wahl
    const stored = localStorage.getItem(this.storageKey);
    if (stored) return stored;

    // 2. System-Präferenz
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }

    // 3. Fallback
    return 'light';
  }

  // Nur DOM aktualisieren – kein localStorage schreiben
  applyTheme(theme) {
    this.theme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    this.updateToggleIcon();
  }

  // DOM + localStorage – nur bei expliziter User-Wahl aufrufen
  setTheme(theme) {
    localStorage.setItem(this.storageKey, theme);
    this.applyTheme(theme);
  }

  toggle() {
    const newTheme = this.theme === 'light' ? 'dark' : 'light';
    this.setTheme(newTheme);
  }

  createToggle() {
    if (document.querySelector('[data-theme-toggle]')) {
      return; // Button existiert bereits im Header
    }

    const toggle = document.createElement('button');
    toggle.setAttribute('data-theme-toggle', '');
    toggle.setAttribute('aria-label', 'Theme wechseln');
    toggle.className = 'theme-toggle';
    toggle.innerHTML = `
      <span class="theme-toggle__icon theme-toggle__icon--light">☀️</span>
      <span class="theme-toggle__icon theme-toggle__icon--dark">🌙</span>
    `;

    document.body.appendChild(toggle);
  }

  updateToggleIcon() {
    const toggle = document.querySelector('[data-theme-toggle]');
    if (!toggle) return;
    toggle.classList.toggle('is-dark', this.theme === 'dark');
  }
}

// Initialize
new ThemeSwitcher();