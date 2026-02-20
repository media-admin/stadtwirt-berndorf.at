/**
 * Scroll Animations using Intersection Observer
 */

export default class ScrollAnimations {
  constructor() {
    this.options = {
      root: null,
      rootMargin: '0px 0px -100px 0px',
      threshold: 0.1
    };
    
    this.init();
  }
  
  init() {
    this.observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          
          // Optional: Stop observing after animation
          // this.observer.unobserve(entry.target);
        }
      });
    }, this.options);
    
    this.observeElements();
  }
  
  observeElements() {
    const elements = document.querySelectorAll('[data-animate]');
    elements.forEach(el => this.observer.observe(el));
  }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  new ScrollAnimations();
});