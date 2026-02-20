// Import CSS
import '../scss/style.scss';

// Import Sentry
import { initSentry } from './utils/sentry';
initSentry();

// Components
import HeroSlider from './components/hero-slider';
import Accordion from './components/accordion';
import './components/carousel';
import BackToTop from './components/back-to-top';
import CookieNotice from './components/cookie-notice';
import './components/faq-accordion';
import DarkMode from './components/theme-switcher';
import ImageComparison from './components/image-comparison';
import Lightbox from './components/lightbox';
import LogoCarousel from './components/logo-carousel';
import Modal from './components/modal';
import Navigation from './components/navigation';
import Notifications from './components/notifications';
import ScrollAnimations from './components/scroll-animations';
import Spoiler from './components/spoiler';
import StatsCounter from './components/stats-counter';
import Tabs from './components/tabs';
import TestimonialsSlider from './components/testimonials-slider';
import VideoPlayer from './components/video-player';
import './components/ajax-search';
import './components/load-more';
import './components/google-maps';
import AjaxFilters from './components/ajax-filters.js';

// Helper: Sichere Initialisierung mit Error-Tracking
const safeInit = (name, initFn) => {
  try {
    initFn();
    console.log(`✅ ${name} initialisiert`);
  } catch (error) {
    console.error(`❌ FEHLER in ${name}:`, error);
    console.error(`Stack:`, error.stack);
  }
};

// Zentrale DOM Ready Initialisierung
const initApp = () => {
  console.log('🚀 Initialisiere Komponenten...');
  
  // Initialisiere jede Komponente einzeln mit Error-Tracking
  safeInit('Accordion', () => new Accordion());
  safeInit('HeroSlider', () => new HeroSlider());
  safeInit('BackToTop', () => new BackToTop());
  safeInit('CookieNotice', () => new CookieNotice());
  safeInit('DarkMode', () => new DarkMode());
  safeInit('ImageComparison', () => new ImageComparison());
  safeInit('Lightbox', () => new Lightbox());
  safeInit('LogoCarousel', () => new LogoCarousel());
  safeInit('Modal', () => new Modal());
  safeInit('Navigation', () => new Navigation());
  safeInit('Notifications', () => new Notifications());
  safeInit('ScrollAnimations', () => new ScrollAnimations());
  safeInit('Spoiler', () => new Spoiler());
  safeInit('StatsCounter', () => new StatsCounter());
  safeInit('Tabs', () => new Tabs());
  safeInit('TestimonialsSlider', () => new TestimonialsSlider());
  safeInit('VideoPlayer', () => new VideoPlayer());
  safeInit('AjaxFilters', () => new AjaxFilters());
  
  console.log('✅ Komponenten-Initialisierung abgeschlossen');
};

// Warte auf DOM Ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

// Theme loaded
console.log('✨ Custom Theme loaded');
