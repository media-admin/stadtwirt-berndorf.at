<?php
/**
 * Local Development Configuration
 */

// Database
define('DB_NAME', 'stadtwirt-berndorf_at');
define('DB_USER', 'media-admin');
define('DB_PASSWORD', 'Tr1-I7ad#1n');
define('DB_HOST', 'localhost');


// Database Table prefix
$table_prefix = 'sT8W1rt_';

// URLs
define('WP_HOME', 'https://at.stadtwirt-berndorf.localdev');
define('WP_SITEURL', 'https://at.stadtwirt-berndorf.localdev/cms');

// Debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
define('SCRIPT_DEBUG', true);
// define('ACF_DEBUG', true);

// Activate Trash for Media
define( 'MEDIA_TRASH', true );

// Environment
define('WP_ENVIRONMENT_TYPE', 'local');
define('VITE_DEV_SERVER', true);


// Google Maps API Key
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBW9G3wlUswYaSaHwTPsl4_b67Q0X1mRUo');