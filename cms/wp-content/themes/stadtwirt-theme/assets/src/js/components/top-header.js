export default function initTopHeader() {
  const topHeader = document.querySelector('[data-top-header]');
  const toggleBtn = document.querySelector('[data-top-header-toggle]');
  
  if (!topHeader || !toggleBtn) return;
  
  toggleBtn.addEventListener('click', () => {
    const isOpen = topHeader.classList.toggle('is-open');
    toggleBtn.setAttribute('aria-expanded', isOpen);
    
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  });
  
  document.addEventListener('click', (e) => {
    if (!topHeader.contains(e.target) && !toggleBtn.contains(e.target)) {
      topHeader.classList.remove('is-open');
      toggleBtn.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
  });
  
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && topHeader.classList.contains('is-open')) {
      topHeader.classList.remove('is-open');
      toggleBtn.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
  });
}
