<?php

namespace FluentCrm\App\Services;
/**
 * Gutenberg Block Parser for Email
 * Converts Gutenberg blocks to email-compatible HTML
 */

use FluentCrm\App\Services\BlockRender\BlockEditorHelper;
use FluentCrm\App\Services\BlockRender\CartProduct;
use FluentCrm\App\Services\BlockRender\CartProducts;
use FluentCrm\App\Services\BlockRender\WooProduct;
use FluentCrm\App\Services\BlockRender\WooProducts;
use FluentCrm\Framework\Support\Arr;

class GutenbergEmailParser
{

    private $inlineStyles = [];

    private $childCss = '';

    private static $rssRenderCache = [];

    private $autoPaddedElements = [
        'core/heading',
        'core/paragraph',
        'core/list'
    ];

    /**
     * Parse Gutenberg blocks and convert to email HTML
     *
     * @param string $content The post content with Gutenberg blocks
     * @return string Email-compatible HTML
     */
    public function parse($content)
    {
        $this->inlineStyles = [];
        $this->childCss = '';

        // Parse blocks using WordPress function if available
        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($content);
        } else {
            // Fallback: use custom parser
            $blocks = $this->parseBlocksManually($content);
        }

        $blockHtmls = $this->renderBlocks($blocks);

        $css = $this->generateInlineStyles();

        $css = BlockEditorHelper::replaceStyleSlugsWithValues($css);

