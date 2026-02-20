<?php
/**
 * Object Cache Drop-in
 */

if (!defined('ABSPATH')) exit;

// Verwende APCu als Object Cache (falls verfügbar)
if (extension_loaded('apcu') && ini_get('apc.enabled')) {
    
    function wp_cache_add($key, $data, $group = '', $expire = 0) {
        return apcu_add($key, $data, $expire);
    }
    
    function wp_cache_set($key, $data, $group = '', $expire = 0) {
        return apcu_store($key, $data, $expire);
    }
    
    function wp_cache_get($key, $group = '', $force = false, &$found = null) {
        $data = apcu_fetch($key, $found);
        return $found ? $data : false;
    }
    
    function wp_cache_delete($key, $group = '') {
        return apcu_delete($key);
    }
    
    function wp_cache_flush() {
        return apcu_clear_cache();
    }
}