<?php
/**
 * Schema.org Markup
 * 
 * @package MediaLab_SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Output Schema.org JSON-LD
 */
function medialab_seo_output_schema() {
    if (get_option('medialab_seo_enabled') !== '1') {
        return;
    }
    
    if (get_option('medialab_seo_schema_enabled') !== '1') {
        return;
    }
    
    $schema = medialab_seo_get_schema_data();
    
    if (empty($schema)) {
        return;
    }
    
    echo "\n<!-- Schema.org Markup by Media Lab SEO -->\n";
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n" . '</script>' . "\n";
}
add_action('wp_head', 'medialab_seo_output_schema', 5);

/**
 * Get Schema Data based on page type
 */
function medialab_seo_get_schema_data() {
    $schema = [];
    
    // Always add Organization
    $schema[] = medialab_seo_get_organization_schema();
    
    // Always add WebSite
    $schema[] = medialab_seo_get_website_schema();
    
    // Page-specific schema
    if (is_front_page()) {
        // Homepage - Organization is enough
    } elseif (is_single()) {
        if (get_post_type() === 'post') {
            $schema[] = medialab_seo_get_article_schema();
        } elseif (get_post_type() === 'product' && class_exists('WooCommerce')) {
            $schema[] = medialab_seo_get_product_schema();
        }
    } elseif (is_page()) {
        $schema[] = medialab_seo_get_webpage_schema();
    }
    
    // Add breadcrumbs if not homepage
    if (!is_front_page()) {
        $breadcrumb_schema = medialab_seo_get_breadcrumb_schema();
        if (!empty($breadcrumb_schema)) {
            $schema[] = $breadcrumb_schema;
        }
    }
    
    return [
        '@context' => 'https://schema.org',
        '@graph' => $schema
    ];
}

/**
 * Organization Schema
 */
function medialab_seo_get_organization_schema() {
    $site_name = get_option('medialab_seo_site_name', get_bloginfo('name'));
    
    $schema = [
        '@type' => 'Organization',
        '@id' => home_url('/#organization'),
        'name' => $site_name,
        'url' => home_url('/'),
    ];
    
    // Add logo if exists
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        if ($logo_url) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo_url
            ];
        }
    }
    
    return $schema;
}

/**
 * WebSite Schema
 */
function medialab_seo_get_website_schema() {
    return [
        '@type' => 'WebSite',
        '@id' => home_url('/#website'),
        'url' => home_url('/'),
        'name' => get_bloginfo('name'),
        'description' => get_bloginfo('description'),
        'publisher' => [
            '@id' => home_url('/#organization')
        ],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => home_url('/?s={search_term_string}')
            ],
            'query-input' => 'required name=search_term_string'
        ]
    ];
}

/**
 * Article Schema (Blog Posts)
 */
function medialab_seo_get_article_schema() {
    global $post;
    
    $schema = [
        '@type' => 'Article',
        '@id' => get_permalink() . '#article',
        'headline' => get_the_title(),
        'description' => get_the_excerpt(),
        'url' => get_permalink(),
        'datePublished' => get_the_date('c'),
        'dateModified' => get_the_modified_date('c'),
        'author' => [
            '@type' => 'Person',
            'name' => get_the_author(),
            'url' => get_author_posts_url(get_the_author_meta('ID'))
        ],
        'publisher' => [
            '@id' => home_url('/#organization')
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => get_permalink()
        ]
    ];
    
    // Add featured image
    if (has_post_thumbnail()) {
        $image_id = get_post_thumbnail_id();
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        $image_meta = wp_get_attachment_metadata($image_id);
        
        $schema['image'] = [
            '@type' => 'ImageObject',
            'url' => $image_url,
            'width' => $image_meta['width'] ?? 1200,
            'height' => $image_meta['height'] ?? 630
        ];
    }
    
    return $schema;
}

/**
 * WebPage Schema
 */
