<?php
/**
 * Top Header – Drag & Drop + Arrow Button Reihenfolge
 *
 * Sortierung der Kontakt-Elemente und Social-Media-Kanäle im Top Header.
 * Unterstützt zwei Eingabemethoden:
 *   1. Drag & Drop via jquery-ui-sortable
 *   2. ▲ / ▼ Pfeil-Buttons (Tastatur- und Touch-freundlich)
 *
 * Gespeichert wird in jedem Fall via AJAX in wp_options:
 *   medialab_top_header_item_order   – Kontakt-Elemente
 *   medialab_top_header_social_order – Social-Kanäle
 *
 * @package  media-lab-agency-core
 * @since    1.9.1  Drag & Drop
 * @since    1.9.2  Arrow Buttons
 */

defined( 'ABSPATH' ) || exit;

// ── Hilfsfunktionen ───────────────────────────────────────────────────────────

/**
 * Standard-Reihenfolge der Kontakt-Elemente.
 *
 * @return string[]
 */
function medialab_get_default_item_order(): array {
    return [ 'address', 'hours', 'phone', 'email' ];
}

/**
 * Standard-Reihenfolge der Social-Kanäle.
 *
 * @return string[]
 */
function medialab_get_default_social_order(): array {
    return [ 'facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'xing' ];
}

/**
 * Gespeicherte Reihenfolge der Kontakt-Elemente lesen.
 * Fehlende Keys werden mit Standard-Werten aufgefüllt.
 *
 * @return string[]
 */
function medialab_get_top_header_order(): array {
    $saved    = get_option( 'medialab_top_header_item_order', [] );
    $defaults = medialab_get_default_item_order();
 
    // Rückwärtskompatibilität: altes Format speicherte JSON-String statt Array.
    if ( is_string( $saved ) ) {
        $saved = json_decode( $saved, true ) ?? [];
    }
 
    if ( empty( $saved ) ) {
        return $defaults;
    }
 
    $saved = array_values( array_unique( array_merge( $saved, $defaults ) ) );
 
    return array_filter( $saved, fn( $k ) => in_array( $k, $defaults, true ) );
}

/**
 * Gespeicherte Reihenfolge der Social-Kanäle lesen.
 * Fehlende Keys werden mit Standard-Werten aufgefüllt.
 *
 * @return string[]
 */
function medialab_get_top_header_social_order(): array {
    $saved    = get_option( 'medialab_top_header_social_order', [] );
    $defaults = medialab_get_default_social_order();
 
    // Rückwärtskompatibilität: altes Format speicherte JSON-String statt Array.
    if ( is_string( $saved ) ) {
        $saved = json_decode( $saved, true ) ?? [];
    }
 
    if ( empty( $saved ) ) {
        return $defaults;
    }
 
    $saved = array_values( array_unique( array_merge( $saved, $defaults ) ) );
 
    return array_filter( $saved, fn( $k ) => in_array( $k, $defaults, true ) );
}

// ── Admin-Seite registrieren ──────────────────────────────────────────────────

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
        return;
    }

    acf_add_options_sub_page( [
        'page_title'  => __( 'Top Header', 'media-lab' ),
        'menu_title'  => __( 'Top Header', 'media-lab' ),
        'menu_slug'   => 'agency-core-top-header',
        'parent_slug' => 'agency-core',
        'capability'  => 'manage_options',
    ] );
} );

// ── Assets einbinden ──────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', function ( string $hook ) {
    if ( ! str_contains( $hook, 'agency-core-top-header' ) ) {
        return;
    }

    wp_enqueue_script( 'jquery-ui-sortable' );

    wp_add_inline_style( 'wp-admin', medialab_top_header_order_css() );

    wp_add_inline_script(
        'jquery-ui-sortable',
        medialab_top_header_order_js(),
        'after'
    );
} );

// ── Admin-Seiten-Output ───────────────────────────────────────────────────────

add_action( 'acf/options_page/submitbox_before_submit', function () {
    $screen = get_current_screen();
    if ( ! $screen || ! str_contains( $screen->id, 'agency-core-top-header' ) ) {
        return;
    }

    medialab_render_top_header_order_ui();
} );

