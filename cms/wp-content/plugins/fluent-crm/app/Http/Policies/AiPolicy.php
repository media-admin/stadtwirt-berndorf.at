<?php

namespace FluentCrm\App\Http\Policies;

use FluentCrm\Framework\Http\Request\Request;

class AiPolicy extends BasePolicy
{
    public function getSettings(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_settings');
    }

    public function saveSettings(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_settings');
    }

    public function testConnection(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_settings');
    }

    public function generate(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_emails');
    }

    public function generateEmailBody(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_emails');
    }

    public function contactSummary(Request $request)
    {
        return $this->currentUserCan('fcrm_read_contacts');
    }
}
