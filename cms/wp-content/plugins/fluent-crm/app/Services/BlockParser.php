<?php

namespace FluentCrm\App\Services;

use FluentCrm\App\Services\BlockRender\CartProduct;
use FluentCrm\App\Services\BlockRender\CartProducts;
use FluentCrm\App\Services\BlockRender\WooProduct;
use FluentCrm\App\Services\BlockRender\WooProducts;
use FluentCrm\Framework\Support\Arr;

class BlockParser
{
    public function __construct($subscriber = null)
    {
        BlockParserHelper::setSubscriber($subscriber);

        if (!fluentCrmRunTimeCache('fluentcrm_block_parser_initiated')) {
            fluentCrmRunTimeCache('fluentcrm_block_parser_initiated', 'yes');
            add_filter('render_block', array($this, 'alterBlockContent'), 999, 2);
        }
    }

    public function parse($content)
    {
        try {
            $gutenParse = new \FluentCrm\App\Services\GutenbergEmailParser();
            $parsed = $gutenParse->parse($content);
        } catch (\Throwable $e) {
            $parsed = '';
        }

        $useFallback = (bool)apply_filters('fluent_crm/block_parser_legacy_fallback_enabled', true, $content, $parsed);
        if ($useFallback && $this->shouldFallbackToLegacy($content, $parsed)) {
            return $this->parseWithLegacyParser($content);
        }

        return $parsed;
    }

    private function shouldFallbackToLegacy($content, $parsed)
    {
        if (!is_string($content) || trim($content) === '') {
            return false;
        }

        if (is_string($parsed) && trim($parsed) !== '') {
            return false;
        }

        return strpos($content, '<!-- wp:') !== false;
    }

    private static $syncedPatternCache = [];

    private function renderSyncedPattern($data)
    {
        $ref = isset($data['attrs']['ref']) ? (int) $data['attrs']['ref'] : 0;
        if (!$ref) {
            return '';
        }

        if (!isset(self::$syncedPatternCache[$ref])) {
            $pattern = \FluentCrm\App\Models\Meta::where('object_type', 'email_pattern')
                ->where('id', $ref)
                ->first();

            self::$syncedPatternCache[$ref] = ($pattern && !empty($pattern->value['content']))
                ? $pattern->value['content']
                : '';
        }

        $content = self::$syncedPatternCache[$ref];
        if (!$content) {
            return '';
        }

        return $this->parseWithLegacyParser($content);
    }

    private function parseWithLegacyParser($content)
    {
        if (!function_exists('parse_blocks') || !function_exists('render_block')) {
            return (string)$content;
        }

        $blocks = parse_blocks((string)$content);
        if (empty($blocks)) {
            return (string)$content;
        }

        $output = '';
        foreach ($blocks as $block) {
            $block = $this->sanitizeBlock($block);
            $output .= render_block($block);
        }

        return $output;
    }

    private function sanitizeBlock($block)
    {
        if (!empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $index => $childBlock) {
                $block['innerBlocks'][$index] = $this->sanitizeBlock($childBlock);
            }
        }

        $blockName = $block['blockName'];

