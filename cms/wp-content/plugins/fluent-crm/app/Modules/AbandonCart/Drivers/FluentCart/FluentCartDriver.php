<?php

namespace FluentCrm\App\Modules\AbandonCart\Drivers\FluentCart;

use FluentCrm\App\Modules\AbandonCart\AbandonCartModel;
use FluentCrm\App\Modules\AbandonCart\AbCartHelper;
use FluentCrm\App\Modules\AbandonCart\Drivers\AbstractCartDriver;
use FluentCrm\Framework\Support\Arr;

class FluentCartDriver extends AbstractCartDriver
{
    public function getProviderSlug()
    {
        return 'fluent_cart';
    }

    public function getProviderLabel()
    {
        return __('FluentCart', 'fluent-crm');
    }

    public function isAvailable()
    {
        return defined('FLUENTCART_VERSION');
    }

    public function getLogo()
    {
        return FLUENTCRM_PLUGIN_URL . 'assets/images/fluent-cart-dark.svg';
    }

    public function register()
    {
        (new FluentCartTrackingInit())->register();
    }

    public function registerAutomationTrigger()
    {
        // Registered inside FluentCartTrackingInit::register()
    }

    protected function getViewsBasePath()
    {
        return __DIR__ . '/Views/';
    }

    public function isWithinCoolOffPeriod(AbandonCartModel $cart)
    {
        $coolOffPeriodDay = AbCartHelper::getSetting('cool_off_period_days', 0);
        if (!$coolOffPeriodDay) {
            return false;
        }

        $coolOffDateTime = gmdate('Y-m-d H:i:s', time() - ($coolOffPeriodDay * DAY_IN_SECONDS));

        $winStatuses = $this->getWinOrderStatuses();

        return fluentCrmDb()->table('fct_orders')
            ->where('created_at', '>=', $coolOffDateTime)
            ->whereIn('status', $winStatuses)
            ->where(function ($q) use ($cart) {
                $q->whereIn('customer_id', function ($sub) use ($cart) {
                    $sub->select('id')
                        ->from('fct_customers')
                        ->where('email', $cart->email);
                    if ($cart->user_id) {
                        $sub->orWhere('user_id', $cart->user_id);
                    }
                });
            })
            ->exists();
    }

    public function getCartItemsHtml(AbandonCartModel $cart)
    {
        $cartItems = Arr::get($cart->cart, 'cart_data', []);

        return $this->loadView('AbandonCartItems', [
            'cartItems' => $cartItems,
            'currency'  => $cart->currency
        ]);
    }

    public function formatPrice($amount, $currency = '')
    {
        if (!class_exists('\FluentCart\Api\CurrencySettings')) {
            return '$' . number_format((float)$amount, 2);
        }

        return \FluentCart\Api\CurrencySettings::getPriceHtml((int) round(((float) $amount) * 100), $currency ?: null);
    }

    public function getRecoveryUrl(AbandonCartModel $cart)
    {
        if ($cart->status != 'processing') {
            return '';
        }

        return add_query_arg([
            'fluentcrm'  => 1,
            'route'      => 'general',
            'handler'    => $this->getHandlerName(),
            'fc_ab_hash' => $cart->checkout_key
        ], home_url());
    }

