<?php

namespace FluentCrm\App\Services\BlockRender;

class WooProducts
{
    /**
     * Render WooCommerce products list block for email body.
     *
     * @param string $content
     * @param array $data
     * @return string
     */
    public static function renderProducts($content, $data)
    {
        $customRenderer = apply_filters('fluent_crm/products_renderer', null, $content, $data);
        if (is_callable($customRenderer)) {
            try {
                $rendered = call_user_func($customRenderer, $content, $data);
                return is_string($rendered) ? ProductListRenderer::postProcessRendered($rendered, $data) : '';
            } catch (\Throwable $e) {
                // Fall through to built-in renderer.
            }
        }

        if (!defined('WC_PLUGIN_FILE') || !function_exists('wc_get_products')) {
            return '';
        }

        $attrs = isset($data['attrs']) && is_array($data['attrs']) ? $data['attrs'] : [];
        $atts = ProductListRenderer::mergeDefaultAttributes($attrs);
        $products = self::getWooProducts($atts);

        if (!$products) {
            return '';
        }

        $html = ProductListRenderer::renderProducts($products, $atts, 'fc_woo_products');

        return ProductListRenderer::postProcessRendered($html, $data);
    }

    private static function getWooProducts($atts)
    {
        if (!defined('WC_PLUGIN_FILE') || !function_exists('wc_get_products')) {
            return [];
        }

        $perPage = max(1, min(20, absint(isset($atts['selectedPostsPerPage']) ? $atts['selectedPostsPerPage'] : 3)));
        $order = strtolower((string)(isset($atts['order']) ? $atts['order'] : 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderByRaw = sanitize_key((string)(isset($atts['orderBy']) ? $atts['orderBy'] : 'date'));

        $orderByMap = [
            'date'       => 'date',
            'modified'   => 'modified',
            'title'      => 'title',
            'menu_order' => 'menu_order',
            'rand'       => 'rand',
        ];
        $orderBy = isset($orderByMap[$orderByRaw]) ? $orderByMap[$orderByRaw] : 'date';

        $queryArgs = [
            'status'  => 'publish',
            'limit'   => $perPage,
            'orderby' => $orderBy,
            'order'   => $order,
            'return'  => 'objects',
        ];

        $taxType = sanitize_text_field((string)(isset($atts['taxType']) ? $atts['taxType'] : 'all'));
        if ($taxType && $taxType !== 'all' && is_numeric($taxType)) {
            $term = get_term((int)$taxType, 'product_cat');
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $queryArgs['category'] = [$term->slug];
            }
        }

        try {
            $wooProducts = wc_get_products($queryArgs);
        } catch (\Throwable $e) {
            return [];
        }

        if (!$wooProducts) {
            return [];
        }

        $products = [];
        foreach ($wooProducts as $product) {
            if (!$product || !method_exists($product, 'get_id')) {
                continue;
            }

            $description = $product->get_short_description();
            if (!$description && method_exists($product, 'get_description')) {
                $description = wc_trim_string($product->get_description(), 400);
            }

            $products[] = [
                'id'                => $product->get_id(),
                'name'              => $product->get_name(),
                'short_description' => wp_kses_post($description),
                'price_html'        => ProductListRenderer::preparePriceHtml($product->get_price_html(), __('Free', 'fluent-crm')),
                'image'             => WooProduct::getImage($product),
                'permalink'         => $product->get_permalink() ?: '#',
            ];
        }

        return $products;
    }
}
