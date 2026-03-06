# Plugin-Dokumentation

**Version:** 1.6.0 | **Letzte Aktualisierung:** 2026-03-06

---

## Übersicht

| Plugin | Version | Zweck | Modifizierbar? |
|---|---|---|---|
| media-lab-agency-core | 1.5.4 | Framework + Features | ❌ Nie |
| media-lab-seo | 1.1.1 | SEO-Toolkit | ✅ Konfigurierbar |
| advanced-custom-fields-pro | aktuell | Custom Fields | ✅ Konfigurierbar |

---

## media-lab-agency-core `v1.5.4`

**Datei:** `cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php`

Dieses Plugin wird **unverändert auf allen Projekten eingesetzt**. Nie direkt modifizieren – stattdessen WordPress-Hooks verwenden.

### Enthaltene Module

| Datei | Inhalt |
|---|---|
| `inc/shortcodes.php` | 44 Shortcodes |
| `inc/ajax-search.php` | AJAX-Suche (Rate-Limit: 20/min) |
| `inc/ajax-filters.php` | Post-Filter (Rate-Limit: 30/min) |
| `inc/ajax-load-more.php` | Load More (Rate-Limit: 30/min) |
| `inc/helpers.php` | `medialab_get_thumbnail()`, `medialab_check_rate_limit()` |
| `inc/smtp.php` | PHPMailer-Konfiguration via wp-config.php Konstanten |
| `inc/svg-support.php` | SVG-Upload mit Allowlist-Sanitizer |
| `inc/activity-log.php` | Activity Log mit DSGVO-IP-Anonymisierung |
| `inc/acf-settings.php` | ACF Options Page für Plugin-Einstellungen |
| `inc/post-order.php` | Drag & Drop Post/Term-Order |
| `inc/white-label.php` | Admin White-Labeling |
| `assets/js/smtp-test.js` | SMTP Test-Mail Admin-Script |
| `inc/maintenance.php` | Maintenance Mode (503, Admin-Bypass, ACF-konfigurierbar) |
| `inc/media-replace.php` | Medien ersetzen ohne Plugin-Verlust der Attachment-ID |
| `inc/cookie-consent.php` | Cookie Consent Manager (Banner, Modal, Snippets, ACF-konfigurierbar) |

### SMTP-Konfiguration

Credentials via `wp-config.php` Konstanten (Passwort landet nie in der DB):

```php
define('MEDIALAB_SMTP_ENABLED',   true);
define('MEDIALAB_SMTP_HOST',      'smtp.example.com');
define('MEDIALAB_SMTP_PORT',      587);
define('MEDIALAB_SMTP_USER',      'user@example.com');
define('MEDIALAB_SMTP_PASS',      'geheimes-passwort');
define('MEDIALAB_SMTP_ENC',       'tls');   // tls | ssl | ''
define('MEDIALAB_SMTP_FROM',      'noreply@example.com');
define('MEDIALAB_SMTP_FROM_NAME', 'Meine Website');
```

Alternativ (weniger sicher): Konfiguration via **Einstellungen → Agency Core → SMTP**.

### SVG-Uploads

SVG-Upload ist auf **Administratoren beschränkt**. Uploads werden automatisch sanitiert:
- Entfernt: `<script>`, `<foreignObject>`, `<animate>`, externe `<use href>`, alle `on*`-Handler
- Erlaubt: Definierte Allowlist für sichere SVG-Tags und -Attribute


### Maintenance Mode

Aktivierung unter **Agency Core → Einstellungen → Maintenance Mode**.

- HTTP 503 + `Retry-After: 3600` (SEO-konform)
- Eingeloggte Admins sehen die normale Website + orangen Admin-Bar-Hinweis
- Konfigurierbar: Überschrift, Nachricht, Datum, Logo, Browser-Titel
- Notfall-Fallback ohne Backend:

```php
// wp-config.php
define('MEDIALAB_MAINTENANCE_MODE', true);
```

### Media Replace

Ermöglicht das Ersetzen von Mediendateien ohne Verlust der Attachment-ID oder Verwendungen im Content. Kein Drittanbieter-Plugin nötig.

**Zugang:**
- Medien → Attachment bearbeiten → **„Neue Datei hochladen"**
- Medien-Bibliothek (Listenansicht) → **„Datei ersetzen"**

**Was passiert beim Ersetzen:**
- Alte Datei wird überschrieben (optional: Dateiname beibehalten)
- Alle Thumbnails/Bildgrößen werden neu generiert
- Attachment-ID, URL und alle Verwendungen im Content bleiben unverändert
- MIME-Typ wird aktualisiert wenn sich der Dateityp ändert
- Eintrag im Activity Log

### Cookie Consent Manager

Aktivierung: automatisch aktiv. Konfiguration unter **Agency Core → Einstellungen → Cookie Consent**.

