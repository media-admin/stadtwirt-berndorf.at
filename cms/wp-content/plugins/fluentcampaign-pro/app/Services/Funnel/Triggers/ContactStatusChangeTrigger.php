<?php

namespace FluentCampaign\App\Services\Funnel\Triggers;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class ContactStatusChangeTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_crm/subscriber_status_changed';
        $this->actionArgNum = 3; // subscriber, oldStatus, $newStatus
        $this->priority = 20;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('CRM', 'fluentcampaign-pro'),
            'label'       => __('Contact Status Changed', 'fluentcampaign-pro'),
            'description' => __('This Funnel will be initiated when a contact status has been changed.', 'fluentcampaign-pro'),
            'icon'        => ''
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [];
    }

    public function getSettingsFields($funnel)
    {
        $statuses = fluentcrm_subscriber_statuses(true);

        return [
            'title'     => __('Contact Status Changed', 'fluentcampaign-pro'),
            'sub_title' => __('This Funnel will be initiated when a contact status has been changed.', 'fluentcampaign-pro'),
            'fields'    => [
                'from_status'      => [
                    'type'        => 'multi-select',
                    'is_multiple' => true,
                    'label'       => __('From Status', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Status', 'fluentcampaign-pro'),
                    'inline_help' => __('Select the previous status of the contact. If you leave it blank, it will trigger for any previous status', 'fluentcampaign-pro'),
                    'options'     => $statuses
                ],
                'to_status'      => [
                    'type'        => 'select',
                    'is_multiple' => false,
                    'label'       => __('To Status', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Status', 'fluentcampaign-pro'),
                    'inline_help'        => __('Select the new status of the contact. If you leave it blank, this will trigger for any new status', 'fluentcampaign-pro'),
                    'options'     => $statuses
                ]
            ]
        ];
    }

    public function prepareEditorDetails($funnel)
    {
        $funnel->settings = wp_parse_args($funnel->settings, $this->getFunnelSettingsDefaults());

        $settingsFields = $this->getSettingsFields($funnel);

        $funnel->settingsFields = $settingsFields;

        $funnel->conditions = wp_parse_args($funnel->conditions, $this->getFunnelConditionDefaults($funnel));

        $conditionFields = $this->getConditionFields($funnel);

        $funnel->conditionFields = $conditionFields;
        return $funnel;
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'run_multiple' => 'yes'
        ];
    }

    public function getConditionFields($funnel)
    {

        return [
            'run_multiple'  => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart the Automation Multiple times for a contact for this event. (Only enable if you want to restart automation for the same contact)', 'fluentcampaign-pro'),
                'inline_help' => __('If you enable, then it will restart the automation for a contact if the contact already in the automation. Otherwise, It will just skip if already exist', 'fluentcampaign-pro')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        return; // NOT implementing this trigger , reason: we need to handle statuses for further funnel processing,
        // example: if a contact is unsubscribed, we don't want to process the funnel further, so we need to deal with that
        // if new status is pending , then it automatically sends double opt-in email, so we need to handle that too
        // discarding the trigger for now
        $subscriber = $originalArgs[0];
        $oldStatus = $originalArgs[1]; // This is actually the old status string, not an object

        $willProcess = $this->isProcessable($funnel, $subscriber, $oldStatus);

        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $this->triggerName, $willProcess, $funnel, $subscriber, $originalArgs);
        if (!$willProcess) {
            return false;
        }

        (new FunnelProcessor())->startFunnelSequence($funnel, [], [
			'source_trigger_name' => $this->triggerName
		], $subscriber);

    }

    private function isProcessable($funnel, $subscriber, $oldStatus)
    {
        $settings = (array)$funnel->settings;
        $conditions = (array)$funnel->conditions;

        // if settings from status is empty, it means it will trigger for any previous status if not then check the old status
         if (!empty($settings['from_status'])) {
            $fromStatuses = Arr::get($settings, 'from_status', []);
            if (!in_array($oldStatus, $fromStatuses)) {
                return false;
            }
        }

         if (!empty($settings['to_status'])) {
             $toStatus = Arr::get($settings, 'to_status');
             if ($subscriber->status != $toStatus) {
                 return false;
             }
         }

        // check run_only_one
        if (FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id)) {
            $multipleRun = Arr::get($conditions, 'run_multiple') == 'yes';
            if ($multipleRun) {
                FunnelHelper::removeSubscribersFromFunnel($funnel->id, [$subscriber->id]);
            } else {
                return false;
            }
        }

        return true;
    }
}
