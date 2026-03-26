<?php

namespace FluentCampaign\App\Modules\AbandonCart;

use FluentCampaign\App\Modules\AbandonCart\Woo\WooCartTrackingInit;
use FluentCrm\Framework\Support\Arr;

class AbandonCart
{
    public function register()
    {
        if (!AbCartHelper::isActive()) {
            return false;
        }

        // For WooCommerce
        (new WooCartTrackingInit())->register();

        // add a new menu link to the fluent-crm admin menu
        add_action('fluent_crm/after_core_menu_items', function ($permissions, $isAdmin) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Abandoned Carts', 'fluentcampaign-pro'),
                __('Abandoned Carts', 'fluentcampaign-pro'),
                ($isAdmin) ? 'manage_options' : 'fcrm_read_funnels',
                'fluentcrm-admin#/abandon-carts',
                '__return_null'
            );
        }, 10, 2);
        add_filter('fluent_crm/core_menu_items', function ($items, $permissions) {
            if (!in_array('fcrm_read_funnels', $permissions)) {
                return $items;
            }

            $items[] = [
                'key'       => 'abandoned_carts',
                'label'     => __('Abandoned Carts', 'fluentcampaign-pro'),
                'permalink' => fluentcrm_menu_url_base() . 'abandon-carts',
            ];

            return $items;

        }, 10, 2);

        // Run the runner
        add_action('fluentcrm_scheduled_five_minute_tasks', [$this, 'maybeRunAbRunner'], 999);

        add_action('fluentcrm_scheduled_daily_tasks', [$this, 'markOldCartsAsLost'], 10);

        add_filter('fluent_crm/sales_stats', function ($stats) {

            [$recoveredCount, $recoveredRevenue] = AbCartHelper::getCountAndSumByStatus('recovered', [], 'recovered_at');
            if (!$recoveredRevenue) {
                return $stats;
            }

            $dateRange = [
                gmdate('Y-m-01 00:00:00', current_time('timestamp')),
                gmdate('Y-m-t 23:59:59', current_time('timestamp'))
            ];

            [$thisMonth, $thisMonthRevenue] = AbCartHelper::getCountAndSumByStatus('recovered', $dateRange, 'recovered_at');

            $stats[] = [
                'title'   => __('Cart Recovered (This Month)', 'fluentcampaign-pro'),
                'content' => wc_price($thisMonthRevenue)
            ];

            $stats[] = [
                'title'   => __('Cart Recovered (All Time)', 'fluentcampaign-pro'),
                'content' => wc_price($recoveredRevenue)
            ];

            return $stats;
        });
    }

    public function maybeRunAbRunner()
    {
        static $counter = 0;

        if (!$counter) {
            if (fluentCrmIsTimeOut(30)) {
                return false;
            }
            // It's the first time. Check if there has any runner or not
            $lastRunner = fluentCrmGetOptionCache('__fc_ab_runner');
            if ($lastRunner) {
                $timeElapsed = time() - $lastRunner;
                if ($timeElapsed < 50) {
                    return false;
                }

                fluentCrmSetOptionCache('__fc_ab_runner', null, 50);
            }
        }

        fluentCrmSetOptionCache('__fc_ab_runner', time(), 50);
        $counter = $counter + 1;

        // Get Draft Carts that need to be abandoned
        $settings = AbCartHelper::getSettings();

        $cutMinutes = Arr::get($settings, 'capture_after_minutes', 5);

        $cutDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($cutMinutes * 60));

        $abCarts = AbandonCartModel::where('status', 'draft')
            ->where('updated_at', '<=', $cutDateTime)
            ->orderBy('id', 'DESC')
            ->limit(10)
            ->get();

        if ($abCarts->isEmpty()) {
            fluentCrmSetOptionCache('__fc_ab_runner', null, 50);
            return false;
        }

        foreach ($abCarts as $abCart) {
            (new AbandonCartRunner())->runAbandonCart($abCart);
        }

        fluentCrmSetOptionCache('__fc_ab_runner', null, 50);

        if (!fluentCrmIsTimeOut(40)) {
            $this->maybeRunAbRunner();
        }

        return true;
    }

    public function markOldCartsAsLost()
    {
        $settings = AbCartHelper::getSettings();
        $cutDays = Arr::get($settings, 'lost_cart_days', 15);
        $cutDateTime = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($cutDays * 86400));

        AbandonCartModel::where('status', 'processing')
            ->where('created_at', '<=', $cutDateTime)
            ->update(['status' => 'lost']);

    }
}
