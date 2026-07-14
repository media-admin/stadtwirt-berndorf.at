<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Services\Sanitize;
use FluentCrm\App\Services\Libs\Emogrifier\Emogrifier;
use FluentCrm\Framework\Support\Arr;

/**
 *  EmailDesignTemplates Class
 *
 * For handling email design templates
 *
 * @package FluentCrm\App\Hooks
 *
 * @version 1.0.0
 */
class EmailDesignTemplates
{

    public function register()
    {
        add_filter('fluent_crm/email-design-template-block_editor', [$this, 'addBlockEditorTemplate'], 10, 3);
        add_filter('fluent_crm/email-design-template-simple', [$this, 'addBlockEditorTemplate'], 10, 3);
        add_filter('fluent_crm/email-design-template-plain', [$this, 'addBlockEditorTemplate'], 10, 3);
        add_filter('fluent_crm/email-design-template-classic', [$this, 'addBlockEditorTemplate'], 10, 3);


        add_filter('fluent_crm/email-design-template-raw_classic', [$this, 'addRawClassicTemplate'], 10, 3);
        add_filter('fluent_crm/email-design-template-web_preview', [$this, 'addWebPreviewTemplate'], 10, 3);
    }

    public function addBlockEditorTemplate($emailBody, $templateData, $campaign)
    {
        $templateData = $this->filterTemplateData($templateData);
        $templateData['email_body'] = $emailBody;

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.block_editor.Template', $templateData);
        $emailBody = $emailBody->__toString();

        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    /**
     * @param string $emailBody
     * @param array $templateData
     * @param \FluentCrm\App\Models\Campaign $campaign
     * @return string
     */
    public function addPlainTemplate($emailBody, $templateData, $campaign)
    {
        $templateData = $this->filterTemplateData($templateData);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.plain.Template', $templateData);
        $emailBody = $emailBody->__toString();

        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    /**
     * @param string $emailBody
     * @param array $templateData
     * @param \FluentCrm\App\Models\Campaign $campaign
     * @return string
     */
    public function addSimpleTemplate($emailBody, $templateData, $campaign)
    {
        if (empty($templateData['config']['body_bg_color'])) {
            $templateData['config']['body_bg_color'] = '#FAFAFA';
        }

        if (empty($templateData['config']['content_bg_color'])) {
            $templateData['config']['content_bg_color'] = '#ffffff';
        }

        $templateData = $this->filterTemplateData($templateData);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.simple.Template', $templateData);
        $emailBody = $emailBody->__toString();
        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    /**
     * @param string $emailBody
     * @param array $templateData
     * @param \FluentCrm\App\Models\Campaign $campaign
     * @return string
     */
    public function addClassicTemplate($emailBody, $templateData, $campaign)
    {
        if (empty($templateData['config']['content_bg_color'])) {
            $templateData['config']['content_bg_color'] = '#ffffff';
        }

        $templateData = $this->filterTemplateData($templateData);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.classic.Template', $templateData);
        $emailBody = $emailBody->__toString();

        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    /**
     * @param string $emailBody
     * @param array $templateData
     * @param \FluentCrm\App\Models\Campaign $campaign
     * @return string
     */
    public function addRawClassicTemplate($emailBody, $templateData, $campaign)
    {
        $templateData = $this->filterTemplateData($templateData);

        $configDefault = [
            'content_width'         => '',
            'content_padding'       => '',
            'headings_font_family'  => '',
            'text_color'            => '',
            'link_color'            => '',
            'body_bg_color'         => '',
            'content_bg_color'      => '',
            'footer_text_color'     => '',
            'content_font_family'   => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
            'paragraph_color'       => '',
            'paragraph_font_size'   => '',
            'paragraph_font_family' => '',
            'paragraph_line_height' => '',
            'headings_color'        => ''
        ];

        $templateData['config'] = wp_parse_args($templateData['config'], $configDefault);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.raw_classic.Template', $templateData);
        $emailBody = $emailBody->__toString();
        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    public function addWebPreviewTemplate($emailBody, $templateData, $campaign)
    {
        $templateData = $this->filterTemplateData($templateData);

        $configDefault = [
            'content_width'         => '',
            'content_padding'       => '',
            'headings_font_family'  => '',
            'text_color'            => '',
            'link_color'            => '',
            'body_bg_color'         => '',
            'content_bg_color'      => '',
            'footer_text_color'     => '',
            'content_font_family'   => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'",
            'paragraph_color'       => '',
            'paragraph_font_size'   => '',
            'paragraph_font_family' => '',
            'paragraph_line_height' => '',
            'headings_color'        => ''
        ];

        $templateData['config'] = wp_parse_args($templateData['config'], $configDefault);

        $view = FluentCrm('view');
        $emailBody = $view->make('emails.web_preview.Template', $templateData);
        $emailBody = $emailBody->__toString();
        $emogrifier = new Emogrifier($emailBody);
        $emogrifier->disableInvisibleNodeRemoval();
        return $emogrifier->emogrify();
    }

    private function filterTemplateData($templateData)
    {
        $footerConfig = Arr::get($templateData, 'footer_config', []);
        $disableFooter = Arr::get($footerConfig, 'disable_footer');
        if ($disableFooter !== 'yes' && $disableFooter !== 'no') {
            $disableFooter = Arr::get($templateData, 'config.disable_footer');
        }

        if ($disableFooter == 'yes') {
            $templateData['footer_text'] = '';
        } else {
            $style = 'font-size: 13px; color: #202020;';
            if ($footerConfig) {
                $fontSize = Arr::get($footerConfig, 'font_size', 13) . 'px';
                $color = sanitize_hex_color(Arr::get($footerConfig, 'font_color', '#202020')) ?: '#202020';
                $backgroundColor = Arr::get($footerConfig, 'background_color', 'transparent');
                $paddingRaw = Arr::get($footerConfig, 'footer_padding');
                $safeBackgroundColor = sanitize_hex_color($backgroundColor);
                if ($backgroundColor === 'transparent') {
                    $safeBackgroundColor = 'transparent';
                }

                $safePadding = 20;
                if ($paddingRaw !== null && $paddingRaw !== '') {
                    $safePadding = min(80, max(0, intval($paddingRaw)));
                }

                $style = "font-size: {$fontSize}; color: {$color};";
                if ($safeBackgroundColor) {
                    $style .= " background-color: {$safeBackgroundColor};";
                }
                $style .= " padding: {$safePadding}px;";
                $templateData['footer_text'] = Sanitize::sanitizeFooterHtml($footerConfig['footer_content'] ?? '');
            }

            if($templateData['footer_text']) {
                $templateData['footer_text'] = "<div style='{$style}'>{$templateData['footer_text']}</div>";
            }
        }

        return $templateData;
    }

}
