<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\Framework\Support\Arr;
use FluentForm\App\Models\Submission;
use FluentForm\App\Modules\Acl\Acl;
use FluentForm\App\Services\FormBuilder\ShortCodeParser;

/**
 *  FormSubmissions Class
 *
 * Fluent Forms Integration Class
 *
 * @package FluentCrm\App\Hooks
 *
 * @version 1.0.0
 */
class FormSubmissions
{
    public function register()
    {
        if (defined('FLUENTFORM')) {
            add_filter('fluent_crm/form_submission_providers', [$this, 'pushDefaultFormProviders']);
            add_filter('fluentcrm_get_form_submissions_fluentform', [$this, 'getFluentFormSubmissions'], 10, 2);
            add_filter('fluent_crm/dynamic_contact_item_view_fluentform', [$this, 'getFluentFormSubmissionDetails'], 10, 2);

            // Smartcodes
            add_filter('fluentform/editor_shortcodes', function ($smartCodes) {
                $smartCodes[0]['shortcodes']['{fluentcrm.CONTACT_DATA_KEY}'] = 'FluentCRM Data';
                return $smartCodes;
            }, 100, 1);
            add_filter('fluentform/editor_shortcode_callback_group_fluentcrm', [$this, 'parseEditorCodes'], 10, 3);

        }

    }

    public function pushDefaultFormProviders($providers)
    {
        if (defined('FLUENTFORM')) {
            $providers['fluentform'] = [
                'title' => __('Form Submissions (Fluent Forms)', 'fluent-crm'),
                'name'  => __('Fluent Forms', 'fluent-crm')
            ];
        }
        return $providers;
    }

    public function getFluentFormSubmissions($data, $subscriber)
    {
        if (!defined('FLUENTFORM')) {
            return $data;
        }

        $app = fluentCrm();
        $page = intval($app->request->get('page', 1));
        $per_page = intval($app->request->get('per_page', 10));

        $query = fluentCrmDb()->table('fluentform_submissions')
            ->select([
                'fluentform_submissions.id',
                'fluentform_submissions.form_id',
                'fluentform_forms.title',
                'fluentform_submissions.status',
                'fluentform_submissions.created_at'
            ])
            ->join('fluentform_forms', 'fluentform_forms.id', '=', 'fluentform_submissions.form_id')
            ->where(function ($query) use ($subscriber) {
                $query->where('fluentform_submissions.response', 'LIKE', '%' . $subscriber->email . '%');
                if ($subscriber->user_id) {
                    $query->orWhere('fluentform_submissions.user_id', $subscriber->user_id);
                }
            });

        $total = $query->count();

        $submissions = $query
            ->limit($per_page)
            ->offset($per_page * ($page - 1))
            ->orderBy('fluentform_submissions.id', 'desc')
            ->get();

        $formattedSubmissions = [];
        foreach ($submissions as $submission) {
            $submissionUrl = admin_url('admin.php?page=fluent_forms&route=entries&form_id=' . $submission->form_id . '#/entries/' . $submission->id);
            $actionUrl = '<a target="_blank" rel="noopener" href="' . $submissionUrl . '">#' . $submission->id . '</a>';

            $badgeClass = 'fcrm_badge';

            if ($submission->status === 'read') {
                $badgeClass .= ' fcrm_badge_success';
            } else if ($submission->status === 'unread') {
                $badgeClass .= ' fcrm_badge_warning';
            }

            $formattedSubmissions[] = [
                '__id'         => $submission->id,
                'id'           => $actionUrl,
                'title'        => $submission->title,
                'Status'       => '<span class="' . $badgeClass . '">' . $submission->status . '</span>',
                'Submitted At' => '<a target="_blank" rel="noopener" href="' . $submissionUrl . '">' . $submission->created_at . '</a>',
                'action'       => 'view'
            ];
        }

        return [
            'total'          => $total,
            'data'           => $formattedSubmissions,
            'columns_config' => [
                'id'           => [
                    'label' => __('ID', 'fluent-crm'),
                    'width' => '100px'
                ],
                'title'        => [
                    'label' => __('Form Title', 'fluent-crm')
                ],
                'Status'       => [
                    'label' => __('Status', 'fluent-crm'),
                    'width' => '100px'
                ],
                'Submitted At' => [
                    'label' => __('Submitted At', 'fluent-crm'),
                    'width' => '180px'
                ],
                'action'       => [
                    'quick_action' => true,
                    'label'        => __('Action', 'fluent-crm'),
                    'width'        => '100px'
                ]
            ]
        ];
    }

