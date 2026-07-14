<?php

namespace FluentCrm\App\Services\ExternalIntegrations\MailComplaince;


use FluentCrm\App\Hooks\Handlers\ExternalPages;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

class Webhook
{
    /**
     * @param $serviceName
     * @param $request \FluentCrm\Framework\Http\Request\Request
     */
    public function handle($serviceName, $request)
    {
        $method = 'handle' . ucfirst(strtolower($serviceName));

        if (method_exists($this, $method)) {
            return $this->{$method}($request);
        }

        return null;
    }

    /**
     * @param $request \FluentCrm\Framework\Http\Request\Request
     */
    private function handleMailgun($request)
    {
        $payload = $this->resolvePayload($request, []);

        $eventData = Arr::get($payload, 'event-data', []);

        if (!$eventData) {
            // Fallback: try reading from request params directly
            $eventData = $request->get('event-data', []);
        }

        if (!$eventData) {
            return false;
        }

        $event = Arr::get($eventData, 'event');

        $catchEvents = ['failed', 'unsubscribed', 'complained'];

        if (!in_array($event, $catchEvents)) {
            return false;
        }

        $recipientEmail = Arr::get($eventData, 'recipient');
        if (!$recipientEmail) {
            return false;
        }

        $externalPages = new ExternalPages();

        // For failed events, check severity to distinguish soft vs hard bounce
        if ($event == 'failed') {
            $severity = Arr::get($eventData, 'severity', 'permanent');
            if ($severity == 'temporary') {
                $description = Arr::get($eventData, 'delivery-status.message', '');
                if (!$description) {
                    $description = Arr::get($eventData, 'delivery-status.description', '');
                }
                return $externalPages->recordSoftBounce([
                    'email'  => $recipientEmail,
                    'reason' => __('Soft bounce was set by Mailgun webhook API.', 'fluent-crm') . ' ' . $description . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
                ]);
            }
        }

        $newStatus = 'bounced';
        if ($event == 'complained') {
            $newStatus = 'complained';
        } else if ($event == 'unsubscribed') {
            $newStatus = 'unsubscribed';
        }

        $unsubscribeData = [
            'email'  => $recipientEmail,
            'reason' => $newStatus . __(' was set by Mailgun webhook API with event name: ', 'fluent-crm') . $event . __(' at ', 'fluent-crm') . current_time('mysql'),
            'status' => $newStatus
        ];

        return $externalPages->recordUnsubscribe($unsubscribeData);
    }

