/**
 * Lightbox Component
 * Supports:
 *  - SK-eigene Trigger: [data-lightbox="group-name"]
 *  - WordPress Gallery Block (.wp-lightbox-container) mit Galerie-Navigation
 */

export default class Lightbox {
  constructor() {
    this.currentIndex = 0;
    this.images = [];
    this.lightbox = null;
    this.isZoomed = false;
    this.init();
  }

  init() {
    this.createLightbox();

    // capture: true → feuert vor dem WP Interactivity API
    document.addEventListener('click', (e) => {

      // ── 1. SK-eigene Trigger ─────────────────────────────────────────────
      const trigger = e.target.closest('[data-lightbox]');
      if (trigger) {
        e.preventDefault();
        this.open(trigger);
        return;
      }

      // ── 2. WP Gallery Block ──────────────────────────────────────────────
      const wpTrigger = e.target.closest(
        '.wp-lightbox-container .lightbox-trigger, .wp-lightbox-container img'
      );
      if (wpTrigger) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const container = wpTrigger.closest('.wp-lightbox-container');
        const gallery   = container.closest('.wp-block-gallery');
        this.openWPGallery(container, gallery);
      }

    }, true); // capture: true

    document.addEventListener('keydown', (e) => {
      if (!this.lightbox.classList.contains('is-active')) return;
      if (e.key === 'Escape')     this.close();
      if (e.key === 'ArrowLeft')  this.prev();
      if (e.key === 'ArrowRight') this.next();
    });
  }

  // ── WP Gallery öffnen ────────────────────────────────────────────────────

  openWPGallery(container, gallery) {
    // Alle Container in dieser Galerie sammeln
    const containers = gallery
      ? Array.from(gallery.querySelectorAll('.wp-lightbox-container'))
      : [container];

    // Pseudo-Trigger-Objekte bauen, die showImage() versteht
    this.images = containers.map(c => {
      const img = c.querySelector('img');

      // Größtes Bild aus srcset wählen, Fallback: src
      let fullSrc = img.src;
      if (img.srcset) {
        const parts = img.srcset
          .split(',')
          .map(s => s.trim().split(/\s+/));
        const largest = parts.reduce((a, b) => {
          const aW = parseInt(a[1]) || 0;
          const bW = parseInt(b[1]) || 0;
          return bW > aW ? b : a;
        });
        if (largest[0]) fullSrc = largest[0];
      }

      return {
        href:    fullSrc,
        src:     fullSrc,
        alt:     img.alt || '',
        dataset: { caption: '' },
      };
    });

    this.currentIndex = containers.indexOf(container);
    if (this.currentIndex < 0) this.currentIndex = 0;

    this.resetZoom();
    this.showImage();
    this.lightbox.classList.add('is-active');
    document.body.style.overflow = 'hidden';
  }

  // ── SK-eigene Trigger öffnen ─────────────────────────────────────────────

  open(trigger) {
    const gallery = trigger.dataset.lightbox;
    if (gallery) {
      this.images = Array.from(
        document.querySelectorAll(`[data-lightbox="${gallery}"]`)
      );
      this.currentIndex = this.images.indexOf(trigger);
    } else {
      this.images = [trigger];
      this.currentIndex = 0;
    }
    this.resetZoom();
    this.showImage();
    this.lightbox.classList.add('is-active');
    document.body.style.overflow = 'hidden';
  }

  // ── Lightbox DOM aufbauen ────────────────────────────────────────────────

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
      <div class="lightbox__zoom-hint">Doppelklick zum Zoomen</div>
    `;

    document.body.appendChild(this.lightbox);

    const img = this.lightbox.querySelector('.lightbox__image');

    // Doppelklick: Zoom togglen
    img.addEventListener('dblclick', (e) => {
      e.stopPropagation();
      this.toggleZoom(e);
    });

    // Gezoomtes Bild verschieben
    img.addEventListener('mousemove', (e) => {
      if (!this.isZoomed) return;
      const rect = img.getBoundingClientRect();
      const x = ((e.clientX - rect.left) / rect.width  - 0.5) * -60;
      const y = ((e.clientY - rect.top)  / rect.height - 0.5) * -60;
      img.style.transform = `scale(2.5) translate(${x}px, ${y}px)`;
    });

    // Touch: Pinch-to-Zoom
    this.initPinchZoom(img);

    this.lightbox.querySelector('.lightbox__close').addEventListener('click', () => this.close());
    this.lightbox.querySelector('.lightbox__prev').addEventListener('click', () => this.prev());
    this.lightbox.querySelector('.lightbox__next').addEventListener('click', () => this.next());

    this.lightbox.addEventListener('click', (e) => {
      if (e.target === this.lightbox) this.close();
      if (this.isZoomed) this.resetZoom();
    });
  }

  // ── Zoom ─────────────────────────────────────────────────────────────────

  toggleZoom(e) {
    if (this.isZoomed) {
      this.resetZoom();
    } else {
      const img = this.lightbox.querySelector('.lightbox__image');
      this.isZoomed = true;
      img.classList.add('is-zoomed');
      this.lightbox.classList.add('is-zoomed');
    }
  }

  resetZoom() {
    const img = this.lightbox.querySelector('.lightbox__image');
    this.isZoomed = false;
    img.style.transform = '';
    img.classList.remove('is-zoomed');
    this.lightbox.classList.remove('is-zoomed');
  }

  initPinchZoom(img) {
    let startDist  = 0;
    let startScale = 1;
    let currentScale = 1;

    img.addEventListener('touchstart', (e) => {
      if (e.touches.length === 2) {
        startDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        startScale = currentScale;
      }
    }, { passive: true });

    img.addEventListener('touchmove', (e) => {
      if (e.touches.length === 2) {
        e.preventDefault();
        const dist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        currentScale = Math.min(Math.max(startScale * (dist / startDist), 1), 4);
        img.style.transform = `scale(${currentScale})`;
        this.isZoomed = currentScale > 1;
        img.classList.toggle('is-zoomed', this.isZoomed);
        this.lightbox.classList.toggle('is-zoomed', this.isZoomed);
      }
    }, { passive: false });

    img.addEventListener('touchend', () => {
      if (currentScale <= 1) this.resetZoom();
    });
  }

  // ── Navigation ───────────────────────────────────────────────────────────

  close() {
    this.resetZoom();
    this.lightbox.classList.remove('is-active');
    document.body.style.overflow = '';
  }

  showImage() {
    const current = this.images[this.currentIndex];
    const img     = this.lightbox.querySelector('.lightbox__image');
    const caption = this.lightbox.querySelector('.lightbox__caption');

    img.src     = current.href || current.src;
    img.alt     = current.alt || '';
    caption.textContent = current.dataset.caption || '';

    const hasPrev = this.images.length > 1;
    this.lightbox.querySelector('.lightbox__prev').style.display = hasPrev ? 'block' : 'none';
    this.lightbox.querySelector('.lightbox__next').style.display = hasPrev ? 'block' : 'none';
  }

  prev() {
    this.resetZoom();
    this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
    this.showImage();
  }

  next() {
    this.resetZoom();
    this.currentIndex = (this.currentIndex + 1) % this.images.length;
    this.showImage();
  }
}