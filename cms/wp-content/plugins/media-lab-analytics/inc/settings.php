<?php
/**
 * Settings Page
 * 
 * @package MediaLab_Analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Settings Page
 */
function medialab_analytics_add_settings_page() {
    add_options_page(
        'Analytics Settings',
        'Analytics',
        'manage_options',
        'medialab-analytics',
        'medialab_analytics_render_settings_page'
    );
}
add_action('admin_menu', 'medialab_analytics_add_settings_page');

/**
 * Register Settings
 */
function medialab_analytics_register_settings() {
    register_setting('medialab_analytics', 'medialab_analytics_enabled');
    register_setting('medialab_analytics', 'medialab_analytics_ga4_id');
    register_setting('medialab_analytics', 'medialab_analytics_gtm_id');
    register_setting('medialab_analytics', 'medialab_analytics_fb_pixel_id');
    register_setting('medialab_analytics', 'medialab_analytics_exclude_roles', [
        'default' => ['administrator']
    ]);
}
add_action('admin_init', 'medialab_analytics_register_settings');

/**
 * Render Settings Page
 */
function medialab_analytics_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Save settings
    if (isset($_POST['medialab_analytics_save'])) {
        check_admin_referer('medialab_analytics_settings');
        
        update_option('medialab_analytics_enabled', isset($_POST['enabled']) ? '1' : '0');
        update_option('medialab_analytics_ga4_id', sanitize_text_field($_POST['ga4_id']));
        update_option('medialab_analytics_gtm_id', sanitize_text_field($_POST['gtm_id']));
        update_option('medialab_analytics_fb_pixel_id', sanitize_text_field($_POST['fb_pixel_id']));
        
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $enabled = get_option('medialab_analytics_enabled', '1');
    $ga4_id = get_option('medialab_analytics_ga4_id', '');
    $gtm_id = get_option('medialab_analytics_gtm_id', '');
    $fb_pixel_id = get_option('medialab_analytics_fb_pixel_id', '');
    ?>
    <div class="wrap">
        <h1>📊 Media Lab Analytics</h1>
        <p>Centralized tracking and analytics management.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('medialab_analytics_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Tracking</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($enabled, '1'); ?>>
                            Enable all tracking codes
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Google Analytics 4 ID</th>
                    <td>
                        <input type="text" name="ga4_id" value="<?php echo esc_attr($ga4_id); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                        <p class="description">Enter your GA4 Measurement ID</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Google Tag Manager ID</th>
                    <td>
                        <input type="text" name="gtm_id" value="<?php echo esc_attr($gtm_id); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
                        <p class="description">Enter your GTM Container ID</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Facebook Pixel ID</th>
                    <td>
                        <input type="text" name="fb_pixel_id" value="<?php echo esc_attr($fb_pixel_id); ?>" class="regular-text" placeholder="XXXXXXXXXXXXXXX">
                        <p class="description">Enter your Facebook Pixel ID</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="medialab_analytics_save" class="button button-primary" value="Save Settings">
            </p>
        </form>
        
        <hr>
        
        <h2>📈 Status</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Status</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Google Analytics 4</td>
                    <td><?php echo $ga4_id ? '<span style="color:green">✅ Active</span>' : '<span style="color:red">❌ Not configured</span>'; ?></td>
                    <td><?php echo esc_html($ga4_id); ?></td>
                </tr>
                <tr>
                    <td>Google Tag Manager</td>
                    <td><?php echo $gtm_id ? '<span style="color:green">✅ Active</span>' : '<span style="color:red">❌ Not configured</span>'; ?></td>
                    <td><?php echo esc_html($gtm_id); ?></td>
                </tr>
                <tr>
                    <td>Facebook Pixel</td>
                    <td><?php echo $fb_pixel_id ? '<span style="color:green">✅ Active</span>' : '<span style="color:red">❌ Not configured</span>'; ?></td>
                    <td><?php echo esc_html($fb_pixel_id); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
