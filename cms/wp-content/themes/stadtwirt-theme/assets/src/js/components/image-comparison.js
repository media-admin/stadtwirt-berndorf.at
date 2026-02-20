/**
 * Image Comparison Slider
 */

export default class ImageComparison {
  constructor() {
    this.comparisons = document.querySelectorAll('.image-comparison');
    this.init();
  }
  
  init() {
    if (this.comparisons.length === 0) return;
    
    this.comparisons.forEach(comparison => {
      this.initComparison(comparison);
    });
  }
  
  initComparison(comparison) {
    const slider = comparison.querySelector('.image-comparison__slider');
    const afterImage = comparison.querySelector('.image-comparison__after');
    const wrapper = comparison.querySelector('.image-comparison__wrapper');
    const isVertical = comparison.classList.contains('image-comparison--vertical');
    
    // Get initial position
    const initialPosition = parseInt(comparison.dataset.position) || 50;
    this.setPosition(comparison, initialPosition);
    
    let isDragging = false;
    
    // Mouse events
    slider.addEventListener('mousedown', (e) => {
      isDragging = true;
      e.preventDefault();
    });
    
    document.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      this.handleMove(e, comparison, wrapper, isVertical);
    });
    
    document.addEventListener('mouseup', () => {
      isDragging = false;
    });
    
    // Touch events
    slider.addEventListener('touchstart', (e) => {
      isDragging = true;
    });
    
    document.addEventListener('touchmove', (e) => {
      if (!isDragging) return;
      this.handleMove(e.touches[0], comparison, wrapper, isVertical);
    });
    
    document.addEventListener('touchend', () => {
      isDragging = false;
    });
    
    // Click anywhere on wrapper to move slider
    wrapper.addEventListener('click', (e) => {
      if (e.target === slider || slider.contains(e.target)) return;
      this.handleMove(e, comparison, wrapper, isVertical);
    });
  }
  
  handleMove(e, comparison, wrapper, isVertical) {
    const rect = wrapper.getBoundingClientRect();
    let position;
    
    if (isVertical) {
      const y = e.clientY - rect.top;
      position = (y / rect.height) * 100;
    } else {
      const x = e.clientX - rect.left;
      position = (x / rect.width) * 100;
    }
    
    // Clamp between 0 and 100
    position = Math.max(0, Math.min(100, position));
    
    this.setPosition(comparison, position);
  }
  
  setPosition(comparison, position) {
    const slider = comparison.querySelector('.image-comparison__slider');
    const afterImage = comparison.querySelector('.image-comparison__after');
    const isVertical = comparison.classList.contains('image-comparison--vertical');
    
    if (isVertical) {
      slider.style.top = position + '%';
      afterImage.style.clipPath = `inset(${position}% 0 0 0)`;
    } else {
      slider.style.left = position + '%';
      afterImage.style.clipPath = `inset(0 0 0 ${position}%)`;
    }
  }
}

// Initialize
new ImageComparison();