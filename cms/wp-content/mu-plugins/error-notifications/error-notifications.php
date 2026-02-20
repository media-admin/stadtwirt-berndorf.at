<?php
/**
 * Plugin Name: Error Notifications
 * Description: Send critical errors to Slack
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ErrorNotifications {
    private $slack_webhook;
    private $last_sent = [];
    
    public function __construct() {
        $this->slack_webhook = defined('SLACK_ERROR_WEBHOOK') ? SLACK_ERROR_WEBHOOK : getenv('SLACK_ERROR_WEBHOOK');
        
        if ($this->slack_webhook && !$this->is_local()) {
            set_error_handler([$this, 'handle_error']);
            register_shutdown_function([$this, 'handle_fatal']);
        }
    }
    
    private function is_local() {
        return defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local';
    }
    
    public function handle_error($errno, $errstr, $errfile, $errline) {
        // Only handle errors, not warnings/notices
        if (!($errno & (E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR))) {
            return false;
        }
        
        $this->send_notification([
            'type' => 'PHP Error',
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'severity' => $this->get_severity($errno),
        ]);
        
        return false; // Let WordPress handle it too
    }
    
    public function handle_fatal() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->send_notification([
                'type' => 'Fatal Error',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'severity' => 'critical',
            ]);
        }
    }
    
    private function send_notification($error) {
        // Prevent spam: Only send same error once per hour
        $error_hash = md5($error['message'] . $error['file'] . $error['line']);
        
        if (isset($this->last_sent[$error_hash]) && 
            time() - $this->last_sent[$error_hash] < 3600) {
            return;
        }
        
        $this->last_sent[$error_hash] = time();
        
        // Build Slack message
        $message = [
            'text' => '🚨 ' . $error['type'] . ' on ' . (defined('WP_ENVIRONMENT_TYPE') ? strtoupper(WP_ENVIRONMENT_TYPE) : 'PRODUCTION'),
            'attachments' => [
                [
                    'color' => $error['severity'] === 'critical' ? 'danger' : 'warning',
                    'fields' => [
                        [
                            'title' => 'Message',
                            'value' => $error['message'],
                            'short' => false,
                        ],
                        [
                            'title' => 'File',
                            'value' => $error['file'] . ':' . $error['line'],
                            'short' => false,
                        ],
                        [
                            'title' => 'URL',
                            'value' => $_SERVER['REQUEST_URI'] ?? 'N/A',
                            'short' => true,
                        ],
                        [
                            'title' => 'Time',
                            'value' => date('Y-m-d H:i:s'),
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];
        
        // Send to Slack (non-blocking)
        wp_remote_post($this->slack_webhook, [
            'body' => json_encode($message),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 5,
            'blocking' => false,
        ]);
    }
    
    private function get_severity($errno) {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'critical';
            default:
                return 'error';
        }
    }
}

new ErrorNotifications();