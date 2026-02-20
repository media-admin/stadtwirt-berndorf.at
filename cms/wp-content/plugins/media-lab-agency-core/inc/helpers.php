<?php
/**
 * Helper Functions
 * 
 * @package MediaLab_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get plugin version
 */
function medialab_core_version() {
    return MEDIALAB_CORE_VERSION;
}

/**
 * Check if Media Lab Core is active
 * Useful for theme/plugin compatibility checks
 */
function is_medialab_core_active() {
    return true;
}
