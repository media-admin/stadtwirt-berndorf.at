<?php

namespace FluentCrm\App\Services\Libs\Mailer;

use FluentCrm\Framework\Support\Arr;

class Mailer
{
    public static function send($data, $subscriber = null, $emailModel = null, $preThrottled = false)
    {

        $headers = static::buildHeaders($data, $subscriber, $emailModel);

        if (apply_filters('fluent_crm/is_simulated_mail', false, $data, $headers)) {
            return true;
        }

        $to = $data['to']['email'];

        if (!$to) {
            return false;
        }

        if (self::willIncludeName()) {
            if ($name = Arr::get($data, 'to.name')) {
                $name = sanitize_text_field($name);
                // If the name contains a comma, we need to wrap it in double quotes to prevent issues with email clients
                if (strpos($name, ',') !== false) {
                    $name = '"' . str_replace('"', '\"', $name) . '"';
                }

                $to = $name . ' <' . $to . '>';
            }
        }

        // Global cross-process rate cap. Every email — campaigns, automation,
        // double opt-in, transactional — funnels through here, so this is the
        // single point that holds the install's aggregate send rate within the
        // provider's per-second limit. Fail-open: never blocks if its store is
        // unavailable. Placed after the simulated-mail / empty-recipient guards
        // so only real dispatches consume a slot.
        //
        // The bulk handlers reserve their slot BEFORE marking the row sent (so a
        // crash mid-wait leaves it recoverable) and pass $preThrottled=true to
        // skip a second reservation here. Direct callers (double opt-in, etc.)
        // leave it false and get throttled here.
        if (!$preThrottled) {
            GlobalRateLimiter::throttle($data);
        }

        return wp_mail(
            $to,
            $data['subject'],
            $data['body'],
            $headers
        );
    }

    protected static function buildHeaders($data, $subscriber = null, $emailModel = null)
    {
        $data = apply_filters('fluent_crm/email_data_before_headers', $data, $subscriber, $emailModel);

        $headers = [];

        $contentType = Arr::get($data, 'headers.Content-Type');
        if ($contentType) {
            $headers[] = "Content-Type: {$contentType}";
        } else {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        }

        $from = Arr::get($data, 'headers.From');
        $replyTo = Arr::get($data, 'headers.Reply-To');

        if ($from) {
            $headers[] = "From: {$from}";
        }

        // Set Reply-To Header
        if ($replyTo) {
            $headers[] = "Reply-To: {$replyTo}";
        }

        if ($subscriber && apply_filters('fluent_crm/enable_unsub_header', true, $data, $subscriber, $emailModel)) {
            $campaign = ($emailModel && $emailModel->campaign) ? $emailModel->campaign : null;
            $isTransactional = $campaign && Arr::get($campaign->settings, 'is_transactional') == 'yes';
            if (!$isTransactional) {
                $args = [
                    'fluentcrm'   => 1,
                    'route'       => 'unsubscribe',
                    'secure_hash' => fluentCrmGetContactManagedHash($subscriber->id)
                ];
                if ($emailModel) {
                    $args['ce_id'] = $emailModel->id;
                }

                $unsubscribeUrl = add_query_arg($args, site_url('index.php'));

                $headers[] = "List-Unsubscribe: <{$unsubscribeUrl}>";
                $headers[] = "List-Unsubscribe-Post: List-Unsubscribe=One-Click";
            }
        }

        return apply_filters('fluent_crm/email_headers', $headers, $data, $subscriber, $emailModel);
    }

    private static function willIncludeName()
    {
        static $status = null;
        if ($status !== null) {
            return $status;
        }
        $status = apply_filters('fluent_crm/enable_mailer_to_name', true);
        return $status;
    }
}
