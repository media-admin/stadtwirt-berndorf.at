# Shortcodes Reference

**Version:** 1.4.0  
**Letzte Aktualisierung:** 2026-03-04  
**Plugin:** Media Lab Agency Core v1.5.1

Complete reference for all 44 shortcodes provided by the Core Plugin.

---

## Table of Contents

1. [Overview](#overview)
2. [Layout Shortcodes](#layout-shortcodes)
3. [Content Shortcodes](#content-shortcodes)
4. [Interactive Shortcodes](#interactive-shortcodes)
5. [Media Shortcodes](#media-shortcodes)
6. [WooCommerce Shortcodes](#woocommerce-shortcodes)
7. [Best Practices](#best-practices)
8. [Troubleshooting](#troubleshooting)

---

## Overview

### What Are Shortcodes?

Shortcodes are simple codes you can insert into pages/posts to add complex functionality:
```
[shortcode_name attribute="value"]Content[/shortcode_name]
```

### Where to Use

- WordPress Editor (Gutenberg)
- Classic Editor
- Widget areas
- Template files (via `do_shortcode()`)
- ACF WYSIWYG fields

### Provided By

All shortcodes are provided by **Media Lab Agency Core Plugin**. Ensure it's active:
```bash
wp plugin is-active media-lab-agency-core
```

---

## Layout Shortcodes

### Hero Slider

Fullscreen hero slider with multiple slides.

**Usage:**
```
[hero_slider autoplay="true" interval="5000"]
  [hero_slide 
    title="Welcome" 
    subtitle="To our website"
    button_text="Learn More"
    button_link="/about"
    image="https://example.com/hero1.jpg"]
  [/hero_slide]
  
  [hero_slide 
    title="Our Services" 
    subtitle="What we offer"
    button_text="View Services"
    button_link="/services"
    image="https://example.com/hero2.jpg"]
  [/hero_slide]
[/hero_slider]
```

**Attributes:**
- `autoplay` - Enable autoplay (true/false)
- `interval` - Slide interval in ms (default: 5000)
- `navigation` - Show navigation arrows (true/false)
- `pagination` - Show pagination dots (true/false)

**Slide Attributes:**
- `title` - Main headline
- `subtitle` - Secondary text
- `button_text` - CTA button text
- `button_link` - Button URL
- `image` - Background image URL
- `overlay` - Dark overlay opacity (0-1)

### Accordion

Collapsible content sections.

**Usage:**
```
[accordion]
  [accordion_item title="Section 1" open="true"]
    Content for section 1
  [/accordion_item]
  
  [accordion_item title="Section 2"]
    Content for section 2
  [/accordion_item]
[/accordion]
```

**Attributes:**
- `style` - Style variant (default/bordered/minimal)
- `multiple` - Allow multiple open (true/false)

**Item Attributes:**
- `title` - Section title
- `open` - Initially open (true/false)
- `icon` - Custom icon class

### Tabs

Tabbed content interface.

**Usage:**
```
[tabs]
  [tab title="Tab 1" active="true"]
    Content for tab 1
  [/tab]
  
  [tab title="Tab 2"]
    Content for tab 2
  [/tab]
[/tabs]
```

**Attributes:**
- `style` - Style variant (default/pills/underline)
- `vertical` - Vertical layout (true/false)

**Tab Attributes:**
- `title` - Tab label
- `active` - Initially active (true/false)
- `icon` - Tab icon class

### Timeline

Vertical timeline for events/milestones.

**Usage:**
```
[timeline]
  [timeline_item 
    date="2024" 
    title="Company Founded"
    icon="flag"]
    Started with a vision
  [/timeline_item]
  
  [timeline_item 
    date="2025" 
    title="First Product"
    icon="rocket"]
    Launched our first product
  [/timeline_item]
[/timeline]
```

**Attributes:**
- `style` - Timeline style (default/minimal)
- `alternate` - Alternating layout (true/false)

**Item Attributes:**
- `date` - Event date/year
- `title` - Event title
- `icon` - Icon class
- `highlight` - Highlight item (true/false)

---

## Content Shortcodes

### Stats Counter

Animated statistics display.

**Usage:**
```
[stats columns="3"]
  [stat number="1000" label="Projects" icon="check" suffix="+"]
  [stat number="50" label="Team Members" icon="users"]
  [stat number="99" label="Satisfaction" icon="heart" suffix="%"]
[/stats]
```

**Attributes:**
- `columns` - Number of columns (2/3/4)
- `style` - Style variant (default/cards/minimal)

**Stat Attributes:**
- `number` - Number to count to
- `label` - Stat description
- `icon` - Icon class
- `prefix` - Number prefix (e.g., "$")
- `suffix` - Number suffix (e.g., "+", "%")
- `duration` - Animation duration (ms)

### Testimonials

Customer testimonials carousel/grid.

**Usage:**
```
[testimonials layout="carousel" columns="3"]
  [testimonial 
    author="John Doe" 
    role="CEO, Company"
    rating="5"
    image="https://example.com/avatar.jpg"]
    Great service, highly recommended!
  [/testimonial]
  
  [testimonial 
    author="Jane Smith" 
    role="Marketing Director"
    rating="5"]
    Outstanding results!
  [/testimonial]
[/testimonials]
```

**Attributes:**
- `layout` - Display layout (carousel/grid/list)
- `columns` - Grid columns (2/3/4)
- `autoplay` - Carousel autoplay (true/false)
- `show_rating` - Show star rating (true/false)

**Testimonial Attributes:**
- `author` - Person name
- `role` - Job title/company
- `rating` - Star rating (1-5)
- `image` - Avatar image URL

### FAQ

Frequently asked questions.

**Usage:**
```
[faq style="accordion"]
  [faq_item question="How does it work?"]
    Detailed answer here...
  [/faq_item]
  
  [faq_item question="What is the price?"]
    Pricing information...
  [/faq_item]
[/faq]
```

**Attributes:**
- `style` - Display style (accordion/list/cards)
- `search` - Enable search (true/false)
- `categories` - Filter by categories (true/false)

**Item Attributes:**
- `question` - Question text
- `category` - FAQ category
- `open` - Initially open (true/false)

---

## Interactive Shortcodes

### Modal

Popup modal windows.

**Usage:**
```
[modal 
  id="contact-modal"
  trigger_text="Contact Us"
  trigger_class="btn btn-primary"
  title="Get in Touch"
  size="medium"]
  
  <p>Modal content here...</p>
  [contact-form-7 id="123"]
  
[/modal]
```

**Attributes:**
- `id` - Unique modal ID
- `trigger_text` - Button text
- `trigger_class` - Button CSS classes
- `title` - Modal title
- `size` - Modal size (small/medium/large)
- `close_button` - Show close button (true/false)

**Trigger from elsewhere:**
```html
<button data-modal-trigger="contact-modal">Open Modal</button>
```

### Video Player

Responsive video embed with custom player.

**Usage:**
```
[video_player 
  url="https://www.youtube.com/watch?v=VIDEO_ID"
  autoplay="false"
  controls="true"
  poster="https://example.com/poster.jpg"
  width="100%"
  aspect="16:9"]
[/video_player]
```

**Attributes:**
- `url` - Video URL (YouTube, Vimeo, or direct)
- `autoplay` - Autoplay video (true/false)
- `controls` - Show controls (true/false)
- `poster` - Poster image URL
- `width` - Player width
- `aspect` - Aspect ratio (16:9, 4:3, 1:1)
- `muted` - Start muted (true/false)

### Carousel

Multi-purpose content carousel.

**Usage:**
```
[carousel items="3" autoplay="true" interval="3000"]
  [carousel_item]
    <img src="image1.jpg" alt="Item 1">
    <h3>Title 1</h3>
  [/carousel_item]
  
  [carousel_item]
    <img src="image2.jpg" alt="Item 2">
    <h3>Title 2</h3>
  [/carousel_item]
[/carousel]
```

**Attributes:**
- `items` - Items per view (1/2/3/4)
- `autoplay` - Enable autoplay (true/false)
- `interval` - Slide interval (ms)
- `loop` - Loop slides (true/false)
- `gap` - Space between items (px)
- `navigation` - Show arrows (true/false)
- `pagination` - Show dots (true/false)

---

## Media Shortcodes

### Logo Carousel

Client logos or partner logos.

**Usage:**
```
[logo_carousel items="5" autoplay="true" grayscale="true"]
  [logo image="logo1.png" link="https://client1.com" alt="Client 1"]
  [logo image="logo2.png" link="https://client2.com" alt="Client 2"]
  [logo image="logo3.png" link="https://client3.com" alt="Client 3"]
[/logo_carousel]
```

**Attributes:**
- `items` - Logos per view (3/4/5/6)
- `autoplay` - Enable autoplay (true/false)
- `speed` - Scroll speed (ms)
- `grayscale` - Grayscale by default (true/false)
- `hover_color` - Color on hover (true/false)

**Logo Attributes:**
- `image` - Logo image URL
- `link` - Logo link URL
- `alt` - Alt text
- `title` - Tooltip title

### Image Gallery

Responsive image gallery with lightbox.

**Usage:**
```
[image_gallery columns="3" lightbox="true" captions="true"]
  [gallery_image 
    src="image1.jpg" 
    thumb="thumb1.jpg"
    alt="Image 1"
    caption="Photo caption"]
  [/gallery_image]
  
  [gallery_image src="image2.jpg" alt="Image 2"]
[/image_gallery]
```

**Attributes:**
- `columns` - Grid columns (2/3/4/5)
- `gap` - Space between images (px)
- `lightbox` - Enable lightbox (true/false)
- `captions` - Show captions (true/false)
- `lazy` - Lazy loading (true/false)

---

## WooCommerce Shortcodes

### Product Grid

Custom product display (requires WooCommerce).

**Usage:**
```
[product_grid 
  posts="8" 
  columns="4"
  category="featured"
  orderby="date"
  order="DESC"]
[/product_grid]
```

**Attributes:**
- `posts` - Number of products
- `columns` - Grid columns (2/3/4)
- `category` - Product category slug
- `tag` - Product tag slug
- `orderby` - Sort field (date/price/title/rand)
- `order` - Sort direction (ASC/DESC)
- `on_sale` - Show only sale items (true/false)

### Product Slider

Products in carousel format.

**Usage:**
```
[product_slider 
  posts="12"
  items="4"
  category="new-arrivals"
  autoplay="true"]
[/product_slider]
```

**Attributes:**
- `posts` - Number of products
- `items` - Items per view (2/3/4)
- `category` - Product category
- `autoplay` - Enable autoplay (true/false)
- `interval` - Slide interval (ms)

---

## Best Practices

### 1. Always Close Shortcodes
```
❌ [accordion]Content
✅ [accordion]Content[/accordion]
```

### 2. Quote Attributes
```
❌ [stat number=100 label=Projects]
✅ [stat number="100" label="Projects"]
```

### 3. Use Proper Nesting
```
✅ Correct:
[tabs]
  [tab title="Tab 1"]Content[/tab]
[/tabs]

❌ Wrong:
[tabs][tab title="Tab 1"]Content[/tabs][/tab]
```

### 4. Escape Special Characters
```php
// In PHP
echo do_shortcode('[stat number="1000" label="Projects"]');

// With quotes in content
[modal title="We're here to help"]Content[/modal]
```

### 5. Test in Staging

Always test new shortcodes in staging before production.

---

## Troubleshooting

### Shortcode Shows as Text

**Problem:** `[accordion]Content[/accordion]` displays as text  
**Solution:**
```bash
# Verify Core Plugin active
wp plugin is-active media-lab-agency-core

# Check shortcode registration
wp eval 'global $shortcode_tags; print_r(array_keys($shortcode_tags));'
```

### Shortcode Not Working

**Problem:** Shortcode has no effect  
**Solution:**
- Check syntax (quotes, closing tags)
- Clear cache
- Check browser console for JS errors
- Verify attribute names

### Styles Not Applied

**Problem:** Shortcode renders but looks wrong  
**Solution:**
```bash
# Rebuild assets
npm run build

# Clear WordPress cache
wp cache flush

# Check if CSS file loads
# View page source, search for: main-*.css
```

### Nested Shortcodes Issue

**Problem:** Nested shortcodes not parsing  
**Solution:**
```
Use different shortcode pairs:
[outer]
  [inner]Content[/inner]
[/outer]
```

---

## Next Steps

- **AJAX Features:** [AJAX Documentation](05_AJAX-FEATURES.md)
- **Custom Post Types:** [CPT Documentation](08_CUSTOM-POST-TYPES.md)
- **Development:** [Development Guide](06_DEVELOPMENT.md)

---

**44 Shortcodes at your fingertips!** 🎨  
**Next:** [AJAX Features](05_AJAX-FEATURES.md) →
