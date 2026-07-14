<?php

namespace FluentCrm\App\Services\ExternalIntegrations\FluentCart;

use FluentCart\Api\ModuleSettings;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Models\Customer;
use FluentCart\App\Models\Order;
use FluentCrm\App\Models\Subscriber;

use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Benchmarks\OrderSuccessBenchmark;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\SmartCode\SmartCodeParser;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\SmartCode\SmartCodeRegister;

use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\OrderCanceledTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\OrderPaidTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\OrderDeliveredTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\OrderRefundedTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\OrderShippedTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\OrderStatusChangedTrigger;

use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\SubscriptionActivatedTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\SubscriptionCancelledTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\SubscriptionEndOfTermTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\SubscriptionExpiredTrigger;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers\SubscriptionRenewedTrigger;


class FluentCart
{
    public function init()
    {
        $this->addAutomations();
        $this->addHooks();

        SmartCodeRegister::push();

        (new RevenueTracker())->init();
    }

    public function addAutomations()
    {
        new OrderPaidTrigger();
        new OrderShippedTrigger();
        new OrderDeliveredTrigger();
        new OrderRefundedTrigger();
        new OrderCanceledTrigger();

        // new OrderStatusChangedTrigger();
        new SubscriptionExpiredTrigger();

        //subscription activated
        new SubscriptionActivatedTrigger();

        //subscription cancelled
        new SubscriptionCancelledTrigger();

        //subscription renewed
        new SubscriptionRenewedTrigger();

        //subscription end of term(completed)
        new SubscriptionEndOfTermTrigger();


        // Goals
        new OrderSuccessBenchmark();
    }

    public function addHooks()
    {

        add_filter('fluent_crm/get_import_driver_fluent_cart', [CartImporter::class, 'processUserDriver'], 10, 2);
        add_filter('fluent_crm/post_import_driver_fluent_cart', [CartImporter::class, 'importData'], 10, 3);

        add_filter('fluentcrm_ajax_options_fluent_cart_products', [$this, 'getProducts'], 10, 3);
        add_filter('fluentcrm_ajax_options_fluent_cart_product_categories', [$this, 'getProductCategories'], 10, 3);
        add_filter('fluentcrm_ajax_options_fluent_cart_subscription_products', [$this, 'getSubscriptionProducts'], 10, 3);

        add_filter('fluent_crm/funnel_icons', [$this, 'addCartIcon'], 10 , 1);
        add_filter('fluent_crm/purchase_history_fluent_cart', [$this, 'purchaseHistory'], 10, 2);

        add_filter('fluent_crm/smartcode_group_callback_cart_order', [SmartCodeParser::class, 'parseCartOrder'], 10, 4);
        add_filter('fluent_crm/smartcode_group_callback_cart_customer', [SmartCodeParser::class, 'parseCartCustomer'], 10, 4);
//        add_filter('fluent_crm/smartcode_group_callback_cart_transaction', [SmartCodeParser::class, 'parseCartTransaction'], 10, 4);
        add_filter('fluent_crm/smartcode_group_callback_cart_receipt', [SmartCodeParser::class, 'parseCartReceipt'], 10, 4);


        add_filter('fluentcrm_automation_condition_groups', array($this, 'addAutomationConditions'), 10, 2);
        add_filter('fluentcrm_automation_conditions_assess_fluent_cart', array($this, 'assessAutomationConditions'), 10, 3);
        // add_filter('fluentcrm_automation_conditions_assess_woo_order', array($this, 'assessAutomationOrderConditions'), 10, 5);
    }

    public function getProducts($items, $search, $ids)
    {
        return CartHelper::getFluentCartProducts($items, $search, $ids);
    }

    public function getProductCategories($items, $search, $ids)
    {
        return CartHelper::getFluentCartProductCategories($items, $search, $ids);
    }

    public function getSubscriptionProducts($items, $search, $ids)
    {
        return CartHelper::getFluentCartSubscriptionProducts($items, $search, $ids);
    }

