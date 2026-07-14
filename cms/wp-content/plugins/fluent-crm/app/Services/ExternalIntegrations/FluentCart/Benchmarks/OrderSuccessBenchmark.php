<?php

namespace FluentCrm\App\Services\ExternalIntegrations\FluentCart\Benchmarks;

use FluentCrm\App\Services\ExternalIntegrations\FluentCart\CartHelper;
use FluentCrm\App\Services\Funnel\BaseBenchMark;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Framework\Support\Arr;

class OrderSuccessBenchmark extends BaseBenchMark
{
    public function __construct()
    {
        $this->triggerName = 'fluent_cart/order_paid_done';
        $this->actionArgNum = 3;
        $this->priority = 20;

        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'       => __('Order Paid (Payment/Subscription)', 'fluent-crm'),
            'description' => __('This will run once new order will be placed as paid in FluentCRM', 'fluent-crm'),
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1"><g id="surface1"><path style=" stroke:none;fill-rule:nonzero;fill:rgb(100%,100%,100%);fill-opacity:1;" d="M 2.398438 0 L 21.601562 0 C 22.925781 0 24 1.074219 24 2.398438 L 24 21.601562 C 24 22.925781 22.925781 24 21.601562 24 L 2.398438 24 C 1.074219 24 0 22.925781 0 21.601562 L 0 2.398438 C 0 1.074219 1.074219 0 2.398438 0 Z M 2.398438 0 "/><path style=" stroke:none;fill-rule:nonzero;fill:rgb(0%,0%,62.352943%);fill-opacity:1;" d="M 10.925781 16.476562 L 3.769531 16.476562 L 4.894531 13.878906 C 5.222656 13.117188 5.972656 12.625 6.804688 12.625 L 15.328125 12.625 L 14.746094 13.964844 C 14.085938 15.488281 12.585938 16.476562 10.925781 16.476562 Z M 10.925781 16.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:rgb(0%,0%,62.352943%);fill-opacity:1;" d="M 16.851562 11.394531 L 6.789062 11.394531 L 7.367188 10.054688 C 8.027344 8.53125 9.53125 7.542969 11.191406 7.542969 L 19.886719 7.542969 L 18.761719 10.140625 C 18.433594 10.902344 17.683594 11.394531 16.851562 11.394531 Z M 16.851562 11.394531 "/></g></svg>',
            'settings'    => [
                'product_ids'        => [],
                'product_categories' => [],
                'type'               => 'required',
                'can_enter'          => 'yes'
            ]
        ];
    }

    public function getDefaultSettings()
    {
        return [
            'product_ids'        => [],
            'product_categories' => [],
            'type'               => 'required',
            'can_enter'          => 'yes'
        ];
    }

    public function getBlockFields($funnel)
    {
        return [
            'title'     => __('New Order Paid in FluentCart', 'fluent-crm'),
            'sub_title' => __('This will run once new order will be placed as paid in FluentCart', 'fluent-crm'),
            'fields'    => [
                'product_ids'        => [
                    'type'        => 'rest_selector',
                    'label'       => __('Target Products', 'fluent-crm'),
                    'option_key'  => 'fluent_cart_products',
                    'is_multiple' => true,
                    'help'        => __('Select for which products this automation will run', 'fluent-crm'),
                    'inline_help' => __('Keep it blank to run to any product purchase', 'fluent-crm')
                ],
                'product_categories' => [
                    'type'        => 'rest_selector',
                    'label'       => __('Or Target Product Categories', 'fluent-crm'),
                    'option_key'  => 'fluent_cart_product_categories',
                    'is_multiple' => true,
                    'help'        => __('Select for which product category the automation will run', 'fluent-crm'),
                    'inline_help' => __('Keep it blank to run to any category products', 'fluent-crm')
                ],
                'type'               => $this->benchmarkTypeField(),
                'can_enter'          => $this->canEnterField()
            ]
        ];
    }

    public function handle($benchMark, $originalArgs)
    {
        $orderData = $originalArgs[0] ?? [];
        $order = Arr::get($orderData, 'order', []);
        $customer = Arr::get($orderData, 'customer', []);

        $settings = $benchMark->settings;;

        if (!$this->checkConditions($settings, $order)) {
            return;
        }

        $subscriberData = CartHelper::prepareSubscriberData($customer);

        if (!is_email($subscriberData['email'])) {
            return;
        }

        $subscriberData['status'] = 'subscribed';
        $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);

        $funnelProcessor = new FunnelProcessor();
        $funnelProcessor->startFunnelFromSequencePoint($benchMark, $subscriber, [], [
            'benchmark_value'    => $order->total_paid, // converted to cents
            'benchmark_currency' => $order->currency,
        ]);
    }


    private function checkConditions($conditions, $order)
    {
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

        // If no products or categories are selected, return true
        if (empty($selectedProductIds) && empty($selectedProductCategories)) {
            return true;
        }

        // Check for matches in product IDs and categories
        $productMatch = !empty($selectedProductIds) && !empty(array_intersect($selectedProductIds, $orderedProductIds));
        $categoryMatch = !empty($selectedProductCategories) && !empty(array_intersect($selectedProductCategories, $orderProductCategories));

        // Return true if either matches
        return $productMatch || $categoryMatch;
    }

}