**Features:**
- Banner mit „Alle akzeptieren" / „Einstellungen" / „Ablehnen"
- Settings Modal mit Toggle pro Kategorie
- Floating Button 🍪 (immer sichtbar, unten links) öffnet Modal jederzeit
- 4 Kategorien: Notwendig (immer aktiv), Statistik, Marketing, Komfort
- Consent gespeichert als JSON in `localStorage` inkl. Version + Timestamp

**Code-Snippets im Backend verwalten:**

Unter **Cookie Consent → Code-Snippets** können pro Kategorie Head- und Body-Code eingetragen werden:

| Kategorie | Wann geladen | Typische Dienste |
|---|---|---|
| Notwendig | Immer (kein Consent nötig) | Eigene Consent-APIs, DSGVO-Chat |
| Statistik | Nach Zustimmung | GA4, Matomo, Hotjar |
| Marketing | Nach Zustimmung | Meta Pixel, Google Ads, LinkedIn Insight |
| Komfort | Nach Zustimmung | YouTube API, Google Maps JS |

**Public JS-API:**
```javascript
// Consent prüfen
window.CookieConsent.hasConsent('statistics'); // → true/false

// Modal programmatisch öffnen
window.CookieConsent.openSettings();

// Auf Consent-Änderungen reagieren
document.addEventListener('cookies:changed', (e) => {
    if (e.detail.statistics) { /* GA4 aktivieren */ }
    if (e.detail.marketing)  { /* Pixel aktivieren */ }
});
```

**Consent-Version erhöhen** (erzwingt erneute Zustimmung bei allen Besuchern):
Unter *Cookie Consent → Consent-Version* die Zahl erhöhen.


### Security-Features

- **Rate-Limiting:** Alle öffentlichen AJAX-Endpunkte sind per Transient begrenzt
- **IP-Anonymisierung:** Activity Log anonymisiert IPs nach 90 Tagen via WP-Cron
- **Output-Escaping:** Alle Shortcode-Ausgaben mit `esc_html()`, `esc_attr()`, `esc_url()`

### Helper-Funktionen

```php
// Responsives Thumbnail-Image (srcset + lazy loading)
echo medialab_get_thumbnail($post_id, 'medium', ['class' => 'mein-bild']);
medialab_the_thumbnail($post_id, 'large'); // direkte Ausgabe

// Rate-Limiting in eigenen AJAX-Handlern
if (!medialab_check_rate_limit('meine_action', 20, 60)) {
    wp_send_json_error(['message' => 'Too many requests.'], 429);
}
```

---

## media-lab-seo `v1.1.1`

**Datei:** `cms/wp-content/plugins/media-lab-seo/media-lab-seo.php`

Pro Projekt aktivieren und konfigurieren unter **Einstellungen → SEO Toolkit**.

### Features

| Feature | Beschreibung |
|---|---|
| Schema.org JSON-LD | Organization, WebSite, Article, Product, BreadcrumbList |
| Open Graph | Facebook und LinkedIn sharing |
| Twitter Cards | Erweiterte Twitter-Vorschauen |
| Canonical URLs | Duplicate Content verhindern |
| Breadcrumbs | Automatische Brotkrummen-Navigation |

### Schema-Typen

- **Organization** (Homepage): Firmeninfos
- **WebSite** (Global): Site-weite Daten inkl. SearchAction
- **Article** (Blogposts): Autor, Datum, Bild
- **Product** (WooCommerce): Preis, Verfügbarkeit
- **BreadcrumbList** (alle Seiten): Navigation

### Breadcrumbs im Template

```php
if (function_exists('medialab_seo_breadcrumbs')) {
    medialab_seo_breadcrumbs([
        'separator'   => ' › ',
        'home_title'  => 'Home',
        'wrapper_class' => 'breadcrumbs',
    ]);
}
```

### Konfiguration

1. **Einstellungen → SEO Toolkit**
2. Site Name eintragen
3. Twitter-Username (ohne @) eintragen
4. Standard-Social-Image hochladen (1200×630px)
5. Einzelne Features aktivieren/deaktivieren

---

## Advanced Custom Fields Pro

Wird für ACF Options Pages und alle Custom Fields benötigt. Lizenz unter [advancedcustomfields.com](https://www.advancedcustomfields.com/).

### ACF JSON-Sync

Feldgruppen werden als JSON in `acf-json/` versioniert. Automatisch aktiv nach Plugin-Aktivierung.

```bash
# Nach Git-Pull: ACF Felder synchronisieren
# WordPress-Admin → Eigene Felder → Synchronisieren verfügbar
```

---

## Plugins die NICHT enthalten sind

Diese Plugins können bei Bedarf pro Projekt ergänzt werden:

| Plugin | Zweck | Hinweis |
|---|---|---|
| WooCommerce | E-Commerce | SCSS-Partial `_woocommerce.scss` bereits vorhanden |
| media-lab-analytics | GA4, GTM, Facebook Pixel | Optional, liegt im Repo |
| media-lab-events | Event-Management | Optional, liegt im Repo |

---

**Weiter:** [docs/04_SHORTCODES.md](04_SHORTCODES.md) – Shortcode-Referenz
