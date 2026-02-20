/**
 * Spoiler Component
 */

export default class Spoiler {
  constructor() {
    this.spoilers = document.querySelectorAll('.spoiler');
    this.init();
  }
  
  init() {
    if (this.spoilers.length === 0) return;
    
    this.spoilers.forEach(spoiler => {
      const button = spoiler.querySelector('.spoiler__toggle');
      if (button) {
        button.addEventListener('click', () => this.toggle(spoiler));
      }
    });
  }
  
  toggle(spoiler) {
    const isOpen = spoiler.classList.contains('is-open');
    const content = spoiler.querySelector('.spoiler__content');
    const button = spoiler.querySelector('.spoiler__toggle');
    const buttonText = button.querySelector('.spoiler__button-text');
    
    if (isOpen) {
      // Close
      spoiler.classList.remove('is-open');
      content.style.display = 'none';
      buttonText.textContent = button.getAttribute('data-open-text');
      button.setAttribute('aria-expanded', 'false');
    } else {
      // Open
      spoiler.classList.add('is-open');
      content.style.display = 'block';
      buttonText.textContent = button.getAttribute('data-close-text');
      button.setAttribute('aria-expanded', 'true');
      
      // Scroll if needed
      setTimeout(() => {
        const rect = spoiler.getBoundingClientRect();
        const isVisible = (rect.top >= 0 && rect.bottom <= window.innerHeight);
        if (!isVisible) {
          spoiler.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }, 300);
    }
  }
}

// Initialize
new Spoiler();