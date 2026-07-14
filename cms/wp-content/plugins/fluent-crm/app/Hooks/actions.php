<?php

/**
 * @var \FluentCrm\Framework\Foundation\Application $app
 */

/*
 * Note: Namespace will be added automatically. For example, if you use MyClass
 * as the controller name then it will become FluentCrm\App\Hooks\Handlers\MyClass.
 */

// Init scheduled tasks

\FluentCrm\App\Hooks\Handlers\Scheduler::register();
(new \FluentCrm\App\Hooks\Handlers\FluentBlockEditorHandler())->register();
(new \FluentCrm\App\Hooks\Handlers\FluentConditionalContentBlockHandler())->register();
(new \FluentCrm\App\Modules\AbandonCart\AbandonCart())->register();

(new \FluentCrm\App\Hooks\Handlers\AutoSubscribeHandler())->register();

add_action('fluentcrm_contacts_filter_subscriber', function ($query, $filters) {
    return (new \FluentCrm\App\Models\Subscriber)->buildGeneralPropertiesFilterQuery($query, $filters);
}, 10, 2);

add_action('fluentcrm_contacts_filter_segment', function ($query, $filters) {
    return (new \FluentCrm\App\Models\Subscriber)->buildSegmentFilterQuery($query, $filters);
}, 10, 2);

add_action('fluentcrm_contacts_filter_custom_fields', function ($query, $filters) {
    return (new \FluentCrm\App\Models\Subscriber)->buildCustomFieldsFilterQuery($query, $filters);
}, 10, 2);

add_action('fluentcrm_contacts_filter_activities', function ($query, $filters) {
    return (new \FluentCrm\App\Models\Subscriber)->buildActivitiesFilterQuery($query, $filters);
}, 10, 2);


// Add admin init

$app->addAction('wp_loaded', 'AdminMenu@init');

$app->addAction('init', 'ExternalPages@route', 99);

$app->addAction('wp_ajax_fluentcrm_unsubscribe_ajax', 'ExternalPages@handleUnsubscribe');
$app->addAction('wp_ajax_nopriv_fluentcrm_unsubscribe_ajax', 'ExternalPages@handleUnsubscribe');

$app->addAction('wp_ajax_fluentcrm_request_unsubscribe_ajax', 'ExternalPages@handleUnsubscribeRequestAjax');
$app->addAction('wp_ajax_nopriv_fluentcrm_request_unsubscribe_ajax', 'ExternalPages@handleUnsubscribeRequestAjax');

$app->addAction('wp_ajax_fluentcrm_manage_preferences_ajax', 'ExternalPages@handleManageSubPref');
$app->addAction('wp_ajax_nopriv_fluentcrm_manage_preferences_ajax', 'ExternalPages@handleManageSubPref');


$app->addAction('wp_ajax_fluentcrm_request_manage_subscription_ajax', 'ExternalPages@handleManageSubRequestAjax');
$app->addAction('wp_ajax_nopriv_fluentcrm_request_manage_subscription_ajax', 'ExternalPages@handleManageSubRequestAjax');


$app->addAction('wp_ajax_fluentcrm_callback_for_background', 'ExternalPages@handleBackgroundProcessCallback');
$app->addAction('wp_ajax_nopriv_fluentcrm_callback_for_background', 'ExternalPages@handleBackgroundProcessCallback');

$app->addAction('wp_ajax_fluent_crm_account_form', 'PrefFormHandler@handleAjax');
$app->addAction('wp_ajax_nopriv_fluent_crm_account_form', 'PrefFormHandler@handleAjax');


// Fallback for funnel sequence save ajax
$app->addAction('wp_ajax_fluentcrm_save_funnel_sequence_ajax', 'FunnelHandler@saveSequences');
$app->addAction('wp_ajax_fluentcrm_export_funnel', 'FunnelHandler@exportFunnel');
$app->addAction('wp_ajax_fluentcrm_save_funnel_email_action', 'FunnelHandler@saveEmailAction');
$app->addAction('wp_ajax_fluentcrm_save_campaign_email_body', 'FunnelHandler@saveCampaignEmail');



