/**
 * Navigation Menu (Mobile & Desktop)
 */

export default class Navigation {
  constructor() {
    this.mobileToggle = document.querySelector('.mobile-menu-toggle');
    this.mobileMenu = document.querySelector('.mobile-menu');
    this.mobileOverlay = document.querySelector('.mobile-menu-overlay');
    this.mobileMenuItems = document.querySelectorAll('.mobile-menu .menu-item-has-children');
    
    this.init();
  }
  
  init() {
    if (!this.mobileToggle) return;
    
    // Mobile menu toggle
    this.mobileToggle.addEventListener('click', () => {
      this.toggleMobileMenu();
    });
    
    // Close on overlay click
    if (this.mobileOverlay) {
      this.mobileOverlay.addEventListener('click', () => {
        this.closeMobileMenu();
      });
    }
    
    // Mobile submenu toggles
    this.mobileMenuItems.forEach(item => {
      const link = item.querySelector('a');
      
      link.addEventListener('click', (e) => {
        // Only prevent default if has submenu
        if (item.classList.contains('menu-item-has-children')) {
          e.preventDefault();
          this.toggleSubmenu(item);
        }
      });
    });
    
    // Close menu on ESC
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeMobileMenu();
      }
    });
    
    // Close on window resize to desktop
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 1024) {
        this.closeMobileMenu();
      }
    });
  }
  
  toggleMobileMenu() {
    const isActive = this.mobileMenu.classList.contains('is-active');
    
    if (isActive) {
      this.closeMobileMenu();
    } else {
      this.openMobileMenu();
    }
  }
  
  openMobileMenu() {
    this.mobileToggle.classList.add('is-active');
    this.mobileMenu.classList.add('is-active');
    if (this.mobileOverlay) {
      this.mobileOverlay.classList.add('is-active');
    }
    document.body.style.overflow = 'hidden';
  }
  
  closeMobileMenu() {
    this.mobileToggle.classList.remove('is-active');
    this.mobileMenu.classList.remove('is-active');
    if (this.mobileOverlay) {
      this.mobileOverlay.classList.remove('is-active');
    }
    document.body.style.overflow = '';
    
    // Close all submenus
    this.mobileMenuItems.forEach(item => {
      item.classList.remove('is-open');
    });
  }
  
  toggleSubmenu(item) {
    const isOpen = item.classList.contains('is-open');
    
    // Close other submenus at same level
    const parent = item.parentElement;
    const siblings = parent.querySelectorAll(':scope > .menu-item-has-children');
    siblings.forEach(sibling => {
      if (sibling !== item) {
        sibling.classList.remove('is-open');
      }
    });
    
    // Toggle current submenu
    if (isOpen) {
      item.classList.remove('is-open');
    } else {
      item.classList.add('is-open');
    }
  }
}

// Initialize
new Navigation();