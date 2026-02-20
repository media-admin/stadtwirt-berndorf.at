/**
 * Modal Component
 */

export default class Modal {
  constructor() {
    this.modals = document.querySelectorAll('.modal');
    this.triggers = document.querySelectorAll('[data-modal-trigger]');
    
    // Nur initialisieren wenn Modals oder Triggers existieren
    if (this.modals.length > 0 || this.triggers.length > 0) {
      this.init();
    } else {
      console.log('ℹ️ Keine Modals auf dieser Seite');
    }
  }
  
  init() {
    // Trigger Event-Listener
    this.triggers.forEach(trigger => {
      if (!trigger) return;
      
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const modalId = trigger.dataset.modalTrigger;
        if (!modalId) return;
        
        const modal = document.getElementById(modalId);
        if (modal) {
          this.openModal(modal);
        }
      });
    });
    
    // Modal Close-Buttons
    this.modals.forEach(modal => {
      if (!modal) return;
      
      const closeButtons = modal.querySelectorAll('[data-modal-close]');
      closeButtons.forEach(btn => {
        if (!btn) return;
        btn.addEventListener('click', () => this.closeModal(modal));
      });
      
      // Backdrop click
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          this.closeModal(modal);
        }
      });
    });
    
    // ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal.is-open');
        if (openModal) {
          this.closeModal(openModal);
        }
      }
    });
  }
  
  openModal(modal) {
    if (!modal) return;
    
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');
    modal.setAttribute('aria-hidden', 'false');
    
    // Focus trap
    const focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length > 0 && focusable[0]) {
      focusable[0].focus();
    }
  }
  
  closeModal(modal) {
    if (!modal) return;
    
    modal.classList.remove('is-open');
    document.body.classList.remove('modal-open');
    modal.setAttribute('aria-hidden', 'true');
  }
}
