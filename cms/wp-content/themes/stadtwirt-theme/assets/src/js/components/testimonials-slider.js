/**
 * Testimonials Slider
 */

export default class TestimonialsSlider {
  constructor() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.init());
    } else {
      this.init();
    }
  }
  
  init() {
    this.sliders = document.querySelectorAll('.testimonials--slider');
    
    if (this.sliders.length === 0) return;
    
    if (typeof Swiper === 'undefined') {
      console.warn('Swiper library not loaded for testimonials slider');
      return;
    }
    
    this.sliders.forEach(slider => {
      this.initSlider(slider);
    });
  }
  
  initSlider(sliderElement) {
    try {
      const autoplay = sliderElement.hasAttribute('data-autoplay');
      const columns = parseInt(sliderElement.getAttribute('data-columns')) || 3;
      
      // Zähle die Anzahl der Slides
      const slideCount = sliderElement.querySelectorAll('.swiper-slide').length;
      
      // Loop nur aktivieren wenn genug Slides vorhanden sind
      const shouldLoop = slideCount > 3;
      
      const swiperInstance = new Swiper(sliderElement, {
        slidesPerView: 1,
        spaceBetween: 30,
        loop: shouldLoop, // Dynamisch: nur wenn genug Slides
        autoplay: autoplay && shouldLoop ? {
          delay: 5000,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        } : false,
        navigation: {
          nextEl: '.testimonials__button--next',
          prevEl: '.testimonials__button--prev',
        },
        pagination: {
          el: '.testimonials__pagination',
          clickable: true,
        },
        breakpoints: {
          640: {
            slidesPerView: Math.min(2, columns),
            spaceBetween: 30,
          },
          1024: {
            slidesPerView: Math.min(3, columns),
            spaceBetween: 40,
          },
        },
      });
      
      // Console Info (optional, kann entfernt werden)
      if (!shouldLoop) {
        console.info('Testimonials slider: Loop disabled (not enough slides)');
      }
      
    } catch (error) {
      console.error('Error initializing testimonials slider:', error);
    }
  }
}

// Auto-initialize
new TestimonialsSlider();