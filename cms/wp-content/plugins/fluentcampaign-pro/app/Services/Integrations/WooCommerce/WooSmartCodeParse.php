<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

use FluentCampaign\App\Services\Commerce\Commerce;
use FluentCampaign\App\Services\Commerce\ContactRelationItemsModel;
use FluentCampaign\App\Services\Commerce\ContactRelationModel;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\Framework\Support\Arr;

class WooSmartCodeParse
{
    public function init()
    {
        add_filter('fluent_crm/smartcode_group_callback_woo_customer', array($this, 'parseCustomer'), 10, 4);
        add_filter('fluent_crm/smartcode_group_callback_woo_order', array($this, 'parseCurrentOrder'), 10, 4);
        add_filter('fluent_crm/smartcode_group_callback_woo_last_order', array($this, 'parseLastOrder'), 10, 4);
        add_filter('fluent_crm/smartcode_group_callback_woo_coupon', array($this, 'parseDynamicCoupon'), 10, 4);
        add_filter('fluent_crm/smartcode_group_callback_woo_subscription', array($this, 'parseCurrentSubscription'), 10, 4);

        add_filter('fluent_crm/extended_smart_codes', array($this, 'pushGeneralCodes'));
        add_filter('fluent_crm_funnel_context_smart_codes', array($this, 'pushContextCodes'), 10, 2);

        /*
         * Create dynamic coupon on the fly
         */

        add_filter('woocommerce_get_shop_coupon_data', [$this, 'maybeCreateDynamicCoupon'], 999, 3);

    }

    public function parseCustomer($code, $valueKey, $defaultValue, $subscriber)
    {
        $userId = $subscriber->getWpUserId();

        if ($userId) {
            $customer = new \WC_Customer($userId);
            if ($customer->get_id()) {
                switch ($valueKey) {
                    case 'total_order_count':
                        return $customer->get_order_count();
                    case 'billing_address':
                        return $customer->get_billing_address();
                    case 'shipping_address':
                        return $customer->get_shipping_address();
                    case 'total_spent':
                        return wc_price($customer->get_total_spent());
                    case 'country':
                        return $customer->get_country();
                    case 'city':
                        return $customer->get_city();
                    case 'postal_code':
                        return $customer->get_postcode();
                }
            }
        }

        if (!Commerce::isEnabled('woo')) {
            return $defaultValue;
        }

        $commerce = ContactRelationModel::provider('woo')->where('subscriber_id', $subscriber->id)->first();

        if (!$commerce) {
            return $defaultValue;
        }

        switch ($valueKey) {
            case 'first_order_date':
            case 'last_order_date':
                return date_i18n(get_option('date_format'), strtotime($commerce->{$valueKey}));
            case 'total_order_count':
                return $commerce->total_order_count;
            case 'total_spent':
                return wc_price($commerce->total_order_value);
        }

        return $defaultValue;
    }

    public function parseLastOrder($code, $valueKey, $defaultValue, $subscriber)
    {
        $userId = $subscriber->getWpUserId();
        $lastOrder = false;
        if ($userId) {
            $customer = new \WC_Customer($userId);
            $lastOrder = $customer->get_last_order();
            if (!$lastOrder) {
                return $defaultValue;
            }
        } else if (Commerce::isEnabled('woo')) {
            $lastItem = ContactRelationItemsModel::provider('woo')
                ->where('subscriber_id', $subscriber->id)
                ->orderBy('origin_id', 'DESC')
                ->first();
            if ($lastItem && $lastItem->origin_id) {
                try {
                    $lastOrder = new \WC_Order($lastItem->origin_id);
                } catch (\Exception $exception) {
                    return $defaultValue;
                }
            } else {
                return $defaultValue;
            }
        } else {
            return $defaultValue;
        }

        return $this->parseOrderProps($lastOrder, $valueKey, $defaultValue);
    }

