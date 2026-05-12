<?php
/**
 * Notifications CPT + ACF
 *
 * Änderungen seit 1.10.0:
 *  - `show_in_rest => true` → Gutenberg-Editor aktiv
 *  - `supports` um `'editor'` erweitert → echter Block-Editor für Notification-Inhalt
 *  - ACF-Feld `notification_message` (textarea) bleibt als Fallback erhalten.
 *    Wenn der Post Gutenberg-Inhalt (`post_content`) hat, wird dieser bevorzugt.
 *    Bestehende Notifications (nur textarea) funktionieren weiterhin.
 *  - Neues ACF-Feld `notification_layout`: Banner-mit-Bild, Kompakt, Standard
 *  - `notification_message` in den einspaltigen Feldern jetzt optional
 *    und als "Kurztext-Fallback (alt)" gekennzeichnet.
 *
 * @package MediaLab_Core
 * @since   1.0.0
 * @updated 1.10.0  Gutenberg-Editor aktiviert
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// CPT REGISTRIERUNG
// =============================================================================

add_action( 'init', function () {
    register_post_type( 'notification', array(
        'labels' => array(
            'name'               => 'Notifications',
            'singular_name'      => 'Notification',
            'add_new_item'       => 'Neue Notification',
            'edit_item'          => 'Notification bearbeiten',
            'new_item'           => 'Neue Notification',
            'view_item'          => 'Notification ansehen',
            'search_items'       => 'Notifications suchen',
            'not_found'          => 'Keine Notifications gefunden',
            'not_found_in_trash' => 'Keine Notifications im Papierkorb',
        ),
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'menu_icon'         => 'dashicons-bell',
        'menu_position'     => 25,
        'supports'          => array( 'title', 'editor' ), // ← 'editor' neu
        'show_in_rest'      => true,                       // ← Gutenberg aktiv
        'rest_base'         => 'notifications',
        'template'          => array(
            // Vordefiniertes Block-Template: Paragraph als Startpunkt
            array( 'core/paragraph', array( 'placeholder' => 'Notification-Inhalt eingeben …' ) ),
        ),
        'template_lock'     => false, // Redakteure können weitere Blöcke hinzufügen
    ) );
} );

// =============================================================================
// ACF FELDER
// =============================================================================

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( array(
        'key'   => 'group_notification',
        'title' => 'Notification Einstellungen',
        'fields' => array(

            // ── Typ ───────────────────────────────────────────────────────────
            array(
                'key'           => 'field_notification_type',
                'label'         => 'Typ',
                'name'          => 'notification_type',
                'type'          => 'select',
                'choices'       => array(
                    'info'    => 'Info (Blau)',
                    'success' => 'Erfolg (Grün)',
                    'warning' => 'Warnung (Gelb)',
                    'error'   => 'Fehler (Rot)',
                ),
                'default_value' => 'info',
                'required'      => 1,
                'wrapper'       => array( 'width' => '33' ),
            ),

            // ── Anzeigemodus ──────────────────────────────────────────────────
            array(
                'key'     => 'field_notification_display',
                'label'   => 'Anzeigemodus',
                'name'    => 'notification_display',
                'type'    => 'select',
                'choices' => array(
                    'banner'    => 'Siteweiter Banner (oben)',
                    'shortcode' => 'Per Shortcode einblendbar',
                    'popup'     => 'Popup',
                    'toast'     => 'Toast (oben rechts)',
                ),
                'default_value' => 'shortcode',
                'required'      => 1,
                'wrapper'       => array( 'width' => '33' ),
            ),

            // ── Layout-Variante ───────────────────────────────────────────────
            array(
                'key'           => 'field_notification_layout',
                'label'         => 'Layout',
                'name'          => 'notification_layout',
                'type'          => 'select',
                'choices'       => array(
                    'standard'   => 'Standard (Icon + Text)',
                    'compact'    => 'Kompakt (Icon + Kurztext, einzeilig)',
                    'banner-img' => 'Banner mit Bild (Bild links)',
                ),
                'default_value' => 'standard',
                'instructions'  => 'Steuert das HTML-Layout der Notification.',
                'wrapper'       => array( 'width' => '34' ),
            ),

            // ── Icon ─────────────────────────────────────────────────────────
            array(
                'key'           => 'field_notification_icon',
                'label'         => 'Icon (Dashicon)',
                'name'          => 'notification_icon',
                'type'          => 'text',
                'default_value' => 'auto',
                'instructions'  => 'z.B. dashicons-info – oder „auto" (automatisch) / „none" (kein Icon)',
                'wrapper'       => array( 'width' => '50' ),
            ),

            // ── Bild (für Layout „Banner mit Bild") ───────────────────────────
            array(
                'key'               => 'field_notification_image',
                'label'             => 'Bild',
                'name'              => 'notification_image',
                'type'              => 'image',
                'return_format'     => 'array',
                'preview_size'      => 'thumbnail',
                'instructions'      => 'Wird links neben dem Text angezeigt. Nur im Layout „Banner mit Bild".',
                'wrapper'           => array( 'width' => '50' ),
                'conditional_logic' => array( array( array(
                    'field'    => 'field_notification_layout',
                    'operator' => '==',
                    'value'    => 'banner-img',
                ) ) ),
            ),

            // ── Schließbar / Aktiv ────────────────────────────────────────────
            array(
                'key'           => 'field_notification_dismissible',
                'label'         => 'Schließbar',
                'name'          => 'notification_dismissible',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
                'wrapper'       => array( 'width' => '25' ),
            ),
            array(
                'key'           => 'field_notification_active',
                'label'         => 'Aktiv',
                'name'          => 'notification_active',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
                'wrapper'       => array( 'width' => '25' ),
            ),

            // ── Popup-Verzögerung ─────────────────────────────────────────────
            array(
                'key'               => 'field_notification_delay',
                'label'             => 'Popup Verzögerung (Sekunden)',
                'name'              => 'notification_delay',
                'type'              => 'number',
                'default_value'     => 3,
                'min'               => 0,
                'max'               => 30,
                'wrapper'           => array( 'width' => '50' ),
                'conditional_logic' => array( array( array(
                    'field' => 'field_notification_display', 'operator' => '==', 'value' => 'popup',
                ) ) ),
            ),

            // ── Datumsbegrenzung ──────────────────────────────────────────────
            array(
                'key'            => 'field_notification_date_from',
                'label'          => 'Anzeigen ab',
                'name'           => 'notification_date_from',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'instructions'   => 'Leer lassen = sofort aktiv',
                'wrapper'        => array( 'width' => '25' ),
            ),
            array(
                'key'            => 'field_notification_date_to',
                'label'          => 'Anzeigen bis',
                'name'           => 'notification_date_to',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'instructions'   => 'Leer lassen = unbegrenzt',
                'wrapper'        => array( 'width' => '25' ),
            ),

            // ── Kurztext Fallback (Legacy / Kompakt-Layout) ────────────────────
            array(
                'key'     => 'field_notification_message',
                'label'   => ' ',
                'type'    => 'message',
                'message' => '<strong style="font-size:12px;color:#555;">Kurztext-Fallback</strong><p style="font-size:12px;color:#888;margin:.25rem 0 0;">Wird nur ausgegeben wenn der Gutenberg-Inhalt (oben) leer ist, oder im Kompakt-Layout als Einzeiler-Text.</p>',
                'name'    => 'notification_message_sep',
                'default_value' => '',
                'wrapper' => array( 'width' => '100' ),
            ),
            array(
                'key'          => 'field_notification_message_text',
                'label'        => 'Kurztext',
                'name'         => 'notification_message',
                'type'         => 'textarea',
                'rows'         => 2,
                'instructions' => 'Optional. Wenn leer, wird der Gutenberg-Inhalt oben verwendet.',
                'wrapper'      => array( 'width' => '100' ),
            ),

        ),
        'location' => array( array( array(
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'notification',
        ) ) ),
        'menu_order'            => 0,
        'position'              => 'side', // ← Seitenleiste im Gutenberg-Editor
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ) );
} );

// =============================================================================
// HELPER: AKTIVE NOTIFICATIONS ABRUFEN
// =============================================================================

/**
 * Gibt aktive Notifications zurück (optional nach Display-Typ gefiltert).
 * Gibt sowohl `message` (Fallback-Text) als auch `content` (Gutenberg-HTML) zurück.
 *
 * @param  string|null $display  'banner'|'shortcode'|'popup'|'toast'|null (= alle)
 * @return array<int, array{id:int, title:string, message:string, content:string, type:string, display:string, layout:string, icon:string, image:array|null, dismissible:bool, delay:int}>
 */