/*
 * Integrations & Funnels Handler Init
 */

(new \FluentCrm\App\Hooks\Handlers\FunnelHandler())->register();

// FluentCart's modal checkout fires fluent_cart/before_payment_methods and calls die()
// during init at priority 10, before a priority-10 callback can register. Only
// CheckoutSubscription needs to be early — everything else in FluentCart::init() is fine at 10.
add_action('init', function () {
    if (defined('FLUENTCART_VERSION')) {
        (new \FluentCrm\App\Services\ExternalIntegrations\FluentCart\CheckoutSubscription())->init();
    }
}, 1);

// All external integrations (FluentCart::init() runs here too, minus CheckoutSubscription)
add_action('init', function () {
    (new \FluentCrm\App\Hooks\Handlers\Integrations())->register();
}, 10);


$app->addAction('fluentcrm_subscriber_status_to_subscribed', 'FunnelHandler@resumeSubscriberFunnels', 1, 2);

/*
 * Cleanup Hooks
 */
$app->addAction('fluentcrm_after_subscribers_deleted', 'Cleanup@deleteSubscribersAssets', 10, 1);
$app->addAction('fluent_crm/campaign_deleted', 'Cleanup@deleteCampaignAssets', 10, 1);
$app->addAction('fluent_crm/list_deleted', 'Cleanup@deleteListAssets', 10, 1);
$app->addAction('fluent_crm/tag_deleted', 'Cleanup@deleteTagAssets', 10, 1);
$app->addAction('fluent_crm/campaign_archived', 'Cleanup@archiveCampaignAssets', 10, 1);
$app->addAction('fluent_crm/sync_subscriber_delete_setting', 'Cleanup@SyncSubscriberDeleteSettings', 10, 2);

$app->addAction('fluentcrm_subscriber_status_to_unsubscribed', 'Cleanup@handleUnsubscribe');
$app->addAction('fluentcrm_subscriber_status_to_bounced', 'Cleanup@handleUnsubscribe');
$app->addAction('fluentcrm_subscriber_status_to_complained', 'Cleanup@handleUnsubscribe');
$app->addAction('fluentcrm_subscriber_status_to_spammed', 'Cleanup@handleUnsubscribe');

$app->addAction('fluent_crm/contact_email_changed', 'Cleanup@handleContactEmailChanged');
$app->addAction('delete_user', 'Cleanup@handleUserDelete', 10, 3);
$app->addAction('fluent_crm/company_deleted', 'Cleanup@handleCompanyDelete', 10, 1);
$app->addAction('after_password_reset', 'Cleanup@handleUserPasswordChanged', 10, 1);

add_action('fluent_crm/debug_log', function ($logData) {
    if (!is_array($logData) || empty($logData['title'])) {
        return;
    }

    \FluentCrm\App\Services\Helper::debugLog($logData['title'], \FluentCrm\Framework\Support\Arr::get($logData, 'description', ''), \FluentCrm\Framework\Support\Arr::get($logData, 'type', 'info'));
});

/*
 * Admin Bar
 */
$app->addAction('admin_bar_menu', 'AdminBar@init');

add_action('wp_ajax_nopriv_fluentcrm-post-campaigns-emails-processing', function () use ($app) {
    $campaignId = isset($_REQUEST['campaign_id']) ? intval($_REQUEST['campaign_id']) : 0;

    if ($campaignId) {
        // Continue processing a specific campaign — skip housekeeping/discovery
        \FluentCrm\App\Hooks\Handlers\Scheduler::processCampaignById($campaignId);
    } else {
        // No campaign ID — run full discovery (backward compat)
        \FluentCrm\App\Hooks\Handlers\Scheduler::processFiveMinutes();
    }

    wp_send_json_success([
        'message' => 'success',
        'time'    => time()
    ]);
});

