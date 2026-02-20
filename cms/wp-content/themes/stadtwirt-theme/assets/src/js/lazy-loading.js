/**
 * Lazy Loading Fallback für ältere Browser
 */

// Prüfe ob Browser natives Lazy Loading unterstützt
if ('loading' in HTMLImageElement.prototype) {
  // Browser unterstützt natives Lazy Loading
  console.log('Native lazy loading supported');
} else {
  // Fallback für ältere Browser
  const images = document.querySelectorAll('img[loading="lazy"]');
  const iframes = document.querySelectorAll('iframe[loading="lazy"]');
  
  const lazyLoad = (entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const element = entry.target;
        
        if (element.dataset.src) {
          element.src = element.dataset.src;
        }
        
        if (element.dataset.srcset) {
          element.srcset = element.dataset.srcset;
        }
        
        element.classList.add('loaded');
        observer.unobserve(element);
      }
    });
  };
  
  const observer = new IntersectionObserver(lazyLoad, {
    rootMargin: '200px' // Lade 200px bevor Element sichtbar wird
  });
  
  images.forEach(img => observer.observe(img));
  iframes.forEach(iframe => observer.observe(iframe));
}