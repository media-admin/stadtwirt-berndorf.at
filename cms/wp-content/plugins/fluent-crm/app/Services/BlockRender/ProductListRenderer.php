<?php

namespace FluentCrm\App\Services\BlockRender;

class ProductListRenderer
{
    /**
     * Default block attributes for list-style product blocks.
     *
     * @param array $attrs
     * @return array
     */
    public static function mergeDefaultAttributes($attrs = [])
    {
        if (!is_array($attrs)) {
            $attrs = [];
        }

        return wp_parse_args($attrs, [
            'selectedLayout'       => 'default',
            'selectedPostsPerPage' => '3',
            'showDescription'      => true,
            'showImage'            => true,
            'showPrice'            => true,
            'showButton'           => true,
            'buttonText'           => 'Buy Now',
            'titleColor'           => '#2a363d',
            'descriptionColor'     => '',
            'priceColor'           => '#37454e',
            'buttonColor'          => '',
            'buttonBG'             => '#2a363d',
            'taxType'              => 'all',
            'order'                => 'desc',
            'orderBy'              => 'date',
        ]);
    }

    /**
     * Convert normalized product arrays into email-safe HTML.
     *
     * @param array $products
     * @param array $atts
     * @param string $tableClass
     * @return string
     */
    public static function renderProducts($products, $atts, $tableClass = 'fc_woo_products')
    {
        if (!is_array($products) || !$products) {
            return '';
        }

        $selectedLayout = self::normalizeLayout(isset($atts['selectedLayout']) ? $atts['selectedLayout'] : 'default');
        $productsHtml = '';

        foreach ($products as $product) {
            $productsHtml .= self::renderProduct($product, $atts, $selectedLayout);
        }

        if (!$productsHtml) {
            return '';
        }

        if ($selectedLayout === 'layout-2') {
            return $productsHtml;
        }

        return '<table class="' . esc_attr($tableClass) . '" border="0" cellpadding="0" cellspacing="0" width="100%"><tbody><tr><td class="template-' . esc_attr($selectedLayout) . '">' . $productsHtml . '</td></tr></tbody></table>';
    }

    public static function postProcessRendered($html, $data)
    {
        if (!is_string($html) || $html === '') {
            return '';
        }

        $attrs = isset($data['attrs']) && is_array($data['attrs']) ? $data['attrs'] : [];
        if (!self::isShowImageEnabled($attrs)) {
            $html = self::removeProductImagePlaceholders($html);
        }

        return $html;
    }

