<?php

namespace FluentCampaign\App\Hooks\Handlers;

use FluentCampaign\App\Models\RecurringCampaign;
use FluentCampaign\App\Models\Sequence;
use FluentCampaign\App\Models\SequenceMail;
use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\Company;
use FluentCrm\App\Models\CompanyNote;
use FluentCrm\App\Models\Label;
use FluentCrm\App\Models\Meta;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Models\Template;
use FluentCrm\App\Services\ContactsQuery;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\Libs\FileSystem;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Support\Str;

class DataExporter
{
    private $request;

    public function exportContacts()
    {
        $this->verifyRequest('fcrm_manage_contacts_export');
        $currentUserId = get_current_user_id();
        $this->deletePreviousContactsCsvExportMetaAndFile($currentUserId);

        $this->request = $request = FluentCrm('request');

        $columns = $request->get('columns');
        $customFields = $request->get('custom_fields', []);
        $with = [];
        if (in_array('tags', $columns)) {
            $with[] = 'tags';
        }

        if (in_array('lists', $columns)) {
            $with[] = 'lists';
        }

        if (in_array('companies', $columns)) {
            $with[] = 'companies';
        }

        if (in_array('primary_company', $columns)) {
            $with[] = 'company';
        }

        $filterType = $this->request->get('filter_type', 'simple');

        $queryArgs = [
            'search'        => trim(sanitize_text_field($this->request->get('search', ''))),
            'sort_by'       => sanitize_sql_orderby($this->request->get('sort_by', 'id')),
            'sort_type'     => sanitize_sql_orderby($this->request->get('sort_type', 'DESC')),
            'custom_fields' => $this->request->get('custom_fields'),
            'company_ids'   => $this->request->get('company_ids', []),
            'has_commerce'  => $this->request->get('has_commerce'),
        ];

        if ($filterType == 'advanced') {
            $queryArgs = [
                'filter_type'        => 'advanced',
                'filters_groups_raw' => $this->request->get('advanced_filters'),
            ];
        } else {
            $queryArgs = [
                'filter_type'   => 'simple',
                'tags'          => $this->request->get('tags', []),
                'statuses'      => $this->request->get('statuses', []),
                'lists'         => $this->request->get('lists', []),
            ];
        }

        $queryArgs['with'] = $with;


        if ($limit = $request->get('limit')) {
            $queryArgs['limit'] = intval($limit);
        }

        if ($offset = $request->get('offset')) {
            $queryArgs['offset'] = intval($offset) ;
        }

        $commerceColumns = $this->request->get('commerce_columns', []);

        if ($commerceColumns) {
            $queryArgs['has_commerce'] = true;
        }
        $total = $queryArgs['limit'] ?? 0;
        if(!$total) {
            $total = (new ContactsQuery([]))->getModel()->count();
        }

        $fileName = 'contacts_export_' . wp_hash($currentUserId . time()) . '.csv';
        $dir = FileSystem::getDir();
        $file_path = FileSystem::getDir() . DIRECTORY_SEPARATOR . $fileName;

        if(!file_exists($dir.'/index.php')) {
            file_put_contents(
                $dir.'/index.php',
                "<?php\n\n// Silence is golden"
            );
        }


        $metaValue = [
            'status' => 'preparing',
            'progress' => 0,
            'total' => $total,
            'offset' => $queryArgs['offset'] ?? 0,
            'file_path' => $file_path
        ];

        $meta = Meta::create([
            'key'   => 'contacts_export_meta_user_id_' . $currentUserId,
            'value' => maybe_serialize($metaValue)
        ]);

        if($meta) {
            $queryArgs['limit'] = min($metaValue['total'], 500);
            $this->prepareSingleChuckContactsCsvExportFile($queryArgs, $columns, $customFields, $commerceColumns, $currentUserId);
            if($metaValue['total'] <= 500) {
                wp_send_json_success([
                    'status' => 'succeed',
                    'progress' => $metaValue['total'],
                    'totalContacts' => $metaValue['total'],
                ]);
            }
            wp_send_json_success([
                'status' => 'file preparing',
                'progress' => $queryArgs['limit'],
                'message' => __('Processing initial batch, remaining tasks are scheduled.', 'fluent-crm'),
                'totalContacts' =>  $metaValue['total']
            ]);
            exit();
        }
        wp_send_json_error([
            'message' => __('The export is not possible.', 'fluent-crm'),
            'status' => 400
        ]);
        exit();
    }

