/**
 * AJAX Filters Component
 * 
 * Supports multiple instances on the same page.
 * Each container is isolated with its own state.
 */

export default class AjaxFilters {
  constructor() {
    this.containers = document.querySelectorAll('.ajax-filters');
    
    if (this.containers.length === 0) {
      console.log('ℹ️ No AJAX Filters on this page');
      return;
    }
    
    console.log(`✅ AJAX Filters: Found ${this.containers.length} container(s)`);
    this.init();
  }
  
  init() {
    this.containers.forEach(container => {
      // Guard: Skip already initialized containers
      if (container.dataset.initialized === 'true') {
        console.log('⚠️ Container already initialized, skipping:', container.id);
        return;
      }
      
      // Mark as initialized immediately
      container.dataset.initialized = 'true';
      
      this.initContainer(container);
    });
  }
  
  initContainer(container) {
    const settings = {
      postType: container.dataset.postType || 'post',
      postsPerPage: parseInt(container.dataset.postsPerPage) || 12,
      template: container.dataset.template || 'card',
      columns: parseInt(container.dataset.gridColumns) || 3,
    };
    
    container.filterSettings = settings;
    container.currentPage = 1;
    container.isLoading = false;
    container.activeFilters = {
      search: '',
      taxonomies: {},
      meta: {},
      orderby: 'date',
      order: 'DESC'
    };
    
    const elements = {
      sidebar: container.querySelector('.ajax-filters__sidebar'),
      results: container.querySelector('.ajax-filters__results'),
      grid: container.querySelector('.ajax-filters__grid'),
      loading: container.querySelector('.ajax-filters__loading'),
      count: container.querySelector('.ajax-filters__count-number'),
      pagination: container.querySelector('.ajax-filters__pagination'),
      activeList: container.querySelector('.ajax-filters__active-list'),
      activeContainer: container.querySelector('.ajax-filters__active'),
      resetBtn: container.querySelector('.ajax-filters__reset'),
      sortSelect: container.querySelector('.ajax-filters__sort-select'),
    };
    
    container.elements = elements;
    
    this.setupSearchFilter(container);
    this.setupTaxonomyFilters(container);
    this.setupRangeFilters(container);
    this.setupSortFilter(container);
    this.setupResetButton(container);
    
    console.log(`🔄 [${container.id}] Loading initial results for: ${settings.postType}`);
    this.loadResults(container);
  }
  
