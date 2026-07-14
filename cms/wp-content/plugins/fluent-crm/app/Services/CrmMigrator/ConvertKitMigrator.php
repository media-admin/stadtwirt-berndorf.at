<?php

namespace FluentCrm\App\Services\CrmMigrator;

use FluentCrm\App\Services\CrmMigrator\Api\ConvertKit;
use FluentCrm\Framework\Support\Arr;

class ConvertKitMigrator extends BaseMigrator
{
    public function getInfo()
    {
        $infoSvg = $this->getInfoIcon();

        $logo = $this->getSvgLogo();

        return [
            'title'                  => 'Kit',
            'formerly'                => 'ConvertKit',
            'description'            => __('Migrate your ConvertKit contacts and associate to FluentCRM', 'fluent-crm'),
            'logo'                   => fluentCrmMix('images/migrators/convertkit.png'),
            'logo_svg'               => $logo,
            'supports'               => [
                'tags'                => true,
                'lists'               => false,
                'empty_tags'          => false,
                'active_imports_only' => false
            ],
            'field_map_info'         => __('Email Address and First name will be mapped automatically', 'fluent-crm'),
            'tags_map_info'           => __('Only Selected tags will be imported from ConvertKit', 'fluent-crm'),
            'credentials'            => [
                'api_key'    => '',
                'api_secret' => ''
            ],
            'credential_fields'      => [
                'api_key'    => [
                    'label'       => __('API Key', 'fluent-crm'),
                    'placeholder' => __('ConvertKit API Key', 'fluent-crm'),
                    'data_type'   => 'text',
                    'type'        => 'input-text',
                    'inline_help' => $infoSvg . ' ' . __('You can find your API key at ConvertKit ', 'fluent-crm') . '<a href="https://app.convertkit.com/account_settings/advanced_settings" rel="noopener" target="_blank">'. __("Account -> Settings -> Advanced", "fluent-crm") .'</a>'
                ],
                'api_secret' => [
                    'label'       => __('API Secret', 'fluent-crm'),
                    'placeholder' => __('ConvertKit API Secret', 'fluent-crm'),
                    'data_type'   => 'password',
                    'type'        => 'input-text',
                    'inline_help' => $infoSvg . ' ' . __('You can find your API Secret key at ConvertKit Account -> Settings -> Advanced', 'fluent-crm')
                ]
            ],
            'refresh_on_list_change' => false,
            'doc_url' => 'https://fluentcrm.com/docs/migrating-into-fluentcrm-from-convertkit/'
        ];
    }

    public function verifyCredentials($credential)
    {
        $api = $this->getApi($credential);

        try {
            $result = $api->auth_test();
            if (!empty($result['error'])) {
                throw new \Exception($result['message']);
            }
        } catch (\Exception $exception) {
            return new \WP_Error('api_error', $exception->getMessage());
        }

        return true;
    }

    public function getListTagMappings($postedData)
    {
        $api = $this->getApi($postedData['credential']);
        $tags = $api->getTags();

        $formattedTags = [];

        foreach ($tags as $tag) {
            $formattedTags[] = [
                'remote_name'  => $tag['name'],
                'remote_id'    => (string)$tag['id'],
                'will_create'  => 'no',
                'fluentcrm_id' => ''
            ];
        }

        $data['tags'] = $formattedTags;

        $contactFields = $api->getCustomFields();
        $formattedContactFields = [];

        foreach ($contactFields as $field) {
            $item = [
                'type'            => 'any',
                'remote_label'    => $field['label'],
                'remote_tag'      => $field['key'],
                'fluentcrm_field' => '',
                'will_skip'       => 'no'
            ];

            $fieldKey = $field['key'];

            if ($fieldKey == 'last_name') {
                $item['fluentcrm_field'] = 'last_name';
            }

            $formattedContactFields[] = $item;
        }

        $data['contact_fields'] = $formattedContactFields;
        $data['contact_fillables'] = $this->getFillables();

        unset($data['contact_fillables']['first_name']);
        unset($data['contact_fillables']['full_name']);

        $data['all_ready'] = true;

        return $data;
    }