    private static function renderProduct($product, $atts, $layout)
    {
        $showImage = RenderValueHelper::normalizeBool(isset($atts['showImage']) ? $atts['showImage'] : true, true);
        $showDescription = RenderValueHelper::normalizeBool(isset($atts['showDescription']) ? $atts['showDescription'] : true, true);
        $showPrice = RenderValueHelper::normalizeBool(isset($atts['showPrice']) ? $atts['showPrice'] : true, true);
        $showButton = RenderValueHelper::normalizeBool(isset($atts['showButton']) ? $atts['showButton'] : true, true);

        $titleColor = trim((string)(isset($atts['titleColor']) ? $atts['titleColor'] : ''));
        $descriptionColor = trim((string)(isset($atts['descriptionColor']) ? $atts['descriptionColor'] : ''));
        $priceColor = trim((string)(isset($atts['priceColor']) ? $atts['priceColor'] : ''));
        $buttonColor = trim((string)(isset($atts['buttonColor']) ? $atts['buttonColor'] : ''));
        $buttonBg = trim((string)(isset($atts['buttonBG']) ? $atts['buttonBG'] : ''));

        $title = wp_kses_post(isset($product['name']) ? $product['name'] : '');
        $description = wp_kses_post(isset($product['short_description']) ? $product['short_description'] : '');
        $priceHtml = self::preparePriceHtml(
            isset($product['price_html']) ? $product['price_html'] : '',
            __('Free', 'fluent-crm')
        );
        $image = esc_url(isset($product['image']) ? $product['image'] : '');
        $permalink = esc_url(isset($product['permalink']) ? $product['permalink'] : '#');
        $buttonText = esc_html(isset($atts['buttonText']) ? $atts['buttonText'] : __('Buy Now', 'fluent-crm'));

        $titleStyle = $titleColor ? 'color:' . esc_attr($titleColor) . ';' : '';
        $descriptionStyle = $descriptionColor ? 'color:' . esc_attr($descriptionColor) . ';' : '';
        $priceStyle = $priceColor ? 'color:' . esc_attr($priceColor) . ';' : '';
        $buttonStyle = $buttonColor ? 'color:' . esc_attr($buttonColor) . ';' : '';

        if ($layout === 'layout-2' && $buttonBg) {
            $buttonStyle .= 'background:' . esc_attr($buttonBg) . ';';
        }

        if ($layout === 'layout-2') {
            return self::renderLayoutTwoProduct(
                $title,
                $description,
                $priceHtml,
                $image,
                $permalink,
                $buttonText,
                $titleStyle,
                $descriptionStyle,
                $priceStyle,
                $buttonStyle,
                $showImage,
                $showDescription,
                $showPrice,
                $showButton
            );
        }

        $contentHtml = '<div class="fc_woo_product_info"><div>';
        $contentHtml .= '<h2 class="title"><a style="' . esc_attr($titleStyle) . '" target="_blank" href="' . $permalink . '">' . $title . '</a></h2>';

        if ($showPrice) {
            $contentHtml .= '<span class="price" style="' . esc_attr($priceStyle) . '">' . $priceHtml . '</span>';
        }

        $contentHtml .= '</div>';

        if ($layout !== 'layout-3' && $showButton) {
            $contentHtml .= '<a href="' . $permalink . '" target="_blank" style="' . esc_attr($buttonStyle) . '" class="add-to-cart-btn">' . $buttonText . '</a>';
        }

        $contentHtml .= '</div>';

        $imageHtml = '';
        if ($showImage && $image) {
            $imageHtml = '<div class="fc_woo_product_img"><img src="' . $image . '" alt="' . esc_attr(wp_strip_all_tags($title)) . '"></div>';
        }

        return '<table class="fce_row fc_woo_product ' . esc_attr($layout) . '" border="0" cellpadding="0" cellspacing="0" width="40%" style="margin-bottom:35px;"><tbody><tr><td width="100%">' . $imageHtml . $contentHtml . '</td></tr></tbody></table>';
    }

    private static function renderLayoutTwoProduct($title, $description, $priceHtml, $image, $permalink, $buttonText, $titleStyle, $descriptionStyle, $priceStyle, $buttonStyle, $showImage, $showDescription, $showPrice, $showButton)
    {
        $hasImage = $showImage && $image;
        $imageCell = '';
        if ($hasImage) {
            $imageCell = '<td width="45%" class="fc_cart_product_img" style="background:url(\'' . $image . '\') center no-repeat;background-size:cover;"></td>';
        }

        $contentWidth = $hasImage ? '55%' : '100%';
        $contentPadding = $hasImage ? 'padding:10px 10px 10px 20px;' : 'padding:10px;';

        $contentHtml = '<div class="fc_woo_product_info"><div>';
        $contentHtml .= '<h2 class="title"><a style="' . esc_attr($titleStyle) . '" target="_blank" href="' . $permalink . '">' . $title . '</a></h2>';

        if ($showDescription) {
            $contentHtml .= '<div class="description" style="' . esc_attr($descriptionStyle) . '">' . $description . '</div>';
        }

        if ($showPrice) {
            $contentHtml .= '<span class="price" style="' . esc_attr($priceStyle) . '">' . $priceHtml . '</span>';
        }

        $contentHtml .= '</div>';

        if ($showButton) {
            $contentHtml .= '<a href="' . $permalink . '" target="_blank" style="' . esc_attr($buttonStyle) . '" class="add-to-cart-btn">' . $buttonText . '</a>';
        }

        $contentHtml .= '</div>';

        return '<table class="fce_row fc_woo_product layout-2" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:35px;"><tbody><tr>' . $imageCell . '<td width="' . esc_attr($contentWidth) . '" style="' . esc_attr($contentPadding) . '">' . $contentHtml . '</td></tr></tbody></table>';
    }

