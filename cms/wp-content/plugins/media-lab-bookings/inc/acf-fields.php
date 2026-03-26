<?php
/**
 * ACF Field Groups
 *
 * 1. Standort-Einstellungen  (mlb_location)
 *    – Öffnungszeiten (Mo–So), Zeitslots, Kontakt, Bestätigungsmail, Dienstleistungen
 *
 * 2. Buchungsdetails         (mlb_booking)
 *    – Alle Felder der eingehenden Buchung + Status
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'acf/include_fields', 'mlb_register_acf_fields' );

function mlb_register_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    // ── 1. STANDORT ────────────────────────────────────────────────────────────
    acf_add_local_field_group( [
        'key'      => 'group_mlb_location',
        'title'    => 'Standort-Einstellungen',
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'mlb_location' ] ] ],
        'menu_order' => 0,
        'fields'   => array_merge(

            // ── Tab: Öffnungszeiten ──────────────────────────────────────────
            [
                [
                    'key'   => 'field_mlb_tab_hours',
                    'label' => 'Öffnungszeiten',
                    'name'  => '',
                    'type'  => 'tab',
                ],
            ],
            mlb_build_hours_fields(),

            // ── Tab: Zeitslots ───────────────────────────────────────────────
            [
                [
                    'key'   => 'field_mlb_tab_slots',
                    'label' => 'Zeitslots',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'           => 'field_mlb_slot_duration',
                    'label'         => 'Slot-Dauer (Minuten)',
                    'name'          => 'mlb_slot_duration',
                    'type'          => 'number',
                    'default_value' => 60,
                    'min'           => 5,
                    'max'           => 480,
                    'step'          => 5,
                    'instructions'  => 'Dauer eines einzelnen Zeitslots in Minuten.',
                    'required'      => 1,
                    'wrapper'       => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_mlb_last_slot_offset',
                    'label'         => 'Letzter Slot: X Minuten vor Schließung',
                    'name'          => 'mlb_last_slot_offset',
                    'type'          => 'number',
                    'default_value' => 60,
                    'min'           => 0,
                    'max'           => 480,
                    'step'          => 5,
                    'instructions'  => 'Der letzte Slot startet frühestens X Minuten vor Schließung (z.B. 60 = letzter Slot 1h vor Ende).',
                    'required'      => 1,
                    'wrapper'       => [ 'width' => '33' ],
                ],
                [
                    'key'           => 'field_mlb_max_capacity',
                    'label'         => 'Max. Buchungen pro Slot',
                    'name'          => 'mlb_max_capacity',
                    'type'          => 'number',
                    'default_value' => 1,
                    'min'           => 1,
                    'max'           => 100,
                    'step'          => 1,
                    'instructions'  => 'Maximale Anzahl gleichzeitiger Buchungen pro Zeitslot.',
                    'required'      => 1,
                    'wrapper'       => [ 'width' => '33' ],
                ],
            ],

            // ── Tab: Kontakt ─────────────────────────────────────────────────
            [
                [
                    'key'   => 'field_mlb_tab_contact',
                    'label' => 'Kontakt',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'          => 'field_mlb_location_email',
                    'label'        => 'Filial-E-Mail',
                    'name'         => 'mlb_location_email',
                    'type'         => 'email',
                    'instructions' => 'An diese Adresse wird eine Kopie jeder Buchung gesendet.',
                    'required'     => 1,
                    'wrapper'      => [ 'width' => '50' ],
                ],
                [
                    'key'     => 'field_mlb_location_phone',
                    'label'   => 'Telefon',
                    'name'    => 'mlb_location_phone',
                    'type'    => 'text',
                    'wrapper' => [ 'width' => '50' ],
                ],
                [
                    'key'   => 'field_mlb_location_address',
                    'label' => 'Adresse',
                    'name'  => 'mlb_location_address',
                    'type'  => 'textarea',
                    'rows'  => 3,
                ],
            ],

            // ── Tab: Bestätigungsmail ────────────────────────────────────────
            [
                [
                    'key'   => 'field_mlb_tab_mail',
                    'label' => 'Bestätigungsmail',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'           => 'field_mlb_confirmation_subject',
                    'label'         => 'Betreff',
                    'name'          => 'mlb_confirmation_subject',
                    'type'          => 'text',
                    'default_value' => 'Ihre Buchungsbestätigung',
                    'instructions'  => 'Betreff der Bestätigungs-E-Mail an den Kunden.',
                    'required'      => 1,
                ],
                [
                    'key'          => 'field_mlb_confirmation_template',
                    'label'        => 'E-Mail-Text (HTML)',
                    'name'         => 'mlb_confirmation_template',
                    'type'         => 'wysiwyg',
                    'tabs'         => 'all',
                    'toolbar'      => 'full',
                    'media_upload' => 0,
                    'instructions' => 'Verfügbare Platzhalter: {name}, {email}, {phone}, {date}, {time}, {service}, {persons}, {notes}, {location_name}, {location_address}, {location_email}, {location_phone}, {booking_id}',
                ],
                [
                    'key'     => 'field_mlb_mail_placeholders_info',
                    'label'   => 'Platzhalter-Übersicht',
                    'name'    => '',
                    'type'    => 'message',
                    'message' => '<table class="widefat" style="font-size:12px">
                        <tr><th>Platzhalter</th><th>Inhalt</th></tr>
                        <tr><td><code>{name}</code></td><td>Name des Kunden</td></tr>
                        <tr><td><code>{email}</code></td><td>E-Mail des Kunden</td></tr>
                        <tr><td><code>{phone}</code></td><td>Telefonnummer</td></tr>
                        <tr><td><code>{date}</code></td><td>Buchungsdatum (formatiert)</td></tr>
                        <tr><td><code>{time}</code></td><td>Uhrzeit</td></tr>
                        <tr><td><code>{service}</code></td><td>Gewählte Dienstleistung</td></tr>
                        <tr><td><code>{persons}</code></td><td>Personenanzahl</td></tr>
                        <tr><td><code>{notes}</code></td><td>Anmerkungen</td></tr>
                        <tr><td><code>{location_name}</code></td><td>Name des Standorts</td></tr>
                        <tr><td><code>{location_address}</code></td><td>Adresse des Standorts</td></tr>
                        <tr><td><code>{location_email}</code></td><td>E-Mail des Standorts</td></tr>
                        <tr><td><code>{location_phone}</code></td><td>Telefon des Standorts</td></tr>
                        <tr><td><code>{booking_id}</code></td><td>Buchungsnummer</td></tr>
                    </table>',
                ],
            ],

            // ── Tab: Dienstleistungen ────────────────────────────────────────
            [
                [
                    'key'   => 'field_mlb_tab_services',
                    'label' => 'Dienstleistungen',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'          => 'field_mlb_services',
                    'label'        => 'Dienstleistungen',
                    'name'         => 'mlb_services',
                    'type'         => 'repeater',
                    'instructions' => 'Verfügbare Dienstleistungen für diesen Standort.',
                    'min'          => 0,
                    'layout'       => 'table',
                    'button_label' => 'Dienstleistung hinzufügen',
                    'sub_fields'   => [
                        [
                            'key'      => 'field_mlb_service_name',
                            'label'    => 'Bezeichnung',
                            'name'     => 'service_name',
                            'type'     => 'text',
                            'required' => 1,
                            'wrapper'  => [ 'width' => '70' ],
                        ],
                        [
                            'key'          => 'field_mlb_service_duration',
                            'label'        => 'Dauer (Min., optional)',
                            'name'         => 'service_duration',
                            'type'         => 'number',
                            'min'          => 0,
                            'instructions' => 'Leer lassen = Standard-Slotdauer',
                            'wrapper'      => [ 'width' => '30' ],
                        ],
                    ],
                ],
            ],

            // ── Tab: Formular-Labels ─────────────────────────────────────────
            [
                [
                    'key'   => 'field_mlb_tab_labels',
                    'label' => 'Formular-Labels',
                    'name'  => '',
                    'type'  => 'tab',
                ],
                [
                    'key'     => 'field_mlb_labels_info',
                    'label'   => '',
                    'name'    => '',
                    'type'    => 'message',
                    'message' => 'Feldbeschriftungen für das Buchungsformular. Leere Felder verwenden den Standard-Text.',
                ],
                // Abschnitts-Header
                [
                    'key'           => 'field_mlb_label_location',
                    'label'         => 'Label: Standort',
                    'name'          => 'mlb_label_location',
                    'type'          => 'text',
                    'placeholder'   => 'Standort wählen',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_date',
                    'label'         => 'Label: Datum',
                    'name'          => 'mlb_label_date',
                    'type'          => 'text',
                    'placeholder'   => 'Datum',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_time',
                    'label'         => 'Label: Uhrzeit',
                    'name'          => 'mlb_label_time',
                    'type'          => 'text',
                    'placeholder'   => 'Uhrzeit',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_service',
                    'label'         => 'Label: Dienstleistung',
                    'name'          => 'mlb_label_service',
                    'type'          => 'text',
                    'placeholder'   => 'Dienstleistung',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_persons',
                    'label'         => 'Label: Personenanzahl',
                    'name'          => 'mlb_label_persons',
                    'type'          => 'text',
                    'placeholder'   => 'Personenanzahl',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_name',
                    'label'         => 'Label: Name',
                    'name'          => 'mlb_label_name',
                    'type'          => 'text',
                    'placeholder'   => 'Vor- und Nachname',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_email',
                    'label'         => 'Label: E-Mail',
                    'name'          => 'mlb_label_email',
                    'type'          => 'text',
                    'placeholder'   => 'E-Mail-Adresse',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_phone',
                    'label'         => 'Label: Telefon',
                    'name'          => 'mlb_label_phone',
                    'type'          => 'text',
                    'placeholder'   => 'Telefon',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_notes',
                    'label'         => 'Label: Anmerkungen',
                    'name'          => 'mlb_label_notes',
                    'type'          => 'text',
                    'placeholder'   => 'Anmerkungen',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => 'field_mlb_label_submit',
                    'label'         => 'Label: Absende-Button',
                    'name'          => 'mlb_label_submit',
                    'type'          => 'text',
                    'placeholder'   => 'Buchung anfragen',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                // DSGVO
                [
                    'key'           => 'field_mlb_label_privacy',
                    'label'         => 'DSGVO-Zustimmungstext',
                    'name'          => 'mlb_label_privacy',
                    'type'          => 'text',
                    'default_value' => 'Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu.',
                    'instructions'  => 'Pflichtfeld – Formular kann ohne Zustimmung nicht abgeschickt werden.',
                    'required'      => 1,
                ],
                [
                    'key'           => 'field_mlb_label_privacy_note',
                    'label'         => 'Hinweistext unter der Checkbox (optional)',
                    'name'          => 'mlb_label_privacy_note',
                    'type'          => 'text',
                    'placeholder'   => 'Mit dem Absenden stimmen Sie der Verarbeitung Ihrer Daten gemäß unserer Datenschutzerklärung zu.',
                    'instructions'  => 'Kleiner Hinweistext unter dem Absende-Button. Leer lassen = kein Hinweis.',
                ],
            ]
        ),
    ] );

    // ── 2. BUCHUNG ─────────────────────────────────────────────────────────────
    acf_add_local_field_group( [
        'key'      => 'group_mlb_booking',
        'title'    => 'Buchungsdetails',
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'mlb_booking' ] ] ],
        'menu_order' => 0,
        'fields'   => [

            // Status
            [
                'key'     => 'field_mlb_booking_status',
                'label'   => 'Status',
                'name'    => 'mlb_booking_status',
                'type'    => 'select',
                'choices' => [
                    'mlb-pending'   => 'Ausstehend',
                    'mlb-confirmed' => 'Bestätigt',
                    'mlb-cancelled' => 'Storniert',
                ],
                'default_value' => 'mlb-pending',
                'required'      => 1,
                'wrapper'       => [ 'width' => '25' ],
            ],

            // Standort
            [
                'key'           => 'field_mlb_booking_location',
                'label'         => 'Standort',
                'name'          => 'mlb_booking_location',
                'type'          => 'post_object',
                'post_type'     => [ 'mlb_location' ],
                'return_format' => 'id',
                'ui'            => 1,
                'required'      => 1,
                'wrapper'       => [ 'width' => '25' ],
            ],

            // Datum
            [
                'key'            => 'field_mlb_booking_date',
                'label'          => 'Datum',
                'name'           => 'mlb_booking_date',
                'type'           => 'date_picker',
                'display_format' => 'd.m.Y',
                'return_format'  => 'Y-m-d',
                'required'       => 1,
                'wrapper'        => [ 'width' => '25' ],
            ],

            // Uhrzeit
            [
                'key'            => 'field_mlb_booking_time',
                'label'          => 'Uhrzeit',
                'name'           => 'mlb_booking_time',
                'type'           => 'time_picker',
                'display_format' => 'H:i',
                'return_format'  => 'H:i',
                'required'       => 1,
                'wrapper'        => [ 'width' => '25' ],
            ],

            // Dienstleistung
            [
                'key'     => 'field_mlb_booking_service',
                'label'   => 'Dienstleistung',
                'name'    => 'mlb_booking_service',
                'type'    => 'text',
                'wrapper' => [ 'width' => '50' ],
            ],

            // Personen
            [
                'key'           => 'field_mlb_booking_persons',
                'label'         => 'Personenanzahl',
                'name'          => 'mlb_booking_persons',
                'type'          => 'number',
                'default_value' => 1,
                'min'           => 1,
                'required'      => 1,
                'wrapper'       => [ 'width' => '25' ],
            ],

            // Name
            [
                'key'      => 'field_mlb_booking_name',
                'label'    => 'Name',
                'name'     => 'mlb_booking_name',
                'type'     => 'text',
                'required' => 1,
                'wrapper'  => [ 'width' => '50' ],
            ],

            // E-Mail
            [
                'key'      => 'field_mlb_booking_email',
                'label'    => 'E-Mail',
                'name'     => 'mlb_booking_email',
                'type'     => 'email',
                'required' => 1,
                'wrapper'  => [ 'width' => '25' ],
            ],

            // Telefon
            [
                'key'     => 'field_mlb_booking_phone',
                'label'   => 'Telefon',
                'name'    => 'mlb_booking_phone',
                'type'    => 'text',
                'wrapper' => [ 'width' => '25' ],
            ],

            // Anmerkungen
            [
                'key'   => 'field_mlb_booking_notes',
                'label' => 'Anmerkungen',
                'name'  => 'mlb_booking_notes',
                'type'  => 'textarea',
                'rows'  => 4,
            ],
        ],
    ] );
}

// ── Helper: Öffnungszeiten-Felder per Loop generieren ─────────────────────────

function mlb_build_hours_fields() {
    $days = [
        'mon' => 'Montag',
        'tue' => 'Dienstag',
        'wed' => 'Mittwoch',
        'thu' => 'Donnerstag',
        'fri' => 'Freitag',
        'sat' => 'Samstag',
        'sun' => 'Sonntag',
    ];

    // Wochentage Mo–Fr per Default aktiv
    $weekdays = [ 'mon', 'tue', 'wed', 'thu', 'fri' ];
    $fields   = [];

    foreach ( $days as $key => $label ) {
        $is_weekday = in_array( $key, $weekdays, true );

        $fields[] = [
            'key'           => "field_mlb_{$key}_active",
            'label'         => $label,
            'name'          => "mlb_{$key}_active",
            'type'          => 'true_false',
            'ui'            => 1,
            'default_value' => $is_weekday ? 1 : 0,
            'wrapper'       => [ 'width' => '15' ],
        ];

        $fields[] = [
            'key'               => "field_mlb_{$key}_open",
            'label'             => $label . ' – Öffnung',
            'name'              => "mlb_{$key}_open",
            'type'              => 'text',
            'placeholder'       => '09:00',
            'default_value'     => '09:00',
            'instructions'      => 'Format: HH:MM',
            'wrapper'           => [ 'width' => '15' ],
            'conditional_logic' => [
                [ [ 'field' => "field_mlb_{$key}_active", 'operator' => '==', 'value' => '1' ] ],
            ],
        ];

        $fields[] = [
            'key'               => "field_mlb_{$key}_close",
            'label'             => $label . ' – Schließung',
            'name'              => "mlb_{$key}_close",
            'type'              => 'text',
            'placeholder'       => '18:00',
            'default_value'     => '18:00',
            'instructions'      => 'Format: HH:MM',
            'wrapper'           => [ 'width' => '20' ],
            'conditional_logic' => [
                [ [ 'field' => "field_mlb_{$key}_active", 'operator' => '==', 'value' => '1' ] ],
            ],
        ];
    }

    return $fields;
}
