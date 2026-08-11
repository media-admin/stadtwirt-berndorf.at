<?php

namespace FluentCrm\App\Http\Policies;

use FluentCrm\Framework\Http\Request\Request;

class EmailPatternPolicy extends BasePolicy
{
    public function verifyRequest(Request $request)
    {
        if ($request->method() == 'GET') {
            return $this->currentUserCan('fcrm_read_emails');
        }

        return $this->currentUserCan('fcrm_manage_emails');
    }

    public function delete(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_email_delete');
    }

    public function handleBulkAction(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_email_delete');
    }

    /**
     * Deleting a pattern category permanently removes data, so it requires the delete
     * permission — consistent with delete()/handleBulkAction() — rather than falling back
     * to the manage permission via verifyRequest(). See repo Rule 6.
     *
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return Boolean
     */
    public function deleteCategory(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_email_delete');
    }
}