    public function parseCurrentOrder($code, $valueKey, $defaultValue, $subscriber)
    {
        if (empty($subscriber->funnel_subscriber_id)) {
            return $this->parseLastOrder($code, $valueKey, $defaultValue, $subscriber);
        }

        $funnelSub = FunnelSubscriber::where('id', $subscriber->funnel_subscriber_id)->first();

        if (!$funnelSub || !$funnelSub->source_ref_id || !Helper::isWooTrigger($funnelSub->source_trigger_name)) {
            return $this->parseLastOrder($code, $valueKey, $defaultValue, $subscriber);
        }

        try {
            $order = new \WC_Order($funnelSub->source_ref_id);
        } catch (\Exception $exception) {
            return $defaultValue;
        }

        if (!$order || !$order->get_id()) {
            return $defaultValue;
        }

        return $this->parseOrderProps($order, $valueKey, $defaultValue);
    }

    public function parseLastSubscription($code, $valueKey, $defaultValue, $subscriber)
    {
        $lastSubscription = false;
        $userId = $subscriber->getWpUserId();

        // Try to get subscription by user ID if exists
        if ($userId) {
            $subscriptions = wcs_get_subscriptions([
                'customer_id' => $userId,
                'orderby'     => 'date_created',
                'order'       => 'DESC',
                'limit'       => 1
            ]);
            $lastSubscription = !empty($subscriptions) ? reset($subscriptions) : false;
        }

        // If no subscription found and commerce is enabled, try getting from commerce data
        if (!$lastSubscription && Commerce::isEnabled('woo')) {
            $lastItem = ContactRelationItemsModel::provider('woo')
                ->where('subscriber_id', $subscriber->id)
                ->orderBy('origin_id', 'DESC')
                ->first();

            if ($lastItem && $lastItem->origin_id) {
                try {
                    $lastSubscription = wcs_get_subscription($lastItem->origin_id);
                } catch (\Exception $exception) {
                    $lastSubscription = false;
                }
            }
        }

        return $lastSubscription ? $this->parseSubscriptionProps($lastSubscription, $valueKey, $defaultValue) : $defaultValue;
    }

    public function parseCurrentSubscription($code, $valueKey, $defaultValue, $subscriber)
    {
        if (empty($subscriber->funnel_subscriber_id)) {
            return $this->parseLastSubscription($code, $valueKey, $defaultValue, $subscriber);
        }

        $funnelSub = FunnelSubscriber::where('id', $subscriber->funnel_subscriber_id)->first();

        if (!$funnelSub || !$funnelSub->source_ref_id || !Helper::isWooTrigger($funnelSub->source_trigger_name)) {
            return $this->parseLastSubscription($code, $valueKey, $defaultValue, $subscriber);
        }

        try {
            $subscription = wcs_get_subscription($funnelSub->source_ref_id);
        } catch (\Exception $exception) {
            return $defaultValue;
        }

        if (!$subscription || !$subscription->get_ID()) {
            return $defaultValue;
        }

        return $this->parseSubscriptionProps($subscription, $valueKey, $defaultValue);
    }

    public function pushGeneralCodes($codes)
    {
        $codes['woo_customer'] = [
            'key'        => 'woo_customer',
            'title'      => 'Woo Customer',
            'shortcodes' => $this->getSmartCodes()
        ];

        return $codes;
    }

    public function pushContextCodes($codes, $context)
    {
        if (!Helper::isWooTrigger($context)) {
            return $codes;
        }

        $codes[] = [
            'key'        => 'woo_order',
            'title'      => 'Current Order - WC',
            'shortcodes' => $this->getSmartCodes('order')
        ];

        if (class_exists('WC_Subscriptions') && Helper::isWooSubscriptionTrigger($context)) {
            $codes[] = [
                'key'        => 'woo_subscription',
                'title'      => __('Current Subscription - WC', 'fluentcampaign-pro'),
                'shortcodes' => $this->getSmartCodes('subscription')
            ];
        }

        return $codes;
    }

