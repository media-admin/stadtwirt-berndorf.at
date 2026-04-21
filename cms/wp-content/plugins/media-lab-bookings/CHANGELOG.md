# Changelog

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
Versionierung nach [Semantic Versioning](https://semver.org/lang/de/).

---

## [1.6.0] - 2026-04-21

### media-lab-bookings 1.6.0

#### Added
- **Wording-Konfiguration** (`inc/settings.php`) – neue ACF-Optionsseite unter **Bookings → Einstellungen**; vier konfigurierbare Begriffe: Singular (z.B. „Reservierung"), Plural (z.B. „Reservierungen"), Verb/Button-Text (z.B. „Jetzt reservieren"), Vergangenheit (z.B. „Reservierung eingegangen"); globale Hilfsfunktion `mlb_term( $type )` gibt den konfigurierten Begriff zurück oder den Standard-Fallback
- **Wording in CPT-Labels** (`inc/cpt.php`) – `mlb_booking` CPT-Labels (name, singular_name, add_new_item, edit_item, not_found) werden dynamisch aus `mlb_term()` befüllt → korrekte Bezeichnung im gesamten WP-Backend
- **Wording im Admin-Menü** (`inc/admin.php`) – Untermenü-Einträge „Buchungen" und „Neue Buchung" sowie Dashboard-Statistik-Labels nutzen `mlb_term()`
- **Wording in Erfolgsmeldung** (`inc/ajax.php`) – Formular-Erfolgsmeldung nutzt `mlb_term('singular')`; kann zusätzlich global unter **Einstellungen → Standard Erfolgsmeldung** überschrieben werden
- **Wording im Shortcode** (`inc/shortcode.php`) – Button-Label Fallback auf `mlb_term('verb')` wenn kein standortspezifisches Label gesetzt ist

#### Fixed
- **iCal-Anhang** (`inc/mail.php`) – Anhang aus der initialen Bestätigungsmail (Formular-Submit) entfernt. iCal wird nur noch bei Status-Mail „Bestätigt" und Erinnerungsmail angehängt.
- **iCal-Download-Link** (`templates/booking-form.php`, `assets/js/booking-form.js`, `inc/ajax.php`) – Download-Link aus dem Formular-Erfolgs-Screen entfernt. Termin-Speicherung ist erst nach Bestätigung sinnvoll.

---

## [1.5.9] - 2026-04-20

### media-lab-bookings 1.5.9

#### Fixed
- **`inc/calendar.php`** – Buchungen wurden im Kalender nicht angezeigt weil ACF das Datum intern als `Ymd` (z.B. `20260422`) speichert, die Kalender-Zellen aber `Y-m-d` (`2026-04-22`) als Array-Key verwenden. Zwei Korrekturen: (1) Datums-Normalisierung beim Gruppieren via `date('Y-m-d', strtotime($date_raw))` damit beide Formate auf denselben Key landen; (2) BETWEEN-Query-Werte auf `Ymd`-Format umgestellt (`20260401`–`20260430`) mit `type => 'CHAR'` statt `type => 'DATE'`, damit der String-Vergleich korrekt funktioniert.

---

## [1.5.8] - 2026-04-20

### media-lab-bookings 1.5.8

#### Fixed
- **`inc/calendar.php`** – Kritischer PHP-Fehler behoben: `all_booking_statuses()` wurde aufgerufen aber nicht in der Klasse definiert (String-Ersetzung in v1.5.7 fehlgeschlagen). Methode direkt in die Klasse eingefügt.

---

## [1.5.7] - 2026-04-20

### media-lab-bookings 1.5.7

#### Fixed
- **Root cause:** `post_status => 'any'` in `WP_Query` schließt Custom Post Statuses mit `public => false` aus — WordPress setzt intern `exclude_from_search => true` für diese Statuses, und `'any'` berücksichtigt nur Statuses mit `exclude_from_search => false`. Da `mlb-pending`, `mlb-confirmed` und `mlb-cancelled` alle mit `public => false` registriert sind, wurden sie von `'any'` ignoriert.
- **`inc/calendar.php`** – neue Hilfsmethode `all_booking_statuses()` liefert alle relevanten Statuses explizit (`publish`, `mlb-pending`, `mlb-confirmed`, `mlb-cancelled`, `draft`, `private`); beide Abfragen (Monatsansicht + Popup) verwenden diese Liste.
- **`inc/dashboard-widget.php`** – `post_status => 'any'` durch explizite Liste ersetzt.
- **`inc/admin.php`** – `count_bookings_by_status()` ebenfalls auf explizite Liste.

---

## [1.5.6] - 2026-04-20

### media-lab-bookings 1.5.6

#### Fixed
- **Root cause identifiziert:** WordPress setzt beim Speichern im Backend-Editor den `post_status` unkontrolliert auf `'publish'`, unabhängig vom ACF-Feld `mlb_booking_status`. Dadurch zählt `wp_count_posts()` alle Buchungen unter `->publish` statt unter `->{'mlb-pending'}` etc. → Dashboard-Zähler zeigen 0.
- **`inc/admin.php`** – `dashboard_page()`: neue Hilfsmethode `count_bookings_by_status()` zählt via `WP_Query` nach ACF-Meta-Feld `mlb_booking_status` (unabhängig vom WP-Post-Status). `wp_count_posts()` entfernt.
- **`inc/notifications.php`** – `on_acf_save()`: nach jedem Statuswechsel wird der WP-Post-Status via `wp_update_post()` synchronisiert (`mlb-pending` → `mlb-pending`, `mlb-confirmed` → `mlb-confirmed` etc.). Damit bleiben WP-Post-Status und ACF-Meta dauerhaft konsistent und alle Abfragen (auch `wp_count_posts`) liefern korrekte Ergebnisse.
- **`inc/dashboard-widget.php`** – `render()`: `post_status => 'any'` statt fester Liste; Ausstehend-Zähler ebenfalls via `WP_Query` nach Meta-Feld; Meta-Filter auf `mlb-pending` und `mlb-confirmed` (IN) statt NOT IN cancelled.

---

## [1.5.5] - 2026-04-20

### media-lab-bookings 1.5.5

#### Fixed
- **`inc/calendar.php`** – Bestätigte und stornierte Buchungen wurden im Kalender weiterhin nicht angezeigt. Ursache: `get_posts()` setzt intern `suppress_filters => true` und verarbeitet `post_status => 'any'` für registrierte Custom Post Statuses nicht korrekt. Fix: beide Abfragen (Monatsansicht + Tages-Popup) auf `new WP_Query()` umgestellt, das `'any'` zuverlässig verarbeitet und keine Filter unterdrückt. Nicht mehr benötigter `exclude_trash_filter` entfernt.

---

## [1.5.4] - 2026-04-20

### media-lab-bookings 1.5.4

#### Fixed
- **`inc/calendar.php`** – Kalenderansicht zeigte nur Buchungen mit WP-`post_status = 'mlb-pending'`. Ursache: WordPress setzt beim Speichern im Backend-Editor den `post_status` automatisch auf `'publish'`, unabhängig vom ACF-Feld `mlb_booking_status`. Bestätigte Buchungen hatten dadurch WP-`post_status = 'publish'` und das ACF-Feld `mlb_booking_status = 'mlb-confirmed'`. Die Kalenderabfrage verwendete eine feste Liste (`['publish', 'mlb-pending', ...]`), die `'publish'` zwar enthielt, aber zu eng gefasst war. Fix: `post_status => 'any'` erfasst alle WP-Statuses zuverlässig; Papierkorb-Einträge werden via `posts_where`-Filter explizit ausgeschlossen. Status-Farbgebung liest `mlb_booking_status` aus Post-Meta (unverändert korrekt).

---

## [1.5.3] - 2026-04-20

### media-lab-bookings 1.5.3

#### Fixed
- **`inc/notifications.php`** – Statuswechsel-Erkennung komplett überarbeitet. Vorher: neuer Status aus `$_POST['acf']['field_mlb_booking_status']` gelesen (unzuverlässig, da ACF zum Zeitpunkt des Hooks den Wert noch nicht gespeichert haben kann) und mit `_mlb_previous_status` verglichen (wurde nie initial gesetzt → leerer Vergleichswert). Führte dazu, dass beim Bestätigen einer Buchung die Stornierungsmail ausgelöst wurde. Jetzt: zwei Hook-Prioritäten — Priorität 5 (`capture_old_status`) sichert den aktuellen DB-Wert **vor** der ACF-Speicherung als Snapshot; Priorität 20 (`on_acf_save`) liest den neuen Status **nach** der ACF-Speicherung direkt aus der DB und vergleicht mit dem Snapshot. Kein `$_POST` mehr.

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
