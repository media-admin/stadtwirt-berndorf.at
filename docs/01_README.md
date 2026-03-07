# Media Lab Starter Kit - Internal Documentation

**Version:** 1.7.0  
**Last Updated:** March 6, 2026  
**Architecture:** Modular Plugin System

---

## 📚 Documentation Index

| # | Document | Description | Status |
|---|----------|-------------|--------|
| 1 | [README](01_README.md) | Documentation overview | ✅ Current |
| 2 | [Installation](02_INSTALLATION.md) | Setup guide | ✅ Updated |
| 3 | [Plugins](03_PLUGINS.md) | Plugin architecture | ✅ New |
| 4 | [Shortcodes](04_SHORTCODES.md) | 44 shortcodes reference | ✅ Updated |
| 5 | [AJAX Features](05_AJAX-FEATURES.md) | AJAX system guide | ✅ Updated |
| 6 | [Development](06_DEVELOPMENT.md) | Development workflow | ✅ Updated |
| 7 | [Troubleshooting](07_TROUBLESHOOTING.md) | Common issues | ✅ Updated |
| 8 | [Custom Post Types](08_CUSTOM-POST-TYPES.md) | 9 CPTs reference | ✅ Updated |
| 9 | [ACF Fields](09_ACF-FIELDS.md) | 12 field groups | ✅ Updated |
| 10 | [Deployment](10_DEPLOYMENT.md) | Production deploy | ✅ Updated |
| 11 | [Testing](11_TESTING.md) | Test suite (23 tests) | ✅ New |
| 12 | [Analytics](12_ANALYTICS.md) | Analytics plugin | ✅ New |
| 13 | [SEO](13_SEO.md) | SEO plugin | ✅ New |

---

## 🏗️ Architecture v1.5.0

### Plugin System Overview
```
WordPress Installation
│
├── 📦 Regular Plugins
│   ├── media-lab-agency-core/      v1.5.4 (Framework)
│   ├── media-lab-project-starter/  v1.0.0 (Content Structure)
│   ├── media-lab-analytics/        v1.0.0 (Tracking)
│   └── media-lab-seo/              v1.1.1 (SEO)
│
├── 🎨 Theme
│   └── custom-theme/               (Presentation Layer)
│
└── 🔌 MU-Plugins
    └── System-level plugins
```

### What Changed in v1.5.0

**From:** Monolithic MU-plugin structure  
**To:** Modular plugin architecture

**Benefits:**
- ✅ Separation of concerns
- ✅ Reusable core framework
- ✅ Client-specific customization
- ✅ Independent updates
- ✅ Better version control

---

## 🎯 Quick Start Paths

### For Developers
1. [Installation](02_INSTALLATION.md) - Set up local environment
2. [Plugins](03_PLUGINS.md) - Understand plugin system
3. [Development](06_DEVELOPMENT.md) - Build workflow
4. [Testing](11_TESTING.md) - Run test suite

### For Content Managers
1. [Shortcodes](04_SHORTCODES.md) - Content building blocks
2. [Custom Post Types](08_CUSTOM-POST-TYPES.md) - Content types
3. [ACF Fields](09_ACF-FIELDS.md) - Custom fields

### For New Client Setup
1. [Deployment](10_DEPLOYMENT.md) - Production setup
2. [Analytics](12_ANALYTICS.md) - Configure tracking
3. [SEO](13_SEO.md) - SEO configuration

---

## 📊 System Status

**Current Version:** v1.5.0  
**Test Coverage:** 23/23 tests passing (100%)  
**PHP Version:** 8.0+  
**WordPress Version:** 6.0+  

**Active Plugins:**
- ✅ Media Lab Agency Core v1.5.4
- ✅ Media Lab Project Starter v1.0.0
- ✅ Media Lab Analytics v1.0.0
- ✅ Media Lab SEO v1.1.1

---

## 🔄 Migration from v1.1.0

### Key Changes
1. **MU-Plugin Migration:**
   - `mu-plugins/agency-core/` → `plugins/media-lab-agency-core/`
   - All features now in regular plugins

2. **Theme Cleanup:**
   - 159 lines → 118 lines (-26%)
   - Presentation layer only
   - Plugin dependency checks

3. **New Plugins:**
   - Analytics: GA4, GTM, Facebook Pixel
   - SEO: Schema.org, Open Graph, Twitter Cards

4. **Testing:**
   - 16 tests → 23 tests
   - Automated test suite

### Migration Guide
See [Deployment Guide](10_DEPLOYMENT.md) for migration steps.

---

