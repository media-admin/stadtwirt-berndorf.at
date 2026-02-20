<?php

/**
 * Save App Settings
 * 
 * @param int $id
 * @param array $settings
 * 
 * @since 3.0.0
 */
if( ! function_exists( 'cf7apps_save_app_settings' ) ):
function cf7apps_save_app_settings( $id, $settings ) {
    $existing_settings = get_option( 'cf7apps_settings', false );

    if( $existing_settings ) {
        if( isset( $existing_settings[$id] ) ) {
            $existing_settings[$id] = array_merge( $existing_settings[$id], $settings );
        }
        else {
            $existing_settings[$id] = $settings;
        }
    }
    else {
        $existing_settings = array(
            $id => $settings
        );
    }
    
    update_option( 'cf7apps_settings', $existing_settings );

    return true;
}
endif;

/**
 * Migrate Legacy Settings
 * 
 * @since 3.0.0
 */
if( ! function_exists( 'cf7apps_migrate_legacy_settings' ) ):
function cf7apps_migrate_legacy_settings() {
    $honeypot4cf7_config = get_option( 'honeypot4cf7_config' );
    $cf7apps_settings = array();

    if( $honeypot4cf7_config ) {
        $cf7apps_settings['is_enabled'] = 1;
        $cf7apps_settings['store_value'] = ! empty( $honeypot4cf7_config['store_honeypot'] ) ? $honeypot4cf7_config['store_honeypot'] : '';
        $cf7apps_settings['global_placeholder'] = ! empty( $honeypot4cf7_config['placeholder'] ) ? $honeypot4cf7_config['placeholder'] : '';
        $cf7apps_settings['accessibility_msg'] = ! empty( $honeypot4cf7_config['accessibility_message'] ) ? $honeypot4cf7_config['accessibility_message'] : '';
        $cf7apps_settings['inline_css'] = ( ! empty( $honeypot4cf7_config['move_inline_css'][0] ) && $honeypot4cf7_config['move_inline_css'][0] == 'true' ) ? 1 : 0;
        $cf7apps_settings['enable_time_check'] = ( ! empty( $honeypot4cf7_config['timecheck_enabled'][0] ) && $honeypot4cf7_config['timecheck_enabled'][0] == 'true' ) ? 1 : 0;
        $cf7apps_settings['time_check'] = ! empty( $honeypot4cf7_config['timecheck_value'] ) ? $honeypot4cf7_config['timecheck_value'] : '';
        $cf7apps_settings['standard_auto_complete'] = ( ! empty( $honeypot4cf7_config['w3c_valid_autocomplete'][0] ) && $honeypot4cf7_config['w3c_valid_autocomplete'][0] == 'true' ) ? 1 : 0;
        $cf7apps_settings['accessibility_label'] = ( ! empty( $honeypot4cf7_config['nomessage'][0] && $honeypot4cf7_config['nomessage'][0] == 'true' ) ) ? 1 : 0;
        $cf7apps_settings['honeypot_count'] = ! empty( $honeypot4cf7_config['honeypot_count'] ) ? $honeypot4cf7_config['honeypot_count'] : 0;
        $cf7apps_settings['honeypot_install_date'] = ! empty( $honeypot4cf7_config['honeypot_install_date'] ) ? $honeypot4cf7_config['honeypot_install_date'] : time();
        $cf7apps_settings['honeypot_cf7_req_msg_dismissed'] = ! empty( $honeypot4cf7_config['honeypot_cf7_req_msg_dismissed'] ) ? $honeypot4cf7_config['honeypot_cf7_req_msg_dismissed'] : 0;
        $cf7apps_settings['honeypot4cf7_version'] = ! empty( $honeypot4cf7_config['honeypot4cf7_version'] ) ? $honeypot4cf7_config['honeypot4cf7_version'] : CF7APPS_VERSION;

        cf7apps_save_app_settings( 'honeypot', $cf7apps_settings );
        
        delete_option( 'honeypot4cf7_config' );
    }
}
endif;

/**
 * Get Default Settings
 * 
 * @since 3.0.0
 */
if( ! function_exists( 'cf7apps_get_default_settings' ) ):
function cf7apps_get_default_settings() {
    return array(
        'is_enabled'                        => 1,
        'store_value'                       => 0,
        'global_placeholder'                => '',
        'accessibility_msg'                 => '',
        'inline_css'                        => 0,
        'enable_time_check'                 => 0,
        'time_check'                        => 4,
        'standard_auto_complete'            => 0,
        'accessibility_label'               => 0,
        'honeypot_count'                    => 0,
        'honeypot_install_date'             => time(),
        'honeypot_cf7_req_msg_dismissed'    => 0,
        'honeypot4cf7_version'              => CF7APPS_VERSION
    );
}
endif;

if ( ! function_exists( 'cf7apps_get_post_types_options' ) ) :
    /**
     * Get Post Types Options
     *
     * @since 3.2.0
     * @return array
     */
    function cf7apps_get_post_types_options() {
        // Default: posts + pages (filterable).
        $default_post_types = array( 'post', 'page' );

        // Filterable list of post types for the dropdown.
        $post_type_slugs = apply_filters( 'cf7apps_redirection_post_types', $default_post_types );

        // Unique, non-empty slugs only.
        $post_type_slugs = array_values( array_filter( array_unique( (array) $post_type_slugs ) ) );

        $query_args = array(
            'post_type'      => $post_type_slugs,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );

        $options  = array();
        $post_ids = get_posts( $query_args );

        foreach ( $post_ids as $post_id ) {
            $title = get_post_field( 'post_title', $post_id );

            if ( '' === $title ) {
                /* translators: %d: post ID. */
                $title = sprintf( __( '(no title) (ID: %d)', 'cf7apps' ), $post_id );
            }

            $options[ $post_id ] = sprintf( '%s (ID: %d)', $title, $post_id );
        }

        return $options;
    }
endif;

if ( ! function_exists( 'cf7apps_save_internal_app_settings' ) ) :
    /**
     * Save Internal App Settings
     *
     * @since 3.2.0
     * @param string     $id App ID.
     * @param string|int $form_id CF7 Form ID.
     * @param array      $app_settings App Settings.
     *
     * @return bool
     */
    function cf7apps_save_internal_app_settings( $id, $form_id, $app_settings  ) {
        $settings = get_post_meta( $form_id, 'cf7apps_settings', true );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        if ( isset( $settings[ $id ] ) ) {
            $settings[ $id ] = array_merge( $settings[ $id ], $app_settings );
        } else {
            $settings[ $id ] = $app_settings;
        }

        return update_post_meta( $form_id, 'cf7apps_settings', $settings );
    }
endif;
