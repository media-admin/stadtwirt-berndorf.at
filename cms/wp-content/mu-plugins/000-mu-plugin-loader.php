<?php
/**
 * MU-Plugin Loader
 * 
 * Loads plugins from subdirectories in mu-plugins.
 * 
 * @package MU_Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agency Core migrated to regular plugin (media-lab-agency-core)
 * No longer loaded as MU-plugin
 */

/**
 * Load Custom Blocks (Legacy - will be deprecated)
 * 
 * Note: Custom blocks are now loaded via Agency Core.
 * This is kept for backwards compatibility during migration.
 */
// Commented out as it's now loaded via Agency Core
// $custom_blocks_file = WPMU_PLUGIN_DIR . '/custom-blocks/custom-blocks.php';
// if (file_exists($custom_blocks_file)) {
//     require_once $custom_blocks_file;
// }

/**
 * Load Custom Functionality (Legacy - will be deprecated)
 * 
 * Note: CPTs and ACF are now in Agency Core.
 * This can be removed after successful migration.
 */
// Commented out - functionality migrated to plugins
