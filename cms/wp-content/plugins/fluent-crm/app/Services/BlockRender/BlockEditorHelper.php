<?php


namespace FluentCrm\App\Services\BlockRender;


use FluentCrm\Framework\Support\Arr;

class BlockEditorHelper
{
    public static function getStyleDefauls()
    {
        return [
            'spacing'     => [
                [
                    'name' => '2X-Small',
                    'slug' => 'fc-xx-small',
                    'size' => '5px',
                ],
                [
                    'name' => 'X-Small',
                    'slug' => 'fc-x-small',
                    'size' => '10px',
                ],
                [
                    'name' => 'Small',
                    'slug' => 'fc-small',
                    'size' => '14px',
                ],
                [
                    'name' => 'Medium',
                    'slug' => 'fc-medium',
                    'size' => '20px',
                ],
                [
                    'name' => 'Large',
                    'slug' => 'fc-large',
                    'size' => '30px',
                ],
                [
                    'name' => 'X-Large',
                    'slug' => 'fc-x-large',
                    'size' => '45px',
                ],
                [
                    'name' => '2X-Large',
                    'slug' => 'fc-xx-large',
                    'size' => '60px',
                ],
            ],
            'font-family' => [
                [
                    'name'       => 'System UI',
                    'slug'       => 'system-ui',
                    'fontFamily' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
                ],
                [
                    'name'       => 'Arial',
                    'slug'       => 'arial',
                    'fontFamily' => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
                ],
                [
                    'name'       => 'Georgia',
                    'slug'       => 'georgia',
                    'fontFamily' => "Georgia, Times, 'Times New Roman', serif",
                ],
                [
                    'name'       => 'Helvetica',
                    'slug'       => 'helvetica',
                    'fontFamily' => "Helvetica, Arial, Verdana, sans-serif",
                ],
                [
                    'name'       => 'Courier New',
                    'slug'       => 'courier-new',
                    'fontFamily' => "'Courier New', Courier, 'Lucida Sans Typewriter', monospace",
                ],
                [
                    'name'       => 'Times New Roman',
                    'slug'       => 'times-new-roman',
                    'fontFamily' => "'Times New Roman', Times, Baskerville, Georgia, serif",
                ],
                [
                    'name'       => 'Trebuchet MS',
                    'slug'       => 'trebuchet-ms',
                    'fontFamily' => "'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', Tahoma, sans-serif",
                ],
                [
                    'name'       => 'Verdana',
                    'slug'       => 'verdana',
                    'fontFamily' => "Verdana, Geneva, sans-serif",
                ],
            ],
            'font-size'   => [
                [
                    'name' => 'Small',
                    'slug' => 'fc-small',
                    'size' => '13px',
                ],
                [
                    'name' => 'Regular',
                    'slug' => 'fc-regular',
                    'size' => '16px',
                ],
                [
                    'name' => 'Medium',
                    'slug' => 'fc-medium',
                    'size' => '18px',
                ],
                [
                    'name' => 'Large',
                    'slug' => 'fc-large',
                    'size' => '26px',
                ],
                [
                    'name' => 'Extra Large',
                    'slug' => 'fc-x-large',
                    'size' => '32px',
                ],
            ],
            'color'       => [
                [
                    'name'  => 'Black',
                    'slug'  => 'black',
                    'color' => '#000000'
                ],
                [
                    'name'  => 'Cyan bluish gray',
                    'slug'  => 'cyan-bluish-gray',
                    'color' => '#abb8c3'
                ],
                [
                    'name'  => 'White',
                    'slug'  => 'white',
                    'color' => '#ffffff'
                ],
                [
                    'name'  => 'Pale pink',
                    'slug'  => 'pale-pink',
                    'color' => '#f78da7'
                ],
                [
                    'name'  => 'Vivid red',
                    'slug'  => 'vivid-red',
                    'color' => '#cf2e2e'
                ],
                [
                    'name'  => 'Luminous vivid orange',
                    'slug'  => 'luminous-vivid-orange',
                    'color' => '#ff6900'
                ],
                [
                    'name'  => 'Luminous vivid amber',
                    'slug'  => 'luminous-vivid-amber',
                    'color' => '#fcb900'
                ],
                [
                    'name'  => 'Light green cyan',
                    'slug'  => 'light-green-cyan',
                    'color' => '#7bdcb5'
                ],
                [
                    'name'  => 'Vivid green cyan',
                    'slug'  => 'vivid-green-cyan',
                    'color' => '#00d084'
                ],
                [
                    'name'  => 'Pale cyan blue',
                    'slug'  => 'pale-cyan-blue',
                    'color' => '#8ed1fc'
                ],
                [
                    'name'  => 'Vivid cyan blue',
                    'slug'  => 'vivid-cyan-blue',
                    'color' => '#0693e3'
                ],
                [
                    'name'  => 'Vivid purple',
                    'slug'  => 'vivid-purple',
                    'color' => '#9b51e0'
                ]
            ]
        ];
    }

