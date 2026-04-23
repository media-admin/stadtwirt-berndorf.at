<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/include_fields', 'mlb_register_acf_fields' );

function mlb_register_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'      => 'group_mlb_location',
        'title'    => 'Standort-Einstellungen',
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'mlb_location' ] ] ],
        'fields'   => array_merge(
            // Tab: Öffnungszeiten
            [ [ 'key' => 'field_mlb_tab_hours', 'label' => 'Öffnungszeiten', 'name' => '', 'type' => 'tab' ] ],
            mlb_build_hours_fields(),
            // Tab: Zeitslots
            [
                [ 'key' => 'field_mlb_tab_slots', 'label' => 'Zeitslots', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_slot_duration',    'label' => 'Slot-Dauer (Min.)',                'name' => 'mlb_slot_duration',    'type' => 'number', 'default_value' => 60,  'min' => 5,  'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
                [ 'key' => 'field_mlb_last_slot_offset', 'label' => 'Letzter Slot: X Min. vor Schluss', 'name' => 'mlb_last_slot_offset', 'type' => 'number', 'default_value' => 60,  'min' => 0,  'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
                [ 'key' => 'field_mlb_max_capacity',     'label' => 'Max. Buchungen pro Slot',          'name' => 'mlb_max_capacity',     'type' => 'number', 'default_value' => 1,   'min' => 1,  'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
                [ 'key' => 'field_mlb_max_per_day',      'label' => 'Max. Buchungen pro Tag (0 = unbegrenzt)', 'name' => 'mlb_max_per_day', 'type' => 'number', 'default_value' => 0, 'min' => 0, 'instructions' => '0 = kein Tageslimit', 'wrapper' => [ 'width' => '25' ] ],
            ],
            // Tab: Kontakt
            [
                [ 'key' => 'field_mlb_tab_contact', 'label' => 'Kontakt', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_location_email',   'label' => 'Filial-E-Mail', 'name' => 'mlb_location_email',   'type' => 'email',    'required' => 1, 'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_location_phone',   'label' => 'Telefon',       'name' => 'mlb_location_phone',   'type' => 'text',     'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_location_address', 'label' => 'Adresse',       'name' => 'mlb_location_address', 'type' => 'textarea', 'rows' => 3 ],
            ],
            // Tab: Bestätigungsmail (initial)
            [
                [ 'key' => 'field_mlb_tab_mail', 'label' => 'Bestätigungsmail', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_confirmation_subject',  'label' => 'Betreff',            'name' => 'mlb_confirmation_subject',  'type' => 'text',    'default_value' => 'Ihre Buchungsanfrage', 'required' => 1 ],
                [ 'key' => 'field_mlb_confirmation_template', 'label' => 'E-Mail-Text (HTML)', 'name' => 'mlb_confirmation_template', 'type' => 'wysiwyg', 'media_upload' => 0, 'instructions' => 'Platzhalter: {name}, {date}, {time}, {service}, {persons}, {notes}, {location_name}, {location_address}, {location_email}, {location_phone}, {booking_id}, {cancel_url}' ],
            ],
            // Tab: Bestätigt-Mail
            [
                [ 'key' => 'field_mlb_tab_confirmed', 'label' => 'Mail: Bestätigt', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_confirmed_subject',  'label' => 'Betreff',            'name' => 'mlb_confirmed_subject',  'type' => 'text',    'placeholder' => 'Ihre Buchung wurde bestätigt' ],
                [ 'key' => 'field_mlb_confirmed_template', 'label' => 'E-Mail-Text (HTML)', 'name' => 'mlb_confirmed_template', 'type' => 'wysiwyg', 'media_upload' => 0, 'instructions' => 'Wird gesendet wenn Status auf "Bestätigt" gesetzt wird. Selbe Platzhalter wie Bestätigungsmail + {cancel_url}. iCal wird automatisch angehängt.' ],
            ],
            // Tab: Storniert-Mail
            [
                [ 'key' => 'field_mlb_tab_cancelled', 'label' => 'Mail: Storniert', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_cancelled_subject',  'label' => 'Betreff',            'name' => 'mlb_cancelled_subject',  'type' => 'text',    'placeholder' => 'Ihre Buchung wurde storniert' ],
                [ 'key' => 'field_mlb_cancelled_template', 'label' => 'E-Mail-Text (HTML)', 'name' => 'mlb_cancelled_template', 'type' => 'wysiwyg', 'media_upload' => 0, 'instructions' => 'Wird gesendet wenn Status auf "Storniert" gesetzt wird (manuell oder via Stornierungslink).' ],
            ],
            // Tab: Erinnerungsmail
            [
                [ 'key' => 'field_mlb_tab_reminder', 'label' => 'Erinnerungsmail', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_reminder_hours',    'label' => 'Erinnerung X Stunden vorher', 'name' => 'mlb_reminder_hours',    'type' => 'number', 'default_value' => 24, 'min' => 0, 'instructions' => '0 = Erinnerung deaktiviert', 'wrapper' => [ 'width' => '33' ] ],
                [ 'key' => 'field_mlb_reminder_subject',  'label' => 'Betreff',                     'name' => 'mlb_reminder_subject',  'type' => 'text',   'placeholder' => 'Erinnerung: Ihr Termin morgen', 'wrapper' => [ 'width' => '67' ] ],
                [ 'key' => 'field_mlb_reminder_template', 'label' => 'E-Mail-Text (HTML)',           'name' => 'mlb_reminder_template', 'type' => 'wysiwyg', 'media_upload' => 0, 'instructions' => 'Wird automatisch per WP-Cron versendet. Selbe Platzhalter verfügbar. iCal wird angehängt.' ],
            ],
            // Tab: Formular-Labels
            [
                [ 'key' => 'field_mlb_tab_labels', 'label' => 'Formular-Labels', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_label_location',     'label' => 'Label: Standort',        'name' => 'mlb_label_location',     'type' => 'text', 'placeholder' => 'Standort wählen',       'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_date',         'label' => 'Label: Datum',            'name' => 'mlb_label_date',         'type' => 'text', 'placeholder' => 'Datum',                 'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_time',         'label' => 'Label: Uhrzeit',          'name' => 'mlb_label_time',         'type' => 'text', 'placeholder' => 'Uhrzeit',               'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_service',      'label' => 'Label: Dienstleistung',   'name' => 'mlb_label_service',      'type' => 'text', 'placeholder' => 'Dienstleistung',        'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_persons',      'label' => 'Label: Personenanzahl',   'name' => 'mlb_label_persons',      'type' => 'text', 'placeholder' => 'Personenanzahl',        'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_name',         'label' => 'Label: Name',             'name' => 'mlb_label_name',         'type' => 'text', 'placeholder' => 'Vor- und Nachname',     'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_email',        'label' => 'Label: E-Mail',           'name' => 'mlb_label_email',        'type' => 'text', 'placeholder' => 'E-Mail-Adresse',        'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_phone',        'label' => 'Label: Telefon',          'name' => 'mlb_label_phone',        'type' => 'text', 'placeholder' => 'Telefon',               'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_notes',        'label' => 'Label: Anmerkungen',      'name' => 'mlb_label_notes',        'type' => 'text', 'placeholder' => 'Anmerkungen',           'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_submit',       'label' => 'Label: Absende-Button',   'name' => 'mlb_label_submit',       'type' => 'text', 'placeholder' => 'Buchung anfragen',      'wrapper' => [ 'width' => '50' ] ],
                [ 'key' => 'field_mlb_label_privacy',      'label' => 'DSGVO-Zustimmungstext',   'name' => 'mlb_label_privacy',      'type' => 'text', 'default_value' => 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu.', 'required' => 1 ],
                [ 'key' => 'field_mlb_label_privacy_note', 'label' => 'Hinweistext (optional)',  'name' => 'mlb_label_privacy_note', 'type' => 'text', 'placeholder' => '' ],
            ],
            // Tab: Pflichtfelder
            [
                [ 'key' => 'field_mlb_tab_required', 'label' => 'Pflichtfelder', 'name' => '', 'type' => 'tab' ],
                [
                    'key'     => 'field_mlb_required_info',
                    'label'   => '',
                    'name'    => '',
                    'type'    => 'message',
                    'message' => 'E-Mail und DSGVO-Zustimmung sind immer Pflichtfelder und können hier nicht deaktiviert werden.',
                ],
                [
                    'key'           => 'field_mlb_required_name',
                    'label'         => 'Name ist Pflichtfeld',
                    'name'          => 'mlb_required_name',
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'default_value' => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_mlb_required_phone',
                    'label'         => 'Telefon ist Pflichtfeld',
                    'name'          => 'mlb_required_phone',
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'default_value' => 0,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_mlb_required_service',
                    'label'         => 'Dienstleistung ist Pflichtfeld',
                    'name'          => 'mlb_required_service',
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'default_value' => 0,
                    'wrapper'       => [ 'width' => '25' ],
                ],
                [
                    'key'           => 'field_mlb_required_persons',
                    'label'         => 'Personenanzahl ist Pflichtfeld',
                    'name'          => 'mlb_required_persons',
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'default_value' => 1,
                    'wrapper'       => [ 'width' => '25' ],
                ],
            ],

            // Tab: Dienstleistungen
            [
                [ 'key' => 'field_mlb_tab_services', 'label' => 'Dienstleistungen', 'name' => '', 'type' => 'tab' ],
                [ 'key' => 'field_mlb_services', 'label' => 'Dienstleistungen', 'name' => 'mlb_services', 'type' => 'repeater', 'min' => 0, 'layout' => 'table', 'button_label' => 'Dienstleistung hinzufügen',
                  'sub_fields' => [
                      [ 'key' => 'field_mlb_service_name',     'label' => 'Bezeichnung',          'name' => 'service_name',     'type' => 'text',   'required' => 1, 'wrapper' => [ 'width' => '70' ] ],
                      [ 'key' => 'field_mlb_service_duration', 'label' => 'Dauer (Min., opt.)',   'name' => 'service_duration', 'type' => 'number', 'min' => 0,      'wrapper' => [ 'width' => '30' ] ],
                  ],
                ],
            ]
        ),
    ] );

    acf_add_local_field_group( [
        'key'      => 'group_mlb_booking',
        'title'    => 'Buchungsdetails',
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'mlb_booking' ] ] ],
        'fields'   => [
            [ 'key' => 'field_mlb_booking_status',   'label' => 'Status',          'name' => 'mlb_booking_status',   'type' => 'select',     'choices' => [ 'mlb-pending' => 'Ausstehend', 'mlb-confirmed' => 'Bestätigt', 'mlb-cancelled' => 'Storniert' ], 'default_value' => 'mlb-pending', 'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_location', 'label' => 'Standort',        'name' => 'mlb_booking_location', 'type' => 'post_object', 'post_type' => [ 'mlb_location' ], 'return_format' => 'id', 'ui' => 1, 'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_date',     'label' => 'Datum',           'name' => 'mlb_booking_date',     'type' => 'date_picker', 'display_format' => 'd.m.Y', 'return_format' => 'Y-m-d', 'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_time',     'label' => 'Uhrzeit',         'name' => 'mlb_booking_time',     'type' => 'time_picker', 'display_format' => 'H:i',   'return_format' => 'H:i',   'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_service',  'label' => 'Dienstleistung',  'name' => 'mlb_booking_service',  'type' => 'text',    'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_mlb_booking_persons',  'label' => 'Personenanzahl',  'name' => 'mlb_booking_persons',  'type' => 'number',  'default_value' => 1, 'min' => 1, 'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_name',     'label' => 'Name',            'name' => 'mlb_booking_name',     'type' => 'text',    'required' => 1, 'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_mlb_booking_email',    'label' => 'E-Mail',          'name' => 'mlb_booking_email',    'type' => 'email',   'required' => 1, 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_phone',    'label' => 'Telefon',         'name' => 'mlb_booking_phone',    'type' => 'text',    'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_mlb_booking_notes',    'label' => 'Anmerkungen',     'name' => 'mlb_booking_notes',    'type' => 'textarea','rows' => 4 ],
        ],
    ] );
}

function mlb_build_hours_fields(): array {
    $days     = [ 'mon' => 'Montag', 'tue' => 'Dienstag', 'wed' => 'Mittwoch', 'thu' => 'Donnerstag', 'fri' => 'Freitag', 'sat' => 'Samstag', 'sun' => 'Sonntag' ];
    $weekdays = [ 'mon', 'tue', 'wed', 'thu', 'fri' ];
    $fields   = [];
    foreach ( $days as $key => $label ) {
        $is_weekday = in_array( $key, $weekdays, true );
        $fields[] = [ 'key' => "field_mlb_{$key}_active", 'label' => $label,                    'name' => "mlb_{$key}_active", 'type' => 'true_false', 'ui' => 1, 'default_value' => $is_weekday ? 1 : 0, 'wrapper' => [ 'width' => '15' ] ];
        $fields[] = [ 'key' => "field_mlb_{$key}_open",   'label' => $label . ' – Öffnung',     'name' => "mlb_{$key}_open",   'type' => 'text', 'placeholder' => '09:00', 'default_value' => '09:00', 'wrapper' => [ 'width' => '15' ], 'conditional_logic' => [ [ [ 'field' => "field_mlb_{$key}_active", 'operator' => '==', 'value' => '1' ] ] ] ];
        $fields[] = [ 'key' => "field_mlb_{$key}_close",  'label' => $label . ' – Schließung',  'name' => "mlb_{$key}_close",  'type' => 'text', 'placeholder' => '18:00', 'default_value' => '18:00', 'wrapper' => [ 'width' => '20' ], 'conditional_logic' => [ [ [ 'field' => "field_mlb_{$key}_active", 'operator' => '==', 'value' => '1' ] ] ] ];
    }
    return $fields;
}
