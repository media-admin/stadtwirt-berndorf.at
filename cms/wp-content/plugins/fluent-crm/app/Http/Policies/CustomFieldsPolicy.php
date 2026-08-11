<?php

namespace FluentCrm\App\Http\Policies;

use FluentCrm\App\Services\PermissionManager;
use FluentCrm\Framework\Http\Request\Request;

/**
 *  CustomFieldsPolicy - REST API Permission Policy
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */

class CustomFieldsPolicy extends BasePolicy
{
    /**
     * Check user permission for any method
     * @param  \FluentCrm\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_settings');
    }

    /**
     * Authorize reading the global labels list.
     *
     * Labels are low-sensitivity taxonomy consumed by the Campaigns, Recurring Campaigns,
     * SMS, Funnels and Labels screens — each reachable by a different manager capability
     * (fcrm_read_emails / fcrm_read_funnels / fcrm_manage_settings). So this is gated on
     * "has any FluentCRM access" (the same signal the admin menu uses to render) rather
     * than a single cap, which keeps every legitimate screen working while blocking
     * unauthenticated / no-access requests. Previously this was an always-true stub.
     *
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function getLabels(Request $request)
    {
        return !empty(PermissionManager::currentUserPermissions());
    }
}
