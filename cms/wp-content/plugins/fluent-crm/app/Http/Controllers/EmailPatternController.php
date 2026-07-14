<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\Meta;
use FluentCrm\Framework\Support\Arr;
use FluentCrm\Framework\Http\Request\Request;

class EmailPatternController extends Controller
{
    private $objectType = 'email_pattern';
    private $categoryObjectType = 'email_pattern_category';

    public function index(Request $request)
    {
        $query = Meta::where('object_type', $this->objectType)
            ->orderBy('id', 'desc');

        if ($search = $request->getSafe('search', 'sanitize_text_field')) {
            $query->where('value', 'LIKE', '%' . $search . '%');
        }

        $patterns = $query->paginate();

        $formattedPatterns = [];
        foreach ($patterns as $pattern) {
            $formattedPatterns[] = $this->formatPattern($pattern);
        }

        return $this->sendSuccess([
            'patterns' => [
                'data'  => $formattedPatterns,
                'total' => $patterns->total()
            ]
        ]);
    }

    public function show(Request $request, $id)
    {
        $pattern = Meta::where('object_type', $this->objectType)
            ->where('id', $id)
            ->firstOrFail();

        return $this->sendSuccess([
            'pattern' => $this->formatPattern($pattern)
        ]);
    }

    /**
     * Return patterns in wp_block REST format for the editor middleware.
     */
    public function indexWpFormat(Request $request)
    {
        $patterns = Meta::where('object_type', $this->objectType)
            ->orderBy('id', 'desc')
            ->get();

        $categoryMap = $this->getCategoryMap();
        $formatted = [];
        foreach ($patterns as $pattern) {
            $formatted[] = $this->formatAsWpBlock($pattern, $categoryMap);
        }

        return $formatted;
    }

    public function store(Request $request)
    {
        $this->validate($request->all(), [
            'title'   => 'required|string',
            'content' => 'required|string',
        ]);

        $title = sanitize_text_field($request->get('title'));
        $content = wp_kses_post($request->get('content'));
        $category = sanitize_text_field($request->get('category', ''));
        $description = sanitize_text_field($request->get('description', ''));
        $syncStatus = sanitize_text_field($request->get('sync_status', 'unsynced'));

        $slug = 'fluentcrm/' . sanitize_title($title . '-' . uniqid());

        $pattern = Meta::create([
            'object_type' => $this->objectType,
            'object_id'   => get_current_user_id(),
            'key'         => $slug,
            'value'       => [
                'title'       => $title,
                'content'     => $content,
                'category'    => $category,
                'description' => $description,
                'sync_status' => $syncStatus,
            ],
        ]);

        return $this->sendSuccess([
            'message' => __('Pattern saved successfully', 'fluent-crm'),
            'pattern' => $this->formatPattern($pattern),
        ]);
    }

    /**
     * Store a pattern from wp_block format (called by editor middleware).
     */
    public function storeWpFormat(Request $request)
    {
        $title = $request->get('title', '');
        if (is_array($title)) {
            $title = Arr::get($title, 'raw', '');
        }
        $title = sanitize_text_field($title);

        $content = $request->get('content', '');
        if (is_array($content)) {
            $content = Arr::get($content, 'raw', '');
        }
        $content = wp_kses_post($content);

        if (!$title && !$content) {
            return $this->sendError([
                'message' => __('Title or content is required', 'fluent-crm')
            ]);
        }

        if (!$title) {
            $title = __('Untitled Pattern', 'fluent-crm');
        }

        $meta = $request->get('meta', []);
        $syncStatus = Arr::get($meta, 'wp_pattern_sync_status', '');
        $syncStatus = sanitize_text_field($syncStatus);

        $categoryIds = (array) $request->get('wp_pattern_category', []);
        $categoryName = $this->resolveCategoryName($categoryIds);

        $slug = 'fluentcrm/' . sanitize_title($title . '-' . uniqid());

        $pattern = Meta::create([
            'object_type' => $this->objectType,
            'object_id'   => get_current_user_id(),
            'key'         => $slug,
            'value'       => [
                'title'       => $title,
                'content'     => $content,
                'category'    => $categoryName,
                'description' => '',
                'sync_status' => $syncStatus,
            ],
        ]);

        $categoryMap = $this->getCategoryMap();
        return $this->formatAsWpBlock($pattern, $categoryMap);
    }

