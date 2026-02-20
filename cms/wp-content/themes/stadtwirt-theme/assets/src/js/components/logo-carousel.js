/**
 * Logo Carousel
 */

import Swiper from 'swiper';
import { Autoplay } from 'swiper/modules';

export default class LogoCarousel {
  constructor() {
    this.carousels = document.querySelectorAll('.logo-carousel');
    this.init();
  }
  
  init() {
    if (this.carousels.length === 0) return;
    
    this.carousels.forEach(carousel => {
      this.initCarousel(carousel);
    });
  }
  
  initCarousel(carouselElement) {
    const autoplay = carouselElement.dataset.autoplay === 'true';
    const speed = parseInt(carouselElement.dataset.speed) || 3000;
    const loop = carouselElement.dataset.loop === 'true';
    const slidesPerView = carouselElement.dataset.slides || 'auto';
    
    // Zähle Slides
    const slideCount = carouselElement.querySelectorAll('.swiper-slide').length;
    const shouldLoop = slideCount > 6; // Mindestens 6 für Logo Carousel
    
    let breakpoints = {};
    
    if (slidesPerView === 'auto') {
      breakpoints = {
        320: {
          slidesPerView: 2,
          spaceBetween: 20,
        },
        640: {
          slidesPerView: 3,
          spaceBetween: 30,
        },
        768: {
          slidesPerView: 4,
          spaceBetween: 40,
        },
        1024: {
          slidesPerView: 5,
          spaceBetween: 50,
        },
        1280: {
          slidesPerView: 6,
          spaceBetween: 60,
        },
      };
    } else {
      const slides = parseInt(slidesPerView);
      breakpoints = {
        320: {
          slidesPerView: Math.max(2, Math.floor(slides / 2)),
          spaceBetween: 20,
        },
        768: {
          slidesPerView: slides,
          spaceBetween: 40,
        },
      };
    }
    
    new Swiper(carouselElement, {
      modules: [Autoplay],
      slidesPerView: 2,
      spaceBetween: 20,
      loop: shouldLoop && loop, // Nur wenn genug Slides UND loop gewünscht
      autoplay: autoplay && shouldLoop ? {
        delay: speed,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
      } : false,
      breakpoints: breakpoints,
      speed: 600,
    });
    
    if (!shouldLoop && loop) {
      console.info('Logo carousel: Loop disabled (not enough logos)');
    }
  }
}

// Initialize
new LogoCarousel();