<?php
/**
 * Multi-Language Support System
 *
 * Only active when "Mehrsprachigkeit aktivieren" is enabled in
 * Agency Core Settings (ACF option: multilang_enable).
 *
 * Compatible with Polylang
 * Fallback functions if Polylang not active
 * Custom language switcher
 * RTL support
 * String translation system
 */

if (!defined('ABSPATH')) exit;

/**
 * Boot multi-language only when the ACF toggle is ON.
 * We hook into init (priority 1) so ACF options are readable,
 * but still early enough for Polylang hooks to work.
 */
add_action('init', function () {
    // If ACF is not available, skip silently
    if (!function_exists('get_field')) {
        return;
    }
    // Read the toggle from the options page
    $enabled = get_field('multilang_enable', 'option');
    if (!$enabled) {
        return; // Feature disabled → do nothing
    }
    // Instantiate the class
    global $medialab_multilang;
    $medialab_multilang = new MediaLab_Multi_Language();
}, 1);

class MediaLab_Multi_Language {
    
    private $polylang_active = false;
    private $current_language = 'de';
    private $default_language = 'de';
    private $available_languages = array();
    
    public function __construct() {
        // Check if Polylang is active
        $this->polylang_active = function_exists('pll_languages_list');
        
        if ($this->polylang_active) {
            $this->setup_polylang();
        } else {
            $this->setup_fallback();
        }
        
        // Language Switcher
        add_shortcode('language_switcher', array($this, 'language_switcher_shortcode'));
        
        // RTL Support
        add_action('wp_head', array($this, 'add_rtl_support'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // String translation system
        add_action('init', array($this, 'register_translatable_strings'));
        
        // Enqueue assets
        
    }
    
    /**
     * Setup Polylang Integration
     */
    private function setup_polylang() {
        if (!function_exists('pll_current_language')) {
            return;
        }
        
        $this->current_language = pll_current_language() ?: 'de';
        $this->default_language = pll_default_language() ?: 'de';
        
        // Get available languages
        $langs = pll_languages_list(array('fields' => 'slug'));
        $this->available_languages = $langs ?: array('de');
    }
    
    /**
     * Setup Fallback (no Polylang)
     */
    private function setup_fallback() {
        $this->current_language = 'de';
        $this->default_language = 'de';
        $this->available_languages = array('de');
    }
    
    /**
     * Language Switcher Shortcode
     */
    public function language_switcher_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'dropdown', // dropdown, list, flags
            'show_names' => true,
            'show_flags' => true,
        ), $atts);
        
        ob_start();
        
