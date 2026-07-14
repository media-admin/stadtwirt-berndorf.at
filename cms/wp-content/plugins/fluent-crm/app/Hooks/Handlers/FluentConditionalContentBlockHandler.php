<?php

namespace FluentCrm\App\Hooks\Handlers;

use FluentCrm\App\Models\Tag;

class FluentConditionalContentBlockHandler
{

    const BLOCK_NAME = 'fluent-crm/conditional-content';

    const DEFAULT_CONDITION = 'show_if_tag_exist';

    /**
     * Legacy condition keys kept for backward compatibility.
     */
    private $legacyMap = [
        'show_if_logged_in'      => 'show_if_user_logged_in',
        'show_if_public_users'   => 'show_if_user_not_logged_in',
        'show_if_tag_exists'     => 'show_if_tag_exist',
        'show_if_tag_not_exists' => 'show_if_tag_not_exist',
    ];

    public function register()
    {
        add_action('init', [$this, 'registerBlock']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueEditorAssets']);
    }

    /**
     * Register the block with a render callback.
     * The JS save() still writes HTML into post_content for storage,
     * but the render_callback fully controls frontend output.
     */
    public function registerBlock()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(self::BLOCK_NAME, [
            'api_version'     => 3,
            'editor_script'   => 'fluent-crm-conditional-content-block',
            'attributes'      => [
                'condition_type' => [
                    'type'    => 'string',
                    'default' => self::DEFAULT_CONDITION,
                ],
                'tag_ids'        => [
                    'type'    => 'array',
                    'default' => [],
                ],
            ],
            'supports'        => [
                'align'  => ['wide', 'full'],
                'anchor' => true,
                'html'   => false,
            ],
            'render_callback' => [$this, 'renderBlock'],
        ]);
    }


    public function enqueueEditorAssets()
    {
        // The iframe editor already registers its own conditional block implementation.
        if (isset($_REQUEST['fluent_crm_block_editor'])) {
            return;
        }

        $handle = 'fluent-crm-conditional-content-block';

        wp_register_script(
            $handle,
            fluentCrmMix('public/conditional-content-block.js'),
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'],
            FLUENTCRM_PLUGIN_VERSION,
            true
        );

        $tags = Tag::select(['id', 'title'])->orderBy('title', 'ASC')->get();

        wp_localize_script($handle, 'fcrmConditionalContentConfig', [
            'hasPro' => defined('FLUENTCAMPAIGN'),
            'tags'   => $tags
        ]);

        wp_set_script_translations($handle, 'fluent-crm');
    }


    /**
     * Render callback. Receives block attributes, the rendered inner blocks
     * as $content, and the WP_Block instance.
     *
     * @param array $attributes
     * @param string $content Inner blocks already rendered.
     * @param \WP_Block $block
     * @return string
     */
    public function renderBlock($attributes, $content, $block)
    {
        if (!$this->passesCondition($attributes)) {
            return '';
        }

        // no inner blocks placed at all — nothing to render.
        // Checking $block->inner_blocks (parsed block data) is the authoritative source of truth
        // and avoids inspecting the rendered HTML string, which loses semantic information.
        if (count($block->inner_blocks) === 0) {
            return '';
        }

        // inner blocks exist but all rendered to nothing — for example a Query Loop
        // with no results, a dynamic block gated by its own conditions, or a plugin-restricted
        // block. Avoids outputting an empty wrapper div in those cases.
        // trim() on the raw HTML string is intentional: any real element (iframe, video, image,
        // paragraph, etc.) produces a non-empty string. wp_strip_all_tags() is deliberately
        // avoided here because it removes HTML tags and would incorrectly treat media-only
        // content (iframes, videos, images) as empty.
        if (trim($content) === '') {
            return '';
        }

        $wrapperAttributes = get_block_wrapper_attributes([
            'class' => 'fc-cond-section',
        ]);

        return sprintf(
            '<div %1$s><div class="fc-cond-blocks">%2$s</div></div>',
            $wrapperAttributes,
            $content
        );
    }

    /**
     * Decide whether the current visitor passes the condition.
     */
    private function passesCondition($attrs)
    {
        $condition = $this->normalizeConditionType(
            isset($attrs['condition_type']) ? $attrs['condition_type'] : self::DEFAULT_CONDITION
        );

        $tagIds = isset($attrs['tag_ids']) && is_array($attrs['tag_ids'])
            ? array_values(array_filter(array_map('intval', $attrs['tag_ids'])))
            : [];

        switch ($condition) {
            case 'show_if_user_logged_in':
                return is_user_logged_in();

            case 'show_if_user_not_logged_in':
                return !is_user_logged_in();

            case 'show_if_tag_exist':
                if (empty($tagIds)) {
                    return false;
                }
                return $this->contactHasAnyTag($tagIds);

            case 'show_if_tag_not_exist':
                if (empty($tagIds)) {
                    return true;
                }
                return !$this->contactHasAnyTag($tagIds);
        }

        return false;
    }

    /**
     * Check if the current contact has any of the given tag IDs.
     */
    private function contactHasAnyTag(array $tagIds)
    {
        $contact = $this->getCurrentContact();

        if (!$contact) {
            return false;
        }

        return $contact->hasAnyTagId($tagIds);
    }

    /**
     * Resolve the current contact once per request.
     */
    private function getCurrentContact()
    {
        static $resolved = null;
        static $cached = false;

        if ($cached) {
            return $resolved;
        }

        $cached = true;

        $contact = fluentcrm_get_current_contact();

        if ($contact) {
            $resolved = $contact->load('tags');
        }

        return $resolved;
    }

    /**
     * Map legacy keys to current condition keys, mirroring the JS side.
     */
    private function normalizeConditionType($value)
    {
        $value = trim((string)$value);

        if (!$value) {
            return self::DEFAULT_CONDITION;
        }

        return isset($this->legacyMap[$value]) ? $this->legacyMap[$value] : $value;
    }

}
