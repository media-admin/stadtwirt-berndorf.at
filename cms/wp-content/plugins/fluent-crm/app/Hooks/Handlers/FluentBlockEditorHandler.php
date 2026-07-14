<?php

namespace FluentCrm\App\Hooks\Handlers;


use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\FunnelCampaign;
use FluentCrm\App\Models\Meta;
use FluentCrm\App\Models\Tag;
use FluentCrm\App\Models\Template;
use FluentCrm\App\Services\BlockRender\BlockEditorHelper;
use FluentCrm\App\Services\BlockRender\CartProductData;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\App\Services\TransStringsGuten;
use FluentCrm\Framework\Support\Arr;

class FluentBlockEditorHandler
{
    /**
     * Data passed to the iframe editor via window.fcrmEditorBoot
     * @var array
     */
    protected $editorBootData = [];

    /*
    |--------------------------------------------------------------------------
    | Public: register(), handleEditorAutosave(), handleCartProductsListing()
    |--------------------------------------------------------------------------
    */

    public function register()
    {
        add_filter('register_block_type_args', [$this, 'registerConditionalVisibilityAttributes'], 10, 2);

        add_action('init', function () {
            register_post_type('fcrm-dummy', [
                'label'        => 'Email-Body',
                'public'       => false,
                'show_in_rest' => true,
                'supports'     => ['editor', 'thumbnail']
            ]);

            if (isset($_REQUEST['fluent_crm_block_editor'])) {
                // Require authentication
                if (!is_user_logged_in()) {
                    $scheme = is_ssl() ? 'https://' : 'http://';
                    $current_url = $scheme . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
                    wp_safe_redirect(wp_login_url(esc_url_raw($current_url)));
                    exit;
                }

                // Determine required capability based on context
                $block_type = isset($_REQUEST['block_type']) ? sanitize_text_field(wp_unslash($_REQUEST['block_type'])) : '';
                $required_cap = self::getRequiredCapability($block_type);
                /**
                 * Filter the required capability for opening the FluentCRM block editor.
                 *
                 * @param string $required_cap Default capability derived from block type.
                 * @param string $block_type The requested block type.
                 * @param array $request Raw request array for advanced checks.
                 */
                $required_cap = apply_filters('fluent_crm/block_editor_required_cap', $required_cap, $block_type, $_REQUEST);

                if (!PermissionManager::currentUserCan($required_cap)) {
                    status_header(403);
                    wp_die(__('Sorry, you are not allowed to access this page.', 'fluent-crm'), 403);
                }

                // Optional nonce enforcement (off by default to preserve existing links)
                $require_nonce = (bool)apply_filters('fluent_crm/block_editor_require_nonce', false, $block_type, $_REQUEST);
                if ($require_nonce) {
                    $nonce = isset($_REQUEST['_fcrm_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['_fcrm_nonce'])) : '';
                    if (!$nonce || !wp_verify_nonce($nonce, 'fcrm_block_editor')) {
                        status_header(403);
                        wp_die(__('Invalid or missing security token.', 'fluent-crm'), 403);
                    }
                }
                if (!defined('IFRAME_REQUEST')) {
                    define('IFRAME_REQUEST', true);
                }

                remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
                add_action('fluent_crm/block_editor_head', function () {
                    $asset_file = FLUENTCRM_PLUGIN_PATH . 'assets/guten-editor/index.asset.php';
                    $asset      = file_exists($asset_file) ? require($asset_file) : [];
                    $version    = !empty($asset['version']) ? $asset['version'] : FLUENTCRM_PLUGIN_VERSION;
                    $url        = FLUENTCRM_PLUGIN_URL . 'assets/guten-editor/index.css';
                    ?>
                    <link rel="stylesheet"
                          href="<?php echo esc_url($url); ?>?version=<?php echo esc_attr($version); ?>"
                          media="screen"/>
                    <?php
                });

                add_filter('should_load_separate_core_block_assets', '__return_false', 20);
                $this->initializeEditor($_REQUEST); // phpcs:ignore WordPress.Security.NonceVerification.Recommended


                $actionHook = 'template_redirect';
                if(is_admin()) {
                    $actionHook = 'admin_init';
                }

                add_action($actionHook, function () {
                    $this->renderPage();
                    exit(200);
                }, -1000);

            }
        }, 2);

        // REST route for autosave
        add_action('rest_api_init', function () {
            register_rest_route('fluent-crm/v1', '/editor-autosave', [
                'methods'             => 'POST',
                'callback'            => [$this, 'handleEditorAutosave'],
                'permission_callback' => function () {
                    // Basic capability check – refined per payload inside handler
                    return is_user_logged_in();
                }
            ]);

            register_rest_route('fluent-crm/v2', '/editor/cart-products', [
                'methods'             => 'GET',
                'callback'            => [$this, 'handleCartProductsListing'],
                'permission_callback' => function () {
                    return is_user_logged_in() && PermissionManager::currentUserCan('fcrm_read_emails');
                }
            ]);

            // Native REST routes for editor pattern CRUD (wp_block-compatible format)
            $patternPermission = function () {
                return is_user_logged_in() && PermissionManager::currentUserCan('fcrm_manage_email_templates');
            };

            register_rest_route('fluent-crm/v2', '/editor-patterns', [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleEditorPatternsList'],
                'permission_callback' => $patternPermission
            ]);

            register_rest_route('fluent-crm/v2', '/editor-patterns', [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleEditorPatternCreate'],
                'permission_callback' => $patternPermission
            ]);

            register_rest_route('fluent-crm/v2', '/editor-patterns/(?P<id>\d+)', [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleEditorPatternGet'],
                'permission_callback' => $patternPermission
            ]);

            register_rest_route('fluent-crm/v2', '/editor-patterns/(?P<id>\d+)', [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'handleEditorPatternUpdate'],
                'permission_callback' => $patternPermission
            ]);

            register_rest_route('fluent-crm/v2', '/editor-patterns/(?P<id>\d+)', [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handleEditorPatternDelete'],
                'permission_callback' => function () {
                    return is_user_logged_in() && PermissionManager::currentUserCan('fcrm_manage_email_delete');
                }
            ]);

            register_rest_route('fluent-crm/v2', '/editor-pattern-categories', [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleEditorPatternCategories'],
                'permission_callback' => $patternPermission
            ]);

            register_rest_route('fluent-crm/v2', '/editor-pattern-categories', [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleEditorPatternCategoryCreate'],
                'permission_callback' => $patternPermission
            ]);
        });
    }

    /**
     * Register FluentCRM conditional visibility attributes in PHP.
     *
     * WordPress validates dynamic block attributes against the server-side
     * block schema during render REST requests, so this also runs when the
     * render request contains FluentCRM's conditional visibility attributes.
     *
     * @param array  $args Block registration arguments.
     * @param string $name Block name.
     * @return array
     */
    public function registerConditionalVisibilityAttributes($args, $name)
    {
        $shouldRegister = $this->shouldRegisterConditionalVisibilityAttributes();

        if (!apply_filters('fluent_crm/block_editor_register_conditional_visibility_attributes', $shouldRegister, $name, $args)) {
            return $args;
        }

        $skipBlocks = [
            'fluentcrm/conditional-group',
            'fluent-crm/conditional-content'
        ];

        if (in_array($name, $skipBlocks, true)) {
            return $args;
        }

        if (!isset($args['attributes']) || !is_array($args['attributes'])) {
            $args['attributes'] = [];
        }

        $attributes = apply_filters('fluent_crm/block_editor_conditional_visibility_attributes', [
            'fcrmConditionType' => [
                'type'    => 'string',
                'default' => ''
            ],
            'fcrmTagIds'       => [
                'type'    => 'array',
                'default' => []
            ]
        ], $name, $args);

        foreach ((array)$attributes as $attributeName => $attributeConfig) {
            if (!isset($args['attributes'][$attributeName])) {
                $args['attributes'][$attributeName] = $attributeConfig;
            }
        }

        return $args;
    }

    /**
     * Check if the current request needs FluentCRM conditional visibility
     * attributes in server-side block schemas.
     *
     * @return bool
     */
    private function shouldRegisterConditionalVisibilityAttributes()
    {
        if (isset($_REQUEST['fluent_crm_block_editor'])) {
            return true;
        }

        if (!isset($_REQUEST['attributes'])) {
            return false;
        }

        $attributes = wp_unslash($_REQUEST['attributes']);

        if (is_array($attributes)) {
            return array_key_exists('fcrmConditionType', $attributes) || array_key_exists('fcrmTagIds', $attributes);
        }

        if (is_string($attributes)) {
            return strpos($attributes, 'fcrmConditionType') !== false || strpos($attributes, 'fcrmTagIds') !== false;
        }

        return false;
    }

    /**
     * Handle autosave requests for the block editor iframe.
     * Creates or updates campaign / recurring campaign / template records.
     * If block_type is empty and id/bid is 0, nothing will be created.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleEditorAutosave(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!$params) {
            $params = $request->get_body_params();
        }

        $blockType = isset($params['block_type']) ? sanitize_text_field($params['block_type']) : '';
        $entityId = isset($params['id']) ? (int)$params['id'] : 0;
        $title = isset($params['title']) ? wp_strip_all_tags($params['title']) : '';
        $content = isset($params['content']) ? $params['content'] : '';
        $prevUpdatedAt = isset($params['prev_updated_at']) ? sanitize_text_field($params['prev_updated_at']) : '';
        $hash = isset($params['hash']) ? sanitize_text_field($params['hash']) : '';

        // Basic guard: if block type empty and no existing entity, do not create
        if (empty($blockType) && !$entityId) {
            return new \WP_REST_Response([
                'status'  => 'skipped',
                'message' => __('No block_type provided; nothing created.', 'fluent-crm')
            ], 200);
        }

        // Capability check
        $required_cap = self::getRequiredCapability($blockType);
        $required_cap = apply_filters('fluent_crm/block_editor_required_cap', $required_cap, $blockType, $params);
        if (!PermissionManager::currentUserCan($required_cap)) {
            return new \WP_Error('forbidden', __('You do not have permission to autosave this item', 'fluent-crm'), ['status' => 403]);
        }

        // Resolve model + field mapping
        $model = null;
        $contentField = null;
        $titleField = null;
        if ($blockType === 'campaign') {
            $model = Campaign::class;
            $contentField = 'email_body';
            $titleField = 'title';
        } elseif ($blockType === 'email_body_in_funnel') {
            $model = FunnelCampaign::class;
            $contentField = 'email_body';
            $titleField = 'title';
        } elseif ($blockType === 'recurring_campaign') {
            if (defined('FLUENTCAMPAIGN')) {
                $model = \FluentCampaign\App\Models\RecurringCampaign::class;
            }
            // Free-tier: $model stays null; the find block below uses withoutGlobalScopes() fallback.
            $contentField = 'email_body';
            $titleField = 'title';
        } elseif ($blockType === 'sequence_mail') {
            if (defined('FLUENTCAMPAIGN')) {
                $model = \FluentCampaign\App\Models\SequenceMail::class;
            }
            // Free-tier: $model stays null; the find block below uses withoutGlobalScopes() fallback.
            $contentField = 'email_body';
            $titleField = 'title';
        } elseif ($blockType === 'recurring_mail') {
            if (defined('FLUENTCAMPAIGN')) {
                $model = \FluentCampaign\App\Models\RecurringMail::class;
            }
            $contentField = 'email_body';
            $titleField = 'title';
        } elseif ($blockType === 'template') {
            $model = Template::class;
            $contentField = 'post_content';
            $titleField = 'post_title';
        } else {
            return new \WP_Error('invalid_block_type', __('Unsupported block_type', 'fluent-crm'), ['status' => 400]);
        }

        // Guard: recurring_campaign requires Pro; without it $model stays null — skip gracefully
        if ($model === null) {
            return new \WP_REST_Response([
                'status'  => 'skipped',
                'message' => __('Feature not available.', 'fluent-crm')
            ], 200);
        }

        $now = current_time('mysql');
        $created = false;
        $record = null;

        if (!$entityId) {
            return new \WP_REST_Response([
                'status'  => 'skipped',
                'message' => __('No entity ID provided.', 'fluent-crm')
            ], 200);
        }

        if ($entityId) {
            if ($model) {
                $record = $model::find($entityId);
            } else {
                // recurring_campaign on free tier: fc_campaigns with type=recurring_campaign.
                // sequence_mail on free tier: fc_campaigns with type=sequence_mail.
                // Bypass the Campaign global scope so the type filter does not exclude it.
                $record = Campaign::withoutGlobalScopes()->find($entityId);
            }
            if (!$record) {
                return new \WP_Error('not_found', __('Entity not found', 'fluent-crm'), ['status' => 404]);
            }
            // Conflict detection (always compare with model's updated_at mapping)
            if (!empty($prevUpdatedAt) && isset($record->updated_at) && $prevUpdatedAt && $prevUpdatedAt !== $record->updated_at) {
                return new \WP_REST_Response([
                    'status'            => 'conflict',
                    'id'                => $entityId,
                    'server_updated_at' => $record->updated_at,
                    'message'           => __('The item was modified elsewhere.', 'fluent-crm')
                ], 200);
            }
        }

        // Update existing record if needed
        $dirty = false;
        if ($blockType === 'template') {
            // For templates we ONLY save post_content using core wp_update_post for proper cache + hooks
            if ($record->{$contentField} !== $content) {

                $postArr = [
                    'ID'                => $entityId,
                    'post_content'      => $content,
                    'post_modified'     => current_time('mysql'),
                    'post_modified_gmt' => gmdate('Y-m-d H:i:s')
                ];
                $result = wp_update_post($postArr, true);
                if (is_wp_error($result)) {
                    return new \WP_Error('update_failed', $result->get_error_message(), ['status' => 500]);
                }
                $dirty = true;
                // Reload record to get fresh timestamps and updated content
                $record = Template::find($entityId);
            }
        } else {
            if ($record->{$contentField} !== $content) {
                $record->{$contentField} = $content;
                $dirty = true;
            }
            // Even though frontend no longer sends title for autosave, keep defensive logic (but campaign/recurring only)
            if ($title && $record->{$titleField} !== $title) {
                $record->{$titleField} = $title;
                $dirty = true;
            }
            if ($dirty) {
                try {
                    $record->save();
                } catch (\Exception $e) {
                    return new \WP_Error('update_failed', $e->getMessage(), ['status' => 500]);
                }
            }
        }

        $updatedAt = isset($record->updated_at) ? $record->updated_at : $now;

        return new \WP_REST_Response([
            'status'     => $created ? 'created' : ($dirty ? 'ok' : 'noop'),
            'id'         => $entityId,
            'hash'       => $hash,
            'updated_at' => $updatedAt,
            'saved_at'   => $now,
            'created'    => $created
        ], 200);
    }

    public function handleCartProductsListing(\WP_REST_Request $request)
    {
        $fallback = [
            'products'   => [],
            'product'    => null,
            'taxonomies' => []
        ];

        $providerResponse = apply_filters('fluent_crm/cart_products_preview_data', null, $request);
        if (is_array($providerResponse)) {
            return new \WP_REST_Response(wp_parse_args($providerResponse, $fallback), 200);
        }

        if (!defined('FLUENTCART_VERSION')) {
            return new \WP_REST_Response($fallback, 200);
        }

        $perPage = max(1, min(20, absint($request->get_param('per_page') ?: 3)));
        $order = strtolower((string)$request->get_param('order')) === 'asc' ? 'ASC' : 'DESC';
        $productId = absint($request->get_param('product_id'));
        $search = sanitize_text_field((string)$request->get_param('search'));
        if (!$productId) {
            $searchProductId = 0;
            if (preg_match('/^\s*(?:id|product_id)\s*:\s*(\d+)\s*$/i', $search, $matches)) {
                $searchProductId = absint($matches[1]);
            } elseif (preg_match('/^\s*#\s*(\d+)\s*$/', $search, $matches)) {
                $searchProductId = absint($matches[1]);
            }

            if ($searchProductId) {
                $productId = $searchProductId;
                $search = '';
            }
        }

        $taxType = sanitize_text_field((string)$request->get_param('taxType'));
        $products = CartProductData::getProducts([
            'perPage'   => $perPage,
            'order'     => $order,
            'orderBy'   => sanitize_key((string)$request->get_param('orderby')),
            'taxType'   => $taxType,
            'search'    => $search,
            'productId' => $productId,
        ], 'medium');

        $selectedProduct = ($productId && !empty($products[0])) ? $products[0] : null;

        $terms = get_terms([
            'taxonomy'   => 'product-categories',
            'hide_empty' => false
        ]);
        $termOptions = [];
        if (!is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $term) {
                $termOptions[] = [
                    'value' => (string)$term->term_id,
                    'label' => $term->name
                ];
            }
        }

        $response = [
            'products'   => $products,
            'product'    => $selectedProduct,
            'taxonomies' => [
                'product' => [
                    'terms' => [
                        'product_cat' => $termOptions
                    ]
                ]
            ]
        ];

        return new \WP_REST_Response(
            apply_filters('fluent_crm/cart_products_preview_response', $response, $request),
            200
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Editor init: initializeEditor(), resolveEntityContent(),
    |              getOrCreateDummyPost(), prepareEditorBootData(), setupEditorHooks()
    |--------------------------------------------------------------------------
    */

    public function initializeEditor($data = [])
    {
        do_action('litespeed_control_set_nocache', 'fluentcrm api request');
        // set no cache headers
        nocache_headers();

        $context = Arr::get($data, 'block_type'); // campaign or template etc ..
        $this->unregisterDefaultBlockPatterns($context, $data);

        // Double-check permissions inside renderer based on context
        $required_cap = self::getRequiredCapability($context);
        $required_cap = apply_filters('fluent_crm/block_editor_required_cap', $required_cap, $context, $data);
        $hasAccess = PermissionManager::currentUserCan($required_cap);

        $entity = $this->resolveEntityContent($context, $data);

        if (!$hasAccess) {
            echo '<h3 style="padding: 100px; text-align: center;">' . esc_html__('Sorry, you do not have access to this page.', 'fluent-crm') . '</h3>';
            exit(200);
        }

        add_filter('should_load_separate_core_block_assets', '__return_false', 20);
        show_admin_bar(false);

        global $post;
        $post = $this->getOrCreateDummyPost($entity['title'], $entity['content']);

        $this->prepareEditorBootData($context, $data, $entity);
        $this->setupEditorHooks($post);
    }

    /**
     * Resolve entity content from the database based on block_type and bid.
     *
     * @param string $context The block_type (campaign, recurring_campaign, template).
     * @param array $data The request data.
     * @return array With keys: content, title, id, updatedAt, availableTags.
     */
    private function resolveEntityContent($context, $data)
    {
        $post_content = '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
        $post_title = 'Demo Title';
        $recordId = null;
        $recordUpdatedAt = current_time('mysql');
        $availableTags = [];

        if ($context == 'campaign') {
            $campaignId = (int)Arr::get($data, 'bid');
            if ($campaignId && $campaignId != 'undefined') {
                $campaign = Campaign::find($campaignId);
                if ($campaign) {
                    $post_content = $campaign->email_body;
                    $post_title = $campaign->title;
                    $recordId = $campaign->id;
                    $recordUpdatedAt = isset($campaign->updated_at) ? $campaign->updated_at : $recordUpdatedAt;
                }
            }
        }

        if ($context == 'email_body_in_funnel') {
            $campaignId = (int)Arr::get($data, 'bid');
            if ($campaignId && $campaignId != 'undefined') {
                $campaign = FunnelCampaign::find($campaignId);
                if ($campaign) {
                    $post_content = $campaign->email_body;
                    $post_title = $campaign->title;
                    $recordId = $campaign->id;
                    $recordUpdatedAt = isset($campaign->updated_at) ? $campaign->updated_at : $recordUpdatedAt;
                }
            }
        }

        // Pro Required for Recurring Campaigns
        if (defined('FLUENTCAMPAIGN')) {

            if ($context == 'recurring_campaign') {
                $campaign = null;
                $campaignId = (int)Arr::get($data, 'bid');
                if ($campaignId && $campaignId != 'undefined') {
                    $campaign = \FluentCampaign\App\Models\RecurringCampaign::find($campaignId);
                }
                // Fallback for free-tier Email Sequences stored in fc_campaigns with type=recurring_campaign.
                if (!$campaign && $campaignId) {
                    $campaign = Campaign::withoutGlobalScopes()->find($campaignId);
                }
                if ($campaign) {
                    $post_content = $campaign->email_body;
                    $post_title = $campaign->title;
                    $recordId = $campaign->id;
                    $recordUpdatedAt = isset($campaign->updated_at) ? $campaign->updated_at : $recordUpdatedAt;
                }
            }

            if ($context == 'recurring_mail') {
                $mailId = (int)Arr::get($data, 'bid');
                if ($mailId && $mailId != 'undefined') {
                    $mail = \FluentCampaign\App\Models\RecurringMail::find($mailId);
                    if ($mail) {
                        $post_content = $mail->email_body;
                        $post_title = $mail->title;
                        $recordId = $mail->id;
                        $recordUpdatedAt = isset($mail->updated_at) ? $mail->updated_at : $recordUpdatedAt;
                    }
                }
            }

            if ($context == 'sequence_mail') {
                $mail = null;
                $mailId = (int)Arr::get($data, 'bid');
                if ($mailId && $mailId != 'undefined') {
                    $mail = \FluentCampaign\App\Models\SequenceMail::find($mailId);
                }
                // Fallback for free-tier Email Sequences stored in fc_campaigns with type=sequence_mail.
                if (!$mail && $mailId) {
                    $mail = Campaign::withoutGlobalScopes()->find($mailId);
                }
                if ($mail) {
                    $post_content = $mail->email_body;
                    $post_title = $mail->title;
                    $recordId = $mail->id;
                    $recordUpdatedAt = isset($mail->updated_at) ? $mail->updated_at : $recordUpdatedAt;
                }
            }
        }

        if ($context == 'template') {
            $templateId = (int)Arr::get($data, 'bid');
            if ($templateId && $templateId != 'undefined') {
                $template = Template::find($templateId);
                if ($template) {
                    $post_content = $template->post_content;
                    $post_title = $template->post_title;
                    $recordId = $templateId;
                    $recordUpdatedAt = isset($template->updated_at) ? $template->updated_at : $recordUpdatedAt;
                }
            }
        }

        if ($context == 'email_pattern') {
            $patternId = (int)Arr::get($data, 'bid');
            if ($patternId && $patternId != 'undefined') {
                $pattern = Meta::where('object_type', 'email_pattern')->where('id', $patternId)->first();
                if ($pattern) {
                    $post_content = Arr::get($pattern->value, 'content', '');
                    $post_title = Arr::get($pattern->value, 'title', '');
                    $recordId = $pattern->id;
                    $recordUpdatedAt = $pattern->updated_at ?: $recordUpdatedAt;
                }
            }
        }

        try {
            $availableTags = Tag::select(['id', 'title'])->orderBy('title', 'ASC')->get();
        } catch (\Throwable $e) {
            $availableTags = [];
        }

        if (!$post_content) {
            $post_content = '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
        }

        return [
            'content'       => $post_content,
            'title'         => $post_title,
            'id'            => $recordId,
            'updatedAt'     => $recordUpdatedAt,
            'availableTags' => $availableTags,
        ];
    }

    /**
     * Retrieve or create the fcrm-dummy post used as a simulated post for the editor.
     *
     * @param string $title
     * @param string $content
     * @return \WP_Post
     */
    private function getOrCreateDummyPost($title, $content)
    {
        $firstPost = fluentCrmDb()->table('posts')
            ->where('post_type', 'fcrm-dummy')
            ->first();

        if ($firstPost) {
            $simulatedPost = get_post($firstPost->ID);
            $simulatedPost->post_content = $content;
            $simulatedPost->post_title = $title;
        } else {
            $newPostId = wp_insert_post(array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_type'    => 'fcrm-dummy',
                'post_status'  => 'draft',
            ));

            $simulatedPost = get_post($newPostId);
        }

        return $simulatedPost;
    }

