<?php

namespace FluentCampaign\App\Modules\AbandonCart;

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
            'wc_recovered_statuses'          => ['processing', 'completed'],
        ];

        $settings = get_option('_fc_ab_cart_settings', []);

        if (is_array($settings) && $settings) {
            $settings = wp_parse_args($settings, $defaults);
        } else {
            $settings = $defaults;
        }

        if (defined('WC_PLUGIN_FILE')) {
            $settings['wc_recovered_statuses'] = array_values(array_unique(array_merge(wc_get_is_paid_statuses(), $settings['wc_recovered_statuses'])));
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
        if (!defined('WC_PLUGIN_FILE')) {
            return false;
        }

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

    public static function isWinOrderStatus($orderStatus)
    {
        $settings = self::getSettings();

        $result = in_array($orderStatus, $settings['wc_recovered_statuses']);

        return apply_filters('fluent_crm/ab_cart_is_win_status', $result, $orderStatus);
    }

    public static function isWooWithinCoolOffPeriod($abCartModel)
    {
        // check cool-off periods first
        $coolOffPeriodDay = AbCartHelper::getSetting('cool_off_period_days', 0);
        if (!$coolOffPeriodDay) {
            return false;
        }

        $coolOffDateTime = gmdate('Y-m-d H:i:s', time() - ($coolOffPeriodDay * DAY_IN_SECONDS));

        $orderStatuses = self::getSetting('wc_recovered_statuses', ['processing', 'completed']);

        // add wc- to the statuses
        $orderStatuses = array_map(function ($status) {
            return 'wc-' . $status;
        }, $orderStatuses);

        if (\FluentCampaign\App\Services\Integrations\WooCommerce\Helper::isWooHposEnabled()) {
            return fluentCrmDb()->table('wc_orders')
                ->where('type', 'shop_order')
                ->where('date_created_gmt', '<=', $coolOffDateTime)
                ->whereIn('status', $orderStatuses)
                ->where(function ($q) use ($abCartModel) {
                    $q->where('billing_email', $abCartModel->email);
                    if($abCartModel->user_id) {
                        $abCartModel->orWhere('customer_id', $abCartModel->user_id);
                    }
                })
                ->exists();
        }

        $check_statuses = implode( "','", array_map( 'esc_sql', $orderStatuses ) );

        global $wpdb;
        // this is for legacy orders
        $order_query = "SELECT posts.ID
            FROM $wpdb->posts AS posts
            LEFT JOIN {$wpdb->postmeta} AS meta on posts.ID = meta.post_id
            WHERE meta.meta_key = '_billing_email'
            AND   meta.meta_value = '" . $abCartModel->email . "'
            AND   posts.post_type = 'shop_order'
            AND   posts.post_status IN ( '" . $check_statuses . "' )
            AND date(posts.post_date) >='" . $coolOffDateTime . "'
            ORDER BY posts.ID DESC LIMIT 0,1";

        $last_order  = $wpdb->get_var( $order_query );

        if($last_order) {
            return true;
        }

        return false;
    }


}
