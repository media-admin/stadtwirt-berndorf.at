<?php
/**
 * Blocked Dates – Österreichische Feiertage & Import
 *
 * Liefert:
 *  1. `mlb_get_at_holidays( int $year ): array`
 *     Gibt alle österreichischen gesetzlichen Feiertage für ein Jahr zurück.
 *     Feste Feiertage sind hardcoded, bewegliche Feiertage werden aus dem
 *     Ostersonntag (Gauss-Algorithmus) berechnet.
 *
 *  2. Admin-Metabox auf dem mlb_location-Edit-Screen:
 *     Button „Feiertage {Jahr} importieren" fügt Feiertage als Sperr-Einträge
 *     in den ACF-Repeater `mlb_blocked_periods` ein. Bestehende Einträge
 *     mit gleichem Datum werden übersprungen (kein Duplikat).
 *
 *  3. AJAX-Handler `mlb_import_holidays`:
 *     Verarbeitet den Import-Button (POST: location_id, year, holidays[]).
 *
 * @package MediaLabBookings
 * @since   1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// =============================================================================
// 1. FEIERTAGS-DATEN
// =============================================================================

/**
 * Gibt österreichische gesetzliche Feiertage für ein Jahr zurück.
 *
 * @param  int  $year  4-stellige Jahreszahl (z.B. 2025)
 * @return array<int, array{date: string, label: string}>  Sortiert nach Datum
 */
function mlb_get_at_holidays( int $year ): array {

    // ── Ostersonntag (Gauss-Algorithmus) ──────────────────────────────────────
    $a = $year % 19;
    $b = (int) ( $year / 100 );
    $c = $year % 100;
    $d = (int) ( $b / 4 );
    $e = $b % 4;
    $f = (int) ( ( $b + 8 ) / 25 );
    $g = (int) ( ( $b - $f + 1 ) / 3 );
    $h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
    $i = (int) ( $c / 4 );
    $k = $c % 4;
    $l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
    $m = (int) ( ( $a + 11 * $h + 22 * $l ) / 451 );
    $month = (int) ( ( $h + $l - 7 * $m + 114 ) / 31 );
    $day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

    $easter = mktime( 0, 0, 0, $month, $day, $year );
    $e = function ( int $offset_days ) use ( $easter ): string {
        return date( 'Y-m-d', strtotime( "+{$offset_days} days", $easter ) );
    };

    // ── Feste Feiertage ───────────────────────────────────────────────────────
    $fixed = [
        [ 'date' => sprintf( '%04d-01-01', $year ), 'label' => 'Neujahr'                  ],
        [ 'date' => sprintf( '%04d-01-06', $year ), 'label' => 'Heilige Drei Könige'      ],
        [ 'date' => sprintf( '%04d-05-01', $year ), 'label' => 'Staatsfeiertag'            ],
        [ 'date' => sprintf( '%04d-08-15', $year ), 'label' => 'Mariä Himmelfahrt'        ],
        [ 'date' => sprintf( '%04d-10-26', $year ), 'label' => 'Nationalfeiertag'         ],
        [ 'date' => sprintf( '%04d-11-01', $year ), 'label' => 'Allerheiligen'            ],
        [ 'date' => sprintf( '%04d-12-08', $year ), 'label' => 'Mariä Empfängnis'        ],
        [ 'date' => sprintf( '%04d-12-25', $year ), 'label' => 'Christtag'               ],
        [ 'date' => sprintf( '%04d-12-26', $year ), 'label' => 'Stefanitag'              ],
    ];

    // ── Bewegliche Feiertage ──────────────────────────────────────────────────
    $variable = [
        [ 'date' => $e( 1  ), 'label' => 'Ostermontag'         ],
        [ 'date' => $e( 39 ), 'label' => 'Christi Himmelfahrt' ],
        [ 'date' => $e( 50 ), 'label' => 'Pfingstmontag'       ],
        [ 'date' => $e( 60 ), 'label' => 'Fronleichnam'        ],
    ];

    $all = array_merge( $fixed, $variable );

    // Sortiert nach Datum
    usort( $all, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );

    return $all;
}

// =============================================================================
// 2. ADMIN-METABOX
// =============================================================================

add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'mlb_import_holidays',
        '🇦🇹 Österreichische Feiertage importieren',
        'mlb_render_import_holidays_metabox',
        'mlb_location',
        'side',
        'default'
    );
} );

