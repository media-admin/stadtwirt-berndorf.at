# Changelog

Alle wesentlichen Änderungen am Media Lab Starter Kit werden hier dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---

## [1.8.5] - 2026-04-23

### media-lab-agency-core 1.8.5

#### Fixed
- **E-Mail Obfuskierung – Gutenberg Buttons** – `protect_content_emails()` in
  `email-obfuscation.php` baute das `<a>`-Tag bisher komplett neu auf, wodurch
  alle Original-Attribute (insb. `class="wp-block-button__link wp-element-button"`)
  verloren gingen und Buttons nicht mehr korrekt dargestellt wurden. Die Funktion
  modifiziert nun das bestehende Tag chirurgisch: nur `href` wird ersetzt und
  `data-obf-email`/`data-obf-label` werden ergänzt – alle anderen Attribute
  (`class`, `id`, `target`, `rel`, …) bleiben erhalten.

---

## [1.18.0] - 2026-03-26

### custom-theme 1.14.0

#### Added
- **Footer Legal Navigation** – neue Menu-Location `footer-legal` registriert
  - Ausgabe via `wp_nav_menu()` in `footer.php` (Tiefe 1, keine Submenüs)
  - Geeignet für Impressum, Datenschutz, AGB, Cookie-Richtlinie
  - Zuweisung im WP-Admin unter Design → Menüs
- **Footer Legal Styling** – `_footer.scss`
  - `.footer-legal` – dezente horizontale Link-Leiste mit Trennpunkten (`·`)
  - `.footer-legal a` – `font-size-xs`, `color-text-muted`, Hover: `color-primary`
  - `.site-footer__bottom` – Flex-Layout: Copyright links, Legal-Links rechts
  - Responsive: unterhalb 768px gestapelt, linksbündig
- **Credit-Line** – dezenter Agentur-Hinweis ganz unten im Footer
  - Text: „Konzept und Programmierung: Media Lab Tritremmel GmbH"
  - Link auf `https://www.media-lab.at` (öffnet in neuem Tab)
  - Styling: `opacity: 0.6` im Ruhezustand, `opacity: 1` bei Hover
  - Trennlinie (`border-top`) zwischen Legal-Bereich und Credit-Line

---

## [1.17.0] - 2026-03-10

### custom-theme 1.13.0

#### Added
- **WCAG 2.1 AA Audit** – 11 Fixes implementiert
  - Skip-Link für Tastaturnavigation
  - Keyboard-Pause für animierte Elemente
  - Primärfarbe `#ff0000` → `#d40000` (WCAG Kontrastanforderung)
  - Focus-Styles für alle interaktiven Elemente
  - `aria-hidden` auf dekorativen Elementen
  - Alt-Text-Fallback für Bilder ohne Alt-Attribut
  - Heading-Level-Hierarchie korrigiert
  - Touch-Targets auf min. 44×44px vergrößert
  - `prefers-reduced-motion` Media Query eingebaut
  - Kontrast-Fixes für Text auf farbigen Hintergründen
  - Semantische Struktur (`main`, `nav`, `footer` Landmarks)

---

## [1.16.0] - 2026-02-20

### custom-theme 1.12.0 / media-lab-agency-core 1.6.0

