<?php
/**
 * Welcome / Maintenance Mode
 *
 * Aktivierung:  Agency Core → Maintenance Mode → "Welcome Mode aktiv"
 * Admin-Bypass: Eingeloggte Administratoren sehen immer die echte Website.
 * Footer-Seiten: Impressum, Datenschutz und andere whitelistete Seiten
 *                bleiben für alle Besucher erreichbar.
 *
 * @package Media_Lab_Agency_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( defined( 'MEDIALAB_MAINTENANCE_LOADED' ) ) return;
define( 'MEDIALAB_MAINTENANCE_LOADED', true );


// ─────────────────────────────────────────────────────────────────────────────
// ACF FIELD GROUP – Maintenance Mode Settings
// Hängt an der Sub-Page 'agency-core-maintenance' (registriert in acf-settings.php)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'acf/init', function () {

    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'      => 'group_maintenance_mode',
        'title'    => 'Maintenance Mode / Welcome Mode',
        'fields'   => array(

            // ── Toggle ──────────────────────────────────────────────────────
            array(
                'key'               => 'field_maintenance_active',
                'label'             => 'Welcome Mode aktiv',
                'name'              => 'maintenance_active',
                'type'              => 'true_false',
                'instructions'      => 'Wenn aktiv, sehen alle Besucher (außer Administratoren) die Welcome Page. Admins sehen immer die echte Website.',
                'ui'                => 1,
                'ui_on_text'        => 'Aktiv',
                'ui_off_text'       => 'Inaktiv',
                'default_value'     => 0,
            ),

            // ── Trennlinie ───────────────────────────────────────────────────
            array(
                'key'   => 'field_maintenance_divider_1',
                'label' => 'Inhalte',
                'name'  => '',
                'type'  => 'tab',
            ),

            // ── Headline ─────────────────────────────────────────────────────
            array(
                'key'           => 'field_maintenance_headline',
                'label'         => 'Überschrift',
                'name'          => 'maintenance_headline',
                'type'          => 'text',
                'default_value' => 'Wir arbeiten gerade für Sie',
                'placeholder'   => 'Wir arbeiten gerade für Sie',
            ),

            // ── Message ──────────────────────────────────────────────────────
            array(
                'key'           => 'field_maintenance_message',
                'label'         => 'Nachricht',
                'name'          => 'maintenance_message',
                'type'          => 'textarea',
                'rows'          => 3,
                'default_value' => 'Unsere Website wird gerade überarbeitet. Wir sind bald wieder für Sie da.',
                'placeholder'   => 'Unsere Website wird gerade überarbeitet.',
            ),

            // ── Wiederherstellungsdatum ───────────────────────────────────────
            array(
                'key'         => 'field_maintenance_date_label',
                'label'       => 'Datum-Label',
                'name'        => 'maintenance_date_label',
                'type'        => 'text',
                'placeholder' => 'Voraussichtlich wieder online:',
            ),
            array(
                'key'         => 'field_maintenance_date',
                'label'       => 'Datum (optional)',
                'name'        => 'maintenance_date',
                'type'        => 'text',
                'placeholder' => 'z.B. 01. Mai 2026',
                'instructions'=> 'Leer lassen wenn kein Datum angezeigt werden soll.',
            ),

            // ── Logo ─────────────────────────────────────────────────────────
            array(
                'key'           => 'field_maintenance_logo',
                'label'         => 'Logo (optional)',
                'name'          => 'maintenance_logo',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Leer lassen um das Standard-WordPress-Logo zu verwenden.',
            ),

            // ── Whitelist ────────────────────────────────────────────────────
            array(
                'key'   => 'field_maintenance_divider_2',
                'label' => 'Erreichbare Seiten',
                'name'  => '',
                'type'  => 'tab',
            ),

            array(
                'key'          => 'field_maintenance_whitelist',
                'label'        => 'Immer erreichbar (Whitelist)',
                'name'         => 'maintenance_whitelist',
                'type'         => 'relationship',
                'post_type'    => array( 'page' ),
                'filters'      => array( 'search' ),
                'return_format'=> 'id',
                'instructions' => 'Seiten die auch im Welcome Mode für alle Besucher erreichbar sind (z.B. Impressum, Datenschutz).',
                'min'          => 0,
                'max'          => 20,
            ),

        ),
        'location' => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'agency-core-maintenance',
                ),
            ),
        ),
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ) );
} );


// ─────────────────────────────────────────────────────────────────────────────
// REDIRECT-LOGIK
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'template_redirect', 'medialab_maintenance_redirect', 1 );

function medialab_maintenance_redirect(): void {

    // 1. ACF muss verfügbar sein
    if ( ! function_exists( 'get_field' ) ) return;

    // 2. Ist der Welcome Mode aktiv?
    $is_active = (bool) get_field( 'maintenance_active', 'option' );
    if ( ! $is_active ) return;

    // 3. Admins sehen immer die echte Website – kein Redirect
    if ( current_user_can( 'manage_options' ) ) return;

    // 4. Login-Seite nie weiterleiten (damit Admins sich einloggen können)
    if ( is_login() ) return;

    // 5. REST API und AJAX nie blockieren
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;

    // 6. WordPress-Cron nicht blockieren
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) return;

    // 7. Welcome-Page selbst nicht weiterleiten (Endlosschleife verhindern)
    $welcome_page_id = medialab_maintenance_get_welcome_page_id();
    if ( $welcome_page_id && is_page( $welcome_page_id ) ) return;

    // 8. Whitelist-Seiten nicht weiterleiten
    $whitelist = get_field( 'maintenance_whitelist', 'option' );
    if ( ! empty( $whitelist ) && is_page( $whitelist ) ) return;

    // 9. Feed/Sitemap nicht blockieren
    if ( is_feed() || is_robots() || ( function_exists( 'is_sitemap' ) && is_sitemap() ) ) return;

    // 10. Weiterleiten zur Welcome Page (oder Standard-Template)
    if ( $welcome_page_id ) {
        $welcome_url = get_permalink( $welcome_page_id );
        if ( $welcome_url && ! is_page( $welcome_page_id ) ) {
            wp_redirect( $welcome_url, 302 );
            exit;
        }
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// HILFSFUNKTIONEN
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Gibt die Post-ID der Seite zurück, der das "Welcome Page"-Template zugewiesen ist.
 */
