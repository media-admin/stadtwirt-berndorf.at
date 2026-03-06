# AJAX Features Documentation

**Version:** 1.4.0  
**Letzte Aktualisierung:** 2026-03-04  
**Plugin:** Media Lab Agency Core v1.5.1

Professional AJAX filtering and loading system for dynamic content.

---

## Table of Contents

1. [Overview](#overview)
2. [AJAX Search](#ajax-search)
3. [AJAX Load More](#ajax-load-more)
4. [AJAX Post Filters](#ajax-post-filters)
5. [Implementation Guide](#implementation-guide)
6. [Customization](#customization)
7. [Troubleshooting](#troubleshooting)

---

## Overview

### What Are AJAX Features?

AJAX (Asynchronous JavaScript and XML) allows dynamic content loading without page reload. The Core Plugin provides three professional AJAX systems:

1. **AJAX Search** - Live search with instant results
2. **AJAX Load More** - Infinite scroll / load more pagination
3. **AJAX Filters** - Advanced post filtering

### Provided By

All AJAX features are in **Media Lab Agency Core Plugin**:
```
media-lab-agency-core/inc/
├── ajax-search.php      (Live search)
├── ajax-load-more.php   (Pagination)
└── ajax-filters.php     (Post filtering)
```

### WordPress Actions

Drei AJAX Actions registriert (öffentlich + eingeloggt):
```php
'agency_search'       // Live-Suche        (max. 20 Req/60s pro IP)
'agency_load_more'    // Load More         (max. 30 Req/60s pro IP)
'ajax_filter_posts'   // Post-Filter       (max. 30 Req/60s pro IP)
```

### Rate-Limiting

Alle drei Endpunkte sind durch Transient-basiertes Rate-Limiting geschützt (Security F-03).  
Bei Überschreitung: HTTP 429 mit `{"success": false, "data": {"message": "Too many requests..."}}`

Um Rate-Limiting in eigenen AJAX-Handlern zu nutzen:
```php
if (!medialab_check_rate_limit('meine_action', 20, 60)) {
    wp_send_json_error(['message' => 'Too many requests. Please try again later.'], 429);
}
```

---

## AJAX Search

### Feature Overview

Live search with instant results as user types.

**Benefits:**
- No page reload
- Instant feedback
- Search across multiple post types
- Customizable results template

### Implementation

**1. Add Search Form:**
```html
<form id="ajax-search-form" class="ajax-search">
    <input 
        type="text" 
        name="s" 
        id="search-input"
        placeholder="Search..."
        autocomplete="off"
    >
    <button type="submit">Search</button>
</form>

<div id="search-results" class="search-results"></div>
```

**2. Initialize JavaScript:**
```javascript
// Already included in theme
// JavaScript automatically binds to #ajax-search-form
```

**3. Customize Results Template:**

Results are returned as HTML. Default template:
```php
// In ajax-search.php
foreach ($posts as $post) {
    echo '<div class="search-result">';
    echo '<h3>' . get_the_title($post) . '</h3>';
    echo '<p>' . get_the_excerpt($post) . '</p>';
    echo '<a href="' . get_permalink($post) . '">Read More</a>';
    echo '</div>';
}
```

### Configuration

**Search Parameters:**
```javascript
// Configure in theme's main.js
const searchConfig = {
    minChars: 3,           // Minimum characters
    delay: 300,            // Debounce delay (ms)
    postsPerPage: 5,       // Results per page
    postTypes: ['post', 'page', 'service']  // Post types
};
```

**Modify Query:**
```php
// In functions.php
add_filter('agency_search_query_args', function($args) {
    $args['posts_per_page'] = 10;
    $args['orderby'] = 'relevance';
    return $args;
});
```

### Styling
```css
.ajax-search {
    position: relative;
}

.search-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
}

.search-result {
    padding: 1rem;
    border-bottom: 1px solid #eee;
}

.search-result:hover {
    background: #f8f9fa;
}
```

---

## AJAX Load More

### Feature Overview

Infinite scroll or "Load More" button for paginated content.

**Benefits:**
- Smooth UX without page reload
- SEO-friendly (progressive enhancement)
- Works with any post type
- Customizable loading states

### Implementation

**1. Initial Posts Display:**
```php
<?php
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

$args = array(
    'post_type' => 'post',
    'posts_per_page' => 6,
    'paged' => $paged
);

$query = new WP_Query($args);

if ($query->have_posts()) : ?>
    
    <div id="posts-container" class="posts-grid">
        <?php while ($query->have_posts()) : $query->the_post(); ?>
            
            <article class="post-item">
                <?php the_post_thumbnail('medium'); ?>
                <h2><?php the_title(); ?></h2>
                <?php the_excerpt(); ?>
                <a href="<?php the_permalink(); ?>">Read More</a>
            </article>
            
        <?php endwhile; ?>
    </div>
    
    <?php if ($query->max_num_pages > 1) : ?>
        <button 
            id="load-more" 
            class="btn-load-more"
            data-page="1"
            data-max="<?php echo $query->max_num_pages; ?>"
            data-post-type="post">
            Load More
        </button>
    <?php endif; ?>
    
<?php endif; wp_reset_postdata(); ?>
```

**2. JavaScript Handles Automatically:**
```javascript
// Theme's main.js automatically handles:
// - Button click
// - AJAX request
// - Result appending
// - Button state management
```

**3. Customize Query:**
```php
// Pass custom query args via data attributes
<button 
    id="load-more"
    data-page="1"
    data-max="<?php echo $query->max_num_pages; ?>"
    data-post-type="service"
    data-category="web-design"
    data-posts-per-page="9">
    Load More Services
</button>
```

### Advanced: Infinite Scroll
```javascript
// In theme's main.js
let loading = false;

window.addEventListener('scroll', function() {
    const loadMoreBtn = document.getElementById('load-more');
    
    if (!loadMoreBtn || loading) return;
    
    const rect = loadMoreBtn.getBoundingClientRect();
    const inView = rect.top <= window.innerHeight;
    
    if (inView) {
        loading = true;
        loadMoreBtn.click();
        
        setTimeout(() => {
            loading = false;
        }, 1000);
    }
});
```

### Loading States
```css
.btn-load-more {
    position: relative;
}

.btn-load-more.loading {
    opacity: 0.6;
    pointer-events: none;
}

.btn-load-more.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
```

---

## AJAX Post Filters

### Feature Overview

Advanced filtering system for posts by taxonomy, date, custom fields, etc.

**Benefits:**
- Multiple filter combinations
- Instant results
- URL updates (optional)
- No page reload

### Implementation

**1. Filter Form:**
```html
<form id="post-filters" class="filter-form">
    
    <!-- Category Filter -->
    <select name="category" id="filter-category">
        <option value="">All Categories</option>
        <?php
        $categories = get_terms(['taxonomy' => 'category']);
        foreach ($categories as $cat) {
            echo '<option value="' . $cat->slug . '">' . $cat->name . '</option>';
        }
        ?>
    </select>
    
    <!-- Year Filter -->
    <select name="year" id="filter-year">
        <option value="">All Years</option>
        <option value="2026">2026</option>
        <option value="2025">2025</option>
        <option value="2024">2024</option>
    </select>
    
    <!-- Search -->
    <input 
        type="text" 
        name="search" 
        id="filter-search"
        placeholder="Search...">
    
    <!-- Submit -->
    <button type="submit">Filter</button>
    <button type="reset" id="clear-filters">Clear</button>
    
</form>

<div id="filtered-results" class="posts-grid">
    <!-- Results appear here -->
</div>

<div id="filter-loading" class="loading-spinner" style="display:none;">
    Loading...
</div>
```

**2. Initialize JavaScript:**
```javascript
// Theme's main.js handles automatically
// Binds to #post-filters form
```

**3. Customize Server Response:**
```php
// In ajax-filters.php or via filter
add_filter('agency_filter_query_args', function($args, $filters) {
    
    // Add custom meta query
    if (!empty($filters['custom_field'])) {
        $args['meta_query'] = [
            [
                'key' => 'custom_field',
                'value' => $filters['custom_field'],
                'compare' => '='
            ]
        ];
    }
    
    return $args;
}, 10, 2);
```

### Multiple Post Types
```html
<form id="post-filters">
    <input type="hidden" name="post_type" value="project">
    
    <select name="project_category">
        <option value="">All Projects</option>
        <?php
        $terms = get_terms('project_category');
        foreach ($terms as $term) {
            echo '<option value="' . $term->slug . '">' . $term->name . '</option>';
        }
        ?>
    </select>
</form>
```

### URL Parameter Sync
```javascript
// Update URL with filter parameters
document.getElementById('post-filters').addEventListener('submit', function(e) {
    const formData = new FormData(this);
    const params = new URLSearchParams(formData);
    
    // Update URL
    window.history.pushState({}, '', '?' + params.toString());
});

// Load filters from URL on page load
window.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    urlParams.forEach((value, key) => {
        const input = document.querySelector(`[name="${key}"]`);
        if (input) input.value = value;
    });
    
    // Trigger filter if params exist
    if (urlParams.toString()) {
        document.getElementById('post-filters').dispatchEvent(new Event('submit'));
    }
});
```

---

## Implementation Guide

### Step-by-Step Setup

**1. Verify Core Plugin Active:**
```bash
wp plugin is-active media-lab-agency-core
```

**2. Check AJAX Actions Registered:**
```bash
wp eval '
$actions = ["agency_search", "agency_load_more", "ajax_filter_posts"];
foreach ($actions as $action) {
    $exists = has_action("wp_ajax_" . $action) || has_action("wp_ajax_nopriv_" . $action);
    echo $action . ": " . ($exists ? "✅" : "❌") . "\n";
}
'
```

**3. Verify JavaScript Loaded:**
```html
<!-- In browser console -->
<script>
console.log(typeof ajaxSearch);  // Should not be 'undefined'
</script>
```

**4. Test AJAX Endpoint:**
```bash
# Test search endpoint
curl -X POST \
  "http://yoursite.com/wp-admin/admin-ajax.php" \
  -d "action=agency_search&s=test"
```

---

## Customization

### Custom Result Templates

**Override in Theme:**
```php
// In theme's functions.php
add_filter('agency_search_result_template', function($html, $post) {
    
    $html = '<div class="custom-result">';
    $html .= '<div class="result-image">' . get_the_post_thumbnail($post, 'thumbnail') . '</div>';
    $html .= '<div class="result-content">';
    $html .= '<h4>' . get_the_title($post) . '</h4>';
    $html .= '<p>' . get_the_excerpt($post) . '</p>';
    $html .= '<a href="' . get_permalink($post) . '" class="btn">View</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}, 10, 2);
```

### Custom Loading Indicator
```javascript
// In theme's main.js
document.addEventListener('ajax:start', function() {
    document.getElementById('custom-loader').style.display = 'block';
});

document.addEventListener('ajax:complete', function() {
    document.getElementById('custom-loader').style.display = 'none';
});
```

### Error Handling
```javascript
// In theme's main.js
document.addEventListener('ajax:error', function(e) {
    console.error('AJAX Error:', e.detail);
    
    // Show user-friendly message
    alert('Something went wrong. Please try again.');
});
```

---

## Troubleshooting

### AJAX Not Working

**Check 1: Plugin Active**
```bash
wp plugin is-active media-lab-agency-core
```

**Check 2: JavaScript Console**
```
Open browser DevTools (F12)
Check Console tab for errors
```

**Check 3: Network Tab**
```
Open DevTools → Network tab
Filter: XHR
Submit form/click button
Check if request sent to admin-ajax.php
Check response status (should be 200)
```

### No Results Returned

**Check Query:**
```php
// Add to ajax handler
error_log('Query Args: ' . print_r($args, true));
error_log('Found Posts: ' . $query->found_posts);
```

**Check Response:**
```javascript
// In theme's JS
fetch(ajaxurl, {
    method: 'POST',
    body: formData
})
.then(r => r.text())
.then(data => {
    console.log('Response:', data);  // Debug response
});
```

### 400/500 Errors

**Enable Debug Mode:**
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check: wp-content/debug.log
```

**Check PHP Errors:**
```bash
tail -f /path/to/php-error.log
```

---

## Performance Tips

### 1. Limit Results
```php
$args['posts_per_page'] = 20;  // Don't load too many
```

### 2. Cache Results
```php
// 15-minute cache
$cache_key = 'ajax_search_' . md5($search_query);
$results = get_transient($cache_key);

if (false === $results) {
    $results = /* ... run query ... */;
    set_transient($cache_key, $results, 15 * MINUTE_IN_SECONDS);
}
```

### 3. Debounce Input
```javascript
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(() => {
        // Perform search
    }, 300);  // Wait 300ms after typing stops
});
```

### 4. Lazy Load Images
```php
// In result template
echo '<img src="placeholder.jpg" data-src="' . $image_url . '" class="lazy">';
```

---

## Next Steps

- **Development:** [Development Guide](06_DEVELOPMENT.md)
- **Troubleshooting:** [Troubleshooting Guide](07_TROUBLESHOOTING.md)
- **Custom Post Types:** [CPT Documentation](08_CUSTOM-POST-TYPES.md)

---

**AJAX features ready!** ⚡  
**Next:** [Development Guide](06_DEVELOPMENT.md) →
