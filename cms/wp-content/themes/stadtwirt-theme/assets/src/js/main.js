/**
 * Main Entry Point
 * Media Lab Starter Kit – Custom Theme
 */

// CSS (inkl. Swiper)
import '../scss/style.scss';
import 'swiper/css/bundle';
import sloganUrl from '../images/slogan_tradition-trifft-kultur.svg?url';

// Sentry (nur in Production)
if (import.meta.env.PROD) {
  import('./utils/sentry').then(({ initSentry }) => initSentry());
}

// ─── Kern-Komponenten (immer geladen) ────────────────────────────────────────
import Navigation    from './components/navigation';
import Toggle        from './components/toggle';
import DarkMode      from './components/theme-switcher';
import CookieConsent from './components/cookie-notice';
import BackToTop     from './components/back-to-top';
import ScrollProgress from './components/scroll-progress';
import Notifications from './components/notifications';
import initTopHeader from './components/top-header';

// ─── Helfer ──────────────────────────────────────────────────────────────────
// Fehler immer sichtbar – nie still schlucken
const safeInit = (name, initFn) => {
  try { initFn(); }
  catch (err) { console.error(`[${name}] Initialisierungsfehler:`, err); }
};

// Prüft ob ein CSS-Selektor im DOM existiert
const has = (selector) => !!document.querySelector(selector);

// ─── Initialisierung ─────────────────────────────────────────────────────────
const initApp = async () => {

  // Kern (immer)
  safeInit('Navigation',    () => new Navigation());
  safeInit('Toggle',       () => new Toggle());
  safeInit('DarkMode',      () => new DarkMode());
  safeInit('CookieConsent', () => {
    const instance = new CookieConsent();
    window.CookieConsent = instance;
  });
  safeInit('Notifications', () => new Notifications());
  safeInit('TopHeader',     () => initTopHeader());

  // Nur initialisieren wenn PHP-Element im DOM (ACF-gesteuert)
  if (has('.back-to-top'))     safeInit('BackToTop',      () => new BackToTop());
  if (has('.scroll-progress')) safeInit('ScrollProgress', () => new ScrollProgress());

  // ── Lazy: nur laden wenn DOM-Element vorhanden ────────────────────────────

  if (has('.accordion, [data-accordion]')) {
    const { default: Accordion } = await import('./components/accordion');
    safeInit('Accordion', () => new Accordion());
  }

  // Hero Slider: Klasse aus PHP → .hero-slider.swiper
  if (has('.hero-slider')) {
    const { default: HeroSlider } = await import('./components/hero-slider');
    safeInit('HeroSlider', () => new HeroSlider());
  }

  // Testimonials: Klasse aus PHP → .testimonials--slider.swiper
  if (has('.testimonials--slider')) {
    const { default: TestimonialsSlider } = await import('./components/testimonials-slider');
    safeInit('TestimonialsSlider', () => new TestimonialsSlider());
  }

  if (has('.logo-carousel')) {
    const { default: LogoCarousel } = await import('./components/logo-carousel');
    safeInit('LogoCarousel', () => new LogoCarousel());
  }

  // Carousel: Klasse aus PHP → .carousel.swiper
  if (has('.carousel')) {
    const { default: Carousel } = await import('./components/carousel');
    safeInit('Carousel', () => new Carousel());
  }

  if (has('.lightbox, [data-lightbox]')) {
    const { default: Lightbox } = await import('./components/lightbox');
    safeInit('Lightbox', () => new Lightbox());
  }

  if (has('.modal, [data-modal]')) {
    const { default: Modal } = await import('./components/modal');
    safeInit('Modal', () => new Modal());
  }

  if (has('.image-comparison')) {
    const { default: ImageComparison } = await import('./components/image-comparison');
    safeInit('ImageComparison', () => new ImageComparison());
  }

  if (has('.stats, .stats-counter, [data-counter]')) {
    const { default: StatsCounter } = await import('./components/stats-counter');
    safeInit('StatsCounter', () => new StatsCounter());
  }

  if (has('.tabs, [data-tabs]')) {
    const { default: Tabs } = await import('./components/tabs');
    safeInit('Tabs', () => new Tabs());
  }

  if (has('.spoiler, [data-spoiler]')) {
    const { default: Spoiler } = await import('./components/spoiler');
    safeInit('Spoiler', () => new Spoiler());
  }

  if (has('.faq-accordion')) {
    await import('./components/faq-accordion');
  }

  if (has('.video-player, [data-video]')) {
    const { default: VideoPlayer } = await import('./components/video-player');
    safeInit('VideoPlayer', () => new VideoPlayer());
  }

  if (has('[data-scroll-animation], .animate-on-scroll')) {
    const { default: ScrollAnimations } = await import('./components/scroll-animations');
    safeInit('ScrollAnimations', () => new ScrollAnimations());
  }

  // ── AJAX / Schwere Features ───────────────────────────────────────────────

  // AJAX Search: Klasse aus PHP → .ajax-search
  if (has('.ajax-search')) {
    await import('./components/ajax-search');
  }

  // Load More: Klasse aus PHP → .posts-load-more
  if (has('.posts-load-more')) {
    await import('./components/load-more');
  }

  // AJAX Filters: Klasse aus PHP → .ajax-filters
  if (has('.ajax-filters')) {
    const { default: AjaxFilters } = await import('./components/ajax-filters');
    safeInit('AjaxFilters', () => new AjaxFilters());
  }

  if (has('.google-map-wrapper, .google-map, [data-map]')) {
    await import('./components/google-maps');
  }
};

// DOM Ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

// Slogan in zweite Spalte des ersten columns-flush Blocks injizieren
document.addEventListener('DOMContentLoaded', () => {
  const firstBlock = document.querySelector('.entry-content .wp-block-columns.columns-flush');
  if (!firstBlock) return;
  const secondCol = firstBlock.querySelectorAll(':scope > .wp-block-column')[1];
  if (!secondCol) return;
  secondCol.style.position = 'relative';
  const img = document.createElement('img');
  img.src = sloganUrl;
  img.alt = 'Tradition trifft Kultur';
  img.className = 'columns-flush__slogan';
  img.setAttribute('aria-hidden', 'true');
  img.setAttribute('loading', 'eager');
  secondCol.appendChild(img);
});

// Carousel Grid: data-columns -> CSS Variable
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.carousel-grid').forEach(grid => {
    const cols = grid.getAttribute('data-columns');
    if (cols) grid.style.setProperty('--carousel-cols', cols);
  });
});
