<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MLB_Mail {

    public static function send_confirmation( int $booking_id ): void {
        $location_id    = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $customer_email = sanitize_email( get_post_meta( $booking_id, 'mlb_booking_email', true ) );
        $location_email = sanitize_email( get_field( 'mlb_location_email', $location_id ) );
        if ( ! is_email( $customer_email ) ) return;

        $raw_subject = get_field( 'mlb_confirmation_subject', $location_id );
        $subject     = $raw_subject ? wp_strip_all_tags( $raw_subject ) : get_bloginfo( 'name' ) . ' – Buchungsbestätigung';
        $subject     = self::replace_placeholders_public( $subject, $booking_id );

        $template = get_field( 'mlb_confirmation_template', $location_id ) ?: self::default_template();
        $body     = self::replace_placeholders_public( $template, $booking_id );
        $body     = apply_filters( 'mlb_confirmation_body', $body, $booking_id, $location_id );
        $subject  = apply_filters( 'mlb_confirmation_subject', $subject, $booking_id, $location_id );
        $body     = self::wrap_html_public( $body );

        $headers     = [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>' ];
        $attachments = class_exists( 'MLB_ICal' ) ? MLB_ICal::attachment( $booking_id ) : [];

        wp_mail( $customer_email, $subject, $body, $headers, $attachments );

        if ( $location_email ) {
            $admin_body    = self::admin_notification( $booking_id );
            $admin_subject = '[Neue Buchung] ' . get_the_title( $booking_id );
            wp_mail( $location_email, $admin_subject, self::wrap_html_public( $admin_body ), $headers );
        }
    }

    public static function replace_placeholders_public( string $template, int $booking_id ): string {
        $location_id = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $location    = get_post( $location_id );
        $date_raw    = get_post_meta( $booking_id, 'mlb_booking_date', true );
        $date_fmt    = $date_raw ? date_i18n( get_option( 'date_format' ), strtotime( $date_raw ) ) : '';
        $cancel_url  = class_exists( 'MLB_Notifications' ) ? MLB_Notifications::get_cancel_url( $booking_id ) : '';

        $placeholders = [
            '{name}'             => esc_html( get_post_meta( $booking_id, 'mlb_booking_name',    true ) ),
            '{email}'            => esc_html( get_post_meta( $booking_id, 'mlb_booking_email',   true ) ),
            '{phone}'            => esc_html( get_post_meta( $booking_id, 'mlb_booking_phone',   true ) ),
            '{date}'             => esc_html( $date_fmt ),
            '{time}'             => esc_html( get_post_meta( $booking_id, 'mlb_booking_time',    true ) ) . ' Uhr',
            '{service}'          => esc_html( get_post_meta( $booking_id, 'mlb_booking_service', true ) ),
            '{persons}'          => esc_html( get_post_meta( $booking_id, 'mlb_booking_persons', true ) ),
            '{notes}'            => esc_html( get_post_meta( $booking_id, 'mlb_booking_notes',   true ) ),
            '{location_name}'    => $location ? esc_html( $location->post_title ) : '',
            '{location_address}' => esc_html( get_field( 'mlb_location_address', $location_id ) ?? '' ),
            '{location_email}'   => esc_html( get_field( 'mlb_location_email',   $location_id ) ?? '' ),
            '{location_phone}'   => esc_html( get_field( 'mlb_location_phone',   $location_id ) ?? '' ),
            '{booking_id}'       => '#' . $booking_id,
            '{cancel_url}'       => esc_url( $cancel_url ),
        ];

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
    }

    private static function admin_notification( int $booking_id ): string {
        $location_id = (int) get_post_meta( $booking_id, 'mlb_booking_location', true );
        $date_raw    = get_post_meta( $booking_id, 'mlb_booking_date', true );
        $date_fmt    = $date_raw ? date_i18n( 'd.m.Y', strtotime( $date_raw ) ) : '';
        $admin_url   = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );
        return '
            <h2>Neue Buchung eingegangen</h2>
            <table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">
                <tr style="background:#f9f9f9"><td><strong>Buchungsnummer</strong></td><td>#' . $booking_id . '</td></tr>
                <tr><td><strong>Name</strong></td><td>' . esc_html( get_post_meta( $booking_id, 'mlb_booking_name',    true ) ) . '</td></tr>
                <tr style="background:#f9f9f9"><td><strong>E-Mail</strong></td><td>' . esc_html( get_post_meta( $booking_id, 'mlb_booking_email',   true ) ) . '</td></tr>
                <tr><td><strong>Telefon</strong></td><td>' . esc_html( get_post_meta( $booking_id, 'mlb_booking_phone',   true ) ) . '</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Standort</strong></td><td>' . esc_html( get_the_title( $location_id ) ) . '</td></tr>
                <tr><td><strong>Datum</strong></td><td>' . esc_html( $date_fmt ) . '</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Uhrzeit</strong></td><td>' . esc_html( get_post_meta( $booking_id, 'mlb_booking_time',    true ) ) . ' Uhr</td></tr>
                <tr><td><strong>Dienstleistung</strong></td><td>' . esc_html( get_post_meta( $booking_id, 'mlb_booking_service', true ) ) . '</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Personen</strong></td><td>' . esc_html( get_post_meta( $booking_id, 'mlb_booking_persons', true ) ) . '</td></tr>
                <tr><td><strong>Anmerkungen</strong></td><td>' . nl2br( esc_html( get_post_meta( $booking_id, 'mlb_booking_notes', true ) ) ) . '</td></tr>
            </table>
            <p style="margin-top:24px">
                <a href="' . esc_url( $admin_url ) . '" style="background:#0073aa;color:#fff;padding:10px 18px;text-decoration:none;border-radius:4px">Buchung im Backend ansehen</a>
            </p>';
    }

    private static function default_template(): string {
        return '
            <p>Guten Tag {name},</p>
            <p>vielen Dank für Ihre Buchung. Wir haben Ihre Anfrage erhalten und werden diese schnellstmöglich bearbeiten.</p>
            <h3>Ihre Buchungsdetails</h3>
            <table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">
                <tr style="background:#f9f9f9"><td><strong>Buchungsnummer</strong></td><td>{booking_id}</td></tr>
                <tr><td><strong>Standort</strong></td><td>{location_name}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Datum</strong></td><td>{date}</td></tr>
                <tr><td><strong>Uhrzeit</strong></td><td>{time}</td></tr>
                <tr style="background:#f9f9f9"><td><strong>Dienstleistung</strong></td><td>{service}</td></tr>
                <tr><td><strong>Personen</strong></td><td>{persons}</td></tr>
            </table>
            <h3>Standortinformationen</h3>
            <p><strong>{location_name}</strong><br>{location_address}<br>{location_phone}<br>{location_email}</p>
            <p>Den Termin finden Sie im Anhang als Kalenderdatei (.ics).</p>
            <p>Möchten Sie den Termin absagen? <a href="{cancel_url}">Hier klicken zum Stornieren</a></p>
            <p>Mit freundlichen Grüßen,<br><strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong></p>';
    }

    public static function wrap_html_public( string $content ): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:15px;color:#333;margin:0;padding:0;background:#f4f4f4}
            .wrap{max-width:600px;margin:32px auto;background:#fff;border-radius:6px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
            .body{padding:32px} table{width:100%;border-collapse:collapse;margin:16px 0} td{padding:10px 12px;border-bottom:1px solid #eee;vertical-align:top}
            h2,h3{color:#111} a{color:#0073aa} .footer{background:#f9f9f9;padding:16px 32px;font-size:12px;color:#888;border-top:1px solid #eee}
        </style></head><body><div class="wrap"><div class="body">' . $content . '</div>
        <div class="footer">' . esc_html( get_bloginfo( 'name' ) ) . ' · ' . esc_html( home_url() ) . '</div></div></body></html>';
    }
}
