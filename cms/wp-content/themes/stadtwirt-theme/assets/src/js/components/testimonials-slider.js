/**
 * Testimonials Slider
 */
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';

export default class TestimonialsSlider {
  constructor() {
    this.init();
  }
  
  init() {
    this.sliders = document.querySelectorAll('.testimonials--slider');
    
    if (this.sliders.length === 0) return;
    
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
        modules: [Navigation, Pagination, Autoplay],
        slidesPerView: 1,
        spaceBetween: 30,
        loop: shouldLoop,
        // Fix: Swiper registriert DOM-Änderungen und aktualisiert sich selbst
        observer: true,
        observeParents: true,
        autoplay: autoplay && shouldLoop ? {
          delay: 5000,
          disableOnInteraction: false,
          pauseOnMouseEnter: true,
        } : false,
        navigation: {
          nextEl: sliderElement.querySelector('.testimonials__button--next'),
          prevEl: sliderElement.querySelector('.testimonials__button--prev'),
        },
        pagination: {
          el: sliderElement.querySelector('.testimonials__pagination'),
          type: 'bullets',
          clickable: true,
        },
        // Fix: Pagination nach jedem Slide-Wechsel (auch Touch/Swipe) erzwingen
        on: {
          realIndexChange: function () {
            this.pagination.render();
            this.pagination.update();
          },
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
      
      // Fix: data-animate entfernen damit Animations-System Slides nicht versteckt
      sliderElement.querySelectorAll('.swiper-slide').forEach(slide => {
        slide.removeAttribute('data-animate');
        slide.style.opacity = '';
        slide.style.visibility = '';
      });

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