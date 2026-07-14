<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\Template;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\Sanitize;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Http\Request\Request;

/**
 *  TemplateController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class TemplateController extends Controller
{
    public function templates(Request $request)
    {
        $order = $request->getSafe('order', 'sanitize_sql_orderby', 'desc');
        $orderBy = $request->getSafe('orderBy', 'sanitize_sql_orderby', 'ID');

        $templatesQuery = Template::emailTemplates(
            $request->get('types', ['publish', 'draft'])
        );

        if ($search = $request->getSafe('search')) {
            $templatesQuery->where('post_title', 'LIKE', '%' . $search . '%');
        }

        // Order the query results and paginate
        $templates = $templatesQuery
            ->orderBy($orderBy, $order)
            ->paginate();

        foreach ($templates as $template) {
            $template->design_template = get_post_meta($template->ID, '_design_template', true);
        }

        return $this->sendSuccess([
            'templates' => $templates
        ]);
    }

    public function template(Request $request, $templateId = 0)
    {
        $template = Template::find($templateId);

        if ($template) {
            $editType = get_post_meta($template->ID, '_edit_type', true);
            if (!$editType) {
                $editType = 'html';
            }

            $designTemplate = get_post_meta($template->ID, '_design_template', true);
            $templateConfig = get_post_meta($template->ID, '_template_config', true);

            if(!$templateConfig || !is_array($templateConfig)) {
                $templateConfig = [];
            }

            $footerSettings = get_post_meta($template->ID, '_footer_settings', true);
            $normalizedSettings = $this->normalizeTemplateSettings([
                'template_config' => $templateConfig,
                'footer_settings' => $footerSettings
            ], $designTemplate);

            $templateData = [
                'post_title'      => $template->post_title,
                'post_content'    => $template->post_content,
                'post_excerpt'    => $template->post_excerpt,
                'email_subject'   => get_post_meta($template->ID, '_email_subject', true),
                'edit_type'       => $editType,
                'design_template' => $designTemplate,
                'settings'        => $normalizedSettings
            ];

            /**
             * Filter the template data before editing.
             *
             * @since 2.6.51
             *
             * @param array  $templateData The data of the template being edited.
             * @param object $template     The template object.
             */
            $templateData = apply_filters('fluent_crm/editing_template_data', $templateData, $template);

        } else {
            $defaultTemplate = Helper::getDefaultEmailTemplate();
            $normalizedSettings = $this->normalizeTemplateSettings([
                'template_config' => Helper::getTemplateConfig($defaultTemplate),
                'footer_settings' => []
            ], $defaultTemplate);

            $templateData = [
                'post_title'      => '',
                'post_content'    => '',
                'post_excerpt'    => '',
                'email_subject'   => '',
                'edit_type'       => 'html',
                'design_template' => $defaultTemplate,
                'settings'        => $normalizedSettings
            ];
        }

        return $this->sendSuccess([
            'template' => $templateData
        ]);
    }

    public function create(Request $request)
    {
        if($templateId = $request->get('template_id')) {
            return $this->update($request, $templateId);
        }

        $templateData = Helper::parseArrayOrJson($this->request->get('template'));

        $designTemplate = Arr::get($templateData, 'design_template');
        if (!$designTemplate) {
            $designTemplate = Helper::getDefaultEmailTemplate();
            $templateData['design_template'] = $designTemplate;
        }

        $templateData['settings'] = $this->normalizeTemplateSettings(Arr::get($templateData, 'settings', []), $designTemplate);

        $postData = Arr::only($templateData, [
            'post_title',
            'post_content',
            'post_excerpt'
        ]);

        if(empty($postData['post_title'])) {
            $postData['post_title'] = 'Email Template @ '.current_time('mysql');
        }

        if (empty($templateData['email_subject'])) {
            $templateData['email_subject'] = $postData['post_title'];
        }

        if(empty($postData['post_excerpt'])) {
            $postData['post_excerpt'] =  '';
        }

        $postData['post_modified'] = current_time('mysql');
        $postData['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
        $postData['post_date'] = current_time('mysql');
        $postData['post_date_gmt'] = gmdate('Y-m-d H:i:s');
        $postData['post_type'] = fluentcrmTemplateCPTSlug();

        $templateId = wp_insert_post($postData);

        update_post_meta($templateId, '_email_subject', Arr::get($templateData, 'email_subject'));
        update_post_meta($templateId, '_edit_type', Arr::get($templateData, 'edit_type'));
        update_post_meta($templateId, '_template_config', Arr::get($templateData, 'settings.template_config', []));
        update_post_meta($templateId, '_footer_settings', Arr::get($templateData, 'settings.footer_settings', []));
        update_post_meta($templateId, '_design_template', $designTemplate);

        do_action('fluent_crm/email_template_created', $templateId, $templateData);

        return $this->sendSuccess([
            'message'     => __('Template successfully created', 'fluent-crm'),
            'template_id' => $templateId
        ]);
    }

    public function duplicate($templateId)
    {
        $template = Template::findOrFail($templateId);

        $postData = [
            'post_title'        => __('[Duplicate] ', 'fluent-crm') . $template['post_title'],
            'post_content'      => $template['post_content'],
            'post_excerpt'      => $template['post_excerpt'],
            'post_modified'     => current_time('mysql'),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
            'post_date'         => current_time('mysql'),
            'post_date_gmt'     => gmdate('Y-m-d H:i:s'),
            'post_type'         => fluentcrmTemplateCPTSlug(),
        ];

        $newTemplateId = wp_insert_post($postData);

        // Meta fields to copy over
        $metaKeys = [
            '_email_subject',
            '_edit_type',
            '_template_config',
            '_design_template',
            '_footer_settings'
        ];

        // Update post meta in a loop
        $this->copyMetaFields($templateId, $newTemplateId, $metaKeys);

        do_action('fluent_crm/email_template_duplicated', $newTemplateId, $template);

        return $this->sendSuccess([
            'message'     => __('Template successfully duplicated', 'fluent-crm'),
            'template_id' => $newTemplateId
        ]);
    }

    /**
     * Helper method to copy meta fields from one post to another
     */
    protected function copyMetaFields($oldPostId, $newPostId, $metaKeys)
    {
        foreach ($metaKeys as $metaKey) {
            update_post_meta($newPostId, $metaKey, get_post_meta($oldPostId, $metaKey, true));
        }
    }

    public function update(Request $request, $id)
    {
        $oldTemplate = Template::findOrFail($id);

        $templateData = Helper::parseArrayOrJson($this->request->get('template'));
        $designTemplate = Arr::get($templateData, 'design_template');
        if (!$designTemplate) {
            $designTemplate = get_post_meta($id, '_design_template', true) ?: Helper::getDefaultEmailTemplate();
            $templateData['design_template'] = $designTemplate;
        }

        $templateData['settings'] = $this->normalizeTemplateSettings(Arr::get($templateData, 'settings', []), $designTemplate);

        $footerSettings = Arr::get($templateData, 'settings.footer_settings');
        if($footerSettings) {
            if (($footerSettings['custom_footer'] == 'yes') && !Helper::hasComplianceText($footerSettings['footer_content'])) {
                return $this->sendError([
                    'message' => __('##crm.manage_subscription_url## or ##crm.unsubscribe_url## string is required for compliance. Please include unsubscription or manage subscription link', 'fluent-crm')
                ]);
            }
        }

        if(empty($templateData['post_title'])) {
            $templateData['post_title'] = 'Email template created at '.gmdate('Y-m-d H:i');
        }

        if(empty($templateData['email_subject'])) {
            $templateData['email_subject'] = 'Email template created at '.gmdate('Y-m-d H:i');
        }

        $postData = Arr::only($templateData, [
            'post_title',
            'post_content',
            'post_excerpt'
        ]);



        $postData['post_modified'] = current_time('mysql');
        $postData['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
        Template::where('ID', $id)->update($postData);

        update_post_meta($id, '_email_subject', Arr::get($templateData, 'email_subject'));
        update_post_meta($id, '_edit_type', Arr::get($templateData, 'edit_type'));
        update_post_meta($id, '_design_template', Arr::get($templateData, 'design_template'));
        update_post_meta($id, '_template_config', Arr::get($templateData, 'settings.template_config', []));
        update_post_meta($id, '_footer_settings', Arr::get($templateData, 'settings.footer_settings', []));

        $template = Template::findOrFail($id);

        do_action('fluent_crm/email_template_updated', $templateData, $template);

        return $this->sendSuccess([
            'message'     => __('Template successfully updated', 'fluent-crm'),
            'template_id' => $id
        ]);
    }

    public function handleBulkAction(Request $request)
    {
        $actionName = sanitize_text_field($request->get('action_name', ''));

        $templateIds = array_map('intval', (array)$request->get('template_ids', []));

        $templateIds = array_unique(array_filter($templateIds));

        $selectAllTemplates = filter_var($request->get('select_all'), FILTER_VALIDATE_BOOLEAN);

        if ($selectAllTemplates) {
            $templateIds = Template::pluck('id')->toArray();
        }

        $templateIds = array_filter($templateIds);
        if ($actionName == 'change_template_status') {
            $newStatus = sanitize_text_field($request->get('status', ''));
            if (!$newStatus) {
                return $this->sendError([
                    'message' => __('Please select status', 'fluent-crm')
                ]);
            }

            $templates = Template::whereIn('ID', $templateIds)->get();

            foreach ($templates as $template) {
                $oldStatus = $template->post_status;
                if ($oldStatus != $newStatus) {
                    $template->post_status = $newStatus;
                    $template->save();
                }
            }

            return [
                'message' => __('Status has been changed for the selected templates', 'fluent-crm')
            ];
        } else if ($actionName == 'delete_templates') {
            $templates = Template::whereIn('id', $templateIds)->get();

            foreach ($templates as $template) {
                wp_delete_post($template->ID, true);
            }

            return $this->sendSuccess([
                'message' => __('Selected Templates have been deleted permanently', 'fluent-crm'),
            ]);
        }

        return [
            'message' => __('invalid bulk action', 'fluent-crm')
        ];
    }

    public function delete(Request $request, $id)
    {
        $template = Template::findOrFail($id);

        wp_delete_post($template->ID, true);

        return $this->sendSuccess([
            'message' => __('The template has been deleted successfully.', 'fluent-crm')
        ]);
    }

    public function render()
    {
        $rendered = Template::findOrFail(
            $this->request->get('ID')
        )->render();

        return $this->sendSuccess($rendered);
    }

    public function allTemplates()
    {
        return $this->sendSuccess([
            'templates'  => Template::emailTemplates(['publish'])->orderBy('ID', 'desc')->get(),
            'smartcodes' => $this->smartCodes()
        ]);
    }

    public function getSmartCodes()
    {
        return $this->sendSuccess([
            'smartcodes' => $this->smartCodes()
        ]);
    }

    protected function smartCodes()
    {
        return Helper::getGlobalSmartCodes();
    }

    public function setGlobalStyle(Request $request)
    {
        $settings = $request->get('config', []);

        foreach ($settings as $settingKey => $setting) {
            $settings[$settingKey] = sanitize_text_field($setting);
        }

        fluentcrm_update_option('global_email_style_config', $settings);

        return [
            'message' => __('Global style settings have been updated', 'fluent-crm')
        ];
    }

    /**
     * Fetches built-in templates from cached locally
     * cached for 24 hours, then refreshed
     * @return 
     */
    public function getBuiltInTemplates()
    {
        $templates = fluentCrmPersistentCache('email_remote_templates', function () {
                return $this->loadRemoteTemplates();
            }, 60 * 60 * 24); // 24 hours

        // Return a success response with the formatted templates
        return $this->sendSuccess([
            'templates' => $templates
        ]);
    }

    /**
     * Downloads a single built-in template file and returns it without saving
     * it as a local email template.
     *
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return \FluentCrm\Framework\Http\Response\Response
     */
    public function getBuiltInTemplate(Request $request)
    {
        $fileUrl = esc_url_raw($request->get('file', ''));

        if (!$fileUrl || !$this->isAllowedRemoteTemplateUrl($fileUrl)) {
            return $this->sendError([
                'message' => __('Invalid template source URL', 'fluent-crm')
            ]);
        }

        $response = wp_remote_get($fileUrl, [
            'sslverify'           => true,
            'timeout'             => 20,
            'redirection'         => 0,
            'limit_response_size' => 1024 * 1024
        ]);

        if (is_wp_error($response)) {
            return $this->sendError([
                'message' => __('Unable to download the selected template. Please try again.', 'fluent-crm')
            ]);
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode < 200 || $responseCode >= 300) {
            return $this->sendError([
                'message' => __('Unable to download the selected template. Please try again.', 'fluent-crm')
            ]);
        }

        $templateData = Helper::parseArrayOrJson(wp_remote_retrieve_body($response));

        if (Arr::get($templateData, 'is_fc_template') !== 'yes') {
            return $this->sendError([
                'message' => __('The selected file is not a valid FluentCRM template.', 'fluent-crm')
            ]);
        }

        $template = $this->formatRemoteTemplateData($templateData);

        $hasVisualBuilderDesign = $template['design_template'] === 'visual_builder' && !empty($template['_visual_builder_design']);

        if (!$template['post_content'] && !$hasVisualBuilderDesign) {
            return $this->sendError([
                'message' => __('The selected template does not have any email content.', 'fluent-crm')
            ]);
        }

        return $this->sendSuccess([
            'message'  => __('Template has been inserted', 'fluent-crm'),
            'template' => $template
        ]);
    }

    /**
     * Restricts direct template downloads to trusted FluentCRM template hosts.
     *
     * @param string $url
     * @return bool
     */
    protected function isAllowedRemoteTemplateUrl($url)
    {
        $parsedUrl = wp_parse_url($url);

        if (empty($parsedUrl['scheme']) || empty($parsedUrl['host']) || $parsedUrl['scheme'] !== 'https') {
            return false;
        }

        $allowedHosts = [
            'fluentcrm.com',
            'www.fluentcrm.com',
            'wpmanageninja.com',
            'www.wpmanageninja.com'
        ];

        if (defined('FC_TEMPLATE_API_DOMAIN')) {
            $configuredHost = wp_parse_url(FC_TEMPLATE_API_DOMAIN, PHP_URL_HOST);
            if ($configuredHost) {
                $allowedHosts[] = strtolower($configuredHost);
            }
        }

        return in_array(strtolower($parsedUrl['host']), array_unique($allowedHosts), true);
    }

    /**
     * Normalizes remote JSON to the local template shape without creating a WP post.
     *
     * @param array $templateData
     * @return array
     */
    protected function formatRemoteTemplateData($templateData)
    {
        $designTemplate = sanitize_text_field(Arr::get($templateData, 'design_template'));
        if (!$designTemplate) {
            $designTemplate = Helper::getDefaultEmailTemplate();
        }

        $normalizedSettings = $this->normalizeTemplateSettings(Arr::get($templateData, 'settings', []), $designTemplate);

        return [
            'post_title'      => sanitize_text_field(Arr::get($templateData, 'post_title', '')),
            'post_content'    => Arr::get($templateData, 'post_content', ''),
            'post_excerpt'    => sanitize_textarea_field(Arr::get($templateData, 'post_excerpt', '')),
            'email_subject'   => sanitize_text_field(Arr::get($templateData, 'email_subject', '')),
            'edit_type'       => sanitize_text_field(Arr::get($templateData, 'edit_type', 'html')),
            'design_template' => $designTemplate,
            'settings'        => $normalizedSettings,
            '_visual_builder_design' => Arr::get($templateData, '_visual_builder_design')
        ];
    }

    /**
     * Normalize template settings with legacy footer disable compatibility.
     *
     * @param array $settings
     * @return array
     */
    protected function normalizeTemplateSettings($settings, $designTemplate = '')
    {
        $templateConfig = Arr::get($settings, 'template_config', []);
        if (!is_array($templateConfig)) {
            $templateConfig = [];
        }

        if (!$this->templateSupportsContentPadding($designTemplate)) {
            unset($templateConfig['content_padding']);
        } elseif (!isset($templateConfig['content_padding'])) {
            $templateConfig['content_padding'] = 20;
        }

        $footerSettings = Arr::get($settings, 'footer_settings', []);
        if (!is_array($footerSettings)) {
            $footerSettings = [];
        }

        $hasExplicitDisableFooter = array_key_exists('disable_footer', $footerSettings);
        $hasExplicitCustomFooter = array_key_exists('custom_footer', $footerSettings);

        $disableFooter = Arr::get($footerSettings, 'disable_footer');
        if ($disableFooter !== 'yes' && $disableFooter !== 'no') {
            $legacyDisable = Arr::get($templateConfig, 'disable_footer');
            $disableFooter = ($legacyDisable === 'yes' || $legacyDisable === 'no') ? $legacyDisable : 'no';
        }

        $customFooter = Arr::get($footerSettings, 'custom_footer');
        if ($customFooter !== 'yes' && $customFooter !== 'no') {
            $legacyFooterContent = Arr::get($footerSettings, 'footer_content', '');
            $customFooter = (is_string($legacyFooterContent) && trim(wp_strip_all_tags($legacyFooterContent)))
                ? 'yes'
                : 'no';
        }

        $footerSettings = wp_parse_args($footerSettings, [
            'custom_footer'    => 'no',
            'footer_content'   => '',
            'disable_footer'   => 'no',
            'font_size'        => 13,
            'font_color'       => '#202020',
            'background_color' => 'transparent',
            'footer_padding'   => 20
        ]);

        // Footer content is user-editable from a raw text mode; sanitize before persistence.
        $footerSettings['footer_content'] = Sanitize::sanitizeFooterHtml(Arr::get($footerSettings, 'footer_content', ''));

        $footerSettings['disable_footer'] = $disableFooter;
        $footerSettings['custom_footer'] = $customFooter;

        // Legacy imported templates may carry disable_footer in template_config without
        // explicit footer settings. Treat those as Global Footer instead of hidden footer.
        $isLegacyImportedDisabled = (
            !$hasExplicitDisableFooter &&
            !$hasExplicitCustomFooter &&
            $footerSettings['disable_footer'] === 'yes' &&
            Arr::get($templateConfig, 'disable_footer') === 'yes' &&
            $footerSettings['custom_footer'] !== 'yes' &&
            !trim(wp_strip_all_tags(Arr::get($footerSettings, 'footer_content', '')))
        );

        if ($isLegacyImportedDisabled) {
            $footerSettings['disable_footer'] = 'no';
            $footerSettings['custom_footer'] = 'no';
        }

        // Keep legacy key in sync during transition to avoid regressions in old readers.
        $templateConfig['disable_footer'] = $footerSettings['disable_footer'];

        return [
            'template_config' => $templateConfig,
            'footer_settings' => $footerSettings
        ];
    }

    /**
     * Raw classic editor templates only support font family and footer flags.
     *
     * @param string $designTemplate
     * @return bool
     */
    protected function templateSupportsContentPadding($designTemplate)
    {
        if ($designTemplate === 'raw_classic') {
            return false;
        }

        $templates = Helper::getEmailDesignTemplates();
        $template = Arr::get($templates, $designTemplate, []);

        return Arr::get($template, 'template_type') !== 'classic_editor';
    }

    /**
     * Fetches and formats email templates from a remote FluentCRM API endpoint.
     * This method makes an HTTP request to retrieve email templates from FluentCRM's public API.
     * It processes the response and formats the templates into a standardized structure.
     * @throws \WP_Error Logs error message if the API request fails
     * @return array
     * @access public
     */

    public function loadRemoteTemplates()
    {
        $restBase =  defined('FC_TEMPLATE_API_DOMAIN') ? FC_TEMPLATE_API_DOMAIN : 'https://fluentcrm.com';
        $restApi = $restBase.'/wp-json/wp/v2/email-templates?per_page=50';

        // Make a GET request to retrieve CRM templates
        $response = wp_remote_get($restApi, [
            'sslverify' => false,
        ]);

        // Check if the request resulted in an error
        if (is_wp_error($response)) {
            // Handle error
            error_log($response->get_error_message());
            return [];
        }

        // Decode the JSON response from the request
        $templateLists = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($templateLists)) {
            return [];
        }

        $formattedTemplates = [];

        foreach ($templateLists as $template) {
            if (!$template['template_json']) {
                // Skip if no template json
                continue;
            }
            $mediaURL = '';
            if ($template['featured_media'] != 0) {
                $mediaURL = $this->getMediaURL($template['featured_media'], $restApi);
            }
            $formattedTemplates[] = [
                'id'                => $template['id'],
                'title'             => $template['title']['rendered'],
                'content'           => $template['template_json'],
                'short_description' => $template['short_description'],
                'link'              => $template['link'],
                'media_url'         => $mediaURL,
                'status'            => $template['status'],
                'cover_image'       => $template['cover_image'],
            ];
        }

        return $formattedTemplates;
    }


    /**
     * Retrieves the full source URL of a media item.
     *
     * @param int    $mediaID Media item ID.
     * @param string $restAPI The base URL of the REST API.
     *
     * @return string Full source URL of the media item.
     */
    public function getMediaURL($mediaID, $restAPI) {
        $request = wp_remote_get($restAPI.'media/'.$mediaID, [
            'sslverify' => false,
        ]);

        // Check for request errors
        if (is_wp_error($request)) {
            return '';
        }

        $image = json_decode($request['body'], true);
        $img   = Arr::get($image, 'source_url');

        return $img;
    }
}
