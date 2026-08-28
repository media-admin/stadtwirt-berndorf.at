# Stadtwirt Berndorf

**Version:** 1.7.0  
**Built on:** Media Lab Starter Kit v1.6  
**Requires:** Media Lab Agency Core Plugin  
**Author:** Media Lab Tritremmel GmbH  
**Theme URI:** https://stadtwirt-berndorf.at

## Description

Custom WordPress Theme für Stadtwirt Berndorf, aufbauend auf dem Media Lab Starter Kit:
- Vite build system
- SCSS with Autoprefixer
- Swiper.js for sliders
- Responsive grid layouts
- 3-level navigation

---

## Requirements

- **Agency Core Plugin** (required)
- WordPress 6.0+ (tested up to 6.7)
- PHP 8.0+
- Node.js 18+ (for development)

---

## Installation

1. Upload theme to `/wp-content/themes/stadtwirt-theme/`
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
stadtwirt-theme/
├── assets/
│   ├── src/          # Source files (SCSS, JS)
│   └── dist/         # Built files
├── inc/               # PHP includes
│   ├── shortcode-overrides.php        # Core Plugin Shortcode-Overrides
│   └── notification-overrides.php     # Notification Session-Reset (siehe unten)
├── templates/         # Template parts
├── functions.php      # Theme setup
└── style.css          # Theme header
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

## Custom Overrides

Diese Overrides sind **projektspezifisch** und nicht Teil des Media Lab Starter Kit / Agency Core Plugins. Sie greifen ausschließlich über Theme-Hooks bzw. clientseitiges JS ein, ohne Core-Plugin-Dateien zu verändern.

### Shortcode-Overrides (`inc/shortcode-overrides.php`)

Überschreibt einzelne Core-Plugin-Shortcodes projektspezifisch via `remove_shortcode()` + `add_shortcode()` auf `init`, Priorität 20.

### Notification Session-Reset (`inc/notification-overrides.php`)

**Zweck:** Popup- und Toast-Notifications (Agency Core, CPT `notification`) merken sich im Standardverhalten dauerhaft in `localStorage`, ob sie bereits weggeklickt wurden (`popup_dismissed_{id}` / `notification_dismissed_{id}`). Auf Kundenwunsch werden diese Notifications stattdessen **einmal pro Besucher-Session** wieder angezeigt, statt nur einmalig für immer.

**Funktionsweise:**
- Hook auf `wp_head`, Priorität `1` — läuft vor `wp_footer`, wo die Notifications-Klasse des Core Plugins initialisiert wird.
- Beim ersten Seitenaufruf einer neuen Session werden alle `popup_dismissed_*`- und `notification_dismissed_*`-Keys aus `localStorage` gelöscht.
- Ein `sessionStorage`-Flag (`ml_notifications_reset_done`) verhindert, dass der Reset bei jeder weiteren Seitennavigation innerhalb derselben Session erneut ausgeführt wird.
- Wird eine Notification innerhalb der Session vom Besucher geschlossen, bleibt sie für den Rest der Session ausgeblendet (Standard-Dismiss-Verhalten des Core Plugins greift normal).
- Neue Session (neuer Tab, Browser-Neustart) → Notifications erscheinen wieder.

**Scope:** Betrifft nur Popup- und Toast-Notifications. Banner haben im Core Plugin ohnehin keine Dismiss-Persistenz und sind von diesem Override nicht betroffen.

**Hinweis:** Kein Backport in die Media Lab Starter Kit vorgesehen — Standardverhalten (dauerhaftes Dismiss) bleibt für alle anderen Projekte unverändert.

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

## Changelog

### 1.7.0
- Notification Session-Reset hinzugefügt (`inc/notification-overrides.php`) — Popup/Toast-Notifications erscheinen 1x pro Session statt nur einmalig dauerhaft

### 1.6.0
- Basis: Media Lab Starter Kit v1.6

---

## License

GPL v2 or later