  // ============================================
  // SEARCH FILTER
  // ============================================
  setupSearchFilter(container) {
    const searchInputs = container.querySelectorAll('.ajax-filter__search-input');
    
    searchInputs.forEach(input => {
      let debounceTimer;
      
      input.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          container.activeFilters.search = e.target.value.trim();
          container.currentPage = 1;
          this.loadResults(container);
        }, 500);
      });
    });
  }
  
  // ============================================
  // TAXONOMY FILTERS
  // ============================================
  setupTaxonomyFilters(container) {
    const checkboxes = container.querySelectorAll('.ajax-filters__taxonomy-checkbox');
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        this.handleTaxonomyChange(container, checkbox);
      });
    });
    
    const buttons = container.querySelectorAll('.ajax-filters__taxonomy-button');
    buttons.forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        button.classList.toggle('is-active');
        const taxonomy = button.dataset.taxonomy;
        const term = button.dataset.term;
        this.toggleTaxonomyTerm(container, taxonomy, term);
        container.currentPage = 1;
        this.loadResults(container);
      });
    });
    
    const dropdowns = container.querySelectorAll('.ajax-filters__taxonomy-select');
    dropdowns.forEach(select => {
      select.addEventListener('change', () => {
        const taxonomy = select.dataset.taxonomy;
        const value = select.value;
        if (value) {
          container.activeFilters.taxonomies[taxonomy] = [value];
        } else {
          delete container.activeFilters.taxonomies[taxonomy];
        }
        container.currentPage = 1;
        this.loadResults(container);
      });
    });
  }
  
  handleTaxonomyChange(container, checkbox) {
    const taxonomy = checkbox.dataset.taxonomy;
    const term = checkbox.value;
    this.toggleTaxonomyTerm(container, taxonomy, term);
    container.currentPage = 1;
    this.loadResults(container);
  }
  
  toggleTaxonomyTerm(container, taxonomy, term) {
    if (!container.activeFilters.taxonomies[taxonomy]) {
      container.activeFilters.taxonomies[taxonomy] = [];
    }
    const index = container.activeFilters.taxonomies[taxonomy].indexOf(term);
    if (index > -1) {
      container.activeFilters.taxonomies[taxonomy].splice(index, 1);
      if (container.activeFilters.taxonomies[taxonomy].length === 0) {
        delete container.activeFilters.taxonomies[taxonomy];
      }
    } else {
      container.activeFilters.taxonomies[taxonomy].push(term);
    }
  }
  
  // ============================================
  // RANGE FILTERS
  // ============================================
  setupRangeFilters(container) {
    const rangeInputs = container.querySelectorAll('.ajax-filters__range-input');
    
    rangeInputs.forEach(input => {
      const key = input.dataset.key;
      const valueDisplay = input.parentElement.querySelector('.ajax-filters__range-value');
      
      input.addEventListener('input', (e) => {
        if (valueDisplay) {
          const prefix = input.dataset.prefix || '';
          const suffix = input.dataset.suffix || '';
          valueDisplay.textContent = prefix + e.target.value + suffix;
        }
      });
      
      input.addEventListener('change', (e) => {
        const value = parseFloat(e.target.value);
        if (!container.activeFilters.meta[key]) {
          container.activeFilters.meta[key] = {};
        }
        container.activeFilters.meta[key].value = value;
        container.currentPage = 1;
        this.loadResults(container);
      });
    });
  }
  
  // ============================================
  // SORT FILTER
  // ============================================
  setupSortFilter(container) {
    const sortSelect = container.elements.sortSelect;
    if (!sortSelect) return;
    
    sortSelect.addEventListener('change', (e) => {
      const value = e.target.value;
      const [orderby, order] = value.split('-');
      container.activeFilters.orderby = orderby;
      container.activeFilters.order = order.toUpperCase();
      this.loadResults(container);
    });
  }
  
  // ============================================
  // RESET BUTTON
  // ============================================
  setupResetButton(container) {
    const resetBtn = container.elements.resetBtn;
    if (!resetBtn) return;
    
    resetBtn.addEventListener('click', (e) => {
      e.preventDefault();
      this.resetFilters(container);
    });
  }
  
  resetFilters(container) {
    container.activeFilters = {
      search: '',
      taxonomies: {},
      meta: {},
      orderby: 'date',
      order: 'DESC'
    };
    container.currentPage = 1;
    
    container.querySelectorAll('.ajax-filter__search-input').forEach(input => {
      input.value = '';
    });
    container.querySelectorAll('.ajax-filters__taxonomy-checkbox').forEach(cb => {
      cb.checked = false;
    });
    container.querySelectorAll('.ajax-filters__taxonomy-button').forEach(btn => {
      btn.classList.remove('is-active');
    });
    container.querySelectorAll('.ajax-filters__taxonomy-select').forEach(sel => {
      sel.selectedIndex = 0;
    });
    container.querySelectorAll('.ajax-filters__range-input').forEach(input => {
      input.value = input.min;
      input.dispatchEvent(new Event('input'));
    });
    if (container.elements.sortSelect) {
      container.elements.sortSelect.value = 'date-desc';
    }
    if (container.elements.resetBtn) {
      container.elements.resetBtn.style.display = 'none';
    }
    if (container.elements.activeContainer) {
      container.elements.activeContainer.style.display = 'none';
    }
    
    this.loadResults(container);
  }
  
  // ============================================
  // LOAD RESULTS
  // ============================================
  async loadResults(container) {
    // Prevent concurrent requests for same container
    if (container.isLoading) {
      console.log(`⚠️ [${container.id}] Already loading, skipping`);
      return;
    }
    
    container.isLoading = true;
    
    const settings = container.filterSettings;
    const filters = container.activeFilters;
    
    this.showLoading(container);
    
    if (!window.customTheme || !window.customTheme.filtersNonce) {
      console.error('❌ Filters nonce missing');
      this.showError(container, 'Configuration error');
      container.isLoading = false;
      return;
    }
    
    const formData = new FormData();
    formData.append('action', 'ajax_filter_posts');
    formData.append('nonce', window.customTheme.filtersNonce);
    formData.append('post_type', settings.postType);
    formData.append('posts_per_page', settings.postsPerPage);
    formData.append('paged', container.currentPage);
    formData.append('template', settings.template);
    
    if (filters.search) {
      formData.append('search', filters.search);
    }
    if (Object.keys(filters.taxonomies).length > 0) {
      formData.append('taxonomies', JSON.stringify(filters.taxonomies));
    }
    if (Object.keys(filters.meta).length > 0) {
      formData.append('meta', JSON.stringify(filters.meta));
    }
    formData.append('orderby', filters.orderby);
    formData.append('order', filters.order);
    
    console.log(`🔄 [${container.id}] Fetching ${settings.postType}, page ${container.currentPage}`);
    
    try {
      const response = await fetch(window.customTheme.ajaxUrl, {
        method: 'POST',
        body: formData
      });
      
      const data = await response.json();
      
      if (data.success) {
        this.renderResults(container, data.data);
        this.updateActiveFilters(container);
      } else {
        this.showError(container, data.data?.message || 'Error loading results');
      }
    } catch (error) {
      console.error(`❌ [${container.id}] AJAX error:`, error);
      this.showError(container, 'Network error');
    } finally {
      container.isLoading = false;
      this.hideLoading(container);
    }
  }
  
  // ============================================
  // RENDER RESULTS
  // ============================================
  renderResults(container, data) {
    const { posts, found_posts, max_pages, current_page } = data;
    const grid = container.elements.grid;
    
    // Always clear grid before rendering
    grid.innerHTML = '';
    
    if (container.elements.count) {
      container.elements.count.textContent = found_posts || 0;
    }
    
    if (posts && posts.length > 0) {
      posts.forEach((post, index) => {
        setTimeout(() => {
          const element = this.renderPost(post, container.filterSettings.template);
          element.style.opacity = '0';
          element.style.transform = 'translateY(20px)';
          grid.appendChild(element);
          
          setTimeout(() => {
            element.style.transition = 'all 0.3s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
          }, 50);
        }, index * 50);
      });
    } else {
      grid.innerHTML = '<div class="ajax-filters__no-results"><p>Keine Ergebnisse gefunden.</p></div>';
    }
    
    this.renderPagination(container, current_page, max_pages);
    
    console.log(`✅ [${container.id}] Rendered ${posts?.length || 0} posts (${found_posts} total)`);
  }
  
  renderPost(post, template) {
    switch (template) {
      case 'job':    return this.renderJobPost(post);
      case 'project': return this.renderProjectPost(post);
      case 'team':   return this.renderTeamPost(post);
      case 'event':  return this.renderEventPost(post);
      default:       return this.renderCardPost(post);
    }
  }
  
  renderCardPost(post) {
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
          <span class="post-card__date">${post.date || ''}</span>
        </div>
        ${post.excerpt ? `<div class="post-card__excerpt">${this.esc(post.excerpt)}</div>` : ''}
        <a href="${post.url}" class="post-card__link">Mehr lesen →</a>
      </div>
    `;
    return article;
  }
  
  renderJobPost(post) {
    const article = document.createElement('article');
    article.className = 'filter-result filter-result--job';
    article.innerHTML = `
      <div class="filter-result__header">
        <h3 class="filter-result__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        ${post.location ? `<div class="filter-result__location">📍 ${this.esc(post.location)}</div>` : ''}
      </div>
      <div class="filter-result__meta">
        ${post.employment_type ? `<span class="filter-result__type">${this.esc(post.employment_type)}</span>` : ''}
      </div>
      ${post.excerpt ? `<div class="filter-result__excerpt">${this.esc(post.excerpt)}</div>` : ''}
      <a href="${post.url}" class="filter-result__button">Details ansehen</a>
    `;
    return article;
  }
  
  renderProjectPost(post) {
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
      </div>
    `;
    return article;
  }
  
  renderTeamPost(post) {
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
      </div>
    `;
    return div;
  }
  renderEventPost(post) {
    const article = document.createElement('article');
    article.className = 'event-card';
    article.innerHTML = `
      ${post.thumbnail ? `
        <div class="event-card__thumbnail">
          <a href="${post.url}">
            <img src="${post.thumbnail}" alt="${this.esc(post.title)}" loading="lazy">
          </a>
          ${post.price ? `<span class="event-card__price">${this.esc(post.price)}</span>` : ''}
        </div>
      ` : ''}
      <div class="event-card__content">
        <h3 class="event-card__title">
          <a href="${post.url}">${this.esc(post.title)}</a>
        </h3>
        <div class="event-card__meta">
          ${post.date_start ? `
            <div class="event-card__date">
              <span>📅</span>
              <span>${this.esc(post.date_start)}${post.date_end ? ' – ' + this.esc(post.date_end) : ''}</span>
            </div>
          ` : ''}
          ${post.location ? `
            <div class="event-card__location">
              <span>📍</span>
              <span>${this.esc(post.location)}</span>
            </div>
          ` : ''}
        </div>
        ${post.excerpt ? `<div class="event-card__excerpt">${this.esc(post.excerpt)}</div>` : ''}
        <a href="${post.url}" class="event-card__link">Details ansehen →</a>
      </div>
    `;
    return article;
  }

  
  // ============================================
  // PAGINATION
  // ============================================
  renderPagination(container, currentPage, maxPages) {
    const paginationEl = container.elements.pagination;
    if (!paginationEl || maxPages <= 1) {
      if (paginationEl) paginationEl.innerHTML = '';
      return;
    }
    
    let html = '<div class="ajax-filters__pagination-list">';
    
    if (currentPage > 1) {
      html += `<button class="ajax-filters__pagination-btn" data-page="${currentPage - 1}">← Zurück</button>`;
    }
    
    for (let i = 1; i <= maxPages; i++) {
      if (i === currentPage) {
        html += `<span class="ajax-filters__pagination-btn is-active">${i}</span>`;
      } else if (i === 1 || i === maxPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
        html += `<button class="ajax-filters__pagination-btn" data-page="${i}">${i}</button>`;
      } else if (i === currentPage - 3 || i === currentPage + 3) {
        html += `<span class="ajax-filters__pagination-dots">...</span>`;
      }
    }
    
    if (currentPage < maxPages) {
      html += `<button class="ajax-filters__pagination-btn" data-page="${currentPage + 1}">Weiter →</button>`;
    }
    
    html += '</div>';
    paginationEl.innerHTML = html;
    
    paginationEl.querySelectorAll('[data-page]').forEach(btn => {
      btn.addEventListener('click', () => {
        container.currentPage = parseInt(btn.dataset.page);
        this.loadResults(container);
        container.elements.results.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }
  
  // ============================================
  // ACTIVE FILTERS
  // ============================================
  updateActiveFilters(container) {
    const activeList = container.elements.activeList;
    const activeContainer = container.elements.activeContainer;
    const resetBtn = container.elements.resetBtn;
    
    if (!activeList || !activeContainer) return;
    
    const filters = container.activeFilters;
    let html = '';
    let hasFilters = false;
    
    if (filters.search) {
      html += `<span class="ajax-filters__active-tag">
        Suche: ${this.esc(filters.search)}
        <button data-remove="search">×</button>
      </span>`;
      hasFilters = true;
    }
    
    for (const [taxonomy, terms] of Object.entries(filters.taxonomies)) {
      if (terms && terms.length > 0) {
        terms.forEach(term => {
          html += `<span class="ajax-filters__active-tag">
            ${this.esc(term)}
            <button data-remove="taxonomy" data-taxonomy="${taxonomy}" data-term="${term}">×</button>
          </span>`;
        });
        hasFilters = true;
      }
    }
    
    activeList.innerHTML = html;
    activeContainer.style.display = hasFilters ? 'block' : 'none';
    if (resetBtn) resetBtn.style.display = hasFilters ? 'block' : 'none';
    
    activeList.querySelectorAll('[data-remove]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        if (btn.dataset.remove === 'search') {
          container.activeFilters.search = '';
          container.querySelectorAll('.ajax-filter__search-input').forEach(i => i.value = '');
        } else if (btn.dataset.remove === 'taxonomy') {
          this.toggleTaxonomyTerm(container, btn.dataset.taxonomy, btn.dataset.term);
          container.querySelectorAll(`.ajax-filters__taxonomy-checkbox[data-taxonomy="${btn.dataset.taxonomy}"][value="${btn.dataset.term}"]`).forEach(cb => cb.checked = false);
          container.querySelectorAll(`.ajax-filters__taxonomy-button[data-taxonomy="${btn.dataset.taxonomy}"][data-term="${btn.dataset.term}"]`).forEach(b => b.classList.remove('is-active'));
        }
        this.loadResults(container);
      });
    });
  }
  
  // ============================================
  // LOADING STATES
  // ============================================
  showLoading(container) {
    if (container.elements.loading) container.elements.loading.style.display = 'flex';
    if (container.elements.grid) container.elements.grid.style.opacity = '0.5';
  }
  
  hideLoading(container) {
    if (container.elements.loading) container.elements.loading.style.display = 'none';
    if (container.elements.grid) container.elements.grid.style.opacity = '1';
  }
  
  showError(container, message) {
    if (container.elements.grid) {
      container.elements.grid.innerHTML = `<div class="ajax-filters__error"><p>${this.esc(message)}</p></div>`;
    }
  }
  
  // ============================================
  // UTILITIES
  // ============================================
  esc(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}
