<?php

abstract class CF7Apps_App {

    /**
     * App ID
     * 
     * @since 3.0.0
     */
    public $id;

    /**
     * Priority
     * 
     * @since 3.0.0
     */
    public $priority = 999;

    /**
     * App Title
     * 
     * @since 3.0.0
     */
    public $title;

    /**
     * App Description
     * 
     * @since 3.0.0
     */
    public $description;

    /**
     * App Icon
     * 
     * @since 3.0.0
     */
    public $icon;

    /**
     * Has Settings
     * 
     * @since 3.0.0
     */
    public $has_admin_settings = false;

    /**
     * Has Internal Settings
     *
     * @since 3.2.0
     * @var bool $has_internal_settings Has Internal Settings
     */
    public $has_internal_settings = false;

    /**
     * Is Pro
     * 
     * @since 3.0.0
     */
    public $is_pro = false;

    /**
     * Is enabled by default
     * 
     * @since 3.0.0
     */
    public $by_default_enabled = false;

    /**
     * Documentation URL
     * 
     * @since 3.0.0
     */
    public $documentation_url;

    /**
     * Parent Menu
     * 
     * @since 3.0.0
     */
    public $parent_menu = false;

    /**
     * Setting Tabs
     * 
     * @since 3.0.0
     */
    public $setting_tabs = array();

    /**
     * Admin Settings
     * 
     * @since 3.0.0
     */
    public function admin_settings() {
        return array();
    }

    /**
     * Internal Settings
     *
     * @since 3.2.0
     * @return array
     */
    public function internal_settings() {
        return array();
    }

    /**
     * Saved Settings
     * 
     * @since 3.0.0
     */
    public $options = false;

    /**
     * Get Option
     * 
     * @param string $key Option key to get
     * 
     * @since 3.0.0
     */
    public function get_option( $key = false ) {
        $default_settings = array();
        $default_settings['is_enabled'] = $this->by_default_enabled;
        
        $options = get_option( 'cf7apps_settings', $default_settings );

        if( $options && ! empty( $options[$this->id] ) ) {
            if( $key ) {
                return isset( $options[$this->id][$key] ) ? $options[$this->id][$key] : false;
            }
            else {
                return $options[$this->id];
            }
        }

        return false;
    }

    public function get_individual_option( $id, $key = false ) {
        $settings = get_post_meta( $id, 'cf7apps_settings', true );

        if ( empty( $settings ) ) {
            return array();
        }

        if ( isset( $settings[ $this->id ] ) ) {
            if ( $key ) {
                if ( isset( $settings[ $this->id ][ $key ] ) ) {
                    return $settings[ $this->id ][ $key ];
                } else {
                    return false;
                }
            } else {
                return $settings[ $this->id ];
            }
        }

        return array();
    }

