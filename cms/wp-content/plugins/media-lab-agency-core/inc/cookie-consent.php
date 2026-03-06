<?php
/**
 * Cookie Consent Manager
 * Banner with "Alle akzeptieren" / "Einstellungen" / "Ablehnen"
 * Settings modal with toggle per category.
 * Floating button (bottom-left) always visible.
 * 4 categories: Notwendig (required), Statistik, Marketing, Komfort
 * Consent stored as JSON in localStorage with version + timestamp.
 * HTTP 503-compliant: no retroactive tracking.
 * Snippet management via ACF: Head + Body code per category.
 * ACF Field Group: group_cookie_consent (20 fields)
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 * TODO: Implement cookie consent manager + ACF field group
 */
if (!defined('ABSPATH')) { exit; }
