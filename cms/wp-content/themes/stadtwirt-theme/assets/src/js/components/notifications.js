/**
 * Notifications - Banner, Popup, Toast
 */
export default class Notifications {
  constructor() {
    this.container = null;
    this.init();
  }

  init() {
    this.createToastContainer();
    this.handleDismissButtons();

    // Programmatisch via window.showNotification()
    window.showNotification = (message, type = 'info', duration = 5000) => {
      this.showToast(message, type, duration);
    };

    // CPT-Notifications aus PHP laden
    if (window.mediaLabNotifications) {
      this.initFromCPT(window.mediaLabNotifications);
    }
  }

  // ─── Container ───────────────────────────────────────────────

  createToastContainer() {
    if (!document.querySelector('.notification-toast-container')) {
      this.container = document.createElement('div');
      this.container.className = 'notification-toast-container';
      document.body.appendChild(this.container);
    } else {
      this.container = document.querySelector('.notification-toast-container');
    }
  }

  // ─── CPT Init ────────────────────────────────────────────────

  initFromCPT(data) {
    // Popups (nach delay)
    if (data.popups && data.popups.length) {
      data.popups.forEach(n => {
        const key = `popup_dismissed_${n.id}`;
        if (sessionStorage.getItem(key)) return; // bereits weggeklickt
        const delay = (n.delay || 3) * 1000;
        setTimeout(() => this.showPopup(n), delay);
      });
    }

    // Toasts
    if (data.toasts && data.toasts.length) {
      data.toasts.forEach((n, i) => {
        if (this.isDismissed(n.id)) return;
        setTimeout(() => this.showToast(n.message, n.type, 6000, n.title, n.id), i * 800);
      });
    }
  }

  // ─── Dismiss ─────────────────────────────────────────────────

  handleDismissButtons() {
    document.addEventListener('click', (e) => {
      if (
        e.target.classList.contains('notification__close') ||
        e.target.classList.contains('notification__dismiss')
      ) {
        const notification = e.target.closest('.notification');
        if (notification) this.dismiss(notification);
      }

      // Popup Overlay klick
      if (e.target.classList.contains('notification-popup__overlay')) {
        this.closePopup();
      }
    });

    // ESC schließt Popup
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') this.closePopup();
    });
  }

  isDismissed(id) {
    return id && sessionStorage.getItem(`notification_dismissed_${id}`);
  }

  markDismissed(id) {
    if (id) sessionStorage.setItem(`notification_dismissed_${id}`, '1');
  }

  dismiss(notification) {
    const id = notification.dataset.notificationId;
    this.markDismissed(id);
    notification.classList.add('notification--dismissed');
    setTimeout(() => notification.remove(), 350);
  }

  // ─── TOAST ───────────────────────────────────────────────────

  showToast(message, type = 'info', duration = 5000, title = '', id = null) {
    const icons = {
      success: 'dashicons-yes-alt',
      error:   'dashicons-dismiss',
      warning: 'dashicons-warning',
      info:    'dashicons-info',
    };

    const toast = document.createElement('div');
    toast.className = `notification notification--toast notification--${type}`;
    if (id) toast.dataset.notificationId = id;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <div class="notification__icon">
        <span class="dashicons ${icons[type] || icons.info}"></span>
      </div>
      <div class="notification__content">
        ${title ? `<div class="notification__title">${title}</div>` : ''}
        <div class="notification__message">${message}</div>
      </div>
      <button class="notification__close" aria-label="Schließen">&times;</button>
    `;

    this.container.appendChild(toast);

    // Einblenden
    requestAnimationFrame(() => toast.classList.add('notification--visible'));

    // Auto dismiss
    if (duration > 0) {
      setTimeout(() => this.dismiss(toast), duration);
    }
  }

  // ─── POPUP ───────────────────────────────────────────────────

  showPopup(n) {
    if (document.querySelector('.notification-popup__overlay')) return;

    const icons = {
      success: 'dashicons-yes-alt',
      error:   'dashicons-dismiss',
      warning: 'dashicons-warning',
      info:    'dashicons-info',
    };

    const overlay = document.createElement('div');
    overlay.className = 'notification-popup__overlay';
    overlay.innerHTML = `
      <div class="notification-popup notification--${n.type}" role="dialog" aria-modal="true" data-id="${n.id}">
        <button class="notification-popup__close" aria-label="Schließen">&times;</button>
        <div class="notification-popup__icon">
          <span class="dashicons ${icons[n.type] || icons.info}"></span>
        </div>
        <div class="notification-popup__content">
          ${n.title ? `<h3 class="notification-popup__title">${n.title}</h3>` : ''}
          <div class="notification-popup__message">${n.message}</div>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);
    document.body.classList.add('popup-open');

    requestAnimationFrame(() => overlay.classList.add('notification-popup__overlay--visible'));

    overlay.querySelector('.notification-popup__close').addEventListener('click', () => {
      this.closePopup();
    });
  }

  closePopup() {
    const overlay = document.querySelector('.notification-popup__overlay');
    if (!overlay) return;

    // Session: nicht mehr anzeigen
    const popup = overlay.querySelector('.notification-popup');
    if (popup && popup.dataset.id) {
      sessionStorage.setItem(`popup_dismissed_${popup.dataset.id}`, '1');
    }

    overlay.classList.remove('notification-popup__overlay--visible');
    document.body.classList.remove('popup-open');
    setTimeout(() => overlay.remove(), 350);
  }
}
