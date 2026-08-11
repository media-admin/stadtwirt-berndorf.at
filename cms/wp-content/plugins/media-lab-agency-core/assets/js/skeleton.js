/**
 * MediaLabSkeleton
 *
 * Zentraler, framework-freier Helper zum Anzeigen von Skeleton-Platzhaltern
 * während AJAX-Requests oder verzögerter JS-Initialisierung (Swiper etc.).
 * Wird site-weit von media-lab-agency-core geladen (siehe inc/skeleton.php)
 * und ist damit in jedem Client-Projekt automatisch verfügbar, sobald das
 * Plugin aktiv ist – auch für Theme-Skripte per Dependency.
 *
 * Nutzung:
 *   window.MediaLabSkeleton.show(container, {
 *     type: 'card',        // 'card' | 'list' | 'text' | 'slide'
 *     count: 6,             // Anzahl Platzhalter
 *     itemClass: 'post-card', // Klasse für bestehendes Grid-/List-CSS
 *   });
 *
 *   // Wird meist nicht explizit gebraucht: sobald der Container per
 *   // innerHTML mit echten Ergebnissen befüllt wird, sind die Skeleton-
 *   // Items automatisch weg. Für den Fehlerfall/Abbruch:
 *   window.MediaLabSkeleton.clear(container);
 *
 * @package Agency_Core
 * @since   1.18.0
 */
( function ( window, document ) {
	'use strict';

	var SKELETON_SELECTOR = '[data-medialab-skeleton]';

	/**
	 * Baut die Zeilen-Platzhalter (erste Zeile = Titel, letzte kürzer).
	 */
	function buildLines( lines ) {
		var frag = document.createDocumentFragment();
		var wrap = document.createElement( 'div' );
		wrap.className = 'medialab-skeleton-lines';

		for ( var i = 0; i < lines; i++ ) {
			var line = document.createElement( 'div' );
			var cls = 'medialab-skeleton medialab-skeleton--text';
			if ( i === 0 ) {
				cls += ' medialab-skeleton--text-title';
			}
			if ( lines > 1 && i === lines - 1 ) {
				cls += ' medialab-skeleton--text-short';
			}
			line.className = cls;
			wrap.appendChild( line );
		}

		frag.appendChild( wrap );
		return frag;
	}

	/**
	 * Baut ein einzelnes Skeleton-Item passend zum gewünschten Typ.
	 */
	function buildItem( type, itemClass, opts ) {
		var item = document.createElement( 'div' );
		item.className = ( 'medialab-skeleton-item ' + ( itemClass || '' ) ).trim();
		item.setAttribute( 'data-medialab-skeleton', '' );
		item.setAttribute( 'aria-hidden', 'true' );

		if ( type === 'slide' ) {
			var slide = document.createElement( 'div' );
			slide.className = 'medialab-skeleton medialab-skeleton--media medialab-skeleton--slide';
			item.appendChild( slide );
			return item;
		}

		if ( type === 'list' ) {
			var thumb = document.createElement( 'div' );
			thumb.className = 'medialab-skeleton medialab-skeleton--media medialab-skeleton--thumb';
			item.appendChild( thumb );
			item.appendChild( buildLines( 2 ) );
			return item;
		}

		if ( type !== 'text' && opts.image !== false ) {
			var media = document.createElement( 'div' );
			media.className = 'medialab-skeleton medialab-skeleton--media';
			item.appendChild( media );
		}

		item.appendChild( buildLines( Math.max( 1, opts.lines || 3 ) ) );
		return item;
	}

	var MediaLabSkeleton = {
		/**
		 * Zeigt Skeleton-Platzhalter in einem Container an.
		 *
		 * Vorhandener Inhalt wird NICHT entfernt, sondern per data-Attribut
		 * versteckt und beim clear()/Neubefüllen wiederhergestellt – so
		 * bleibt das Verhalten sicher, auch wenn eine Aufrufstelle vergisst,
		 * clear() zu rufen (der nächste innerHTML-Replace räumt ohnehin auf).
		 *
		 * @param {Element} container
		 * @param {Object}  options
		 * @param {string}  [options.type='card']
		 * @param {number}  [options.count=6]
		 * @param {string}  [options.itemClass='']
		 * @param {boolean} [options.image=true]
		 * @param {number}  [options.lines=3]
		 * @param {boolean} [options.append=false] An bestehenden Inhalt anhängen statt zu ersetzen
		 */
		show: function ( container, options ) {
			if ( ! container ) {
				return;
			}
			options = options || {};

			var type = options.type || 'card';
			var count = options.count || 6;

			if ( ! options.append ) {
				this.clear( container );
				container.innerHTML = '';
			}

			var frag = document.createDocumentFragment();
			for ( var i = 0; i < count; i++ ) {
				frag.appendChild( buildItem( type, options.itemClass, options ) );
			}
			container.appendChild( frag );
			container.setAttribute( 'aria-busy', 'true' );
		},

		/**
		 * Entfernt alle vom Skeleton-System erzeugten Platzhalter-Elemente
		 * aus einem Container (lässt sonstigen Inhalt unangetastet).
		 *
		 * @param {Element} container
		 */
		clear: function ( container ) {
			if ( ! container ) {
				return;
			}
			var nodes = container.querySelectorAll( SKELETON_SELECTOR );
			for ( var i = 0; i < nodes.length; i++ ) {
				nodes[ i ].parentNode && nodes[ i ].parentNode.removeChild( nodes[ i ] );
			}
			container.removeAttribute( 'aria-busy' );
		},

		/**
		 * True, wenn der Container aktuell (noch) Skeleton-Platzhalter zeigt.
		 *
		 * @param {Element} container
		 * @return {boolean}
		 */
		isShowing: function ( container ) {
			return !! ( container && container.querySelector( SKELETON_SELECTOR ) );
		}
	};

	window.MediaLabSkeleton = MediaLabSkeleton;
} )( window, document );
