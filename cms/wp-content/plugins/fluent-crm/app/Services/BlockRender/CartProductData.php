<?php

namespace FluentCrm\App\Services\BlockRender;

class CartProductData
{
    /**
     * Query FluentCart products and return normalized product payloads.
     *
     * @param array $options
     * @param string $imageSize
     * @return array
     */
    public static function getProducts($options = [], $imageSize = 'large')
    {
        if (!defined('FLUENTCART_VERSION')) {
            return [];
        }

        $queryArgs = self::buildQueryArgs($options);
        $query = new \WP_Query($queryArgs);
        $posts = $query->posts;
        wp_reset_postdata();

        if (!$posts) {
            return [];
        }

        $postIds = wp_list_pluck($posts, 'ID');
        $detailMap = self::buildDetailMap($postIds);

        $products = [];
        foreach ($posts as $post) {
            $products[] = self::mapPostToProduct($post, $detailMap, $imageSize);
        }

        return $products;
    }

    /**
     * Get a single FluentCart product by ID.
     *
     * @param int $productId
     * @param string $imageSize
     * @return array|null
     */
    public static function getProductById($productId, $imageSize = 'large')
    {
        $productId = absint($productId);
        if (!$productId) {
            return null;
        }

        $products = self::getProducts([
            'productId' => $productId,
            'perPage'   => 1,
        ], $imageSize);

        return isset($products[0]) ? $products[0] : null;
    }

    /**
     * Build query args for FluentCart products.
     *
     * @param array $options
     * @return array
     */
    public static function buildQueryArgs($options = [])
    {
        $perPage = max(1, min(20, absint(isset($options['perPage']) ? $options['perPage'] : 3)));
        $order = strtolower((string)(isset($options['order']) ? $options['order'] : 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderByRaw = sanitize_key((string)(isset($options['orderBy']) ? $options['orderBy'] : 'date'));
        $productId = absint(isset($options['productId']) ? $options['productId'] : 0);
        $search = sanitize_text_field((string)(isset($options['search']) ? $options['search'] : ''));
        $taxType = sanitize_text_field((string)(isset($options['taxType']) ? $options['taxType'] : 'all'));

        $orderByMap = [
            'date'       => 'date',
            'modified'   => 'modified',
            'title'      => 'title',
            'menu_order' => 'menu_order',
            'rand'       => 'rand',
        ];
        $orderBy = isset($orderByMap[$orderByRaw]) ? $orderByMap[$orderByRaw] : 'date';

        $queryArgs = [
            'post_type'      => 'fluent-products',
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'orderby'        => $orderBy,
            'order'          => $order,
            'no_found_rows'  => true,
        ];

        if ($productId) {
            $queryArgs['post__in'] = [$productId];
            $queryArgs['posts_per_page'] = 1;
            $queryArgs['orderby'] = 'post__in';
        } elseif ($search !== '') {
            $queryArgs['s'] = $search;
        }

        if ($taxType && $taxType !== 'all' && is_numeric($taxType)) {
            $queryArgs['tax_query'] = [[
                'taxonomy' => 'product-categories',
                'field'    => 'term_id',
                'terms'    => [(int)$taxType],
            ]];
        }

        return $queryArgs;
    }

    private static function buildDetailMap($postIds)
    {
        $detailMap = [];
        if (!$postIds || !class_exists('\FluentCart\App\Models\ProductDetail')) {
            return $detailMap;
        }

        try {
            $details = \FluentCart\App\Models\ProductDetail::query()
                ->whereIn('post_id', $postIds)
                ->get();

            foreach ($details as $detail) {
                $detailMap[(int)$detail->post_id] = $detail;
            }
        } catch (\Throwable $e) {
            $detailMap = [];
        }

        return $detailMap;
    }

    private static function mapPostToProduct($post, $detailMap, $imageSize)
    {
        $postId = (int)$post->ID;
        $detail = isset($detailMap[$postId]) ? $detailMap[$postId] : null;

        $priceHtml = ($detail && isset($detail->formatted_min_price) && $detail->formatted_min_price)
            ? (string)$detail->formatted_min_price
            : __('Free', 'fluent-crm');
        $priceHtml = ProductListRenderer::preparePriceHtml($priceHtml, __('Free', 'fluent-crm'));

        $priceText = html_entity_decode(
            wp_strip_all_tags((string)$priceHtml),
            ENT_QUOTES,
            get_bloginfo('charset') ?: 'UTF-8'
        );
        $priceText = preg_replace('/\s+/', ' ', trim($priceText));
        if ($priceText === '') {
            $priceText = __('Free', 'fluent-crm');
        }

        $categories = wp_get_post_terms($postId, 'product-categories', ['fields' => 'names']);
        if (is_wp_error($categories) || !is_array($categories)) {
            $categories = [];
        }

        $title = get_the_title($postId);
        $label = '#' . $postId . ' - ' . $title;
        if ($priceText) {
            $label .= ' | ' . $priceText;
        }
        if ($categories) {
            $label .= ' | ' . implode(', ', $categories);
        }

        return [
            'id'                => $postId,
            'name'              => $title,
            'label'             => $label,
            'short_description' => wp_kses_post(get_the_excerpt($postId)),
            'price_html'        => $priceHtml,
            'price_text'        => $priceText,
            'categories'        => $categories,
            'image'             => get_the_post_thumbnail_url($postId, $imageSize) ?: '',
            'permalink'         => get_permalink($postId) ?: '#',
        ];
    }
}
