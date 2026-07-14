<?php

namespace FluentCrm\App\Http\Policies;

use FluentCrm\Framework\Http\Request\Request;
class SettingsPolicy extends BasePolicy
{
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_settings');
    }
}
