<?php

namespace FluentCampaign\App\Hooks\Handlers;


use FluentCrm\App\Models\Subscriber;

class ExtendedSmartCodesHandler
{
    public function register()
    {
        add_filter('fluent_crm/smartcode_groups', array($this, 'pushUserCodes'));
        add_filter('fluent_crm/smartcode_group_callback_wp_user', array($this, 'parseUserSmartCode'), 10, 4);
    }

    public function pushUserCodes($codes)
    {
        $codes[] = [
            'key'        => 'wp_user',
            'title'      => 'WP User',
            'shortcodes' => [
                '{{wp_user.display_name}}'        => __('User\'s Display Name', 'fluentcampaign-pro'),
                '{{wp_user.user_login}}'          => __('User Login (username)', 'fluentcampaign-pro'),
                '##wp_user.password_reset_url## ' => __('Password Reset URL (on button / link)', 'fluentcampaign-pro'),
                '{{wp_user.password_reset_url}} ' => __('Password Reset URL (as plain text)', 'fluentcampaign-pro'),
                '{{wp_user.meta.META_KEY}}'       => __('User Meta Data', 'fluentcampaign-pro'),
            ]
        ];

        return $codes;
    }

    public function parseUserSmartCode($code, $valueKey, $defaultValue, $subscriber)
    {
        $wpUser = $subscriber->getWpUser();

        if (!$wpUser) {
            return $defaultValue;
        }

        $userKeys = [
            'ID',
            'user_login',
            'user_nicename',
            'user_url',
            'user_registered',
            'display_name'
        ];

        if (in_array($valueKey, $userKeys)) {
            return $wpUser->{$valueKey};
        }

       if (strpos($valueKey, 'meta.') === 0) {
            $metaKey = str_replace('meta.', '', $valueKey);
            $metaValue = get_user_meta($wpUser->ID, $metaKey, true);
            if (!$metaValue) {
                return $defaultValue;
            }
            // If the meta value is an array (like from a checkbox field), convert to comma-separated string
            if (is_array($metaValue)) {
                return implode(', ', $metaValue);
            }
            return $metaValue;
        }

        if ($valueKey == 'password_reset_url') {

            static $linkCache = [];
            if (isset($linkCache[$wpUser->ID])) {
                return $linkCache[$wpUser->ID];
            }

            $key = get_password_reset_key($wpUser);
            if (is_wp_error($key)) {
                return wp_lostpassword_url();
            }

            $linkCache[$wpUser->ID] = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($wpUser->user_login), 'login') . '&wp_lang=' . get_user_locale($wpUser);
            return $linkCache[$wpUser->ID];
        }

        return $defaultValue;
    }
}
