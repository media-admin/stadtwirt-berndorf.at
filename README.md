# Media Lab Starter Kit

**Professional WordPress Agency Framework** – Modulares Plugin-System für skalierbare Kundenprojekte.

[![Version](https://img.shields.io/badge/version-1.4.0-blue.svg)](CHANGELOG.md)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-proprietary-red.svg)](#lizenz)

---

## Übersicht

Vollständiges WordPress-Starter-Kit mit modularer Plugin-Architektur für Agentur-Workflows. Entwickelt für Wartbarkeit, Sicherheit und schnelles Client-Deployment.

### Architektur-Prinzip

```
media-lab-agency-core   →  Wiederverwendbares Framework (nie modifizieren)
media-lab-seo           →  SEO-Toolkit (pro Projekt aktivieren + konfigurieren)
custom-theme            →  Präsentationsebene (pro Projekt anpassen)
```

---

## Plugins & Versionen

### media-lab-agency-core `v1.5.1`

Framework-Plugin – wird **unverändert** auf allen Projekten eingesetzt.

**Features:**
- 44 Shortcodes: Hero Slider, Accordion, Stats, Testimonials, Modal, Tabs, Carousel, FAQ, Timeline, Video Player, Team, Projects, Services u.v.m.
- AJAX-Features: Search, Load More, Post-Filter mit Rate-Limiting (30 Req/min)
- Security: SVG-Sanitizer (Allowlist, DOMDocument), IP-Anonymisierung (DSGVO, 90 Tage)
- Admin: Drag & Drop Post/Term-Order, Duplicate Post/Term, SMTP-Mailer, White-Label
- Helper: `medialab_get_thumbnail()` für responsive Bilder mit srcset + lazy loading

### media-lab-seo `v1.1.0`

SEO-Toolkit – pro Projekt aktivieren und konfigurieren.

**Features:** Schema.org JSON-LD, Open Graph Tags, Twitter Cards, Canonical URLs, Breadcrumbs

### custom-theme `v1.4.0`

Präsentationsebene – enthält keine Business-Logik.

**Features:**
- Vite Build-System mit Code-Splitting (27 Dynamic-Import-Komponenten)
- SCSS mit Design-Tokens, `@use`/`@forward` (Dart Sass 2.0+ kompatibel)
- JS: nur geladen wenn DOM-Element vorhanden (Dynamic Imports)
- Performance: Emoji/oEmbed deaktiviert, WP Head bereinigt, Responsive Images
- Security: HTTP-Header in `.htaccess` (X-Frame-Options, X-Content-Type-Options, etc.)

---

## Schnellstart

### Voraussetzungen

| Software | Version |
|---|---|
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Node.js | 18+ |
| npm | 9+ |
| Composer | 2.0+ |
| WP-CLI | 2.8+ |

### Installation

```bash
# 1. Repository klonen
git clone https://github.com/media-admin/media-lab-starter-kit.git
cd media-lab-starter-kit

# 2. Dependencies
npm install
composer install

# 3. WordPress (Valet-Beispiel)
cd cms
wp core download --locale=de_DE
wp core config --dbname=media_lab --dbuser=root --dbpass=root
wp core install \
  --url=media-lab-starter-kit.test \
  --title="Media Lab" \
  --admin_user=admin \
  --admin_password=SICHERES_PASSWORT \
  --admin_email=admin@media-lab.at

# 4. Plugins & Theme aktivieren
wp plugin activate media-lab-agency-core media-lab-seo advanced-custom-fields-pro
wp theme activate custom-theme
cd ..

# 5. Assets bauen
npm run build
```

Vollständige Anleitung: [docs/02_INSTALLATION.md](docs/02_INSTALLATION.md)

---

## Build-System

```bash
npm run dev        # Development mit Hot Reload
npm run build      # Production Build
npm run watch      # Watch ohne Dev-Server
```

**Build-Output:**
```
assets/dist/
├── css/style.css          # Alle Styles (eine Datei)
├── js/main.js             # Kern-Bundle
├── js/ajax-filters.js     # Lazy Chunk
├── js/ajax-search.js      # Lazy Chunk
├── js/load-more.js        # Lazy Chunk
├── js/google-maps.js      # Lazy Chunk
├── js/notifications.js    # Lazy Chunk
└── js/chunks/             # Automatische Vite-Chunks
```

---

## SMTP-Konfiguration

SMTP-Credentials in `cms/wp-config.php` definieren – **nie** in der Datenbank speichern:

```php
define('MEDIALAB_SMTP_ENABLED',   true);
define('MEDIALAB_SMTP_HOST',      'smtp.example.com');
define('MEDIALAB_SMTP_PORT',      587);
define('MEDIALAB_SMTP_USER',      'user@example.com');
define('MEDIALAB_SMTP_PASS',      'geheimes-passwort');
define('MEDIALAB_SMTP_ENC',       'tls');
define('MEDIALAB_SMTP_FROM',      'noreply@example.com');
define('MEDIALAB_SMTP_FROM_NAME', 'Meine Website');
```

---

## Neues Kundenprojekt

```bash
./scripts/setup-project.sh
```

Fragt nach Projekt-Name, Theme-Slug, Plugin-Slug und Text-Domain und benennt automatisch um.

Vollständige Anleitung: [docs/10_DEPLOYMENT.md](docs/10_DEPLOYMENT.md)

---

## Projektstruktur

```
media-lab-starter-kit/
├── cms/
│   └── wp-content/
│       ├── plugins/
│       │   ├── media-lab-agency-core/     # Framework v1.5.1
│       │   └── media-lab-seo/             # SEO-Toolkit v1.1.0
│       └── themes/
│           └── custom-theme/              # Theme v1.4.0
│               ├── assets/src/scss/       # SCSS + Design-Tokens
│               ├── assets/src/js/         # 27 JS-Komponenten
│               ├── assets/dist/           # Build-Output (nicht committen)
│               └── inc/                   # PHP-Helpers
├── docs/                                  # Dokumentation
├── scripts/                               # Deploy-Scripts
├── tests/                                 # Playwright E2E
├── vite.config.js
├── package.json
└── CHANGELOG.md
```

---

## Dokumentation

| Dokument | Inhalt |
|---|---|
| [docs/02_INSTALLATION.md](docs/02_INSTALLATION.md) | Vollständige Installationsanleitung |
| [docs/04_SHORTCODES.md](docs/04_SHORTCODES.md) | Shortcode-Referenz |
| [docs/05_AJAX-FEATURES.md](docs/05_AJAX-FEATURES.md) | AJAX Search, Filter, Load More |
| [docs/06_DEVELOPMENT.md](docs/06_DEVELOPMENT.md) | Entwicklungs-Guide |
| [docs/07_TROUBLESHOOTING.md](docs/07_TROUBLESHOOTING.md) | Fehlerbehebung |
| [docs/10_DEPLOYMENT.md](docs/10_DEPLOYMENT.md) | Neues Kundenprojekt deployen |
| [CHANGELOG.md](CHANGELOG.md) | Versionshistorie |
| [WORKFLOW.md](WORKFLOW.md) | Git-Workflow & Branching |

---

## Versionshistorie

| Version | Schwerpunkt |
|---|---|
| v1.4.0 | Performance: Code-Splitting, Lazy Loading, WP-Cleanup, Responsive Images |
| v1.3.0 | Security: Rate-Limiting, SMTP Credentials, Inline-Nonce, HTTP-Header |
| v1.2.0 | Security Hoch: SVG-Sanitizer, DSGVO/IP + Design-Tokens + Sass-Migration |
| v1.1.0 | Features: Top Header, Dark Mode, SMTP, Drag & Drop, Duplicate Post |

Vollständiger Changelog: [CHANGELOG.md](CHANGELOG.md)

---

## Lizenz

Proprietär – Media Lab Tritremmel GmbH  
Kontakt: [markus.tritremmel@media-lab.at](mailto:markus.tritremmel@media-lab.at)  
Website: [www.media-lab.at](https://www.media-lab.at)