/**
 * Gibt die vollständige Sortier-Oberfläche aus.
 */
function medialab_render_top_header_order_ui(): void {
    $item_labels = [
        'address' => __( 'Adresse', 'media-lab' ),
        'hours'   => __( 'Öffnungszeiten', 'media-lab' ),
        'phone'   => __( 'Telefon', 'media-lab' ),
        'email'   => __( 'E-Mail', 'media-lab' ),
    ];

    $social_labels = [
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin'  => 'LinkedIn',
        'twitter'   => 'X / Twitter',
        'youtube'   => 'YouTube',
        'xing'      => 'Xing',
    ];

    $item_order   = medialab_get_top_header_order();
    $social_order = medialab_get_top_header_social_order();

    ?>
    <div class="ml-order-wrap">
        <h2><?php esc_html_e( 'Reihenfolge der Elemente', 'media-lab' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Elemente per Drag & Drop oder Pfeil-Buttons verschieben. Wird sofort gespeichert.', 'media-lab' ); ?>
        </p>

        <div id="ml-order-notice" role="status" aria-live="polite"></div>

        <div class="ml-order-columns">

            <div class="ml-order-col">
                <h3><?php esc_html_e( 'Kontakt-Elemente', 'media-lab' ); ?></h3>
                <ul id="ml-sortable-items" class="ml-sortable"
                    data-option="medialab_top_header_item_order"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'medialab_top_header_order' ) ); ?>">
                    <?php foreach ( $item_order as $key ) :
                        if ( ! isset( $item_labels[ $key ] ) ) continue; ?>
                        <li class="ml-sortable-item" data-key="<?php echo esc_attr( $key ); ?>">
                            <span class="ml-drag-handle" aria-hidden="true" title="<?php esc_attr_e( 'Ziehen zum Sortieren', 'media-lab' ); ?>">⠿</span>
                            <span class="ml-item-label"><?php echo esc_html( $item_labels[ $key ] ); ?></span>
                            <span class="ml-arrow-buttons">
                                <button type="button" class="ml-btn-up button button-small" aria-label="<?php esc_attr_e( 'Nach oben', 'media-lab' ); ?>">▲</button>
                                <button type="button" class="ml-btn-down button button-small" aria-label="<?php esc_attr_e( 'Nach unten', 'media-lab' ); ?>">▼</button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="ml-order-col">
                <h3><?php esc_html_e( 'Social-Media-Kanäle', 'media-lab' ); ?></h3>
                <ul id="ml-sortable-social" class="ml-sortable"
                    data-option="medialab_top_header_social_order"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'medialab_top_header_order' ) ); ?>">
                    <?php foreach ( $social_order as $key ) :
                        if ( ! isset( $social_labels[ $key ] ) ) continue; ?>
                        <li class="ml-sortable-item" data-key="<?php echo esc_attr( $key ); ?>">
                            <span class="ml-drag-handle" aria-hidden="true" title="<?php esc_attr_e( 'Ziehen zum Sortieren', 'media-lab' ); ?>">⠿</span>
                            <span class="ml-item-label"><?php echo esc_html( $social_labels[ $key ] ); ?></span>
                            <span class="ml-arrow-buttons">
                                <button type="button" class="ml-btn-up button button-small" aria-label="<?php esc_attr_e( 'Nach oben', 'media-lab' ); ?>">▲</button>
                                <button type="button" class="ml-btn-down button button-small" aria-label="<?php esc_attr_e( 'Nach unten', 'media-lab' ); ?>">▼</button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div><!-- .ml-order-columns -->
    </div><!-- .ml-order-wrap -->
    <?php
}

// ── AJAX Handler ──────────────────────────────────────────────────────────────