    /**
     * @param $request \FluentCrm\Framework\Http\Request\Request
     * @return boolean
     */
    private function handleSendgrid($request)
    {
        $events = $this->resolvePayload($request, []);

        if (!$events || !count($events)) {
            return false;
        }

        $externalPages = new ExternalPages();
        $processed = false;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventName = Arr::get($event, 'event');
            $email = Arr::get($event, 'email');

            if (!$email || !in_array($eventName, ['dropped', 'bounce', 'spamreport', 'unsubscribe'])) {
                continue;
            }

            if ($eventName == 'unsubscribe') {
                $externalPages->recordUnsubscribe([
                    'email'  => $email,
                    'reason' => __('unsubscribed status was set from SendGrid Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
                    'status' => 'unsubscribed'
                ]);
                $processed = true;
                continue;
            }

            if ($eventName == 'spamreport') {
                $externalPages->recordUnsubscribe([
                    'email'  => $email,
                    'reason' => __('complained status was set from SendGrid Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
                    'status' => 'complained'
                ]);
                $processed = true;
                continue;
            }

            // bounce or dropped — check type field for soft vs hard bounce
            $bounceType = Arr::get($event, 'type', 'bounce');
            $reason = Arr::get($event, 'reason', '');
            if ($bounceType == 'blocked') {
                $externalPages->recordSoftBounce([
                    'email'  => $email,
                    'reason' => __('Soft bounce from SendGrid Webhook API. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
                ]);
            } else {
                $externalPages->recordUnsubscribe([
                    'email'  => $email,
                    'reason' => __('bounced status was set from SendGrid Webhook API. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
                    'status' => 'bounced'
                ]);
            }
            $processed = true;
        }

        return $processed;
    }

    /**
     * @param $request \FluentCrm\Framework\Http\Request\Request
     * @return boolean
     */
    private function handlePepipost($request)
    {
        $events = $this->resolvePayload($request, []);

        if (!$events || !count($events)) {
            return false;
        }

        $externalPages = new ExternalPages();
        $processed = false;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventName = Arr::get($event, 'EVENT');

            if (!in_array($eventName, ['bounced', 'invalid', 'spam', 'unsubscribed'])) {
                continue;
            }

            $newStatus = 'bounced';
            if ($eventName == 'unsubscribed') {
                $newStatus = 'unsubscribed';
            } else if ($eventName == 'spam') {
                $newStatus = 'complained';
            }

            $reason = $newStatus . __(' status was set from Pepipost Webhook API. Reason: ', 'fluent-crm') . Arr::get($event, 'BOUNCE_TYPE') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql');

            if ($sourceResponse = Arr::get($event, 'RESPONSE')) {
                $reason = $sourceResponse;
            }

            $email = Arr::get($event, 'EMAIL');
            if ($email) {
                $externalPages->recordUnsubscribe([
                    'email'  => $email,
                    'reason' => $reason,
                    'status' => $newStatus
                ]);
                $processed = true;
            }
        }

        return $processed;
    }

    /**
     * @param $request \FluentCrm\Framework\Http\Request\Request
     * @return boolean
     */
    private function handleSparkpost($request)
    {
        $events = $this->resolvePayload($request, []);

        if (!$events || !count($events)) {
            return false;
        }

        $externalPages = new ExternalPages();
        $processed = false;

        // SparkPost hard bounce classes: 10 (Invalid Recipient), 25 (Admin Failure),
        // 30 (Generic Bounce: No RCPT), 90 (Unsubscribe)
        $hardBounceClasses = [10, 25, 30, 90];

        foreach ($events as $eventWrapper) {
            if (!is_array($eventWrapper)) {
                continue;
            }

            // SparkPost wraps events in msys.message_event or msys.unsubscribe_event
            $event = Arr::get($eventWrapper, 'msys.message_event');
            if (!$event || !is_array($event)) {
                $event = Arr::get($eventWrapper, 'msys.unsubscribe_event');
            }
            if (!$event || !is_array($event)) {
                continue;
            }

            $eventName = Arr::get($event, 'type');
            if (!in_array($eventName, ['bounce', 'out_of_band', 'spam_complaint', 'link_unsubscribe', 'list_unsubscribe'])) {
                continue;
            }

            $email = Arr::get($event, 'rcpt_to');
            if (!$email) {
                continue;
            }

            $reason = Arr::get($event, 'raw_reason', '');
            if (!$reason) {
                $reason = $eventName . __(' status was set from SparkPost Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql');
            }

            // Both bounce and out_of_band events carry bounce_class
            if (in_array($eventName, ['bounce', 'out_of_band'])) {
                $bounceClass = (int)Arr::get($event, 'bounce_class', 0);
                if (!in_array($bounceClass, $hardBounceClasses)) {
                    $externalPages->recordSoftBounce([
                        'email'  => $email,
                        'reason' => $reason
                    ]);
                    $processed = true;
                    continue;
                }
            }

            $newStatus = 'bounced';
            if (in_array($eventName, ['link_unsubscribe', 'list_unsubscribe'])) {
                $newStatus = 'unsubscribed';
            } else if ($eventName == 'spam_complaint') {
                $newStatus = 'complained';
            }

            $externalPages->recordUnsubscribe([
                'email'  => $email,
                'reason' => $reason,
                'status' => $newStatus
            ]);
            $processed = true;
        }

        return $processed;
    }

    /**
     * @param $request \FluentCrm\Framework\Http\Request\Request
     * @return boolean
     */
    private function handlePostmark($request)
    {
        $event = $this->resolvePayload($request, []);

        if (!$event || !is_array($event)) {
            return false;
        }

        $eventName = Arr::get($event, 'RecordType');
        if (!in_array($eventName, ['Bounce', 'SpamComplaint'])) {
            return false;
        }

        $email = Arr::get($event, 'Email');
        if (!$email) {
            return false;
        }

        $reason = Arr::get($event, 'Description', '');
        if (!$reason) {
            $reason = $eventName . __(' status was set from Postmark Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql');
        }

        $externalPages = new ExternalPages();

        if ($eventName == 'SpamComplaint') {
            return $externalPages->recordUnsubscribe([
                'email'  => $email,
                'reason' => $reason,
                'status' => 'complained'
            ]);
        }

        // For Bounce events, check Type to distinguish soft vs hard
        $bounceType = Arr::get($event, 'Type', '');
        if ($bounceType == 'SoftBounce' || $bounceType == 'Transient') {
            return $externalPages->recordSoftBounce([
                'email'  => $email,
                'reason' => $reason
            ]);
        }

        return $externalPages->recordUnsubscribe([
            'email'  => $email,
            'reason' => $reason,
            'status' => 'bounced'
        ]);
    }

    private function handleElasticemail($request)
    {
        $status = strtolower($request->get('status'));

        $processStatuses = [
            'error',
            'abusereport',
            'unsubscribed'
        ];

        if (!in_array($status, $processStatuses)) {
            return [
                'message' => 'unknown_status'
            ];
        }

        $email = $request->get('to');
        if (!$email) {
            return false;
        }

        $externalPages = new ExternalPages();

        $softBounceCategories = [
            'AccountProblem',
            'Throttled',
            'SPFProblem',
            'Timeout',
            'ConnectionProblem',
            'GreyListed',
            'WhitelistingProblem',
            'CodeError'
        ];

        $category = $request->get('category', 'unknown');

        if ($status == 'error') {
            if (in_array($category, $softBounceCategories)) {
                return $externalPages->recordSoftBounce([
                    'email'  => $email,
                    'reason' => __('Soft bounce from ElasticEmail Webhook API. Category: ', 'fluent-crm') . $category . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
                ]);
            }

            return $externalPages->recordUnsubscribe([
                'email'  => $email,
                'reason' => __('bounced status was set from ElasticEmail Webhook API. Category: ', 'fluent-crm') . $category . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
                'status' => 'bounced'
            ]);
        }

        if ($status == 'abusereport') {
            return $externalPages->recordUnsubscribe([
                'email'  => $email,
                'reason' => __('complained status was set from ElasticEmail Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
                'status' => 'complained'
            ]);
        }

        // unsubscribed
        return $externalPages->recordUnsubscribe([
            'email'  => $email,
            'reason' => __('unsubscribed status was set from ElasticEmail Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
            'status' => 'unsubscribed'
        ]);
    }

    private function handlePostalserver($request)
    {
        $event = strtolower($request->get('event'));

        $processStatuses = [
            'messagebounced',
            'messagedeliveryfailed',
            'messagedelayed'
        ];

        if (!in_array($event, $processStatuses)) {
            return false;
        }

        $payload = $request->get('payload');

        if (!$payload || !is_array($payload)) {
            return false;
        }

        $externalPages = new ExternalPages();

        if ($event == 'messagedeliveryfailed') {
            $payloadStatus = Arr::get($payload, 'status');
            $toEmail = Arr::get($payload, 'message.to');
            $reason = Arr::get($payload, 'details', 'Unknown Reason');

            if (!$toEmail || !is_email($toEmail)) {
                return false;
            }

            // SoftFail → record as soft bounce
            if ($payloadStatus != 'HardFail') {
                return $externalPages->recordSoftBounce([
                    'email'  => $toEmail,
                    'reason' => __('Soft bounce from PostalServer. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
                ]);
            }

            return $externalPages->recordUnsubscribe([
                'email'  => $toEmail,
                'reason' => $reason,
                'status' => 'bounced'
            ]);
        }

        if ($event == 'messagedelayed') {
            $toEmail = Arr::get($payload, 'message.to');
            $reason = Arr::get($payload, 'details', 'Unknown Reason');

            if (!$toEmail || !is_email($toEmail)) {
                return false;
            }

            return $externalPages->recordSoftBounce([
                'email'  => $toEmail,
                'reason' => __('Soft bounce (delayed) from PostalServer. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
            ]);
        }

        // messagebounced — use original_message.to (not bounce.to which is the return-path)
        $toEmail = Arr::get($payload, 'original_message.to');
        if (!$toEmail || !is_email($toEmail)) {
            return false;
        }

        $reason = __('Bounce notification received from PostalServer.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql');

        return $externalPages->recordUnsubscribe([
            'email'  => $toEmail,
            'reason' => $reason,
            'status' => 'bounced'
        ]);
    }

    private function handleSmtp2go($request)
    {
        $event = strtolower($request->get('event'));

        $processStatuses = [
            'bounce',
            'spam',
            'unsubscribe'
        ];

        if (!in_array($event, $processStatuses)) {
            return false;
        }

        $toEmail = $request->get('rcpt');
        if (!$toEmail || !is_email($toEmail)) {
            return false;
        }

        $reason = sanitize_textarea_field($request->get('message', 'Unknown Reason'));
        $externalPages = new ExternalPages();

        if ($event == 'bounce') {
            $bounceType = $request->get('bounce');
            if ($bounceType == 'soft') {
                return $externalPages->recordSoftBounce([
                    'email'  => $toEmail,
                    'reason' => __('Soft bounce from SMTP2GO Webhook API. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
                ]);
            }
        }

        $newStatus = 'bounced';
        if ($event == 'unsubscribe') {
            $newStatus = 'unsubscribed';
        } else if ($event == 'spam') {
            $newStatus = 'complained';
        }

        return $externalPages->recordUnsubscribe([
            'email'  => $toEmail,
            'reason' => $reason,
            'status' => $newStatus
        ]);
    }

    /**
     * @param $request \FluentCrm\Framework\Http\Request\Request
     * @return boolean
     */
    private function handleBrevo($request)
    {
        $event = $this->resolvePayload($request, []);

        if (!$event || !count($event)) {
            return false;
        }

        $eventName = Arr::get($event, 'event');
        if (!in_array($eventName, ['soft_bounce', 'hard_bounce', 'invalid', 'invalid_email', 'spam', 'error', 'blocked', 'deferred', 'unsubscribe', 'unsubscribed'])) {
            return false;
        }

        $email = Arr::get($event, 'email');
        if (!$email) {
            return false;
        }

        $externalPages = new ExternalPages();
        $reason = Arr::get($event, 'reason', '');

        // Soft bounces and deferred deliveries should be tracked separately
        if (in_array($eventName, ['soft_bounce', 'deferred'])) {
            $reasonPrefix = $eventName === 'deferred'
                ? __('Deferred delivery from Brevo Webhook API. Reason: ', 'fluent-crm')
                : __('Soft bounce from Brevo Webhook API. Reason: ', 'fluent-crm');
            return $externalPages->recordSoftBounce([
                'email'  => $email,
                'reason' => $reasonPrefix . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
            ]);
        }

        $newStatus = 'bounced';
        if (in_array($eventName, ['unsubscribe', 'unsubscribed'])) {
            $newStatus = 'unsubscribed';
        } else if ($eventName == 'spam') {
            $newStatus = 'complained';
        }
        // hard_bounce, invalid, invalid_email, error, blocked → bounced

        return $externalPages->recordUnsubscribe([
            'email'  => $email,
            'reason' => $newStatus . __(' status was set from Brevo Webhook API. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
            'status' => $newStatus
        ]);
    }

    private function handleTosend($request)
    {
        $event = $this->resolvePayload($request, []);

        if (!$event || !is_array($event)) {
            return false;
        }

        $eventType = Arr::get($event, 'type');
        if (!in_array($eventType, ['bounce', 'complaint'])) {
            return false;
        }

        $email = Arr::get($event, 'email');
        if (!$email) {
            return false;
        }

        $externalPages = new ExternalPages();

        if ($eventType == 'complaint') {
            return $externalPages->recordUnsubscribe([
                'email'  => $email,
                'reason' => __('Complaint received from ToSend Webhook API.', 'fluent-crm') . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
                'status' => 'complained'
            ]);
        }

        // Bounce event — check is_hard_bounce flag
        $reason = Arr::get($event, 'reason', 'Unknown');
        $isHardBounce = Arr::get($event, 'is_hard_bounce', false);

        // Sender-fault bounces (our content/message, not the recipient's mailbox) —
        // do NOT penalise the subscriber. Prefer the explicit `sender_fault` flag;
        // fall back to `bounce_sub_type` for older payloads.
        $senderFaultSubTypes = ['MessageTooLarge', 'ContentRejected', 'AttachmentRejected'];
        $isSenderFault = (bool) Arr::get($event, 'sender_fault', false)
            || (!$isHardBounce && in_array(Arr::get($event, 'bounce_sub_type'), $senderFaultSubTypes, true));

        if ($isSenderFault) {
            return true;
        }

        if (!$isHardBounce) {
            return $externalPages->recordSoftBounce([
                'email'  => $email,
                'reason' => __('Soft bounce from ToSend Webhook API. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql')
            ]);
        }

        return $externalPages->recordUnsubscribe([
            'email'  => $email,
            'reason' => __('Hard bounce from ToSend Webhook API. Reason: ', 'fluent-crm') . $reason . __(' Recorded at: ', 'fluent-crm') . current_time('mysql'),
            'status' => 'bounced'
        ]);
    }

    private function resolvePayload($request, $default = [])
    {
        $contentPayload = Helper::parseArrayOrJson($request->getContent(), []);

        if ($contentPayload) {
            return $contentPayload;
        }

        return Helper::parseArrayOrJson($request->get(), $default);
    }
}
