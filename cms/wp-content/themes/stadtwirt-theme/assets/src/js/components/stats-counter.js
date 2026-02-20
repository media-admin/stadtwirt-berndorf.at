/**
 * Stats Counter with Intersection Observer
 */

export default class StatsCounter {
  constructor() {
    this.counters = document.querySelectorAll('[data-counter]');
    this.init();
  }
  
  init() {
    if (this.counters.length === 0) return;
    
    // Use Intersection Observer to trigger animation when visible
    const observerOptions = {
      root: null,
      rootMargin: '0px',
      threshold: 0.3
    };
    
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          this.animateCounter(entry.target);
          observer.unobserve(entry.target); // Only animate once
        }
      });
    }, observerOptions);
    
    this.counters.forEach(counter => {
      observer.observe(counter);
    });
  }
  
  animateCounter(counter) {
    const valueElement = counter.querySelector('.stat__value');
    if (!valueElement) return;
    
    const target = parseFloat(valueElement.getAttribute('data-target'));
    const duration = parseInt(valueElement.getAttribute('data-duration')) || 2000;
    
    // Check if number has decimals
    const hasDecimals = target % 1 !== 0;
    const decimals = hasDecimals ? (target.toString().split('.')[1] || '').length : 0;
    
    const startTime = performance.now();
    
    const updateCounter = (currentTime) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      
      // Easing function (easeOutExpo)
      const easeOutExpo = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
      
      const current = target * easeOutExpo;
      
      // Format number
      valueElement.textContent = hasDecimals 
        ? current.toFixed(decimals)
        : Math.floor(current).toLocaleString();
      
      if (progress < 1) {
        requestAnimationFrame(updateCounter);
      } else {
        // Ensure final value is exact
        valueElement.textContent = hasDecimals
          ? target.toFixed(decimals)
          : target.toLocaleString();
      }
    };
    
    valueElement.classList.add('is-counting');
    requestAnimationFrame(updateCounter);
  }
}

// Initialize
new StatsCounter();