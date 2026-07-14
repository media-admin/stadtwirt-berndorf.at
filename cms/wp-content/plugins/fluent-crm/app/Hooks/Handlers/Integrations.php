<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Services\ExternalIntegrations\BricksBuilderIntegration;

/**
 *  Integrations Class
 *
 * @package FluentCrm\App\Hooks
 *
 * @version 1.0.0
 */
class Integrations
{
    public function register()
    {
        // Full-featured integrations with functionality
        if (defined('FLUENTFORM')) {
            (new \FluentCrm\App\Services\ExternalIntegrations\FluentForm\FluentFormInit())->init();
        }

        if(defined('FLUENTCART_VERSION')) {
            (new \FluentCrm\App\Services\ExternalIntegrations\FluentCart\FluentCart())->init();
        }

        /*
         * Oxygen Editor Integration
         */
        if (defined('CT_VERSION')) {
            require_once FLUENTCRM_PLUGIN_PATH . 'app/Services/ExternalIntegrations/Oxygen/oxy_init.php';
        }

        (new EventTrackingHandler())->register();

        if(defined('BRICKS_VERSION')) {
            (new BricksBuilderIntegration())->register();
        }
    }
}