    public function getFluentFormSubmissionDetails($dataView, $params)
    {
        $submissionId = (int)Arr::get($params, '__id');

        if (!$submissionId) {
            $dataView['content_html'] = '<div class="fc-crm-no-data-view"><p>' . __('No submission found', 'fluent-crm') . '</p></div>';
            return $dataView;
        }

        $submission = Submission::with(['form'])->find($submissionId);
        if (!$submission || !$submission->form) {
            $dataView['content_html'] = '<div class="fc-crm-no-data-view"><p>' . __('No submission found', 'fluent-crm') . '</p></div>';
            return $dataView;
        }

        $form = $submission->form;

        if (!Acl::hasPermission('fluentform_entries_viewer', $form->id)) {
            $dataView['title'] = __('Permission Denied', 'fluent-crm');
            $dataView['content_html'] = '<div class="fc-crm-no-data-view"><p>' . __('You do not have permission to view this submission.', 'fluent-crm') . '</p></div>';
            return $dataView;
        }

        $submittedData = json_decode($submission->response, true);
        $html = '<b>Submission Details</b><br/><br/><div>{all_data}</div>';
        if ($submission->payment_status) {
            $html .= '<h2>Payment Details</h2>';
            $html .= '{payment.receipt}';
        }

        $html .= '<h4>Additional Details:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Source URL:</strong> {submission.source_url}</li>';
        $html .= '<li><strong>Serial #:</strong> {submission.serial_number}</li>';
        $html .= '<li><strong>Browser:</strong> {submission.browser} / {submission.device}</li>';
        $html .= '<li><strong>Date:</strong> {submission.created_at}</li>';
        $html .= '</ul>';

        $body = ShortCodeParser::parse(
            $html,
            $submission->id,
            $submittedData,
            $form,
            false,
            true
        );

        $dataView['title'] = sprintf(__('Submission #%d - %s', 'fluent-crm'), $submission->id, $form->title);
        $dataView['content_html'] = '<style>.fc-crm-form-submission-view table { width: 100% !important; }</style><div class="fc-crm-form-submission-view">' . $body . '</div>';

        $dataView['footer_content'] = '<a class="el-button fcrm_primary_btn" target="_blank" rel="noopener" href="' . admin_url('admin.php?page=fluent_forms&route=entries&form_id=' . $form->id . '#/entries/' . $submission->id) . '">View in FluentForms</a>';

        return $dataView;

    }

    public function parseEditorCodes($code, $form, $keys)
    {
        $contact = FluentCrmApi('contacts')->getCurrentContact(true, true);

        $providedKey = $keys[0];

        // maybe has fallback value
        $dynamicKey = explode('|', $providedKey);
        $fallBack = '';
        if (count($dynamicKey) > 1) {
            $fallBack = $dynamicKey[1];
        }
        $ref = $dynamicKey[0];

        if (!$contact) {
            return $fallBack;
        }

        $validMainProps = (new Subscriber)->getFillable();
        $validMainProps[] = 'id';

        if (in_array($ref, $validMainProps)) {
            if ($contact->{$ref}) {
                return $contact->{$ref};
            }

            return $fallBack;
        }

        // Maybe it's a custom field
        $customData = $contact->custom_fields();

        if ($customData && !empty($customData[$ref])) {
            $value = $customData[$ref];
            if (is_array($value)) {
                return implode(',', $value);
            }

            return $customData[$ref];
        }

        $listMaps = [
            'list_ids'    => 'id',
            'list_titles' => 'title',
            'list_slugs'  => 'slug'
        ];

        $tagMaps = [
            'tag_ids'    => 'id',
            'tag_titles' => 'title',
            'tag_slugs'  => 'slug'
        ];


        if (isset($listMaps[$ref])) {
            $listProps = [];
            foreach ($contact->lists as $list) {
                $listProps[] = $list->{$listMaps[$ref]};
            }
            if ($listProps) {
                return trim(implode(', ', $listProps));
            }
        } else if (isset($tagMaps[$ref])) {
            $tagProps = [];
            foreach ($contact->tags as $tag) {
                $tagProps[] = $tag->{$tagMaps[$ref]};
            }
            if ($tagProps) {
                return trim(implode(', ', $tagProps));
            }
        }

        return $fallBack;
    }
}
