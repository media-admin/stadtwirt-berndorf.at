<?php
namespace FluentCampaign\App\Services\Integrations\MemberPress;

use MeprOptions;

class MemberPressInit
{
    public function init()
    {
        new \FluentCampaign\App\Services\Integrations\MemberPress\MembershipTrigger();
        new \FluentCampaign\App\Services\Integrations\MemberPress\SubscriptionExpiredTrigger();
        new \FluentCampaign\App\Services\Integrations\MemberPress\MemberPressImporter();

        add_filter('fluent_crm/subscriber_info_widgets', array($this, 'pushSubscriberInfoWidget'), 10, 2);
    }

    public function pushSubscriberInfoWidget($widgets, $subscriber)
    {
        if(!$subscriber->user_id) {
            return $widgets;
        }

        $subscriptionList = fluentCrmDb()->table('mepr_subscriptions')
            ->join('posts', 'mepr_subscriptions.product_id', '=', 'posts.ID')
            ->where('posts.post_type', 'memberpressproduct')
            ->where('mepr_subscriptions.user_id', $subscriber->user_id)
            ->select('mepr_subscriptions.*', 'posts.post_title')
            ->orderBy('mepr_subscriptions.created_at', 'desc')
            ->get();

        // Get all transactions
        $transactionList = fluentCrmDb()
            ->table('mepr_transactions')
            ->join('posts', 'mepr_transactions.product_id', '=', 'posts.ID')
            ->where('posts.post_type', 'memberpressproduct')
            ->where('mepr_transactions.user_id', $subscriber->user_id)
            ->where('mepr_transactions.subscription_id', 0)
            ->select('mepr_transactions.*', 'posts.post_title')
            ->orderBy('mepr_transactions.created_at', 'desc')
            ->get();

        if (empty($subscriptionList) && empty($transactionList)) {
            return $widgets;
        }

        $html = $this->generateSubscriptionHtml($subscriptionList, $transactionList);

        $widgets[] = [
            'title' => __('MemberPress Subscriptions', 'fluentcampaign-pro'),
            'content' => $html
        ];

        return $widgets;
    }

    private function generateSubscriptionHtml($subscriptionList, $transactionList)
    {
        $mepr_options = MeprOptions::fetch();

        $html = '<div class="fc-contact-profile-mepr-subscription-widget">';
        $html .= '<ul class="fc_full_listed fc_memberpress_subscription_lists">';

        if (!empty($subscriptionList)) {
            $html .= '<h4>' . __('Recurring Subscriptions', 'fluentcampaign-pro') . '</h4>';
            foreach ($subscriptionList as $subscription) {
                $html .= $this->generateSubscriptionItemHtml($subscription, $mepr_options);
            }
        }

        if (!empty($transactionList)) {
            $html .= '<h4>' . __('One-time Subscriptions', 'fluentcampaign-pro') . '</h4>';
            foreach ($transactionList as $transaction) {
                $html .= $this->generateTransactionItemHtml($transaction, $mepr_options);
            }
        }

        $html .= '</ul>';
        $html .= '</div>';
        return $html;
    }

    private function generateSubscriptionItemHtml($subscription, $mepr_options) {
        $periodDate = $this->calculatePeriodDate($subscription);

        $formatted_date = date_i18n(get_option('date_format'), strtotime($subscription->created_at));
        $html = '<li>';
        $html .= '<span class="fc_mepr_subscription_header">';
        $html .= '<span class="fc_mepr_subscription_status ' . esc_attr($subscription->status) . '">' . esc_html($subscription->status) . '</span>';
        $html .= '<span class="fc_mepr_subscription_price">' . $mepr_options->currency_symbol . esc_html($subscription->total) . '</span>';
        $html .= '</span>';
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=memberpress-subscriptions&action=edit&id=' . $subscription->id)) . '" target="_blank" class="fc_mepr_subscription_title">';
        $html .= '<b>' . esc_html($subscription->post_title) . '<span class="fc_dash_extrernal dashicons dashicons-external"></span></b>';
        $html .= '</a>';
        $html .= sprintf('<span class="fc_date">%s%s</span>', __('Start Date: ', 'fluentcampaign-pro'), $formatted_date);

        if ($subscription->period_type == 'lifetime') {
            $html .= sprintf('<span class="fc_date period_date">%s</span>', __('Lifetime Subscription', 'fluentcampaign-pro'));
        } else {
            $html .= sprintf('<span class="fc_date period_date">%s%s</span>', __('Expiry Date: ', 'fluentcampaign-pro'),$periodDate);
        }
        $html .= '</li>';
        return $html;
    }

    private function calculatePeriodDate($subscription) {
        if ($subscription->period_type == 'lifetime') {
            return null;
        }

        $baseDate = strtotime($subscription->created_at);
        switch ($subscription->period_type) {
            case 'weeks':
                return date(get_option('date_format'), strtotime("+ {$subscription->period} weeks", $baseDate));
            case 'months':
                return date(get_option('date_format'), strtotime("+ {$subscription->period} months", $baseDate));
            case 'years':
                return date(get_option('date_format'), strtotime("+ {$subscription->period} years", $baseDate));
            default:
                return null;
        }
    }

    private function generateTransactionItemHtml($transaction, $mepr_options)
    {
        $formatted_date = date_i18n(get_option('date_format'), strtotime($transaction->created_at));
        $expiryDate = date_i18n(get_option('date_format'), strtotime($transaction->expires_at));

        $html = '<li>';
        $html .= '<span class="fc_mepr_subscription_header">';
        $html .= '<span class="fc_mepr_subscription_status ' . esc_attr($transaction->status) . '">' . esc_html($transaction->status) . '</span>';
        $html .= '<span class="fc_mepr_subscription_price">' . $mepr_options->currency_symbol . esc_html($transaction->total) . '</span>';
        $html .= '</span>';
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=memberpress-trans&action=edit&id=' . $transaction->id)) . '" target="_blank" class="fc_mepr_subscription_title">';
        $html .= '<b>' . esc_html($transaction->post_title) . '<span class="fc_dash_extrernal dashicons dashicons-external"></span></b>';
        $html .= '</a>';
        $html .= sprintf('<span class="fc_date">%s%s</span>', __('Start Date: ', 'fluentcampaign-pro'), $formatted_date);

        if ($transaction->expires_at == '0000-00-00 00:00:00') {
            $html .= sprintf('<span class="fc_date period_date">%s</span>', __('Lifetime Subscription', 'fluentcampaign-pro'));
        } else {
            $html .= sprintf('<span class="fc_date period_date">%s%s</span>', __('Expiry Date: ', 'fluentcampaign-pro'),$expiryDate);
        }
        $html .= '</li>';
        return $html;
    }

}