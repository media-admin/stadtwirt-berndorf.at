/**
 * Cookie Notice Component
 */

export default class CookieNotice {
  constructor() {
    this.storageKey = 'cookie-consent';
    this.notice = null;
    this.init();
  }
  
  init() {
    // Check if already accepted
    if (this.hasConsent()) {
      return;
    }
    
    this.createNotice();
    this.showNotice();
  }
  
  hasConsent() {
    return localStorage.getItem(this.storageKey) === 'accepted';
  }
  
  createNotice() {
    this.notice = document.createElement('div');
    this.notice.className = 'cookie-notice';
    this.notice.innerHTML = `
      <div class="cookie-notice__content">
        <p class="cookie-notice__text">
          Wir verwenden Cookies, um Ihnen die beste Erfahrung auf unserer Website zu bieten.
          <a href="/datenschutz" class="cookie-notice__link">Mehr erfahren</a>
        </p>
        <div class="cookie-notice__actions">
          <button class="btn btn-primary btn-sm" data-cookie-accept>
            Akzeptieren
          </button>
          <button class="btn btn-outline btn-sm" data-cookie-decline>
            Ablehnen
          </button>
        </div>
      </div>
    `;
    
    document.body.appendChild(this.notice);
    
    // Event listeners
    this.notice.querySelector('[data-cookie-accept]').addEventListener('click', () => {
      this.accept();
    });
    
    this.notice.querySelector('[data-cookie-decline]').addEventListener('click', () => {
      this.decline();
    });
  }
  
  showNotice() {
    setTimeout(() => {
      this.notice.classList.add('is-visible');
    }, 1000);
  }
  
  hideNotice() {
    this.notice.classList.remove('is-visible');
    setTimeout(() => {
      this.notice.remove();
    }, 300);
  }
  
  accept() {
    localStorage.setItem(this.storageKey, 'accepted');
    this.hideNotice();
    
    // Emit event for tracking scripts
    document.dispatchEvent(new CustomEvent('cookies:accepted'));
  }
  
  decline() {
    localStorage.setItem(this.storageKey, 'declined');
    this.hideNotice();
    
    // Emit event
    document.dispatchEvent(new CustomEvent('cookies:declined'));
  }
}

// Initialize
new CookieNotice();