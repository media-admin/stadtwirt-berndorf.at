<?php

namespace FluentCrm\App\Services\ExternalIntegrations\FluentCart\Triggers;

use FluentCart\App\Helpers\Status;
use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\App\Services\ExternalIntegrations\FluentCart\CartHelper;

class OrderStatusChangedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'fluent_cart/order_status_changed';
        $this->priority = 20;
        $this->actionArgNum = 1;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category' => __('FluentCart', 'fluent-crm'),
            'label' => __('Order Status Changed', 'fluent-crm'),
            'description' => __('This funnel will start when an order status updates', 'fluent-crm'),
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"><g id="surface1"><path style=" stroke:none;fill-rule:nonzero;fill:rgb(100%,100%,100%);fill-opacity:1;" d="M 2.398438 0 L 21.601562 0 C 22.925781 0 24 1.074219 24 2.398438 L 24 21.601562 C 24 22.925781 22.925781 24 21.601562 24 L 2.398438 24 C 1.074219 24 0 22.925781 0 21.601562 L 0 2.398438 C 0 1.074219 1.074219 0 2.398438 0 Z M 2.398438 0 "/><path style=" stroke:none;fill-rule:nonzero;fill:rgb(0%,0%,62.352943%);fill-opacity:1;" d="M 10.925781 16.476562 L 3.769531 16.476562 L 4.894531 13.878906 C 5.222656 13.117188 5.972656 12.625 6.804688 12.625 L 15.328125 12.625 L 14.746094 13.964844 C 14.085938 15.488281 12.585938 16.476562 10.925781 16.476562 Z M 10.925781 16.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:rgb(0%,0%,62.352943%);fill-opacity:1;" d="M 16.851562 11.394531 L 6.789062 11.394531 L 7.367188 10.054688 C 8.027344 8.53125 9.53125 7.542969 11.191406 7.542969 L 19.886719 7.542969 L 18.761719 10.140625 C 18.433594 10.902344 17.683594 11.394531 16.851562 11.394531 Z M 16.851562 11.394531 "/></g></svg>'
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
            'title'     => __('FluentCart Order Status Changed', 'fluent-crm'),
            'sub_title' => __('This Funnel will start when a Order status will change from one state to another', 'fluent-crm'),
            'fields'    => [
                'subscription_status'      => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'editable_statuses',
                    'is_multiple' => false,
                    'label'       => __('Subscription Status', 'fluent-crm'),
                    'placeholder' => __('Select Status', 'fluent-crm')
                ],
                'subscription_status_info' => [
                    'type'       => 'html',
                    'info'       => '<b>' . __('An Automated double-optin email will be sent for new subscribers', 'fluent-crm') . '</b>',
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
            'product_ids'        => [],
            'product_categories' => [],
            'from_status'        => 'any',
            'to_status'          => 'any',
            'run_multiple'       => 'no'
        ];
    }

    public function getConditionFields($funnel)
    {
        $orderStatuses = Status::getOrderStatuses();

        $formattedStatuses = [[
            'id'    => 'any',
            'title' => __('Any', 'fluent-crm')
        ]];

        foreach ($orderStatuses as $statusId => $statusName) {
            $formattedStatuses[] = [
                'id' => $statusId,
                'title' => $statusName
            ];
        }

        return [
            'product_ids'     => [
                'type'        => 'rest_selector',
                'option_key'  => 'fluent_cart_products',
                'is_multiple' => true,
                'label'       => __('Target Products', 'fluent-crm'),
                'help'        => __('Select for which products this automation will run', 'fluent-crm'),
                'inline_help' => __('Keep it blank to run for any product\'s order status change', 'fluent-crm'),
            ],
            'product_categories' => [
                'type'        => 'rest_selector',
                'option_key'  => 'fluent_cart_product_categories',
                'is_multiple' => true,
                'label'       => __('Or Target Product Categories', 'fluent-crm'),
                'help'        => __('Select for which product category the automation will run', 'fluent-crm'),
                'inline_help' => __('Keep it blank to run to any category products', 'fluent-crm'),
            ],
            'from_status' => [
                'type' => 'select',
                'label' => __('From Order Status', 'fluent-crm'),
                'help' => __('The current status that will trigger an action when it changes from this status to the \'To Order Status.\'', 'fluent-crm'),
                'options' => $formattedStatuses
            ],
            'to_status' => [
                'type' => 'select',
                'label' => __('To Order Status', 'fluent-crm'),
                'help' => __('The target status that will trigger an action when the order moves from the \'From Order Status\' to this status.', 'fluent-crm'),
                'options' => $formattedStatuses
            ],
            'run_multiple'  => [
                'type'        => 'yes_no_check',
                'label'       => '',
                'check_label' => __('Restart the Automation Multiple times for a contact for this event. (Only enable if you want to restart automation for the same contact)', 'fluent-crm'),
                'inline_help' => __('If enabled, it will restart the automation for a contact if the contact is already in the automation. Otherwise, it will skip if it already exists', 'fluent-crm')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $orderData = $originalArgs[0] ?? [];

        $order = Arr::get($orderData, 'order', []);
        $fromStatus = Arr::get($orderData, 'old_status', '');
        $toStatus = Arr::get($orderData, 'new_status', '');

        $customer = Arr::get($order, 'customer');

        $orderId = Arr::get($order, 'id', 0);

        $subscriberData = CartHelper::prepareSubscriberData($customer);


        if (!is_email($subscriberData['email'])) {
            return;
        }

        $willProcess = $this->isProcessable($funnel, $subscriberData, $fromStatus, $toStatus, $order);


        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $this->triggerName, $willProcess, $funnel, $subscriberData, $originalArgs);

        if (!$willProcess) {
            return;
        }

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);

        $subscriberData['status'] = (!empty($subscriberData['subscription_status'])) ? $subscriberData['subscription_status'] : 'subscribed';
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id'       => $orderId
        ]);
    }

    private function isProcessable($funnel, $subscriberData, $fromStatus, $toStatus, $order)
    {
        $conditions = (array)$funnel->conditions;

        $orderItems = Arr::get($order, 'order_items', []);
        // Post IDs of ordered products are the product IDs in FluentCart

        $orderedProductIds = [];
        foreach ($orderItems as $item) {
            $productId = $item->post_id;
            if ($productId) {
                $orderedProductIds[] = $productId;
            }
        }

        $orderProductCategories = CartHelper::getProductCategoriesByIds($orderedProductIds);

        $selectedProductIds = Arr::get($conditions, 'product_ids', []);
        $selectedProductCategories = Arr::get($conditions, 'product_categories', []);

        if (!empty($selectedProductIds) || !empty($selectedProductCategories)) {
            $productMatch = !empty($selectedProductIds) && !empty(array_intersect($selectedProductIds, $orderedProductIds));
            $categoryMatch = !empty($selectedProductCategories) && !empty(array_intersect($selectedProductCategories, $orderProductCategories));

            if (!$productMatch && !$categoryMatch) {
                return false;
            }
        }

        $fromCondition = Arr::get($conditions, 'from_status', 'any');
        if ($fromCondition !== 'any' && $fromCondition !== $fromStatus) {
            return false;
        }

        $toCondition = Arr::get($conditions, 'to_status', 'any');
        if ($toCondition !== 'any' && $toCondition !== $toStatus) {
            return false;
        }

        $subscriber = FunnelHelper::getSubscriber($subscriberData['email']);

        if ($subscriber) {
            $funnelSub = FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id);
            if ($funnelSub) {
                $multipleRun = Arr::get($conditions, 'run_multiple') == 'yes';
                if ($multipleRun) {
                    FunnelHelper::removeSubscribersFromFunnel($funnel->id, [$subscriber->id]);
                }
                return $multipleRun;
            }
        }

        return true;
    }
}