function medialab_seo_get_webpage_schema() {
    return [
        '@type' => 'WebPage',
        '@id' => get_permalink() . '#webpage',
        'url' => get_permalink(),
        'name' => get_the_title(),
        'description' => get_the_excerpt(),
        'isPartOf' => [
            '@id' => home_url('/#website')
        ],
        'datePublished' => get_the_date('c'),
        'dateModified' => get_the_modified_date('c')
    ];
}

/**
 * Product Schema (WooCommerce)
 */
function medialab_seo_get_product_schema() {
    if (!class_exists('WooCommerce')) {
        return [];
    }
    
    global $product;
    
    if (!$product) {
        return [];
    }
    
    // Ensure $product is a WC_Product object, not a string/ID
    if (!is_a($product, 'WC_Product')) {
        $product = wc_get_product(get_the_ID());
    }
    
    if (!$product || !is_a($product, 'WC_Product')) {
        return [];
    }
    
    $schema = [
        '@type' => 'Product',
        '@id' => get_permalink() . '#product',
        'name' => $product->get_name(),
        'description' => $product->get_short_description() ?: $product->get_description(),
        'url' => get_permalink(),
        'sku' => $product->get_sku(),
        'offers' => [
            '@type' => 'Offer',
            'price' => $product->get_price(),
            'priceCurrency' => get_woocommerce_currency(),
            'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => get_permalink()
        ]
    ];
    
    // Add image
    if (has_post_thumbnail()) {
        $image_url = wp_get_attachment_image_url(get_post_thumbnail_id(), 'full');
        $schema['image'] = $image_url;
    }
    
    // Add brand if available
    $brands = wp_get_post_terms(get_the_ID(), 'product_brand');
    if (!empty($brands) && !is_wp_error($brands)) {
        $schema['brand'] = [
            '@type' => 'Brand',
            'name' => $brands[0]->name
        ];
    }
    
    return $schema;
}

/**
 * Breadcrumb Schema
 */
function medialab_seo_get_breadcrumb_schema() {
    $breadcrumbs = medialab_seo_get_breadcrumbs_array();
    
    if (empty($breadcrumbs)) {
        return [];
    }
    
    $items = [];
    $position = 1;
    
    foreach ($breadcrumbs as $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $crumb['title'],
            'item' => $crumb['url']
        ];
    }
    
    return [
        '@type' => 'BreadcrumbList',
        '@id' => get_permalink() . '#breadcrumb',
        'itemListElement' => $items
    ];
}

/**
 * Get breadcrumbs as array
 */
function medialab_seo_get_breadcrumbs_array() {
    $breadcrumbs = [];
    
    // Home
    $breadcrumbs[] = [
        'title' => 'Home',
        'url' => home_url('/')
    ];
    
    if (is_single()) {
        // Post type archive
        $post_type = get_post_type();
        $post_type_obj = get_post_type_object($post_type);
        
        if ($post_type !== 'post' && $post_type_obj->has_archive) {
            $breadcrumbs[] = [
                'title' => $post_type_obj->labels->name,
                'url' => get_post_type_archive_link($post_type)
            ];
        } elseif ($post_type === 'post') {
            // Blog page
            if (get_option('page_for_posts')) {
                $breadcrumbs[] = [
                    'title' => get_the_title(get_option('page_for_posts')),
                    'url' => get_permalink(get_option('page_for_posts'))
                ];
            }
        }
        
        // Current post
        $breadcrumbs[] = [
            'title' => get_the_title(),
            'url' => get_permalink()
        ];
    } elseif (is_page()) {
        // Parent pages
        global $post;
        if ($post->post_parent) {
            $parent_ids = array_reverse(get_post_ancestors($post->ID));
            foreach ($parent_ids as $parent_id) {
                $breadcrumbs[] = [
                    'title' => get_the_title($parent_id),
                    'url' => get_permalink($parent_id)
                ];
            }
        }
        
        // Current page
        $breadcrumbs[] = [
            'title' => get_the_title(),
            'url' => get_permalink()
        ];
    } elseif (is_archive()) {
        $breadcrumbs[] = [
            'title' => get_the_archive_title(),
            'url' => get_permalink()
        ];
    }
    
    return $breadcrumbs;
}