        if ($blockName == 'core/columns') {
            $blockCounts = count($block['innerBlocks']);
            $lastContentIndex = $blockCounts * 2;
            foreach ($block['innerBlocks'] as $blockIndex => $blockItem) {
                $block['innerBlocks'][$blockIndex]['fc_total_blocks'] = $blockCounts;
            }
            $block['innerContent'][0] = $this->getRowOpening($block);
            $block['innerContent'][$lastContentIndex] = $this->getRowClosing($block);
        } else if ($blockName == 'core/media-text') {
            $blockCounts = count($block['innerBlocks']);
            $lastContentIndex = $blockCounts * 2;
            $block['innerContent'][0] = $this->getMediaTextOpening($block);
            $block['innerContent'][$lastContentIndex] = $this->getMediaTextClosing($block);
        } else if ($blockName == 'core/buttons') {
            $blockCounts = count($block['innerBlocks']);
            $lastContentIndex = $blockCounts * 2;
            foreach ($block['innerBlocks'] as $blockIndex => $blockItem) {
                $block['innerBlocks'][$blockIndex]['fc_total_blocks'] = $blockCounts;
                $block['innerBlocks'][$blockIndex]['parent_attrs'] = $block['attrs'];
            }
            $block['innerContent'][0] = $this->getButtonsOpening($block);
            $block['innerContent'][$lastContentIndex] = $this->getButtonsClosing($block);
        } else if ($blockName == 'core/image') {
            $block['innerContent'][0] = $this->getImageBlockHtml($block);
        } else if ($blockName == 'core/latest-posts') {
            $block['blockName'] = 'fluent-crm/core-posts';
            $block['fc_total_blocks'] = 1;
        } else if ($blockName == 'fluentcrm/woo-product' || $blockName == 'fluent-crm/woo-product') {
            $block['innerContent'][0] = '';
            $block['innerContent'][2] = '';
            $block['fc_total_blocks'] = 1;
        } else if ($blockName == 'fluent-crm/latest-posts') {
            $block['innerContent'][0] = '';
            $block['innerContent'][2] = '';
            $block['fc_total_blocks'] = 1;
        } else if ($blockName == 'fluent-crm/woo-products') {
            $block['innerContent'][0] = '';
            $block['innerContent'][2] = '';
            $block['fc_total_blocks'] = 1;
        } else if ($blockName == 'fluent-crm/cart-products') {
            $block['innerContent'][0] = '';
            $block['innerContent'][2] = '';
            $block['fc_total_blocks'] = 1;
        } else if ($blockName == 'fluent-crm/cart-product') {
            $block['innerContent'][0] = '';
            $block['innerContent'][2] = '';
            $block['fc_total_blocks'] = 1;
        }