## 🛠️ Essential Commands
```bash
# Plugin Management
wp plugin activate media-lab-agency-core
wp plugin activate media-lab-project-starter
wp plugin activate media-lab-analytics
wp plugin activate media-lab-seo

# Testing
./tests/run-tests.sh

# Development
npm run dev       # Development with HMR
npm run build     # Production build

# Cache
wp cache flush
wp transient delete --all
```

---

## 📖 Documentation Standards

### File Naming
- `01_` prefix for ordering
- UPPERCASE for main words
- `.md` extension

### Content Structure
1. Title with version/date
2. Table of contents
3. Main content with examples
4. Troubleshooting
5. Related documents

### Code Examples
- Always include working examples
- Show both PHP and shortcode usage
- Include expected output

---

## 🔍 Finding Information

### By Topic
- **Setup:** Installation, Deployment
- **Content:** Shortcodes, CPTs, ACF
- **Development:** Development, Testing
- **Features:** AJAX, Analytics, SEO
- **Issues:** Troubleshooting

### By Role
- **Developer:** 02, 03, 06, 11
- **Content Manager:** 04, 05, 08, 09
- **Project Manager:** 10, 12, 13
- **Admin:** 02, 07, 10

---

## 🆘 Support Resources

**Internal:**
- GitHub Issues: Track bugs and features
- Documentation: This docs folder
- Test Suite: `./tests/run-tests.sh`

**External:**
- WordPress Codex: https://codex.wordpress.org/
- ACF Documentation: https://www.advancedcustomfields.com/resources/
- Vite Documentation: https://vitejs.dev/

**Contact:**
- Email: markus.tritremmel@media-lab.at
- Emergency: Check Troubleshooting first

---

## 📈 Changelog

### v1.7.0 (2026-03-06)
- ✅ Fullwidth-Helper (Klasse + Mixin, 3 Varianten)
- ✅ Navigation auf 4 Ebenen erweitert (Header + Mobile + Footer)
- ✅ Viewport-Kollisionserkennung für Desktop-Flyouts
- ✅ Footer-Nav: Flyout nach oben (Desktop), Accordion (Mobile)
- ✅ Docs aktualisiert

### v1.6.0 (2026-03-06)
- ✅ Cookie Consent Manager (Banner, Modal, Floating Button 🍪)
- ✅ Code-Snippet-Verwaltung im Backend (Head + Body pro Kategorie)
- ✅ Button-System zentralisiert (Mixins + globale .btn-Klassen)
- ✅ 7 SCSS-Komponenten auf @include btn-* umgestellt
- ✅ Alle Docs aktualisiert

### v1.5.0 (2026-03-04)
- ✅ 404-Seite mit Suchformular + Navigationslinks
- ✅ Maintenance Mode (503, ACF-konfigurierbar, Admin-Bypass)
- ✅ Footer Navigation
- ✅ Media Replace (Attachment-ID bleibt erhalten)
- ✅ Security F-04 – redirects.php Open Redirect + SQL abgesichert
- ✅ AJAX Bugfixes (Swiper lokal, wp_head Nonce, Selektoren)
- ✅ Alle Docs aktualisiert

### v1.4.0 (2026-02-26)
- ✅ Dynamic Imports / Code-Splitting
- ✅ Rate-Limiting AJAX
- ✅ SVG-Upload Sanitizer
- ✅ SMTP via wp-config.php
- ✅ HTTP-Security-Headers

### v1.2.0 (2026-02-16)
- ✅ Analytics Plugin (GA4, GTM, FB Pixel)
- ✅ SEO Plugin (Schema, OG, Twitter)
- ✅ Modular Plugin-Architektur

### v1.1.0 (2026-02-16)
- ✅ Plugin architecture migration
- ✅ Core Plugin (44 shortcodes)
- ✅ Project Plugin (CPTs + ACF)
- ✅ Theme cleanup (118 lines)
- ✅ Initial test suite (16 tests)

### v1.0.0
- Initial release
- Monolithic MU-plugin structure
- Theme with Vite build
- 24 JavaScript components

---

## 🎓 Learning Path

**Week 1: Fundamentals**
- Day 1-2: Installation & Setup
- Day 3-4: Plugin Architecture
- Day 5: Shortcodes & Content

**Week 2: Advanced**
- Day 1-2: Development Workflow
- Day 3: AJAX Features
- Day 4-5: CPTs & ACF

**Week 3: Production**
- Day 1-2: Testing & QA
- Day 3-4: Deployment
- Day 5: Analytics & SEO

---

**Ready to start?** → [Installation Guide](02_INSTALLATION.md)