    public function update(Request $request, $id)
    {
        $pattern = Meta::where('object_type', $this->objectType)
            ->where('id', $id)
            ->firstOrFail();

        $value = $pattern->value;

        if ($title = $request->get('title')) {
            if (is_array($title)) {
                $title = Arr::get($title, 'raw', '');
            }
            $value['title'] = sanitize_text_field($title);
        }

        if ($request->has('content')) {
            $content = $request->get('content');
            if (is_array($content)) {
                $content = Arr::get($content, 'raw', '');
            }
            $value['content'] = wp_kses_post($content);
        }

        $value['category'] = sanitize_text_field($request->get('category', ''));

        if ($request->has('wp_pattern_category')) {
            $categoryIds = (array) $request->get('wp_pattern_category', []);
            $value['category'] = $this->resolveCategoryName($categoryIds);
        }

        if ($request->has('description')) {
            $value['description'] = sanitize_text_field($request->get('description'));
        }

        if ($request->exists('sync_status')) {
            $value['sync_status'] = sanitize_text_field($request->get('sync_status'));
        }

        $meta = $request->get('meta', []);
        if (is_array($meta) && isset($meta['wp_pattern_sync_status'])) {
            $value['sync_status'] = sanitize_text_field($meta['wp_pattern_sync_status']);
        }

        if ($title = $request->get('title')) {
            if (is_array($title)) {
                $title = Arr::get($title, 'raw', '');
            }
            if ($title) {
                $pattern->key = 'fluentcrm/' . sanitize_title($title . '-' . $pattern->id);
            }
        }

        $pattern->value = $value;
        $pattern->save();

        return $this->sendSuccess([
            'message' => __('Pattern updated successfully', 'fluent-crm'),
            'pattern' => $this->formatPattern($pattern),
        ]);
    }

    public function delete(Request $request, $id)
    {
        Meta::where('object_type', $this->objectType)
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return $this->sendSuccess([
            'message' => __('Pattern deleted successfully', 'fluent-crm'),
        ]);
    }

    public function handleBulkAction(Request $request)
    {
        $actionName = sanitize_text_field($request->get('action_name'));

        if ($actionName !== 'delete_patterns') {
            return $this->sendError([
                'message' => __('Invalid action', 'fluent-crm')
            ]);
        }

        $query = Meta::where('object_type', $this->objectType);

        if (filter_var($request->get('select_all'), FILTER_VALIDATE_BOOLEAN)) {
            $search = $request->getSafe('search', 'sanitize_text_field', '');
            if ($search !== '') {
                $query->where('value', 'LIKE', '%' . $search . '%');
            }
        } else {
            $patternIds = array_map('intval', (array) $request->get('pattern_ids', []));
            if (empty($patternIds)) {
                return $this->sendError([
                    'message' => __('No patterns selected', 'fluent-crm')
                ]);
            }
            $query->whereIn('id', $patternIds);
        }

        $count = $query->delete();

        return $this->sendSuccess([
            'message' => sprintf(__('%d pattern(s) deleted successfully', 'fluent-crm'), $count)
        ]);
    }

    /**
     * CRUD for pattern categories (stored as fc_meta with separate object_type).
     */
    public function getCategories()
    {
        // Collect unique category names from all patterns
        $patterns = Meta::where('object_type', $this->objectType)->get();
        $categories = [];
        foreach ($patterns as $pattern) {
            $cat = Arr::get($pattern->value, 'category', '');
            if ($cat && !in_array($cat, $categories)) {
                $categories[] = $cat;
            }
        }

        sort($categories);

        return $this->sendSuccess([
            'categories' => $categories
        ]);
    }

    public function storeCategory(Request $request)
    {
        $name = sanitize_text_field($request->get('name', ''));
        if (!$name) {
            return $this->sendError(['message' => __('Category name is required', 'fluent-crm')]);
        }

        $slug = sanitize_title($name);

        // Check for existing
        $existing = Meta::where('object_type', $this->categoryObjectType)
            ->where('key', $slug)
            ->first();

        if ($existing) {
            return $this->formatCategoryAsWpTerm($existing);
        }

        $category = Meta::create([
            'object_type' => $this->categoryObjectType,
            'object_id'   => 0,
            'key'         => $slug,
            'value'       => ['name' => $name],
        ]);

        return $this->formatCategoryAsWpTerm($category);
    }

    public function deleteCategory(Request $request, $id)
    {
        Meta::where('object_type', $this->categoryObjectType)
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        return $this->sendSuccess([
            'message' => __('Category deleted successfully', 'fluent-crm'),
        ]);
    }

