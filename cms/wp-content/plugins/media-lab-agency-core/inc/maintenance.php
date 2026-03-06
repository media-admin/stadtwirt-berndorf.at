<?php
/**
 * Maintenance Mode
 * 503 header, admin bypass, configurable via ACF settings.
 * Toggle in Agency Core → Einstellungen → Maintenance Mode.
 * Configurable: Heading, message, date, logo, browser title.
 * Logged-in admins see normal site + orange admin bar indicator.
 * Fallback via define('MEDIALAB_MAINTENANCE_MODE', true) in wp-config.php
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 * TODO: Implement maintenance mode
 */
if (!defined('ABSPATH')) { exit; }
