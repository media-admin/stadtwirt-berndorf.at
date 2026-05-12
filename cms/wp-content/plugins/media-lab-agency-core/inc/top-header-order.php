<?php
/**
 * Top Header – Reihenfolge der Kontakt- und Social-Elemente
 *
 * Drag & Drop auf Agency Core → Top Header für:
 *   - Kontakt-Elemente (Adresse, Öffnungszeiten, Telefon, E-Mail)
 *   - Social-Media-Kanäle (Facebook, Instagram, LinkedIn, …)
 *
 * @package MediaLab_Core
 * @since   1.8.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Konfiguration ────────────────────────────────────────────────────────────

const MEDIALAB_TOP_HEADER_CONTACT_ITEMS = [
    'address' => 'Adresse',
    'hours'   => 'Öffnungszeiten',
    'phone'   => 'Telefon',
    'email'   => 'E-Mail',
];

const MEDIALAB_TOP_HEADER_SOCIAL_ITEMS = [
    'facebook'  => 'Facebook',
    'instagram' => 'Instagram',
    'linkedin'  => 'LinkedIn',
    'twitter'   => 'X / Twitter',
    'youtube'   => 'YouTube',
    'xing'      => 'Xing',
];

const MEDIALAB_TOP_HEADER_ORDER_OPTION        = 'medialab_top_header_item_order';
const MEDIALAB_TOP_HEADER_SOCIAL_ORDER_OPTION = 'medialab_top_header_social_order';

// ─── Helper ───────────────────────────────────────────────────────────────────

function medialab_get_top_header_order(): array {
    return medialab_resolve_order(
        get_option( MEDIALAB_TOP_HEADER_ORDER_OPTION, '' ),
        array_keys( MEDIALAB_TOP_HEADER_CONTACT_ITEMS )
    );
}

function medialab_get_top_header_social_order(): array {
    return medialab_resolve_order(
        get_option( MEDIALAB_TOP_HEADER_SOCIAL_ORDER_OPTION, '' ),
        array_keys( MEDIALAB_TOP_HEADER_SOCIAL_ITEMS )
    );
}

function medialab_resolve_order( string $saved, array $default ): array {
    if ( $saved ) {
        $order = json_decode( $saved, true );
        if ( is_array( $order ) && ! empty( $order ) ) {
            $order = array_values( array_filter( $order, fn( $k ) => in_array( $k, $default, true ) ) );
            foreach ( $default as $k ) {
                if ( ! in_array( $k, $order, true ) ) $order[] = $k;
            }
            return $order;
        }
    }
    return $default;
}

function medialab_sanitize_order( array $raw, array $valid ): array {
    $order = array_values( array_filter(
        array_map( 'sanitize_key', $raw ),
        fn( $k ) => in_array( $k, $valid, true )
    ) );
    foreach ( $valid as $k ) {
        if ( ! in_array( $k, $order, true ) ) $order[] = $k;
    }
    return $order;
}

// ─── Assets ───────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', function () {
    if ( ( $_GET['page'] ?? '' ) !== 'agency-core-top-header' ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
} );

// ─── Admin UI ─────────────────────────────────────────────────────────────────

add_action( 'admin_footer', function () {
    if ( ( $_GET['page'] ?? '' ) !== 'agency-core-top-header' ) return;

    $contact_order = medialab_get_top_header_order();
    $social_order  = medialab_get_top_header_social_order();
    $nonce         = wp_create_nonce( 'medialab_top_header_order' );
    ?>

    <style>
    #medialab-order-section {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        padding: 1.25rem 1.5rem 1.5rem;
        margin-top: 1.5rem;
    }
    #medialab-order-section > h2 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 0.25rem;
        padding: 0;
    }
    #medialab-order-section > p {
        color: #646970;
        font-size: 0.8125rem;
        margin: 0 0 1.25rem;
    }
    .medialab-order-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 1.25rem;
    }
    @media (max-width: 782px) {
        .medialab-order-grid { grid-template-columns: 1fr; }
    }
    .medialab-order-col h3 {
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #646970;
        margin: 0 0 0.625rem;
    }
    .medialab-sortable {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .medialab-sortable li {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.5rem 0.75rem;
        margin-bottom: 0.375rem;
        background: #f6f7f7;
        border: 1px solid #dcdcde;
        border-radius: 3px;
        cursor: grab;
        font-size: 0.875rem;
        user-select: none;
    }
    .medialab-sortable li:hover { background: #f0f0f1; }
    .medialab-sortable li.ui-sortable-helper {
        cursor: grabbing;
        box-shadow: 0 4px 14px rgba(0,0,0,.15);
        background: #fff;
    }
    .medialab-sortable li.ui-sortable-placeholder {
        background: #e8f4fd !important;
        border: 1px dashed #72aee6 !important;
        visibility: visible !important;
    }
    .medialab-sortable .dashicons {
        color: #8c8f94;
        flex-shrink: 0;
        font-size: 18px;
        width: 18px;
        height: 18px;
    }
    #medialab-order-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-top: 1px solid #f0f0f1;
        padding-top: 1rem;
    }
    #medialab-order-status { font-size: 0.875rem; font-weight: 500; }
    </style>

    <div id="medialab-order-section" style="display:none;">
        <h2>Reihenfolge der Elemente</h2>
        <p>Elemente per Drag &amp; Drop sortieren, dann „Speichern" klicken.</p>

        <div class="medialab-order-grid">

            <div class="medialab-order-col">
                <h3>Kontakt</h3>
                <ul id="medialab-contact-sortable" class="medialab-sortable">
                    <?php foreach ( $contact_order as $key ) :
                        if ( ! isset( MEDIALAB_TOP_HEADER_CONTACT_ITEMS[ $key ] ) ) continue; ?>
                    <li data-key="<?php echo esc_attr( $key ); ?>">
                        <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                        <?php echo esc_html( MEDIALAB_TOP_HEADER_CONTACT_ITEMS[ $key ] ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="medialab-order-col">
                <h3>Social Media</h3>
                <ul id="medialab-social-sortable" class="medialab-sortable">
                    <?php foreach ( $social_order as $key ) :
                        if ( ! isset( MEDIALAB_TOP_HEADER_SOCIAL_ITEMS[ $key ] ) ) continue; ?>
                    <li data-key="<?php echo esc_attr( $key ); ?>">
                        <span class="dashicons dashicons-menu" aria-hidden="true"></span>
                        <?php echo esc_html( MEDIALAB_TOP_HEADER_SOCIAL_ITEMS[ $key ] ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div>

        <div id="medialab-order-actions">
            <button id="medialab-save-order" class="button button-primary">Reihenfolge speichern</button>
            <span id="medialab-order-status"></span>
        </div>
    </div>

    <script>
    jQuery(function ($) {
        var $section = $('#medialab-order-section');
        $('#wpbody-content .wrap').first().append($section);
        $section.show();

        var opts = {
            axis: 'y', cursor: 'grabbing',
            placeholder: 'ui-sortable-placeholder',
            forcePlaceholderSize: true,
            handle: '.dashicons-menu',
        };
        $('#medialab-contact-sortable').sortable(opts).disableSelection();
        $('#medialab-social-sortable').sortable(opts).disableSelection();

        $('#medialab-save-order').on('click', function () {
            var $btn = $(this), $status = $('#medialab-order-status');
            var contactOrder = [], socialOrder = [];

            $('#medialab-contact-sortable li').each(function () { contactOrder.push($(this).data('key')); });
            $('#medialab-social-sortable li').each(function ()  { socialOrder.push($(this).data('key')); });

            $btn.prop('disabled', true).text('Wird gespeichert…');
            $status.text('').css('color', '');

            $.post(ajaxurl, {
                action        : 'medialab_save_top_header_order',
                contact_order : contactOrder,
                social_order  : socialOrder,
                nonce         : <?php echo wp_json_encode( $nonce ); ?>,
            }, function (r) {
                $btn.prop('disabled', false).text('Reihenfolge speichern');
                $status.text(r.success ? '✓ Gespeichert' : '✗ Fehler').css('color', r.success ? '#00a32a' : '#d63638');
                setTimeout(function () { $status.text(''); }, 3500);
            }).fail(function () {
                $btn.prop('disabled', false).text('Reihenfolge speichern');
                $status.text('✗ Verbindungsfehler').css('color', '#d63638');
            });
        });
    });
    </script>
    <?php
} );

// ─── AJAX-Handler ────────────────────────────────────────────────────────────

add_action( 'wp_ajax_medialab_save_top_header_order', function () {
    check_ajax_referer( 'medialab_top_header_order', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Keine Berechtigung', 403 );

    $contact = medialab_sanitize_order(
        is_array( $_POST['contact_order'] ?? null ) ? $_POST['contact_order'] : [],
        array_keys( MEDIALAB_TOP_HEADER_CONTACT_ITEMS )
    );
    $social = medialab_sanitize_order(
        is_array( $_POST['social_order'] ?? null ) ? $_POST['social_order'] : [],
        array_keys( MEDIALAB_TOP_HEADER_SOCIAL_ITEMS )
    );

    update_option( MEDIALAB_TOP_HEADER_ORDER_OPTION,        wp_json_encode( $contact ), false );
    update_option( MEDIALAB_TOP_HEADER_SOCIAL_ORDER_OPTION, wp_json_encode( $social ),  false );

    wp_send_json_success();
} );
