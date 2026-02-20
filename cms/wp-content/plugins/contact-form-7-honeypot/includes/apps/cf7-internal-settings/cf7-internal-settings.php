<?php
/**
 * CF7Apps Internal Settings Apps Class
 *
 * @since 3.2.0
 * @package CF7Apps
 */
if ( ! class_exists( 'CF7Apps_Internal_Settings_Apps' ) ) :
    /**
     * CF7Apps Internal Settings Apps Class
     *
     * @since 3.2.0
     */
    class CF7Apps_Internal_Settings_Apps {
        /**
         * The singleton instance of the class.
         *
         * @since 3.2.0
         * @var CF7Apps_Internal_Settings_Apps $instance The single instance of the class.
         */
        private static $instance;

        /**
         * Private constructor to prevent direct instantiation.
         *
         * @since 3.2.0
         */
        private function __construct() {
            if ( isset( $_GET['page'] ) ) {
                // Run on existing form edit screen (page=wpcf7&post=ID)
                if ( 'wpcf7' === $_GET['page'] && isset( $_GET['post'] ) ) {
                    $this->run();
                }

                // Also run on "Add New" form screen (page=wpcf7-new, no post ID yet)
                if ( 'wpcf7-new' === $_GET['page'] ) {
                    $this->run();
                }
            }
        }

        /**
         * Run the main functionality of the class.
         *
         * @since 3.2.0
         */
        private function run() {
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        }

        /**
         * Enqueue admin scripts and styles.
         *
         * @since 3.2.0
         * @param string $hook The current admin page hook.
         */
        public function admin_enqueue_scripts( $hook ) {
            if ( 'toplevel_page_wpcf7' === $hook  || 'contact_page_wpcf7-new' === $hook ) {

                $form_id  = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
                $options                 = get_option( 'cf7apps_settings' );
                $redirection_app_enabled = false;
                $webhook_app_enabled     = false;

                if ( ! empty( $options ) ) {
                    if ( isset( $options['cf7-redirection'] ) ) {
                        if ( isset( $options['cf7-redirection']['is_enabled'] ) && $options['cf7-redirection']['is_enabled'] ) {
                            $redirection_app_enabled = true;
                        }
                    }
                    if ( isset( $options['webhook'] ) ) {
                        if ( isset( $options['webhook']['is_enabled'] ) && $options['webhook']['is_enabled'] ) {
                            $webhook_app_enabled = true;
                        }
                    }
                }

                $dir_url  = CF7APPS_PLUGIN_DIR_URL . '/includes/apps/cf7-internal-settings/';
                $dir_path = CF7APPS_PLUGIN_DIR . '/includes/apps/cf7-internal-settings/';
                wp_enqueue_style( 'cf7apps-gradient', $dir_url . 'assets/css/gradient.css', array(), CF7APPS_VERSION );
                wp_enqueue_script( 'enqueue-wrapper', $dir_url . 'assets/js/enqueue-wrapper.js', array( 'jquery' ), CF7APPS_VERSION, true );
                wp_localize_script(
                    'enqueue-wrapper',
                    'cf7appsWrapperObjects',
                    array(
                        'cf7appsRedirectionEnabled' => $redirection_app_enabled,
                        'cf7appsWebhookEnabled'     => $webhook_app_enabled,
                    )
                );

                if ( file_exists( $dir_path . 'build/index.asset.php' ) ) {
                    $asset = include $dir_path . 'build/index.asset.php';

                    wp_enqueue_style(
                        'cf7apps-internal-settings-app',
                        $dir_url . 'build/index.css',
                        array_filter(
                            $asset['dependencies'],
                            function ( $dependency ) {
                                return wp_style_is( $dependency, 'registered' );
                            }
                        ),
                        $asset['version']
                    );

                    wp_enqueue_script(
                        'cf7apps-internal-settings-app',
                        $dir_url . 'build/index.js',
                        array_filter(
                            $asset['dependencies'],
                            function ( $dependency ) {
                                return wp_script_is( $dependency, 'registered' );
                            }
                        ),
                        $asset['version'],
                        true
                    );

                    wp_localize_script(
                        'cf7apps-internal-settings-app',
                        'CF7AppsInternalSettings',
                        array(
                            'assetsURL' => CF7APPS_PLUGIN_DIR_URL . '/assets/',
                            'restURL'   => rest_url(),
                            'nonce'     => wp_create_nonce( 'wp_rest' ),
                            'formID'    => $form_id,
                            'appIndexURL' => admin_url( 'admin.php?page=cf7apps' ),
                            'pluginVersion' => CF7APPS_VERSION,
                        )
                    );
                }
            }
        }

        /**
         * Get the singleton instance of the class.
         *
         * @since 3.2.0
         * @return CF7Apps_Internal_Settings_Apps
         */
        public static function get_instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }
    }

    CF7Apps_Internal_Settings_Apps::get_instance();
endif;