<?php

namespace FluentCampaign\App\Modules\AbandonCart;

use FluentCampaign\App\Services\Integrations\WooCommerce\WooDataHelper;
use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Request\Request;
use FluentCrm\Framework\Support\Arr;

class SettingsController extends Controller
{
    public function getSettings(Request $request)
    {
        $settings = AbCartHelper::getSettings();

        $returnData = [
            'settings' => $settings
        ];

        if (defined('WC_PLUGIN_FILE')) {

            $allStatuses = wc_get_order_statuses();
            // remove wc- from each array key and keep the value
            foreach ($allStatuses as $key => $value) {
                // remove the beginning wc- from the key using regex
                $newKey = preg_replace('/^wc-/', '', $key);
                $allStatuses[$newKey] = $value;
                unset($allStatuses[$key]);
            }

            // Unset know failed statuses
            unset($allStatuses['refunded']);
            unset($allStatuses['failed']);
            unset($allStatuses['cancelled']);

            $returnData['wooOptions'] = [
                'all_statuses'  => $allStatuses,
                'paid_statuses' => wc_get_is_paid_statuses(),
            ];
        }

        return $returnData;
    }

    public function saveSettings(Request $request)
    {
        $prevSettings = AbCartHelper::getSettings();

        $settings = $request->get('settings', []);

        $settings = Arr::only($settings, array_keys($prevSettings));

        $isEnabled = Arr::get($settings, 'enabled') === 'yes';

        if ($isEnabled) {
            AbandonCartMigrator::migrate();
        }

        /*
         * Adding this to experimental settings so we don't have to do extra query
         */
        $experiments = Helper::getExperimentalSettings();
        $experiments['abandoned_cart'] = $isEnabled ? 'yes' : 'no';
        update_option('_fluentcrm_experimental_settings', $experiments, 'yes');

        update_option('_fc_ab_cart_settings', $settings);

        return [
            'message' => __('Settings has been saved successfully', 'fluentcampaign-pro'),
            'reload'  => $prevSettings['enabled'] !== $settings['enabled']
        ];
    }

}
