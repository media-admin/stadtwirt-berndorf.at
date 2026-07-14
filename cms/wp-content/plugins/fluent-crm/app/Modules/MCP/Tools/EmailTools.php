<?php

namespace FluentCrm\App\Modules\MCP\Tools;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CustomEmailCampaign;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Modules\MCP\Helpers\MCPHelper;
use FluentCrm\App\Modules\MCP\Tools\ContextTools;
use FluentCrm\App\Services\BlockParser;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\Libs\Mailer\Mailer;
use FluentCrm\App\Services\Sanitize;
use FluentCrm\Framework\Support\Arr;

/**
 * One-off email tools — wraps SubscriberController::sendCustomEmail
 * (MCP_PLAN.md § 5.9), exposing every option the contact-profile "Send
 * Custom Email" UI surfaces *except* the interactive ones (visual_builder
 * design template and template_id picker):
 *
 *   - subject + preheader + body
 *   - design_template (plain | classic | raw_html | raw_classic)
 *   - mailer overrides: from_name/from_email/reply_to_name/reply_to_email
 *   - is_transactional (auto-disables the footer to match UI behavior)
 *   - explicit disable_footer override
 *   - click/open trackers (yes|no|anonymous)
 *   - UTM tagging
 *   - free-form settings passthrough for template_config / footer_settings
 *
 * Reuses the normal queue + bounce + FluentSMTP plumbing so MCP-sent emails
 * behave identically to one-offs sent from the contact profile.
 */
