<?php
/**
 * CF7 Webhook Integration
 *
 * @since 3.2.0
 * @package CF7Apps
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CF7Apps_Webhook' ) && class_exists( 'CF7Apps_App' ) ) :
    /**
     * Class CF7Apps_Webhook
     */
    class CF7Apps_Webhook extends CF7Apps_App {
        /**
         * App version
         *
         * @var string
         */
        private $app_version = '1.0.0';

        /**
         * Constructor
         */
        public function __construct() {
            $this->id                    = 'webhook';
            $this->priority              = 2;
            $this->title                 = __( 'Webhook', 'cf7apps' );
            $this->description           = __( 'Send Contact Form 7 data to third-party apps instantly using webhook endpoints.', 'cf7apps' );
            $this->icon                  = plugin_dir_url( __FILE__ ) . 'assets/images/logo.png';
            $this->has_admin_settings    = true;
            $this->is_pro                = false;
            $this->by_default_enabled    = false;
            $this->has_internal_settings = true;
            $this->documentation_url     = 'https://cf7apps.com/docs/integration/webhook';
            $this->parent_menu           = __( 'Integration', 'cf7apps' );

            $this->run();
        }

        /**
         * Admin (global) settings
         *
         * @return array
         */
        public function admin_settings() {
            return array(
                'general' => array(
                    'fields' => array(
                        'notice' => array(
                            'type'  => 'notice',
                            'class' => 'info',
                            'text'  => sprintf( __( 'Stuck? Check our Documentation on %s', 'cf7apps' ), '<a href="https://cf7apps.com/docs/integration/webhook" target="_blank"><u>' . __( 'Webhook', 'cf7apps' ) . '</u></a>' ),
                        ),

                        'is_enabled' => array(
                            'title'   => __( 'Enable Webhook App', 'cf7apps' ),
                            'type'    => 'checkbox',
                            'default' => false,
                        ),

                        'global_settings' => array(
                            'title'   => __( 'Global Settings', 'cf7apps' ),
                            'type'    => 'checkbox',
                            'default' => false,
                            'help'    => __( 'Enable global settings, so they apply across all forms. These global settings will automatically sync with each individual form, where they can still be modified if needed.', 'cf7apps' ),
                        ),

                        'webhook_url' => array(
                            'title'       => __( 'Webhook URL *', 'cf7apps' ),
                            'type'        => 'text',
                            'default'     => '',
                            'placeholder' => 'Webhook URL',
                             'class'      => 'lg',
                             'required'    => true
                        ),

                        'method' => array(
                            'title'   => __( 'Method', 'cf7apps' ),
                            'type'    => 'select',
                            'options' => array(
                                'POST' => 'POST',
                                'GET'  => 'GET',
                            ),
                            'default' => 'POST',
                            'class'   => 'inline-select xs',
                        ),

                        'data_type' => array(
                            'title'   => __( 'Data Type', 'cf7apps' ),
                            'type'    => 'select',
                            'options' => array( 'json' => __( 'JSON', 'cf7apps' ), 'form' => __( 'Form Data', 'cf7apps' ) ),
                            'default' => 'json',
                            'class'   => 'inline-select xs',
                        ),

                        'headers' => array(
                            'title'       => __( 'Request Headers', 'cf7apps' ),
                            'type'        => 'textarea',
                            'default'     => '{"Content-Type":"application/json"}',
                            'placeholder' => '{"Authorization":"Bearer TOKEN"}',
                            'help'        => __( 'Optional JSON object of headers to send with the webhook request.', 'cf7apps' ),
                            'collapsible' => true,
                            'collapsed_button_color' => '#468db4',
                            'pre_text'    => sprintf( __( 'When you need authentication/authorization.<br>You can add %s to your webhook request.', 'cf7apps' ), '<a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers" target="_blank" style="color: #468db4; text-decoration: none;"><u>HTTP Headers</u></a>' ),
                            'post_text'   => __( 'One header by line, separated by colon. Example: Authorization: Bearer 99999999999999999999', 'cf7apps' ),
                        ),

                        'payload' => array(
                            'title'       => __( 'Request Body', 'cf7apps' ),
                            'type'        => 'textarea',
                            'default'     => '{"form_id":"{{form_id}}","fields":{{fields}}}',
                            'help'        => __( 'Use {{form_id}} and {{fields}} placeholders. {{fields}} will be a JSON object of submitted fields.', 'cf7apps' ),
                            'collapsible' => true,
                            'collapsed_button_color' => '#468db4',
                            'pre_text'    => __( 'When you need customize your request.<br><br><strong style="color: #A61B1B;">BE CAREFUL!</strong> This can break your integration (check your quotes).<br>REMEMBER: You can change field name with webhook config: [email* your_email webhook:email]', 'cf7apps' ),
                            'post_text'   => sprintf( __( 'To create your own body, you can replace values like in mail body (case sensitive).<br>Example: {"message": "Sir [your-name] from [your-city]." }<br><br>We will send your form data as JSON:<br><br><textarea style="width: 500px; min-height: 150px; font-family: monospace; background: #f8f9fa; border: 1px solid #ddd; padding: 10px; pointer-events: none;" readonly>%s</textarea><br><br>This is just an example of field names and will not reflect actual data or customizations.', 'cf7apps' ), "{\n    \"your-name\": \"??????\",\n    \"your-email\": \"??????\",\n    \"your-subject\": \"??????\",\n    \"your-message\": \"??????\"\n}" ),
                        ),

                        'save_settings' => array(
                            'type'  => 'save_button',
                            'text'  => __( 'Save Settings', 'cf7apps' ),
                            'class' => 'button-primary',
                        ),
                    ),
                ),
            );
        }

        /**
         * Default settings
         *
         * @return array
         */
        private function get_default_settings() {
            return array(
                'is_enabled'      => false,
                'global_settings' => false,
                'webhook_url'     => '',
                'method'          => 'POST',
                'data_type'       => 'json',
                'headers'         => '{"Content-Type":"application/json"}',
                'payload'         => '{"form_id":"{{form_id}}","fields":{{fields}}}',
            );
        }


        /**
         * Internal settings per form
         *
         * @return array
         */
        public function internal_settings() {
            $enabled = $this->get_option( 'is_enabled' );
            $global_settings_enabled = $this->get_option( 'global_settings' );

            $settings = array(
                'general' => array(
                    'fields' => array(),
                ),
            );

            $settings['general']['fields']['notice'] = array(
                'type'  => 'notice',
                'class' => 'info',
                'text'  => sprintf( __( 'Stuck? Check our Documentation on %s', 'cf7apps' ), '<a href="https://cf7apps.com/docs/integration/webhook" target="_blank"><u>' . __( 'Webhook', 'cf7apps' ) . '</u></a>' ),
            );


            $settings['general']['fields']['is_enabled'] = array(
                'title'    => __( 'Enable Webhook', 'cf7apps' ),
                'type'     => 'checkbox',
                'default'  => true,
                'disabled' => ! $enabled,
            );

            $settings['general']['fields']['webhook_url'] = array(
                'title'       => __( 'Webhook URL *', 'cf7apps' ),
                'type'        => 'text',
                'default'     => '',
                'placeholder' => 'Webhook URL',
                'disabled'    => ! $enabled,
                'class'       => 'lg',
                'required'    => true,
            );

            $settings['general']['fields']['method'] = array(
                'title'    => __( 'Method', 'cf7apps' ),
                'type'     => 'select',
                'options'  => array( 'POST' => 'POST', 'GET' => 'GET' ),
                'default'  => 'POST',
                'disabled' => ! $enabled,
                'class'    => 'inline-select xs',
            );

            $settings['general']['fields']['data_type'] = array(
                'title'    => __( 'Data Type', 'cf7apps' ),
                'type'     => 'select',
                'options'  => array( 'json' => __( 'JSON', 'cf7apps' ), 'form' => __( 'Form Data', 'cf7apps' ) ),
                'default'  => 'json',
                'disabled' => ! $enabled,
                'class'    => 'inline-select xs',
            );

            $settings['general']['fields']['headers'] = array(
                'title'       => __( 'Request Headers', 'cf7apps' ),
                'type'        => 'textarea',
                'default'     => '{"Content-Type":"application/json"}',
                'placeholder' => '{"Authorization":"Bearer TOKEN"}',
                'disabled'    => ! $enabled,
                'help'        => __( 'Optional JSON object of headers to send with the webhook request.', 'cf7apps' ),
                'collapsible' => true,
                'collapsed_button_color' => '#468db4',
                'pre_text'    => sprintf( __( 'When you need authentication/authorization.<br>You can add %s to your webhook request.', 'cf7apps' ), '<a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers" target="_blank" style="color: #468db4; text-decoration: none;"><u>HTTP Headers</u></a>' ),
                'post_text'   => __( 'One header by line, separated by colon. Example: Authorization: Bearer 99999999999999999999', 'cf7apps' ),
            );

            $settings['general']['fields']['payload'] = array(
                'title'       => __( 'Request Body', 'cf7apps' ),
                'type'        => 'textarea',
                'default'     => '{"form_id":"{{form_id}}","fields":{{fields}}}',
                'disabled'    => ! $enabled,
                'help'        => __( 'Use {{form_id}} and {{fields}} placeholders.', 'cf7apps' ),
                'collapsible' => true,
                'collapsed_button_color' => '#468db4',
                'pre_text'    => __( 'When you need customize your request.<br><br><strong style="color: #A61B1B;">BE CAREFUL!</strong> This can break your integration (check your quotes).<br>REMEMBER: You can change field name with webhook config: [email* your_email webhook:email]', 'cf7apps' ),
                'post_text'   => sprintf( __( 'To create your own body, you can replace values like in mail body (case sensitive).<br>Example: {"message": "Sir [your-name] from [your-city]." }<br><br>We will send your form data as JSON:<br><br><textarea style="width: 500px; min-height: 150px; font-family: monospace; background: #f8f9fa; border: 1px solid #ddd; padding: 10px; pointer-events: none;" readonly>%s</textarea><br><br>This is just an example of field names and will not reflect actual data or customizations.', 'cf7apps' ), "{\n    \"your-name\": \"??????\",\n    \"your-email\": \"??????\",\n    \"your-subject\": \"??????\",\n    \"your-message\": \"??????\"\n}" ),
            );

            $settings['general']['fields']['save_settings'] = array(
                'type'     => 'save_button',
                'text'     => __( 'Save Settings', 'cf7apps' ),
                'class'    => 'button-primary',
                'disabled' => ! $enabled,
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
            
            // Set is_enabled to default (true) when form has no saved is_enabled value
            if ( isset( $settings['admin_settings']['general']['fields']['is_enabled'] ) ) {
                if ( ! isset( $form_settings[ $this->id ]['is_enabled'] ) ) {
                    // Form has no saved is_enabled value, use default (true)
                    $settings['admin_settings']['general']['fields']['is_enabled']['checked'] = true;
                }
            }
            
            // Check if form has custom settings (excluding is_enabled toggle)
            $has_custom_settings = ! empty( $form_settings ) && isset( $form_settings[ $this->id ] ) && (
                isset( $form_settings[ $this->id ]['webhook_url'] ) ||
                isset( $form_settings[ $this->id ]['method'] ) ||
                isset( $form_settings[ $this->id ]['data_type'] ) ||
                isset( $form_settings[ $this->id ]['headers'] ) ||
                isset( $form_settings[ $this->id ]['payload'] )
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
                // Skip notice, save_button, and is_enabled (already handled above)
                if ( in_array( $field_key, array( 'notice', 'save_settings', 'is_enabled' ) ) ) {
                    continue;
                }
                
                // Only populate if field doesn't have a saved value
                if ( ! isset( $form_settings[ $this->id ][ $field_key ] ) && isset( $global_settings[ $field_key ] ) ) {
                    if ( $field['type'] == 'checkbox' ) {
                        $settings['admin_settings']['general']['fields'][ $field_key ]['checked'] = ( $global_settings[ $field_key ] == '1' || $global_settings[ $field_key ] === true || $global_settings[ $field_key ] === 1 );
                    } elseif ( $field['type'] == 'select' ) {
                        $settings['admin_settings']['general']['fields'][ $field_key ]['selected'] = $global_settings[ $field_key ];
                    } else {
                        $settings['admin_settings']['general']['fields'][ $field_key ]['value'] = $global_settings[ $field_key ];
                    }
                }
            }
            
            return $settings;
        }

        /**
         * Register this app
         *
         * @param array $apps
         * @return array
         */
        public static function initialize_module( $apps ) {
            $apps[] = __CLASS__;

            return $apps;
        }

        /**
         * Run: register ajax and enqueue
         */
        private function run() {
            add_action( 'wpcf7_mail_sent', array( $this, 'handle_webhook_submission' ), 10, 2 );

        }



        /**
         * Handle webhook submission (following redirection pattern)
         */
        public function handle_webhook_submission( $contact_form, $result = null ) {
      
            // Check if submission was successful
            if ( $result && isset( $result['status'] ) && 'mail_sent' !== $result['status'] ) {
                return;
            }

            $form_id = $contact_form->id();
 
            if ( ! $form_id ) {
                return;
            }

            // Check if webhook app is enabled globally
            if ( ! $this->get_option( 'is_enabled' ) ) {
                return;
            }
 
            // Get individual form settings
            $individual_settings = $this->get_individual_option( $form_id );

            // Check if global settings are enabled (used as fallback when a form
            // does not have its own custom settings).
            $global_settings_enabled = $this->get_option( 'global_settings' );
            
            // Check if form has custom settings (excluding is_enabled toggle)
            $has_custom_settings = ! empty( $individual_settings ) && (
                isset( $individual_settings['webhook_url'] ) ||
                isset( $individual_settings['method'] ) ||
                isset( $individual_settings['data_type'] ) ||
                isset( $individual_settings['headers'] ) ||
                isset( $individual_settings['payload'] )
            );

            // Determine which settings to use
            if ( $has_custom_settings ) {
                // Form has custom settings - use individual settings
                $webhook_settings = $individual_settings;
                
                // Check if form toggle is enabled
                $form_toggle_enabled = isset( $individual_settings['is_enabled'] ) && $individual_settings['is_enabled'];
                
                if ( ! $form_toggle_enabled ) {
                    // Form toggle is disabled - skip this form
                    return;
                }
            } else {
                // Form has no custom settings - fall back to global settings
                // only when global settings are enabled.
                if ( ! $global_settings_enabled ) {
                    return;
                }

                $global_settings = $this->get_option( null );
                
                if ( empty( $global_settings ) ) {
                    return;
                }
                
                // Use global settings
                $webhook_settings = $global_settings;
                
                // Check if form has explicitly disabled webhook (even without custom settings)
                if ( isset( $individual_settings['is_enabled'] ) && ! $individual_settings['is_enabled'] ) {
                    // Form toggle is explicitly disabled - skip this form
                    return;
                }
                
                // Default to enabled when using global settings
                $webhook_settings['is_enabled'] = true;
            }

            // Ensure webhook is enabled in the selected settings
            if ( empty( $webhook_settings ) || ! isset( $webhook_settings['is_enabled'] ) || ! $webhook_settings['is_enabled'] ) {
                return;
            }

            $webhook_settings = wp_parse_args( $webhook_settings, $this->get_default_settings() );

            // Get form submission data
            $submission = WPCF7_Submission::get_instance();
            if ( ! $submission ) {
                return;
            }

            $posted_data = $submission->get_posted_data();
            if ( ! $posted_data ) {
                return;
            }
          
            $webhook_data = $this->prepare_webhook_data( $form_id, $posted_data, $webhook_settings );

            // Send webhook request
            $this->send_webhook_request( $webhook_settings, $webhook_data );
        }

        /**
         * Prepare webhook data based on settings
         *
         * @since 1.0.0
         * @param int $form_id The form ID
         * @param array $posted_data The form submission data
         * @param array $settings The webhook settings
         * @return array Prepared webhook data
         */
        private function prepare_webhook_data( $form_id, $posted_data, $settings ) {
            $webhook_data = array();

            // Apply field name mapping if provided
            $mapped_data = $this->apply_field_mapping( $posted_data, $form_id );

            // Use custom payload template if provided
            if ( ! empty( $settings['payload'] ) ) {
                $payload_template = $settings['payload'];
                
                // Replace placeholders
                $payload_template = str_replace( '{{form_id}}', $form_id, $payload_template );
                $payload_template = str_replace( '{{fields}}', wp_json_encode( $mapped_data ), $payload_template );
                
                // Replace individual field placeholders
                foreach ( $mapped_data as $field_name => $field_value ) {
                    $placeholder = '[' . $field_name . ']';
                    $payload_template = str_replace( $placeholder, $field_value, $payload_template );
                }
                
                // Try to decode as JSON, fallback to raw template
                $decoded_payload = json_decode( $payload_template, true );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $webhook_data = $decoded_payload;
                } else {
                    // If not valid JSON, use as raw string
                    $webhook_data = $payload_template;
                }
            } else {
                // Default payload structure
                $webhook_data = array(
                    'form_id'    => $form_id,
                    'fields'     => $mapped_data,
                    'timestamp'  => current_time( 'mysql' ),
                    'site_url'   => home_url(),
                );
            }

            return $webhook_data;
        }

        /**
         * Apply field name mapping based on form configuration
         *
         * @since 1.0.0
         * @param array $posted_data The form submission data
         * @param int $form_id The form ID
         * @return array Mapped data
         */
        private function apply_field_mapping( $posted_data, $form_id ) {
            $mapped_data = $posted_data;

            // Get the form object to check for field mappings
            $form = wpcf7_contact_form( $form_id );
            if ( ! $form ) {
                return $mapped_data;
            }

            $form_content = $form->prop( 'form' );
            
            // Look for webhook field mappings in the form content
            // Pattern: [field_type* field_name webhook:mapped_name]
            preg_match_all( '/\[([^\]]+)\s+([^\s]+)\s+webhook:([^\]]+)\]/', $form_content, $matches, PREG_SET_ORDER );
            
            if ( ! empty( $matches ) ) {
                foreach ( $matches as $match ) {
                    $original_field = $match[2];
                    $mapped_field = $match[3];
                    
                    if ( isset( $mapped_data[ $original_field ] ) ) {
                        // Rename the field
                        $mapped_data[ $mapped_field ] = $mapped_data[ $original_field ];
                        unset( $mapped_data[ $original_field ] );
                    }
                }
            }

            return $mapped_data;
        }

        /**
         * Send webhook request
         *
         * @since 1.0.0
         * @param array $settings The webhook settings
         * @param array $data The data to send
         */
        private function send_webhook_request( $settings, $data ) {
                $webhook_url = $settings['webhook_url'] ?? '';
                if ( empty( $webhook_url ) ) {
                    return;
                }

                $method = strtoupper( $settings['method'] ?? 'POST' );
                $data_type = isset( $settings['data_type'] ) ? $settings['data_type'] : 'json';

                // Prepare headers and allow data-type to control Content-Type unless user provided one
                $headers = $this->prepare_headers( $settings );

                // Detect whether user provided Content-Type in settings['headers']
                $user_provided_content_type = false;
                if ( ! empty( $settings['headers'] ) ) {
                    $custom = $settings['headers'];
                    if ( is_string( $custom ) ) {
                        $decoded = json_decode( $custom, true );
                    } else {
                        $decoded = $custom;
                    }

                    if ( is_array( $decoded ) ) {
                        foreach ( $decoded as $hk => $hv ) {
                            if ( strtolower( $hk ) === 'content-type' ) {
                                $user_provided_content_type = true;
                                break;
                            }
                        }
                    }
                }

                if ( ! $user_provided_content_type ) {
                    if ( 'form' === $data_type && 'GET' !== $method ) {
                        $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                    } else {
                        $headers['Content-Type'] = 'application/json';
                    }
                }

                $args = array(
                    'method'  => $method,
                    'headers' => $headers,
                    'timeout' => 30,
                );

                // Add basic auth if provided
                if ( ! empty( $settings['username'] ) && ! empty( $settings['password'] ) ) {
                    $args['headers']['Authorization'] = 'Basic ' . base64_encode( $settings['username'] . ':' . $settings['password'] );
                }

                // Build request depending on method and data type
                if ( 'GET' === $method ) {
                    // Send data as query parameters
                    if ( is_array( $data ) ) {
                        $query = http_build_query( $data );
                    } else {
                        $query = 'payload=' . rawurlencode( $data );
                    }

                    $url = $webhook_url . ( strpos( $webhook_url, '?' ) === false ? '?' : '&' ) . $query;
                    $response = wp_remote_request( $url, $args );
                } else {
                    // Non-GET methods: POST/PUT etc.
                    if ( 'json' === $data_type ) {
                        $args['body'] = is_string( $data ) ? $data : wp_json_encode( $data );
                    } else {
                        // form data: WP HTTP will format array body as application/x-www-form-urlencoded
                        if ( is_array( $data ) ) {
                            $args['body'] = $data;
                        } else {
                            // try to decode if JSON string
                            $decoded = json_decode( $data, true );
                            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                                $args['body'] = $decoded;
                            } else {
                                // send raw string as body
                                $args['body'] = $data;
                            }
                        }
                    }

                    $response = wp_remote_request( $webhook_url, $args );
                }

                // Log the response for debugging (optional)
                if ( is_wp_error( $response ) ) {
                    error_log( 'Webhook request failed: ' . $response->get_error_message() );
                } else {
                    $response_code = wp_remote_retrieve_response_code( $response );
                    $response_body = wp_remote_retrieve_body( $response );
                }
        }

        /**
         * Prepare headers for webhook request
         *
         * @since 1.0.0
         * @param array $settings The webhook settings
         * @return array Headers array
         */
        private function prepare_headers( $settings ) {
            $headers = array(
                'Content-Type' => 'application/json',
            );

            // Add custom headers if provided
            if ( ! empty( $settings['headers'] ) ) {
                $custom_headers = $settings['headers'];
                
                // If headers is a JSON string, decode it
                if ( is_string( $custom_headers ) ) {
                    $decoded_headers = json_decode( $custom_headers, true );
                    if ( json_last_error() === JSON_ERROR_NONE ) {
                        $custom_headers = $decoded_headers;
                    }
                }

                // If headers is an array, merge with defaults
                if ( is_array( $custom_headers ) ) {
                    $headers = array_merge( $headers, $custom_headers );
                }
            }

            return $headers;
        }

    }

    add_filter( 'cf7apps_apps', array( CF7Apps_Webhook::class, 'initialize_module' ) );
endif;
