<?php

namespace FluentCrm\App\Modules\AbandonCart;

use FluentCrm\App\Modules\AbandonCart\Drivers\DriverManager;
use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Support\Arr;

class AbCartHelper
{
    public static function getSettings($useCache = true)
    {
        static $settings;

        if ($useCache && $settings) {
            return $settings;
        }

        $defaults = [
            'enabled'                        => 'no',
            'enabled_providers'              => [],
            'capture_after_minutes'          => 30,
            'lost_cart_days'                 => 10,
            'cool_off_period_days'           => 10,
            'gdpr_consent'                   => 'no',
            'gdpr_consent_text'              => 'Your email and cart are saved so we can send you email reminders about this order. {{opt_out label="No Thanks"}}',
            'disabled_user_roles'            => [],
            'track_add_to_cart'              => 'no',
            'add_to_cart_exclude_user_roles' => [],
            'tags_on_cart_abandoned'         => [],
            'lists_on_cart_abandoned'        => [],
            'tags_on_cart_lost'              => [],
            'lists_on_cart_lost'             => [],
            'new_contact_status'             => 'transactional',
        ];

        // Merge provider-specific defaults from available drivers
        foreach (DriverManager::getAvailable() as $driver) {
            $providerDefaults = $driver->getProviderSettingsDefaults();
            if ($providerDefaults) {
                $defaults = array_merge($defaults, $providerDefaults);
            }
        }

        $settings = get_option('_fc_ab_cart_settings', []);

        if (is_array($settings) && $settings) {
            if ($settings['enabled'] === 'yes' && !isset($settings['enabled_providers'])) {
                // backwards compatibility: if enabled but no providers selected, enable woo only as we had that.
                if (defined('WC_PLUGIN_FILE')) {
                    $settings['enabled_providers'] = ['woo'];
                }
            }

            $settings = wp_parse_args($settings, $defaults);
        } else {
            $settings = $defaults;
        }

        // Let each driver process settings (e.g. merge WC paid statuses)
        foreach (DriverManager::getAvailable() as $driver) {
            $settings = $driver->processSettings($settings);
        }

        return $settings;
    }

    public static function getSetting($key, $default = '')
    {
        $setting = self::getSettings();
        return Arr::get($setting, $key, $default);
    }

    public static function isActive()
    {
        return Helper::isExperimentalEnabled('abandoned_cart');
    }

    public static function willCartTrack()
    {
        if (!self::isActive()) {
            return false;
        }

        $settings = self::getSettings();
        if ($settings['enabled'] !== 'yes') {
            return false;
        }

        $disableUserRoles = Arr::get($settings, 'disabled_user_roles', []);

        if (!$disableUserRoles) {
            return true;
        }

        $user = wp_get_current_user();

        if (!$user) {
            return true;
        }

        $userRoles = array_values($user->roles);

        return !array_intersect($userRoles, $disableUserRoles);
    }

    public static function getGDPRMessage()
    {
        $settings = self::getSettings();

        if (Arr::get($settings, 'gdpr_consent') !== 'yes' || empty($settings['gdpr_consent_text'])) {
            return '';
        }

        $text = wp_kses_post($settings['gdpr_consent_text']);

        // {{opt_out label="No Thanks"}}
        return preg_replace('/{{opt_out label="([^"]+)"}}/', '<a style="text-decoration:underline;cursor: pointer;" id="fc_ab_opt_out" class="fc-ab-cart-opt-out">$1</a>', $text);
    }

    public static function getCountAndSumByStatus($status, $dateRange = [], $dateColumn = 'created_at')
    {
        $query = AbandonCartModel::where('status', $status);

        if ($dateRange) {
            $query = $query->whereBetween($dateColumn, $dateRange);
        }

        $count = $query->count();
        $sum = 0;

        if ($count) {
            $sum = $query->sum('total');
        }


        return [$count, $sum];
    }

    public static function getSortedAutomations($provider = 'woo')
    {
        $triggerName = 'fc_ab_cart_simulation_' . $provider;

        $funnels = Funnel::where('trigger_name', $triggerName)
            ->where('status', 'published')
            ->orderBy('id', 'DESC')
            ->get();

        $formattedFunnels = [];

        foreach ($funnels as $funnel) {
            $priority = Arr::get($funnel->settings, 'priority', 1);
            if (isset($formattedFunnels[$priority])) {
                $priority++;
            }

            $formattedFunnels[$priority] = $funnel;
        }

        // reverse the array to get the latest funnels first
        krsort($formattedFunnels);

        return array_values($formattedFunnels);
    }

    public static function getAbCartByDataProps($props = [], $statuses = ['processing', 'draft'])
    {

        if (empty($props)) {
            return null;
        }

        if ($token = Arr::get($props, 'checkout_key')) {
            $record = AbandonCartModel::where('checkout_key', $token)
                ->when($statuses, function ($query) use ($statuses) {
                    return $query->whereIn('status', $statuses);
                })
                ->first();

            if ($record) {
                return $record;
            }
        }


        if ($billingEmail = Arr::get($props, 'email')) {
            $record = AbandonCartModel::where('email', $billingEmail)
                ->when($statuses, function ($query, $statuses) {
                    return $query->whereIn('status', $statuses);
                })
                ->orderBy('id', 'DESC')
                ->first();

            if ($record) {
                return $record;
            }
        }

        if ($userId = Arr::get($props, 'user_id')) {
            $record = AbandonCartModel::where('user_id', $userId)
                ->when($statuses, function ($query, $statuses) {
                    return $query->whereIn('status', $statuses);
                })
                ->orderBy('id', 'DESC')
                ->first();

            if ($record) {
                return $record;
            }
        }


        return null;
    }

    /**
     * Check if the given order status is considered a "win" (i.e., completed) status for the specified driver.
     * @param string $driver The driver slug (e.g., 'woo')
     * @param string $orderStatus The order status to check (e.g., 'completed')
     * @return bool True if it's a win status, false otherwise
     */
    public static function isWinOrderStatus($driver, $orderStatus)
    {
        $driver = DriverManager::getDriver($driver);
        if ($driver) {
            return $driver->isWinOrderStatus($orderStatus);
        }

        return false;
    }

    /**
     * @deprecated Use DriverManager::getDriver('woo')->isWithinCoolOffPeriod() instead
     */
    public static function isWooWithinCoolOffPeriod($abCartModel)
    {
        $driver = DriverManager::getDriver('woo');
        if ($driver) {
            return $driver->isWithinCoolOffPeriod($abCartModel);
        }

        return false;
    }


}
