<?php
/**
 * ACF Settings Page + All Field Groups
 */

if (!defined('ABSPATH')) exit;

if (defined('MEDIALAB_ACF_SETTINGS_LOADED')) return;
define('MEDIALAB_ACF_SETTINGS_LOADED', true);


// ─────────────────────────────────────────────────────────────────
// 1. ELTERN-MENÜ  –  nativ via admin_menu
// ─────────────────────────────────────────────────────────────────
add_action('admin_menu', function () {
    add_menu_page(
        'Agency Core Settings',
        'Agency Core',
        'manage_options',
        'agency-core',
        '__return_null',
        'dashicons-admin-generic',
        81
    );
}, 5);

add_action('admin_menu', function () {
    remove_submenu_page('agency-core', 'agency-core');
}, 999);


// ─────────────────────────────────────────────────────────────────
// 1b. OAUTH-UNTERSEITE  –  native WP-Seite (kein ACF, eigener Callback)
//     Platziert zwischen "E-Mail / SMTP" (pos 6) und "Spam-Schutz" (pos 8)
// ─────────────────────────────────────────────────────────────────
add_action('admin_menu', function () {
    add_submenu_page(
        'agency-core',
        'E-Mail / OAuth',
        'E-Mail / OAuth',
        'manage_options',
        'agency-core-smtp-oauth',
        'medialab_render_smtp_oauth_page'
    );
}, 5);

/**
 * Render-Callback für die OAuth-Seite.
 * Nutzt render_oauth_section() aus inc/smtp-oauth.php.
 */
