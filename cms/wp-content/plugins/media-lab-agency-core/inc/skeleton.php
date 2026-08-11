<?php
/**
 * Skeleton Loading States
 *
 * Zentrale, wiederverwendbare Skeleton-Screen-API für alle Plugins/Themes
 * des Starter Kits. Ersetzt Spinner/Opacity-Loading-States durch
 * Content-förmige Platzhalter, um die gefühlte Ladezeit bei AJAX-Requests
 * (Produktfilter, Booking-Slots, ...) und bei verzögerter JS-Initialisierung
 * (z.B. Swiper) zu verkürzen.
 *
 * PHP-Helper:  medialab_render_skeleton( $type, $count, $args )
 * JS-Helper:   window.MediaLabSkeleton.show( container, options )
 *              window.MediaLabSkeleton.clear( container )
 *
 * Konsumenten (Stand 1.18.0):
 *  - custom-theme/assets/src/js/components/ajax-filters.js
 *
 * Geplante Konsumenten (siehe Roadmap):
 *  - media-lab-woocommerce Produktfilter-Frontend (client-spezifisch)
 *  - media-lab-bookings Slot-Anzeige
 *  - medialab/slider, medialab/parallax Blocks (initiales Laden bis
 *    Swiper-Init)
 *
 * @package Agency_Core
 * @since   1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'medialab_skeleton_lines' ) ) {
	/**
	 * Erzeugt Text-Zeilen-Platzhalter mit variierender Breite (erste Zeile
	 * etwas breiter/als Titel markiert, letzte Zeile kürzer), damit das
	 * Ergebnis nicht wie ein starres Raster wirkt.
	 *
	 * @param int $lines Anzahl Zeilen.
	 * @return string
	 */
	function medialab_skeleton_lines( int $lines ): string {
		$lines = max( 1, $lines );
		$html  = '';

		for ( $i = 0; $i < $lines; $i++ ) {
			$modifier = ( 0 === $i ) ? ' medialab-skeleton--text-title' : '';
			if ( $lines > 1 && $i === $lines - 1 ) {
				$modifier .= ' medialab-skeleton--text-short';
			}
			$html .= '<div class="medialab-skeleton medialab-skeleton--text' . $modifier . '"></div>';
		}

		return $html;
	}
}

if ( ! function_exists( 'medialab_render_skeleton' ) ) {
	/**
	 * Rendert Skeleton-Platzhalter als HTML-String.
	 *
	 * Die Items bekommen zusätzlich zur Skeleton-Klasse optional die
	 * echte Karten-Klasse (item_class), damit bestehendes Grid-/List-CSS
	 * (Spaltenbreite, Gap, ...) unverändert greift und im Plugin nur die
	 * *interne* Optik der Platzhalter definiert werden muss.
	 *
	 * @param string $type  'card' | 'list' | 'text' | 'slide'.
	 * @param int    $count Anzahl der Platzhalter-Items.
	 * @param array  $args  {
	 *     @type string $item_class Zusätzliche Klasse(n) am Item-Wrapper
	 *                              (z.B. 'post-card', 'project-card').
	 *     @type bool   $image      Bild-/Medien-Platzhalter anzeigen
	 *                              (default true; ignoriert bei 'text').
	 *     @type int    $lines      Anzahl Text-Zeilen (default 3).
	 * }
	 * @return string
	 */
	function medialab_render_skeleton( string $type = 'card', int $count = 3, array $args = array() ): string {
		$defaults = array(
			'item_class' => '',
			'image'      => true,
			'lines'      => 3,
		);
		$args  = wp_parse_args( $args, $defaults );
		$count = max( 1, $count );
		$type  = in_array( $type, array( 'card', 'list', 'text', 'slide' ), true ) ? $type : 'card';

		$item_class = trim( 'medialab-skeleton-item ' . $args['item_class'] );

		$html = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$html .= '<div class="' . esc_attr( $item_class ) . '" data-medialab-skeleton aria-hidden="true">';

			if ( 'slide' === $type ) {
				$html .= '<div class="medialab-skeleton medialab-skeleton--media medialab-skeleton--slide"></div>';
			} elseif ( 'list' === $type ) {
				$html .= '<div class="medialab-skeleton medialab-skeleton--media medialab-skeleton--thumb"></div>';
				$html .= '<div class="medialab-skeleton-lines">' . medialab_skeleton_lines( 2 ) . '</div>';
			} else {
				if ( $args['image'] && 'text' !== $type ) {
					$html .= '<div class="medialab-skeleton medialab-skeleton--media"></div>';
				}
				$html .= '<div class="medialab-skeleton-lines">' . medialab_skeleton_lines( max( 1, (int) $args['lines'] ) ) . '</div>';
			}

			$html .= '</div>';
		}

		return $html;
	}
}

if ( ! function_exists( 'medialab_the_skeleton' ) ) {
	/**
	 * Gibt medialab_render_skeleton() direkt aus.
	 */
	function medialab_the_skeleton( string $type = 'card', int $count = 3, array $args = array() ): void {
		echo medialab_render_skeleton( $type, $count, $args ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Skeleton-CSS + JS zentral & site-weit registrieren.
 *
 * Das JS wird bewusst OHNE type="module"/defer als klassisches Script
 * eingebunden, damit window.MediaLabSkeleton beim Ausführen von
 * abhängigen Theme-/Plugin-Skripten garantiert bereits existiert.
 * custom-theme/inc/enqueue.php führt 'medialab-skeleton' entsprechend
 * als Dependency von 'custom-theme-script'.
 */
if ( ! function_exists( 'medialab_enqueue_skeleton_assets' ) ) {
	function medialab_enqueue_skeleton_assets(): void {
		wp_enqueue_style(
			'medialab-skeleton',
			MEDIALAB_CORE_URL . 'assets/css/skeleton.css',
			array(),
			MEDIALAB_CORE_VERSION
		);

		wp_enqueue_script(
			'medialab-skeleton',
			MEDIALAB_CORE_URL . 'assets/js/skeleton.js',
			array(),
			MEDIALAB_CORE_VERSION,
			false
		);
	}
}
add_action( 'wp_enqueue_scripts', 'medialab_enqueue_skeleton_assets', 5 );