function mlb_render_import_holidays_metabox( WP_Post $post ): void {
    $current_year = (int) date( 'Y' );
    $years        = [ $current_year, $current_year + 1 ];

    wp_nonce_field( 'mlb_import_holidays_' . $post->ID, '_mlb_import_nonce' );
    ?>
    <div id="mlb-import-holidays-box" style="font-size:13px;">
        <p style="color:#666;margin:0 0 .75rem;">
            Wähle ein Jahr und importiere alle gesetzlichen österreichischen Feiertage
            als Sperren in den Repeater „Sperren". Bereits vorhandene Datumseinträge
            werden übersprungen.
        </p>

        <?php foreach ( $years as $year ) :
            $holidays = mlb_get_at_holidays( $year );
        ?>
        <details style="margin-bottom:.75rem;">
            <summary style="cursor:pointer;font-weight:600;padding:.3rem 0;">
                Feiertage <?php echo esc_html( $year ); ?> (<?php echo count( $holidays ); ?> Einträge)
            </summary>
            <ol style="margin:.5rem 0 .75rem 1.25rem;padding:0;color:#555;">
                <?php foreach ( $holidays as $h ) : ?>
                    <li style="margin-bottom:.2rem;">
                        <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $h['date'] ) ) ); ?>
                        – <?php echo esc_html( $h['label'] ); ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <button type="button"
                    class="button button-secondary mlb-import-btn"
                    data-location="<?php echo esc_attr( $post->ID ); ?>"
                    data-year="<?php echo esc_attr( $year ); ?>"
                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'mlb_import_holidays' ) ); ?>"
                    style="width:100%;">
                Alle <?php echo esc_html( $year ); ?> importieren
            </button>
            <p class="mlb-import-result-<?php echo esc_attr( $year ); ?>"
               style="margin:.4rem 0 0;font-size:12px;"></p>
        </details>
        <?php endforeach; ?>
    </div>

    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.mlb-import-btn');
            if (!btn) return;

            btn.disabled = true;
            btn.textContent = 'Importiert…';
            var year     = btn.dataset.year;
            var resultEl = document.querySelector('.mlb-import-result-' + year);

            var fd = new FormData();
            fd.append('action',      'mlb_import_holidays');
            fd.append('nonce',       btn.dataset.nonce);
            fd.append('location_id', btn.dataset.location);
            fd.append('year',        year);

            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        if (resultEl) resultEl.textContent = '✓ ' + res.data.message;
                        // Seite neu laden damit der Repeater die neuen Einträge zeigt
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        btn.disabled    = false;
                        btn.textContent = 'Alle ' + year + ' importieren';
                        if (resultEl) resultEl.textContent = '✗ ' + (res.data && res.data.message ? res.data.message : 'Fehler');
                    }
                })
                .catch(function () {
                    btn.disabled    = false;
                    btn.textContent = 'Alle ' + year + ' importieren';
                    if (resultEl) resultEl.textContent = '✗ Netzwerkfehler';
                });
        });
    })();
    </script>
    <?php
}

// =============================================================================
// 3. AJAX-HANDLER: Import
// =============================================================================

add_action( 'wp_ajax_mlb_import_holidays', 'mlb_ajax_import_holidays' );

function mlb_ajax_import_holidays(): void {
    check_ajax_referer( 'mlb_import_holidays', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }

    $location_id = (int) ( $_POST['location_id'] ?? 0 );
    $year        = (int) ( $_POST['year']        ?? 0 );

    if ( ! $location_id || get_post_type( $location_id ) !== 'mlb_location' ) {
        wp_send_json_error( [ 'message' => 'Ungültiger Standort.' ] );
    }

    if ( $year < 2000 || $year > 2100 ) {
        wp_send_json_error( [ 'message' => 'Ungültiges Jahr.' ] );
    }

    if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
        wp_send_json_error( [ 'message' => 'ACF nicht verfügbar.' ] );
    }

    $holidays = mlb_get_at_holidays( $year );

    // Bestehende Einträge einlesen (Datum-Duplikate vermeiden)
    $existing_rows = get_field( 'mlb_blocked_periods', $location_id ) ?: [];
    $existing_dates = [];
    foreach ( $existing_rows as $row ) {
        if ( ! empty( $row['blocked_date_from'] ) ) {
            $existing_dates[] = $row['blocked_date_from'];
        }
    }

    $imported = 0;
    $skipped  = 0;
    $new_rows = $existing_rows; // Bestehende behalten

    foreach ( $holidays as $h ) {
        if ( in_array( $h['date'], $existing_dates, true ) ) {
            $skipped++;
            continue;
        }

        $new_rows[] = [
            'blocked_label'     => $h['label'],
            'blocked_type'      => 'day',
            'blocked_date_from' => $h['date'],
            'blocked_date_to'   => '',   // einzel Tag
            'blocked_time_from' => '',
            'blocked_time_to'   => '',
            'blocked_yearly'    => false,
        ];

        $imported++;
    }

    if ( $imported > 0 ) {
        update_field( 'mlb_blocked_periods', $new_rows, $location_id );
    }

    $msg = $imported > 0
        ? sprintf( '%d Feiertage importiert', $imported )
            . ( $skipped > 0 ? sprintf( ', %d bereits vorhanden übersprungen', $skipped ) : '' )
            . '.'
        : 'Alle Feiertage waren bereits vorhanden.';

    wp_send_json_success( [ 'message' => $msg, 'imported' => $imported, 'skipped' => $skipped ] );
}
