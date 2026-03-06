# Installationsanleitung

**Version:** 1.4.0 | **Letzte Aktualisierung:** 2026-03-04

---

## Inhaltsverzeichnis

1. [Voraussetzungen](#voraussetzungen)
2. [Lokale Umgebung (Valet)](#lokale-umgebung-valet)
3. [WordPress installieren](#wordpress-installieren)
4. [Plugins & Theme aktivieren](#plugins--theme-aktivieren)
5. [Assets kompilieren](#assets-kompilieren)
6. [SMTP konfigurieren](#smtp-konfigurieren)
7. [Nach der Installation](#nach-der-installation)
8. [Troubleshooting](#troubleshooting)

---

## Voraussetzungen

| Software | Version | Prüfen |
|---|---|---|
| PHP | 8.0+ | `php -v` |
| MySQL / MariaDB | 5.7+ / 10.3+ | `mysql --version` |
| Node.js | 18+ | `node -v` |
| npm | 9+ | `npm -v` |
| Composer | 2.0+ | `composer --version` |
| WP-CLI | 2.8+ | `wp --version` |
| Laravel Valet | aktuell | `valet --version` |

**ACF Pro** – Lizenz erforderlich. Download unter [advancedcustomfields.com](https://www.advancedcustomfields.com/)

---

## Lokale Umgebung (Valet)

```bash
# Repository klonen
git clone https://github.com/media-admin/media-lab-starter-kit.git
cd media-lab-starter-kit

# Valet-Link setzen
valet link

# Dependencies installieren
npm install
composer install

# Erreichbar unter:
# http://media-lab-starter-kit.test
```

---

## WordPress installieren

```bash
cd cms

# WordPress herunterladen (Deutsch)
wp core download --locale=de_DE

# Datenbank anlegen
mysql -u root -e "CREATE DATABASE media_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# wp-config.php erstellen
wp core config \
  --dbname=media_lab \
  --dbuser=root \
  --dbpass=root \
  --dbprefix=ml_ \
  --locale=de_DE

# WordPress installieren
wp core install \
  --url=media-lab-starter-kit.test \
  --title="Media Lab Starter Kit" \
  --admin_user=admin \
  --admin_password=SICHERES_PASSWORT_HIER \
  --admin_email=markus.tritremmel@media-lab.at

cd ..
```

**Wichtig:** Admin-Passwort sofort nach Installation ändern!

---

## Plugins & Theme aktivieren

```bash
cd cms

# ACF Pro manuell hochladen (Lizenz-Download) dann:
wp plugin activate advanced-custom-fields-pro

# Media Lab Plugins aktivieren
wp plugin activate media-lab-agency-core
wp plugin activate media-lab-seo

# Theme aktivieren
wp theme activate custom-theme

# Standardinhalte bereinigen
wp post delete 1 2 --force          # Sample-Post + Sample-Page
wp comment delete 1 --force         # Sample-Comment
wp plugin delete hello akismet      # Unnötige Standard-Plugins

# Permalinks setzen
wp rewrite structure '/%postname%/'
wp rewrite flush

cd ..
```

---

## Assets kompilieren

```bash
# Production Build (einmalig für die Installation)
npm run build

# Verifizieren
ls cms/wp-content/themes/custom-theme/assets/dist/css/
ls cms/wp-content/themes/custom-theme/assets/dist/js/
# Sollte style.css, main.js und die Lazy Chunks zeigen
```

Für Development mit Hot Reload:
```bash
npm run dev
# Browser: http://media-lab-starter-kit.test
```

---

## SMTP konfigurieren

In `cms/wp-config.php` **vor** `/* That's all */` einfügen:

```php
// SMTP-Konfiguration (Media Lab Agency Core)
define('MEDIALAB_SMTP_ENABLED',   true);
define('MEDIALAB_SMTP_HOST',      'smtp.example.com');
define('MEDIALAB_SMTP_PORT',      587);
define('MEDIALAB_SMTP_USER',      'user@example.com');
define('MEDIALAB_SMTP_PASS',      'geheimes-passwort');
define('MEDIALAB_SMTP_ENC',       'tls');   // tls | ssl | ''
define('MEDIALAB_SMTP_FROM',      'noreply@example.com');
define('MEDIALAB_SMTP_FROM_NAME', 'Meine Website');
```

Test-Mail im Backend: **Einstellungen → Agency Core → SMTP → Test-Mail senden**

---

## Nach der Installation

### Checkliste

```bash
cd cms

# Pluginstatus prüfen
wp plugin list

# Theme aktiv?
wp theme list

# Build-Output vorhanden?
ls ../cms/wp-content/themes/custom-theme/assets/dist/

# WordPress-Version
wp core version
```

### WordPress-Einstellungen

1. **Allgemein** → Zeitzone: Europa/Wien, Datumsformat: d.m.Y
2. **Lesen** → Startseite auf statische Seite setzen
3. **Agency Core** → SMTP konfigurieren und testen
4. **ACF** → JSON-Load-Pfad auf Plugin-Ordner zeigen

### Debugging aktivieren (nur lokal!)

In `cms/wp-config.php`:
```php
define('WP_DEBUG',         true);
define('WP_DEBUG_LOG',     true);
define('WP_DEBUG_DISPLAY', false);
```

---

## Troubleshooting

### Assets laden nicht

```bash
# Build neu ausführen
npm run build

# Cache leeren
cd cms && wp cache flush && cd ..

# Dateiberechtigungen prüfen
chmod -R 755 cms/wp-content/themes/custom-theme/assets/dist/
```

### Weißer Bildschirm (WSOD)

```bash
# Debug-Log prüfen
tail -f cms/wp-content/debug.log

# Plugin-Konflikte isolieren
cd cms
wp plugin deactivate --all
wp plugin activate media-lab-agency-core
```

### SCSS-Build-Fehler "Undefined variable"

`@use '../abstracts' as *;` fehlt am Anfang des Partials. Jedes SCSS-Partial muss diese Zeile enthalten um auf Tokens und Mixins zugreifen zu können.

### Permalinks zeigen 404

```bash
cd cms
wp rewrite structure '/%postname%/'
wp rewrite flush --hard
```

### `window.customTheme` ist undefined

`enqueue.php` wird nicht geladen. In `functions.php` prüfen:
```php
require_once get_template_directory() . '/inc/enqueue.php';
```

### ACF-Felder erscheinen nicht

```bash
cd cms
wp plugin list | grep advanced-custom-fields
# Falls nicht aktiv:
wp plugin activate advanced-custom-fields-pro
wp rewrite flush
```

---

**Weiter:** [docs/06_DEVELOPMENT.md](06_DEVELOPMENT.md) – Entwicklungs-Guide
