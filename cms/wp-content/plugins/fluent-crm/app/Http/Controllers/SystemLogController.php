<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\SystemLog;
use FluentCrm\Framework\Http\Request\Request;

/**
 *  SystemLog Controller - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 2.8.40
 */
class SystemLogController extends Controller
{
    /**
     * Get all the System Logs
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return array || \WP_REST_Response
     */
    public function index(Request $request)
    {
        $search = $this->getSearchTerm($request);

        $logs = $this->getLogsQuery($search);

        $logs = $logs->paginate($request->per_page ?: 20);

        return [
            'logs' => $logs
        ];
    }

    /**
     * Stream system logs as CSV without loading all rows into memory.
     *
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return void
     */
    public function export(Request $request)
    {
        $range = $this->getExportRange($request);
        $startDate = $this->getExportStartDate($range);
        $search = $this->getSearchTerm($request);
        $chunkSize = $this->getExportChunkSize();
        $lastId = PHP_INT_MAX;

        $this->prepareCsvDownload($this->getExportFilename($range));

        $output = fopen('php://output', 'w');

        if (!$output) {
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $this->getCsvHeaders(), ',', '"', '\\');

        do {
            $logs = $this->getLogsQuery($search, $startDate)
                ->where('id', '<', $lastId)
                ->select(['id', 'created_at', 'title', 'description'])
                ->limit($chunkSize)
                ->get();

            $count = count($logs);

            foreach ($logs as $log) {
                $lastId = (int) $log->id;
                fputcsv($output, $this->formatCsvLogRow($log), ',', '"', '\\');
            }

            fflush($output);

            if (function_exists('flush')) {
                flush();
            }

            if (connection_aborted()) {
                break;
            }
        } while ($count === $chunkSize);

        fclose($output);
        exit;
    }

    public function deleteAll(Request $request)
    {
        SystemLog::where('id', '>', 0)->delete();

        return [
            'message' => __('All logs have been deleted', 'fluent-crm')
        ];
    }

    /**
     * @param string      $search
     * @param string|null $startDate
     * @return mixed
     */
    private function getLogsQuery($search = '', $startDate = null)
    {
        global $wpdb;

        $logs = SystemLog::orderBy('id', 'DESC');

        if ($startDate) {
            $logs = $logs->where('created_at', '>=', $startDate);
        }

        if ($search !== '') {
            $searchLike = '%' . $wpdb->esc_like($search) . '%';
            $logs = $logs->where(function ($query) use ($searchLike) {
                $query->where('title', 'LIKE', $searchLike)
                    ->orWhere('description', 'LIKE', $searchLike);
            });
        }

        return $logs;
    }

    /**
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return string
     */
    private function getSearchTerm(Request $request)
    {
        $search = $request->get('search', '');

        if (!is_scalar($search)) {
            return '';
        }

        return trim(sanitize_text_field($search));
    }

    /**
     * @param \FluentCrm\Framework\Http\Request\Request $request
     * @return int|string
     */
    private function getExportRange(Request $request)
    {
        $range = $request->get('range', 'all');

        if (!is_scalar($range)) {
            return 'all';
        }

        return $this->normalizeExportRange(sanitize_text_field($range));
    }

    /**
     * @param mixed $range
     * @return int|string
     */
    private function normalizeExportRange($range)
    {
        $range = is_scalar($range) ? (string) $range : 'all';

        if ($range === 'all') {
            return 'all';
        }

        $range = intval($range);
        $allowedRanges = [7, 15, 30];

        return in_array($range, $allowedRanges, true) ? $range : 'all';
    }

    /**
     * @param int|string $range
     * @param int|null   $currentTimestamp
     * @return string|null
     */
    private function getExportStartDate($range, $currentTimestamp = null)
    {
        $range = $this->normalizeExportRange($range);

        if ($range === 'all') {
            return null;
        }

        if (!$currentTimestamp) {
            $currentTimestamp = current_time('timestamp');
        }

        return gmdate('Y-m-d H:i:s', $currentTimestamp - ($range * 86400));
    }

    /**
     * @return array
     */
    private function getCsvHeaders()
    {
        return ['ID', 'Date & Time', 'Title', 'Description'];
    }

    /**
     * @param object $log
     * @return array
     */
    private function formatCsvLogRow($log)
    {
        return [
            (int) $log->id,
            $this->sanitizeCsvCell($log->created_at),
            $this->sanitizeCsvCell($log->title),
            $this->sanitizeCsvCell($this->plainText($log->description))
        ];
    }

    /**
     * @param int|string $range
     * @return string
     */
    private function getExportFilename($range)
    {
        $range = $this->normalizeExportRange($range);
        $rangePart = ($range === 'all') ? 'all' : 'last-' . $range . '-days';

        return 'fluent-crm-system-logs-' . $rangePart . '-' . gmdate('Y-m-d-His') . '.csv';
    }

    /**
     * Prevent spreadsheet formula execution while preserving visible values.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeCsvCell($value)
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } else {
            $value = is_scalar($value) ? (string) $value : wp_json_encode($value);
        }

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            $value = "'" . $value;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function plainText($value)
    {
        if ($value === null) {
            return '';
        }

        $value = is_scalar($value) ? (string) $value : wp_json_encode($value);

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * @return int
     */
    private function getExportChunkSize()
    {
        $chunkSize = (int) apply_filters('fluent_crm/system_logs_export_chunk_size', 1000);

        if ($chunkSize < 100) {
            return 100;
        }

        if ($chunkSize > 5000) {
            return 5000;
        }

        return $chunkSize;
    }

    /**
     * @param string $filename
     * @return void
     */
    private function prepareCsvDownload($filename)
    {
        if (function_exists('set_time_limit')) {
            // Shared hosts may still enforce web server timeouts; this only removes PHP's timer.
            @set_time_limit(0);
        }

        while (ob_get_level()) {
            if (!@ob_end_clean()) {
                break;
            }
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
    }
}
