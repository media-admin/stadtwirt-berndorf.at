<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

use FluentCampaign\App\Services\Commerce\Commerce;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Libs\ConditionAssessor;

class AutomationConditions
{
    public function init()
    {
        add_filter('fluentcrm_automation_condition_groups', array($this, 'addAutomationConditions'), 10, 2);
        add_filter('fluentcrm_automation_conditions_assess_woo', array($this, 'assessAutomationConditions'), 10, 3);
        add_filter('fluentcrm_automation_conditions_assess_woo_order', array($this, 'assessAutomationOrderConditions'), 10, 5);
    }

    public function addAutomationConditions($groups, $funnel)
    {
        $disabled = !Commerce::isEnabled('woo');

        $customerItems = [
            [
                'value'             => 'purchased_items', // customer_purchased_products
                'label'             => __('Purchased Products', 'fluentcampaign-pro'),
                'type'              => 'selections',
                'component'         => 'product_selector',
                'is_singular_value' => true,
                'is_multiple'       => true,
                'disabled'          => false
            ],
            [
                'value'             => 'variation_purchased',
                'label'             => __('Purchased Product Variations', 'fluentcampaign-pro'),
                'type'              => 'cascade_selections',
                'provider'          => 'woo_variations',
                'is_multiple'       => true,
                'disabled'          => $disabled,
                'value_description' => __('This filter will check if a contact has purchased at least one specific product variation or not', 'fluentcampaign-pro'),
                'custom_operators'  => [
                    'exist'     => __('Purchased', 'fluentcampaign-pro'),
                    'not_exist' => __('Not Purchased', 'fluentcampaign-pro'),
                ]
            ],
            [
                'value'    => 'total_order_count', // customer_order_count
                'label'    => __('Total Order Count', 'fluentcampaign-pro'),
                'type'     => 'numeric',
                'disabled' => false,
                'min'      => 1,
                'help'     => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'    => 'total_order_value', // customer_total_spend
                'label'    => __('Total Order Value', 'fluentcampaign-pro'),
                'type'     => 'numeric',
                'disabled' => false,
                'min'      => 1,
                'help'     => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'             => 'billing_country', // customer_billing_country
                'label'             => __('Country', 'fluentcampaign-pro'),
                'type'              => 'selections',
                'component'         => 'options_selector',
                'option_key'        => 'countries',
                'is_multiple'       => true,
                'is_singular_value' => true
            ],
            [
                'value'       => 'guest_user', // customer_guest_user
                'label'       => __('Is guest?', 'fluentcampaign-pro'),
                'type'        => 'single_assert_option',
                'options'     => [
                    'yes' => __('Yes', 'fluentcampaign-pro'),
                    'no'  => __('No', 'fluentcampaign-pro')
                ],
                'is_multiple' => false,
            ],
            [
                'value'    => 'first_order_date',
                'label'    => __('First Order Date', 'fluentcampaign-pro'),
                'type'     => 'dates',
                'disabled' => $disabled,
                'help'     => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'    => 'last_order_date',
                'label'    => __('Last Order Date', 'fluentcampaign-pro'),
                'type'     => 'dates',
                'disabled' => $disabled,
                'help'     => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'       => 'purchased_categories', // customer_cat_purchased
                'label'       => __('Purchased Categories', 'fluentcampaign-pro'),
                'type'        => 'selections',
                'component'   => 'tax_selector',
                'taxonomy'    => 'product_cat',
                'is_multiple' => true,
                'disabled'    => $disabled,
                'help'        => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'       => 'purchased_tags',
                'label'       => __('Purchased Tags', 'fluentcampaign-pro'),
                'type'        => 'selections',
                'component'   => 'tax_selector',
                'taxonomy'    => 'product_tag',
                'is_multiple' => true,
                'disabled'    => $disabled,
                'help'        => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'       => 'commerce_coupons',
                'label'       => __('Used Coupons', 'fluentcampaign-pro'),
                'type'        => 'selections',
                'component'   => 'ajax_selector',
                'option_key'  => 'woo_coupons',
                'is_multiple' => true,
                'disabled'    => $disabled,
                'help'        => __('Will filter the contacts who have at least one order', 'fluentcampaign-pro')
            ],
            [
                'value'               => 'purchase_times_count',
                'label'               => __('Specific Product Purchase Times', 'fluentcampaign-pro'),
                'help'                => __('Count how many times a specific product was purchased by this contact', 'fluentcampaign-pro'),
                'type'                => 'times_numeric',
                'component'           => 'item_times_selection',
                'is_multiple'         => false,
                'disabled'            => $disabled,
                'primary_selector'    => 'product_selector_woo',
                'primary_placeholder' => __('Select Product', 'fluentcampaign-pro'),
                'numeric_placeholder' => __('Purchase Times', 'fluentcampaign-pro'),
                'input_help'          => __('Select which product you want to match first then how many times it was purchased separately', 'fluentcampaign-pro'),
            ],
            [
                'value'             => 'commerce_exist',
                'label'             => __('Is a customer?', 'fluentcampaign-pro'),
                'type'              => 'selections',
                'is_multiple'       => false,
                'disable_values'    => true,
                'value_description' => __('This filter will check if a contact has at least one shop order or not', 'fluentcampaign-pro'),
                'custom_operators'  => [
                    'exist'     => __('Yes', 'fluentcampaign-pro'),
                    'not_exist' => __('No', 'fluentcampaign-pro'),
                ],
                'disabled'          => $disabled
            ]
        ];

        $groups['woo'] = [
            'label'    => __('WooCommerce', 'fluentcampaign-pro'),
            'value'    => 'woo',
            'children' => $customerItems,
        ];

        if (Helper::isWooTrigger($funnel->trigger_name)) {
            $orderProps = apply_filters('fluent_crm/woo_order_conditions', [
                [
                    'value'    => 'total_value',
                    'label'    => __('Total Order Value', 'fluentcampaign-pro'),
                    'type'     => 'numeric',
                    'disabled' => false
                ],
                [
                    'value'       => 'product_ids',
                    'label'       => __('Products in Order', 'fluentcampaign-pro'),
                    'type'        => 'selections',
                    'component'   => 'product_selector',
                    'is_multiple' => true,
                    'disabled'    => false
                ],
                [
                    'value'       => 'cat_purchased',
                    'label'       => __('Purchased From Categories', 'fluentcampaign-pro'),
                    'type'        => 'selections',
                    'component'   => 'tax_selector',
                    'taxonomy'    => 'product_cat',
                    'is_multiple' => true,
                    'disabled'    => false
                ],
                [
                    'value'             => 'billing_country',
                    'label'             => __('Country', 'fluentcampaign-pro'),
                    'type'              => 'selections',
                    'component'         => 'options_selector',
                    'option_key'        => 'countries',
                    'is_multiple'       => true,
                    'is_singular_value' => true
                ],
                [
                    'value'   => 'shipping_method',
                    'label'   => __('Shipping Method', 'fluentcampaign-pro'),
                    'type'    => 'single_assert_option',
                    'options' => Helper::getShippingMethods(false),
                ],
                [
                    'value'   => 'payment_gateway',
                    'label'   => __('Payment Gateway', 'fluentcampaign-pro'),
                    'type'    => 'single_assert_option',
                    'options' => Helper::getPaymentGateways(false),
                ],
                [
                    'value'   => 'order_status',
                    'label'   => __('Order Status', 'fluentcampaign-pro'),
                    'type'    => 'straight_assert_option',
                    'options' => Helper::getOrderStatuses(false),
                ]
            ], $funnel);
            $groups['woo_order'] = [
                'label'    => __('Woo Current Order', 'fluentcampaign-pro'),
                'value'    => 'woo_order',
                'children' => $orderProps
            ];
        }

        return $groups;
    }

