<?php
/**
 * Plugin Name: Log Forwarder
 * Description: Forward logs to Better Stack
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class LogForwarder {
    private $source_token;
    private $endpoint = 'https://in.logs.betterstack.com';
    
    public function __construct() {
        $this->source_token = defined('LOGTAIL_SOURCE_TOKEN') ? LOGTAIL_SOURCE_TOKEN : getenv('LOGTAIL_SOURCE_TOKEN');
        
        if ($this->source_token && !$this->is_local()) {
            add_action('shutdown', [$this, 'send_logs']);
        }
    }
    
    private function is_local() {
        return defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local';
    }
    
    public function send_logs() {
        // Read debug.log
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_file)) {
            return;
        }
        
        // Only send new logs (implement offset tracking)
        $logs = $this->get_new_logs($log_file);
        
        if (empty($logs)) {
            return;
        }
        
        // Send to Better Stack
        $this->send_to_betterstack($logs);
    }
    
    private function get_new_logs($log_file) {
        // Implement offset tracking to only send new logs
        // This is a simplified version
        $offset_file = WP_CONTENT_DIR . '/logtail-offset.txt';
        $last_offset = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;
        
        $current_size = filesize($log_file);
        
        if ($current_size <= $last_offset) {
            return [];
        }
        
        $handle = fopen($log_file, 'r');
        fseek($handle, $last_offset);
        $new_content = fread($handle, $current_size - $last_offset);
        fclose($handle);
        
        // Update offset
        file_put_contents($offset_file, $current_size);
        
        return explode("\n", trim($new_content));
    }
    
    private function send_to_betterstack($logs) {
        $payload = [];
        
        foreach ($logs as $log) {
            if (empty($log)) {
                continue;
            }
            
            $payload[] = [
                'dt' => date('c'),
                'message' => $log,
                'level' => $this->detect_log_level($log),
                'environment' => defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production',
                'application' => 'wordpress',
            ];
        }
        
        if (empty($payload)) {
            return;
        }
        
        wp_remote_post($this->endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->source_token,
            ],
            'body' => json_encode($payload),
            'timeout' => 5,
            'blocking' => false, // Don't block page load
        ]);
    }
    
    private function detect_log_level($log) {
        if (stripos($log, 'fatal') !== false || stripos($log, 'error') !== false) {
            return 'error';
        } elseif (stripos($log, 'warning') !== false) {
            return 'warn';
        } elseif (stripos($log, 'notice') !== false) {
            return 'info';
        }
        
        return 'debug';
    }
}

new LogForwarder();