    /**
     * Format a pattern Meta record as a wp_block REST response.
     */
    private function formatAsWpBlock($meta, $categoryMap = [])
    {
        $value = $meta->value;
        $title = Arr::get($value, 'title', '');
        $content = Arr::get($value, 'content', '');
        $syncStatus = Arr::get($value, 'sync_status', 'unsynced');
        $category = Arr::get($value, 'category', '');

        $categoryIds = [];
        if ($category) {
            $catSlug = sanitize_title($category);
            if (isset($categoryMap[$catSlug])) {
                $categoryIds[] = (int) $categoryMap[$catSlug];
            }
        }

        return [
            'id'                     => (int) $meta->id,
            'date'                   => $meta->created_at ? $meta->created_at : gmdate('Y-m-d\TH:i:s'),
            'date_gmt'               => $meta->created_at ? $meta->created_at : gmdate('Y-m-d\TH:i:s'),
            'modified'               => $meta->updated_at ? $meta->updated_at : gmdate('Y-m-d\TH:i:s'),
            'modified_gmt'           => $meta->updated_at ? $meta->updated_at : gmdate('Y-m-d\TH:i:s'),
            'slug'                   => $meta->key,
            'status'                 => 'publish',
            'type'                   => 'wp_block',
            'link'                   => '',
            'title'                  => ['raw' => $title],
            'content'                => ['raw' => $content, 'protected' => false],
            'meta'                   => new \stdClass(),
            'wp_pattern_sync_status' => $syncStatus ?: '',
            'wp_pattern_category'    => $categoryIds,
        ];
    }

    private function formatCategoryAsWpTerm($meta)
    {
        $value = $meta->value;

        return [
            'id'     => (int) $meta->id,
            'count'  => 0,
            'name'   => Arr::get($value, 'name', $meta->key),
            'slug'   => $meta->key,
            'parent' => 0,
        ];
    }

    private function formatPattern($meta)
    {
        $value = $meta->value;

        return [
            'id'          => (int) $meta->id,
            'slug'        => $meta->key,
            'title'       => Arr::get($value, 'title', ''),
            'content'     => Arr::get($value, 'content', ''),
            'category'    => Arr::get($value, 'category', ''),
            'description' => Arr::get($value, 'description', ''),
            'sync_status' => Arr::get($value, 'sync_status', 'unsynced'),
            'created_at'  => $meta->created_at ? (string) $meta->created_at : '',
            'updated_at'  => $meta->updated_at ? (string) $meta->updated_at : '',
        ];
    }

    /**
     * Build slug → id map for all pattern categories.
     */
    private function getCategoryMap()
    {
        $categories = Meta::where('object_type', $this->categoryObjectType)->get();
        $map = [];
        foreach ($categories as $cat) {
            $map[$cat->key] = $cat->id;
        }
        return $map;
    }

    /**
     * Resolve category IDs back to a single category name.
     */
    private function resolveCategoryName($categoryIds)
    {
        if (empty($categoryIds)) {
            return '';
        }

        $categoryIds = array_map('intval', $categoryIds);
        $category = Meta::where('object_type', $this->categoryObjectType)
            ->whereIn('id', $categoryIds)
            ->first();

        if ($category) {
            return Arr::get($category->value, 'name', $category->key);
        }

        return '';
    }

    /**
     * Get patterns formatted for the block editor boot data.
     */
    public static function getEditorPatterns()
    {
        $patterns = Meta::where('object_type', 'email_pattern')
            ->orderBy('id', 'desc')
            ->get();

        $editorPatterns = [];
        $categories = [];
        $seenCategories = [];

        foreach ($patterns as $pattern) {
            $value = $pattern->value;
            $title = Arr::get($value, 'title', '');
            $content = Arr::get($value, 'content', '');
            $category = Arr::get($value, 'category', '');
            $description = Arr::get($value, 'description', '');

            if (!$content) {
                continue;
            }

            $patternCategories = [];
            if ($category) {
                $catSlug = sanitize_title($category);
                $patternCategories[] = $catSlug;
                if (!isset($seenCategories[$catSlug])) {
                    $seenCategories[$catSlug] = true;
                    $categories[] = [
                        'name'  => $catSlug,
                        'label' => $category,
                    ];
                }
            }

            // Always include in the general fluentcrm-patterns category
            $patternCategories[] = 'fluentcrm-patterns';

            $editorPatterns[] = [
                'name'        => $pattern->key,
                'title'       => $title,
                'content'     => $content,
                'description' => $description,
                'categories'  => $patternCategories,
                'keywords'    => ['fluentcrm', 'email'],
            ];
        }

        // Always add the root category
        array_unshift($categories, [
            'name'  => 'fluentcrm-patterns',
            'label' => __('My Patterns', 'fluent-crm'),
        ]);

        return [
            'patterns'   => $editorPatterns,
            'categories' => $categories,
        ];
    }
}