    public function exportNotes()
    {
        $this->verifyRequest('fcrm_manage_contacts_export');
        $this->request = FluentCrm('request');

        $contactId = $this->request->get('subscriber_id');

        $routePrefix = $this->request->get('route_prefix', 'subscribers');

        $fileName = $contactId . '-contact-notes-' . gmdate('Y-m-d_H-i') . '.csv';
        if ($routePrefix == 'companies') {
            $notes = CompanyNote::where('subscriber_id', $contactId)
                ->orderBy('id', 'DESC')
                ->get();
            $fileName = $contactId . '-company-notes-' . gmdate('Y-m-d_H-i') . '.csv';
        } else {
            $notes = SubscriberNote::where('subscriber_id', $contactId)
                ->orderBy('id', 'DESC')
                ->get();
        }

        $writer = $this->getCsvWriter();
        $writer->insertOne([
            'Id',
            'Title',
            'Description',
            'Type',
            'Created At'
        ]);

        $rows = [];
        foreach ($notes as $note) {
            $rows[] = [
                $note->id,
                $this->sanitizeForCSV($note->title),
                $this->sanitizeForCSV($note->description),
                $note->type,
                $note->created_at
            ];
        }

        $writer->insertAll($rows);
        $writer->output($fileName);
        die();
    }

    public function exportCompanies()
    {
        $this->verifyRequest('fcrm_manage_contacts_export');

        $request = FluentCrm('request');

        if (!Helper::isExperimentalEnabled('company_module')) {
            die('Company Module is not enabled');
        }

        $companies = Company::with(['owner'])
            ->searchBy(sanitize_text_field($request->get('search', '')))
            ->get();

        $mainProps = [
            'id',
            'name',
            'industry',
            'description',
            'logo',
            'type',
            'email',
            'phone',
            'address_line_1',
            'address_line_2',
            'postal_code',
            'city',
            'state',
            'country',
            'employees_number',
            'linkedin_url',
            'facebook_url',
            'twitter_url',
            'website',
            'created_at',
            'updated_at'
        ];


        $header = $mainProps;
        $header[] = 'owner_email';
        $header[] = 'owner_name';

        $customFields = fluentcrm_get_custom_company_fields();

        if ($customFields) {
            foreach ($customFields as $field) {
                $header[] = $field['slug'];
            }
        }

        $writer = $this->getCsvWriter();
        $writer->insertOne($header);

        $rows = [];
        foreach ($companies as $company) {

            $row = [];

            foreach ($mainProps as $mainProp) {
                $row[] = $this->sanitizeForCSV($company->{$mainProp});
            }
            $row[] = $company->owner ? $company->owner->email : '';
            $row[] = $company->owner ? $this->sanitizeForCSV($company->owner->full_name) : '';

            if ($customFields) {
                $customValues = $company->getCustomValues();

                foreach ($customFields as $field) {
                    $value = Arr::get($customValues, $field['slug'], '');

                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }

                    $row[] = $this->sanitizeForCSV($value);
                }

            }

            $rows[] = $row;
        }

