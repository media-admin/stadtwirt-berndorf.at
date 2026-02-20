<?php
/**
 * Plugin Name: Sentry Integration
 * Description: Error tracking with Sentry
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load Sentry SDK
require_once WPMU_PLUGIN_DIR . '/../../vendor/autoload.php';

/**
 * Initialize Sentry
 */
function init_sentry() {
    // Don't initialize in local development
    if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
        return;
    }
    
    // Get DSN from environment or constant
    $dsn = defined('SENTRY_DSN') ? SENTRY_DSN : getenv('SENTRY_DSN');
    
    if (!$dsn) {
        return;
    }
    
    \Sentry\init([
        'dsn' => $dsn,
        'environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production',
        'release' => defined('SENTRY_RELEASE') ? SENTRY_RELEASE : 'unknown',
        'traces_sample_rate' => 0.2, // 20% of transactions for performance monitoring
        'profiles_sample_rate' => 0.2, // 20% for profiling
        
        // Configure error reporting
        'error_types' => E_ALL & ~E_DEPRECATED & ~E_NOTICE,
        
        // Add context
        'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
            // Add user context if logged in
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                $event->setUser([
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                ]);
            }
            
            // Add WordPress context
            $event->setExtra('wordpress', [
                'version' => get_bloginfo('version'),
                'theme' => wp_get_theme()->get('Name'),
                'plugins' => get_option('active_plugins'),
            ]);
            
            return $event;
        },
    ]);
}
add_action('plugins_loaded', 'init_sentry', 1);

/**
 * Catch fatal errors
 */
function sentry_fatal_error_handler() {
    $error = error_get_last();
    
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        \Sentry\captureException(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }
}
register_shutdown_function('sentry_fatal_error_handler');

/**
 * Catch WordPress errors
 */
add_action('wp_error_added', function($code, $message, $data) {
    \Sentry\captureMessage("WordPress Error: {$code} - {$message}", \Sentry\Severity::error());
}, 10, 3);