class EmailTools
{
    public static function sendEmailToContact($params)
    {
        $params = (array) $params;

        $resolved = MCPHelper::resolveContact($params);
        if (is_wp_error($resolved)) {
            return $resolved;
        }
        $contact = $resolved;

        $subject = trim((string) ($params['subject'] ?? ''));
        $body    = (string) ($params['body'] ?? '');

        if ($subject === '' || $body === '') {
            return MCPHelper::error('invalid_param', __('subject and body are required', 'fluent-crm'));
        }

        // Status check matches the controller's gate. Phrase the error so
        // the agent doesn't think `is_transactional=yes` will bypass it
        // (review #8 — that flag is the *message* type, not a status
        // override).
        $allowedStatuses = ['subscribed', 'transactional'];
        if (!in_array($contact->status, $allowedStatuses, true)) {
            return MCPHelper::error('invalid_param', sprintf(
                /* translators: 1: current contact status, 2: comma-separated list of allowed statuses */
                __("The contact's status is '%1\$s'. To send to this contact, the contact's own status must be one of: %2\$s. The is_transactional parameter controls the message type, not the contact gate.", 'fluent-crm'),
                $contact->status,
                implode(', ', $allowedStatuses)
            ), [
                'current_status'   => $contact->status,
                'allowed_statuses' => $allowedStatuses,
            ]);
        }

        $defaults = Helper::getGlobalEmailSettings();

        $designTemplate = sanitize_key((string) ($params['design_template'] ?? 'classic'));
        if ($designTemplate === '') {
            $designTemplate = 'classic';
        }
        // Defense in depth — even though the schema enum constrains this,
        // a non-honoring agent (or a direct REST call) could still try to
        // pass `visual_builder` or another disallowed value. Reject server
        // side with a structured error.
        $allowed = array_keys(ContextTools::allowedDesignTemplates());
        if (!in_array($designTemplate, $allowed, true)) {
            return MCPHelper::error('invalid_param', __('design_template not allowed via MCP', 'fluent-crm'), [
                'design_template' => $designTemplate,
                'allowed'         => $allowed,
            ]);
        }

        $isTransactional = self::yesNo($params['is_transactional'] ?? null, 'no');

        // Footer toggle — UI behavior: turning on transactional auto-disables
        // the global footer because transactional mail must not include a
        // marketing unsubscribe link. Honor that by default; let the caller
        // override explicitly.
        if (array_key_exists('disable_footer', $params)) {
            $disableFooter = self::yesNo($params['disable_footer'], 'no');
        } else {
            $disableFooter = $isTransactional === 'yes' ? 'yes' : 'no';
        }

        $clickTracker = self::trackerValue($params['click_tracker'] ?? null);
        $openTracker  = self::trackerValue($params['open_tracker'] ?? null);

        // Build the mailer override block.
        $fromName     = sanitize_text_field((string) ($params['from_name'] ?? $defaults['from_name']));
        $fromEmail    = sanitize_email((string) ($params['from_email'] ?? $defaults['from_email']));
        $replyToName  = sanitize_text_field((string) ($params['reply_to_name'] ?? ($defaults['reply_to_name'] ?? '')));
        $replyToEmail = sanitize_email((string) ($params['reply_to_email'] ?? ($defaults['reply_to_email'] ?? '')));

        $mailerSettings = [
            'from_name'      => $fromName,
            'from_email'     => $fromEmail,
            'reply_to_name'  => $replyToName,
            'reply_to_email' => $replyToEmail,
            'is_custom'      => 'yes',
        ];

        // Compose the settings object the way the UI does.
        $settings = [
            'mailer_settings'  => $mailerSettings,
            'is_transactional' => $isTransactional,
            'footer_settings'  => [
                'disable_footer' => $disableFooter,
            ],
            'template_config'  => Helper::getTemplateConfig($designTemplate),
        ];
        if ($clickTracker !== null) {
            $settings['click_tracker'] = $clickTracker;
        }
        if ($openTracker !== null) {
            $settings['open_tracker'] = $openTracker;
        }

        // Allow callers to pass an arbitrary `settings` object for things
        // we haven't surfaced as top-level params (e.g. visual-builder style
        // overrides). Caller-provided keys win on conflict.
        if (!empty($params['settings']) && is_array($params['settings'])) {
            $settings = array_replace_recursive($settings, $params['settings']);
        }

        // Custom title for audit / log; default keeps recipient email so the
        // entry is searchable in the campaign list.
        $title = isset($params['title']) && $params['title'] !== ''
            ? sanitize_text_field((string) $params['title'])
            : sprintf(__('MCP one-off to %s', 'fluent-crm'), $contact->email);

        $campaignData = [
            'title'            => $title,
            'email_subject'    => $subject,
            'email_pre_header' => sanitize_text_field((string) ($params['pre_header'] ?? $params['preheader'] ?? '')),
            'email_body'       => $body,
            'design_template'  => $designTemplate,
            'settings'         => $settings,
            'status'           => 'draft',
        ];

        // UTM tagging — flatten the optional `utm` object onto the
        // campaign's utm_* columns.
        if (!empty($params['utm']) && is_array($params['utm'])) {
            $utm = $params['utm'];
            $campaignData['utm_status'] = !empty($utm['status']) ? 1 : 0;
            foreach (['source', 'medium', 'campaign', 'term', 'content'] as $key) {
                if (isset($utm[$key])) {
                    $campaignData['utm_' . $key] = sanitize_text_field((string) $utm[$key]);
                }
            }
        }

        $campaignData = Sanitize::campaign($campaignData);

        // Mirror the WP_Error surfacing behavior of the controller.
        add_action('wp_mail_failed', function ($wpError) {
            if (method_exists(Helper::class, 'debugLog')) {
                Helper::debugLog('MCP send-email-to-contact failure', $wpError->get_error_message(), 'error');
            }
        }, 10, 1);

        $campaign = CustomEmailCampaign::create($campaignData);

        $campaign->subscribe([(int) $contact->id], [
            'status'       => 'scheduled',
            'scheduled_at' => current_time('mysql'),
        ]);

        do_action('fluentcrm_process_contact_jobs', $contact);

        return [
            'ok'          => true,
            'campaign_id' => (int) $campaign->id,
            'message'     => __('Email queued for delivery', 'fluent-crm'),
            'contact'     => [
                'id'    => (int) $contact->id,
                'email' => $contact->email,
            ],
            'applied'     => [
                'is_transactional' => $isTransactional,
                'disable_footer'   => $disableFooter,
                'design_template'  => $designTemplate,
                'from'             => self::formatAddress($fromName, $fromEmail),
                'reply_to'         => self::formatAddress($replyToName, $replyToEmail),
            ],
        ];
    }

