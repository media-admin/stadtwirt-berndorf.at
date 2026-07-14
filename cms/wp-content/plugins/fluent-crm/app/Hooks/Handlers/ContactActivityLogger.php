<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Models\Meta;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

/**
 *  ContactActivityLogger Class
 *
 * Logs Contact's activity based on different WordPress Events.
 *
 * @package FluentCrm\App\Hooks
 *
 * @version 1.0.0
 */
class ContactActivityLogger
{
    public function register()
    {
        // Login Tracker
        add_action('wp_login', array($this, 'trackLogin'), 10, 2);

        // Global Tracker
        add_action('fluent_crm/track_activity_by_subscriber', array($this, 'trackActivityBySubscriber'));


        add_action('fluent_crm/email_opened_anonymously', [$this, 'trackEmailOpenAnonymously'], 10, 1);

        add_action('fluent_crm/anonymous_email_url_clicked', [$this, 'trackEmailClickAnonymously'], 10, 2);
    }

    public function trackLogin($username, $user)
    {
        update_user_meta($user->ID, '_last_login', current_time('mysql'));
        $this->trackActivityByUser($user, 'login');
    }

    public function trackActivityByUser($user, $type = '')
    {
        if (is_numeric($user)) {
            $user = get_user_by('ID', $user);
        }
        if (!$user || empty($user->user_email)) {
            return;
        }

        $subscriber = Subscriber::where('email', $user->user_email)->first();

        if (!$subscriber) {
            return;
        }

        $this->trackActivityBySubscriber($subscriber);

        if ($type == 'login') {
            fluentcrm_update_subscriber_meta($subscriber->id, '_last_login', current_time('mysql'));
        }

        return true;
    }

    public function trackActivityBySubscriber($subscriber)
    {
        if (!$subscriber) {
            return;
        }

        if (is_numeric($subscriber)) {
            $subscriber = Subscriber::where('id', $subscriber)->first();
        }

        if (!$subscriber) {
            return;
        }

        if ($subscriber->last_activity && strtotime($subscriber->last_activity) > (current_time('timestamp') - 3600)) {
            return;
        }

        $data = [
            'last_activity' => current_time('mysql')
        ];

        if (!$subscriber->ip && fluentCrmWillTrackIp()) {
            $ip = FluentCrm('request')->getIp(fluentCrmWillAnonymizeIp());
            if ($ip != '127.0.0.1') {
                $data['ip'] = $ip;
            }
        }

        return fluentCrmDb()->table('fc_subscribers')
            ->where('id', $subscriber->id)
            ->update($data);

    }

    public function trackEmailOpenAnonymously($campaignEmaillModel)
    {
        if (!$campaignEmaillModel->campaign_id) {
            return;
        }

        // check if the campaign exist
        global $wpdb;
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM {$wpdb->prefix}fc_campaigns WHERE id = %d LIMIT 1",
                $campaignEmaillModel->campaign_id
            )
        );

        if (!$exists) {
            return;
        }


        $existingMetaModel = fluentcrm_get_campaign_meta($campaignEmaillModel->campaign_id, '_ano_open_count', false);
        if ($existingMetaModel) {
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}fc_meta SET value = value + 1 WHERE id = %d",
                $existingMetaModel->id
            ));
        } else {
            // we creating new one
            Meta::create([
                'key'         => '_ano_open_count',
                'value'       => 1,
                'object_id'   => $campaignEmaillModel->campaign_id,
                'object_type' => 'FluentCrm\App\Models\Campaign'
            ]);
        }

        return true;
    }

    public function trackEmailClickAnonymously($url, $campaign)
    {
        $existingMetaModel = fluentcrm_get_campaign_meta($campaign->id, '_ano_url_clicks', false);

        $url = (string)$url;

        if ($existingMetaModel) {
            $urls = is_array($existingMetaModel->value) ? $existingMetaModel->value : [];
            if (isset($urls[$url])) {
                $urls[$url] = (int)$urls[$url] + 1;
            } else {
                $urls[$url] = 1;
            }

            $existingMetaModel->value = $urls;
            $existingMetaModel->save();
        } else {
            Meta::create([
                'key'         => '_ano_url_clicks',
                'value'       => [
                    $url => 1
                ],
                'object_id'   => $campaign->id,
                'object_type' => 'FluentCrm\App\Models\Campaign'
            ]);
        }

        return true;
    }
}
