<?php
/**
 * WordPress Configuration - Environment Switcher
 * 
 * Loads environment-specific config based on server.
 */

// Determine environment
if (file_exists(__DIR__ . '/wp-config-local.php')) {
    // Local development
    require_once __DIR__ . '/wp-config-local.php';
} elseif (file_exists(__DIR__ . '/wp-config-staging.php')) {
    // Staging server
    require_once __DIR__ . '/wp-config-staging.php';
} elseif (file_exists(__DIR__ . '/wp-config-production.php')) {
    // Production server
    require_once __DIR__ . '/wp-config-production.php';
} else {
    die('No environment configuration found!');
}

// Shared configuration (all environments)

// Database Table prefix
$table_prefix = 'sT8W1rt_';

// Authentication Unique Keys and Salts
// Generate: https://api.wordpress.org/secret-key/1.1/salt/
define('AUTH_KEY',         '%XySf<n+-Tr%:HxHnbJ_+-7 bR7;E_R:[wuzpQyZr9+NNMDTX+$3,7?@1N :bRL_');
define('SECURE_AUTH_KEY',  'o9N$r8L|ut#pms{hY.07>x,+,+z:Vzj/4mgf_j!cJgl5}F%W$-die|-f5W=G-FXY');
define('LOGGED_IN_KEY',    ']*GJ;*pQ9ADLdE`bYY?)YoiC9w?`h.y[_@UN9JMGo5QfMI+8!JmwEM.@{$DHqr4}');
define('NONCE_KEY',        'OV$+b)]@w?sg~M,a@GaZlbB-t<yAj>>*t+d}#)]-q{/=9o|FYrd{:C8/>r|2sbKz');
define('AUTH_SALT',        ' 3@d5A2qn(@,Lq)HrcRZP/GSlC,MZ-nAM9DDgOzP:uVqF@/{LBV3IN&,4CZM6sD,');
define('SECURE_AUTH_SALT', 'jki[c;(!$8T;+*zgNRk$QF{M/-l(vgx-VV5-- ?SxXLxfkS[(a)Tdy~tDnW Rdyv');
define('LOGGED_IN_SALT',   'a|Z,x3TZ^wp/_odT25B,^<-(v6V2 AT2L~1X&mk/VN7Y/R?1]l~)E`@<YHLvvW7/');
define('NONCE_SALT',       '+2=+sz+|9/rfbn8s3Y5H-uuw#-|Kbq^Taeg3EOju#&vCjs{tU !P-YD?a*<,tbR[');

// Database settings
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// WordPress Memory
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Auto-updates
define('AUTOMATIC_UPDATER_DISABLED', true);

// Absolute path
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Bootstrap WordPress
require_once ABSPATH . 'wp-settings.php';

// Better Stack (Logtail)
define('LOGTAIL_SOURCE_TOKEN', 'qqP84gVb14fpM7mesNM2EYn8');