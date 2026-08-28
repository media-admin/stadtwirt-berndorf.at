<?php
/**
 * Notification Overrides – Stadtwirt Berndorf
 *
 * Kunde möchte: Popup-/Toast-Notifications (Core Plugin: media-lab-agency-core)
 * sollen bei JEDEM Besuch angezeigt werden, nicht nur 1x.
 *
 * Hintergrund:
 * Die JS-Klasse `Notifications` (media-lab-agency-core, notifications.js)
 * merkt sich geschlossene Popups/Toasts dauerhaft in localStorage:
 *   - Popup:  popup_dismissed_{id}
 *   - Toast:  notification_dismissed_{id}
 * Diese Werte werden beim Schließen (X, Overlay-Klick, ESC) gesetzt und beim
 * nächsten Laden in `initFromCPT()` geprüft (Popup) bzw. `isDismissed()` (Toast).
 * Banner sind NICHT betroffen – die haben keine Persistenz-Prüfung.
 *
 * Anstatt das Core Plugin zu patchen (würde SK + alle anderen Projekte
 * betreffen und bei Plugin-Updates überschrieben werden), löschen wir hier
 * projektspezifisch die relevanten localStorage-Keys – BEVOR das Plugin-Script
 * seine Dismiss-Prüfung durchführt (daher `wp_head` mit Priorität 1 – läuft
 * vor `wp_footer`, wo die Notifications-Klasse initialisiert wird).
 *
 * Update: Reset ist auf 1x pro Session begrenzt (via sessionStorage-Flag),
 * damit die Notification nicht bei jeder einzelnen Seitennavigation innerhalb
 * desselben Besuchs erneut erscheint. Erst ein neuer Tab / neue Session
 * (sessionStorage wird beim Schließen des Tabs geleert) löst den nächsten
 * Reset aus. Wird die Notification innerhalb der Session vom Besucher
 * weggeklickt, bleibt sie für den Rest der Session geschlossen.
 *
 * Betrifft: nur stadtwirt-berndorf.at
 * Core Plugin (media-lab-agency-core) und SK bleiben unverändert.
 *
 * @package Stadtwirt_Theme
 * @since   1.0.0
 * @updated 1.1.0  Reset auf 1x pro Session begrenzt (sessionStorage-Flag)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', function () {
    ?>
    <script>
    (function () {
        try {
            var FLAG = 'ml_notifications_reset_done';

            // Bereits in dieser Session zurückgesetzt → nichts tun,
            // Dismiss-Status aus localStorage bleibt für den Rest der Session gültig.
            if ( sessionStorage.getItem( FLAG ) ) return;

            Object.keys( localStorage ).forEach( function ( key ) {
                if ( key.indexOf( 'popup_dismissed_' ) === 0 || key.indexOf( 'notification_dismissed_' ) === 0 ) {
                    localStorage.removeItem( key );
                }
            } );

            sessionStorage.setItem( FLAG, '1' );
        } catch ( e ) {
            // localStorage/sessionStorage evtl. nicht verfügbar (Private Mode etc.)
        }
    })();
    </script>
    <?php
}, 1 );
