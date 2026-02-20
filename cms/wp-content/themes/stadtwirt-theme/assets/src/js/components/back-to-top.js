/**
 * Back to Top Button
 */
export default class BackToTop {
  constructor() {
    this.button = null;
    this.scrollThreshold = 300;
    this.init();
  }
  
  init() {
    this.createButton();
    this.bindEvents();
  }
  
  createButton() {
    this.button = document.createElement('button');
    this.button.className = 'back-to-top';
    this.button.setAttribute('aria-label', 'Back to top');
    this.button.innerHTML = '↑';
    document.body.appendChild(this.button);
  }
  
  bindEvents() {
    // Scroll event
    window.addEventListener('scroll', () => {
      this.toggleVisibility();
    });
    
    // Click event
    this.button.addEventListener('click', () => {
      this.scrollToTop();
    });
  }
  
  toggleVisibility() {
    if (window.pageYOffset > this.scrollThreshold) {
      this.button.classList.add('is-visible');
    } else {
      this.button.classList.remove('is-visible');
    }
  }
  
  scrollToTop() {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  }
}