    /**
     * Get Settings
     * 
     * @since 3.0.0
     */
    public function get_settings() {
        $default_settings = array();
        $default_settings['is_enabled'] = $this->by_default_enabled;
        
        $this->options = get_option( 'cf7apps_settings', $default_settings );

        $settings = new StdClass;
        $settings->id = $this->id;
        $settings->priority = $this->priority;
        $settings->title = $this->title;
        $settings->description = $this->description;
        $settings->icon = $this->icon;
        $settings->has_admin_settings = $this->has_admin_settings;
        $settings->is_pro = $this->is_pro;
        $settings->by_default_enabled = $this->by_default_enabled;
        $settings->documentation_url = $this->documentation_url;
        $settings->parent_menu = $this->parent_menu;
        $settings->setting_tabs = $this->setting_tabs;
        $settings = (array)$settings;

        if( $this->has_admin_settings ) {
            $settings['admin_settings'] = $this->admin_settings();
            if( isset( $this->options[$this->id] ) ) {
                
                if( empty( $this->setting_tabs ) ) {
                    $setting_fields = $settings['admin_settings']['general']['fields'];
                        
                    foreach( $setting_fields as $field_key => $field ) {
                        if( array_key_exists( $field_key, $this->options[$this->id] ) ) {
                            // If data is saved, set the value
                            if ( ( $field['type'] == 'checkbox' || $field['type'] == 'radio' ) && $this->options[$this->id][$field_key] == '1' ) {
                                $settings['admin_settings']['general']['fields'][$field_key]['checked'] = true;
                            } 
                            elseif ( $field['type'] == 'select' ) {
                                $settings['admin_settings']['general']['fields'][$field_key]['selected'] = $this->options[$this->id][$field_key];
                            } 
                            else {
                                $settings['admin_settings']['general']['fields'][$field_key]['value'] = $this->options[$this->id][$field_key];
                            }
                        } else {
                            // Set default value
                            $_field = $settings['admin_settings']['general']['fields'][$field_key];

                            if( isset( $field['type'] ) ) {
                                if ( $field['type'] == 'checkbox' ) {
                                    $settings['admin_settings']['general']['fields'][$field_key]['checked'] = ( isset( $_field['default'] ) && $_field['default'] ) ? true : false;
                                }
                                elseif ( $field['type'] == 'select' ) {
                                    $settings['admin_settings']['general']['fields'][$field_key]['selected'] = ( isset( $_field['default'] ) ) ? $_field['default'] : '';
                                }
                                else {
                                    $settings['admin_settings']['general']['fields'][$field_key]['value'] = ( isset( $_field['default'] ) ) ? $_field['default'] : '';
                                }
                            }
                        }

                        if ( isset( $field['sub_fields'] ) ) {
                            foreach ( $field['sub_fields'] as $sub_field_key => $sub_field ) {
                                if ( array_key_exists( $sub_field_key, $this->options[$this->id] ) ) {
                                    // If data is saved, set the value
                                    if ( ( $sub_field['type'] == 'checkbox' || $sub_field['type'] == 'radio' ) && $this->options[$this->id][$sub_field_key] == '1' ) {
                                        $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['checked'] = true;
                                    }
                                    elseif ( $sub_field['type'] == 'select' ) {
                                        $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['selected'] = $this->options[$this->id][$sub_field_key];
                                    }
                                    else {
                                        $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['value'] = $this->options[$this->id][$sub_field_key];
                                    }
                                }
                                else {
                                    // Set default value
                                    $_sub_field = $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key];

                                    if( isset( $sub_field['type'] ) ) {
                                        if ( $sub_field['type'] == 'checkbox' || 'radio' === $sub_field['type'] ) {
                                            $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['checked'] = ( isset( $_sub_field['default'] ) && $_sub_field['default'] ) ? true : false;
                                        }
                                        elseif ( $sub_field['type'] == 'select' ) {
                                            $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['selected'] = ( isset( $_sub_field['default'] ) ) ? $_sub_field['default'] : '';
                                        }
                                        else {
                                            $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['value'] = ( isset( $_sub_field['default'] ) ) ? $_sub_field['default'] : '';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                else {
                    foreach( $this->setting_tabs as $tab_key => $tab_title ) {
                        $settings_tab_fields = $settings['admin_settings']['general']['fields'][$tab_key];

                        // Templates are managed in React
                        if( array_key_exists( 'template', $settings_tab_fields ) ) {
                            continue;
                        }
                        
                        foreach( $settings_tab_fields as $field_key => $field ) {
                            if( array_key_exists( $field_key, $this->options[$this->id] ) ) {
                                // If data is saved, set the value
                                if ( ( $field['type'] == 'checkbox' || $field['type'] == 'radio' ) && $this->options[$this->id][$field_key] == '1' ) {
                                    $settings['admin_settings']['general']['fields'][$tab_key][$field_key]['checked'] = true;
                                } 
                                elseif ( $field['type'] == 'select' ) {
                                    $settings['admin_settings']['general']['fields'][$tab_key][$field_key]['selected'] = $this->options[$this->id][$field_key];
                                } 
                                else {
                                    $settings['admin_settings']['general']['fields'][$tab_key][$field_key]['value'] = $this->options[$this->id][$field_key];
                                }
                            }
                            else {
                                // Set default value
                                $_field = $settings['admin_settings']['general']['fields'][$tab_key][$field_key];

                                if( isset( $field['type'] ) ) {
                                    if ( $field['type'] == 'checkbox' || 'radio' === $field['type'] ) {
                                        $settings['admin_settings']['general']['fields'][$tab_key][$field_key]['checked'] = ( isset( $_field['default'] ) && $_field['default'] ) ? true : false;
                                    }
                                    elseif ( $field['type'] == 'select' ) {
                                        $settings['admin_settings']['general']['fields'][$tab_key][$field_key]['selected'] = ( isset( $_field['default'] ) ) ? $_field['default'] : '';
                                    }
                                    else {
                                        $settings['admin_settings']['general']['fields'][$tab_key][$field_key]['value'] = ( isset( $_field['default'] ) ) ? $_field['default'] : '';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $is_enabled = isset( $this->options[$this->id] ) ? array( 'is_enabled' => $this->options[$this->id]['is_enabled'] ) : $default_settings;
        $settings = array_merge( $settings, $is_enabled );

        return $settings;
    }

    /**
     * Get Internal Settings
     *
     * @since 3.2.0
     */
    public function get_internal_settings( $form_id ) {
        $default_settings = array();
        $default_settings['is_enabled'] = $this->by_default_enabled;
        $settings = get_post_meta( $form_id, 'cf7apps_settings', true );
        if ( empty( $settings ) ) {
            $settings = array();
        }

        $this->options = $settings;
        // merge default settings to option
        $this->options = array_merge( $this->options, $default_settings );

        $settings = new StdClass;
        $settings->id = $this->id;
        $settings->priority = $this->priority;
        $settings->title = $this->title;
        $settings->description = $this->description;
        $settings->icon = $this->icon;
        $settings->has_internal_settings = $this->has_internal_settings;
        $settings->is_pro = $this->is_pro;
        $settings->by_default_enabled = $this->by_default_enabled;
        $settings->documentation_url = $this->documentation_url;
        $settings->parent_menu = $this->parent_menu;
        $settings->setting_tabs = $this->setting_tabs;
        $settings = (array)$settings;

        if ( $this->has_internal_settings ) {
            $settings['admin_settings'] = $this->internal_settings();
            if ( isset( $this->options[ $this->id ] ) ) {
                $setting_fields = $settings['admin_settings']['general']['fields'];

                foreach ( $setting_fields as $field_key => $field ) {
                    if ( array_key_exists( $field_key, $this->options[ $this->id ] ) ) {
                        if ( $field['type'] == 'checkbox' ) {
                            // Explicitly set checked to true or false based on saved value
                            $settings['admin_settings']['general']['fields'][ $field_key ]['checked'] = ( $this->options[ $this->id ][ $field_key ] == '1' || $this->options[ $this->id ][ $field_key ] === true || $this->options[ $this->id ][ $field_key ] === 1 );
                        } elseif ( $field['type'] == 'select' ) {
                            $settings['admin_settings']['general']['fields'][ $field_key ]['selected'] = $this->options[ $this->id ][ $field_key ];
                        } else {
                            $settings['admin_settings']['general']['fields'][ $field_key ]['value'] = $this->options[ $this->id ][ $field_key ];
                        }
                    } else {
                        $_field = $settings['admin_settings']['general']['fields'][ $field_key ];
                        if( isset( $field['type'] ) ) {
                            if ( $field['type'] == 'checkbox' ) {
                                $settings['admin_settings']['general']['fields'][ $field_key ]['checked'] = ( isset( $_field['default'] ) && $_field['default'] ) ? true : false;
                            } elseif ( $field['type'] == 'select' ) {
                                $settings['admin_settings']['general']['fields'][ $field_key ]['selected'] = ( isset( $_field['default'] ) ) ? $_field['default'] : '';
                            } else {
                                $settings['admin_settings']['general']['fields'][ $field_key ]['value'] = ( isset( $_field['default'] ) ) ? $_field['default'] : '';
                            }
                        }
                    }

                    if ( isset( $field['sub_fields'] ) ) {
                        foreach ( $field['sub_fields'] as $sub_field_key => $sub_field ) {
                            if ( array_key_exists( $sub_field_key, $this->options[$this->id] ) ) {
                                // If data is saved, set the value
                                if ( $sub_field['type'] == 'checkbox' || $sub_field['type'] == 'radio' ) {
                                    // Explicitly set checked to true or false based on saved value
                                    $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['checked'] = ( $this->options[$this->id][$sub_field_key] == '1' || $this->options[$this->id][$sub_field_key] === true || $this->options[$this->id][$sub_field_key] === 1 );
                                }
                                elseif ( $sub_field['type'] == 'select' ) {
                                    $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['selected'] = $this->options[$this->id][$sub_field_key];
                                }
                                else {
                                    $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['value'] = $this->options[$this->id][$sub_field_key];
                                }
                            }
                            else {
                                // Set default value
                                $_sub_field = $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key];

                                if( isset( $sub_field['type'] ) ) {
                                    if ( $sub_field['type'] == 'checkbox' || 'radio' === $sub_field['type'] ) {
                                        $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['checked'] = ( isset( $_sub_field['default'] ) && $_sub_field['default'] ) ? true : false;
                                    }
                                    elseif ( $sub_field['type'] == 'select' ) {
                                        $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['selected'] = ( isset( $_sub_field['default'] ) ) ? $_sub_field['default'] : '';
                                    }
                                    else {
                                        $settings['admin_settings']['general']['fields'][$field_key]['sub_fields'][$sub_field_key]['value'] = ( isset( $_sub_field['default'] ) ) ? $_sub_field['default'] : '';
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $settings;
    }
}