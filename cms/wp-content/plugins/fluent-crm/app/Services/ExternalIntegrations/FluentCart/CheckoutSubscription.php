<?php

namespace FluentCrm\App\Services\ExternalIntegrations\FluentCart;

use FluentCart\App\Helpers\AddressHelper;
use FluentCrm\App\Services\AutoSubscribe;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Framework\Support\Arr;

/**
 * Adds a newsletter opt-in checkbox to the FluentCart checkout form and
 * subscribes the customer to FluentCRM when the order is paid.
 *
 * Controlled by the 'FluentCart Checkout Subscription Field' panel in
 * FluentCRM -> Settings -> General Settings (option key:
 * fluent_cart_checkout_form_subscribe_settings, defined in the base plugin's
 * AutoSubscribe service).
 */
class CheckoutSubscription
{
    /**
     * Form field name posted with the checkout request and the
     * order meta key holding the captured opt-in state.
     */
    const OPTIN_FIELD = '_fc_cart_checkout_subscribe';

    /**
     * Order meta flag preventing the same order from being processed twice.
     */
    const PROCESSED_META = '_fc_cart_checkout_optin_processed';

    public function init()
    {
        // Renders inside the checkout <form> on both the standard checkout
        // page and the modal checkout (FormData picks the value up on submit)
        add_action('fluent_cart/before_payment_methods', [$this, 'renderOptinCheckbox']);

        // Fires while the order is being created from the checkout request —
        // the only point where the raw POST data is available
        add_action('fluent_cart/checkout/prepare_other_data', [$this, 'captureOptinState']);

        // Async (Action Scheduler) hook recommended by FluentCart for
        // third-party post-payment processing
        add_action('fluent_cart/order_paid_done', [$this, 'maybeSubscribeContact'], 20);
    }

    protected function getSettings()
    {
        return (new AutoSubscribe())->getFluentCartCheckoutSettings();
    }

    /**
     * Prints the opt-in checkbox above the payment methods section,
     * using FluentCart's native checkbox markup classes.
     */
    public function renderOptinCheckbox($data)
    {
        $settings = $this->getSettings();

        if (Arr::get($settings, 'status') != 'yes') {
            return;
        }

        if (Arr::get($settings, 'show_only_new') == 'yes') {
            $contact = fluentcrm_get_current_contact();
            if ($contact && $contact->status == 'subscribed') {
                return;
            }
        }

        $label = Arr::get($settings, 'checkbox_label');
        if (!$label) {
            $label = __('Sign me up for the newsletter!', 'fluent-crm');
        }

        $isChecked = Arr::get($settings, 'auto_checked') == 'yes';
        ?>
        <div class="fct_checkout_form_section fcrm_checkout_subscribe" data-fct-checkout-form-section>
            <div class="fct_form_section_body">
                <label class="fct_input_label fct_input_label_checkbox" for="fcrm_cart_subscribe">
                    <input type="checkbox" class="fct-input fct-input-checkbox" id="fcrm_cart_subscribe"
                           name="<?php echo esc_attr(self::OPTIN_FIELD); ?>" value="1" <?php checked($isChecked); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * Persists the posted opt-in state to order meta so the async
     * order-paid handler can read it later. Missing value = unchecked.
     */
    public function captureOptinState($eventData)
    {
        $order = Arr::get($eventData, 'order');

        if (!$order || !is_object($order)) {
            return;
        }

        $settings = $this->getSettings();
        if (Arr::get($settings, 'status') != 'yes') {
            return;
        }

        $isChecked = Arr::get((array)Arr::get($eventData, 'request_data', []), self::OPTIN_FIELD) == '1';

        $order->updateMeta(self::OPTIN_FIELD, $isChecked ? '1' : '0');
    }

    /**
     * Creates/updates the FluentCRM contact when a paid order has the
     * opt-in flag. Runs async via fluent_cart/order_paid_done. Idempotent —
     * payment retries or hook re-runs will not duplicate processing.
     */
    public function maybeSubscribeContact($eventData)
    {
        $order = Arr::get($eventData, 'order');
        $customer = Arr::get($eventData, 'customer');

        if (!$order || !$customer) {
            return false;
        }

        // Treat both the final marker and an in-flight claim as processed so
        // serial retries of the paid-order event (e.g. Action Scheduler
        // re-runs) cannot double-process. This read-then-write guard is not
        // atomic, so it does not protect against truly concurrent invocations
        $processedState = $order->getMeta(self::PROCESSED_META);
        if ($processedState == 'yes' || $processedState == 'processing') {
            return false;
        }

        if ($order->getMeta(self::OPTIN_FIELD) != '1') {
            return false;
        }

        $settings = $this->getSettings();
        if (Arr::get($settings, 'status') != 'yes') {
            return false;
        }

        $email = sanitize_email($customer->email);
        if (!$email || !is_email($email)) {
            return false;
        }

        $orderBillingAddress = $order->billing_address;

        $address1 = Arr::get($orderBillingAddress, 'address_1', '');
        $address2 = Arr::get($orderBillingAddress, 'address_2', '');

        $state = AddressHelper::getStateNameByCode($customer->state, $customer->country);

        $subscriberData = [
            'first_name'     => sanitize_text_field($customer->first_name),
            'last_name'      => sanitize_text_field($customer->last_name),
            'country'        => sanitize_text_field($customer->country),
            'state'          => sanitize_text_field($state),
            'city'           => sanitize_text_field($customer->city),
            'postal_code'    => sanitize_text_field($customer->postcode),
            'address_line_1' => sanitize_text_field($address1),
            'address_line_2' => sanitize_text_field($address2),
            'email'          => $email
        ];

        if ($listId = Arr::get($settings, 'target_list')) {
            $subscriberData['lists'] = [$listId];
        }

        if ($tags = Arr::get($settings, 'target_tags')) {
            $subscriberData['tags'] = $tags;
        }

        if (Arr::get($settings, 'double_optin') == 'yes') {
            $subscriberData['status'] = 'pending';
        } else {
            $subscriberData['status'] = 'subscribed';
        }

        $subscriberData = apply_filters('fluent_crm/fluent_cart_checkout_auto_subscribe_data', $subscriberData, $order);

        // Claim the order before contact creation / email side effects so an
        // Action Scheduler retry re-entering this handler cannot run them twice
        $order->updateMeta(self::PROCESSED_META, 'processing');

        try {
            $contact = FunnelHelper::createOrUpdateContact($subscriberData);

            if (!$contact) {
                // Release the claim so a later retry can attempt processing again
                $order->updateMeta(self::PROCESSED_META, '0');
                return false;
            }

            if ($contact->status == 'pending') {
                $contact->sendDoubleOptinEmail();
            }
        } catch (\Throwable $e) {
            // Release the claim on failure too — otherwise the order would be
            // stuck in 'processing' and permanently skipped by the guard above
            $order->updateMeta(self::PROCESSED_META, '0');
            throw $e;
        }

        $order->updateMeta(self::PROCESSED_META, 'yes');

        return true;
    }
}
