<?php
/**
 * Plugin Name: Performance Monitor
 * Description: Log slow queries and page loads
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log slow database queries
 */
add_filter('log_query_custom_data', function($query_data, $query) {
    // Log queries slower than 1 second
    if ($query_data['elapsed'] > 1.0) {
        error_log(sprintf(
            '[SLOW QUERY] %.4f seconds: %s',
            $query_data['elapsed'],
            $query
        ));
        
        // Send to Sentry if available
        if (function_exists('\\Sentry\\captureMessage')) {
            \Sentry\captureMessage(
                "Slow database query: {$query}",
                \Sentry\Severity::warning(),
                [
                    'extra' => [
                        'duration' => $query_data['elapsed'],
                        'query' => $query,
                    ]
                ]
            );
        }
    }
    
    return $query_data;
}, 10, 2);

/**
 * Log slow page loads
 */
add_action('shutdown', function() {
    $execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    
    // Log page loads slower than 3 seconds
    if ($execution_time > 3.0) {
        error_log(sprintf(
            '[SLOW PAGE] %.4f seconds: %s',
            $execution_time,
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));
        
        // Send to Sentry
        if (function_exists('\\Sentry\\captureMessage')) {
            \Sentry\captureMessage(
                "Slow page load: {$_SERVER['REQUEST_URI']}",
                \Sentry\Severity::warning(),
                [
                    'extra' => [
                        'duration' => $execution_time,
                        'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                    ]
                ]
            );
        }
    }
});

/**
 * Monitor memory usage
 */
add_action('shutdown', function() {
    $memory_peak = memory_get_peak_usage(true);
    $memory_limit = wp_convert_hr_to_bytes(WP_MEMORY_LIMIT);
    $memory_percentage = ($memory_peak / $memory_limit) * 100;
    
    // Alert if using more than 80% of memory limit
    if ($memory_percentage > 80) {
        error_log(sprintf(
            '[HIGH MEMORY] %.1f%% used (%s / %s) on %s',
            $memory_percentage,
            size_format($memory_peak),
            WP_MEMORY_LIMIT,
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        ));
    }
});