# Media Lab Agency Core

Core functionality plugin for Media Lab agency websites.

## Features

- **Shortcodes**: Hero Slider, Accordion, Stats, Testimonials, etc.
- **Security**: Enhanced WordPress security features
- **Admin**: Dashboard customizations
- **Helpers**: Utility functions for theme development
- **WP All Import Integration**: Timeout- und User-Agent-Blocking-Fixes für Bilder-Downloads (siehe Hinweis unten)

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Upload to `/wp-content/plugins/media-lab-agency-core/`
2. Activate through WordPress admin
3. Use shortcodes in your content

## WP All Import – Bilder-Download-Fixes

Bei aktivem WP All Import (`PMXI_VERSION` definiert) stellt das Plugin automatisch bereit:

- **Timeout-Fix**: Erhöht den Bilder-Download-Timeout auf 30s (Filter `pmxi_image_download_timeout`, überschreibbar via `mlac_wpai_image_timeout_seconds`).
- **`custom_file_download()`**: Umgeht User-Agent-basiertes Blocking durch CDNs/WAFs, die den erkennbaren WP-All-Import-UA stillschweigend droppen (Symptom: `cURL error 28`, TCP/TLS erfolgreich, 0 bytes empfangen).

**`custom_file_download()` erfordert manuelle Einrichtung pro Projekt/Import**, da sie nicht automatisch greift:

1. Bild-Feld im Import-Template auf `[custom_file_download({Bildfeld}, "png")]` umstellen (statt reiner URL-Zuordnung)
2. In den Image Options die Checkbox "Use images currently uploaded in wp-content/uploads/wpallimport/files/" aktivieren

Nur bei Bedarf einsetzen (Verdacht auf UA-Blocking, z. B. wenn der Timeout-Fix allein nicht reicht).

## Changelog

### 1.18.0
- WP All Import: Timeout-Fix (`pmxi_image_download_timeout`) und `custom_file_download()`-Helper gegen UA-basiertes Blocking ergänzt. Backport aus dem Janecka-Projekt.

## Version

1.18.0