    private static function normalizeLayout($layout)
    {
        $layout = is_string($layout) ? trim($layout) : 'default';

        return in_array($layout, ['default', 'layout-2', 'layout-3'], true) ? $layout : 'default';
    }

    /**
     * Prepare product price markup for email/block-editor visual output.
     *
     * WooCommerce sale price HTML includes screen-reader helper spans. Remove
     * those snippet-visible labels while keeping visible sale prices readable.
     *
     * @param string $priceHtml Raw product price HTML.
     * @param string $fallback Fallback text when no price is available.
     * @return string
     */
    public static function preparePriceHtml($priceHtml, $fallback = '')
    {
        $priceHtml = wp_kses_post((string)$priceHtml);

        if ($priceHtml === '') {
            return esc_html($fallback);
        }

        $priceHtml = self::removeScreenReaderText($priceHtml);
        $priceHtml = self::removeAttributeFromTag($priceHtml, 'del', 'aria-hidden');
        $priceHtml = self::removeAttributeFromTag($priceHtml, 'ins', 'aria-hidden');
        $priceHtml = self::addInlineStyleToTag(
            $priceHtml,
            'del',
            'color:#9aa3aa;text-decoration:line-through;margin-right:8px;'
        );
        $priceHtml = self::addInlineStyleToTag(
            $priceHtml,
            'ins',
            'text-decoration:none;font-weight:600;'
        );

        return trim(wp_kses_post($priceHtml));
    }

    private static function removeScreenReaderText($html)
    {
        return preg_replace('/<span\b(?=[^>]*\bclass=(["\'])[^"\']*\bscreen-reader-text\b[^"\']*\1)[^>]*>.*?<\/span>/is', '', $html);
    }

