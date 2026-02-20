# Media Lab SEO Toolkit

Comprehensive SEO solution for WordPress websites.

## Features

- **Schema.org Markup**: Automatic structured data (Organization, Article, Product, etc.)
- **Open Graph Tags**: Facebook, LinkedIn social sharing
- **Twitter Cards**: Enhanced Twitter previews
- **Meta Management**: Title & description optimization
- **Breadcrumbs**: Automatic breadcrumb navigation
- **Canonical URLs**: Prevent duplicate content issues

## Installation

1. Upload to `/wp-content/plugins/media-lab-seo/`
2. Activate through WordPress admin
3. Configure in Settings → SEO Toolkit

## Schema Types Supported

- Organization (Homepage)
- WebSite (Site-wide)
- Article (Blog Posts)
- Product (WooCommerce)
- BreadcrumbList (Navigation)

## Breadcrumbs Usage

In your theme template:
```php
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs();
}
```

## Version

1.0.0
