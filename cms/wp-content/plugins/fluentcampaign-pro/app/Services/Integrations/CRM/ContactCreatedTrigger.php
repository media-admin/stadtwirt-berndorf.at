<?php

namespace FluentCampaign\App\Services\Integrations\CRM;

use FluentCampaign\App\Services\FunnelMultiConditionTrait;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class ContactCreatedTrigger extends BaseTrigger
{
    use FunnelMultiConditionTrait;

    public function __construct()
    {
        $this->triggerName = 'fluent_crm/contact_created';
        $this->actionArgNum = 2;
        $this->priority = 25;

        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('CRM', 'fluentcampaign-pro'),
            'label'       => __('Contact Created', 'fluentcampaign-pro'),
            'description' => __('This will run when a new contact will be added', 'fluentcampaign-pro'),
            'icon'        => ''//'fc-icon-tag_applied'
        ];
    }

    public function getFunnelSettingsDefaults()
    {

    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Contact Created', 'fluentcampaign-pro'),
            'sub_title' => __('This will run when a new contact will be added', 'fluentcampaign-pro')
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $subscriber = $originalArgs[0];

        if ($subscriber->status != 'subscribed' && $subscriber->status != 'pending') {
            return;
        }

        $willProcess = $this->isProcessable($funnel,  $subscriber);
        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $this->triggerName, $willProcess, $funnel, $subscriber, $originalArgs);

        if (!$willProcess) {
            return;
        }

        (new FunnelProcessor())->startFunnelSequence($funnel, [], [
            'source_trigger_name' => $this->triggerName
        ], $subscriber);
    }

    private function isProcessable($funnel, $subscriber)
    {

        // check run_only_one
        if ($subscriber && FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id)) {
            $multipleRun = Arr::get($funnel->conditions, 'run_multiple') == 'yes';
            if ($multipleRun) {
                FunnelHelper::removeSubscribersFromFunnel($funnel->id, [$subscriber->id]);
            } else {
                return false;
            }
        }

        return true;
    }
}
