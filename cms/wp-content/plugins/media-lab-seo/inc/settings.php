<?php
/**
 * Settings Page
 * 
 * @package MediaLab_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Settings Page
 */
function medialab_seo_add_settings_page() {
    add_options_page(
        'SEO Toolkit Settings',
        'SEO Toolkit',
        'manage_options',
        'medialab-seo',
        'medialab_seo_render_settings_page'
    );
}
add_action('admin_menu', 'medialab_seo_add_settings_page');

/**
 * Register Settings
 */
function medialab_seo_register_settings() {
    register_setting('medialab_seo', 'medialab_seo_enabled');
    register_setting('medialab_seo', 'medialab_seo_schema_enabled');
    register_setting('medialab_seo', 'medialab_seo_og_enabled');
    register_setting('medialab_seo', 'medialab_seo_twitter_enabled');
    register_setting('medialab_seo', 'medialab_seo_site_name');
    register_setting('medialab_seo', 'medialab_seo_twitter_username');
    register_setting('medialab_seo', 'medialab_seo_default_image');
}
add_action('admin_init', 'medialab_seo_register_settings');

/**
 * Render Settings Page
 */
function medialab_seo_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Save settings
    if (isset($_POST['medialab_seo_save'])) {
        check_admin_referer('medialab_seo_settings');
        
        update_option('medialab_seo_enabled', isset($_POST['enabled']) ? '1' : '0');
        update_option('medialab_seo_schema_enabled', isset($_POST['schema_enabled']) ? '1' : '0');
        update_option('medialab_seo_og_enabled', isset($_POST['og_enabled']) ? '1' : '0');
        update_option('medialab_seo_twitter_enabled', isset($_POST['twitter_enabled']) ? '1' : '0');
        update_option('medialab_seo_site_name', sanitize_text_field($_POST['site_name']));
        update_option('medialab_seo_twitter_username', sanitize_text_field($_POST['twitter_username']));
        update_option('medialab_seo_default_image', esc_url_raw($_POST['default_image']));
        
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $enabled = get_option('medialab_seo_enabled', '1');
    $schema_enabled = get_option('medialab_seo_schema_enabled', '1');
    $og_enabled = get_option('medialab_seo_og_enabled', '1');
    $twitter_enabled = get_option('medialab_seo_twitter_enabled', '1');
    $site_name = get_option('medialab_seo_site_name', get_bloginfo('name'));
    $twitter_username = get_option('medialab_seo_twitter_username', '');
    $default_image = get_option('medialab_seo_default_image', '');
    ?>
    <div class="wrap">
        <h1>🔍 Media Lab SEO Toolkit</h1>
        <p>Comprehensive SEO solution for better search visibility.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('medialab_seo_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Enable SEO Features</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($enabled, '1'); ?>>
                            Enable all SEO features
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Schema.org Markup</th>
                    <td>
                        <label>
                            <input type="checkbox" name="schema_enabled" value="1" <?php checked($schema_enabled, '1'); ?>>
                            Enable Schema.org structured data
                        </label>
                        <p class="description">Adds JSON-LD markup for better search results</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Open Graph</th>
                    <td>
                        <label>
                            <input type="checkbox" name="og_enabled" value="1" <?php checked($og_enabled, '1'); ?>>
                            Enable Open Graph tags
                        </label>
                        <p class="description">Facebook, LinkedIn sharing optimization</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Twitter Cards</th>
                    <td>
                        <label>
                            <input type="checkbox" name="twitter_enabled" value="1" <?php checked($twitter_enabled, '1'); ?>>
                            Enable Twitter Cards
                        </label>
                        <p class="description">Enhanced Twitter previews</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Site Name</th>
                    <td>
                        <input type="text" name="site_name" value="<?php echo esc_attr($site_name); ?>" class="regular-text">
                        <p class="description">Your site name (for Schema & Open Graph)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Twitter Username</th>
                    <td>
                        <input type="text" name="twitter_username" value="<?php echo esc_attr($twitter_username); ?>" class="regular-text" placeholder="@yourhandle">
                        <p class="description">Your Twitter handle (with @)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Default Social Image</th>
                    <td>
                        <input type="url" name="default_image" value="<?php echo esc_url($default_image); ?>" class="regular-text" placeholder="https://example.com/image.jpg">
                        <p class="description">Fallback image for social sharing (1200x630px recommended)</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="medialab_seo_save" class="button button-primary" value="Save Settings">
            </p>
        </form>
        
        <hr>
        
        <h2>📊 Status</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Schema.org Markup</td>
                    <td><?php echo $schema_enabled ? '<span style="color:green">✅ Active</span>' : '<span style="color:red">❌ Disabled</span>'; ?></td>
                </tr>
                <tr>
                    <td>Open Graph Tags</td>
                    <td><?php echo $og_enabled ? '<span style="color:green">✅ Active</span>' : '<span style="color:red">❌ Disabled</span>'; ?></td>
                </tr>
                <tr>
                    <td>Twitter Cards</td>
                    <td><?php echo $twitter_enabled ? '<span style="color:green">✅ Active</span>' : '<span style="color:red">❌ Disabled</span>'; ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
}
