<?php

namespace FluentCampaign\App\Http\Controllers;

use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\Framework\Request\Request;
use FluentCrm\Framework\Support\Arr;

class DynamicPostDataController extends Controller
{
    public function getPosts(Request $request)
    {

        add_filter('excerpt_more', function ($more) {
            return '...';
        }, 99999);

        add_filter('excerpt_length', function ($length) use ($request) {
            $excerptLength = '55';

            if ( $request->get('excerptLength') ) {
                $excerptLength = $request->get('excerptLength');
            }
            return $excerptLength;
        }, 99999);

        $args = [
            'post_type'      => $request->get('post_type'),
            'post_status'    => 'publish',
            'posts_per_page' => $request->get('per_page'),
            'orderby'       => $request->get('orderBy'),
            'order'          => $request->get('order')
        ];

        if (!empty($request->get('days')) && $request->get('days') !== '0') {
            $args['date_query'] = [
                [
                    'after' => $request->get('days') . ' days ago'
                ]
            ];
        }

        $taxTypes = $request->get('taxTypes');

        if ($taxTypes) {
            $tax_queries = [
                'relation' => $request->get('operator', 'OR')
            ];

            foreach ($taxTypes[0] as $taxonomy => $terms) {
                $termIds = array_filter(array_map(function($termName) use ($taxonomy) {
                    $term = get_term_by('name', $termName, $taxonomy);
                    return $term ? $term->term_id : null;
                }, $terms));

                if (!empty($termIds)) {
                    $tax_queries[] = [
                        'taxonomy' => $taxonomy,
                        'terms'    => $termIds
                    ];
                }
            }

            if (count($tax_queries) > 1) {
                $args['tax_query'] = $tax_queries;
            }
        }
        $posts = get_posts($args);

        $resultData = []; // Ensure resultData is initialized

        global $post;
        foreach ($posts as $post) {
            setup_postdata($post); // Ensures correct global $post context

            if (class_exists('\FLBuilderLoader')) {
                // Temporarily remove wpautop() to prevent <p> wrapping
                remove_filter('the_excerpt', 'wpautop');
                $post_excerpt = get_the_excerpt();
                add_filter('the_excerpt', 'wpautop'); // Restore after fetching excerpt
            } else {
                $post_excerpt = get_the_excerpt();
            }

            $result = [
                'post_title'    => $post->post_title,
                'post_excerpt'  => $post_excerpt,
                'comment_count' => (int) $post->comment_count,
                'date'          => get_the_time(get_option('date_format')),
                'thumbnail'     => get_the_post_thumbnail_url($post->ID),
                'author_avatar' => esc_url(get_avatar_url($post->post_author)),
                'author'        => get_the_author_meta('display_name', $post->post_author)
            ];

            $resultData[] = $result;
        }
        wp_reset_postdata(); // Resets global $post

        $data = [
            'posts'      => $resultData,
            'post_types' => $this->getPostTypes()
        ];

        return $data;
    }


    public function getPostTypes()
    {
        $args = [
            'public'       => true,
        ];
        $postTypes = get_post_types($args, 'objects');
        unset($postTypes['attachment']);

        $formattedPostTypes = [];

        foreach ($postTypes as $postTypeName => $postType) {
            $formattedPostTypes[] = [
                'value' => $postTypeName,
                'label' => $postType->label
            ];
        }
        return apply_filters('fluentcrm/latest_post_blocks_post_types', $formattedPostTypes);
    }

    public function getPostsTaxonomies()
    {
        $post_types = $this->getPostTypes();

        $output = [];
        foreach ($post_types as $key => $post_type) {
            $taxonomies = get_object_taxonomies($post_type['value'], 'objects');
            foreach ($taxonomies as $term_slug => $term) {
                if (!$term->public || !$term->show_ui) {
                    continue;
                }
                $terms = get_terms([
                    'taxonomy' => $term_slug,
                    'hide_empty' => false
                ]);
                $term_items = [
                    [
                        'value' => 'all',
                        'label' => 'all'
                    ]
                ];
                if (!empty($terms)) {
                    foreach ($terms as $term_key => $term_item) {
                        $term_items[] = [
                            'value' => $term_item->term_id,
                            'label' => $term_item->name,
                        ];
                    }
                    $output[$post_type['value']]['terms'][$term_slug] = $term_items;
                }
            }
        }

        $taxonomies = apply_filters('fluentcrm/latest_post_blocks_taxonomies', $output);
        return [
            'taxonomies' => $taxonomies
        ];
    }


    public function getProducts(Request $request)
    {
        $defaultArgs = [
            'status'    => 'publish',
            'limit'     => $request->get('per_page')
        ];

        if ($request->get('taxType') && $request->get('taxType') !== 'all') {
            $defaultArgs['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'terms'    => $request->get('taxType'),
                ],
            ];
        }

        $products = wc_get_products( $defaultArgs );

        $resultData = [];

        foreach ($products as $product) {

            $result = [
                'name'              => $product->get_title(),
                'price_html'        => wp_kses_post($product->get_price_html()),
                'product_link'      => $product->get_permalink(),
                'short_description' => wc_format_content(wp_kses_post($product->get_short_description() ? $product->get_short_description() : wc_trim_string($product->get_description(), 400))),
                'image'             => wp_get_attachment_image_url($product->get_image_id(), '')
            ];

            $resultData[] = $result;
        }

        return [
            'products'   => $resultData,
            'taxonomies' => $this->getProductTaxonomies()
        ];
    }

    public function getProductTaxonomies()
    {
        $post_types = [
            [
                'value' => 'product',
                'label' => 'Products'
            ]
        ];
        $output = [];
        foreach ($post_types as $key => $post_type) {
            $taxonomies = get_object_taxonomies($post_type['value'], 'objects');
//            $taxs = [];
            foreach ($taxonomies as $term_slug => $term) {
                if (!$term->public || !$term->show_ui) {
                    continue;
                }
//                $taxs[$term_slug] = $term;
                $terms = get_terms($term_slug);
                $term_items = [];
                if (!empty($terms)) {
                    foreach ($terms as $term_key => $term_item) {
                        $term_items[] = [
                            'value' => $term_item->term_id,
                            'label' => $term_item->name,
                        ];
                    }
                    $output[$post_type['value']]['terms'][$term_slug] = $term_items;
                }
            }
//            $output[$post_type['value']]['taxonomy'] = $taxs;
        }
        return apply_filters('fluent-crm/woo_product_blocks_taxonomies', $output);
    }

}
