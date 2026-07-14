<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Services\DbPerformanceService;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\App\Services\TransStrings;
use FluentCrm\Framework\Support\Arr;

/**
 * Admin Menu Class
 *
 *
 * @package FluentCrm\App\Hooks
 *
 * @version 1.0.0
 */
class AdminMenu
{
    public $version = FLUENTCRM_PLUGIN_VERSION;

    protected static $mainScriptLoaded = false;

    public function init()
    {
        // Maybe we have to update the database tables
        UpgradationHandler::maybeUpdateDbTables();

        add_action('admin_menu', array($this, 'addMenu'));

        if (isset($_GET['page']) && $_GET['page'] == 'fluentcrm-admin' && is_admin()) {
            $this->mayBeRedirect();
            add_action('admin_enqueue_scripts', array($this, 'loadAssets'), 1);
        }

    }

    public function addMenu()
    {
        $permissions = PermissionManager::currentUserPermissions();

        if (!$permissions) {
            return;
        }

        $isAdmin = false;
        if (in_array('administrator', $permissions)) {
            $dashboardPermission = 'manage_options';
            $isAdmin = true;
        } else {
            $user = get_user_by('ID', get_current_user_id());
            $roles = array_values((array)$user->roles);
            $dashboardPermission = Arr::get($roles, 0);
        }

        $title = __('FluentCRM', 'fluent-crm');
        if (defined('FLUENTCAMPAIGN')) {
            $title = __('FluentCRM Pro', 'fluent-crm');
        }
        add_menu_page(
            $title,
            $title,
            $dashboardPermission,
            'fluentcrm-admin',
            array($this, 'render'),
            $this->getMenuIcon(),
            2
        );

        add_submenu_page(
            'fluentcrm-admin',
            __('Dashboard', 'fluent-crm'),
            __('Dashboard', 'fluent-crm'),
            $dashboardPermission,
            'fluentcrm-admin',
            array($this, 'render')
        );



        if (in_array('fcrm_read_contacts', $permissions)) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Contacts', 'fluent-crm'),
                __('Contacts', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/subscribers',
                array($this, 'render')
            );


            if (in_array('fcrm_manage_contact_cats', $permissions)) {
                if (Helper::isCompanyEnabled()) {
                    add_submenu_page(
                        'fluentcrm-admin',
                        __('Companies', 'fluent-crm'),
                        __('Companies', 'fluent-crm'),
                        $dashboardPermission,
                        'fluentcrm-admin#/contact-groups/companies',
                        array($this, 'render')
                    );
                }

                add_submenu_page(
                    'fluentcrm-admin',
                    __('Lists', 'fluent-crm'),
                    __('Lists', 'fluent-crm'),
                    $dashboardPermission,
                    'fluentcrm-admin#/contact-groups/lists',
                    array($this, 'render')
                );

                add_submenu_page(
                    'fluentcrm-admin',
                    __('Tags', 'fluent-crm'),
                    __('Tags', 'fluent-crm'),
                    $dashboardPermission,
                    'fluentcrm-admin#/contact-groups/tags',
                    array($this, 'render')
                );
            }
        }

        if (in_array('fcrm_read_emails', $permissions)) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Campaigns', 'fluent-crm'),
                __('Campaigns', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/email/campaigns',
                array($this, 'render')
            );

            add_submenu_page(
                'fluentcrm-admin',
                __('Recurring Campaigns', 'fluent-crm'),
                __('Recurring Campaigns', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/email/recurring-campaigns',
                array($this, 'render')
            );

            add_submenu_page(
                'fluentcrm-admin',
                __('Email Sequences', 'fluent-crm'),
                __('Email Sequences', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/email/sequences',
                array($this, 'render')
            );

            add_submenu_page(
                'fluentcrm-admin',
                __('Email Templates', 'fluent-crm'),
                __('Email Templates', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/email/templates',
                array($this, 'render')
            );
        }

        if (in_array('fcrm_manage_forms', $permissions)) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Forms', 'fluent-crm'),
                __('Forms', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/forms',
                array($this, 'render')
            );
        }

        if (in_array('fcrm_read_funnels', $permissions)) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Automations', 'fluent-crm'),
                __('Automations', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/funnels',
                array($this, 'render')
            );
        }

        do_action('fluent_crm/after_core_menu_items', $permissions, $isAdmin);

