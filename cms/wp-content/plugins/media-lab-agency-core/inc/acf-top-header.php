<?php
/**
 * Dieser File ist ein Kompatibilitäts-Shim.
 *
 * Ältere Versionen von media-lab-agency-core.php laden noch
 * acf-top-header.php direkt. Dieser Shim leitet einfach
 * auf die neue, zentrale acf-settings.php weiter, damit
 * kein Code doppelt läuft.
 */
if (!defined('ABSPATH')) exit;

// Nur einbinden, falls acf-settings noch nicht geladen wurde
if (!defined('MEDIALAB_ACF_SETTINGS_LOADED')) {
    require_once __DIR__ . '/acf-settings.php';
}