    public function assessAutomationConditions($result, $conditions, $subscriber)
    {
        $legacyConditions = [];
        if (Commerce::isEnabled('woo')) {
            $formattedConditions = [];

            $commerceProps = ['purchased_items', 'variation_purchased', 'total_order_count', 'total_order_value', 'last_order_date', 'first_order_date', 'purchased_categories', 'purchased_tags', 'commerce_coupons', 'commerce_exist'];

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
                    do_action_ref_array('fluentcrm_contacts_filter_woo', [&$q, $formattedConditions]);
                })->first();
                if (!$hasSubscriber) {
                    return false;
                }
            }
        } else {
            $legacyConditions = $conditions;
        }

        if ($legacyConditions) {
            $wooCustomer = fluentCrmDb()->table('wc_customer_lookup')
                ->where('email', $subscriber->email)
                ->when($subscriber->user_id, function ($q) use ($subscriber) {
                    return $q->orWhere('user_id', $subscriber->user_id);
                })
                ->first();

            if (!$wooCustomer) {
                return false;
            }

            $dataValues = [];
            foreach ($conditions as $conditionIndex => $condition) {
                $dataKey = $condition['data_key'];
                if ($dataKey == 'purchase_times_count') {
                    $values = $condition['data_value'];
                    if (!$values || count($values) != 2) {
                        unset($conditions[$conditionIndex]);
                        continue;
                    }
                    $productId = (int)$values[0];
                    $conditions[$conditionIndex]['data_value'] = (int)$values[1];
                    $dataValues[$dataKey] = WooDataHelper::getProductPurchaseCount($productId, $subscriber->id);
                } else {
                    $dataValues[$dataKey] = WooDataHelper::getCustomerItem($dataKey, $wooCustomer);
                }
            }

            if (!ConditionAssessor::matchAllConditions($conditions, $dataValues)) {
                return false;
            }
        }

        return $result;
    }

    public function assessAutomationOrderConditions($result, $conditions, $subscriber, $sequence, $funnelSubscriberId)
    {
        if (!$sequence || !$funnelSubscriberId) {
            return $result;
        }

        $funnelSub = FunnelSubscriber::find($funnelSubscriberId);
        $order = false;
        if ($funnelSub && Helper::isWooTrigger($funnelSub->source_trigger_name)) {
            $order = wc_get_order($funnelSub->source_ref_id);
        }

        if (!$order) {
            return $result;
        }

        $dataValues = [];

        foreach ($conditions as $item) {
            $key = $item['data_key'];
            $dataValues[$key] = WooDataHelper::getOrderItem($key, $order);
        }

        if (!ConditionAssessor::matchAllConditions($conditions, $dataValues)) {
            return false;
        }

        return $result;
    }
}
