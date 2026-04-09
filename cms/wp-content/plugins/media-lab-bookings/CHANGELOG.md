# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---

## [1.5.2] - 2026-04-09

### media-lab-bookings 1.5.2

#### Fixed
- **`inc/cpt.php`** – `show_in_menu => false` für beide CPTs (`mlb_location`, `mlb_booking`). WordPress hat die Untermenüeinträge automatisch generiert, zusätzlich zu den manuell angelegten in `admin.php` → doppelte Einträge. Vollständige Kontrolle über das Menü nun in `admin.php`.

#### Added
- **iCal-Feed** (`inc/feed.php`) – abonnierbare URL (`/mlb-calendar-feed/`) für Google Calendar, Apple Calendar, Outlook u.a.; optionaler Token-Schutz; Filter nach Standort (`?location=ID`) und Status (`?status=confirmed`); stornierte Buchungen werden standardmäßig ausgeblendet; Feed-URLs werden im Backend-Dashboard angezeigt
- **Menü: Neue Buchung** (`inc/admin.php`) – Untermenüeintrag und Button im Dashboard für direkte manuelle Buchungserfassung im Backend (`post-new.php?post_type=mlb_booking`)
- **Menü: Neuer Standort** – Direktlink im Untermenü

#### Changed
- **`inc/admin.php`** – `dashboard_page()` zeigt Feed-URLs tabellarisch (alle Standorte + je Standort einzeln); „+ Neue Buchung"-Button als primärer CTA; Menü-Einträge neu geordnet: Übersicht → Buchungen → Neue Buchung → Standorte → Neuer Standort → Kalender

---

## [1.5.1] - 2026-04-09

### media-lab-bookings 1.5.1

#### Fixed
- **`media-lab-bookings.php`** – Include-Reihenfolge korrigiert: `admin.php` wird vor `calendar.php` geladen. Dadurch ist das Eltern-Menü `mlb-bookings` bereits registriert wenn `calendar.php` sein Untermenü hinzufügt. Verhindert „Sorry, you are not allowed to access this page." beim Aufruf der Kalenderansicht.
- **`inc/calendar.php`** – `$_GET`- und `$_POST`-Parameter werden null-sicher ausgelesen (`isset()` statt `?? default`). Verhindert Deprecated-Warnings „strpos(): Passing null to parameter #1" und „str_replace(): Passing null to parameter #3" in PHP 8.x.

---

## [1.5.0] - 2026-04-09

### media-lab-bookings 1.5.0

#### Added
- **Kalenderansicht** (`inc/calendar.php`, `assets/css/calendar.css`, `assets/js/calendar.js`) – neue Admin-Seite unter Bookings → Kalender; Monatsansicht mit allen Buchungen farbkodiert nach Status (Ausstehend, Bestätigt, Storniert); Navigation vor/zurück + Heute-Button; Filter nach Standort; Klick auf einen Tag öffnet Detail-Popup mit vollständiger Buchungstabelle und direktem Link zur Buchung
- **Max. Buchungen pro Tag** (`inc/acf-fields.php`, `inc/slots.php`) – neues ACF-Feld `mlb_max_per_day` im Tab Zeitslots pro Standort (0 = unbegrenzt); wenn Tageslimit erreicht, gibt `MLB_Slots::generate()` leeres Array zurück → alle Slots des Tages deaktiviert; neue Hilfsmethode `MLB_Slots::count_day_bookings()`
- **WP-Dashboard-Widget** (`inc/dashboard-widget.php`) – zeigt nächste X bevorstehende Buchungen (Status: Ausstehend/Bestätigt) direkt auf der WordPress-Startseite; Anzahl konfigurierbar (1–20, Standard: 5) über Widget-Einstellungen; heutiger Termin wird rot hervorgehoben; Links zu Kalender und Buchungsliste

#### Changed
- **`media-lab-bookings.php`** – Version 1.4.0 → 1.5.0; neue Includes `calendar.php` und `dashboard-widget.php`
- **`inc/acf-fields.php`** – Tab Zeitslots: `mlb_max_capacity` Breite 33 → 25%; `mlb_max_per_day` als viertes Feld ergänzt

---

## [1.4.0] - 2026-04-09

### media-lab-bookings 1.4.0

#### Added

