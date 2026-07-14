<?php

namespace FluentCrm\App\Modules\AbandonCart;

use FluentCrm\App\Modules\AbandonCart\Drivers\DriverManager;
use FluentCrm\App\Modules\AbandonCart\Drivers\FluentCart\FluentCartDriver;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\Framework\Support\Arr;

class AbandonCart
{
    public function register()
    {
        add_action('init', function () {
            // Register built-in FluentCart driver
            DriverManager::register(new FluentCartDriver());

            // Allow pro addon and third parties to register drivers
            do_action('fluent_crm/abandon_cart_register_drivers');

            if (!AbCartHelper::isActive()) {
                return false;
            }

            $this->init();
        }, 90);
    }

    protected function init()
    {
        $drivers = DriverManager::getEnabled();

        if (!$drivers) {
            return false;
        }

        // Boot only enabled drivers (available + toggled on in settings)
        foreach ($drivers as $driver) {
            $driver->register();
            $driver->registerAutomationTrigger();
        }

        // Expose abandon cart availability to the frontend via fcAdmin vars
        add_filter('fluent_crm/admin_vars', function ($vars) {
            $vars['has_abandon_carts'] = true;
            $vars['can_read_abandon_carts'] = PermissionManager::currentUserCan('fcrm_read_funnels');
            return $vars;
        });

        // Add Abandoned Carts as a sub-item under the Reports dropdown
        add_filter('fluent_crm/menu_items', function ($items) {
            if (!PermissionManager::currentUserCan('fcrm_read_funnels')) {
                return $items;
            }

            $urlBase = fluentcrm_menu_url_base();
            $hasReportsMenu = false;
            foreach ($items as &$item) {
                if (!empty($item['key']) && $item['key'] === 'reports' && isset($item['sub_items'])) {
                    $item['sub_items'][] = [
                        'key'       => 'reports_abandoned_carts',
                        'label'     => __('Abandoned Carts', 'fluent-crm'),
                        'permalink' => $urlBase . 'reports?tab=abandoned_carts',
                        'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="fcrm_menu_icon"><path d="M5.5 15.75C5.76522 15.75 6.0195 15.8554 6.20703 16.043C6.39457 16.2305 6.5 16.4848 6.5 16.75C6.5 17.0152 6.39457 17.2695 6.20703 17.457C6.0195 17.6446 5.76522 17.75 5.5 17.75C5.23478 17.75 4.9805 17.6446 4.79297 17.457C4.60543 17.2695 4.5 17.0152 4.5 16.75C4.5 16.4848 4.60543 16.2305 4.79297 16.043C4.9805 15.8554 5.23478 15.75 5.5 15.75ZM14.5 15.75C14.7652 15.75 15.0195 15.8554 15.207 16.043C15.3946 16.2305 15.5 16.4848 15.5 16.75C15.5 17.0152 15.3946 17.2695 15.207 17.457C15.0195 17.6446 14.7652 17.75 14.5 17.75C14.2348 17.75 13.9805 17.6446 13.793 17.457C13.6054 17.2695 13.5 17.0152 13.5 16.75C13.5 16.4848 13.6054 16.2305 13.793 16.043C13.9805 15.8554 14.2348 15.75 14.5 15.75ZM4.75 3C4.8163 3 4.87987 3.02636 4.92676 3.07324C4.97364 3.12013 5 3.1837 5 3.25V12.75H15.2188L16.9688 5.75H7.5V5.25H17.29C17.328 5.25001 17.3653 5.25878 17.3994 5.27539C17.4336 5.29206 17.4639 5.31672 17.4873 5.34668C17.5105 5.37653 17.5263 5.41126 17.5342 5.44824C17.542 5.48538 17.5414 5.52373 17.5322 5.56055L15.6572 13.0605C15.6437 13.1146 15.6123 13.163 15.5684 13.1973C15.5245 13.2314 15.4706 13.25 15.415 13.25H4.75C4.68369 13.25 4.62012 13.2236 4.57324 13.1768C4.52636 13.1299 4.5 13.0663 4.5 13V3.5H3V3H4.75Z" stroke="var(--fc-secondary-text)"></path></svg>',
                    ];
                    $hasReportsMenu = true;
                    break;
                }
            }
            unset($item);

            if (!$hasReportsMenu) {
                $items[] = [
                    'key'          => 'reports',
                    'label'        => __('Reports', 'fluent-crm'),
                    'permalink'    => $urlBase . 'reports?tab=abandoned_carts',
                    'layout_class' => 'fc_1_col_menu',
                    'sub_items'    => [
                        [
                            'key'       => 'reports_abandoned_carts',
                            'label'     => __('Abandoned Carts', 'fluent-crm'),
                            'permalink' => $urlBase . 'reports?tab=abandoned_carts',
                            'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" class="fcrm_menu_icon"><path d="M5.5 15.75C5.76522 15.75 6.0195 15.8554 6.20703 16.043C6.39457 16.2305 6.5 16.4848 6.5 16.75C6.5 17.0152 6.39457 17.2695 6.20703 17.457C6.0195 17.6446 5.76522 17.75 5.5 17.75C5.23478 17.75 4.9805 17.6446 4.79297 17.457C4.60543 17.2695 4.5 17.0152 4.5 16.75C4.5 16.4848 4.60543 16.2305 4.79297 16.043C4.9805 15.8554 5.23478 15.75 5.5 15.75ZM14.5 15.75C14.7652 15.75 15.0195 15.8554 15.207 16.043C15.3946 16.2305 15.5 16.4848 15.5 16.75C15.5 17.0152 15.3946 17.2695 15.207 17.457C15.0195 17.6446 14.7652 17.75 14.5 17.75C14.2348 17.75 13.9805 17.6446 13.793 17.457C13.6054 17.2695 13.5 17.0152 13.5 16.75C13.5 16.4848 13.6054 16.2305 13.793 16.043C13.9805 15.8554 14.2348 15.75 14.5 15.75ZM4.75 3C4.8163 3 4.87987 3.02636 4.92676 3.07324C4.97364 3.12013 5 3.1837 5 3.25V12.75H15.2188L16.9688 5.75H7.5V5.25H17.29C17.328 5.25001 17.3653 5.25878 17.3994 5.27539C17.4336 5.29206 17.4639 5.31672 17.4873 5.34668C17.5105 5.37653 17.5263 5.41126 17.5342 5.44824C17.542 5.48538 17.5414 5.52373 17.5322 5.56055L15.6572 13.0605C15.6437 13.1146 15.6123 13.163 15.5684 13.1973C15.5245 13.2314 15.4706 13.25 15.415 13.25H4.75C4.68369 13.25 4.62012 13.2236 4.57324 13.1768C4.52636 13.1299 4.5 13.0663 4.5 13V3.5H3V3H4.75Z" stroke="var(--fc-secondary-text)"></path></svg>',
                        ]
                    ],
                ];
            }
            return $items;
        });

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
                'title'   => __('Cart Recovered (This Month)', 'fluent-crm'),
                'content' => DriverManager::formatPrice($thisMonthRevenue)
            ];

            $stats[] = [
                'title'   => __('Cart Recovered (All Time)', 'fluent-crm'),
                'content' => DriverManager::formatPrice($recoveredRevenue)
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

        $enabledSlugs = DriverManager::getEnabledSlugs();

        if (!$enabledSlugs) {
            fluentCrmSetOptionCache('__fc_ab_runner', null, 50);
            return false;
        }

        $abCarts = AbandonCartModel::where('status', 'draft')
            ->whereIn('provider', $enabledSlugs)
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
