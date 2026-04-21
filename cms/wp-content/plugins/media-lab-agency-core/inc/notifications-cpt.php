<?php
/**
 * Notifications CPT + ACF
 */

if (!defined('ABSPATH')) exit;

// CPT registrieren
add_action('init', function() {
    register_post_type('notification', array(
        'labels' => array(
            'name'          => 'Notifications',
            'singular_name' => 'Notification',
            'add_new_item'  => 'Neue Notification',
            'edit_item'     => 'Notification bearbeiten',
        ),
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-bell',
        'menu_position'=> 25,
        'supports'     => array('title'),
        'show_in_rest' => false,
    ));
});

// ACF Felder
add_action('acf/init', function() {
    if (!function_exists('acf_add_local_field_group')) return;

    acf_add_local_field_group(array(
        'key'    => 'group_notification',
        'title'  => 'Notification Einstellungen',
        'fields' => array(

            // Type
            array(
                'key'     => 'field_notification_type',
                'label'   => 'Typ',
                'name'    => 'notification_type',
                'type'    => 'select',
                'choices' => array(
                    'info'    => 'Info (Blau)',
                    'success' => 'Erfolg (Grün)',
                    'warning' => 'Warnung (Gelb)',
                    'error'   => 'Fehler (Rot)',
                ),
                'default_value' => 'info',
                'required' => 1,
            ),

            // Display Mode
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
                'required' => 1,
            ),

            // Message
            array(
                'key'      => 'field_notification_message',
                'label'    => 'Nachricht',
                'name'     => 'notification_message',
                'type'     => 'textarea',
                'rows'     => 3,
                'required' => 1,
            ),

            // Icon
            array(
                'key'           => 'field_notification_icon',
                'label'         => 'Icon (Dashicon)',
                'name'          => 'notification_icon',
                'type'          => 'text',
                'default_value' => 'auto',
                'instructions'  => 'z.B. dashicons-info, dashicons-warning - oder "auto" für automatisch, "none" für kein Icon',
            ),

            // Dismissible
            array(
                'key'           => 'field_notification_dismissible',
                'label'         => 'Schließbar',
                'name'          => 'notification_dismissible',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
            ),

            // Active
            array(
                'key'           => 'field_notification_active',
                'label'         => 'Aktiv',
                'name'          => 'notification_active',
                'type'          => 'true_false',
                'default_value' => 1,
                'ui'            => 1,
            ),

            // Popup Delay
            array(
                'key'               => 'field_notification_delay',
                'label'             => 'Popup Verzögerung (Sekunden)',
                'name'              => 'notification_delay',
                'type'              => 'number',
                'default_value'     => 3,
                'min'               => 0,
                'max'               => 30,
                'conditional_logic' => array(array(array(
                    'field'    => 'field_notification_display',
                    'operator' => '==',
                    'value'    => 'popup',
                ))),
            ),

            // Date From
            array(
                'key'           => 'field_notification_date_from',
                'label'         => 'Anzeigen ab',
                'name'          => 'notification_date_from',
                'type'          => 'date_picker',
                'display_format'=> 'd.m.Y',
                'return_format' => 'Y-m-d',
                'instructions'  => 'Leer lassen = sofort',
            ),

            // Date To
            array(
                'key'           => 'field_notification_date_to',
                'label'         => 'Anzeigen bis',
                'name'          => 'notification_date_to',
                'type'          => 'date_picker',
                'display_format'=> 'd.m.Y',
                'return_format' => 'Y-m-d',
                'instructions'  => 'Leer lassen = unbegrenzt',
            ),

        ),
        'location' => array(array(array(
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'notification',
        ))),
        'menu_order' => 0,
    ));
});


/**
 * Helper: Holt aktive Notifications nach Display-Typ
 */
function media_lab_get_active_notifications($display = null) {
    $today = date('Y-m-d');

    $args = array(
        'post_type'      => 'notification',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'   => 'notification_active',
                'value' => '1',
            ),
        ),
    );

    if ($display) {
        $args['meta_query'][] = array(
            'key'   => 'notification_display',
            'value' => $display,
        );
    }

    $query = new WP_Query($args);
    $notifications = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();

            // Datum prüfen
            $date_from = get_field('notification_date_from', $id);
            $date_to   = get_field('notification_date_to', $id);

            if ($date_from && $date_from > $today) continue;
            if ($date_to   && $date_to   < $today) continue;

            $notifications[] = array(
                'id'          => $id,
                'title'       => get_the_title(),
                'message'     => get_field('notification_message', $id),
                'type'        => get_field('notification_type', $id) ?: 'info',
                'display'     => get_field('notification_display', $id) ?: 'shortcode',
                'icon'        => get_field('notification_icon', $id) ?: 'auto',
                'dismissible' => get_field('notification_dismissible', $id),
                'delay'      => get_field('notification_delay', $id) ?: 3,
            );
        }
        wp_reset_postdata();
    }

    return $notifications;
}
