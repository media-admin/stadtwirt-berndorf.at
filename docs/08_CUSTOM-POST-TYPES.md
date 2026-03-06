# Custom Post Types Reference

**Version:** 1.4.0  
**Letzte Aktualisierung:** 2026-03-04  
**Plugin:** Media Lab Project Starter v1.0.0 (optional)

Complete reference for all 9 Custom Post Types provided by the Project Starter plugin.

---

## Table of Contents

1. [Overview](#overview)
2. [Team Members](#team-members)
3. [Projects](#projects)
4. [Jobs](#jobs)
5. [Services](#services)
6. [Testimonials](#testimonials)
7. [FAQ](#faq)
8. [Google Maps](#google-maps)
9. [Hero Slides](#hero-slides)
10. [Carousel Items](#carousel-items)
11. [Usage Examples](#usage-examples)
12. [Customization](#customization)

---

## Overview

### What Are Custom Post Types?

Custom Post Types (CPTs) extend WordPress beyond posts and pages, allowing you to create specific content types like Team Members, Projects, Services, etc.

### Provided By

All CPTs are provided by **Media Lab Project Starter Plugin** (optional – im Repo vorhanden, pro Projekt aktivierbar):
```
cms/wp-content/plugins/media-lab-project-starter/inc/custom-post-types.php
```

> **Hinweis:** Das Plugin ist nicht im Standard-Setup aktiviert. Für neue Projekte aktivieren via:
> ```bash
> cd cms && wp plugin activate media-lab-project-starter
> ```

### Available CPTs

| Post Type | Slug | Description | Archive |
|-----------|------|-------------|---------|
| Team | `team` | Team members | Yes |
| Project | `project` | Portfolio projects | Yes |
| Job | `job` | Job listings | Yes |
| Service | `service` | Services offered | Yes |
| Testimonial | `testimonial` | Client testimonials | No |
| FAQ | `faq` | FAQ items | No |
| Google Map | `gmap` | Map locations | No |
| Hero Slide | `hero_slide` | Slider slides | No |
| Carousel | `carousel` | Carousel items | No |

### Associated Taxonomies

Each CPT has associated taxonomies for organization:

- `project_category` - Organize projects
- `service_category` - Organize services
- `faq_category` - Organize FAQs
- `carousel_category` - Organize carousel items
- `job_category` - Organize jobs
- `job_type` - Full-time, Part-time, etc.
- `job_location` - Remote, Office, etc.

**See:** [ACF Fields Documentation](09_ACF-FIELDS.md) for custom fields

---

## Team Members

### Overview

Display team members, staff, or employees.

**Post Type:** `team`  
**Slug:** `/team/` (singular)  
**Archive:** `/team/` (plural)  
**Supports:** Title, Editor, Thumbnail, Excerpt  
**Hierarchical:** No

### ACF Fields

- `position` - Job title (Text)
- `email` - Contact email (Email)
- `phone` - Phone number (Text)
- `social_links` - Social media (Repeater)
  - `platform` - Platform name
  - `url` - Profile URL

### Usage

**Create Team Member:**
```php
$team_member = wp_insert_post([
    'post_type' => 'team',
    'post_title' => 'John Doe',
    'post_content' => 'Bio text here...',
    'post_status' => 'publish'
]);

// Add custom fields
update_field('position', 'CEO', $team_member);
update_field('email', 'john@example.com', $team_member);
```

**Query Team Members:**
```php
$team = new WP_Query([
    'post_type' => 'team',
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC'
]);

if ($team->have_posts()) :
    while ($team->have_posts()) : $team->the_post();
        $position = get_field('position');
        $email = get_field('email');
        ?>
        <div class="team-member">
            <?php the_post_thumbnail('medium'); ?>
            <h3><?php the_title(); ?></h3>
            <p class="position"><?php echo esc_html($position); ?></p>
            <a href="mailto:<?php echo esc_attr($email); ?>">Contact</a>
        </div>
        <?php
    endwhile;
endif;
wp_reset_postdata();
```

**Template File:** `single-team.php` or `singular.php`

### Display Example
```php
<!-- In template -->
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    
    <article class="team-member-single">
        <div class="member-header">
            <?php the_post_thumbnail('large'); ?>
            <h1><?php the_title(); ?></h1>
            <p class="position"><?php the_field('position'); ?></p>
        </div>
        
        <div class="member-bio">
            <?php the_content(); ?>
        </div>
        
        <div class="member-contact">
            <a href="mailto:<?php the_field('email'); ?>">
                Email: <?php the_field('email'); ?>
            </a>
            <a href="tel:<?php the_field('phone'); ?>">
                Phone: <?php the_field('phone'); ?>
            </a>
        </div>
        
        <?php if (have_rows('social_links')) : ?>
            <div class="member-social">
                <?php while (have_rows('social_links')) : the_row(); ?>
                    <a href="<?php the_sub_field('url'); ?>" target="_blank">
                        <?php the_sub_field('platform'); ?>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </article>
    
<?php endwhile; endif; ?>
```

---

## Projects

### Overview

Portfolio projects, case studies, or work samples.

**Post Type:** `project`  
**Slug:** `/projects/` (plural)  
**Archive:** `/projects/`  
**Supports:** Title, Editor, Thumbnail, Excerpt  
**Hierarchical:** No  
**Taxonomy:** `project_category`

### ACF Fields

- `client_name` - Client name (Text)
- `project_date` - Completion date (Date Picker)
- `project_url` - Live URL (URL)
- `technologies` - Technologies used (Checkbox)
- `gallery` - Project images (Gallery)
- `testimonial` - Client testimonial (Textarea)

### Usage

**Create Project:**
```php
$project = wp_insert_post([
    'post_type' => 'project',
    'post_title' => 'Website Redesign',
    'post_content' => 'Project description...',
    'post_status' => 'publish'
]);

// Add to category
wp_set_object_terms($project, 'web-design', 'project_category');

// Add custom fields
update_field('client_name', 'ACME Corp', $project);
update_field('project_url', 'https://acmecorp.com', $project);
```

**Query Projects by Category:**
```php
$projects = new WP_Query([
    'post_type' => 'project',
    'posts_per_page' => 6,
    'tax_query' => [
        [
            'taxonomy' => 'project_category',
            'field' => 'slug',
            'terms' => 'web-design'
        ]
    ]
]);
```

**Template File:** `single-project.php` or `archive-project.php`

---

## Jobs

### Overview

Job listings and career opportunities.

**Post Type:** `job`  
**Slug:** `/jobs/` or `/careers/`  
**Archive:** `/jobs/`  
**Supports:** Title, Editor, Excerpt  
**Hierarchical:** No  
**Taxonomies:** `job_category`, `job_type`, `job_location`

### ACF Fields

- `salary_range` - Salary information (Text)
- `employment_type` - Full-time, Part-time, etc. (Select)
- `location` - Job location (Text)
- `remote` - Remote work option (True/False)
- `application_deadline` - Deadline (Date Picker)
- `application_email` - Email for applications (Email)
- `requirements` - Job requirements (Wysiwyg)
- `benefits` - Benefits offered (Wysiwyg)

### Usage

**Create Job:**
```php
$job = wp_insert_post([
    'post_type' => 'job',
    'post_title' => 'Senior Developer',
    'post_content' => 'Job description...',
    'post_status' => 'publish'
]);

// Add taxonomies
wp_set_object_terms($job, 'development', 'job_category');
wp_set_object_terms($job, 'full-time', 'job_type');
wp_set_object_terms($job, 'remote', 'job_location');

// Add fields
update_field('salary_range', '$80k - $120k', $job);
update_field('remote', true, $job);
```

**Query Open Jobs:**
```php
$jobs = new WP_Query([
    'post_type' => 'job',
    'posts_per_page' => -1,
    'meta_query' => [
        [
            'key' => 'application_deadline',
            'value' => date('Ymd'),
            'compare' => '>=',
            'type' => 'DATE'
        ]
    ]
]);
```

**Template File:** `single-job.php` or `archive-job.php`

---

## Services

### Overview

Services or products offered.

**Post Type:** `service`  
**Slug:** `/services/`  
**Archive:** `/services/`  
**Supports:** Title, Editor, Thumbnail, Excerpt, Page Attributes  
**Hierarchical:** Yes  
**Taxonomy:** `service_category`

### ACF Fields

- `icon` - Service icon (Text/Image)
- `price` - Pricing information (Text)
- `duration` - Service duration (Text)
- `features` - Key features (Repeater)
- `cta_text` - Call-to-action text (Text)
- `cta_link` - CTA link (URL)

### Usage

**Create Service:**
```php
$service = wp_insert_post([
    'post_type' => 'service',
    'post_title' => 'Web Development',
    'post_content' => 'Service description...',
    'post_status' => 'publish',
    'menu_order' => 1  // For ordering
]);

update_field('icon', 'fa-code', $service);
update_field('price', 'Starting at $5000', $service);
```

**Query Services (Ordered):**
```php
$services = new WP_Query([
    'post_type' => 'service',
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC'
]);
```

**Template File:** `single-service.php` or `archive-service.php`

---

## Testimonials

### Overview

Client testimonials and reviews.

**Post Type:** `testimonial`  
**Slug:** N/A (no single view by default)  
**Archive:** No  
**Supports:** Title, Editor, Thumbnail  
**Hierarchical:** No

### ACF Fields

- `client_name` - Client name (Text)
- `client_role` - Job title (Text)
- `client_company` - Company name (Text)
- `rating` - Star rating (Number, 1-5)
- `featured` - Featured testimonial (True/False)

### Usage

**Create Testimonial:**
```php
$testimonial = wp_insert_post([
    'post_type' => 'testimonial',
    'post_title' => 'Great Service!',
    'post_content' => 'Testimonial text...',
    'post_status' => 'publish'
]);

update_field('client_name', 'Jane Smith', $testimonial);
update_field('client_company', 'Tech Corp', $testimonial);
update_field('rating', 5, $testimonial);
```

**Query Testimonials:**
```php
$testimonials = new WP_Query([
    'post_type' => 'testimonial',
    'posts_per_page' => 6,
    'orderby' => 'rand',
    'meta_query' => [
        [
            'key' => 'featured',
            'value' => '1',
            'compare' => '='
        ]
    ]
]);
```

**Display with Shortcode:**
```
[testimonials layout="carousel" columns="3"]
```

---

## FAQ

### Overview

Frequently asked questions.

**Post Type:** `faq`  
**Slug:** N/A  
**Archive:** No  
**Supports:** Title, Editor  
**Hierarchical:** No  
**Taxonomy:** `faq_category`

### ACF Fields

- `answer` - FAQ answer (Wysiwyg)
- `order` - Display order (Number)

### Usage

**Create FAQ:**
```php
$faq = wp_insert_post([
    'post_type' => 'faq',
    'post_title' => 'How does it work?',
    'post_content' => 'Detailed answer...',
    'post_status' => 'publish'
]);

wp_set_object_terms($faq, 'general', 'faq_category');
update_field('order', 1, $faq);
```

**Query FAQs:**
```php
$faqs = new WP_Query([
    'post_type' => 'faq',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'faq_category',
            'field' => 'slug',
            'terms' => 'general'
        ]
    ],
    'meta_key' => 'order',
    'orderby' => 'meta_value_num',
    'order' => 'ASC'
]);
```

**Display with Shortcode:**
```
[faq style="accordion"]
```

---

## Google Maps

### Overview

Map locations for display.

**Post Type:** `gmap`  
**Slug:** N/A  
**Archive:** No  
**Supports:** Title, Editor  
**Hierarchical:** No

### ACF Fields

- `location` - Map location (Google Map)
- `address` - Full address (Text)
- `phone` - Location phone (Text)
- `email` - Location email (Email)
- `hours` - Opening hours (Textarea)

### Usage

**Create Map Location:**
```php
$location = wp_insert_post([
    'post_type' => 'gmap',
    'post_title' => 'Office Location',
    'post_status' => 'publish'
]);

// ACF Google Map field format
update_field('location', [
    'address' => 'Vienna, Austria',
    'lat' => 48.2082,
    'lng' => 16.3738
], $location);
```

**Display Map:**
```php
$location = get_field('location');
if ($location) : ?>
    <div class="acf-map">
        <div class="marker" 
             data-lat="<?php echo esc_attr($location['lat']); ?>"
             data-lng="<?php echo esc_attr($location['lng']); ?>">
            <h4><?php the_title(); ?></h4>
            <p><?php echo esc_html($location['address']); ?></p>
        </div>
    </div>
<?php endif; ?>
```

---

## Hero Slides

### Overview

Slides for hero slider.

**Post Type:** `hero_slide`  
**Slug:** N/A  
**Archive:** No  
**Supports:** Title, Editor, Thumbnail, Page Attributes  
**Hierarchical:** No

### ACF Fields

- `subtitle` - Slide subtitle (Text)
- `button_text` - CTA button text (Text)
- `button_link` - CTA link (URL)
- `background_video` - Background video (File)
- `overlay_opacity` - Dark overlay (Range, 0-1)

### Usage

**Create Slide:**
```php
$slide = wp_insert_post([
    'post_type' => 'hero_slide',
    'post_title' => 'Welcome to Our Site',
    'post_content' => 'Slide content...',
    'post_status' => 'publish',
    'menu_order' => 1
]);

update_field('subtitle', 'Your tagline here', $slide);
update_field('button_text', 'Learn More', $slide);
update_field('button_link', '/about', $slide);
```

**Query for Slider:**
```php
$slides = new WP_Query([
    'post_type' => 'hero_slide',
    'posts_per_page' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'post_status' => 'publish'
]);
```

**Use with Shortcode:**
```
[hero_slider autoplay="true"]
```

---

## Carousel Items

### Overview

Items for carousels (logos, images, etc.).

**Post Type:** `carousel`  
**Slug:** N/A  
**Archive:** No  
**Supports:** Title, Editor, Thumbnail, Page Attributes  
**Hierarchical:** No  
**Taxonomy:** `carousel_category`

### ACF Fields

- `link` - Item link (URL)
- `link_target` - Open in new tab (True/False)
- `subtitle` - Item subtitle (Text)

### Usage

**Create Carousel Item:**
```php
$item = wp_insert_post([
    'post_type' => 'carousel',
    'post_title' => 'Client Logo',
    'post_status' => 'publish',
    'menu_order' => 1
]);

wp_set_object_terms($item, 'client-logos', 'carousel_category');
update_field('link', 'https://client.com', $item);
```

**Query Items:**
```php
$items = new WP_Query([
    'post_type' => 'carousel',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'carousel_category',
            'field' => 'slug',
            'terms' => 'client-logos'
        ]
    ],
    'orderby' => 'menu_order',
    'order' => 'ASC'
]);
```

**Use with Shortcode:**
```
[logo_carousel items="5" autoplay="true"]
```

---

## Usage Examples

### Display Team Grid
```php
<div class="team-grid">
    <?php
    $team = new WP_Query([
        'post_type' => 'team',
        'posts_per_page' => -1
    ]);
    
    while ($team->have_posts()) : $team->the_post();
        ?>
        <div class="team-member">
            <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail('medium'); ?>
                <h3><?php the_title(); ?></h3>
                <p><?php the_field('position'); ?></p>
            </a>
        </div>
        <?php
    endwhile;
    wp_reset_postdata();
    ?>
</div>
```

### Display Services with Icons
```php
<div class="services-list">
    <?php
    $services = new WP_Query([
        'post_type' => 'service',
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ]);
    
    while ($services->have_posts()) : $services->the_post();
        ?>
        <div class="service-item">
            <i class="<?php the_field('icon'); ?>"></i>
            <h3><?php the_title(); ?></h3>
            <?php the_excerpt(); ?>
            <a href="<?php the_permalink(); ?>">Learn More</a>
        </div>
        <?php
    endwhile;
    wp_reset_postdata();
    ?>
</div>
```

### Display Recent Projects
```php
<div class="projects-grid">
    <?php
    $projects = new WP_Query([
        'post_type' => 'project',
        'posts_per_page' => 6,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    while ($projects->have_posts()) : $projects->the_post();
        $client = get_field('client_name');
        $url = get_field('project_url');
        ?>
        <article class="project-card">
            <?php the_post_thumbnail('large'); ?>
            <h3><?php the_title(); ?></h3>
            <?php if ($client) : ?>
                <p class="client">Client: <?php echo esc_html($client); ?></p>
            <?php endif; ?>
            <?php the_excerpt(); ?>
            <?php if ($url) : ?>
                <a href="<?php echo esc_url($url); ?>" target="_blank">View Live</a>
            <?php endif; ?>
            <a href="<?php the_permalink(); ?>">View Case Study</a>
        </article>
        <?php
    endwhile;
    wp_reset_postdata();
    ?>
</div>
```

---

## Customization

### Adding New CPT

**1. Edit Plugin File:**
```php
// media-lab-project-starter/inc/custom-post-types.php

// Add new CPT
register_post_type('product', [
    'label' => 'Products',
    'public' => true,
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
    'has_archive' => true,
    'rewrite' => ['slug' => 'products'],
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-products'
]);
```

**2. Flush Rewrite Rules:**
```bash
wp rewrite flush
```

**3. Add ACF Fields:**
- Go to: Custom Fields → Add New
- Create field group for "product"
- Export to JSON

### Modifying Existing CPT

**Change Archive Slug:**
```php
// In register_post_type()
'rewrite' => ['slug' => 'our-team'],  // Was: 'team'
```

**Add Custom Columns:**
```php
// Admin columns
add_filter('manage_team_posts_columns', function($columns) {
    $columns['position'] = 'Position';
    return $columns;
});

add_action('manage_team_posts_custom_column', function($column, $post_id) {
    if ($column === 'position') {
        echo esc_html(get_field('position', $post_id));
    }
}, 10, 2);
```

---

## Next Steps

- **ACF Fields:** [ACF Documentation](09_ACF-FIELDS.md)
- **Development:** [Development Guide](06_DEVELOPMENT.md)
- **Shortcodes:** [Shortcodes Reference](04_SHORTCODES.md)

---

**9 CPTs ready to use!** 📋  
**Next:** [ACF Fields](09_ACF-FIELDS.md) →