function medialab_render_smtp_oauth_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Settings-Update-Notice
    if ( isset( $_GET['settings-updated'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__( 'Einstellungen gespeichert.', 'media-lab-core' )
            . '</p></div>';
    }
    if ( isset( $_GET['ms365_disconnected'] ) ) {
        echo '<div class="notice notice-info is-dismissible"><p>'
            . esc_html__( 'Microsoft 365 Verbindung getrennt.', 'media-lab-core' )
            . '</p></div>';
    }
    if ( isset( $_GET['gws_connected'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__( 'Google Workspace erfolgreich verbunden.', 'media-lab-core' )
            . '</p></div>';
    }
    if ( isset( $_GET['gws_disconnected'] ) ) {
        echo '<div class="notice notice-info is-dismissible"><p>'
            . esc_html__( 'Google Workspace Verbindung getrennt.', 'media-lab-core' )
            . '</p></div>';
    }
    if ( isset( $_GET['gws_error'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>'
            . esc_html__( 'Google Workspace Verbindung fehlgeschlagen. Bitte Credentials prüfen.', 'media-lab-core' )
            . '</p></div>';
    }

    echo '<div class="wrap"><h1>' . esc_html__( 'E-Mail / OAuth', 'media-lab-core' ) . '</h1>';

    // OAuth-Abschnitt aus smtp-oauth.php rendern
    if ( class_exists( 'MediaLab_SMTP_OAuth' ) ) {
        global $medialab_smtp_oauth;
        if ( $medialab_smtp_oauth instanceof MediaLab_SMTP_OAuth ) {
            $medialab_smtp_oauth->render_oauth_section();
        }
    } else {
        echo '<div class="notice notice-warning"><p>'
            . esc_html__( 'smtp-oauth.php ist nicht geladen. Bitte prüfen ob die Datei in media-lab-agency-core.php per require_once eingebunden ist.', 'media-lab-core' )
            . '</p></div>';
    }

    echo '</div>';
}


// ─────────────────────────────────────────────────────────────────
// 2. ACF OPTIONS SUB-PAGES
// ─────────────────────────────────────────────────────────────────
add_action('acf/init', function () {
    if ( ! function_exists('acf_add_options_sub_page') ) return;

    $parent = 'agency-core';

    acf_add_options_sub_page(array(
        'page_title'  => 'Plugin Status',
        'menu_title'  => 'Plugin Status',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-plugin-status',
        'position'    => 1,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'Maintenance Mode / Wartungsmodus',
        'menu_title'  => 'Maintenance Mode / Wartungsmodus',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-maintenance',
        'position'    => 2,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'Logo / Globale Einstellungen',
        'menu_title'  => 'Logo / Globale Einstellungen',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-logo',
        'position'    => 3,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'Hero Image / Globale Einstellungen',
        'menu_title'  => 'Hero Image / Globale Einstellungen',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-hero',
        'position'    => 4,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'Cookie Consent',
        'menu_title'  => 'Cookie Consent',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-cookie-consent',
        'position'    => 5,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'E-Mail / SMTP',
        'menu_title'  => 'E-Mail / SMTP',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-smtp',
        'position'    => 6,
    ));

    // Position 7 = native WP-Seite "E-Mail / OAuth" (via admin_menu oben)

    acf_add_options_sub_page(array(
        'page_title'  => 'Spam-Schutz / E-Mail Obfuskierung',
        'menu_title'  => 'Spam-Schutz / E-Mail Obfuskierung',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-spam',
        'position'    => 8,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'Top Header / Kontaktdaten',
        'menu_title'  => 'Top Header / Kontaktdaten',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-top-header',
        'position'    => 9,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'Multi Language / Mehrsprachigkeit',
        'menu_title'  => 'Multi Language / Mehrsprachigkeit',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-multilang',
        'position'    => 10,
    ));

    acf_add_options_sub_page(array(
        'page_title'  => 'White Label / Agentur-Branding',
        'menu_title'  => 'White Label / Agentur-Branding',
        'parent_slug' => $parent,
        'capability'  => 'manage_options',
        'slug'        => 'agency-core-white-label',
        'position'    => 11,
    ));

}, 5);



// ─────────────────────────────────────────────────────────────────
// 3. FIELD GROUPS  –  Priorität 20
// ─────────────────────────────────────────────────────────────────
add_action('acf/init', function () {

    if (!function_exists('acf_add_local_field_group')) return;

    // ── Field Group: Plugin Status ────────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_plugin_status',
        'title'  => 'Plugin Status',
        'fields' => array(
            array(
                'key'     => 'field_plugin_info',
                'label'   => 'Plugin Status',
                'name'    => 'plugin_info',
                'type'    => 'message',
                'label'   => ' ',
                'message' => '<strong>Media Lab Agency Core</strong> &nbsp;·&nbsp; Version '
                           . MEDIALAB_CORE_VERSION
                           . ' &nbsp;<span style="color:#00a32a;">● Aktiv</span><br><br>'
                           . '<strong>Custom Post Types:</strong> Hero Slides, Team, Projekte, Testimonials, FAQs, Google Maps, Karussell, Services<br>'
                           . '<strong>Theme:</strong> ' . wp_get_theme()->get('Name'),
            ),
        ),
        'location' => array(array(array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-plugin-status',
        ))),
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));


    // ── Field Group: White Label ──────────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_white_label',
        'title'  => 'White Label / Agentur-Branding',
        'fields' => array(
            array(
                'key'           => 'field_wl_group',
                'label'         => 'White Label',
                'name'          => 'white_label',
                'type'          => 'group',
                'layout'        => 'block',
                'sub_fields'    => array(
                    array(
                        'key'           => 'field_wl_enabled',
                        'label'         => 'White Label aktivieren',
                        'name'          => 'enabled',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'instructions'  => 'Aktiviert das gesamte White-Label-Branding im WP-Backend.',
                    ),
                    // ── Login Screen ──────────────────────────────
                    array(
                        'key' => 'field_wl_login_tab', 'label' => 'Login Screen', 'name' => 'wl_login_tab',
                        'type' => 'message', 'message' => '<strong style="font-size:13px;">Login Screen</strong>',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_login_logo', 'label' => 'Login Logo', 'name' => 'login_logo',
                        'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium',
                        'instructions' => 'Ersetzt das WordPress-Logo. Empfohlen: SVG oder PNG transparent.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_login_logo_width', 'label' => 'Logo Breite', 'name' => 'login_logo_width',
                        'type' => 'number', 'default_value' => 200, 'min' => 80, 'max' => 400, 'append' => 'px',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_login_bg_color', 'label' => 'Hintergrundfarbe', 'name' => 'login_bg_color',
                        'type' => 'color_picker', 'default_value' => '#1d2327',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_login_bg_image', 'label' => 'Hintergrundbild (optional)', 'name' => 'login_bg_image',
                        'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium',
                        'instructions' => 'Überschreibt die Hintergrundfarbe. Empfohlen: 1920×1080px.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_login_primary', 'label' => 'Primärfarbe (Buttons, Links)', 'name' => 'login_primary',
                        'type' => 'color_picker', 'default_value' => '#2271b1',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_login_tab_title', 'label' => 'Browser-Tab Titel', 'name' => 'login_tab_title',
                        'type' => 'text', 'placeholder' => 'Anmelden – Meine Website',
                        'instructions' => 'Leer lassen = Standard WordPress-Titel.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    // ── Agentur-Branding ──────────────────────────
                    array(
                        'key' => 'field_wl_branding_tab', 'label' => 'Agentur-Branding', 'name' => 'wl_branding_tab',
                        'type' => 'message', 'message' => '<strong style="font-size:13px;">Agentur-Branding</strong>',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_agency_name', 'label' => 'Agentur Name', 'name' => 'agency_name',
                        'type' => 'text', 'placeholder' => 'Media Lab',
                        'instructions' => 'Wird im Dashboard-Widget, Footer und Admin-Bar verwendet.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_admin_bar_logo', 'label' => 'Admin-Bar Logo', 'name' => 'admin_bar_logo',
                        'type' => 'image', 'return_format' => 'id', 'preview_size' => 'thumbnail',
                        'instructions' => 'Ersetzt das WP-Logo in der Admin-Bar. Empfohlen: 28×28px.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_footer_text', 'label' => 'Footer-Text', 'name' => 'footer_text',
                        'type' => 'text', 'placeholder' => 'Realisiert von Media Lab',
                        'instructions' => 'Leer lassen = automatisch aus Agentur-Name generiert. HTML erlaubt.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    // ── Kontaktdaten ──────────────────────────────
                    array(
                        'key' => 'field_wl_contact_tab', 'label' => 'Kontaktdaten', 'name' => 'wl_contact_tab',
                        'type' => 'message', 'message' => '<strong style="font-size:13px;">Kontaktdaten (Dashboard-Widget)</strong>',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_contact_text', 'label' => 'Begrüßungstext', 'name' => 'contact_text',
                        'type' => 'textarea', 'rows' => 3,
                        'placeholder' => 'Willkommen im CMS! Bei Fragen stehen wir gerne zur Verfügung.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_contact_phone', 'label' => 'Telefon', 'name' => 'contact_phone',
                        'type' => 'text', 'placeholder' => '+43 1 234 567',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_contact_email', 'label' => 'E-Mail', 'name' => 'contact_email',
                        'type' => 'email', 'placeholder' => 'support@media-lab.at',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key' => 'field_wl_contact_url', 'label' => 'Website', 'name' => 'contact_url',
                        'type' => 'url', 'placeholder' => 'https://media-lab.at',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    // ── Menü-Sichtbarkeit ─────────────────────────
                    array(
                        'key' => 'field_wl_visibility_tab', 'label' => 'Menü-Sichtbarkeit', 'name' => 'wl_visibility_tab',
                        'type' => 'message', 'message' => '<strong style="font-size:13px;">Agency Core Menü – Sichtbarkeit</strong>',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                    array(
                        'key'           => 'field_wl_hide_menu_roles',
                        'label'         => 'Sichtbar nur für Rollen',
                        'name'          => 'hide_menu_roles',
                        'type'          => 'checkbox',
                        'choices'       => array(
                            'administrator' => 'Administrator',
                            'editor'        => 'Editor',
                            'author'        => 'Author',
                            'contributor'   => 'Contributor',
                            'subscriber'    => 'Subscriber',
                        ),
                        'instructions'  => 'Nur ausgewählte Rollen sehen den "Agency Core" Menüpunkt. Leer lassen = für alle sichtbar.',
                        'conditional_logic' => array(array(array('field' => 'field_wl_enabled', 'operator' => '==', 'value' => '1'))),
                    ),
                ),
            ),
        ),
        'location' => array(array(array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-white-label',
        ))),
        'menu_order'            => 1,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));


    // ── Field Group: Logo ────────────────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_logo',
        'title'  => 'Logo',
        'fields' => array(

            array(
                'key'           => 'field_logo_desktop',
                'label'         => 'Logo (Desktop)',
                'name'          => 'logo_desktop',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'instructions'  => 'Empfohlen: SVG oder PNG mit transparentem Hintergrund',
            ),

            array(
                'key'          => 'field_logo_desktop_width',
                'label'        => 'Logo Breite (Desktop)',
                'name'         => 'logo_desktop_width',
                'type'         => 'number',
                'default_value'=> 180,
                'min'          => 50,
                'max'          => 500,
                'append'       => 'px',
                'instructions' => 'Maximale Breite des Logos im Header',
            ),

            array(
                'key'           => 'field_logo_mobile',
                'label'         => 'Logo (Mobile)',
                'name'          => 'logo_mobile',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
                'instructions'  => 'Alternatives Logo für Mobile (z.B. Icon-Variante). Wenn leer, wird das Desktop-Logo verwendet.',
            ),

            array(
                'key'          => 'field_logo_mobile_width',
                'label'        => 'Logo Breite (Mobile)',
                'name'         => 'logo_mobile_width',
                'type'         => 'number',
                'default_value'=> 120,
                'min'          => 30,
                'max'          => 300,
                'append'       => 'px',
                'instructions' => 'Maximale Breite des Logos auf Mobile',
            ),

            // ── UI-Features ───────────────────────────────────────────────────
            array(
                'key'     => 'field_ui_features_heading',
                'label'   => ' ',
                'name'    => 'ui_features_heading',
                'type'    => 'message',
                'message' => '<strong style="font-size:13px;">UI-Features</strong>',
            ),

            array(
                'key'           => 'field_btt_enabled',
                'label'         => 'Back-to-Top Button',
                'name'          => 'btt_enabled',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'instructions'  => 'Zeigt einen Button zum schnellen Zurückspringen an den Seitenanfang.',
            ),

            array(
                'key'           => 'field_scroll_progress_enabled',
                'label'         => 'Scroll Progress Bar',
                'name'          => 'scroll_progress_enabled',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'instructions'  => 'Zeigt eine dünne Fortschritts-Linie am oberen Viewport-Rand.',
            ),

            array(
                'key'     => 'field_scroll_progress_scope',
                'label'   => 'Scroll Progress Bar – Anzeigebereich',
                'name'    => 'scroll_progress_scope',
                'type'    => 'select',
                'choices' => array(
                    'single'   => 'Nur Blog-Beiträge (single.php)',
                    'singular' => 'Beiträge + Seiten (single + page)',
                    'all'      => 'Alle Seiten (inkl. Archiv, Startseite)',
                ),
                'default_value'     => 'single',
                'instructions'      => 'Auf welchen Seitentypen die Scroll Progress Bar angezeigt wird.',
                'conditional_logic' => array(array(array(
                    'field'    => 'field_scroll_progress_enabled',
                    'operator' => '==',
                    'value'    => '1',
                ))),
            ),

            array(
                'key'           => 'field_dark_mode_enabled',
                'label'         => 'Dark Mode Toggle',
                'name'          => 'dark_mode_enabled',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 1,
                'instructions'  => 'Zeigt den Dark/Light-Mode-Umschalter im Frontend an.',
            ),

        ),
        'location' => array(array(array(
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'agency-core-logo',
        ))),
        'menu_order'            => 5,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));


    // ── Field Group: SMTP ─────────────────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_smtp',
        'title'  => 'E-Mail / SMTP',
        'fields' => array(

            array(
                'key'    => 'field_smtp_settings',
                'label'  => 'SMTP-Konfiguration',
                'name'   => 'smtp_settings',
                'type'   => 'group',
                'layout' => 'block',
                'sub_fields' => array(

                    array(
                        'key'           => 'field_smtp_enabled',
                        'label'         => 'SMTP aktivieren',
                        'name'          => 'enabled',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'instructions'  => 'Ersetzt den WordPress-Standard-Mailer (PHP mail())',
                    ),

                    array(
                        'key'         => 'field_smtp_host',
                        'label'       => 'SMTP Host',
                        'name'        => 'host',
                        'type'        => 'text',
                        'placeholder' => 'smtp.example.com',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_smtp_port',
                        'label'         => 'Port',
                        'name'          => 'port',
                        'type'          => 'number',
                        'default_value' => 587,
                        'instructions'  => '587 (TLS) · 465 (SSL) · 25 (unverschlüsselt)',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'     => 'field_smtp_encryption',
                        'label'   => 'Verschlüsselung',
                        'name'    => 'encryption',
                        'type'    => 'select',
                        'choices' => array('tls' => 'TLS (empfohlen)', 'ssl' => 'SSL', '' => 'Keine'),
                        'default_value' => 'tls',
                        'ui'      => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'         => 'field_smtp_username',
                        'label'       => 'Benutzername',
                        'name'        => 'username',
                        'type'        => 'text',
                        'placeholder' => 'user@example.com',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'         => 'field_smtp_password',
                        'label'       => 'Passwort',
                        'name'        => 'password',
                        'type'        => 'password',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'         => 'field_smtp_from_email',
                        'label'       => 'Absender E-Mail',
                        'name'        => 'from_email',
                        'type'        => 'email',
                        'placeholder' => 'noreply@example.com',
                        'instructions'=> 'Wenn leer: WordPress Admin-E-Mail',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'         => 'field_smtp_from_name',
                        'label'       => 'Absender Name',
                        'name'        => 'from_name',
                        'type'        => 'text',
                        'placeholder' => 'Meine Website',
                        'instructions'=> 'Wenn leer: WordPress Site-Name',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'          => 'field_smtp_debug',
                        'label'        => 'Debug-Modus',
                        'name'         => 'smtp_debug',
                        'type'         => 'true_false',
                        'ui'           => 1,
                        'default_value'=> 0,
                        'instructions' => 'Gibt SMTP-Kommunikation im Quelltext aus – nur für Fehlersuche aktivieren!',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'     => 'field_smtp_test_notice',
                        'label'   => 'Test-Mail',
                        'name'    => 'smtp_test_notice',
                        'type'    => 'message',
                        'label'   => ' ',
                        'message' => '<button type="button" id="medialab-smtp-test" class="button button-secondary">Test-Mail senden</button>
                                      <input type="email" id="medialab-smtp-test-to" placeholder="' . esc_attr(get_option('admin_email')) . '" style="margin-left:8px;width:260px;" class="regular-text">
                                      <span id="medialab-smtp-test-result" style="margin-left:10px;font-weight:600;"></span>',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_smtp_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'     => 'field_smtp_oauth_hint',
                        'label'   => ' ',
                        'name'    => 'smtp_oauth_hint',
                        'type'    => 'message',
                        'message' => '<p style="margin:0;padding:10px 12px;background:#f0f6fc;border-left:3px solid #2271b1;">'
                                   . '🔐 <strong>OAuth2 (Microsoft 365 / Google Workspace)</strong> wird unter '
                                   . '<a href="' . admin_url('admin.php?page=agency-core-smtp-oauth') . '">Agency Core → E-Mail / OAuth</a> konfiguriert.</p>',
                    ),
                ),
            ),
        ),
        'location' => array(array(array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-smtp',
        ))),
        'menu_order'            => 20,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));


    // ── Field Group: E-Mail Obfuskierung ─────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_obfuscation',
        'title'  => 'Spam-Schutz / E-Mail Obfuskierung',
        'fields' => array(

            array(
                'key'    => 'field_obfuscation_settings',
                'label'  => 'Einstellungen',
                'name'   => 'obfuscation_settings',
                'type'   => 'group',
                'layout' => 'block',
                'sub_fields' => array(

                    array(
                        'key'           => 'field_obf_enabled',
                        'label'         => 'E-Mail Obfuskierung aktivieren',
                        'name'          => 'enabled',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'instructions'  => 'Kodiert E-Mail-Adressen so, dass Spam-Bots sie nicht auslesen können.',
                    ),

                    array(
                        'key'           => 'field_obf_auto_protect',
                        'label'         => 'Automatisch alle E-Mails im Content schützen',
                        'name'          => 'auto_protect',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'instructions'  => 'Schützt automatisch alle mailto:-Links und nackte E-Mail-Adressen in Seiteninhalten.',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_obf_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'     => 'field_obf_shortcode_hint',
                        'label'   => ' ',
                        'name'    => 'obf_shortcode_hint',
                        'type'    => 'message',
                        'message' => '<code>[obfuscate_email email="info@example.com" label="Kontakt aufnehmen"]</code><br>Schützt eine einzelne E-Mail-Adresse manuell.',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_obf_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    // ── Honeypot ──────────────────────────────────────────────
                    array(
                        'key'     => 'field_honeypot_separator',
                        'label'   => ' ',
                        'name'    => 'honeypot_separator',
                        'type'    => 'message',
                        'message' => '<strong style="font-size:13px;">Honeypot</strong>'
                                   . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                                   . 'DSGVO-konformer Basisschutz ohne externe Requests. Immer aktiv.</p>',
                    ),

                    array(
                        'key'           => 'field_honeypot_min_time',
                        'label'         => 'Mindest-Ausfüllzeit (Sekunden)',
                        'name'          => 'honeypot_min_time',
                        'type'          => 'number',
                        'default_value' => 3,
                        'min'           => 1,
                        'max'           => 30,
                        'step'          => 1,
                        'instructions'  => 'Formulare die schneller abgeschickt werden, gelten als Spam. Standard: 3 s.',
                        'wrapper'       => array( 'width' => '50' ),
                    ),

                    array(
                        'key'           => 'field_honeypot_max_age',
                        'label'         => 'Maximales Formular-Alter (Sekunden)',
                        'name'          => 'honeypot_max_age',
                        'type'          => 'number',
                        'default_value' => 86400,
                        'min'           => 300,
                        'max'           => 604800,
                        'step'          => 60,
                        'instructions'  => 'Formulare die älter als dieser Wert sind, werden abgelehnt. Standard: 86400 s (24 h).',
                        'wrapper'       => array( 'width' => '50' ),
                    ),

                    // ── Cloudflare Turnstile ───────────────────────────────────
                    array(
                        'key'     => 'field_turnstile_separator',
                        'label'   => ' ',
                        'name'    => 'turnstile_separator',
                        'type'    => 'message',
                        'message' => '<strong style="font-size:13px;">Cloudflare Turnstile</strong>'
                                   . '<p style="margin:.4rem 0 0;color:#666;font-size:12px;">'
                                   . 'Site- und Secret-Key unter '
                                   . '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com → Turnstile</a> erstellen.</p>',
                    ),

                    array(
                        'key'           => 'field_turnstile_enabled',
                        'label'         => 'Cloudflare Turnstile aktivieren',
                        'name'          => 'turnstile_enabled',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                    ),

                    array(
                        'key'          => 'field_turnstile_site_key',
                        'label'        => 'Site Key',
                        'name'         => 'turnstile_site_key',
                        'type'         => 'text',
                        'placeholder'  => '0x4AAAAAAA…',
                        'instructions' => 'Öffentlicher Schlüssel – wird im HTML ausgegeben.',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                        'wrapper' => array( 'width' => '50' ),
                    ),

                    array(
                        'key'          => 'field_turnstile_secret_key',
                        'label'        => 'Secret Key',
                        'name'         => 'turnstile_secret_key',
                        'type'         => 'text',
                        'placeholder'  => '0x4AAAAAAA…',
                        'instructions' => 'Privater Schlüssel – nur serverseitig verwendet.',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                        'wrapper' => array( 'width' => '50' ),
                    ),

                    array(
                        'key'           => 'field_turnstile_cf7',
                        'label'         => 'Contact Form 7',
                        'name'          => 'turnstile_cf7',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_turnstile_wp_login',
                        'label'         => 'WordPress Login',
                        'name'          => 'turnstile_wp_login',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_turnstile_woo_checkout',
                        'label'         => 'WooCommerce Checkout',
                        'name'          => 'turnstile_woo_checkout',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_turnstile_woo_login',
                        'label'         => 'WooCommerce Login',
                        'name'          => 'turnstile_woo_login',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_turnstile_woo_register',
                        'label'         => 'WooCommerce Registrierung',
                        'name'          => 'turnstile_woo_register',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_turnstile_appearance',
                        'label'         => 'Erscheinungsbild',
                        'name'          => 'turnstile_appearance',
                        'type'          => 'select',
                        'choices'       => array(
                            'auto'  => 'Auto (passt sich dem Theme an)',
                            'light' => 'Light',
                            'dark'  => 'Dark',
                        ),
                        'default_value' => 'auto',
                        'allow_null'    => 0,
                        'return_format' => 'value',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                        'wrapper' => array( 'width' => '33' ),
                    ),

                    array(
                        'key'           => 'field_turnstile_size',
                        'label'         => 'Größe',
                        'name'          => 'turnstile_size',
                        'type'          => 'select',
                        'choices'       => array(
                            'normal'   => 'Normal',
                            'compact'  => 'Compact',
                            'flexible' => 'Flexible (volle Breite)',
                        ),
                        'default_value' => 'normal',
                        'allow_null'    => 0,
                        'return_format' => 'value',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                        'wrapper' => array( 'width' => '33' ),
                    ),

                    array(
                        'key'           => 'field_turnstile_dsgvo_mode',
                        'label'         => 'DSGVO-Modus',
                        'name'          => 'turnstile_dsgvo_mode',
                        'type'          => 'select',
                        'choices'       => array(
                            'legitimate_interest' => 'Berechtigtes Interesse (empfohlen)',
                            'consent'             => 'Consent-abhängig (strenger)',
                        ),
                        'default_value' => 'legitimate_interest',
                        'allow_null'    => 0,
                        'return_format' => 'value',
                        'instructions'  => 'Berechtigtes Interesse: Widget lädt sofort. Consent-abhängig: erst nach Cookie-Zustimmung.',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                        'wrapper' => array( 'width' => '34' ),
                    ),

                    array(
                        'key'           => 'field_turnstile_consent_category',
                        'label'         => 'Consent-Kategorie',
                        'name'          => 'turnstile_consent_category',
                        'type'          => 'select',
                        'choices'       => array(
                            'necessary'  => 'Notwendig',
                            'statistics' => 'Statistik',
                            'marketing'  => 'Marketing',
                            'comfort'    => 'Komfort',
                        ),
                        'default_value' => 'necessary',
                        'allow_null'    => 0,
                        'return_format' => 'value',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_turnstile_dsgvo_mode', 'operator' => '==', 'value' => 'consent',
                        ))),
                        'wrapper' => array( 'width' => '50' ),
                    ),

                    // ── hCaptcha ──────────────────────────────────────────────
                    array(
                        'key'     => 'field_hcaptcha_separator',
                        'label'   => ' ',
                        'name'    => 'hcaptcha_separator',
                        'type'    => 'message',
                        'message' => '<strong style="font-size:13px;">hCaptcha</strong>',
                    ),

                    array(
                        'key'           => 'field_hcaptcha_enabled',
                        'label'         => 'hCaptcha aktivieren',
                        'name'          => 'hcaptcha_enabled',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'instructions'  => 'CAPTCHA-Schutz via hCaptcha. Keys unter hcaptcha.com holen.',
                    ),

                    array(
                        'key'          => 'field_hcaptcha_site_key',
                        'label'        => 'Site Key',
                        'name'         => 'hcaptcha_site_key',
                        'type'         => 'text',
                        'placeholder'  => '10000000-ffff-ffff-ffff-000000000001',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'          => 'field_hcaptcha_secret_key',
                        'label'        => 'Secret Key',
                        'name'         => 'hcaptcha_secret_key',
                        'type'         => 'text',
                        'placeholder'  => '0x0000000000000000000000000000000000000000',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_hcaptcha_cf7',
                        'label'         => 'Contact Form 7',
                        'name'          => 'hcaptcha_cf7',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_hcaptcha_wp_login',
                        'label'         => 'WordPress Login',
                        'name'          => 'hcaptcha_wp_login',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 1,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_hcaptcha_woo_checkout',
                        'label'         => 'WooCommerce Checkout',
                        'name'          => 'hcaptcha_woo_checkout',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_hcaptcha_woo_register',
                        'label'         => 'WooCommerce Registrierung',
                        'name'          => 'hcaptcha_woo_register',
                        'type'          => 'true_false',
                        'ui'            => 1,
                        'default_value' => 0,
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_hcaptcha_theme',
                        'label'         => 'Widget-Theme',
                        'name'          => 'hcaptcha_theme',
                        'type'          => 'select',
                        'choices'       => array( 'light' => 'Light', 'dark' => 'Dark' ),
                        'default_value' => 'light',
                        'allow_null'    => 0,
                        'return_format' => 'value',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),

                    array(
                        'key'           => 'field_hcaptcha_size',
                        'label'         => 'Widget-Größe',
                        'name'          => 'hcaptcha_size',
                        'type'          => 'select',
                        'choices'       => array(
                            'normal'    => 'Normal',
                            'compact'   => 'Compact',
                            'invisible' => 'Invisible',
                        ),
                        'default_value' => 'normal',
                        'allow_null'    => 0,
                        'return_format' => 'value',
                        'instructions'  => 'Invisible: kein sichtbares Widget, nur bei verdächtigem Verhalten ausgelöst.',
                        'conditional_logic' => array(array(array(
                            'field' => 'field_hcaptcha_enabled', 'operator' => '==', 'value' => '1',
                        ))),
                    ),
                ),
            ),
        ),
        'location' => array(array(array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-spam',
        ))),
        'menu_order'            => 25,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));


    // ── Field Group: Top Header ───────────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_top_header',
        'title'  => 'Top Header – Kontaktdaten',
        'fields' => array(

            array(
                'key'           => 'field_top_header_enable',
                'label'         => 'Top Header anzeigen',
                'name'          => 'top_header_enable',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
            ),

            array(
                'key'    => 'field_top_header_address',
                'label'  => 'Adresse',
                'name'   => 'top_header_address',
                'type'   => 'group',
                'layout' => 'block',
                'conditional_logic' => array(
                    array(array('field' => 'field_top_header_enable', 'operator' => '==', 'value' => '1')),
                ),
                'sub_fields' => array(
                    array('key' => 'field_address_enable',  'label' => 'Adresse anzeigen',    'name' => 'enable',    'type' => 'true_false', 'ui' => 1, 'default_value' => 1),
                    array('key' => 'field_address_street',  'label' => 'Straße & Hausnummer', 'name' => 'street',    'type' => 'text', 'placeholder' => 'Musterstraße 123',
                        'conditional_logic' => array(array(array('field' => 'field_address_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_address_city',    'label' => 'PLZ & Stadt',         'name' => 'city',      'type' => 'text', 'placeholder' => '12345 Musterstadt',
                        'conditional_logic' => array(array(array('field' => 'field_address_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_address_country', 'label' => 'Land (optional)',      'name' => 'country',   'type' => 'text', 'placeholder' => 'Österreich',
                        'conditional_logic' => array(array(array('field' => 'field_address_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_address_link',    'label' => 'Google Maps Link',     'name' => 'maps_link', 'type' => 'url',  'placeholder' => 'https://maps.google.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_address_enable', 'operator' => '==', 'value' => '1')))),
                ),
            ),

            array(
                'key'    => 'field_top_header_hours',
                'label'  => 'Öffnungszeiten',
                'name'   => 'top_header_hours',
                'type'   => 'group',
                'layout' => 'block',
                'conditional_logic' => array(
                    array(array('field' => 'field_top_header_enable', 'operator' => '==', 'value' => '1')),
                ),
                'sub_fields' => array(
                    array('key' => 'field_hours_enable', 'label' => 'Öffnungszeiten anzeigen', 'name' => 'enable', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1),
                    array('key' => 'field_hours_text',   'label' => 'Text',                    'name' => 'text',   'type' => 'text', 'placeholder' => 'Mo-Fr: 9-18 Uhr',
                        'conditional_logic' => array(array(array('field' => 'field_hours_enable', 'operator' => '==', 'value' => '1')))),
                ),
            ),

            array(
                'key'    => 'field_top_header_phone',
                'label'  => 'Telefon',
                'name'   => 'top_header_phone',
                'type'   => 'group',
                'layout' => 'block',
                'conditional_logic' => array(
                    array(array('field' => 'field_top_header_enable', 'operator' => '==', 'value' => '1')),
                ),
                'sub_fields' => array(
                    array('key' => 'field_phone_enable',  'label' => 'Telefon anzeigen',   'name' => 'enable',  'type' => 'true_false', 'ui' => 1, 'default_value' => 1),
                    array('key' => 'field_phone_number',  'label' => 'Telefonnummer',       'name' => 'number',  'type' => 'text', 'placeholder' => '+43 123 456789',
                        'conditional_logic' => array(array(array('field' => 'field_phone_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_phone_display', 'label' => 'Anzeige-Text (opt.)', 'name' => 'display', 'type' => 'text', 'placeholder' => '(0123) 456789',
                        'instructions' => 'Wenn leer, wird die Nummer direkt verwendet',
                        'conditional_logic' => array(array(array('field' => 'field_phone_enable', 'operator' => '==', 'value' => '1')))),
                ),
            ),

            array(
                'key'    => 'field_top_header_email',
                'label'  => 'E-Mail',
                'name'   => 'top_header_email',
                'type'   => 'group',
                'layout' => 'block',
                'conditional_logic' => array(
                    array(array('field' => 'field_top_header_enable', 'operator' => '==', 'value' => '1')),
                ),
                'sub_fields' => array(
                    array('key' => 'field_email_enable',  'label' => 'E-Mail anzeigen', 'name' => 'enable',  'type' => 'true_false', 'ui' => 1, 'default_value' => 1),
                    array('key' => 'field_email_address', 'label' => 'E-Mail Adresse',  'name' => 'address', 'type' => 'email', 'placeholder' => 'info@beispiel.at',
                        'conditional_logic' => array(array(array('field' => 'field_email_enable', 'operator' => '==', 'value' => '1')))),
                ),
            ),

            array(
                'key'    => 'field_top_header_social',
                'label'  => 'Social Media',
                'name'   => 'top_header_social',
                'type'   => 'group',
                'layout' => 'block',
                'conditional_logic' => array(
                    array(array('field' => 'field_top_header_enable', 'operator' => '==', 'value' => '1')),
                ),
                'sub_fields' => array(
                    array('key' => 'field_social_enable',    'label' => 'Social Media anzeigen', 'name' => 'enable',    'type' => 'true_false', 'ui' => 1, 'default_value' => 0),
                    array('key' => 'field_social_facebook',  'label' => 'Facebook',  'name' => 'facebook',  'type' => 'url', 'placeholder' => 'https://facebook.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_social_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_social_instagram', 'label' => 'Instagram', 'name' => 'instagram', 'type' => 'url', 'placeholder' => 'https://instagram.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_social_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_social_linkedin',  'label' => 'LinkedIn',  'name' => 'linkedin',  'type' => 'url', 'placeholder' => 'https://linkedin.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_social_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_social_twitter',   'label' => 'Twitter / X','name' => 'twitter',  'type' => 'url', 'placeholder' => 'https://twitter.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_social_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_social_youtube',   'label' => 'YouTube',   'name' => 'youtube',   'type' => 'url', 'placeholder' => 'https://youtube.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_social_enable', 'operator' => '==', 'value' => '1')))),
                    array('key' => 'field_social_xing',      'label' => 'Xing',      'name' => 'xing',      'type' => 'url', 'placeholder' => 'https://xing.com/...',
                        'conditional_logic' => array(array(array('field' => 'field_social_enable', 'operator' => '==', 'value' => '1')))),
                ),
            ),

            array(
                'key'    => 'field_top_header_style',
                'label'  => 'Styling',
                'name'   => 'top_header_style',
                'type'   => 'group',
                'layout' => 'block',
                'conditional_logic' => array(
                    array(array('field' => 'field_top_header_enable', 'operator' => '==', 'value' => '1')),
                ),
                'sub_fields' => array(
                    array(
                        'key'           => 'field_style_background',
                        'label'         => 'Hintergrundfarbe',
                        'name'          => 'background',
                        'type'          => 'select',
                        'choices'       => array('primary' => 'Primary Color', 'dark' => 'Dunkel', 'light' => 'Hell'),
                        'default_value' => 'primary',
                        'ui'            => 1,
                    ),
                    array(
                        'key'           => 'field_style_mobile',
                        'label'         => 'Mobile Verhalten',
                        'name'          => 'mobile',
                        'type'          => 'select',
                        'choices'       => array('show' => 'Immer anzeigen', 'hide' => 'Auf Mobile ausblenden', 'toggle' => 'Mit Toggle-Button'),
                        'default_value' => 'toggle',
                        'ui'            => 1,
                    ),
                ),
            ),

        ),
        'location' => array(array(array(
            'param'    => 'options_page',
            'operator' => '==',
            'value'    => 'agency-core-top-header',
        ))),
        'menu_order'            => 10,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));

    // ── Field Group: Mehrsprachigkeit ─────────────────────────────
    acf_add_local_field_group(array(
        'key'    => 'group_multi_language',
        'title'  => 'Mehrsprachigkeit',
        'fields' => array(

            array(
                'key'           => 'field_multilang_enable',
                'label'         => 'Mehrsprachigkeit aktivieren',
                'name'          => 'multilang_enable',
                'type'          => 'true_false',
                'ui'            => 1,
                'default_value' => 0,
                'instructions'  => 'Aktiviert Polylang-Integration, Language Switcher Shortcode und String-Übersetzungen.',
            ),

            array(
                'key'               => 'field_multilang_polylang_notice',
                'label'             => 'Hinweis',
                'name'              => 'multilang_polylang_notice',
                'type'              => 'message',
                'message'           => '<strong>Voraussetzung:</strong> Das Plugin <a href="'
                                     . admin_url('plugin-install.php?s=polylang&tab=search')
                                     . '" target="_blank">Polylang</a> muss installiert und aktiviert sein.',
                'conditional_logic' => array(
                    array(array('field' => 'field_multilang_enable', 'operator' => '==', 'value' => '1')),
                ),
            ),

            array(
                'key'               => 'field_multilang_default_language',
                'label'             => 'Standard-Sprache',
                'name'              => 'multilang_default_language',
                'type'              => 'text',
                'default_value'     => 'de',
                'placeholder'       => 'de',
                'instructions'      => 'ISO-Sprachcode, z.B. de, en, fr',
                'conditional_logic' => array(
                    array(array('field' => 'field_multilang_enable', 'operator' => '==', 'value' => '1')),
                ),
            ),

            array(
                'key'               => 'field_multilang_rtl_support',
                'label'             => 'RTL-Unterstützung aktivieren',
                'name'              => 'multilang_rtl_support',
                'type'              => 'true_false',
                'ui'                => 1,
                'default_value'     => 0,
                'instructions'      => 'CSS für Rechts-nach-Links-Sprachen (z.B. Arabisch, Hebräisch)',
                'conditional_logic' => array(
                    array(array('field' => 'field_multilang_enable', 'operator' => '==', 'value' => '1')),
                ),
            ),

        ),
        'location' => array(array(array(
            'param' => 'options_page', 'operator' => '==', 'value' => 'agency-core-multilang',
        ))),
        'menu_order'            => 30,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ));

}, 20);