<?php
/**
 * Plugin Name: Media Lab Events
 * Plugin URI:  https://media-lab.de
 * Description: Events CPT with ACF fields and shortcodes for Media Lab Agency sites.
 * Version:     1.0.0
 * Author:      Media Lab
 * Text Domain: media-lab-events
 */

if (!defined('ABSPATH')) exit;

define('MEDIA_LAB_EVENTS_VERSION', '1.0.0');
define('MEDIA_LAB_EVENTS_PATH', plugin_dir_path(__FILE__));
define('MEDIA_LAB_EVENTS_URL', plugin_dir_url(__FILE__));

require_once MEDIA_LAB_EVENTS_PATH . 'inc/cpt.php';
require_once MEDIA_LAB_EVENTS_PATH . 'inc/acf.php';
require_once MEDIA_LAB_EVENTS_PATH . 'inc/shortcodes.php';
