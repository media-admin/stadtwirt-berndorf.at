/**
 * Carousel Component (Swiper-based)
 */
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';

export default class Carousel {
  constructor() {
    this.carousels = document.querySelectorAll('.carousel');
    this.init();
  }
  
  init() {
    if (this.carousels.length === 0) {
      return;
    }
    
    this.carousels.forEach((carouselElement) => {
      this.initCarousel(carouselElement);
    });
  }
  
  initCarousel(carouselElement) {
    const autoplay = carouselElement.getAttribute('data-autoplay') === 'true';
    const delay = parseInt(carouselElement.getAttribute('data-delay')) || 3000;
    const loop = carouselElement.getAttribute('data-loop') === 'true';
    const slidesPerView = parseInt(carouselElement.getAttribute('data-slides')) || 3;
    const spaceBetween = parseInt(carouselElement.getAttribute('data-space')) || 30;
    const mobileSlides = parseInt(carouselElement.getAttribute('data-mobile')) || 1;
    const tabletSlides = parseInt(carouselElement.getAttribute('data-tablet')) || 2;
    
    const swiper = new Swiper(carouselElement, {
      modules: [Navigation, Pagination, Autoplay],
      slidesPerView: mobileSlides,
      spaceBetween: 15,
      loop: loop,
      autoplay: autoplay ? { 
        delay, 
        disableOnInteraction: false 
      } : false,
      navigation: {
        nextEl: carouselElement.parentElement.querySelector('.swiper-button-next'),
        prevEl: carouselElement.parentElement.querySelector('.swiper-button-prev'),
      },
      pagination: {
        el: carouselElement.parentElement.querySelector('.swiper-pagination'),
        clickable: true,
      },
      breakpoints: {
        640: {
          slidesPerView: tabletSlides,
          spaceBetween: 20,
        },
        1024: {
          slidesPerView: slidesPerView,
          spaceBetween: spaceBetween,
        },
      },
    });
    
    console.log('✅ Carousel initialisiert');
  }
}

// Initialize
new Carousel();