    public function getSummary($postedData)
    {
        $api = $this->getApi($postedData['credential']);

        $subscribers = $api->getSubscribers([
            'page' => 1
        ]);

        if (is_wp_error($subscribers)) {
            return $subscribers;
        }

        $totalSubscribers = Arr::get($subscribers, 'total_subscribers', 0);


        $tagMappings = Arr::get($postedData, 'tags', []);

        $tagCounts = 0;

        foreach ($tagMappings as $tagMapping) {
            if ($tagMapping['will_create'] == 'yes') {
                $tagCounts++;
                continue;
            }

            if ($tagMapping['will_create'] == 'no' || empty($tagMapping['fluentcrm_id'])) {
                continue;
            }

            $tagCounts++;

        }

        $message = __('Based on your selections, ', 'fluent-crm') . $tagCounts . __(' tags and associate contacts will be imported from ConvertKit', 'fluent-crm');


        return [
            'subscribed_count'   => $totalSubscribers,
            'unsubscribed_count' => 0,
            'all_count'          => $totalSubscribers,
            'message'            => $message
        ];
    }

    public function runImport($postedData)
    {
        if (!defined('FLUENTCRM_DISABLE_TAG_LIST_EVENTS')) {
            define('FLUENTCRM_DISABLE_TAG_LIST_EVENTS', true);
        }

        $api = $this->getApi($postedData['credential']);

        $tagMappings = Arr::get($postedData, 'tags', []);

        $taggingArray = $this->mapTags($tagMappings);

        $taggingKeys = array_keys($taggingArray);

        if (!$taggingKeys) {
            return new \WP_Error('not_found', 'No Tag found based on your selection');
        }

        $import_tracker = Arr::get($postedData, 'import_tracker', []);

        if (empty($import_tracker)) {
            $import_tracker = [
                'current_index'  => 0,
                'completed_page' => 0
            ];
        }

        $currentTagId = $taggingKeys[$import_tracker['current_index']];
        $completedPage = $import_tracker['completed_page'];

        $members = $api->getTagSubscribers($currentTagId, [
            'page' => $completedPage + 1
        ]);

        $subscribers = $members['subscriptions'];

        $fieldMaps = Arr::get($postedData, 'contact_fields');

        $mapSettings = Arr::get($postedData, 'map_settings', []);

        foreach ($subscribers as $subscriberItem) {

            $subscriber = $subscriberItem['subscriber'];
            if ($subscriber['state'] != 'active') {
                continue;
            }

            $data = [
                'email'      => $subscriber['email_address'],
                'first_name' => $subscriber['first_name'],
                'created_at' => gmdate('Y-m-d H:i:s', strtotime($subscriber['created_at'])),
                'source'     => 'ConvertKit',
                'status'     => 'subscribed'
            ];

            $mergeData = $this->getMergedData($subscriber['fields'], $fieldMaps);

            if ($mergeData) {
                $data = array_merge($data, $mergeData);
            }

            if (!empty($mapSettings['local_list_id'])) {
                $data['lists'] = [$mapSettings['local_list_id']];
            }

            $data['tags'] = [$taggingArray[$currentTagId]];

            $contact = FluentCrmApi('contacts')->createOrUpdate($data);

            if ($contact && $contact->status != 'subscribed') {
                $contact->updateStatus('subscribed');
            }
        }

        $stepCompleted = ($completedPage + 1) >= $members['total_pages'];

        if (!$stepCompleted) {
            $import_tracker = [
                'current_index'  => $import_tracker['current_index'],
                'completed_page' => $completedPage + 1
            ];
        } else {
            $nextIndex = $import_tracker['current_index'] + 1;
            $import_tracker = [
                'current_index'  => $nextIndex,
                'completed_page' => 0
            ];
        }

        return [
            'completed'      => 0,
            'total'          => 0,
            'import_tracker' => $import_tracker,
            'has_more'       => isset($taggingKeys[$import_tracker['current_index']]),
            'message' => __('Importer is running now. ', 'fluent-crm').( $import_tracker['current_index']+1 ) .__(' tags have been imported so far', 'fluent-crm')
        ];
    }

    private function getApi($credential)
    {
        return new ConvertKit($credential['api_key'], $credential['api_secret']);
    }

