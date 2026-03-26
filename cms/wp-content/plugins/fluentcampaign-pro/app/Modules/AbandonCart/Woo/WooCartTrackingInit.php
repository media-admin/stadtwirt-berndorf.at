<?php

namespace FluentCampaign\App\Modules\AbandonCart\Woo;

use FluentCampaign\App\Modules\AbandonCart\AbandonCartModel;
use FluentCampaign\App\Modules\AbandonCart\AbCartHelper;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Framework\Support\Arr;

class WooCartTrackingInit
{
    public function register()
    {
        // Funnel Automations
        (new AbandonCartAutomationTrigger())->register();

        /*
         * Checkout Frontend
         */
        add_action('woocommerce_after_checkout_form', array($this, 'addAbandonScript'));
        add_action('woocommerce_blocks_enqueue_checkout_block_scripts_after', array($this, 'addAbandonScriptWooBlocks'));

        add_action('wc_ajax_fc_ab_cart_skip_track_data', array($this, 'handleAjaxOptOut'));

        // Update the cart Ajax
        add_action('wc_ajax_fc_ab_cart_update_cart_data', [$this, 'handleAjaxUpdateCart']);

        // Restore the cart eg url: example.com/?fluentcrm=1&route=general&handler=fc_cart_woo&fc_ab_hash=123
        add_action('fluent_crm/handle_frontend_for_fc_cart_woo', function ($data) {
            add_action('template_redirect', function () use ($data) {
                $this->maybeRestoreCart($data);
            }, 1);
        });

        add_filter('woocommerce_checkout_fields', [$this, 'maybePrefillCheckoutFields'], 10, 1);

        /*
         * Process Orders
         */
        add_action('woocommerce_checkout_order_processed', [$this, 'handleOrderCreated'], 1);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'handleOrderCreated'], 1);

        add_action('woocommerce_order_status_changed', function ($orderId, $from, $to, $order) {

            if (!$order instanceof \WC_Order) {
                $order = wc_get_order($orderId);
            }

            if (!$order instanceof \WC_Order) {
                return;
            }

            $this->maybeHandleCartOrderChanged($order);
        }, 10, 4);

        // Let's push contextual Smartcodes
        add_filter('fluent_crm_funnel_context_smart_codes', array($this, 'pushContextCodes'), 1, 2);

        // Let's parse the context codes
        add_filter('fluent_crm/smartcode_group_callback_ab_cart_woo', array($this, 'parseSmartCodes'), 10, 4);

    }

    public function addAbandonScript()
    {
        if (!AbCartHelper::willCartTrack()) {
            return false;
        }

        if (isset($_COOKIE['fc_ab_cart_skip_track']) && $_COOKIE['fc_ab_cart_skip_track'] == 'yes') {
            return false;
        }

        wp_enqueue_script('fluent_crm-abandon-cart-woo', FLUENTCAMPAIGN_PLUGIN_URL . 'app/Modules/AbandonCart/assets/fc-cart-abandon-woo.js', array('jquery'), FLUENTCAMPAIGN_PLUGIN_VERSION, true);

        wp_localize_script('fluent_crm-abandon-cart-woo', 'fc_ab_cart', array(
            'wc_ajaxurl'       => home_url('?wc-ajax=fc_ab_cart_update_cart_data', 'relative'),
            'wc_no_thanks_url' => home_url('?wc-ajax=fc_ab_cart_skip_track_data', 'relative'),
            'nonce'            => wp_create_nonce('fc_ab_cart_nonce'),
            '_post_id'         => get_the_ID(),
            '__gdpr_message'   => AbCartHelper::getGDPRMessage(),
        ));

    }

    public function addAbandonScriptWooBlocks()
    {
        if (!AbCartHelper::willCartTrack()) {
            return false;
        }

        if (isset($_COOKIE['fc_ab_cart_skip_track']) && $_COOKIE['fc_ab_cart_skip_track'] == 'yes') {
            return false;
        }

        wp_enqueue_script('fluent_crm-abandon-cart-woo', FLUENTCAMPAIGN_PLUGIN_URL . 'app/Modules/AbandonCart/assets/fc-cart-abandon-wc-blocks.js', array('jquery'), FLUENTCAMPAIGN_PLUGIN_VERSION, true);

        wp_localize_script('fluent_crm-abandon-cart-woo', 'fc_ab_cart_blocks', array(
            'wc_ajaxurl'       => home_url('?wc-ajax=fc_ab_cart_update_cart_data', 'relative'),
            'wc_no_thanks_url' => home_url('?wc-ajax=fc_ab_cart_skip_track_data', 'relative'),
            'nonce'            => wp_create_nonce('fc_ab_cart_nonce'),
            '_post_id'         => get_the_ID(),
            '__gdpr_message'   => AbCartHelper::getGDPRMessage(),
        ));

    }

    public function handleAjaxOptOut()
    {
        $nonce = Arr::get($_REQUEST, '_nonce');
        if (!wp_verify_nonce($nonce, 'fc_ab_cart_nonce')) {
            wp_send_json([
                'message' => 'Security failed. Invalid Nonce'
            ], 403);
        }

        $cartHash = WC()->session->get('fc_ab_cart_hash');

        $abCart = null;

        if ($cartHash) {
            $abCart = AbandonCartModel::where('cart_hash', $cartHash)
                ->first();
        }

        if (!$abCart) {
            $exitingToken = Arr::get($_COOKIE, 'fc_ab_cart_token');
            if ($exitingToken) {
                $abCart = AbandonCartModel::where('checkout_key', $exitingToken)
                    ->first();
            }
        }

        if ($abCart) {
            $abCart->optOut();
        }

        $cookieDays = (int)apply_filters('fluent_crm/ab_cart_opt_out_cookie_validity', 7);
        // set cookie for 30 days
        setcookie('fc_ab_cart_skip_track', 'yes', time() + (86400 * $cookieDays), COOKIEPATH, COOKIE_DOMAIN);

        wp_send_json([
            'message' => __('You have opted out from cart tracking', 'fluentcampaign-pro')
        ]);
    }

    public function handleAjaxUpdateCart()
    {
        $nonce = Arr::get($_REQUEST, '_nonce');
        if (!wp_verify_nonce($nonce, 'fc_ab_cart_nonce')) {
            wp_send_json([
                'message' => 'Security failed. Invalid Nonce'
            ], 403);
        }

        if (isset($_COOKIE['fc_ab_cart_skip_track']) && $_COOKIE['fc_ab_cart_skip_track'] == 'yes') {
            $record = $this->getCurrentRecord();
            if ($record) {
                $record->status = 'opt_out';
                $record->save();
            }

            wp_send_json([
                'skipped' => 'yes',
                'reason'  => 'opt_out'
            ]);
        }

        $validKeys = [
            'billing_email',
            'billingAddress',
            'shippingAddress',
            'differentShipping',
            'order_comments'
        ];

        $cartData = Arr::only($_REQUEST, $validKeys);

        $sanitizers = [
            'order_comments' => 'sanitize_textarea_field',
            'billing_email'  => 'sanitize_email'
        ];

        if (!empty($cartData['order_comments'])) {
            $cartData['order_comments'] = wp_unslash($cartData['order_comments']);
        }

        foreach ($cartData as $key => $value) {
            if (isset($sanitizers[$key])) {
                $cartData[$key] = call_user_func($sanitizers[$key], $value);
            } else {
                if (is_array($value)) {
                    $cartData[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $cartData[$key] = sanitize_text_field($value);
                }
            }
        }

        $billingEmail = Arr::get($cartData, 'billing_email');
        unset($cartData['billing_email']);
        $cart = WC()->cart;

        $feesTotal = $cart->get_fee_total();
        if ($feesTotal) {
            $cart->calculate_fees();
        }

        $cartTotal = $cart->get_total(false);

        $record = $this->getCurrentRecord($billingEmail);

        if (!$cartTotal) {
            if ($record) {
                $record->delete();
                setcookie('fc_ab_cart_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }

            wp_send_json([
                'skipped' => 'yes',
                'reason'  => 'empty_cart'
            ]);
        }

        $products = $cart->get_cart_contents();
        foreach ($products as &$product) {
            $cuttentProduct = wc_get_product($product['product_id']);

            $product['title'] = $cuttentProduct->get_title();
        }
        $taxTotal = wc_round_tax_total($cart->get_cart_contents_tax() + $cart->get_shipping_tax() + $cart->get_fee_tax());
        $contact = FluentCrmApi('contacts')->getContact($billingEmail);

        $billing = Arr::get($cartData, 'billingAddress', []);

        $fullName = trim(Arr::get($billing, 'first_name') . ' ' . Arr::get($billing, 'last_name'));

        $data = [
            'email'            => $billingEmail,
            'full_name'        => $fullName,
            'contact_id'       => $contact ? $contact->id : NULL,
            'checkout_page_id' => (int)Arr::get($_REQUEST, '__page_id'),
            'provider'         => 'woo',
            'user_id'          => get_current_user_id(),
            'subtotal'         => $cart->get_cart_contents_total(),
            'shipping'         => $cart->get_shipping_total(),
            'tax'              => $taxTotal,
            'total'            => $cartTotal,
            'currency'         => get_woocommerce_currency(),
            'discounts'        => $cart->get_discount_total(),
            'fees'             => $feesTotal,
            'cart_hash'        => $cart->get_cart_hash(),
            'cart'             => [
                'customer_data' => $cartData,
                'cart_contents' => $products,
                'coupons'       => $cart->get_applied_coupons(),
                'fees'          => $feesTotal ? $cart->get_fees() : [],
            ],
            'order_id'         => absint(WC()->session->get('store_api_draft_order', 0))
        ];

        if (!$record) {
            $data['status'] = 'draft';
            // we have to create a new record
            $record = AbandonCartModel::create($data);
        } else {
            $record->fill($data);
            $record->save();
        }

        $cookieDays = (int)apply_filters('fluent_crm/ab_cart_cookie_validity', 30);
        setcookie('fc_ab_cart_token', $record->checkout_key, time() + (86400 * $cookieDays), COOKIEPATH, COOKIE_DOMAIN);

        wp_send_json([
            'message' => __('Cart data has been updated', 'fluentcampaign-pro'),
            'id'      => $record->id,
            'record'  => $record
        ]);

    }

    private function getCurrentRecord($billingEmail = null)
    {
        // First try from the cookie
        $exitingToken = Arr::get($_COOKIE, 'fc_ab_cart_token');

        if ($exitingToken) {
            $record = AbandonCartModel::where('checkout_key', $exitingToken)
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        // try with billing email then
        if ($billingEmail) {
            $record = AbandonCartModel::where('email', $billingEmail)
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        // if user logged in try with user id
        $userId = get_current_user_id();
        if ($userId) {
            $record = AbandonCartModel::where('user_id', $userId)
                ->whereIn('status', ['pending', 'opt_out', 'draft', 'processing'])
                ->first();

            if ($record) {
                return $record;
            }
        }

        return null;
    }

    public function maybeRestoreCart($data)
    {
        $cartHash = sanitize_text_field(Arr::get($data, 'fc_ab_hash'));

        $abandonCart = null;
        if ($cartHash) {
            $abandonCart = AbandonCartModel::where('checkout_key', $cartHash)
                ->first();
        }

        if (!$abandonCart || $abandonCart->status != 'processing' || $abandonCart->provider != 'woo') {

            do_action('fluentcrm/ab_cart_restore_failed', $abandonCart);

            // check if the cart has any item or not
            $cart = WC()->cart;
            if ($cart->is_empty()) {
                // set a notice
                wc_add_notice(__('Sorry, we could not retrieve your cart', 'fluentcampaign-pro'), 'error');
            }

            // Let's redirect to woocommerce  checkout page
            wp_redirect(wc_get_checkout_url());
            exit();
        }


        // Let's try to restore the cart
        $cookieDays = (int)apply_filters('fluent_crm/ab_cart_cookie_validity', 30);
        setcookie('fc_ab_cart_token', $abandonCart->checkout_key, time() + (86400 * $cookieDays), COOKIEPATH, COOKIE_DOMAIN);

        $abandonCart->click_counts = $abandonCart->click_counts + 1;
        $abandonCart->save();

        // Let's try to restore the cart
        $cart = $abandonCart->cart;

        $cartItems = Arr::get($cart, 'cart_contents', []);
        WC()->cart->empty_cart();
        wc_clear_notices();

        foreach ($cartItems as $itemKey => $cartItem) {
            $variation_data = array();
            $id = $cartItem['product_id'];
            $qty = $cartItem['quantity'];

            // Skip bundled products when added main product.
            if (isset($cartItem['bundled_by'])) {
                continue;
            }

            if (isset($cartItem['variation'])) {
                foreach ($cartItem['variation'] as $key => $value) {
                    $variation_data[$key] = $value;
                }
            }

            $cartItemData = $cartItem;

            WC()->cart->add_to_cart($id, $qty, $cartItem['variation_id'], $variation_data, $cartItemData);
        }

        if ($abandonCart->order_id) {
            WC()->session->set('store_api_draft_order', $abandonCart->order_id);
        }

        // Handle Coupons
        $coupons = Arr::get($cart, 'applied_coupons', []);
        foreach ($coupons as $coupon) {
            if (!WC()->cart->has_discount($coupon)) {
                WC()->cart->add_discount($coupon);
            }
        }

        // Set selected shipping method
        $shippingMethod = Arr::get($cart, 'customer_data.shipping_method', '');
        if ($shippingMethod && !is_null(WC()->session)) {
            WC()->session->set('chosen_shipping_methods', [$shippingMethod]);
        }

        // Remove all the notices
        wc_clear_notices();
        WC()->session->set('fc_ab_wo_restore_id', $abandonCart->id);
        $cartHash = WC()->cart->get_cart_hash();
        $abandonCart->cart_hash = $cartHash;
        $abandonCart->save();

        if ($abandonCart->order_id) {
            $operation = fluentCrmDb()->table('wc_order_operational_data')
                ->where('order_id', $abandonCart->order_id)
                ->first();
            if ($operation) {
                fluentCrmDb()->table('wc_order_operational_data')
                    ->where('order_id', $abandonCart->order_id)
                    ->update([
                        'cart_hash' => $cartHash
                    ]);
            }
        }

        $customerData = [
            'billing_email'  => $abandonCart->email,
            'order_comments' => Arr::get($cart, 'customer_data.order_comments')
        ];

        foreach (Arr::get($cart, 'customer_data.billingAddress', []) as $key => $value) {
            if ($value) {
                $customerData['billing_' . $key] = $value;
            }
        }

        foreach (Arr::get($cart, 'customer_data.shippingAddress', []) as $key => $value) {
            if ($value) {
                $customerData['shipping_' . $key] = $value;
            }
        }

        $customerData = array_filter($customerData);

        WC()->customer->set_props($customerData);

        $checkoutUrl = wc_get_checkout_url();

        if ($abandonCart->checkout_page_id) {
            $checkoutUrl = get_permalink($abandonCart->checkout_page_id);
        }

        wc_add_notice(__('Your cart has been restored', 'fluentcampaign-pro'), 'success');
        wp_redirect($checkoutUrl);
        exit();
    }

    public function maybePrefillCheckoutFields($fields)
    {
        if (!WC()->session || !$cartId = WC()->session->get('fc_ab_wo_restore_id')) {
            return $fields;
        }

        $abandonCart = AbandonCartModel::find($cartId);

        // remove the session
        WC()->session->set('fc_ab_wo_restore_id', null);

        if (!$abandonCart || $abandonCart->provider != 'woo') {
            return $fields;
        }

        $inputs = Arr::get($abandonCart->cart, 'customer_data', []);

        $customerData = [
            'billing'  => array_filter([
                'billing_first_name' => Arr::get($inputs, 'billing_first_name'),
                'billing_last_name'  => Arr::get($inputs, 'billing_last_name'),
                'billing_email'      => $abandonCart->email,
                'billing_company'    => Arr::get($inputs, 'billing_company'),
                'billing_address_1'  => Arr::get($inputs, 'billing_address_1'),
                'billing_address_2'  => Arr::get($inputs, 'billing_address_2'),
                'billing_city'       => Arr::get($inputs, 'billing_city'),
                'billing_state'      => Arr::get($inputs, 'billing_state'),
                'billing_postcode'   => Arr::get($inputs, 'billing_postcode'),
                'billing_country'    => Arr::get($inputs, 'billing_country'),
                'billing_phone'      => Arr::get($inputs, 'billing_phone'),
            ]),
            'shipping' => array_filter([
                'shipping_first_name' => Arr::get($inputs, 'shipping_first_name'),
                'shipping_last_name'  => Arr::get($inputs, 'shipping_last_name'),
                'shipping_company'    => Arr::get($inputs, 'shipping_company'),
                'shipping_address_1'  => Arr::get($inputs, 'shipping_address_1'),
                'shipping_address_2'  => Arr::get($inputs, 'shipping_address_2'),
                'shipping_city'       => Arr::get($inputs, 'shipping_city'),
                'shipping_state'      => Arr::get($inputs, 'shipping_state'),
                'shipping_postcode'   => Arr::get($inputs, 'shipping_postcode'),
                'shipping_country'    => Arr::get($inputs, 'shipping_country'),
            ]),
            'order'    => [
                'order_comments' => Arr::get($inputs, 'order_comments')
            ]
        ];

        if (Arr::get($inputs, 'differentShipping') == 'yes') {
            add_filter('woocommerce_ship_to_different_address_checked', '__return_true');
        } else {
            add_filter('woocommerce_ship_to_different_address_checked', '__return_false');
            unset($customerData['shipping']);
        }

        foreach ($customerData as $key => $items) {
            foreach ($items as $itemKey => $default) {
                if (isset($fields[$key][$itemKey]) && empty($fields[$key][$itemKey]['default'])) {
                    $fields[$key][$itemKey]['default'] = $default;
                }
            }
        }

        return $fields;
    }

    public function handleOrderCreated($order)
    {
        if (!$order instanceof \WC_Order) {
            $order = wc_get_order($order);
        }

        if (!$order instanceof \WC_Order) {
            return false;
        }

        $token = Arr::get($_COOKIE, 'fc_ab_cart_token');
        if (!$token) {
            return false;
        }

        $abCart = AbCartHelper::getAbCartByDataProps([
            'checkout_key' => $token
        ], ['processing', 'draft']);

        // delete the cookie
        setcookie('fc_ab_cart_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);

        if (!$abCart) {
            return false;
        }

        $abCart->email = $order->get_billing_email(false);
        $abCart->order_id = $order->get_id();
        $abCart->save();

        $order->update_meta_data('_fc_ab_cart_id', $abCart->id);
        $order->save();
    }

    public function maybeHandleCartOrderChanged(\WC_Order $order, $abCart = null)
    {
        $status = $order->get_status(false);

        $isSuccessfulOrder = AbCartHelper::isWinOrderStatus($status);

        if ($isSuccessfulOrder) {
            // let's cancel the processing Automations by order
            $this->cancelAutomationsByOrder($order);
        }

        if (!$order->get_meta('_fc_ab_cart_id', true)) {
            return false;
        }

        if (!$isSuccessfulOrder) {
            $lostStatuses = ['refunded', 'failed', 'cancelled'];
            if (in_array($status, $lostStatuses)) {
                $this->handleCartLost($order, $abCart);
            }
            return false;
        }

        if (!$abCart) {
            $abCart = AbandonCartModel::where('order_id', $order->get_id())
                ->provider('woo')
                ->first();
        }

        if (!$abCart || $abCart->status == 'recovered') {
            $order->delete_meta_data('_fc_ab_cart_id');
            $order->save();
            return false;
        }

        $deletableStatuses = ['draft', 'opt_out', 'pending'];
        if (in_array($abCart->status, $deletableStatuses, true)) {
            $abCart->deleteCart();
            $order->delete_meta_data('_fc_ab_cart_id');
            $order->save();
            $this->deleteOtherCarts($abCart, $order);
            return false;
        }

        $recoverableStatuses = ['processing', 'lost', 'cancelled'];
        if (!in_array($abCart->status, $recoverableStatuses, true)) {
            return false; //  not our order to process
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

        $oldStatus = $abCart->status;
        $abCart->status = 'recovered';
        $abCart->order_id = $order->get_id();
        $abCart->total = $order->get_total(false);
        $abCart->recovered_at = current_time('mysql');
        $abCart->save();
        do_action('fluent_crm/ab_cart_woo_recovered', $abCart, $order, $oldStatus);

        $abCartUrl = admin_url('admin.php?page=fluentcrm-admin#/abandon-carts?ab_cart_id=' . $abCart->id);
        $order->add_order_note(sprintf(__('This order has been recovered from an FluentCRM abandoned cart. %s', 'fluentcampaign-pro'), '<a target="_blank" rel="noopener" href="' . $abCartUrl . '">' . __('View data.', 'fluent-campaign-pro') . '</a>'));

        $this->deleteOtherCarts($abCart, $order);

        $this->handleCartRecovered($abCart);

        return true;
    }

    public function handleCartLost($order, $abCart = null)
    {
        if (!$abCart) {
            $abCart = AbandonCartModel::where('order_id', $order->get_id())
                ->provider('woo')
                ->first();
        }

        if (!$abCart || $abCart->status == 'lost') {
            return false;
        }

        $oldStatus = $abCart->status;

        $abCart->status = 'lost';
        $abCart->save();
        do_action('fluent_crm/ab_cart_woo_lost', $abCart, $order, $oldStatus);

        if ($abCart->automation_id) {
            // remove this automation
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
                        $q->where('trigger_name', 'fc_ab_cart_simulation_woo');
                    })
                    ->where('funnel_id', $abCart->automation_id)
                    ->update([
                        'status' => 'cancelled',
                        'notes'  => __('Automatically cancelled because the cart has been lost', 'fluentcampaign-pro')
                    ]);
            }
        }

        return true;
    }

    public function handleCartRecovered($abCart)
    {
        if (!$abCart->automation_id) {
            return false;
        }

        // Cancel All the abandon cart automations for the contact
        $contact = $abCart->subscriber;
        if (!$contact) {
            return false;
        }

        $this->cancelAutomations($contact);
        return true;
    }

    private function cancelAutomations($subscriber)
    {
        FunnelSubscriber::where('subscriber_id', $subscriber->id)
            ->whereHas('funnel', function ($q) {
                $q->where('trigger_name', 'fc_ab_cart_simulation_woo');
            })
            ->where('status', ['active', 'pending', 'paused'])
            ->update([
                'status' => 'cancelled',
                'notes'  => __('Automatically cancelled because a cart has been recovered', 'fluentcampaign-pro')
            ]);
    }

    public function pushContextCodes($codes, $context)
    {
        if ($context != 'fc_ab_cart_simulation_woo') {
            return $codes;
        }

        $smartCodes = [
            'key'        => 'ab_cart_woo',
            'title'      => 'Abandoned Cart - Woo',
            'shortcodes' => [
                '{{ab_cart_woo.billing_email}}'      => 'Cart Billing Email',
                '{{ab_cart_woo.cart_items_table}}'   => 'Cart Items',
                '##ab_cart_woo.recovery_url##'       => 'Cart Recovery URL',
                '{{ab_cart_woo.cart_total}}'         => 'Cart Total',
                '{{ab_cart_woo.subtotal}}'           => 'Cart Subtotal (only products)',
                '{{ab_cart_woo.shipping_total}}'     => 'Cart Shipping Total',
                '{{ab_cart_woo.tax_total}}'          => 'Cart Tax Total',
                '{{ab_cart_woo.billing_full_name}}'  => 'Billing Full Name',
                '{{ab_cart_woo.billing_address}}'    => 'Billing Address',
                '{{ab_cart_woo.shipping_address}}'   => 'Shipping Address',
                '{{ab_cart_woo.currency_symbol}}'    => 'Cart Currency (Symbol)',
                '{{ab_cart_woo.billing_first_name}}' => 'Billing First Name',
                '{{ab_cart_woo.billing_last_name}}'  => 'Billing Last Name',
                '{{ab_cart_woo.billing_company}}'    => 'Billing Company',
                '{{ab_cart_woo.billing_address_1}}'  => 'Billing Address 1',
                '{{ab_cart_woo.billing_address_2}}'  => 'Billing Address 2',
                '{{ab_cart_woo.billing_city}}'       => 'Billing City',
                '{{ab_cart_woo.billing_state}}'      => 'Billing State',
                '{{ab_cart_woo.billing_postcode}}'   => 'Billing Postcode',
                '{{ab_cart_woo.billing_country}}'    => 'Billing Country',
                '{{ab_cart_woo.billing_phone}}'      => 'Billing Phone',
                '{{ab_cart_woo.shipping_address_1}}' => 'Shipping Address 1',
                '{{ab_cart_woo.shipping_address_2}}' => 'Shipping Address 2',
                '{{ab_cart_woo.shipping_city}}'      => 'Shipping City',
                '{{ab_cart_woo.shipping_state}}'     => 'Shipping State',
                '{{ab_cart_woo.shipping_postcode}}'  => 'Shipping Postcode',
                '{{ab_cart_woo.shipping_country}}'   => 'Shipping Country'
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
                ->whereIn('status', ['processing', 'opt_out', 'lost'])
                ->orderBy('id', 'DESC')
                ->first();
        }

        if (!$abCart && defined('FLUENTCRM_PREVIEWING_EMAIL')) {
            $abCart = AbandonCartModel::orderBy('id', 'DESC')
                ->first();
        }

        if (!$abCart) {
            if (defined('FLUENTCRM_PREVIEWING_EMAIL')) {
                return 'Dynamic Text will be available on real email';
            }

            return $defaultValue;
        }

        // We have the cart now. Let's parse the values
        switch ($valueKey) {
            case 'billing_email':
                return $abCart->email;
            case 'cart_total':
                return wc_price($abCart->total);
            case 'subtotal':
                return wc_price($abCart->subtotal);
            case 'shipping_total':
                return $abCart->shipping ? wc_price($abCart->shipping, ['currency' => $abCart->currency]) : $defaultValue;
            case 'tax_total':
                return $abCart->tax ? wc_price($abCart->tax, ['currency' => $abCart->currency]) : $defaultValue;
            case 'billing_full_name':
                return $abCart->full_name ?: $defaultValue;
            case 'billing_address':
                return implode(', ', $abCart->getAddress('billing'));
            case 'shipping_address':
                return implode(', ', $abCart->getAddress('shipping'));
            case 'currency_symbol':
                return get_woocommerce_currency_symbol($abCart->currency);
            case 'billing_first_name':
            case 'billing_last_name':
            case 'billing_company':
            case 'billing_address_1':
            case 'billing_address_2':
            case 'billing_city':
            case 'billing_state':
            case 'billing_postcode':
            case 'billing_country':
            case 'billing_phone':
                $key = str_replace('billing_', '', $valueKey);
                return $abCart->getAddressProp($key, 'billingAddress', $defaultValue);
            case 'shipping_company':
            case 'shipping_address_1':
            case 'shipping_address_2':
            case 'shipping_city':
            case 'shipping_state':
            case 'shipping_postcode':
            case 'shipping_country':
            case 'shipping_phone':
                $key = str_replace('shipping_', '', $valueKey);
                $value = $abCart->getAddressProp($key, 'shippingAddress', $defaultValue);
                if (!$value) {
                    $value = $abCart->getAddressProp($key, 'billingAddress', $defaultValue);
                }
                return $value;
            case 'recovery_url':
                return add_query_arg([
                    'fluentcrm'  => 1,
                    'route'      => 'general',
                    'handler'    => 'fc_cart_woo',
                    'fc_ab_hash' => $abCart->checkout_key
                ], home_url());
            case 'cart_items_table':
                return $abCart->getCartItemsHtml();
            default:
                return apply_filters('fluent_crm/ab_cart_smart_code_default_value', $defaultValue, $valueKey, $abCart);
        }
    }

    private function deleteOtherCarts($abCart, $wooOrder)
    {
        $otherCarts = AbandonCartModel::where(function ($q) use ($abCart, $wooOrder) {
            $q->where('email', $abCart->email);
            $orderUserId = $wooOrder->get_customer_id();
            if ($orderUserId) {
                $q->orWhere('user_id', $orderUserId);
            }
        })
            ->where('id', '!=', $abCart->id)
            ->whereIn('status', ['processing', 'draft'])
            ->get();

        foreach ($otherCarts as $cart) {
            $cart->deleteCart();
        }
    }

    private function cancelAutomationsByOrder(\WC_Order $order)
    {
        $billingEmail = $order->get_billing_email(false);
        $userId = $order->get_user_id(false);

        $subscriberIds = Subscriber::select(['id'])
            ->where('email', $billingEmail)
            ->when($userId, function ($q) use ($userId) {
                return $q->orWhere('user_id', $userId);
            })
            ->pluck('id')
            ->toArray();

        if (!$subscriberIds) {
            return;
        }

        FunnelSubscriber::whereIn('subscriber_id', $subscriberIds)
            ->whereHas('funnel', function ($q) {
                $q->where('trigger_name', 'fc_ab_cart_simulation_woo');
            })
            ->where('status', ['active', 'pending', 'paused'])
            ->update([
                'status' => 'cancelled',
                'notes'  => __('Automatically cancelled because a cart has been recovered', 'fluentcampaign-pro')
            ]);
    }
}