    public function addCartIcon($icons)
    {
        $icons['fluentcart'] = [
            'svg' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="18" height="18" rx="1.8" fill="#00009F"/><path d="M9.19408 13.3557H3.82861L4.67063 11.4086C4.91784 10.8369 5.48112 10.4668 6.10395 10.4668H12.4955L12.0607 11.4722C11.5663 12.6155 10.4397 13.3557 9.19408 13.3557Z" fill="white"/><path d="M13.6389 9.54518H6.0918L6.52656 8.5398C7.02098 7.39646 8.14753 6.65625 9.39319 6.65625H15.9142L15.0722 8.60341C14.825 9.17507 14.2617 9.54518 13.6389 9.54518Z" fill="white"/></svg>',
        ];
        return $icons;
    }

    public function purchaseHistory($data, $subscriber)
    {
        $customer = Customer::where('email', $subscriber->email)->first();
        if (!$customer) {
            return [];
        }

        $ordersQuery = Order::with('appliedCoupons')->where('customer_id', $customer->id);
        $totalCount = $ordersQuery->count();

        if (!$totalCount) {
            return [];
        }

        // Pagination params (using super global to avoid dependency on request wrapper here)
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) { $page = 1; }
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        if ($perPage < 1) { $perPage = 10; }

        $orders = $ordersQuery
            ->orderBy('id', 'DESC')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        // Use a helper method for formatting
        $formattedOrders = $this->formatOrders($orders);

