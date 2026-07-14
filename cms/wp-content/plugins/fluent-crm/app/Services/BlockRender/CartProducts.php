<?php

namespace FluentCrm\App\Services\BlockRender;

class CartProducts
{
    /**
     * Render FluentCart products list block for email body.
     *
     * @param string $content
     * @param array $data
     * @return string
     */
    public static function renderProducts($content, $data)
    {
        $customRenderer = apply_filters('fluent_crm/cart_products_renderer', null, $content, $data);
        if (is_callable($customRenderer)) {
            try {
                $rendered = call_user_func($customRenderer, $content, $data);
                return is_string($rendered) ? ProductListRenderer::postProcessRendered($rendered, $data) : '';
            } catch (\Throwable $e) {
                // Fall through to built-in renderer.
            }
        }

        if (!defined('FLUENTCART_VERSION')) {
            return '';
        }

        $attrs = isset($data['attrs']) && is_array($data['attrs']) ? $data['attrs'] : [];
        $atts = ProductListRenderer::mergeDefaultAttributes($attrs);
        $products = self::getFluentCartProducts($atts);

        if (!$products) {
            return '';
        }

        $html = ProductListRenderer::renderProducts($products, $atts, 'fc_woo_products fc_cart_products');

        return ProductListRenderer::postProcessRendered($html, $data);
    }

    private static function getFluentCartProducts($atts)
    {
        return CartProductData::getProducts([
            'perPage' => isset($atts['selectedPostsPerPage']) ? $atts['selectedPostsPerPage'] : 3,
            'order'   => isset($atts['order']) ? $atts['order'] : 'desc',
            'orderBy' => isset($atts['orderBy']) ? $atts['orderBy'] : 'date',
            'taxType' => isset($atts['taxType']) ? $atts['taxType'] : 'all',
        ], 'large');
    }
}
