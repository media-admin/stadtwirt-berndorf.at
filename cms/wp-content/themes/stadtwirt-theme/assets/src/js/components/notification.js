/**
 * Notification Component
 */

export default class Notification {
  constructor() {
    this.notifications = document.querySelectorAll('.notification--dismissible');
    this.init();
  }
  
  init() {
    if (this.notifications.length === 0) return;
    
    this.notifications.forEach(notification => {
      this.initDismiss(notification);
    });
  }
  
  initDismiss(notification) {
    const dismissButton = notification.querySelector('.notification__dismiss');
    
    if (!dismissButton) return;
    
    dismissButton.addEventListener('click', () => {
      this.dismiss(notification);
    });
  }
  
  dismiss(notification) {
    // Add dismissed class for animation
    notification.classList.add('notification--dismissed');
    
    // Remove from DOM after animation
    setTimeout(() => {
      notification.remove();
    }, 300);
  }
  
  // Static method to create notifications programmatically
  static create(options = {}) {
    const {
      type = 'info',
      title = '',
      message = '',
      dismissible = true,
      duration = null, // Auto-dismiss after X ms (null = no auto-dismiss)
      container = document.body,
    } = options;
    
    const notification = document.createElement('div');
    notification.className = `notification notification--${type}${dismissible ? ' notification--dismissible' : ''}`;
    notification.setAttribute('role', 'alert');
    
    // Icon
    const icons = {
      info: 'dashicons-info',
      success: 'dashicons-yes-alt',
      warning: 'dashicons-warning',
      error: 'dashicons-dismiss',
    };
    
    const icon = icons[type] || icons.info;
    
    notification.innerHTML = `
      <div class="notification__icon">
        <span class="dashicons ${icon}"></span>
      </div>
      <div class="notification__content">
        ${title ? `<div class="notification__title">${title}</div>` : ''}
        <div class="notification__message">${message}</div>
      </div>
      ${dismissible ? '<button class="notification__dismiss" aria-label="Schließen">&times;</button>' : ''}
    `;
    
    container.appendChild(notification);
    
    // Initialize dismiss functionality
    if (dismissible) {
      const notificationInstance = new Notification();
      notificationInstance.initDismiss(notification);
    }
    
    // Auto-dismiss
    if (duration) {
      setTimeout(() => {
        notification.classList.add('notification--dismissed');
        setTimeout(() => notification.remove(), 300);
      }, duration);
    }
    
    return notification;
  }
}

// Initialize
new Notification();

// Make available globally for programmatic use
window.Notification = Notification;