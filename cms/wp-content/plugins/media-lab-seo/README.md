# Media Lab SEO Toolkit

**Version:** 1.0.0  
**Requires:** WordPress 6.0+, PHP 8.0+  
**Depends on:** [Media Lab Agency Core](https://github.com/media-admin/media-lab-starter-kit)

---

SEO- und Analytics-Plugin für Media Lab Kundenprojekte. Kombiniert Google Search Console Verifikation, Open Graph Tags, Consent-aware Analytics (GA4 / GTM) und einen wöchentlichen Report-Mailer in einem Plugin.

---

## Voraussetzungen

Das Plugin benötigt **Media Lab Agency Core** als aktives Plugin. Beim Aktivierungsversuch ohne Agency Core deaktiviert es sich automatisch und zeigt eine Admin-Notice.

SMTP-Versand (für den Report-Mailer) wird ausschließlich über Agency Core konfiguriert:  
**Agency Core → E-Mail / SMTP**

---

## Installation

1. Plugin-Ordner nach `wp-content/plugins/media-lab-toolkit/` kopieren
2. Sicherstellen dass **Media Lab Agency Core** aktiv ist
3. Plugin im WordPress-Backend aktivieren
4. Einstellungen unter **ML Toolkit** konfigurieren

---

## Einstellungen

### SEO

| Feld | Beschreibung |
|---|---|
| GSC Verification Code | Inhalt des `<meta name="google-site-verification">`-Tags. Kann als voller String (`google-site-verification=ABC...`) oder nur als Wert eingetragen werden. |
| OG Fallback-Bild | Wird als `og:image` verwendet wenn eine Seite kein eigenes Beitragsbild hat. |

Folgende Tags werden automatisch im `<head>` ausgegeben:
- `og:type`, `og:url`, `og:title`, `og:description`, `og:image` (+ width/height/alt)
- `og:locale`, `og:site_name`
- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`
- `rel="canonical"`

WordPress' eigene Canonical-Ausgabe wird deaktiviert um Dopplung zu vermeiden.

---

### Analytics

| Feld | Beschreibung |
|---|---|
| Analytics aktivieren | Toggle. Bei **deaktiviert** wird kein Script geladen – kein Code im Frontend, kein Datenschutz-Risiko. |
| Provider | GA4 (Google Analytics 4) oder GTM (Google Tag Manager) |
| Measurement ID / Container ID | GA4: `G-XXXXXXXXXX` — GTM: `GTM-XXXXXXX` |

**Consent-Anbindung:**  
Analytics läuft vollständig über **Google Consent Mode v2**. Tracking bleibt initial `denied` und wird erst aktiviert wenn Agency Core Cookie Consent das Event `cookies:changed` mit `statistics: true` feuert.

```
Besucher akzeptiert → cookies:changed { statistics: true }
  → Bridge setzt localStorage('mlt_consent_analytics', '1')
  → dispatcht mlt:consent:analytics
    → GA4/GTM aktualisiert Consent auf 'granted' und startet Tracking
```

Wiederkehrende Besucher: Consent wird über `window.CookieConsent.hasConsent('statistics')` (Agency Core API) oder `localStorage` geprüft – kein erneuter Banner.

---

### Wöchentlicher Report

| Feld | Beschreibung |
|---|---|
| Report aktivieren | Aktiviert den wöchentlichen HTML-Report per E-Mail (jeden Montag 08:00 Uhr). Nur aktivierbar wenn SMTP in Agency Core konfiguriert ist. |
| Empfänger-E-Mail | Ziel-Adresse für den Report. |
| Test-Mail senden | Sendet sofort eine Test-Mail an die eingetragene Adresse und zeigt direktes Feedback (Erfolg / Fehlermeldung). |

Der Report-Inhalt kann über den Filter `mlt_weekly_report_html` erweitert werden:

```php
add_filter( 'mlt_weekly_report_html', function( $html, $to ) {
    return $html . '<p>Zusätzliche Infos...</p>';
}, 10, 2 );
```

---

## Dateistruktur

```
media-lab-toolkit/
├── media-lab-toolkit.php     Haupt-Plugin-Datei, Loader, Dependency-Check
├── uninstall.php             Bereinigt Optionen + Cron bei Deinstallation
├── README.md
├── inc/
│   ├── class-settings.php   Admin-Einstellungsseite (SEO + Analytics + Report)
│   ├── class-seo.php        Meta-Tags, Open Graph, Twitter Cards, Canonical
│   └── class-analytics.php  GA4 / GTM Einbindung (Consent Mode v2)
└── assets/
    ├── admin.css            Styling der Settings-Seite
    └── admin.js             Toggle-Interaktivität, OG-Upload, Test-Mail AJAX
```

---

## Option-Keys

Alle Optionen werden über die WordPress Options API gespeichert:

| Key | Typ | Default |
|---|---|---|
| `mlt_gsc_verification` | string | `''` |
| `mlt_og_default_image` | int | `0` |
| `mlt_analytics_enabled` | bool (0/1) | `0` |
| `mlt_analytics_provider` | string (ga4/gtm) | `'ga4'` |
| `mlt_analytics_id` | string | `''` |
| `mlt_report_enabled` | bool (0/1) | `0` |
| `mlt_report_email` | string | WordPress Admin-E-Mail |

---

## Hooks

### Actions

| Hook | Beschreibung |
|---|---|
| `mlt_weekly_report` | WP-Cron-Hook für den wöchentlichen Report-Versand |

### Filter

| Filter | Parameter | Beschreibung |
|---|---|---|
| `mlt_weekly_report_html` | `$html`, `$to` | Report-HTML vor dem Versand anpassen |

---

## Deinstallation

Beim Löschen des Plugins über das WordPress-Backend werden automatisch entfernt:
- Alle `mlt_*` Optionen aus der Datenbank
- Geplante WP-Cron-Events (`mlt_weekly_report`)

---

## Changelog

### 1.0.0
- Initiales Release
- SEO-Modul: GSC-Verifikation, Open Graph, Twitter Cards, Canonical URL
- Analytics-Modul: GA4 + GTM mit Consent Mode v2, Bridge zu Agency Core Cookie Consent
- Settings-Seite: Analytics-Toggle, OG-Bild-Upload, Test-Mail-Button
- Report-Mailer: Wöchentlicher HTML-Report via Agency Core SMTP