    /**
     * @param $order \WC_Order
     * @param $valueKey string
     * @param $defaultValue string
     * @return string
     */
    protected function parseOrderProps($order, $valueKey, $defaultValue = '')
    {
        if (!$order || !$order->get_id()) {
            return $defaultValue;
        }

        switch ($valueKey) {
            case 'shipping_address':
                return $order->get_formatted_shipping_address($defaultValue);
            case 'billing_address':
                return $order->get_formatted_billing_address($defaultValue);
            case 'order_number':
                return $order->get_order_number();
            case 'order_note':
                return $order->get_customer_note();
            case 'order_id':
                return $order->get_id();
            case 'status':
                return wc_get_order_status_name($order->get_status());
            case 'currency':
                return $order->get_currency();
            case 'total_amount':
                return $order->get_formatted_order_total();
            case 'payment_method':
                return $order->get_payment_method_title();
            case 'date':
                return date_i18n(get_option('date_format'), strtotime($order->get_date_created()));
            case 'items_count':
                return $order->get_item_count();
            case 'order_items_table':
                return $this->getOrderDetailsTable($order);
            case 'request_review_table':
                return $this->getOrderLeaveReviewsTable($order);
            case 'view_url':
                if ($order->get_customer_id()) {
                    return $order->get_view_order_url();
                }
                return $order->get_checkout_order_received_url();
        }

        return $defaultValue;
    }

    protected function parseSubscriptionProps($subscription, $valueKey, $defaultValue = '')
    {
        if (!$subscription || !$subscription->get_ID()) {
            return $defaultValue;
        }

        switch ($valueKey) {
            case 'status':
                return $subscription->get_status();
            case 'total_amount':
                return $subscription->get_total();
            case 'start_date':
                return date_i18n(get_option('date_format'), strtotime($subscription->get_date_created()));
            case 'end_date':
                $endDate = 'Lifetime';
                if ($subscription->get_date('end') != '0' && !empty($subscription->get_date('end'))) {
                    $endDate = date_i18n(get_option('date_format'), strtotime($subscription->get_date('end')));
                }
                return $endDate;
            case 'trial_end':
                return date_i18n(get_option('date_format'), strtotime($subscription->get_date('trial_end')));
            case 'next_payment':
                return date_i18n(get_option('date_format'), strtotime($subscription->get_date('next_payment')));
            case 'billing_period':
                return $subscription->get_billing_period();
            case 'billing_interval':
                return $subscription->get_billing_interval();

        }

        return $defaultValue;
    }

    private function getSmartCodes($context = '')
    {
        if (!$context) {
            $generalCodes = [
                '{{woo_customer.total_order_count}}' => __('Total Order Count', 'fluentcampaign-pro'),
                '{{woo_customer.billing_address}}'   => __('Billing Address', 'fluentcampaign-pro'),
                '{{woo_customer.shipping_address}}'  => __('Shipping Address', 'fluentcampaign-pro'),
                '{{woo_customer.total_spent}}'       => __('Total Spent', 'fluentcampaign-pro'),
                '{{woo_customer.country}}'           => __('Billing Country', 'fluentcampaign-pro'),
                '{{woo_customer.city}}'              => __('Billing City', 'fluentcampaign-pro'),
                '{{woo_customer.postal_code}}'       => __('Billing Postal Code', 'fluentcampaign-pro'),
            ];

            if (Commerce::isEnabled('woo')) {
                $generalCodes['{{woo_customer.first_order_date}}'] = __('First Order Date', 'fluentcampaign-pro');
                $generalCodes['{{woo_customer.last_order_date}}'] = __('Last Order Date', 'fluentcampaign-pro');
            }

            return $generalCodes;
        }

        if ($context == 'order') {
            return [
                '{{woo_order.shipping_address}}'     => __('Shipping Address', 'fluentcampaign-pro'),
                '{{woo_order.billing_address}}'      => __('Billing Address', 'fluentcampaign-pro'),
                '{{woo_order.order_number}}'         => __('Order Number', 'fluentcampaign-pro'),
                '{{woo_order.order_note}}'           => __('Customer Order Note', 'fluentcampaign-pro'),
                '{{woo_order.order_id}}'             => __('Customer Order ID', 'fluentcampaign-pro'),
                '{{woo_order.status}}'               => __('Status', 'fluentcampaign-pro'),
                '{{woo_order.currency}}'             => __('Currency', 'fluentcampaign-pro'),
                '{{woo_order.total_amount}}'         => __('Total Amount', 'fluentcampaign-pro'),
                '{{woo_order.payment_method}}'       => __('Payment Method', 'fluentcampaign-pro'),
                '{{woo_order.date}}'                 => __('Order Date', 'fluentcampaign-pro'),
                '{{woo_order.items_count}}'          => __('Items Count', 'fluentcampaign-pro'),
                '{{woo_order.order_items_table}}'    => __('Ordered Items (table)', 'fluentcampaign-pro'),
                '{{woo_order.request_review_table}}' => __('Request Review (table)', 'fluentcampaign-pro'),
                '##woo_order.view_url##'             => __('order View URL', 'fluentcampaign-pro'),
            ];
        }

        if ($context == 'subscription') {
            return  [
                '{{woo_subscription.status}}'           => __('Status', 'fluentcampaign-pro'),
                '{{woo_subscription.total_amount}}'     => __('Total Amount', 'fluentcampaign-pro'),
                '{{woo_subscription.start_date}}'       => __('Start Date', 'fluentcampaign-pro'),
                '{{woo_subscription.end_date}}'         => __('End Date', 'fluentcampaign-pro'),
                '{{woo_subscription.trial_end}}'        => __('Trial End Date', 'fluentcampaign-pro'),
                '{{woo_subscription.next_payment}}'     => __('Next Payment Date', 'fluentcampaign-pro'),
                '{{woo_subscription.billing_period}}'   => __('Billing Period', 'fluentcampaign-pro'),
                '{{woo_subscription.billing_interval}}' => __('Billing Interval', 'fluentcampaign-pro'),
            ];
        }

        return [];
    }

