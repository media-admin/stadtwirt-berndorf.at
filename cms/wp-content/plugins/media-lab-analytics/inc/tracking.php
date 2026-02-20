<?php
/**
 * Tracking Code Implementation
 * 
 * @package MediaLab_Analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if tracking should be loaded
 */
function medialab_analytics_should_track() {
    // Check if enabled
    if (get_option('medialab_analytics_enabled') !== '1') {
        return false;
    }
    
    // Check if user should be excluded
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $exclude_roles = get_option('medialab_analytics_exclude_roles', ['administrator']);
        
        foreach ($exclude_roles as $role) {
            if (in_array($role, $user->roles)) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Output Google Analytics 4 tracking code
 */
function medialab_analytics_ga4_code() {
    if (!medialab_analytics_should_track()) {
        return;
    }
    
    $ga4_id = get_option('medialab_analytics_ga4_id');
    
    if (empty($ga4_id)) {
        return;
    }
    ?>
    <!-- Google Analytics 4 - Media Lab Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo esc_js($ga4_id); ?>', {
            'anonymize_ip': true,
            'cookie_flags': 'SameSite=None;Secure'
        });
    </script>
    <?php
}
add_action('wp_head', 'medialab_analytics_ga4_code', 1);

/**
 * Output Google Tag Manager code (head)
 */
function medialab_analytics_gtm_head() {
    if (!medialab_analytics_should_track()) {
        return;
    }
    
    $gtm_id = get_option('medialab_analytics_gtm_id');
    
    if (empty($gtm_id)) {
        return;
    }
    ?>
    <!-- Google Tag Manager - Media Lab Analytics -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
    <?php
}
add_action('wp_head', 'medialab_analytics_gtm_head', 1);

/**
 * Output Google Tag Manager code (body)
 */
function medialab_analytics_gtm_body() {
    if (!medialab_analytics_should_track()) {
        return;
    }
    
    $gtm_id = get_option('medialab_analytics_gtm_id');
    
    if (empty($gtm_id)) {
        return;
    }
    ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php
}
add_action('wp_body_open', 'medialab_analytics_gtm_body', 1);

/**
 * Output Facebook Pixel code
 */
function medialab_analytics_fb_pixel() {
    if (!medialab_analytics_should_track()) {
        return;
    }
    
    $fb_pixel_id = get_option('medialab_analytics_fb_pixel_id');
    
    if (empty($fb_pixel_id)) {
        return;
    }
    ?>
    <!-- Facebook Pixel - Media Lab Analytics -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?php echo esc_js($fb_pixel_id); ?>');
    fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?php echo esc_attr($fb_pixel_id); ?>&ev=PageView&noscript=1"
    /></noscript>
    <?php
}
add_action('wp_head', 'medialab_analytics_fb_pixel', 2);

/**
 * Add admin notice if no tracking IDs configured
 */
function medialab_analytics_admin_notice() {
    $ga4_id = get_option('medialab_analytics_ga4_id');
    $gtm_id = get_option('medialab_analytics_gtm_id');
    $fb_pixel_id = get_option('medialab_analytics_fb_pixel_id');
    
    if (empty($ga4_id) && empty($gtm_id) && empty($fb_pixel_id)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Media Lab Analytics:</strong> 
                No tracking IDs configured. 
                <a href="<?php echo admin_url('options-general.php?page=medialab-analytics'); ?>">Configure now</a>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'medialab_analytics_admin_notice');
