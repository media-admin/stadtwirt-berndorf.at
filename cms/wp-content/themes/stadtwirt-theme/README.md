# Custom Theme

**Version:** 1.0.0  
**Requires:** Media Lab Core Plugin  
**Author:** Media Lab Tritremmel GmbH

## Description

A modern, responsive WordPress theme built with:
- Vite build system
- SCSS with Autoprefixer
- Swiper.js for sliders
- Responsive grid layouts
- 3-level navigation

---

## Requirements

- **Agency Core Plugin** (required)
- WordPress 5.9+
- PHP 7.4+
- Node.js 18+ (for development)

---

## Installation

1. Upload theme to `/wp-content/themes/custom-theme/`
2. Install **Agency Core** plugin
3. Activate theme
4. Configure in Customizer

---

## Development

### Setup
```bash
# Install dependencies
npm install

# Development (with HMR)
npm run dev

# Production build
npm run build
```

### File Structure
```
custom-theme/
├── assets/
│   ├── src/          # Source files (SCSS, JS)
│   └── dist/         # Built files
├── inc/              # PHP includes
├── templates/        # Template parts
├── functions.php     # Theme setup
└── style.css         # Theme header
```

---

## Features

### Build System
- Vite with Hot Module Replacement
- SCSS compilation
- Autoprefixer
- Production optimization

### Components Styling
All Agency Core shortcodes are styled:
- Hero Slider
- Pricing Tables
- Team Cards
- Stats/Counters
- And 17+ more!

### Responsive
- Mobile-first approach
- Breakpoints: 640px, 768px, 1024px, 1280px

---

## Customization

### Colors

Edit `assets/src/scss/config/_variables.scss`:
```scss
$color-primary: #667eea;
$color-secondary: #764ba2;
```

### Typography
```scss
$font-family-base: 'Inter', sans-serif;
```

### Spacing
```scss
$spacing-base: 1rem;
```

---

## Theme Support

This theme **requires** Agency Core for:
- Custom Post Types
- Shortcodes
- ACF Fields

Without Agency Core, the theme will display a warning.

---

## License

GPL v2 or later