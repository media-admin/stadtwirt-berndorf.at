/**
 * Lightbox Component
 */

export default class Lightbox {
  constructor() {
    this.currentIndex = 0;
    this.images = [];
    this.lightbox = null;
    this.init();
  }
  
  init() {
    // Create lightbox container
    this.createLightbox();
    
    // Listen for image clicks
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-lightbox]');
      if (trigger) {
        e.preventDefault();
        this.open(trigger);
      }
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (!this.lightbox.classList.contains('is-active')) return;
      
      if (e.key === 'Escape') this.close();
      if (e.key === 'ArrowLeft') this.prev();
      if (e.key === 'ArrowRight') this.next();
    });
  }
  
  createLightbox() {
    this.lightbox = document.createElement('div');
    this.lightbox.className = 'lightbox';
    this.lightbox.innerHTML = `
      <button class="lightbox__close" aria-label="Close">&times;</button>
      <button class="lightbox__prev" aria-label="Previous">&lsaquo;</button>
      <button class="lightbox__next" aria-label="Next">&rsaquo;</button>
      <div class="lightbox__content">
        <img class="lightbox__image" src="" alt="">
        <div class="lightbox__caption"></div>
      </div>
    `;
    
    document.body.appendChild(this.lightbox);
    
    // Event listeners
    this.lightbox.querySelector('.lightbox__close').addEventListener('click', () => this.close());
    this.lightbox.querySelector('.lightbox__prev').addEventListener('click', () => this.prev());
    this.lightbox.querySelector('.lightbox__next').addEventListener('click', () => this.next());
    this.lightbox.addEventListener('click', (e) => {
      if (e.target === this.lightbox) this.close();
    });
  }
  
  open(trigger) {
    const gallery = trigger.dataset.lightbox;
    
    // Get all images in gallery
    if (gallery) {
      this.images = Array.from(document.querySelectorAll(`[data-lightbox="${gallery}"]`));
      this.currentIndex = this.images.indexOf(trigger);
    } else {
      this.images = [trigger];
      this.currentIndex = 0;
    }
    
    this.showImage();
    this.lightbox.classList.add('is-active');
    document.body.style.overflow = 'hidden';
  }
  
  close() {
    this.lightbox.classList.remove('is-active');
    document.body.style.overflow = '';
  }
  
  showImage() {
    const current = this.images[this.currentIndex];
    const img = this.lightbox.querySelector('.lightbox__image');
    const caption = this.lightbox.querySelector('.lightbox__caption');
    
    img.src = current.href || current.src;
    img.alt = current.alt || '';
    caption.textContent = current.dataset.caption || '';
    
    // Show/hide navigation
    const hasPrev = this.images.length > 1;
    const hasNext = this.images.length > 1;
    this.lightbox.querySelector('.lightbox__prev').style.display = hasPrev ? 'block' : 'none';
    this.lightbox.querySelector('.lightbox__next').style.display = hasNext ? 'block' : 'none';
  }
  
  prev() {
    this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
    this.showImage();
  }
  
  next() {
    this.currentIndex = (this.currentIndex + 1) % this.images.length;
    this.showImage();
  }
}

// Initialize
// new Lightbox();

// Auto-initialize für WordPress Media Library Bilder
document.addEventListener('DOMContentLoaded', () => {
  // Alle verlinkten Bilder finden
  const imageLinks = document.querySelectorAll('a[href$=".jpg"], a[href$=".jpeg"], a[href$=".png"], a[href$=".gif"], a[href$=".webp"]');
  
  imageLinks.forEach((link, index) => {
    // Nur wenn Link ein img-Tag enthält
    const img = link.querySelector('img');
    if (img) {
      // Lightbox-Attribute hinzufügen
      link.setAttribute('data-lightbox', 'wp-gallery');
      
      // Caption aus img alt oder title nehmen
      const caption = img.getAttribute('alt') || img.getAttribute('title') || '';
      if (caption) {
        link.setAttribute('data-caption', caption);
      }
      
      // Verhindere Standard-Link-Verhalten
      link.addEventListener('click', (e) => {
        e.preventDefault();
      });
    }
  });

  // Galerie-Bilder gruppieren
  document.querySelectorAll('.wp-block-gallery').forEach((gallery, galleryIndex) => {
    const links = gallery.querySelectorAll('a[href$=".jpg"], a[href$=".jpeg"], a[href$=".png"]');
    links.forEach(link => {
      link.setAttribute('data-lightbox', `gallery-${galleryIndex}`);
    });
  });
  
  // Lightbox initialisieren
  new Lightbox();
});