        $writer->insertAll($rows);
        $writer->output('companies_' . gmdate('Y-m-d_H:i') . '.csv');
        die();
    }

    public function importFunnel()
    {
        $this->verifyRequest('fcrm_write_funnels');
        $this->request = FluentCrm('request');
        $files = $this->request->files();
        $file = $files['file'];
        $content = file_get_contents($file);
        $funnel = json_decode($content, true);


        if (empty($funnel['type']) || $funnel['type'] != 'funnels') {
            wp_send_json([
                'message' => __('The provided JSON file is not valid', 'fluentcampaign-pro')
            ], 423);
        }

        $funnelTrigger = $funnel['trigger_name'];
        $triggers = apply_filters('fluentcrm_funnel_triggers', []);

        $funnel['title'] .= ' (Imported @ ' . current_time('mysql') . ')';

        if (!isset($triggers[$funnelTrigger])) {
            wp_send_json([
                'message'  => __('The trigger defined in the JSON file is not available on your site.', 'fluentcampaign-pro'),
                'requires' => [
                    'Trigger Name Required: ' . $funnelTrigger
                ]
            ], 423);
        }

        $sequences = $funnel['sequences'];
        $formattedSequences = [];

        $blocks = apply_filters('fluentcrm_funnel_blocks', [], (object)$funnel);
        foreach ($sequences as $sequence) {
            $actionName = $sequence['action_name'];

            if ($sequence['type'] == 'conditional') {
                $sequence = (object)$sequence;
                $sequence = (array)FunnelHelper::migrateConditionSequence($sequence, true);
                $actionName = $sequence['action_name'];
            }

            if (!isset($blocks[$actionName])) {
                wp_send_json([
                    'message'  => __('The Block Action defined in the JSON file is not available on your site.', 'fluentcampaign-pro'),
                    'requires' => [
                        'Missing Action: ' . $actionName
                    ],
                    'sequence' => $sequence
                ], 423);
            }

            $formattedSequences[] = $sequence;
        }

        unset($funnel['sequences']);

        $data = [
            'funnel'           => $funnel,
            'blocks'           => $blocks,
            'block_fields'     => apply_filters('fluentcrm_funnel_block_fields', [], (object)$funnel),
            'funnel_sequences' => $formattedSequences
        ];
        wp_send_json($data, 200);
    }

    public function importEmailSequence()
    {
        $this->verifyRequest('fcrm_manage_emails');
        $this->request = FluentCrm('request');
        $files = $this->request->files();
        $file = $files['file'];
        $content = file_get_contents($file);
        $jsonArray = json_decode($content, true);

        $sequence = Arr::get($jsonArray, 'sequence', []);


        if (empty($sequence['type']) || $sequence['type'] != 'email_sequence') {
            wp_send_json([
                'message' => __('The provided JSON file is not valid. sequence key is required in the JSON File', 'fluentcampaign-pro')
            ], 423);
        }

        $emails = Arr::get($jsonArray, 'emails', []);

        if (empty($emails)) {
            wp_send_json([
                'message' => __('The provided JSON file is not valid. No valid email sequence found', 'fluentcampaign-pro')
            ], 423);
        }

        $sequenceData = Arr::only($sequence, (new Sequence())->getFillable());

        $sequenceData['title'] = '[imported] ' . $sequenceData['title'];

        $createdSequence = Sequence::create($sequenceData);

        if (!$createdSequence) {
            wp_send_json([
                'message' => __('Failed to import', 'fluentcampaign-pro')
            ], 423);
        }

        foreach ($emails as $email) {

            $emailData = Arr::only($email, [
                'title',
                'type',
                'available_urls',
                'status',
                'template_id',
                'email_subject',
                'email_pre_header',
                'email_body',
                'delay',
                'utm_status',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'design_template',
                'scheduled_at',
                'settings'
            ]);

            $emailData['template_id'] = (int)$emailData['template_id'];

            $emailData = array_filter($emailData);

            $emailData['parent_id'] = $createdSequence->id;

            $sequenceMail = SequenceMail::create($emailData);

            // While importing, we need to set the design template data per email
            if ($sequenceMail['design_template'] == 'visual_builder') {
                $design = Arr::get($email, '_visual_builder_design', []);
                fluentcrm_update_campaign_meta($sequenceMail['id'], '_visual_builder_design', $design);
            }
        }

        wp_send_json([
            'message'  => __('Sequence has been successfully imported', 'fluent-campaign-pro'),
            'sequence' => $createdSequence
        ]);

    }

    public function exportEmailSequence()
    {
        $this->verifyRequest('fcrm_manage_emails');
        $this->request = FluentCrm('request');

        $sequenceId = $this->request->get('sequence_id');

        if (!$sequenceId) {
            die(__('Please provide sequence_id', 'fluentcampaign-pro'));
        }

        $data = [];
        $sequence = Sequence::findOrFail($sequenceId);

        $data['sequence'] = $sequence;
        $data['emails'] = SequenceMail::where('parent_id', $sequence->id)
            ->orderBy('delay', 'ASC')
            ->get();

        foreach ($data['emails'] as $email) {
            // For Export, we have to push the design template data per email
            if ($email->design_template == 'visual_builder') {
                $design = fluentcrm_get_campaign_meta($email->id, '_visual_builder_design', true);
                $email->_visual_builder_design = $design;
            }
        }

        header('Content-disposition: attachment; filename=' . sanitize_title($sequence->title, 'sequence', 'display') . '-' . $sequence->id . '.json');
        header('Content-type: application/json');
        echo wp_json_encode($data);
        exit();
    }

    public function exportDynamicSegment()
    {
        $this->verifyRequest('fcrm_manage_contact_cats');
        $this->request = FluentCrm('request');

        $segmentId = $this->request->get('segment_id');

        if (!$segmentId) {
            die('Please provide segment_id');
        }

        $segment = Meta::where('id', $segmentId)->where('object_type', 'custom_segment')->first();
        $title = '';
        if ($segment) {
            $title = $segment->value['title'];
        }

        header('Content-disposition: attachment; filename=' . sanitize_title($title, 'dynamic-segment', 'display') . '-' . $segment->id . '.json');
        header('Content-type: application/json');
        echo wp_json_encode($segment);
        exit();
    }

    public function importDynamicSegment()
    {
        $this->verifyRequest('fcrm_manage_contact_cats');
        $this->request = FluentCrm('request');
        $files = $this->request->files();
        $file = $files['file'];
        $content = file_get_contents($file);
        $segment = json_decode($content, true);
        unset($segment['id']);

        if (empty($segment['object_type']) || $segment['object_type'] != 'custom_segment') {
            wp_send_json([
                'message' => __('The provided JSON file is not valid. object type is required in the JSON File', 'fluentcampaign-pro')
            ], 423);
        }

        $title = '[imported] ' . $segment['value']['title'];
        $segment['value']['title'] = $title;

        $createdSegment = Meta::create($segment);

        if (!$createdSegment) {
            wp_send_json([
                'message' => __('Failed to import', 'fluencampaign-pro')
            ], 423);
        }

        wp_send_json([
            'message' => __('Segment has been successfully imported', 'fluent-campaign-pro'),
            'segment' => $createdSegment
        ]);

    }

    private function contactColumnMaps()
    {
        return [
            'id'              => __('ID', 'fluentcampaign-pro'),
            'user_id'         => __('User ID', 'fluentcampaign-pro'),
            'prefix'          => __('Title', 'fluentcampaign-pro'),
            'first_name'      => __('First Name', 'fluentcampaign-pro'),
            'last_name'       => __('Last Name', 'fluentcampaign-pro'),
            'email'           => __('Email', 'fluentcampaign-pro'),
            'timezone'        => __('Timezone', 'fluentcampaign-pro'),
            'address_line_1'  => __('Address Line 1', 'fluentcampaign-pro'),
            'address_line_2'  => __('Address Line 2', 'fluentcampaign-pro'),
            'postal_code'     => __('Postal Code', 'fluentcampaign-pro'),
            'city'            => __('City', 'fluentcampaign-pro'),
            'state'           => __('State', 'fluentcampaign-pro'),
            'country'         => __('Country', 'fluentcampaign-pro'),
            'ip'              => __('IP Address', 'fluentcampaign-pro'),
            'phone'           => __('Phone', 'fluentcampaign-pro'),
            'status'          => __('Status', 'fluentcampaign-pro'),
            'contact_type'    => __('Contact Type', 'fluentcampaign-pro'),
            'source'          => __('Source', 'fluentcampaign-pro'),
            'date_of_birth'   => __('Date Of Birth', 'fluentcampaign-pro'),
            'last_activity'   => __('Last Activity', 'fluentcampaign-pro'),
            'created_at'      => __('Created At', 'fluentcampaign-pro'),
            'updated_at'      => __('Updated At', 'fluentcampaign-pro'),
            'lists'           => __('Lists', 'fluentcampaign-pro'),
            'tags'            => __('Tags', 'fluentcampaign-pro'),
            'companies'       => __('Companies', 'fluentcampaign-pro'),
            'primary_company' => __('Primary Company', 'fluentcampaign-pro'),
        ];
    }

    public function exportEmailTemplate()
    {
        $this->verifyRequest('fcrm_manage_email_templates');
        $templateId = (int)$_REQUEST['template_id'];

        if (!$templateId) {
            die('Please provide Template ID');
        }

        $template = Template::findOrFail($templateId);

        $editType = get_post_meta($template->ID, '_edit_type', true);
        if (!$editType) {
            $editType = 'html';
        }

        $footerSettings = false;
        if ($template) {
            $footerSettings = get_post_meta($template->ID, '_footer_settings', true);
        }

        if (!$footerSettings) {
            $footerSettings = [
                'custom_footer'  => 'no',
                'footer_content' => ''
            ];
        }

        $templateData = [
            'is_fc_template'  => 'yes',
            'post_title'      => $template->post_title,
            'post_content'    => $template->post_content,
            'post_excerpt'    => $template->post_excerpt,
            'email_subject'   => get_post_meta($template->ID, '_email_subject', true),
            'edit_type'       => $editType,
            'design_template' => get_post_meta($template->ID, '_design_template', true),
            'settings'        => [
                'template_config' => get_post_meta($template->ID, '_template_config', true),
                'footer_settings' => $footerSettings
            ]
        ];

        $templateData = apply_filters('fluent_crm/editing_template_data', $templateData, $template);

        header('Content-disposition: attachment; filename=Email-Template-' . sanitize_title($template->post_title, 'template', 'display') . '-' . $template->ID . '.json');
        header('Content-type: application/json');
        echo wp_json_encode($templateData);
        exit();
    }

    public function importEmailTemplate()
    {
        $this->verifyRequest('fcrm_manage_email_templates');

        $builtInTemplate = Arr::get($_REQUEST['body'], 'file');
        $this->request = FluentCrm('request');

        if ($builtInTemplate) {
            $file = $builtInTemplate;
        } else {
            $files = $this->request->files();
            $file = $files['file'];
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (empty($data['post_title']) || empty($data['post_content']) || Arr::get($data, 'is_fc_template') != 'yes') {
            wp_send_json([
                'message'  => __('The provided JSON file is not valid.', 'fluentcampaign-pro'),
                'requires' => [
                    __('File is not valid', 'fluentcampaign-pro')
                ]
            ], 423);
        }

        $postData = Arr::only($data, ['post_title', 'post_content', 'post_excerpt']);

        if (empty($postData['post_title'])) {
            $postData['post_title'] = 'Imported Email Template @ ' . current_time('mysql');
        } else {
            $postData['post_title'] = sanitize_text_field($postData['post_title']);
        }

        $postData['post_title'] = '[Imported] ' . $postData['post_title'];

        if (empty($data['email_subject'])) {
            $data['email_subject'] = $data['post_title'];
        } else {
            $data['email_subject'] = sanitize_text_field($data['email_subject']);
        }

        $postData['post_modified'] = current_time('mysql');
        $postData['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
        $postData['post_date'] = current_time('mysql');
        $postData['post_date_gmt'] = gmdate('Y-m-d H:i:s');
        $postData['post_type'] = fluentcrmTemplateCPTSlug();

        $templateId = wp_insert_post($postData);

        if (is_wp_error($templateId)) {
            wp_send_json([
                'message'  => $templateId->get_error_message(),
                'requires' => [
                    __('Could not create the template', 'fluent-crm')
                ]
            ], 423);
        }

        update_post_meta($templateId, '_email_subject', $data['email_subject']);
        if ($editType = Arr::get($data, '_edit_type')) {
            update_post_meta($templateId, '_edit_type', $editType);
        }

        if (isset($data['design_template'])) {
            update_post_meta($templateId, '_design_template', sanitize_text_field($data['design_template']));
        }

        if (isset($data['_visual_builder_design'])) {
            update_post_meta($templateId, '_visual_builder_design', $data['_visual_builder_design']);
        }

        if (!empty($data['settings']['template_config'])) {
            update_post_meta($templateId, '_template_config', $data['settings']['template_config']);
        }

        if (!empty($data['settings']['footer_settings'])) {
            update_post_meta($templateId, '_footer_settings', $data['settings']['footer_settings']);
        }

        wp_send_json([
            'message'     => __('Templates has been successfully imported', 'fluentcampaign-pro'),
            'template_id' => $templateId
        ]);
    }

    private function verifyRequest($permission = 'fcrm_manage_contacts_export')
    {
        if (PermissionManager::currentUserCan($permission)) {
            return true;
        }

        die('You do not have permission');
    }

    private function getCsvWriter()
    {
        if (!class_exists('\League\Csv\Writer')) {
            include FLUENTCRM_PLUGIN_PATH . 'app/Services/Libs/csv/autoload.php';
        }

        return \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
    }

    private function sanitizeForCSV($content)
    {
        $formulas = ['=', '-', '+', '@', "\t", "\r"];

        if (Str::startsWith($content, $formulas)) {
            $content = "'" . $content;
        }

        return $content;
    }

    public function exportRecurringCampaign()
    {
        $this->verifyRequest('fcrm_manage_emails');
        $campaignId = (int)$_REQUEST['campaign_id'];

        if (!$campaignId) {
            die(__('Please provide Campaign ID', 'fluentcampaign-pro'));
        }

        $campaign = RecurringCampaign::findOrFail($campaignId);

        $campaignData = [
            'title'            => $campaign->title,
            'settings'         => $campaign->settings,
            'template_id'      => $campaign->template_id,
            'email_subject'    => $campaign->email_subject,
            'email_pre_header' => $campaign->email_pre_header,
            'email_body'       => $campaign->email_body,
            'utm_status'       => $campaign->utm_status,
            'utm_source'       => $campaign->utm_source,
            'utm_medium'       => $campaign->utm_medium,
            'utm_campaign'     => $campaign->utm_campaign,
            'utm_term'         => $campaign->utm_term,
            'utm_content'      => $campaign->utm_content,
            'design_template'  => $campaign->design_template,
            'status'           => 'draft'
        ];

        $campaignData = apply_filters('fluent_crm/editing_recurring_campaign_data', $campaignData, $campaign);

        header('Content-disposition: attachment; filename=Recurring-email-campaign-' . sanitize_title($campaign->title, 'recurring-campaign', 'display') . '-' . $campaign->id . '.json');
        header('Content-type: application/json');
        echo wp_json_encode($campaignData);
        exit();
    }

    public function importRecurringCampaign()
    {
        $this->verifyRequest('fcrm_manage_emails');

        $this->request = FluentCrm('request');
        $files = $this->request->files();

        if (!isset($files['file'])) {
            wp_send_json([
                'message' => __('No file uploaded', 'fluentcampaign-pro')
            ], 423);
        }

        $file = $files['file'];
        $content = file_get_contents($file);
        $campaignData = json_decode($content, true);

        $settings = Arr::get($campaignData, 'settings.scheduling_settings', []);

        if (empty($campaignData) || empty(Arr::get($settings, 'time'))) {
            wp_send_json([
                'message' => __('Missing or invalid "time" in scheduling settings.', 'fluentcampaign-pro')
            ], 423);
        }

        if (Arr::get($settings, 'type') == 'weekly' && empty(Arr::get($settings, 'day'))) {
            wp_send_json([
                'message' => __('Missing "day" for weekly scheduling type.', 'fluentcampaign-pro')
            ], 423);
        }


        if (empty($campaignData['title'])) {
            $campaignData['title'] = 'Imported Recurring Campaigns @ ' . current_time('mysql');
        } else {
            $campaignData['title'] = '[Imported] ' . sanitize_text_field($campaignData['title']) . ' - ' .current_time('mysql');
        }

        $newCampaign = RecurringCampaign::create($campaignData);

        if (is_wp_error($newCampaign)) {
            wp_send_json([
                'message'  => $newCampaign->get_error_message(),
                'requires' => [
                    'Could not create the campaign'
                ]
            ], 423);
        }

        wp_send_json([
            'message' => __('Recurring Email Campaigns has been successfully imported', 'fluentcampaign-pro'),
            'campaign_id' => $newCampaign['id']
        ]);
    }

    public function exportArchivedCampaignEmails()
    {
        $this->verifyRequest('fcrm_manage_contacts_export');
        $campaignId = (int)$_REQUEST['campaign_id'];
        $filterType = sanitize_text_field($_REQUEST['filter_type']);

        if (!$campaignId) {
            die(__('Please provide Campaign ID', 'fluentcampaign-pro'));
        }

        $fileName = sprintf(
            '%d-archived-campaign-emails-%s.csv',
            $campaignId,
            gmdate('Y-m-d_H-i')
        );

        $emailsQuery = CampaignEmail::with(['subscriber'])->where('campaign_id', $campaignId);

        if ($filterType == 'click') {
            $emailsQuery = $emailsQuery->whereNotNull('click_counter')
                ->orderBy('click_counter', 'DESC');
        } else if ($filterType == 'view') {
            $emailsQuery = $emailsQuery->where('is_open', '>', 0)
                ->orderBy('is_open', 'DESC');
        } else if ($filterType == 'unopened') {
            $emailsQuery = $emailsQuery->where('is_open', '==', 0)
                ->orderBy('is_open', 'DESC');
        }

        $emails = $emailsQuery->get()->toArray();

        // Generate CSV
        $this->generateArchivedCampaignEmailsCsv($emails, $filterType, $fileName);
    }

    private function generateArchivedCampaignEmailsCsv($emails, $filterType, $fileName)
    {
        $writer = $this->getCsvWriter();
        $headers = ['Id', 'Name', 'Email', 'Status', 'Created At'];
        if (in_array($filterType, ['click', 'all'])) {
            $headers[] = 'Click Counter';
        }
        if (in_array($filterType, ['view', 'all'])) {
            $headers[] = 'Is Open';
        }

        $writer->insertOne($headers);

        $rows = array_map(function ($email) use ($filterType) {
            $row = [
                Arr::get($email, 'id'),
                Arr::get($email, 'subscriber.full_name'),
                Arr::get($email, 'subscriber.email'),
                Arr::get($email, 'status'),
                Arr::get($email, 'created_at')
            ];
            if (in_array($filterType, ['click', 'all'])) {
                $row[] = Arr::get($email, 'click_counter');
            }
            if (in_array($filterType, ['view', 'all'])) {
                $row[] = Arr::get($email, 'is_open') ? 'opened' : '';
            }
            return $row;
        }, $emails);

        $writer->insertAll($rows);
        $writer->output($fileName);
        exit();
    }

    public function exportEmailCampaign()
    {
        $this->verifyRequest('fcrm_manage_emails');
        $campaignId = (int)$_REQUEST['campaign_id'];

        if (!$campaignId) {
            die(__('Please provide Campaign ID', 'fluentcampaign-pro'));
        }

        $campaign = Campaign::findOrFail($campaignId);

        $campaignData = [
            'title'            => $campaign->title,
            'slug'             => $campaign->slug . '-' . time(),
            'email_body'       => $campaign->email_body,
            'status'           => 'draft',
            'template_id'      => $campaign->template_id,
            'email_subject'    => $campaign->email_subject,
            'email_pre_header' => $campaign->email_pre_header,
            'utm_status'       => $campaign->utm_status,
            'utm_source'       => $campaign->utm_source,
            'utm_medium'       => $campaign->utm_medium,
            'utm_campaign'     => $campaign->utm_campaign,
            'utm_term'         => $campaign->utm_term,
            'utm_content'      => $campaign->utm_content,
            'design_template'  => $campaign->design_template,
            'created_by'       => get_current_user_id(),
            'settings'         => $campaign->settings,
            'labels'           => $campaign->getFormattedLabels()->toArray()
        ];

        $campaignData = apply_filters('fluent_crm/email_campaign_export_data', $campaignData, $campaign);
        $fileName = 'email-campaign-' . sanitize_file_name(sanitize_title($campaign->title)) . '-' . $campaign->id . '.json';
        if (!headers_sent()) {
            header('Content-Disposition: attachment; filename=' . $fileName);
            header('Content-Type: application/json');
        }
        echo wp_json_encode($campaignData);
        exit();
    }

    public function importEmailCampaign()
    {
        $this->verifyRequest('fcrm_manage_emails');

        $this->request = FluentCrm('request');
        $files = $this->request->files();

        if (!isset($files['file'])) {
            wp_send_json([
                'message' => __('No file uploaded', 'fluentcampaign-pro')
            ], 423);
        }

        $file = $files['file'];
        $content = file_get_contents($file);
        $campaignData = json_decode($content, true);

        $campaignData['title'] = empty($campaignData['title'])
            ? 'Imported Email Campaigns @ ' . current_time('mysql')
            : '[Imported] ' . sanitize_text_field($campaignData['title']) . ' - ' . current_time('mysql');


        $formattedData = [
            'title'            => sanitize_text_field($campaignData['title']),
            'slug'             => sanitize_title($campaignData['slug']) . '-' . time(),
            'email_body'       => wp_kses_post($campaignData['email_body']),
            'status'           => sanitize_text_field($campaignData['status']),
            'template_id'      => absint($campaignData['template_id']),
            'email_subject'    => sanitize_text_field($campaignData['email_subject']),
            'email_pre_header' => sanitize_text_field($campaignData['email_pre_header']),
            'utm_status'       => $campaignData['utm_status'],
            'utm_source'       => sanitize_text_field($campaignData['utm_source']),
            'utm_medium'       => sanitize_text_field($campaignData['utm_medium']),
            'utm_campaign'     => sanitize_text_field($campaignData['utm_campaign']),
            'utm_term'         => sanitize_text_field($campaignData['utm_term']),
            'utm_content'      => sanitize_text_field($campaignData['utm_content']),
            'design_template'  => sanitize_text_field($campaignData['design_template']),
            'created_by'       => get_current_user_id(),
            'settings'         => $campaignData['settings']
        ];

        $campaign = Campaign::create($formattedData);

        if (is_wp_error($campaign)) {
            wp_send_json([
                'message'  => $campaign->get_error_message(),
                'requires' => [
                    __('Could not create the email campaign', 'fluentcampaign-pro')
                ]
            ], 423);
        }

        if (isset($campaignData['labels']) && !empty($campaignData['labels'])) {
            $labelIds = [];

            foreach ($campaignData['labels'] as $label) {
                $existLabel = Label::where('slug', '=', sanitize_text_field($label['slug']))->first();
                if ($existLabel) {
                    $labelIds[] = $existLabel->id;
                    continue;
                }

                $labelData = [
                    'slug'  => sanitize_text_field($label['slug']),
                    'title' => sanitize_text_field($label['title']),
                    'settings' => [
                        'color' => sanitize_hex_color($label['color'])
                    ]
                ];

                // Create the new label
                $newLabel = Label::create($labelData);
                $labelIds[] = $newLabel->id;
            }

            if (!empty($labelIds)) {
                $campaign->attachLabels($labelIds);
            }
        }

        wp_send_json([
            'message' => __('Email Campaigns has been successfully imported', 'fluentcampaign-pro'),
            'campaign_id' => $campaign['id']
        ]);
    }
    public function prepareSingleChuckContactsCsvExportFile($queryArgs, $columns, $customFields, $commerceColumns, $currentUserId)
    {
        try {
            $meta = Meta::where('key', 'contacts_export_meta_user_id_' . $currentUserId)->first();
            $metaValue = maybe_unserialize($meta->value);
            $subscribers = (new ContactsQuery($queryArgs))->get();

            $maps = $this->contactColumnMaps();
            $header = Arr::only($maps, $columns);
            $header = array_intersect($maps, $header);

            $insertHeaders = $header;
            $customHeaders = [];
            if ($customFields) {
                $allCustomFields = fluentcrm_get_custom_contact_fields();
                foreach ($allCustomFields as $field) {
                    if (in_array($field['slug'], $customFields)) {
                        $insertHeaders[$field['slug']] = $field['label'];
                        $customHeaders[] = $field['slug'];
                    }
                }
            }

            if ($commerceColumns) {
                foreach ($commerceColumns as $column) {
                    $insertHeaders['_commerce_' . $column] = ucwords(implode(' ', explode('_', $column)));
                }
            }

            $file_path = $metaValue['file_path'];

            // Open the file in append mode
            if (!file_exists(dirname($file_path))) {
                wp_mkdir_p(dirname($file_path), 0755, true);
            }

            $file = fopen($file_path, 'a');

            if ($file === false) {
                return new \WP_Error('file_open_error', __('Failed to open file for writing.'));
            }

            // Write the header row to CSV if it's the first chunk
            if ($metaValue['progress'] === 0) {
                fputcsv($file, $insertHeaders);
            }

            foreach ($subscribers as $subscriber) {
                $row = [];
                foreach ($header as $headerKey => $column) {
                    if (in_array($headerKey, ['lists', 'tags', 'companies'])) {
                        $strings = [];
                        foreach ($subscriber->{$headerKey} as $item) {
                            $field = $headerKey == 'companies' ? 'name' : 'title';
                            $strings[] = $this->sanitizeForCSV($item->{$field});
                        }
                        $row[] = implode(', ', $strings);
                    } elseif ($headerKey == 'primary_company') {
                        $row[] = $subscriber->company->name;
                    } else {
                        $row[] = $this->sanitizeForCSV($subscriber->{$headerKey});
                    }
                }
                if ($customHeaders) {
                    $customValues = $subscriber->custom_fields();
                    foreach ($customHeaders as $valueKey) {
                        $value = Arr::get($customValues, $valueKey, '');
                        if (is_array($value)) {
                            $value = implode(', ', array_map([$this, 'sanitizeForCSV'], $value));
                        }
                        $row[] = $value;
                    }
                }

                if ($commerceColumns) {
                    foreach ($commerceColumns as $column) {
                        if ($subscriber->commerce_by_provider) {
                            $row[] = $this->sanitizeForCSV($subscriber->commerce_by_provider->{$column});
                        } else {
                            $row[] = '';
                        }
                    }
                }

                fputcsv($file, $row);
            }

            $limit = 500;
            $metaValue['progress'] += $queryArgs['limit'];
            $metaValue['offset'] += $queryArgs['limit'];
            $remainingContacts = $metaValue['total'] - $metaValue['progress'];
            $metaValue['status'] = $remainingContacts > 0 ? 'preparing' : 'succeed';
            $meta->value = maybe_serialize($metaValue);
            $meta->save();

            $queryArgs['offset'] = ($queryArgs['offset'] ?? 0) + $limit;

            $queryArgs['limit'] = min($remainingContacts, $limit);
            if($remainingContacts > 0) {
                as_enqueue_async_action('fluentcrm_prepare_contacts_csv_export_file', [
                    $queryArgs, $columns, $customFields, $commerceColumns, $currentUserId
                ]);

            };
        } catch (\Exception $e) {
            Meta::where('key', 'contacts_export_meta_user_id_' . $currentUserId)->delete();
            exit();
        }
    }
    public function exportContactsCsvStatus()
    {
        $meta = Meta::where('key', 'contacts_export_meta_user_id_' . get_current_user_id())->first();
        if($meta) {
            $value = maybe_unserialize($meta->value);
            wp_send_json_success([
                'status' => $value['status'],
                'progress' =>  $value['progress'],
                'total' => $value['total']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Export status not found.'),
                'status' => 404
            ]);
        }
    }
    public function downloadContactsCsvFile()
    {
        $this->verifyRequest('fcrm_manage_contacts_export');
        $userId = get_current_user_id(); // Get the current user ID

        $meta = Meta::where('key' , 'contacts_export_meta_user_id_' . $userId)->first();


        if($meta) {
            $metaValue = \maybe_unserialize($meta->value);

            $status = $metaValue['status'];
            $file_path = $metaValue['file_path'];

            if ($status === 'succeed') {

                // Set headers for download
                header('Content-Description: File Transfer');
                header('Content-Type: application/csv');
                header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($file_path));

                // Read the file and send it to the output buffer
                readfile($file_path);

                // Delete the file after download
                unlink($file_path);
                $meta->delete();

                // Exit after the file is sent to prevent additional output
                exit();
            } else {
                wp_send_json([
                    'success' => false,
                    'message' => __('The export is not successful.', 'fluent-crm')
                ]);
            }

        } else {
            wp_send_json([
                'success' => false,
                'message' => __('Export not found.', 'fluent-crm')
            ]);
        }

    }
    public function deletePreviousContactsCsvExportMetaAndFile($currentUserId)
    {
        $meta = Meta::where('key', 'contacts_export_meta_user_id_' . get_current_user_id())->first();
        if($meta) {
            $metaValue = maybe_unserialize($meta->value);
            $file_path = $metaValue['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $meta->delete();
        }
        return true;
    }
}
