<?php
namespace FluentCampaign\App\Services\Integrations\WishlistMember;

class WishlistMemberInit
{
    public function init()
    {
        new \FluentCampaign\App\Services\Integrations\WishlistMember\WishlistMembershipTrigger();
        new \FluentCampaign\App\Services\Integrations\WishlistMember\WishlistMemberImporter();
        (new \FluentCampaign\App\Services\Integrations\WishlistMember\AutomationConditions())->init();

        add_filter('fluent_crm/subscriber_info_widgets', array($this, 'pushSubscriberInfoWidget'), 10, 2);
    }


    public function pushSubscriberInfoWidget($widgets, $subscriber)
    {
        if(!$subscriber->user_id) {
            return $widgets;
        }

        $levels = (array)\wlmapi_get_member_levels($subscriber->user_id);
        if (empty($levels)) {
            return $widgets;
        }

        $html = '<ul class="fc_full_listed fc_memberpress_subscription_lists">';
        foreach ($levels as $level) {
            $formatted_date = date_i18n(get_option('date_format'), $level->Timestamp);

            $html .= '<li>';
            $status = !empty($level->Active)
                ? __('Active', 'fluentcampaign-pro')
                : __('Cancelled', 'fluentcampaign-pro');

            $html .= '<span class="fc_mepr_subscription_header">';
            $html .= '<span class="fc_mepr_subscription_status ' . strtolower($level->Status[0]) . '">' . $status . '</span>';
            $html .= '</span>';
            $html .= '<a href="' . esc_url(admin_url('admin.php?page=WishListMember&wl=setup%2Flevels&level_id='.esc_attr($level->Level_ID).'#levels_access-'.esc_attr($level->Level_ID).'')) . '" target="_blank" class="fc_mepr_subscription_title">';
            $html .= '<b>' . esc_html($level->Name) . '<span class="fc_dash_extrernal dashicons dashicons-external"></span></b>';
            $html .= '</a>';

            $html .= '<span class="fc_date">' . $formatted_date . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';

        $widgets[] = [
            'title'   => __('Wishlist Membership', 'fluentcampaign-pro'),
            'content' => $html
        ];

        return $widgets;
    }

}