    public function extractCartConditionData(AbandonCartModel $cart)
    {
        $items = Arr::get($cart->cart, 'cart_data', []);

        $productIds = [];
        $categoryIds = [];

        foreach ($items as $item) {
            $postId = Arr::get($item, 'post_id');
            if ($postId) {
                $productIds[] = $postId;
            }
        }

        if ($productIds) {
            $cats = fluentCrmDb()->table('term_relationships')
                ->join('term_taxonomy', 'term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
                ->whereIn('term_relationships.object_id', $productIds)
                ->where('term_taxonomy.taxonomy', 'product-categories')
                ->select('term_taxonomy.term_id')
                ->get();

            foreach ($cats as $cat) {
                $categoryIds[] = $cat->term_id;
            }
        }

        return [
            'product_ids'  => $productIds,
            'category_ids' => $categoryIds
        ];
    }

    public function enrichCartForListing(AbandonCartModel $cart)
    {
        if ($cart->order_id) {
            $cart->order_url = admin_url('admin.php?page=fluent-cart#/orders/' . $cart->order_id . '/view');
        }

        $newCart = $cart->cart ?: [];
        $formData = Arr::get($newCart, 'checkout_data.form_data', []);

        $billingFullName = trim(Arr::get($formData, 'billing_first_name', '') . ' ' . Arr::get($formData, 'billing_last_name', ''));

        if (!$billingFullName) {
            $billingFullName = $cart->full_name;
        }

        // Build customer_data with billingAddress/shippingAddress for the shared Vue modal
        $newCart['customer_data'] = [
            'billingAddress'  => [
                'first_name' => $billingFullName,
                'last_name'  => '',
                'address_1'  => Arr::get($formData, 'billing_address_1', ''),
                'address_2'  => Arr::get($formData, 'billing_address_2', ''),
                'postcode'   => Arr::get($formData, 'billing_postcode', ''),
                'city'       => Arr::get($formData, 'billing_city', ''),
                'country'    => Arr::get($formData, 'billing_country', ''),
            ],
            'shippingAddress' => [
                'first_name' => Arr::get($formData, 'shipping_full_name', ''),
                'last_name'  => '',
                'address_1'  => Arr::get($formData, 'shipping_address_1', ''),
                'address_2'  => Arr::get($formData, 'shipping_address_2', ''),
                'postcode'   => Arr::get($formData, 'shipping_postcode', ''),
                'city'       => Arr::get($formData, 'shipping_city', ''),
                'country'    => Arr::get($formData, 'shipping_country', ''),
            ],
            'order_comments'  => Arr::get($formData, 'order_comments', ''),
        ];

        if (Arr::get($formData, 'ship_to_different') !== 'yes') {
            $newCart['customer_data']['shippingAddress'] = $newCart['customer_data']['billingAddress'];
        }

        // Build cart_contents from cart_data for the shared Vue modal
        $cartContents = [];
        $cartItems = Arr::get($newCart, 'cart_data', []);
        foreach ($cartItems as $cartItem) {
            $imageUrl = Arr::get($cartItem, 'featured_media', '');
            if (!$imageUrl) {
                $postId = Arr::get($cartItem, 'post_id');
                if ($postId) {
                    $imageUrl = get_the_post_thumbnail_url($postId, 'thumbnail');
                }
            }

            $subtotal = (int)Arr::get($cartItem, 'subtotal', 0);

            $title = Arr::get($cartItem, 'post_title', '');
            $subTitle = Arr::get($cartItem, 'title', '');

            if($title && $subTitle && $title != $subTitle) {
                $title .= ' - ' . $subTitle;
            }


            $cartContents[] = [
                'title'         => $title,
                'quantity'      => (int)Arr::get($cartItem, 'quantity', 1),
                'line_total'    => number_format($subtotal / 100, 2, '.', ''),
                'product_image' => $imageUrl ?: '',
            ];
        }
        $newCart['cart_contents'] = $cartContents;

        $cart->cart = $newCart;

        return $cart;
    }

    public function getProviderSettingsResponse()
    {
        if (!defined('FLUENTCART_VERSION')) {
            return [];
        }

        return [
            'fct_recovered_statuses' => $this->getWinOrderStatuses(),
        ];
    }

    public function getProviderSettingsDefaults()
    {
        return [
            'fct_recovered_statuses' => ['completed', 'processing'],
        ];
    }

    public function getSettingsFields()
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $statuses = [
            ['id' => 'completed', 'label' => __('Completed', 'fluent-crm')],
            ['id' => 'processing', 'label' => __('Processing', 'fluent-crm')],
            ['id' => 'on-hold', 'label' => __('On Hold', 'fluent-crm')],
        ];

        return [
            'fct_recovered_statuses' => [
                'name'        => 'fct_recovered_statuses',
                'label'       => __('Mark Cart as Recovered when FluentCart Order Status Changes to:', 'fluent-crm'),
                'type'        => 'checkbox-group',
                'options'     => $statuses,
                'inline_help' => __('Automatically mark a cart as recovered when the corresponding FluentCart order status changes to the selected status.', 'fluent-crm'),
            ]
        ];
    }

    /**
     * Check if a FluentCart order status counts as a successful recovery.
     *
     * @param string $orderStatus
     * @return bool
     */
    public function isWinOrderStatus($orderStatus)
    {
        $recoveredStatuses = $this->getWinOrderStatuses();
        $result = in_array($orderStatus, $recoveredStatuses, true);

        return apply_filters('fluent_crm/ab_cart_is_win_status', $result, $orderStatus, $this);
    }

    private function getWinOrderStatuses()
    {
        $settings = AbCartHelper::getSettings();
        return Arr::get($settings, 'fct_recovered_statuses', ['completed', 'processing']);
    }
}