        return [
            'data' => $formattedOrders,
            'total' => $totalCount,
            'sidebar_html' => $this->getSidebarHtml($subscriber),
            'after_html' => '',
            'has_recount' => false,
            'columns_config' => [
                'order' => ['label' => __('Order', 'fluent-crm'), 'width' => '100px', 'sortable' => true, 'key' => 'id'],
                'date' => ['label' => __('Date', 'fluent-crm'), 'sortable' => true, 'key' => 'created_at'],
//                'coupon' => ['label' => __('Coupon', 'fluent-crm'), 'sortable' => true, 'key' => 'created_at'],
                'status' => ['label' => __('Status', 'fluent-crm'), 'width' => '140px', 'sortable' => false],
//                'payment' => ['label' => __('Payment', 'fluent-crm'), 'width' => '140px', 'sortable' => false, 'key' => 'payment_status'],
                'total' => ['label' => __('Total', 'fluent-crm'), 'width' => '120px', 'sortable' => true, 'key' => 'total'],
                'action' => ['label' => __('', 'fluent-crm'), 'width' => '100px', 'sortable' => false],
            ],
        ];

    }

    private function formatOrders($orders)
    {
        $formattedOrders = [];

        foreach ($orders as $order) {
            $orderActionHtml = '<a target="_blank" href="' . admin_url('admin.php?page=fluent-cart#/orders/' . $order->id . '/view') . '">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8.5 5.5V7H4.75V15.25H13V11.5H14.5V16C14.5 16.1989 14.421 16.3897 14.2803 16.5303C14.1397 16.671 13.9489 16.75 13.75 16.75H4C3.80109 16.75 3.61032 16.671 3.46967 16.5303C3.32902 16.3897 3.25 16.1989 3.25 16V6.25C3.25 6.05109 3.32902 5.86032 3.46967 5.71967C3.61032 5.57902 3.80109 5.5 4 5.5H8.5ZM16.75 3.25V9.25H15.25V5.80975L9.40525 11.6553L8.34475 10.5948L14.1888 4.75H10.75V3.25H16.75Z" fill="#525866"/>
            </svg>
            </a>';
            $coupons = implode(', ', array_column($order['appliedCoupons']->toArray(), 'code'));

            $date = '<span class="order_id">#' . $order->id .'</span><span class="order_date">'.date_i18n(get_option('date_format'), strtotime($order->created_at)).'</span>';
            $status = '<span class="fcrm_badge fcrm_badge_'.esc_attr($order->status).'">'. \FluentCrm\App\Services\Helper::getStatusText($order->status) .'</span>';

            $formattedOrders[] = [
                'date' => $date,
                'status' => $status,
//                'payment' => $order->payment_status,
//                'coupons' => $coupons,
                'total' => Helper::toDecimal($order->total_amount), // Adjust if using a different helper
                'action' => $orderActionHtml,
            ];
        }

        return $formattedOrders;
    }

    private function getSidebarHtml($subscriber = null)
    {
        // We will build a similar widget like WooCommerce's purchase sidebar
        // Show a quick customer summary + recently purchased products (flat list of items)
        $customer = null;
        if ($subscriber && !empty($subscriber->email)) {
            $customer = Customer::where('email', $subscriber->email)->first();
        }

        if (!$customer) {
            // Fallback: Just return the static block as before
            return '<div class="fluent-crm-sidebar-content">'
                . '<h3>' . __('Product History', 'fluent-crm') . '</h3>'
                . '<p>' . __('View your purchase history from FluentCart.', 'fluent-crm') . '</p>'
                . '</div>';
        }

        // Aggregate order stats
        $ordersQuery = Order::where('customer_id', $customer->id);
        $orderCount = (clone $ordersQuery)->count();

        if (!$orderCount) {
            return '<div class="fluent-crm-sidebar-content">'
                . '<h3>' . __('Product History', 'fluent-crm') . '</h3>'
                . '<p>' . __('No purchases found for this contact in FluentCart.', 'fluent-crm') . '</p>'
                . '</div>';
        }

        $totalSpent = (clone $ordersQuery)->sum('total_amount');
        $firstOrder = (clone $ordersQuery)->orderBy('id', 'ASC')->value('created_at');
        $lastOrder = (clone $ordersQuery)->orderBy('id', 'DESC')->value('created_at');

        // Fetch recent purchased items (latest 15 order items across latest orders)
        // We'll eager load order_items for performance
        $recentOrders = (clone $ordersQuery)
            ->with(['order_items' => function($q){
                $q->orderBy('id', 'DESC');
            }])
            ->orderBy('id', 'DESC')
            ->limit(200)
            ->get();

        $items = [];
        foreach ($recentOrders as $order) {
            foreach ($order->order_items as $orderItem) {

                $itemDisplayName = $orderItem->post_title;

                if($orderItem->post_title != $orderItem->title) {
                    $itemDisplayName = $orderItem->post_title . ' - ' . $orderItem->title;
                }

                $items[] = [
                    'name' => $itemDisplayName,
                    'price' => isset($orderItem->line_total) ? Helper::toDecimal($orderItem->line_total) : 0,
                    'created_at' => $order->created_at,
                    'order_id' => $order->id,
                ];
                if (count($items) >= 15) {
                    break 2; // Exit both loops once we have enough
                }
            }
        }

        $html = '<div class="fluent-crm-sidebar-content fc_payment_summary">';
        $html .= '<h3 class="history_title">' . __('Order Summary', 'fluent-crm') . '</h3>';
        $html .= '<div class="fc_history_widget"><ul class="fc_full_listed">';
        $html .= '<li><span class="fc_list_sub">' . __('Total Orders', 'fluent-crm') . '</span><span class="fc_list_value">' . intval($orderCount) . '</span></li>';
        $html .= '<li><span class="fc_list_sub">' . __('Total Spent', 'fluent-crm') . '</span><span class="fc_list_value">' . esc_html(Helper::toDecimal($totalSpent)) . '</span></li>';
        if ($firstOrder) {
            $html .= '<li><span class="fc_list_sub">' . __('First Order', 'fluent-crm') . '</span><span class="fc_list_value">' . date_i18n(get_option('date_format'), strtotime($firstOrder)) . '</span></li>';
        }
        if ($lastOrder) {
            $html .= '<li><span class="fc_list_sub">' . __('Last Order', 'fluent-crm') . '</span><span class="fc_list_value">' . date_i18n(get_option('date_format'), strtotime($lastOrder)) . '</span></li>';
        }
        $html .= '</ul></div>';

        $html .= '<h3 class="history_title">' . __('Purchased Products', 'fluent-crm') . '</h3>';
        $html .= '<div class="fc_history_widget"><ul class="fc_full_listed max_height_550">';
        foreach ($items as $item) {
            $orderUrl = admin_url('admin.php?page=fluent-cart#/orders/' . $item['order_id'] . '/view');
            $badges = '<span class="el-tag el-tag--primary">' . esc_html(Helper::toDecimal($item['price'])) . '</span>';
            $badges .= '<span class="el-tag el-tag--primary"><a target="_blank" rel="noopener" href="' . esc_url($orderUrl) . '">' . date_i18n(get_option('date_format'), strtotime($item['created_at'])) . '</a></span>';
            $html .= '<li class="fc_product_name">' . esc_html($item['name']) . ' ' . $badges . '</li>';
        }
        if (!$items) {
            $html .= '<li>' . __('No purchased products found.', 'fluent-crm') . '</li>';
        }
        $html .= '</ul></div>';

        $html .= '</div>';

        return $html;
    }


     public function addAutomationConditions($groups)
    {
        $conditionItems = [
            [
                'value'             => 'commerce_exist',
                'label'             => __('Is a customer?', 'fluent-crm'),
                'type'              => 'selections',
                'is_multiple'       => false,
                'disable_values'    => true,
                'value_description' => __('This filter will check if a contact has at least one shop order or not', 'fluent-crm'),
                'custom_operators'  => [
                    'exist'     => __('Yes', 'fluent-crm'),
                    'not_exist' => __('No', 'fluent-crm'),
                ]
            ],
            [
                'value' => 'ltv',
                'label' => __('Lifetime Value', 'fluent-crm'),
                'type'  => 'numeric'
            ],
            [
                'value' => 'aov',
                'label' => __('Average Order Value', 'fluent-crm'),
                'type'  => 'numeric',
            ],
            [
                'value' => 'first_purchase_date',
                'label' => __('First Order Date', 'fluent-crm'),
                'type'  => 'dates'
            ],
            [
                'value' => 'last_purchase_date',
                'label' => __('Last Order Date', 'fluent-crm'),
                'type'  => 'dates'
            ],
            [
                'value'            => 'purchased_items',
                'label'            => __('Products', 'fluent-crm'),
                'type'             => 'selections',
                'component'        => 'product_selector',
                'is_multiple'      => true,
                'custom_operators' => [
                    'exist'     => __('purchased', 'fluent-crm'),
                    'not_exist' => __('not purchased', 'fluent-crm'),
                ],
                'help'             => __('Will filter the contacts who have at least one order', 'fluent-crm')
            ],
            [
                'value'             => 'variation_purchased',
                'label'             => __('Product Variations', 'fluent-crm'),
                'type'              => 'cascade_selections',
                'provider'          => 'fct_variations',
                'is_multiple'       => true,
                'value_description' => __('This filter will check if a contact has purchased at least one specific product variation or not', 'fluent-crm'),
                'custom_operators'  => [
                    'exist'     => __('purchased', 'fluent-crm'),
                    'not_exist' => __('not purchased', 'fluent-crm'),
                ]
            ],
            [
                'value'            => 'purchased_categories',
                'label'            => __('Product Categories', 'fluent-crm'),
                'type'             => 'selections',
                'component'        => 'tax_selector',
                'taxonomy'         => 'product-categories',
                'is_multiple'      => true,
                'disabled'         => true,
                'help'             => __('Will filter the contacts who have at least one order', 'fluent-crm'),
                'custom_operators' => [
                    'exist'     => __('purchased', 'fluent-crm'),
                    'not_exist' => __('not purchased', 'fluent-crm'),
                ]
            ],
            [
                'value'            => 'commerce_coupons',
                'label'            => __('Used Coupons', 'fluent-crm'),
                'type'             => 'selections',
                'component'        => 'ajax_selector',
                'option_key'       => 'fct_coupons',
                'is_multiple'      => true,
                'disabled'         => true,
                'custom_operators' => [
                    'exist'     => __('in', 'fluent-crm'),
                    'not_exist' => __('not in', 'fluent-crm'),
                ],
                'help'             => __('Will filter the contacts who have at least one order', 'fluent-crm')
            ]
        ];

        if (ModuleSettings::isActive('license')) {
            $conditionItems[] = [
                'value'            => 'active_licenses',
                'label'            => __('Active Licenses', 'fluent-crm'),
                'type'             => 'selections',
                'component'        => 'product_selector',
                'is_multiple'      => true,
                'custom_operators' => [
                    'exist'     => __('have', 'fluent-crm'),
                    'not_exist' => __('do not have', 'fluent-crm'),
                ],
                'help'             => __('Will filter the contacts who have at least one active licenses or not', 'fluent-crm')
            ];
            $conditionItems[] = [
                'value'             => 'active_variation_licenses',
                'label'             => __('Active Variation Licenses', 'fluent-crm'),
                'type'              => 'cascade_selections',
                'provider'          => 'fct_variations',
                'is_multiple'       => true,
                'value_description' => __('This filter will check if a contact has at least one specific variation license or not', 'fluent-crm'),
                'custom_operators'  => [
                    'exist'     => __('have', 'fluent-crm'),
                    'not_exist' => __('do not have', 'fluent-crm'),
                ]
            ];
            $conditionItems[] = [
                'value'            => 'expired_licenses',
                'label'            => __('Expired Licenses', 'fluent-crm'),
                'type'             => 'selections',
                'component'        => 'product_selector',
                'is_multiple'      => true,
                'custom_operators' => [
                    'exist'     => __('have', 'fluent-crm'),
                    'not_exist' => __('do not have', 'fluent-crm'),
                ],
                'help'             => __('Will filter the contacts who have at least one expired licenses or not', 'fluent-crm')
            ];
            $conditionItems[] = [
                'value'             => 'expired_variation_licenses',
                'label'             => __('Expired Variation Licenses', 'fluent-crm'),
                'type'              => 'cascade_selections',
                'provider'          => 'fct_variations',
                'is_multiple'       => true,
                'value_description' => __('This filter will check if a contact has at least one specific variation expired license or not', 'fluent-crm'),
                'custom_operators'  => [
                    'exist'     => __('have', 'fluent-crm'),
                    'not_exist' => __('do not have', 'fluent-crm'),
                ]
            ];
            $conditionItems[] = [
                'value'             => 'license_exist',
                'label'             => __('Has any active license?', 'fluent-crm'),
                'type'              => 'selections',
                'is_multiple'       => false,
                'disable_values'    => true,
                'value_description' => __('Check if contacts has any active license from any products', 'fluent-crm'),
                'custom_operators'  => [
                    'exist'     => __('Yes', 'fluent-crm'),
                    'not_exist' => __('No', 'fluent-crm'),
                ]
            ];
        }

        $groups['fluent_cart'] = [
            'label'    => __('FluentCart', 'fluent-crm'),
            'value'    => 'fluent_cart',
            'children' => $conditionItems
        ];

        return $groups;
    }

    public function assessAutomationConditions($result, $conditions, $subscriber)
    {
        $legacyConditions = [];
        // if (Commerce::isEnabled('woo')) {
            $formattedConditions = [];

            $commerceProps = [
                'commerce_exist',
                'ltv', // lifetime value
                'aov', // average order value
                'first_purchase_date',
                'last_purchase_date',
                'purchased_items', // products purchased
                'variation_purchased', // product variations purchased
                'purchased_categories', // product categories 
                'commerce_coupons', // used coupons
            ];

            foreach ($conditions as $condition) {
                $prop = $condition['data_key'];
                $operator = $condition['operator'];
                if (in_array($prop, $commerceProps)) {
                    $formattedConditions[] = [
                        'operator' => $operator,
                        'value'    => $condition['data_value'],
                        'property' => $prop,
                    ];
                } else {
                    $legacyConditions[] = $condition;
                }
            }

            if ($formattedConditions) {
                $hasSubscriber = Subscriber::where('id', $subscriber->id)->where(function ($q) use ($formattedConditions) {
                    do_action_ref_array('fluentcrm_contacts_filter_fluent_cart', [&$q, $formattedConditions]);
                })->first();
                if (!$hasSubscriber) {
                    return false;
                }
            }
        // } else {
        //     $legacyConditions = $conditions;
        // }

        if ($legacyConditions) {
            $cartCustomer = Customer::query()
                ->where('email', $subscriber->email)
                ->when($subscriber->user_id, function ($q) use ($subscriber) {
                    return $q->orWhere('user_id', $subscriber->user_id);
                })
                ->first();

            if (!$cartCustomer) {
                return false;
            }
        }

        return $result;
    }
}
