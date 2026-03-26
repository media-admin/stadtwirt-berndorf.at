<?php

namespace FluentCampaign\App\Services\Funnel\Actions;

use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Framework\Support\Arr;

class ChangeContactStatusAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'change_contact_status';
        $this->priority = 20;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('CRM', 'fluentcampaign-pro'),
            'title'       => __('Change Contact Status', 'fluentcampaign-pro'),
            'description' => __('Change status of a contact to subscribed, pending or unsubscribed.', 'fluentcampaign-pro'),
            'icon'        => '',
            'settings'    => [
                'status' => '',
                'select_type' => 'any', // any | specific
                'conditional_status' => 'unsubscribed',
                'unsub_reason' => __('Unsubscribed by Automation Action', 'fluentcampaign-pro')
            ]
        ];
    }

    public function getBlockFields()
    {
        $statuses = fluentcrm_subscriber_statuses(true);

        return [
            'title'     => __('Change Contact Status', 'fluentcampaign-pro'),
            'sub_title' => __('Change status of a contact to subscribed, pending or unsubscribed etc.', 'fluentcampaign-pro'),
            'fields'    => [
                'status'           => [
                    'type'        => 'select',
                    'label'       => __('New Status', 'fluentcampaign-pro'),
                    'options'     => $statuses,
                    'placeholder' => __('Select New Status', 'fluentcampaign-pro')
                ],
                'info'     => [
                    'type'        => 'html',
                    'info'       => '<b>Selecting status other than "Subscribed" will not send any email to the contact. This funnel will end here.</b>',
                    'dependency'  => [
                        'depends_on'    => 'status',
                        'operator' => '!=',
                        'value'    => 'subscribed'
                    ]
                ]
            ]
        ];
    }

    /**
     * Handle the action
     *
     * @param \FluentCrm\App\Models\Subscriber $subscriber
     * @param array $sequence
     * @param int $funnelSubscriberId
     * @param array $funnelMetric
     *
     * @return bool
     */
    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        if (!$subscriber) {
            return false;
        }

        $settings = $sequence->settings;
        $newStatus = Arr::get($settings, 'status');

        // If the new status is empty or same as the current status then we don't need to do anything.
        if (empty($newStatus) || $newStatus == $subscriber->status) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $oldStatus = $subscriber->status;

        $subscriber = Subscriber::find($subscriber->id);

        $subscriber->status = $newStatus;
        $subscriber->save();

        // if unsub then call handleUnsubscribe in cleanup
        if ($newStatus != 'subscribed') {
            SubscriberNote::create([
                'subscriber_id' => $subscriber->id,
                'type'          => 'system_log',
                'title'         => __('Contact Status Changed', 'fluent-crm'),
                'description'   => sprintf(__('Contact status change to %s from %s by Funnel Action', 'fluentcampaign-pro'), $newStatus, $oldStatus),
            ]);
            if ($newStatus == 'unsubscribed') {
                do_action('fluentcrm_subscriber_status_to_unsubscribed', $subscriber, $oldStatus);
            } elseif ($newStatus == 'pending') {
                do_action('fluentcrm_subscriber_status_to_pending', $subscriber, $oldStatus);
            } else {
                do_action('fluentcrm_subscriber_status_to_subscribed', $subscriber, $oldStatus);
            }

            // If the status is other than subscribed, we will end the funnel here, no further actions will run for this contact.
            FunnelSubscriber::where('id', $funnelSubscriberId)
                            ->update([
                                'status' => 'completed'
                            ]);

        }

        do_action('fluent_crm/subscriber_status_changed', $subscriber, $oldStatus);

    }
}