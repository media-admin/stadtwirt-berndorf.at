# Media Lab Analytics

Centralized analytics and tracking management for WordPress.

## Features

- **Google Analytics 4**: Easy GA4 integration
- **Google Tag Manager**: GTM container support
- **Facebook Pixel**: FB tracking integration
- **Custom Events**: Track custom interactions
- **Dashboard Widget**: Quick analytics overview
- **Privacy Friendly**: GDPR compliant options

## Installation

1. Upload to `/wp-content/plugins/media-lab-analytics/`
2. Activate through WordPress admin
3. Configure in Settings → Analytics

## Configuration

Navigate to **Settings → Analytics** and add your tracking IDs:

- Google Analytics 4 ID (Format: G-XXXXXXXXXX)
- Google Tag Manager ID (Format: GTM-XXXXXXX)
- Facebook Pixel ID (Format: XXXXXXXXXXXXXXX)

## Custom Events

Track custom events:
```php
do_action('medialab_track_event', 'button_click', [
    'button_name' => 'Download Brochure',
    'button_location' => 'homepage'
]);
```

## Version

1.0.0
