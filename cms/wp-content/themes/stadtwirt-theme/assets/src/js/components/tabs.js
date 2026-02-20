/**
 * Tabs Component
 */

export default class Tabs {
  constructor() {
    this.tabContainers = document.querySelectorAll('.tabs');
    this.init();
  }
  
  init() {
    if (this.tabContainers.length === 0) return;
    
    this.tabContainers.forEach(container => {
      this.initTabs(container);
    });
  }
  
  initTabs(container) {
    const buttons = container.querySelectorAll('.tabs__button');
    const panels = container.querySelectorAll('.tabs__panel');
    
    buttons.forEach(button => {
      button.addEventListener('click', () => {
        const targetId = button.getAttribute('data-tab');
        this.switchTab(container, targetId, buttons, panels);
      });
      
      // Keyboard navigation
      button.addEventListener('keydown', (e) => {
        this.handleKeyboard(e, buttons);
      });
    });
  }
  
  switchTab(container, targetId, buttons, panels) {
    // Deactivate all
    buttons.forEach(btn => {
      btn.classList.remove('is-active');
      btn.setAttribute('aria-selected', 'false');
    });
    
    panels.forEach(panel => {
      panel.classList.remove('is-active');
      panel.setAttribute('aria-hidden', 'true');
    });
    
    // Activate target
    const targetButton = container.querySelector(`[data-tab="${targetId}"]`);
    const targetPanel = container.querySelector(`#${targetId}`);
    
    if (targetButton && targetPanel) {
      targetButton.classList.add('is-active');
      targetButton.setAttribute('aria-selected', 'true');
      
      targetPanel.classList.add('is-active');
      targetPanel.setAttribute('aria-hidden', 'false');
      
      // Focus management
      targetButton.focus();
    }
  }
  
  handleKeyboard(e, buttons) {
    const currentIndex = Array.from(buttons).indexOf(e.target);
    let targetIndex;
    
    switch(e.key) {
      case 'ArrowLeft':
      case 'ArrowUp':
        e.preventDefault();
        targetIndex = currentIndex - 1;
        if (targetIndex < 0) targetIndex = buttons.length - 1;
        buttons[targetIndex].click();
        break;
        
      case 'ArrowRight':
      case 'ArrowDown':
        e.preventDefault();
        targetIndex = currentIndex + 1;
        if (targetIndex >= buttons.length) targetIndex = 0;
        buttons[targetIndex].click();
        break;
        
      case 'Home':
        e.preventDefault();
        buttons[0].click();
        break;
        
      case 'End':
        e.preventDefault();
        buttons[buttons.length - 1].click();
        break;
    }
  }
}

// Initialize
new Tabs();