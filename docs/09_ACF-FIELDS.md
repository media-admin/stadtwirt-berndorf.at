# ACF Fields Reference

**Version:** 1.7.0  
**Letzte Aktualisierung:** 2026-03-06  
**Plugin:** Media Lab Project Starter v1.0.0 (optional)

Complete reference for all ACF Field Groups: 11 CPT Field Groups (JSON, 65 custom fields) + 10 Options Sub-Pages (PHP, 92 custom fields).

---

## Table of Contents

1. [Overview](#overview)
2. [Team Member Fields](#team-member-fields)
3. [Project Fields](#project-fields)
4. [Job Fields](#job-fields)
5. [Service Fields](#service-fields)
6. [Testimonial Fields](#testimonial-fields)
5. [FAQ Fields](#faq-fields)
7. [Google Map Fields](#google-map-fields)
8. [Hero Slide Fields](#hero-slide-fields)
9. [Carousel Fields](#carousel-fields)
10. [Page Builder Fields](#page-builder-fields)
11. [Product Fields (WooCommerce)](#product-fields)
12. [Usage Examples](#usage-examples)
13. [JSON Management](#json-management)

---

## Overview

### What is ACF?

Advanced Custom Fields (ACF) extends WordPress with custom fields for posts, pages, and custom post types.

### How Fields are Stored

All field groups are stored as JSON files for version control:
```
cms/wp-content/plugins/media-lab-project-starter/acf-json/
├── group_team_member.json
├── group_project.json
├── group_job.json
├── group_service.json
├── group_testimonial.json
├── group_faq.json
├── group_gmap.json
├── group_hero_slide.json
├── group_carousel.json
├── group_page_builder.json
└── group_product_additional.json
```

### Total Fields

- **11 CPT Field Groups** (JSON-Export, 65 Custom Fields)
- **10 Options Sub-Pages** (PHP, 92 Custom Fields)
- **13 Field Groups total** (inkl. group_maintenance, group_cookie_consent)

### Accessing Fields
```php
// Get field value
$value = get_field('field_name');

// Get field from specific post
$value = get_field('field_name', $post_id);

// Check if field has value
if (get_field('field_name')) {
    // Field has value
}

// Get all fields
$fields = get_fields();
```

---

## Team Member Fields

**Field Group:** `group_team_member`  
**Location:** Post Type = Team  
**Total Fields:** 7

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `position` | Text | Job title | Yes |
| `email` | Email | Contact email | No |
| `phone` | Text | Phone number | No |
| `bio_short` | Textarea | Short bio (140 chars) | No |
| `social_links` | Repeater | Social media links | No |
| - `platform` | Select | Platform (Facebook, Twitter, etc.) | Yes |
| - `url` | URL | Profile URL | Yes |

### Usage Example
```php
<?php
$position = get_field('position');
$email = get_field('email');
$phone = get_field('phone');
?>

<div class="team-member-details">
    <h3><?php the_title(); ?></h3>
    <p class="position"><?php echo esc_html($position); ?></p>
    
    <?php if ($email) : ?>
        <a href="mailto:<?php echo esc_attr($email); ?>">
            <?php echo esc_html($email); ?>
        </a>
    <?php endif; ?>
    
    <?php if (have_rows('social_links')) : ?>
        <div class="social-links">
            <?php while (have_rows('social_links')) : the_row(); 
                $platform = get_sub_field('platform');
                $url = get_sub_field('url');
            ?>
                <a href="<?php echo esc_url($url); ?>" target="_blank">
                    <i class="fab fa-<?php echo esc_attr(strtolower($platform)); ?>"></i>
                </a>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>
```

---

## Project Fields

**Field Group:** `group_project`  
**Location:** Post Type = Project  
**Total Fields:** 8

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `client_name` | Text | Client/company name | No |
| `project_date` | Date Picker | Completion date | No |
| `project_url` | URL | Live project URL | No |
| `technologies` | Checkbox | Technologies used | No |
| `project_type` | Select | Project type | No |
| `gallery` | Gallery | Project images | No |
| `testimonial_text` | Textarea | Client testimonial | No |
| `featured_project` | True/False | Featured project | No |

### Usage Example
```php
<?php
$client = get_field('client_name');
$date = get_field('project_date');
$url = get_field('project_url');
$gallery = get_field('gallery');
?>

<article class="project-single">
    <div class="project-meta">
        <?php if ($client) : ?>
            <p><strong>Client:</strong> <?php echo esc_html($client); ?></p>
        <?php endif; ?>
        
        <?php if ($date) : ?>
            <p><strong>Date:</strong> <?php echo esc_html($date); ?></p>
        <?php endif; ?>
        
        <?php if ($url) : ?>
            <a href="<?php echo esc_url($url); ?>" target="_blank">View Live Site</a>
        <?php endif; ?>
    </div>
    
    <?php if ($gallery) : ?>
        <div class="project-gallery">
            <?php foreach ($gallery as $image) : ?>
                <img src="<?php echo esc_url($image['url']); ?>" 
                     alt="<?php echo esc_attr($image['alt']); ?>">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>
```

---

## Job Fields

**Field Group:** `group_job`  
**Location:** Post Type = Job  
**Total Fields:** 9

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `salary_range` | Text | Salary information | No |
| `employment_type` | Select | Full-time, Part-time, etc. | Yes |
| `location` | Text | Job location | Yes |
| `remote` | True/False | Remote work available | No |
| `application_deadline` | Date Picker | Application deadline | No |
| `application_email` | Email | Email for applications | Yes |
| `requirements` | Wysiwyg | Job requirements | No |
| `benefits` | Wysiwyg | Benefits offered | No |
| `is_featured` | True/False | Featured job | No |

### Usage Example
```php
<?php
$type = get_field('employment_type');
$location = get_field('location');
$remote = get_field('remote');
$deadline = get_field('application_deadline');
?>

<div class="job-details">
    <div class="job-meta">
        <span><?php echo esc_html($type); ?></span>
        <span><?php echo esc_html($location); ?></span>
        <?php if ($remote) : ?>
            <span class="remote">Remote OK</span>
        <?php endif; ?>
    </div>
    
    <?php if ($deadline) : ?>
        <p class="deadline">Apply by: <?php echo esc_html($deadline); ?></p>
    <?php endif; ?>
    
    <div class="job-requirements">
        <h3>Requirements</h3>
        <?php the_field('requirements'); ?>
    </div>
    
    <div class="job-benefits">
        <h3>Benefits</h3>
        <?php the_field('benefits'); ?>
    </div>
    
    <a href="mailto:<?php the_field('application_email'); ?>" class="btn-apply">
        Apply Now
    </a>
</div>
```

---

## Service Fields

**Field Group:** `group_service`  
**Location:** Post Type = Service  
**Total Fields:** 7

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `icon` | Text | Icon class or code | No |
| `price` | Text | Pricing info | No |
| `duration` | Text | Service duration | No |
| `features` | Repeater | Key features | No |
| - `feature` | Text | Feature description | Yes |
| `cta_text` | Text | Call-to-action text | No |
| `cta_link` | URL | CTA link | No |

### Usage Example
```php
<?php
$icon = get_field('icon');
$price = get_field('price');
?>

<div class="service-card">
    <?php if ($icon) : ?>
        <i class="<?php echo esc_attr($icon); ?>"></i>
    <?php endif; ?>
    
    <h3><?php the_title(); ?></h3>
    <?php the_excerpt(); ?>
    
    <?php if ($price) : ?>
        <p class="price"><?php echo esc_html($price); ?></p>
    <?php endif; ?>
    
    <?php if (have_rows('features')) : ?>
        <ul class="features">
            <?php while (have_rows('features')) : the_row(); ?>
                <li><?php the_sub_field('feature'); ?></li>
            <?php endwhile; ?>
        </ul>
    <?php endif; ?>
    
    <?php 
    $cta_text = get_field('cta_text');
    $cta_link = get_field('cta_link');
    if ($cta_text && $cta_link) :
    ?>
        <a href="<?php echo esc_url($cta_link); ?>" class="btn">
            <?php echo esc_html($cta_text); ?>
        </a>
    <?php endif; ?>
</div>
```

---

## Testimonial Fields

**Field Group:** `group_testimonial`  
**Location:** Post Type = Testimonial  
**Total Fields:** 5

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `client_name` | Text | Client name | Yes |
| `client_role` | Text | Job title | No |
| `client_company` | Text | Company name | No |
| `rating` | Number | Star rating (1-5) | No |
| `featured` | True/False | Featured testimonial | No |

### Usage Example
```php
<?php
$client = get_field('client_name');
$role = get_field('client_role');
$company = get_field('client_company');
$rating = get_field('rating');
?>

<div class="testimonial">
    <?php the_post_thumbnail('thumbnail'); ?>
    
    <blockquote>
        <?php the_content(); ?>
    </blockquote>
    
    <?php if ($rating) : ?>
        <div class="rating">
            <?php for ($i = 0; $i < $rating; $i++) : ?>
                <i class="fa fa-star"></i>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    
    <cite>
        <strong><?php echo esc_html($client); ?></strong>
        <?php if ($role || $company) : ?>
            <br>
            <?php if ($role) echo esc_html($role); ?>
            <?php if ($role && $company) echo ', '; ?>
            <?php if ($company) echo esc_html($company); ?>
        <?php endif; ?>
    </cite>
</div>
```

---

## FAQ Fields

**Field Group:** `group_faq`  
**Location:** Post Type = FAQ  
**Total Fields:** 2

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `answer` | Wysiwyg | Detailed answer | Yes |
| `order` | Number | Display order | No |

### Usage Example
```php
<?php
$faqs = new WP_Query([
    'post_type' => 'faq',
    'posts_per_page' => -1,
    'meta_key' => 'order',
    'orderby' => 'meta_value_num',
    'order' => 'ASC'
]);

if ($faqs->have_posts()) : ?>
    <div class="faq-list">
        <?php while ($faqs->have_posts()) : $faqs->the_post(); ?>
            <div class="faq-item">
                <h3><?php the_title(); ?></h3>
                <div class="answer">
                    <?php the_field('answer'); ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; wp_reset_postdata(); ?>
```

---

## Google Map Fields

**Field Group:** `group_gmap`  
**Location:** Post Type = Google Map  
**Total Fields:** 5

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `location` | Google Map | Map location | Yes |
| `address` | Text | Full address | No |
| `phone` | Text | Location phone | No |
| `email` | Email | Location email | No |
| `hours` | Textarea | Opening hours | No |

### Usage Example
```php
<?php
$location = get_field('location');
if ($location) : ?>
    <div class="location-info">
        <h3><?php the_title(); ?></h3>
        
        <?php if ($location['address']) : ?>
            <p><?php echo esc_html($location['address']); ?></p>
        <?php endif; ?>
        
        <?php if (get_field('phone')) : ?>
            <p>Phone: <?php the_field('phone'); ?></p>
        <?php endif; ?>
        
        <div class="acf-map" data-zoom="14">
            <div class="marker" 
                 data-lat="<?php echo esc_attr($location['lat']); ?>" 
                 data-lng="<?php echo esc_attr($location['lng']); ?>">
                <h4><?php the_title(); ?></h4>
            </div>
        </div>
    </div>
<?php endif; ?>
```

---

## Hero Slide Fields

**Field Group:** `group_hero_slide`  
**Location:** Post Type = Hero Slide  
**Total Fields:** 5

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `subtitle` | Text | Slide subtitle | No |
| `button_text` | Text | CTA button text | No |
| `button_link` | URL | CTA link | No |
| `background_video` | File | Background video | No |
| `overlay_opacity` | Range | Dark overlay (0-1) | No |

### Usage Example
```php
<?php
$slides = new WP_Query([
    'post_type' => 'hero_slide',
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC'
]);

if ($slides->have_posts()) : ?>
    <div class="hero-slider">
        <?php while ($slides->have_posts()) : $slides->the_post(); 
            $subtitle = get_field('subtitle');
            $btn_text = get_field('button_text');
            $btn_link = get_field('button_link');
        ?>
            <div class="hero-slide" style="background-image: url('<?php the_post_thumbnail_url('full'); ?>')">
                <div class="hero-content">
                    <h1><?php the_title(); ?></h1>
                    <?php if ($subtitle) : ?>
                        <p><?php echo esc_html($subtitle); ?></p>
                    <?php endif; ?>
                    <?php if ($btn_text && $btn_link) : ?>
                        <a href="<?php echo esc_url($btn_link); ?>" class="btn">
                            <?php echo esc_html($btn_text); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif; wp_reset_postdata(); ?>
```

---

## Carousel Fields

**Field Group:** `group_carousel`  
**Location:** Post Type = Carousel  
**Total Fields:** 3

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `link` | URL | Item link | No |
| `link_target` | True/False | Open in new tab | No |
| `subtitle` | Text | Item subtitle | No |

---

## Page Builder Fields

**Field Group:** `group_page_builder`  
**Location:** Post Type = Page  
**Total Fields:** 10+ (Flexible Content)

Complex flexible content field for building custom page layouts.

### Layouts

- Hero Section
- Content Section
- Grid Section
- CTA Section
- Form Section
- And more...

**Usage:** Advanced page building - see separate page builder documentation.

---

## Product Fields (WooCommerce)

**Field Group:** `group_product_additional`  
**Location:** Post Type = Product  
**Total Fields:** 4

### Fields

| Field Name | Type | Description | Required |
|-----------|------|-------------|----------|
| `features` | Wysiwyg | Product features | No |
| `specifications` | Repeater | Technical specs | No |
| `downloads` | Repeater | Downloadable files | No |
| `related_services` | Post Object | Related services | No |

---

## Usage Examples

### Get All Fields
```php
$fields = get_fields();
foreach ($fields as $name => $value) {
    echo $name . ': ' . $value;
}
```

### Conditional Field Display
```php
<?php if (get_field('featured_project')) : ?>
    <span class="badge">Featured</span>
<?php endif; ?>
```

### Repeater Field Loop
```php
<?php if (have_rows('social_links')) : ?>
    <ul>
        <?php while (have_rows('social_links')) : the_row(); ?>
            <li>
                <a href="<?php the_sub_field('url'); ?>">
                    <?php the_sub_field('platform'); ?>
                </a>
            </li>
        <?php endwhile; ?>
    </ul>
<?php endif; ?>
```

---

## JSON Management

### Location

All field groups automatically save to:
```
cms/wp-content/plugins/cms/wp-content/plugins/media-lab-project-starter/acf-json/
```

### Syncing Fields

If JSON and database are out of sync:

1. Go to: **Custom Fields → Tools**
2. Click **Sync available**
3. Select field groups to sync
4. Click **Sync**

### Version Control
```bash
# Track field changes
git add cms/wp-content/plugins/cms/wp-content/plugins/media-lab-project-starter/acf-json/*.json
git commit -m "Update: ACF field groups"
```

### Export/Import

**Export:**
```
Custom Fields → Tools → Export Field Groups
Select groups → Generate export code
```

**Import:**
```
Custom Fields → Tools → Import Field Groups
Paste code → Import
```

---

## Options Sub-Pages – Übersicht

Das Agency Core Plugin registriert 10 separate Unterseiten unter **Agency Core** im WP-Admin:

| # | Menübezeichnung | Slug | Field Group |
|---|---|---|---|
| 1 | Plugin Status | `agency-core-plugin-status` | `group_plugin_status` |
| 2 | Maintenance Mode / Wartungsmodus | `agency-core-maintenance` | `group_maintenance` |
| 3 | Logo / Globale Einstellungen | `agency-core-logo` | `group_logo` |
| 4 | Hero Image / Globale Einstellungen | `agency-core-hero` | `group_hero_global`, `group_hero_image` |
| 5 | Cookie Consent | `agency-core-cookie-consent` | `group_cookie_consent` |
| 6 | E-Mail / SMTP | `agency-core-smtp` | `group_smtp` |
| 7 | Spam-Schutz / E-Mail Obfuskierung | `agency-core-spam` | `group_obfuscation` |
| 8 | Top Header / Kontaktdaten | `agency-core-top-header` | `group_top_header` |
| 9 | Multi Language / Mehrsprachigkeit | `agency-core-multilang` | `group_multi_language` |
| 10 | White Label / Agentur-Branding | `agency-core-white-label` | `group_white_label` |

> **Hinweis:** Gespeicherte Feldwerte bleiben bei einer Slug-Änderung erhalten, da ACF Options-Werte nach dem Feld-`name` (nicht nach dem Page-Slug) in der Datenbank gespeichert werden.

## 12. Maintenance Mode Settings

**Field Group:** `group_maintenance`  
**Options Page:** Agency Core → Maintenance Mode / Wartungsmodus  
**Plugin:** media-lab-agency-core

| Field | Name | Typ | Beschreibung |
|---|---|---|---|
| Maintenance Mode aktivieren | `maintenance_enabled` | true_false | Aktiviert 503-Seite für alle Besucher |
| Überschrift | `maintenance_headline` | text | H1 der Wartungsseite |
| Nachricht | `maintenance_message` | textarea | Text für Besucher (HTML erlaubt) |
| Voraussichtliches Ende | `maintenance_date` | text | Freitext, z.B. "15. März 2026, 10:00 Uhr" |
| Logo | `maintenance_logo` | image | Leer = Site-Name als Text |
| Browser-Tab Titel | `maintenance_title` | text | Leer = automatisch aus Site-Name |

**Admin-Bypass:** Eingeloggte Administratoren werden automatisch durchgelassen und sehen einen Hinweis in der Admin-Bar.

**Notfall-Fallback** (ohne Backend-Zugang):
```php
// wp-config.php
define('MEDIALAB_MAINTENANCE_MODE', true);
```


---

## 13. Cookie Consent Settings

**Field Group:** `group_cookie_consent`  
**Options Page:** Agency Core → Cookie Consent  
**Plugin:** media-lab-agency-core

### Allgemein

| Field | Name | Typ | Beschreibung |
|---|---|---|---|
| Consent-Version | `cc_version` | text | Erhöhen erzwingt erneute Zustimmung aller Besucher |
| Datenschutz-URL | `cc_privacy_url` | text | Pfad zur Datenschutzerklärung |
| Datenschutz Link-Text | `cc_privacy_label` | text | Linktext im Banner |

### Banner-Texte

| Field | Name | Typ |
|---|---|---|
| Titel | `cc_banner_title` | text |
| Text | `cc_banner_text` | textarea |
| Button „Alle akzeptieren" | `cc_accept_all` | text |
| Button „Einstellungen" | `cc_settings_btn` | text |
| Button „Ablehnen" | `cc_decline_all` | text |

### Modal-Texte

| Field | Name | Typ |
|---|---|---|
| Modal Titel | `cc_modal_title` | text |
| Einleitungstext | `cc_modal_intro` | textarea |
| Button „Auswahl speichern" | `cc_save_btn` | text |

### Kategorien (Bezeichnungen)

| Field | Name |
|---|---|
| Notwendig – Bezeichnung/Beschreibung | `cc_cat_necessary_label` / `cc_cat_necessary_desc` |
| Statistik – Bezeichnung/Beschreibung | `cc_cat_statistics_label` / `cc_cat_statistics_desc` |
| Marketing – Bezeichnung/Beschreibung | `cc_cat_marketing_label` / `cc_cat_marketing_desc` |
| Komfort – Bezeichnung/Beschreibung | `cc_cat_comfort_label` / `cc_cat_comfort_desc` |

### Code-Snippets

Pro Kategorie je ein Head- und Body-Code Feld. Snippets werden nach Consent des Besuchers automatisch injiziert. Notwendige Snippets werden **immer** geladen.

| Field | Name | Typ | Wann geladen |
|---|---|---|---|
| Notwendig – Head/Body | `cc_snippet_necessary_head/body` | textarea | Immer |
| Statistik – Head/Body | `cc_snippet_statistics_head/body` | textarea | Nach Consent |
| Marketing – Head/Body | `cc_snippet_marketing_head/body` | textarea | Nach Consent |
| Komfort – Head/Body | `cc_snippet_comfort_head/body` | textarea | Nach Consent |


---

## Next Steps

- **Custom Post Types:** [CPT Documentation](08_CUSTOM-POST-TYPES.md)
- **Development:** [Development Guide](06_DEVELOPMENT.md)
- **Deployment:** [Deployment Guide](10_DEPLOYMENT.md)

---

**92 fields ready!** 🎨  
**Next:** [Deployment Guide](10_DEPLOYMENT.md) →