    private static function addInlineStyleToTag($html, $tag, $style)
    {
        return preg_replace_callback('/<' . preg_quote($tag, '/') . '\b([^>]*)>/i', function ($matches) use ($tag, $style) {
            $attributes = $matches[1];

            if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attributes)) {
                $attributes = preg_replace_callback('/\sstyle=(["\'])(.*?)\1/i', function ($styleMatches) use ($style) {
                    if (strpos($styleMatches[2], rtrim($style, ';')) !== false) {
                        return $styleMatches[0];
                    }

                    return ' style=' . $styleMatches[1] . rtrim($styleMatches[2], ';') . ';' . $style . $styleMatches[1];
                }, $attributes, 1);

                return '<' . $tag . $attributes . '>';
            }

            return '<' . $tag . ' style="' . $style . '"' . $attributes . '>';
        }, $html);
    }

    private static function removeAttributeFromTag($html, $tag, $attribute)
    {
        return preg_replace_callback('/<' . preg_quote($tag, '/') . '\b([^>]*)>/i', function ($matches) use ($tag, $attribute) {
            $attributes = preg_replace('/\s' . preg_quote($attribute, '/') . '\s*=\s*(["\']).*?\1/i', '', $matches[1]);
            $attributes = preg_replace('/\s' . preg_quote($attribute, '/') . '\s*=\s*[^\s>]*/i', '', $attributes);

            return '<' . $tag . $attributes . '>';
        }, $html);
    }

    private static function removeProductImagePlaceholders($html)
    {
        // Fast path first; also helps as fallback when DOM extension is unavailable.
        $html = preg_replace('/<img\b[^>]*>/i', '', $html);
        $html = preg_replace('/<figure\b[^>]*>\s*<\/figure>/i', '', $html);
        $html = self::stripImageColumnsWithRegex($html);

        if (!class_exists('\DOMDocument') || trim($html) === '') {
            return $html;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="fc_products_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);

        $imageClassQueries = [
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' fc_woo_product_img ')]",
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' fc_cart_product_img ')]"
        ];

        foreach ($imageClassQueries as $query) {
            $nodes = $xpath->query($query);
            if (!$nodes) {
                continue;
            }

            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                self::removeNode($nodes->item($i));
            }
        }

        $imageCells = $xpath->query("//td[contains(translate(@style, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'url(')]");
        if ($imageCells) {
            for ($i = $imageCells->length - 1; $i >= 0; $i--) {
                $cell = $imageCells->item($i);
                if (self::isImageOnlyCell($cell)) {
                    self::removeNode($cell);
                }
            }
        }

        $rows = $xpath->query('//tr[count(td)=1]');
        if ($rows) {
            for ($i = 0; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $row->getElementsByTagName('td');
                if (!$cells || !$cells->item(0)) {
                    continue;
                }

                $cell = $cells->item(0);
                $cell->setAttribute('width', '100%');

                $style = self::normalizeCellStyle($cell->getAttribute('style'));
                if ($style) {
                    $cell->setAttribute('style', $style);
                } else {
                    $cell->removeAttribute('style');
                }
            }
        }

        $root = $dom->getElementById('fc_products_root');
        if (!$root) {
            return $html;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return self::stripImageColumnsWithRegex($output ?: $html);
    }

    private static function stripImageColumnsWithRegex($html)
    {
        $html = preg_replace(
            '/<div\b[^>]*class=(["\'])[^"\']*(?:fc_woo_product_img|fc_cart_product_img)[^"\']*\1[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        $html = preg_replace(
            '/<td\b[^>]*style=(["\'])[^"\']*url\([^)]+\)[^"\']*\1[^>]*>\s*<\/td>/is',
            '',
            $html
        );

        $html = preg_replace('/\bwidth=(["\'])55%\1/i', 'width="100%"', $html);
        $html = preg_replace('/\bpadding-left\s*:\s*[^;]+;?/i', '', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);

        return $html;
    }

    private static function normalizeCellStyle($style)
    {
        if (!$style) {
            return '';
        }

        $style = preg_replace('/\bbackground(?:-image)?\s*:\s*[^;]+;?/i', '', $style);
        $style = preg_replace('/\bbackground-size\s*:\s*[^;]+;?/i', '', $style);
        $style = preg_replace('/\bbackground-position\s*:\s*[^;]+;?/i', '', $style);
        $style = preg_replace('/\bbackground-repeat\s*:\s*[^;]+;?/i', '', $style);
        $style = preg_replace('/\bpadding-left\s*:\s*[^;]+;?/i', '', $style);
        $style = preg_replace('/\bwidth\s*:\s*(?:40%|45%|50%);?/i', '', $style);

        $style = preg_replace_callback(
            '/\bpadding\s*:\s*([0-9.]+[a-z%]+)\s+([0-9.]+[a-z%]+)\s+([0-9.]+[a-z%]+)\s+([0-9.]+[a-z%]+)\s*;?/i',
            function ($matches) {
                return 'padding:' . $matches[1] . ' ' . $matches[2] . ' ' . $matches[3] . ';';
            },
            $style
        );

        $style = preg_replace('/;\s*;/', ';', $style);

        return trim($style, " \t\n\r\0\x0B;");
    }

    private static function removeNode($node)
    {
        if ($node && $node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }

    private static function isImageOnlyCell($cell)
    {
        if (!$cell) {
            return false;
        }

        $text = trim(str_replace("\xc2\xa0", '', (string)$cell->textContent));
        if ($text !== '') {
            return false;
        }

        foreach ($cell->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (!in_array($tag, ['img', 'figure', 'picture', 'source', 'br'], true)) {
                return false;
            }
        }

        return true;
    }

    private static function isShowImageEnabled($attrs)
    {
        if (!array_key_exists('showImage', $attrs)) {
            return true;
        }

        return RenderValueHelper::normalizeBool($attrs['showImage'], true);
    }
}
