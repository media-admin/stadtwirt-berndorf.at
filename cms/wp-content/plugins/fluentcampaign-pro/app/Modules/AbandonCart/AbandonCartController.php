<?php

namespace FluentCampaign\App\Modules\AbandonCart;

use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Request\Request;
use FluentCrm\Framework\Support\Arr;

class AbandonCartController extends Controller
{
    public function getCarts(Request $request)
    {
        $query = $request->get('query', []);
        $dateRangeInput = $request->get('date_range', []);
        $dateRange = $this->getDateRange($dateRangeInput);

        $haveAutomation = Funnel::query()
            ->where('trigger_name', 'fc_ab_cart_simulation_woo')
            ->where('status', 'published')
            ->exists();

        $carts = AbandonCartModel::orderBy('id', 'DESC')
            ->with(['subscriber', 'automation']);

        if ($dateRange) {
            $carts = $carts->whereBetween('created_at', $dateRange);
        }

        $status = sanitize_text_field(Arr::get($query, 'status', ''));
        $search = sanitize_text_field(Arr::get($query, 'search', ''));

        $carts = $carts->statusBy($status)
            ->searchBy($search)
            ->paginate();

        return [
            'carts'          => $this->mutateCartData($carts),
            'haveAutomation' => $haveAutomation
        ];
    }

    public function mutateCartData($carts)
    {
        $updatedData = $carts->getCollection()->transform(function ($cart) {
            if ($cart->status == 'processing') {
                $cart->recovery_url = $cart->getRecoveryUrl();
            }

            // Customer Avatar
            $cart->customer_avatar = $cart->subscriber ? $cart->subscriber->photo : fluentcrmGravatar($cart->email, $cart->full_name);

            // WooCommerce Order URL
            if ($cart->order_id && $cart->provider == 'woo') {
                $cart->order_url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $cart->order_id);
            }

            // Remove subscriber to clean up output
            unset($cart->subscriber);

            $oldCart = $cart->cart;
            $newCart = $oldCart;

            if (!empty($cart->cart['cart_contents'])) {
                foreach ($oldCart['cart_contents'] as $key => $cartItem) {
                    $product = wc_get_product($cartItem['product_id']);
                    if ($product) {
                        $product_image_url = wp_get_attachment_url($product->get_image_id());
                        if (!$product_image_url) {
                            $product_image_url = wc_placeholder_img_src();
                        }
                        $newCart['cart_contents'][$key]['product_image'] = $product_image_url;
                    }
                }
            }
            $cart->cart = $newCart;
            return $cart;
        });

        $carts->setCollection(
            $updatedData
        );

        return $carts;
    }

    public function handleBulkDeleteCart(Request $request)
    {
        $cartIds = $request->get('cart_ids', []);

        if (!$cartIds || !is_array($cartIds)) {
            return $this->sendError([
                'message' => __('No carts selected to delete', 'fluentcampaign-pro')
            ]);
        }

        $carts = AbandonCartModel::whereIn('id', $cartIds)->get();

        foreach ($carts as $cart) {
            $cart->deleteCart();
        }

        return [
            'message' => __('Selected carts has been deleted successfully', 'fluentcampaign-pro')
        ];
    }

    public function getReportSummary(Request $request)
    {
        $dateRangeInput = $request->get('date_range', []);
        $dateRange = $this->getDateRange($dateRangeInput);

        [$recoveredCount, $recoveredRevenue] = AbCartHelper::getCountAndSumByStatus('recovered', $dateRange, 'recovered_at');
        [$processingCount, $processingRevenue] = AbCartHelper::getCountAndSumByStatus('processing', $dateRange);
        [$lostCount, $lostRevenue] = AbCartHelper::getCountAndSumByStatus('lost', $dateRange);
        [$draftCount, $draftRevenue] = AbCartHelper::getCountAndSumByStatus('draft', $dateRange);
        [$optoutCount, $optoutRevenue] = AbCartHelper::getCountAndSumByStatus('opt_out', $dateRange);

        $recoveryRate = '0%';

        if ($lostCount) {
            $recoveryRate = number_format(($recoveredCount / ($lostCount + $recoveredCount)) * 100, 2) . '%';
        } else if($recoveredCount) {
            $recoveryRate = '100%';
        }

        return [
            'widgets' => [
                'recovered_revenue'  => [
                    'title' => esc_html__('Recovered Revenue', 'fluentcampaign-pro'),
                    'value' => wc_price($recoveredRevenue),
                    'count' => number_format($recoveredCount),
                ],
                'processing_revenue' => [
                    'title' => esc_html__('Processing Revenue', 'fluentcampaign-pro'),
                    'value' => wc_price($processingRevenue),
                    'count' => number_format($processingCount),
                ],
                'lost_revenue'       => [
                    'title' => esc_html__('Lost Revenue', 'fluentcampaign-pro'),
                    'value' => wc_price($lostRevenue),
                    'count' => number_format($lostCount),
                ],
                'draft_revenue'      => [
                    'title' => esc_html__('Draft Revenue', 'fluentcampaign-pro'),
                    'value' => wc_price($draftRevenue),
                    'count' => number_format($draftCount)
                ],
                'optout_revenue'     => [
                    'title' => esc_html__('Optout Revenue', 'fluentcampaign-pro'),
                    'value' => wc_price($optoutRevenue),
                    'count' => number_format($optoutCount)
                ],
                'recovery_rate'      => [
                    'title' => esc_html__('Recovery Rate', 'fluentcampaign-pro'),
                    'value' => $recoveryRate,
                    'count' => ''
                ]
            ]
        ];

    }

    public function getDateRange($dateRangeInput)
    {
        if ($dateRangeInput) {
            $dateRange = array_filter($dateRangeInput);

            if (count($dateRange) != 2 || strtotime($dateRange[0]) > strtotime($dateRange[1])) {
                // Invalid date range, fallback to last 30 days
                $startDate = gmdate('Y-m-d 00:00:01', strtotime('-30 days'));
                $endDate = gmdate('Y-m-d 23:59:59');
                $dateRange = [$startDate, $endDate];
            } else {
                $startDateString = $dateRange[0];
                $endDateString = $dateRange[1];

                // Remove timezone identifiers
                $startDateString = preg_replace('/\(.*\)/', '', $startDateString);
                $endDateString = preg_replace('/\(.*\)/', '', $endDateString);

                // Parse dates
                $startDate = new \DateTime($startDateString);
                $endDate = new \DateTime($endDateString);

                // Adjust times for range
                $startDate->setTime(0, 0, 1); // Set time to 00:00:01
                $endDate->setTime(23, 59, 59); // Set time to 23:59:59

                // Format for SQL or other usage
                $dateRange = [
                    $startDate->format("Y-m-d H:i:s"),
                    $endDate->format("Y-m-d H:i:s")
                ];
            }
        } else {
            // Default to last 30 days if no date range provided
            $startDate = gmdate('Y-m-d 00:00:01', strtotime('-30 days'));
            $endDate = gmdate('Y-m-d 23:59:59');
            $dateRange = [$startDate, $endDate];
        }

        return $dateRange;
    }

}
