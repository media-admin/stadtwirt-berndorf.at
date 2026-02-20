/**
 * AJAX Live Search Component
 */
document.addEventListener('DOMContentLoaded', function() {
    const searchContainers = document.querySelectorAll('.ajax-search');
    
    if (searchContainers.length === 0) return;
    
    searchContainers.forEach(container => {
        const form = container.querySelector('.ajax-search__form');
        const input = container.querySelector('.ajax-search__input');
        const resultsContainer = container.querySelector('.ajax-search__results');
        const loadingIndicator = container.querySelector('.ajax-search__loading');
        const submitButton = container.querySelector('.ajax-search__submit');
        
        if (!input || !resultsContainer || !form) return;
        
        // Get settings from data attributes
        const limit = parseInt(container.dataset.limit) || 5;
        const postTypes = container.dataset.postTypes 
            ? container.dataset.postTypes.split(',').map(type => type.trim()) 
            : ['post', 'page'];
        // const searchPage = container.dataset.searchPage || '/search/';
        
        let debounceTimer;
        let isSubmitting = false;
        
        // ============================================
        // AJAX LIVE SEARCH (on input)
        // ============================================
        input.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(debounceTimer);
            
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                resultsContainer.innerHTML = '';
                return;
            }
            
            // Show loading
            if (loadingIndicator) {
                loadingIndicator.style.display = 'block';
            }
            if (submitButton) {
                submitButton.style.display = 'none';
            }
            
            debounceTimer = setTimeout(() => {
                performSearch(query, resultsContainer, loadingIndicator, submitButton, limit, postTypes);
            }, 300);
        });
        
        // ============================================
        // FORM SUBMIT (Enter oder Button Click)
        // ============================================
        form.addEventListener('submit', function(e) {
            const query = input.value.trim();
            
            if (query.length < 2) {
                e.preventDefault();
                return;
            }
            
            // Let form submit naturally to search page
            // URL will be: /search/?s=query
            isSubmitting = true;
        });
        
        // ============================================
        // CLICK ON SEARCH RESULT (navigate directly)
        // ============================================
        resultsContainer.addEventListener('click', function(e) {
            const link = e.target.closest('.ajax-search__item');
            if (link) {
                // Let the link work naturally
                resultsContainer.style.display = 'none';
            }
        });
        
        // ============================================
        // CLOSE RESULTS (click outside)
        // ============================================
        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                resultsContainer.style.display = 'none';
            }
        });
        
        // ============================================
        // KEYBOARD NAVIGATION (optional)
        // ============================================
        input.addEventListener('keydown', function(e) {
            // ESC = close results
            if (e.key === 'Escape') {
                resultsContainer.style.display = 'none';
                resultsContainer.innerHTML = '';
            }
        });
    });
});

/**
 * Perform AJAX Search
 */
function performSearch(query, resultsContainer, loadingIndicator, submitButton, limit, postTypes) {
    const ajaxUrl = window.customTheme?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = window.customTheme?.searchNonce || '';
    
    fetch(ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'agency_search',
            nonce: nonce,
            query: query,
            post_types: JSON.stringify(postTypes),
            limit: limit
        })
    })
    .then(response => response.json())
    .then(data => {
        // Hide loading, show submit button again
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
        if (submitButton) {
            submitButton.style.display = 'flex';
        }
        
        if (data.success && data.data.results && data.data.results.length > 0) {
            displayResults(data.data.results, resultsContainer);
        } else {
            displayNoResults(resultsContainer);
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        if (loadingIndicator) {
            loadingIndicator.style.display = 'none';
        }
        if (submitButton) {
            submitButton.style.display = 'flex';
        }
        displayError(resultsContainer);
    });
}

/**
 * Display search results
 */
function displayResults(results, container) {
    let html = '<div class="ajax-search__list">';
    
    results.forEach(result => {
        // Post Type Label
        let typeLabel = result.post_type;
        switch(result.post_type) {
            case 'post': typeLabel = 'Beitrag'; break;
            case 'page': typeLabel = 'Seite'; break;
            case 'product': typeLabel = 'Produkt'; break;
            case 'project': typeLabel = 'Projekt'; break;
            case 'service': typeLabel = 'Leistung'; break;
            case 'job': typeLabel = 'Job'; break;
        }
        
        html += `
            <a href="${result.permalink}" class="ajax-search__item ajax-search__item--${result.post_type}">
                ${result.thumbnail ? `
                    <div class="ajax-search__thumbnail">
                        <img src="${result.thumbnail}" alt="${result.title}">
                    </div>
                ` : ''}
                <div class="ajax-search__content">
                    <div class="ajax-search__meta">
                        <span class="ajax-search__type">${typeLabel}</span>
                        ${result.date ? `<span class="ajax-search__date">${result.date}</span>` : ''}
                    </div>
                    <div class="ajax-search__title">${result.title}</div>
                    ${result.excerpt ? `<div class="ajax-search__excerpt">${result.excerpt}</div>` : ''}
                    ${result.price ? `<div class="ajax-search__price">${result.price}</div>` : ''}
                </div>
            </a>
        `;
    });
    
    html += '</div>';
    
    container.innerHTML = html;
    container.style.display = 'block';
}

/**
 * Display no results message
 */
function displayNoResults(container) {
    container.innerHTML = '<div class="ajax-search__no-results">Keine Ergebnisse gefunden.</div>';
    container.style.display = 'block';
}

/**
 * Display error message
 */
function displayError(container) {
    container.innerHTML = '<div class="ajax-search__error">Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.</div>';
    container.style.display = 'block';
}