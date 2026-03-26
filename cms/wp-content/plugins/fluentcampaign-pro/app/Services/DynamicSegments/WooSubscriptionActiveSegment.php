<?php
namespace FluentCampaign\App\Services\DynamicSegments;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\Framework\Support\Arr;

class WooSubscriptionActiveSegment extends BaseSegment
{
    private $model = null;

    public $slug = 'wc_active_subscription_customers';

    public function getInfo()
    {
        return [
            'id'          => 0,
            'slug'        => $this->slug,
            'is_system'   => true,
            'title'       => __('WooCommerce Active Subscription Customers', 'fluentcampaign-pro'),
            'subtitle'    => __('WooCommerce Active Subscription Customers who are also in the contact list as subscribed', 'fluentcampaign-pro'),
            'description' => __('This segment contains all your Subscribed contacts which are also your WooCommerce Customers', 'fluentcampaign-pro'),
            'settings'    => []
        ];
    }

    public function getCount()
    {
        return $this->getModel()->count();
    }
    
    public function getModel($segment = [])
    {
        if ($this->model) {
            return $this->model;
        }

        $query = Subscriber::where('status', 'subscribed');

        // check HPOS is enabled or not
        if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
            $subQuery = fluentCrmDb()
                ->table('wc_orders')
                ->join('fc_subscribers', 'fc_subscribers.email', '=', 'wc_orders.billing_email')
                ->where('wc_orders.type', '=', 'shop_subscription')
                ->where('wc_orders.status', '=', 'wc-active')
                ->select('wc_orders.billing_email');
        } else {
            $subQuery = fluentCrmDb()
                ->table('posts')
                ->join('postmeta as billing_email', 'posts.ID', '=', 'billing_email.post_id')
                ->where('billing_email.meta_key', '=', '_billing_email')
                ->where('posts.post_type', '=', 'shop_subscription')
                ->where('posts.post_status', '=', 'wc-active')
                ->select('billing_email.meta_value as email');
        }

        $this->model = $query->whereIn('email', $subQuery);

        return $this->model;
    }

    public function getSegmentDetails($segment, $id, $config)
    {
        $segment = $this->getInfo();

        if (Arr::get($config, 'model')) {
            $segment['model'] = $this->getModel($segment);
        }

        if (Arr::get($config, 'subscribers')) {
            $segment['subscribers'] = $this->getSubscribers($config);
        }

        if (Arr::get($config, 'contact_count')) {
            $segment['contact_count'] = $this->getCount();
        }

        return $segment;
    }
}
