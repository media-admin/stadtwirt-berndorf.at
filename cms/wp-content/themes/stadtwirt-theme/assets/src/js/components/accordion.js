/**
 * Accordion Component
 */

export default class Accordion {
  constructor() {
    this.accordions = document.querySelectorAll('.accordion');
    
    if (this.accordions.length > 0) {
      this.init();
    } else {
      console.log('ℹ️ Keine Accordions auf dieser Seite');
    }
  }
  
  init() {
    this.accordions.forEach(accordion => {
      this.initAccordion(accordion);
    });
  }
  
  initAccordion(accordion) {
    const items = accordion.querySelectorAll('.accordion__item');
    
    if (!items || items.length === 0) {
      console.warn('⚠️ Accordion ohne Items:', accordion);
      return;
    }
    
    items.forEach(item => {
      const trigger = item.querySelector('.accordion__trigger');
      const content = item.querySelector('.accordion__content');
      
      if (!trigger || !content) {
        console.warn('⚠️ Accordion Item ohne Trigger/Content:', item);
        return;
      }
      
      trigger.addEventListener('click', () => {
        const isActive = item.classList.contains('is-active');
        
        if (isActive) {
          item.classList.remove('is-active');
          trigger.setAttribute('aria-expanded', 'false');
          content.style.maxHeight = null;
        } else {
          item.classList.add('is-active');
          trigger.setAttribute('aria-expanded', 'true');
          content.style.maxHeight = content.scrollHeight + 'px';
        }
      });
    });
  }
}

// ✅ KEINE Auto-Initialisierung mehr!
// main.js macht das zur richtigen Zeit