        return $block;
    }

    public function alterBlockContent($content, $data)
    {
        if (isset($data['blockName']) && $data['blockName'] === 'core/block') {
            return $this->renderSyncedPattern($data);
        }

        if (isset($data['blockName']) && in_array($data['blockName'], ['fluent-crm/conditional-content', 'fluentcrm/conditional-group'])) {
            return $this->renderConditionalBlock($content, $data);
        }

        if (empty($data['fc_total_blocks'])) {
            return $content;
        }

        $blockName = $data['blockName'];

        if ($blockName == 'core/column') {
            $content = $this->getColumnOpening($data) . $content . $this->getColumnClosing($data);
        } else if ($blockName == 'core/button') {
            $content = $this->getButtonWrapper($content, $data);
        } else if ($blockName == 'fluent-crm/core-posts') {
            $content = $this->renderLatestPosts($data);
        } else if ($blockName == 'fluentcrm/woo-product' || $blockName == 'fluent-crm/woo-product') {
            $content = WooProduct::renderProduct($content, $data);
        } else if ($blockName == 'fluent-crm/latest-posts') {

            $content = '';
            if (class_exists('\FluentCampaign\App\Services\PostParser\LatestPost')) {
                $content = \FluentCampaign\App\Services\PostParser\LatestPost::renderPosts($content, $data);
            }
        } else if ($blockName == 'fluent-crm/woo-products') {
            $content = WooProducts::renderProducts($content, $data);
        } else if ($blockName == 'fluent-crm/cart-products') {
            $content = CartProducts::renderProducts($content, $data);
        } else if ($blockName == 'fluent-crm/cart-product') {
            $content = CartProduct::renderProduct('', $data);
        }

        return $content;
    }

    private function getMediaTextOpening($block)
    {
        $backgroundColorClass = Arr::get($block, 'attrs.backgroundColor');
        $prevContent = $block['innerContent'][0];
        preg_match('/<figure (.*?)<\/figure>/s', $prevContent, $match);
        $figure = $match[0];
        $mediaWidth = Arr::get($block, 'attrs.mediaWidth', 50);
        $contentWidth = 100 - $mediaWidth;
        $MediaAlign = Arr::get($block, 'attrs.mediaPosition', 'left');
        $textAlign = ($MediaAlign == 'right') ? 'left' : 'right';

        $imageFill = Arr::get($block, 'attrs.imageFill') ? 'has_bg_image' : 'no_image_fill';

        $background = Arr::get($block, 'attrs.style.color.background');
        $extraCss = '';
        if (!$backgroundColorClass && $background) {
            $extraCss = 'background: ' . $background . ';background-color:' . $background . ';';
        }
        if ($backgroundColorClass) {
            $backgroundColorClass = 'has-' . Helper::kebabCase($backgroundColorClass) . '-background-color';
        }
        $html = '<table class="fce_row fc_row_media_text ' . $backgroundColorClass . '" border="0" cellpadding="0" cellspacing="0" width="100%" style="' . $extraCss . 'table-layout: fixed; border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;"><tbody><tr>';
        $html .= '<td><table class="fc_media_table" align="' . $MediaAlign . '" style="width: ' . $mediaWidth . '%;" border="0" cellpadding="0" cellspacing="0"><tbody><tr><td align="left" class="' . $imageFill . '" valign="middle">' . $figure . '</td></tr></tbody></table>';
        $html .= '<table class="fc_media_text" valign="middle" align="' . $textAlign . '" style="width: ' . $contentWidth . '%;" border="0" cellpadding="0" cellspacing="0"><tbody><tr><td style="padding: 30px 20px 10px;" align="left" valign="middle">';
        return $html;
    }

    private function getMediaTextClosing($block)
    {
        return '</td></tr></tbody></table></td></tr></tbody></table>';
    }

    private function getRowOpening($block)
    {
        $isStackOnMobile = Arr::get($block, 'attrs.isStackedOnMobile', true);

        $background = Arr::get($block, 'attrs.style.color.background');
        $defaultBackground = Arr::get($block, 'attrs.backgroundColor');

        $style = 'margin-bottom: 10px;';
        if ($background) {
            $style .= 'background-color:' . $background . ';';
        } else if ($defaultBackground) {
            $defaultBackground = 'has-' . Helper::kebabCase($defaultBackground) . '-background-color';
        }

        $class = 'fce_row';

        if ($isStackOnMobile) {
            $class = 'fce_row fce_stacked';
        }

        return '<table class="' . esc_attr($class) . ' ' . esc_attr($defaultBackground) . '" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%;' . $style . '"><tbody><tr>';
    }

    private function getRowClosing($block)
    {
        return '</tr></tbody></table>';
    }

    private function getColumnOpening($block)
    {

        $width = Arr::get($block, 'attrs.width');
        if (!$width) {
            $total = !empty($block['fc_total_blocks']) ? $block['fc_total_blocks'] : 1;
            $width = 100 / $total;
        }
        $vAlign = Arr::get($block, 'attrs.verticalAlignment', 'middle');
        return '<td align="center" valign="' . $vAlign . '" width="' . $width . '%" class="fce_column"><table border="0" cellpadding="10" cellspacing="0" width="100%"><tr><td class="fc_column_content">';
    }

    private function getColumnClosing($block)
    {
        return '</td></tr></table></td>';
    }

    private function getButtonsOpening($block)
    {
        $alignment = Arr::get($block, 'innerBlocks.0.attrs.align', '');
        $align = Arr::get($block, 'attrs.layout.justifyContent', 'left');
        $tableCssClass = 'fce_row fce_buttons_row';

        $tableCssClass .= ' tb_btn_' . $alignment;

        if ($definedWidth = Arr::get($block, 'innerBlocks.0.attrs.width')) {
            $tableCssClass .= ' wp-block-button__width-' . $definedWidth;
        }

        $btnCount = count(Arr::get($block, 'innerBlocks'));

        if ($btnCount > 1) {
            $tableCssClass .= ' fc_btn_multiple fc_btn_count_' . $btnCount;
        }

        $extraStyle = '';
        if ($spacings = Arr::get($block, 'attrs.style.spacing.margin', [])) {

            if (!empty($spacings['top'])) {
                $extraStyle .= 'margin-top:' . $spacings['top'] . ';';
            }

            if (!empty($spacings['bottom'])) {
                $extraStyle .= 'margin-bottom:' . $spacings['bottom'] . ';';
            }
        }

        return '<table valign="middle" align="' . $align . '" class="' . $tableCssClass . '" border="0" cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed; border-collapse: collapse;mso-table-lspace: 0pt;mso-table-rspace: 0pt;-ms-text-size-adjust: 100%;-webkit-text-size-adjust: 100%; float:none;' . $extraStyle . '"><tbody><tr>';
    }

    private function getButtonWrapper($content, $data)
    {
        $defaultClass = Arr::get($data, 'attrs.className', '');
        $backgroundColor = Arr::get($data, 'attrs.style.color.background');

        $typoTextTransform = Arr::get($data, 'attrs.style.typography.textTransform');
        $typoFontWeight = Arr::get($data, 'attrs.style.typography.fontWeight');
        $typoFontStyle = Arr::get($data, 'attrs.style.typography.fontStyle');

        $typography = '';
        if ($typoTextTransform) {
            $typography .= 'text-transform: ' . $typoTextTransform . ';';
        }
        if ($typoFontWeight) {
            $typography .= 'font-weight: ' . $typoFontWeight . ';';
        }
        if ($typoFontStyle) {
            $typography .= 'font-style: ' . $typoFontStyle . ';';
        }

        if (!$backgroundColor) {
            $bgClass = Arr::get($data, 'attrs.backgroundColor');
            $backgroundColor = Helper::getColorSchemeValue($bgClass);
        }

        $hasTextColor = Arr::get($data, 'attrs.style.color.text') || Arr::get($data, 'attrs.textColor');

        $btn_wrapper_class = $defaultClass . ' ';
        if (!$backgroundColor && strpos($defaultClass, 'is-style-outline') === false) {
            $btn_wrapper_class .= 'fc_d_btn_bg ';
            $backgroundColor = '#32373c';
        }

        if (!$hasTextColor) {
            $btn_wrapper_class .= 'fc_d_btn_color ';
        }

        if (strpos($defaultClass, 'is-style-outline') !== false) {
            $backgroundColor = Arr::get($data, 'attrs.style.color.background');
            if (!$backgroundColor) {
                $backgroundColor = 'white';
            }
        }

        $borderRadius = Arr::get($data, 'attrs.style.border.radius', '0px');

        $content = trim(preg_replace("/<\/?div[^>]*\>/i", "", $content));

        if (Arr::get($data, 'attrs.fontSize')) {
            $btn_wrapper_class .= ' has-' . Helper::kebabCase(Arr::get($data, 'attrs.fontSize')) . '-font-size ';
        }

        $td = '<td class="fc_btn ' . trim($btn_wrapper_class) . '" align="center" style="' . $typography . ' border-radius: ' . $borderRadius . ';" bgcolor="' . $backgroundColor . '" border-radius="30px">';

        $align = Arr::get($data, 'parent_attrs.parent_attrs.layout.justifyContent', 'center');

        $alignment = $align == 'center' ? 'text-align: -webkit-center;' : ' ';

        return '<td style="padding-right: 10px;' . $alignment . '" align="' . $align . '" valign="middle" class="fce_column"><table style="margin-bottom: 4px; margin-top: 4px;" border="0" cellspacing="0" cellpadding="0"><tr>' . $td . $content . '</td></tr></table></td>';
    }

    private function getButtonsClosing($block)
    {
        return '</tr></tbody></table>';
    }

    private function renderConditionalBlock($content, $data)
    {
        $checkType = $this->normalizeConditionalCheckType(Arr::get($data, 'attrs.condition_type', 'show_if_tag_exist'));

        // Login-state conditions do not require subscriber/tag context.
        if ($checkType === 'show_if_user_logged_in') {
            return is_user_logged_in() ? $content : '';
        }

        if ($checkType === 'show_if_user_not_logged_in') {
            return is_user_logged_in() ? '' : $content;
        }

        $subscriber = $this->getConditionalBlockSubscriber();

        if (!$subscriber) {
            return '';
        }

        $tagIds = Arr::get($data, 'attrs.tag_ids');
        if (!$tagIds) {
            return '';
        }

        $tagMatched = $subscriber->hasAnyTagId($tagIds);

        if ($checkType == 'show_if_tag_exist') {
            if ($tagMatched) {
                return $content;
            };
            return '';
        }

        if ($checkType == 'show_if_tag_not_exist') {
            if ($tagMatched) {
                return '';
            };
            return $content;
        }

        return '';
    }

    private function getConditionalBlockSubscriber()
    {
        $subscriber = BlockParserHelper::getSubscriber();

        // Frontend conditional blocks should resolve the active FluentCRM contact lazily.
        if (!$subscriber && function_exists('fluentcrm_get_current_contact')) {
            $subscriber = fluentcrm_get_current_contact();
        }

        if (!$subscriber) {
            /**
             * Filter the current subscriber while rendering the Conditional Block in FluentCRM.
             *
             * This filter allows you to modify the subscriber object used in the current block condition.
             *
             * @since 2.8.44
             *
             * @param object $subscriber The current subscriber object.
             * @return object The modified subscriber object.
             */
            $subscriber = apply_filters('fluent_crm/get_current_block_condition_subscriber', $subscriber);
        }

        return $subscriber;
    }

    /**
     * Keep backward compatibility with v2 conditional block values.
     */
    private function normalizeConditionalCheckType($checkType)
    {
        $checkType = trim((string)$checkType);
        if ($checkType === '') {
            return 'show_if_tag_exist';
        }

        $map = [
            // v2 legacy values
            'show_if_logged_in'       => 'show_if_user_logged_in',
            'show_if_public_users'    => 'show_if_user_not_logged_in',
            'show_if_tag_exists'      => 'show_if_tag_exist',
            'show_if_tag_not_exists'  => 'show_if_tag_not_exist',
        ];

        return Arr::get($map, $checkType, $checkType);
    }

    private function getImageBlockHtml($block)
    {
        $classNames = implode(' ', array_filter([
            Arr::get($block, 'attrs.className'),
            'wp-block-image size-' . Arr::get($block, 'attrs.sizeSlug'),
            'align' . Arr::get($block, 'attrs.align', 'left')
        ]));
        $radius = Arr::get($block, 'attrs.style.border.radius', '0px');
        $marginTop = $this->getSpacing('attrs.marginTop', $block);
        $marginBottom = $this->getSpacing('attrs.marginBottom', $block);
        $marginLeft = $this->getSpacing('attrs.marginLeft', $block);
        $marginRight = $this->getSpacing('attrs.marginRight', $block);

        $paddingTop = $this->getSpacing('attrs.paddingTop', $block);
        $paddingBottom = $this->getSpacing('attrs.paddingBottom', $block);
        $paddingLeft = $this->getSpacing('attrs.paddingLeft', $block);
        $paddingRight = $this->getSpacing('attrs.paddingRight', $block);

        $margin = '' . $marginTop . 'px ' . $marginRight . 'px ' . $marginBottom . 'px ' . $marginLeft . 'px';
        $padding = '' . $paddingTop . 'px ' . $paddingRight . 'px ' . $paddingBottom . 'px ' . $paddingLeft . 'px';


        $content = $block['innerContent'][0];
        $html = strip_tags($content, '<a><figcaption><img>');
        $html = str_replace(['<figcaption', 'figcaption/>'], ['<p', '/p>'], $html);
        $html = '<div class="' . $classNames . '" style="border-radius: ' . $radius . '; margin: ' . $margin . '; padding: ' . $padding . '">' . $html . '</div>';
        return $html;
    }

    private function renderLatestPosts($attributes)
    {
        return '';
    }

    private function getSpacing($key, $block)
    {
        $data = Arr::get($block, $key, '0');
        if (empty($data)) {
            $data = '0';
        }
        return $data;
    }
}