        return $css . $blockHtmls;
    }


    private function collectInlineStyles($id, $attrs, $blockName = '')
    {
        if (!$attrs) {
            return;
        }

        $idSelector = '#' . $id;

        $styleAttr = [];

        // Handling the variable css attribuytes
        if ($fontSize = Arr::get($attrs, 'fontSize')) {
            $styleAttr['font-size'] = $this->resolveFontSizeValue($fontSize);
        }

        // Some blocks (notably list) may only store preset font size as className.
        if (empty($styleAttr['font-size']) && ($className = Arr::get($attrs, 'className'))) {
            if (preg_match('/has-([a-z0-9-]+)-font-size/i', (string)$className, $matches)) {
                $styleAttr['font-size'] = $this->resolveFontSizeValue($matches[1]);
            }
        }

        if ($height = Arr::get($attrs, 'height')) {
            $styleAttr['height'] = $height;
        }

        if ($textColor = Arr::get($attrs, 'textColor')) {
            $styleAttr['color'] = 'var(--fcom--color--' . $textColor . ')';
        }

        if ($backgroundColor = Arr::get($attrs, 'backgroundColor')) {
            $colorValue = 'var(--fcom--color--' . $backgroundColor . ')';
            if ($blockName === 'core/separator') {
                $styleAttr['color'] = $colorValue;
            } else {
                $styleAttr['background-color'] = $colorValue;
            }
        }

        $textAlign = Arr::get($attrs, 'align');
        if ($blockName === 'core/button') {
            $textAlign = Arr::get($attrs, 'textAlign');
        }

        if ($textAlign) {
            $styleAttr['text-align'] = $this->getDirectionalTextAlign($textAlign);
        }

        $className = (string)Arr::get($attrs, 'className', '');
        if (preg_match('/has-([a-z0-9-]+)-font-family/i', $className, $fontFamilyMatch)) {
            $resolvedFontFamily = $this->resolveFontFamilyValue($fontFamilyMatch[1]);
            if ($resolvedFontFamily) {
                $styleAttr['font-family'] = $resolvedFontFamily;
            }
        }
        if (empty($styleAttr['font-family']) && ($fontFamily = Arr::get($attrs, 'fontFamily'))) {
            $resolvedFontFamily = $this->resolveFontFamilyValue($fontFamily);
            if ($resolvedFontFamily) {
                $styleAttr['font-family'] = $resolvedFontFamily;
            }
        }

        $style = Arr::get($attrs, 'style', []);

        $childCss = '';
        $hadPadding = false;

        if ($style && is_array($style)) {
            if ($linkColor = Arr::get($style, 'elements.link.color.text')) {
                $childCss .= $idSelector . ' a { color: ' . $this->transformToCssVar($linkColor) . '; }';
            }

            if ($color = Arr::get($style, 'color.text')) {
                $styleAttr['color'] = $color;
            }

            if ($bgColor = Arr::get($style, 'color.background')) {
                if ($blockName === 'core/separator') {
                    $styleAttr['color'] = $bgColor;
                } else {
                    $styleAttr['background-color'] = $bgColor;
                }
            }

            if ($paddings = Arr::get($style, 'spacing.padding', [])) {
                foreach ($paddings as $paddingType => $padding) {
                    if (!$padding) {
                        continue;
                    }
                    $hadPadding = true;
                    $styleAttr['padding-' . $paddingType] = $this->transformToCssVar($padding);
                }
            }

            if ($margins = Arr::get($style, 'spacing.margin', [])) {
                foreach ($margins as $marginType => $margin) {
                    if (!$margin) {
                        continue;
                    }
                    $styleAttr['margin-' . $marginType] = $this->transformToCssVar($margin);
                }
            }

            if ($borderConfig = Arr::get($style, 'border', [])) {
                $width = Arr::get($borderConfig, 'width', '0px');
                $color = Arr::get($borderConfig, 'color', '');

                if (!$color) {
                    if ($borderColor = Arr::get($attrs, 'borderColor', '')) {
                        $color = 'var(--fcom--color--' . $borderColor . ')';
                    }
                }

                $borderStyle = Arr::get($borderConfig, 'style', 'solid');

                if ($width || $color) {
                    $styleAttr['border'] = trim($width . ' ' . $borderStyle . ' ' . $color);
                }

                foreach (['top', 'right', 'bottom', 'left'] as $borderSide) {
                    $sideConfig = Arr::get($borderConfig, $borderSide, []);
                    if (!$sideConfig || !is_array($sideConfig)) {
                        continue;
                    }

                    $sideWidth = $this->normalizeCssSize(Arr::get($sideConfig, 'width', ''));
                    $sideStyle = Arr::get($sideConfig, 'style', 'solid');
                    $sideColor = Arr::get($sideConfig, 'color', '');

                    if ($sideColor && strpos($sideColor, 'var:') === 0) {
                        $sideColor = $this->transformToCssVar($sideColor);
                    } elseif ($sideColor && strpos($sideColor, '#') !== 0 && strpos($sideColor, 'rgb') !== 0 && strpos($sideColor, 'hsl') !== 0 && strpos($sideColor, 'var(') !== 0) {
                        $sideColor = 'var(--fcom--color--' . $sideColor . ')';
                    }

                    if (!$sideWidth && !$sideColor) {
                        continue;
                    }

                    $styleAttr['border-' . $borderSide] = trim($sideWidth . ' ' . $sideStyle . ' ' . $sideColor);
                }

                $borderRadius = Arr::get($borderConfig, 'radius', []);

                if (is_string($borderRadius) || is_numeric($borderRadius)) {
                    $styleAttr['border-radius'] = $this->normalizeCssSize($borderRadius);
                } elseif ($borderRadius) {
                    $radiusTypeMap = [
                        'topLeft'     => 'top-left',
                        'topRight'    => 'top-right',
                        'bottomLeft'  => 'bottom-left',
                        'bottomRight' => 'bottom-right'
                    ];

                    foreach ($borderRadius as $radiusType => $radius) {
                        if (!$radius || !isset($radiusTypeMap[$radiusType])) {
                            continue;
                        }
                        $styleAttr['border-' . $radiusTypeMap[$radiusType] . '-radius'] = $radius;
                    }
                }
            }

            if ($minHeight = Arr::get($style, 'dimensions.minHeight')) {
                $styleAttr['min-height'] = $this->normalizeCssSize($minHeight);
            }

            $typoGraphis = Arr::get($style, 'typography', []);

            foreach ($typoGraphis as $typographyType => $typoValue) {
                if (!$typoValue || !is_string($typoValue)) {
                    continue;
                }

                $cssProperty = $this->camelCaseToKebabCase($typographyType);

                // Normalize font-size slugs/tokens to a valid CSS size.
                if ($cssProperty === 'font-size') {
                    if (strpos($typoValue, 'var:') === 0) {
                        $typoValue = $this->transformToCssVar($typoValue);
                    }
                    $typoValue = $this->resolveFontSizeValue($typoValue);
                } else if ($cssProperty === 'font-family') {
                    $typoValue = $this->resolveFontFamilyValue($typoValue);
                } else if (strpos($typoValue, 'var:') === 0) {
                    // Convert Gutenberg var:preset|... tokens to CSS vars.
                    $typoValue = $this->transformToCssVar($typoValue);
                }

                $styleAttr[$cssProperty] = $typoValue;
            }
        }

        if ($blockName === 'core/list') {
            if (!empty($styleAttr['font-size'])) {
                $size = $styleAttr['font-size'];
                $childCss .= $idSelector . ' li, ' . $idSelector . ' li p { font-size: ' . $size . '; }';
            }
            if (!empty($styleAttr['line-height'])) {
                $lineHeight = $styleAttr['line-height'];
                $childCss .= $idSelector . ' li, ' . $idSelector . ' li p { line-height: ' . $lineHeight . '; }';
            }
        } else if ($blockName === 'core/button') {
            $className = (string)Arr::get($attrs, 'className', '');
            $isOutline = strpos($className, 'is-style-outline') !== false;

            // Keep button text centered by default unless explicitly set.
            if (empty($styleAttr['text-align'])) {
                $styleAttr['text-align'] = 'center';
            }

            if (empty($styleAttr['line-height'])) {
                $styleAttr['line-height'] = '1.5';
            }

            if ($isOutline) {
                $backgroundColor = Arr::get($styleAttr, 'background-color');
                $hasExplicitBackground = !empty($backgroundColor);

                if (!$hasExplicitBackground) {
                    $styleAttr['background-color'] = 'transparent';
                }

                if (empty($styleAttr['border'])) {
                    $borderWidth = Arr::get($attrs, 'style.border.width', '2px');
                    $borderStyle = Arr::get($attrs, 'style.border.style', 'solid');
                    $borderColor = Arr::get($attrs, 'style.border.color', 'currentColor');

                    if (is_numeric($borderWidth)) {
                        $borderWidth .= 'px';
                    }
                    if (is_string($borderColor) && strpos($borderColor, 'var:') === 0) {
                        $borderColor = $this->transformToCssVar($borderColor);
                    }

                    $styleAttr['border'] = trim($borderWidth . ' ' . $borderStyle . ' ' . $borderColor);
                }
            }
        } else if ($blockName === 'core/buttons') {
            $justifyContent = Arr::get($attrs, 'layout.justifyContent', 'left');
            $alignMap = [
                'left'          => 'left',
                'center'        => 'center',
                'right'         => 'right',
                'space-between' => 'start',
                'space-around'  => 'center',
                'space-evenly'  => 'center'
            ];

            if (isset($alignMap[(string)$justifyContent])) {
                $styleAttr['text-align'] = $this->getDirectionalTextAlign($alignMap[(string)$justifyContent]);
            } else if (empty($styleAttr['text-align'])) {
                $styleAttr['text-align'] = $this->getDirectionalTextAlign('left');
            }
        }

        if (!empty($styleAttr['background-color']) && !$hadPadding && in_array($blockName, $this->autoPaddedElements)) {
            $styleAttr['padding'] = '20px';
        }

        $this->inlineStyles[$idSelector] = $styleAttr;
        $this->childCss .= $childCss . ' ';
    }

    private function setDefaultBlockStyles($id, $styles = [])
    {
        $idSelector = '#' . $id;

        if (!isset($this->inlineStyles[$idSelector])) {
            $this->inlineStyles[$idSelector] = $styles;
            return;
        }

        foreach ($styles as $property => $value) {
            if (!isset($this->inlineStyles[$idSelector][$property])) {
                $this->inlineStyles[$idSelector][$property] = $value;
            }
        }

        return;
    }

    private function getDirectionalTextAlign($align, $default = 'left')
    {
        $align = strtolower(trim((string)$align));
        if ($align === '') {
            $align = strtolower((string)$default);
        }

        if ($align === 'start') {
            $align = 'left';
        } else if ($align === 'end') {
            $align = 'right';
        }

        if (!in_array($align, ['left', 'center', 'right', 'justify'], true)) {
            $align = strtolower((string)$default);
        }

        if (fluentcrm_is_rtl()) {
            if ($align === 'left') {
                return 'right';
            }

            if ($align === 'right') {
                return 'left';
            }
        }

        return $align;
    }

    private function camelCaseToKebabCase($string)
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }

    private function transformToCssVar($cssProperty)
    {
        if (strpos($cssProperty, 'var:') === 0) {
            $parts = explode('|', substr($cssProperty, 4));
            if (count($parts) === 3) {
                return 'var(--wp--' . str_replace('|', '--', $parts[0] . '--' . $parts[1] . '--' . $parts[2]) . ')';
            }
        }

        return $cssProperty;
    }

    private function normalizeCssSize($value)
    {
        if ($value === null || $value === '') {
            return '';
        }

        $value = trim((string)$value);

        if (strpos($value, 'var:') === 0) {
            return $this->transformToCssVar($value);
        }

        return $value;
    }

    private function normalizeHtmlWidthAttribute($width)
    {
        $width = trim((string)$width);

        if ($width === '') {
            return '';
        }

        if (substr($width, -2) === 'px') {
            return preg_replace('/[^0-9.]/', '', $width);
        }

        return $width;
    }

    private function generateInlineStyles()
    {

        $css = '<style type="text/css">';

        foreach ($this->inlineStyles as $selector => $styles) {
            $css .= $selector . ' { ';
            foreach ($styles as $property => $value) {
                $css .= $property . ': ' . $value . '; ';
            }
            $css .= '} ';
        }

        if ($this->childCss) {
            $css .= $this->childCss;
        }

        $css .= '</style>';

        return $css;
    }

    /**
     * Manual block parser (fallback if parse_blocks not available)
     */
    private function parseBlocksManually($content)
    {
        $blocks = [];
        $pattern = '/<!--\s+wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(\{.*?\})?\s+(\/)?-->/';

        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        $lastOffset = 0;
        foreach ($matches[0] as $index => $match) {
            $blockName = ($matches[1][$index][0] ?? '') . $matches[2][$index][0];
            $attrs = $matches[3][$index][0] ?? '{}';
            $isSelfClosing = !empty($matches[4][$index][0]);

            $blockStart = $match[1] + strlen($match[0]);

            // Find closing tag if not self-closing
            if (!$isSelfClosing) {
                $closingPattern = '/<!--\s+\/wp:' . preg_quote($blockName, '/') . '\s+-->/';
                if (preg_match($closingPattern, $content, $closeMatch, PREG_OFFSET_CAPTURE, $blockStart)) {
                    $innerHTML = substr($content, $blockStart, $closeMatch[0][1] - $blockStart);
                    $lastOffset = $closeMatch[0][1] + strlen($closeMatch[0][0]);
                } else {
                    $innerHTML = '';
                }
            } else {
                $innerHTML = '';
            }

            $blocks[] = [
                'blockName'   => $blockName,
                'attrs'       => json_decode($attrs, true) ?? [],
                'innerHTML'   => trim($innerHTML),
                'innerBlocks' => []
            ];
        }

        return $blocks;
    }

    /**
     * Render blocks to email HTML
     */
    private function renderBlocks($blocks, $nested = false)
    {
        $html = '';

        foreach ($blocks as $block) {
            if (empty($block['blockName'])) {
                // Classic content or unrecognized block
                if (!empty($block['innerHTML'])) {
                    $html .= $this->wrapInTable($block['innerHTML']);
                }
                continue;
            }

            $html .= $this->renderBlock($block, $nested);
        }

        return $html;
    }

    /**
     * Render individual block
     */
    private function renderBlock($block, $isNested = false)
    {
        $isInvisible = isset($block['attrs']['metadata']['blockVisibility']) && $block['attrs']['metadata']['blockVisibility'] === false;
        if ($isInvisible) {
            return '';
        }

        $blockName = $block['blockName'];
        $attrs = $block['attrs'] ?? [];
        $innerHTML = $block['innerHTML'] ?? '';
        $innerBlocks = $block['innerBlocks'] ?? [];

        // Per-block conditional visibility (conditional-content has its own handler)
        if ($blockName !== 'fluent-crm/conditional-content') {
            if (!$this->checkBlockConditionVisibility($attrs)) {
                return '';
            }
        }

        // create an unique element ID for blocks that don't have one, to help with styling if needed
        $elementId = uniqid('block-', false);
        $this->collectInlineStyles($elementId, $attrs, $blockName);

        $attrs['elem_id'] = $elementId;
        $attrs['is_root'] = !$isNested;

        // For blocks with innerContent array, reconstruct innerHTML
        if (empty($innerHTML) && !empty($block['innerContent'])) {
            $innerHTML = implode('', array_filter($block['innerContent'], 'is_string'));
        }

        // Handle different block types
        switch ($blockName) {
            case 'core/block':
                return $this->renderSyncedPattern($attrs, $isNested);

            case 'core/paragraph': // done
                return $this->renderParagraph($innerHTML, $attrs);

            case 'core/heading': // done
                return $this->renderHeading($innerHTML, $attrs);

            case 'core/image': // done
                return $this->renderImage($attrs, $innerHTML);

            case 'core/list': // done
                return $this->renderList($innerHTML, $innerBlocks, $attrs);

            case 'core/list-item': // done
                return $this->renderListItem($innerHTML, $attrs);

            case 'core/quote': // done
                return $this->renderQuote($innerHTML, $innerBlocks, $attrs);

            case 'core/button': // done
                return $this->renderButton($innerHTML, $attrs);

            case 'core/buttons': // done
                return $this->renderButtons($innerBlocks, $attrs);

            case 'core/columns': // done
                return $this->renderColumns($innerBlocks, $attrs);

            case 'core/column': // done
                return $this->renderColumn($innerBlocks, $attrs, $innerHTML);

            case 'core/separator': // partially done
                return $this->renderSeparator($innerHTML, $attrs);

            case 'core/spacer': // done
                return $this->renderSpacer($innerHTML, $attrs);

            case 'core/group': // done
                return $this->renderGroup($innerBlocks, $attrs, $innerHTML);

            case 'core/row':
                return $this->renderRow($innerBlocks, $attrs, $innerHTML);

            case 'core/table': // done
                return $this->renderTable($innerHTML, $attrs);

            case 'core/rss': // done
                return $this->renderRss($attrs);

            case 'fluentcrm/woo-product':
            case 'fluent-crm/woo-product':
                return $this->renderWooProductBlock($block, $attrs, $innerHTML);

            case 'fluent-crm/cart-product':
                return $this->renderCartProductBlock($block, $attrs, $innerHTML);

            case 'fluent-crm/latest-posts': // partially done
                return $this->renderLatestPostsBlock($block, $attrs);

            case 'fluent-crm/woo-products': // partially done
                return $this->renderProductsBlock($block, $attrs, $blockName);

            case 'fluent-crm/cart-products': // partially done
                return $this->renderCartProductsBlock($block, $attrs);

            case 'fluent-crm/conditional-content': // done
            case 'fluentcrm/conditional-group': // done
                return $this->renderConditionalGroupBlock($innerBlocks, $attrs, $innerHTML);

            case 'core/freeform': // done
            case 'core/html': // done
                // Classic editor content - render as-is with email-safe wrapper
                return $this->wrapInTable($innerHTML, $attrs);

            case 'core/preformatted': // done
            case 'core/code': // done
            case 'core/verse': // done
                return $this->renderCodeBlock($innerHTML, $attrs);
            case 'core/pullquote': // done
                return $this->renderPullQuote($innerHTML, $attrs);
            case 'core/embed':
            case 'core/video':
            case 'core/audio':
                // For video/audio embeds in email, show a linked thumbnail or text
                $url = $attrs['url'] ?? '';
                if (!empty($url)) {
                    $linkText = __('Click here to view media content', 'fluent-crm');
                    return $this->wrapInTable("<p style=\"text-align: center; padding: 20px; background: #f0f0f0;\"><a href=\"{$url}\" style=\"color: #0073aa; text-decoration: underline;\">{$linkText}</a></p>", $attrs);
                }
                return '';
            default:
                // Fallback for unrecognized blocks
                if (!empty($innerHTML)) {
                    $attrs['td_id'] = $attrs['elem_id'] ?? '';
                    return $this->wrapInTable($innerHTML, $attrs);
                }
                if (!empty($innerBlocks)) {
                    return $this->renderBlocks($innerBlocks, true);
                }
                return '';
        }
    }

    /**
     * Render paragraph block
     */
    private function renderParagraph($content, $attrs)
    {
        $content = trim($content);

        // Skip empty paragraphs
        if (empty($content) || $content === '<p></p>') {
            return '';
        }

        // Extract content if it's wrapped in <p> tags
        if (preg_match('/<p[^>]*>(.*?)<\/p>/s', $content, $matches)) {
            $innerContent = $matches[1];
        } else {
            $innerContent = $content;
        }

        $elementId = $attrs['elem_id'] ?? '';

        return $this->wrapInTable("<p id=\"{$elementId}\">{$innerContent}</p>", $attrs);
    }

    /**
     * Render heading block
     */
    private function renderHeading($content, $attrs)
    {
        $level = $attrs['level'] ?? 2;

        // Extract content if it's wrapped in heading tags
        if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/s', $content, $matches)) {
            $innerContent = $matches[1];
        } else {
            $innerContent = $content;
        }
        // find the existing classnames from $content and preserve them in the new heading tag
        $className = '';
        if (preg_match('/<h[1-6][^>]*class=["\']([^"\']*)["\'][^>]*>/s', $content, $matches)) {
            $className = $matches[1];
        }

        $elemId = $attrs['elem_id'] ?? '';

        return $this->wrapInTable("<h{$level} class='$className' id='$elemId'>{$innerContent}</h{$level}>", $attrs);
    }

    /**
     * Render image block
     */
    private function renderImage($attrs, $innerHTML = '')
    {

        // get the content between <figure> tags if exists, as sometimes image block can have that wrapper in innerHTML
        if (preg_match('/<figure[^>]*>(.*?)<\/figure>/s', $innerHTML, $matches)) {
            $innerHTML = $matches[1];
        }

        $id = $attrs['elem_id'] ?? '';

        // add the id to the <img tag if exists in innerHTML
        if ($id && preg_match('/<img[^>]*>/s', $innerHTML, $matches)) {
            $imgTag = $matches[0];
            // add id attribute to img tag
            if (strpos($imgTag, 'id=') === false) {
                $newImgTag = str_replace('<img', '<img id="' . $id . '"', $imgTag);
                $innerHTML = str_replace($imgTag, $newImgTag, $innerHTML);
            }
        }

        $align = Arr::get($attrs, 'align', 'left');

        $class = 'fc_image';

        $class .= ' fc_align_' . $align;

        $html = '<div class="' . $class . '">' . $innerHTML . '</div>';

        return $this->wrapInTable($html, $attrs);
    }

    /**
     * Render list block
     */
    private function renderList($content, $innerBlocks, $attrs)
    {
        $ordered = $attrs['ordered'] ?? false;
        $tag = $ordered ? 'ol' : 'ul';

        $id = $attrs['elem_id'] ?? '';

        $classes = ['fc_list_item'];
        if ($attrsClass = trim((string)Arr::get($attrs, 'className', ''))) {
            $classes = array_merge($classes, preg_split('/\s+/', $attrsClass));
        }
        if (preg_match('/<' . $tag . '[^>]*class=["\']([^"\']+)["\']/i', $content, $matches)) {
            $classes = array_merge($classes, preg_split('/\s+/', trim((string)$matches[1])));
        }
        $classes = array_filter(array_unique($classes));
        $classAttr = implode(' ', $classes);

        $fontSizeValue = '';
        if ($fontSize = Arr::get($attrs, 'fontSize')) {
            $fontSizeValue = $this->resolveFontSizeValue($fontSize);
        } elseif (preg_match('/has-([a-z0-9-]+)-font-size/i', $classAttr, $matches)) {
            $fontSizeValue = $this->resolveFontSizeValue($matches[1]);
        }

        $lineHeightValue = Arr::get($attrs, 'style.typography.lineHeight', '');
        if (is_string($lineHeightValue) && strpos($lineHeightValue, 'var:') === 0) {
            $lineHeightValue = $this->transformToCssVar($lineHeightValue);
        }
        if (!is_string($lineHeightValue)) {
            $lineHeightValue = '';
        }

        $listItems = '';
        // If we have innerBlocks, render them
        if (!empty($innerBlocks)) {
            $listItems = '';
            foreach ($innerBlocks as $block) {
                if ($block['blockName'] === 'core/list-item') {
                    $listItems .= $this->renderListItem($block['innerHTML'], $block['attrs'] ?? []);
                }
            }
        }

        if (!$listItems) {
            return '';
        }

        $inlineStyle = '';
        if ($fontSizeValue) {
            $inlineStyle .= 'font-size:' . $fontSizeValue . ';';
            if ($id) {
                $this->childCss .= '#' . $id . ' li, #' . $id . ' li p { font-size: ' . $fontSizeValue . '; } ';
            }
        }
        if ($lineHeightValue) {
            $inlineStyle .= 'line-height:' . $lineHeightValue . ';';
            if ($id) {
                $this->childCss .= '#' . $id . ' li, #' . $id . ' li p { line-height: ' . $lineHeightValue . '; } ';
            }
        }

        $styleAttr = $inlineStyle ? " style=\"{$inlineStyle}\"" : '';

        return $this->wrapInTable("<{$tag} class='{$classAttr}' id='$id'{$styleAttr}>{$listItems}</{$tag}>", $attrs);
    }

    /**
     * Render list item
     */
    private function renderListItem($content, $attrs)
    {
        if (!$this->checkBlockConditionVisibility($attrs)) {
            return '';
        }

        $fontSizeValue = '';
        $fontFamilyValue = '';

        // Explicit typography style on list item takes highest priority.
        $styleFontSize = Arr::get($attrs, 'style.typography.fontSize', '');
        if (is_string($styleFontSize) && $styleFontSize !== '') {
            if (strpos($styleFontSize, 'var:') === 0) {
                $styleFontSize = $this->transformToCssVar($styleFontSize);
            }
            $fontSizeValue = $this->resolveFontSizeValue($styleFontSize);
        }

        // Preset slug from attrs (e.g. fc-small).
        if (!$fontSizeValue && ($fontSize = Arr::get($attrs, 'fontSize'))) {
            $fontSizeValue = $this->resolveFontSizeValue($fontSize);
        }

        // Fallback: detect preset class directly from li markup.
        if (!$fontSizeValue && is_string($content) && preg_match('/has-([a-z0-9-]+)-font-size/i', $content, $matches)) {
            $fontSizeValue = $this->resolveFontSizeValue($matches[1]);
        }

        // Explicit typography style on list item takes highest priority.
        $styleFontFamily = Arr::get($attrs, 'style.typography.fontFamily', '');
        if (is_string($styleFontFamily) && $styleFontFamily !== '') {
            $fontFamilyValue = $this->resolveFontFamilyValue($styleFontFamily);
        }

        // Preset slug or raw stack from attrs.
        if (!$fontFamilyValue && ($fontFamily = Arr::get($attrs, 'fontFamily'))) {
            $fontFamilyValue = $this->resolveFontFamilyValue($fontFamily);
        }

        // Fallback: detect font-family preset class from li markup.
        if (!$fontFamilyValue && is_string($content) && preg_match('/has-([a-z0-9-]+)-font-family/i', $content, $matches)) {
            $fontFamilyValue = $this->resolveFontFamilyValue($matches[1]);
        }

        if (!$fontSizeValue && !$fontFamilyValue) {
            return $content;
        }

        // Ensure list item typography is inline for email clients.
        $content = preg_replace_callback('/<li\b([^>]*)>/i', function ($matches) use ($fontSizeValue, $fontFamilyValue) {
            $attrs = $matches[1];
            $styleParts = [];
            if ($fontSizeValue) {
                $styleParts[] = 'font-size:' . $fontSizeValue;
            }
            if ($fontFamilyValue) {
                $styleParts[] = 'font-family:' . $fontFamilyValue;
            }
            $appendedStyles = implode(';', $styleParts);

            if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $attrs, $styleMatch)) {
                $existingStyle = rtrim(trim($styleMatch[2]), ';');
                $updatedStyle = $existingStyle . ';' . $appendedStyles;
                return str_replace($styleMatch[0], ' style="' . esc_attr($updatedStyle) . '"', $matches[0]);
            }

            return '<li' . $attrs . ' style="' . esc_attr($appendedStyles) . '">';
        }, $content, 1);

        return $content;
    }

    /**
     * Render the core RSS block with email-safe markup.
     *
     * @param array $attrs Block attributes.
     * @return string
     */
    private function renderRss($attrs)
    {
        $feedUrl = esc_url_raw((string)Arr::get($attrs, 'feedURL', ''));
        if (!$feedUrl || !$this->isSafeRssFeedUrl($feedUrl)) {
            return '';
        }

        $rssCacheKey = md5(wp_json_encode([
            'feed_url'       => $feedUrl,
            'items_to_show'  => (int)Arr::get($attrs, 'itemsToShow', 5),
            'display_date'   => !empty($attrs['displayDate']),
            'display_author' => !empty($attrs['displayAuthor']),
            'display_excerpt' => !empty($attrs['displayExcerpt']),
            'excerpt_length' => (int)Arr::get($attrs, 'excerptLength', 55),
            'open_new_tab'   => !empty($attrs['openInNewTab']),
            'rel'            => (string)Arr::get($attrs, 'rel', '')
        ]));

        if (isset(self::$rssRenderCache[$rssCacheKey])) {
            return $this->wrapRssHtmlWithCurrentBlock(self::$rssRenderCache[$rssCacheKey], $attrs);
        }

        if (!function_exists('fetch_feed') && defined('ABSPATH') && defined('WPINC')) {
            require_once ABSPATH . WPINC . '/feed.php';
        }

        if (!function_exists('fetch_feed')) {
            return '';
        }

        $rssRequestArgsFilter = function ($requestArgs, $url) use ($feedUrl) {
            if ($url === $feedUrl) {
                $requestArgs['timeout'] = 5;
                $requestArgs['redirection'] = 3;
                $requestArgs['reject_unsafe_urls'] = true;
            }

            return $requestArgs;
        };

        add_filter('http_request_args', $rssRequestArgsFilter, 10, 2);
        $rss = fetch_feed($feedUrl);
        remove_filter('http_request_args', $rssRequestArgsFilter, 10);

        if (is_wp_error($rss) || !$rss || !method_exists($rss, 'get_item_quantity')) {
            return '';
        }

        $itemsToShow = max(1, min(20, (int)Arr::get($attrs, 'itemsToShow', 5)));
        $quantity = $rss->get_item_quantity($itemsToShow);
        if (!$quantity) {
            return '';
        }

        $items = $rss->get_items(0, $quantity);
        if (!$items) {
            return '';
        }

        $listItems = '';
        $displayDate = !empty($attrs['displayDate']);
        $displayAuthor = !empty($attrs['displayAuthor']);
        $displayExcerpt = !empty($attrs['displayExcerpt']);
        $excerptLength = max(1, (int)Arr::get($attrs, 'excerptLength', 55));
        $openInNewTab = !empty($attrs['openInNewTab']);
        $rel = trim((string)Arr::get($attrs, 'rel', ''));

        $linkAttrs = '';
        if ($openInNewTab) {
            $linkAttrs .= ' target="_blank"';
        }
        if ($rel !== '') {
            $linkAttrs .= ' rel="' . esc_attr($rel) . '"';
        }

        foreach ($items as $item) {
            $title = trim(wp_strip_all_tags(html_entity_decode((string)$item->get_title(), ENT_QUOTES, get_option('blog_charset'))));
            if ($title === '') {
                $title = __('(no title)', 'fluent-crm');
            }

            $link = esc_url((string)$item->get_link());
            $titleHtml = $link
                ? '<a href="' . $link . '"' . $linkAttrs . '>' . esc_html($title) . '</a>'
                : esc_html($title);

            $metaHtml = '';

            if ($displayDate) {
                $timestamp = $item->get_date('U');
                if ($timestamp) {
                    $gmtOffset = get_option('gmt_offset');
                    $timestamp += (int)((float)$gmtOffset * HOUR_IN_SECONDS);
                    $metaHtml .= '<span class="wp-block-rss__item-publish-date" style="display:block;font-size:13px;color:#6b7280;">' .
                        esc_html(date_i18n(get_option('date_format'), $timestamp)) .
                        '</span>';
                }
            }

            if ($displayAuthor) {
                $author = $item->get_author();
                if (is_object($author) && method_exists($author, 'get_name')) {
                    $authorName = trim(wp_strip_all_tags((string)$author->get_name()));
                    if ($authorName !== '') {
                        $metaHtml .= '<span class="wp-block-rss__item-author" style="display:block;font-size:13px;color:#6b7280;">' .
                            sprintf(
                                /* translators: %s: author name. */
                                esc_html__('by %s', 'fluent-crm'),
                                esc_html($authorName)
                            ) .
                            '</span>';
                    }
                }
            }

            $excerptHtml = '';
            if ($displayExcerpt) {
                $description = html_entity_decode((string)$item->get_description(), ENT_QUOTES, get_option('blog_charset'));
                $description = trim(wp_strip_all_tags($description));
                if ($description !== '') {
                    $excerptHtml = '<div class="wp-block-rss__item-excerpt" style="margin-top:6px;">' .
                        esc_html(wp_trim_words($description, $excerptLength, ' [...]')) .
                        '</div>';
                }
            }

            $listItems .= '<div class="wp-block-rss__item" style="margin-bottom:12px;">' .
                '<div class="wp-block-rss__item-title">' . $titleHtml . '</div>' .
                $metaHtml .
                $excerptHtml .
                '</div>';
        }

        if (!$listItems) {
            return '';
        }

        self::$rssRenderCache[$rssCacheKey] = $listItems;

        return $this->wrapRssHtmlWithCurrentBlock($listItems, $attrs);
    }

    /**
     * Validate RSS feed URLs before the server fetches remote content.
     *
     * @param string $url Feed URL.
     * @return bool
     */
    private function isSafeRssFeedUrl($url)
    {
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return false;
        }

        $scheme = strtolower(isset($parsed['scheme']) ? $parsed['scheme'] : '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = trim($parsed['host'], '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
        }

        $isSafe = (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        /**
         * Allow site owners to enforce custom RSS feed source policies.
         *
         * @param bool   $isSafe Whether the feed URL resolves to a public IP.
         * @param string $url    Feed URL.
         * @param string $host   Parsed URL host.
         * @param string $ip     Resolved host IP.
         */
        return (bool)apply_filters('fluent_crm/rss_block_is_safe_feed_url', $isSafe, $url, $host, $ip);
    }

    /**
     * Wrap cached RSS list items with the current block id and table attributes.
     *
     * @param string $listItems Rendered RSS item markup.
     * @param array  $attrs Block attributes.
     * @return string
     */
    private function wrapRssHtmlWithCurrentBlock($listItems, $attrs)
    {
        $elementId = esc_attr(Arr::get($attrs, 'elem_id', ''));
        $html = '<div id="' . $elementId . '" class="wp-block-rss" style="padding:0;margin:0;">' . $listItems . '</div>';

        return $this->wrapInTable($html, $attrs);
    }

    /**
     * Render quote block
     */
    private function renderQuote($content, $innerBlocks, $attrs)
    {

        $borderColor = BlockEditorHelper::getBorderColor($attrs, '#4f46e5');

        $quoteSide = fluentcrm_is_rtl() ? 'right' : 'left';
        $styles = "border-{$quoteSide}: 4px solid " . $borderColor . '; padding-' . $quoteSide . ': 20px;';

        if (!isset($attrs['style']['spacing']['margin']['bottom'])) {
            $styles .= ' margin-bottom: 20px;';
        }

        $html = '';
        if ($innerBlocks) {
            $html = $this->renderBlocks($innerBlocks, true);
        }

        if (!$html) {
            return '';
        }

        // get <cite> content if exists in $content
        if (preg_match('/<cite[^>]*>(.*?)<\/cite>/s', $content, $matches)) {
            $cite = '<div style="font-style: italic; font-size: 90%;">' . $matches[1] . '</div>';
            $html .= $cite;
        }

        $id = $attrs['elem_id'] ?? '';

        return $this->wrapInTable("<div id='$id' style=\"{$styles}\">{$html}</div>", $attrs);
    }

    /**
     * Render buttons container
     */
    private function renderButtons($innerBlocks, $attrs)
    {
        $layout = $attrs['layout'] ?? [];
        $justifyContent = $layout['justifyContent'] ?? 'left';
        $orientation = strtolower((string)($layout['orientation'] ?? 'horizontal'));
        $isVertical = ($orientation === 'vertical');

        $alignMap = [
            'left'          => 'left',
            'center'        => 'center',
            'right'         => 'right',
            'space-between' => 'start', // For email, align to start in the active direction
            'space-around'  => 'center', // For email, we'll center and let spacing
        ];

        $textAlign = $this->getDirectionalTextAlign($alignMap[$justifyContent] ?? 'left');

        $tableStyles = "width: 100%; border-collapse: collapse; text-align: {$textAlign};";

        $elementId = $attrs['elem_id'] ?? '';

        unset($attrs['elem_id']);

        $innerHtml = <<<HTML
        <table role="presentation" style="$tableStyles" align="$textAlign" class="fc_buttons_wrap" width="100%" cellspacing="0" cellpadding="0" border="0"><td id="$elementId" class="fc_buttons_inner">
        HTML;


        $buttonsHtml = '';

        foreach ($innerBlocks as $button) {
            if ($button['blockName'] === 'core/button') {
                $buttonElementId = uniqid('block-', false);
                $button['attrs']['elem_id'] = $buttonElementId;
                $bttonHtml = $this->renderButton($button['innerHTML'], $button['attrs'] ?? []);
                if (!$bttonHtml) {
                    continue;
                }

                if ($isVertical) {
                    $buttonsHtml .= '<div style="display:block;">' . $bttonHtml . '</div>';
                } else {
                    $buttonsHtml .= $bttonHtml;
                }

                $this->collectInlineStyles($buttonElementId, $button['attrs'], 'core/button');

            }
        }

        if (!$buttonsHtml) {
            return '';
        }

        $innerHtml .= $buttonsHtml;

        $innerHtml .= "</td></tr></table>";

        return $this->wrapInTable($innerHtml, $attrs);
    }

    /**
     * Render button block
     */
    private function renderButton($content, $attrs)
    {
        if (!$this->checkBlockConditionVisibility($attrs)) {
            return '';
        }

        // Extract URL and text from content
        $url = '#';
        $text = '';

        if (!empty($content)) {
            // Try to extract from anchor tag
            if (preg_match('/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/s', $content, $matches)) {
                $url = $matches[1];
                $rawText = $matches[2];
                // Remove all HTML tags but keep the text
                $text = trim(preg_replace('/<[^>]*>/', '', $rawText));
            }
        }

        // Fallback to attrs if extraction failed
        if ($url === '#' && !empty($attrs['url'])) {
            $url = $attrs['url'];
        }
        if (!empty($attrs['text'])) {
            $text = $attrs['text'];
        }

        if (!$text) {
            return '';
        }

        $elementId = $attrs['elem_id'] ?? '';

        $styles = '';

        if ($width = Arr::get($attrs, 'width')) {
            $styles .= "min-width: {$width}%;";
        }

        $class = 'fc_button';
        $className = (string)Arr::get($attrs, 'className', '');
        if ($className) {
            $class .= ' ' . $className;
        }

        return "<a class='" . esc_attr($class) . "' id=\"" . esc_attr($elementId) . "\" style='" . esc_attr($styles) . "' href=\"" . $this->escapeButtonUrl($url) . "\">" . esc_html($text) . "</a>";
    }

    /**
     * Escape button URLs without stripping smartcodes before the parser phase.
     *
     * Campaign smartcodes are parsed after Gutenberg blocks are rendered, so running
     * esc_url() on a smartcode URL here removes the curly braces and makes the later
     * parser miss it. Keep only smartcode-bearing safe-protocol URLs intact with
     * esc_attr(); static URLs and unsafe protocols still go through esc_url().
     */
    private function escapeButtonUrl($url)
    {
        $url = (string)$url;

        if (preg_match('/^http:\/\/(\{\{[^{}\r\n]+}}|##[^#\r\n]+##)$/i', trim($url), $matches)) {
            $url = $matches[1];
        }

        if (!preg_match('/(\{\{[^{}\r\n]+}}|##[^#\r\n]+##)/', $url)) {
            return esc_url($url);
        }

        if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', ltrim($url), $matches)) {
            return esc_attr($url);
        }

        $protocol = strtolower($matches[1]);

        if (in_array($protocol, ['http', 'https', 'mailto', 'tel'], true)) {
            return esc_attr($url);
        }

        return esc_url($url);
    }

    /**
     * Render legacy Woo single product block.
     */
    private function renderWooProductBlock($block, $attrs, $innerHTML)
    {
        if (!defined('WC_PLUGIN_FILE')) {
            return '';
        }

        $buttonText = !empty($attrs['buttonText']) ? $attrs['buttonText'] : __('Buy Now', 'fluent-crm');
        $buttonUrl = '#';

        if (!empty($attrs['productId']) && function_exists('wc_get_product')) {
            $product = wc_get_product((int)$attrs['productId']);
            if ($product && method_exists($product, 'get_permalink')) {
                $buttonUrl = $product->get_permalink();
            }
        }

        if (!empty($innerHTML)) {
            if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $innerHTML, $urlMatch)) {
                $buttonUrl = $urlMatch[1];
            }
            if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $innerHTML, $textMatch)) {
                $parsedText = trim(wp_strip_all_tags($textMatch[1]));
                if ($parsedText) {
                    $buttonText = $parsedText;
                }
            }
        }

        $buttonBlock = $this->getFirstButtonBlock($block);
        $buttonAttrs = Arr::get($buttonBlock, 'attrs', []);
        $buttonInnerHTML = Arr::get($buttonBlock, 'innerHTML', '');

        if (!empty($buttonInnerHTML)) {
            if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $buttonInnerHTML, $urlMatch)) {
                $buttonUrl = $urlMatch[1];
            }

            if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $buttonInnerHTML, $textMatch)) {
                $parsedText = trim(wp_strip_all_tags($textMatch[1]));
                if ($parsedText) {
                    $buttonText = $parsedText;
                }
            }
        }

        if (!empty($buttonAttrs['url'])) {
            $buttonUrl = $buttonAttrs['url'];
        }

        if (!empty($buttonAttrs['text'])) {
            $buttonText = $buttonAttrs['text'];
        }

        $buttonElementId = uniqid('block-', false);
        $buttonAttrs['elem_id'] = $buttonElementId;

        $buttonHtml = $this->renderButton(
            '<a href="' . esc_url($buttonUrl) . '">' . esc_html($buttonText) . '</a>',
            $buttonAttrs
        );

        if (!$buttonHtml) {
            $buttonHtml = '<a href="' . esc_url($buttonUrl) . '">' . esc_html($buttonText) . '</a>';
        } else {
            $this->collectInlineStyles($buttonElementId, $buttonAttrs, 'core/button');
        }

        $html = WooProduct::renderProduct($buttonHtml, [
            'blockName' => 'fluentcrm/woo-product',
            'attrs'     => $attrs
        ]);

        if (!$html) {
            return '';
        }

        $attrs['td_id'] = $attrs['elem_id'] ?? '';
        return $this->wrapInTable($html, $attrs);
    }

    /**
     * Find the first nested Gutenberg button block inside product blocks.
     */
    private function getFirstButtonBlock($block)
    {
        $innerBlocks = Arr::get($block, 'innerBlocks', []);

        foreach ($innerBlocks as $innerBlock) {
            if (Arr::get($innerBlock, 'blockName') === 'core/button') {
                return $innerBlock;
            }

            $buttonBlock = $this->getFirstButtonBlock($innerBlock);
            if ($buttonBlock) {
                return $buttonBlock;
            }
        }

        return [];
    }

    /**
     * Render FluentCart single product block.
     */
    private function renderCartProductBlock($block, $attrs, $innerHTML)
    {
        if (!defined('FLUENTCART_VERSION')) {
            return '';
        }

        $buttonText = !empty($attrs['buttonText']) ? $attrs['buttonText'] : __('Buy Now', 'fluent-crm');
        $buttonUrl = '#';

        if (!empty($attrs['productId'])) {
            $productPermalink = get_permalink((int)$attrs['productId']);
            if ($productPermalink) {
                $buttonUrl = $productPermalink;
            }
        }

        if (!empty($innerHTML)) {
            if (preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $innerHTML, $urlMatch)) {
                $buttonUrl = $urlMatch[1];
            }
            if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $innerHTML, $textMatch)) {
                $parsedText = trim(wp_strip_all_tags($textMatch[1]));
                if ($parsedText) {
                    $buttonText = $parsedText;
                }
            }
        }

        $buttonAttrs = $this->getFirstButtonBlockAttrs($block);
        $buttonElementId = uniqid('block-', false);
        $buttonAttrs['elem_id'] = $buttonElementId;

        $buttonHtml = $this->renderButton(
            '<a href="' . esc_url($buttonUrl) . '">' . esc_html($buttonText) . '</a>',
            $buttonAttrs
        );

        if (!$buttonHtml) {
            $buttonHtml = '<a href="' . esc_url($buttonUrl) . '">' . esc_html($buttonText) . '</a>';
        } else {
            $this->collectInlineStyles($buttonElementId, $buttonAttrs, 'core/button');
        }

        $html = CartProduct::renderProduct($buttonHtml, [
            'blockName' => 'fluent-crm/cart-product',
            'attrs'     => $attrs
        ]);

        if (!$html) {
            return '';
        }

        $attrs['td_id'] = $attrs['elem_id'] ?? '';
        return $this->wrapInTable($html, $attrs);
    }

    /**
     * Render legacy latest posts block from FluentCampaign (if available).
     */
    private function renderLatestPostsBlock($block, $attrs)
    {
        if (!class_exists('\FluentCampaign\App\Services\PostParser\LatestPost')) {
            return '';
        }

        try {
            $html = \FluentCampaign\App\Services\PostParser\LatestPost::renderPosts('', [
                'blockName' => 'fluent-crm/latest-posts',
                'attrs'     => $attrs,
                'innerHTML' => isset($block['innerHTML']) ? $block['innerHTML'] : ''
            ]);

            $attrs['elem_id'] = $attrs['elem_id'] ?? '';
            return $this->wrapInTable($html, $attrs);

        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Render product listing blocks via dedicated renderer.
     */
    private function renderProductsBlock($block, $attrs, $blockName)
    {
        $html = WooProducts::renderProducts('', [
            'blockName' => $blockName,
            'attrs'     => $attrs,
            'innerHTML' => isset($block['innerHTML']) ? $block['innerHTML'] : ''
        ]);

        if (!$html) {
            return '';
        }

        $attrs['td_id'] = $attrs['elem_id'] ?? '';
        return $this->wrapInTable($html, $attrs);

    }

    private function renderCartProductsBlock($block, $attrs)
    {
        $html = CartProducts::renderProducts('', [
            'blockName' => 'fluent-crm/cart-products',
            'attrs'     => $attrs,
            'innerHTML' => isset($block['innerHTML']) ? $block['innerHTML'] : ''
        ]);

        if (!$html) {
            return '';
        }

        $attrs['td_id'] = $attrs['elem_id'] ?? '';
        return $this->wrapInTable($html, $attrs);
    }

    /**
     * Render conditional group block based on subscriber tags.
     */
    private function renderConditionalGroupBlock($innerBlocks, $attrs, $innerHTML)
    {
        $content = '';
        if (!empty($innerBlocks)) {
            $content = $this->renderBlocks($innerBlocks, true);
        } elseif (!empty($innerHTML)) {
            $content = $innerHTML;
        }

        if (!$content) {
            return '';
        }

        $content = $this->wrapInTable($content, $attrs);

        $subscriber = BlockParserHelper::getSubscriber();
        if (!$subscriber) {
            $subscriber = apply_filters('fluent_crm/get_current_block_condition_subscriber', $subscriber);
        }

        // Keep content visible in preview/test contexts when subscriber is unknown.
        if (!$subscriber) {
            return $content;
        }

        $tagIds = isset($attrs['tag_ids']) && is_array($attrs['tag_ids']) ? $attrs['tag_ids'] : [];
        if (!$tagIds) {
            return '';
        }

        $checkType = $this->normalizeConditionalCheckType(isset($attrs['condition_type']) ? $attrs['condition_type'] : 'show_if_tag_exist');
        $tagMatched = method_exists($subscriber, 'hasAnyTagId') ? $subscriber->hasAnyTagId($tagIds) : false;

        if ($checkType === 'show_if_tag_exist') {
            return $tagMatched ? $content : '';
        }

        if ($checkType === 'show_if_tag_not_exist') {
            return $tagMatched ? '' : $content;
        }

        return '';
    }


    /**
     * Render a synced pattern (core/block) by looking up its content from fc_meta
     * and recursively rendering the contained blocks.
     */
    private static $syncedPatternCache = [];

    private function renderSyncedPattern($attrs, $nested = false)
    {
        $ref = isset($attrs['ref']) ? (int) $attrs['ref'] : 0;
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

        $blocks = parse_blocks($content);
        if (empty($blocks)) {
            return '';
        }

        return $this->renderBlocks($blocks, $nested);
    }

    private function renderCodeBlock($innerHTML, $attrs)
    {
        $code = '';
        // get the content between <pre> tags if exists, as sometimes code block can have that wrapper in innerHTML
        if (preg_match('/<pre[^>]*>(.*?)<\/pre>/s', $innerHTML, $matches)) {
            $code = $matches[1];
        }


        if (!$code) {
            return '';
        }

        $elementId = $attrs['elem_id'] ?? '';

        if (!isset($this->inlineStyles['#' . $elementId]['background-color'])) {
            $this->inlineStyles['#' . $elementId]['background-color'] = '#e5e7eb';
        }

        return $this->wrapInTable("<pre id=\"{$elementId}\" style=\"font-family: monospace; padding: 15px; overflow-x: auto; text-wrap: auto; width: 100%; border-radius: 4px;\">{$code}</pre>", $attrs);

    }

    private function renderPullQuote($innerHTML, $attrs)
    {
        $html = '';
        // get the content between <pre> tags if exists, as sometimes code block can have that wrapper in innerHTML
        if (preg_match('/<blockquote[^>]*>(.*?)<\/blockquote>/s', $innerHTML, $matches)) {
            $html = $matches[1];
        }


        if (!$html) {
            return '';
        }

        $elementId = $attrs['elem_id'] ?? '';
        $elementSelector = $elementId ? ('#' . $elementId) : '';
        $existingStyles = $elementSelector ? ($this->inlineStyles[$elementSelector] ?? []) : [];

        $hasBlockFontSize = !empty(Arr::get($existingStyles, 'font-size')) ||
            !empty(Arr::get($attrs, 'fontSize')) ||
            !empty(Arr::get($attrs, 'style.typography.fontSize'));

        // Pullquote defaults for title paragraph:
        // - Always ensure margin + line-height when missing.
        // - Add 25px font-size only when no font-size is configured.
        $hasParagraphFontSize = preg_match('/<p[^>]*(?:style=["\'][^"\']*font-size\s*:|class=["\'][^"\']*has-[a-z0-9-]+-font-size)/i', $html);
        $needsDefaultTitleFontSize = (!$hasBlockFontSize && !$hasParagraphFontSize);

        $firstPApplied = false;
        $html = preg_replace_callback('/<p([^>]*)>/i', function ($matches) use (&$firstPApplied, $needsDefaultTitleFontSize) {
            if ($firstPApplied) {
                return $matches[0];
            }
            $firstPApplied = true;

            $attrs = $matches[1];
            if (preg_match('/style=(["\'])(.*?)\1/i', $attrs, $styleMatch)) {
                $quote = $styleMatch[1];
                $styleValue = rtrim($styleMatch[2], ';');

                if ($needsDefaultTitleFontSize && !preg_match('/font-size\s*:/i', $styleValue)) {
                    $styleValue .= '; font-size: 25px';
                }
                if (!preg_match('/(?:^|;)\s*margin\s*:/i', $styleValue)) {
                    $styleValue .= '; margin: 0 0 1em 0';
                }
                if (!preg_match('/line-height\s*:/i', $styleValue)) {
                    $styleValue .= '; line-height: 1.6';
                }

                $styleValue = trim($styleValue, " ;") . ';';
                $attrs = preg_replace('/style=(["\'])(.*?)\1/i', 'style=' . $quote . $styleValue . $quote, $attrs, 1);
                return '<p' . $attrs . '>';
            }

            $styleParts = [];
            if ($needsDefaultTitleFontSize) {
                $styleParts[] = 'font-size: 25px';
            }
            $styleParts[] = 'margin: 0 0 1em 0';
            $styleParts[] = 'line-height: 1.6';

            return '<p' . $attrs . ' style="' . implode('; ', $styleParts) . ';">';
        }, $html, 1);

        if (!$hasBlockFontSize && !preg_match('/<cite[^>]*(?:style=["\'][^"\']*font-size\s*:|class=["\'][^"\']*has-[a-z0-9-]+-font-size)/i', $html)) {
            $html = preg_replace_callback('/<cite([^>]*)>/i', function ($matches) {
                $attrs = $matches[1];
                if (preg_match('/style=(["\'])(.*?)\1/i', $attrs, $styleMatch)) {
                    $quote = $styleMatch[1];
                    $styleValue = rtrim($styleMatch[2], ';') . '; font-size: 16px;';
                    $attrs = preg_replace('/style=(["\'])(.*?)\1/i', 'style=' . $quote . $styleValue . $quote, $attrs, 1);
                    return '<cite' . $attrs . '>';
                }

                return '<cite' . $attrs . ' style="font-size: 16px;">';
            }, $html, 1);
        }

        $styles = 'width: 100%;';

        $defaultStyles = [
            'padding-left'   => '20px',
            'padding-right'  => '20px',
            'padding-bottom' => '20px',
            'padding-top'    => '20px',
            'margin-bottom'  => '20px',
            'text-align'     => $this->getDirectionalTextAlign(Arr::get($attrs, 'textAlign', 'left'))
        ];

        if ($elementId) {
            $hasCustomBorder = isset($existingStyles['border'])
                || isset($existingStyles['border-left'])
                || isset($existingStyles['border-right'])
                || isset($existingStyles['border-top'])
                || isset($existingStyles['border-bottom']);

            if (!$hasCustomBorder) {
                $defaultStyles['border-left'] = '4px solid #e5e7eb';
                $defaultStyles['border-right'] = '4px solid #e5e7eb';
                $defaultStyles['border-bottom'] = '4px solid #e5e7eb';
                $defaultStyles['border-top'] = '4px solid #e5e7eb';
            }

            $this->setDefaultBlockStyles($elementId, $defaultStyles);
        }

        return $this->wrapInTable("<div class='fc_pull_qoute' id=\"{$elementId}\" style=\"$styles\">{$html}</div>", $attrs);

    }

    /**
     * Check per-block conditional visibility attributes.
     * Returns true if the block should be shown, false if hidden.
     */
    private function checkBlockConditionVisibility($attrs)
    {
        $conditionType = isset($attrs['fcrmConditionType']) ? $attrs['fcrmConditionType'] : '';
        $conditionType = $this->normalizeConditionalCheckType($conditionType);

        if (empty($conditionType)) {
            return true;
        }

        $subscriber = BlockParserHelper::getSubscriber();

        if (!$subscriber) {
            $subscriber = apply_filters('fluent_crm/get_current_block_condition_subscriber', $subscriber);
        }

        if (!$subscriber) {
            return true;
        }

        $tagIds = isset($attrs['fcrmTagIds']) && is_array($attrs['fcrmTagIds']) ? $attrs['fcrmTagIds'] : [];
        if (empty($tagIds)) {
            return true;
        }

        $tagMatched = $subscriber->hasAnyTagId($tagIds);

        if ($conditionType === 'show_if_tag_exist') {
            return $tagMatched;
        }

        if ($conditionType === 'show_if_tag_not_exist') {
            return !$tagMatched;
        }

        return true;
    }

    /**
     * Keep backward compatibility with legacy conditional values.
     */
    private function normalizeConditionalCheckType($checkType)
    {
        $checkType = trim((string)$checkType);
        if ($checkType === '') {
            return '';
        }

        $map = [
            // v2 legacy values
            'show_if_tag_exists'     => 'show_if_tag_exist',
            'show_if_tag_not_exists' => 'show_if_tag_not_exist',
        ];

        return Arr::get($map, $checkType, $checkType);
    }

    /**
     * Render columns block
     */
    private function renderColumns($innerBlocks, $attrs)
    {
        if (empty($innerBlocks)) {
            return '';
        }

        $hasBlockGap = isset($attrs['style']['spacing']['blockGap']['left']);
        $blockGap = 20;

        if ($hasBlockGap) {
            $blockGap = (int)str_replace('px', '', $attrs['style']['spacing']['blockGap']['left']);
        }

        $id = $attrs['elem_id'] ?? '';

        $verticalAlignment = Arr::get($attrs, 'verticalAlignment', 'top');

        $columnCount = count($innerBlocks);
        $columnWidth = floor(100 / $columnCount);
        $hasExplicitColumnWidths = false;

        foreach ($innerBlocks as $innerBlock) {
            if (!empty(Arr::get($innerBlock, 'attrs.width'))) {
                $hasExplicitColumnWidths = true;
                break;
            }
        }

        $isMobileStackable = Arr::get($attrs, 'isStackedOnMobile', true);

        $tableClass = 'fc_columns';

        if ($isMobileStackable) {
            $tableClass .= ' fc_columns_stack_mobile';
        }

        $columnTableStyles = [
            'width'            => '100%',
            'border-collapse'  => 'collapse',
            'border-spacing'   => '0'
        ];

        if ($margins = Arr::get($attrs, 'style.spacing.margin', [])) {
            foreach ($margins as $marginType => $marginValue) {
                if (!$marginValue) {
                    continue;
                }

                $columnTableStyles['margin-' . $marginType] = $this->normalizeCssSize($marginValue);
            }
        }

        $columnsHtml = '<table class="' . $tableClass . '" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="' . BlockEditorHelper::renderStyles($columnTableStyles) . '"><tr>';

        foreach ($innerBlocks as $index => $column) {

            $blockName = $column['blockName'] ?? '';

            if ($blockName === 'core/column') {
                $elementId = uniqid('block-', false);
                $this->collectInlineStyles($elementId, $column['attrs'] ?? [], 'core/column');

                $columnVerticalAlignment = Arr::get($column, 'attrs.verticalAlignment', $verticalAlignment);
                $styles = [
                    'vertical-align' => $columnVerticalAlignment,
                ];

                $columnWidthValue = $this->normalizeCssSize(Arr::get($column, 'attrs.width', ''));
                $widthAttr = '';

                if ($columnWidthValue) {
                    $styles['width'] = $columnWidthValue;

                    if (substr($columnWidthValue, -2) === 'px') {
                        $styles['max-width'] = $columnWidthValue;
                    }

                    $widthAttr = $this->normalizeHtmlWidthAttribute($columnWidthValue);
                } elseif (!$hasExplicitColumnWidths) {
                    $styles['width'] = $columnWidth . '%';
                    $widthAttr = $columnWidth . '%';
                }

                $padding = $blockGap / 2;

                $styles['padding-left'] = $padding . 'px';
                $styles['padding-right'] = $padding . 'px';

                $widthMarkup = $widthAttr ? ' width="' . esc_attr($widthAttr) . '"' : '';
                $columnsHtml .= '<td class="fc_column"' . $widthMarkup . ' style="' . BlockEditorHelper::renderStyles($styles) . '">';

                $column['attrs']['elem_id'] = $elementId;

                $columnsHtml .= $this->renderColumn($column['innerBlocks'], $column['attrs'], $column['innerHTML']);
                $columnsHtml .= '</td>';
            } else {
                $columnsHtml .= '<td width="' . $columnWidth . '%" style="vertical-align: ' . $verticalAlignment . '; padding: 0 10px;">';
                $columnsHtml .= $this->renderBlock($column, true);
                $columnsHtml .= '</td>';
            }


        }

        $columnsHtml .= '</tr></table>';

        $attrs['td_id'] = $id;

        return $this->wrapInTable($columnsHtml, $attrs);
    }

    /**
     * Render column block
     */
    private function renderColumn($innerBlocks, $attrs, $innerHTML)
    {
        $html = '';

        if (!empty($innerBlocks)) {
            $html = $this->renderBlocks($innerBlocks, true);
        } elseif (!empty($innerHTML)) {
            $html = $innerHTML;
        }

        $attrs['td_id'] = $attrs['elem_id'] ?? '';

        return $this->wrapInTable($html, $attrs);
    }

    /**
     * Render cover block
     */
    private function renderCover($innerBlocks, $attrs, $innerHTML)
    {
        $url = $attrs['url'] ?? '';
        $dimRatio = $attrs['dimRatio'] ?? 50;
        $overlayColor = $attrs['overlayColor'] ?? '';
        $style = $attrs['style'] ?? [];
        $contentPosition = $attrs['contentPosition'] ?? 'center center';
        $minHeight = $attrs['minHeight'] ?? '';
        $minHeightUnit = $attrs['minHeightUnit'] ?? 'px';

        // Extract image URL from innerHTML if not in attrs
        if (empty($url) && !empty($innerHTML)) {
            if (preg_match('/src=["\']([^"\']+)["\']/', $innerHTML, $matches)) {
                $url = $matches[1];
            }
        }

        $opacity = $dimRatio / 100;

        // Parse content position (e.g., "top center", "center center", "bottom left")
        $verticalAlign = 'center';
        $textAlign = 'center';

        if (!empty($contentPosition)) {
            $positions = explode(' ', $contentPosition);
            if (count($positions) >= 2) {
                $verticalAlign = $positions[0]; // top, center, bottom
                $textAlign = $positions[1]; // left, center, right
            } elseif (count($positions) === 1) {
                $verticalAlign = $positions[0];
            }
        }

        $textAlign = $this->getDirectionalTextAlign($textAlign, 'center');

        // Map vertical alignment to table cell vertical-align
        $vAlignStyle = $verticalAlign === 'top' ? 'top' : ($verticalAlign === 'bottom' ? 'bottom' : 'middle');

        // Determine minimum height
        $minHeightValue = $minHeight ? $minHeight . $minHeightUnit : '300px';

        // Render inner content
        $innerContent = '';
        if (!empty($innerBlocks)) {
            foreach ($innerBlocks as $block) {
                $innerContent .= $this->renderBlock($block);
            }
        }

        // For email, create a table-based layout with background image
        if ($url) {
            $html = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0; background-image: url(\'' . $url . '\'); background-size: cover; background-position: center; min-height: ' . $minHeightValue . ';">';
            $html .= '<tr>';
            $html .= '<td style="padding: 40px 20px; vertical-align: ' . $vAlignStyle . '; text-align: ' . $textAlign . '; background-color: rgba(0,0,0,' . $opacity . '); color: #ffffff; min-height: ' . $minHeightValue . ';">';
            $html .= $innerContent;
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';
            return $html;
        }

        return $this->wrapInTable($innerContent);
    }

    /**
     * Render separator block
     */
    private function renderSeparator($innerHTML, $attrs)
    {
        $class = 'fc_separator';
        if ($className = Arr::get($attrs, 'className')) {
            $class .= ' ' . $className;
        }

        $id = $attrs['elem_id'] ?? '';
        $separatorColor = Arr::get($attrs, 'style.color.background', '');

        if (!$separatorColor) {
            $separatorColor = Arr::get($attrs, 'backgroundColor', '');
        }

        if ($separatorColor && strpos($separatorColor, '#') !== 0 && strpos($separatorColor, 'rgb') !== 0 && strpos($separatorColor, 'hsl') !== 0 && strpos($separatorColor, 'var(') !== 0 && strpos($separatorColor, 'var:') !== 0) {
            $separatorColor = 'var(--fcom--color--' . $separatorColor . ')';
        }

        if ($separatorColor && strpos($separatorColor, 'var:') === 0) {
            $separatorColor = $this->transformToCssVar($separatorColor);
        }

        if ($separatorColor && strpos($separatorColor, 'var(') === 0) {
            $separatorColor = BlockEditorHelper::replaceStyleSlugsWithValues($separatorColor);
        }

        if (!$separatorColor) {
            $separatorColor = '#d1d5db';
        }

        $escapedId = esc_attr($id);
        $escapedClass = esc_attr($class);
        $escapedSeparatorColor = esc_attr($separatorColor);

        $isDots = strpos($class, 'is-style-dots') !== false;
        $hasWideAlignmentClass = strpos($class, 'alignwide') !== false || strpos($class, 'alignfull') !== false;
        $isWide = strpos($class, 'is-style-wide') !== false || $hasWideAlignmentClass || in_array(Arr::get($attrs, 'align', ''), ['wide', 'full'], true);

        if ($isDots) {
            $separator = "<div id='$escapedId' class='$escapedClass' style='border:none;text-align:center;line-height:1;color:$escapedSeparatorColor;font-size:24px;letter-spacing:10px;'>&middot;&middot;&middot;</div>";
            return $this->wrapInTable($separator, $attrs);
        }

        $lineWidth = $isWide ? '100%' : '100px';
        $separator = "<hr id='$escapedId' class='$escapedClass' style='margin-left: auto;margin-right: auto;border:none;height:2px;line-height:2px;font-size:0;background-color:$escapedSeparatorColor;width:$lineWidth;' />";

        return $this->wrapInTable($separator, $attrs);
    }

    /**
     * Render spacer block
     */
    private function renderSpacer($innerHtml, $attrs)
    {
        $id = $attrs['elem_id'] ?? '';

        return $this->wrapInTable("<div id='$id'>&nbsp;</div>", $attrs);
    }

    /**
     * Render group block
     */
    private function renderGroup($innerBlocks, $attrs, $innerHTML)
    {
        $layoutType = strtolower((string)Arr::get($attrs, 'layout.type', ''));
        if ($layoutType === 'flex') {
            return $this->renderRow($innerBlocks, $attrs, $innerHTML);
        }

        if (!empty($innerBlocks)) {
            $content = $this->renderBlocks($innerBlocks, true);
        } elseif (!empty($innerHTML)) {
            $content = $innerHTML;
        } else {
            return '';
        }

        $id = $attrs['elem_id'] ?? '';
        $disableBottomSpacing = !empty($attrs['fcrmDisableBottomSpacing']);
        $groupClasses = ['fc_group'];

        if (!empty($attrs['is_root'])) {
            $groupClasses[] = 'fc_group_root';
        }

        if ($disableBottomSpacing) {
            $groupClasses[] = 'fcrm-no-bottom-spacing';
        }

        $contentSize = $this->normalizeCssSize(Arr::get($attrs, 'layout.contentSize', ''));
        if ($contentSize) {
            $innerStyles = [
                'max-width'    => $contentSize,
                'margin-left'  => 'auto',
                'margin-right' => 'auto'
            ];

            $content = "<div class='fc_group_content' style='" . BlockEditorHelper::renderStyles($innerStyles) . "'>{$content}</div>";
        }

        return $this->wrapInTable("<div id='$id' class='" . esc_attr(implode(' ', $groupClasses)) . "'>{$content}</div>", $attrs);
    }

    /**
     * Render row block.
     */
    private function renderRow($innerBlocks, $attrs, $innerHTML)
    {
        if (empty($innerBlocks)) {
            if (!empty($innerHTML)) {
                return $this->wrapInTable($innerHTML, $attrs);
            }

            return '';
        }

        $layout = Arr::get($attrs, 'layout', []);
        $orientation = strtolower((string)Arr::get($layout, 'orientation', 'horizontal'));
        $className = strtolower((string)Arr::get($attrs, 'className', ''));
        if (strpos($className, 'is-vertical') !== false) {
            $orientation = 'vertical';
        }

        // A vertical row behaves like stacked content in email clients.
        if ($orientation === 'vertical') {
            $content = !empty($innerBlocks) ? $this->renderBlocks($innerBlocks, true) : $innerHTML;
            if (!$content) {
                return '';
            }

            $id = $attrs['elem_id'] ?? '';
            $disableBottomSpacing = !empty($attrs['fcrmDisableBottomSpacing']);
            $groupClasses = ['fc_group'];
            if (!empty($attrs['is_root'])) {
                $groupClasses[] = 'fc_group_root';
            }
            if ($disableBottomSpacing) {
                $groupClasses[] = 'fcrm-no-bottom-spacing';
            }

            $contentSize = $this->normalizeCssSize(Arr::get($attrs, 'layout.contentSize', ''));
            if ($contentSize) {
                $innerStyles = [
                    'max-width'    => $contentSize,
                    'margin-left'  => 'auto',
                    'margin-right' => 'auto'
                ];
                $content = "<div class='fc_group_content' style='" . BlockEditorHelper::renderStyles($innerStyles) . "'>{$content}</div>";
            }

            return $this->wrapInTable("<div id='$id' class='" . esc_attr(implode(' ', $groupClasses)) . "'>{$content}</div>", $attrs);
        }

        $justifyContent = strtolower((string)Arr::get($layout, 'justifyContent', 'left'));
        $justifyMode = 'left';
        if (in_array($justifyContent, ['right', 'end'], true)) {
            $justifyMode = 'right';
        } elseif ($justifyContent === 'center') {
            $justifyMode = 'center';
        } elseif ($justifyContent === 'space-between') {
            $justifyMode = 'space-between';
        }

        $rowGap = Arr::get($attrs, 'style.spacing.blockGap', '');
        if (is_array($rowGap)) {
            $rowGap = Arr::get($rowGap, 'left', Arr::get($rowGap, 'horizontal', ''));
        }
        $rowGap = $this->normalizeCssSize($rowGap);
        if (!$rowGap) {
            $rowGap = '20px';
        }

        $id = $attrs['elem_id'] ?? '';
        $blockCount = count($innerBlocks);

        $renderRowChild = function ($innerBlock) {
            $childBlock = $innerBlock;
            $childAttrs = (array)Arr::get($childBlock, 'attrs', []);
            // Row children must remain content-width for predictable alignment.
            $childAttrs['fcrmTableWidth'] = 'auto';
            $childBlock['attrs'] = $childAttrs;

            return $this->renderBlock($childBlock, true);
        };

        if ($justifyMode === 'space-between') {
            $tableStyles = [
                'width'           => '100%',
                'border-collapse' => 'separate',
                'border-spacing'  => '0'
            ];
            $rowHtml = '<table id="' . esc_attr($id) . '" class="fc_row" role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="' . BlockEditorHelper::renderStyles($tableStyles) . '"><tr>';

            $startAlign = $this->getDirectionalTextAlign('left');
            $endAlign = $this->getDirectionalTextAlign('right');

            foreach ($innerBlocks as $index => $innerBlock) {
                $cellStyles = [
                    'vertical-align' => 'top'
                ];

                $cellAlign = 'center';
                if ($index === 0) {
                    $cellAlign = $startAlign;
                } elseif ($index === ($blockCount - 1)) {
                    $cellAlign = $endAlign;
                }

                $rowHtml .= '<td class="fc_row_item" align="' . esc_attr($cellAlign) . '" style="' . BlockEditorHelper::renderStyles($cellStyles) . '">';
                $rowHtml .= $renderRowChild($innerBlock);
                $rowHtml .= '</td>';
            }

            $rowHtml .= '</tr></table>';
        } else {
            $outerAlign = $justifyMode === 'center'
                ? 'center'
                : ($justifyMode === 'right' ? $this->getDirectionalTextAlign('right') : $this->getDirectionalTextAlign('left'));

            $innerTableStyles = [
                'width'           => 'auto',
                'border-collapse' => 'separate',
                'border-spacing'  => '0'
            ];

            $innerHtml = '<table id="' . esc_attr($id) . '" class="fc_row" role="presentation" cellspacing="0" cellpadding="0" border="0" style="' . BlockEditorHelper::renderStyles($innerTableStyles) . '"><tr>';
            foreach ($innerBlocks as $index => $innerBlock) {
                $cellStyles = [
                    'vertical-align' => 'top'
                ];
                if ($index < ($blockCount - 1)) {
                    if (fluentcrm_is_rtl()) {
                        $cellStyles['padding-left'] = $rowGap;
                    } else {
                        $cellStyles['padding-right'] = $rowGap;
                    }
                }

                $innerHtml .= '<td class="fc_row_item" style="' . BlockEditorHelper::renderStyles($cellStyles) . '">';
                $innerHtml .= $renderRowChild($innerBlock);
                $innerHtml .= '</td>';
            }
            $innerHtml .= '</tr></table>';

            $rowHtml = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="' . esc_attr($outerAlign) . '">' . $innerHtml . '</td></tr></table>';
        }

        unset($attrs['elem_id']);

        return $this->wrapInTable($rowHtml, $attrs);
    }

    /**
     * Append inline CSS to an opening HTML tag without dropping existing styles.
     */
    private function appendInlineStyleToTag($tag, $style)
    {
        if (preg_match('/\sstyle=(["\'])(.*?)\1/i', $tag, $matches)) {
            $quote = $matches[1];
            $existingStyle = rtrim(trim($matches[2]), ';');
            $newStyle = $existingStyle ? $existingStyle . '; ' . $style : $style;

            // Use str_replace (not preg_replace) so existing style content cannot be
            // misread as a regex backreference in the replacement string.
            return str_replace($matches[0], ' style=' . $quote . $newStyle . $quote, $tag);
        }

        return preg_replace('/>$/', ' style="' . $style . '">', $tag, 1);
    }

    /**
     * Apply email-safe stripe backgrounds to table body rows.
     */
    private function applyTableStripeStyles($tableContent, $stripeColor = '#f0f0f0')
    {
        return preg_replace_callback('/<tbody\b([^>]*)>(.*?)<\/tbody>/is', function ($tbodyMatches) use ($stripeColor) {
            $rowIndex = 0;
            $tbodyContent = preg_replace_callback('/<tr\b([^>]*)>(.*?)<\/tr>/is', function ($rowMatches) use (&$rowIndex, $stripeColor) {
                $rowIndex++;

                if ($rowIndex % 2 === 0) {
                    return $rowMatches[0];
                }

                $rowContent = preg_replace_callback('/<(td|th)\b([^>]*)>/i', function ($cellMatches) use ($stripeColor) {
                    $tag = $this->appendInlineStyleToTag($cellMatches[0], 'background-color: ' . $stripeColor . ';');

                    if (stripos($tag, ' bgcolor=') === false) {
                        $tag = preg_replace('/>$/', ' bgcolor="' . $stripeColor . '">', $tag, 1);
                    }

                    return $tag;
                }, $rowMatches[2]);

                return '<tr' . $rowMatches[1] . '>' . $rowContent . '</tr>';
            }, $tbodyMatches[2]);

            return '<tbody' . $tbodyMatches[1] . '>' . $tbodyContent . '</tbody>';
        }, $tableContent);
    }

    /**
     * Render table block
     */
    private function renderTable($content, $attrs)
    {
        $borderConfig = Arr::get($attrs, 'style.border', []);
        $hasCustomBorder = !empty(Arr::get($borderConfig, 'width')) ||
            !empty(Arr::get($borderConfig, 'style')) ||
            !empty(Arr::get($borderConfig, 'color')) ||
            !empty(Arr::get($attrs, 'borderColor'));
        $className = (string)Arr::get($attrs, 'className', '');
        $isStriped = strpos($className, 'is-style-stripes') !== false;

        if ($hasCustomBorder) {
            $borderWidth = Arr::get($borderConfig, 'width', '1px');
            $borderStyle = Arr::get($borderConfig, 'style', 'solid');
            $borderColor = BlockEditorHelper::getBorderColor($attrs);
        } else {
            $borderWidth = '1px';
            $borderStyle = 'solid';
            $borderColor = '#6b7280';
        }

        $borderCss = "border: {$borderWidth} {$borderStyle} {$borderColor};";

        $styles = "width: 100%; border-collapse: collapse;";
        $cellStyles = "padding: 6px 10px;" . $borderCss;

        if (!isset($attrs['style']['spacing']['margin']['bottom'])) {
            $styles .= " margin-bottom: 20px;";
        }

        // Extract table content
        if (preg_match('/<table[^>]*>(.*?)<\/table>/s', $content, $matches)) {
            $tableContent = $matches[1];
            // Add styles to table cells
            $tableContent = preg_replace_callback('/<td\b([^>]*)>/i', function ($matches) use ($cellStyles) {
                return $this->appendInlineStyleToTag($matches[0], $cellStyles);
            }, $tableContent);
            $tableContent = preg_replace_callback('/<th\b([^>]*)>/i', function ($matches) use ($cellStyles) {
                return $this->appendInlineStyleToTag($matches[0], $cellStyles . ' font-weight: bold;');
            }, $tableContent);

            if ($isStriped) {
                $tableContent = $this->applyTableStripeStyles($tableContent);
            }
        } else {
            return '';
        }

        $id = $attrs['elem_id'] ?? '';
        $tableClass = 'fc_table';
        if ($isStriped) {
            $tableClass .= ' fc_table_striped';
        }

        return $this->wrapInTable("<table id='$id' class='{$tableClass}' style=\"{$styles}\">{$tableContent}</table>", $attrs);
    }

    /**
     * Render social links block
     */
    private function renderSocialLinks($innerBlocks, $attrs, $innerHTML)
    {
        $socialHtml = '<div style="margin: 20px 0; text-align: center;">';

        $socialLinks = [];

        // First, try to get from innerBlocks (preferred method)
        if (!empty($innerBlocks)) {
            foreach ($innerBlocks as $block) {
                if ($block['blockName'] === 'core/social-link') {
                    $blockAttrs = $block['attrs'] ?? [];
                    $blockInner = $block['innerHTML'] ?? '';

                    // Reconstruct innerHTML from innerContent if available
                    if (empty($blockInner) && !empty($block['innerContent'])) {
                        $blockInner = implode('', array_filter($block['innerContent'], 'is_string'));
                    }

                    $url = $blockAttrs['url'] ?? '';
                    $service = $blockAttrs['service'] ?? 'link';
                    $label = $blockAttrs['label'] ?? '';

                    // If URL is empty, try to extract from innerHTML
                    if (empty($url) && !empty($blockInner)) {
                        if (preg_match('/<a[^>]*href=["\']([^"\']*)["\']/', $blockInner, $urlMatch)) {
                            $url = $urlMatch[1];
                        }
                    }

                    // Extract label from aria-label or innerHTML
                    if (empty($label) && !empty($blockInner)) {
                        if (preg_match('/aria-label=["\']([^"\']*)["\']/', $blockInner, $labelMatch)) {
                            $label = $labelMatch[1];
                        }
                    }

                    // Determine service from URL if not set
                    if ($service === 'link' && !empty($url)) {
                        if (strpos($url, 'wordpress.org') !== false || strpos($url, 'wordpress.com') !== false) {
                            $service = 'wordpress';
                        } elseif (strpos($url, 'facebook.com') !== false) {
                            $service = 'facebook';
                        } elseif (strpos($url, 'github.com') !== false) {
                            $service = 'github';
                        } elseif (strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) {
                            $service = 'twitter';
                        } elseif (strpos($url, 'linkedin.com') !== false) {
                            $service = 'linkedin';
                        } elseif (strpos($url, 'instagram.com') !== false) {
                            $service = 'instagram';
                        } elseif (strpos($url, 'amazon.com') !== false) {
                            $service = 'amazon';
                        }
                    }

                    if (!empty($url)) {
                        $socialLinks[] = [
                            'url'     => $url,
                            'service' => $service,
                            'label'   => $label
                        ];
                    }
                }
            }
        }

        // Fallback: Parse social links from innerHTML
        if (empty($socialLinks) && !empty($innerHTML)) {
            preg_match_all('/<li[^>]*class="[^"]*wp-social-link[^"]*"[^>]*>.*?<a[^>]*href=["\']([^"\']*)["\'][^>]*(?:aria-label=["\']([^"\']*)["\'])?[^>]*>.*?<\/a>.*?<\/li>/s', $innerHTML, $matches);

            if (!empty($matches[1])) {
                foreach ($matches[0] as $index => $match) {
                    $url = $matches[1][$index];
                    $label = $matches[2][$index] ?? '';

                    // Determine service from class or URL
                    $service = 'link';
                    if (strpos($match, 'wp-social-link-wordpress') !== false || strpos($url, 'wordpress.org') !== false || strpos($url, 'wordpress.com') !== false) {
                        $service = 'wordpress';
                    } elseif (strpos($match, 'wp-social-link-facebook') !== false || strpos($url, 'facebook.com') !== false) {
                        $service = 'facebook';
                    } elseif (strpos($match, 'wp-social-link-github') !== false || strpos($url, 'github.com') !== false) {
                        $service = 'github';
                    } elseif (strpos($match, 'wp-social-link-twitter') !== false || strpos($url, 'twitter.com') !== false || strpos($url, 'x.com') !== false) {
                        $service = 'twitter';
                    } elseif (strpos($match, 'wp-social-link-linkedin') !== false || strpos($url, 'linkedin.com') !== false) {
                        $service = 'linkedin';
                    } elseif (strpos($match, 'wp-social-link-instagram') !== false || strpos($url, 'instagram.com') !== false) {
                        $service = 'instagram';
                    } elseif (strpos($match, 'wp-social-link-amazon') !== false || strpos($url, 'amazon.com') !== false) {
                        $service = 'amazon';
                    }

                    $socialLinks[] = [
                        'url'     => $url,
                        'service' => $service,
                        'label'   => $label
                    ];
                }
            }
        }

        // Render social links using image icons or better text fallbacks
        if (!empty($socialLinks)) {
            foreach ($socialLinks as $link) {
                $iconHtml = $this->getSocialIconHtml($link['service'], $link['label']);

                $socialHtml .= '<a href="' . htmlspecialchars($link['url']) . '" style="display: inline-block; margin: 0 5px; text-decoration: none;">';
                $socialHtml .= $iconHtml;
                $socialHtml .= '</a>';
            }
        }

        $socialHtml .= '</div>';

        return $this->wrapInTable($socialHtml);
    }

    /**
     * Get social media icon HTML (with better styling)
     */
    private function getSocialIconHtml($service, $label = '')
    {
        // Get background color for each service
        $colors = [
            'facebook'  => '#1877f2',
            'twitter'   => '#1da1f2',
            'linkedin'  => '#0077b5',
            'instagram' => '#e4405f',
            'github'    => '#181717',
            'wordpress' => '#21759b',
            'amazon'    => '#ff9900',
            'link'      => '#0073aa'
        ];

        $bgColor = $colors[$service] ?? '#0073aa';
        $alt = $label ?: ucfirst($service);

        // Use service-specific icon rendering
        $iconContent = $this->getSocialIconSVG($service);

        return '<span style="display: inline-block; width: 44px; height: 44px; background-color: ' . $bgColor . '; border-radius: 50%; text-align: center; padding: 10px; box-sizing: border-box;" title="' . htmlspecialchars($alt) . '">' . $iconContent . '</span>';
    }

    /**
     * Get social media icon SVG or text representation
     */
    private function getSocialIconSVG($service)
    {
        // Simple SVG icons as inline data
        $icons = [
            'facebook'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'twitter'   => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'linkedin'  => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'instagram' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
            'github'    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
            'wordpress' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M21.469 6.825c.84 1.537 1.318 3.3 1.318 5.175 0 3.979-2.156 7.456-5.363 9.325l3.295-9.527c.615-1.54.82-2.771.82-3.864 0-.405-.026-.78-.07-1.11zm-7.981.105c.647-.03 1.232-.105 1.232-.105.582-.075.514-.93-.067-.899 0 0-1.755.135-2.88.135-1.064 0-2.85-.15-2.85-.15-.585-.03-.661.855-.075.885 0 0 .54.061 1.125.09l1.68 4.605-2.37 7.08L5.354 6.9c.649-.03 1.234-.1 1.234-.1.585-.075.516-.93-.065-.896 0 0-1.746.138-2.874.138-.2 0-.438-.008-.69-.015C4.911 3.15 8.235 1.215 12 1.215c2.809 0 5.365 1.072 7.286 2.833-.046-.003-.091-.009-.141-.009-1.06 0-1.812.923-1.812 1.914 0 .89.513 1.643 1.06 2.531.411.72.89 1.643.89 2.977 0 .915-.354 1.994-.821 3.479l-1.075 3.585-3.9-11.61.001.014zM12 22.784c-1.059 0-2.081-.153-3.048-.437l3.237-9.406 3.315 9.087c.024.053.05.101.078.149-1.12.393-2.325.607-3.582.607zM1.211 12c0-1.564.336-3.05.935-4.39L7.29 21.709C3.694 19.96 1.212 16.271 1.212 12zm10.785-10.784C6.596 1.215 1.214 6.597 1.214 12s5.382 10.785 10.784 10.785S22.784 17.403 22.784 12 17.402 1.215 11.996 1.215z"/></svg>',
            'amazon'    => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M14.465 11.813c-1.75-1.297-4.056-2.017-6.121-2.017-2.766 0-5.264 1.032-7.153 2.742-.207.183-.371.322-.559.322-.188 0-.316-.128-.316-.316 0-.184.18-.404.484-.672C2.821 9.84 5.831 8.621 9.071 8.621c2.766 0 5.262 1.031 7.151 2.742.211.183.375.322.563.322.184 0 .312-.128.312-.316 0-.184-.176-.404-.484-.672zm3.438 1.227c-.133.027-.27.04-.402.04-.297 0-.559-.09-.805-.274-1.367-.992-3.164-1.547-4.93-1.547-2.465 0-4.742 1.051-6.562 2.871-.188.183-.316.304-.508.304-.188 0-.316-.12-.316-.308 0-.184.176-.402.488-.672 1.98-1.96 4.621-3.039 7.465-3.039 2.027 0 3.926.582 5.504 1.684.238.164.375.402.375.672 0 .238-.188.5-.309.524v-.003zm4.016-3.863c-.863 0-1.637.504-1.875 1.297-.231.77.13 1.617.895 1.895.691.25 1.457-.145 1.703-.836.227-.652-.012-1.391-.637-1.746-.18-.094-.387-.137-.586-.16-.164-.012-.329-.012-.5.039.004.004.004-.012.004-.016v-.012c.238-.348.54-.602.984-.707.43-.097.851-.016 1.262.16.199.082.379.211.578.32-.012.012-.027.027-.043.043-.125.133-.266.273-.398.402-.145.148-.273.297-.437.399-.035.023-.074.046-.113.05-.039.008-.102-.008-.125-.035-.098-.098-.207-.195-.309-.293-.543-.52-1.254-.742-2.055-.742-.012-.012-.012 0-.012.012zM24 17.887c-2.059 1.52-4.121 3.031-6.18 4.551-.078.059-.164.113-.258.16-.586.305-1.199.031-1.199-.559v-8.664c0-.129.047-.266.129-.383.078-.102.172-.195.277-.258 2.078-1.523 4.156-3.043 6.234-4.566.074-.054.156-.109.242-.152.375-.195.797-.094 1.016.258.094.145.117.297.117.457v8.765c0 .129-.039.262-.129.39h-.004z"/></svg>',
            'link'      => '<svg width="24" height="24" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>'
        ];

        return $icons[$service] ?? $icons['link'];
    }

    /**
     * Get color from WordPress color slug
     */
    private function getColorFromSlug($slug)
    {
        // Debug: Uncomment to see what colors your theme provides
        // $this->debugThemeColors();

        // First, try to get from FluentCRM Helper (which reads theme.json and editor-color-palette)
        static $colorMap = null;

        if ($colorMap === null) {
            $colorMap = [];

            // Get theme colors using the same method as AdminMenu.php
            if (class_exists('\FluentCrm\App\Services\Helper')) {
                $themeColors = \FluentCrm\App\Services\Helper::getThemeColorPalette();
                if (!empty($themeColors)) {
                    foreach ($themeColors as $colorData) {
                        if (isset($colorData['slug']) && isset($colorData['color'])) {
                            $colorMap[$colorData['slug']] = $colorData['color'];
                        }
                    }
                }

                // Also get theme preferences
                $themePref = \FluentCrm\App\Services\Helper::getThemePrefScheme();
                if (!empty($themePref['colors'])) {
                    foreach ($themePref['colors'] as $colorData) {
                        if (isset($colorData['slug']) && isset($colorData['color'])) {
                            $colorMap[$colorData['slug']] = $colorData['color'];
                        }
                    }
                }
            }
        }

        // Check our color map first
        if (isset($colorMap[$slug])) {
            return $colorMap[$slug];
        }

        // Try to get theme color from WordPress theme.json or global settings
        if (function_exists('wp_get_global_settings')) {
            $settings = wp_get_global_settings();
            if (!empty($settings['color']['palette']['theme'])) {
                foreach ($settings['color']['palette']['theme'] as $color) {
                    if (isset($color['slug']) && $color['slug'] === $slug && !empty($color['color'])) {
                        return $color['color'];
                    }
                }
            }
        }

        // Try WP_Theme_JSON for block themes
        if (class_exists('WP_Theme_JSON_Resolver')) {
            $theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
            if ($theme_json) {
                $settings = $theme_json->get_settings();
                if (!empty($settings['color']['palette'])) {
                    foreach ($settings['color']['palette'] as $palette) {
                        if (isset($palette['slug']) && $palette['slug'] === $slug && !empty($palette['color'])) {
                            return $palette['color'];
                        }
                    }
                }
            }
        }

        // Fallback: Common WordPress and popular theme colors
        $colors = [
            // Theme palette colors (adjust these based on your active theme)
            // Twenty Twenty-Three defaults
            'theme-palette-color-1' => '#000000', // Base/Black
            'theme-palette-color-2' => '#6f42c1', // Purple
            'theme-palette-color-3' => '#007cba', // Blue
            'theme-palette-color-4' => '#16a085', // Teal
            'theme-palette-color-5' => '#e74c3c', // Red
            'theme-palette-color-6' => '#f39c12', // Orange
            'theme-palette-color-7' => '#ffffff', // White
            'theme-palette-color-8' => '#f5f5f5', // Light Gray
            'theme-palette-color-9' => '#cccccc', // Gray

            // Standard WordPress colors
            'black'                 => '#000000',
            'white'                 => '#ffffff',
            'primary'               => '#0073aa',
            'secondary'             => '#23282d',
            'tertiary'              => '#F0F0F1',

            // Common named colors
            'red'                   => '#e74c3c',
            'blue'                  => '#3498db',
            'green'                 => '#2ecc71',
            'yellow'                => '#f1c40f',
            'orange'                => '#e67e22',
            'purple'                => '#9b59b6',
            'cyan'                  => '#1abc9c',
            'vivid-red'             => '#cf2e2e',
            'vivid-orange'          => '#ff6900',
            'vivid-cyan-blue'       => '#0693e3',
            'vivid-green-cyan'      => '#00d084',
            'vivid-purple'          => '#9b51e0',
            'luminous-vivid-amber'  => '#fcb900',
            'luminous-vivid-orange' => '#ff6900',
            'light-green-cyan'      => '#7bdcb5',
            'pale-pink'             => '#f78da7',
            'pale-cyan-blue'        => '#8ed1fc',
        ];

        return $colors[$slug] ?? '#0073aa';
    }

    /**
     * Get font size from WordPress font size slug
     */
    private function getFontSizeFromSlug($slug)
    {
        static $fontSizeMap = null;

        if ($fontSizeMap === null) {
            $fontSizeMap = [];

            // Try wp_get_global_settings (WordPress 5.9+)
            if (function_exists('wp_get_global_settings')) {
                $settings = wp_get_global_settings();
                if (!empty($settings['typography']['fontSizes'])) {
                    foreach ($settings['typography']['fontSizes'] as $size) {
                        if (isset($size['slug']) && isset($size['size'])) {
                            $fontSizeMap[$size['slug']] = $size['size'];
                        }
                    }
                }
            }

            // Try WP_Theme_JSON for block themes
            if (empty($fontSizeMap) && class_exists('WP_Theme_JSON_Resolver')) {
                $theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
                if ($theme_json) {
                    $settings = $theme_json->get_settings();
                    if (!empty($settings['typography']['fontSizes'])) {
                        foreach ($settings['typography']['fontSizes'] as $size) {
                            if (isset($size['slug']) && isset($size['size'])) {
                                $fontSizeMap[$size['slug']] = $size['size'];
                            }
                        }
                    }
                }
            }

            // Fallback presets
            if (empty($fontSizeMap)) {
                $fontSizeMap = [
                    'small'       => '14px',
                    'medium'      => '18px',
                    'large'       => '20px',
                    'x-large'     => '28px',
                    'extra-small' => '12px',
                    'extra-large' => '32px',
                    'huge'        => '42px'
                ];
            }
        }

        return $fontSizeMap[$slug] ?? null;
    }

    /**
     * Get font family from WordPress font family slug or return as-is
     */
    private function getFontFamilyFromSlug($fontFamily)
    {
        // If it looks like a font stack (contains comma), return as-is
        if (strpos($fontFamily, ',') !== false) {
            return $fontFamily;
        }

        static $fontFamilyMap = null;

        if ($fontFamilyMap === null) {
            $fontFamilyMap = [];

            // Try wp_get_global_settings (WordPress 5.9+)
            if (function_exists('wp_get_global_settings')) {
                $settings = wp_get_global_settings();
                if (!empty($settings['typography']['fontFamilies'])) {
                    foreach ($settings['typography']['fontFamilies'] as $family) {
                        if (isset($family['slug']) && isset($family['fontFamily'])) {
                            $fontFamilyMap[$family['slug']] = $family['fontFamily'];
                        }
                    }
                }
            }

            // Try WP_Theme_JSON for block themes
            if (empty($fontFamilyMap) && class_exists('WP_Theme_JSON_Resolver')) {
                $theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
                if ($theme_json) {
                    $settings = $theme_json->get_settings();
                    if (!empty($settings['typography']['fontFamilies'])) {
                        foreach ($settings['typography']['fontFamilies'] as $family) {
                            if (isset($family['slug']) && isset($family['fontFamily'])) {
                                $fontFamilyMap[$family['slug']] = $family['fontFamily'];
                            }
                        }
                    }
                }
            }

            // Common font family presets
            $fontFamilyMap = array_merge([
                'system-ui'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
                'system'     => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
                'arial'      => 'Arial, Helvetica, sans-serif',
                'helvetica'  => '"Helvetica Neue", Helvetica, Arial, sans-serif',
                'times'      => '"Times New Roman", Times, serif',
                'times-new-roman' => '"Times New Roman", Times, serif',
                'georgia'    => 'Georgia, serif',
                'courier'    => '"Courier New", Courier, monospace',
                'courier-new' => '"Courier New", Courier, monospace',
                'verdana'    => 'Verdana, Geneva, sans-serif',
                'tahoma'     => 'Tahoma, Geneva, sans-serif',
                'trebuchet'  => '"Trebuchet MS", Helvetica, sans-serif',
                'trebuchet-ms' => '"Trebuchet MS", Helvetica, sans-serif',
                'palatino'   => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
                'garamond'   => 'Garamond, serif',
                'impact'     => 'Impact, Charcoal, sans-serif',
                'comic-sans' => '"Comic Sans MS", cursive, sans-serif',
                'comic-sans-ms' => '"Comic Sans MS", cursive, sans-serif',
                'monospace'  => 'Monaco, "Lucida Console", Courier, monospace'
            ], $fontFamilyMap);
        }

        // Return mapped font family or original value
        return $fontFamilyMap[$fontFamily] ?? $fontFamily;
    }

    /**
     * Resolve Gutenberg font-family values to email-safe values.
     */
    private function resolveFontFamilyValue($fontFamilyValue)
    {
        $value = trim((string)$fontFamilyValue);
        if ($value === '') {
            return '';
        }

        if (strpos($value, 'var:preset|font-family|') === 0) {
            return $this->getFontFamilyFromSlug(substr($value, strlen('var:preset|font-family|')));
        }

        if (preg_match('/^var\\(--wp--preset--font-family--([a-z0-9-]+)\\)$/i', $value, $matches)) {
            return $this->getFontFamilyFromSlug($matches[1]);
        }

        if (strpos($value, 'var(') === 0) {
            return $value;
        }

        return $this->getFontFamilyFromSlug($value);
    }

    /**
     * Debug helper: Log all theme colors (for development only)
     * Uncomment the call in getColorFromSlug() to use
     */
    private function debugThemeColors()
    {
        static $logged = false;
        if ($logged) return;

        error_log('=== THEME COLORS DEBUG ===');

        // Check wp_get_global_settings
        if (function_exists('wp_get_global_settings')) {
            $settings = wp_get_global_settings();
            error_log('Global Settings Colors: ' . print_r($settings['color'] ?? 'none', true));
        }

        // Check WP_Theme_JSON_Resolver
        if (class_exists('WP_Theme_JSON_Resolver')) {
            $theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
            if ($theme_json) {
                $settings = $theme_json->get_settings();
                error_log('Theme JSON Colors: ' . print_r($settings['color']['palette'] ?? 'none', true));
            }
        }

        $logged = true;
    }

    /**
     * Wrap content in email-safe table structure
     */
    private function wrapInTable($content, $atts = [])
    {
        if (empty(trim($content))) {
            return '';
        }

        $atts = (array)$atts;

        $align = Arr::get($atts, 'align', '');

        $tableClass = 'la-default';

        if ($align) {
            $tableClass = 'la-' . esc_attr($align);
        }

        $tableAlign = Arr::get($atts, 'fcrmTableAlign', '');
        if (!$tableAlign && in_array($align, ['left', 'center', 'right'], true)) {
            $tableAlign = $this->getDirectionalTextAlign($align);
        }

        $tableAlignMarkup = $tableAlign ? ' align="' . esc_attr($tableAlign) . '"' : '';

        $tableWidth = trim((string)Arr::get($atts, 'fcrmTableWidth', '100%'));
        $tableWidthMarkup = '';
        if ($tableWidth !== '' && strtolower($tableWidth) !== 'auto') {
            $tableWidthMarkup = ' width="' . esc_attr($tableWidth) . '"';
        }

        $tdClass = 'la-column';
        if (!empty($atts['is_root'])) {
            $tdClass = 'la-root-column';
        }

        $tdId = Arr::get($atts, 'td_id', '');

        return <<<HTML
<table role="presentation" class="$tableClass"$tableWidthMarkup$tableAlignMarkup cellspacing="0" cellpadding="0" border="0">
    <tr>
        <td id="$tdId" class="$tdClass">
            {$content}
        </td>
    </tr>
</table>
HTML;
    }

    private function resolveFontSizeValue($slugOrSize)
    {
        $value = trim((string)$slugOrSize);
        if ($value === '') {
            return '';
        }

        // If a WP preset CSS var is passed, extract its slug for proper resolution.
        if (preg_match('/^var\\(--wp--preset--font-size--([a-z0-9-]+)\\)$/i', $value, $matches)) {
            $value = $matches[1];
        } elseif (strpos($value, 'var(') === 0) {
            // Keep unknown CSS var expressions untouched.
            return $value;
        }

        // Already an explicit CSS size.
        if (preg_match('/^-?\d+(\.\d+)?(px|em|rem|%|pt|vh|vw)$/i', $value)) {
            return $value;
        }

        // Custom defaults in block editor helper (fc-small, fc-medium, etc).
        $fontPresets = BlockEditorHelper::getDefaultPreset('font-size');
        foreach ((array)$fontPresets as $preset) {
            if (!is_array($preset)) {
                continue;
            }
            if (Arr::get($preset, 'slug') === $value && Arr::get($preset, 'size')) {
                return Arr::get($preset, 'size');
            }
        }

        // Theme/WordPress presets (small, medium, large, x-large, etc).
        if (class_exists('\FluentCrm\App\Services\Helper')) {
            $themeFontSizes = Helper::getThemeFontSizes();
            foreach ((array)$themeFontSizes as $preset) {
                if (!is_array($preset)) {
                    continue;
                }
                if (Arr::get($preset, 'slug') !== $value || !Arr::get($preset, 'size')) {
                    continue;
                }

                $size = Arr::get($preset, 'size');
                if (is_numeric($size)) {
                    return $size . 'px';
                }

                return (string)$size;
            }
        }

        // Fallback to WP CSS variable naming.
        return 'var(--wp--preset--font-size--' . $value . ')';
    }

    /**
     * Generate complete email HTML with wrapper
     */
    public function generateEmailHtml($content, $title = '')
    {
        $parsedContent = $this->parse($content);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$title}</title>
    <style type="text/css">
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
        }
        table {
            border-collapse: collapse;
        }
        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        a {
            color: #0073aa;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" class="email-container" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff;">
                    <tr>
                        <td style="padding: 40px 30px;">
                            {$parsedContent}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

// Usage Example:
/*
$parser = new GutenbergEmailParser();

// Option 1: Parse blocks only (returns HTML fragment)
$post_content = get_post_field('post_content', $post_id);
$emailHtml = $parser->parse($post_content);

// Option 2: Generate complete email HTML with wrapper
$completeEmail = $parser->generateEmailHtml($post_content, 'Email Title');

// Send email
wp_mail($to, $subject, $completeEmail, ['Content-Type: text/html; charset=UTF-8']);
*/
