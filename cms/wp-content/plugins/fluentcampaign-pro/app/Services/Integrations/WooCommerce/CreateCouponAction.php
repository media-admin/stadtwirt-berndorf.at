<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class CreateCouponAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'fcrm_create_woo_coupon';
        $this->priority = 99;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('WooCommerce', 'fluentcampaign-pro'),
            'title'       => __('Create Coupon', 'fluentcampaign-pro'),
            'description' => __('Create WooCommerce Coupon Code', 'fluentcampaign-pro'),
            'icon'        => 'fc-icon-woo',
            'settings'    => [
                'code_settings' => [
                    'base_coupon_id'             => '',
                    'template_type'              => 'new',
                    'smart_code'                 => '',
                    'code_type'                  => 'masked',
                    'code_prefix'                => '',
                    'amount'                     => 0,
                    'discount_type'              => 'percent',
                    'product_ids'                => [],
                    'exclude_product_ids'        => [],
                    'free_shipping'              => 'no',
                    'product_categories'         => [],
                    'exclude_product_categories' => [],
                    'minimum_amount'             => '',
                    'maximum_amount'             => '',
                    'expiry_type'                => 'never', // never | fixed | relative_days
                    'expiry_days'                => '',
                    'date_expires'               => '',
                    'individual_use'             => 'no',
                    'exclude_sale_items'         => 'no',
                    'contact_email_only'         => 'no',
                    'limit_usage_to_x_items'     => '',
                    'usage_limit'                => '',
                    'usage_limit_per_user'       => ''
                ]
            ]
        ];
    }

    public function getBlockFields()
    {

        return [
            'title'     => __('Create Coupon', 'fluentcampaign-pro'),
            'sub_title' => __('Create WooCommerce Coupon Code', 'fluentcampaign-pro'),
            'fields'    => [
                'code_settings' => [
                    'type'  => 'advanced_coupon_settings',
                    'label' => ''
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $settings = $sequence->settings;
        $funnelMetric->notes = $this->getFreshCoupon($settings['code_settings']['code_prefix'], $subscriber);
        $funnelMetric->save();
        return true;
    }

    private function getFreshCoupon($codePrefix, $subscriber = null)
    {
        if (!$codePrefix) {
            // generate a random code
            $codePrefix = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 4)), 0, 4) . mt_rand(0, 9);
        }

        if ($subscriber) {
            $codePrefix = (string )apply_filters('fluent_crm/parse_campaign_email_text', $codePrefix, $subscriber);
        }

        $randomSuffix = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', 5)), 0, 5) . mt_rand(0, 9);
        $code = $codePrefix . '-' . $randomSuffix;

        $exist = fluentCrmDb()->table('posts')
            ->where('post_title', $code)
            ->first();

        if ($exist) {
            return $this->getFreshCoupon($codePrefix, null);
        }

        return $code;
    }
}
