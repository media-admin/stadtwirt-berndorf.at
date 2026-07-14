<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Models\Company;
use FluentCrm\App\Models\CompanyNote;
use FluentCrm\App\Models\CustomCompanyField;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Services\AutoSubscribe;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\Libs\FileSystem;
use FluentCrm\App\Services\Sanitize;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Support\Collection;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $order = [
            'by'    => $request->getSafe('sort_by', 'sanitize_sql_orderby', 'id'),
            'order' => $request->getSafe('sort_order', 'sanitize_sql_orderby', 'DESC')
        ];

        $companies = Company::orderBy($order['by'], $order['order'])
            ->with(['owner'])
            ->searchBy($request->getSafe('search', 'sanitize_text_field'));

        $inlineFilters = $request->get('inline_filters', []);

        if ($inlineFilters && is_array($inlineFilters)) {
            $inlineFilters = array_filter($inlineFilters);

            foreach ($inlineFilters as $key => $values) {
                if (!is_array($values)) {
                    continue;
                }
                $values = array_map('sanitize_text_field', $values);

                if ($key == 'company_categories') {
                    $companies->whereIn('industry', $values);
                } else if ($key == 'company_types') {
                    $companies->whereIn('type', $values);
                }
            }
        }

        $companies = $companies->paginate();

        foreach ($companies as $company) {
            $company->contacts_count = $company->getContactsCount();
        }

        return [
            'companies' => $companies
        ];
    }

    public function searchCompanies(Request $request)
    {
        $search = $request->getSafe('search', 'sanitize_text_field');
        $companies = Company::orderBy('name', 'ASC')
            ->searchBy($search);

        $subscriberId = $request->getSafe('subscriber_id', 'intval');

        if ($subscriberId) {
            $companies = $companies->doesnthave('subscribers', 'and', function ($query) use ($subscriberId) {
                $query->where('fc_subscribers.id', $subscriberId);
            });
        }

        $companies = $companies->limit(50)->get();

        $formatted = [];

        $values = (array)$request->get('values', []);

        $pushedIds = [];

        foreach ($companies as $company) {
            $pushedIds[] = $company->id;
            $formatted[] = [
                'id'      => $company->id,
                'name'    => $company->name,
                'email'   => $company->email,
                'logo'    => $company->logo,
                'phone'   => $company->phone,
                'website' => $company->website
            ];
        }

        if ($values && $newIds = array_diff($values, $pushedIds)) {
            $newItems = Company::whereIn('id', $newIds)
                ->get();
            foreach ($newItems as $item) {
                $formatted[] = [
                    'id'      => $item->id,
                    'name'    => $item->name,
                    'email'   => $item->email,
                    'logo'    => $item->logo,
                    'phone'   => $item->phone,
                    'website' => $item->website
                ];
            }
        }

        return [
            'results'  => $formatted,
            'has_more' => Company::count() >= 50
        ];
    }

    public function searchUnattachedContacts(Request $request)
    {
        $search = $request->getSafe('search', 'sanitize_text_field');
        $companyId = $request->getSafe('company_id', 'intval', '');

        $contacts = Subscriber::orderBy('id', 'DESC')
            ->searchBy($search)
            ->whereDoesntHave('companies', function ($query) use ($companyId) {
                $query->where('fc_companies.id', $companyId);
            })
            ->limit($request->getSafe('limit', 'intval', 20))
            ->get();

        return [
            'results' => $contacts
        ];
    }

    public function attachSubscribers(Request $request)
    {
        $subscriberIds = $request->get('subscriber_ids');
        $companyIds = $request->get('company_ids');

        $result = FluentCrmApi('companies')->attachContactsByIds($subscriberIds, $companyIds);

        if (!$result) {
            return $this->sendError('Invalid data', 422);
        }

        return [
            'message'   => __('Selected Companies have been attached successfully', 'fluent-crm'),
            'companies' => $result['companies']
        ];
    }

    public function detachSubscribers(Request $request)
    {
        $subscriberIds = $request->get('subscriber_ids');
        $companyIds = $request->get('company_ids');

        $result = FluentCrmApi('companies')->detachContactsByIds($subscriberIds, $companyIds);

        if (!$result) {
            return $this->sendError('Invalid data', 422);
        }
        $result['message'] = __('Company has been successfully detached', 'fluent-crm');

        return $result;
    }

    /**
     * Find a company.
     */
    public function find(Request $request, $id)
    {

        $findBy = $request->getSafe('find_by', 'sanitize_text_field', 'id');
        $findByValue = $request->getSafe('find_by_value', 'sanitize_text_field');

        $customFindBys = ['name', 'email', 'phone'];

        if (in_array($findBy, $customFindBys)) {
            $company = Company::where($findBy, $findByValue)->first();
            if (!$company) {
                return $this->sendError('Company not found', 422);
            }
        } else {
            $company = Company::findOrFail($id);
        }

        $company->load(['owner']);
        if ($company->owner) {
            $company->owner->stats = $company->owner->stats();
        }

        $company->contacts_count = $company->getContactsCount();

        return [
            'company' => $company
        ];
    }

    /**
     * Store a company.
     * @param Request $request
     * @return \WP_REST_Response | array
     */
    public function create(Request $request)
    {
        $allData = $request->all();

        $allData = $this->validate($allData, [
            'name' => 'required|unique:fc_companies,name'
        ]);

        $data = $this->getSanitizedData($allData);

        if (empty($data['logo']) && !empty($allData['website']) && Helper::isExperimentalEnabled('company_auto_logo')) {
            $data['logo'] = $this->getLogoWebsiteUrl($allData['website']);
        }

        $company = FluentCrmApi('companies')->createOrUpdate($data);

        if ($contactId = $request->getSafe('intended_contact_id', 'intval')) {
            $contact = Subscriber::find($contactId);
            if ($contact) {
                $contact->attachCompanies([$company->id]);
                if (!$contact->company_id) {
                    $contact->company_id = $company->id;
                    $contact->save();
                }
            }
        }

        return [
            'message' => __('Company has been created successfully', 'fluent-crm'),
            'company' => $company
        ];
    }

    public function update(Request $request, $id = 0)
    {
        if ($id == 0) {
            return $this->create($request);
        }

        $company = Company::findOrFail($id);

        $allData = $request->all();

        $name = sanitize_text_field($allData['name']);

        if (Company::where('id', '!=', $id)->where('name', $name)->first()) {
            return $this->sendError([
                'message' => __('Company name already exists. Please use a different company name', 'fluent-crm')
            ], 422);
        }

        $data = $this->getSanitizedData($allData);

        $company = FluentCrmApi('companies')->createOrUpdate($data);

        return [
            'message' => __('Company has been updated', 'fluent-crm'),
            'company' => $company
        ];

    }

    public function updateProperty()
    {
        $column = $this->request->getSafe('property', 'sanitize_text_field');
        $value = $this->request->getSafe('value', 'sanitize_text_field');
        $companyIds = $this->request->get('companies');
        
        if (!is_array($companyIds)) {
            $companyIds = [$companyIds];
        }
        $companyIds = array_map('intval', $companyIds);
        $companyIds = array_filter($companyIds);

        $validColumns = ['type', 'logo', 'owner_id', 'refetch_logo'];
        $types = Helper::companyTypes();
        $statuses = Helper::companyTypes();

        $this->validate([
            'column'      => $column,
            'value'       => $value,
            'company_ids' => $companyIds
        ], [
            'column'      => 'required',
            'value'       => 'required',
            'company_ids' => 'required'
        ]);

        if (!in_array($column, $validColumns)) {
            return $this->sendError([
                'message' => __('Column is not valid', 'fluent-crm')
            ]);
        }

        if ($column == 'type' && !in_array($value, $types)) {
            return $this->sendError([
                'message' => __('Value is not valid', 'fluent-crm')
            ]);
        } else if ($column == 'status' && !in_array($value, $statuses)) {
            return $this->sendError([
                'message' => __('Value is not valid', 'fluent-crm')
            ]);
        }

        $companies = Company::whereIn('id', $companyIds)->get();

        foreach ($companies as $company) {

            if ($column == 'refetch_logo') {
                $newLogo = $this->getLogoWebsiteUrl($company->website);
                if ($newLogo) {
                    $company->logo = $newLogo;
                    $company->save();
                    return [
                        'message'      => __('Logo has been updated successfully', 'fluent-crm'),
                        'updated_logo' => $newLogo
                    ];
                }

                return $this->sendError([
                    'message' => __('Sorry, we could not find the logo from website. Please upload manually', 'fluent-crm')
                ]);
            }

            $oldValue = $company->{$column};
            if ($oldValue != $value) {
                $company->{$column} = $value;
                $company->save();
                if (in_array($column, ['type', 'status', 'owner_id'])) {
                    do_action('fluent_crm/company_' . $column . '_to_' . $value, $company, $oldValue);
                }
            }
        }

        return $this->sendSuccess([
            'message' => __('Company successfully updated', 'fluent-crm')
        ]);
    }

    public function delete(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        do_action('fluent_crm/before_company_delete', $company);
        $company->delete();
        do_action('fluent_crm/company_deleted', $id);

        return [
            'message' => __('Company has been deleted successfully', 'fluent-crm')
        ];
    }

    public function handleBulkActions(Request $request)
    {
        $actionName = sanitize_text_field($request->get('action_name', ''));

        $companyIds = array_map('intval', $request->get('company_ids', []));
        $companyIds = array_filter($companyIds);
        $lastId = $request->get('last_id', 0);

        if (!$companyIds) {
        

            $companyQuery = Company::orderBy('id', 'ASC')
                ->searchBy($request->getSafe('search', 'sanitize_text_field'));

            $inlineFilters = $request->get('company_query.inline_filters', []);

            if ($inlineFilters && is_array($inlineFilters)) {
                $inlineFilters = array_filter($inlineFilters);

                foreach ($inlineFilters as $key => $values) {
                    if (!is_array($values)) {
                        continue;
                    }
                    $values = array_map('sanitize_text_field', $values);

                    if ($key == 'company_categories') {
                        $companyQuery->whereIn('industry', $values);
                    } else if ($key == 'company_types') {
                        $companyQuery->whereIn('type', $values);
                    }
                }
            }
            $companyQuery = $companyQuery->limit(50)
                ->where('id', '>', $lastId);
        } else {
            $companyQuery = Company::whereIn('id', $companyIds);
        }

        $companies = $companyQuery->get();
        if ($companies->isEmpty()) {
            return [
                'is_completed'       => true,
                'completed_companies' => 0,
                'message'            => __('All companies have been processed', 'fluent-crm')
            ];
        }
        $companyIds = $companyQuery->pluck('id')->toArray();
        $lastCompanyId = end($companyIds);

        if ($actionName == 'delete_companies') {
            foreach ($companies as $company) {
                $id = $company->id;
                do_action('fluent_crm/before_company_delete', $company);
                $company->delete();
                do_action('fluent_crm/company_deleted', $id);
            }

            return $this->sendSuccess([
                'last_company_id'    => $lastCompanyId,
                'completed_companies' => count($companyIds),
                'message' => __('Selected Companies have been deleted permanently', 'fluent-crm'),
            ]);
        } elseif ($actionName == 'change_company_status') {
            $newStatus = sanitize_text_field($request->get('new_status', ''));
            if (!$newStatus) {
                return $this->sendError([
                    'message' => __('Please select status', 'fluent-crm')
                ]);
            }

            foreach ($companies as $company) {
                $oldStatus = $company->status;
                if ($oldStatus != $newStatus) {
                    $company->status = $newStatus;
                    $company->save();
                    do_action('fluent_crm/company_status_to_' . $newStatus, $company, $oldStatus);
                }
            }

            return [
                'last_company_id'    => $lastCompanyId,
                'completed_companies' => count($companyIds),
                'message' => __('Status has been changed for the selected companies', 'fluent-crm')
            ];
        } else if ($actionName == 'change_company_type') {
            $newType = sanitize_text_field($request->get('new_status', ''));
            if (!$newType) {
                return $this->sendError([
                    'message' => __('Please select new type', 'fluent-crm')
                ]);
            }
            foreach ($companies as $company) {
                $oldType = $company->type;
                if ($oldType != $newType) {
                    $company->type = $newType;
                    $company->save();
                    do_action('fluent_crm/company_type_to_' . $newType, $company, $oldType);
                }
            }

            return [
                'last_company_id'    => $lastCompanyId,
                'completed_companies' => count($companyIds),
                'message' => __('Company Type has been updated for the selected companies', 'fluent-crm')
            ];
        } else if ($actionName == 'change_company_category') {
            $newCategory = sanitize_text_field($request->get('new_status', ''));
            if (!$newCategory) {
                return $this->sendError([
                    'message' => __('Please select new category', 'fluent-crm')
                ]);
            }
            foreach ($companies as $company) {
                $oldCategory = $company->industry;
                if ($oldCategory != $newCategory) {
                    $company->industry = $newCategory;
                    $company->save();
                    do_action('fluent_crm/company_category_to_' . $newCategory, $company, $oldCategory);
                }
            }

            return [
                'last_company_id'    => $lastCompanyId,
                'completed_companies' => count($companyIds),
                'message' => __('Company Category has been updated for the selected companies', 'fluent-crm')
            ];
        }

        return [
            'last_company_id'    => $lastCompanyId,
            'completed_companies' => count($companyIds),
            'message' => __('Selected bulk action has been successfully completed', 'fluent-crm')
        ];
    }

    private function getSanitizedData($allData)
    {
        $rules = [
            'name' => 'required'
        ];

        if (Arr::get($allData, 'website')) {
            $allData['website'] = $this->makeHttpUrl($allData['website']);
            $rules['website'] = 'url';
        }

        if (Arr::get($allData, 'linkedin_url')) {
            $allData['linkedin_url'] = $this->makeHttpUrl($allData['linkedin_url']);
            $rules['linkedin_url'] = 'url';
        }

        if (Arr::get($allData, 'facebook_url')) {
            $allData['facebook_url'] = $this->makeHttpUrl($allData['facebook_url']);
            $rules['facebook_url'] = 'url';
        }

        if (Arr::get($allData, 'twitter_url')) {
            $allData['twitter_url'] = $this->makeHttpUrl($allData['twitter_url']);
            $rules['twitter_url'] = 'url';
        }

        $allData = $this->validate($allData, $rules);

        $data = Sanitize::company($allData);

        return Arr::only($data, array_keys($allData));
    }

    private function makeHttpUrl($url)
    {
        if (!$url) {
            return $url;
        }
        $parsed_url = wp_parse_url($url);
        if (!$parsed_url || empty($parsed_url['scheme'])) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * Returns true only if the URL resolves to a public, routable IP address.
     * Blocks private/reserved ranges to prevent SSRF attacks.
     */
    private function isSSRFSafeUrl($url)
    {
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return false;
        }

        $host = $parsed['host'];
        // Strip IPv6 brackets if present
        $host = trim($host, '[]');

        // If it looks like a raw IP, validate directly; otherwise resolve the hostname
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
            // gethostbyname() returns the original string on failure
            if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
                return false;
            }
        }

        // Reject private, loopback, link-local, and other reserved ranges
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function getLogoWebsiteUrl($url)
    {
        if (!$url) {
            return NULL;
        }

        $url = $this->makeHttpUrl($url);

        if (!$this->isSSRFSafeUrl($url)) {
            return NULL;
        }

        $response = wp_remote_get($url, [
            'sslverify'  => false, // Disable SSL verification to avoid 403 Forbidden error
            'timeout'    => 10, // Set a timeout of 10 seconds
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3' // Set a User-Agent header to avoid 403 Forbidden error
        ]);

        // Check for errors in the response
        if (is_wp_error($response)) {
            return NULL;
        }

        // Extract the HTML content from the response
        $html = wp_remote_retrieve_body($response);

        preg_match('/<link rel="apple-touch-icon"(?:.*?)href="([^"]+)"/i', $html, $matches);
        // Use regular expressions to find the logo image URL
        if (!isset($matches[1])) {
            preg_match('/<link rel="(?:shortcut|icon)"(?:.*?)href="([^"]+)"/i', $html, $matches);
        }

        // If a logo URL is found, download the image to the uploads directory
        if (isset($matches[1])) {
            $logoUrl = $matches[1];

            // Resolve relative URLs against the base domain
            if (!preg_match('/^https?:\/\//i', $logoUrl)) {
                $parsedBase = wp_parse_url($url);
                $baseOrigin = ($parsedBase['scheme'] ?? 'https') . '://' . ($parsedBase['host'] ?? '');
                $logoUrl = $baseOrigin . '/' . ltrim($logoUrl, '/');
            }

            $extension = strtolower(substr($logoUrl, strrpos($logoUrl, '.') + 1));
            if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'ico'])) {
                return NULL;
            }

            // Block SSRF on the logo URL too (the link tag href may point to a different host)
            if (!$this->isSSRFSafeUrl($logoUrl)) {
                return NULL;
            }

            $uploadDir = wp_upload_dir(); // Get the uploads directory

            $filename = md5($url . time()) . '-' . basename($logoUrl); // Get the filename from the URL
            $filepath = $uploadDir['basedir'] . '/fluentcrm/' . $filename; // Combine the uploads directory path with the filename

            // Download the image using wp_remote_get() and save it to the uploads directory
            $image = wp_remote_get($logoUrl, [
                'timeout'   => 10, // Set a timeout of 10 seconds
                'sslverify' => false // Disable SSL verification to avoid 403 Forbidden error
            ]);

            if (!is_wp_error($image)) {
                // Check if the downloaded file is actually an image
                $headers = wp_remote_retrieve_headers($image);
                $imageBody = wp_remote_retrieve_body($image);
                if (defined('FILEINFO_MIME_TYPE') && class_exists('\finfo')) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $content_type = $finfo->buffer($imageBody);
                } else {
                    $content_type = wp_remote_retrieve_header($headers, 'content-type');
                    if (!$content_type) {
                        $content_type = Arr::get($headers, 'content-type');
                    }

                    if (strpos($content_type, 'image/') !== 0) {
                        return null;
                    }

                    // Temporary file to validate the image
                    $tmpFilePath = tempnam(sys_get_temp_dir(), 'tmpimg');
                    file_put_contents($tmpFilePath, $imageBody);
                    $imgSize = getimagesize($tmpFilePath);
                    wp_delete_file($tmpFilePath);
                    if (!$imgSize) {
                        return null;
                    }
                }

                if (strpos($content_type, 'image/') === 0) {
                    global $wp_filesystem;
                    if (!$wp_filesystem) {
                        require_once(ABSPATH . '/wp-admin/includes/file.php');
                        WP_Filesystem();
                    }

                    FileSystem::setCustomUploadDir([
                        'baseurl' => $uploadDir['baseurl'],
                        'basedir' => $uploadDir['basedir'],
                    ]);

                    $wp_filesystem->put_contents($filepath, $imageBody);
                    // Return the URL of the saved image
                    return $uploadDir['baseurl'] . FLUENTCRM_UPLOAD_DIR . '/' . $filename;
                } else {
                    // If the downloaded file is not an image, delete the file and return null
                    wp_delete_file($filepath);
                }
            }
        }

        // If no logo URL is found, or if an error occurs, or if the downloaded file is not an image, return null
        return NULL;
    }

    public function getNotes()
    {
        $companyId = $this->request->get('id');
        $search = $this->request->get('search');
        $includeId = intval($this->request->get('include_id', 0));

        $notes = CompanyNote::where('subscriber_id', $companyId);

        if (!empty($search)) {
            global $wpdb;
            $notes = $notes->where('title', 'LIKE', '%' . $wpdb->esc_like(sanitize_text_field($search)) . '%');
        }

        $notes = $notes->orderBy('id', 'DESC')
            ->paginate();

        foreach ($notes as $note) {
            $note->added_by = $note->createdBy();
        }
        $fields['fields'] = Helper::getNoteSyncFields();

        $response = [
            'notes'  => $notes,
            'fields' => $fields
        ];

        if ($includeId) {
            $noteIds = (new Collection($notes->items()))->pluck('id')->toArray();
            if (!in_array($includeId, $noteIds)) {
                $includedNote = CompanyNote::where('id', $includeId)
                    ->where('subscriber_id', $companyId)
                    ->first();
                if ($includedNote) {
                    $includedNote->added_by = $includedNote->createdBy();
                    $response['included_note'] = $includedNote;
                }
            }
        }

        return $this->sendSuccess($response);
    }

    public function addNote(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        $note = $this->validate($request->get('note'), [
            'title'       => 'required',
            'description' => 'required',
            'type'        => 'required',
            'created_at'  => 'nullable|date'
        ]);

        if (empty($note['created_at'])) {
            $note['created_at'] = current_time('mysql');
        }

        $note['subscriber_id'] = $id;

        $note = Sanitize::contactNote($note);

        $subscriberNote = CompanyNote::create(wp_unslash($note));

        /**
         * Subscriber's Note Added
         *
         * @param SubscriberNote $subscriberNote Note Model.
         * @param Subscriber $subscriber Contact Model.
         * @param array $note Contact Note Data Array.
         * @since 1.0
         */
        do_action('fluent_crm/company_note_added', $subscriberNote, $company, $note);

        return $this->sendSuccess([
            'note'    => $subscriberNote,
            'message' => __('Note has been successfully added', 'fluent-crm')
        ]);
    }

    public function updateNote(Request $request, $id, $noteId)
    {
        $company = Company::findOrFail($id);

        $note = $this->validate($request->get('note'), [
            'title'       => 'required',
            'description' => 'required',
            'type'        => 'required',
            'created_at'  => 'sometimes|date'
        ]);

        $note = Arr::only(wp_unslash($note), ['title', 'description', 'type', 'created_at']);

        if (empty($note['created_at'])) {
            unset($note['created_at']);
        }

        $note = Sanitize::contactNote($note);

        $companyNote = CompanyNote::findOrFail($noteId);
        $companyNote->fill($note);
        $companyNote->save();

        /**
         * Subscriber's Note Updated
         *
         * @param CompanyNote $companyNote Note Model.
         * @param Company $company Contact Model.
         * @param array $note Contact Note Data Array.
         * @since 1.0
         */
        do_action('fluent_crm/company_note_updated', $companyNote, $company, $note);

        return $this->sendSuccess([
            'note'    => $companyNote,
            'message' => __('Note successfully updated', 'fluent-crm')
        ]);
    }

    public function deleteNote($id, $noteId)
    {
        $company = Company::findOrFail($id);
        CompanyNote::where('id', $noteId)->delete();

        /**
         * Subscriber's Note Delete
         *
         * @param int $noteId Note ID.
         * @param Company $company Company Model.
         * @since 1.0
         */
        do_action('fluent_crm/company_note_deleted', $noteId, $company);

        return $this->sendSuccess([
            'message' => __('Note successfully deleted', 'fluent-crm')
        ]);
    }

    public function bulkDeleteNotes(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        $noteIds = array_filter(array_map('intval', (array) $request->get('note_ids', [])));

        if (empty($noteIds)) {
            return $this->sendError([
                'message' => __('No note IDs provided', 'fluent-crm')
            ]);
        }

        if (count($noteIds) > 200) {
            return $this->sendError([
                'message' => __('Too many notes selected. Please delete 200 or fewer notes at a time.', 'fluent-crm')
            ]);
        }

        // Scope delete to this company so users cannot delete notes belonging to other companies.
        $deletableNoteIds = CompanyNote::where('subscriber_id', $company->id)
            ->whereIn('id', $noteIds)
            ->pluck('id')
            ->toArray();

        $deletedCount = 0;
        if ($deletableNoteIds) {
            $deletedCount = CompanyNote::whereIn('id', $deletableNoteIds)->delete();

            foreach ($deletableNoteIds as $deletedNoteId) {
                do_action('fluent_crm/company_note_deleted', $deletedNoteId, $company);
            }
        }

        return $this->sendSuccess([
            'message' => sprintf(
                /* translators: %d: number of deleted notes */
                _n('%d note deleted', '%d notes deleted', $deletedCount, 'fluent-crm'),
                $deletedCount
            )
        ]);
    }

    public function getCustomGlobalFields(CustomCompanyField $model)
    {
        return $this->sendSuccess(
            $model->getGlobalFields(
                $this->request->get('with', [])
            )
        );
    }

    public function saveCustomGlobalFields(CustomCompanyField $model)
    {
        $fields = $model->saveGlobalFields(
            Helper::parseArrayOrJson($this->request->get('fields'))
        );

        return $this->sendSuccess([
            'fields'  => $fields,
            'message' => __('Fields saved successfully!', 'fluent-crm')
        ]);
    }

    public function updateCustomFieldGroupName(CustomCompanyField $model)
    {
        $oldName = sanitize_text_field($this->request->get('old_name'));
        $newName = sanitize_text_field($this->request->get('new_name'));
        $updatedCustomFields = $model->updateGroupName($oldName, $newName);

        return $this->sendSuccess([
            'fields'  => $updatedCustomFields,
            'message' => __('Group name updated successfully!', 'fluent-crm')
        ]);
    }

    public function getCompanyExternalView(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        $sectionId = $request->get('section_provider');

        return apply_filters('fluent_crm/company_profile_section_' . $sectionId, [
            'heading'      => '',
            'content_html' => ''
        ], $company);
    }

    public function saveExternalViewData(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        $sectionId = $request->get('section_provider');

        $response = apply_filters('fluent_crm/company_profile_section_save_' . $sectionId, '', $request->get('data', []), $company);

        if (!$response) {
            return $this->sendError([
                'message' => __('Handler could not be found.', 'fluent-crm')
            ]);
        }

        return $response;
    }
}
