# Analytics Documentation

**Version:** 1.4.0  
**Letzte Aktualisierung:** 2026-03-04  
**Plugin:** Media Lab Analytics v1.0.0 *(optional – im Repo vorhanden)*

> **Hinweis:** Das Analytics-Plugin ist nicht im Standard-Setup aktiviert.  
> Aktivieren via: `cd cms && wp plugin activate media-lab-analytics`

Complete guide for the Analytics plugin.

---

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Google Analytics 4](#google-analytics-4)
4. [Google Tag Manager](#google-tag-manager)
5. [Facebook Pixel](#facebook-pixel)
6. [Custom Events](#custom-events)
7. [Auto-Tracking](#auto-tracking)
8. [Privacy & GDPR](#privacy--gdpr)
9. [Troubleshooting](#troubleshooting)

---

## Overview

### What is Media Lab Analytics?

Centralized analytics management plugin that handles:
- Google Analytics 4 (GA4)
- Google Tag Manager (GTM)
- Facebook Pixel
- Custom event tracking
- WooCommerce integration

### Features

✅ **Easy Setup** - Add tracking IDs, done  
✅ **Custom Events** - Track any interaction  
✅ **Auto-Tracking** - Forms, WooCommerce  
✅ **Privacy-Friendly** - GDPR compliant  
✅ **Admin Exclusion** - Don't track admins  
✅ **Clean Code** - Lightweight, no bloat

---

## Installation

### 1. Activate Plugin
```bash
wp plugin activate media-lab-analytics
```

### 2. Configure Settings

**Admin:** Settings → Analytics
```
✅ Enable Analytics Tracking
Google Analytics 4 ID: G-XXXXXXXXXX
Google Tag Manager ID: GTM-XXXXXXX
Facebook Pixel ID: XXXXXXXXXXXXXXX
```

### 3. Verify Installation

**Check HTML Source:**
```bash
curl -sL https://yoursite.com/ | grep "gtag\|fbq\|GTM"
```

**Should see:**
- `gtag('config', 'G-XXXXXXXXXX')`
- `gtm.js`
- `fbq('init', 'XXXXXXXXXXXXXXX')`

---

## Google Analytics 4

### Setup

**1. Get GA4 ID:**
- Go to: https://analytics.google.com/
- Admin → Data Streams
- Copy Measurement ID (G-XXXXXXXXXX)

**2. Add to WordPress:**
```
Settings → Analytics
Google Analytics 4 ID: G-XXXXXXXXXX
Save Changes
```

### What Gets Tracked

**Automatic:**
- Page views
- Scroll depth
- Outbound links
- File downloads
- Video engagement (YouTube)

**Custom (via code):**
```php
// Track button click
do_action('medialab_track_event', 'button_click', [
    'button_name' => 'Download PDF',
    'button_location' => 'sidebar'
]);
```

### Enhanced Ecommerce (WooCommerce)

**Auto-tracked:**
- `add_to_cart`
- `begin_checkout`
- `purchase`
- `view_item`

**Verify in GA4:**
1. Go to GA4 → Reports → Realtime
2. Perform action on site
3. Check event appears

---

## Google Tag Manager

### Setup

**1. Create GTM Account:**
- Go to: https://tagmanager.google.com/
- Create account
- Copy Container ID (GTM-XXXXXXX)

**2. Add to WordPress:**
```
Settings → Analytics
Google Tag Manager ID: GTM-XXXXXXX
Save Changes
```

### Container Setup

**In GTM:**

**1. Add GA4 Tag:**
- Tags → New
- Tag Type: Google Analytics: GA4 Configuration
- Measurement ID: G-XXXXXXXXXX
- Trigger: All Pages

**2. Add Custom Events:**
- Triggers → New
- Trigger Type: Custom Event
- Event name: button_click
- Variables: eventCategory, eventAction

**3. Publish Container**

### Data Layer

Plugin pushes events to dataLayer:
```javascript
dataLayer.push({
    'event': 'custom_event',
    'eventCategory': 'Button',
    'eventAction': 'Click',
    'eventLabel': 'Download PDF'
});
```

---

## Facebook Pixel

### Setup

**1. Get Pixel ID:**
- Go to: https://business.facebook.com/
- Events Manager → Pixels
- Copy Pixel ID (15 digits)

**2. Add to WordPress:**
```
Settings → Analytics
Facebook Pixel ID: XXXXXXXXXXXXXXX
Save Changes
```

### What Gets Tracked

**Automatic:**
- `PageView`
- `ViewContent`

**WooCommerce:**
- `AddToCart`
- `InitiateCheckout`
- `Purchase`

**Custom:**
```php
do_action('medialab_track_event', 'fb_custom', [
    'content_name' => 'Product Demo',
    'content_category' => 'Videos',
    'value' => 50.00,
    'currency' => 'USD'
]);
```

### Verify Installation

**1. Install Facebook Pixel Helper:**
- Chrome Extension
- Load your site
- Check green checkmark

**2. Test Events:**
- Go to: Events Manager → Test Events
- Enter your website URL
- Perform actions on site
- Check events appear

---

## Custom Events

### Track Custom Events

**PHP (Server-side):**
```php
// Track button click
do_action('medialab_track_event', 'button_click', [
    'button_name' => 'Download Brochure',
    'button_location' => 'Homepage Hero',
    'button_type' => 'CTA'
]);

// Track video play
do_action('medialab_track_event', 'video_play', [
    'video_title' => 'Product Demo',
    'video_duration' => '02:30',
    'video_position' => 'About Page'
]);

// Track form submission
do_action('medialab_track_event', 'form_submit', [
    'form_name' => 'Contact Form',
    'form_location' => 'Contact Page'
]);

// Track file download
do_action('medialab_track_event', 'file_download', [
    'file_name' => 'Product-Catalog.pdf',
    'file_type' => 'PDF',
    'file_size' => '2.5MB'
]);
```

**JavaScript (Client-side):**
```javascript
// Track scroll depth
window.addEventListener('scroll', function() {
    const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
    
    if (scrollPercent > 75) {
        // Track once
        if (!window.scrollTracked75) {
            gtag('event', 'scroll_depth', {
                'scroll_percent': 75
            });
            window.scrollTracked75 = true;
        }
    }
});

// Track outbound link
document.querySelectorAll('a[href^="http"]').forEach(link => {
    if (!link.href.includes(window.location.hostname)) {
        link.addEventListener('click', function() {
            gtag('event', 'click', {
                'event_category': 'Outbound Link',
                'event_label': this.href
            });
        });
    }
});
```

### Event Parameters

**Standard Parameters:**
```php
[
    'event_category' => 'Category',
    'event_label' => 'Label',
    'value' => 100,
    'currency' => 'USD'
]
```

**Custom Parameters:**
```php
[
    'custom_param_1' => 'Value 1',
    'custom_param_2' => 'Value 2',
    // Any custom data
]
```

---

## Auto-Tracking

### Form Submissions

**Automatically tracked:**
- Contact Form 7
- Gravity Forms
- WPForms
- Ninja Forms
- All forms with `<form>` tag

**Event Data:**
```javascript
{
    'event': 'form_submit',
    'form_id': 'contact-form',
    'form_name': 'Contact Form'
}
```

### WooCommerce Events

**Add to Cart:**
```php
// Automatically tracked
add_action('woocommerce_add_to_cart', 'medialab_track_add_to_cart');

// Event data
[
    'item_id' => 'SKU123',
    'item_name' => 'Product Name',
    'price' => 99.99,
    'quantity' => 1
]
```

**Purchase:**
```php
// Automatically tracked
add_action('woocommerce_thankyou', 'medialab_track_purchase');

// Event data
[
    'transaction_id' => 'ORDER-123',
    'value' => 299.99,
    'currency' => 'USD',
    'items' => [...]
]
```

### Disable Auto-Tracking
```php
// In functions.php
add_filter('medialab_analytics_auto_track_forms', '__return_false');
add_filter('medialab_analytics_auto_track_woocommerce', '__return_false');
```

---

## Privacy & GDPR

### Admin Users Excluded

Admins are NOT tracked by default:
```php
// Check in plugin
if (current_user_can('manage_options')) {
    return; // Don't track
}
```

### IP Anonymization

Enabled by default for GA4:
```javascript
gtag('config', 'G-XXXXXXXXXX', {
    'anonymize_ip': true
});
```

### Cookie Consent Integration

**With Cookie Notice plugin:**
```php
// Only track if consent given
add_filter('medialab_analytics_should_track', function($should_track) {
    if (function_exists('cn_cookies_accepted')) {
        return cn_cookies_accepted();
    }
    return $should_track;
});
```

### Opt-Out

**User opt-out link:**
```php
<a href="#" onclick="window['ga-disable-G-XXXXXXXXXX'] = true; return false;">
    Opt out of analytics
</a>
```

### Data Retention

**Set in GA4:**
1. Go to: Admin → Data Settings → Data Retention
2. Set to: 2 months (GDPR compliant)
3. Save

---

## Troubleshooting

### Analytics Not Tracking

**1. Check Plugin Active:**
```bash
wp plugin is-active media-lab-analytics
```

**2. Check Settings:**
```bash
wp option get medialab_analytics_enabled
wp option get medialab_analytics_ga4_id
```

**3. Check HTML Output:**
```bash
curl -sL https://yoursite.com/ | grep "gtag\|GTM"
```

**4. Check Console:**
```
Open DevTools (F12)
Console tab
Look for: gtag is not defined
```

### Events Not Firing

**1. Test Event Manually:**
```php
// Add to functions.php temporarily
add_action('wp_footer', function() {
    do_action('medialab_track_event', 'test_event', ['test' => 'value']);
});
```

**2. Check GA4 Realtime:**
- Go to GA4 → Realtime
- Should see event within 30 seconds

**3. Check GTM Debug:**
- Add ?gtm_debug to URL
- Opens GTM preview mode
- See all events firing

### Facebook Pixel Not Working

**1. Check Pixel Helper:**
- Install Chrome extension
- Green = working
- Red = not working

**2. Check HTML:**
```bash
curl -sL https://yoursite.com/ | grep "fbq"
# Should see: fbq('init', 'XXXXXXXXXXXXXXX')
```

**3. Test Event:**
```javascript
// In console
fbq('track', 'PageView');
// Check in Events Manager
```

---

## Advanced Configuration

### Custom Dimensions (GA4)
```php
// Add custom dimension
add_filter('medialab_analytics_ga4_config', function($config) {
    $config['custom_dimension_1'] = get_current_user_id();
    $config['custom_dimension_2'] = wp_get_theme()->get('Name');
    return $config;
});
```

### Enhanced Link Attribution
```php
// Enable enhanced link attribution
add_filter('medialab_analytics_enhanced_link_attribution', '__return_true');
```

### Debug Mode
```php
// Enable debug mode
define('MEDIALAB_ANALYTICS_DEBUG', true);

// Check debug.log
tail -f cms/wp-content/debug.log
```

---

## Best Practices

### Event Naming

**Good:**
- `button_click`
- `video_play`
- `form_submit`

**Bad:**
- `Button Click` (spaces)
- `click-button` (inconsistent)
- `btn_clk` (unclear)

### Event Parameters

**Keep it simple:**
```php
// Good
['button_name' => 'Download', 'location' => 'Hero']

// Too much
['btn_nm' => 'DL', 'loc' => 'H', 'clr' => 'red', 'sz' => 'lg']
```

### Performance

**Lazy load tracking:**
```javascript
// Load analytics after page load
window.addEventListener('load', function() {
    // Initialize analytics
});
```

---

## Next Steps

- **SEO:** [SEO Documentation](13_SEO.md)
- **Development:** [Development Guide](06_DEVELOPMENT.md)
- **Deployment:** [Deployment Guide](10_DEPLOYMENT.md)

---

**Track everything!** 📊  
**Next:** [SEO Documentation](13_SEO.md) →
