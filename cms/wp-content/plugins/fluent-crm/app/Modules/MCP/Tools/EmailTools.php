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
use FluentCrm\App\Services\PermissionManager;
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

        // TRC-006: validate utm value types + validate/sanitize the settings
        // closed shapes before any write. composeSettings merges the sanitized
        // subset; here we only need the hard error on invalid input.
        $utmError = MCPHelper::validateUtm($params);
        if (is_wp_error($utmError)) {
            return $utmError;
        }
        $settingsCheck = MCPHelper::sanitizeSettingsShape($params['settings'] ?? null);
        if (is_wp_error($settingsCheck)) {
            return $settingsCheck;
        }

        $composed = self::composeSettings($params, $designTemplate);
        $settings = $composed['settings'];
        $isTransactional = $composed['applied']['is_transactional'];
        $disableFooter   = $composed['applied']['disable_footer'];

        // Custom title for audit / log; default keeps recipient email so the
        // entry is searchable in the campaign list. Shares the title builder
        // with SubscriberController::sendCustomEmail so both one-off paths
        // produce the same shape — the 'MCP' tag records the provenance.
        $title = isset($params['title']) && $params['title'] !== ''
            ? sanitize_text_field((string) $params['title'])
            : Helper::oneOffEmailTitle($contact->email, 'MCP');

        $campaignData = [
            'title'            => $title,
            'email_subject'    => $subject,
            'email_pre_header' => sanitize_text_field((string) ($params['pre_header'] ?? $params['preheader'] ?? '')),
            'email_body'       => $body,
            'design_template'  => $designTemplate,
            'settings'         => $settings,
            // Matches SubscriberController::sendCustomEmail. A one-off is
            // queued for delivery the moment it is created, so 'draft' was
            // wrong on its face and made list-campaigns(include_one_offs=true,
            // status=[...]) return an arbitrary slice of one-offs depending on
            // whether the UI or MCP created them. The row's own status is
            // descriptive only — the real delivery state lives on the single
            // fc_campaign_emails row (see CampaignTools::formatOneOffEmail).
            'status'           => 'published',
        ];

        // UTM tagging — flatten the optional `utm` object onto the
        // campaign's utm_* columns.
        if (!empty($params['utm']) && is_array($params['utm'])) {
            $utm = $params['utm'];
            $campaignData['utm_status'] = !empty($utm['status']) ? 1 : 0;
            // Shared allowlist with upsert-campaign so the two never drift.
            foreach (MCPHelper::utmAllowedKeys() as $key) {
                if ($key === 'status') {
                    continue;
                }
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
            'applied'     => $composed['applied'] + [
                'design_template' => $designTemplate,
            ],
        ];
    }

    /**
     * Compose the campaign-style settings block (mailer overrides,
     * transactional flag, footer, trackers, template config) from tool
     * params. Shared by send-email-to-contact and send-test-email so a
     * test/preview renders with exactly the settings the real send would
     * use — a preview that silently falls back to the site default sender
     * and footer misrepresents the live send (MCP feedback 2026-07, P1 #1).
     *
     * @return array{settings: array, applied: array}
     */
    private static function composeSettings(array $params, $designTemplate)
    {
        $defaults = Helper::getGlobalEmailSettings();

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

        // Compose the settings object the way the UI does.
        $settings = [
            'mailer_settings'  => [
                'from_name'      => $fromName,
                'from_email'     => $fromEmail,
                'reply_to_name'  => $replyToName,
                'reply_to_email' => $replyToEmail,
                'is_custom'      => 'yes',
            ],
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

        // Allow callers to pass an arbitrary `settings` object for things we
        // haven't surfaced as top-level params (e.g. visual-builder overrides).
        // Only the sanitized closed shapes are merged — write tools already
        // hard-errored on invalid values; the shared render path
        // (send-test/preview) uses the sanitized subset (TRC-006).
        if (!empty($params['settings']) && is_array($params['settings'])) {
            $extraSettings = MCPHelper::sanitizeSettingsShape($params['settings']);
            if (is_array($extraSettings)) {
                $settings = array_replace_recursive($settings, $extraSettings);
            }
        }

        return [
            'settings' => $settings,
            'applied'  => [
                'is_transactional' => $isTransactional,
                'disable_footer'   => $disableFooter,
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
     * Shared render pipeline for send-test-email and render-email-preview so
     * their subject/body/footer/SmartCode output can never drift apart
     * (TRC-004a). Sources content from a saved campaign (campaign_id) or an
     * inline draft (subject + body), composes settings with full sender/footer
     * parity to a live send (MCP feedback 2026-07, P1 #1), resolves the
     * subscriber SmartCodes render against, and returns the finished pieces.
     *
     * This only renders: NO campaign record is created, NO subscriber is
     * enrolled, and NO row is logged to fc_campaign_emails. $requireRecipient
     * errors on a missing/invalid to_email — a real send needs a destination, a
     * preview does not. Open/click tracking rewrites happen at real send time
     * against a logged row, so they never apply here.
     *
     * @return array|\WP_Error
     */
    private static function buildRenderedEmail(array $params, $requireRecipient)
    {
        // Validate + sanitize the caller's settings passthrough once, up front,
        // so both content sources (saved campaign / inline draft) merge the same
        // vetted value and a malformed shape errors instead of being dropped.
        $extraSettings = MCPHelper::sanitizeSettingsShape($params['settings'] ?? null);
        if (is_wp_error($extraSettings)) {
            return $extraSettings;
        }

        // Resolve recipient address — defaults to the current WP user. A
        // preview sends nothing, so it doesn't require a valid recipient.
        $toEmail = sanitize_email((string) ($params['to_email'] ?? ''));
        if (!$toEmail) {
            $user = wp_get_current_user();
            $toEmail = $user ? $user->user_email : '';
        }
        if ($requireRecipient && (!$toEmail || !is_email($toEmail))) {
            return MCPHelper::error('invalid_param', __('A valid to_email is required.', 'fluent-crm'));
        }
        $toEmail = sanitize_email($toEmail);

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

        // Settings parity with send-email-to-contact. Inline drafts get the
        // full composed block (same defaults the live send would use). For a
        // saved campaign, its stored settings win and only explicitly-passed
        // params override the matching keys.
        if (!$campaignId) {
            $composed = self::composeSettings($params, $designTemplate);
            $settings = $composed['settings'];
        } else {
            $override = [];
            $mailerOverride = [];
            foreach (['from_name', 'from_email', 'reply_to_name', 'reply_to_email'] as $mailerKey) {
                if (isset($params[$mailerKey]) && $params[$mailerKey] !== '') {
                    $mailerOverride[$mailerKey] = strpos($mailerKey, 'email') !== false
                        ? sanitize_email((string) $params[$mailerKey])
                        : sanitize_text_field((string) $params[$mailerKey]);
                }
            }
            if ($mailerOverride) {
                $mailerOverride['is_custom'] = 'yes';
                $override['mailer_settings'] = $mailerOverride;
            }
            if (array_key_exists('is_transactional', $params)) {
                $override['is_transactional'] = self::yesNo($params['is_transactional'], 'no');
            }
            if (array_key_exists('disable_footer', $params)) {
                $override['footer_settings'] = [
                    'disable_footer' => self::yesNo($params['disable_footer'], 'no'),
                ];
            }
            // The schema documents `settings` as merged into the render settings;
            // the inline-draft branch gets that via composeSettings(), so apply it
            // here too or a saved-campaign preview would silently ignore it.
            // Merged last so it wins over the top-level shorthand params, matching
            // composeSettings()'s precedence.
            if ($extraSettings) {
                $override = array_replace_recursive($override, $extraSettings);
            }
            if ($override) {
                $settings = array_replace_recursive($settings, $override);
            }
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
            // The catch-all fallback picks a contact the caller never named,
            // and the rendered SmartCodes carry that contact's PII into a body
            // delivered to a to_email the caller chose. Gating only explicit
            // selection would close the front door and leave this one open, so
            // the same capability applies here (review PR #2025). Matching
            // to_email above is exempt: that address came from the caller, so
            // rendering against it discloses nothing new.
            if (!PermissionManager::currentUserCan('fcrm_read_contacts')) {
                return MCPHelper::error('forbidden', __('No contact matches to_email, and rendering against an unrelated contact requires contact read permission. Send the test to an address that exists as a contact, or ask an administrator for contact-read access.', 'fluent-crm'), [
                    'required' => 'fcrm_read_contacts',
                    'to_email' => $toEmail,
                ]);
            }
            $subscriber = Subscriber::where('status', 'subscribed')->first();
        }
        if (!$subscriber) {
            return MCPHelper::error('not_supported', __('No subscriber found to drive smartcode rendering. Add at least one subscribed contact.', 'fluent-crm'));
        }

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

        $headers = Helper::getMailHeadersFromSettings(Arr::get($settings, 'mailer_settings', []));

        // Echo the effective sender/footer config so the agent can verify
        // what the render used without inspecting the received email.
        $mailer  = Arr::get($settings, 'mailer_settings', []);
        $applied = [
            'is_transactional' => Arr::get($settings, 'is_transactional', 'no'),
            'disable_footer'   => Arr::get($settings, 'footer_settings.disable_footer', 'no'),
            'from'             => self::formatAddress(Arr::get($mailer, 'from_name', ''), Arr::get($mailer, 'from_email', '')),
            'reply_to'         => self::formatAddress(Arr::get($mailer, 'reply_to_name', ''), Arr::get($mailer, 'reply_to_email', '')),
            'design_template'  => $designTemplate,
        ];

        $renderedAgainst = [
            'contact_id' => (int) $subscriber->id,
            'email'      => $subscriber->email,
        ];

        return [
            'subject'          => $subject,
            'pre_header'       => $preHeader,
            'html'             => $body,
            'headers'          => $headers,
            'applied'          => $applied,
            'rendered_against' => $renderedAgainst,
            'to_email'         => $toEmail,
            'subscriber'       => $subscriber,
        ];
    }

    /**
     * `render-email-preview` — resolve subject/body/SmartCodes for a saved
     * campaign or an inline draft and return the final HTML. Sends nothing,
     * creates no record, enrolls no one — safe to auto-approve. Shares the exact
     * render pipeline as send-test-email so the preview matches the live send.
     */
    public static function renderEmailPreview($params)
    {
        $rendered = self::buildRenderedEmail((array) $params, false);
        if (is_wp_error($rendered)) {
            return $rendered;
        }

        return [
            'ok'               => true,
            'rendered'         => true,
            'sent'             => false,
            'subject'          => $rendered['subject'],
            'pre_header'       => $rendered['pre_header'],
            'html'             => $rendered['html'],
            'headers'          => $rendered['headers'],
            'applied'          => $rendered['applied'],
            'rendered_against' => $rendered['rendered_against'],
            'note'             => __('Preview only — SmartCodes resolved with the exact settings a live send would use. Nothing was sent and no records were created.', 'fluent-crm'),
        ];
    }

    /**
     * `send-test-email` — deliver a real test copy (subject prefixed "TEST:")
     * of a saved campaign or an inline draft. This ALWAYS sends an external
     * email; it is not a preview — use render-email-preview for a no-send
     * render. No campaign record is created, no subscriber is enrolled, and
     * nothing is logged to fc_campaign_emails.
     */
    public static function sendTestEmail($params)
    {
        $params = (array) $params;

        // Back-compat: render_only was the pre-1.8.0 way to preview from this
        // tool. render-email-preview is the dedicated tool now, but honor the
        // flag so a caller that still passes it previews instead of accidentally
        // sending real mail after the split.
        if (!empty($params['render_only'])) {
            // Enforce render-email-preview's OWN permission gate here. A preview
            // returns the rendered body plus rendered_against — the resolved
            // contact's email and name via SmartCodes — which is why the
            // dedicated tool requires fcrm_read_contacts on top of the email
            // caps. This tool only requires fcrm_manage_emails, and that
            // capability does NOT imply fcrm_read_contacts (see
            // PermissionManager: it depends on fcrm_read_emails alone). The
            // Abilities API validates input against the schema but never strips
            // undeclared keys, so render_only reaches this callback over MCP
            // even though it is no longer advertised — without this re-check the
            // back-compat path would hand an email-only manager exactly the
            // contact PII the split was made to protect.
            // BOTH caps, exactly mirroring render-email-preview's callback —
            // fcrm_manage_emails does not imply fcrm_read_emails either
            // (PermissionManager's `depends` map documents intent; it is not
            // enforced by currentUserCan). Checking only contact-read left a
            // caller with manage_emails + read_contacts but no read_emails able
            // to render here while the dedicated tool refused them.
            foreach (['fcrm_read_emails', 'fcrm_read_contacts'] as $cap) {
                $guard = MCPHelper::permissionGuard($cap);
                if (is_wp_error($guard)) {
                    return $guard;
                }
            }

            return self::renderEmailPreview($params);
        }

        // Naming the render contact is a capability this tool adds ON TOP of
        // core: CampaignController::sendTestEmail only ever renders against the
        // recipient address, then falls back to an arbitrary subscribed contact
        // — it never lets the caller pick one. Picking one turns a
        // deliverability check into a contact lookup, because the body's
        // SmartCodes resolve that contact's PII and the mail then goes to any
        // to_email the caller chooses. fcrm_manage_emails does NOT imply
        // fcrm_read_contacts (PermissionManager's `depends` map documents
        // intent; currentUserCan does not enforce it), so gate the beyond-core
        // part on contact-read and leave plain test sends at parity with core.
        if (!empty($params['against_contact_id']) || !empty($params['against_contact_email'])) {
            $guard = MCPHelper::permissionGuard('fcrm_read_contacts');
            if (is_wp_error($guard)) {
                return $guard;
            }
        }

        $rendered = self::buildRenderedEmail($params, true);
        if (is_wp_error($rendered)) {
            return $rendered;
        }

        $toEmail    = $rendered['to_email'];
        $subscriber = $rendered['subscriber'];
        $subject    = $rendered['subject'];

        // Catch wp_mail errors for the response.
        $mailErrors = [];
        $mailErrorListener = function ($wpError) use (&$mailErrors) {
            $mailErrors[] = $wpError->get_error_message();
        };
        add_action('wp_mail_failed', $mailErrorListener, 10, 1);

        $data = [
            'to'      => [
                'email' => $toEmail,
                'name'  => $subscriber->full_name ?: $toEmail,
            ],
            'subject' => 'TEST: ' . $subject,
            'body'    => $rendered['html'],
            'headers' => $rendered['headers'],
        ];

        if (method_exists(Helper::class, 'maybeDisableEmojiOnEmail')) {
            Helper::maybeDisableEmojiOnEmail();
        }
        $result = Mailer::send($data, $subscriber, null, true);

        remove_action('wp_mail_failed', $mailErrorListener, 10);

        $sent = $result !== false && empty($mailErrors);

        // rendered_against identifies a contact the caller may not be entitled
        // to read: with no explicit selection the fallback lands on the
        // to_email match or, failing that, ANY subscribed contact. Echo the
        // identity only to callers who hold contact-read; everyone else still
        // gets the confirmation that a render contact was found.
        $renderedAgainst = PermissionManager::currentUserCan('fcrm_read_contacts')
            ? $rendered['rendered_against']
            : ['note' => __('Rendered against a contact. Identity hidden — requires contact read permission.', 'fluent-crm')];

        return [
            'ok'                 => $sent,
            'sent'               => $sent,
            'to'                 => $toEmail,
            'rendered_against'   => $renderedAgainst,
            'subject_preview'    => 'TEST: ' . $subject,
            'applied'            => $rendered['applied'],
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
