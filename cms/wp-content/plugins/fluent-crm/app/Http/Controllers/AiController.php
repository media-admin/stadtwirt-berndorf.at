<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\Framework\Support\Arr;

class AiController extends Controller
{
    private $credentialsOptionKey = '_fluent_ai_creds';
    private $writingSettingsOptionKey = '_ai_writing_settings';
    private $providerModels = [
        'wordpress' => ['wordpress'],
        'open_ai' => ['auto', 'gpt-5.5', 'gpt-5.4', 'gpt-5.4-mini', 'gpt-5.4-nano', 'gpt-4.1', 'gpt-4o', 'gpt-4o-mini'],
        'claude'  => ['auto', 'claude-opus-4-7', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001', 'claude-opus-4-6'],
        'gemini'  => ['auto', 'gemini-3.5-flash', 'gemini-3.1-pro-preview', 'gemini-3-flash-preview', 'gemini-3.1-flash-lite', 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.5-flash-lite'],
    ];

    private $autoProviderModels = [
        'open_ai' => 'gpt-5.4',
        'claude'  => 'claude-sonnet-4-6',
        'gemini'  => 'gemini-3.5-flash',
        'wordpress' => 'wordpress',
    ];

    public function getSettings(Request $request)
    {
        $settings = $this->getSavedSettings();

        // Mask the API key for frontend display
        if (!empty($settings['api_key'])) {
            $settings['api_key'] = '****' . substr($settings['api_key'], -4);
        }

        global $wp_version;
        $hasWordPressAi = (intval(explode('.', $wp_version)[0]) >= 7);
        $connectorsUrl = admin_url('options-connectors.php');

        return $this->sendSuccess([
            'settings' => $settings,
            'has_wordpress_ai' => $hasWordPressAi,
            'connectors_url' => $connectorsUrl,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->get('settings', []);

        $isEnabled = sanitize_text_field(Arr::get($data, 'is_enabled', 'no'));
        $provider = $this->normalizeProvider(sanitize_text_field(Arr::get($data, 'provider', '')));
        $model = sanitize_text_field(Arr::get($data, 'model', 'auto'));
        $apiKey = sanitize_text_field(Arr::get($data, 'api_key', ''));
        $customPrompt = sanitize_textarea_field(Arr::get($data, 'custom_prompt', ''));

        if ($isEnabled !== 'yes') {
            $isEnabled = 'no';
        }

        global $wp_version;
        $hasWordPressAi = (intval(explode('.', $wp_version)[0]) >= 7);
        if ($provider === 'wordpress' && !$hasWordPressAi) {
            return $this->sendError([
                'message' => __('WordPress AI is only supported in WordPress 7.0 or higher.', 'fluent-crm'),
            ], 422);
        }

        if (!$model) {
            $model = 'auto';
        }

        $validProviders = array_keys($this->providerModels);
        if ($provider && !in_array($provider, $validProviders, true)) {
            return $this->sendError([
                'message' => __('Invalid AI provider selected.', 'fluent-crm'),
            ], 422);
        }

        $existingCredentials = $this->getSavedCredentials();

        // Handle API key: if masked value is sent back, keep existing; if empty, clear it.
        $plainApiKey = Arr::get($existingCredentials, 'api_key', '');
        if (empty($apiKey)) {
            $plainApiKey = '';
        } elseif (strpos($apiKey, '****') !== 0) {
            $plainApiKey = $apiKey;
        }

        $credentials = [
            'provider'   => $provider,
            'model'      => $model,
            'api_key'    => $plainApiKey,
            'created_by' => 'fluent_crm',
        ];

        $preferences = [
            'is_enabled'    => $isEnabled,
            'custom_prompt' => $customPrompt,
        ];

        update_option($this->credentialsOptionKey, $credentials, false);
        fluentcrm_update_option($this->writingSettingsOptionKey, $preferences);

        return $this->sendSuccess([
            'message' => __('AI configuration saved successfully.', 'fluent-crm'),
        ]);
    }

    public function getModels(Request $request)
    {
        $data = $request->get('settings', []);
        $provider = $this->normalizeProvider(sanitize_text_field(Arr::get($data, 'provider', '')));

        $validProviders = array_keys($this->providerModels);
        if (!$provider || !in_array($provider, $validProviders, true)) {
            return $this->sendError([
                'message' => __('Invalid AI provider selected.', 'fluent-crm'),
            ], 422);
        }

        return $this->sendSuccess([
            'models' => $this->formatModelOptions($provider),
        ]);
    }

    public function testConnection(Request $request)
    {
        $data = $request->get('settings', []);
        $provider = $this->normalizeProvider(sanitize_text_field(Arr::get($data, 'provider', '')));
        $model = sanitize_text_field(Arr::get($data, 'model', 'auto'));
        $apiKey = sanitize_text_field(Arr::get($data, 'api_key', ''));

        if (!$provider || !$model) {
            return $this->sendError([
                'message' => __('Please select a provider and model first.', 'fluent-crm'),
            ], 422);
        }

        $validProviders = array_keys($this->providerModels);
        if (!in_array($provider, $validProviders, true)) {
            return $this->sendError([
                'message' => __('Invalid AI provider selected.', 'fluent-crm'),
            ], 422);
        }

        // If the API key is masked, use the saved one
        if (!$apiKey || strpos($apiKey, '****') === 0) {
            $savedSettings = $this->getSavedSettings();
            $apiKey = Arr::get($savedSettings, 'api_key', '');
        }

        if ($provider !== 'wordpress' && !$apiKey) {
            return $this->sendError([
                'message' => __('Please enter an API key.', 'fluent-crm'),
            ], 422);
        }

        $resolvedModel = $this->resolveModel($provider, $model);
        if (!$resolvedModel) {
            return $this->sendError([
                'message' => __('AI model is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $result = $this->callProviderApi($provider, $resolvedModel, $apiKey, 'Say "Connection successful" in exactly two words.', '', 15);

        if (is_wp_error($result)) {
            return $this->sendError([
                'message' => $result->get_error_message(),
            ], 422);
        }

        return $this->sendSuccess([
            'message' => __('Connection successful! Your API key is valid.', 'fluent-crm'),
        ]);
    }

    public function generate(Request $request)
    {
        $action = sanitize_text_field($request->get('action', ''));
        $content = sanitize_textarea_field($request->get('content', ''));
        $tone = sanitize_text_field($request->get('tone', ''));
        $customPrompt = sanitize_textarea_field($request->get('custom_prompt', ''));

        $validActions = ['rewrite', 'shorten', 'expand', 'fix_grammar', 'custom'];

        if (!in_array($action, $validActions, true)) {
            return $this->sendError([
                'message' => __('Invalid action specified.', 'fluent-crm'),
            ], 422);
        }

        if (empty($content) && $action !== 'custom') {
            return $this->sendError([
                'message' => __('No content provided to process.', 'fluent-crm'),
            ], 422);
        }

        if ($action === 'custom' && empty($content) && empty($customPrompt)) {
            return $this->sendError([
                'message' => __('Please provide a prompt or select some text.', 'fluent-crm'),
            ], 422);
        }

        $settings = $this->getSavedSettings();

        if (Arr::get($settings, 'is_enabled') !== 'yes') {
            return $this->sendError([
                'message' => __('AI features are not enabled. Please configure AI in Settings.', 'fluent-crm'),
            ], 422);
        }

        $provider = Arr::get($settings, 'provider', '');
        $apiKey = Arr::get($settings, 'api_key', '');
        if ($provider !== 'wordpress' && !$apiKey) {
            return $this->sendError([
                'message' => __('AI API key is not configured. Please add it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $model = Arr::get($settings, 'model', '');

        $validProviders = array_keys($this->providerModels);
        if (!$provider || !in_array($provider, $validProviders, true)) {
            return $this->sendError([
                'message' => __('AI provider is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        if (!$model) {
            return $this->sendError([
                'message' => __('AI model is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $resolvedModel = $this->resolveModel($provider, $model);
        if (!$resolvedModel) {
            return $this->sendError([
                'message' => __('AI model is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $userPrompt = $this->buildUserPrompt($action, $content, $customPrompt);

        $result = $this->callProviderApi($provider, $resolvedModel, $apiKey, $userPrompt, $this->getSystemPrompt($tone, $settings), 30);

        if (is_wp_error($result)) {
            return $this->sendError([
                'message' => $result->get_error_message(),
            ], 422);
        }

        return $this->sendSuccess([
            'content' => sanitize_textarea_field($result),
        ]);
    }

    /**
     * Generate a complete email body from a user prompt for campaign editors.
     */
    public function generateEmailBody(Request $request)
    {
        $prompt = sanitize_textarea_field($request->get('prompt', ''));
        $tone = sanitize_text_field($request->get('tone', 'friendly'));
        $audience = sanitize_text_field($request->get('audience', ''));
        $length = sanitize_text_field($request->get('length', 'medium'));
        $cta = sanitize_text_field($request->get('cta', ''));
        $context = Helper::parseArrayOrJson($request->get('context', []));

        if (!$prompt) {
            return $this->sendError([
                'message' => __('Please provide a prompt to generate the email body.', 'fluent-crm'),
            ], 422);
        }

        if (!in_array($tone, ['friendly', 'professional', 'casual', 'persuasive', 'educational'], true)) {
            $tone = 'friendly';
        }

        if (!in_array($length, ['short', 'medium', 'long'], true)) {
            $length = 'medium';
        }

        $settings = $this->getSavedSettings();
        $aiConfig = $this->validateAiGenerationConfig($settings);

        if (is_wp_error($aiConfig)) {
            return $this->sendError([
                'message' => $aiConfig->get_error_message(),
            ], 422);
        }

        $promptData = [
            'prompt'   => $prompt,
            'tone'     => $tone,
            'audience' => $audience,
            'length'   => $length,
            'cta'      => $cta,
            'context'  => $this->sanitizePromptContext($context),
        ];

        $result = $this->callProviderApi(
            $aiConfig['provider'],
            $aiConfig['model'],
            $aiConfig['api_key'],
            $this->buildEmailBodyUserPrompt($promptData),
            $this->getEmailBodySystemPrompt($settings),
            45
        );

        if (is_wp_error($result)) {
            return $this->sendError([
                'message' => $result->get_error_message(),
            ], 422);
        }

        $generated = $this->parseGeneratedEmailBody($result);

        /**
         * Filter generated AI email body content before returning it to the editor.
         *
         * @param array  $generated Generated subject suggestions, preheader, and body HTML.
         * @param array  $promptData Sanitized prompt inputs and editor context.
         * @param string $result Raw AI provider response.
         */
        $generated = apply_filters('fluent_crm/ai_email_body_generated_content', $generated, $promptData, $result);

        if (!is_array($generated)) {
            $generated = [
                'subject_suggestions' => [],
                'preview_text'        => '',
                'email_body'          => '',
            ];
        }

        return $this->sendSuccess([
            'email_body'           => $this->sanitizeGeneratedEmailBody(Arr::get($generated, 'email_body', ''), Arr::get($promptData, 'context.output_format', '')),
            'subject_suggestions'  => array_values(array_map('sanitize_text_field', Arr::get($generated, 'subject_suggestions', []))),
            'preview_text'         => sanitize_text_field(Arr::get($generated, 'preview_text', '')),
            'provider'             => $aiConfig['provider'],
            'model'                => $aiConfig['model'],
        ]);
    }

    private function sanitizeGeneratedEmailBody($emailBody, $outputFormat)
    {
        $emailBody = (string) $emailBody;

        if ($outputFormat === 'gutenberg_blocks' && function_exists('filter_block_content')) {
            return filter_block_content($emailBody);
        }

        return wp_kses_post($emailBody);
    }

    /**
     * Generate or return a cached markdown summary for a contact profile.
     */
    public function contactSummary(Request $request)
    {
        $subscriberId = intval($request->get('subscriber_id', 0));
        $generate = $request->get('generate') == 'yes';
        $regenerate = $request->get('regenerate') == 'yes';
        $locale = $this->getContactSummaryLocale();

        if (!$subscriberId) {
            return $this->sendError([
                'message' => __('Invalid contact selected.', 'fluent-crm'),
            ], 422);
        }

        $subscriber = Subscriber::with(['tags', 'lists'])->find($subscriberId);

        if (!$subscriber) {
            return $this->sendError([
                'message' => __('Subscriber not found', 'fluent-crm'),
            ], 404);
        }

        $metaKey = '_ai_contact_summary';
        $cachedSummary = fluentcrm_get_subscriber_meta($subscriberId, $metaKey, []);

        if (!$regenerate && $this->isCachedContactSummaryForLocale($cachedSummary, $locale)) {
            return $this->sendSuccess([
                'summary' => $cachedSummary,
                'cached'  => true,
            ]);
        }

        if (!$generate && !$regenerate) {
            return $this->sendSuccess([
                'summary' => [],
                'cached'  => false,
            ]);
        }

        $settings = $this->getSavedSettings();

        if (Arr::get($settings, 'is_enabled') !== 'yes') {
            return $this->sendError([
                'message' => __('AI features are not enabled. Please configure AI in Settings.', 'fluent-crm'),
            ], 422);
        }

        $provider = Arr::get($settings, 'provider', '');
        $apiKey = Arr::get($settings, 'api_key', '');
        if ($provider !== 'wordpress' && !$apiKey) {
            return $this->sendError([
                'message' => __('AI API key is not configured. Please add it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $model = Arr::get($settings, 'model', '');

        $validProviders = array_keys($this->providerModels);
        if (!$provider || !in_array($provider, $validProviders, true)) {
            return $this->sendError([
                'message' => __('AI provider is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        if (!$model) {
            return $this->sendError([
                'message' => __('AI model is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $resolvedModel = $this->resolveModel($provider, $model);
        if (!$resolvedModel) {
            return $this->sendError([
                'message' => __('AI model is not configured. Please set it in Settings.', 'fluent-crm'),
            ], 422);
        }

        $context = $this->buildContactSummaryContext($subscriber, $locale);
        $result = $this->callProviderApi(
            $provider,
            $resolvedModel,
            $apiKey,
            $this->buildContactSummaryPrompt($context, $locale),
            $this->getContactSummarySystemPrompt($settings, $locale),
            45
        );

        if (is_wp_error($result)) {
            return $this->sendError([
                'message' => $result->get_error_message(),
            ], 422);
        }

        $summary = [
            'content'      => sanitize_textarea_field($result),
            'generated_at' => fluentCrmTimestamp(),
            'provider'     => $provider,
            'model'        => $model,
            'locale'       => $locale,
            'counts'       => Arr::get($context, 'counts', []),
        ];

        fluentcrm_update_subscriber_meta($subscriberId, $metaKey, $summary);

        return $this->sendSuccess([
            'summary' => $summary,
            'cached'  => false,
        ]);
    }

    /**
     * Resolve the WordPress site locale used for AI contact summaries.
     *
     * The contact summary must follow Settings > General > Site Language, not the
     * current admin user's profile language. The fallback keeps cache comparison
     * deterministic if WordPress returns an empty locale for any reason.
     *
     * @return string Sanitized WordPress locale, for example en_US or bn_BD.
     */
    private function getContactSummaryLocale()
    {
        $locale = sanitize_text_field((string) get_locale());

        return $locale ?: 'en_US';
    }

    /**
     * Check whether a cached AI contact summary can be reused for the site locale.
     *
     * New summaries store their generation locale and are reusable only when it
     * matches the current site locale. Older cached summaries did not store a
     * locale and were generated from English-only prompts, so they are treated as
     * valid only while the current site locale is English.
     *
     * @param array  $summary Cached subscriber meta summary payload.
     * @param string $locale  Current sanitized WordPress site locale.
     *
     * @return bool True when the cached summary can be shown without regenerating.
     */
    private function isCachedContactSummaryForLocale($summary, $locale)
    {
        if (!is_array($summary) || empty($summary['content'])) {
            return false;
        }

        $cachedLocale = sanitize_text_field(Arr::get($summary, 'locale', ''));

        if ($cachedLocale) {
            return $cachedLocale === $locale;
        }

        // Legacy cached summaries were generated from English-only prompts.
        return $this->isEnglishLocale($locale);
    }

    /**
     * Determine whether a WordPress locale belongs to the English language family.
     *
     * Used only for legacy AI summary cache compatibility, where existing cached
     * records have no explicit locale but were produced by English prompts.
     *
     * @param string $locale WordPress locale to inspect.
     *
     * @return bool True for locales beginning with en, such as en_US or en_GB.
     */
    private function isEnglishLocale($locale)
    {
        return strtolower(substr((string) $locale, 0, 2)) === 'en';
    }

    private function getSystemPrompt($tone = '', $settings = [])
    {
        $prompt = 'You are an email copywriting assistant. Write like a real human — natural, conversational, and warm. '
            . 'Avoid AI-sounding patterns: no em dashes, no "I hope this email finds you well", no "In today\'s fast-paced world", no "leverage", no "streamline", no "I\'d be happy to". '
            . 'Use short sentences. Use simple words. Write the way people actually talk in emails. '
            . 'Return ONLY the improved text in markdown format. No explanations, preamble, or wrapping code blocks.' . "\n\n"
            . 'You can use these smartcode placeholders to personalize the email: '
            . '{{contact.first_name}} (recipient first name), '
            . '{{contact.last_name}} (recipient last name), '
            . '{{contact.full_name}} (recipient full name), '
            . '{{contact.email}} (recipient email), '
            . '{{crm.business_name}} (sender business name), '
            . '{{crm.business_address}} (sender business address). '
            . 'Use these smartcodes where appropriate to make emails feel personal. Keep existing smartcodes in the text intact.';

        if ($tone) {
            $prompt .= ' Use a ' . strtolower($tone) . ' tone throughout.';
        }

        $customSystemPrompt = trim(Arr::get($settings, 'custom_prompt', ''));
        if ($customSystemPrompt) {
            $prompt .= "\n\nAdditional instructions: " . $customSystemPrompt;
        }

        return $prompt;
    }

    private function buildUserPrompt($action, $content, $customPrompt = '')
    {
        switch ($action) {
            case 'rewrite':
                return "Rewrite the following email text while keeping the same meaning:\n\n" . $content;
            case 'shorten':
                return "Make the following email text shorter and more concise:\n\n" . $content;
            case 'expand':
                return "Expand the following email text with more detail and engagement:\n\n" . $content;
            case 'fix_grammar':
                return "Fix grammar, spelling, and punctuation in the following text:\n\n" . $content;
            case 'custom':
                return $customPrompt . "\n\nText:\n" . $content;
            default:
                return $content;
        }
    }

    private function validateAiGenerationConfig($settings)
    {
        if (Arr::get($settings, 'is_enabled') !== 'yes') {
            return new \WP_Error('ai_disabled', __('AI features are not enabled. Please configure AI in Settings.', 'fluent-crm'));
        }

        $provider = Arr::get($settings, 'provider', '');
        $apiKey = Arr::get($settings, 'api_key', '');
        if ($provider !== 'wordpress' && !$apiKey) {
            return new \WP_Error('missing_api_key', __('AI API key is not configured. Please add it in Settings.', 'fluent-crm'));
        }

        $model = Arr::get($settings, 'model', '');

        $validProviders = array_keys($this->providerModels);
        if (!$provider || !in_array($provider, $validProviders, true)) {
            return new \WP_Error('missing_provider', __('AI provider is not configured. Please set it in Settings.', 'fluent-crm'));
        }

        if (!$model) {
            return new \WP_Error('missing_model', __('AI model is not configured. Please set it in Settings.', 'fluent-crm'));
        }

        $resolvedModel = $this->resolveModel($provider, $model);
        if (!$resolvedModel) {
            return new \WP_Error('missing_model', __('AI model is not configured. Please set it in Settings.', 'fluent-crm'));
        }

        return [
            'provider' => $provider,
            'model'    => $resolvedModel,
            'api_key'  => $apiKey,
        ];
    }

    private function getEmailBodySystemPrompt($settings = [])
    {
        $prompt = 'You are an expert email marketing copywriter for FluentCRM users. '
            . 'Generate a complete email body that is ready to insert into an email editor. '
            . 'Use real, specific copy based only on the user prompt. Do not invent discounts, dates, guarantees, scarcity, purchase history, or personal facts. '
            . 'Use FluentCRM smartcodes sparingly when helpful, such as {{contact.first_name}}, {{contact.full_name}}, and {{crm.business_name}}. '
            . 'Match the requested output_format exactly: gutenberg_blocks must return valid WordPress block markup using paragraph, heading, list, and button blocks; classic_html must return clean rich HTML fragments; raw_html must return clean raw HTML fragments. '
            . 'For HTML outputs, use only these tags when needed: h2, h3, p, ul, ol, li, strong, em, a, br. Do not include html, head, body, style, script, table, img, or wrapper div tags. '
            . 'Return ONLY valid JSON with this exact shape: {"subject_suggestions":["..."],"preview_text":"...","email_body":"..."}. No markdown fences, no explanations.';

        $customSystemPrompt = trim(Arr::get($settings, 'custom_prompt', ''));
        if ($customSystemPrompt) {
            $prompt .= "\n\nAdditional brand instructions: " . $customSystemPrompt;
        }

        /**
         * Filter the AI email body system prompt.
         *
         * @param string $prompt   System prompt sent to the configured AI provider.
         * @param array  $settings Saved AI settings.
         */
        return apply_filters('fluent_crm/ai_email_body_system_prompt', $prompt, $settings);
    }

    private function buildEmailBodyUserPrompt($promptData)
    {
        $lengthMap = [
            'short'  => 'Short: about 2-3 short paragraphs or one heading plus a few bullets.',
            'medium' => 'Medium: about 4-6 short paragraphs or sections.',
            'long'   => 'Long: a more detailed email with clear sections and supporting bullets.',
        ];

        $prompt = "Create a complete marketing email body from this brief.\n\n"
            . 'Goal: ' . Arr::get($promptData, 'prompt') . "\n"
            . 'Tone: ' . Arr::get($promptData, 'tone') . "\n"
            . 'Length: ' . Arr::get($lengthMap, Arr::get($promptData, 'length'), $lengthMap['medium']) . "\n";

        if ($audience = Arr::get($promptData, 'audience')) {
            $prompt .= 'Audience: ' . $audience . "\n";
        }

        if ($cta = Arr::get($promptData, 'cta')) {
            $prompt .= 'Primary CTA: ' . $cta . "\n";
        }

        $context = Arr::get($promptData, 'context', []);
        if ($context) {
            $prompt .= "\nEditor context:\n" . wp_json_encode($context, JSON_PRETTY_PRINT);
        }

        $prompt .= "\n\nOutput rules:\n" . $this->getEmailBodyOutputRules(Arr::get($context, 'output_format', 'classic_html'));

        /**
         * Filter the AI email body user prompt.
         *
         * @param string $prompt     User prompt sent to the configured AI provider.
         * @param array  $promptData Sanitized prompt inputs and editor context.
         */
        return apply_filters('fluent_crm/ai_email_body_user_prompt', $prompt, $promptData);
    }

    private function getEmailBodyOutputRules($outputFormat)
    {
        if ($outputFormat === 'gutenberg_blocks') {
            return 'Set email_body to WordPress Gutenberg block markup only. Example shape: <!-- wp:heading --><h2>Headline</h2><!-- /wp:heading --> followed by <!-- wp:paragraph --><p>Copy</p><!-- /wp:paragraph --> and <!-- wp:list --><ul><li>Point</li></ul><!-- /wp:list -->. Use a button block for the primary CTA when a CTA exists.';
        }

        if ($outputFormat === 'raw_html') {
            return 'Set email_body to raw HTML fragments suitable for a raw HTML editor. Use semantic HTML and include links for CTAs when appropriate. Do not include WordPress block comments.';
        }

        return 'Set email_body to clean rich HTML suitable for a classic WYSIWYG email editor. Do not include WordPress block comments.';
    }

    private function sanitizePromptContext($context)
    {
        if (!is_array($context)) {
            return [];
        }

        $allowed = ['design_template', 'editor_type', 'output_format', 'campaign_type', 'has_existing_body'];
        $sanitized = [];

        foreach ($allowed as $key) {
            if (isset($context[$key]) && is_scalar($context[$key])) {
                $sanitized[$key] = sanitize_text_field((string) $context[$key]);
            }
        }

        return $sanitized;
    }

    private function parseGeneratedEmailBody($result)
    {
        $decoded = json_decode(trim($result), true);

        if (!is_array($decoded) && preg_match('/\{.*\}/s', $result, $matches)) {
            $decoded = json_decode($matches[0], true);
        }

        if (!is_array($decoded)) {
            return [
                'subject_suggestions' => [],
                'preview_text'        => '',
                'email_body'          => $result,
            ];
        }

        $subjects = Arr::get($decoded, 'subject_suggestions', []);
        if (!is_array($subjects)) {
            $subjects = $subjects ? [$subjects] : [];
        }

        return [
            'subject_suggestions' => array_slice($subjects, 0, 5),
            'preview_text'        => Arr::get($decoded, 'preview_text', ''),
            'email_body'          => Arr::get($decoded, 'email_body', ''),
        ];
    }

    private function getContactSummarySystemPrompt($settings = [], $locale = '')
    {
        $prompt = 'You are a CRM assistant creating an internal contact summary for sales and support teams. '
            . 'Use only the supplied contact data. Do not invent purchases, courses, tickets, emails, dates, or recommendations. '
            . 'If a section has no data, say it is not available. '
            . 'Do not repeat basic contact details like name, email, status, or created date unless directly relevant to a decision. '
            . 'Return markdown only in the requested WordPress site language. Use decision-focused headings, concise bullets, and a final section equivalent to "Suggested next action" in that language.';

        if ($locale) {
            $prompt .= "\n\nRequested WordPress site locale: " . $locale . ". Write the entire summary in this site language.";
        }

        $customSystemPrompt = trim(Arr::get($settings, 'custom_prompt', ''));
        if ($customSystemPrompt) {
            $prompt .= "\n\nAdditional business context: " . $customSystemPrompt;
        }

        /**
         * Filter the AI contact summary system prompt.
         *
         * @param string $prompt   System prompt sent to the configured AI provider.
         * @param array  $settings Saved AI settings.
         * @param string $locale   WordPress site locale requested for the summary output.
         */
        return apply_filters('fluent_crm/ai_contact_summary_system_prompt', $prompt, $settings, $locale);
    }

    private function buildContactSummaryPrompt($context, $locale = '')
    {
        $languageInstruction = $locale
            ? 'Write the entire summary in the WordPress site language for locale ' . $locale . '. '
            : 'Write the entire summary in the WordPress site language. ';

        $prompt = "Summarize this contact for a CRM user who already sees the basic profile on screen. Focus on what they should understand or do next. Include email engagement, purchase history, course or membership history when present, support ticket history when present, risk signals, opportunities, and suggested next action. Do not create a Contact Details section. " . $languageInstruction . "Translate the explanatory prose and headings, but keep contact names, company names, product names, email subjects, URLs, IDs, tag names, list names, order numbers, and other source data values unchanged.\n\nContact context:\n" . wp_json_encode($context, JSON_PRETTY_PRINT);

        /**
         * Filter the AI contact summary user prompt.
         *
         * @param string $prompt  User prompt sent to the configured AI provider.
         * @param array  $context Structured contact context used for summary generation.
         * @param string $locale  WordPress site locale requested for the summary output.
         */
        return apply_filters('fluent_crm/ai_contact_summary_user_prompt', $prompt, $context, $locale);
    }

    private function buildContactSummaryContext($subscriber, $locale = '')
    {
        $context = [
            'contact' => [
                'id'         => (int) $subscriber->id,
                'name'       => $subscriber->full_name,
                'email'      => $subscriber->email,
                'status'     => $subscriber->status,
                'created_at' => $subscriber->created_at,
                'lists'      => $this->pluckTitles($subscriber->lists),
                'tags'       => $this->pluckTitles($subscriber->tags),
            ],
            'language'          => [
                'site_locale' => $locale,
            ],
            'emails'            => $this->getEmailSummaryContext($subscriber->id),
            'purchase_history'  => $this->getPurchaseSummaryContext($subscriber),
            'support_tickets'   => $this->getSupportTicketSummaryContext($subscriber),
            'counts'            => [],
        ];

        $context['counts'] = [
            'emails'             => Arr::get($context, 'emails.total', 0),
            'purchase_providers' => count(Arr::get($context, 'purchase_history.providers', [])),
            'support_providers'  => count(Arr::get($context, 'support_tickets.providers', [])),
        ];

        return $context;
    }

    private function getEmailSummaryContext($subscriberId)
    {
        $emails = CampaignEmail::where('subscriber_id', $subscriberId)
            ->orderBy('id', 'DESC')
            ->limit(20)
            ->get();

        $items = [];
        $opened = 0;
        $clicked = 0;

        foreach ($emails as $email) {
            if ($email->is_open) {
                $opened++;
            }

            if ($email->click_counter) {
                $clicked++;
            }

            $items[] = [
                'subject'       => wp_strip_all_tags($email->email_subject),
                'status'        => $email->status,
                'sent_at'       => $email->scheduled_at ?: $email->created_at,
                'opened'        => (bool) $email->is_open,
                'click_counter' => intval($email->click_counter),
            ];
        }

        return [
            'total'        => CampaignEmail::where('subscriber_id', $subscriberId)->count(),
            'recent_count' => count($items),
            'opened'       => $opened,
            'clicked'      => $clicked,
            'recent'       => $items,
        ];
    }

    private function getPurchaseSummaryContext($subscriber)
    {
        $providers = [];
        foreach (Helper::getPurchaseHistoryProviders() as $providerKey => $provider) {
            $providerKey = sanitize_key($providerKey);
            if (!$providerKey) {
                continue;
            }

            $data = apply_filters('fluent_crm/purchase_history_' . $providerKey, [
                'orders' => [],
                'total'  => 0,
            ], $subscriber);

            $providers[$providerKey] = [
                'title'  => sanitize_text_field(Arr::get($provider, 'title', $providerKey)),
                'total'  => intval(Arr::get($data, 'total', 0)),
                'orders' => $this->normalizeSummaryRows(Arr::get($data, 'orders', []), 10),
            ];
        }

        return [
            'providers' => $providers,
        ];
    }

    private function getSupportTicketSummaryContext($subscriber)
    {
        $providers = [];
        $supportProviders = apply_filters('fluentcrm-support_tickets_providers', []);

        foreach ($supportProviders as $providerKey => $provider) {
            $providerKey = sanitize_key($providerKey);
            if (!$providerKey) {
                continue;
            }

            $data = apply_filters('fluentcrm-get_support_tickets_' . $providerKey, [
                'data'  => [],
                'total' => 0,
            ], $subscriber);

            $providers[$providerKey] = [
                'title'   => sanitize_text_field(Arr::get($provider, 'title', $providerKey)),
                'total'   => intval(Arr::get($data, 'total', 0)),
                'tickets' => $this->normalizeSummaryRows(Arr::get($data, 'data', []), 10),
            ];
        }

        return [
            'providers' => $providers,
        ];
    }

    private function normalizeSummaryRows($rows, $limit = 10)
    {
        if (!$rows) {
            return [];
        }

        if (is_object($rows) && method_exists($rows, 'toArray')) {
            $rows = $rows->toArray();
        }

        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $row = (array) $row;
            $item = [];

            foreach ($row as $key => $value) {
                if (is_scalar($value) || $value === null) {
                    $item[sanitize_key($key)] = sanitize_text_field(wp_strip_all_tags((string) $value));
                }
            }

            if ($item) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function pluckTitles($items)
    {
        $titles = [];
        foreach ($items as $item) {
            if (!empty($item->title)) {
                $titles[] = $item->title;
            }
        }

        return $titles;
    }

    private function callProviderApi($provider, $model, $apiKey, $userPrompt, $systemPrompt = '', $timeout = 30)
    {
        switch ($provider) {
            case 'open_ai':
                return $this->callOpenAi($model, $apiKey, $userPrompt, $systemPrompt, $timeout);
            case 'claude':
                return $this->callClaude($model, $apiKey, $userPrompt, $systemPrompt, $timeout);
            case 'gemini':
                return $this->callGemini($model, $apiKey, $userPrompt, $systemPrompt, $timeout);
            case 'wordpress':
                return $this->callWordPress($model, $userPrompt, $systemPrompt, $timeout);
            default:
                return new \WP_Error('invalid_provider', __('Invalid AI provider.', 'fluent-crm'));
        }
    }

    private function callWordPress($model, $userPrompt, $systemPrompt, $timeout)
    {
        $filtered = apply_filters('fluent_crm/wordpress_ai_generate', null, $userPrompt, $systemPrompt, $model, $timeout);
        if ($filtered !== null) {
            return $filtered;
        }

        if (function_exists('wp_ai_client_prompt')) {
            $prompt = wp_ai_client_prompt($userPrompt);
            if ($systemPrompt) {
                $prompt->using_system_instruction($systemPrompt);
            }
            if ($prompt->is_supported_for_text_generation()) {
                $result = $prompt->generate_text();
                if (is_wp_error($result)) {
                    return $result;
                }
                if (empty($result)) {
                    return new \WP_Error('empty_response', __('No content generated by WordPress AI client. Please try again.', 'fluent-crm'));
                }
                return $result;
            } else {
                return new \WP_Error('not_supported', __('WordPress AI client is not configured or supported on this site.', 'fluent-crm'));
            }
        }

        return new \WP_Error(
            'wordpress_ai_not_supported',
            __('WordPress AI Client functions are not available on this WordPress installation. Please ensure you have an AI provider plugin or WordPress AI Core features enabled.', 'fluent-crm')
        );
    }

    private function callOpenAi($model, $apiKey, $userPrompt, $systemPrompt, $timeout)
    {
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $userPrompt];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'                 => $model,
                'messages'              => $messages,
                'max_completion_tokens' => 2048,
            ]),
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', __('Failed to connect to OpenAI: ', 'fluent-crm') . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $errorMessage = Arr::get($body, 'error.message', __('Unknown error from OpenAI.', 'fluent-crm'));
            return new \WP_Error('api_error', $errorMessage);
        }

        $content = Arr::get($body, 'choices.0.message.content', '');
        if (empty($content)) {
            return new \WP_Error('empty_response', __('No content generated. Please try again.', 'fluent-crm'));
        }

        return $content;
    }

    private function callClaude($model, $apiKey, $userPrompt, $systemPrompt, $timeout)
    {
        $data = [
            'model'      => $model,
            'max_tokens' => 2048,
            'messages'   => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        if ($systemPrompt) {
            $data['system'] = $systemPrompt;
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => $timeout,
            'headers' => [
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode($data),
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', __('Failed to connect to Claude: ', 'fluent-crm') . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $errorMessage = Arr::get($body, 'error.message', __('Unknown error from Claude.', 'fluent-crm'));
            return new \WP_Error('api_error', $errorMessage);
        }

        $content = Arr::get($body, 'content.0.text', '');
        if (empty($content)) {
            return new \WP_Error('empty_response', __('No content generated. Please try again.', 'fluent-crm'));
        }

        return $content;
    }

    private function callGemini($model, $apiKey, $userPrompt, $systemPrompt, $timeout)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

        $data = [
            'contents'         => [
                ['parts' => [['text' => $userPrompt]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 2048,
            ],
        ];

        if ($systemPrompt) {
            $data['system_instruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        $response = wp_remote_post($url, [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $apiKey,
            ],
            'body' => wp_json_encode($data),
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', __('Failed to connect to Gemini: ', 'fluent-crm') . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $errorMessage = Arr::get($body, 'error.message', __('Unknown error from Gemini.', 'fluent-crm'));
            return new \WP_Error('api_error', $errorMessage);
        }

        $content = Arr::get($body, 'candidates.0.content.parts.0.text', '');
        if (empty($content)) {
            return new \WP_Error('empty_response', __('No content generated. Please try again.', 'fluent-crm'));
        }

        return $content;
    }

    private function getSavedSettings()
    {
        $defaults = [
            'is_enabled'    => 'no',
            'provider'      => '',
            'api_key'       => '',
            'model'         => 'auto',
            'custom_prompt' => '',
        ];

        $settings = array_merge(
            $this->getSavedPreferences(),
            $this->getSavedCredentials()
        );

        if (!is_array($settings)) {
            return $defaults;
        }

        return wp_parse_args($settings, $defaults);
    }

    private function getSavedCredentials()
    {
        $credentials = get_option($this->credentialsOptionKey, []);

        if (!is_array($credentials)) {
            $credentials = [];
        }

        $provider = $this->normalizeProvider(Arr::get($credentials, 'provider', ''));
        $model = sanitize_text_field(Arr::get($credentials, 'model', 'auto'));

        return [
            'provider'   => $provider,
            'model'      => $model ?: 'auto',
            'api_key'    => sanitize_text_field(Arr::get($credentials, 'api_key', '')),
            'created_by' => sanitize_text_field(Arr::get($credentials, 'created_by', '')),
        ];
    }

    private function getSavedPreferences()
    {
        $preferences = fluentcrm_get_option($this->writingSettingsOptionKey, []);

        if (!is_array($preferences)) {
            $preferences = [];
        }

        return [
            'is_enabled'    => sanitize_text_field(Arr::get($preferences, 'is_enabled', 'no')) === 'yes' ? 'yes' : 'no',
            'custom_prompt' => sanitize_textarea_field(Arr::get($preferences, 'custom_prompt', '')),
        ];
    }

    private function normalizeProvider($provider)
    {
        $provider = sanitize_key($provider);

        return $provider === 'openai' ? 'open_ai' : $provider;
    }

    private function resolveModel($provider, $model)
    {
        $model = $model ?: 'auto';

        if ($model !== 'auto') {
            return $model;
        }

        return Arr::get($this->autoProviderModels, $provider, '');
    }

    private function formatModelOptions($provider)
    {
        $models = [];

        foreach (Arr::get($this->providerModels, $provider, []) as $model) {
            $models[] = [
                'value' => $model,
                'label' => $model === 'auto' ? __('Auto', 'fluent-crm') : $model,
            ];
        }

        return $models;
    }
}