        if ($this->polylang_active) {
            $this->render_polylang_switcher($atts);
        } else {
            $this->render_fallback_notice();
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render Polylang Switcher
     */
    private function render_polylang_switcher($atts) {
        $languages = pll_the_languages(array('raw' => 1));
        
        if (empty($languages)) {
            return;
        }
        
        $type = $atts['type'];
        $show_names = $atts['show_names'];
        $show_flags = $atts['show_flags'];
        
        ?>
        <div class="language-switcher language-switcher--<?php echo esc_attr($type); ?>" 
             data-language-switcher>
            
            <?php if ($type === 'dropdown') : ?>
                <div class="language-dropdown">
                    <button class="language-dropdown__trigger" 
                            aria-expanded="false"
                            aria-haspopup="true">
                        <?php
                        $current = null;
                        foreach ($languages as $lang) {
                            if ($lang['current_lang']) {
                                $current = $lang;
                                break;
                            }
                        }
                        if ($current) {
                            if ($show_flags && !empty($current['flag'])) {
                                echo '<span class="language-flag">' . $current['flag'] . '</span>';
                            }
                            if ($show_names) {
                                echo '<span class="language-name">' . esc_html($current['name']) . '</span>';
                            }
                        }
                        ?>
                        <svg width="12" height="12" viewBox="0 0 12 12" class="language-dropdown__icon">
                            <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="2" fill="none"/>
                        </svg>
                    </button>
                    
                    <ul class="language-dropdown__menu" role="menu">
                        <?php foreach ($languages as $lang) : ?>
                            <li role="none">
                                <a href="<?php echo esc_url($lang['url']); ?>" 
                                   class="language-dropdown__item <?php echo $lang['current_lang'] ? 'is-active' : ''; ?>"
                                   role="menuitem"
                                   hreflang="<?php echo esc_attr($lang['slug']); ?>">
                                    <?php if ($show_flags && !empty($lang['flag'])) : ?>
                                        <span class="language-flag"><?php echo $lang['flag']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($show_names) : ?>
                                        <span class="language-name"><?php echo esc_html($lang['name']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            
            <?php elseif ($type === 'list') : ?>
                <ul class="language-list">
                    <?php foreach ($languages as $lang) : ?>
                        <li class="language-list__item <?php echo $lang['current_lang'] ? 'is-active' : ''; ?>">
                            <a href="<?php echo esc_url($lang['url']); ?>" 
                               class="language-list__link"
                               hreflang="<?php echo esc_attr($lang['slug']); ?>">
                                <?php if ($show_flags && !empty($lang['flag'])) : ?>
                                    <span class="language-flag"><?php echo $lang['flag']; ?></span>
                                <?php endif; ?>
                                <?php if ($show_names) : ?>
                                    <span class="language-name"><?php echo esc_html($lang['name']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            
            <?php elseif ($type === 'flags') : ?>
                <div class="language-flags">
                    <?php foreach ($languages as $lang) : ?>
                        <a href="<?php echo esc_url($lang['url']); ?>" 
                           class="language-flags__link <?php echo $lang['current_lang'] ? 'is-active' : ''; ?>"
                           hreflang="<?php echo esc_attr($lang['slug']); ?>"
                           title="<?php echo esc_attr($lang['name']); ?>">
                            <?php if (!empty($lang['flag'])) : ?>
                                <span class="language-flag"><?php echo $lang['flag']; ?></span>
                            <?php else : ?>
                                <span class="language-code"><?php echo esc_html($lang['slug']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Render Fallback Notice
     */
    private function render_fallback_notice() {
        if (current_user_can('manage_options')) {
            echo '<div class="language-switcher-notice">';
            echo '<p>⚠️ <strong>Polylang not active.</strong> Install Polylang to enable multi-language support.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add RTL Support
     */
    public function add_rtl_support() {
        if (!$this->polylang_active) {
            return;
        }
        
        // Check if current language is RTL
        $lang = pll_current_language();
        $lang_obj = null;
        
        if (function_exists('PLL')) {
            $lang_obj = PLL()->model->get_language($lang);
        }
        
        if ($lang_obj && !empty($lang_obj->is_rtl)) {
            ?>
            <style id="rtl-support">
                /* RTL Support */
                body {
                    direction: rtl;
                    text-align: right;
                }
                
                /* Flip layout elements */
                .container,
                .row {
                    direction: rtl;
                }
                
                /* Flip flexbox */
                .flex-row {
                    flex-direction: row-reverse;
                }
                
                /* Margins & Paddings */
                [class*="ml-"] { 
                    margin-left: 0 !important;
                    margin-right: var(--spacing) !important; 
                }
                [class*="mr-"] { 
                    margin-right: 0 !important;
                    margin-left: var(--spacing) !important; 
                }
                [class*="pl-"] { 
                    padding-left: 0 !important;
                    padding-right: var(--spacing) !important; 
                }
                [class*="pr-"] { 
                    padding-right: 0 !important;
                    padding-left: var(--spacing) !important; 
                }
                
                /* Text alignment */
                .text-left { text-align: right !important; }
                .text-right { text-align: left !important; }
                
                /* Borders */
                .border-left { 
                    border-left: none !important;
                    border-right: 1px solid currentColor !important; 
                }
                .border-right { 
                    border-right: none !important;
                    border-left: 1px solid currentColor !important; 
                }
            </style>
            <?php
        }
    }
    
    /**
     * Register Translatable Strings
     */
    public function register_translatable_strings() {
        if (!$this->polylang_active || !function_exists('pll_register_string')) {
            return;
        }
        
        // Register common theme strings
        $strings = array(
            'Read More' => 'Read More',
            'Learn More' => 'Learn More',
            'Contact Us' => 'Contact Us',
            'Get Started' => 'Get Started',
            'View All' => 'View All',
            'Load More' => 'Load More',
            'Search' => 'Search',
            'No Results' => 'No Results Found',
            'Related Posts' => 'Related Posts',
            'Share' => 'Share',
            'Previous' => 'Previous',
            'Next' => 'Next',
            'Close' => 'Close',
            'Menu' => 'Menu',
        );
        
        foreach ($strings as $name => $value) {
            pll_register_string($name, $value, 'Theme');
        }
    }
    
    /**
     * Enqueue Assets
     */
    /**
     * Admin Notices
     */
    public function admin_notices() {
        if (!$this->polylang_active && current_user_can('manage_options')) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Multi-Language Support:</strong> 
                    Install <a href="<?php echo admin_url('plugin-install.php?s=polylang&tab=search'); ?>">Polylang</a> 
                    to enable multi-language features.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get Current Language
     */
    public static function get_current_language() {
        if (function_exists('pll_current_language')) {
            return pll_current_language() ?: 'de';
        }
        return 'de';
    }
    
    /**
     * Get Translated String
     */
    public static function translate_string($string, $context = 'Theme') {
        if (function_exists('pll__')) {
            return pll__($string);
        }
        return $string;
    }
    
    /**
     * Get Translated Post/Page
     */
    public static function get_translated_post($post_id, $lang = null) {
        if (function_exists('pll_get_post')) {
            return pll_get_post($post_id, $lang);
        }
        return $post_id;
    }
}

// Instantiation is handled by the add_action('init') hook above.

/**
 * Helper Functions
 * Safe to call even when multilang is disabled — returns graceful fallback.
 */

// Get current language
function medialab_get_language() {
    return MediaLab_Multi_Language::get_current_language();
}

// Translate string
function medialab_translate($string, $context = 'Theme') {
    return MediaLab_Multi_Language::translate_string($string, $context);
}

// Shorthand
function __ml($string, $context = 'Theme') {
    return medialab_translate($string, $context);
}

// Get translated post
function medialab_get_translated_post($post_id, $lang = null) {
    return MediaLab_Multi_Language::get_translated_post($post_id, $lang);
}
