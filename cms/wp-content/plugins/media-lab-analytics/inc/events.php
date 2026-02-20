<?php
/**
 * Custom Events System
 * 
 * @package MediaLab_Analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Track custom event
 * 
 * Usage:
 * do_action('medialab_track_event', 'button_click', ['button_name' => 'Download']);
 */
function medialab_analytics_track_event($event_name, $event_params = []) {
    // Only track if GA4 is configured
    $ga4_id = get_option('medialab_analytics_ga4_id');
    if (empty($ga4_id)) {
        return;
    }
    
    // Store event for output
    if (!isset($GLOBALS['medialab_analytics_events'])) {
        $GLOBALS['medialab_analytics_events'] = [];
    }
    
    $GLOBALS['medialab_analytics_events'][] = [
        'name' => sanitize_key($event_name),
        'params' => $event_params
    ];
}
add_action('medialab_track_event', 'medialab_analytics_track_event', 10, 2);

/**
 * Output custom events in footer
 */
function medialab_analytics_output_events() {
    if (!isset($GLOBALS['medialab_analytics_events']) || empty($GLOBALS['medialab_analytics_events'])) {
        return;
    }
    
    $ga4_id = get_option('medialab_analytics_ga4_id');
    if (empty($ga4_id)) {
        return;
    }
    ?>
    <script>
    // Custom Events - Media Lab Analytics
    <?php foreach ($GLOBALS['medialab_analytics_events'] as $event): ?>
    gtag('event', '<?php echo esc_js($event['name']); ?>', <?php echo json_encode($event['params']); ?>);
    <?php endforeach; ?>
    </script>
    <?php
}
add_action('wp_footer', 'medialab_analytics_output_events', 100);

/**
 * Automatic form submission tracking
 */
function medialab_analytics_form_tracking_script() {
    if (!medialab_analytics_should_track()) {
        return;
    }
    
    $ga4_id = get_option('medialab_analytics_ga4_id');
    if (empty($ga4_id)) {
        return;
    }
    ?>
    <script>
    // Auto-track form submissions
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var formName = form.getAttribute('id') || form.getAttribute('class') || 'unknown_form';
                
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'form_submit', {
                        'form_name': formName,
                        'form_action': form.getAttribute('action') || window.location.href
                    });
                }
            });
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'medialab_analytics_form_tracking_script', 90);

/**
 * Track WooCommerce events (if WooCommerce is active)
 */
if (class_exists('WooCommerce')) {
    
    // Add to cart
    function medialab_analytics_track_add_to_cart($cart_item_key, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        do_action('medialab_track_event', 'add_to_cart', [
            'item_id' => $product_id,
            'item_name' => $product->get_name(),
            'quantity' => $quantity,
            'price' => $product->get_price()
        ]);
    }
    add_action('woocommerce_add_to_cart', 'medialab_analytics_track_add_to_cart', 10, 3);
    
    // Purchase
    function medialab_analytics_track_purchase($order_id) {
        $order = wc_get_order($order_id);
        
        do_action('medialab_track_event', 'purchase', [
            'transaction_id' => $order_id,
            'value' => $order->get_total(),
            'currency' => $order->get_currency(),
            'items' => count($order->get_items())
        ]);
    }
    add_action('woocommerce_thankyou', 'medialab_analytics_track_purchase');
}
