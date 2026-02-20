<?php
/**
 * CF7 Redirection
 *
 * @since 3.2.0
 * @package CF7Apps
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CF7Apps_Redirection' ) && class_exists( 'CF7Apps_App' ) ) :
    /**
     * Class CF7Apps_Redirection
     *
     * @since 3.2.0
     */
    class CF7Apps_Redirection extends CF7Apps_App {
        /**
         * App version
         *
         * @since 3.2.1
         * @var string $app_version App version.
         */
        private $app_version = '1.0.0';

        /**
         * CF7Apps_Redirection constructor.
         *
         * @since 3.2.0
         */
        public function __construct() {
            $this->id                    = 'cf7-redirection';
            $this->priority              = 1;
            $this->title                 = __( 'Redirection', 'cf7apps' );
            $this->description           = __( 'Easily redirect users to a specific URL after form submission, enhancing user experience and engagement.', 'cf7apps' );
            $this->icon                  = plugin_dir_url( __FILE__ ) . 'assets/images/logo.png';
            $this->has_admin_settings    = true;
            $this->is_pro                = false;
            $this->by_default_enabled    = false;
            $this->has_internal_settings = true;
            $this->documentation_url     = 'https://cf7apps.com/docs/general/redirection';
            $this->parent_menu           = __( 'General', 'cf7apps' );

            $this->run();
        }

        /**
         * Admin settings for the Redirection app.
         *
         * @since 3.2.0
         * @return array[]
         */
        public function admin_settings() {
            $posts = cf7apps_get_post_types_options();
            return array(
                'general' => array(
                    'fields' => array(
                        'notice'           => array(
                            'type'  => 'notice',
                            'class' => 'info',
                            'text'  => sprintf(
                                __( 'Stuck? Check our Documentation on %s', 'cf7apps' ),
                                '<a href="https://cf7apps.com/docs/general/redirection" target="_blank"><u>' . __( 'Redirection', 'cf7apps' ) . '</u></a>'
                            ),
                        ),

                        'is_enabled'       => array(
                            'title'   => __( 'Enable Redirection App', 'cf7apps' ),
                            'type'    => 'checkbox',
                            'default' => false,
                        ),

                        'global_settings'  => array(
                            'title'       => __( 'Global Settings', 'cf7apps' ),
                            'type'        => 'checkbox',
                            'default'     => false,
                            'help' => __( 'Enable global settings, so they apply across all forms. These global settings will automatically sync with each individual form, where they can still be modified if needed.', 'cf7apps' ),
                        ),

                        'redirection_type' => array(
                            'title'       => __( 'Redirection Type', 'cf7apps' ),
                            'type'        => 'radio',
                            'options'     => array(
                                'post_type'    => __( 'Internal URL', 'cf7apps' ),
                                'external_url' => __( 'External URL', 'cf7apps' ),
                            ),
                            'default'     => 'post_type',

                            'sub_fields'  => array(
                                'post_type'    => array(
                                    'title'       => __( 'Select Post Type', 'cf7apps' ),
                                    'type'        => 'select',
                                    'options'     => $posts,
                                    'default'     => array_keys( $posts )[0],
                                    'help'        => __( 'Choose the post type to redirect users to after form submission.', 'cf7apps' ),
                                ),

                                'external_url' => array(
                                    'title'       => __( 'External URL', 'cf7apps' ),
                                    'type'        => 'text',
                                    'default'     => '',
                                    'placeholder' => 'Enter URL',
                                    'help'        => __( 'Enter the external URL to redirect users to after form submission. Make sure to include the full URL (e.g., https://example.com).', 'cf7apps' ),
                                    'required'    => true,
                                ),
                            ),
                        ),

                        'new_tab'          => array(
                            'title'       => __( 'Open in New Tab', 'cf7apps' ),
                            'type'        => 'checkbox',
                            'default'     => false,
                            'help' => __( 'Open the redirection link in a new browser tab.', 'cf7apps' ),
                        ),

                        'save_settings'    => array(
                            'type'  => 'save_button',
                            'text'  => __( 'Save Settings', 'cf7apps' ),
                            'class' => 'button-primary'
                        ),
                    ),
                ),
            );
        }

        /**
         * Get default settings for the Redirection app.
         *
         * @since 3.2.0
         * @return array
         */
        private function get_default_settings() {
            $posts = cf7apps_get_post_types_options();
            $global_settings = $this->get_option( 'global_settings' );
            return array(
                'is_enabled'       => false,
                'global_settings'  => false,
                'enable_redirection' => $global_settings ? true : false,
                'redirection_type' => 'post_type',
                'post_type'        => array_keys( $posts )[0],
                'external_url'     => '',
                'new_tab'          => false,
            );
        }

        /**
         * Internal settings for individual forms.
         *
         * @since 3.2.0
         * @return array[]
         */
        public function internal_settings() {
            $enabled         = $this->get_option( 'is_enabled' );
            $global_settings = $this->get_option( 'global_settings' );
            $posts    = cf7apps_get_post_types_options();
            $settings = array(
                'general' => array(
                    'fields' => array()
                )
            );

            $settings['general']['fields']['notice'] = array(
                'type'  => 'notice',
                'class' => 'info',
                'text'  => sprintf(
                    __( 'Stuck? Check our Documentation on %s', 'cf7apps' ),
                    '<a href="https://cf7apps.com/docs/general/redirection/" target="_blank"><u>' . __( 'Redirection', 'cf7apps' ) . '</u></a>'
                ),
            );

            // Default enable_redirection to true when global settings are enabled
            $default_enable_redirection = $global_settings ? true : false;
            
            $settings['general']['fields']['enable_redirection'] = array(
                'title'   => __( 'Enable Redirection', 'cf7apps' ),
                'type'    => 'checkbox',
                'default' => true,
                'disabled' => ! $enabled,
            );

            $settings['general']['fields']['redirection_type'] = array(
                'title'       => __( 'Redirection Type', 'cf7apps' ),
                'type'        => 'radio',
                'options'     => array(
                    'post_type'    => __( 'Internal URL', 'cf7apps' ),
                    'external_url' => __( 'External URL', 'cf7apps' ),
                ),
                'default'     => 'post_type',
                'disabled'    => ! $enabled,
                'sub_fields'  => array(
                    'post_type'    => array(
                        'title'       => __( 'Select Post Type', 'cf7apps' ),
                        'type'        => 'select',
                        'options'     => $posts,
                        'default'     => array_keys( $posts )[0],
                        'help'        => __( 'Choose the post type to redirect users to after form submission.', 'cf7apps' ),
                        'disabled'    => ! $enabled,
                    ),
                    'external_url' => array(
                        'title'       => __( 'External URL', 'cf7apps' ),
                        'type'        => 'text',
                        'default'     => '',
                        'placeholder' => 'Enter URL',
                        'help'        => __( 'Enter the external URL to redirect users to after form submission. Make sure to include the full URL (e.g., https://example.com).', 'cf7apps' ),
                        'disabled'    => ! $enabled,
                        'required'    => true,
                    ),
                ),
            );

            $settings['general']['fields']['new_tab']          = array(
                'title'       => __( 'Open in New Tab', 'cf7apps' ),
                'type'        => 'checkbox',
                'default'     => false,
                'help' => __( 'Open the redirection link in a new browser tab.', 'cf7apps' ),
                'disabled'    => ! $enabled,
            );

            $settings['general']['fields']['save_settings']    = array(
                'type'        => 'save_button',
                'text'        => __( 'Save Settings', 'cf7apps' ),
                'class'       => 'button-primary',
                'disabled'    => ! $enabled,
            );

            return $settings;
        }

        /**
         * Get Internal Settings
         * Override to populate global settings when form has no custom settings
         *
         * @since 3.3.0
         * @param int $form_id The form ID
         * @return array
         */
        public function get_internal_settings( $form_id ) {
            // Call parent method first to get base structure
            $settings = parent::get_internal_settings( $form_id );
            
            // Check if global settings are enabled
            $global_settings_enabled = $this->get_option( 'global_settings' );
            
            if ( ! $global_settings_enabled ) {
                return $settings;
            }
            
            // Get form's individual settings from post meta
            $form_settings = get_post_meta( $form_id, 'cf7apps_settings', true );
            if ( empty( $form_settings ) ) {
                $form_settings = array();
            }
            
            // Set enable_redirection to default (true) when form has no saved enable_redirection value
            if ( isset( $settings['admin_settings']['general']['fields']['enable_redirection'] ) ) {
                if ( ! isset( $form_settings[ $this->id ]['enable_redirection'] ) ) {
                    // Form has no saved enable_redirection value, use default (true)
                    $settings['admin_settings']['general']['fields']['enable_redirection']['checked'] = true;
                }
            }
            
            // Check if form has custom settings (excluding enable_redirection toggle)
            $has_custom_settings = ! empty( $form_settings ) && isset( $form_settings[ $this->id ] ) && (
                isset( $form_settings[ $this->id ]['redirection_type'] ) ||
                isset( $form_settings[ $this->id ]['post_type'] ) ||
                isset( $form_settings[ $this->id ]['external_url'] ) ||
                isset( $form_settings[ $this->id ]['new_tab'] )
            );
            
            // If form has custom settings, don't populate global settings
            if ( $has_custom_settings ) {
                return $settings;
            }
            
            // Form has no custom settings - populate with global settings for display
            $global_settings = $this->get_option( null );
            if ( empty( $global_settings ) || ! isset( $settings['admin_settings']['general']['fields'] ) ) {
                return $settings;
            }
            
            // Populate fields with global settings values
            $setting_fields = $settings['admin_settings']['general']['fields'];
            foreach ( $setting_fields as $field_key => $field ) {
                // Skip notice, save_settings, and enable_redirection (already handled above)
                if ( in_array( $field_key, array( 'notice', 'save_settings', 'enable_redirection' ) ) ) {
                    continue;
                }
                
                // Only populate if field doesn't have a saved value
                if ( ! isset( $form_settings[ $this->id ][ $field_key ] ) && isset( $global_settings[ $field_key ] ) ) {
                    if ( $field['type'] == 'checkbox' ) {
                        $settings['admin_settings']['general']['fields'][ $field_key ]['checked'] = ( $global_settings[ $field_key ] == '1' || $global_settings[ $field_key ] === true || $global_settings[ $field_key ] === 1 );
                    } elseif ( $field['type'] == 'radio' ) {
                        // Radio buttons use 'value' property (like text fields)
                        $settings['admin_settings']['general']['fields'][ $field_key ]['value'] = $global_settings[ $field_key ];
                    } elseif ( $field['type'] == 'select' ) {
                        $settings['admin_settings']['general']['fields'][ $field_key ]['selected'] = $global_settings[ $field_key ];
                    } else {
                        $settings['admin_settings']['general']['fields'][ $field_key ]['value'] = $global_settings[ $field_key ];
                    }
                }
                
                // Handle sub_fields if they exist (for redirection_type radio field)
                if ( isset( $field['sub_fields'] ) ) {
                    foreach ( $field['sub_fields'] as $sub_field_key => $sub_field ) {
                        if ( ! isset( $form_settings[ $this->id ][ $sub_field_key ] ) && isset( $global_settings[ $sub_field_key ] ) ) {
                            if ( $sub_field['type'] == 'checkbox' || $sub_field['type'] == 'radio' ) {
                                $settings['admin_settings']['general']['fields'][ $field_key ]['sub_fields'][ $sub_field_key ]['checked'] = ( $global_settings[ $sub_field_key ] == '1' || $global_settings[ $sub_field_key ] === true || $global_settings[ $sub_field_key ] === 1 );
                            } elseif ( $sub_field['type'] == 'select' ) {
                                $settings['admin_settings']['general']['fields'][ $field_key ]['sub_fields'][ $sub_field_key ]['selected'] = $global_settings[ $sub_field_key ];
                            } else {
                                $settings['admin_settings']['general']['fields'][ $field_key ]['sub_fields'][ $sub_field_key ]['value'] = $global_settings[ $sub_field_key ];
                            }
                        }
                    }
                }
            }
            
            return $settings;
        }

        /**
         * CF7Apps_Redirection constructor.
         *
         * @since 3.2.0
         * @param CF7Apps_App[] $apps List of registered apps.
         *
         * @return CF7Apps_App[]
         */
        public static function initialize_module( $apps ) {

            $apps[] = __CLASS__;

            return $apps;
        }

        /**
         * Run the Redirection app.
         *
         * @since 3.2.0
         */
        private function run() {
            add_action( 'wp_ajax_nopriv_cf7apps_fetch_settings', array( $this, 'fetch_app_settings' ) );
            add_action( 'wp_ajax_cf7apps_fetch_settings', array( $this, 'fetch_app_settings' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
        }

        /**
         * Fetch app settings via AJAX.
         *
         * @since 3.2.0
         */
        public function fetch_app_settings() {
            if ( $this->get_option( 'is_enabled' ) ) {

                if ( isset( $_POST['formId'] ) ) {
                    $form_id             = intval( wp_unslash( $_POST['formId'] ) );
                    $global_app_enabled  = $this->get_option( 'global_settings' ); // from global app settings.
                    $form_settings      = $this->get_individual_option( $form_id );
                    $default_settings   = $this->get_default_settings();
                    
                    // If global settings enabled: Individual settings override, otherwise global applies. If global disabled: Form uses Individual settings only.
                    if ( $global_app_enabled ) {
                        // Check if form has custom redirection settings (excluding enable_redirection)
                        $has_custom_settings = ! empty( $form_settings ) && (
                            isset( $form_settings['redirection_type'] ) ||
                            isset( $form_settings['post_type'] ) ||
                            isset( $form_settings['external_url'] ) ||
                            isset( $form_settings['new_tab'] )
                        );
                        
                        if ( $has_custom_settings ) {
                            // Form has custom settings, check if form explicitly disabled redirection
                            if ( isset( $form_settings['enable_redirection'] ) && ! $form_settings['enable_redirection'] ) {
                                // Form toggle is OFF, skip redirection
                                wp_send_json_error(
                                    array(
                                        'message' => __( 'Redirection is not enabled for this form.', 'cf7apps' ),
                                    ),
                                    '403'
                                );
                            } else {
                                // Use form's custom settings (Individual settings override)
                                $settings = wp_parse_args( $form_settings, $default_settings );
                            }
                        } else {
                            // Form has no custom settings - use global settings
                            $global_settings = $this->get_option( null );
                            
                            if ( empty( $global_settings ) ) {
                                wp_send_json_error(
                                    array(
                                        'message' => __( 'Redirection settings not found.', 'cf7apps' ),
                                    ),
                                    '404'
                                );
                            }
                            
                            // Use global settings
                            $settings = wp_parse_args( $global_settings, $default_settings );
                            
                            // Check if form has explicitly disabled redirection (even without custom settings)
                            if ( isset( $form_settings['enable_redirection'] ) && ! $form_settings['enable_redirection'] ) {
                                // Form toggle is explicitly disabled - skip redirection
                                wp_send_json_error(
                                    array(
                                        'message' => __( 'Redirection is not enabled for this form.', 'cf7apps' ),
                                    ),
                                    '403'
                                );
                            }
                            
                            // Default to enabled when using global settings
                            $settings['enable_redirection'] = true;
                        }
                    } else {
                        // Global settings not enabled, check form's enable_redirection toggle
                        $enable_redirection = isset( $form_settings['enable_redirection'] ) 
                            ? $form_settings['enable_redirection'] 
                            : $default_settings['enable_redirection'];
                        
                        // If redirection is not enabled for this form, return error
                        if ( ! $enable_redirection ) {
                            wp_send_json_error(
                                array(
                                    'message' => __( 'Redirection is not enabled for this form.', 'cf7apps' ),
                                ),
                                '403'
                            );
                        }
                        
                        // Use form settings only
                        $settings = wp_parse_args( $form_settings, $default_settings );
                    }

                    if ( $settings ) {
                        $redirect_type = $settings['redirection_type'];
                        $redirect_to   = $settings[ $redirect_type ];
                        $new_tab       = $settings['new_tab'];

                        if ( 'post_type' === $redirect_type ) {
                            $redirect_to = get_permalink( $redirect_to );
                        }

                        wp_send_json_success(
                            array(
                                'redirectTo' => $redirect_to,
                                'newTab'     => $new_tab,
                                'isEnabled' => true,
                            ),
                            200
                        );
                    }
                }
                wp_send_json_error(
                    array(
                        'message' => __( 'Form ID is required.', 'cf7apps' ),
                    ),
                    '404'
                );
            }

            wp_send_json_error(
                array(
                    'message' => __( 'Redirection app is not enabled.', 'cf7apps' ),
                ),
                '403'
            );
        }

        /**
         * Enqueue frontend scripts for redirection.
         *
         * @since 3.2.0
         */
        public function wp_enqueue_scripts() {
            if ( $this->get_option( 'is_enabled' ) ) {
                wp_enqueue_script(
                    'cf7apps-redirection',
                    plugin_dir_url( __FILE__ ) . 'assets/js/app.js',
                    array( 'jquery' ),
                    CF7APPS_VERSION . '-' . $this->app_version,
                    true
                );

                wp_localize_script(
                    'cf7apps-redirection',
                    'cf7appsRedirection',
                    array(
                        'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    )
                );
            }
        }
    }

    add_filter( 'cf7apps_apps', array( CF7Apps_Redirection::class, 'initialize_module' ) );
endif;
