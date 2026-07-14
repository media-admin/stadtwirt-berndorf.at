<?php

namespace FluentCrm\App\Modules\AbandonCart\Drivers\FluentCart;

use FluentCart\Api\StoreSettings;
use FluentCart\App\Helpers\AddressHelper;
use FluentCart\App\Models\Cart;
use FluentCrm\App\Modules\AbandonCart\AbandonCartModel;
use FluentCrm\App\Modules\AbandonCart\AbCartHelper;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\Framework\Support\Arr;

class FluentCartTrackingInit
{
    public function register()
    {
        // Funnel Automations
        (new FluentCartAutomationTrigger())->register();

        // Checkout Frontend - inject tracking script
        add_action('fluent_cart/after_checkout_page', [$this, 'addAbandonScript']);

        // AJAX handler for GDPR opt-out
        add_action('wp_ajax_fc_ab_fct_cart_skip', [$this, 'handleAjaxOptOut']);
        add_action('wp_ajax_nopriv_fc_ab_fct_cart_skip', [$this, 'handleAjaxOptOut']);

        // Sync abandoned cart when FluentCart saves checkout data
        add_filter('fluent_cart/checkout/after_patch_checkout_data_fragments', function ($fragments, $data) {
            $this->maybeSyncCart(Arr::get($data, 'cart'));
            return $fragments;
        }, 99, 2);

        // Sync abandoned cart when cart amounts change (item add/remove, coupon)
        add_action('fluent_cart/checkout/cart_amount_updated', function ($data) {
            $this->maybeSyncCart(Arr::get($data, 'cart'));
        }, 99);

        // Sync abandoned cart on form data change (alternative save path)
        add_action('fluent_cart/checkout/form_data_changed', function ($data) {
            $this->maybeSyncCart(Arr::get($data, 'cart'));
        }, 99);

        // Cart recovery URL handler
        // URL: example.com/?fluentcrm=1&route=general&handler=fc_cart_fluent_cart&fc_ab_hash=xyz
        add_action('fluent_crm/handle_frontend_for_fc_cart_fluent_cart', function ($data) {
            add_action('template_redirect', function () use ($data) {
                $this->maybeRestoreCart($data);
            }, 1);
        });

        // Order lifecycle - link cart to order when created
        add_action('fluent_cart/order_created', [$this, 'handleOrderCreated'], 1);

        // Order paid - mark cart as recovered
        add_action('fluent_cart/order_paid', [$this, 'handleOrderPaid'], 1);

        // Order status changes
        add_action('fluent_cart/order_status_changed', [$this, 'handleOrderStatusChanged'], 10);

        // Push contextual smart codes for this provider
        add_filter('fluent_crm_funnel_context_smart_codes', [$this, 'pushContextCodes'], 1, 2);

        // Parse the context codes
        add_filter('fluent_crm/smartcode_group_callback_ab_cart_fluent_cart', [$this, 'parseSmartCodes'], 10, 4);
    }

    public function addAbandonScript()
    {
        if (!AbCartHelper::willCartTrack()) {
            return;
        }

        if (isset($_COOKIE['fc_ab_cart_skip_track']) && $_COOKIE['fc_ab_cart_skip_track'] == 'yes') {
            return;
        }

        wp_enqueue_script(
            'fluent_crm-abandon-cart-fct',
            FLUENTCRM_PLUGIN_URL . 'app/Modules/AbandonCart/Drivers/FluentCart/assets/fc-cart-abandon-fluent-cart.js',
            [],
            FLUENTCRM_PLUGIN_VERSION,
            true
        );

        wp_localize_script('fluent_crm-abandon-cart-fct', 'fc_ab_fct_cart', [
            'nonce'          => wp_create_nonce('fc_ab_fct_cart_nonce'),
            '__gdpr_message' => AbCartHelper::getGDPRMessage(),
        ]);
    }

