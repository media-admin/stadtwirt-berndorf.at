<?php

defined('ABSPATH') or die;

use FluentForm\Framework\Helpers\ArrayHelper;
use FluentForm\App\Modules\Component\BaseComponent;
use FluentForm\App\Services\FormBuilder\EditorShortCode;

/**
 ***** DO NOT CALL ANY FUNCTIONS DIRECTLY FROM THIS FILE ******
 *
 * This file will be loaded even before the framework is loaded
 * so the $app is not available here, only declare functions here.
 */

//if ('dev' == $app->config->get('app.env')) {
//    $globalsDevFile = __DIR__ . '/globals_dev.php';
//
//    is_readable($globalsDevFile) && include $globalsDevFile;
//}

if (!function_exists('dd')) {
    // function dd()
    // {
    //     foreach (func_get_args() as $arg) {
    //         echo '<pre>';
    //         print_r($arg); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $value is only used for debugging in development.
    //         echo '</pre>';
    //     }
    //     exit();
    // }
}

/**
 * Get fluentform instance or other core modules
 *
 * @param string $key
 *
 * @return mixed
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Global helper function, part of plugin API
function wpFluentForm($key = null)
{
    return \FluentForm\App\App::make($key);
}

/**
 * Generate URL for static assets
 *
 * @param string $path
 *
 * @return string
 */
function fluentFormMix($path = '')
{
    return wpFluentForm('url.assets') . ltrim($path, '/');
}

if (! function_exists('wpFluent')) {
    /**
     * @return \FluentForm\Framework\Database\Query\Builder|\FluentForm\Framework\Database\Query\WPDBConnection
     */
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Global helper function, part of plugin API
    function wpFluent()
    {
        return wpFluentForm('db');
    }
}


// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Global helper function, part of plugin API
function wpFluentFormAddComponent(BaseComponent $component)
{
    return $component->_init();
}

/**
 * Sanitize form inputs recursively.
 *
 * @param $input
 *
 * @return mixed $input
 */
function fluentFormSanitizer($input, $attribute = null, $fields = [])
{
    if (is_string($input)) {
        $element = ArrayHelper::get($fields, $attribute . '.element');

        if (in_array($element, ['post_content', 'rich_text_input'])) {
            return wp_kses_post($input);
        } elseif ('textarea' === $element) {
            $input = sanitize_textarea_field($input);
        } elseif ('input_email' === $element) {
            $input = strtolower(sanitize_text_field($input));
        } elseif ('input_url' === $element) {
            $input = sanitize_url($input);
        } elseif ('input_password' === $element) {
            $input = trim($input);
        } else {
            $input = sanitize_text_field($input);
        }
    } elseif (is_array($input)) {
        $sanitizedInput = [];
        
        foreach ($input as $key => &$value) {
            $key = fluentFormSanitizer($key);
            $attribute = $attribute ? $attribute . '[' . $key . ']' : $key;

            $value = fluentFormSanitizer($value, $attribute, $fields);
            $attribute = null;
            $sanitizedInput[$key] = $value;
        }
        
        $input = $sanitizedInput;
    }

    return $input;
}

function fluentFormEditorShortCodes()
{
    $generalShortCodes = [EditorShortCode::getGeneralShortCodes()];
    /* This filter is  deprecated, will be removed soon. */
    $generalShortCodes = apply_filters('fluentform_editor_shortcodes', $generalShortCodes);

    return apply_filters('fluentform/editor_shortcodes', $generalShortCodes);
}

function fluentFormGetAllEditorShortCodes($form)
{
    $editorShortCodes = EditorShortCode::getShortCodes($form);
    /* This filter is deprecated and will be removed soon */
    $editorShortCodes = apply_filters(
        'fluentform_all_editor_shortcodes',
        $editorShortCodes,
        $form
    );
    return apply_filters(
        'fluentform/all_editor_shortcodes',
        $editorShortCodes,
        $form
    );
}

/**
 * Recursively implode a multi-dimentional array
 *
 * @param string $glue
 * @param array  $array
 *
 * @return string
 */
function fluentImplodeRecursive($glue, array $array)
{
    $fn = function ($glue, array $array) use (&$fn) {
        $result = '';
        foreach ($array as $item) {
            if (is_array($item)) {
                $result .= $fn($glue, $item);
            } else {
                $result .= $glue . $item;
            }
        }

        return $result;
    };

    return ltrim($fn($glue, $array), $glue);
}

