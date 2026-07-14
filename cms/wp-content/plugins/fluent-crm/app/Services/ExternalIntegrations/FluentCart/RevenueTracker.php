<?php

namespace FluentCrm\App\Services\ExternalIntegrations\FluentCart;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Services\Helper as FluentCrmHelper;
use FluentCrm\Framework\Support\Arr;

/**
 * Stamps the originating campaign id (`_fc_cid` cookie set by RedirectionHandler when an
 * email link is clicked) onto each FluentCart order at creation time, then rolls the
 * paid total into the `_campaign_revenue` campaign meta when the order is paid.
 *
 * This mirrors the WooCommerce/EDD revenue attribution flow used by
 * CampaignAnalyticsController so the existing campaign analytics UI works for FluentCart.
 */
class RevenueTracker
{
    public function init()
    {
        add_action('fluent_cart/order_created', [$this, 'attributeOrder'], 10, 1);
        add_action('fluent_cart/order_paid', [$this, 'recordRevenue'], 10, 1);
    }
    
    /**
     * Stamp the originating campaign id on the order while we still have access to
     * the visitor's `fc_cid` cookie (the request runs in the user's browser context here;
     * by the time `order_paid` fires from a gateway webhook the cookie is gone).
     */
    public function attributeOrder($eventData)
    {
        $cid = $this->getCampaignIdFromCookie();
        if (!$cid) {
            return;
        }

        $order = Arr::get($eventData, 'order');
        if (!$order || empty($order->id)) {
            return;
        }

        if ($order->getMeta('_fc_cid')) {
            return;
        }

        $order->updateMeta('_fc_cid', $cid);
    }

    /**
     * On payment success, accumulate this order's total into the campaign's
     * `_campaign_revenue` meta. Helper::recordCampaignRevenue handles dedup
     * (same order_id won't be counted twice) and per-currency bucketing.
     */
    public function recordRevenue($eventData)
    {
        $order = Arr::get($eventData, 'order');
        if (!$order || empty($order->id)) {
            return;
        }

        $cid = (int) $order->getMeta('_fc_cid');
        if (!$cid) {
            return;
        }

        // FluentCart stores monetary amounts as integer cents — same convention the
        // Woo re-sync uses when writing into `_campaign_revenue`.
        $amountCents = (int) $order->total_amount;
        if ($amountCents <= 0) {
            return;
        }

        $currency = $order->currency ? strtoupper($order->currency) : 'USD';

        FluentCrmHelper::recordCampaignRevenue($cid, $amountCents, $order->id, $currency);
    }

    private function getCampaignIdFromCookie()
    {
        if (empty($_COOKIE['fc_cid'])) {
            return 0;
        }

        $cid = (int) $_COOKIE['fc_cid'];
        if ($cid <= 0) {
            return 0;
        }

        $exists = Campaign::withoutGlobalScopes()->where('id', $cid)->exists();

        return $exists ? $cid : 0;
    }
}