function medialab_maintenance_get_welcome_page_id(): int {
    static $page_id = null;

    if ( $page_id === null ) {
        $pages = get_pages( array(
            'meta_key'   => '_wp_page_template',
            'meta_value' => 'page-templates/template-welcome.php',
            'number'     => 1,
            'post_status'=> 'publish',
        ) );
        $page_id = ! empty( $pages ) ? (int) $pages[0]->ID : 0;
    }

    return $page_id;
}

/**
 * Ist der Welcome Mode gerade aktiv?
 * Kann im Theme oder anderen Plugins verwendet werden.
 */
function medialab_maintenance_is_active(): bool {
    if ( ! function_exists( 'get_field' ) ) return false;
    return (bool) get_field( 'maintenance_active', 'option' );
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN-BAR INDIKATOR
// Zeigt Admins einen farbigen Hinweis wenn der Welcome Mode aktiv ist.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'admin_bar_menu', 'medialab_maintenance_admin_bar', 100 );

function medialab_maintenance_admin_bar( WP_Admin_Bar $wp_admin_bar ): void {

    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! medialab_maintenance_is_active() ) return;

    $wp_admin_bar->add_node( array(
        'id'    => 'medialab-maintenance-indicator',
        'title' => '🔧 Welcome Mode aktiv',
        'href'  => admin_url( 'admin.php?page=agency-core-maintenance' ),
        'meta'  => array(
            'title' => 'Welcome Mode ist aktiv – Besucher sehen die Welcome Page. Klicken um zu deaktivieren.',
        ),
    ) );

    // Inline-Style für den Indikator
    add_action( 'wp_head',    'medialab_maintenance_admin_bar_style' );
    add_action( 'admin_head', 'medialab_maintenance_admin_bar_style' );
}

function medialab_maintenance_admin_bar_style(): void {
    echo '<style>
        #wp-admin-bar-medialab-maintenance-indicator > .ab-item {
            background-color: #d63638 !important;
            color: #fff !important;
            font-weight: 600;
        }
        #wp-admin-bar-medialab-maintenance-indicator > .ab-item:hover {
            background-color: #b32d2e !important;
        }
    </style>' . "\n";
}


// ─────────────────────────────────────────────────────────────────────────────
// HTTP 503 STATUS für Suchmaschinen
// Teilt Crawlern mit, dass die Seite vorübergehend nicht verfügbar ist.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'template_redirect', 'medialab_maintenance_503', 2 );

function medialab_maintenance_503(): void {
    if ( ! medialab_maintenance_is_active() ) return;
    if ( current_user_can( 'manage_options' ) ) return;

    $welcome_page_id = medialab_maintenance_get_welcome_page_id();
    if ( $welcome_page_id && is_page( $welcome_page_id ) ) {
        // Welcome Page mit 503 ausliefern + Retry-After Header
        status_header( 503 );
        header( 'Retry-After: 86400' ); // 24 Stunden
    }
}