    public function getSvgLogo()
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="71" height="32" viewBox="0 0 71 32" fill="none">
  <g clip-path="url(#clip0_6972_60083)">
    <mask id="mask0_6972_60083" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="0" y="0" width="71" height="32">
      <path d="M70.9328 0H0V32H70.9328V0Z" fill="white"/>
    </mask>
    <g mask="url(#mask0_6972_60083)">
      <mask id="mask1_6972_60083" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="0" y="0" width="71" height="32">
        <path d="M70.961 0H0V32H70.961V0Z" fill="white"/>
      </mask>
      <g mask="url(#mask1_6972_60083)">
        <path d="M19.436 13.3155C28.1679 15.0036 30.8781 23.0778 30.9496 31.1988C30.9512 31.3815 30.8035 31.5305 30.6206 31.5305H19.6288C19.4475 31.5305 19.2998 31.3843 19.2988 31.2028C19.2658 24.9024 18.2438 19.3425 11.9939 19.1024C11.8074 19.0954 11.652 19.2445 11.652 19.431V31.202C11.652 31.3834 11.5047 31.5305 11.323 31.5305H0.329015C0.147368 31.5305 0 31.3837 0 31.202V1.20928C0 1.02784 0.147368 0.88064 0.329015 0.88064H11.323C11.5047 0.88064 11.652 1.02784 11.652 1.20928V12.4445C11.652 12.6112 11.7872 12.7463 11.9541 12.7463C12.0864 12.7463 12.204 12.6601 12.2425 12.5335C15.0744 3.26849 20.3634 0.93888 28.9517 0.8816C29.1341 0.88032 29.2833 1.02816 29.2833 1.21024V12.4176C29.2833 12.599 29.136 12.7463 28.9543 12.7463H19.491C19.3322 12.7463 19.2033 12.8749 19.2033 13.0336C19.2033 13.1712 19.301 13.2896 19.436 13.3155ZM49.5337 20.3389V13.0749C49.5337 12.8934 49.681 12.7463 49.8627 12.7463H57.9529C58.1121 12.7463 58.2412 12.6173 58.2412 12.4583C58.2412 12.3203 58.1429 12.2022 58.0074 12.1757C51.6775 10.9216 48.7555 7.28192 48.6546 1.2096C48.6518 1.02912 48.7968 0.88064 48.9772 0.88064H60.8567C61.0384 0.88064 61.1857 1.02784 61.1857 1.20928V6.32481C61.1857 6.50624 61.333 6.65344 61.5148 6.65344H68.3263C68.508 6.65344 68.6553 6.80064 68.6553 6.98208V12.4176C68.6553 12.599 68.508 12.7463 68.3263 12.7463H61.5148C61.333 12.7463 61.1857 12.8934 61.1857 13.0749V18.9341C61.1857 21.0041 62.4563 21.6867 64.146 21.6867C66.7937 21.6867 69.4056 20.4951 70.4532 19.9549C70.6724 19.842 70.9329 20.001 70.9329 20.247V29.3683C70.9329 29.6119 70.7983 29.8359 70.5823 29.9494C69.5483 30.4928 66.3509 31.9994 62.6933 31.9994C55.1711 32 49.5337 28.937 49.5337 20.3389ZM34.0697 31.202V13.0743C34.0697 12.8928 34.217 12.7456 34.3987 12.7456H45.3927C45.5743 12.7456 45.7217 12.8928 45.7217 13.0743V31.202C45.7217 31.3834 45.5743 31.5305 45.3927 31.5305H34.3987C34.217 31.5305 34.0697 31.3837 34.0697 31.202ZM33.4471 5.58688C33.4471 8.67232 35.6286 11.1738 39.8196 11.1738C44.0105 11.1738 46.192 8.67232 46.192 5.58688C46.192 2.50144 44.0103 0 39.8196 0C35.6286 0 33.4471 2.50144 33.4471 5.58688Z" fill="#1E1E1E"/>
      </g>
    </g>
  </g>
  <defs>
    <clipPath id="clip0_6972_60083">
      <rect width="71" height="32" fill="white"/>
    </clipPath>
  </defs>
</svg>';
    }
}
