/**
 * Hero Slider Component
 */
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay, EffectFade } from 'swiper/modules';

export default class HeroSlider {
  constructor() {
    this.sliders = document.querySelectorAll('.hero-slider');
    this.init();
  }
  
  init() {
    if (this.sliders.length === 0) {
      return;
    }
    
    this.sliders.forEach((sliderElement) => {
      this.initSlider(sliderElement);
    });
  }
  
  initSlider(sliderElement) {
    const autoplay = sliderElement.getAttribute('data-autoplay') === 'true';
    const delay = parseInt(sliderElement.getAttribute('data-delay')) || 5000;
    const loop = sliderElement.getAttribute('data-loop') === 'true';
    
    const slideCount = sliderElement.querySelectorAll('.swiper-slide').length;
    const shouldLoop = slideCount > 1 && loop;
    
    new Swiper(sliderElement, {
      modules: [Navigation, Pagination, Autoplay, EffectFade],
      effect: 'fade',
      fadeEffect: { crossFade: true },
      loop: shouldLoop,
      autoplay: shouldLoop && autoplay ? {
        delay: delay,
        disableOnInteraction: false,
      } : false,
      speed: 800,
      navigation: {
        nextEl: sliderElement.querySelector('.swiper-button-next'),
        prevEl: sliderElement.querySelector('.swiper-button-prev'),
      },
      pagination: {
        el: sliderElement.querySelector('.swiper-pagination'),
        clickable: true,
      },
    });
  }
}

// Initialize
new HeroSlider();