function fluentform_get_active_theme_slug()
{
    $ins = get_option('_ff_ins_by');

    if ($ins) {
        return sanitize_text_field($ins);
    }

    if (defined('TEMPLATELY_FILE')) {
        return 'templately';
    }

    return get_option('template');
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Global helper function, part of plugin API
function getFluentFormCountryList()
{
    static $countries = null;

    if (is_null($countries)) {
        $countries = fluentformLoadFile('/Services/FormBuilder/CountryNames.php');
    }

    return $countries;
}

function fluentFormWasSubmitted($action = 'fluentform_submit')
{
    return wpFluentForm('request')->get('action') == $action;
}

if (!function_exists('isWpAsyncRequest')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Global helper function, part of plugin API
    function isWpAsyncRequest($action)
    {
        return false !== strpos(wpFluentForm('request')->get('action'), $action);
    }
}

function fluentFormIsHandlingSubmission()
{
    $status = fluentFormWasSubmitted() || isWpAsyncRequest('fluentform_async_request');
    
    $status = apply_filters_deprecated(
        'fluentform_is_handling_submission',
        [
            $status
        ],
        FLUENTFORM_FRAMEWORK_UPGRADE,
        'fluentform/is_handling_submission',
        'Use fluentform/is_handling_submission instead of fluentform_is_handling_submission'
    );
    return apply_filters('fluentform/is_handling_submission', $status);
}

function fluentform_mb_strpos($haystack, $needle)
{
    if (function_exists('mb_strpos')) {
        return mb_strpos($haystack, $needle);
    }

    return strpos($haystack, $needle);
}

function fluentFormHandleScheduledTasks()
{
    $failedActions = wpFluent()->table('ff_scheduled_actions')->where('status', 'failed')->where('retry_count', '<', 4)->get();

    if (count($failedActions)) {
        $scheduler = wpFluentForm('fluentFormAsyncRequest');

        foreach ($failedActions as $action) {
            $scheduler->process($action);
        }
    }

    $rand = wp_rand(1, 10);
    if ($rand >= 5) {
        do_action('fluentform/maybe_scheduled_jobs');
    }
}

function fluentFormHandleScheduledEmailReport()
{
    \FluentForm\App\Services\Scheduler\Scheduler::processEmailReport();
}

function fluentform_upgrade_url()
{
    return 'https://fluentforms.com/pricing/?utm_source=plugin&utm_medium=wp_install&utm_campaign=ff_upgrade&theme_style=' . fluentform_get_active_theme_slug();
}

function fluentform_integrations_url()
{
    return 'https://fluentforms.com/integration/?utm_source=plugin&utm_medium=wp_install&utm_campaign=ff_upgrade&theme_style=' . fluentform_get_active_theme_slug();
}

function fluentFormApi($module = 'forms')
{
    if ('forms' == $module) {
        return new \FluentForm\App\Api\Form();
    } elseif ('submissions' == $module) {
        return new \FluentForm\App\Api\Submission();
    }

    throw new \Exception(esc_html('No Module found with name ' . $module));
}

function fluentFormGetRandomPhoto()
{
    $photos = [
        'demo_1.jpg',
        'demo_2.jpg',
        'demo_3.jpg',
        'demo_4.jpg',
        'demo_5.jpg',
    ];

    $selected = array_rand($photos, 1);

    $photoName = $photos[$selected];

    return fluentformMix('img/conversational/' . $photoName);
}

function fluentFormRender($atts)
{
    $shortcodeDefaults = [
        'id'                 => null,
        'title'              => null,
        'css_classes'        => '',
        'permission'         => '',
        'type'               => 'classic',
        'permission_message' => __('Sorry, You do not have permission to view this form', 'fluentform'),
    ];
    $atts = shortcode_atts($shortcodeDefaults, $atts);

    return (new \FluentForm\App\Modules\Component\Component(wpFluentForm()))->renderForm($atts);
}

/**
 * Print internal content (not user input) without escaping.
 */
function fluentFormPrintUnescapedInternalString($string)
{
    echo $string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- deprecated function, should remove it later.
}

function fluentform_options_sanitize($options)
{
    return \FluentForm\App\Helpers\Helper::sanitizeAdvancedOptions($options);
}

function fluentform_iframe_srcdoc_sanitize($value)
{
    $tags = wp_kses_allowed_html('post');
    $tags['style'] = [
        'types' => [],
    ];
    // Check if decoding is necessary
    if (strpos($value, '&') !== false) {
        // Decode HTML entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = stripslashes($value);
    }
    return wp_kses($value, $tags);
}


function fluentform_sanitize_html($html)
{
    if (!$html) {
        return $html;
    }

    // Remove event handlers (e.g., onerror, onclick, onmouseover)
    $html = preg_replace('/\s+on[a-z]+\s*=\s*([\'"])[^\'"]*\1/i', '', $html);

    // Remove JavaScript protocol (e.g., `href="javascript:alert(1)"`)
    $html = preg_replace('/\bjavascript\s*:/i', '', $html);

    $tags = wp_kses_allowed_html('post');
    $tags['style'] = [
        'types' => [],
    ];
    // iframe
    $tags['iframe'] = [
        'width'           => [],
        'height'          => [],
        'src'             => [],
        'srcdoc'          => [
            'value_callback' => 'fluentform_iframe_srcdoc_sanitize'
        ],
        'title'           => [],
        'frameborder'     => [],
        'allow'           => [],
        'class'           => [],
        'id'              => [],
        'allowfullscreen' => [],
        'style'           => [],
    ];
    
    //svg
    if (empty($tags['svg'])) {
        $svg_args = [
            'svg' => [
                'class'           => true,
                'aria-hidden'     => true,
                'aria-labelledby' => true,
                'role'            => true,
                'xmlns'           => true,
                'width'           => true,
                'height'          => true,
                'viewbox'         => true,
                'fill'            => true,
                'stroke'          => true,
                'stroke-width'    => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true
            ],
            'g'     => ['fill' => true],
            'title' => ['title' => true],
            'path'  => [
                'd'         => true,
                'fill'      => true,
                'transform' => true,
            ],
            'polyline' => [
                'points' => true
            ]
        ];
        $tags = array_merge($tags, $svg_args);
    }
    
    $tags = apply_filters_deprecated(
        'fluentform_allowed_html_tags',
        [
            $tags
        ],
        FLUENTFORM_FRAMEWORK_UPGRADE,
        'fluentform/allowed_html_tags',
        'Use fluentform/allowed_html_tags instead of fluentform_allowed_html_tags'
    );

    $tags = apply_filters('fluentform/allowed_html_tags', $tags);

    // Event-handler attributes are executable JavaScript and must not be re-enabled by filters.
    foreach ($tags as $tagName => $attributes) {
        if (!is_array($attributes)) {
            continue;
        }

        foreach (array_keys($attributes) as $attribute) {
            if (preg_match('/^on[a-z]+/i', $attribute)) {
                unset($tags[$tagName][$attribute]);
            }
        }
    }

    return wp_kses($html, $tags);
}

function fluentform_kses_js($content)
{
    if (!$content) {
        return '';
    }

    return preg_replace('/<\/?script[^>]*>/is', '', $content);
}

function fluentform_sanitize_json_object($value)
{
    if (!is_string($value) || '' === trim($value)) {
        return '';
    }

    $value = trim($value);

    $decoded = json_decode($value, true);

    // Best-effort recovery of a pure-data JS-object literal (unquoted keys,
    // single quotes, trailing commas) — the documented "JS object" format for
    // the Date/Time field's advanced config. json_decode below is the security
    // gate: any function or expression that survives normalisation is still not
    // valid JSON, so it is rejected. Normalisation can only recover data, never
    // execute or emit code.
    if (JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
        $decoded = json_decode(fluentform_js_object_to_json($value), true);
    }

    if (JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
        return '';
    }

    if ([] === $decoded) {
        return '{}';
    }

    // date_config must be an object; reject a top-level JSON array. Nested
    // arrays (e.g. flatpickr `disable: [...]`) are preserved by not forcing
    // JSON_FORCE_OBJECT recursively.
    if (array_keys($decoded) === range(0, count($decoded) - 1)) {
        return '';
    }

    return wp_json_encode($decoded);
}

function fluentform_js_object_to_json($value)
{
    // Single-quoted strings -> double-quoted (respecting escapes).
    $value = preg_replace_callback(
        "/'((?:\\\\.|[^'\\\\])*)'/s",
        function ($m) {
            return '"' . str_replace(['\\\'', '"'], ['\'', '\\"'], $m[1]) . '"';
        },
        $value
    );

    // Quote unquoted object keys: `{ key:` / `, key:` -> `{ "key":`.
    $value = preg_replace('/([{,]\s*)([A-Za-z_$][A-Za-z0-9_$]*)(\s*:)/', '$1"$2"$3', $value);

    // Drop trailing commas before a closing brace/bracket.
    $value = preg_replace('/,\s*([}\]])/', '$1', $value);

    return $value;
}

/**
 * Sanitize inputs recursively.
 *
 * @param array $input
 * @param array $sanitizeMap
 *
 * @return array $input
 */
function fluentform_backend_sanitizer($inputs, $sanitizeMap = [])
{
    $originalValues = $inputs;
    foreach ($inputs as $key => &$value) {
        if (is_array($value)) {
            $value = fluentform_backend_sanitizer($value, $sanitizeMap);
        } else {
            $method = ArrayHelper::get($sanitizeMap, $key);
            if (is_callable($method)) {
                $value = call_user_func($method, $value);
            }
        }
    }

    return apply_filters('fluentform/backend_sanitized_values', $inputs, $originalValues);
}

/**
 * Sanitizes CSS.
 *
 * @return mixed $css
 */
function fluentformSanitizeCSS($css)
{
    if ($css === null || $css === '') {
        return '';
    }

    // Convert to string if not already
    if (!is_string($css)) {
        $css = (string) $css;
    }
    
    return preg_match('#</?\w+#', $css) ? '' : $css;
}

function fluentformCanUnfilteredHTML()
{
    return current_user_can('unfiltered_html') || apply_filters('fluentform/disable_fields_sanitize', false);
}

function fluentformLoadFile($path)
{
    return require wpFluentForm('path.app') . '/' . ltrim($path, '/');
}

if (!function_exists('fluentValidator')) {
    function fluentValidator($data = [], $rules = [], $messages = [])
    {
        return wpFluentForm('validator')->make($data, $rules, $messages);
    }
}

function fluentformGetPages()
{
    $pages = get_pages();
    $formattedPages = [];

    foreach ($pages as $page) {
        $formattedPages[] = [
            'ID'         => $page->ID,
            'post_title' => $page->post_title,
            'guid'       => $page->guid,
        ];
    }

    return $formattedPages;
}