function media_lab_get_active_notifications( ?string $display = null ): array {
    $today = date( 'Y-m-d' );

    $args = array(
        'post_type'      => 'notification',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array( 'key' => 'notification_active', 'value' => '1' ),
        ),
    );

    if ( $display ) {
        $args['meta_query'][] = array(
            'key' => 'notification_display', 'value' => $display,
        );
    }

    $query         = new WP_Query( $args );
    $notifications = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $id = get_the_ID();

            $date_from = get_field( 'notification_date_from', $id );
            $date_to   = get_field( 'notification_date_to',   $id );

            if ( $date_from && $date_from > $today ) continue;
            if ( $date_to   && $date_to   < $today ) continue;

            // Gutenberg-Inhalt (bevorzugt) vs. ACF-Fallback-Text
            $post_content = get_post_field( 'post_content', $id );
            $content_html = ! empty( trim( $post_content ) )
                ? apply_filters( 'the_content', $post_content )
                : '';

            $message = get_field( 'notification_message', $id ) ?? '';

            $notifications[] = array(
                'id'          => $id,
                'title'       => get_the_title(),
                'message'     => $message,    // ACF-Fallback-Text
                'content'     => $content_html, // Gutenberg-HTML
                'type'        => get_field( 'notification_type',        $id ) ?: 'info',
                'display'     => get_field( 'notification_display',     $id ) ?: 'shortcode',
                'layout'      => get_field( 'notification_layout',      $id ) ?: 'standard',
                'icon'        => get_field( 'notification_icon',        $id ) ?: 'auto',
                'image'       => get_field( 'notification_image',       $id ) ?: null,
                'dismissible' => (bool) get_field( 'notification_dismissible', $id ),
                'delay'       => (int) ( get_field( 'notification_delay', $id ) ?: 3 ),
            );
        }
        wp_reset_postdata();
    }

    return $notifications;
}
