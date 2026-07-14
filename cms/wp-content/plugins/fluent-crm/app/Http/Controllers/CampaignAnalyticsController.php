<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Http\Request\Request;

/**
 *  CampaignAnalyticsController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class CampaignAnalyticsController extends Controller
{
    public function getLinksReport(CampaignUrlMetric $campaignUrlMetric, $campaignId)
    {
        $campaign = Campaign::withoutGlobalScopes()->findOrFail($campaignId);
        $clickStatus = $campaign->settings['click_tracker'] ?? '';
        $openStatus = $campaign->settings['open_tracker'] ?? '';

        if ($clickStatus === '') {
            $clickStatus = fluentcrmTrackClicking();
        }

        if ($openStatus === '') {
            $openStatus = fluentcrmTrackEmailOpen();
        }

        $links = array_values($campaignUrlMetric->getLinksReport($campaign));

        return $this->sendSuccess([
            'links'        => $links,
            'click_status' => $clickStatus,
            'open_status'  => $openStatus
        ]);
    }

    public function getRevenueReport(Request $request, $campaignId)
    {
        $limit = intval($request->get('per_page', 10));
        $offset = (intval($request->get('page', 1)) - 1) * $limit;

        $sources = $this->getActiveRevenueSources();
        $multiSource = count($sources) > 1;

        if (empty($sources)) {
            return [
                'orders' => [],
                'labels' => $this->getRevenueLabels(false),
                'total'  => 0
            ];
        }

        // Build a single newest-first index across every active commerce source so
        // pagination spans them all. Within each source, ids stay in DB-newest order.
        $index = [];
        foreach ($sources as $source) {
            foreach ($this->getAttributedOrderIds($source, $campaignId) as $orderId) {
                $index[] = ['source' => $source, 'order_id' => (int) $orderId];
            }
        }

        $totalOrders = count($index);
        $pageEntries = array_slice($index, $offset, $limit);

        $orders = [];
        foreach ($pageEntries as $entry) {
            $row = $this->formatRevenueRow($entry['source'], $entry['order_id'], $multiSource);
            if ($row) {
                $orders[] = $row;
            }
        }

        return [
            'orders' => $orders,
            'labels' => $this->getRevenueLabels($multiSource),
            'total'  => $totalOrders
        ];
    }

    public function getRevenueReSyncReport(Request $request, $campaignId)
    {
        $sources = $this->getActiveRevenueSources();
        if (empty($sources)) {
            return [
                'message' => __('No revenue found for this campaign', 'fluent-crm')
            ];
        }

        $revenueData = ['orderIds' => []];
        $primaryCurrency = null;

        foreach ($sources as $source) {
            $sourceData = $this->reSyncSourceRevenue($source, $campaignId);
            foreach ($sourceData['orderIds'] as $oid) {
                if (!in_array($oid, $revenueData['orderIds'])) {
                    $revenueData['orderIds'][] = $oid;
                }
            }
            foreach ($sourceData['totals'] as $currency => $cents) {
                if (!isset($revenueData[$currency])) {
                    $revenueData[$currency] = 0;
                    if ($primaryCurrency === null) {
                        $primaryCurrency = $currency;
                    }
                }
                $revenueData[$currency] += $cents;
            }
        }

        if (empty($revenueData['orderIds'])) {
            return [
                'message' => __('No order found to re-sync', 'fluent-crm')
            ];
        }

        fluentcrm_update_campaign_meta($campaignId, '_campaign_revenue', $revenueData);

        $primaryTotal = $primaryCurrency ? $revenueData[$primaryCurrency] : 0;

        return [
            'message' => __('Revenue has been re-synced successfully', 'fluent-crm'),
            'total'   => number_format($primaryTotal / 100, 2)
        ];
    }

    /**
     * Active commerce sources that participate in campaign revenue attribution.
     * Order matters: it determines display precedence within the merged report.
     */
    protected function getActiveRevenueSources()
    {
        $sources = [];
        if (defined('WC_PLUGIN_FILE')) {
            $sources[] = 'woo';
        }
        if (Helper::isEdd3()) {
            $sources[] = 'edd';
        }
        if (defined('FLUENTCART_VERSION')) {
            $sources[] = 'fct';
        }
        return $sources;
    }

    /**
     * Lightweight index query — returns just order IDs attributed to this campaign,
     * newest-first per source. Used both for paginated report rendering and re-sync.
     */
    protected function getAttributedOrderIds($source, $campaignId)
    {
        if ($source === 'woo') {
            if (Helper::isWooHposEnabled()) {
                return fluentCrmDb()->table('wc_orders_meta')
                    ->where('meta_key', '_fc_cid')
                    ->where('meta_value', $campaignId)
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->pluck('order_id')
                    ->map(function ($orderId) {
                        return intval($orderId);
                    })
                    ->all();
            }
            return fluentCrmDb()->table('postmeta')
                ->where('meta_key', '_fc_cid')
                ->where('meta_value', $campaignId)
                ->orderBy('meta_id', 'DESC')
                ->get()
                ->pluck('post_id')
                ->map(function ($orderId) {
                    return intval($orderId);
                })
                ->all();
        }

        if ($source === 'edd') {
            /*
             * EDD 3 writes order attribution meta to edd_ordermeta via the
             * order meta API. Do not read legacy postmeta/edd_payment records.
             */
            return fluentCrmDb()->table('edd_ordermeta')
                ->where('meta_key', '_fc_cid')
                ->where('meta_value', $campaignId)
                ->orderBy('meta_id', 'DESC')
                ->get()
                ->pluck('edd_order_id')
                ->map(function ($orderId) {
                    return intval($orderId);
                })
                ->all();
        }

        if ($source === 'fct') {
            return fluentCrmDb()->table('fct_order_meta')
                ->where('meta_key', '_fc_cid')
                ->where('meta_value', $campaignId)
                ->orderBy('id', 'DESC')
                ->get()
                ->pluck('order_id')
                ->map(function ($orderId) {
                    return intval($orderId);
                })
                ->all();
        }

        return [];
    }

    /**
     * Sum NET revenue per currency for one source — i.e. only orders in a successful
     * (paid/completed) status, with refunded amounts subtracted. Returns
     * `['orderIds' => [int...], 'totals' => ['usd' => cents, ...]]`.
     * Orders that net to zero or below (fully refunded, cancelled, pending) are skipped
     * so they don't pollute the order list with non-revenue rows.
     */
    protected function reSyncSourceRevenue($source, $campaignId)
    {
        $result = ['orderIds' => [], 'totals' => []];
        $orderIds = $this->getAttributedOrderIds($source, $campaignId);
        if (!$orderIds) {
            return $result;
        }

        if ($source === 'woo') {
            $paidStatuses = function_exists('wc_get_is_paid_statuses') ? wc_get_is_paid_statuses() : ['processing', 'completed'];
            $currency = strtolower(get_woocommerce_currency());
            foreach ($orderIds as $orderId) {
                $order = wc_get_order($orderId);
                if (!$order || !$order->get_id()) {
                    continue;
                }
                if (!in_array($order->get_status(), $paidStatuses, true)) {
                    continue;
                }
                $netCents = intval(((float) $order->get_total() - (float) $order->get_total_refunded()) * 100);
                if ($netCents <= 0) {
                    continue;
                }
                $result['orderIds'][] = (int) $order->get_id();
                $result['totals'][$currency] = ($result['totals'][$currency] ?? 0) + $netCents;
            }
            return $result;
        }

        if ($source === 'edd') {
            // EDD 3 keeps canonical status and refund data in order tables.
            $completeStatuses = ['complete', 'completed', 'partially_refunded'];
            foreach ($orderIds as $orderId) {
                $payment = new \EDD_Payment($orderId);
                if (!$payment || !$payment->ID) {
                    continue;
                }
                if (!in_array($payment->status, $completeStatuses, true)) {
                    continue;
                }
                $netTotal = function_exists('edd_get_order_total')
                    ? edd_get_order_total($payment->ID)
                    : $payment->total;
                $netCents = intval(((float) $netTotal) * 100);
                if ($netCents <= 0) {
                    continue;
                }
                $currency = strtolower(edd_get_payment_currency_code($payment->ID) ?: 'usd');
                $result['orderIds'][] = (int) $payment->ID;
                $result['totals'][$currency] = ($result['totals'][$currency] ?? 0) + $netCents;
            }
            return $result;
        }

        if ($source === 'fct') {
            // Canonical "successful" set: paid, partially_paid, partially_refunded.
            // Net revenue subtracts total_refund below so partial refunds still contribute.
            $successStatuses = \FluentCart\App\Helpers\Status::getOrderPaymentSuccessStatuses();
            $orders = \FluentCart\App\Models\Order::query()
                ->whereIn('id', $orderIds)
                ->whereIn('payment_status', $successStatuses)
                ->get();
            foreach ($orders as $order) {
                $netCents = (int) $order->total_amount - (int) ($order->total_refund ?? 0);
                if ($netCents <= 0) {
                    continue;
                }
                $currency = strtolower($order->currency ?: 'usd');
                $result['orderIds'][] = (int) $order->id;
                $result['totals'][$currency] = ($result['totals'][$currency] ?? 0) + $netCents;
            }
            return $result;
        }

        return $result;
    }

    /**
     * Render a single order row for the merged revenue table. The `source` key
     * is added when more than one commerce platform is contributing data.
     */
    protected function formatRevenueRow($source, $orderId, $multiSource)
    {
        $row = null;
        if ($source === 'woo') {
            $row = $this->formatWooOrderRow($orderId);
        } else if ($source === 'edd') {
            $row = $this->formatEddOrderRow($orderId);
        } else if ($source === 'fct') {
            $row = $this->formatFluentCartOrderRow($orderId);
        }

        if (!$row) {
            return null;
        }

        if ($multiSource) {
            $row = ['source' => $this->getSourceLabel($source)] + $row;
        }

        return $row;
    }

    protected function getSourceLabel($source)
    {
        $labels = [
            'woo' => 'WooCommerce',
            'edd' => 'EDD',
            'fct' => 'FluentCart',
        ];
        return $labels[$source] ?? $source;
    }

    protected function getRevenueLabels($multiSource)
    {
        $labels = [
            'order'  => '#',
            'title'  => __('Customer', 'fluent-crm'),
            'status' => __('Status', 'fluent-crm'),
            'date'   => __('Date', 'fluent-crm'),
            'total'  => __('Total', 'fluent-crm'),
            'action' => __('View', 'fluent-crm'),
        ];
        if ($multiSource) {
            $labels = ['source' => __('Source', 'fluent-crm')] + $labels;
        }
        return $labels;
    }

    protected function formatWooOrderRow($orderId)
    {
        $order = wc_get_order($orderId);
        if (!$order || !$order->get_id()) {
            return null;
        }

        /* translators: 1: billing first name, 2: billing last name */
        $buyer = trim(sprintf(_x('%1$s %2$s', 'full name', 'fluent-crm'), $order->get_billing_first_name(), $order->get_billing_last_name()));

        $order_timestamp = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : '';

        if (!$order_timestamp) {
            $show_date = '&ndash;';
        } else if ($order_timestamp > strtotime('-1 day', time()) && $order_timestamp <= time()) {
            $show_date = sprintf(
            /* translators: %s: human-readable time difference */
                _x('%s ago', '%s = human-readable time difference', 'fluent-crm'),
                human_time_diff($order->get_date_created()->getTimestamp(), time())
            );
        } else {
            /**
             * Determine the date format for displaying the order creation date in the WooCommerce admin in FluentCRM.
             *
             * @param string The date format to be used. Default is 'M j, Y'.
             * @param string The context for the date format. Default is 'woocommerce'.
             * @since 2.2.0
             */
            $show_date = $order->get_date_created()->date_i18n(apply_filters('woocommerce_admin_order_date_format', __('M j, Y', 'fluent-crm')));
        }

        $editUrl = admin_url('post.php?post=' . absint($order->get_id()) . '&action=edit');

        return [
            'order'  => '#' . esc_html($order->get_order_number()),
            'title'  => '<a href="' . esc_url($editUrl) . '" class="order-view"><strong>' . esc_html($buyer) . '</strong></a>',
            'status' => wc_get_order_status_name($order->get_status()),
            'date'   => $show_date,
            'total'  => $order->get_formatted_order_total(),
            'action' => '<a href="' . esc_url($editUrl) . '">' . esc_html__('View', 'fluent-crm') . '</a>',
        ];
    }

    protected function formatEddOrderRow($orderId)
    {
        $payment = new \EDD_Payment($orderId);
        if (!$payment || !$payment->ID) {
            return null;
        }

        $orderActionHtml = '<a href="' . add_query_arg('id', $payment->ID, admin_url('edit.php?post_type=download&page=edd-payment-history&view=view-order-details')) . '">' . esc_html__('View', 'fluent-crm') . '</a>';
        $amount = !empty($payment->total) ? $payment->total : 0;
        $customer_id = edd_get_payment_customer_id($payment->ID);

        if (!empty($customer_id)) {
            $customer = new \EDD_Customer($customer_id);
            $customerName = '<a href="' . esc_url(admin_url("edit.php?post_type=download&page=edd-customers&view=overview&id=$customer_id")) . '">' . esc_html($customer->name) . '</a>';
        } else {
            $email = edd_get_payment_user_email($payment->ID);
            $customerName = '<a href="' . esc_url(admin_url("edit.php?post_type=download&page=edd-payment-history&s=$email")) . '">' . esc_html__('(customer missing)', 'fluent-crm') . '</a>';
        }

        return [
            'order'  => '#' . $payment->number,
            'title'  => $customerName,
            'status' => $payment->status_nicename,
            'date'   => date_i18n(get_option('date_format'), strtotime($payment->date)),
            'total'  => edd_currency_filter(edd_format_amount($amount), edd_get_payment_currency_code($payment->ID)),
            'action' => $orderActionHtml,
        ];
    }

    protected function formatFluentCartOrderRow($orderId)
    {
        $order = \FluentCart\App\Models\Order::with('customer')->find($orderId);
        if (!$order) {
            return null;
        }

        $customerName = '';
        if ($order->customer) {
            $customerName = trim($order->customer->first_name . ' ' . $order->customer->last_name);
            if (!$customerName) {
                $customerName = $order->customer->email;
            }
        }

        $orderUrl = admin_url('admin.php?page=fluent-cart#/orders/' . $order->id . '/view');

        return [
            'order'  => '#' . ($order->invoice_no ?: $order->id),
            'title'  => '<a target="_blank" rel="noopener" href="' . esc_url($orderUrl) . '">' . esc_html($customerName) . '</a>',
            'status' => esc_html(\FluentCrm\App\Services\Helper::getStatusText($order->status)),
            'date'   => date_i18n(get_option('date_format'), strtotime($order->created_at)),
            'total'  => \FluentCart\App\Helpers\Helper::toDecimal($order->total_amount, true, $order->currency),
            'action' => '<a target="_blank" rel="noopener" href="' . esc_url($orderUrl) . '">' . esc_html__('View', 'fluent-crm') . '</a>',
        ];
    }

    public function getUnsubscribers(Request $request, $campaignId)
    {
        $unsubscribes = CampaignUrlMetric::with('subscriber')
            ->where('campaign_id', $campaignId)
            ->where('type', 'unsubscribe')
            ->paginate();

        foreach ($unsubscribes as $unsubscribe) {
            $unsubscribe->subscriber->reason = $unsubscribe->subscriber->unsubscribeReason();
        }

        return [
            'unsubscribes' => $unsubscribes
        ];
    }

    public function getSegmentedContacts(Request $request, $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        $contactsModel = $campaign->getSubscribersModel();

        $search = $request->getSafe('search', 'sanitize_text_field');

        if ($search) {
            $contactsModel->searchBy($search);
        }

        if ($orderBy = $request->getSafe('sort_by', 'sanitize_sql_orderby', 'id')) {
            $orderType = $request->getSafe('sort_type', 'sanitize_sql_orderby', 'desc');
            $contactsModel->orderBy($orderBy, $orderType);
        }

        $contacts = $contactsModel->with(['lists', 'tags'])->paginate();

        return [
            'subscribers' => $contacts
        ];
    }
}