add_action( 'wp_ajax_medialab_save_top_header_order', function () {
    check_ajax_referer( 'medialab_top_header_order', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
    }

    $option = sanitize_key( wp_unslash( $_POST['option'] ?? '' ) );
    $order  = array_map( 'sanitize_key', (array) ( $_POST['order'] ?? [] ) );

    $allowed = [
        'medialab_top_header_item_order'   => medialab_get_default_item_order(),
        'medialab_top_header_social_order' => medialab_get_default_social_order(),
    ];

    if ( ! array_key_exists( $option, $allowed ) ) {
        wp_send_json_error( [ 'message' => 'Ungültige Option.' ], 400 );
    }

    // Nur bekannte Keys zulassen.
    $order = array_values(
        array_filter( $order, fn( $k ) => in_array( $k, $allowed[ $option ], true ) )
    );

    update_option( $option, $order );

    wp_send_json_success( [
        'message' => __( 'Reihenfolge gespeichert.', 'media-lab' ),
        'order'   => $order,
    ] );
} );

// ── Inline CSS ────────────────────────────────────────────────────────────────

function medialab_top_header_order_css(): string {
    return '
    /* ── Top Header Order UI ─────────────────────────────────────── */

    .ml-order-wrap {
        margin: 24px 0 32px;
        padding: 20px 24px;
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
    }

    .ml-order-wrap h2 {
        margin: 0 0 4px;
        font-size: 14px;
        font-weight: 600;
    }

    .ml-order-wrap .description {
        margin: 0 0 16px;
        color: #646970;
    }

    .ml-order-columns {
        display: flex;
        gap: 32px;
        flex-wrap: wrap;
    }

    .ml-order-col {
        flex: 1;
        min-width: 220px;
    }

    .ml-order-col h3 {
        margin: 0 0 8px;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    /* Liste */
    .ml-sortable {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .ml-sortable-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        margin-bottom: 4px;
        background: #f6f7f7;
        border: 1px solid #dcdcde;
        border-radius: 3px;
        user-select: none;
        transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
    }

    .ml-sortable-item:last-child {
        margin-bottom: 0;
    }

    .ml-sortable-item:hover {
        background: #f0f6fc;
        border-color: #2271b1;
    }

    /* Drag-Handle */
    .ml-drag-handle {
        color: #a7aaad;
        cursor: grab;
        font-size: 16px;
        line-height: 1;
        flex-shrink: 0;
        transition: color 0.15s;
    }

    .ml-drag-handle:hover { color: #2271b1; }
    .ml-drag-handle:active { cursor: grabbing; }

    /* Label */
    .ml-item-label {
        flex: 1;
        font-size: 13px;
        color: #1d2327;
    }

    /* Arrow Buttons */
    .ml-arrow-buttons {
        display: flex;
        gap: 3px;
        flex-shrink: 0;
    }

    .ml-arrow-buttons .button {
        padding: 0 7px;
        min-height: 26px;
        line-height: 24px;
        font-size: 11px;
        color: #646970;
        border-color: #c3c4c7;
        background: #fff;
    }

    .ml-arrow-buttons .button:hover {
        color: #2271b1;
        border-color: #2271b1;
        background: #f0f6fc;
    }

    .ml-arrow-buttons .button:focus {
        color: #2271b1;
        border-color: #2271b1;
        box-shadow: 0 0 0 2px #2271b11a;
        outline: none;
    }

    /* Deaktivierter Zustand – erstes/letztes Element */
    .ml-arrow-buttons .button:disabled {
        opacity: .35;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Drag-Placeholder */
    .ml-sortable .ui-sortable-placeholder {
        background: #e8f0fe;
        border: 2px dashed #2271b1;
        border-radius: 3px;
        visibility: visible !important;
        height: 40px;
    }

    /* Aktives Drag-Element */
    .ml-sortable .ui-sortable-helper {
        box-shadow: 0 4px 12px rgba(0,0,0,.15);
        background: #fff;
        border-color: #2271b1;
        border-radius: 3px;
    }

    /* Status-Notice */
    #ml-order-notice {
        min-height: 24px;
        margin-bottom: 12px;
        font-size: 13px;
        transition: opacity 0.3s;
    }

    #ml-order-notice.ml-notice--success { color: #00a32a; }
    #ml-order-notice.ml-notice--error   { color: #d63638; }
    #ml-order-notice.ml-notice--saving  { color: #646970; }
    ';
}

// ── Inline JS ─────────────────────────────────────────────────────────────────

function medialab_top_header_order_js(): string {
    return '
(function ($) {
    "use strict";

    var $notice   = $("#ml-order-notice");
    var saveTimer = null;

    // ── Hilfsfunktionen ───────────────────────────────────────────────────────

    /**
     * Pfeil-Buttons je nach Position (erstes/letztes Element) deaktivieren.
     * @param {jQuery} $list
     */
    function updateArrowStates($list) {
        $list.find(".ml-sortable-item").each(function (index, el) {
            var $el     = $(el);
            var $items  = $list.find(".ml-sortable-item");
            var isFirst = index === 0;
            var isLast  = index === $items.length - 1;

            $el.find(".ml-btn-up").prop("disabled", isFirst);
            $el.find(".ml-btn-down").prop("disabled", isLast);
        });
    }

    /**
     * Status-Meldung anzeigen und nach 2,5 s automatisch ausblenden.
     * @param {string} message
     * @param {string} type  success | error | saving
     */
    function showNotice(message, type) {
        $notice
            .removeClass("ml-notice--success ml-notice--error ml-notice--saving")
            .addClass("ml-notice--" + type)
            .text(message)
            .css("opacity", 1);

        if (type !== "saving") {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function () {
                $notice.css("opacity", 0);
            }, 2500);
        }
    }

    /**
     * Aktuelle Reihenfolge einer Liste per AJAX speichern.
     * @param {jQuery} $list
     */
    function saveOrder($list) {
        var order = $list.find(".ml-sortable-item").map(function () {
            return $(this).data("key");
        }).get();

        showNotice("Speichern …", "saving");

        $.post(ajaxurl, {
            action:  "medialab_save_top_header_order",
            nonce:   $list.data("nonce"),
            option:  $list.data("option"),
            order:   order
        })
        .done(function (res) {
            if (res.success) {
                showNotice("✓ Reihenfolge gespeichert.", "success");
            } else {
                showNotice("Fehler beim Speichern.", "error");
            }
        })
        .fail(function () {
            showNotice("Netzwerkfehler.", "error");
        });
    }

    // ── Drag & Drop ───────────────────────────────────────────────────────────

    $(".ml-sortable").sortable({
        handle:      ".ml-drag-handle",
        placeholder: "ui-sortable-placeholder",
        tolerance:   "pointer",
        axis:        "y",
        start: function (e, ui) {
            ui.placeholder.height(ui.item.outerHeight());
        },
        update: function () {
            var $list = $(this);
            updateArrowStates($list);
            saveOrder($list);
        }
    }).disableSelection();

    // ── Pfeil-Buttons ─────────────────────────────────────────────────────────

    $(document).on("click", ".ml-btn-up, .ml-btn-down", function () {
        var $btn    = $(this);
        var $item   = $btn.closest(".ml-sortable-item");
        var $list   = $item.closest(".ml-sortable");
        var isUp    = $btn.hasClass("ml-btn-up");

        if (isUp) {
            var $prev = $item.prev(".ml-sortable-item");
            if ($prev.length) {
                // Smooth-Swap: Element vor den Vorgänger setzen.
                $item.insertBefore($prev);
            }
        } else {
            var $next = $item.next(".ml-sortable-item");
            if ($next.length) {
                $item.insertAfter($next);
            }
        }

        // Button-Zustände und Fokus aktualisieren.
        updateArrowStates($list);

        // Fokus auf das verschobene Element zurücksetzen (Accessibility).
        if (isUp) {
            $item.find(".ml-btn-up").focus();
        } else {
            $item.find(".ml-btn-down").focus();
        }

        saveOrder($list);
    });

    // ── Initialisierung ───────────────────────────────────────────────────────

    $(".ml-sortable").each(function () {
        updateArrowStates($(this));
    });

}(jQuery));
    ';
}
