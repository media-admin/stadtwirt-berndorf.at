<?php
/**
 * WooCommerce Theme Integration
 * 
 * @package Custom_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disable default WooCommerce styles
 */
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

/**
 * Custom WooCommerce wrapper
 */
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);

add_action('woocommerce_before_main_content', function() {
    echo '<div class="container"><div class="woocommerce-wrapper">';
});

add_action('woocommerce_after_main_content', function() {
    echo '</div></div>';
});

/**
 * Customize products per page
 */
add_filter('loop_shop_per_page', function($cols) {
    return 12; // 12 products per page
}, 20);

/**
 * Customize products per row
 */
add_filter('loop_shop_columns', function($cols) {
    return 3; // 3 columns
});

/**
 * Customize related products
 */
add_filter('woocommerce_output_related_products_args', function($args) {
    $args['posts_per_page'] = 3;
    $args['columns'] = 3;
    return $args;
});

/**
 * Remove breadcrumbs (we'll add custom ones)
 */
remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

/**
 * Custom Add to Cart button text
 */
add_filter('woocommerce_product_single_add_to_cart_text', function($text) {
    return __('In den Warenkorb', 'custom-theme');
});

add_filter('woocommerce_product_add_to_cart_text', function($text) {
    global $product;
    
    if ($product->is_type('simple')) {
        return __('Kaufen', 'custom-theme');
    }
    
    return $text;
});

/**
 * Custom sale badge
 */
add_filter('woocommerce_sale_flash', function($html, $post, $product) {
    $percentage = '';
    
    if ($product->is_type('variable')) {
        $percentages = array();
        
        $prices = $product->get_variation_prices();
        
        foreach ($prices['price'] as $key => $price) {
            if ($prices['regular_price'][$key] !== $price) {
                $percentages[] = round((($prices['regular_price'][$key] - $price) / $prices['regular_price'][$key]) * 100);
            }
        }
        
        if (!empty($percentages)) {
            $percentage = max($percentages) . '%';
        }
    } elseif ($product->is_on_sale()) {
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        
        if ($regular_price && $sale_price) {
            $percentage = round((($regular_price - $sale_price) / $regular_price) * 100) . '%';
        }
    }
    
    return $percentage ? '<span class="onsale">-' . $percentage . '</span>' : $html;
}, 10, 3);

/**
 * Custom currency symbol
 */
add_filter('woocommerce_currency_symbol', function($currency_symbol, $currency) {
    switch ($currency) {
        case 'EUR':
            $currency_symbol = '€';
            break;
    }
    return $currency_symbol;
}, 10, 2);

/**
 * Remove default sorting dropdown (we'll add custom)
 */
remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);

/**
 * Remove result count
 */
remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);

/**
 * Custom pagination
 */
remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);

add_action('woocommerce_after_shop_loop', function() {
    global $wp_query;
    
    if ($wp_query->max_num_pages <= 1) {
        return;
    }
    
    echo '<nav class="woocommerce-pagination">';
    
    echo paginate_links(array(
        'base' => esc_url_raw(str_replace(999999999, '%#%', get_pagenum_link(999999999, false))),
        'format' => '?paged=%#%',
        'add_args' => false,
        'current' => max(1, get_query_var('paged')),
        'total' => $wp_query->max_num_pages,
        'prev_text' => '&larr;',
        'next_text' => '&rarr;',
        'type' => 'list',
        'end_size' => 3,
        'mid_size' => 3,
    ));
    
    echo '</nav>';
});

/**
 * Disable WooCommerce scripts on non-shop pages
 */
add_action('wp_enqueue_scripts', function() {
    if (function_exists("is_woocommerce") && !is_woocommerce() && !is_cart() && !is_checkout()) {
        // Dequeue WooCommerce styles
        wp_dequeue_style('woocommerce-general');
        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
        
        // Dequeue WooCommerce scripts
        wp_dequeue_script('wc-cart-fragments');
        wp_dequeue_script('woocommerce');
        wp_dequeue_script('wc-add-to-cart');
    }
}, 99);

/**
 * Optimize cart fragments
 */
add_filter('woocommerce_add_to_cart_fragments', function($fragments) {
    // Only update what's necessary
    ob_start();
    ?>
    <span class="cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
    <?php
    $fragments['.cart-count'] = ob_get_clean();
    
    return $fragments;
});

/**
 * Disable cart fragments on non-WC pages
 */
add_action('wp_enqueue_scripts', function() {
    if (function_exists("is_woocommerce") && !is_woocommerce() && !is_cart() && !is_checkout()) {
        wp_dequeue_script('wc-cart-fragments');
    }
}, 100);