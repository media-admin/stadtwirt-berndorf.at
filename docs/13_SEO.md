# SEO Documentation

**Version:** 1.4.0  
**Letzte Aktualisierung:** 2026-03-04  
**Plugin:** Media Lab SEO Toolkit v1.1.0

Complete guide for the SEO plugin with Schema.org, Open Graph, and Twitter Cards.

---

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Schema.org Markup](#schemaorg-markup)
4. [Open Graph Tags](#open-graph-tags)
5. [Twitter Cards](#twitter-cards)
6. [Breadcrumbs](#breadcrumbs)
7. [Canonical URLs](#canonical-urls)
8. [Testing & Validation](#testing--validation)
9. [Troubleshooting](#troubleshooting)

---

## Overview

### What is Media Lab SEO?

Comprehensive SEO plugin that provides:
- Schema.org structured data
- Open Graph tags (Facebook/LinkedIn)
- Twitter Cards
- Canonical URLs
- Breadcrumbs
- Meta management

### Features

✅ **Schema.org** - 5 schema types  
✅ **Open Graph** - Social sharing  
✅ **Twitter Cards** - Rich Twitter previews  
✅ **Breadcrumbs** - Navigation hierarchy  
✅ **Canonical** - Prevent duplicate content  
✅ **Lightweight** - No bloat

---

## Installation

### 1. Activate Plugin
```bash
wp plugin activate media-lab-seo
```

### 2. Configure Settings

**Admin:** Settings → SEO Toolkit
```
✅ Enable SEO Features
✅ Schema.org Markup
✅ Open Graph Tags
✅ Twitter Cards

Site Name: Your Company Name
Twitter Username: @yourhandle
Default Social Image: https://yoursite.com/og-image.jpg
```

### 3. Verify Installation
```bash
curl -sL https://yoursite.com/ | grep -E "schema.org|og:|twitter:"
```

---

## Schema.org Markup

### What is Schema.org?

Structured data that helps search engines understand your content.

**Benefits:**
- Rich snippets in search results
- Better click-through rates
- Voice search optimization
- Knowledge graph inclusion

### Schema Types Supported

#### 1. Organization (Homepage)

**Automatically added to homepage:**
```json
{
  "@type": "Organization",
  "@id": "https://yoursite.com/#organization",
  "name": "Your Company Name",
  "url": "https://yoursite.com/",
  "logo": "https://yoursite.com/logo.png"
}
```

**Logo:** Uses WordPress Site Logo (Customizer → Site Identity)

#### 2. WebSite (Site-wide)

**Added to all pages:**
```json
{
  "@type": "WebSite",
  "@id": "https://yoursite.com/#website",
  "url": "https://yoursite.com/",
  "name": "Your Company Name",
  "description": "Site tagline",
  "publisher": {
    "@id": "https://yoursite.com/#organization"
  },
  "potentialAction": {
    "@type": "SearchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "https://yoursite.com/?s={search_term_string}"
    },
    "query-input": "required name=search_term_string"
  }
}
```

**SearchAction:** Enables site search in Google

#### 3. Article (Blog Posts)

**Added to single posts:**
```json
{
  "@type": "Article",
  "headline": "Post Title",
  "description": "Post excerpt",
  "image": "https://yoursite.com/featured-image.jpg",
  "datePublished": "2026-02-16T10:00:00+00:00",
  "dateModified": "2026-02-16T15:30:00+00:00",
  "author": {
    "@type": "Person",
    "name": "Author Name"
  },
  "publisher": {
    "@id": "https://yoursite.com/#organization"
  }
}
```

#### 4. Product (WooCommerce)

**Added to WooCommerce products:**
```json
{
  "@type": "Product",
  "name": "Product Name",
  "description": "Product description",
  "image": "https://yoursite.com/product-image.jpg",
  "sku": "SKU123",
  "offers": {
    "@type": "Offer",
    "price": "99.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock",
    "url": "https://yoursite.com/product/"
  }
}
```

#### 5. BreadcrumbList (Navigation)

**Added to all non-homepage pages:**
```json
{
  "@type": "BreadcrumbList",
  "@id": "#breadcrumb",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "https://yoursite.com/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Blog",
      "item": "https://yoursite.com/blog/"
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "Post Title",
      "item": "https://yoursite.com/blog/post-title/"
    }
  ]
}
```

### Output Format

**JSON-LD in <head>:**
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    { ... Organization ... },
    { ... WebSite ... },
    { ... Article ... },
    { ... BreadcrumbList ... }
  ]
}
</script>
```

---

## Open Graph Tags

### What is Open Graph?

Meta tags for rich social sharing on Facebook, LinkedIn, etc.

**Example:**
```html
<meta property="og:title" content="Page Title">
<meta property="og:description" content="Page description">
<meta property="og:image" content="https://yoursite.com/image.jpg">
<meta property="og:url" content="https://yoursite.com/page/">
```

### Tags Generated

**All Pages:**
- `og:site_name` - Site name
- `og:type` - website or article
- `og:url` - Current page URL
- `og:title` - Page title
- `og:description` - Page excerpt/description
- `og:locale` - Site language

**With Images:**
- `og:image` - Featured image or default
- `og:image:width` - Image width
- `og:image:height` - Image height

### Image Requirements

**Recommended Size:** 1200x630px

**Set Default Image:**
```
Settings → SEO Toolkit
Default Social Image: Upload 1200x630px image
```

**Per-Post Image:**
- Uses Featured Image if available
- Falls back to default image

### Preview

**Facebook Debugger:**
- https://developers.facebook.com/tools/debug/
- Enter your URL
- Check preview

**LinkedIn Post Inspector:**
- https://www.linkedin.com/post-inspector/
- Enter your URL
- Check preview

---

## Twitter Cards

### What are Twitter Cards?

Meta tags for rich previews on Twitter.

**Types:**
- `summary` - Small image + text
- `summary_large_image` - Large image + text

### Tags Generated
```html
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@yourhandle">
<meta name="twitter:title" content="Page Title">
<meta name="twitter:description" content="Page description">
<meta name="twitter:image" content="https://yoursite.com/image.jpg">
```

### Configuration

**Set Twitter Username:**
```
Settings → SEO Toolkit
Twitter Username: @yourhandle
```

**Card Type:**
- With featured image: `summary_large_image`
- Without image: `summary`

### Preview

**Twitter Card Validator:**
- https://cards-dev.twitter.com/validator
- Enter your URL
- Check preview

---

## Breadcrumbs

### What are Breadcrumbs?

Navigation hierarchy showing page location.

**Example:**
```
Home › Blog › Category › Post Title
```

### Usage in Templates
```php
<?php
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs();
}
?>
```

### Customization
```php
<?php
medialab_seo_breadcrumbs([
    'separator' => ' › ',           // Separator between items
    'home_title' => 'Home',        // Home link text
    'wrapper_class' => 'breadcrumbs', // Wrapper CSS class
    'item_class' => 'breadcrumb-item', // Item CSS class
    'show_current' => true         // Show current page
]);
?>
```

### Styling
```css
.breadcrumbs {
    padding: 1rem 0;
    font-size: 0.875rem;
}

.breadcrumb-list {
    display: flex;
    flex-wrap: wrap;
    list-style: none;
    margin: 0;
    padding: 0;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
}

.breadcrumb-item a {
    color: #666;
    text-decoration: none;
}

.breadcrumb-item a:hover {
    color: #333;
    text-decoration: underline;
}

.breadcrumb-item.current {
    color: #333;
    font-weight: 500;
}

.separator {
    margin: 0 0.5rem;
    color: #999;
}
```

### Hierarchy Examples

**Blog Post:**
```
Home › Blog › Post Title
```

**Page with Parent:**
```
Home › About › Team › John Doe
```

**Custom Post Type:**
```
Home › Projects › Web Design › Project Name
```

**Archive:**
```
Home › Projects
```

---

## Canonical URLs

### What are Canonical URLs?

`<link rel="canonical">` tells search engines the preferred URL for a page.

**Prevents duplicate content issues.**

### Automatic Generation

**Homepage:**
```html
<link rel="canonical" href="https://yoursite.com/">
```

**Single Post/Page:**
```html
<link rel="canonical" href="https://yoursite.com/page/">
```

**Archives:**
```html
<link rel="canonical" href="https://yoursite.com/category/name/">
```

### WordPress Default Removed

Plugin removes default WordPress canonical:
```php
remove_action('wp_head', 'rel_canonical');
```

Uses custom implementation for better control.

---

## Testing & Validation

### Google Rich Results Test

**Test Schema.org:**
- https://search.google.com/test/rich-results
- Enter your URL
- Check for errors

**Common Errors:**
- Missing required field
- Invalid date format
- Invalid image URL

### Facebook Debugger

**Test Open Graph:**
- https://developers.facebook.com/tools/debug/
- Enter URL
- Check preview
- Click "Scrape Again" to refresh

### Twitter Card Validator

**Test Twitter Cards:**
- https://cards-dev.twitter.com/validator
- Enter URL
- Check preview

### Schema.org Validator

**Validate JSON-LD:**
- https://validator.schema.org/
- Paste schema JSON
- Check for errors

### Browser DevTools

**Inspect Meta Tags:**
```
1. Right-click → View Page Source
2. Search for: "og:" or "twitter:" or "schema.org"
3. Verify all tags present
```

---

## Troubleshooting

### Schema Not Appearing

**1. Check Plugin Active:**
```bash
wp plugin is-active media-lab-seo
```

**2. Check Settings:**
```bash
wp option get medialab_seo_enabled
wp option get medialab_seo_schema_enabled
```

**3. Check Output:**
```bash
curl -sL https://yoursite.com/ | grep "schema.org"
```

**4. Clear Cache:**
```bash
wp cache flush
```

### Open Graph Not Working

**1. Check HTML:**
```bash
curl -sL https://yoursite.com/ | grep "og:"
```

**2. Scrape Again:**
- Go to Facebook Debugger
- Click "Scrape Again"
- Facebook caches for 24h

**3. Check Image:**
- Minimum 200x200px
- Maximum 8MB
- JPG or PNG format

### Twitter Cards Not Showing

**1. Validate Card:**
- https://cards-dev.twitter.com/validator
- Check for errors

**2. Wait for Approval:**
- Twitter may need to approve domain
- Can take 1-2 weeks for new domains

**3. Check Image Size:**
- 2:1 ratio recommended
- 1200x600px ideal

### Breadcrumbs Not Showing

**1. Check Function Call:**
```php
// In template
<?php
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs();
} else {
    echo 'Function not found';
}
?>
```

**2. Check Plugin Active:**
```bash
wp plugin is-active media-lab-seo
```

---

## Best Practices

### Images

**Social Sharing:**
- Size: 1200x630px
- Format: JPG or PNG
- File size: < 1MB
- Quality: High

### Meta Descriptions

**Length:**
- 150-160 characters ideal
- Too short: Not descriptive
- Too long: Gets cut off

**Content:**
- Unique per page
- Include keywords
- Call to action

### Schema.org

**Don't:**
- Add false information
- Spam keywords
- Hide data from users

**Do:**
- Be accurate
- Keep updated
- Test regularly

### Titles

**Format:**
- Post: "Post Title | Site Name"
- Page: "Page Title | Site Name"
- Homepage: "Site Name | Tagline"

**Length:**
- 50-60 characters
- Include primary keyword
- Make it compelling

---

## Advanced Configuration

### Custom Schema Types
```php
// Add custom schema type
add_filter('medialab_seo_schema_types', function($types, $post) {
    if ($post->post_type === 'event') {
        $types[] = [
            '@type' => 'Event',
            'name' => get_the_title($post),
            'startDate' => get_field('event_date', $post),
            'location' => [
                '@type' => 'Place',
                'name' => get_field('event_location', $post)
            ]
        ];
    }
    return $types;
}, 10, 2);
```

### Disable on Specific Pages
```php
// Disable SEO on specific pages
add_filter('medialab_seo_should_output', function($should, $post_id) {
    // Don't output on page ID 123
    if ($post_id === 123) {
        return false;
    }
    return $should;
}, 10, 2);
```

### Custom OG Image
```php
// Custom OG image per post
add_filter('medialab_seo_og_image', function($image, $post_id) {
    $custom_image = get_field('custom_og_image', $post_id);
    return $custom_image ?: $image;
}, 10, 2);
```

---

## Monitoring

### Google Search Console

**Setup:**
1. Go to: https://search.google.com/search-console
2. Add property
3. Verify ownership
4. Submit sitemap

**Monitor:**
- Coverage errors
- Mobile usability
- Core Web Vitals
- Rich results

### Regular Checks

**Weekly:**
- Check Google Search Console for errors
- Test rich results on new content

**Monthly:**
- Review schema coverage
- Update default images if needed
- Check social preview on new pages

**Quarterly:**
- Full SEO audit
- Update schema.org implementation
- Review and update meta descriptions

---

## Next Steps

- **Testing:** [Testing Guide](11_TESTING.md)
- **Analytics:** [Analytics Documentation](12_ANALYTICS.md)
- **Deployment:** [Deployment Guide](10_DEPLOYMENT.md)

---

**SEO optimized!** 🚀  
**Complete documentation available in `/docs`!** 📚