- **iCal-Generator** (`inc/ical.php`) – RFC 5545-konforme `.ics`-Datei pro Buchung; Zeitzone aus WordPress-Einstellungen; Summary aus Dienstleistung + Standortname; AJAX-Download-Endpunkt `mlb_download_ical` (Nonce-gesichert, kein Login erforderlich)
- **iCal-Anhang in Bestätigungsmail** – wird automatisch an die initiale Bestätigungsmail sowie an Status-Mails (Bestätigt, Erinnerung) angehängt
- **iCal-Download-Link im Erfolgs-Screen** – nach erfolgreichem Formular-Submit erscheint ein „Termin in Kalender speichern"-Link
- **Status-E-Mails** (`inc/notifications.php`) – bei manuellem Statuswechsel auf `mlb-confirmed` → Bestätigungsmail mit iCal-Anhang; bei `mlb-cancelled` (manuell oder via Link) → Stornierungsmail; Templates per ACF pro Standort konfigurierbar; Fallback auf Standardtemplates
- **Erinnerungs-E-Mail via WP-Cron** – wird bei Statuswechsel auf `mlb-confirmed` geplant; Vorlaufzeit in Stunden per ACF pro Standort konfigurierbar (`mlb_reminder_hours`, Standard: 24h); iCal-Anhang inklusive; bei Stornierung wird Cron-Job automatisch entfernt
- **Stornierung via Link** (`inc/notifications.php`) – einmaliger Token (`_mlb_cancel_token`) wird beim Erstellen der Buchung generiert (`mlb_after_save_booking`); Link `{cancel_url}` als Platzhalter in allen Mail-Templates verfügbar; AJAX-Endpunkt `mlb_cancel_booking` setzt Status, invalidiert Token, versendet Stornierungsmail
- **CSV-Export** (`inc/export.php`) – Export-Button in Buchungs-Listenansicht; respektiert aktive Filter (Standort, Status); UTF-8 BOM für Excel-Kompatibilität; Spalten: ID, Status, Standort, Datum, Uhrzeit, Name, E-Mail, Telefon, Dienstleistung, Personen, Anmerkungen, Eingangsdatum
- **Neue ACF-Tabs pro Standort** – „Mail: Bestätigt" (Betreff + Template), „Mail: Storniert" (Betreff + Template), „Erinnerungsmail" (Stunden vorher + Betreff + Template)
- **Neuer Platzhalter `{cancel_url}`** – in allen Mail-Templates verfügbar (Bestätigung, Bestätigt, Erinnerung)

#### Changed
- **`inc/mail.php`** – `replace_placeholders` und `wrap_html` als `public static` Methoden (`replace_placeholders_public`, `wrap_html_public`) damit `notifications.php` sie nutzen kann; `{cancel_url}` Platzhalter ergänzt; iCal-Anhang in `send_confirmation()`
- **`inc/ajax.php`** – `ical_url` in AJAX-Erfolgsantwort; `mlb_after_save_booking` Hook löst Token-Generierung aus
- **`assets/js/booking-form.js`** – `showSuccess()` nimmt `icalUrl` Parameter und zeigt Download-Link wenn vorhanden
- **`assets/css/booking-form.css`** – `.mlb-form__ical-link` Stil ergänzt
- **`templates/booking-form.php`** – iCal-Download-Link im Erfolgs-Screen (initial `hidden`, via JS eingeblendet)

---

## [1.3.1] - 2026-03-27

### media-lab-bookings 1.3.1

#### Changed
- **`templates/booking-form.php`** – Name-Feld in eigene Zeile (volle Breite); E-Mail + Telefon in 2-spaltigem Grid darunter
- **`assets/css/booking-form.css`** – `.mlb-form__row--3` entfernt

---

## [1.3.0] - 2026-03-27

### media-lab-bookings 1.3.0

#### Added
- **Template-Override** – `locate_template( 'media-lab-bookings/booking-form.php' )` hat Vorrang vor Plugin-Template
- **Filter `mlb_before_save_booking`** – Buchungsdaten vor dem Speichern filtern
- **Action `mlb_after_save_booking`** – nach dem Speichern ausgelöst
- **Filter `mlb_confirmation_body`** – HTML-Body der Bestätigungsmail
- **Filter `mlb_confirmation_subject`** – Betreff der Bestätigungsmail

---

## [1.2.2] - 2026-03-27

### media-lab-bookings 1.2.2

#### Fixed
- **`assets/js/booking-form.js`** – Initialisierung ans Ende der IIFE verschoben (`TypeError: self.loadLocationData is not a function`)

---

## [1.2.1] - 2026-03-25

### media-lab-bookings 1.2.1

#### Fixed
- **`templates/booking-form.php`** – `mlb_label()` aus Template entfernt; Funktion in `inc/shortcode.php` definiert (verhindert `Cannot redeclare`-Fehler bei mehrfachem Shortcode)

---

## [1.2.0] - 2026-03-25

### media-lab-bookings 1.2.0

#### Changed
- **`shortcode.php`** – Automatische Vorauswahl bei einzelnem Standort; Dropdown ausgeblendet

---

## [1.1.0] - 2026-03-25

### media-lab-bookings 1.1.0

#### Added
- **DSGVO-Zustimmungs-Checkbox** – client- und serverseitig; Inline-Fehlermeldung; automatische Verlinkung der Datenschutzerklärung
- **ACF-Tab „Formular-Labels"** – alle Feldbeschriftungen + DSGVO-Text pro Standort konfigurierbar

---

## [1.0.0] - 2026-03-25

### media-lab-bookings 1.0.0

#### Added
- Initial Release – CPTs, ACF-Felder, Slot-Logik, AJAX, Mail, Shortcode, Admin-Dashboard