    /*
     * -----------------------------------------------------------------------
     * Native REST handlers for editor patterns (wp_block-compatible format).
     * These bypass WPFluent's response pipeline so core-data gets raw responses.
     * -----------------------------------------------------------------------
     */

    public function handleEditorPatternsList()
    {
        $patterns = Meta::where('object_type', 'email_pattern')
            ->orderBy('id', 'desc')
            ->get();

        $categoryMap = $this->getPatternCategoryMap();
        $items = [];
        foreach ($patterns as $pattern) {
            $items[] = $this->formatMetaAsWpBlock($pattern, $categoryMap);
        }

        return new \WP_REST_Response($items, 200);
    }

    public function handleEditorPatternGet(\WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        $pattern = Meta::where('object_type', 'email_pattern')->where('id', $id)->first();

        if (!$pattern) {
            return new \WP_Error('not_found', __('Pattern not found', 'fluent-crm'), ['status' => 404]);
        }

        $categoryMap = $this->getPatternCategoryMap();
        return new \WP_REST_Response($this->formatMetaAsWpBlock($pattern, $categoryMap), 200);
    }

    public function handleEditorPatternCreate(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $title = Arr::get($params, 'title', '');
        if (is_array($title)) {
            $title = Arr::get($title, 'raw', '');
        }
        $title = sanitize_text_field($title);

        $content = Arr::get($params, 'content', '');
        if (is_array($content)) {
            $content = Arr::get($content, 'raw', '');
        }
        $content = wp_kses_post($content);

        if (!$title) {
            $title = __('Untitled Pattern', 'fluent-crm');
        }

        $meta = Arr::get($params, 'meta', []);
        $syncStatus = is_array($meta) ? sanitize_text_field(Arr::get($meta, 'wp_pattern_sync_status', '')) : '';

        $categoryIds = (array) Arr::get($params, 'wp_pattern_category', []);
        $categoryName = $this->resolvePatternCategoryName($categoryIds);

        $slug = 'fluentcrm/' . sanitize_title($title . '-' . uniqid());

        $pattern = Meta::create([
            'object_type' => 'email_pattern',
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

        $categoryMap = $this->getPatternCategoryMap();
        return new \WP_REST_Response($this->formatMetaAsWpBlock($pattern, $categoryMap), 201);
    }

    public function handleEditorPatternUpdate(\WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        $pattern = Meta::where('object_type', 'email_pattern')->where('id', $id)->first();

        if (!$pattern) {
            return new \WP_Error('not_found', __('Pattern not found', 'fluent-crm'), ['status' => 404]);
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $value = $pattern->value;

        $title = Arr::get($params, 'title');
        if ($title !== null) {
            if (is_array($title)) {
                $title = Arr::get($title, 'raw', '');
            }
            $value['title'] = sanitize_text_field($title);
        }

        $content = Arr::get($params, 'content');
        if ($content !== null) {
            if (is_array($content)) {
                $content = Arr::get($content, 'raw', '');
            }
            $value['content'] = wp_kses_post($content);
        }

        $meta = Arr::get($params, 'meta', []);
        if (is_array($meta) && isset($meta['wp_pattern_sync_status'])) {
            $value['sync_status'] = sanitize_text_field($meta['wp_pattern_sync_status']);
        }

        $categoryIds = Arr::get($params, 'wp_pattern_category');
        if ($categoryIds !== null) {
            $value['category'] = $this->resolvePatternCategoryName((array) $categoryIds);
        }

        $pattern->value = $value;
        $pattern->save();

        $categoryMap = $this->getPatternCategoryMap();
        return new \WP_REST_Response($this->formatMetaAsWpBlock($pattern, $categoryMap), 200);
    }

    public function handleEditorPatternDelete(\WP_REST_Request $request)
    {
        $id = (int) $request->get_param('id');
        $pattern = Meta::where('object_type', 'email_pattern')->where('id', $id)->first();

        if (!$pattern) {
            return new \WP_Error('not_found', __('Pattern not found', 'fluent-crm'), ['status' => 404]);
        }

        $response = $this->formatMetaAsWpBlock($pattern, $this->getPatternCategoryMap());
        $pattern->delete();

        return new \WP_REST_Response($response, 200);
    }

    public function handleEditorPatternCategories()
    {
        $categories = Meta::where('object_type', 'email_pattern_category')
            ->orderBy('id', 'asc')
            ->get();

        $items = [];
        foreach ($categories as $cat) {
            $items[] = [
                'id'     => (int) $cat->id,
                'count'  => 0,
                'name'   => Arr::get($cat->value, 'name', $cat->key),
                'slug'   => $cat->key,
                'parent' => 0,
            ];
        }

        return new \WP_REST_Response($items, 200);
    }

    public function handleEditorPatternCategoryCreate(\WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $name = sanitize_text_field(Arr::get($params, 'name', ''));
        if (!$name) {
            return new \WP_Error('missing_name', __('Category name is required', 'fluent-crm'), ['status' => 400]);
        }

        $slug = sanitize_title($name);
        $existing = Meta::where('object_type', 'email_pattern_category')->where('key', $slug)->first();

        if ($existing) {
            return new \WP_REST_Response([
                'id'     => (int) $existing->id,
                'count'  => 0,
                'name'   => Arr::get($existing->value, 'name', $existing->key),
                'slug'   => $existing->key,
                'parent' => 0,
            ], 200);
        }

        $category = Meta::create([
            'object_type' => 'email_pattern_category',
            'object_id'   => 0,
            'key'         => $slug,
            'value'       => ['name' => $name],
        ]);

        return new \WP_REST_Response([
            'id'     => (int) $category->id,
            'count'  => 0,
            'name'   => $name,
            'slug'   => $slug,
            'parent' => 0,
        ], 201);
    }

    private function formatMetaAsWpBlock($meta, $categoryMap = [])
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
            'date'                   => $meta->created_at ?: gmdate('Y-m-d\TH:i:s'),
            'date_gmt'               => $meta->created_at ?: gmdate('Y-m-d\TH:i:s'),
            'modified'               => $meta->updated_at ?: gmdate('Y-m-d\TH:i:s'),
            'modified_gmt'           => $meta->updated_at ?: gmdate('Y-m-d\TH:i:s'),
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

    private function getPatternCategoryMap()
    {
        $categories = Meta::where('object_type', 'email_pattern_category')->get();
        $map = [];
        foreach ($categories as $cat) {
            $map[$cat->key] = $cat->id;
        }
        return $map;
    }

    private function resolvePatternCategoryName($categoryIds)
    {
        if (empty($categoryIds)) {
            return '';
        }
        $categoryIds = array_map('intval', $categoryIds);
        $category = Meta::where('object_type', 'email_pattern_category')
            ->whereIn('id', $categoryIds)
            ->first();

        return $category ? Arr::get($category->value, 'name', $category->key) : '';
    }

    /**
     * Build the editorBootData array that is injected into the iframe as window.fcrmEditorBoot.
     *
     * @param string $context
     * @param array $data
     * @param array $entity Return value from resolveEntityContent().
     */
    private function prepareEditorBootData($context, $data, $entity)
    {
        $canSave = (!empty($context) && $context !== '0');
        // If block_type null/empty and bid == 0 we won't create anything (flag can_save false)
        if (empty($context) && (int)Arr::get($data, 'bid') === 0) {
            $canSave = false;
        }
        // New templates (id=0) should not autosave until they are explicitly created.
        if ($context === 'template' && (int)Arr::get($data, 'bid') === 0) {
            $canSave = false;
        }
        $hideBackBtn = self::parseBoolParam(Arr::get($data, 'hideBackBtn', Arr::get($data, 'hide_back_btn', false)));
        $hideNextBtn = self::parseBoolParam(Arr::get($data, 'hideNextBtn', Arr::get($data, 'hide_next_btn', false)));
        $hideSaveBtn = self::parseBoolParam(Arr::get($data, 'hideSaveBtn', Arr::get($data, 'hide_save_btn', false)));
        $disableAutosave = self::parseBoolParam(Arr::get($data, 'disableAutosave', Arr::get($data, 'disable_autosave', false)));
        if ($disableAutosave) {
            $canSave = false;
        }
        // Load user-saved email patterns from the database
        $savedPatternData = \FluentCrm\App\Http\Controllers\EmailPatternController::getEditorPatterns();
        $savedPatterns = $savedPatternData['patterns'];
        $savedPatternCategories = $savedPatternData['categories'];

        $customPatterns = apply_filters('fluent_crm/block_editor_custom_patterns', $savedPatterns, $context, $data);
        if (!is_array($customPatterns)) {
            $customPatterns = [];
        }
        $customPatternCategories = apply_filters('fluent_crm/block_editor_custom_pattern_categories', $savedPatternCategories, $context, $data);
        if (!is_array($customPatternCategories)) {
            $customPatternCategories = [];
        }
        $designTemplate = isset($data['design_template']) ? sanitize_text_field($data['design_template']) : '';
        $features = $this->getEditorFeatures($context);
        $this->editorBootData = [
            'entity'                  => [
                'id'         => $entity['id'],
                'block_type' => $context,
                'title'      => $entity['title'],
                'content'    => $entity['content'],
                'updated_at' => $entity['updatedAt'],
            ],
            'can_save'                => $canSave,
            'autosave'                => [
                'endpoint' => rest_url('fluent-crm/v1/editor-autosave'),
                'nonce'    => wp_create_nonce('wp_rest')
            ],
            'fcrm_ui'                 => isset($data['fcrm_ui']) ? sanitize_text_field($data['fcrm_ui']) : '',
            'compose_nav'             => [
                'hideBackBtn'    => $hideBackBtn,
                'hideNextBtn'    => $hideNextBtn,
                'hideSaveBtn'    => $hideSaveBtn
            ],
            'email_template_designs'  => \FluentCrm\App\Services\Helper::getEmailDesignTemplates(),
            'current_design_template' => $designTemplate,
            'available_tags'          => $entity['availableTags'],
            'global_email_footer'     => \FluentCrm\App\Services\Helper::getEmailFooterContent(),
            'more_menu'               => [
                'help_url'             => apply_filters('fluent_crm/block_editor_help_url', 'https://fluentcrm.com/docs/'),
                'patterns_url'         => apply_filters('fluent_crm/block_editor_patterns_url', admin_url('edit.php?post_type=wp_block')),
                'hide_welcome_guide'   => (bool)apply_filters('fluent_crm/block_editor_hide_welcome_guide', true),
                'hide_manage_patterns' => (bool)apply_filters('fluent_crm/block_editor_hide_manage_patterns', true),
                'replace_native_help'  => (bool)apply_filters('fluent_crm/block_editor_replace_native_help', true),
            ],
            'patterns'                => [
                'unregister_all' => (bool)apply_filters('fluent_crm/block_editor_unregister_all_patterns', true, $context, $data),
                'custom'         => array_values($customPatterns),
                'categories'     => array_values($customPatternCategories),
            ],
            'ai_writing'              => $this->getAiWritingConfig(),
            'features'                => $features,
        ];
    }

    /**
     * Get editor feature flags based on content type.
     * This is the single source of truth for which UI elements show per context.
     *
     * @param string $context  block_type: campaign, template, email_pattern, recurring_campaign, sequence_mail, email_body_in_funnel
     * @return array
     */
    private function getEditorFeatures($context)
    {
        // Full email editing features (default for campaigns, templates, etc.)
        $emailDefaults = [
            'email_style_settings' => true,
            'email_footer'         => true,
            'email_preview'        => true,
            'save_as_template'     => true,
            'browse_templates'     => true,
            'smartcodes'           => true,
            'design_switcher'      => true,
            'save_draft'           => true,
        ];

        // Minimal features for non-email content (patterns, snippets, etc.)
        $minimalDefaults = [
            'email_style_settings' => false,
            'email_footer'         => false,
            'email_preview'        => true,
            'save_as_template'     => false,
            'browse_templates'     => false,
            'smartcodes'           => false,
            'design_switcher'      => false,
            'switch_editor'        => false,
            'create_pattern'       => false,
            'save_draft'           => true,
            'sidebar_panel_title'  => __('Pattern Info', 'fluent-crm'),
            'sidebar_content'      => '<h3>' . __('Editing Pattern', 'fluent-crm') . '</h3>'
                . '<p>' . __('Patterns are reusable block layouts that can be inserted into any email. Changes here will apply to all future emails that use this pattern.', 'fluent-crm') . '</p>'
                . '<p>' . __('Synced patterns stay linked — editing here updates everywhere. Unsynced patterns are copied on insert.', 'fluent-crm') . '</p>',
        ];

        $presets = [
            'campaign'              => $emailDefaults,
            'template'              => $emailDefaults,
            'recurring_campaign'    => $emailDefaults,
            'recurring_mail'        => $emailDefaults,
            'sequence_mail'         => $emailDefaults,
            'email_body_in_funnel'  => $emailDefaults,
            'email_pattern'         => $minimalDefaults,
        ];

        $features = isset($presets[$context]) ? $presets[$context] : $emailDefaults;

        return apply_filters('fluent_crm/block_editor_features', $features, $context);
    }

    private function getAiWritingConfig()
    {
        $credentials = get_option('_fluent_ai_creds', []);
        $preferences = fluentcrm_get_option('_ai_writing_settings', []);

        if (!is_array($credentials)) {
            $credentials = [];
        }

        $provider = isset($credentials['provider']) ? $credentials['provider'] : '';
        $hasApiKey = !empty($credentials['api_key']) || $provider === 'wordpress';

        return [
            'enabled' => (
                is_array($credentials)
                && is_array($preferences)
                && isset($preferences['is_enabled'])
                && $preferences['is_enabled'] === 'yes'
                && $hasApiKey
                && !empty($credentials['provider'])
                && !empty($credentials['model'])
            ),
        ];
    }

    /**
     * Register wp_enqueue_scripts callbacks, post-locking filters, and other editor hooks.
     *
     * @param \WP_Post $post
     */
    private function setupEditorHooks($post)
    {
        $enqueueHook = 'wp_enqueue_scripts';

        add_action($enqueueHook, function () use ($post) {
            wp_enqueue_script('postbox', admin_url('js/postbox.min.js'), array('jquery-ui-sortable'), false, 1);
            wp_enqueue_editor();
            wp_enqueue_script('wp-tinymce');
            wp_enqueue_style('dashicons');
            wp_enqueue_style('media');
            wp_enqueue_style('admin-menu');
            wp_enqueue_style('admin-bar');
            wp_enqueue_style('l10n');

            wp_add_inline_script(
                'wp-api-fetch',
                \sprintf(
                    'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );',
                    wp_json_encode(
                        array(
                            '/wp/v2/fcrm-dummy/' . $post->ID . '?context=edit' => array(
                                'body' => array(
                                    'id'                 => $post->ID,
                                    'title'              => array('raw' => $post->post_title),
                                    'content'            => array(
                                        'block_format' => 1,
                                        'raw'          => $post->post_content,
                                    ),
                                    'excerpt'            => array('raw' => ''),
                                    'date'               => '',
                                    'date_gmt'           => '',
                                    'modified'           => '',
                                    'modified_gmt'       => '',
                                    'link'               => home_url('/'),
                                    'guid'               => array(),
                                    'parent'             => 0,
                                    'menu_order'         => 0,
                                    'author'             => 0,
                                    'featured_media'     => 0,
                                    'comment_status'     => 'closed',
                                    'ping_status'        => 'closed',
                                    'template'           => '',
                                    'meta'               => array(),
                                    '_links'             => array(),
                                    'type'               => 'fcrm-dummy',
                                    'status'             => 'pending', // pending is the best state to remove draft saving possibilities.
                                    'slug'               => '',
                                    'generated_slug'     => '',
                                    'permalink_template' => home_url('/'),
                                ),
                            ),
                        )
                    )
                ),
                'after'
            );
        }, 11);

        add_action($enqueueHook, function ($hook) use ($post) {
            // Gutenberg requires the post-locking functions defined within:
            // See `show_post_locked_dialog` and `get_post_metadata` filters below.
            include_once ABSPATH . 'wp-admin/includes/post.php';
            $this->enqueueEditorAssets($hook, $post);
        });

        // Disable post locking dialogue.
        add_filter('show_post_locked_dialog', '__return_false');

        // Everyone can richedit! This avoids a case where a page can be cached where a user can't richedit.
        $GLOBALS['wp_rich_edit'] = true;
        add_filter('user_can_richedit', '__return_true', 1000);

        // This prevents other logged-in users taking a lock of the post on the front-end.
        add_filter('get_post_metadata', function ($value, $post_id, $meta_key) {
            if ($meta_key !== '_edit_lock') {
                return $value;
            }
            return time() . ':' . get_current_user_id();
        }, 10, 3);

        // Disable Jetpack Blocks for now.
        add_filter('jetpack_gutenberg', '__return_false');
    }

    /*
    |--------------------------------------------------------------------------
    | Assets: enqueueEditorAssets(), enqueueEditorScripts(),
    |         enqueueEditorStyles(), enqueueCustomEditorAssets()
    |--------------------------------------------------------------------------
    */

    private function enqueueEditorAssets($hook, $post)
    {

        $initial_edits = array(
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        );

        $editor_settings = $this->getEditorSettings($post);

        $init_script = <<<JS
			( function() {
				window._wpLoadBlockEditor = new Promise( function( resolve, reject ) {
					wp.domReady( function() {
						try {
							resolve( wp.editPost.initializeEditor( 'editor', "%s", %d, %s, %s ) );
						} catch (e) {
							console.error('[FCRM Editor] initializeEditor failed:', e);
							reject(e);
						}
					} );
				} );
			} )();
			JS;

        $script = sprintf(
            $init_script,
            $post->post_type,
            $post->ID,
            wp_json_encode($editor_settings),
            wp_json_encode($initial_edits)
        );
        wp_add_inline_script('wp-edit-post', $script);

        $this->enqueueEditorScripts($post);
        $this->enqueueEditorStyles();
        $this->enqueueCustomEditorAssets();
    }

    /**
     * Enqueue media, tinymce, and postbox init scripts.
     *
     * @param \WP_Post $post
     */
    private function enqueueEditorScripts($post)
    {
        wp_enqueue_media(
            array(
                'post' => null
            )
        );

        add_filter('user_can_richedit', '__return_true');
        wp_tinymce_inline_scripts();
        wp_enqueue_editor();
        wp_enqueue_script('wp-tinymce');
    }

    /**
     * Enqueue wp-edit-post, block library styles, and editor format library assets.
     */
    private function enqueueEditorStyles()
    {
        wp_enqueue_style('wp-edit-post');

        /*
        These styles are usually registered by Gutenberg and register properly when the user is signed in.
        However, if the use is not registered they are not added. For now, include them, but this isn't a good long term strategy

        See: https://github.com/WordPress/wporg-gutenberg/issues/26
        */
        wp_enqueue_style('wp-block-library');
        wp_enqueue_style('wp-block-image');
        wp_enqueue_style('wp-block-group');
        wp_enqueue_style('wp-block-heading');
        wp_enqueue_style('wp-block-button');
        wp_enqueue_style('wp-block-paragraph');
        wp_enqueue_style('wp-block-separator');
        wp_enqueue_style('wp-block-columns');
        wp_enqueue_style('wp-block-row');
        wp_enqueue_style('wp-block-cover');
        wp_enqueue_style('wp-block-spacer');

        wp_register_style('fluent_crm_editor_styles', FLUENTCRM_PLUGIN_URL . 'assets/guten-editor/style.css', false, FLUENTCRM_PLUGIN_VERSION, 'all');

        add_action('fluent_enqueue_block_editor_assets', 'wp_enqueue_editor_format_library_assets');

        /**
         * Fires after block assets have been enqueued for the editing interface.
         *
         * Call `add_action` on any hook before 'admin_enqueue_scripts'.
         *
         * In the function call you supply, simply use `wp_enqueue_script` and
         * `wp_enqueue_style` to add your functionality to the Gutenberg editor.
         *
         * @since 0.4.0
         */
        do_action('fluent_enqueue_block_editor_assets');
    }

    /**
     * Enqueue fcrm_editor_custom JS, inline block editor config, smartcodes, and boot data.
     */
    private function enqueueCustomEditorAssets()
    {
        $editor_asset_file = FLUENTCRM_PLUGIN_PATH . 'assets/guten-editor/index.asset.php';
        $editor_asset      = file_exists($editor_asset_file) ? require($editor_asset_file) : [];
        $editor_deps       = !empty($editor_asset['dependencies']) ? $editor_asset['dependencies'] : ['react', 'wp-edit-post', 'wp-plugins'];
        $editor_version    = !empty($editor_asset['version']) ? $editor_asset['version'] : FLUENTCRM_PLUGIN_VERSION;
        if (!in_array('wp-edit-post', $editor_deps)) {
            $editor_deps[] = 'wp-edit-post';
        }
        if (!in_array('wp-reusable-blocks', $editor_deps)) {
            $editor_deps[] = 'wp-reusable-blocks';
        }
        wp_enqueue_script('fcrm_editor_custom', FLUENTCRM_PLUGIN_URL . 'assets/guten-editor/index.js', $editor_deps, $editor_version, true);

        $availableDesigns = Helper::getEmailDesignTemplates();
        $availableDesigns = array_filter($availableDesigns, function ($design) {
            return !empty($design['use_gutenberg']);
        });
        $designPresetPayloads = [];
        foreach ($availableDesigns as $designKey => $design) {
            $designPresetPayloads[$designKey] = $design;
            $designPresetPayloads[$designKey]['config'] = [
                'design_template' => Arr::get($design, 'id', $designKey)
            ];
        }

        $blockEditorConfig = [
            'modules'                 => [
                'hasWooCommerce'    => defined('WC_PLUGIN_FILE'),
                'hasFluentCampaign' => defined('FLUENTCAMPAIGN'),
                'hasFluentCart'     => defined('FLUENTCART_VERSION')
            ],
            'endpoints'               => [
                'products'     => apply_filters('fluent_crm/block_editor_products_endpoint', 'fluent-crm/v2/campaigns-pro/products'),
                'cartProducts' => apply_filters('fluent_crm/block_editor_cart_products_endpoint', 'fluent-crm/v2/editor/cart-products'),
                'tags'         => apply_filters('fluent_crm/block_editor_tags_endpoint', 'fluent-crm/v2/reports/options?fields=tags')
            ],
            'fontSizes'               => BlockEditorHelper::getDefaultPreset('font-size'),
            'spacingPresets'          => BlockEditorHelper::getDefaultPreset('spacing'),
            'defaultDesignConfig'     => Helper::getTemplateConfig(false),
            // Keep the shared design metadata intact for legacy consumers while ensuring
            // Gutenberg preset clicks only change the selected design template.
            'designTemplatePresets'   => $designPresetPayloads,
            'default_design_template' => Helper::getDefaultEmailTemplate()
        ];

        wp_add_inline_script(
            'fcrm_editor_custom',
            'window.fcrmBlockEditorConfig = ' . wp_json_encode($blockEditorConfig) . ';',
            'before'
        );

        // Inject smartcodes into the iframe window
        $globalSmartCodes = Helper::getGlobalSmartCodes();
        $extendedSmartCodes = Helper::getExtendedSmartCodes();
        $transStrings = TransStringsGuten::getStrings();
        wp_add_inline_script(
            'fcrm_editor_custom',
            'window.fcAdmin = window.fcAdmin || {};' .
            'window.fcAdmin.globalSmartCodes = ' . wp_json_encode($globalSmartCodes) . ';' .
            'window.fcAdmin.extendedSmartCodes = ' . wp_json_encode($extendedSmartCodes) . ';' .
            'window.fcAdmin.trans = Object.assign({}, window.fcAdmin.trans || {}, ' . wp_json_encode($transStrings) . ');',
            'before'
        );

        if (!empty($this->editorBootData)) {
            wp_add_inline_script(
                'fcrm_editor_custom',
                'window.fcrmEditorBoot = ' . wp_json_encode($this->editorBootData) . ';',
                'before'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Settings: getEditorSettings(), getAllowedBlocks(), getEditorStyleSheets(),
    |           getResolvedAssets(), getDefaultEditorStyles()
    |--------------------------------------------------------------------------
    */

    private function getEditorSettings($post)
    {
        // Run pattern cleanup here as well (late) so theme/plugin init hooks cannot re-add defaults.
        $context = Arr::get($this->editorBootData, 'entity.block_type', '');
        $requestData = is_array($_REQUEST) ? $_REQUEST : [];
        $this->unregisterDefaultBlockPatterns($context, $requestData);

        // Media settings.
        $max_upload_size = wp_max_upload_size();
        if (!$max_upload_size) {
            $max_upload_size = 0;
        }

        $lock_details = array(
            'isLocked' => false,
            'user'     => '',
        );

        $allowedBlocks = $this->getAllowedBlocks();

        $blockStyleDefaults = BlockEditorHelper::getStyleDefauls();


        $editor_settings = array(
            'maxUploadFileSize'                => $max_upload_size,
            'allowedMimeTypes'                 => get_allowed_mime_types(),
            'postLock'                         => $lock_details,
            'postLockUtils'                    => array(
                'nonce'       => wp_create_nonce('lock-post_' . $post->ID),
                'unlockNonce' => wp_create_nonce('update-post_' . $post->ID),
                'ajaxUrl'     => admin_url('admin-ajax.php'),
            ),
            '__experimentalFeatures'           => array(
                'appearanceTools'               => true,
                'useRootPaddingAwareAlignments' => false,
                'border'                        => [
                    'color'  => 1,
                    'radius' => 1,
                    'style'  => 1,
                    'width'  => 1,
                ],
                'color'                         => [
                    'background'       => true,
                    'button'           => 1,
                    'caption'          => 1,
                    'customDuotone'    => 0,
                    'defaultDuotone'   => 0,
                    'defaultGradients' => 0,
                    'defaultPalette'   => true,
                    'duotone'          => [],
                    'gradients'        => [],
                    'heading'          => 1,
                    'link'             => 1,
                    'palette'          => [
                        'default' => $blockStyleDefaults['color'],
                    ],
                    'text'             => true,
                ],
                'dimensions'                    => [
                    'units'               => ['px'],
                    'defaultAspectRatios' => false,
                    'aspectRatio'         => false,
                    'minHeight'           => 1,
                ],
                'shadow'                        => [
                    'defaultPresets' => true,
                    'presets'        => [
                        'default' => [
                            [
                                'name'   => 'Natural',
                                'slug'   => 'natural',
                                'shadow' => '6px 6px 9px rgba(0, 0, 0, 0.2)',
                            ],
                            [
                                'name'   => 'Deep',
                                'slug'   => 'deep',
                                'shadow' => '12px 12px 50px rgba(0, 0, 0, 0.4)',
                            ],
                            [
                                'name'   => 'Sharp',
                                'slug'   => 'sharp',
                                'shadow' => '6px 6px 0px rgba(0, 0, 0, 0.2)',
                            ],
                            [
                                'name'   => 'Outlined',
                                'slug'   => 'outlined',
                                'shadow' => '6px 6px 0px -3px rgba(255, 255, 255, 1), 6px 6px rgba(0, 0, 0, 1)',
                            ],
                            [
                                'name'   => 'Crisp',
                                'slug'   => 'crisp',
                                'shadow' => '6px 6px 0px rgba(0, 0, 0, 1)',
                            ],
                        ],
                    ],
                ],
                'spacing'                       => [
                    'blockGap'            => 1,
                    'margin'              => 1,
                    'padding'             => 1,
                    'units'               => ['px'],
                    'defaultSpacingSizes' => true,
                    'spacingScale'        => [
                        'default' => [
                            'operator'   => '*',
                            'increment'  => 1.5,
                            'steps'      => 7,
                            'mediumStep' => 24,
                            'unit'       => 'px',
                        ],
                    ],
                    'spacingSizes'        => [
                        'default' => $blockStyleDefaults['spacing'],
                    ]
                ],
                'typography'                    => [
                    'defaultFontSizes' => true,
                    'dropCap'          => false,
                    'fontFamilies'     => [
                        'default' => $blockStyleDefaults['font-family']
                    ],
                    'fontSizes'        => [
                        'default' => $blockStyleDefaults['font-size']
                    ],
                    'fontStyle'        => true,
                    'fontWeight'       => true,
                    'letterSpacing'    => true,
                    'textAlign'        => true,
                    'textDecoration'   => true,
                    'textTransform'    => true,
                    'writingMode'      => false,
                    'units'            => ['px'],
                    'fluid'            => false
                ],
                'blocks'                        => [
                    'core/button'    => [
                        'border' => [
                            'radius' => true,
                        ]
                    ],
                    'core/buttons'   => [
                        'border'      => [
                            'radius' => false,
                        ],
                        'spacing'     => [
                            'blockGap' => false,
                        ],
                        'layout'      => false,
                        'contentRole' => false
                    ],
                    'core/image'     => [
                        'lightbox' => [
                            'allowEditing' => true,
                        ]
                    ],
                    'core/pullquote' => [
                        'border' => [
                            'color'  => true,
                            'radius' => true,
                            'style'  => true,
                            'width'  => true,
                        ]
                    ],
                    'core/paragraph' => [
                        'spacing' => [
                            'margin'  => 1,
                            'padding' => 1,
                        ]
                    ],
                    'core/columns'   => [
                        // disable block gap for columns block, but keep for inner column blocks
                        'spacing' => [
                            'blockGap'            => array(
                                '__experimentalDefault' => '20px',
                                'sides'                 => array(
                                    'horizontal'
                                )
                            ),
                            'defaultSpacingSizes' => false,
                        ],
                        'border'  => [
                            'color'  => true,
                            'radius' => false,
                            'style'  => true,
                            'width'  => true,
                        ],
                        'shadow'  => false,
                    ],
                    'core/group'     => [
                        '__experimentalSettings' => false,
                        'shadow'                 => false,
                        'dimensions'             => [
                            'minHeight' => false
                        ],
                        'spacing'                => [
                            'blockGap' => false,
                            'margin'   => true,
                            'padding'  => true,
                        ],
                        'position'               => [
                            'sticky' => false,
                        ],
                        'layout'                 => [
                            'allowSizingOnChildren' => false,
                            'contentSize'           => false
                        ]
                    ],
                    'core/row'       => [
                        '__experimentalSettings' => false,
                        'shadow'                 => false,
                        'dimensions'             => [
                            'minHeight' => false
                        ],
                        'spacing'                => [
                            'blockGap' => true,
                            'margin'   => true,
                            'padding'  => true,
                        ],
                        'position'               => [
                            'sticky' => false,
                        ]
                    ]
                ],
                'layout'                        => [
                    'contentSize' => 'var(--theme-block-max-width)',
                    'wideSize'    => 'var(--theme-block-wide-max-width)',
                ],
                'background'                    => [
                    'backgroundImage' => 1,
                    'backgroundSize'  => 1,
                ],
                'position'                      => [
                    'sticky' => 0,
                ]
            ),
            '__experimentalDiscussionSettings' => [
                'avatarURL'            => 'https://secure.gravatar.com/avatar/?s=96&f=y&r=g',
                'commentOrder'         => 'asc',
                'commentsPerPage'      => '50',
                'defaultCommentsPage'  => 'newest',
                'defaultCommentStatus' => 'open',
                'pageComments'         => '',
                'threadComments'       => '1',
                'threadCommentsDepth'  => '5'
            ],
            '__unstableGalleryWithImageBlocks' => false,
            '__unstableIsBlockBasedTheme'      => false,
            'enableCustomUnits'                => false,
            'fontSizes'                        => [
                [
                    'name' => 'Small',
                    'size' => 'var(--fcom-font-size-small)',
                    'slug' => 'small'
                ],
                [
                    'name' => 'Medium',
                    'size' => 'var(--fcom-font-size-medium)',
                    'slug' => 'medium'
                ],
                [
                    'name' => 'Large',
                    'size' => 'var(--fcom-font-size-large)',
                    'slug' => 'large'
                ],
                [
                    'name' => 'Larger',
                    'size' => 'var(--fcom-font-size-larger)',
                    'slug' => 'larger'
                ],
                [
                    'name' => 'XX-Large',
                    'size' => 'var(--fcom-font-size-xxlarge)',
                    'slug' => 'xxlarge'
                ]
            ],
            'fullscreenMode'                   => 1,
            'enableCustomSpacing'              => 1,
            'enableCustomLineHeight'           => 1,
            'enableCustomFields'               => false,
            'disablePostFormats'               => true,
            'disableLayoutStyles'              => true,
            'disableCustomSpacingSizes'        => false,
            'disableCustomGradients'           => 1,
            'alignWide'                        => true,
            'disableCustomFontSizes'           => false,
            'disableCustomColors'              => false,
            'canUpdateBlockBindings'           => false,
            'bodyPlaceholder'                  => __('Start writing your email...', 'fluent-crm'),
            'allowedBlockTypes'                => $allowedBlocks,
            'gradients'                        => [],
            'imageDefaultSize'                 => 'large',
            'imageEditing'                     => true,
            'isRTL'                            => is_rtl(),
            'autosaveInterval'                 => 999,
            'localAutosaveInterval'            => 999,
            'richEditingEnabled'               => true,
            'spacingSizes'                     => [
                [
                    'name' => '2X-Small',
                    'size' => '0.44rem',
                    'slug' => '20'
                ],
                [
                    'name' => 'X-Small',
                    'size' => '0.67rem',
                    'slug' => '30'
                ],
                [
                    'name' => 'Small',
                    'size' => '1rem',
                    'slug' => '40'
                ],
                [
                    'name' => 'Medium',
                    'size' => '1.5rem',
                    'slug' => '50'
                ],
                [
                    'name' => 'Large',
                    'size' => '2.25rem',
                    'slug' => '60'
                ],
                [
                    'name' => 'X-Large',
                    'size' => '3.38rem',
                    'slug' => '70'
                ],
                [
                    'name' => '2X-Large',
                    'size' => '5.06rem',
                    'slug' => '80'
                ]
            ],
            'titlePlaceholder'                 => __('Add Lesson title', 'fluent-crm')
        );

        $editor_settings['styles'] = $this->getEditorStyleSheets();
        $editor_settings['__unstableResolvedAssets'] = $this->getResolvedAssets();
        $editor_settings['defaultEditorStyles'] = $this->getDefaultEditorStyles();
        $editor_settings['imageSizes'] = $this->getAvailableImageSizes();

        $editor_settings['__experimentalBlockPatterns'] = array_values((array)apply_filters(
            'fluent_crm/block_editor_custom_patterns',
            [],
            $context,
            $requestData
        ));
        $editor_settings['__experimentalBlockPatternCategories'] = array_values((array)apply_filters(
            'fluent_crm/block_editor_custom_pattern_categories',
            [],
            $context,
            $requestData
        ));

        $editor_settings = apply_filters('fluent_crm/block_editor_settings', $editor_settings);
        return $editor_settings;
    }

    /**
     * Return the allowed blocks array for the editor.
     *
     * @return array
     */
    private function getAllowedBlocks()
    {
        $allowedBlocks = [
            'core/block',
            'core/buttons',
            'core/button',
            'core/code',
            'core/columns',
            'core/column',
            'core/footnotes',
            'core/freeform',
            'core/group',
            'core/row',
            'core/heading',
            'core/html',
            'core/image',
            'core/list',
            'core/list-item',
            'core/missing',
            'core/paragraph',
            'core/preformatted',
            'core/pullquote',
            'core/quote',
            'core/rss',
            'core/separator',
            'core/spacer',
            'core/table',
            'core/verse',
            'core/freeform',
            'fluentcrm/conditional-group'
        ];

        if (defined('WC_PLUGIN_FILE')) {
            $allowedBlocks[] = 'fluentcrm/woo-product';
            $allowedBlocks[] = 'fluent-crm/woo-product';
        }

        if (defined('FLUENTCAMPAIGN')) {
            $allowedBlocks[] = 'fluent-crm/latest-posts';
            if (defined('WC_PLUGIN_FILE')) {
                $allowedBlocks[] = 'fluent-crm/woo-products';
            }
        }

        if (defined('FLUENTCART_VERSION')) {
            $allowedBlocks[] = 'fluent-crm/cart-products';
            $allowedBlocks[] = 'fluent-crm/cart-product';
        }

        $aiConfig = $this->getAiWritingConfig();
        if (!empty($aiConfig['enabled'])) {
            $allowedBlocks[] = 'fluent-crm/ai-writer';
        }

        $allowedBlocks = apply_filters('fluent_crm/new_editor_allowed_block_types', $allowedBlocks);
        $allowedBlocks = array_values(array_unique($allowedBlocks));

        return $allowedBlocks;
    }

    /**
     * Return the `styles` array for the editor settings (the massive CSS).
     *
     * @return array
     */
    private function getEditorStyleSheets()
    {
        $defaultPresets = BlockEditorHelper::getStyleDefaultPresets();
        $dynamicCss = BlockEditorHelper::getDynamicCssForEditor();

        return [
            [
                '__unstableType' => 'presets',
                'css'            => ':root{' . $defaultPresets . '--wp--preset--aspect-ratio--square: 1;--wp--preset--aspect-ratio--4-3: 4/3;--wp--preset--aspect-ratio--3-4: 3/4;--wp--preset--aspect-ratio--3-2: 3/2;--wp--preset--aspect-ratio--2-3: 2/3;--wp--preset--aspect-ratio--16-9: 16/9;--wp--preset--aspect-ratio--9-16: 9/16;--wp--preset--color--theme-palette-color-1: var(--theme-palette-color-1);--wp--preset--color--theme-palette-color-2: var(--theme-palette-color-2);--wp--preset--color--theme-palette-color-3: var(--theme-palette-color-3);--wp--preset--color--theme-palette-color-4: var(--theme-palette-color-4);--wp--preset--color--theme-palette-color-5: var(--theme-palette-color-5);--wp--preset--color--theme-palette-color-6: var(--theme-palette-color-6);--wp--preset--color--theme-palette-color-7: var(--theme-palette-color-7);--wp--preset--color--theme-palette-color-8: var(--theme-palette-color-8);--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple: linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%);--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan: linear-gradient(135deg,rgb(122,220,180) 0%,rgb(0,208,130) 100%);--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange: linear-gradient(135deg,rgba(252,185,0,1) 0%,rgba(255,105,0,1) 100%);--wp--preset--gradient--luminous-vivid-orange-to-vivid-red: linear-gradient(135deg,rgba(255,105,0,1) 0%,rgb(207,46,46) 100%);--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray: linear-gradient(135deg,rgb(238,238,238) 0%,rgb(169,184,195) 100%);--wp--preset--gradient--cool-to-warm-spectrum: linear-gradient(135deg,rgb(74,234,220) 0%,rgb(151,120,209) 20%,rgb(207,42,186) 40%,rgb(238,44,130) 60%,rgb(251,105,98) 80%,rgb(254,248,76) 100%);--wp--preset--gradient--blush-light-purple: linear-gradient(135deg,rgb(255,206,236) 0%,rgb(152,150,240) 100%);--wp--preset--gradient--blush-bordeaux: linear-gradient(135deg,rgb(254,205,165) 0%,rgb(254,45,45) 50%,rgb(107,0,62) 100%);--wp--preset--gradient--luminous-dusk: linear-gradient(135deg,rgb(255,203,112) 0%,rgb(199,81,192) 50%,rgb(65,88,208) 100%);--wp--preset--gradient--pale-ocean: linear-gradient(135deg,rgb(255,245,203) 0%,rgb(182,227,212) 50%,rgb(51,167,181) 100%);--wp--preset--gradient--electric-grass: linear-gradient(135deg,rgb(202,248,128) 0%,rgb(113,206,126) 100%);--wp--preset--gradient--midnight: linear-gradient(135deg,rgb(2,3,129) 0%,rgb(40,116,252) 100%);--wp--preset--gradient--juicy-peach: linear-gradient(to right, #ffecd2 0%, #fcb69f 100%);--wp--preset--gradient--young-passion: linear-gradient(to right, #ff8177 0%, #ff867a 0%, #ff8c7f 21%, #f99185 52%, #cf556c 78%, #b12a5b 100%);--wp--preset--gradient--true-sunset: linear-gradient(to right, #fa709a 0%, #fee140 100%);--wp--preset--gradient--morpheus-den: linear-gradient(to top, #30cfd0 0%, #330867 100%);--wp--preset--gradient--plum-plate: linear-gradient(135deg, #667eea 0%, #764ba2 100%);--wp--preset--gradient--aqua-splash: linear-gradient(15deg, #13547a 0%, #80d0c7 100%);--wp--preset--gradient--love-kiss: linear-gradient(to top, #ff0844 0%, #ffb199 100%);--wp--preset--gradient--new-retrowave: linear-gradient(to top, #3b41c5 0%, #a981bb 49%, #ffc8a9 100%);--wp--preset--gradient--plum-bath: linear-gradient(to top, #cc208e 0%, #6713d2 100%);--wp--preset--gradient--high-flight: linear-gradient(to right, #0acffe 0%, #495aff 100%);--wp--preset--gradient--teen-party: linear-gradient(-225deg, #FF057C 0%, #8D0B93 50%, #321575 100%);--wp--preset--gradient--fabled-sunset: linear-gradient(-225deg, #231557 0%, #44107A 29%, #FF1361 67%, #FFF800 100%);--wp--preset--gradient--arielle-smile: radial-gradient(circle 248px at center, #16d9e3 0%, #30c7ec 47%, #46aef7 100%);--wp--preset--gradient--itmeo-branding: linear-gradient(180deg, #2af598 0%, #009efd 100%);--wp--preset--gradient--deep-blue: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);--wp--preset--gradient--strong-bliss: linear-gradient(to right, #f78ca0 0%, #f9748f 19%, #fd868c 60%, #fe9a8b 100%);--wp--preset--gradient--sweet-period: linear-gradient(to top, #3f51b1 0%, #5a55ae 13%, #7b5fac 25%, #8f6aae 38%, #a86aa4 50%, #cc6b8e 62%, #f18271 75%, #f3a469 87%, #f7c978 100%);--wp--preset--gradient--purple-division: linear-gradient(to top, #7028e4 0%, #e5b2ca 100%);--wp--preset--gradient--cold-evening: linear-gradient(to top, #0c3483 0%, #a2b6df 100%, #6b8cce 100%, #a2b6df 100%);--wp--preset--gradient--mountain-rock: linear-gradient(to right, #868f96 0%, #596164 100%);--wp--preset--gradient--desert-hump: linear-gradient(to top, #c79081 0%, #dfa579 100%);--wp--preset--gradient--ethernal-constance: linear-gradient(to top, #09203f 0%, #537895 100%);--wp--preset--gradient--happy-memories: linear-gradient(-60deg, #ff5858 0%, #f09819 100%);--wp--preset--gradient--grown-early: linear-gradient(to top, #0ba360 0%, #3cba92 100%);--wp--preset--gradient--morning-salad: linear-gradient(-225deg, #B7F8DB 0%, #50A7C2 100%);--wp--preset--gradient--night-call: linear-gradient(-225deg, #AC32E4 0%, #7918F2 48%, #4801FF 100%);--wp--preset--gradient--mind-crawl: linear-gradient(-225deg, #473B7B 0%, #3584A7 51%, #30D2BE 100%);--wp--preset--gradient--angel-care: linear-gradient(-225deg, #FFE29F 0%, #FFA99F 48%, #FF719A 100%);--wp--preset--gradient--juicy-cake: linear-gradient(to top, #e14fad 0%, #f9d423 100%);--wp--preset--gradient--rich-metal: linear-gradient(to right, #d7d2cc 0%, #304352 100%);--wp--preset--gradient--mole-hall: linear-gradient(-20deg, #616161 0%, #9bc5c3 100%);--wp--preset--gradient--cloudy-knoxville: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);--wp--preset--gradient--soft-grass: linear-gradient(to top, #c1dfc4 0%, #deecdd 100%);--wp--preset--gradient--saint-petersburg: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);--wp--preset--gradient--everlasting-sky: linear-gradient(135deg, #fdfcfb 0%, #e2d1c3 100%);--wp--preset--gradient--kind-steel: linear-gradient(-20deg, #e9defa 0%, #fbfcdb 100%);--wp--preset--gradient--over-sun: linear-gradient(60deg, #abecd6 0%, #fbed96 100%);--wp--preset--gradient--premium-white: linear-gradient(to top, #d5d4d0 0%, #d5d4d0 1%, #eeeeec 31%, #efeeec 75%, #e9e9e7 100%);--wp--preset--gradient--clean-mirror: linear-gradient(45deg, #93a5cf 0%, #e4efe9 100%);--wp--preset--gradient--wild-apple: linear-gradient(to top, #d299c2 0%, #fef9d7 100%);--wp--preset--gradient--snow-again: linear-gradient(to top, #e6e9f0 0%, #eef1f5 100%);--wp--preset--gradient--confident-cloud: linear-gradient(to top, #dad4ec 0%, #dad4ec 1%, #f3e7e9 100%);--wp--preset--gradient--glass-water: linear-gradient(to top, #dfe9f3 0%, white 100%);--wp--preset--gradient--perfect-white: linear-gradient(-225deg, #E3FDF5 0%, #FFE6FA 100%);--wp--preset--font-size--small: var(--fcom-font-size-small);--wp--preset--font-size--medium: var(--fcom-font-size-medium);--wp--preset--font-size--large: var(--fcom-font-size-large);--wp--preset--font-size--x-large: 42px;--wp--preset--font-size--larger: var(--fcom-font-size-larger);--wp--preset--font-size--xxlarge: var(--fcom-font-size-xxlarge);--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2);--wp--preset--shadow--deep: 12px 12px 50px rgba(0, 0, 0, 0.4);--wp--preset--shadow--sharp: 6px 6px 0px rgba(0, 0, 0, 0.2);--wp--preset--shadow--outlined: 6px 6px 0px -3px rgba(255, 255, 255, 1), 6px 6px rgba(0, 0, 0, 1);--wp--preset--shadow--crisp: 6px 6px 0px rgba(0, 0, 0, 1);}',
                'isGlobalStyles' => true
            ],
            [
                '__unstableType' => 'presets',
                'css'            => '.has-theme-palette-color-1-color{color: var(--wp--preset--color--theme-palette-color-1) !important;}.has-theme-palette-color-2-color{color: var(--wp--preset--color--theme-palette-color-2) !important;}.has-theme-palette-color-3-color{color: var(--wp--preset--color--theme-palette-color-3) !important;}.has-theme-palette-color-4-color{color: var(--wp--preset--color--theme-palette-color-4) !important;}.has-theme-palette-color-5-color{color: var(--wp--preset--color--theme-palette-color-5) !important;}.has-theme-palette-color-6-color{color: var(--wp--preset--color--theme-palette-color-6) !important;}.has-theme-palette-color-7-color{color: var(--wp--preset--color--theme-palette-color-7) !important;}.has-theme-palette-color-8-color{color: var(--wp--preset--color--theme-palette-color-8) !important;}.has-theme-palette-color-1-background-color{background-color: var(--wp--preset--color--theme-palette-color-1) !important;}.has-theme-palette-color-2-background-color{background-color: var(--wp--preset--color--theme-palette-color-2) !important;}.has-theme-palette-color-3-background-color{background-color: var(--wp--preset--color--theme-palette-color-3) !important;}.has-theme-palette-color-4-background-color{background-color: var(--wp--preset--color--theme-palette-color-4) !important;}.has-theme-palette-color-5-background-color{background-color: var(--wp--preset--color--theme-palette-color-5) !important;}.has-theme-palette-color-6-background-color{background-color: var(--wp--preset--color--theme-palette-color-6) !important;}.has-theme-palette-color-7-background-color{background-color: var(--wp--preset--color--theme-palette-color-7) !important;}.has-theme-palette-color-8-background-color{background-color: var(--wp--preset--color--theme-palette-color-8) !important;}.has-theme-palette-color-1-border-color{border-color: var(--wp--preset--color--theme-palette-color-1) !important;}.has-theme-palette-color-2-border-color{border-color: var(--wp--preset--color--theme-palette-color-2) !important;}.has-theme-palette-color-3-border-color{border-color: var(--wp--preset--color--theme-palette-color-3) !important;}.has-theme-palette-color-4-border-color{border-color: var(--wp--preset--color--theme-palette-color-4) !important;}.has-theme-palette-color-5-border-color{border-color: var(--wp--preset--color--theme-palette-color-5) !important;}.has-theme-palette-color-6-border-color{border-color: var(--wp--preset--color--theme-palette-color-6) !important;}.has-theme-palette-color-7-border-color{border-color: var(--wp--preset--color--theme-palette-color-7) !important;}.has-theme-palette-color-8-border-color{border-color: var(--wp--preset--color--theme-palette-color-8) !important;}.has-vivid-cyan-blue-to-vivid-purple-gradient-background{background: var(--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple) !important;}.has-light-green-cyan-to-vivid-green-cyan-gradient-background{background: var(--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan) !important;}.has-luminous-vivid-amber-to-luminous-vivid-orange-gradient-background{background: var(--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange) !important;}.has-luminous-vivid-orange-to-vivid-red-gradient-background{background: var(--wp--preset--gradient--luminous-vivid-orange-to-vivid-red) !important;}.has-very-light-gray-to-cyan-bluish-gray-gradient-background{background: var(--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray) !important;}.has-cool-to-warm-spectrum-gradient-background{background: var(--wp--preset--gradient--cool-to-warm-spectrum) !important;}.has-blush-light-purple-gradient-background{background: var(--wp--preset--gradient--blush-light-purple) !important;}.has-blush-bordeaux-gradient-background{background: var(--wp--preset--gradient--blush-bordeaux) !important;}.has-luminous-dusk-gradient-background{background: var(--wp--preset--gradient--luminous-dusk) !important;}.has-pale-ocean-gradient-background{background: var(--wp--preset--gradient--pale-ocean) !important;}.has-electric-grass-gradient-background{background: var(--wp--preset--gradient--electric-grass) !important;}.has-midnight-gradient-background{background: var(--wp--preset--gradient--midnight) !important;}.has-juicy-peach-gradient-background{background: var(--wp--preset--gradient--juicy-peach) !important;}.has-young-passion-gradient-background{background: var(--wp--preset--gradient--young-passion) !important;}.has-true-sunset-gradient-background{background: var(--wp--preset--gradient--true-sunset) !important;}.has-morpheus-den-gradient-background{background: var(--wp--preset--gradient--morpheus-den) !important;}.has-plum-plate-gradient-background{background: var(--wp--preset--gradient--plum-plate) !important;}.has-aqua-splash-gradient-background{background: var(--wp--preset--gradient--aqua-splash) !important;}.has-love-kiss-gradient-background{background: var(--wp--preset--gradient--love-kiss) !important;}.has-new-retrowave-gradient-background{background: var(--wp--preset--gradient--new-retrowave) !important;}.has-plum-bath-gradient-background{background: var(--wp--preset--gradient--plum-bath) !important;}.has-high-flight-gradient-background{background: var(--wp--preset--gradient--high-flight) !important;}.has-teen-party-gradient-background{background: var(--wp--preset--gradient--teen-party) !important;}.has-fabled-sunset-gradient-background{background: var(--wp--preset--gradient--fabled-sunset) !important;}.has-arielle-smile-gradient-background{background: var(--wp--preset--gradient--arielle-smile) !important;}.has-itmeo-branding-gradient-background{background: var(--wp--preset--gradient--itmeo-branding) !important;}.has-deep-blue-gradient-background{background: var(--wp--preset--gradient--deep-blue) !important;}.has-strong-bliss-gradient-background{background: var(--wp--preset--gradient--strong-bliss) !important;}.has-sweet-period-gradient-background{background: var(--wp--preset--gradient--sweet-period) !important;}.has-purple-division-gradient-background{background: var(--wp--preset--gradient--purple-division) !important;}.has-cold-evening-gradient-background{background: var(--wp--preset--gradient--cold-evening) !important;}.has-mountain-rock-gradient-background{background: var(--wp--preset--gradient--mountain-rock) !important;}.has-desert-hump-gradient-background{background: var(--wp--preset--gradient--desert-hump) !important;}.has-ethernal-constance-gradient-background{background: var(--wp--preset--gradient--ethernal-constance) !important;}.has-happy-memories-gradient-background{background: var(--wp--preset--gradient--happy-memories) !important;}.has-grown-early-gradient-background{background: var(--wp--preset--gradient--grown-early) !important;}.has-morning-salad-gradient-background{background: var(--wp--preset--gradient--morning-salad) !important;}.has-night-call-gradient-background{background: var(--wp--preset--gradient--night-call) !important;}.has-mind-crawl-gradient-background{background: var(--wp--preset--gradient--mind-crawl) !important;}.has-angel-care-gradient-background{background: var(--wp--preset--gradient--angel-care) !important;}.has-juicy-cake-gradient-background{background: var(--wp--preset--gradient--juicy-cake) !important;}.has-rich-metal-gradient-background{background: var(--wp--preset--gradient--rich-metal) !important;}.has-mole-hall-gradient-background{background: var(--wp--preset--gradient--mole-hall) !important;}.has-cloudy-knoxville-gradient-background{background: var(--wp--preset--gradient--cloudy-knoxville) !important;}.has-soft-grass-gradient-background{background: var(--wp--preset--gradient--soft-grass) !important;}.has-saint-petersburg-gradient-background{background: var(--wp--preset--gradient--saint-petersburg) !important;}.has-everlasting-sky-gradient-background{background: var(--wp--preset--gradient--everlasting-sky) !important;}.has-kind-steel-gradient-background{background: var(--wp--preset--gradient--kind-steel) !important;}.has-over-sun-gradient-background{background: var(--wp--preset--gradient--over-sun) !important;}.has-premium-white-gradient-background{background: var(--wp--preset--gradient--premium-white) !important;}.has-clean-mirror-gradient-background{background: var(--wp--preset--gradient--clean-mirror) !important;}.has-wild-apple-gradient-background{background: var(--wp--preset--gradient--wild-apple) !important;}.has-snow-again-gradient-background{background: var(--wp--preset--gradient--snow-again) !important;}.has-confident-cloud-gradient-background{background: var(--wp--preset--gradient--confident-cloud) !important;}.has-glass-water-gradient-background{background: var(--wp--preset--gradient--glass-water) !important;}.has-perfect-white-gradient-background{background: var(--wp--preset--gradient--perfect-white) !important;}.has-small-font-size{font-size: var(--wp--preset--font-size--small) !important;}.has-medium-font-size{font-size: var(--wp--preset--font-size--medium) !important;}.has-large-font-size{font-size: var(--wp--preset--font-size--large) !important;}.has-x-large-font-size{font-size: var(--wp--preset--font-size--x-large) !important;}.has-larger-font-size{font-size: var(--wp--preset--font-size--larger) !important;}.has-xxlarge-font-size{font-size: var(--wp--preset--font-size--xxlarge) !important;}',
                'isGlobalStyles' => true
            ],
            [
                '__unstableType' => 'theme',
                'css'            => ':root { --wp--style--global--content-size: var(--theme-block-max-width);--wp--style--global--wide-size: var(--theme-block-wide-max-width); }:where(body) { margin: 0; }.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }.wp-site-blocks > .alignright { float: right; margin-left: 2em; }.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }:where(.wp-site-blocks) > * { margin-block-start: var(--theme-content-spacing); margin-block-end: 0; }:where(.wp-site-blocks) > :first-child { margin-block-start: 0; }:where(.wp-site-blocks) > :last-child { margin-block-end: 0; }:root { --wp--style--block-gap: var(--theme-content-spacing); }:root :where(.is-layout-flow) > :first-child{margin-block-start: 0;}:root :where(.is-layout-flow) > :last-child{margin-block-end: 0;}:root :where(.is-layout-flow) > *{margin-block-start: var(--theme-content-spacing);margin-block-end: 0;}:root :where(.is-layout-constrained) > :first-child{margin-block-start: 0;}:root :where(.is-layout-constrained) > :last-child{margin-block-end: 0;}:root :where(.is-layout-constrained) > *{margin-block-start: var(--theme-content-spacing);margin-block-end: 0;}:root :where(.is-layout-flex){gap: var(--theme-content-spacing);}:root :where(.is-layout-grid){gap: var(--theme-content-spacing);}.is-layout-flow > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}.is-layout-flow > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}.is-layout-flow > .aligncenter{margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > .alignleft{float: left;margin-inline-start: 0;margin-inline-end: 2em;}.is-layout-constrained > .alignright{float: right;margin-inline-start: 2em;margin-inline-end: 0;}.is-layout-constrained > .aligncenter{margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)){max-width: var(--wp--style--global--content-size);margin-left: auto !important;margin-right: auto !important;}.is-layout-constrained > .alignwide{max-width: var(--wp--style--global--wide-size);}body .is-layout-flex{display: flex;}.is-layout-flex{flex-wrap: wrap;align-items: center;}.is-layout-flex > :is(*, div){margin: 0;}body .is-layout-grid{display: grid;}.is-layout-grid > :is(*, div){margin: 0;}body{padding-top: 0px;padding-right: 0px;padding-bottom: 0px;padding-left: 0px;}',
                'isGlobalStyles' => true
            ],
            [
                '__unstableType' => 'user',
                'css'            => " :root{--theme-block-max-width: 700px;--global-calc-content-width: 700px;--theme-block-wide-max-width: 820px;--theme-font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\";--theme-font-weight: 400;--theme-text-transform: none;--theme-text-decoration: none;--theme-font-size: 16px;--theme-line-height: 1.60;--theme-letter-spacing: 0em;--theme-button-font-weight: 500;--theme-button-font-size: 16px;--theme-palette-color-1: #4F46E5;--theme-palette-color-2: #7C3AED;--theme-palette-color-3: #1F2937;--theme-palette-color-4: #374151;--theme-palette-color-5: #6B7280;--theme-palette-color-6: #9CA3AF;--theme-palette-color-7: #E5E7EB;--theme-palette-color-8: #ffffff;--theme-text-color: var(--fcom-primary-text, #19283a);--theme-link-initial-color: var(--theme-palette-color-1);--theme-link-hover-color: var(--theme-palette-color-2);--theme-selection-text-color: #ffffff;--theme-selection-background-color: var(--theme-palette-color-1);--theme-border-color: var(--theme-palette-color-5);--theme-headings-color: var(--theme-palette-color-4);--theme-content-spacing: 1.5em;--theme-button-min-height: 40px;--theme-button-shadow: none;--theme-button-transform: none;--theme-button-text-initial-color: #ffffff;--theme-button-text-hover-color: #ffffff;--theme-button-background-initial-color: var(--theme-palette-color-1);--theme-button-background-hover-color: var(--theme-palette-color-2);--theme-button-border: none;--theme-button-padding: 5px 20px;--theme-normal-container-max-width: 1290px;--theme-content-vertical-spacing: 60px;--theme-container-edge-spacing: 90vw;--theme-narrow-container-max-width: 750px;--theme-wide-offset: 130px;--fcom-font-size-small: 16px;--fcom-font-size-medium: 18px;--fcom-font-size-large: 22px;--fcom-font-size-larger: 26px;--fcom-font-size-xxlarge: 32px;--wp--preset--spacing--20: 0.44rem;--wp--preset--spacing--30: 0.67rem;--wp--preset--spacing--40: 1rem;--wp--preset--spacing--50: 1.5rem;--wp--preset--spacing--60: 2.25rem;--wp--preset--spacing--70: 3.38rem;--wp--preset--spacing--80: 5.06rem}body .has-theme-palette-color-1-color{color:var(--theme-palette-color-1)}body .has-theme-palette-color-2-color{color:var(--theme-palette-color-2)}body .has-theme-palette-color-3-color{color:var(--theme-palette-color-3)}body .has-theme-palette-color-4-color{color:var(--theme-palette-color-4)}body .has-theme-palette-color-5-color{color:var(--theme-palette-color-5)}body .has-theme-palette-color-6-color{color:var(--theme-palette-color-6)}body .has-theme-palette-color-7-color{color:var(--theme-palette-color-7)}body .has-theme-palette-color-8-color{color:var(--theme-palette-color-8)}body .has-theme-palette-color-1-background-color{background-color:var(--theme-palette-color-1)}body .has-theme-palette-color-2-background-color{background-color:var(--theme-palette-color-2)}body .has-theme-palette-color-3-background-color{background-color:var(--theme-palette-color-3)}body .has-theme-palette-color-4-background-color{background-color:var(--theme-palette-color-4)}body .has-theme-palette-color-5-background-color{background-color:var(--theme-palette-color-5)}body .has-theme-palette-color-6-background-color{background-color:var(--theme-palette-color-6)}body .has-theme-palette-color-7-background-color{background-color:var(--theme-palette-color-7)}body .has-theme-palette-color-8-background-color{background-color:var(--theme-palette-color-8)}body .has-small-font-size{font-size:var(--fcom-font-size-small)}body .has-medium-font-size{font-size:var(--fcom-font-size-medium)}body .has-large-font-size{font-size:var(--fcom-font-size-large)}body .has-larger-font-size{font-size:var(--fcom-font-size-larger)}body .has-xxlarge-font-size{font-size:var(--fcom-font-size-xxlarge)}body .is-root-container>.alignfull{margin-inline:var(--has-wide, -20px)}body .is-root-container>.wp-block.alignleft{margin-inline-start:calc((100% - min(var(--theme-block-max-width),100%))/2)}body .is-root-container>.wp-block.alignright{margin-inline-end:calc((100% - min(var(--theme-block-max-width),100%))/2)}body :root .wp-element-button{font-family:var(--theme-button-font-family, var(--theme-font-family));font-size:var(--theme-button-font-size);font-weight:var(--theme-button-font-weight);font-style:var(--theme-button-font-style);line-height:var(--theme-button-line-height);letter-spacing:var(--theme-button-letter-spacing);text-transform:var(--theme-button-text-transform);-webkit-text-decoration:var(--theme-button-text-decoration);text-decoration:var(--theme-button-text-decoration)}body :root .wp-block-button[style*=font-weight] .wp-element-button{font-weight:inherit}body .wp-block-columns:last-child{margin-bottom:0}body .has-drop-cap:not(:focus):first-letter{font-size:5.8em;font-weight:700;margin:.1em .12em .05em 0}body figcaption{text-align:center;margin-block:.5em 0}body .wp-block-code,body .wp-block-verse,body .wp-block-preformatted{box-sizing:border-box;tab-size:4;padding:15px 20px;border-radius:3px;background:var(--theme-palette-color-7)}body blockquote{margin-inline:0}body blockquote:where(:not(.is-style-plain)):where(:not(.has-text-align-center):not(.has-text-align-right)){border-inline-start:4px solid var(--theme-palette-color-1)}body blockquote:where(:not(.is-style-plain)).has-text-align-center{padding-block:1.5em;border-block:3px solid var(--theme-palette-color-1)}body blockquote:where(:not(.is-style-plain)).has-text-align-right{border-inline-end:4px solid var(--theme-palette-color-1)}body blockquote:where(:not(.is-style-plain):not(.has-text-align-center):not(.has-text-align-right)){padding-inline-start:1.5em}body blockquote.has-text-align-right{padding-inline-end:1.5em}body blockquote p:last-child{margin-bottom:0}body blockquote cite{font-size:14px}body .wp-block-list{padding-left:30px}body .wp-block-pullquote{position:relative;padding:70px;text-align:initial;border-width:10px;border-style:solid;border-color:var(--theme-palette-color-1)}body .wp-block-pullquote blockquote{border:0;padding:0;margin:0;position:relative;isolation:isolate}body .wp-block-pullquote blockquote p{margin-top:0;margin-bottom:1em}body .wp-block-pullquote blockquote p:last-child{margin-bottom:0}body .wp-block-pullquote blockquote cite{font-size:16px;font-weight:500}body [data-align=left] .wp-block-pullquote,body [data-align=right] .wp-block-pullquote{max-width:50%;margin-top:.3em;margin-bottom:.3em}body .wp-block-table table{border-width:1px}body .wp-block-table table:not(.has-border-color) thead,body .wp-block-table table:not(.has-border-color) tfoot,body .wp-block-table table:not(.has-border-color) td,body .wp-block-table table:not(.has-border-color) th{border-color:var(--theme-table-border-color, var(--theme-border-color))}body .wp-block-table th:not([class*=has-text-align]){text-align:inherit}body .wp-block-table.is-style-stripes{border:0}body .wp-block-button.is-style-outline .wp-element-button{padding:var(--theme-button-padding);border:2px solid;border-color:var(--theme-button-background-initial-color)}body .wp-block-button.is-style-outline .wp-element-button:not(.has-text-color){color:var(--theme-button-background-initial-color)}body .wp-block-button.is-style-outline .wp-element-button:hover{color:var(--theme-button-text-hover-color);border-color:var(--theme-button-background-hover-color);background-color:var(--theme-button-background-hover-color)}body .wp-block-separator{border:none;margin-inline:auto;color:var(--theme-form-field-border-initial-color)}body .wp-block-separator:not(:where(.is-style-wide,.is-style-dots,.alignfull,.alignwide)){max-width:100px !important}body .wp-block-separator:not(.is-style-dots){height:2px;background-color:currentColor}body :root :where(p.has-background,.wp-block-group.has-background){padding:30px;box-sizing:border-box}body h1.has-background,body h2.has-background,body h3.has-background,body h4.has-background,body h5.has-background,body h6.has-background{padding:1.25em 2.375em}body{background-color:var(--fcom-primary-bg, white);background-image:none;font-family:var(--theme-font-family);line-height:var(--theme-line-height)}body .is-root-container{font-size:var(--theme-font-size, 16px);font-family:var(--theme-font-family, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\")}.block-editor-iframe__html.is-zoomed-out .block-editor-iframe__body{padding:20px 30px}.editor-visual-editor__post-title-wrapper.edit-post-visual-editor__post-title-wrapper{margin-top:0px !important;padding-top:0;margin-bottom:30px;position:relative}.editor-visual-editor__post-title-wrapper.edit-post-visual-editor__post-title-wrapper h1{font-size:32px;font-weight:700}h1{--theme-font-weight: 700;--theme-font-size: 40px;--theme-line-height: 1.5}h2{--theme-font-weight: 700;--theme-font-size: 35px;--theme-line-height: 1.5}h3{--theme-font-weight: 700;--theme-font-size: 30px;--theme-line-height: 1.5}h4{--theme-font-weight: 700;--theme-font-size: 25px;--theme-line-height: 1.5}h5{--theme-font-weight: 700;--theme-font-size: 20px;--theme-line-height: 1.5}h6{--theme-font-weight: 700;--theme-font-size: 16px;--theme-line-height: 1.5}.wp-block-pullquote{--theme-font-family: Georgia;--theme-font-weight: 600;--theme-font-size: 25px}pre,code,samp,kbd{--theme-font-family: monospace;--theme-font-weight: 400;--theme-font-size: 16px}figcaption{--theme-font-size: 14px}li::marker{color:#959595}.editor-styles-wrapper{--true: initial;--false: ;--wp--style--global--content-size: var(--theme-block-max-width);--wp--style--global--wide-size: var(--theme-block-wide-max-width);box-sizing:border-box;border:var(--has-boxed, var(--theme-boxed-content-border));padding:var(--has-boxed, var(--theme-boxed-content-spacing));box-shadow:var(--has-boxed, var(--theme-boxed-content-box-shadow));border-radius:var(--has-boxed, var(--theme-boxed-content-border-radius));margin-inline:auto;margin-block:var(--has-boxed, 20px);width:calc(100% - 40px);max-width:100%}:is(.is-layout-flow,.is-layout-constrained)>*:where(:not(h1,h2,h3,h4,h5,h6)){margin-block-start:0;margin-block-end:var(--theme-content-spacing)}:is(.is-layout-flow,.is-layout-constrained) :where(h1,h2,h3,h4,h5,h6){margin-block-end:calc(var(--has-theme-content-spacing, 1)*(.3em + 10px))}:root{color:var(--theme-text-color)}a{color:var(--theme-link-initial-color)}.block-editor-block-list__layout.is-root-container>.alignwide{max-width:var(--theme-block-wide-max-width);box-sizing:border-box}.is-root-container{padding:0 20px}\n"
            ],
            [
                'css'            => file_exists(FLUENTCRM_PLUGIN_PATH . 'assets/guten-editor/index.css')
                    ? file_get_contents(FLUENTCRM_PLUGIN_PATH . 'assets/guten-editor/index.css')
                    : '',
                '__unstableType' => 'user'
            ],
            [
                'css'            => $dynamicCss,
                '__unstableType' => 'user'
            ]
        ];
    }

    /**
     * Return the `__unstableResolvedAssets` for the editor settings.
     *
     * @return array
     */
    private function getResolvedAssets()
    {
        $resolvedStyles = [
            'wp-components-css'           => includes_url('/css/dist/components/style.min.css'),
            'wp-preferences-css'          => includes_url('/css/dist/preferences/style.min.css'),
            'wp-block-editor-css'         => includes_url('/css/dist/block-editor/style.min.css'),
            'wp-reusable-blocks-css'      => includes_url('/css/dist/reusable-blocks/style.min.css'),
            'wp-patterns-css'             => includes_url('/css/dist/patterns/style.min.css'),
            'wp-editor-css'               => includes_url('/css/dist/editor/style.min.css'),
            'wp-block-library-css'        => includes_url('/css/dist/block-library/style.min.css'),
            'wp-block-editor-content-css' => includes_url('/css/dist/block-editor/content.min.css'),
            'wp-edit-blocks-css'          => includes_url('/css/dist/block-library/editor.min.css'),
        ];

        global $wp_version;
        $cssFiles = '';
        foreach ($resolvedStyles as $name => $file) {
            $cssFiles .= "<link rel='stylesheet' id='{$name}' href='{$file}?ver={$wp_version}' media='all' />\n";
        }

        return [
            'scripts' => '<script src="' . includes_url('/js/dist/vendor/wp-polyfill.min.js?ver=3.15.0') . '" id="wp-polyfill-js"></script>',
            'styles'  => $cssFiles
        ];
    }

    /**
     * Return the `defaultEditorStyles` for the editor settings.
     *
     * @return array
     */
    private function getDefaultEditorStyles()
    {
        return [
            [
                'css' => ':root{--wp-admin-theme-color:#007cba;--wp-admin-theme-color--rgb:0, 124, 186;--wp-admin-theme-color-darker-10:#006ba1;--wp-admin-theme-color-darker-10--rgb:0, 107, 161;--wp-admin-theme-color-darker-20:#005a87;--wp-admin-theme-color-darker-20--rgb:0, 90, 135;--wp-admin-border-width-focus:2px;--wp-block-synced-color:#7a00df;--wp-block-synced-color--rgb:122, 0, 223;--wp-bound-block-color:var(--wp-block-synced-color);}@media (min-resolution:192dpi){:root{--wp-admin-border-width-focus:1.5px;}}body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Oxygen-Sans,Ubuntu,Cantarell,Helvetica Neue,sans-serif;font-size:18px;line-height:1.5;--wp--style--block-gap:2em;}p{line-height:1.8;}.editor-post-title__block{font-size:2.5em;font-weight:800;margin-bottom:1em;margin-top:2em;}'
            ]
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Render: renderPage()
    |--------------------------------------------------------------------------
    */

    protected function renderPage()
    {
        add_action('fluent_crm/new_block_editor_footer', function () {
            wp_underscore_playlist_templates();
            if (function_exists('wp_script_modules') && method_exists(wp_script_modules(), 'print_import_map')) {
                wp_script_modules()->print_import_map();
            }
            wp_print_footer_scripts();
            wp_print_media_templates();
        });

        add_action('fluent_crm_block_editor/head', 'wp_enqueue_scripts', 1);
        add_action('fluent_crm_block_editor/head', 'wp_resource_hints', 2);
        add_action('fluent_crm_block_editor/head', 'wp_preload_resources', 1);
        add_action('fluent_crm_block_editor/head', 'wp_print_styles', 8);
        add_action('fluent_crm_block_editor/head', 'wp_print_head_scripts', 9);

        $this->unloadOtherScripts();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <title>FluentCRM Block Editor</title>
            <meta charset='utf-8'>
            <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=0,viewport-fit=cover"/>
            <meta name="mobile-web-app-capable" content="yes">
            <meta name="robots" content="noindex">
            <?php do_action('fluent_crm_block_editor/head'); ?>
            <?php do_action('fluent_crm/block_editor_head'); ?>
        </head>
        <body class="fcrm_custom_editor">
        <div class="wp-site-blocks">
            <div id="editor" class="gutenberg__editor"></div>
        </div>
        <?php
        do_action('fluent_crm/new_block_editor_footer');
        ?>
        </body>
        </html>
        <?php
    }

    /*
    |--------------------------------------------------------------------------
    | Isolation: unloadOtherScripts(), buildApprovedSlugsPattern(),
    |            unregisterDefaultBlockPatterns()
    |--------------------------------------------------------------------------
    */

    private function unloadOtherScripts()
    {
        $isSkip = apply_filters('fluent_crm_editor/skip_no_conflict', false);
        if ($isSkip) {
            return;
        }

        $approvedSlugsPattern = $this->buildApprovedSlugsPattern();

        $pluginUrl = str_replace(['http:', 'https:'], '', plugins_url());
        $themesUrl = str_replace(['http:', 'https:'], '', get_theme_root_uri());

        add_filter('script_loader_src', function ($src, $handle) use ($approvedSlugsPattern, $pluginUrl, $themesUrl) {
            if (!$src) {
                return $src;
            }

            if ($this->isThirdPartyAsset($src, $approvedSlugsPattern, $pluginUrl, $themesUrl)) {
                return false;
            }

            return $src;
        }, 1, 2);

        add_filter('style_loader_src', function ($src, $handle) use ($approvedSlugsPattern, $pluginUrl, $themesUrl) {
            if (!$src) {
                return $src;
            }

            if ($this->isThirdPartyAsset($src, $approvedSlugsPattern, $pluginUrl, $themesUrl)) {
                return false;
            }

            return $src;
        }, 1, 2);

        add_action('wp_print_scripts', function () use ($approvedSlugsPattern, $pluginUrl, $themesUrl) {
            global $wp_scripts;
            if (!$wp_scripts) {
                return;
            }

            foreach ($wp_scripts->queue as $script) {
                if (empty($wp_scripts->registered[$script]) || empty($wp_scripts->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_scripts->registered[$script]->src;

                if (!$this->isThirdPartyAsset($src, $approvedSlugsPattern, $pluginUrl, $themesUrl)) {
                    continue;
                }

                wp_dequeue_script($wp_scripts->registered[$script]->handle);
            }
        }, 1);

        add_action('wp_print_styles', function () {
            $isSkip = apply_filters('fluent_crm_editor/skip_no_conflict', false, 'styles');

            if ($isSkip) {
                return;
            }

            // Dequeue theme.json global styles that aren't caught by URL-based filtering
            wp_dequeue_style('global-styles');
            wp_dequeue_style('global-styles-css-custom-properties');

            global $wp_styles;
            if (!$wp_styles) {
                return;
            }

            $approvedSlugs = apply_filters('fluent_crm_editor/asset_listed_slugs', [
                '\/gutenberg\/',
            ]);

            $approvedSlugs[] = '\/fluent-crm\/';

            $approvedSlugs = array_unique($approvedSlugs);
            $approvedSlugs = implode('|', $approvedSlugs);

            $pluginUrl = plugins_url();
            $themeUrl = get_theme_root_uri();

            $pluginUrl = str_replace(['http:', 'https:'], '', $pluginUrl);
            $themeUrl = str_replace(['http:', 'https:'], '', $themeUrl);

            foreach ($wp_styles->queue as $script) {

                if (empty($wp_styles->registered[$script]) || empty($wp_styles->registered[$script]->src)) {
                    continue;
                }

                $src = $wp_styles->registered[$script]->src;
                $pluginMatched = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);
                $themeMatched = (strpos($src, $themeUrl) !== false) && !preg_match('/' . $approvedSlugs . '/', $src);

                if (!$pluginMatched && !$themeMatched) {
                    continue;
                }

                wp_dequeue_style($wp_styles->registered[$script]->handle);
            }
        }, 999999);
    }

    /**
     * Build the regex pattern string from approved asset slugs.
     *
     * @return string
     */
    private function buildApprovedSlugsPattern()
    {
        /**
         * Define the list of approved slugs for FluentCRM assets.
         *
         * This filter allows modification of the list of slugs that are approved for FluentCRM assets.
         *
         * @param array $approvedSlugs An array of approved slugs for FluentCRM assets.
         */
        $approvedSlugs = apply_filters('fluent_crm_editor/asset_listed_slugs', [
            '\/gutenberg\/',
        ]);
        $approvedSlugs[] = 'fluent-crm';
        $approvedSlugs = array_unique($approvedSlugs);

        return implode('|', $approvedSlugs);
    }

    /**
     * Check if an asset src is a third-party plugin/theme asset that should be blocked.
     *
     * @param string $src
     * @param string $pattern
     * @param string $pluginUrl
     * @param string $themesUrl
     * @return bool
     */
    private function isThirdPartyAsset($src, $pattern, $pluginUrl, $themesUrl)
    {
        $pluginMatched = (strpos($src, $pluginUrl) !== false) && !preg_match('/' . $pattern . '/', $src);
        if ($pluginMatched) {
            return true;
        }

        $themeMatched = (strpos($src, $themesUrl) !== false) && !preg_match('/' . $pattern . '/', $src);
        if ($themeMatched) {
            return true;
        }

        return false;
    }

    protected function unregisterDefaultBlockPatterns($context = '', $data = [])
    {
        // Only affect FluentCRM iframe editor requests, never global wp-admin editors.
        if (!isset($_REQUEST['fluent_crm_block_editor'])) {
            return;
        }

        $shouldUnregister = (bool)apply_filters('fluent_crm/block_editor_unregister_all_patterns', true, $context, $data);
        if (!$shouldUnregister) {
            return;
        }

        // Prevent late core/theme pattern registration hooks from repopulating defaults.
        foreach ([
            '_register_core_block_patterns_and_categories',
            '_register_theme_block_patterns',
            '_register_remote_theme_patterns'
        ] as $callback) {
            remove_action('init', $callback, 9);
            remove_action('init', $callback, 10);
        }

        add_filter('should_load_remote_block_patterns', '__return_false', 999);
        remove_theme_support('core-block-patterns');

        if (class_exists('\WP_Block_Patterns_Registry')) {
            $registry = \WP_Block_Patterns_Registry::get_instance();
            $patterns = method_exists($registry, 'get_all_registered') ? $registry->get_all_registered() : [];
            foreach (array_keys($patterns) as $patternName) {
                if (method_exists($registry, 'unregister')) {
                    $registry->is_registered($patternName) && $registry->unregister($patternName);
                }
            }
        }

        if (class_exists('\WP_Block_Pattern_Categories_Registry')) {
            $categoryRegistry = \WP_Block_Pattern_Categories_Registry::get_instance();
            $categories = method_exists($categoryRegistry, 'get_all_registered') ? $categoryRegistry->get_all_registered() : [];
            foreach (array_keys($categories) as $categoryName) {
                if (method_exists($categoryRegistry, 'unregister')) {
                    $categoryRegistry->is_registered($categoryName) && $categoryRegistry->unregister($categoryName);
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers: getRequiredCapability(), parseBoolParam(), getAvailableImageSizes()
    |--------------------------------------------------------------------------
    */

    /**
     * Map a block type to the required FluentCRM capability.
     *
     * @param string $blockType
     * @return string
     */
    private static function getRequiredCapability($blockType)
    {
        if (in_array($blockType, ['campaign', 'email_body_in_funnel', 'recurring_campaign', 'recurring_mail', 'sequence_mail'], true)) {
            return 'fcrm_manage_emails';
        }
        if ($blockType === 'template') {
            return 'fcrm_manage_email_templates';
        }
        return 'fcrm_read_emails';
    }

    /**
     * Parse a loosely-typed boolean parameter from a request value.
     *
     * @param mixed $value
     * @return bool
     */
    private static function parseBoolParam($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return ((int)$value) === 1;
        }
        $value = strtolower(trim((string)$value));
        if ($value === '1' || $value === 'true' || $value === 'yes' || $value === 'on') {
            return true;
        }
        if ($value === '0' || $value === 'false' || $value === 'no' || $value === 'off') {
            return false;
        }
        return false;
    }

    /**
     * Get available image sizes for the editor.
     *
     * @return array
     */
    private function getAvailableImageSizes()
    {
        $size_names = apply_filters(
            'image_size_names_choose',
            array(
                'thumbnail' => __('Thumbnail', 'fluent-crm'),
                'medium'    => __('Medium', 'fluent-crm'),
                'large'     => __('Large', 'fluent-crm'),
                'full'      => __('Full Size', 'fluent-crm'),
            )
        );
        $all_sizes = array();
        foreach ($size_names as $size_slug => $size_name) {
            $all_sizes[] = array(
                'slug' => $size_slug,
                'name' => $size_name,
            );
        }
        return $all_sizes;
    }
}