    public function handleAjaxOptOut()
    {
        $nonce = Arr::get($_REQUEST, '_nonce');
        if (!wp_verify_nonce($nonce, 'fc_ab_fct_cart_nonce')) {
            wp_send_json([
                'message' => __('Security check failed. Invalid nonce.', 'fluent-crm')
            ], 403);
        }

        $record = $this->getCurrentRecord();

        if ($record) {
            $record->optOut();
        }

        $cookieDays = (int)apply_filters('fluent_crm/ab_cart_opt_out_cookie_validity', 7);
        setcookie('fc_ab_cart_skip_track', 'yes', time() + (86400 * $cookieDays), COOKIEPATH, COOKIE_DOMAIN);

        wp_send_json([
            'message' => __('You have opted out from cart tracking', 'fluent-crm')
        ]);
    }

    public function maybeSyncCart($fctCart)
    {
        if (!$fctCart || !AbCartHelper::willCartTrack()) {
            return;
        }

        if (!$fctCart->email || empty($fctCart->cart_data)) {
            return;
        }

        $billingEmail = $fctCart->email;

        if (isset($_COOKIE['fc_ab_cart_skip_track']) && $_COOKIE['fc_ab_cart_skip_track'] == 'yes') {
            $record = $this->getCurrentRecord($billingEmail, $fctCart->cart_hash);
            if ($record && $record->status !== 'opt_out') {
                $record->status = 'opt_out';
                $record->save();
            }
            return;
        }

        $checkoutData = $fctCart->checkout_data ?: [];

        // Calculate totals for the table columns (FluentCart stores amounts in cents).
        // $subtotal is the items' price BEFORE any discount.
        $subtotal = $fctCart->getItemsSubtotal();

        // Coupon and per-item manual discounts are stored on each cart item as
        // discount_total (manual_discount + coupon_discount). The old code only read
        // custom_checkout_data.discount_total, which is empty for normal frontend
        // coupons, so the coupon was never reflected in the cart total. Sum the per-item
        // discounts plus any checkout-level (manual/upgrade/prorate) discounts.
        $itemsDiscountTotal = array_sum(array_map(function ($item) {
            return (int)Arr::get($item, 'discount_total', 0);
        }, $fctCart->cart_data ?: []));

        $discountTotal = $itemsDiscountTotal
            + (int)Arr::get($checkoutData, 'manual_discount.amount', 0)
            + (int)Arr::get($checkoutData, 'upgrade_discount.amount', 0)
            + (int)Arr::get($checkoutData, 'prorate_credit.amount', 0);

        $shippingTotal = (int)$fctCart->getShippingTotal();
        $taxTotal = (int)Arr::get($checkoutData, 'tax_data.tax_total', 0);

        // Use FluentCart's authoritative total so the displayed Cart Total matches the
        // checkout exactly (coupons, fees, shipping and tax all included).
        $total = (int)$fctCart->getEstimatedTotal();

        if ($total <= 0) {
            $record = $this->getCurrentRecord($billingEmail);
            if ($record) {
                $record->delete();
                setcookie('fc_ab_fct_cart_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
            return;
        }

        $currency = '';
        if (class_exists('\FluentCart\Api\CurrencySettings')) {
            $currency = \FluentCart\Api\CurrencySettings::get('currency') ?: 'USD';
        }

        $contact = FluentCrmApi('contacts')->getContact($billingEmail);
        $fullName = $fctCart->full_name ?? trim($fctCart->first_name . ' ' . $fctCart->last_name);
        if (!$fullName && $contact) {
            $fullName = trim($contact->first_name . ' ' . $contact->last_name);
        }

        // Build a per-coupon breakdown (code + discounted value) for the cart details view.
        // FluentCart stores the applied codes on $fctCart->coupons and the amount each code
        // saved (in cents) on checkout_data.__per_coupon_discounts, keyed by code.
        $perCouponDiscounts = Arr::get($checkoutData, '__per_coupon_discounts', []);
        $couponDetails = [];
        foreach (($fctCart->coupons ?: []) as $couponCode) {
            $couponAmount = (int)Arr::get($perCouponDiscounts, $couponCode, 0);
            $couponDetails[] = [
                'code'     => $couponCode,
                'discount' => number_format($couponAmount / 100, 2, '.', ''),
            ];
        }

        // Snapshot the FluentCart data as-is for easy restore
        $data = [
            'cart_hash'  => $fctCart->cart_hash,
            'full_name'  => $fullName,
            'email'      => $billingEmail,
            'provider'   => 'fluent_cart',
            'user_id'    => $fctCart->user_id,
            'contact_id' => $contact ? $contact->id : null,
            'order_id'   => $fctCart->order_id,
            'subtotal'   => number_format($subtotal / 100, 2, '.', ''),
            'shipping'   => number_format($shippingTotal / 100, 2, '.', ''),
            'discounts'  => number_format($discountTotal / 100, 2, '.', ''),
            'tax'        => number_format($taxTotal / 100, 2, '.', ''),
            'fees'       => 0,
            'total'      => number_format($total / 100, 2, '.', ''),
            'currency'   => $currency,
            'cart'       => [
                'cart_data'      => $fctCart->cart_data ?: [],
                'checkout_data'  => $checkoutData,
                'coupons'        => $fctCart->coupons ?: [],
                'coupons_detail' => $couponDetails,
                'utm_data'       => $fctCart->utm_data ?: [],
                'cart_group'     => $fctCart->cart_group,
                'customer_data'  => $this->buildCustomerData($checkoutData),
            ],
        ];

        $record = $this->getCurrentRecord($billingEmail, $fctCart->cart_hash);

        if (!$record) {
            $data['status'] = 'draft';
            $record = AbandonCartModel::create($data);
        } else {
            $record->fill($data);
            $record->save();
        }

        $cookieDays = (int)apply_filters('fluent_crm/ab_cart_cookie_validity', 30);
        setcookie('fc_ab_fct_cart_token', $record->checkout_key, time() + (86400 * $cookieDays), COOKIEPATH, COOKIE_DOMAIN);
    }

    private function buildCustomerData($checkoutData)
    {
        $formData = Arr::get($checkoutData, 'form_data', []);

        return [
            'billingAddress' => [
                'first_name' => Arr::get($formData, 'billing_first_name', ''),
                'last_name'  => Arr::get($formData, 'billing_last_name', ''),
                'address_1'  => Arr::get($formData, 'billing_address_1', ''),
                'address_2'  => Arr::get($formData, 'billing_address_2', ''),
                'postcode'   => Arr::get($formData, 'billing_postcode', ''),
                'city'       => Arr::get($formData, 'billing_city', ''),
                'state'      => Arr::get($formData, 'billing_state', ''),
                'country'    => Arr::get($formData, 'billing_country', ''),
                'phone'      => Arr::get($formData, 'billing_phone', ''),
            ],
        ];
    }

    private function getCurrentRecord($billingEmail = null, $cartHash = null)
    {
        if ($cartHash) {
            // Try to find by cart hash first if available
            $record = AbandonCartModel::where('cart_hash', $cartHash)
                ->where('provider', 'fluent_cart')
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        // First try from the cookie
        $existingToken = Arr::get($_COOKIE, 'fc_ab_fct_cart_token');

        if ($existingToken) {
            $record = AbandonCartModel::where('checkout_key', $existingToken)
                ->where('provider', 'fluent_cart')
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        // Try with billing email
        if ($billingEmail) {
            $record = AbandonCartModel::where('email', $billingEmail)
                ->where('provider', 'fluent_cart')
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        // If user logged in, try with user id
        $userId = get_current_user_id();
        if ($userId) {
            $record = AbandonCartModel::where('user_id', $userId)
                ->where('provider', 'fluent_cart')
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        return null;
    }

    public function handleOrderCreated($eventData)
    {
        $order = Arr::get($eventData, 'order');
        if (!$order) {
            return;
        }

        $token = sanitize_text_field(Arr::get($_COOKIE, 'fc_ab_fct_cart_token', ''));
        if (!$token) {
            return;
        }

        $abCart = AbCartHelper::getAbCartByDataProps([
            'checkout_key' => $token
        ], ['processing', 'draft']);

        if (!$abCart || $abCart->provider !== 'fluent_cart') {
            return;
        }

        $abCart->order_id = $order->id;
        $abCart->save();
    }

    public function handleOrderPaid($eventData)
    {
        $order = Arr::get($eventData, 'order');
        $customer = Arr::get($eventData, 'customer');
        if (!$order || !$customer) {
            return;
        }

        $abCart = AbandonCartModel::query()->where('order_id', $order->id)->where('provider', 'fluent_cart')->first();

        if (!$abCart) {
            $this->cancelAutomationsByCustomer($customer);
            return;
        }

        $this->markCartAsRecovered($abCart, $order);
    }

    public function handleOrderStatusChanged($eventData)
    {
        $order = Arr::get($eventData, 'order');
        $newStatus = Arr::get($eventData, 'new_status');

        if (!$order || !$newStatus) {
            return;
        }

        $abCartId = $order->getMeta('_fc_ab_cart_id');
        if (!$abCartId) {
            return;
        }

        $abCart = AbandonCartModel::find($abCartId);
        if (!$abCart || $abCart->provider !== 'fluent_cart') {
            return;
        }

        $driver = new FluentCartDriver();

        if ($driver->isWinOrderStatus($newStatus)) {
            if ($abCart->status !== 'recovered') {
                $this->markCartAsRecovered($abCart, $order);
            }
            return;
        }

        $lostStatuses = ['failed', 'canceled'];
        if (in_array($newStatus, $lostStatuses, true)) {
            $this->handleCartLost($abCart, $order);
        }
    }

    private function markCartAsRecovered($abCart, $order)
    {
        $deletableStatuses = ['draft', 'opt_out', 'pending'];
        if (in_array($abCart->status, $deletableStatuses, true)) {
            $abCart->deleteCart();
            $this->deleteOtherCarts($abCart, $order);
            return;
        }

        $recoverableStatuses = ['processing', 'lost', 'cancelled'];
        if (!in_array($abCart->status, $recoverableStatuses, true)) {
            return;
        }

        $settings = AbCartHelper::getSettings();
        $subscriber = $abCart->subscriber;
        if ($subscriber) {
            if ($attachLists = Arr::get($settings, 'lists_on_cart_abandoned', [])) {
                $subscriber->detachLists($attachLists);
            }

            if ($attachTags = Arr::get($settings, 'tags_on_cart_abandoned', [])) {
                $subscriber->detachTags($attachTags);
            }
        }

        $orderTotal = $order->total_amount ?? 0;

        $oldStatus = $abCart->status;
        $abCart->status = 'recovered';
        $abCart->order_id = $order->id;
        $abCart->total = $orderTotal / 100;
        $abCart->recovered_at = current_time('mysql');
        $abCart->save();

        do_action('fluent_crm/ab_cart_fluent_cart_recovered', $abCart, $order, $oldStatus);

        $this->deleteOtherCarts($abCart, $order);

        $this->handleCartRecoveredAutomations($abCart);
    }

    private function handleCartLost($abCart, $order)
    {
        if ($abCart->status == 'lost') {
            return;
        }

        $oldStatus = $abCart->status;
        $abCart->status = 'lost';
        $abCart->save();

        do_action('fluent_crm/ab_cart_fluent_cart_lost', $abCart, $order, $oldStatus);

        if ($abCart->automation_id) {
            $subscriber = $abCart->subscriber;
            if ($subscriber) {
                $settings = AbCartHelper::getSettings();
                if ($attachLists = Arr::get($settings, 'lists_on_cart_lost', [])) {
                    $subscriber->attachLists($attachLists);
                }

                if ($attachTags = Arr::get($settings, 'tags_on_cart_lost', [])) {
                    $subscriber->attachTags($attachTags);
                }

                FunnelSubscriber::where('subscriber_id', $subscriber->id)
                    ->where('source_ref_id', $abCart->id)
                    ->whereHas('funnel', function ($q) {
                        $q->where('trigger_name', 'fc_ab_cart_simulation_fluent_cart');
                    })
                    ->where('funnel_id', $abCart->automation_id)
                    ->update([
                        'status' => 'cancelled',
                        'notes'  => __('Automatically cancelled because the cart has been lost', 'fluent-crm')
                    ]);
            }
        }
    }

    private function handleCartRecoveredAutomations($abCart)
    {
        if (!$abCart->automation_id) {
            return;
        }

        $contact = $abCart->subscriber;
        if (!$contact) {
            return;
        }

        $this->cancelAutomations($contact);
    }

    private function cancelAutomations($subscriber)
    {
        FunnelSubscriber::where('subscriber_id', $subscriber->id)
            ->whereHas('funnel', function ($q) {
                $q->where('trigger_name', 'fc_ab_cart_simulation_fluent_cart');
            })
            ->whereIn('status', ['active', 'pending', 'paused'])
            ->update([
                'status' => 'cancelled',
                'notes'  => __('Automatically cancelled because a cart has been recovered', 'fluent-crm')
            ]);
    }

    private function cancelAutomationsByCustomer($customer)
    {
        $subscriberIds = Subscriber::select(['id'])
            ->where('email', $customer->email)
            ->when($customer->user_id, function ($q) use ($customer) {
                return $q->orWhere('user_id', $customer->user_id);
            })
            ->pluck('id')
            ->toArray();

        if (!$subscriberIds) {
            return;
        }

        FunnelSubscriber::whereIn('subscriber_id', $subscriberIds)
            ->whereHas('funnel', function ($q) {
                $q->where('trigger_name', 'fc_ab_cart_simulation_fluent_cart');
            })
            ->whereIn('status', ['active', 'pending', 'paused'])
            ->update([
                'status' => 'cancelled',
                'notes'  => __('Automatically cancelled because a cart has been recovered', 'fluent-crm')
            ]);
    }

    private function deleteOtherCarts($abCart, $order)
    {
        $customerEmail = $abCart->email;
        $customerId = 0;

        if (method_exists($order, 'getAttribute')) {
            $customerId = $order->customer_id ?? 0;
        }

        $query = AbandonCartModel::where('provider', 'fluent_cart')
            ->where('id', '!=', $abCart->id)
            ->whereIn('status', ['processing', 'draft']);

        $query->where(function ($q) use ($customerEmail, $customerId) {
            $q->where('email', $customerEmail);
            if ($customerId) {
                // Look up user_id from the FluentCart customer
                $userId = fluentCrmDb()->table('fct_customers')
                    ->where('id', $customerId)
                    ->value('user_id');
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            }
        });

        $otherCarts = $query->get();

        foreach ($otherCarts as $cart) {
            $cart->deleteCart();
        }
    }

    public function maybeRestoreCart($data)
    {
        $cartHash = sanitize_text_field(Arr::get($data, 'fc_ab_hash', ''));

        $abandonCart = null;
        if ($cartHash) {
            $abandonCart = AbandonCartModel::where('checkout_key', $cartHash)->first();
        }

        if (!$abandonCart || $abandonCart->status != 'processing' || $abandonCart->provider != 'fluent_cart') {
            do_action('fluent_crm/ab_cart_restore_failed', $abandonCart);

            $checkoutUrl = home_url();
            if (class_exists('\FluentCart\Api\StoreSettings')) {
                $checkoutUrl = (new \FluentCart\Api\StoreSettings())->getCheckoutPage() ?: $checkoutUrl;
            }

            wp_redirect($checkoutUrl);
            exit();
        }

        // Set tracking cookie
        $cookieDays = (int)apply_filters('fluent_crm/ab_cart_cookie_validity', 30);
        setcookie('fc_ab_fct_cart_token', $abandonCart->checkout_key, time() + (86400 * $cookieDays), COOKIEPATH, COOKIE_DOMAIN);

        $abandonCart->click_counts = $abandonCart->click_counts + 1;
        $abandonCart->save();

        // Restore the FluentCart cart from our snapshot
        $snapshot = $abandonCart->cart;
        $fctCartHash = $abandonCart->cart_hash;

        if ($fctCartHash) {
            $fctCart = \FluentCart\App\Models\Cart::where('cart_hash', $fctCartHash)->first();

            if ($fctCart) {
                // just redirect to checkout if the cart still exists in FluentCart
                $checkoutUrl = add_query_arg([
                    'fct_cart_hash' => $fctCart->cart_hash,
                ], (new StoreSettings())->getCheckoutPage());

                wp_redirect($checkoutUrl);
                exit();
            }
        }


        $newCart = new Cart();
        $newCart->cart_hash = $abandonCart->cart_hash ?: Cart::generateCartHash();
        // Write the snapshot back directly
        $newCart->cart_data = Arr::get($snapshot, 'cart_data', []);
        $newCart->checkout_data = Arr::get($snapshot, 'checkout_data', []);
        $newCart->coupons = Arr::get($snapshot, 'coupons', []);
        $newCart->email = $abandonCart->email;
        $newCart->utm_data = Arr::get($snapshot, 'utm_data', []);
        $newCart->cart_group = 'instant';
        $newCart->ip_address = AddressHelper::getIpAddress();
        $newCart->user_agent = AddressHelper::getUserAgent();

        $fullName = $abandonCart->full_name;
        if ($fullName) {
            // Try to split full name into first and last name for better compatibility
            $nameParts = explode(' ', $fullName, 2);
            $newCart->first_name = $nameParts[0];
            $newCart->last_name = isset($nameParts[1]) ? $nameParts[1] : '';
        }

        $newCart->save();

        \FluentCart\Api\Cookie\Cookie::setCartHash($newCart->cart_hash);

        $abandonCart->cart_hash = $newCart->cart_hash;
        $abandonCart->save();

        wp_redirect(add_query_arg(['fct_cart_hash' => $newCart->cart_hash], (new \FluentCart\Api\StoreSettings())->getCheckoutPage()));
        exit();
    }

    public function pushContextCodes($codes, $context)
    {
        if ($context != 'fc_ab_cart_simulation_fluent_cart') {
            return $codes;
        }

        $smartCodes = [
            'key'        => 'ab_cart_fluent_cart',
            'title'      => 'Abandoned Cart - FluentCart',
            'shortcodes' => [
                '{{ab_cart_fluent_cart.billing_email}}'     => __('Cart Billing Email', 'fluent-crm'),
                '{{ab_cart_fluent_cart.cart_items_table}}'  => __('Cart Items', 'fluent-crm'),
                '##ab_cart_fluent_cart.recovery_url##'      => __('Cart Recovery URL', 'fluent-crm'),
                '{{ab_cart_fluent_cart.cart_total}}'        => __('Cart Total', 'fluent-crm'),
                '{{ab_cart_fluent_cart.subtotal}}'          => __('Cart Subtotal (only products)', 'fluent-crm'),
                '{{ab_cart_fluent_cart.shipping_total}}'    => __('Cart Shipping Total', 'fluent-crm'),
                '{{ab_cart_fluent_cart.discount_total}}'    => __('Cart Discount Total', 'fluent-crm'),
                '{{ab_cart_fluent_cart.coupon_codes}}'      => __('Applied Coupon Codes', 'fluent-crm'),
                '{{ab_cart_fluent_cart.tax_total}}'         => __('Cart Tax Total', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_full_name}}' => __('Billing Full Name', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_address}}'   => __('Billing Address', 'fluent-crm'),
                '{{ab_cart_fluent_cart.shipping_address}}'  => __('Shipping Address', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_city}}'      => __('Billing City', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_state}}'     => __('Billing State', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_postcode}}'  => __('Billing Postcode', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_country}}'   => __('Billing Country', 'fluent-crm'),
                '{{ab_cart_fluent_cart.billing_phone}}'     => __('Billing Phone', 'fluent-crm'),
            ]
        ];

        $codes[] = $smartCodes;

        return $codes;
    }

    public function parseSmartCodes($code, $valueKey, $defaultValue, $subscriber)
    {
        $abCart = null;

        if ($subscriber->funnel_subscriber_id) {
            $funnelSub = FunnelSubscriber::find($subscriber->funnel_subscriber_id);
            if ($funnelSub) {
                $abCart = AbandonCartModel::find($funnelSub->source_ref_id);
            }
        }

        if (!$abCart) {
            $abCart = AbandonCartModel::where('email', $subscriber->email)
                ->where('provider', 'fluent_cart')
                ->whereIn('status', ['processing', 'opt_out', 'lost'])
                ->orderBy('id', 'DESC')
                ->first();
        }

        if (!$abCart && defined('FLUENTCRM_PREVIEWING_EMAIL')) {
            $abCart = AbandonCartModel::where('provider', 'fluent_cart')
                ->orderBy('id', 'DESC')
                ->first();
        }

        if (!$abCart) {
            if (defined('FLUENTCRM_PREVIEWING_EMAIL')) {
                return __('Dynamic Text will be available on real email', 'fluent-crm');
            }

            return $defaultValue;
        }

        $formatPrice = function ($amount) use ($abCart) {
            $driver = new FluentCartDriver();
            return $driver->formatPrice($amount, $abCart->currency);
        };

        switch ($valueKey) {
            case 'billing_email':
                return $abCart->email;
            case 'cart_total':
                return $formatPrice($abCart->total);
            case 'subtotal':
                return $formatPrice($abCart->subtotal);
            case 'shipping_total':
                return $abCart->shipping ? $formatPrice($abCart->shipping) : $defaultValue;
            case 'discount_total':
                return ($abCart->discounts > 0) ? $formatPrice($abCart->discounts) : $defaultValue;
            case 'coupon_codes':
                $couponCodes = Arr::get($abCart->cart, 'coupons', []);
                return $couponCodes ? implode(', ', $couponCodes) : $defaultValue;
            case 'tax_total':
                return $abCart->tax ? $formatPrice($abCart->tax) : $defaultValue;
            case 'billing_full_name':
                return $abCart->full_name ?: $defaultValue;
            case 'billing_address':
            case 'shipping_address':
                $prefix = ($valueKey === 'shipping_address') ? 'shipping_' : 'billing_';
                $formData = Arr::get($abCart->cart, 'checkout_data.form_data', []);
                return implode(', ', array_filter([
                    Arr::get($formData, $prefix . 'address_1'),
                    Arr::get($formData, $prefix . 'address_2'),
                    Arr::get($formData, $prefix . 'city'),
                    Arr::get($formData, $prefix . 'state'),
                    Arr::get($formData, $prefix . 'postcode'),
                ])) ?: $defaultValue;
            case 'billing_city':
            case 'billing_state':
            case 'billing_postcode':
            case 'billing_country':
            case 'billing_phone':
                return Arr::get($abCart->cart, 'checkout_data.form_data.' . $valueKey, $defaultValue);
            case 'recovery_url':
                return add_query_arg([
                    'fluentcrm'  => 1,
                    'route'      => 'general',
                    'handler'    => 'fc_cart_fluent_cart',
                    'fc_ab_hash' => $abCart->checkout_key
                ], home_url());
            case 'cart_items_table':
                return $abCart->getCartItemsHtml();
            default:
                return apply_filters('fluent_crm/ab_cart_smart_code_default_value', $defaultValue, $valueKey, $abCart);
        }
    }
}
