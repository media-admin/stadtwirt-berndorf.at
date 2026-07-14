<?php

namespace FluentCrm\App\Services\BlockRender;

use FluentCrm\Framework\Support\Arr;

class CartProduct
{
    private static $productsCache = [];

    public static function renderProduct($buttonHtml, $data)
    {
        if (!defined('FLUENTCART_VERSION')) {
            return '';
        }

        $defaultAtts = [
            'productId'       => null,
            'showImage'       => true,
            'showDescription' => true,
            'showPrice'       => true,
            'showButton'      => true,
            'buttonText'      => __('Buy Now', 'fluent-crm'),
            'backgroundColor' => '#fffeeb',
            'contentColor'    => '',
            'pricingColor'    => '',
            'template'        => 'left',
        ];

        $attrs = isset($data['attrs']) && is_array($data['attrs']) ? $data['attrs'] : [];
        $atts = wp_parse_args($attrs, $defaultAtts);
        $productId = absint($atts['productId']);
        if (!$productId) {
            return '';
        }

        $product = self::getProductData($productId);
        if (!$product) {
            return '';
        }

        $template = self::normalizeTemplate(Arr::get($atts, 'template', 'left'));
        $showImage = RenderValueHelper::normalizeBool(Arr::get($atts, 'showImage'), true);
        $showDescription = RenderValueHelper::normalizeBool(Arr::get($atts, 'showDescription'), true);
        $showPrice = RenderValueHelper::normalizeBool(Arr::get($atts, 'showPrice'), true);
        $showButton = RenderValueHelper::normalizeBool(Arr::get($atts, 'showButton'), true);

        $tableStyle = 'border-radius: 5px;';
        $contentColorStyle = '';

        if ($bgColor = Arr::get($atts, 'backgroundColor')) {
            $tableStyle .= 'background-color:' . sanitize_text_field($bgColor) . ';';
        }

        if ($contentColor = Arr::get($atts, 'contentColor')) {
            $safeContentColor = sanitize_text_field($contentColor);
            $tableStyle .= 'color:' . $safeContentColor . ';';
            $contentColorStyle = 'color:' . $safeContentColor . ';';
        }

        $priceStyle = '';
        if ($pricingColor = Arr::get($atts, 'pricingColor')) {
            $priceStyle = 'color:' . sanitize_text_field($pricingColor) . ';';
        }

        $contentHtml = sprintf(
            '<h2 style="%1$smargin:0;"><a href="%2$s" target="_blank" style="%1$stext-decoration:none;">%3$s</a></h2>',
            $contentColorStyle,
            esc_url($product['permalink']),
            wp_kses_post($product['name'])
        );

        if ($showDescription && !empty($product['short_description'])) {
            $contentHtml .= sprintf(
                '<div style="%1$smargin-bottom:5px;">%2$s</div>',
                $contentColorStyle,
                wp_kses_post($product['short_description'])
            );
        }

        if ($showPrice) {
            $contentHtml .= sprintf(
                '<div style="%1$smargin:5px 0;">%2$s</div>',
                $priceStyle,
                ProductListRenderer::preparePriceHtml($product['price_html'], __('Free', 'fluent-crm'))
            );
        }

        $image = '';
        if ($showImage && $template !== 'none') {
            $customImage = trim((string)Arr::get($atts, 'customImage'));
            $image = $customImage ? esc_url_raw($customImage) : $product['image'];
        }

        if (!$image) {
            $template = 'none';
        }

        if ($showButton) {
            $fallbackButton = '<a href="' . esc_url($product['permalink']) . '" style="text-decoration:none;display:inline-block;padding:8px 18px;border-radius:4px;background:#2a363d;color:#ffffff;">' . esc_html(Arr::get($atts, 'buttonText', __('Buy Now', 'fluent-crm'))) . '</a>';
            $contentHtml .= '<div style="margin-top:10px;margin-bottom:10px;">' . ($buttonHtml ?: $fallbackButton) . '</div>';
        }

        $contentStyle = '';
        if ($template === 'none' || $template === 'top') {
            $contentStyle = 'text-align:center;';
        }

        $imageTd = '';
        if ($image) {
            if ($template === 'top') {
                $contentHtml = '<img src="' . esc_url($image) . '" width="auto" alt="' . esc_attr(wp_strip_all_tags($product['name'])) . '" style="border:0;height:auto;outline:none;text-decoration:none;max-width:100%;-ms-interpolation-mode:bicubic;display:block;margin:0 auto 15px;">' . $contentHtml;
            } elseif ($template === 'left') {
                $imageContent = '<img src="' . esc_url($image) . '" width="auto" alt="' . esc_attr(wp_strip_all_tags($product['name'])) . '" style="border:0;height:auto;outline:none;text-decoration:none;max-width:100%;-ms-interpolation-mode:bicubic;display:block;margin:0 auto;">';
                $imageTd = self::getContentTd($imageContent, $template);
            }
        }

        ob_start();
        ?>
        <table class="fce_row" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout:fixed;border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;margin-bottom:20px;margin-top:20px;<?php echo $tableStyle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><tbody><tr>
            <?php
            if ($imageTd) {
                echo $imageTd; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo self::getContentTd($contentHtml, $template, $contentStyle); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </tr></tbody></table>
        <?php

        return ob_get_clean();
    }

    private static function getProductData($productId)
    {
        if (isset(self::$productsCache[$productId])) {
            return self::$productsCache[$productId];
        }
        self::$productsCache[$productId] = CartProductData::getProductById($productId, 'large');

        return self::$productsCache[$productId];
    }

    private static function getContentTd($contentHtml, $template, $extraStyle = '')
    {
        $width = ($template === 'left') ? '50' : '100';

        return '<td align="center" valign="middle" width="' . $width . '%" class="fce_column"><table border="0" cellpadding="10" cellspacing="0" width="100%"><tr><td class="fc_column_content" style="padding:10px;' . $extraStyle . '">' . $contentHtml . '</td></tr></table></td>';
    }

    private static function normalizeTemplate($template)
    {
        $template = is_string($template) ? trim($template) : 'left';
        return in_array($template, ['left', 'top', 'none'], true) ? $template : 'left';
    }

}
