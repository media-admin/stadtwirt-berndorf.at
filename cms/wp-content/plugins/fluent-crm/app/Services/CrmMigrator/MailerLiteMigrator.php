<?php

namespace FluentCrm\App\Services\CrmMigrator;

use FluentCrm\App\Services\CrmMigrator\Api\MailerLite;
use FluentCrm\Framework\Support\Arr;

class MailerLiteMigrator extends BaseMigrator
{
    public function getInfo()
    {
        $infoSvg = $this->getInfoIcon();

        $logo = $this->getSvgLogo();

        return [
            'title'                  => 'MailerLite',
            'description'            => __('Migrate your MailerLite contacts and associate to FluentCRM', 'fluent-crm'),
            'logo'                   => fluentCrmMix('images/migrators/mailerlite.png'),
            'logo_svg'               => $logo,
            'supports'               => [
                'tags'                => true,
                'lists'               => false,
                'empty_tags'          => false,
                'active_imports_only' => true
            ],
            'field_map_info'         => __('Email Address and First name will be mapped automatically', 'fluent-crm'),
            'tags_map_info'           => __('Only Selected Groups will be imported from MailerLite', 'fluent-crm'),
            'credentials'            => [
                'api_key' => ''
            ],
            'credential_fields'      => [
                'api_key' => [
                    'label'       => __('API Key', 'fluent-crm'),
                    'placeholder' => __('MailerLite API Key', 'fluent-crm'),
                    'data_type'   => 'password',
                    'type'        => 'input-text',
                    'inline_help' => $infoSvg . ' ' . __('You can find your API key at MailerLite', 'fluent-crm') . ' <a href="https://app.mailerlite.com/integrations/api/" target="_blank" rel="noopener">'. __("Account -> Integrations -> Developer API", "fluent-crm") .'</a>'
                ]
            ],
            'refresh_on_list_change' => false,
            'doc_url' => 'https://fluentcrm.com/docs/migrating-into-fluentcrm-from-mailerlite/'
        ];
    }

    public function verifyCredentials($credential)
    {
        $api = $this->getApi($credential);

        try {
            $result = $api->auth_test();
            if (is_wp_error($result)) {
                return $result;
            }
        } catch (\Exception $exception) {
            return new \WP_Error('api_error', $exception->getMessage());
        }

        return true;
    }

    public function getListTagMappings($postedData)
    {
        $api = $this->getApi($postedData['credential']);

        $groups = $api->getGroups();

        $formattedTags = [];

        foreach ($groups as $tag) {
            $formattedTags[] = [
                'remote_name'  => (string)$tag['name'],
                'remote_id'    => (string)$tag['id'],
                'will_create'  => 'no',
                'fluentcrm_id' => ''
            ];
        }

        $data['tags'] = $formattedTags;

        $contactFields = $api->getCustomFields();
        $formattedContactFields = [];

        $autoFieldMaps = [
            'name'      => 'first_name',
            'last_name' => 'last_name',
            'country'   => 'country',
            'city'      => 'city',
            'phone'     => 'phone',
            'state'     => 'state',
            'zip'       => 'postal_code'
        ];

        foreach ($contactFields as $field) {
            $item = [
                'type'            => 'any',
                'remote_label'    => $field['title'],
                'remote_tag'      => $field['key'],
                'fluentcrm_field' => '',
                'will_skip'       => 'no'
            ];


            $fieldKey = $field['key'];

            if ($fieldKey == 'email') {
                continue;
            }

            if (isset($autoFieldMaps[$fieldKey])) {
                $item['fluentcrm_field'] = $autoFieldMaps[$fieldKey];
            }

            $formattedContactFields[] = $item;
        }

        $data['contact_fields'] = $formattedContactFields;
        $data['contact_fillables'] = $this->getFillables();

        $data['all_ready'] = true;

        return $data;
    }

