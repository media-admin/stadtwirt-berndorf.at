/**
 * Hero Slider Component
 */

export default class HeroSlider {
  constructor() {
    this.sliders = document.querySelectorAll('.hero-slider');
    this.init();
  }
  
  init() {
    if (this.sliders.length === 0) {
      return;
    }
    
    // Check if Swiper is available (from CDN)
    if (typeof Swiper === 'undefined') {
      console.error('Swiper nicht geladen!');
      return;
    }
    
    console.log('Hero Slider Init:', this.sliders.length);
    
    this.sliders.forEach((sliderElement) => {
      this.initSlider(sliderElement);
    });
  }
  
  initSlider(sliderElement) {
    // Get slider settings from data attributes
    const autoplay = sliderElement.getAttribute('data-autoplay') === 'true';
    const delay = parseInt(sliderElement.getAttribute('data-delay')) || 5000;
    const loop = sliderElement.getAttribute('data-loop') === 'true';
    
    // Count slides
    const slideCount = sliderElement.querySelectorAll('.swiper-slide').length;
    const shouldLoop = slideCount > 1 && loop;
    
    if (slideCount <= 1 && loop) {
      console.log('Hero Slider: Loop disabled (not enough slides)');
    }
    
    // Initialize Swiper (CDN version hat alle Module eingebaut)
    const swiper = new Swiper(sliderElement, {
      // Effect
      effect: 'fade',
      fadeEffect: {
        crossFade: true
      },
      
      // Loop
      loop: shouldLoop,
      
      // Autoplay
      autoplay: shouldLoop && autoplay ? {
        delay: delay,
        disableOnInteraction: false,
      } : false,
      
      // Speed
      speed: 800,
      
      // Navigation
      navigation: {
        nextEl: sliderElement.querySelector('.swiper-button-next'),
        prevEl: sliderElement.querySelector('.swiper-button-prev'),
      },
      
      // Pagination
      pagination: {
        el: sliderElement.querySelector('.swiper-pagination'),
        clickable: true,
      },
    });
    
    console.log('✅ Hero Slider initialisiert');
  }
}

// Initialize
new HeroSlider();