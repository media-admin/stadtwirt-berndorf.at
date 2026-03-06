# Deployment Guide

**Version:** 1.4.0  
**Letzte Aktualisierung:** 2026-03-04

Complete guide for deploying Media Lab Starter Kit to production.

---

## Table of Contents

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Build for Production](#build-for-production)
3. [Server Requirements](#server-requirements)
4. [Database Migration](#database-migration)
5. [File Transfer](#file-transfer)
6. [Plugin Activation](#plugin-activation)
7. [Configuration](#configuration)
8. [Post-Deployment](#post-deployment)
9. [Rollback Procedure](#rollback-procedure)
10. [Monitoring](#monitoring)

---

## Pre-Deployment Checklist

### Code Quality
```bash
# Run tests
./tests/run-tests.sh
# Must show: 23/23 passing

# Check for debug code
grep -r "console.log" cms/wp-content/themes/custom-theme/assets/js/
grep -r "var_dump\|print_r" cms/wp-content/plugins/media-lab-*/

# Verify Git status
git status
# Should be clean or only non-critical changes
```

### Content Review

- [ ] All placeholder content removed
- [ ] All images optimized (WebP format)
- [ ] All URLs updated (staging → production)
- [ ] Privacy Policy updated
- [ ] Cookie Notice configured
- [ ] Contact forms tested

### SEO & Analytics

- [ ] Analytics tracking codes ready
- [ ] SEO meta data complete
- [ ] Sitemap generated
- [ ] Robots.txt configured
- [ ] 301 redirects mapped (if replacing old site)

### Performance
```bash
# Test page speed (staging)
npm install -g lighthouse
lighthouse https://staging.site.com --view

# Target scores:
# Performance: 90+
# Accessibility: 90+
# Best Practices: 90+
# SEO: 90+
```

---

## Build for Production

### 1. Production Build
```bash
cd /path/to/media-lab-starter-kit

# Clean previous builds
rm -rf cms/wp-content/themes/custom-theme/assets/dist/

# Build assets
npm run build

# Verify output
ls -lh cms/wp-content/themes/custom-theme/assets/dist/css/
ls -lh cms/wp-content/themes/custom-theme/assets/dist/js/
# Sollte zeigen:
# css/style.css
# js/main.js
# js/ajax-filters.js, ajax-search.js, load-more.js, google-maps.js, notifications.js
# js/chunks/  (automatische Sub-Chunks)
# manifest.json
```

### 2. Was der Build automatisch macht
```
✅ console.log / console.debug entfernt (Terser)
✅ JS minifiziert + Code-Splitting
✅ CSS minifiziert + Autoprefixer
✅ SCSS Tokens aufgelöst
```

npm install -g terser
terser cms/wp-content/themes/custom-theme/assets/dist/*.js -o output.js -c -m
```

### 3. WordPress Configuration
```php
// wp-config.php for production
define('WP_ENV', 'production');
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', false);  // Allow plugin updates
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// SMTP – Credentials nie in der Datenbank (Security F-05)
define('MEDIALAB_SMTP_ENABLED',   true);
define('MEDIALAB_SMTP_HOST',      'smtp.example.com');
define('MEDIALAB_SMTP_PORT',      587);
define('MEDIALAB_SMTP_USER',      'noreply@example.com');
define('MEDIALAB_SMTP_PASS',      'sicheres-passwort');
define('MEDIALAB_SMTP_ENC',       'tls');
define('MEDIALAB_SMTP_FROM',      'noreply@example.com');
define('MEDIALAB_SMTP_FROM_NAME', 'Firmenname');

// Google Maps (falls genutzt)
// define('GOOGLE_MAPS_API_KEY', 'AIza...');
```

---

## Server Requirements

### Minimum Requirements

- **PHP:** 8.0 or higher
- **MySQL:** 5.7+ or MariaDB 10.3+
- **Memory:** 256MB PHP memory limit
- **Disk Space:** 1GB minimum
- **HTTPS:** SSL certificate required

### Recommended Server Stack

**Web Server:**
- Nginx 1.18+ (recommended)
- OR Apache 2.4+ with mod_rewrite

**PHP Extensions:**
```bash
# Required
php8.0-curl
php8.0-gd
php8.0-mbstring
php8.0-mysql
php8.0-xml
php8.0-zip

# Recommended
php8.0-imagick
php8.0-intl
php8.0-opcache
```

**Caching:**
- OPcache enabled
- Object caching (Redis/Memcached)
- Page caching (Nginx FastCGI Cache)

### Server Configuration

**Nginx Config:**
```nginx
server {
    listen 443 ssl http2;
    server_name example.com www.example.com;
    
    root /var/www/example.com/cms;
    index index.php;
    
    # SSL
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    
    # WordPress permalinks
    location / {
        try_files $uri $uri/ /index.php?$args;
    }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Cache static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## Database Migration

### 1. Export Staging Database
```bash
# On staging server
cd /path/to/cms

wp db export staging-$(date +%Y%m%d).sql

# Compress
gzip staging-$(date +%Y%m%d).sql
```

### 2. Search & Replace URLs
```bash
# On staging (before export)
wp search-replace \
    'https://staging.example.com' \
    'https://example.com' \
    --all-tables \
    --report-changed-only

# Export after replacement
wp db export production-ready-$(date +%Y%m%d).sql
```

### 3. Import to Production
```bash
# On production server
cd /path/to/cms

# Backup existing database first!
wp db export backup-before-deploy-$(date +%Y%m%d).sql

# Import new database
wp db import production-ready-20260216.sql

# Verify
wp db check
```

---

## File Transfer

### Method 1: Git Deployment (Recommended)
```bash
# On production server
cd /var/www/example.com

# Clone repository
git clone https://github.com/media-admin/media-lab-starter-kit.git .

# Checkout specific version
git checkout v1.2.0

# Install dependencies
npm install
composer install

# Build assets
npm run build

# Set permissions
chown -R www-data:www-data cms/wp-content/
chmod -R 755 cms/wp-content/
```

### Method 2: SFTP/SCP
```bash
# From local machine
rsync -avz \
    --exclude 'node_modules' \
    --exclude '.git' \
    --exclude 'cms/wp-content/uploads' \
    /local/path/ \
    user@server:/var/www/example.com/
```

### Method 3: Automated Deployment

**Using Deployer:**
```bash
# deploy.php
namespace Deployer;

require 'recipe/wordpress.php';

set('repository', 'git@github.com:media-admin/media-lab-starter-kit.git');
set('branch', 'main');

host('production')
    ->hostname('example.com')
    ->user('deploy')
    ->set('deploy_path', '/var/www/example.com');

task('build', function() {
    run('npm install');
    run('npm run build');
});

after('deploy:vendors', 'build');
```

### File Permissions
```bash
# WordPress directories
find cms/wp-content/ -type d -exec chmod 755 {} \;
find cms/wp-content/ -type f -exec chmod 644 {} \;

# wp-config.php
chmod 600 cms/wp-config.php

# Uploads directory
chmod 755 cms/wp-content/uploads/
```

---

## Plugin Activation

### 1. Activate Core Plugins
```bash
cd /var/www/example.com/cms

# Activate in order
wp plugin activate media-lab-agency-core
wp plugin activate media-lab-project-starter
wp plugin activate media-lab-analytics
wp plugin activate media-lab-seo
```

### 2. Verify Activation
```bash
wp plugin list --status=active

# Should show:
# media-lab-agency-core       active
# media-lab-project-starter   active  
# media-lab-analytics         active
# media-lab-seo              active
# advanced-custom-fields-pro  active
```

### 3. Activate Theme
```bash
wp theme activate custom-theme
wp theme list --status=active
```

---

## Configuration

### 1. Analytics Setup
```bash
# Via WP-CLI
wp option update medialab_analytics_enabled 1
wp option update medialab_analytics_ga4_id 'G-XXXXXXXXXX'
wp option update medialab_analytics_gtm_id 'GTM-XXXXXXX'
wp option update medialab_analytics_fb_pixel 'XXXXXXXXXXXXXXX'

# Or via admin
# Settings → Analytics
# Enter tracking IDs
```

### 2. SEO Setup
```bash
# Via WP-CLI
wp option update medialab_seo_enabled 1
wp option update medialab_seo_schema_enabled 1
wp option update medialab_seo_og_enabled 1
wp option update medialab_seo_twitter_enabled 1
wp option update medialab_seo_site_name 'Company Name'
wp option update medialab_seo_twitter_username '@handle'

# Or via admin
# Settings → SEO Toolkit
```

### 3. Permalink Structure
```bash
wp rewrite structure '/%postname%/'
wp rewrite flush
```

### 4. Site Settings
```bash
# Site identity
wp option update blogname 'Company Name'
wp option update blogdescription 'Tagline'

# Timezone
wp option update timezone_string 'Europe/Vienna'

# Date/Time format
wp option update date_format 'F j, Y'
wp option update time_format 'g:i a'

# Enable search engine indexing
wp option update blog_public 1
```

---

## Post-Deployment

### 1. Smoke Tests
```bash
# Test homepage
curl -I https://example.com
# Should return: 200 OK

# Test admin
curl -I https://example.com/wp-admin/
# Should return: 200 or 302 (redirect to login)

# Test API
curl https://example.com/wp-json/
# Should return JSON
```

### 2. Functional Tests

**Manual Checklist:**
- [ ] Homepage loads correctly
- [ ] Navigation works
- [ ] Forms submit successfully
- [ ] Search works
- [ ] AJAX features work
- [ ] Mobile responsive
- [ ] No console errors

### 3. Performance Test
```bash
lighthouse https://example.com --view

# Check:
# - Performance score
# - First Contentful Paint
# - Largest Contentful Paint
# - Time to Interactive
```

### 4. Security Scan
```bash
# Check for known vulnerabilities
wpscan --url https://example.com

# SSL test
curl -I https://example.com | grep -i "strict-transport"
```

### 5. Monitoring Setup

**Uptime Monitoring:**
- UptimeRobot (free)
- Pingdom
- StatusCake

**Error Monitoring:**
- Sentry
- Rollbar
- New Relic

**Analytics:**
- Google Analytics
- Google Search Console
- Matomo (self-hosted)

---

## Rollback Procedure

### Quick Rollback

**If deployment fails:**
```bash
# 1. Restore database
cd /var/www/example.com/cms
wp db import backup-before-deploy-$(date +%Y%m%d).sql

# 2. Revert code
git reset --hard HEAD~1
# or
git checkout previous-tag

# 3. Rebuild
npm install
npm run build

# 4. Clear cache
wp cache flush
wp rewrite flush

# 5. Verify
curl -I https://example.com
```

### Database Rollback
```bash
# List backups
ls -lh backups/*.sql

# Import specific backup
wp db import backups/backup-20260215.sql

# Verify
wp db check
```

---

## Monitoring

### Health Checks
```bash
# Create health check endpoint
# wp-content/mu-plugins/health-check.php

<?php
add_action('rest_api_init', function() {
    register_rest_route('health/v1', '/check', [
        'methods' => 'GET',
        'callback' => function() {
            $checks = [
                'database' => check_database_connection(),
                'cache' => check_cache_status(),
                'disk' => check_disk_space(),
            ];
            
            return [
                'status' => 'ok',
                'checks' => $checks
            ];
        }
    ]);
});
```

**Monitor endpoint:**
```bash
curl https://example.com/wp-json/health/v1/check
```

### Log Monitoring
```bash
# Watch error log
tail -f /var/log/nginx/error.log

# Watch PHP errors
tail -f /var/log/php8.0-fpm.log

# WordPress debug log
tail -f cms/wp-content/debug.log
```

### Performance Monitoring

**New Relic APM:**
```php
// wp-config.php
if (extension_loaded('newrelic')) {
    newrelic_set_appname('Production Site');
}
```

**Query Monitor Plugin:**
```bash
# Install for admin only
wp plugin install query-monitor --activate
```

---

## Automated Backups

### Database Backups
```bash
# Cron job: Daily at 2am
0 2 * * * cd /var/www/example.com/cms && wp db export backups/backup-$(date +\%Y\%m\%d).sql && gzip backups/backup-$(date +\%Y\%m\%d).sql

# Keep last 30 days
0 3 * * * find /var/www/example.com/cms/backups/ -name "*.sql.gz" -mtime +30 -delete
```

### File Backups
```bash
# Cron job: Weekly on Sunday at 3am
0 3 * * 0 tar -czf /backups/files-$(date +\%Y\%m\%d).tar.gz /var/www/example.com/

# Keep last 4 weeks
0 4 * * 0 find /backups/ -name "files-*.tar.gz" -mtime +28 -delete
```

---

## Production Maintenance

### Plugin Updates
```bash
# Check for updates
wp plugin list --update=available

# Update specific plugin
wp plugin update media-lab-agency-core

# Update all (carefully!)
wp plugin update --all --dry-run
wp plugin update --all
```

### WordPress Core Updates
```bash
# Check version
wp core version

# Update core
wp core update
wp core update-db
```

### Theme Updates
```bash
# Git workflow
git pull origin main
npm install
npm run build
wp cache flush
```

---

## Emergency Contacts

**Development Team:**
- Email: markus.tritremmel@media-lab.at
- Phone: [Your phone]

**Hosting Support:**
- Provider: [Hosting provider]
- Support: [Support URL/phone]

**Emergency Procedure:**
1. Contact development team
2. Check error logs
3. Restore from backup if needed
4. Document incident

---

## Next Steps

- **Testing:** [Testing Guide](11_TESTING.md)
- **Analytics:** [Analytics Documentation](12_ANALYTICS.md)
- **Monitoring:** Setup continuous monitoring

---

**Deployment checklist complete!** 🚀  
**Next:** [Testing Guide](11_TESTING.md) →