    public function getSummary($postedData)
    {
        $api = $this->getApi($postedData['credential']);

        $groups = $api->getGroups();

        if (is_wp_error($groups)) {
            return $groups;
        }

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

        $message = __('Based on your selections, ', 'fluent-crm') . $tagCounts . __(' groups and associate contacts will be imported from MailerLite', 'fluent-crm');

        return [
            'subscribed_count'   => 1,
            'unsubscribed_count' => 0,
            'all_count'          => 1,
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

        $limitPerChunk = 50;

        $import_tracker = Arr::get($postedData, 'import_tracker', []);

        if (empty($import_tracker)) {
            $import_tracker = [
                'current_index' => 0,
                'offset'        => 0
            ];
        }

        $currentTagId = $taggingKeys[$import_tracker['current_index']];

        $subscribers = $api->getGroupSubscribers($currentTagId, [
            'offset' => $import_tracker['offset'],
            'limit'  => $limitPerChunk
        ]);

        if (is_wp_error($subscribers)) {
            return $subscribers;
        }

        $fieldMaps = Arr::get($postedData, 'contact_fields', []);

        $mapSettings = Arr::get($postedData, 'map_settings', []);

        foreach ($subscribers as $subscriber) {

            if ($subscriber['type'] != 'active') {
                continue;
            }

            $data = [
                'email'      => $subscriber['email'],
                'first_name' => $subscriber['name'],
                'created_at' => gmdate('Y-m-d H:i:s', strtotime($subscriber['date_created'])),
                'source'     => 'MailerLite',
                'status'     => 'subscribed',
                'ip'         => $subscriber['signup_ip']
            ];

            $remoteData = $subscriber['fields'];

            $formattedRemoteData = [];

            foreach ($remoteData as $remoteDatum) {
                $formattedRemoteData[$remoteDatum['key']] = $remoteDatum['value'];
            }

            $mergeData = $this->getMergedData($formattedRemoteData, $fieldMaps);

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

        $stepCompletedCount = $limitPerChunk + $import_tracker['offset'];

        $groupContactsCount = $api->getContactCountByGroup($currentTagId);

        if (is_wp_error($groupContactsCount)) {
            return $groupContactsCount;
        }
        $stepCompleted = $stepCompletedCount >= $groupContactsCount;

        if (!$stepCompleted) {
            $import_tracker = [
                'current_index' => $import_tracker['current_index'],
                'offset'        => $stepCompletedCount
            ];
        } else {
            $nextIndex = $import_tracker['current_index'] + 1;
            $import_tracker = [
                'current_index' => $nextIndex,
                'offset'        => 0
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
        return new MailerLite($credential['api_key']);
    }

    public function getSvgLogo()
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="26" viewBox="0 0 32 26" fill="none">
  <g clip-path="url(#clip0_6972_60099)">
    <path d="M28.3562 0H3.50365C1.56496 0 0 1.56937 0 3.51351V25.836L4.83504 21.0342H28.3796C30.3182 21.0342 31.8832 19.4649 31.8832 17.5207V3.51351C31.8599 1.56937 30.2949 0 28.3562 0ZM7.45109 15.7405C7.45109 16.2559 7.03066 16.6775 6.51679 16.6775C6.00292 16.6775 5.58248 16.2559 5.58248 15.7405V5.78559C5.58248 5.27027 6.00292 4.84865 6.51679 4.84865C7.03066 4.84865 7.45109 5.27027 7.45109 5.78559V15.7405ZM11.7956 15.7405C11.7956 16.2559 11.3752 16.6775 10.8613 16.6775C10.3474 16.6775 9.92701 16.2559 9.92701 15.7405V9.15856C9.92701 8.64324 10.3474 8.22162 10.8613 8.22162C11.3752 8.22162 11.7956 8.64324 11.7956 9.15856V15.7405ZM11.9124 6.16036C11.9124 6.72252 11.4686 7.16757 10.908 7.16757H10.8146C10.254 7.16757 9.81022 6.72252 9.81022 6.16036V6.09009C9.81022 5.52793 10.254 5.08288 10.8146 5.08288H10.908C11.4686 5.08288 11.9124 5.52793 11.9124 6.09009V6.16036ZM18.4993 16.4432C18.0788 16.6541 17.6117 16.7477 17.1212 16.7477C15.5095 16.7477 14.6453 15.9748 14.6453 14.4991V9.97838H13.8044C13.5241 9.97838 13.2905 9.76757 13.2905 9.48649V9.46306C13.2905 9.2991 13.3839 9.13513 13.5241 9.01802L15.6029 6.98018C15.7197 6.86306 15.8599 6.79279 16.0234 6.76937C16.3037 6.76937 16.5606 7.0036 16.5606 7.28468V7.30811V8.29189H18.0555C18.5226 8.29189 18.8963 8.66667 18.8963 9.13513C18.8963 9.6036 18.5226 9.97838 18.0555 9.97838H16.5606V14.382C16.5606 15.0144 16.8876 15.0613 17.3314 15.0613C17.5183 15.0613 17.7051 15.0378 17.8686 14.991C17.9854 14.9441 18.1255 14.9441 18.2423 14.9207C18.6628 14.9207 19.0131 15.2721 19.0365 15.7171C18.9898 16.0216 18.7796 16.3261 18.4993 16.4432ZM24.2219 14.991C24.8993 15.0144 25.5766 14.8504 26.1839 14.5459C26.3007 14.4757 26.4409 14.4523 26.5577 14.4523C27.0248 14.4523 27.3985 14.8036 27.3985 15.2721V15.2955C27.3752 15.6234 27.1883 15.9279 26.8847 16.0685C26.2307 16.4432 25.5299 16.7712 24.0818 16.7712C21.4657 16.7712 19.8774 15.155 19.8774 12.4613C19.8774 9.2991 21.9796 8.15135 23.7547 8.15135C26.4175 8.15135 27.6321 10.2829 27.6321 12.2505C27.6555 12.7423 27.2584 13.164 26.7679 13.1874C26.7445 13.1874 26.7212 13.1874 26.6978 13.1874H21.7927C22.0496 14.3586 22.8905 14.991 24.2219 14.991Z" fill="#21C16C"/>
    <path d="M23.8018 9.81448C22.7741 9.79106 21.9098 10.564 21.8164 11.5947H25.8106C25.6938 10.564 24.8295 9.79106 23.8018 9.81448Z" fill="#21C16C"/>
  </g>
  <defs>
    <clipPath id="clip0_6972_60099">
      <rect width="32" height="26" fill="white"/>
    </clipPath>
  </defs>
</svg>';
    }

}
