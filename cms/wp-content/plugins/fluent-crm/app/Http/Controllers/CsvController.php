<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\Company;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\Libs\FileSystem;
use FluentCrm\App\Services\Sanitize;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\App\Models\Subscriber;

/**
 *  CsvController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class CsvController extends Controller
{

    /**
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return \WP_REST_Response
     * @throws \FluentCrm\Framework\Validator\ValidationException
     */
    public function upload(Request $request)
    {
        if (is_multisite()) {
            add_filter('upload_mimes', function ($types) {
                if (empty($types['csv'])) {
                    $types['csv'] = 'text/csv';
                }
                return $types;
            });
        }

        $files = $this->validate($this->request->files(), [
            'file' => 'mimetypes:' . implode(',', fluentcrmCsvMimes())
        ], [
            'file.mimetypes' => __('The file must be a valid CSV.', 'fluent-crm')
        ]);

        $delimeter = $request->get('delimiter', 'comma');

        if ($delimeter == 'comma') {
            $delimeter = ',';
        } else {
            $delimeter = ';';
        }

        $uploadedFiles = FileSystem::put($files);

        try {
            $csv = $this->getCsvReader(FileSystem::get($uploadedFiles[0]['file']));
            $csv->setDelimiter($delimeter);
            $headers = $csv->fetchOne();
        } catch (\Exception $exception) {
            return $this->sendError([
                'message' => $exception->getMessage()
            ]);
        }

        if (count($headers) != count(array_unique($headers))) {
            return $this->sendError([
                'message' => __('Looks like your csv has same name header multiple times. Please fix your csv first and remove any duplicate header column', 'fluent-crm')
            ]);
        }


        if ($request->get('type') == 'company') {
            $mappables = Company::mappables();
        } else {
            $mappables = Subscriber::mappables();
        }

        $headerItems = array_values(array_filter($headers));
        $subscriberColumns = array_keys($mappables);

        $maps = [];

        $customFields = fluentcrm_get_custom_contact_fields();

        $fieldsMap = [];
        if ($customFields) {
            foreach ($customFields as $field) {
                $fieldsMap[$field['slug']] = $field['label'];
            }
        }

        foreach ($headerItems as $headerItem) {
            $tableMap = (in_array($headerItem, $subscriberColumns)) ? $headerItem : null;

            if (!$tableMap) {
                $santizedItem = str_replace(' ', '_', strtolower($headerItem));
                if (in_array($santizedItem, $subscriberColumns)) {
                    $tableMap = $santizedItem;
                }
            }

            if (!empty($fieldsMap) && in_array($headerItem, $fieldsMap)) {
                $tableMap = array_search($headerItem, $fieldsMap);
            }

            $maps[] = [
                'csv'   => $headerItem,
                'table' => $tableMap
            ];
        }

        if ($request->get('type') == 'company') {
            /**
             * Determine the columns of the company table in FluentCRM.
             *
             * This filter allows you to modify the columns of the company table in the CSV export.
             *
             * @since 2.8.0
             *
             * @param array $subscriberColumns An array of default subscriber columns.
             */
            $columns = apply_filters(
                'fluent_crm/company_table_columns', $subscriberColumns
            );
        } else {
            /**
             * Determine the columns of the subscriber table in FluentCRM.
             *
             * This filter allows you to modify the columns displayed in the subscriber table.
             *
             * @since 2.8.0
             *
             * @param array $subscriberColumns An array of default subscriber table columns.
             */
            $columns = apply_filters(
                'fluent_crm/subscriber_table_columns', $subscriberColumns
            );
        }

        return $this->send([
            'file'    => $uploadedFiles[0]['file'],
            'headers' => $headerItems,
            'fields'  => $mappables,
            'columns' => $columns,
            'map'     => $maps
        ]);
    }

    public function import()
    {
        $inputs = $this->request->only([
            'map', 'tags', 'lists', 'file', 'update', 'new_status', 'double_optin_email', 'import_silently', 'force_update_status'
        ]);

        if (Arr::get($inputs, 'import_silently') == 'yes') {
            if (!defined('FLUENTCRM_DISABLE_TAG_LIST_EVENTS')) {
                define('FLUENTCRM_DISABLE_TAG_LIST_EVENTS', true);
            }
        }

        $forceStatusChange = Arr::get($inputs, 'force_update_status') == 'yes';

        $delimeter = $this->request->get('delimiter', 'comma');

        if ($delimeter == 'comma') {
            $delimeter = ',';
        } else {
            $delimeter = ';';
        }

        $status = $inputs['new_status'];

        $page = $this->request->get('importing_page', 1);

        $processPerRequest = apply_filters('fluent_crm/csv_import_contact_limit_per_request', 100);

        $offset = ($page - 1) * $processPerRequest;

        try {
            $reader = $this->getCsvReader(FileSystem::get($inputs['file']));
            $reader->setDelimiter($delimeter);
            $aHeaders = $reader->fetchOne(0);
            $totalCount = $this->getCsvTotalRowCount($reader, $inputs['file'], $page == 1);
            $records = $this->getCsvRecordsChunk($reader, $aHeaders, $offset, $processPerRequest);
        } catch (\Exception $exception) {
            return $this->sendError([
                'message' => $exception->getMessage()
            ]);
        }


        $customFieldKeys = $this->customFieldKeys();
        $subscribers = [];
        $skipped = [];

        $isCompanyEnabled = Helper::isCompanyEnabled();

        foreach ($records as $record) {
            if (!array_filter($record)) {
                continue;
            }

            $subscriber = [
                'custom_values' => []
            ];
            foreach ($inputs['map'] as $map) {
                if (!$map['table']) {
                    continue;
                }
                if (isset($map['csv'], $map['table'])) {
                    if (in_array($map['table'], ['tags', 'lists'])) {
                        // str_getcsv respects inner quoting, so a cell like
                        // "Sales, EMEA","Newsletter" keeps quoted names intact
                        // where a raw explode(',') would split them.
                        if ($map['table'] == 'tags') {
                            $subscriber['tags'] = !empty($record[$map['csv']]) ? array_map('trim', str_getcsv($record[$map['csv']])) : [];
                        } else {
                            $subscriber['lists'] = !empty($record[$map['csv']]) ? array_map('trim', str_getcsv($record[$map['csv']])) : [];
                        }
                    }
                    else if (in_array($map['table'], $customFieldKeys)) {
                        $subscriber['custom_values'][$map['table']] = $record[$map['csv']];
                    } else {
                        $subscriber[$map['table']] = $record[$map['csv']];
                    }
                }
            }

            // Never trust a CSV-mapped user_id: a column mapped to user_id would link every
            // imported contact to an arbitrary WP user (e.g. an admin) — privilege escalation.
            unset($subscriber['user_id']);

            if (!array_key_exists('email', $subscriber)) {
                return $this->sendError(['email' => __('The email field is required.', 'fluent-crm')], 422);
            }

            $subscriber['email'] = is_string($subscriber['email']) ? trim($subscriber['email']) : $subscriber['email'];

            if ($subscriber['email'] && is_email($subscriber['email'])) {

                if (isset($subscriber['company_id']) && $subscriber['company_id'] && $isCompanyEnabled) {
                    $companyNameOrId = $subscriber['company_id'];
                    if (is_string($companyNameOrId)) {
                        $company = Company::query()->firstOrCreate([
                            'name' => $subscriber['company_id']
                        ], [
                            'name' => $subscriber['company_id']
                        ]);

                        if ($company) {
                            $subscriber['company_id'] = $company->id;
                        } else {
                            unset($subscriber['company_id']);
                        }
                    } else {
                        $company = Company::find($subscriber['company_id']);
                        if (!$company) {
                            unset($subscriber['company_id']);
                        }
                    }
                }

                $subscribers[] = Sanitize::contact($subscriber);
            } else {
                $skipped[] = $subscriber;
            }
        }

        if (!isset($inputs['tags'])) {
            $inputs['tags'] = [];
        }

        if (!isset($inputs['lists'])) {
            $inputs['lists'] = [];
        }

        $sendDoubleOptin = Arr::get($inputs, 'double_optin_email') == 'yes';

        $result = Subscriber::import(
            $subscribers, $inputs['tags'], $inputs['lists'], $inputs['update'], $status, $sendDoubleOptin, $forceStatusChange, 'csv'
        );

        $totalSkipped = count($result['skips']) + count($skipped);

        $completed = $offset + count($records);
        $hasMore = $completed < $totalCount;
        if (!$hasMore) {
            FileSystem::delete($inputs['file']);
            $this->clearCsvTotalRowCount($inputs['file']);
        }

        return $this->sendSuccess([
            'total'                => $totalCount,
            'completed'            => $completed,
            'total_page'           => ceil($totalCount / $processPerRequest),
            'skipped'              => $totalSkipped,
            'invalid_contacts'     => $skipped,
            'skipped_contacts'     => $result['skips'],
            'invalid_email_counts' => count($skipped),
            'inserted'             => count($result['inserted']),
            'updated'              => count($result['updated']),
            'has_more'             => $hasMore,
            'last_page'            => $page,
            'tags'                 => $inputs['tags'],
            'lists'                => $inputs['lists'],
            'offset'               => $offset,
            'result'               => $result
        ]);
    }

    public function importCompanies()
    {
        $inputs = $this->request->only([
            'map', 'file', 'update', 'create_owner'
        ]);

        $delimeter = $this->request->get('delimiter', 'comma');

        if ($delimeter == 'comma') {
            $delimeter = ',';
        } else {
            $delimeter = ';';
        }

        $page = $this->request->get('importing_page', 1);
        $processPerRequest = 100;
        $offset = ($page - 1) * $processPerRequest;

        try {
            $reader = $this->getCsvReader(FileSystem::get($inputs['file']));
            $reader->setDelimiter($delimeter);
            $aHeaders = $reader->fetchOne(0);
            $totalCount = $this->getCsvTotalRowCount($reader, $inputs['file'], $page == 1);
            $records = $this->getCsvRecordsChunk($reader, $aHeaders, $offset, $processPerRequest);
        } catch (\Exception $exception) {
            return $this->sendError([
                'message' => $exception->getMessage()
            ]);
        }

        $willCreateOwner = $this->request->get('create_owner') == 'yes';
        $willUpdate = $this->request->get('update') == 'yes';

        $customFields = fluentcrm_get_custom_company_fields();

        $companies = [];
        $skipped = [];
        foreach ($records as $record) {
            if (!array_filter($record)) {
                continue;
            }

            $company = [];
            foreach ($inputs['map'] as $map) {
                if (!$map['table']) {
                    continue;
                }
                if (isset($map['csv'], $map['table'])) {
                    $company[$map['table']] = trim($record[$map['csv']]);
                }
            }

            if (empty($company['name'])) {
                return $this->sendError(['email' => __('The company name field is required.', 'fluent-crm')], 422);
            }

            if (!$willUpdate) {
                // check if exists
                if (Company::where('name', $company['name'])->first()) {
                    $skipped[] = $company;
                    continue;
                }
            }


            if ($customFields) {
                $customValues = [];

                foreach ($company as $dataKey => $dataValue) {
                    if (strpos($dataKey, '_custom_') === 0) {
                        $customKey = str_replace('_custom_', '', $dataKey);
                        $customValues[$customKey] = $dataValue;
                        unset($company[$dataKey]);
                    }
                }

                $company['custom_values'] = $customValues;
            }

            $company = Sanitize::company($company);

            if (!empty($company['owner_email']) && is_email($company['owner_email'])) {
                $ownerEmail = sanitize_email($company['owner_email']);
            } else {
                $ownerEmail = null;
            }

            if ($ownerEmail) {
                $owner = FluentCrmApi('contacts')->getContact($ownerEmail);
                if ($owner) {
                    $company['owner_id'] = $owner->id;
                } else if ($willCreateOwner) {
                    $owner = FluentCrmApi('contacts')->createOrUpdate([
                        'full_name' => sanitize_text_field(Arr::get($company, 'owner_name')),
                        'email'     => $ownerEmail,
                        'status'    => 'subscribed'
                    ]);

                    if ($owner) {
                        $company['owner_id'] = $owner->id;
                    }
                }
            }

            $createdCompany = FluentCrmApi('companies')->createOrUpdate($company);
            $companies[] = $createdCompany;
        }

        // Progress must advance by the rows CONSUMED (skipped ones included), and
        // report the cumulative position — otherwise skipped rows understate the
        // totals and produce a ghost extra request at the end.
        $completed = $offset + count($records);
        $hasMore = $completed < $totalCount;
        if (!$hasMore) {
            FileSystem::delete($inputs['file']);
            $this->clearCsvTotalRowCount($inputs['file']);
        }

        return $this->sendSuccess([
            'total'      => $totalCount,
            'completed'  => $completed,
            'total_page' => ceil($totalCount / $processPerRequest),
            'skipped'    => count($skipped),
            'has_more'   => $hasMore,
            'last_page'  => $page,
            'offset'     => $offset
        ]);
    }

    protected function customFieldKeys()
    {
        $fields = fluentcrm_get_option('contact_custom_fields', []);
        $keys = [];
        foreach ($fields as $field) {
            $keys[] = $field['slug'];
        }
        return $keys;
    }

    private function getCsvReader($file)
    {
        if (!class_exists(' \League\Csv\Reader')) {
            include FLUENTCRM_PLUGIN_PATH . 'app/Services/Libs/csv/autoload.php';
        }

        return \League\Csv\Reader::createFromString($file);
    }

    /**
     * Total number of data rows (header excluded) for an import file.
     *
     * Counted by streaming the reader once — no array materialization — and cached
     * in the options table so the ~N/100 follow-up chunk requests skip the recount.
     */
    private function getCsvTotalRowCount($reader, $file, $isFirstPage)
    {
        $cacheKey = '_fc_csv_import_total_' . md5($file);

        if (!$isFirstPage) {
            $cached = (int)fluentcrm_get_option($cacheKey, 0);
            if ($cached > 0) {
                return $cached;
            }
        }

        $total = max(0, iterator_count($reader->getIterator()) - 1);
        fluentcrm_update_option($cacheKey, $total);

        return $total;
    }

    private function clearCsvTotalRowCount($file)
    {
        fluentcrm_delete_option('_fc_csv_import_total_' . md5($file));
    }

    /**
     * Fetch one chunk of CSV data rows mapped to $headers WITHOUT parsing the whole
     * file into an in-memory array (the old iterator_to_array + array_slice pattern
     * was O(N) memory and O(N²) aggregate parse work across an import's requests).
     *
     * $dataOffset is 0-based over data rows; the header line is skipped internally.
     */
    private function getCsvRecordsChunk($reader, $headers, $dataOffset, $limit)
    {
        // +1 skips the header line, which both fetch paths would otherwise
        // return as the first mapped record.
        $chunkOffset = $dataOffset + 1;

        if (method_exists($reader, 'setOffset')) {
            // Bundled league/csv 8.x — offset/limit stream via LimitIterator.
            $reader->setOffset($chunkOffset);
            $reader->setLimit($limit);
            $records = $reader->fetchAssoc($headers);
            if (!is_array($records)) {
                $records = iterator_to_array($records, false);
            }

            return array_values($records);
        }

        // league/csv 9.x (loaded by another plugin's autoloader).
        $statement = new \League\Csv\Statement();
        $statement = $statement->offset($chunkOffset)->limit($limit);

        return array_values(iterator_to_array($statement->process($reader, $headers), false));
    }
}