    /**
     * Render an RFC-5322 "Display Name <addr>" string. Previous version
     * (`trim(... ' <>')`) ate the closing `>` from any "Name (with parens)"
     * — review #15.
     */
    private static function formatAddress($name, $email)
    {
        $email = trim((string) $email);
        $name  = trim((string) $name);
        if ($email === '') return '';
        if ($name === '')  return $email;
        return $name . ' <' . $email . '>';
    }

    /**
     * `send-test-email` — render and send a one-off test copy of either:
     *   - a saved campaign (pass campaign_id), or
     *   - a draft body/subject the agent supplies inline.
     *
     * Differs from send-email-to-contact: NO campaign record is created,
     * NO subscriber is enrolled, NO row is logged to fc_campaign_emails,
     * and the recipient does NOT need to be subscribed. The subject is
     * prefixed with "TEST:" to match what the contact-profile UI does.
     * Mirrors CampaignController::sendTestEmail.
     */
    public static function sendTestEmail($params)
    {
        $params = (array) $params;

        // Resolve recipient address — defaults to the current WP user.
        $toEmail = sanitize_email((string) ($params['to_email'] ?? ''));
        if (!$toEmail) {
            $user = wp_get_current_user();
            $toEmail = $user ? $user->user_email : '';
        }
        if (!$toEmail || !is_email($toEmail)) {
            return MCPHelper::error('invalid_param', __('A valid to_email is required.', 'fluent-crm'));
        }

        // Source the email content from a saved campaign or inline params.
        $campaignId = isset($params['campaign_id']) ? (int) $params['campaign_id'] : 0;
        $subject = $body = $preHeader = '';
        $designTemplate = '';
        $settings = [];

        if ($campaignId) {
            // Need to bypass the global type scope so test sends work for
            // custom_email_campaign / sequence_mail / etc., not just
            // type='campaign'.
            $campaign = Campaign::withoutGlobalScope('type')->find($campaignId);
            if (!$campaign) {
                return MCPHelper::error('not_found', __('Campaign not found', 'fluent-crm'), ['campaign_id' => $campaignId]);
            }
            $subject        = (string) $campaign->email_subject;
            $body           = (string) $campaign->email_body;
            $preHeader      = (string) $campaign->email_pre_header;
            $designTemplate = (string) $campaign->design_template;
            $settings       = is_array($campaign->settings) ? $campaign->settings : (array) maybe_unserialize($campaign->settings);
        }

        // Inline params override campaign-derived values.
        if (isset($params['subject']) && $params['subject'] !== '') {
            $subject = (string) $params['subject'];
        }
        if (isset($params['body']) && $params['body'] !== '') {
            $body = (string) $params['body'];
        }
        if (isset($params['pre_header'])) {
            $preHeader = (string) $params['pre_header'];
        }
        if (isset($params['design_template']) && $params['design_template'] !== '') {
            $designTemplate = sanitize_key((string) $params['design_template']);
        }
        if ($designTemplate === '') {
            $designTemplate = 'classic';
        }
        // Apply the same MCP-safe enum guard as send-email-to-contact.
        $allowedTemplates = array_keys(ContextTools::allowedDesignTemplates());
        if (!in_array($designTemplate, $allowedTemplates, true)) {
            return MCPHelper::error('invalid_param', __('design_template not allowed via MCP', 'fluent-crm'), [
                'design_template' => $designTemplate,
                'allowed'         => $allowedTemplates,
            ]);
        }

        if ($subject === '' || $body === '') {
            return MCPHelper::error('invalid_param', __('Provide either campaign_id, or subject + body.', 'fluent-crm'));
        }

        // Resolve the subscriber whose data smartcodes get filled with.
        // Priority: explicit against_contact_*, then to_email, then any
        // subscribed contact (mirrors CampaignController fallback).
        $subscriber = null;
        if (!empty($params['against_contact_id'])) {
            $subscriber = Subscriber::find((int) $params['against_contact_id']);
        }
        if (!$subscriber && !empty($params['against_contact_email'])) {
            $subscriber = Subscriber::where('email', sanitize_email($params['against_contact_email']))->first();
        }
        if (!$subscriber) {
            $subscriber = Subscriber::where('email', $toEmail)->first();
        }
        if (!$subscriber) {
            $subscriber = Subscriber::where('status', 'subscribed')->first();
        }
        if (!$subscriber) {
            return MCPHelper::error('not_supported', __('No subscriber found to drive smartcode rendering. Add at least one subscribed contact.', 'fluent-crm'));
        }

        // Catch wp_mail errors for the response.
        $mailErrors = [];
        $mailErrorListener = function ($wpError) use (&$mailErrors) {
            $mailErrors[] = $wpError->get_error_message();
        };
        add_action('wp_mail_failed', $mailErrorListener, 10, 1);

        // Block-template rendering — same gate the controller uses.
        $rawTemplates = ['raw_html', 'raw_classic'];
        if (!in_array($designTemplate, $rawTemplates, true)) {
            $body = (new BlockParser($subscriber))->parse($body);
        }

        // Footer config — pulled from a stand-in object so we can pass non-
        // persisted draft data through Helper::getFooterConfig the same way
        // the controller does.
        $stub = (object) [
            'design_template' => $designTemplate,
            'settings'        => $settings ?: ['template_config' => []],
            'email_pre_header' => $preHeader,
            'email_body'      => $body,
            'email_subject'   => $subject,
        ];
        $footerConfig = method_exists(Helper::class, 'getFooterConfig') ? Helper::getFooterConfig($stub) : ['footer_content' => ''];
        $footerText   = Arr::get($footerConfig, 'footer_content', '');

        // Run the standard parse_campaign_email_text filter chain so
        // smartcodes resolve.
        $body       = apply_filters('fluent_crm/parse_campaign_email_text', $body, $subscriber);
        $footerText = apply_filters('fluent_crm/parse_campaign_email_text', $footerText, $subscriber);
        $subject    = apply_filters('fluent_crm/parse_campaign_email_text', $subject, $subscriber);
        $preHeader  = apply_filters('fluent_crm/parse_campaign_email_text', $preHeader, $subscriber);

        $footerConfig['footer_content'] = $footerText;

        $templateData = [
            'preHeader'     => $preHeader,
            'email_body'    => $body,
            'footer_text'   => $footerText,
            'footer_config' => $footerConfig,
            'config'        => wp_parse_args(
                Arr::get($settings, 'template_config', []),
                Helper::getTemplateConfig($designTemplate)
            ),
        ];

        $body = apply_filters(
            'fluent_crm/email-design-template-' . $designTemplate,
            $body,
            $templateData,
            $stub,
            $subscriber
        );

        $body = str_replace('{{crm_global_email_footer}}', $footerText, $body);
        $body = str_replace('{{crm_preheader_text}}', $preHeader, $body);

        $data = [
            'to'      => [
                'email' => $toEmail,
                'name'  => $subscriber->full_name ?: $toEmail,
            ],
            'subject' => 'TEST: ' . $subject,
            'body'    => $body,
            'headers' => Helper::getMailHeadersFromSettings(Arr::get($settings, 'mailer_settings', [])),
        ];

        if (method_exists(Helper::class, 'maybeDisableEmojiOnEmail')) {
            Helper::maybeDisableEmojiOnEmail();
        }
        $result = Mailer::send($data, $subscriber, null, true);

        remove_action('wp_mail_failed', $mailErrorListener, 10);

        $sent = $result !== false && empty($mailErrors);

        return [
            'ok'                 => $sent,
            'sent'               => $sent,
            'to'                 => $toEmail,
            'rendered_against'   => [
                'contact_id' => (int) $subscriber->id,
                'email'      => $subscriber->email,
            ],
            'subject_preview'    => 'TEST: ' . $subject,
            'design_template'    => $designTemplate,
            'errors'             => $mailErrors,
            'note'               => __('Test sends bypass the queue, do not enroll the recipient, and do not appear in email_history.', 'fluent-crm'),
        ];
    }

    private static function yesNo($value, $default = 'no')
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        $str = strtolower((string) $value);
        if (in_array($str, ['yes', 'true', '1', 'on'], true)) {
            return 'yes';
        }
        if (in_array($str, ['no', 'false', '0', 'off', ''], true)) {
            return 'no';
        }
        return $default;
    }

    private static function trackerValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $str = strtolower((string) $value);
        if (in_array($str, ['yes', 'no', 'anonymous'], true)) {
            return $str;
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        return null;
    }
}