    public static function getDefaultPreset($key = '')
    {
        $defaults = self::getStyleDefauls();

        if ($key && isset($defaults[$key])) {
            return $defaults[$key];
        }

        return [];
    }

    public static function getStyleDefaultPresets()
    {
        $defaults = self::getStyleDefauls();

        $cssProps = '';

        foreach ($defaults['spacing'] as $item) {
            $cssProps .= '--wp--preset--spacing--' . $item['slug'] . ': ' . $item['size'] . ';';
        }

        // Keep core color preset variables available inside the editor canvas.
        // Without these, Gutenberg's link color styles can resolve to empty values.
        foreach ($defaults['color'] as $item) {
            $cssProps .= '--wp--preset--color--' . $item['slug'] . ': ' . $item['color'] . ';';
        }

        return $cssProps;
    }

    public static function replaceStyleSlugsWithValues($css = '')
    {
        if (!$css) {
            return '';
        }

        $defaults = self::getStyleDefauls();

        $replaces = [
            'var(--wp--preset--color--white)' => 'white',
        ];

        foreach ($defaults as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($key === 'spacing') {
                        $replaces['var(--wp--preset--spacing--' . $item['slug'] . ')'] = $item['size'];
                    } else if ($key === 'font-size') {
                        $replaces['var(--fcom--font--size--' . $item['slug'] . ')'] = $item['size'];
                        $replaces['var(--wp--preset--font-size--' . $item['slug'] . ')'] = $item['size'];
                    } else if ($key === 'color') {
                        $replaces['var(--fcom--color--' . $item['slug'] . ')'] = $item['color'];
                        $replaces['var(--wp--preset--color--' . $item['slug'] . ')'] = $item['color'];
                    } else if ($key === 'font-family') {
                        $replaces['var(--wp--preset--font-family--' . $item['slug'] . ')'] = $item['fontFamily'];
                    }
                }
            }
        }

        // Include active theme font size presets too (small, medium, large, etc).
        $themeFontSizes = \FluentCrm\App\Services\Helper::getThemeFontSizes();
        foreach ((array)$themeFontSizes as $themeFontSize) {
            if (!is_array($themeFontSize) || empty($themeFontSize['slug']) || !isset($themeFontSize['size'])) {
                continue;
            }

            $size = $themeFontSize['size'];
            if (is_numeric($size)) {
                $size = $size . 'px';
            }

            $replaces['var(--wp--preset--font-size--' . $themeFontSize['slug'] . ')'] = $size;
        }

        return str_replace(array_keys($replaces), array_values($replaces), $css);
    }

    public static function renderAtts($atts = [], $echo = false)
    {
        $rendered = '';
        foreach ($atts as $key => $value) {
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            $value = esc_attr($value);
            $key = esc_attr($key);
            $rendered .= $key . '="' . $value . '" ';
        }

        if ($echo) {
            echo $rendered;
        }

        return $rendered;
    }

    public static function renderStyles($styles = [], $echo = false)
    {
        $rendered = '';
        foreach ($styles as $key => $value) {
            if (!$value) {
                continue;
            }
            $rendered .= $key . ':' . $value . ';';
        }

        if ($echo) {
            echo esc_attr($rendered);
        }

        return esc_attr($rendered);
    }

    public static function getBorderColor($attrs = [], $default = '')
    {
        if ($borderColor = Arr::get($attrs, 'borderColor', '')) {
            $color = 'var(--fcom--color--' . $borderColor . ')';
            return self::replaceStyleSlugsWithValues($color);
        }

        $color = $default;
        if ($borderConfig = Arr::get($attrs, 'style.border', [])) {
            $color = Arr::get($borderConfig, 'color', '');
        }

        return $color;
    }

    public static function getDynamicCssForEditor()
    {
        $fonts = self::getDefaultPreset('font-family');

        $css = '';

        foreach ($fonts as $font) {
            $css .= '.editor-styles-wrapper .has-' . $font['slug'] . '-font-family { font-family: ' . $font['fontFamily'] . ' !important; }';
        }

        return $css;

    }

    public static function getDefaultPrefConfig()
    {
        return [
            'content_width'          => 700,
            'content_border_radius'  => '0px',
            'content_padding_top'    => '20px', // matched
            'content_padding_right'  => '20px', // matched
            'content_padding_bottom' => '20px', // matched
            'content_padding_left'   => '20px', // matched
            'content_margin_top'     => '20px', // new
            'content_margin_bottom'  => '20px', // new

            'headings_font_family' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'", // matched
            'content_font_family'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'", // matched

            'body_bg_color'    => '#FAFAFA', // matched
            'content_bg_color' => '#FFFFFF', // matched

            'headings_color'   => '#202020', // matched
            'text_color'       => '#202020', // matched
            'link_color'       => '#0693e3',
            'link_color_hover' => '', // not used now

            'paragraph_font_size'   => '16px', // matched
            'paragraph_line_height' => '1.5', // matched

            'footer_text_color' => '#202020', // matched,

            'design_template' => 'simple'
        ];
    }
}
