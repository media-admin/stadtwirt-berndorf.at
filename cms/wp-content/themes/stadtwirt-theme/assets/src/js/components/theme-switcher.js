/**
 * Dark Mode / Light Mode Switcher
 */

export default class ThemeSwitcher {
  constructor() {
    this.storageKey = 'theme-preference';
    this.theme = this.getTheme();
    this.init();
  }
  
  init() {
    // Set initial theme
    this.setTheme(this.theme);
    
    // Create toggle button
    this.createToggle();
    
    // Listen for toggle
    document.addEventListener('click', (e) => {
      if (e.target.closest('[data-theme-toggle]')) {
        this.toggle();
      }
    });
    
    // Listen for system preference changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem(this.storageKey)) {
        this.setTheme(e.matches ? 'dark' : 'light');
      }
    });
  }
  
  getTheme() {
    // Check localStorage
    const stored = localStorage.getItem(this.storageKey);
    if (stored) return stored;
    
    // Check system preference
    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    
    return 'light';
  }
  
  setTheme(theme) {
    this.theme = theme;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem(this.storageKey, theme);
    
    // Update toggle button icon
    this.updateToggleIcon();
  }
  
  toggle() {
    const newTheme = this.theme === 'light' ? 'dark' : 'light';
    this.setTheme(newTheme);
  }
  
  createToggle() {
    // Only create if not exists
    if (document.querySelector('[data-theme-toggle]')) {
        return; // Button existiert bereits im Header
    }
    
    const toggle = document.createElement('button');
    toggle.setAttribute('data-theme-toggle', '');
    toggle.setAttribute('aria-label', 'Toggle theme');
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