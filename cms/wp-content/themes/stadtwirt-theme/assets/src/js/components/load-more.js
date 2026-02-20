/**
 * Load More Component
 */

export default class LoadMore {
  constructor() {
    this.buttons = document.querySelectorAll('.posts-load-more__button');
    
    if (this.buttons.length === 0) {
      return;
    }
    
    console.log(`✅ Load More: Found ${this.buttons.length} button(s)`);
    this.init();
  }
  
  init() {
    this.buttons.forEach((button, index) => {
      console.log(`Setting up button ${index + 1}`);
      
      button.addEventListener('click', () => {
        this.loadMore(button);
      });
    });
  }
  
  async loadMore(button) {
    console.log('=== LOAD MORE CLICKED ===');
    
    // Prevent double clicks
    if (button.classList.contains('is-loading')) {
      return;
    }
    
    // Find container relative to THIS button
    const parent = button.closest('.posts-load-more');
    
    if (!parent) {
      console.error('❌ Parent .posts-load-more not found');
      alert('Container parent nicht gefunden!');
      return;
    }
    
    const container = parent.querySelector('.posts-load-more__grid');
    
    if (!container) {
      console.error('❌ Grid not found in parent');
      alert('Grid nicht gefunden!');
      return;
    }
    
    console.log('✅ Container found successfully');
    
    // Check customTheme
    if (typeof window.customTheme === 'undefined') {
      console.error('❌ customTheme not defined');
      alert('Configuration error');
      return;
    }
    
    if (!window.customTheme.loadMoreNonce) {
      console.error('❌ loadMoreNonce missing');
      alert('Security token missing');
      return;
    }
    
    // Get settings
    const postType = button.dataset.postType || 'post';
    const postsPerPage = parseInt(button.dataset.postsPerPage) || 6;
    const category = button.dataset.category || '';
    const orderby = button.dataset.orderby || 'date';
    const order = button.dataset.order || 'DESC';
    const template = button.dataset.template || 'card';
    const maxPages = parseInt(button.dataset.maxPages) || 1;
    let currentPage = parseInt(button.dataset.currentPage) || 1;
    const buttonText = button.dataset.buttonText || 'Mehr laden';
    const loadingText = button.dataset.loadingText || 'Lädt...';
    const nextPage = currentPage + 1;
    
    console.log('Settings:', { postType, postsPerPage, currentPage, nextPage, maxPages, template });
    
    // Update button
    button.classList.add('is-loading');
    const buttonTextEl = button.querySelector('.posts-load-more__button-text');
    if (buttonTextEl) {
      buttonTextEl.textContent = loadingText;
    }
    
    try {
      console.log(`Fetching page ${nextPage} of ${maxPages}...`);
      
      const response = await fetch(window.customTheme.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'agency_load_more',
          nonce: window.customTheme.loadMoreNonce,
          post_type: postType,
          posts_per_page: postsPerPage,
          page: nextPage,
          category: category,
          orderby: orderby,
          order: order,
          template: template,
        }),
      });
      
      const data = await response.json();
      console.log('Response:', data);
      
      if (data.success && data.data.posts && data.data.posts.length > 0) {
        console.log(`✅ Loaded ${data.data.posts.length} posts`);
        
        // Render posts
        data.data.posts.forEach((post, index) => {
          setTimeout(() => {
            const element = this.renderPost(post, template);
            element.style.opacity = '0';
            element.style.transform = 'translateY(20px)';
            container.appendChild(element);
            
            setTimeout(() => {
              element.style.transition = 'all 0.5s ease';
              element.style.opacity = '1';
              element.style.transform = 'translateY(0)';
            }, 50);
          }, index * 100);
        });
        
        // Update page
        button.dataset.currentPage = nextPage;
        
        // Hide if no more
        if (nextPage >= maxPages) {
          button.parentElement.style.display = 'none';
        }
      } else {
        console.log('No more posts');
        button.parentElement.style.display = 'none';
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Fehler beim Laden');
    } finally {
      button.classList.remove('is-loading');
      if (buttonTextEl) {
        buttonTextEl.textContent = buttonText;
      }
    }
  }
  
  renderPost(post, template) {
    switch (template) {
      case 'team':
        return this.renderTeam(post);
      case 'project':
        return this.renderProject(post);
      case 'list':
        return this.renderList(post);
      default:
        return this.renderCard(post);
    }
  }
  
  renderCard(post) {
    const article = document.createElement('article');
    article.className = 'post-card';
    
    article.innerHTML = `
      ${post.thumbnail ? `
        <div class="post-card__thumbnail">
          <a href="${post.url}">
            <img src="${post.thumbnail}" alt="${this.esc(post.title)}" loading="lazy">
          </a>
        </div>
      ` : ''}
      <div class="post-card__content">
        <h3 class="post-card__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        <div class="post-card__meta">
          <span class="post-card__date">${post.date}</span>
        </div>
        <div class="post-card__excerpt">
          ${this.esc(post.excerpt)}
        </div>
        <a href="${post.url}" class="post-card__link">
          Mehr lesen →
        </a>
      </div>
    `;
    
    return article;
  }
  
  renderTeam(post) {
    const div = document.createElement('div');
    div.className = 'team-card';
    
    div.innerHTML = `
      ${post.thumbnail ? `
        <div class="team-card__image">
          <img src="${post.thumbnail}" alt="${this.esc(post.title)}" loading="lazy">
        </div>
      ` : ''}
      <div class="team-card__content">
        <h3 class="team-card__name">${this.esc(post.title)}</h3>
        ${post.role ? `<p class="team-card__role">${this.esc(post.role)}</p>` : ''}
        ${post.email ? `<a href="mailto:${post.email}" class="team-card__email">${this.esc(post.email)}</a>` : ''}
      </div>
    `;
    
    return div;
  }
  
  renderProject(post) {
    const article = document.createElement('article');
    article.className = 'project-card';
    
    article.innerHTML = `
      ${post.thumbnail_large ? `
        <div class="project-card__image">
          <a href="${post.url}">
            <img src="${post.thumbnail_large}" alt="${this.esc(post.title)}" loading="lazy">
          </a>
        </div>
      ` : ''}
      <div class="project-card__content">
        <h3 class="project-card__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        ${post.client ? `<p class="project-card__client">Client: ${this.esc(post.client)}</p>` : ''}
        ${post.project_date ? `<p class="project-card__date">${this.esc(post.project_date)}</p>` : ''}
      </div>
    `;
    
    return article;
  }
  
  renderList(post) {
    const article = document.createElement('article');
    article.className = 'post-list-item';
    
    article.innerHTML = `
      <div class="post-list-item__content">
        <h3 class="post-list-item__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        <div class="post-list-item__meta">
          <span class="post-list-item__date">${post.date}</span>
        </div>
        <div class="post-list-item__excerpt">
          ${this.esc(post.excerpt)}
        </div>
      </div>
      ${post.thumbnail ? `
        <div class="post-list-item__thumbnail">
          <a href="${post.url}">
            <img src="${post.thumbnail}" alt="${this.esc(post.title)}" loading="lazy">
          </a>
        </div>
      ` : ''}
    `;
    
    return article;
  }
  
  esc(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 LoadMore: Initializing after DOMContentLoaded');
    new LoadMore();
  });
} else {
  console.log('🚀 LoadMore: DOM already ready, initializing now');
  new LoadMore();
}