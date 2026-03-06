# Development Guide

**Version:** 1.6.0  
**Letzte Aktualisierung:** 2026-03-04

---

## Inhaltsverzeichnis

1. [Setup](#setup)
2. [Build-System](#build-system)
3. [SCSS-Architektur](#scss-architektur)
4. [JavaScript-Architektur](#javascript-architektur)
5. [PHP-Entwicklung](#php-entwicklung)
6. [Git-Workflow](#git-workflow)
7. [Best Practices](#best-practices)
8. [Debugging](#debugging)

---

## Setup

### Voraussetzungen prüfen

```bash
php -v      # 8.0+
node -v     # 18+
npm -v      # 9+
composer -v # 2.0+
git --version
```

### Lokale Umgebung (Valet)

```bash
cd ~/Valet-Umgebung/media-lab-starter-kit
valet link
# Erreichbar unter: http://media-lab-starter-kit.test
```

### wp-config.php für Development

```php
define('WP_DEBUG',         true);
define('WP_DEBUG_LOG',     true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG',     true);
define('WP_MEMORY_LIMIT',  '256M');
```

---

## Build-System

### Vite-Konfiguration

Die Build-Config liegt im **Projekt-Root** (`vite.config.js`), nicht im Theme-Ordner.

**Entry Points:**
```
assets/src/js/main.js             → dist/js/main.js       (Kern)
assets/src/js/components/
  ajax-filters.js                 → dist/js/ajax-filters.js
  ajax-search.js                  → dist/js/ajax-search.js
  load-more.js                    → dist/js/load-more.js
  google-maps.js                  → dist/js/google-maps.js
  notifications.js                → dist/js/notifications.js
```

**Befehle:**

```bash
npm run dev      # Hot Reload (Valet-URL: media-lab-starter-kit.test)
npm run build    # Production: minifiziert, console.log entfernt
npm run watch    # Watch ohne Dev-Server
```

**Was `npm run build` macht:**
- Terser: entfernt alle `console.log`, `console.info`, `console.debug`, `debugger`
- SCSS: Autoprefixer, komprimiert zu einer `style.css`
- JS: Code-Splitting in 6 Chunks + dynamische Sub-Chunks
- Output: `assets/dist/` (nicht in Git committen)

### Legacy-Warning unterdrücken

Die Warning `[legacy-js-api]` kommt von Vite intern und ist kein eigener Code – sie verschwindet sobald Vite intern auf die neue Sass-API umstellt. Kein Handlungsbedarf.

---

## SCSS-Architektur

### Ordnerstruktur

```
assets/src/scss/
├── abstracts/
│   ├── _index.scss        ← @forward Entry Point (nicht direkt bearbeiten)
│   ├── _variables.scss    ← Alle Design-Tokens
│   └── _mixins.scss       ← Alle Mixins
├── base/
│   ├── _reset.scss
│   ├── _typography.scss
│   ├── _global.scss
│   └── _grid-fix.scss
├── components/            ← 35 Partials
├── layout/
│   ├── _header.scss
│   ├── _footer.scss
│   ├── _navigation.scss
│   ├── _grid.scss
│   └── _top-header.scss
├── templates/
│   ├── _page-builder.scss
│   └── _search-results.scss
├── utilities/
│   ├── _animations.scss
│   └── _helpers.scss
├── woocommerce/
│   └── _woocommerce.scss
└── style.scss             ← Haupt-Entry
```

### @use / @forward System

Alle Partials importieren Tokens und Mixins selbst – kein globaler Import nötig:

```scss
// Jedes Partial beginnt mit:
@use '../abstracts' as *;

// Damit sind alle Tokens und Mixins ohne Namespace verfügbar:
color: $color-primary;
@include respond-to('md') { ... }
```

`abstracts/_index.scss` leitet weiter – nur `_variables.scss` und `_mixins.scss` enthalten echten Code.

### Neue Komponente erstellen

```scss
// components/_meine-komponente.scss
@use '../abstracts' as *;

.meine-komponente {
  color: $color-primary;
  padding: $spacing-md;

  @include respond-to('md') {
    padding: $spacing-lg;
  }
}
```

Dann in `style.scss` einbinden:
```scss
@use 'components/meine-komponente';
```

### Button-System

Buttons werden zentral über Mixins in `abstracts/_mixins.scss` gesteuert.

**In HTML** (direkte Klassen):
```html
<button class="btn btn--primary">Primär</button>
<button class="btn btn--outline">Outline</button>
<button class="btn btn--ghost">Ghost</button>
<a href="#" class="btn btn--primary btn--lg">Groß</a>
<button class="btn btn--outline btn--sm">Klein</button>
<button class="btn btn--primary btn--full">Volle Breite</button>
```

**In SCSS-Komponenten** (für BEM-Elemente):
```scss
.meine-komponente__button {
    @include btn-base;      // Basis: display, padding, border-radius, transition …
    @include btn-primary;   // Farbe: Primary filled
    // @include btn-outline; // Farbe: Outline
    // @include btn-ghost;   // Farbe: dezent
    // @include btn-sm;      // Größe: klein
    // @include btn-lg;      // Größe: groß
}
```

| Mixin | Beschreibung |
|---|---|
| `btn-base` | Pflicht-Basis: layout, cursor, transition, focus-ring |
| `btn-primary` | Filled, Primärfarbe |
| `btn-outline` | Umrandet, Primärfarbe |
| `btn-ghost` | Dezent, Border-Farbe |
| `btn-sm` | Kleinere Größe |
| `btn-lg` | Größere Größe |

**Niemals** duplizierte Button-Styles in Komponenten schreiben – immer `@include` verwenden.


### Design-Tokens (Auswahl)

```scss
// Farben
$color-primary        #e00000
$color-secondary      #1a1a2e
$color-success        #0a8754
$color-warning        #f59e0b
$color-error          #dc2626

// Spacing
$spacing-xs   0.25rem
$spacing-sm   0.5rem
$spacing-md   1rem
$spacing-lg   1.5rem
$spacing-xl   2rem
$spacing-2xl  3rem

// Breakpoints
$breakpoint-sm   480px
$breakpoint-md   768px
$breakpoint-lg   1024px
$breakpoint-xl   1280px

// Vorkompilierte Farbvarianten (statt darken/lighten)
$color-primary-dark       #cc0000
$color-primary-darker     #b20000
$color-warning-light-bg   #fdebce
```

---

## JavaScript-Architektur

### Dynamic Import Prinzip

Kein Modul wird geladen bevor geprüft wurde ob das DOM-Element existiert:

```javascript
// main.js – Kern-Komponenten (immer geladen)
import Navigation from './components/navigation';
new Navigation();

// Lazy – nur wenn Element auf der Seite vorhanden
if (document.querySelector('.accordion')) {
  const { default: Accordion } = await import('./components/accordion');
  new Accordion();
}
```

### Neue Komponente erstellen

**1. Komponenten-File:**
```javascript
// assets/src/js/components/meine-komponente.js

export default class MeineKomponente {
  constructor() {
    this.elements = document.querySelectorAll('.meine-komponente');
    if (!this.elements.length) return;
    this.init();
  }

  init() {
    this.elements.forEach(el => {
      el.addEventListener('click', this.handleClick.bind(this));
    });
  }

  handleClick(e) {
    // Logik
  }
}
```

**2. In main.js registrieren:**
```javascript
if (has('.meine-komponente')) {
  const { default: MeineKomponente } = await import('./components/meine-komponente');
  safeInit('MeineKomponente', () => new MeineKomponente());
}
```

### AJAX-Pattern

```javascript
const response = await fetch(window.customTheme.ajaxUrl, {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    action: 'meine_action',
    nonce:  window.customTheme.nonce,
    data:   JSON.stringify(payload),
  }),
});

const result = await response.json();
if (!result.success) throw new Error(result.data);
```

### `window.customTheme` Objekt

Wird via `wp_localize_script` in `enqueue.php` gesetzt:

```javascript
window.customTheme = {
  ajaxUrl:          '/wp-admin/admin-ajax.php',
  nonce:            '...',
  searchNonce:      '...',
  loadMoreNonce:    '...',
  filtersNonce:     '...',
  googleMapsApiKey: '...',
  themePath:        '/wp-content/themes/custom-theme',
  homeUrl:          'https://example.com/',
  isDebug:          false,
}
```

---

## PHP-Entwicklung

### Plugin-Struktur

Das `media-lab-agency-core` Plugin wird **nie direkt modifiziert**. Erweiterungen via WordPress-Hooks:

```php
// Im Theme oder separatem Plugin
add_filter('medialab_shortcode_output', function($output, $tag) {
    // Output anpassen
    return $output;
}, 10, 2);
```

### Responsive Thumbnail

Statt `get_the_post_thumbnail_url()` immer `medialab_get_thumbnail()` verwenden:

```php
// ❌ Alt – nur URL, kein srcset, kein lazy
$url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
echo '<img src="' . esc_url($url) . '" alt="">';

// ✅ Neu – srcset, sizes, loading=lazy, decoding=async
echo medialab_get_thumbnail(get_the_ID(), 'medium', [
    'class' => 'mein-bild',
    'alt'   => 'Beschreibung',  // optional, sonst aus Attachment-Meta
]);
```

### Output-Escaping (Pflicht)

```php
echo esc_html($text);           // Allgemeiner Text
echo esc_attr($attribute);      // HTML-Attribute
echo esc_url($url);             // URLs
echo wp_kses_post($html);       // HTML mit erlaubten Tags
echo esc_js($js_string);        // Inline-JS-Strings
```

### Rate-Limiting für AJAX

```php
function mein_ajax_handler() {
    // Rate-Limiting: max. 20 Anfragen / 60 Sekunden pro IP
    if (!medialab_check_rate_limit('meine_action', 20, 60)) {
        wp_send_json_error(['message' => 'Too many requests.'], 429);
    }

    check_ajax_referer('mein_nonce', 'nonce');
    // ...
}
add_action('wp_ajax_nopriv_meine_action', 'mein_ajax_handler');
```

### Bild-Größen (Theme)

Registriert in `functions.php`:

| Slug | Breite | Höhe | Crop |
|---|---|---|---|
| `custom-thumbnail` | 400px | 300px | ✓ |
| `custom-medium` | 800px | 600px | ✓ |
| `custom-large` | 1200px | 900px | ✓ |

---

## Git-Workflow

### Branches

```
main          →  Produktionsstand
feature/*     →  Neue Features
fix/*         →  Bugfixes
hotfix/*      →  Kritische Fixes auf main
```

### Commit-Konvention

```
release: v1.4.0          →  Neues Release
feat: kurze Beschreibung →  Neues Feature
fix: kurze Beschreibung  →  Bugfix
refactor: ...            →  Refactoring ohne Feature-Änderung
security: ...            →  Security-Fix
chore: ...               →  Kein Code-Change (Deps, Cleanup)
docs: ...                →  Nur Dokumentation
```

### Release-Prozess

```bash
# 1. Versionen bumpen
#    - package.json
#    - cms/wp-content/themes/custom-theme/style.css
#    - cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php

# 2. CHANGELOG.md aktualisieren

# 3. Build testen
npm run build

# 4. Commit + Tag
git add -A
git commit -m "release: vX.Y.Z"
git tag -a vX.Y.Z -m "Version X.Y.Z"
git push origin main --follow-tags
```

---

## Best Practices

### SCSS

```scss
// ✅ BEM-Naming
.card {
  &__header { ... }
  &__body   { ... }
  &--featured { border: 2px solid $color-gold; }
}

// ✅ Tokens statt Hardcoding
color: $color-primary;        // nie: color: #e00000;
padding: $spacing-md;         // nie: padding: 16px;

// ✅ Mixins für Breakpoints
@include respond-to('md') { ... }   // nie: @media (min-width: 768px)

// ❌ Keine darken()/lighten() – stattdessen vorkompilierte Tokens
color: $color-primary-dark;   // statt: darken($color-primary, 10%)
```

### JavaScript

```javascript
// ✅ DOM-Check vor Initialisierung
if (!document.querySelector('.mein-element')) return;

// ✅ customTheme für AJAX-URLs/Nonces
fetch(window.customTheme.ajaxUrl, { ... })

// ✅ console.log nur mit Debug-Flag (wird in Production von Terser entfernt)
if (window.customTheme?.isDebug) console.log('Debug:', data);
```

### PHP Security

```php
// ✅ Input immer sanitieren
$value = sanitize_text_field($_POST['field'] ?? '');

// ✅ Output immer escapen
echo esc_html($value);

// ✅ Nonce bei jedem AJAX-Handler prüfen
check_ajax_referer('nonce_action', 'nonce');

// ✅ Capabilities prüfen
if (!current_user_can('manage_options')) wp_die();

// ✅ ABSPATH-Guard in jedem PHP-File
if (!defined('ABSPATH')) exit;
```

---

## Debugging

### PHP

```bash
# Debug-Log verfolgen
tail -f cms/wp-content/debug.log

# Valet-Log
tail -f ~/.valet/Log/nginx-error.log
```

### JavaScript

```javascript
// In Development (wird in Production entfernt)
console.log('State:', someValue);
console.table(arrayData);
```

### SCSS Build-Fehler

```bash
# Typische Fehler:
# "Undefined variable" → @use '../abstracts' as * fehlt im Partial
# "Can't find stylesheet" → File existiert nicht oder Pfad falsch
# "darken() deprecated" → $color-primary-dark Token verwenden

# Build mit Details
npm run build 2>&1 | head -50
```

---

**Weiter:** [docs/07_TROUBLESHOOTING.md](07_TROUBLESHOOTING.md)
