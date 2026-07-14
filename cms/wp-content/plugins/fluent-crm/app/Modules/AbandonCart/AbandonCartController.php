<?php

namespace FluentCrm\App\Modules\AbandonCart;

use FluentCrm\App\Modules\AbandonCart\Drivers\DriverManager;
use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\Framework\Support\Arr;

class AbandonCartController extends Controller
{
    public function getCarts(Request $request)
    {
        $query = $request->get('query', []);
        $dateRangeInput = $request->get('date_range', []);
        $dateRange = $this->getDateRange($dateRangeInput);

        $enabledDrivers = DriverManager::getEnabled();
        $missingAutomations = [];
        $formattedDrivers = [];

        $triggerNames = [];
        foreach ($enabledDrivers as $driver) {
            $triggerNames[$driver->getTriggerName()] = $driver;
        }

        $activeTriggers = Funnel::query()
            ->whereIn('trigger_name', array_keys($triggerNames))
            ->where('status', 'published')
            ->pluck('trigger_name')
            ->unique()
            ->toArray();

        foreach ($enabledDrivers as $driver) {
            if (!in_array($driver->getTriggerName(), $activeTriggers, true)) {
                // Include the provider slug so the UI can scope the "no automation" notice
                // (doc link + starter template differ per provider).
                $missingAutomations[] = [
                    'provider' => $driver->getProviderSlug(),
                    'label'    => $driver->getProviderLabel(),
                ];
            }

            $formattedDrivers[$driver->getProviderSlug()] = [
                'label' => $driver->getProviderLabel(),
                'logo'  => $driver->getLogo()
            ];
        }

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
            'carts'              => $this->mutateCartData($carts),
            'haveAutomation'     => empty($missingAutomations),
            'missingAutomations' => $missingAutomations,
            'drivers'            => $formattedDrivers
        ];
    }

    public function mutateCartData($carts)
    {
        $updatedData = $carts->getCollection()->transform(function ($cart) {
            if ($cart->status == 'processing') {
                $cart->recovery_url = $cart->getRecoveryUrl();
            }

            $subscriber = $cart->subscriber;
            // Customer Avatar
            $cart->customer_avatar = $subscriber ? $subscriber->photo : fluentcrmGravatar($cart->email, $cart->full_name);

            // Driver-specific enrichment (product images, order URL, etc.)
            $driver = DriverManager::getDriver($cart->provider);
            if ($driver) {
                $cart = $driver->enrichCartForListing($cart);
            }

            // Remove subscriber to clean up output
            unset($cart->subscriber);

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
                'message' => __('No carts selected to delete', 'fluent-crm')
            ]);
        }

        $cartIds = array_map('intval', $cartIds);

        $carts = AbandonCartModel::whereIn('id', $cartIds)->get();

        foreach ($carts as $cart) {
            $cart->deleteCart();
        }

        return [
            'message' => __('Selected carts have been deleted successfully', 'fluent-crm')
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
        } else if ($recoveredCount) {
            $recoveryRate = '100%';
        }

        return [
            'widgets' => [
                'recovered_revenue'  => [
                    'title' => esc_html__('Recovered Revenue', 'fluent-crm'),
                    'value' => DriverManager::formatPrice($recoveredRevenue),
                    'count' => number_format($recoveredCount),
                ],
                'processing_revenue' => [
                    'title' => esc_html__('Processing Revenue', 'fluent-crm'),
                    'value' => DriverManager::formatPrice($processingRevenue),
                    'count' => number_format($processingCount),
                ],
                'lost_revenue'       => [
                    'title' => esc_html__('Lost Revenue', 'fluent-crm'),
                    'value' => DriverManager::formatPrice($lostRevenue),
                    'count' => number_format($lostCount),
                ],
                'draft_revenue'      => [
                    'title' => esc_html__('Draft Revenue', 'fluent-crm'),
                    'value' => DriverManager::formatPrice($draftRevenue),
                    'count' => number_format($draftCount)
                ],
                'optout_revenue'     => [
                    'title' => esc_html__('Optout Revenue', 'fluent-crm'),
                    'value' => DriverManager::formatPrice($optoutRevenue),
                    'count' => number_format($optoutCount)
                ],
                'recovery_rate'      => [
                    'title' => esc_html__('Recovery Rate', 'fluent-crm'),
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

            $startTime = isset($dateRange[0]) ? strtotime($dateRange[0]) : false;
            $endTime = isset($dateRange[1]) ? strtotime($dateRange[1]) : false;

            if (count($dateRange) != 2 || !$startTime || !$endTime || $startTime > $endTime) {
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

                try {
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
                } catch (\Exception $e) {
                    // Fallback to last 30 days
                    $dateRange = [
                        gmdate('Y-m-d 00:00:01', strtotime('-30 days')),
                        gmdate('Y-m-d 23:59:59')
                    ];
                }
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