    /**
     * @param $order \WC_Order
     * @return false|string
     */
    private function getOrderDetailsTable($order)
    {
        $order_items = $order->get_items(apply_filters('woocommerce_purchase_order_item_types', 'line_item'));
        ob_start();
        ?>
        <div class="fc-wp-woo-block-table wp-block-table">
            <table class="fc-woo-order-table woo_order_table">
                <thead>
                <tr>
                    <th colspan="2" style="text-align: left; width: 70%;"><?php esc_html_e('Product', 'fluentcampaign-pro'); ?></th>
                    <th style="text-align: left; width: 30%;"><?php esc_html_e('Total', 'fluentcampaign-pro'); ?></th>
                </tr>
                </thead>

                <tbody>
                <?php
                foreach ($order_items as $item_id => $item) {
                    $product = $item->get_product();
                    ?>
                    <tr>
                        <td style="text-align: left; padding: 5px 10px; border: 1px solid #5f5f5f; width: 20%;">
                            <div>
                                <?php
                                    echo wp_kses_post($this->getProductPhoto($product, 'shop_catalog', false, 60)); //phpcs:ignore WordPress.Security.EscapeOutput
                                ?>
                            </div>
                        </td>
                        <td style="text-align: left; padding: 5px 10px; border: 1px solid #5f5f5f; width: 50%;">
                            <div>
                            <?php
                            $is_visible = $product && $product->is_visible();
                            $product_permalink = apply_filters('woocommerce_order_item_permalink', $is_visible ? $product->get_permalink($item) : '', $item, $order);

                            echo wp_kses_post(apply_filters('woocommerce_order_item_name', $product_permalink ? sprintf('<a href="%s">%s</a>', $product_permalink, $item->get_name()) : $item->get_name(), $item, $is_visible));

                            ?> <br/>
                            <?php
                            $qty = $item->get_quantity();
                            $refunded_qty = $order->get_qty_refunded_for_item($item_id);

                            if ($refunded_qty) {
                                $qty_display = '<del>' . esc_html($qty) . '</del> <ins>' . esc_html($qty - ($refunded_qty * -1)) . '</ins>';
                            } else {
                                $qty_display = esc_html($qty);
                            }
                            echo apply_filters('woocommerce_order_item_quantity_html', ' <strong class="product-quantity">' . sprintf('&times;&nbsp;%s', $qty_display) . '</strong>', $item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            wc_display_item_meta($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

                            ?>
                            </div>
                        </td>
                        <td style="text-align: left; border: 1px solid #5f5f5f;">
                            <?php echo $order->get_formatted_line_subtotal($item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>

                <tfoot>
                <?php
                foreach ($order->get_order_item_totals() as $key => $total) {
                    ?>
                    <tr>
                        <th style="text-align: right;border: 1px solid #5f5f5f"><?php echo esc_html($total['label']); ?></th>
                        <td style="text-align: left;border: 1px solid #5f5f5f;"><?php echo ('payment_method' === $key) ? esc_html($total['value']) : wp_kses_post($total['value']); ?></td>
                    </tr>
                    <?php
                }
                ?>
                </tfoot>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function getOrderLeaveReviewsTable($order)
    {
        $order_items = $order->get_items(apply_filters('woocommerce_purchase_order_item_types', 'line_item'));
        $hash = apply_filters('fluent_crm/woo_product_review_hash', '#tab-reviews');

        $base = apply_filters('fluencrm_review_btn_bg_color', get_option('woocommerce_email_base_color'));
        $base_text = apply_filters('fluencrm_review_btn_color', wc_light_or_dark($base, '#202020', '#ffffff'));

        ob_start();
        ?>
        <div class="wp-block-table">
            <table class="woo_order_table">
                <tbody>
                <?php
                foreach ($order_items as $item_id => $item) {
                    $product = $item->get_product(); // WC_Product
                    if ($product) {
                        ?>
                        <tr>
                            <td width="100">
                                <?php echo wp_kses_post($this->getProductPhoto($product, 'shop_catalog', false, 100)); //phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </td>
                            <td width="">
                                <p style="vertical-align:middle;"><?php echo $product->get_name(); ?></p>
                            </td>
                            <td align="center" width="170">
                                <div style="padding: 0px 10px;" class="fc_btn">
                                    <a href="<?php echo esc_url_raw($product->get_permalink() . $hash); ?>"
                                       class="fc_review_btn">
                                        <!--[if mso]>
                                        <i style="letter-spacing: 25px;mso-font-width:-100%;mso-text-raise:30pt" hidden>&nbsp;</i>
                                        <![endif]-->
                                        <span
                                            style="mso-text-raise:15pt;"><?php echo apply_filters('fluent_crm/review_button_text', esc_html__('Leave a review', 'fluentcampaign-pro')); ?></span>
                                        <!--[if mso]>
                                        <i style="letter-spacing: 25px;mso-font-width:-100%" hidden>&nbsp;</i>
                                        <![endif]-->
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php }
                } ?>
                </tbody>
            </table>
            <style>
                .fc_review_btn {
                    background-color: <?php echo esc_attr( $base ); ?>;
                    color: <?php echo esc_attr( $base_text ); ?>;
                }
            </style>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * @param $product \WC_Product
     * @param $size
     * @param $url
     * @param $with
     * @return false|string
     */
    private function getProductPhoto($product, $size = 'shop_catalog', $url = false, $with = '')
    {
        $image_id = $product->get_image_id();

        $image_url = '';
        if (!empty($image_id)) {
            $image_url = wp_get_attachment_image_url($image_id, $size);
        }

        if (empty($image_url) && function_exists('wc_placeholder_img_src')) {
            $image_url = wc_placeholder_img_src($size);
        }

        if ($url) {
            $image = $image_url;
        } else {
            $width = !empty($with) ? "width='{$with}'" : '';
            $height = !empty($with) ? "height='{$with}'" : '';
            $image = '<img src="' . $image_url . '" ' . $width . ' ' . $height . ' alt="' . sanitize_text_field($product->get_name()) . '" />';
        }



        return $image;
    }

    public function maybeCreateDynamicCoupon($result, $data, $couponClass)
    {
        if ($result || !$data || !is_string($data) || $couponClass->get_id()) {
            return $result;
        }

        $parts = explode('-', $data);

        if (count($parts) < 2) {
            return $result;
        }

        // get the last part
        $part = (string)array_pop($parts);

        if (strlen($part) !== 6) {
            return $result;
        }

        // check if the last char is numeric or not
        if (!is_numeric(substr($part, -1))) {
            return $result;
        }

        $dbCoupon = fluentCrmDb()->table('posts')
            ->where('post_type', 'shop_coupon')
            ->where('post_title', $data)
            ->first();

        if ($dbCoupon) {
            return $result; // coupon has been created already
        }

        $funnelMetric = FunnelMetric::where('notes', $data)->first();

        if (!$funnelMetric) {
            return $result;
        }

        // We have to create the coupon now!

        $sequence = $funnelMetric->sequence;
        $subscriber = $funnelMetric->subscriber;

        if (!$sequence || $sequence->action_name != 'fcrm_create_woo_coupon' || !$subscriber) {
            return $result;
        }

        $couponData = $this->getFormattedCouponData(Arr::get($sequence->settings, 'code_settings', []));

        if (!$couponData) {
            return $result;
        }

        $createdCoupon = new \WC_Coupon();
        $createdCoupon->set_code($data);
        $createdCoupon->set_description(sprintf('Created dynamically from FluentCRM Automation Funnel: %s', $funnelMetric->funnel->title));
        $createdCoupon->set_discount_type(Arr::get($couponData, 'discount_type', 'percent'));
        $createdCoupon->set_amount(Arr::get($couponData, 'amount', '0'));

        if (Arr::get($couponData, 'free_shipping') == 'yes') {
            $createdCoupon->set_free_shipping(true);
        }

        $expType = Arr::get($couponData, 'expiry_type');
        if ($expType != 'never') {

            if ($expType == 'fixed') {
                $createdCoupon->set_date_expires(Arr::get($couponData, 'date_expires'));
            } else {
                $createdCoupon->set_date_expires(gmdate('Y-m-d', strtotime('+' . Arr::get($couponData, 'expiry_days') . ' days')));
            }
        }

        if ($minAmount = Arr::get($couponData, 'minimum_amount')) {
            $createdCoupon->set_minimum_amount($minAmount);
        }

        if ($maxAmount = Arr::get($couponData, 'maximum_amount')) {
            $createdCoupon->set_maximum_amount($maxAmount);
        }

        if ($productCats = Arr::get($couponData, 'product_categories')) {
            $createdCoupon->set_product_categories($productCats);
        }

        if ($excludeProductCats = Arr::get($couponData, 'exclude_product_categories')) {
            $createdCoupon->set_excluded_product_categories($excludeProductCats);
        }

        if ($productIds = Arr::get($couponData, 'product_ids')) {
            $createdCoupon->set_product_ids($productIds);
        }

        if ($excludeProductIds = Arr::get($couponData, 'exclude_product_ids')) {
            $createdCoupon->set_excluded_product_ids($excludeProductIds);
        }

        if ($usageLimit = Arr::get($couponData, 'usage_limit')) {
            $createdCoupon->set_usage_limit($usageLimit);
        }

        if ($usageLimitPerUser = Arr::get($couponData, 'usage_limit_per_user')) {
            $createdCoupon->set_usage_limit_per_user($usageLimitPerUser);
        }

        if ($limitUsageToXItems = Arr::get($couponData, 'limit_usage_to_x_items')) {
            $createdCoupon->set_limit_usage_to_x_items($limitUsageToXItems);
        }

        if (Arr::get($couponData, 'individual_use') === 'yes') {
            $createdCoupon->set_individual_use(true);
        }

        if (Arr::get($couponData, 'exclude_sale_items') === 'yes') {
            $createdCoupon->set_exclude_sale_items(true);
        }

        if (Arr::get($couponData, 'contact_email_only') === 'yes') {
            $createdCoupon->set_email_restrictions([$subscriber->email]);
        }

        $createdCoupon->set_amount(Arr::get($couponData, 'amount', '0'));


        $createdCoupon->save();
        $couponPostId = $createdCoupon->get_id();

        if ($couponPostId) {
            $createdCoupon->add_meta_data('_fc_coupon', $funnelMetric->id, true);

            // Load other meta data from base coupon
            $otherData = $this->loadOtherData($couponData);
            if (!empty($otherData)) {
                foreach ($otherData as $index => $meta_value) {
                    if (!metadata_exists( 'post', $couponPostId, $index )) {
                        $createdCoupon->add_meta_data($index, $meta_value, true);
                    }
                }
            }

            /**
             * Action hook fired after a dynamic coupon has been created.
             *
             * @param \WC_Coupon $createdCoupon The created coupon object
             * @param \FluentCrm\App\Models\FunnelMetric $funnelMetric The funnel metric
             * @param \FluentCrm\App\Models\Subscriber $subscriber The subscriber
             * @param array $couponData The coupon configuration data
             */
            do_action('fluent_crm/woo_dynamic_coupon_created', $createdCoupon, $funnelMetric, $subscriber, $couponData);

            $createdCoupon->save();
        }

        return $result;
    }

    public function parseDynamicCoupon($code, $valueKey, $defaultValue, $subscriber)
    {
        $codeParts = explode('_', $valueKey);
        $codeParts = array_map('trim', $codeParts);
        $codeParts = array_map('intval', $codeParts);
        $codeParts = array_filter($codeParts);

        if (count($codeParts) < 2) {
            return $defaultValue;
        }

        $funnelId = $codeParts[0];
        $sequenceId = $codeParts[1];

        $sequenceMetrix = FunnelMetric::where('funnel_id', $funnelId)
            ->where('sequence_id', $sequenceId)
            ->where('subscriber_id', $subscriber->id)
            ->orderBy('id', 'DESC')
            ->first();

        if (!$sequenceMetrix || !$sequenceMetrix->notes) {
            return $defaultValue;
        }

        return (string)$sequenceMetrix->notes;
    }

    private function getFormattedCouponData($data)
    {
        if (Arr::get($data, 'template_type') == 'new') {
            return $data;
        }

        $existingCoupon = Arr::get($data, 'base_coupon_id');

        if (!$existingCoupon) {
            return false;
        }

        $coupon = new \WC_Coupon($existingCoupon);

        if (!$coupon->get_id()) {
            return false;
        }

        $data['amount'] = $coupon->get_amount();
        $data['discount_type'] = $coupon->get_discount_type();
        $data['product_ids'] = $coupon->get_product_ids();
        $data['exclude_product_ids'] = $coupon->get_excluded_product_ids();
        $data['free_shipping'] = $coupon->get_free_shipping() ? 'yes' : 'no';
        $data['product_categories'] = $coupon->get_product_categories();
        $data['exclude_product_categories'] = $coupon->get_excluded_product_categories();
        $data['minimum_amount'] = $coupon->get_minimum_amount();
        $data['maximum_amount'] = $coupon->get_maximum_amount();

        $expiry_date = $coupon->get_date_expires();

        if ($expiry_date) {
            $data['expiry_type'] = 'fixed';
            $data['date_expires'] = $expiry_date->format('Y-m-d');
        } else {
            $data['expiry_type'] = 'never';
        }

        $data['individual_use'] = $coupon->get_individual_use() ? 'yes' : 'no';
        $data['exclude_sale_items'] = $coupon->get_exclude_sale_items() ? 'yes' : 'no';
        $data['limit_usage_to_x_items'] = $coupon->get_limit_usage_to_x_items();
        $data['usage_limit'] = $coupon->get_usage_limit();
        $data['usage_limit_per_user'] = $coupon->get_usage_limit_per_user();

        return $data;
    }

    private function loadOtherData($data)
    {
        if (Arr::get($data, 'template_type') == 'new') {
            return [];
        }
        $existingCoupon = Arr::get($data, 'base_coupon_id');
        $coupon = new \WC_Coupon($existingCoupon);

        $coupon_id =  $coupon->get_id();
        if (!$coupon_id) {
            return [];
        }

        $otherData = [];
        $keys = array_keys($data);
        $otherKeys = fluentCrmDb()->table('postmeta')
            ->where('post_id', $coupon_id)
            ->whereNotIn('meta_key', $keys)
            ->pluck('meta_key');

        foreach($otherKeys as $otherKey) {
            $value = $coupon->get_meta($otherKey, true);
            if (isset($value)) {
                $otherData[$otherKey] = $value;
            }
        }

        return $otherData;
    }

}