/*
 * For Short URL Redirect
 */
add_action('wp_loaded', function () use ($app) {
    if (isset($_GET['ns_url'])) {
        (new \FluentCrm\App\Hooks\Handlers\RedirectionHandler())->redirect($_GET);
    }
});

/*
 * Contact Activity Logger Class Init
 */
add_action('init', function () {
    (new \FluentCrm\App\Hooks\Handlers\ContactActivityLogger())->register();
    (new \FluentCrm\App\Hooks\Handlers\ActivityLogHandler())->register();
});

/*
 * Setup-wizard
 */
if (!empty($_GET['page']) && 'fluentcrm-setup' == $_GET['page']) {
    add_action('admin_menu', function () {
        add_dashboard_page('FluentCRM Setup', 'FluentCRM Setup', 'manage_options', 'fluentcrm-setup', function () {
            return '';
        });
    });

    add_action('current_screen', function () {
        new \FluentCrm\App\Hooks\Handlers\SetupWizard();
    }, 999);
}


add_shortcode('fluentcrm_pref', function ($atts, $content) {
    return (new \FluentCrm\App\Hooks\Handlers\PrefFormHandler())->handleShortCode($atts, $content);
});

add_shortcode('fluentcrm_content', function ($atts, $content) {
    $result = (new \FluentCrm\App\Hooks\Handlers\PrefFormHandler())->handleDynamicContentShortCode($atts, $content);
    return wp_kses_post($result);
});

// require the CLI
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('fluent_crm', '\FluentCrm\App\Hooks\CLI\Commands');
}

add_action('admin_notices', function () {
    if (defined('FLUENTCAMPAIGN_FRAMEWORK_VERSION') && FLUENTCAMPAIGN_FRAMEWORK_VERSION < 3) {
        echo '<div class="fc_notice notice notice-error fc_notice_error"><h3>Update FluentCRM Pro Plugin</h3><p>You are using an out-of-date version of FluentCRM Pro. <a href="' . esc_url(admin_url('plugins.php?s=fluentcampaign=pro&plugin_status=all&fluentcrm_pro_check_update=' . time())) . '">' . esc_html__('Please update FluentCRM Pro to latest version', 'fluent-crm') . '</a>.</p></div>';
    }
});

/*
 * For REST API Nonce Renew
 */
add_action('wp_ajax_fluentcrm_renew_rest_nonce', function () {
    if (!\FluentCrm\App\Services\PermissionManager::currentUserPermissions()) {
        wp_send_json([
            'error' => 'You do not have permission to do this'
        ], 403);
    }
    wp_send_json([
        'nonce' => wp_create_nonce('wp_rest'),
        'time'  => time()
    ], 200);
});

/*
 * Add custom CSS for fcrm_notice
 */
add_action('admin_head', function () {
    echo '<style>
        .fcrm_notice {
            background: #ffffff;
            border: 1px solid #E1E4EA;
            border-left: 3px solid #FB3748;
            padding: 10px 12px !important;
            border-radius: 8px;
            margin-bottom: 5px;
        }
    </style>';
});

/*
 * MCP — Register abilities for the WordPress Abilities API.
 *
 * Lazy-register guard:
 *  - On WP < 6.9 (no Abilities API in core) OR sites without the WP MCP Adapter
 *    plugin active, `wp_register_ability` is undefined — we skip silently.
 *  - The opt-out option `fluent_crm_mcp_enabled` (default 'yes') lets admins
 *    disable the entire MCP surface from Settings → MCP without uninstalling
 *    the adapter.
 *
 * See `app/Modules/MCP/MCPInit.php` for the registration logic.
 */
add_action('init', function () {
    if (!function_exists('wp_register_ability')) {
        return;
    }

    if (fluentcrm_get_option('mcp_enabled', 'yes') !== 'yes') {
        return;
    }

    (new \FluentCrm\App\Modules\MCP\MCPInit())->init();
}, 5);