#### Added
- **8 Custom Gutenberg Blocks** abgeschlossen (Kategorie „Design")
  - Hero, Testimonial, Team-Mitglied, Logo-Leiste, Logo-Slider (ACF-Blöcke)
  - CTA-Banner, Accordion/FAQ, Icon+Text (Native Blöcke)
- **ACF-Felder** via PHP registriert (`inc/acf-blocks.php`)

---

## [1.15.0] - 2026-02-10

### media-lab-agency-core 1.5.0 (→ 1.8.4)

#### Added
- ACF Field Groups programmatisch via PHP registriert (Version Control-fähig)
- `inc/acf-blocks.php` als zentrale Registrierungsdatei

---

## [1.14.0] - 2026-01-28

#### Changed
- **Vite Multi-Config Build** – zwei separate Config-Dateien
  - `vite.config.js` → Theme-Assets (SCSS, JS)
  - `vite.config.blocks.js` → Gutenberg-Block-Assets
- **Stale Hot-File Fix** – `npm run dev:stop &&` vor Build-Scripts
- `cssCodeSplit: false` – CSS direkt via `filemtime()` eingebunden

---

## [1.13.0] - 2026-01-15

### custom-theme 1.10.0

#### Added
- **4-Ebenen Navigation** – Desktop (Flyout) + Mobile (Accordion)
- **Footer Navigation** – `footer` Menu-Location, Flyout nach oben (Desktop)
- Viewport-Kollisionserkennung: `.opens-left` bei Überlauf

---

## [1.12.0] - 2025-12-10

### media-lab-seo 1.3.0

#### Added
- SEO-Toolkit: Meta-Tags, Open Graph, Sitemap-Integration
- Schema.org Structured Data

---

## [1.11.0] - 2025-11-20

#### Added
- Dark Mode (CSS Custom Properties, `data-theme`-Attribut)
- Maintenance Mode mit ACF-Konfiguration, 503-Header, Admin-Bypass

---

## [1.10.0] - 2025-11-05

#### Added
- 404-Seite mit Suchformular und Quick-Links aus Hauptmenü

#### Fixed
- Security: Open Redirect Fix, SQL-Wildcard, DB-Flooding Prevention

---

## [1.9.0] - 2025-10-15

#### Added
- Performance: Code-Splitting, Lazy Loading, Responsive Images
- WP-Cleanup: Entfernung nicht benötigter Core-Skripte

---

## [1.8.0] - 2025-10-01

### media-lab-agency-core

#### Added
- **SMTP Mailer** – PHPMailer-Integration, konfigurierbar über ACF Options Page;
  Fallback auf `wp-config.php`-Konstanten (`MEDIALAB_SMTP_HOST`, `_PORT`, etc.)
  damit Passwörter nie in der DB landen; Test-Mail-Funktion im Backend
- **E-Mail Obfuskierung** – ROT13-Kodierung + JS-Decoder; Shortcode
  `[obfuscate_email email="..." label="..."]`; Auto-Protect-Modus für alle
  `mailto:`-Links und nackte E-Mail-Adressen im Content

#### Security
- Rate-Limiting für alle AJAX-Endpunkte (30 Req/60s Filter/Load-More, 20 Req/60s Search)
- SMTP Credentials via `wp-config.php`-Konstanten (kein DB-Passwort)
- Inline-Nonce via `wp_localize_script()` – kein `<script>`-Tag in ACF Message Fields
- Output-Escaping: `esc_attr()` für `data-post-id` und Search-Input-Ausgaben
- HTTP Security Header in `.htaccess`: `X-Frame-Options`, `X-Content-Type-Options`,
  `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`

---

## [1.7.0] - 2025-09-15

#### Added
- Fullwidth-Helper: Klassen + Mixins (`.fullwidth`, `.fullwidth--bg`, `.fullwidth--media`)
- Design Tokens vollständig als CSS Custom Properties

---

## [1.6.0] - 2025-09-01

#### Added
- Sass-Migration: 7-1-Architektur, Abstracts, Mixins, Breakpoints (`@use`/`@forward`)

---

## [1.4.0] - 2026-03-04

### custom-theme 1.4.0

#### Performance
- **Code-Splitting** – 27 Komponenten als Dynamic Imports
- **console.log entfernt** – Terser entfernt alle `console.log/info/debug` in Production
- **type="module"** – `<script type="module">` statt `defer`
- **Preconnect** – Google Fonts + Maps DNS-Prefetch im `<head>`
- **Emoji-Scripts deaktiviert** – WordPress Emoji-JS/CSS komplett entfernt
- **oEmbed deaktiviert** – REST-Route und Discovery-Links entfernt
- **WP Head bereinigt** – RSD, WLW Manifest, WP Generator, Shortlink entfernt
- **Responsive Images** – `medialab_get_thumbnail()` mit `srcset`, `sizes`,
  `loading=lazy`, `decoding=async`

### media-lab-agency-core 1.5.1

#### Added
- `medialab_get_thumbnail()` – responsive Thumbnail-Hilfsfunktion
- `medialab_the_thumbnail()` – Echo-Wrapper

---

## [1.3.0] - 2026-03-04

### media-lab-agency-core 1.5.0

#### Security
- SVG Sanitizer – vollständige Allowlist; entfernt `<script>`, `on*`-Handler,
  `javascript:`- und `data:`-URLs; SVG-Upload auf Administratoren beschränkt
- IP-Adressen (DSGVO) – WP-Cron anonymisiert IPs nach 90 Tagen automatisch

### media-lab-seo 1.1.0

#### Added
- **Redirections** – 301/302/307 mit Wildcard-Support, Hit-Counter, Modal
- **404-Log** – Tracking aller 404-Aufrufe mit URL, Referrer, Zeitstempel
- **SEO Meta Box** – Meta Title/Description mit Live-Zeichenzähler,
  Google Snippet Vorschau, Fokus-Keyword, Canonical URL, OG Image, Robots

### custom-theme 1.1.0

#### Added
- Logo-Ausgabe in `header.php` mit Desktop/Mobile-Variante via ACF Options
- Top Header Rendering (Adresse, Öffnungszeiten, Telefon, E-Mail, Social Media)
- Inline Theme-Detection Script im `<head>` verhindert FOUC beim Dark/Light Mode

#### Fixed
- Theme Switcher: System-Präferenz wird korrekt respektiert

---

## [1.2.0] - 2026-03-04

### media-lab-agency-core 1.4.0

#### Security
- SVG Sanitizer – `MediaLab_SVG_Sanitizer` mit vollständiger Tag/Attribut-Allowlist
- IP-Adressen (DSGVO) – `get_client_ip()` prüft Cloudflare/nginx/X-Forwarded-For
  korrekt; akzeptiert nur öffentliche IPs; WP-Cron anonymisiert nach 90 Tagen

#### Fixed
- Deaktivierungs-Hook bereinigt IP-Anonymisierungs-Cron-Job

### custom-theme 1.2.0

#### Changed
- **Design Tokens** – `$color-gray-100`, `$container-max-width`, `$color-woo-danger`
  ergänzt; 12 vorkompilierte Farbvarianten als Ersatz für `darken()`/`lighten()`
- **Token-Bereinigung** – alle hardcodierten Hex-Werte in 10 SCSS-Files durch Tokens ersetzt
- **Sass Module System** – Migration auf `@use`/`@forward`; `abstracts/_index.scss`
  als zentraler Einstiegspunkt; keine Deprecation Warnings mehr

### Projekt

#### Removed
- Backup-Files entfernt: `shortcodes.php.bak*`, `multi-language.php.backup4` u.a.
- `lighthouse-report.html` aus Repository entfernt
- Inaktives Plugin `media-lab-core` (Vorgänger) gelöscht

---

## [1.0.0] - 2026-01-27

### Added
- Initiales Starter Kit Setup
- `media-lab-agency-core` Plugin-Grundstruktur
- `media-lab-seo` SEO-Toolkit
- `custom-theme` Theme-Grundstruktur
- Vite Build-System
- Figma Design Tokens (Color System, Typography Scale, Spacing System)
- Custom Post Types
- ACF PRO Integration

## [0.1.0] - 2026-01-20

### Added
- Projektinitialisierung
- Git Repository Setup
