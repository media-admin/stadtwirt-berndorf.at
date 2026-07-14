<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\Framework\Support\Arr;

/**
 *  DocsController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class DocsController extends Controller
{
    private $restApi = 'https://fluentcrm.com/wp-json/wp/v2/';

    public function index()
    {

        $formattedDocs = $this->getDocsPerChunk($this->restApi . 'docs?per_page=100', 'fluentcrm_all_docs');
        $moreDocs = $this->getDocsPerChunk($this->restApi . 'docs?per_page=100&offset=100', 'fluentcrm_all_docs_2');

        if ($moreDocs) {
            $formattedDocs = array_merge($formattedDocs, $moreDocs);
        }

        return [
            'docs' => $formattedDocs
        ];
    }

    public function getDoc($docId)
    {
        $request = wp_remote_get($this->restApi . 'docs/' . $docId);

        if (is_wp_error($request)) {
            return [
                'content'  => 'sorry, we could not fetch the doc at this moment. Please try again',
                'is_error' => true
            ];
        }

        $doc = json_decode(wp_remote_retrieve_body($request), true);

        return [
            'title'   => sanitize_text_field($doc['title']['rendered']),
            'content' => links_add_target(Helper::sanitizeHtml($doc['content']['rendered'])),
            'link'    => esc_url($doc['link']),
            'id'      => $doc['id']
        ];
    }

    public function getAddons(Request $request)
    {
        $canAutoInstallToolkit = (bool) apply_filters('fluent_toolkit/can_auto_install', false);
        $toolkitPluginFile = 'fluent-toolkit/fluent-toolkit.php';
        $toolkitLoaded = defined('FLUENT_TOOLKIT_VERSION');
        $toolkitPluginExists = $this->isPluginInstalled($toolkitPluginFile);
        $toolkitActionText = __('Get FluentHub from GitHub', 'fluent-crm');

        if ($canAutoInstallToolkit) {
            $toolkitActionText = $toolkitPluginExists ? __('Activate FluentHub', 'fluent-crm') : __('Install FluentHub', 'fluent-crm');
        }

        $addOns = [
            'fluentform'     => [
                'title'          => __('Fluent Forms', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluentform.png'),
                'is_installed'   => defined('FLUENTFORM'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluentform/',
                'settings_url'   => admin_url('admin.php?page=fluent_forms'),
                'action_text'    => $this->isPluginInstalled('fluent-form/fluent-form.php') ? __('Activate Fluent Forms', 'fluent-crm') : __('Install Fluent Forms', 'fluent-crm'),
                'description'    => __('Collect leads and build any type of forms, accept payments, connect with your CRM with the Fastest Contact Form Builder Plugin for WordPress', 'fluent-crm')
            ],
            'fluentsmtp'     => [
                'title'          => __('Fluent SMTP', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-smtp.svg'),
                'is_installed'   => defined('FLUENTMAIL'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-smtp/',
                'settings_url'   => admin_url('options-general.php?page=fluent-mail#/'),
                'action_text'    => $this->isPluginInstalled('fluent-smtp/fluent-smtp.php') ? __('Activate Fluent SMTP', 'fluent-crm') : __('Install Fluent SMTP', 'fluent-crm'),
                'description'    => __('The Ultimate SMTP and SES Plugin for WordPress. Connect with any SMTP, SendGrid, Mailgun, SES, Sendinblue, PepiPost, Google, Microsoft and more.', 'fluent-crm')
            ],
            'fluent-support' => [
                'title'          => __('Fluent Support', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-support.svg'),
                'is_installed'   => defined('FLUENT_SUPPORT_VERSION'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-support/',
                'settings_url'   => admin_url('admin.php?page=fluent-support#/'),
                'action_text'    => $this->isPluginInstalled('fluent-support/fluent-support.php') ? __('Activate Fluent Support', 'fluent-crm') : __('Install Fluent Support', 'fluent-crm'),
                'description'    => __('WordPress Helpdesk and Customer Support Ticket Plugin. Provide awesome support and manage customer queries right from your WordPress dashboard.', 'fluent-crm')
            ],
            'fluent-cart' => [
                'title'          => __('Fluent Cart', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-cart-dark.svg'),
                'is_installed'   => defined('FLUENTCART_VERSION'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-cart/',
                'settings_url'   => admin_url('admin.php?page=fluent-cart#/'),
                'action_text'    => $this->isPluginInstalled('fluent-cart/fluent-cart.php') ? __('Activate Fluent Cart', 'fluent-crm') : __('Install Fluent Cart', 'fluent-crm'),
                'description'    => __('WordPress eCommerce and Shopping Cart Plugin. Build an online store and manage products, orders, and customers right from your WordPress dashboard.', 'fluent-crm')
            ],
            'fluent-boards' => [
                'title'          => __('Fluent Boards', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-boards.svg'),
                'is_installed'   => defined('FLUENT_BOARDS'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-boards/',
                'settings_url'   => admin_url('admin.php?page=fluent-boards#/'),
                'action_text'    => $this->isPluginInstalled('fluent-boards/fluent-boards.php') ? __('Activate Fluent Boards', 'fluent-crm') : __('Install Fluent Boards', 'fluent-crm'),
                'description'    => __('WordPress Project Management and Collaboration Plugin. Manage projects, tasks, and team collaboration right from your WordPress dashboard.', 'fluent-crm')
            ],
            'fluent-community' => [
                'title'          => __('Fluent Community', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-community.svg'),
                'is_installed'   => defined('FLUENT_COMMUNITY_PLUGIN_VERSION'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-community/',
                'settings_url'   => admin_url('admin.php?page=fluent-community#/'),
                'action_text'    => $this->isPluginInstalled('fluent-community/fluent-community.php') ? __('Activate Fluent Community', 'fluent-crm') : __('Install Fluent Community', 'fluent-crm'),
                'description'    => __('WordPress Forum and Community Plugin. Build a thriving online community and discussion forum right from your WordPress dashboard.', 'fluent-crm')
            ],
            'fluent-booking' => [
                'title'          => __('Fluent Booking', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-booking.svg'),
                'is_installed'   => defined('FLUENT_BOOKING_VERSION'),
                'learn_more_url' => 'https://wordpress.org/plugins/fluent-booking/',
                'settings_url'   => admin_url('admin.php?page=fluent-booking#/'),
                'action_text'    => $this->isPluginInstalled('fluent-booking/fluent-booking.php') ? __('Activate Fluent Booking', 'fluent-crm') : __('Install Fluent Booking', 'fluent-crm'),
                'description'    => __('WordPress Appointment Booking Plugin. Manage appointments, bookings, and customer scheduling right from your WordPress dashboard.', 'fluent-crm')
            ],
            'fluent-toolkit' => [
                'title'          => __('FluentHub', 'fluent-crm'),
                'logo'           => fluentCrmMix('images/fluent-toolkit.svg'),
                'is_installed'   => $toolkitLoaded,
                'learn_more_url' => 'https://github.com/WPManageNinja/fluent-toolkit',
                'settings_url'   => admin_url('admin.php?page=fluent-toolkit'),
                'action_text'    => $toolkitActionText,
                'install_route'  => $canAutoInstallToolkit ? 'mcp/install-adapter' : '',
                'install_url'    => $canAutoInstallToolkit ? '' : 'https://github.com/WPManageNinja/fluent-toolkit',
                'description'    => __('FluentCRM ships AI agent tools, but they only become available once FluentHub is installed and active.', 'fluent-crm')
            ]
        ];

        $data = [
            'addons' => $addOns
        ];

        if (in_array('experimental_features', $request->get('with', []))) {
            $data['experimental_features'] = Helper::getExperimentalSettings();
        }

        return $data;
    }

    private function isPluginInstalled($plugin)
    {
        return file_exists(WP_PLUGIN_DIR . '/' . $plugin);
    }

    private function getDocsPerChunk($url, $chunkKey)
    {
        return fluentCrmGetFromCache($chunkKey, function () use ($url) {
            $request = wp_remote_get($url);

            if (is_wp_error($request)) {
                return [];
            }

            $docs = json_decode(wp_remote_retrieve_body($request), true);

            $formattedDocs = [];

            foreach ($docs as $doc) {

                if (empty($doc['title'])) {
                    continue;
                }

                $primaryCategory = Arr::get($doc, 'taxonomy_info.doc_category.0', ['value' => 'none', 'label' => 'Other']);
                $formattedDocs[] = [
                    'title'    => sanitize_text_field($doc['title']['rendered']),
                    'content'  => links_add_target(Helper::sanitizeHtml($doc['content']['rendered'])),
                    'link'     => esc_url($doc['link']),
                    'category' => wp_kses_post_deep($primaryCategory)
                ];
            }
            
            return $formattedDocs;
        }, WEEK_IN_SECONDS);
    }

}
