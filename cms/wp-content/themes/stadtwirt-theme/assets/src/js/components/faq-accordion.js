/**
 * FAQ Accordion Component
 */

export default class FaqAccordion {
  constructor() {
    this.accordions = document.querySelectorAll('.faq-accordion');
    
    if (this.accordions.length === 0) {
      return;
    }
    
    console.log(`✅ Found ${this.accordions.length} FAQ accordion(s)`);
    this.init();
  }
  
  init() {
    this.accordions.forEach(accordion => {
      this.initAccordion(accordion);
    });
  }
  
  initAccordion(accordion) {
    const items = accordion.querySelectorAll('.faq-item');
    
    if (!items || items.length === 0) {
      return;
    }
    
    items.forEach(item => {
      const question = item.querySelector('.faq-question');
      const answer = item.querySelector('.faq-answer');
      
      if (!question || !answer) {
        return;
      }
      
      question.addEventListener('click', () => {
        const isActive = item.classList.contains('is-active');
        
        // Toggle current item
        if (isActive) {
          item.classList.remove('is-active');
          question.setAttribute('aria-expanded', 'false');
        } else {
          item.classList.add('is-active');
          question.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }
}

// Initialize
new FaqAccordion();