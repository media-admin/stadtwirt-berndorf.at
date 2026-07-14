<?php

namespace FluentCrm\App\Modules\AbandonCart;

use FluentCrm\App\Modules\AbandonCart\Drivers\DriverManager;
use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\Framework\Support\Arr;

class SettingsController extends Controller
{
    public function getSettings(Request $request)
    {
        $settings = AbCartHelper::getSettings();

        $returnData = [
            'settings' => $settings
        ];

        $availables = DriverManager::getAvailable();

        // Collect provider-specific options from each available driver
        foreach ($availables as $driver) {
            $providerOptions = $driver->getProviderSettingsResponse();
            if ($providerOptions) {
                $returnData[$driver->getProviderSlug() . 'Options'] = $providerOptions;
            }
        }

        $returnData['available_providers'] = array_map(function ($driver) {
            return [
                'slug'            => $driver->getProviderSlug(),
                'label'           => $driver->getProviderLabel(),
                'settings_fields' => $driver->getSettingsFields()
            ];
        }, array_values($availables));

        return $returnData;
    }

    public function saveSettings(Request $request)
    {
        $prevSettings = AbCartHelper::getSettings();

        $settings = (array) $request->get('settings', []);

        $settings = Arr::only($settings, array_keys($prevSettings));

        do_action_ref_array('fluent_crm/abandon_cart_before_settings_save', [&$settings, $prevSettings]);

        if (is_wp_error($settings)) {
            return $this->sendError([
                'message' => $settings->get_error_message()
            ], 422);
        }

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
            'message'  => __('Settings has been saved successfully', 'fluent-crm'),
            'reload'   => $prevSettings['enabled'] !== $settings['enabled'],
            'settings' => $settings
        ];
    }

}
