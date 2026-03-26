<?php

namespace FluentCampaign\App\Services\Integrations\Voxel;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;

class VoxelOrderPlacedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'voxel/app-events/products/orders/customer:order_placed';
        $this->priority = 20;
        $this->actionArgNum = 1;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => __('Voxel', 'fluentcampaign-pro'),
            'label'       => __('Order Placed', 'fluentcampaign-pro'),
            'icon'        => 'el-icon-shopping-cart-1',
            'description' => __('This funnel will start when an order is placed by customer', 'fluentcampaign-pro')
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'subscription_status' => 'subscribed'
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Voxel Order Placed', 'fluentcampaign-pro'),
            'sub_title' => __('This Funnel will start once new order is placed by Customer', 'fluentcampaign-pro'),
            'fields'    => [
                'subscription_status'      => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'editable_statuses',
                    'is_multiple' => false,
                    'label'       => __('Subscription Status', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Status', 'fluentcampaign-pro')
                ],
                'subscription_status_info' => [
                    'type'       => 'html',
                    'info'       => '<b>' . __('An Automated double-optin email will be sent for new subscribers', 'fluentcampaign-pro') . '</b>',
                    'dependency' => [
                        'depends_on' => 'subscription_status',
                        'operator'   => '=',
                        'value'      => 'pending'
                    ]
                ]
            ]
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
//            'product_ids'        => [],
            'product_types' => [],
//            'purchase_type'      => 'all',
            'run_multiple'       => 'no'
        ];
    }

    public function getConditionFields($funnel)
    {
        return [
//            'product_ids'        => [
//                'type'        => 'rest_selector',
//                'option_key'  => 'voxel_products',
//                'is_multiple' => true,
//                'label'       => __('Target Products', 'fluentcampaign-pro'),
//                'help'        => __('Select for which products this automation will run', 'fluentcampaign-pro'),
//                'inline_help' => __('Keep it blank to run to any product purchase', 'fluentcampaign-pro')
//            ],
            'product_types' => [
                'type'        => 'rest_selector',
                'option_key'  => 'voxel_product_types',
                'is_multiple' => true,
                'label'       => __('Target Product Types', 'fluentcampaign-pro'),
                'help'        => __('Select for which product type the automation will run', 'fluentcampaign-pro'),
                'inline_help' => __('Keep it blank to run to any category products', 'fluentcampaign-pro')
            ],
//            'purchase_type'      => [
//                'type'        => 'radio',
//                'label'       => __('Purchase Type', 'fluentcampaign-pro'),
//                'help'        => __('Select the purchase type', 'fluentcampaign-pro'),
//                'options'     => Helper::purchaseTypeOptions(),
//                'inline_help' => __('For what type of purchase you want to run this funnel', 'fluentcampaign-pro')
//            ],
            'run_multiple'       => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart the Automation Multiple times for a contact for this event. (Only enable if you want to restart automation for the same contact)', 'fluentcampaign-pro'),
                'inline_help' => __('If you enable, then it will restart the automation for a contact if the contact already in the automation. Otherwise, It will just skip if already exist', 'fluentcampaign-pro')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $event = $originalArgs[0]; // this now equal to $event in hook

        $orderId = $event->order->get_id(); // Using the getter method instead of direct property access

        // $order = \Voxel\Product_Types\Orders\Order::get($orderId);

        $customerId = $event->customer->get_id(); // Using the getter method instead of direct property access

        $user = \Voxel\User::get($customerId);

        $email = $user->get_email(); // Assuming this method exists to get the email


        $subscriber = Subscriber::where('email', $email)->first();

        if (!$subscriber) {
            return;
        }

        $order = \Voxel\Product_Types\Orders\Order::get($orderId);
        if (!$order) {
            return;
        }

        $subscriberData = [
            'email'                => $email,
            'first_name'          => $user->get_first_name(),
            'last_name'           => $user->get_last_name(),
        ];

        $subscriberData = FunnelHelper::maybeExplodeFullName($subscriberData);

        if (!is_email($subscriberData['email'])) {
            return;
        }

        $willProcess = $this->isProcessable($funnel, $order, $subscriberData, $orderId);

        $triggerName = 'voxel_customer_order_placed'; // we modified the trigger name to be more specific

        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $triggerName, $willProcess, $funnel, $subscriberData, $originalArgs);
        if (!$willProcess) {
            return;
        }

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);

        $subscriberData['status'] = (!empty($subscriberData['subscription_status'])) ? $subscriberData['subscription_status'] : 'subscribed';
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $triggerName,
            'source_ref_id'       => $orderId
        ]);

    }

    private function isProcessable($funnel, $order, $subscriber, $orderId)
    {
        $conditions = $funnel->conditions;

        //items ids 
        $productIds = $order->get_item_ids();
        // items 
        // $items = $order->get_items();

        // Check Product IDs if specified
//        if (!empty($conditions['product_ids'])) {
//            $matched = false;
//            foreach ($productIds as $productId) {
//                if (in_array($productId, $conditions['product_ids'])) {
//                    $matched = true;
//                    break;
//                }
//            }
//
//            if (!$matched) {
//                return false;
//            }
//        }


        $productTypeOfOrder = fluentCrmDb()->table('vx_order_items')
                                           ->where('order_id', $orderId)
                                           ->value('product_type');

        // Check Product Types if specified
        if (!empty($conditions['product_types']) && class_exists('\Voxel\Product_Type')) {
            
            if (!in_array($productTypeOfOrder, $conditions['product_types'])) {
                return false;
            }
        }

        return true;
    }
}