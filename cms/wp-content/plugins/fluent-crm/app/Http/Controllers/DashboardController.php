<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\Stats;
use FluentCrm\Framework\Support\Arr;

/**
 *  DashboardController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class DashboardController extends Controller
{
    public function getStats(Stats $stats)
    {
        $overallStats = $stats->getCounts();

        $nextMinuteTask = Helper::getNextMinuteTaskTimeStamp();

        $notices = [];

        if ((time() - $nextMinuteTask) > 120) {
            $notices[] = '<div class=""><b>Attention: </b> Looks like the scheduled cron jobs are not running timely. Please consider setup server side cron. <a href="' . admin_url('admin.php?page=fluentcrm-admin#/settings/settings_tools') . '">Click here to check the status</a></div>';
        }

        $systemTips = '';
        $emailsCount = Arr::get($overallStats, 'email_sent.count', 0);
        if ($emailsCount > 400000) {
            $lastEmail = CampaignEmail::orderBy('id', 'ASC')->first();
            if ($lastEmail && strtotime($lastEmail->created_at) < strtotime('-120 days')) {
                $emailsCount = number_format($emailsCount, 0);
                $sysBody = '<div class="fc_system_tips">';
                /* translators: %s: number of emails in the database */
                $sysBody .= '<p>' . sprintf(__('You have %s email history in the database. Consider cleaning up old email history to speed up your next email campaign.', 'fluent-crm'), $emailsCount) . '</p>';
                $sysBody .= '<a href="' . fluentcrm_menu_url_base('settings/settings_tools') . '" class="el-button fcrm_primary_btn">' . __('View Data Cleanup', 'fluent-crm') . '</a>';
                $sysBody .= '</div>';
                $systemTips = [
                    'title' => __('Database Cleanup Suggestion', 'fluent-crm'),
                    'body'  => $sysBody,
                ];
            }
        }

        /**
         * Define the FluentCRM dashboard notices.
         *
         * This filter allows modification of the notices displayed on the FluentCRM dashboard.
         *
         * @since 2.8.40
         *
         * @param array $notices An array of notices to be displayed on the dashboard.
         */
        $notices = apply_filters('fluent_crm/dashboard_notices', $notices);

        /**
         * Define the dashboard data for FluentCRM.
         *
         * @since 2.9.23
         * 
         * @param array {
         *     The dashboard data array.
         *
         *     @type array $stats             Overall statistics.
         *     @type array $sales             Sales statistics.
         *     @type array $dashboard_notices Notices to be displayed on the dashboard.
         *     @type array $onboarding        Onboarding statistics.
         *     @type array $quick_links       Quick links for the dashboard.
         *     @type array $ff_config         FluentForm configuration.
         *     @type array $recommendation    Recommendations for the user.
         *     @type array $system_tips       System tips for the user.
         * }
         */
        return apply_filters('fluent_crm/dashboard_data', [
            'stats'             => $overallStats,
            /**
             * Determine the FluentCRMsales statistics data.
             *
             * This filter allows modification of the sales statistics data before it is used.
             *
             * @since 2.7.0
             *
             * @param array An array of sales statistics data.
             */
            'sales'             => apply_filters('fluent_crm/sales_stats', []),
            'dashboard_notices' => $notices,
            'onboarding'        => $stats->getOnboardingStat(),
            'quick_links'       => $stats->getQuickLinks(),
            'ff_config'         => [
                'is_installed'     => defined('FLUENTFORM'),
                'create_form_link' => admin_url('admin.php?page=fluent_forms#add=1')
            ],
            'recommendation'    => $this->recommendation(),
            'system_tips'       => $systemTips,
            'recent_contacts'    => $stats->getRecentContacts(3),
            'active_automations' => $stats->getActiveAutomations(3),
            'recent_campaigns'   => $stats->getRecentCampaigns(3),
            'triggers'           => $this->getTriggers()
        ]);
    }

    private function recommendation()
    {
        if (defined('FLUENTCAMPAIGN')) {
            return false;
        }

        $recommendations = [];

        if (defined('WC_PLUGIN_FILE')) {
            $recommendations[] = [
                'provider'    => 'WooCommerce',
                'title'       => __('Do more with WooCommerce + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate FluentCRM with WooCommerce and segment your customers by purchase behavior, send super targeted emails, onboarding emails, cross promotions and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/woocommerce-marketing-automation/',
                'base_title'  => __('Supercharge your WooCommerce store by upgrading FluentCRM Pro', 'fluent-crm')
            ];
            $recommendations[] = [
                'provider'    => 'WooCommerce',
                'title'       => __('Do more with WooCommerce + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate FluentCRM with WooCommerce and segment your customers by purchase behavior, send super targeted emails, onboarding emails, cross promotions and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/woocommerce-marketing-automation/',
                'base_title'  => __('Supercharge your WooCommerce store by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        }

        if (Helper::isEdd3()) {
            $recommendations[] = [
                'provider'    => 'EDD',
                'title'       => __('Do more with EDD + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate FluentCRM with Easy Digital Downloads and segment your customers by purchase behavior, send super targeted emails, onboarding emails, cross promotions and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/easy-digital-downloads-integration-fluentcrm/',
                'base_title'  => __('Supercharge your Digital Downloads store by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        }

        if (defined('LLMS_PLUGIN_FILE')) {
            $recommendations[] = [
                'provider'    => 'LifterLMS',
                'title'       => __('Do more with LifterLMS + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate LifterLMS with FluentCRM and segment your students by courses, send super targeted emails, onboarding emails, cross promote more courses and many more.', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/lifterlms/',
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your LMS by upgrading FluentCRM Pro', 'fluent-crm')
            ];
            $recommendations[] = [
                'provider'    => 'LifterLMS',
                'title'       => __('Do more with LifterLMS + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate LifterLMS with FluentCRM and segment your students by courses, send super targeted emails, onboarding emails, cross promote more courses and many more.', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/lifterlms/',
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your LMS by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        } else if (defined('LEARNDASH_VERSION')) {
            $recommendations[] = [
                'provider'    => 'LearnDash',
                'title'       => __('Do more with LearnDash + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate LearnDash with FluentCRM and segment your students by courses, send super targeted emails, onboarding emails, cross promote more courses and many more.', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/learndash-integration-fluentcrm/',
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your LMS by upgrading FluentCRM Pro', 'fluent-crm')
            ];
            $recommendations[] = [
                'provider'    => 'LearnDash',
                'title'       => __('Do more with LearnDash + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate LearnDash with FluentCRM and segment your students by courses, send super targeted emails, onboarding emails, cross promote more courses and many more.', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/integrations/learndash-integration-fluentcrm/',
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your LMS by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        } else if (defined('TUTOR_VERSION')) {
            $recommendations[] = [
                'provider'    => 'TutorLMS',
                'title'       => __('Do more with TutorLMS + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate TutorLMS with FluentCRM and segment your students by courses, send super targeted emails, onboarding emails, cross promote more courses and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/docs/tutorlms-integration-with-fluentcrm/',
                'base_title'  => __('Supercharge your LMS by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        } else if (defined('LP_PLUGIN_FILE')) {
            $recommendations[] = [
                'provider'    => 'LearnPress',
                'title'       => __('Do more with LearnPress + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate LearnPress with FluentCRM and segment your students by courses, send super targeted emails, onboarding emails, cross promote more courses and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'learn_more'  => 'https://fluentcrm.com/docs/learpress-integration-with-fluentcrm/',
                'base_title'  => __('Supercharge your LMS by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        }

        if (defined('PMPRO_VERSION')) {
            $recommendations[] = [
                'provider'    => 'PaidMembership Pro',
                'title'       => __('Do more with PaidMembership Pro + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate PaidMembership Pro with FluentCRM and segment your members by membership levels, send super targeted emails, onboarding emails, cross promote more levels and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your Membership Site by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        } else if (defined('WLM3_PLUGIN_VERSION')) {
            $recommendations[] = [
                'provider'    => 'Wishlist Member',
                'title'       => __('Do more with Wishlist Member + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate Wishlist Member with FluentCRM and segment your members by membership levels, send super targeted emails, onboarding emails, cross promote more levels and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your Membership Site by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        } else if (defined('MEPR_PLUGIN_NAME')) {
            $recommendations[] = [
                'provider'    => 'MemberPress',
                'title'       => __('Do more with MemberPress + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate MemberPress with FluentCRM and segment your members by membership levels, send super targeted emails, onboarding emails, cross promote more levels and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your Membership Site by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        } else if (class_exists('\Restrict_Content_Pro')) {
            $recommendations[] = [
                'provider'    => 'Restrict Content Pro',
                'title'       => __('Do more with Restrict Content Pro + FluentCRM', 'fluent-crm'),
                'description' => __('Integrate Restrict Content Pro with FluentCRM and segment your members by membership levels, send super targeted emails, onboarding emails, cross promote more levels and many more.', 'fluent-crm'),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your Membership Site by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        }

        if (defined('BP_REQUIRED_PHP_VERSION') && function_exists('\buddypress')) {
            $title = defined('BP_PLATFORM_VERSION') ? 'BuddyBoss' : 'BuddyPress';
            $recommendations[] = [
                'provider'    => $title,
                /* translators: %s: plugin name (BuddyBoss or BuddyPress) */
                'title'       => sprintf(__('Do more with %s + FluentCRM', 'fluent-crm'), $title),
                /* translators: %s: plugin name (BuddyBoss or BuddyPress) */
                'description' => sprintf(__('Integrate %s with FluentCRM and segment your members by different group, send super targeted emails, onboarding emails, cross promote more groups and many more.', 'fluent-crm'), $title),
                'btn_text'    => __('Upgrade to Pro', 'fluent-crm'),
                'base_title'  => __('Supercharge your Community Site by upgrading FluentCRM Pro', 'fluent-crm')
            ];
        }

        if (!$recommendations) {
            return false;
        }

        return $recommendations[array_rand($recommendations)];

    }

    private function getTriggers()
    {
        /**
         * Determine the list of funnel triggers in FluentCRM.
         *
         * This filter allows you to modify the array of funnel triggers.
         *
         * @since 1.0.0
         *
         * @param array An array of funnel triggers.
         */
        return apply_filters('fluentcrm_funnel_triggers', []);
    }
}