        if (in_array('fcrm_manage_settings', $permissions)) {

            add_submenu_page(
                'fluentcrm-admin',
                __('Settings', 'fluent-crm'),
                __('Settings', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/settings',
                array($this, 'render')
            );

            add_submenu_page(
                'fluentcrm-admin',
                __('Reports', 'fluent-crm'),
                __('Reports', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/reports',
                array($this, 'render')
            );

            add_submenu_page(
                'fluentcrm-admin',
                __('Addons', 'fluent-crm'),
                __('Addons', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/add-ons',
                array($this, 'render')
            );
        }

        if (!defined('FLUENTMAIL_PLUGIN_VERSION')) {
            add_submenu_page(
                'fluentcrm-admin',
                __('SMTP', 'fluent-crm'),
                __('SMTP', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/settings/smtp_settings',
                array($this, 'render')
            );
        }

        if (in_array('fcrm_view_dashboard', $permissions)) {
            add_submenu_page(
                'fluentcrm-admin',
                __('Help', 'fluent-crm'),
                __('Help', 'fluent-crm'),
                $dashboardPermission,
                'fluentcrm-admin#/documentation',
                array($this, 'render')
            );
        }

    }

    public function render()
    {
        $this->changeFooter();
        $app = FluentCrm();

        /**
         * FluentCRM Admin App Loading Hook
         */
        do_action('fluentcrm_loading_app');

        wp_enqueue_script(
            'fluentcrm_admin_app_start',
            fluentCrmMix('/admin/js/app.js'),
            array('fluentcrm_admin_app_boot'),
            $this->version
        );

        /**
         * Controls whether the FluentCRM admin top menu bar should be rendered.
         *
         * @param bool $renderTopMenuBar Whether to render the top menu bar.
         */
        $renderTopMenuBar = apply_filters('fluent_crm/render_top_menu_bar', true);

        $urlBase = fluentcrm_menu_url_base();

        $menuItems = $this->getMenuItems($urlBase);

        $proData = [
            'label'     => __('Upgrade to Pro', 'fluent-crm'),
            'permalink' => 'https://fluentcrm.com?utm_source=dashboard&utm_medium=plugin&utm_campaign=pro&utm_id=wp',
            'has_pro'   => defined('FLUENTCAMPAIGN')
        ];

        $app['view']->render('admin.new_menu_page', [
            'menuItems'   => $menuItems,
            'settingsUrl' => $urlBase . 'settings',
            'logo'        => FLUENTCRM_PLUGIN_URL . 'assets/images/fluentcrm-logo.svg',
            'base_url'    => $urlBase,
            'proData'          => $proData,
            'renderTopMenuBar' => $renderTopMenuBar
        ]);
    }

    public function changeFooter()
    {
        add_filter('admin_footer_text', function ($content) {
            $url = 'https://fluentcrm.com';
            $extraHtml = '';
            if (!defined('DISABLE_WP_CRON')) {
                $doc_url = 'https://fluentcrm.com/docs/fluentcrm-cron-job-basics-and-checklist/';
                $extraHtml = ' ' . sprintf(
                        wp_kses(
                        /* translators: %1$s: Opening <a> tag linking to FluentCRM cron job docs. %2$s: Closing </a> tag. */
                            __('Server-Side Cron Job is not enabled %1$sView Documentation%2$s.', 'fluent-crm'),
                            array(
                                'a' => array(
                                    'href'   => array(),
                                    'target' => array(),
                                    'rel'    => array(),
                                    'style'  => array(),
                                )
                            )
                        ),
                        '<a style="font-weight: 500;" target="_blank" rel="noopener" href="' . esc_url($doc_url) . '">',
                        '</a>'
                    );
            }
            /* translators: %s: the FluentCRM website URL (used in the href of the link) */
            return sprintf(wp_kses(__('Thank you for using <a href="%s">FluentCRM</a>.', 'fluent-crm'), array('a' => array('href' => array()))), esc_url($url)) . '<span title="based on your WP timezone settings" style="margin-left: 10px;" data-timestamp="' . current_time('timestamp') . '" id="fc_server_timestamp"></span>. ' . $extraHtml;
        });

        add_filter('update_footer', function ($text) {
            if (defined('FLUENTCAMPAIGN_PLUGIN_VERSION') && FLUENTCRM_PLUGIN_VERSION != FLUENTCAMPAIGN_PLUGIN_VERSION) {
                return FLUENTCRM_PLUGIN_VERSION . ' & ' . FLUENTCAMPAIGN_PLUGIN_VERSION;
            }
            return FLUENTCRM_PLUGIN_VERSION;
        });
    }

    public function getMenuItems($urlBase = null)
    {
        if (!$urlBase) {
            $urlBase = fluentcrm_menu_url_base();
        }


        $permissions = PermissionManager::currentUserPermissions();

        $menuItems = [
            [
                'key'       => 'dashboard',
                'label'     => __('Dashboard', 'fluent-crm'),
                'permalink' => $urlBase
            ]
        ];

        if (in_array('fcrm_read_contacts', $permissions)) {
            $contactMenu = [
                'key'          => 'contacts',
                'label'        => __('Contacts', 'fluent-crm'),
                'permalink'    => $urlBase . 'subscribers',
                'layout_class' => 'fc_1_col_menu',
                'sub_items'    => [
                    [
                        'key'         => 'all_contacts',
                        'label'       => __('All Contacts', 'fluent-crm'),
                        'permalink'   => $urlBase . 'subscribers',
                        'description' => __('Browse all your subscribers and customers', 'fluent-crm'),
                        'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 3.75C4.65482 3.75 4.375 4.02982 4.375 4.375V5.625H5.625V5H14.375V15H5.625V14.375H4.375V15.625C4.375 15.9702 4.65482 16.25 5 16.25H15C15.3452 16.25 15.625 15.9702 15.625 15.625V4.375C15.625 4.02982 15.3452 3.75 15 3.75H5ZM8.125 12.5C8.125 11.4644 8.96444 10.625 10 10.625C11.0356 10.625 11.875 11.4644 11.875 12.5H8.125ZM10 10C9.30963 10 8.75 9.44037 8.75 8.75C8.75 8.05964 9.30963 7.5 10 7.5C10.6904 7.5 11.25 8.05964 11.25 8.75C11.25 9.44037 10.6904 10 10 10ZM6.25 8.125V6.875H3.75V8.125H6.25ZM6.25 9.375V10.625H3.75V9.375H6.25ZM6.25 13.125V11.875H3.75V13.125H6.25Z" fill="currentColor"/>
                        </svg>'
                    ]
                ]
            ];

            if (in_array('fcrm_manage_contact_cats', $permissions)) {

                $contactMenu['sub_items'][] = [
                    'key'         => 'lists',
                    'label'       => __('Lists', 'fluent-crm'),
                    'permalink'   => $urlBase . 'contact-groups/lists',
                    'description' => __('Browse and Manage your lists associate with contact', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 4H16.75V5.5H7V4ZM4.375 5.875C4.07663 5.875 3.79048 5.75647 3.5795 5.5455C3.36853 5.33452 3.25 5.04837 3.25 4.75C3.25 4.45163 3.36853 4.16548 3.5795 3.9545C3.79048 3.74353 4.07663 3.625 4.375 3.625C4.67337 3.625 4.95952 3.74353 5.1705 3.9545C5.38147 4.16548 5.5 4.45163 5.5 4.75C5.5 5.04837 5.38147 5.33452 5.1705 5.5455C4.95952 5.75647 4.67337 5.875 4.375 5.875ZM4.375 11.125C4.07663 11.125 3.79048 11.0065 3.5795 10.7955C3.36853 10.5845 3.25 10.2984 3.25 10C3.25 9.70163 3.36853 9.41548 3.5795 9.2045C3.79048 8.99353 4.07663 8.875 4.375 8.875C4.67337 8.875 4.95952 8.99353 5.1705 9.2045C5.38147 9.41548 5.5 9.70163 5.5 10C5.5 10.2984 5.38147 10.5845 5.1705 10.7955C4.95952 11.0065 4.67337 11.125 4.375 11.125ZM4.375 16.3C4.07663 16.3 3.79048 16.1815 3.5795 15.9705C3.36853 15.7595 3.25 15.4734 3.25 15.175C3.25 14.8766 3.36853 14.5905 3.5795 14.3795C3.79048 14.1685 4.07663 14.05 4.375 14.05C4.67337 14.05 4.95952 14.1685 5.1705 14.3795C5.38147 14.5905 5.5 14.8766 5.5 15.175C5.5 15.4734 5.38147 15.7595 5.1705 15.9705C4.95952 16.1815 4.67337 16.3 4.375 16.3ZM7 9.25H16.75V10.75H7V9.25ZM7 14.5H16.75V16H7V14.5Z" fill="currentColor"/>
                        </svg>'
                ];
                $contactMenu['sub_items'][] = [
                    'key'         => 'tags',
                    'label'       => __('Tags', 'fluent-crm'),
                    'permalink'   => $urlBase . 'contact-groups/tags',
                    'description' => __('Browse and Manage your tags associate with contact', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M9.17489 2.57495L16.5991 3.6362L17.6596 11.0612L10.7656 17.9552C10.625 18.0958 10.4343 18.1748 10.2354 18.1748C10.0365 18.1748 9.84578 18.0958 9.70514 17.9552L2.28014 10.5302C2.13953 10.3896 2.06055 10.1988 2.06055 9.99995C2.06055 9.80108 2.13953 9.61035 2.28014 9.4697L9.17489 2.57495ZM9.70514 4.16645L3.87089 9.99995L10.2354 16.3637L16.0689 10.5302L15.2739 4.96145L9.70514 4.16645ZM11.2951 8.93945C11.0138 8.65799 10.8557 8.27629 10.8558 7.87831C10.8559 7.68125 10.8947 7.48613 10.9701 7.30409C11.0456 7.12204 11.1561 6.95664 11.2955 6.81733C11.4349 6.67801 11.6003 6.56751 11.7824 6.49213C11.9645 6.41675 12.1596 6.37797 12.3567 6.37801C12.7546 6.37808 13.1363 6.53624 13.4176 6.8177C13.699 7.09916 13.857 7.48087 13.857 7.87884C13.8569 8.27682 13.6987 8.65846 13.4173 8.93983C13.1358 9.22119 12.7541 9.37921 12.3561 9.37914C11.9581 9.37907 11.5765 9.22091 11.2951 8.93945Z" fill="currentColor"/>
</svg>'
                ];

                if (Helper::isCompanyEnabled()) {
                    $contactMenu['sub_items'][] = [
                        'key'         => 'companies',
                        'label'       => __('Companies', 'fluent-crm'),
                        'permalink'   => $urlBase . 'contact-groups/companies',
                        'description' => __('Browse and Manage contact business/companies', 'fluent-crm'),
                        'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M16.75 15.25H18.25V16.75H1.75V15.25H3.25V4C3.25 3.80109 3.32902 3.61032 3.46967 3.46967C3.61032 3.32902 3.80109 3.25 4 3.25H11.5C11.6989 3.25 11.8897 3.32902 12.0303 3.46967C12.171 3.61032 12.25 3.80109 12.25 4V15.25H15.25V9.25H13.75V7.75H16C16.1989 7.75 16.3897 7.82902 16.5303 7.96967C16.671 8.11032 16.75 8.30109 16.75 8.5V15.25ZM4.75 4.75V15.25H10.75V4.75H4.75ZM6.25 9.25H9.25V10.75H6.25V9.25ZM6.25 6.25H9.25V7.75H6.25V6.25Z" fill="currentColor"/>
</svg>'
                    ];
                }

                $contactMenu['sub_items'][] = [
                    'key'         => 'dynamic_segments',
                    'label'       => __('Segments', 'fluent-crm'),
                    'permalink'   => $urlBase . 'contact-groups/dynamic-segments',
                    'description' => __('Manage your dynamic contact segments', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 11.5V13C8.80653 13 7.66193 13.4741 6.81802 14.318C5.97411 15.1619 5.5 16.3065 5.5 17.5H4C4 15.9087 4.63214 14.3826 5.75736 13.2574C6.88258 12.1321 8.4087 11.5 10 11.5V11.5ZM10 10.75C7.51375 10.75 5.5 8.73625 5.5 6.25C5.5 3.76375 7.51375 1.75 10 1.75C12.4862 1.75 14.5 3.76375 14.5 6.25C14.5 8.73625 12.4862 10.75 10 10.75ZM10 9.25C11.6575 9.25 13 7.9075 13 6.25C13 4.5925 11.6575 3.25 10 3.25C8.3425 3.25 7 4.5925 7 6.25C7 7.9075 8.3425 9.25 10 9.25ZM11.9462 15.109C11.8512 14.7088 11.8512 14.2919 11.9462 13.8917L11.2022 13.462L11.9522 12.163L12.6962 12.5927C12.9949 12.3099 13.3558 12.1013 13.75 11.9838V11.125H15.25V11.9838C15.649 12.1023 16.009 12.3137 16.3037 12.5927L17.0477 12.163L17.7977 13.462L17.0537 13.8917C17.1487 14.2917 17.1487 14.7083 17.0537 15.1083L17.7977 15.538L17.0477 16.837L16.3037 16.4072C16.0051 16.6901 15.6442 16.8987 15.25 17.0162V17.875H13.75V17.0162C13.3558 16.8987 12.9949 16.6901 12.6962 16.4072L11.9522 16.837L11.2022 15.538L11.9462 15.109V15.109ZM14.5 15.625C14.7984 15.625 15.0845 15.5065 15.2955 15.2955C15.5065 15.0845 15.625 14.7984 15.625 14.5C15.625 14.2016 15.5065 13.9155 15.2955 13.7045C15.0845 13.4935 14.7984 13.375 14.5 13.375C14.2016 13.375 13.9155 13.4935 13.7045 13.7045C13.4935 13.9155 13.375 14.2016 13.375 14.5C13.375 14.7984 13.4935 15.0845 13.7045 15.2955C13.9155 15.5065 14.2016 15.625 14.5 15.625Z" fill="currentColor"/>
                            </svg>'
                ];
            }

            $menuItems[] = $contactMenu;
        }

        if (in_array('fcrm_read_emails', $permissions)) {
            $campaignMenu = [
                'key'          => 'campaigns',
                'label'        => __('Emails', 'fluent-crm'),
                'permalink'    => $urlBase . 'email/campaigns',
                'layout_class' => 'fc_1_col_menu'
            ];

            $campaignMenu['sub_items'] = [
                [
                    'key'         => 'all_campaigns',
                    'label'       => __('Campaigns', 'fluent-crm'),
                    'permalink'   => $urlBase . 'email/campaigns',
                    'description' => __('Send Email Broadcast to your selected subscribers by tags, lists or segment', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8.125 13.125C8.125 13.125 12.5 13.75 14.375 15.625H15C15.3452 15.625 15.625 15.3452 15.625 15V11.2106C16.1641 11.0719 16.5625 10.5824 16.5625 10C16.5625 9.41756 16.1641 8.92812 15.625 8.78937V5C15.625 4.65482 15.3452 4.375 15 4.375H14.375C12.5 6.25 8.125 6.875 8.125 6.875H5.625C4.93464 6.875 4.375 7.43464 4.375 8.125V11.875C4.375 12.5654 4.93464 13.125 5.625 13.125H6.25L6.875 16.25H8.125V13.125ZM9.375 7.91325C9.80206 7.82162 10.3297 7.69496 10.8996 7.52733C11.9484 7.21884 13.2812 6.73289 14.375 5.98411V14.0159C13.2812 13.2671 11.9484 12.7812 10.8996 12.4727C10.3297 12.3051 9.80206 12.1784 9.375 12.0868V7.91325ZM5.625 8.125H8.125V11.875H5.625V8.125Z" fill="currentColor"/>
                        </svg>'
                ],
                [
                    'key'         => 'recurring_campaigns',
                    'label'       => __('Recurring Campaigns', 'fluent-crm'),
                    'permalink'   => $urlBase . 'email/recurring-campaigns',
                    'description' => __('Send automated daily or weekly emails of your dynamic data like new blog posts', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15.5313 12.3274C16.0179 11.1711 16.1299 9.89137 15.8516 8.66813C15.5732 7.4449 14.9185 6.33966 13.9795 5.5078C13.0404 4.67595 11.8643 4.15931 10.6164 4.03053C9.36853 3.90175 8.11167 4.16731 7.02253 4.78986L6.27853 3.48711C7.41675 2.83657 8.70567 2.49589 10.0167 2.49905C11.3277 2.5022 12.615 2.8491 13.75 3.50511C17.1175 5.44911 18.4075 9.61161 16.8378 13.0826L17.8443 13.6631L14.7205 15.3236L14.5968 11.7881L15.5313 12.3274V12.3274ZM4.46878 7.67286C3.98214 8.82914 3.87012 10.1089 4.14847 11.3321C4.42682 12.5553 5.08154 13.6606 6.02058 14.4924C6.95963 15.3243 8.13577 15.8409 9.38365 15.9697C10.6315 16.0985 11.8884 15.8329 12.9775 15.2104L13.7215 16.5131C12.5833 17.1637 11.2944 17.5043 9.98338 17.5012C8.67237 17.498 7.38511 17.1511 6.25003 16.4951C2.88253 14.5511 1.59253 10.3886 3.16228 6.91761L2.15503 6.33786L5.27878 4.67736L5.40253 8.21286L4.46803 7.67361L4.46878 7.67286ZM11.0613 12.1211L8.93803 10.0001L6.81703 12.1211L5.75653 11.0606L8.93878 7.87911L11.0605 10.0001L13.1823 7.87911L14.2428 8.93961L11.0605 12.1211H11.0613Z" fill="currentColor"/>
                        </svg>'
                ],
                [
                    'key'         => 'email_sequences',
                    'label'       => __('Sequences', 'fluent-crm'),
                    'permalink'   => $urlBase . 'email/sequences',
                    'description' => __('Create Multiple Emails and Send in order as a Drip Email Campaign', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.5 10.75H16V6.4285L10.054 11.7535L4 6.412V15.25H11.5V16.75H3.25C3.05109 16.75 2.86032 16.671 2.71967 16.5303C2.57902 16.3897 2.5 16.1989 2.5 16V4C2.5 3.80109 2.57902 3.61032 2.71967 3.46967C2.86032 3.32902 3.05109 3.25 3.25 3.25H16.75C16.9489 3.25 17.1397 3.32902 17.2803 3.46967C17.421 3.61032 17.5 3.80109 17.5 4V10.75ZM4.38325 4.75L10.0457 9.7465L15.6265 4.75H4.38325ZM16.75 14.5H19V16H16.75V18.25H15.25V16H13V14.5H15.25V12.25H16.75V14.5Z" fill="currentColor"/>
                        </svg>'
                ],
                [
                    'key'         => 'email_templates',
                    'label'       => __('Templates', 'fluent-crm'),
                    'permalink'   => $urlBase . 'email/templates',
                    'description' => __('Create email templates to use as a starting point in your emails', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4.75 7H15.25V4.75H4.75V7ZM11.5 15.25V8.5H4.75V15.25H11.5ZM13 15.25H15.25V8.5H13V15.25ZM4 3.25H16C16.1989 3.25 16.3897 3.32902 16.5303 3.46967C16.671 3.61032 16.75 3.80109 16.75 4V16C16.75 16.1989 16.671 16.3897 16.5303 16.5303C16.3897 16.671 16.1989 16.75 16 16.75H4C3.80109 16.75 3.61032 16.671 3.46967 16.5303C3.32902 16.3897 3.25 16.1989 3.25 16V4C3.25 3.80109 3.32902 3.61032 3.46967 3.46967C3.61032 3.32902 3.80109 3.25 4 3.25V3.25Z" fill="currentColor"/>
                        </svg>'
                ],
                [
                    'key'         => 'email_patterns',
                    'label'       => __('Patterns', 'fluent-crm'),
                    'permalink'   => $urlBase . 'email/patterns',
                    'description' => __('Create reusable email sections to use across your campaigns and templates', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 3.25H10C10.1989 3.25 10.3897 3.32902 10.5303 3.46967C10.671 3.61032 10.75 3.80109 10.75 4V10C10.75 10.1989 10.671 10.3897 10.5303 10.5303C10.3897 10.671 10.1989 10.75 10 10.75H4C3.80109 10.75 3.61032 10.671 3.46967 10.5303C3.32902 10.3897 3.25 10.1989 3.25 10V4C3.25 3.80109 3.32902 3.61032 3.46967 3.46967C3.61032 3.32902 3.80109 3.25 4 3.25ZM4.75 4.75V9.25H9.25V4.75H4.75ZM10 12.25H16C16.1989 12.25 16.3897 12.329 16.5303 12.4697C16.671 12.6103 16.75 12.8011 16.75 13V16C16.75 16.1989 16.671 16.3897 16.5303 16.5303C16.3897 16.671 16.1989 16.75 16 16.75H10C9.80109 16.75 9.61032 16.671 9.46967 16.5303C9.32902 16.3897 9.25 16.1989 9.25 16V13C9.25 12.8011 9.32902 12.6103 9.46967 12.4697C9.61032 12.329 9.80109 12.25 10 12.25ZM10.75 13.75V15.25H15.25V13.75H10.75ZM13 3.25H16C16.1989 3.25 16.3897 3.32902 16.5303 3.46967C16.671 3.61032 16.75 3.80109 16.75 4V7C16.75 7.19891 16.671 7.38968 16.5303 7.53033C16.3897 7.67098 16.1989 7.75 16 7.75H13C12.8011 7.75 12.6103 7.67098 12.4697 7.53033C12.329 7.38968 12.25 7.19891 12.25 7V4C12.25 3.80109 12.329 3.61032 12.4697 3.46967C12.6103 3.32902 12.8011 3.25 13 3.25ZM13.75 4.75V6.25H15.25V4.75H13.75ZM4 13.25H7C7.19891 13.25 7.38968 13.329 7.53033 13.4697C7.67098 13.6103 7.75 13.8011 7.75 14V17C7.75 17.1989 7.67098 17.3897 7.53033 17.5303C7.38968 17.671 7.19891 17.75 7 17.75H4C3.80109 17.75 3.61032 17.671 3.46967 17.5303C3.32902 17.3897 3.25 17.1989 3.25 17V14C3.25 13.8011 3.32902 13.6103 3.46967 13.4697C3.61032 13.329 3.80109 13.25 4 13.25Z" fill="currentColor"/>
                        </svg>'
                ],
                [
                    'key'         => 'all_emails',
                    'label'       => __('All Activities', 'fluent-crm'),
                    'permalink'   => $urlBase . 'email/all-emails',
                    'description' => __('Find all the emails that are being sent or scheduled by FluentCRM', 'fluent-crm'),
                    'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3.25 3.75H16.75C16.9489 3.75 17.1397 3.82902 17.2803 3.96967C17.421 4.11032 17.5 4.30109 17.5 4.5V15.5C17.5 15.6989 17.421 15.8897 17.2803 16.0303C17.1397 16.171 16.9489 16.25 16.75 16.25H3.25C3.05109 16.25 2.86032 16.171 2.71967 16.0303C2.57902 15.8897 2.5 15.6989 2.5 15.5V4.5C2.5 4.30109 2.57902 4.11032 2.71967 3.96967C2.86032 3.82902 3.05109 3.75 3.25 3.75ZM16 6.9285L10.054 12.2535L4 6.912V14.75H16V6.9285ZM4.38325 5.25L10.0457 10.2465L15.6265 5.25H4.38325Z" fill="currentColor"/>
                        </svg>'
                ]
            ];

            $menuItems[] = $campaignMenu;
        }

        if (in_array('fcrm_manage_forms', $permissions)) {
            $menuItems[] = [
                'key'       => 'forms',
                'label'     => __('Forms', 'fluent-crm'),
                'permalink' => $urlBase . 'forms'
            ];
        }

        if (in_array('fcrm_read_funnels', $permissions)) {
            $menuItems[] = [
                'key'       => 'funnels',
                'label'     => __('Automations', 'fluent-crm'),
                'permalink' => $urlBase . 'funnels'
            ];
        }

        /**
         * Filters the core menu items for FluentCRM.
         *
         * This filter allows modification of the core menu items in the FluentCRM admin menu.
         *
         * @param array $menuItems The current menu items.
         * @param array $permissions The permissions associated with the menu items.
         * @return array The filtered menu items.
         */
        $menuItems = apply_filters('fluent_crm/core_menu_items', $menuItems, $permissions, $urlBase);

        if (in_array('fcrm_manage_settings', $permissions)) {

            $menuItems[] = [
                'key'       => 'reports',
                'label'     => __('Reports', 'fluent-crm'),
                'permalink' => $urlBase . 'reports',
                'layout_class' => 'fc_1_col_menu',
                'sub_items' => [
                    [
                        'key'       => 'reports_contacts',
                        'label'     => __('Contacts', 'fluent-crm'),
                        'permalink' => $urlBase . 'reports?tab=contacts',
                        'icon'        => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 3.75C4.65482 3.75 4.375 4.02982 4.375 4.375V5.625H5.625V5H14.375V15H5.625V14.375H4.375V15.625C4.375 15.9702 4.65482 16.25 5 16.25H15C15.3452 16.25 15.625 15.9702 15.625 15.625V4.375C15.625 4.02982 15.3452 3.75 15 3.75H5ZM8.125 12.5C8.125 11.4644 8.96444 10.625 10 10.625C11.0356 10.625 11.875 11.4644 11.875 12.5H8.125ZM10 10C9.30963 10 8.75 9.44037 8.75 8.75C8.75 8.05964 9.30963 7.5 10 7.5C10.6904 7.5 11.25 8.05964 11.25 8.75C11.25 9.44037 10.6904 10 10 10ZM6.25 8.125V6.875H3.75V8.125H6.25ZM6.25 9.375V10.625H3.75V9.375H6.25ZM6.25 13.125V11.875H3.75V13.125H6.25Z" fill="currentColor"/>
                        </svg>'
                    ],
                    [
                        'key'       => 'reports_emails',
                        'label'     => __('Emails', 'fluent-crm'),
                        'permalink' => $urlBase . 'reports?tab=emails',
                        'icon'      => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.25 3.75H16.75C16.9489 3.75 17.1397 3.82902 17.2803 3.96967C17.421 4.11032 17.5 4.30109 17.5 4.5V15.5C17.5 15.6989 17.421 15.8897 17.2803 16.0303C17.1397 16.171 16.9489 16.25 16.75 16.25H3.25C3.05109 16.25 2.86032 16.171 2.71967 16.0303C2.57902 15.8897 2.5 15.6989 2.5 15.5V4.5C2.5 4.30109 2.57902 4.11032 2.71967 3.96967C2.86032 3.82902 3.05109 3.75 3.25 3.75ZM16 6.9285L10.054 12.2535L4 6.912V14.75H16V6.9285ZM4.38325 5.25L10.0457 10.2465L15.6265 5.25H4.38325Z" fill="currentColor"/></svg>',
                    ],
                ],
            ];

            $menuItems[] = [
                'key'       => 'settings',
                'label'     => __('Settings', 'fluent-crm'),
                'permalink' => $urlBase . 'settings'
            ];
        }

        /**
         * Filter the menu items for FluentCRM.
         *
         * This filter allows modification of the menu items in the FluentCRM admin menu.
         *
         * @param array $menuItems An array of menu items for FluentCRM.
         * @return array The filtered array of menu items.
         */
        return apply_filters('fluent_crm/menu_items', $menuItems);

    }

    public function mayBeRedirect()
    {
        if (fluentcrm_get_option('fluentcrm_setup_wizard_ran') != 'yes' && !isset($_GET['setup_complete'])) {
            if (current_user_can('manage_options')) {
                wp_safe_redirect(admin_url('index.php?page=fluentcrm-setup'));
                exit();
            }
        }
    }

    public function loadAssets()
    {
        if (!isset($_GET['page']) || $_GET['page'] != 'fluentcrm-admin') {
            return;
        }
        /*
         * LearnPress loads all their JS weired way on every Admin Pages
         */
        if (defined('LEARNPRESS_VERSION')) {
            add_filter('learn-press/admin-default-scripts', function ($scripts) {
                return [];
            });
        }

        $this->loadCssJs();
    }

    public function loadCssJs()
    {
        $this->unloadOtherScripts();

        // Inject Vite client for HMR in development mode
        add_action('admin_head', function () {
            \FluentCrm\App\Vite::injectViteClient();
        }, 1);

        // NOTE: HTML-level <link rel="modulepreload"> hints were attempted
        // and reverted. Hosting layers that fingerprint asset URLs (the
        // 3.0.5 trigger) would rewrite the preload hrefs but NOT the
        // chunk-to-chunk import URLs Vite bakes at build time — leading
        // to URL divergence and module duplication at the chunk level
        // (the same root cause as 3.0.5, one level down the chunk tree).
        // Route preloading now happens via Vite-baked dynamic import()
        // calls in resources/admin/app.js's preload callback, which
        // guarantees URL parity. See docs/app-entry-refactor-roadmap.md.

        wp_enqueue_script('fluentcrm_global_admin', fluentCrmMix('admin/js/global_admin.js'), array('jquery'), $this->version);
        wp_enqueue_script('fluentcrm_admin_app_boot', fluentCrmMix('admin/js/boot.js'), array(), $this->version);

        // Ensure block editor styles are loaded
        // wp_enqueue_style('wp-block-editor'); // may not needed as we are using editor styles in the react app

        $this->emailBuilderBlockInit();

        /**
         * Action Hook when global admin scripts are loaded
         */
        do_action('fluent_crm/global_app_boot_loaded');

        $footerHook = 'admin_footer';
        if (!is_admin()) {
            $footerHook = 'wp_footer';
        }

        add_action($footerHook, function () {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (_ && _.noConflict) {
                        if (window._.each.length == 2) {
                            window.lodash = _.noConflict();
                            console.log('_.noConflict() Loaded');
                        }
                    }
                });
            </script>
            <?php
        }, 99999);

        $this->loadCss();

        wp_enqueue_script('dompurify', fluentCrmMix('libs/purify/purify.min.js'), [], $this->version, true);

        $inlineCss = Helper::generateThemePrefCss();
        wp_add_inline_style('fluentcrm_app_global', $inlineCss);

        remove_action('admin_print_scripts', 'print_emoji_detection_script');

        add_filter('tiny_mce_plugins', function ($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, array('wpemoji'));
            }
            return array();
        });

        wp_localize_script('fluentcrm_admin_app_boot', 'fcAdmin', $this->getAdminVars());
    }

    public function getAdminVars()
    {
        $app = FluentCrm();

        $tags = Tag::orderBy('title', 'ASC')->get();
        $formattedTags = [];
        foreach ($tags as $tag) {
            $formattedTags[] = [
                'id'    => strval($tag->id),
                'title' => $tag->title,
                'slug'  => $tag->slug
            ];
        }

        $lists = Lists::orderBy('title', 'ASC')->get();
        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[] = [
                'id'    => strval($list->id),
                'title' => $list->title,
                'slug'  => $list->slug
            ];
        }

        $currentUser = wp_get_current_user();

        $activatedFeatures = Helper::getActivatedFeatures();

        $postTypes = get_post_types(['public' => true], 'objects');
        unset($postTypes['attachment']);

        $formattedPostTypes = [];

        foreach ($postTypes as $postTypeName => $postType) {
            $formattedPostTypes[] = [
                'id'    => $postTypeName,
                'title' => $postType->label
            ];
        }

        $blockEditorUrl = site_url('?fluent_crm_block_editor=1');

        if (current_user_can('edit_posts')) {
            $blockEditorUrl = admin_url('post-new.php?fluent_crm_block_editor=1');
        }

        $existingSettings = get_option('fluentcrm-global-settings');
        $data = array(
            'business_settings'                   => [
                'business_name'    => Arr::get($existingSettings, 'business_settings.business_name'),
                'business_email'   => Arr::get($existingSettings, 'business_settings.admin_email'),
                'business_address' => Arr::get($existingSettings, 'business_settings.business_address')
            ],
            'images_url'                          => fluentCrmMix('images'),
            'ajaxurl'                             => admin_url('admin-ajax.php'),
            'ajax_nonce'                          => wp_create_nonce('fluentcrm_ajax_nonce'),
            'admin_url'                           => admin_url('admin.php?page=fluentcrm-admin#/'),
            'site_url'                            => site_url('/'),
            'slug'                                => FLUENTCRM,
            'rest'                                => $this->getRestInfo($app),
            /**
             * Filters the list of countries in FluentCRM.
             *
             * This filter allows you to modify the list of countries used in the application.
             *
             * @param array An array of countries.
             */
            'countries'                           => apply_filters('fluent_crm/countries', []),
            'contact_types'                       => fluentcrm_contact_types(),
            'purchase_providers'                  => Helper::getPurchaseHistoryProviders(),
            /**
             * Filters the form submission providers in FluentCRM.
             *
             * This filter allows you to modify the list of form submission providers.
             *
             * @param array An array of form submission providers.
             */
            'form_submission_providers'           => apply_filters('fluent_crm/form_submission_providers', []),
            /**
             * Filters the list of support ticket providers in FluentCRM.
             *
             * This filter allows you to modify the array of support ticket providers.
             *
             * @param array An array of support ticket providers.
             */
            'support_tickets_providers'           => apply_filters('fluentcrm-support_tickets_providers', []),
            'activity_types'                      => fluentcrm_activity_types(),
            'profile_sections'                    => Helper::getProfileSections(),
            'globalSmartCodes'                    => Helper::getGlobalSmartCodes(),
            'extendedSmartCodes'                  => Helper::getExtendedSmartCodes(),
            'addons'                              => $activatedFeatures,
            'email_template_designs'              => Helper::getEmailDesignTemplates(),
            'contact_prefixes'                    => Helper::getContactPrefixes(),
            'contact_custom_fields'               => fluentcrm_get_custom_contact_fields(),
            'server_time'                         => current_time('mysql'),
            // Only users who can manage settings can hit the repair endpoint
            // (SettingsPolicy → fcrm_manage_settings), so only they get a real
            // health signal. Everyone else gets `true` — and the health check is
            // skipped via short-circuit — so the SPA never fires a repair the
            // server would just reject. For permitted users this is a cheap
            // cached read (one fc_meta option) on the happy path; when it is
            // false, app.js fires a one-off background repair on boot.
            'db_index_health_ok'                  => !PermissionManager::currentUserCan('fcrm_manage_settings') || !DbPerformanceService::hasBrokenIndex(false),
            'crm_pro_url'                         => 'https://fluentcrm.com/?utm_source=plugin&utm_medium=admin&utm_campaign=promo',
            /**
             * Determine if request verification is required in FluentCRM.
             *
             * This filter allows you to specify whether request verification is required.
             * By default, it is set to false.
             *
             * @param bool Whether request verification is required. Default false.
             */
            'require_verify_request'              => apply_filters('fluentcrm_is_require_verify', false),
            'trans'                               => TransStrings::getStrings(),
            'has_fluentsmtp'                      => defined('FLUENTMAIL'),
            /**
             * Determine if FluentMail suggestion should be disabled in FluentCRM.
             *
             * This filter allows customization of the FluentMail suggestion feature in FluentCRM.
             *
             * @return bool True if FluentMail suggestion is disabled, false otherwise.
             */
            'disable_fluentmail_suggest'          => apply_filters('fluent_crm/fluentmail_suggest', defined('FLUENTMAIL')),
            'verified_senders'                    => $this->getVerifiedSenders(),
            'has_smart_link'                      => $this->hasSmartLink(),
            'auth'                                => [
                'permissions' => PermissionManager::currentUserPermissions(),
                'first_name'  => $currentUser->first_name,
                'last_name'   => $currentUser->last_name,
                'email'       => $currentUser->user_email,
                'avatar'      => fluentcrmGetAvatarHtml($currentUser->user_email, $currentUser->display_name, 128),
                'user_id'     => $currentUser->ID
            ],
            'is_rtl'                              => fluentcrm_is_rtl(),
            'icons'                               => [
                'trigger_icon' => 'fc-icon-trigger',
            ],
            /**
             * Define the funnel category icons in FluentCRM.
             *
             * This filter allows you to change the icons used for different funnel categories in FluentCRM.
             *
             * @param array An associative array where the keys are funnel categories and values are arrays with:
             *    - 'svg'  => (string) Inline SVG markup (highest priority)
             *    - 'icon' => (string) CSS icon class name (fallback)
             */
            'funnel_cat_icons'                    => apply_filters('fluent_crm/funnel_icons', [
                'crm'                  => ['svg' => $this->getFunnelCatSvgIcon('crm')],
                'wordpresstriggers'    => ['svg' => $this->getFunnelCatSvgIcon('wordpresstriggers')],
                'woocommerce'          => ['svg' => $this->getFunnelCatSvgIcon('woocommerce')],
                'lifterlms'            => ['svg'  => $this->getFunnelCatSvgIcon('lifterlms')],
                'easydigitaldownloads' => ['icon' => 'fc-icon-edd'],
                'learndash'            => ['svg'  => $this->getFunnelCatSvgIcon('learndash')],
                'memberpress'          => ['svg' => $this->getFunnelCatSvgIcon('memberpress')],
                // Keep both keys for backward compatibility with existing filter integrations.
                'paidmembershippro'    => ['svg'  => $this->getFunnelCatSvgIcon('paidmembershipspro')],
                'paidmembershipspro'   => ['svg'  => $this->getFunnelCatSvgIcon('paidmembershipspro')],
                'restrictcontentpro'   => ['icon' => 'fc-icon-restric_content'],
                'tutorlms'             => ['svg' => $this->getFunnelCatSvgIcon('tutorlms')],
                'wishlistmember'       => ['svg' => $this->getFunnelCatSvgIcon('wishlistmember')],
                'surecart'             => ['svg' => $this->getFunnelCatSvgIcon('surecart')],
                'fluentforms'          => ['svg' => $this->getFunnelCatSvgIcon('fluentforms')],
                'fluentboards'         => ['svg' => $this->getFunnelCatSvgIcon('fluentboards')],
                'community'            => ['svg' => $this->getFunnelCatSvgIcon('community')],
            ]),
            'advanced_filter_options'             => Helper::getAdvancedFilterOptions(),
            /**
             * Modify the advanced filter suggestions in FluentCRM.
             *
             * This filter allows you to modify the suggestions provided for the advanced filter.
             * @return array Modified array of suggestions for the advanced filter.
             */
            'advanced_filter_suggestions'         => apply_filters('fluentcrm_advanced_filter_suggestions', []),
            /**
             * Define the commerce provider in FluentCRM.
             *
             * This filter allows you to change the commerce provider used in FluentCRM.
             *
             * @param string The current commerce provider. Default is an empty string.
             */
            'commerce_provider'                   => apply_filters('fluentcrm_commerce_provider', ''),
            /**
             * Define the currency sign used in FluentCRM.
             *
             * This filter allows you to change the currency sign used in the FluentCRM plugin.
             *
             * @param string The current currency sign. Default is an empty string.
             */
            'commerce_currency_sign'              => apply_filters('fluentcrm_currency_sign', ''),
            'disable_time_diff'                   => Helper::isExperimentalEnabled('classic_date_time'),
            'wp_date_time_format'                 => $this->getDefaultDateTimeFormatForMoment(),
            'disable_ai'                          => Helper::isExperimentalEnabled('disable_visual_ai'),
            'app_version'                         => FLUENTCRM_PLUGIN_VERSION,
            'available_tags'                      => $formattedTags,
            'available_lists'                     => $formattedLists,
            'available_funnel_label_colors'       => Helper::funnelLabelColors(),
            'available_contact_statuses'          => fluentcrm_subscriber_statuses(true),
            'available_contact_editable_statuses' => fluentcrm_subscriber_editable_statuses(true),
            'available_sms_statuses'              => fluentcrm_subscriber_sms_statuses(true),
            'available_contact_types'             => fluentcrm_contact_types(true),
            'available_custom_fields'             => fluentcrm_get_option('contact_custom_fields', []),
            'contact_sample_csv'                  => fluentCrmMix('sample.csv'),
            'global_email_footer'                 => Helper::getEmailFooterContent(),
            'experimentals'                       => Helper::getExperimentalSettings(),
            'publicPostTypes'                     => $formattedPostTypes,
            'has_woo'                             => defined('WC_PLUGIN_FILE'),
            'debugs'                              => [
                '_fc_last_automation_processor' => get_option('_fc_last_funnel_processor_ran'),
                '_fcrm_last_scheduler'          => fluentCrmGetOptionCache('_fcrm_last_scheduler'),
                '_fcrm_last_scheduler_for_sms'  => fluentCrmGetOptionCache('_fcrm_last_scheduler_for_sms'),
            ],
            /**
             * Determine the custom contact bulk actions in FluentCRM.
             *
             * This filter allows you to add or modify the bulk actions available for contacts in the FluentCRM admin interface.
             *
             * @param array An array of custom bulk actions for contacts.
             */
            'custom_contact_bulk_actions'         => apply_filters('fluent_crm/custom_contact_bulk_actions', []),
            'crm_editor_frame'                    => $blockEditorUrl,
        );

        if (Arr::get($activatedFeatures, 'company_module')) {
            $data['company_categories'] = Helper::companyCategories();
            $data['company_types'] = Helper::companyTypes();
            $data['company_profile_sections'] = Helper::getCompanyProfileSections();
            $data['company_custom_fields'] = fluentcrm_get_custom_company_fields();
        }
        /**
         * Filter the admin variables for FluentCRM.
         *
         * This filter allows modification of the admin variables used in FluentCRM.
         *
         * @param array $data The array of admin variables.
         * @return array The filtered array of admin variables.
         */
        return apply_filters('fluent_crm/admin_vars', $data);
    }

    public function loadCss()
    {
        $isRtl = fluentcrm_is_rtl();

        $v3Css = 'admin/css/app3.css';
        $appGlobalCss = 'admin/css/app_global.css';
        $vendorStyle = 'admin/css/style.css';

        if ($isRtl) {
            // Keep loading the base bundles in RTL mode; dedicated *-rtl build files
            // are not produced in this Vite pipeline. RTL-specific tweaks live in
            // admin_rtl.css and are loaded as an additive override below.
            // $appGlobalCss = 'admin/css/app_global-rtl.css'; // TODO:: The CSS file does not exist.
            wp_enqueue_style('fluentcrm_app_rtl', fluentCrmMix('admin/css/admin_rtl.css'), [], $this->version);
            // style.css works for both LTR and RTL — Element Plus handles RTL natively via dir="rtl"
        }

        // Legacy fluentcrm-admin.css eliminated — all styles now in app3.css.
        // Register empty handle for backward compatibility (add-ons may depend on it).
        wp_register_style('fluentcrm_admin_app', false);
        wp_enqueue_style('fluentcrm_admin_app');

        wp_enqueue_style('fluentcrm_app_global', fluentCrmMix($appGlobalCss), array(), $this->version);
        wp_enqueue_style('fluentcrm_admin_app1', fluentCrmMix($v3Css), array(), $this->version);
        // style.css is a build-only artifact (merged Vue/Element Plus chunk CSS).
        // In dev mode, Vite injects this CSS via HMR automatically.
        // Element Plus CSS is wrapped in @layer, so overrides always win regardless of load order.
        if (!\FluentCrm\App\Vite::underDevelopment()) {
            wp_enqueue_style('fluentcrm_vendor', fluentCrmMix($vendorStyle), array(), $this->version);
        }
    }

    protected function getRestInfo($app)
    {
        $ns = $app->config->get('app.rest_namespace');
        $v = $app->config->get('app.rest_version');

        $restUrl = rest_url($ns . '/' . $v);
        $restUrl = rtrim($restUrl, '/\\');
        return [
            'base_url'  => esc_url_raw(rest_url()),
            'url'       => $restUrl,
            'nonce'     => wp_create_nonce('wp_rest'),
            'namespace' => $ns,
            'version'   => $v,
        ];
    }

    public function emailBuilderBlockInit()
    {
        if (function_exists('wp_enqueue_media')) {
            // Editor default styles.
            add_filter('user_can_richedit', '__return_true');
            wp_tinymce_inline_scripts();
            wp_enqueue_editor();
            wp_enqueue_media();
        }
    }

    private function getMenuIcon()
    {
        return 'data:image/svg+xml;base64,' . base64_encode('<?xml version="1.0" encoding="UTF-8" standalone="no"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd"><svg width="100%" height="100%" viewBox="0 0 300 235" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve" xmlns:serif="http://www.serif.com/" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;"><g><path d="M300,0c0,0 -211.047,56.55 -279.113,74.788c-12.32,3.301 -20.887,14.466 -20.887,27.221l0,38.719c0,0 169.388,-45.387 253.602,-67.952c27.368,-7.333 46.398,-32.134 46.398,-60.467c0,-7.221 0,-12.309 0,-12.309Z"/><path d="M184.856,124.521c0,-0 -115.6,30.975 -163.969,43.935c-12.32,3.302 -20.887,14.466 -20.887,27.221l0,38.719c0,0 83.701,-22.427 138.458,-37.099c27.368,-7.334 46.398,-32.134 46.398,-60.467c0,-7.221 0,-12.309 0,-12.309Z"/></g></svg>');
    }

    private function getVerifiedSenders()
    {
        $verifiedSenders = [];
        if (defined('FLUENTMAIL')) {
            $smtpSettings = get_option('fluentmail-settings', []);
            $mappings = (array) Arr::get($smtpSettings, 'mappings', []);
            if ($mappings) {
                $verifiedSenders = array_keys($mappings);
            }
        }

        /**
         * Determine the list of verified email senders in FluentCRM.
         *
         * This filter allows modification of the array of verified email senders.
         *
         * @param array $verifiedSenders An array of verified email senders.
         * @return array Filtered array of verified email senders.
         */
        return apply_filters('fluent_crm/verfied_email_senders', $verifiedSenders);
    }

    private function hasSmartLink()
    {
        if (!defined('FLUENTCAMPAIGN')) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fc_smart_links';
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        if ($wpdb->get_var($query) == $table_name) {
            return true;
        }

        return false;
    }

    private function getDefaultDateTimeFormatForMoment()
    {

        $phpFormat = get_option('date_format') . ' ' . get_option('time_format');

        $replacements = [
            'A' => 'A',      // for the sake of escaping below
            'a' => 'a',      // for the sake of escaping below
            'B' => '',       // Swatch internet time (.beats), no equivalent
            'c' => 'YYYY-MM-DD[T]HH:mm:ssZ', // ISO 8601
            'D' => 'ddd',
            'd' => 'DD',
            'e' => 'zz',     // deprecated since version 1.6.0 of moment.js
            'F' => 'MMMM',
            'G' => 'H',
            'g' => 'h',
            'H' => 'HH',
            'h' => 'hh',
            'I' => '',       // Daylight Saving Time? => moment().isDST();
            'i' => 'mm',
            'j' => 'D',
            'L' => '',       // Leap year? => moment().isLeapYear();
            'l' => 'dddd',
            'M' => 'MMM',
            'm' => 'MM',
            'N' => 'E',
            'n' => 'M',
            'O' => 'ZZ',
            'o' => 'YYYY',
            'P' => 'Z',
            'r' => 'ddd, DD MMM YYYY HH:mm:ss ZZ', // RFC 2822
            'S' => 'o',
            's' => 'ss',
            'T' => 'z',      // deprecated since version 1.6.0 of moment.js
            't' => '',       // days in the month => moment().daysInMonth();
            'U' => 'X',
            'u' => 'SSSSSS', // microseconds
            'v' => 'SSS',    // milliseconds (from PHP 7.0.0)
            'W' => 'W',      // for the sake of escaping below
            'w' => 'e',
            'Y' => 'YYYY',
            'y' => 'YY',
            'Z' => '',       // time zone offset in minutes => moment().zone();
            'z' => 'DDD',
        ];

        // Converts escaped characters.
        foreach ($replacements as $from => $to) {
            $replacements['\\' . $from] = '[' . $from . ']';
        }

        $format = strtr($phpFormat, $replacements);

        /**
         * Determine the date and time format used in FluentCRM.
         *
         * This filter allows you to modify the date and time format used in FluentCRM.
         *
         * @param string $format The current date and time format.
         * @return string The modified date and time format.
         */
        return apply_filters('fluent_crm/moment_date_time_format', $format);
    }

    private function unloadOtherScripts()
    {
        /**
         * Determine whether to skip the no-conflict mode in FluentCRM.
         *
         * This filter allows you to skip the no-conflict mode by returning true.
         * By default, it returns false, meaning the no-conflict mode is not skipped.
         *
         * @return bool Whether to skip the no-conflict mode. Default is false.
         */
        $isSkip = apply_filters('fluent_crm/skip_no_conflict', false);
        if ($isSkip) {
            return;
        }

        /**
         * Define the list of approved slugs for FluentCRM assets.
         *
         * This filter allows modification of the list of slugs that are approved for FluentCRM assets.
         *
         * @param array $approvedSlugs An array of approved slugs for FluentCRM assets.
         */
        $approvedSlugs = apply_filters('fluent_crm_asset_listed_slugs', [
            '\/gutenberg\/'
        ]);
        $approvedSlugs[] = 'fluent-crm';
        $approvedSlugs = array_unique($approvedSlugs);
        $approvedSlugs = implode('|', $approvedSlugs);

        $pluginUrl = str_replace(['http:', 'https:'], '', plugins_url());

        add_filter('script_loader_src', function ($src, $handle) use ($approvedSlugs, $pluginUrl) {
            if (!$src) {
                return $src;
            }

            $willSkip = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
            if ($willSkip) {
                return false;
            }
            return $src;
        }, 1, 2);

        add_action('wp_print_scripts', function () {
            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }

            /**
             * Define the list of approved slugs for FluentCRM assets.
             *
             * This filter allows modification of the list of slugs that are approved for FluentCRM assets.
             *
             * @param array $approvedSlugs An array of approved slugs for FluentCRM assets.
             */
            $approvedSlugs = apply_filters('fluent_crm_asset_listed_slugs', [
                '\/gutenberg\/'
            ]);

            $approvedSlugs[] = 'fluent-crm';

            $approvedSlugs = array_unique($approvedSlugs);

            $approvedSlugs = implode('|', $approvedSlugs);

            $pluginUrl = plugins_url();

            $pluginUrl = str_replace(['http:', 'https:'], '', $pluginUrl);

            foreach ($wp_scripts->queue as $script) {
                if (empty($wp_scripts->registered[$script]) || empty($wp_scripts->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_scripts->registered[$script]->src;
                $isMatched = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
                if (!$isMatched) {
                    continue;
                }

                wp_dequeue_script($wp_scripts->registered[$script]->handle);
            }
        }, 1);
    }

    private function getFunnelCatSvgIcon($key)
    {
        $icons = [
            'crm'          => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M19 2.8C19 1.80658 18.1934 1 17.2 1H2.8C1.80658 1 1 1.80658 1 2.8V17.2C1 18.1934 1.80658 19 2.8 19H17.2C18.1934 19 19 18.1934 19 17.2V2.8Z" fill="#7742E6"/><path fill-rule="evenodd" clip-rule="evenodd" d="M16.0575 5.26758C16.0575 5.26758 8.29299 7.34802 5.19609 8.17788C4.45689 8.37594 3.94287 9.04578 3.94287 9.81108C3.94287 10.3928 3.94287 10.9504 3.94287 10.9504C3.94287 10.9504 10.1801 9.27918 13.7037 8.33502C15.0921 7.96302 16.0575 6.70488 16.0575 5.26758Z" fill="white"/><path fill-rule="evenodd" clip-rule="evenodd" d="M11.4078 10.2969C11.4078 10.2969 7.32225 11.3916 5.19609 11.9613C4.45689 12.1594 3.94287 12.8292 3.94287 13.5945C3.94287 14.1763 3.94287 14.7339 3.94287 14.7339C3.94287 14.7339 6.86613 13.9506 9.05397 13.3644C10.4424 12.9924 11.4078 11.7342 11.4078 10.2969V10.2969Z" fill="white"/></svg>',
            'surecart'     => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M10 19C14.9706 19 19 14.9706 19 10C19 5.02944 14.9706 1 10 1C5.02944 1 1 5.02944 1 10C1 14.9706 5.02944 19 10 19ZM10.0388 5.5C9.31611 5.5 8.31603 5.91328 7.80503 6.42308L6.41716 7.8077H13.3348L15.648 5.5H10.0388ZM12.1833 13.5769C11.6723 14.0867 10.6723 14.5001 9.94959 14.5001H4.34041L6.65351 12.1923H13.5712L12.1833 13.5769ZM14.4316 8.96155H5.26312L4.83004 9.39424C3.80455 10.3173 4.1087 11.0385 5.54484 11.0385H14.7382L15.1714 10.6058C16.1869 9.68814 15.8678 8.96155 14.4316 8.96155Z" fill="#01824C"/></svg>',
            'fluentboards' => '<svg width="24" height="24" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 25.6C0 11.4615 11.4615 0 25.6 0H230.4C244.538 0 256 11.4615 256 25.6V230.4C256 244.538 244.538 256 230.4 256H25.6C11.4615 256 0 244.538 0 230.4V25.6ZM140.8 89.6C140.8 75.4615 152.262 64 166.4 64H186.88C189.708 64 192 66.2923 192 69.12V166.4C192 180.538 180.538 192 166.4 192H145.92C143.092 192 140.8 189.708 140.8 186.88V89.6ZM89.6 64C75.4615 64 64 75.4615 64 89.5999V148.48C64 151.308 66.2923 153.6 69.12 153.6H89.6C103.739 153.6 115.2 142.138 115.2 128V69.12C115.2 66.2923 112.908 64 110.08 64H89.6Z" fill="#6268F1"/></svg>',
            'community'    => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="18" height="18" rx="1.8" fill="#4A5FE0"/><path d="M7.46851 12.3184L14.609 8.1981C15.2691 7.81719 16.113 8.04354 16.494 8.70367L17.1837 9.89894L11.2384 13.3295C9.91819 14.0913 8.23033 13.6386 7.46851 12.3184Z" fill="white"/><path d="M12.6421 7.68945L5.50161 11.8097C4.84148 12.1906 3.99755 11.9643 3.61664 11.3041L2.92694 10.1089L8.87215 6.67832C10.1924 5.91649 11.8803 6.36919 12.6421 7.68945Z" fill="white"/></svg>',
            'fluentforms'  => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="1" y="1" width="18" height="18" rx="1.8" fill="#089DFF"/><path d="M3.44189 7.4219C3.44189 6.00558 4.59005 4.85742 6.00637 4.85742H16.1649C16.1649 6.27374 15.0167 7.4219 13.6004 7.4219H3.44189Z" fill="white"/><path d="M3.44189 11.4121C3.44189 9.99581 4.59005 8.84766 6.00637 8.84766H16.1649C16.1649 10.264 15.0167 11.4121 13.6004 11.4121H3.44189Z" fill="white"/><path d="M5.42017 15.4004C5.42017 13.9841 6.56832 12.8359 7.98464 12.8359H13.9022C13.9022 14.2523 12.754 15.4004 11.3377 15.4004H5.42017Z" fill="white"/></svg>',
            'learndash'  => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.99966 5.63868C9.75449 5.63813 9.5116 5.68603 9.28499 5.77963C9.05837 5.87319 8.85247 6.01062 8.67908 6.18397C8.50573 6.35735 8.36831 6.56325 8.27474 6.78987C8.18114 7.01648 8.13324 7.25934 8.13379 7.50454V18.2941C8.13637 18.3847 8.1735 18.4709 8.23761 18.5351C8.30171 18.5992 8.38792 18.6363 8.47859 18.6389C9.37634 18.6372 10.2369 18.2799 10.8717 17.6451C11.5066 17.0102 11.8639 16.1497 11.8655 15.2519V7.50454C11.866 7.25934 11.8181 7.01648 11.7246 6.78987C11.631 6.56325 11.4936 6.35735 11.3202 6.18397C11.1468 6.01062 10.9409 5.87319 10.7143 5.77963C10.4877 5.68603 10.2448 5.63813 9.99966 5.63868Z" fill="var(--fc-primary-text)"/><path d="M4.58486 10.9121C4.33968 10.9116 4.09681 10.9595 3.87019 11.0531C3.64358 11.1466 3.43767 11.2841 3.26431 11.4574C3.09094 11.6308 2.95352 11.8367 2.85994 12.0633C2.76636 12.2899 2.71846 12.5328 2.719 12.778V18.4972C2.72155 18.5879 2.7587 18.6741 2.82281 18.7382C2.88692 18.8023 2.97314 18.8395 3.06378 18.842C3.96156 18.8404 4.8221 18.4831 5.45693 17.8482C6.09176 17.2134 6.44911 16.3529 6.45072 15.4551V12.778C6.45124 12.5328 6.40337 12.2899 6.30978 12.0633C6.2162 11.8367 6.07878 11.6308 5.90542 11.4574C5.73205 11.2841 5.52615 11.1466 5.29953 11.0531C5.07291 10.9595 4.83004 10.9116 4.58486 10.9121Z" fill="var(--fc-primary-text)"/><path d="M15.4152 1.15625C15.17 1.15572 14.9271 1.20362 14.7005 1.2972C14.4739 1.39077 14.268 1.52819 14.0946 1.70156C13.9213 1.87493 13.7838 2.08083 13.6903 2.30745C13.5967 2.53407 13.5488 2.77694 13.5493 3.02212V18.4155C13.5519 18.5061 13.589 18.5923 13.6531 18.6564C13.7173 18.7205 13.8035 18.7577 13.8941 18.7603C14.7919 18.7586 15.6525 18.4013 16.2873 17.7665C16.9221 17.1316 17.2794 16.2711 17.2811 15.3733V3.02212C17.2816 2.77694 17.2337 2.53407 17.1401 2.30745C17.0465 2.08083 16.9091 1.87493 16.7358 1.70156C16.5624 1.52819 16.3565 1.39077 16.1299 1.2972C15.9032 1.20362 15.6604 1.15572 15.4152 1.15625Z" fill="var(--fc-primary-text)"/></svg>',
            'lifterlms'    => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.15406 11.791L6.6759 11.5178L5.39133 13.7311C4.23023 12.119 3.99792 9.94627 4.89962 8.07448L4.91317 8.06072C4.96781 7.93789 5.03621 7.82841 5.09084 7.71914C6.0063 6.13408 7.60512 5.16399 9.29924 4.98631C9.59995 4.57655 9.92797 4.22121 10.2969 3.87963C7.8783 3.74304 5.45952 4.94523 4.16162 7.18634C2.49461 10.0831 3.25992 13.7174 5.82863 15.7126L7.11298 11.9002C7.12675 11.8731 7.14051 11.8318 7.15406 11.791ZM14.9699 8.25194C15.6532 9.82344 15.6122 11.6815 14.6965 13.2667C14.6421 13.376 14.5737 13.4851 14.5053 13.5946L14.4917 13.6085C13.3166 15.33 11.3216 16.2181 9.34032 16.0131L10.6249 13.7995L10.1465 13.5262C10.1192 13.5539 10.0919 13.581 10.0646 13.6085L7.41369 16.6281C10.4199 17.858 13.9587 16.6965 15.6257 13.7995C16.9238 11.5589 16.7599 8.8669 15.4209 6.84476C15.3115 7.32293 15.1749 7.80109 14.9699 8.25194Z" fill="#466DD8"/><path fill-rule="evenodd" clip-rule="evenodd" d="M10.529 11.7098C10.1872 12.1609 9.64087 12.3934 9.05344 12.448C8.87577 12.4618 8.71165 12.3659 8.62969 12.202C8.38362 11.6689 8.30188 11.0815 8.53419 10.5623L5.85579 9.01828C5.67833 8.90901 5.59638 8.73134 5.65101 8.54012C5.69188 8.34868 5.85579 8.21188 6.04722 8.21188L8.76628 8.11659C8.97127 7.28287 9.31285 6.49045 9.80478 5.75245C10.5836 4.59113 11.6903 3.73031 12.9884 3.21106C13.3029 3.08802 13.617 2.97896 13.9314 2.8968C14.1635 2.84216 14.3958 2.97896 14.4505 3.19772C14.5462 3.51176 14.6146 3.83979 14.6692 4.1676C14.8604 5.56143 14.6555 6.95506 14.0545 8.21188C13.6718 9.00473 13.1388 9.68788 12.5238 10.2889L13.7946 12.6801C13.8904 12.8578 13.863 13.0628 13.7127 13.1994C13.5761 13.3362 13.3709 13.3497 13.2072 13.254L10.529 11.7098ZM12.5512 5.98475C12.9611 6.23082 13.0977 6.75007 12.8656 7.15983C12.6331 7.56981 12.1005 7.70661 11.6903 7.47431C11.2806 7.24179 11.144 6.70899 11.3761 6.29901C11.6221 5.88925 12.1414 5.75245 12.5512 5.98475Z" fill="#2295FF"/><path fill-rule="evenodd" clip-rule="evenodd" d="M6.52563 16.3414L9.42236 13.0482C9.31309 13.062 9.20382 13.0755 9.09434 13.0893C8.65726 13.1166 8.24728 12.8843 8.05627 12.4745C8.00142 12.3788 7.96034 12.2831 7.91947 12.1738L6.52563 16.3414Z" fill="#F8954F"/></svg>',
            'paidmembershipspro' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_15026_188044)">
<path d="M16.7299 10.59L17.3845 15.1473L6.98375 15.0942C6.96553 15.116 6.94753 15.1377 6.9292 15.1597C6.91109 15.1813 6.89287 15.203 6.87487 15.225C6.85665 15.2468 6.83883 15.2686 6.82061 15.2904C6.80272 15.3124 6.78461 15.3344 6.76683 15.3563C6.75074 15.3765 6.73454 15.3966 6.71834 15.4169C6.70214 15.437 6.68605 15.4573 6.66996 15.4774C6.65387 15.4977 6.63789 15.5181 6.6218 15.5384C6.60593 15.5585 6.58973 15.579 6.57397 15.5992L17.8049 15.8027L17.0264 10.5639L16.7299 10.59Z" fill="#598C2E"/>
<path d="M7.75167 14.2015C7.71376 14.2438 7.67592 14.2865 7.63812 14.329C7.60062 14.3714 7.56311 14.4143 7.52538 14.457C7.4881 14.5 7.45059 14.5427 7.41309 14.5859L16.6315 14.515L16.0428 10.0985L15.9718 9.56394L15.5866 9.60644L16.1318 13.907L7.86547 14.0748C7.82734 14.1169 7.78929 14.1591 7.75167 14.2015Z" fill="#598C2E"/>
<path d="M14.7829 8.66013L14.1923 8.73819C14.1314 8.77662 14.0708 8.81545 14.01 8.8545C13.9497 8.89321 13.8894 8.93241 13.8292 8.97153C13.7688 9.01077 13.7089 9.05022 13.6487 9.08993C13.5889 9.12953 13.5289 9.16964 13.4694 9.20998C13.3889 9.26434 13.3084 9.3189 13.2286 9.37381C13.1486 9.42892 13.0689 9.48428 12.9892 9.54019C12.91 9.5961 12.8309 9.65259 12.7518 9.70924C12.673 9.76574 12.5943 9.82301 12.5158 9.88046C12.4467 9.93123 12.3778 9.98214 12.3087 10.0334C12.2402 10.0844 12.1712 10.1357 12.103 10.1876C12.0347 10.2393 11.9663 10.2914 11.8984 10.3435C11.8304 10.3961 11.7625 10.4487 11.695 10.5019C11.6511 10.5363 11.6071 10.5713 11.5632 10.6063C11.5196 10.6412 11.4758 10.6761 11.432 10.7113C11.3887 10.7463 11.3448 10.7815 11.3011 10.8168C11.2577 10.852 11.2142 10.8874 11.171 10.9228C11.1548 10.9364 11.1382 10.9497 11.1221 10.9637C11.106 10.9773 11.0895 10.9911 11.0734 11.005C11.057 11.0189 11.0407 11.0328 11.0245 11.0463C11.008 11.0603 10.9916 11.0738 10.9752 11.0877C10.9201 11.1334 10.8653 11.1796 10.8106 11.2257C10.756 11.2726 10.7014 11.3187 10.6472 11.3656C10.5928 11.4126 10.5386 11.4595 10.4845 11.5068C10.4303 11.5538 10.3763 11.6012 10.3223 11.6488C10.1513 11.7998 9.98146 11.9532 9.81281 12.1087C9.64467 12.2641 9.47816 12.4213 9.31289 12.5806C9.14806 12.7397 8.98463 12.9007 8.82281 13.0637C8.6614 13.2267 8.50145 13.3916 8.34302 13.5586L15.32 13.3303L14.8411 9.16601L14.7829 8.66013Z" fill="#598C2E"/>
<mask id="mask0_15026_188044" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="6" y="8" width="9" height="8">
<path d="M14.7827 8.65961L14.192 8.73767C14.1312 8.7761 14.0705 8.81493 14.0097 8.85398C13.9494 8.89269 13.8891 8.93189 13.8289 8.97101C13.7685 9.01024 13.7086 9.0497 13.6484 9.08941C13.5887 9.12901 13.5287 9.16912 13.4691 9.20946C13.3887 9.26382 13.3081 9.31838 13.2283 9.37329C13.1483 9.4284 13.0686 9.48376 12.9889 9.53967C12.9098 9.59558 12.8306 9.65207 12.7516 9.70872C12.6727 9.76522 12.5941 9.82249 12.5156 9.87994C12.4464 9.93071 12.3776 9.98162 12.3085 10.0329C12.2399 10.0839 12.171 10.1351 12.1027 10.1871C12.0344 10.2388 11.966 10.2909 11.8981 10.3429C11.8302 10.3956 11.7623 10.4482 11.6948 10.5013C11.6508 10.5358 11.6069 10.5708 11.563 10.6058C11.5194 10.6406 11.4756 10.6755 11.4318 10.7108C11.3884 10.7458 11.3445 10.7809 11.3009 10.8162C11.2575 10.8515 11.2139 10.8869 11.1707 10.9223C11.1546 10.9359 11.1379 10.9492 11.1219 10.9632C11.1058 10.9768 11.0893 10.9906 11.0732 11.0045C11.0568 11.0184 11.0404 11.0322 11.0243 11.0458C11.0078 11.0598 10.9913 11.0733 10.975 11.0872C10.9198 11.1329 10.865 11.1791 10.8103 11.2252C10.7558 11.272 10.7011 11.3182 10.6469 11.3651C10.5925 11.412 10.5383 11.4589 10.4843 11.5063C10.4301 11.5532 10.3761 11.6007 10.322 11.6482C10.151 11.7993 9.9812 11.9527 9.81255 12.1082C9.64442 12.2636 9.4779 12.4208 9.31263 12.58C9.1478 12.7392 8.98437 12.9002 8.82255 13.0632C8.66114 13.2261 8.49742 13.3876 8.34276 13.5581C8.21731 13.6964 8.07651 13.831 7.86525 14.074C7.66593 14.3032 7.61729 14.3435 7.41286 14.5851C7.28892 14.7317 7.11057 14.9403 6.98351 15.0938C6.73915 15.3892 6.57373 15.5989 6.57373 15.5989L7.79993 15.6211C9.25367 13.6097 12.1892 10.5958 14.7827 8.65961Z" fill="white"/>
</mask>
<g mask="url(#mask0_15026_188044)">
<path d="M7.80018 15.6211L6.57397 15.5989C6.57497 15.5976 6.74009 15.3883 6.98375 15.0938L8.19228 15.1C8.05199 15.2799 7.92104 15.4539 7.80018 15.6211Z" fill="#4B7229"/>
</g>
<mask id="mask1_15026_188044" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="6" y="8" width="9" height="8">
<path d="M14.7827 8.65961L14.192 8.73767C14.1312 8.7761 14.0705 8.81493 14.0097 8.85398C13.9494 8.89269 13.8891 8.93189 13.8289 8.97101C13.7685 9.01024 13.7086 9.0497 13.6484 9.08941C13.5887 9.12901 13.5287 9.16912 13.4691 9.20946C13.3887 9.26382 13.3081 9.31838 13.2283 9.37329C13.1483 9.4284 13.0686 9.48376 12.9889 9.53967C12.9098 9.59558 12.8306 9.65207 12.7516 9.70872C12.6727 9.76522 12.5941 9.82249 12.5156 9.87994C12.4464 9.93071 12.3776 9.98162 12.3085 10.0329C12.2399 10.0839 12.171 10.1351 12.1027 10.1871C12.0344 10.2388 11.966 10.2909 11.8981 10.3429C11.8302 10.3956 11.7623 10.4482 11.6948 10.5013C11.6508 10.5358 11.6069 10.5708 11.563 10.6058C11.5194 10.6406 11.4756 10.6755 11.4318 10.7108C11.3884 10.7458 11.3445 10.7809 11.3009 10.8162C11.2575 10.8515 11.2139 10.8869 11.1707 10.9223C11.1546 10.9359 11.1379 10.9492 11.1219 10.9632C11.1058 10.9768 11.0893 10.9906 11.0732 11.0045C11.0568 11.0184 11.0404 11.0322 11.0243 11.0458C11.0078 11.0598 10.9913 11.0733 10.975 11.0872C10.9198 11.1329 10.865 11.1791 10.8103 11.2252C10.7558 11.272 10.7011 11.3182 10.6469 11.3651C10.5925 11.412 10.5383 11.4589 10.4843 11.5063C10.4301 11.5532 10.3761 11.6007 10.322 11.6482C10.151 11.7993 9.9812 11.9527 9.81255 12.1082C9.64442 12.2636 9.4779 12.4208 9.31263 12.58C9.1478 12.7392 8.98437 12.9002 8.82255 13.0632C8.66114 13.2261 8.49742 13.3876 8.34276 13.5581C8.21731 13.6964 8.07651 13.831 7.86525 14.074C7.66593 14.3032 7.61729 14.3435 7.41286 14.5851C7.28892 14.7317 7.11057 14.9403 6.98351 15.0938C6.73915 15.3892 6.57373 15.5989 6.57373 15.5989L7.79993 15.6211C9.25367 13.6097 12.1892 10.5958 14.7827 8.65961Z" fill="white"/>
</mask>
<g mask="url(#mask1_15026_188044)">
<path d="M7.41309 14.5859C7.45059 14.5427 7.4881 14.5 7.52538 14.457C7.56249 14.415 7.5994 14.3728 7.63629 14.3311C7.70061 14.2602 7.76324 14.1923 7.86547 14.0748L9.0592 14.0505C8.90437 14.2292 8.75567 14.4048 8.61354 14.5767L7.41309 14.5859Z" fill="#4B7229"/>
</g>
<mask id="mask2_15026_188044" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="6" y="8" width="9" height="8">
<path d="M14.7827 8.65961L14.192 8.73767C14.1312 8.7761 14.0705 8.81493 14.0097 8.85398C13.9494 8.89269 13.8891 8.93189 13.8289 8.97101C13.7685 9.01024 13.7086 9.0497 13.6484 9.08941C13.5887 9.12901 13.5287 9.16912 13.4691 9.20946C13.3887 9.26382 13.3081 9.31838 13.2283 9.37329C13.1483 9.4284 13.0686 9.48376 12.9889 9.53967C12.9098 9.59558 12.8306 9.65207 12.7516 9.70872C12.6727 9.76522 12.5941 9.82249 12.5156 9.87994C12.4464 9.93071 12.3776 9.98162 12.3085 10.0329C12.2399 10.0839 12.171 10.1351 12.1027 10.1871C12.0344 10.2388 11.966 10.2909 11.8981 10.3429C11.8302 10.3956 11.7623 10.4482 11.6948 10.5013C11.6508 10.5358 11.6069 10.5708 11.563 10.6058C11.5194 10.6406 11.4756 10.6755 11.4318 10.7108C11.3884 10.7458 11.3445 10.7809 11.3009 10.8162C11.2575 10.8515 11.2139 10.8869 11.1707 10.9223C11.1546 10.9359 11.1379 10.9492 11.1219 10.9632C11.1058 10.9768 11.0893 10.9906 11.0732 11.0045C11.0568 11.0184 11.0404 11.0322 11.0243 11.0458C11.0078 11.0598 10.9913 11.0733 10.975 11.0872C10.9198 11.1329 10.865 11.1791 10.8103 11.2252C10.7558 11.272 10.7011 11.3182 10.6469 11.3651C10.5925 11.412 10.5383 11.4589 10.4843 11.5063C10.4301 11.5532 10.3761 11.6007 10.322 11.6482C10.151 11.7993 9.9812 11.9527 9.81255 12.1082C9.64442 12.2636 9.4779 12.4208 9.31263 12.58C9.1478 12.7392 8.98437 12.9002 8.82255 13.0632C8.66114 13.2261 8.49742 13.3876 8.34276 13.5581C8.21731 13.6964 8.07651 13.831 7.86525 14.074C7.66593 14.3032 7.61729 14.3435 7.41286 14.5851C7.28892 14.7317 7.11057 14.9403 6.98351 15.0938C6.73915 15.3892 6.57373 15.5989 6.57373 15.5989L7.79993 15.6211C9.25367 13.6097 12.1892 10.5958 14.7827 8.65961Z" fill="white"/>
</mask>
<g mask="url(#mask2_15026_188044)">
<path d="M8.34277 13.5586C8.50121 13.3916 8.66115 13.2267 8.82257 13.0637C8.98438 12.9007 9.14781 12.7397 9.31264 12.5806C9.47791 12.4213 9.64443 12.2641 9.81257 12.1087C9.98121 11.9532 10.151 11.7998 10.322 11.6488C10.3761 11.6012 10.4301 11.5538 10.4843 11.5068C10.5383 11.4595 10.5926 11.4126 10.6469 11.3656C10.7011 11.3187 10.7558 11.2726 10.8104 11.2257C10.865 11.1796 10.9199 11.1334 10.975 11.0877C10.9913 11.0738 11.0078 11.0603 11.0243 11.0463C11.0405 11.0328 11.0568 11.0189 11.0732 11.005C11.0893 10.9911 11.1058 10.9773 11.1219 10.9637C11.138 10.9497 11.1546 10.9364 11.1708 10.9228C11.2139 10.8874 11.2575 10.852 11.3009 10.8168C11.3445 10.7815 11.3884 10.7463 11.4318 10.7113C11.4756 10.6761 11.5194 10.6412 11.563 10.6063C11.6069 10.5713 11.6508 10.5363 11.6948 10.5019C11.7623 10.4487 11.8302 10.3961 11.8981 10.3435C11.966 10.2914 12.0344 10.2393 12.1027 10.1876C12.171 10.1357 12.2399 10.0844 12.3085 10.0334C12.3776 9.98214 12.4464 9.93123 12.5156 9.88046C12.5941 9.82301 12.6727 9.76574 12.7516 9.70924C12.8306 9.65259 12.9098 9.5961 12.9889 9.54019C13.0686 9.48428 13.1483 9.42892 13.2283 9.37381C13.3081 9.3189 13.3887 9.26434 13.4691 9.20998C13.5287 9.16964 13.5887 9.12953 13.6484 9.08993C13.7086 9.05022 13.7685 9.01077 13.8289 8.97153C13.8891 8.93241 13.9494 8.89321 14.0097 8.8545C14.0706 8.81545 14.1312 8.77662 14.192 8.73819L14.7827 8.66013C12.9946 9.99503 11.0441 11.8421 9.52853 13.5198L8.34277 13.5586Z" fill="#4B7229"/>
</g>
<path d="M17.9594 5.89743C17.6176 6.02487 17.2745 6.15751 16.931 6.29523C16.5916 6.43112 16.2517 6.57181 15.9125 6.71698C15.5774 6.86073 15.2427 7.00858 14.9092 7.161C14.9004 7.16467 14.892 7.16849 14.8833 7.17257C14.8749 7.1765 14.8662 7.18065 14.8575 7.18476C14.849 7.18888 14.8404 7.19292 14.8319 7.19711C14.8232 7.20118 14.8148 7.20522 14.8063 7.20938C14.5355 7.33376 14.2655 7.46112 13.9972 7.59131C13.731 7.72036 13.466 7.8522 13.2026 7.98654C12.9416 8.11973 12.6821 8.25569 12.4246 8.39418C12.169 8.53142 11.9154 8.67098 11.664 8.8131C11.5743 8.86387 11.4849 8.91497 11.3956 8.96632C11.3068 9.01735 11.2183 9.06867 11.1301 9.12035C11.0419 9.17218 10.9544 9.22416 10.867 9.27647C10.78 9.32856 10.6932 9.38095 10.6067 9.43384C10.5316 9.47932 10.4569 9.52535 10.3822 9.57138C10.308 9.61734 10.2337 9.66355 10.1597 9.71016C10.0859 9.75649 10.0122 9.80288 9.93891 9.84979C9.86592 9.8967 9.79282 9.94343 9.72019 9.99085C9.67171 10.0223 9.62355 10.0539 9.57517 10.0856C9.52701 10.1176 9.47885 10.1493 9.43069 10.1813C9.38286 10.213 9.33503 10.2449 9.2872 10.2769C9.23945 10.3088 9.19206 10.3409 9.14442 10.3733C9.12833 10.3845 9.11179 10.3958 9.09552 10.4071C9.07932 10.4185 9.06301 10.4299 9.04641 10.4411C9.03021 10.4524 9.01379 10.4636 8.99751 10.4751C8.9812 10.4862 8.96511 10.4975 8.94869 10.5086C8.89032 10.5491 8.83235 10.5897 8.77461 10.6304C8.71708 10.6711 8.65944 10.7119 8.60221 10.753C8.54502 10.7937 8.48778 10.8347 8.4311 10.876C8.37431 10.9171 8.31774 10.9583 8.26117 10.9998C7.88827 11.2742 7.5239 11.5555 7.16934 11.8446C6.81845 12.1307 6.47645 12.4246 6.14532 12.7263C5.81695 13.0252 5.4985 13.3329 5.19092 13.6485C4.88547 13.9623 4.59043 14.2848 4.30702 14.617C4.28113 14.6471 4.25556 14.6772 4.22989 14.7078C4.20432 14.7378 4.17873 14.7682 4.15328 14.7986C4.12804 14.829 4.10257 14.8595 4.07743 14.8898C4.05227 14.9205 4.02745 14.9508 4.00241 14.9814C3.98324 15.0046 3.96406 15.0281 3.94551 15.0517C3.92644 15.0752 3.90758 15.0987 3.88885 15.1224C3.87009 15.1459 3.85112 15.1694 3.8327 15.1929C3.81426 15.2165 3.79583 15.2403 3.77729 15.2639C3.76706 15.2516 3.75704 15.2397 3.74703 15.2278C3.73691 15.2157 3.72711 15.2041 3.7173 15.1924C3.70739 15.1811 3.69759 15.1694 3.6879 15.1582C3.67819 15.1466 3.66871 15.1353 3.65913 15.124C3.56314 15.0104 3.47311 14.9062 3.38809 14.8091C3.3037 14.7129 3.22411 14.6235 3.14814 14.539C3.0725 14.4551 3.00017 14.376 2.93047 14.3C2.86091 14.2245 2.79378 14.1515 2.72783 14.0796C2.71047 14.0612 2.6932 14.0425 2.67617 14.0237C2.65901 14.0054 2.64207 13.9869 2.62501 13.9682C2.60818 13.9496 2.59113 13.9311 2.5743 13.9124C2.55758 13.8938 2.54084 13.8753 2.52434 13.8563C2.45092 13.7761 2.37794 13.6942 2.30325 13.6086C2.2289 13.5236 2.15324 13.4345 2.07439 13.34C1.99663 13.2456 1.91576 13.1457 1.83073 13.0371C1.74647 12.9296 1.65824 12.8142 1.5648 12.6885C1.52261 12.6319 1.47925 12.5732 1.43504 12.5124C1.39093 12.4516 1.34543 12.389 1.29908 12.3238C1.25306 12.2591 1.20543 12.192 1.15663 12.1223C1.10794 12.0531 1.05808 11.9815 1.00684 11.9072C1.10784 12.2335 1.20384 12.5409 1.29642 12.8327C1.3905 13.1295 1.48064 13.4095 1.56779 13.676C1.65632 13.9468 1.74199 14.2034 1.8253 14.4489C1.90989 14.6987 1.99267 14.937 2.07482 15.168C2.09391 15.2218 2.11277 15.2751 2.13182 15.3284C2.15089 15.3816 2.16976 15.4344 2.18883 15.4873C2.20802 15.5399 2.22709 15.5921 2.24614 15.6444C2.26511 15.6968 2.28438 15.7491 2.30357 15.801C2.31221 15.8247 2.32136 15.8487 2.33 15.8726C2.33884 15.8966 2.34789 15.9206 2.35674 15.9446C2.36559 15.9685 2.37453 15.9926 2.38358 16.0167C2.39244 16.0406 2.40149 16.065 2.41055 16.089C2.41992 16.1138 2.42918 16.1387 2.43856 16.1631C2.44804 16.1881 2.45731 16.2129 2.46691 16.2379C2.4764 16.2628 2.48587 16.2876 2.49525 16.3125C2.50484 16.3374 2.51421 16.3623 2.52391 16.3875C2.53074 16.4054 2.53744 16.4229 2.54426 16.441C2.5515 16.4589 2.5581 16.477 2.56525 16.4948C2.57208 16.5129 2.57919 16.5307 2.58623 16.5485C2.59306 16.5668 2.60019 16.5846 2.60733 16.6029C2.61021 16.6105 2.61308 16.6182 2.61607 16.6259C2.61896 16.6335 2.62193 16.6412 2.62501 16.6489C2.6279 16.6565 2.63088 16.6642 2.63375 16.6719C2.63684 16.6795 2.63992 16.687 2.64281 16.6949C2.65261 16.7194 2.66209 16.7439 2.67169 16.7685C2.68126 16.7931 2.69096 16.818 2.70067 16.8426C2.71035 16.8675 2.72006 16.8923 2.73007 16.9171C2.73977 16.942 2.74947 16.9669 2.75915 16.9924L2.75245 17.0176L2.76726 17.0118C2.82553 17.1587 2.88488 17.3076 2.94646 17.4602C3.00824 17.614 3.07229 17.7716 3.13888 17.9342C3.20568 18.0984 3.27536 18.2674 3.34791 18.4437C3.42122 18.6216 3.49771 18.8064 3.57783 19C3.71186 18.7561 3.84857 18.5154 3.98803 18.2781C4.12739 18.0409 4.26919 17.8069 4.41378 17.576C4.55836 17.345 4.70569 17.117 4.85509 16.8923C5.005 16.6673 5.1569 16.4453 5.31174 16.2262C5.31493 16.2219 5.31791 16.2175 5.321 16.2129C5.32419 16.2084 5.32739 16.2039 5.33058 16.1994C5.334 16.1952 5.33708 16.1906 5.34017 16.1861C5.34337 16.1817 5.34656 16.1771 5.34943 16.1729C5.37948 16.1307 5.40919 16.0888 5.43924 16.047C5.4694 16.005 5.49905 15.9634 5.5295 15.9219C5.55955 15.8802 5.5896 15.8385 5.61983 15.7975C5.6501 15.7561 5.6807 15.7147 5.71093 15.6734C5.73727 15.6379 5.76335 15.6026 5.78969 15.5677C5.816 15.5324 5.84252 15.4974 5.86886 15.4624C5.89516 15.4275 5.92201 15.3927 5.94824 15.3579C5.97506 15.3233 6.00158 15.2885 6.02814 15.2536C6.03291 15.2473 6.03791 15.2407 6.04272 15.2341C6.04742 15.228 6.0522 15.2217 6.05712 15.2151C6.06179 15.2089 6.06649 15.2025 6.07126 15.1963C6.07619 15.19 6.08107 15.1838 6.08589 15.1777C6.11784 15.1359 6.14991 15.0947 6.1822 15.0528C6.21446 15.0118 6.24675 14.9705 6.27926 14.9295C6.31195 14.8881 6.34435 14.8474 6.37697 14.8064C6.40977 14.7652 6.44269 14.7247 6.47549 14.6843C6.50852 14.6431 6.54165 14.602 6.57479 14.5615C6.60803 14.5205 6.64139 14.4796 6.67474 14.4391C6.7081 14.3982 6.74156 14.3578 6.77521 14.3172C6.80879 14.2769 6.84233 14.2364 6.87631 14.196C7.11563 13.9112 7.35929 13.6323 7.60677 13.3587C7.85514 13.0845 8.10754 12.8152 8.36388 12.5515C8.62128 12.2868 8.88231 12.0273 9.14699 11.7738C9.41335 11.5186 9.68309 11.2689 9.95596 11.0248C10.0086 10.9778 10.0615 10.9309 10.1143 10.8843C10.1671 10.8376 10.22 10.791 10.2731 10.7449C10.3264 10.6985 10.3795 10.6525 10.4332 10.6067C10.4866 10.5607 10.5399 10.5151 10.5936 10.4699C10.6098 10.4563 10.626 10.4426 10.6418 10.4289C10.6577 10.4154 10.6739 10.4017 10.6896 10.388C10.7055 10.3743 10.7215 10.3606 10.7375 10.347C10.7531 10.3332 10.7691 10.3199 10.785 10.3067C10.8273 10.2714 10.8698 10.2362 10.9123 10.2011C10.9547 10.1665 10.9972 10.1316 11.0399 10.0968C11.0825 10.0621 11.1253 10.0275 11.1682 9.99309C11.2109 9.95875 11.2539 9.92411 11.2968 9.8898C11.363 9.83723 11.4292 9.78481 11.4956 9.73323C11.562 9.681 11.6287 9.62971 11.6953 9.57847C11.7621 9.527 11.8292 9.47631 11.896 9.42547C11.9631 9.37485 12.0306 9.32441 12.0979 9.27398C12.1744 9.21696 12.2511 9.16047 12.328 9.10419C12.4052 9.04798 12.4824 8.99211 12.5597 8.93686C12.6372 8.88136 12.7151 8.82633 12.7929 8.77174C12.8709 8.71689 12.9491 8.66293 13.0275 8.60893C13.1755 8.50729 13.3238 8.40707 13.4724 8.30851C13.622 8.20959 13.7719 8.11228 13.9223 8.01673C14.0731 7.92082 14.2248 7.8263 14.3766 7.73384C14.5295 7.64119 14.6825 7.54998 14.836 7.46064C15.1735 7.26429 15.5126 7.07636 15.8527 6.89783C16.1966 6.71731 16.5416 6.5458 16.8867 6.38435C17.2359 6.22129 17.5855 6.06807 17.9348 5.92543C18.2882 5.78146 18.6412 5.64812 18.9927 5.52641C18.6501 5.64477 18.3054 5.76842 17.9594 5.89743Z" fill="#4697CD"/>
<path d="M3.34791 18.4437H3.3479H3.34791ZM13.2026 7.98654C13.2026 7.98654 13.2026 7.98654 13.2027 7.98654C13.2026 7.98657 13.2027 7.98654 13.2026 7.98654ZM13.9927 7.59347C13.9942 7.59274 13.9957 7.59204 13.9972 7.59131C13.9957 7.59204 13.9942 7.59278 13.9927 7.59347Z" fill="#8BA0BB"/>
<path d="M3.57779 19C3.57758 18.9995 3.57738 18.999 3.57718 18.9986C3.49718 18.8052 3.34887 18.4461 3.34788 18.4437C3.34786 18.4437 3.34786 18.4437 3.34786 18.4437L3.10986 17.8634C6.43599 12.401 11.2536 9.27144 13.2026 7.98654C13.2026 7.98654 13.2026 7.98657 13.2026 7.98654C13.2043 7.98573 13.7255 7.72307 13.9927 7.59347C13.9942 7.59278 13.9956 7.59204 13.9971 7.59131C14.2654 7.46112 14.5355 7.33376 14.8062 7.20938C14.8147 7.20522 14.8231 7.20118 14.8319 7.19711C14.8403 7.19292 14.8489 7.18888 14.8575 7.18476C14.8662 7.18065 14.8748 7.1765 14.8832 7.17257C14.892 7.16849 14.9004 7.16467 14.9091 7.161C15.2426 7.00858 15.5773 6.86073 15.9124 6.71698C16.2517 6.57181 16.5916 6.43112 16.9309 6.29523C17.2745 6.15751 17.6176 6.02487 17.9594 5.89743C18.3053 5.76842 18.6501 5.64477 18.9926 5.52641C18.6411 5.64812 18.2881 5.78146 17.9348 5.92543C17.5855 6.06807 17.2358 6.22129 16.8867 6.38435C16.5416 6.5458 16.1966 6.71731 15.8527 6.89783C15.5126 7.07636 15.1734 7.26429 14.8359 7.46064C14.6825 7.54998 14.5294 7.64119 14.3765 7.73384C14.2247 7.8263 14.0731 7.92082 13.9223 8.01673C13.7719 8.11228 13.622 8.20959 13.4724 8.30851C13.3237 8.40707 13.1754 8.50729 13.0274 8.60893C12.9491 8.66293 12.8708 8.71689 12.7929 8.77174C12.7151 8.82633 12.6372 8.88136 12.5596 8.93686C12.4824 8.99211 12.4052 9.04798 12.328 9.10419C12.2511 9.16047 12.1744 9.21696 12.0979 9.27398C12.0305 9.32441 11.9631 9.37485 11.896 9.42547C11.8292 9.47631 11.762 9.527 11.6952 9.57847C11.6287 9.62971 11.5619 9.681 11.4956 9.73323C11.4292 9.78481 11.3629 9.83723 11.2968 9.8898C11.2538 9.92411 11.2109 9.95875 11.1682 9.99309C11.1252 10.0275 11.0825 10.0621 11.0399 10.0968C10.9972 10.1316 10.9547 10.1665 10.9123 10.2011C10.8697 10.2362 10.8272 10.2714 10.7849 10.3067C10.7691 10.3199 10.7531 10.3332 10.7374 10.347C10.7214 10.3606 10.7054 10.3743 10.6896 10.388C10.6738 10.4017 10.6576 10.4154 10.6417 10.4289C10.626 10.4426 10.6098 10.4563 10.5936 10.4699C10.5399 10.5151 10.4865 10.5607 10.4331 10.6067C10.3794 10.6525 10.3264 10.6985 10.273 10.7449C10.2199 10.791 10.1671 10.8376 10.1142 10.8843C10.0614 10.9309 10.0085 10.9778 9.95591 11.0248C9.68304 11.2689 9.4133 11.5186 9.14694 11.7738C8.88226 12.0273 8.62123 12.2868 8.36383 12.5515C8.10749 12.8152 7.85509 13.0845 7.60673 13.3587C7.35924 13.6323 7.11558 13.9112 6.87626 14.196C6.84228 14.2364 6.80874 14.2769 6.77516 14.3172C6.74151 14.3578 6.70805 14.3982 6.67469 14.4391C6.64134 14.4796 6.60798 14.5205 6.57474 14.5615C6.5416 14.602 6.50847 14.6431 6.47544 14.6843C6.44264 14.7247 6.40973 14.7652 6.37692 14.8064C6.3443 14.8474 6.3119 14.8881 6.27921 14.9295C6.2467 14.9705 6.21441 15.0118 6.18215 15.0528C6.14986 15.0947 6.1178 15.1359 6.08584 15.1777C6.08102 15.1838 6.07614 15.19 6.07122 15.1963C6.06644 15.2025 6.06174 15.2089 6.05707 15.2151C6.05215 15.2217 6.04737 15.228 6.04267 15.2341C6.03786 15.2407 6.03286 15.2473 6.02809 15.2536C6.00153 15.2885 5.97501 15.3233 5.94819 15.3579C5.92196 15.3927 5.89511 15.4275 5.86881 15.4624C5.84247 15.4974 5.81595 15.5324 5.78964 15.5677C5.76331 15.6026 5.73722 15.6379 5.71089 15.6734C5.68065 15.7147 5.65005 15.7561 5.61978 15.7975C5.58955 15.8385 5.5595 15.8802 5.52945 15.9219C5.499 15.9634 5.46935 16.005 5.4392 16.047C5.40915 16.0888 5.37943 16.1307 5.34938 16.1729C5.34651 16.1771 5.34332 16.1817 5.34012 16.1861C5.33704 16.1906 5.33395 16.1952 5.33053 16.1994C5.32734 16.2039 5.32414 16.2084 5.32095 16.2129C5.31786 16.2175 5.31489 16.2219 5.31169 16.2262C5.15685 16.4453 5.00495 16.6673 4.85504 16.8923C4.70564 17.117 4.55832 17.345 4.41373 17.576C4.26914 17.8069 4.12734 18.0409 3.98798 18.2781C3.84852 18.5154 3.71181 18.7561 3.57779 19Z" fill="#387CAB"/>
<path d="M6.32762 5.91351L6.3666 4.67357C6.37479 4.41573 6.4332 4.15814 6.53198 3.91246C6.6301 3.66737 6.76858 3.4326 6.93801 3.21983C7.10805 3.00662 7.30961 2.8142 7.53388 2.65478C7.76009 2.49395 8.01069 2.36608 8.2764 2.28376C8.54563 2.20018 8.80622 2.17146 9.04775 2.19118C9.29263 2.21146 9.51825 2.28203 9.71239 2.39752C9.90918 2.51482 10.0737 2.67806 10.1926 2.88102C10.3129 3.08703 10.3859 3.33327 10.3982 3.61215L10.4567 4.95697L6.32762 5.91351ZM14.1209 4.95242C14.1081 4.83733 14.0691 4.73429 14.0093 4.6462C13.95 4.55829 13.8707 4.48585 13.7768 4.43145C13.6838 4.3773 13.5763 4.34126 13.4606 4.32613C13.3457 4.31103 13.2224 4.31654 13.0957 4.346L12.2076 4.55139L12.2062 4.52982L11.9091 4.59881L11.813 3.23952C11.7806 2.77916 11.6449 2.3775 11.4305 2.04667C11.2216 1.7234 10.9386 1.46891 10.6052 1.29188C10.2802 1.11927 9.90734 1.02056 9.5073 1.00282C9.11681 0.985403 8.69936 1.04528 8.27225 1.18899C7.85373 1.32979 7.46177 1.53933 7.10923 1.79698C6.76091 2.05137 6.44833 2.35465 6.18439 2.6885C5.92148 3.02135 5.70475 3.38715 5.5491 3.76879C5.39236 4.15241 5.29563 4.55558 5.27473 4.96116L5.21361 6.15291L5.02938 6.19559L5.02832 6.21433L4.31489 6.37971C4.23284 6.39852 4.15379 6.43533 4.08081 6.48521C4.00795 6.53524 3.94113 6.59817 3.88382 6.67065C3.8265 6.74279 3.77834 6.82442 3.74254 6.91071C3.70706 6.99744 3.68383 7.08939 3.67659 7.18207L3.18799 13.4871L3.7207 14.0898L3.84354 11.8269C3.85623 11.5933 3.90598 11.3686 3.98451 11.1618C4.06313 10.9558 4.17063 10.7665 4.3004 10.6035C4.43007 10.44 4.58241 10.3018 4.75001 10.1986C4.91866 10.0948 5.10351 10.0258 5.29776 10.0015L5.53746 9.97119C5.44 9.93941 5.35081 9.89019 5.2722 9.82631C5.19388 9.76297 5.12632 9.6851 5.07155 9.59543C5.01689 9.50638 4.97523 9.40533 4.94827 9.29556C4.92167 9.1862 4.90952 9.06788 4.9144 8.94291C4.9209 8.78168 4.9547 8.62453 5.01043 8.47807C5.06582 8.3319 5.14326 8.19584 5.23734 8.07663C5.3313 7.95717 5.44213 7.85431 5.56464 7.77478C5.6877 7.69481 5.82248 7.63846 5.96428 7.6122H5.96505H5.96567L5.9663 7.61197L5.96696 7.61194C6.10993 7.58567 6.24655 7.59313 6.37119 7.62935C6.4969 7.66568 6.6107 7.73147 6.70658 7.82118C6.80334 7.9118 6.88162 8.02693 6.93565 8.16142C6.99054 8.29664 7.02036 8.4514 7.01948 8.62005C7.01875 8.75115 6.99957 8.88064 6.96442 9.00488C6.92926 9.12963 6.87824 9.24869 6.8141 9.3586C6.74996 9.46866 6.67271 9.5692 6.58543 9.65677C6.49851 9.74428 6.40175 9.81855 6.29798 9.87656L6.50883 9.84967C6.655 9.83134 6.79713 9.8374 6.93213 9.86546C7.0686 9.8936 7.19816 9.94389 7.31762 10.0138C7.43822 10.0843 7.54872 10.1746 7.64629 10.2821C7.73485 10.38 7.81079 10.494 7.87536 10.6183C7.93003 10.5764 7.98458 10.5347 8.03891 10.4945L8.03016 9.75155C8.02825 9.57779 8.0583 9.40926 8.11414 9.25247C8.17005 9.09609 8.2519 8.95129 8.35387 8.82466C8.45603 8.69804 8.57876 8.58978 8.71611 8.50668C8.85427 8.423 9.00738 8.36515 9.17052 8.34035L9.3708 8.30964C9.28719 8.28955 9.20927 8.25593 9.13981 8.2109C9.07045 8.16637 9.00929 8.11054 8.95827 8.04541C8.90757 7.98046 8.86676 7.90626 8.83818 7.82474C8.80975 7.74359 8.79333 7.65484 8.7909 7.56051C8.78793 7.43917 8.80857 7.31975 8.84865 7.2076C8.88851 7.09552 8.94794 6.99057 9.02211 6.89789C9.09635 6.80502 9.18606 6.72446 9.28653 6.66146C9.38744 6.59799 9.49941 6.55225 9.61854 6.52922C9.7387 6.50626 9.8543 6.50905 9.96116 6.53344C10.0689 6.55806 10.1672 6.60482 10.2519 6.67028C10.337 6.73589 10.4078 6.82009 10.4592 6.9192C10.5109 7.01889 10.5429 7.13376 10.5497 7.25936C10.5548 7.357 10.5448 7.45387 10.521 7.54736C10.4974 7.64059 10.4602 7.73044 10.4116 7.81372C10.3633 7.89707 10.3035 7.97381 10.2349 8.04129C10.1665 8.10889 10.0889 8.16704 10.0049 8.21343L10.1799 8.18739C10.2727 8.17321 10.3642 8.1699 10.4528 8.1764C10.5419 8.1832 10.6284 8.19977 10.7117 8.22552C10.7947 8.25156 10.8742 8.28657 10.9487 8.33014C11.0237 8.37404 11.0935 8.42627 11.1575 8.48589C11.1377 8.49724 11.1187 8.50962 11.0993 8.5209C11.124 8.52149 11.1485 8.52186 11.1731 8.52292C11.2625 8.46977 11.3483 8.4194 11.4364 8.36742L11.4144 8.03678C11.4055 7.90541 11.4241 7.77647 11.4652 7.65576C11.5058 7.53505 11.5692 7.42224 11.6503 7.32265C11.7315 7.22336 11.8304 7.13722 11.9429 7.07032C12.0555 7.0031 12.1821 6.95505 12.3177 6.93202L12.4838 6.90457C12.4134 6.89131 12.3472 6.86744 12.2875 6.83478C12.2277 6.80205 12.1743 6.76079 12.1289 6.71175C12.0837 6.66293 12.0469 6.6068 12.0197 6.54472C11.9925 6.48279 11.9754 6.41487 11.9696 6.34239C11.9622 6.24864 11.9751 6.15581 12.0045 6.06842C12.0339 5.98118 12.0798 5.89893 12.139 5.82608C12.1983 5.75302 12.2707 5.68946 12.3532 5.63921C12.4359 5.58863 12.5282 5.5516 12.6277 5.53246C12.7271 5.51343 12.824 5.51409 12.9141 5.53143C13.0044 5.54925 13.0877 5.58426 13.16 5.63319C13.2327 5.6826 13.2937 5.74626 13.3392 5.82167C13.3848 5.89731 13.4147 5.98441 13.4237 6.08025C13.4311 6.15464 13.4256 6.22829 13.4088 6.29963C13.3923 6.37086 13.3646 6.43973 13.3274 6.50391C13.29 6.56823 13.2433 6.62763 13.189 6.68031C13.135 6.73328 13.0732 6.77913 13.0052 6.81593L13.1471 6.79187C13.2301 6.77806 13.3115 6.7738 13.3907 6.77854C13.4702 6.78339 13.5468 6.79694 13.6202 6.81862C13.6932 6.84055 13.763 6.87037 13.8275 6.90777C13.8477 6.91949 13.866 6.93264 13.8851 6.94572L13.8878 6.9441C13.9159 6.93003 13.9444 6.91582 13.9724 6.90211C14.031 6.8732 14.0893 6.84459 14.1468 6.81652C14.2044 6.78809 14.2615 6.76043 14.3178 6.73328L14.1209 4.95242Z" fill="#414042"/>
</g>
<defs>
<clipPath id="clip0_15026_188044">
<rect width="18" height="18" fill="white" transform="translate(1 1)"/>
</clipPath>
</defs>
</svg>
',
            'wordpresstriggers' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="8.75" fill="#028CB0"/><path d="M4.03462 10C4.03462 12.3639 5.40962 14.4 7.39808 15.3678L4.55288 7.5726C4.21124 8.33625 4.03463 9.16341 4.03462 10ZM10 15.9654C10.6928 15.9654 11.3591 15.8438 11.9832 15.6269L11.9409 15.5476L10.1058 10.5236L8.31827 15.7221C8.84712 15.8808 9.41298 15.9654 10 15.9654ZM10.8197 7.2024L12.9774 13.6173L13.575 11.6288C13.8288 10.8038 14.0245 10.2115 14.0245 9.69856C14.0245 8.95817 13.7601 8.45048 13.538 8.05913C13.2313 7.56202 12.951 7.14423 12.951 6.65769C12.951 6.10769 13.3635 5.6 13.9558 5.6H14.0298C12.9306 4.59091 11.4921 4.03213 10 4.03462C9.01202 4.03443 8.03948 4.27985 7.16989 4.74881C6.30029 5.21776 5.56092 5.89553 5.01827 6.72115L5.39904 6.73173C6.02308 6.73173 6.98558 6.6524 6.98558 6.6524C7.31346 6.63654 7.35048 7.10721 7.02788 7.14423C7.02788 7.14423 6.70529 7.18654 6.34038 7.2024L8.51923 13.6649L9.82548 9.75144L8.89471 7.2024C8.68623 7.1909 8.47812 7.17326 8.27067 7.14952C7.94808 7.12837 7.9851 6.63654 8.30769 6.6524C8.30769 6.6524 9.29135 6.73173 9.87837 6.73173C10.5024 6.73173 11.4649 6.6524 11.4649 6.6524C11.7875 6.63654 11.8298 7.10721 11.5072 7.14423C11.5072 7.14423 11.1846 7.18125 10.8197 7.2024ZM12.9986 15.1562C13.901 14.6315 14.65 13.8792 15.1706 12.9743C15.6912 12.0695 15.9653 11.0439 15.9654 10C15.9654 8.96346 15.701 7.99038 15.2356 7.13894C15.3301 8.07568 15.1883 9.02116 14.8231 9.88894L12.9986 15.1562ZM10 16.875C8.17664 16.875 6.42795 16.1507 5.13864 14.8614C3.84933 13.572 3.125 11.8234 3.125 10C3.125 8.17664 3.84933 6.42795 5.13864 5.13864C6.42795 3.84933 8.17664 3.125 10 3.125C11.8234 3.125 13.572 3.84933 14.8614 5.13864C16.1507 6.42795 16.875 8.17664 16.875 10C16.875 11.8234 16.1507 13.572 14.8614 14.8614C13.572 16.1507 11.8234 16.875 10 16.875Z" fill="white"/></svg>',
            'woocommerce' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_15026_186696)"><path fill-rule="evenodd" clip-rule="evenodd" d="M8.39109 7.41211C7.92268 7.41211 7.61767 7.56461 7.34534 8.0766L6.10351 10.4186V8.33804C6.10351 7.71712 5.80939 7.41211 5.26473 7.41211C4.72007 7.41211 4.49131 7.59729 4.21898 8.12017L3.04251 10.4186V8.35982C3.04251 7.69533 2.77017 7.41211 2.10569 7.41211H0.754924C0.242941 7.41211 -0.0402832 7.65176 -0.0402832 8.08749C-0.0402832 8.52322 0.232048 8.78466 0.733137 8.78466H1.28869V11.4099C1.28869 12.1507 1.78978 12.5864 2.50874 12.5864C3.22769 12.5864 3.55449 12.3032 3.91396 11.6387L4.69828 10.1681V11.4099C4.69828 12.1398 5.17758 12.5864 5.90743 12.5864C6.63728 12.5864 6.90961 12.3359 7.32355 11.6387L9.13183 8.58858C9.52399 7.92409 9.25166 7.41211 8.3802 7.41211C8.3802 7.41211 8.3802 7.41211 8.39109 7.41211Z" fill="#873EFF"/><path fill-rule="evenodd" clip-rule="evenodd" d="M11.7898 7.41211C10.3083 7.41211 9.18628 8.51233 9.18628 10.0047C9.18628 11.4971 10.3192 12.5864 11.7898 12.5864C13.2604 12.5864 14.3824 11.4862 14.3933 10.0047C14.3933 8.51233 13.2604 7.41211 11.7898 7.41211ZM11.7898 10.996C11.2342 10.996 10.8529 10.582 10.8529 10.0047C10.8529 9.42736 11.2342 9.00252 11.7898 9.00252C12.3453 9.00252 12.7266 9.42736 12.7266 10.0047C12.7266 10.582 12.3562 10.996 11.7898 10.996Z" fill="#873EFF"/><path fill-rule="evenodd" clip-rule="evenodd" d="M17.3562 7.41211C15.8856 7.41211 14.7527 8.51233 14.7527 10.0047C14.7527 11.4971 15.8856 12.5864 17.3562 12.5864C18.8268 12.5864 19.9597 11.4862 19.9597 10.0047C19.9597 8.52322 18.8268 7.41211 17.3562 7.41211ZM17.3562 10.996C16.7897 10.996 16.4302 10.582 16.4302 10.0047C16.4302 9.42736 16.8006 9.00252 17.3562 9.00252C17.9117 9.00252 18.293 9.42736 18.293 10.0047C18.293 10.582 17.9226 10.996 17.3562 10.996Z" fill="#873EFF"/></g><defs><clipPath id="clip0_15026_186696"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>',
            'tutorlms' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.89811 8.14016C5.25588 7.38947 5.97143 6.91026 6.80548 6.87876C7.99731 6.91026 8.95212 7.92867 8.92379 9.12857V13.182C8.98342 13.6319 9.40082 13.9319 9.84804 13.8704C10.2058 13.8104 10.504 13.5412 10.5323 13.182V9.13232C10.501 7.93242 11.4565 6.91251 12.6506 6.88176C13.4563 6.88176 14.1711 7.33172 14.4983 8.05241C15.84 10.7222 14.7965 13.9619 12.143 15.3141C11.5148 15.6368 10.8294 15.8315 10.1261 15.8869C9.42287 15.9423 8.71568 15.8573 8.04519 15.6368C7.37471 15.4163 6.75414 15.0647 6.21917 14.6021C5.68421 14.1395 5.24538 13.575 4.92792 12.9413C4.18256 11.4414 4.15424 9.64153 4.89811 8.14016ZM8.26713 2.8006H11.2784V3.9705C10.7708 3.84986 10.251 3.7892 9.72953 3.78977C9.22268 3.78977 8.71584 3.84976 8.23881 3.93976L8.26788 2.79985L8.26713 2.8006ZM16.4676 11.1099C16.4676 10.9292 16.4989 10.7807 16.4989 10.5685C16.4989 8.10866 15.1863 5.8581 13.0404 4.6582V2.79985H14.3522C14.4705 2.80209 14.588 2.7803 14.6977 2.7358C14.8074 2.69129 14.9071 2.62497 14.9907 2.54082C15.0744 2.45666 15.1403 2.3564 15.1845 2.24602C15.2287 2.13564 15.2504 2.01742 15.2482 1.89843C15.2482 1.38847 14.8598 1 14.3522 1H5.19625C4.68941 1.03075 4.30331 1.41922 4.30331 1.92992C4.30331 2.43988 4.69164 2.8306 5.19923 2.8306H6.51107V4.6627C3.25832 6.45955 2.03593 10.5722 3.82479 13.8412C3.91423 13.9912 3.97386 14.1104 4.0648 14.2604C6.59902 18.5508 13.3967 18.97 15.6931 19C15.9018 19 16.0822 18.9085 16.2596 18.7893C16.4087 18.6393 16.4683 18.4293 16.4683 18.2193L16.4676 11.1099Z" fill="#0049F8"/><path fill-rule="evenodd" clip-rule="evenodd" d="M6.76668 12.16C6.55739 12.15 6.35932 12.0618 6.21116 11.9127C6.063 11.7637 5.97538 11.5644 5.96542 11.3538V9.51794C5.96542 9.07398 6.32543 8.71176 6.76668 8.71176C7.20868 8.71176 7.56794 9.07398 7.56794 9.51794V11.353C7.56794 11.797 7.23701 12.1592 6.82184 12.1592L6.76668 12.16ZM12.5134 12.16C12.0721 12.16 11.7121 11.827 11.7121 11.383V9.51794C11.7121 9.07398 12.0721 8.71176 12.5134 8.71176C12.9547 8.71176 13.3147 9.07398 13.3147 9.51794V11.353C13.3141 11.5667 13.2295 11.7715 13.0794 11.9227C12.9293 12.0739 12.7258 12.1592 12.5134 12.16Z" fill="#0049F8"/></svg>',
            'memberpress' => '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.2025 8.50281C11.2025 7.70508 11.8502 7.05735 12.648 7.05735C13.4457 7.05735 14.0934 7.70508 14.0934 8.50281V11.9324C14.0934 12.4778 14.5366 12.921 15.0821 12.921C15.6275 12.921 16.0707 12.4778 16.0707 11.9324V8.50281C16.0707 6.61417 14.5366 5.08008 12.648 5.08008C11.6934 5.08008 10.8343 5.46871 10.2139 6.10281C10.8275 6.72326 11.2025 7.56872 11.2025 8.50281Z" fill="#20D1CC"/><path d="M9.23233 8.50281C9.23233 7.56872 9.60733 6.72326 10.221 6.10281C9.60051 5.47553 8.74142 5.08008 7.78687 5.08008C6.83233 5.08008 5.97324 5.46871 5.35278 6.10281C5.9596 6.71644 6.3346 7.5619 6.34142 8.49599C6.34824 7.69826 6.99597 7.05735 7.78687 7.05735C8.5846 7.05735 9.23233 7.70508 9.23233 8.50281Z" fill="#05D0E0"/><path d="M11.2029 11.9331V8.50352C11.2029 7.56943 10.8279 6.72397 10.2142 6.10352C9.6074 6.72397 9.22559 7.56943 9.22559 8.50352V11.9331C9.22559 12.2058 9.33468 12.4512 9.51195 12.6285C9.68922 12.8058 9.93468 12.9149 10.2074 12.9149C10.7597 12.9217 11.2029 12.4785 11.2029 11.9331Z" fill="#01A9B2"/><path d="M2.91149 5.08008C2.36603 5.08008 1.92285 5.52326 1.92285 6.06871C1.92285 6.61417 2.36603 7.05735 2.91149 7.05735C3.70922 7.05735 4.35694 7.70508 4.35694 8.49599C4.35694 7.5619 4.73194 6.72326 5.34558 6.10281C4.72512 5.47553 3.86603 5.08008 2.91149 5.08008Z" fill="#0282C9"/><path d="M4.36328 8.49574V8.50256V11.9321C4.36328 12.4776 4.80646 12.9207 5.35192 12.9207C5.52237 12.9207 5.67919 12.8798 5.82237 12.8048C6.12919 12.6412 6.34055 12.3139 6.34055 11.9389V8.50937V8.50256C6.34055 7.56847 5.96555 6.72983 5.35192 6.10938C4.73828 6.71619 4.36328 7.56165 4.36328 8.49574Z" fill="#016BB1"/><path d="M2.91149 7.05735C3.4575 7.05735 3.90012 6.61472 3.90012 6.06871C3.90012 5.52271 3.4575 5.08008 2.91149 5.08008C2.36548 5.08008 1.92285 5.52271 1.92285 6.06871C1.92285 6.61472 2.36548 7.05735 2.91149 7.05735Z" fill="#06429E"/><path d="M5.34557 12.9226C5.89158 12.9226 6.33421 12.48 6.33421 11.9339C6.33421 11.3879 5.89158 10.9453 5.34557 10.9453C4.79956 10.9453 4.35693 11.3879 4.35693 11.9339C4.35693 12.48 4.79956 12.9226 5.34557 12.9226Z" fill="#01569A"/><path d="M10.214 12.9226C10.76 12.9226 11.2026 12.48 11.2026 11.9339C11.2026 11.3879 10.76 10.9453 10.214 10.9453C9.66797 10.9453 9.22534 11.3879 9.22534 11.9339C9.22534 12.48 9.66797 12.9226 10.214 12.9226Z" fill="#008C9D"/><path d="M15.0887 12.9226C15.6347 12.9226 16.0774 12.48 16.0774 11.9339C16.0774 11.3879 15.6347 10.9453 15.0887 10.9453C14.5427 10.9453 14.1001 11.3879 14.1001 11.9339C14.1001 12.48 14.5427 12.9226 15.0887 12.9226Z" fill="#03ABA3"/></svg>',
            'wishlistmember' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_15036_188296)"><path d="M18.3813 1H1.61875C1.27702 1 1 1.27702 1 1.61875V18.3813C1 18.723 1.27702 19 1.61875 19H18.3813C18.723 19 19 18.723 19 18.3813V1.61875C19 1.27702 18.723 1 18.3813 1Z" fill="#1E7FC0"/><path d="M1 10.1518C1 10.1518 2.88895 11.7116 5.78617 16.2862C5.78617 16.2862 10.5899 10.2864 19 8.06664V5.11914C19 5.11914 12.0549 6.47547 5.87828 13.6023C5.87828 13.6023 3.50242 9.9598 1 8.20867V10.1518Z" fill="white"/></g><defs><clipPath id="clip0_15036_188296"><rect width="18" height="18" fill="white" transform="translate(1 1)"/></clipPath></defs></svg>'
        ];

        return isset($icons[$key]) ? $icons[$key] : '';
    }
}
