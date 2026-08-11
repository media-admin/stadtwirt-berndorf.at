<?php
/**
 * Post Navigation (Backend Prev/Next)
 *
 * Fügt "Voriger/Nächster"-Navigation direkt auf der Post/Page/CPT- sowie
 * Taxonomy-Term-Detailseite im Backend hinzu (analog zu Drittanbieter-
 * Plugins wie "Post Navigation" bzw. "Admin Posts Navigation" — hier aber
 * für alle Inhaltstypen inkl. Taxonomien, und mit engerer Kopplung an die
 * bestehende Post-Order-Funktion).
 *
 * Reihenfolge:
 *   - Ist Post Order für den Post Type / die Taxonomie aktiv (siehe
 *     MediaLab_Post_Order), wird nach menu_order navigiert.
 *   - Andernfalls Fallback auf die WP-Standardreihenfolge der jeweiligen
 *     Listenansicht (hierarchische Post Types: Titel A–Z, sonst Datum
 *     absteigend; Taxonomien: Name A–Z).
 *
 * Filter-/Suchkontext:
 *   - Kommt man von einer gefilterten/durchsuchten Übersichtsseite
 *     (edit.php bzw. edit-tags.php) auf die Detailseite, wird dieser
 *     Kontext (Suche, Status, Autor, Monatsarchiv, Taxonomie-Filter)
 *     übernommen, sodass "Voriger/Nächster" nur innerhalb der gefilterten
 *     Menge navigiert. Der Kontext wird über einen signierten Query-Param
 *     (mlpn_ctx) an die Prev/Next-Links weitergereicht, damit er über
 *     mehrere Navigationsschritte hinweg erhalten bleibt.
 *
 * UI:
 *   - Klassischer Button-Bereich oberhalb des Titelfelds (funktioniert in
 *     Classic Editor UND Block Editor, da 'edit_form_top' in beiden Fällen
 *     rendert).
 *   - Zusätzlich ein Gutenberg-Sidebar-Panel ("Document Settings") für
 *     Post Types mit Block Editor.
 *   - Für Taxonomy-Terms: klassischer Button-Bereich auf term.php via
 *     "{$taxonomy}_term_edit_form_top".
 *
 * @package MediaLab_Core
 * @since   1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Post_Navigation {

	/**
	 * Query-Param, über den der Filter-/Suchkontext zwischen den
	 * Navigationsschritten weitergereicht wird.
	 */
	const CTX_PARAM = 'mlpn_ctx';

	/**
	 * Post Types, die grundsätzlich nie navigierbar sind (rein interne,
	 * nicht redaktionell editierte Typen). Über den Filter
	 * 'medialab_post_navigation_excluded_post_types' erweiterbar.
	 */
	private $excluded_types = array(
		'attachment', 'revision', 'nav_menu_item',
		'custom_css', 'customize_changeset', 'oembed_cache',
		'user_request', 'wp_block', 'wp_template',
		'wp_template_part', 'wp_global_styles', 'wp_navigation',
		'wp_font_family', 'wp_font_face',
	);

	/**
	 * Taxonomien, die grundsätzlich nie navigierbar sind. Über den Filter
	 * 'medialab_post_navigation_excluded_taxonomies' erweiterbar.
	 */
	private $excluded_taxonomies = array(
		'nav_menu', 'link_category', 'post_format',
		'wp_theme', 'wp_template_part_area', 'wp_pattern_category',
	);

	/**
	 * Obergrenze für die Anzahl an IDs, die zur Positionsermittlung geladen
	 * werden (Performance-Schutz bei sehr großen Katalogen). Über den
	 * Filter 'medialab_post_navigation_max_items' konfigurierbar.
	 */
	const DEFAULT_MAX_ITEMS = 5000;

	public function __construct() {
		// Posts/Pages/CPTs – klassischer Button-Bereich (funktioniert auch im Block Editor)
		add_action( 'edit_form_top', array( $this, 'render_classic_post_nav' ) );

		// Gutenberg – Sidebar-Panel
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		// CSS für den klassischen Button-Bereich
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_classic_assets' ) );

		// Taxonomy-Terms – klassischer Button-Bereich auf term.php
		add_action( 'admin_init', array( $this, 'register_term_edit_hooks' ) );
	}

	// =========================================================================
	// Hilfsmethoden – zulässige Post Types / Taxonomien
	// =========================================================================

	private function get_excluded_post_types(): array {
		return apply_filters( 'medialab_post_navigation_excluded_post_types', $this->excluded_types );
	}

	private function get_excluded_taxonomies(): array {
		return apply_filters( 'medialab_post_navigation_excluded_taxonomies', $this->excluded_taxonomies );
	}

	private function is_post_type_navigable( string $post_type ): bool {
		if ( in_array( $post_type, $this->get_excluded_post_types(), true ) ) return false;

		$obj = get_post_type_object( $post_type );
		return $obj && $obj->show_ui;
	}

	private function is_taxonomy_navigable( string $taxonomy ): bool {
		if ( in_array( $taxonomy, $this->get_excluded_taxonomies(), true ) ) return false;

		$obj = get_taxonomy( $taxonomy );
		return $obj && $obj->show_ui;
	}

	/**
	 * Aktivierte Post-Order-Post-Types (menu_order-Sortierung), ohne
	 * Abhängigkeit von einer bereits instanziierten MediaLab_Post_Order.
	 * 'page' ist dort immer enthalten (siehe MediaLab_Post_Order::get_sortable_types()).
	 */
	private function get_post_order_active_types(): array {
		if ( ! class_exists( 'MediaLab_Post_Order' ) ) return array();

		$saved = get_option( MediaLab_Post_Order::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) $saved = array();

		return array_unique( array_merge( array( 'page' ), $saved ) );
	}

	/**
	 * Aktivierte Post-Order-Taxonomien (menu_order-Sortierung).
	 */
	private function get_post_order_active_taxonomies(): array {
		if ( ! class_exists( 'MediaLab_Post_Order' ) ) return array();

		$saved = get_option( MediaLab_Post_Order::OPTION_KEY_TERMS, array() );
		return is_array( $saved ) ? $saved : array();
	}

	private function get_max_items(): int {
		return max( 1, (int) apply_filters( 'medialab_post_navigation_max_items', self::DEFAULT_MAX_ITEMS ) );
	}

	// =========================================================================
	// Kontext-Ermittlung (Filter/Suche der Ausgangs-Listenansicht)
	// =========================================================================

	/**
	 * Ermittelt den aktuell gültigen Filter-/Suchkontext für Posts:
	 *   1. Wenn ein mlpn_ctx-Param in der URL steht (Klick auf unseren
	 *      eigenen Prev/Next-Link) → diesen dekodieren.
	 *   2. Sonst: wenn der HTTP-Referer eine gefilterte edit.php-
	 *      Listenansicht für denselben Post Type ist → Kontext daraus lesen.
	 *   3. Sonst: leerer Kontext (keine Einschränkung).
	 *
	 * Der Kontext wird in jedem Fall (unabhängig von der Quelle) durch
	 * sanitize_post_context() whitelist-gefiltert.
	 */
	private function get_post_context( string $post_type ): array {
		// Eigener Round-Trip-Parameter (mlpn_ctx)
		if ( isset( $_GET[ self::CTX_PARAM ] ) ) {
			$decoded = $this->decode_context( sanitize_text_field( wp_unslash( $_GET[ self::CTX_PARAM ] ) ) );
			if ( is_array( $decoded ) ) {
				return $this->sanitize_post_context( $decoded, $post_type );
			}
		}

		// Referer der Listenansicht
		$referer = wp_get_referer();
		if ( ! $referer ) return array();

		$parts = wp_parse_url( $referer );
		if ( empty( $parts['path'] ) || false === strpos( $parts['path'], 'edit.php' ) ) return array();
		if ( empty( $parts['query'] ) ) return array();

		parse_str( $parts['query'], $qs );

		$referer_post_type = sanitize_key( $qs['post_type'] ?? 'post' );
		if ( $referer_post_type !== $post_type ) return array();

		return $this->sanitize_post_context( $qs, $post_type );
	}

	/**
	 * Whitelist + Sanitizing für den Post-Kontext. Wird sowohl für aus dem
	 * Referer gelesene als auch für aus mlpn_ctx dekodierte Rohdaten
	 * verwendet, damit niemals ungeprüfte Werte in eine WP_Query wandern.
	 */
	private function sanitize_post_context( array $raw, string $post_type ): array {
		$context = array();

		if ( ! empty( $raw['s'] ) ) {
			$context['s'] = sanitize_text_field( (string) $raw['s'] );
		}

		if ( ! empty( $raw['post_status'] ) ) {
			$allowed_statuses = array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' );
			$status = sanitize_key( (string) $raw['post_status'] );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$context['post_status'] = $status;
			}
		}

		if ( ! empty( $raw['author'] ) && is_numeric( $raw['author'] ) ) {
			$context['author'] = absint( $raw['author'] );
		}

		// Monatsarchiv-Dropdown (Format YYYYMM, z. B. 202606)
		if ( ! empty( $raw['m'] ) && preg_match( '/^[0-9]{4,6}$/', (string) $raw['m'] ) ) {
			$context['m'] = absint( $raw['m'] );
		}

		// Taxonomie-Filter (z. B. product_cat-Dropdown, Kategorie-Filter der Listenansicht)
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$tax_query  = array();

		foreach ( $taxonomies as $tax_slug => $tax_obj ) {
			$query_var = $tax_obj->query_var;
			if ( ! $query_var || empty( $raw[ $query_var ] ) ) continue;

			$term_slug = sanitize_title( (string) $raw[ $query_var ] );
			if ( ! $term_slug || '0' === $term_slug ) continue;

			$tax_query[] = array(
				'taxonomy' => $tax_slug,
				'field'    => 'slug',
				'terms'    => $term_slug,
			);
		}

		if ( ! empty( $tax_query ) ) {
			$context['tax_query'] = count( $tax_query ) > 1
				? array_merge( array( 'relation' => 'AND' ), $tax_query )
				: $tax_query;
		}

		return $context;
	}

	/**
	 * Analog zu get_post_context(), für Taxonomy-Term-Listen (edit-tags.php).
	 */
	private function get_term_context( string $taxonomy ): array {
		if ( isset( $_GET[ self::CTX_PARAM ] ) ) {
			$decoded = $this->decode_context( sanitize_text_field( wp_unslash( $_GET[ self::CTX_PARAM ] ) ) );
			if ( is_array( $decoded ) ) {
				return $this->sanitize_term_context( $decoded );
			}
		}

		$referer = wp_get_referer();
		if ( ! $referer ) return array();

		$parts = wp_parse_url( $referer );
		if ( empty( $parts['path'] ) || false === strpos( $parts['path'], 'edit-tags.php' ) ) return array();
		if ( empty( $parts['query'] ) ) return array();

		parse_str( $parts['query'], $qs );

		$referer_taxonomy = sanitize_key( $qs['taxonomy'] ?? '' );
		if ( $referer_taxonomy !== $taxonomy ) return array();

		return $this->sanitize_term_context( $qs );
	}

	private function sanitize_term_context( array $raw ): array {
		$context = array();

		if ( ! empty( $raw['s'] ) ) {
			$context['s'] = sanitize_text_field( (string) $raw['s'] );
		}

		return $context;
	}

	private function decode_context( string $encoded ) {
		$json = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		if ( false === $json ) return null;

		$data = json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	private function encode_context( array $context ): string {
		if ( empty( $context ) ) return '';
		return strtr( base64_encode( wp_json_encode( $context ) ), '+/', '-_' );
	}

	// =========================================================================
	// ID-Listen ermitteln (Reihenfolge + Kontext)
	// =========================================================================

	/**
	 * Liefert die IDs aller Posts eines Post Types in der aktuell gültigen
	 * Reihenfolge (menu_order bei aktiver Post Order, sonst WP-Standard),
	 * eingeschränkt auf den übergebenen Filter-/Suchkontext.
	 */
	private function get_ordered_post_ids( string $post_type, array $context ): array {
		$args = array(
			'post_type'           => $post_type,
			'post_status'         => $context['post_status'] ?? array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'      => $this->get_max_items(),
			'fields'              => 'ids',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		);

		if ( ! empty( $context['s'] ) )         $args['s']         = $context['s'];
		if ( ! empty( $context['author'] ) )    $args['author']    = $context['author'];
		if ( ! empty( $context['m'] ) )         $args['m']         = $context['m'];
		if ( ! empty( $context['tax_query'] ) ) $args['tax_query'] = $context['tax_query'];

		if ( in_array( $post_type, $this->get_post_order_active_types(), true ) ) {
			$args['orderby'] = 'menu_order';
			$args['order']   = 'ASC';
		} elseif ( is_post_type_hierarchical( $post_type ) ) {
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
		} else {
			$args['orderby'] = 'date';
			$args['order']   = 'DESC';
		}

		$query = new WP_Query( $args );
		return array_map( 'intval', $query->posts );
	}

	/**
	 * Liefert die IDs aller Terms einer Taxonomie in der aktuell gültigen
	 * Reihenfolge, eingeschränkt auf den Kontext.
	 */
	private function get_ordered_term_ids( string $taxonomy, array $context ): array {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
			'number'     => $this->get_max_items(),
		);

		if ( ! empty( $context['s'] ) ) $args['search'] = $context['s'];

		if ( in_array( $taxonomy, $this->get_post_order_active_taxonomies(), true ) ) {
			$args['orderby']    = 'meta_value_num';
			$args['meta_key']   = 'menu_order';
			$args['order']      = 'ASC';
			$args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => 'menu_order', 'compare' => 'EXISTS' ),
				array( 'key' => 'menu_order', 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$args['orderby'] = 'name';
			$args['order']   = 'ASC';
		}

		$ids = get_terms( $args );
		return is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
	}

	// =========================================================================
	// Navigation ermitteln (Prev/Next + Position)
	// =========================================================================

	/**
	 * @return array{prev:?WP_Post,next:?WP_Post,position:int,total:int,context:array}
	 */
	private function get_post_navigation( WP_Post $post ): array {
		$context = $this->get_post_context( $post->post_type );
		$ids     = $this->get_ordered_post_ids( $post->post_type, $context );
		$index   = array_search( $post->ID, $ids, true );

		$prev_post = null;
		$next_post = null;

		if ( false !== $index ) {
			if ( isset( $ids[ $index - 1 ] ) ) $prev_post = get_post( $ids[ $index - 1 ] );
			if ( isset( $ids[ $index + 1 ] ) ) $next_post = get_post( $ids[ $index + 1 ] );
		}

		return array(
			'prev'     => $prev_post,
			'next'     => $next_post,
			'position' => false !== $index ? $index + 1 : 0,
			'total'    => count( $ids ),
			'context'  => $context,
		);
	}

	/**
	 * @return array{prev:?WP_Term,next:?WP_Term,position:int,total:int,context:array}
	 */
	private function get_term_navigation( WP_Term $term ): array {
		$context = $this->get_term_context( $term->taxonomy );
		$ids     = $this->get_ordered_term_ids( $term->taxonomy, $context );
		$index   = array_search( $term->term_id, $ids, true );

		$prev_term = null;
		$next_term = null;

		if ( false !== $index ) {
			if ( isset( $ids[ $index - 1 ] ) ) {
				$t = get_term( $ids[ $index - 1 ], $term->taxonomy );
				$prev_term = ( $t && ! is_wp_error( $t ) ) ? $t : null;
			}
			if ( isset( $ids[ $index + 1 ] ) ) {
				$t = get_term( $ids[ $index + 1 ], $term->taxonomy );
				$next_term = ( $t && ! is_wp_error( $t ) ) ? $t : null;
			}
		}

		return array(
			'prev'     => $prev_term,
			'next'     => $next_term,
			'position' => false !== $index ? $index + 1 : 0,
			'total'    => count( $ids ),
			'context'  => $context,
		);
	}

	// =========================================================================
	// Klassischer Button-Bereich – Posts/Pages/CPTs
	// =========================================================================

	public function render_classic_post_nav( $post ): void {
		if ( ! $post instanceof WP_Post ) return;

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) return;

		// Nur bestehende Beiträge, nicht "Neu erstellen"
		if ( 'auto-draft' === $post->post_status || empty( $post->ID ) ) return;

		if ( ! $this->is_post_type_navigable( $post->post_type ) ) return;
		if ( ! current_user_can( 'edit_post', $post->ID ) ) return;

		$nav = $this->get_post_navigation( $post );
		if ( ! $nav['prev'] && ! $nav['next'] ) return;

		echo $this->render_nav_html(
			$nav['prev'] ? $this->build_post_edit_url( $nav['prev']->ID, $nav['context'] ) : '',
			$nav['prev'] ? $nav['prev']->post_title : '',
			$nav['next'] ? $this->build_post_edit_url( $nav['next']->ID, $nav['context'] ) : '',
			$nav['next'] ? $nav['next']->post_title : '',
			$nav['position'],
			$nav['total']
		);
	}

	private function build_post_edit_url( int $post_id, array $context ): string {
		$url = get_edit_post_link( $post_id, 'raw' );
		if ( ! $url ) return '';

		$encoded = $this->encode_context( $context );
		return $encoded ? add_query_arg( self::CTX_PARAM, $encoded, $url ) : $url;
	}

	// =========================================================================
	// Klassischer Button-Bereich – Taxonomy-Terms
	// =========================================================================

	public function register_term_edit_hooks(): void {
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'names' ) as $taxonomy ) {
			if ( ! $this->is_taxonomy_navigable( $taxonomy ) ) continue;
			add_action( "{$taxonomy}_term_edit_form_top", array( $this, 'render_classic_term_nav' ), 10, 2 );
		}
	}

	public function render_classic_term_nav( $tag, $taxonomy ): void {
		if ( ! $tag instanceof WP_Term ) return;
		if ( ! current_user_can( 'edit_term', $tag->term_id ) ) return;

		$nav = $this->get_term_navigation( $tag );
		if ( ! $nav['prev'] && ! $nav['next'] ) return;

		echo $this->render_nav_html(
			$nav['prev'] ? $this->build_term_edit_url( $nav['prev']->term_id, $taxonomy, $nav['context'] ) : '',
			$nav['prev'] ? $nav['prev']->name : '',
			$nav['next'] ? $this->build_term_edit_url( $nav['next']->term_id, $taxonomy, $nav['context'] ) : '',
			$nav['next'] ? $nav['next']->name : '',
			$nav['position'],
			$nav['total']
		);
	}

	private function build_term_edit_url( int $term_id, string $taxonomy, array $context ): string {
		$url = get_edit_term_link( $term_id, $taxonomy );
		if ( ! $url ) return '';

		$encoded = $this->encode_context( $context );
		return $encoded ? add_query_arg( self::CTX_PARAM, $encoded, $url ) : $url;
	}

	// =========================================================================
	// Gemeinsames HTML-Markup (Posts + Terms)
	// =========================================================================

	private function render_nav_html( string $prev_url, string $prev_title, string $next_url, string $next_title, int $position, int $total ): string {
		ob_start();
		?>
		<div class="medialab-post-nav">
			<?php if ( $prev_url ) : ?>
				<a href="<?php echo esc_url( $prev_url ); ?>" class="medialab-post-nav__link medialab-post-nav__link--prev" title="<?php echo esc_attr( $prev_title ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<span class="medialab-post-nav__label"><?php esc_html_e( 'Voriger Eintrag', 'media-lab-core' ); ?></span>
				</a>
			<?php else : ?>
				<span class="medialab-post-nav__link medialab-post-nav__link--disabled">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
					<span class="medialab-post-nav__label"><?php esc_html_e( 'Voriger Eintrag', 'media-lab-core' ); ?></span>
				</span>
			<?php endif; ?>

			<?php if ( $position && $total ) : ?>
				<span class="medialab-post-nav__position">
					<?php
					printf(
						/* translators: 1: aktuelle Position, 2: Gesamtanzahl */
						esc_html__( '%1$d von %2$d', 'media-lab-core' ),
						(int) $position,
						(int) $total
					);
					?>
				</span>
			<?php endif; ?>

			<?php if ( $next_url ) : ?>
				<a href="<?php echo esc_url( $next_url ); ?>" class="medialab-post-nav__link medialab-post-nav__link--next" title="<?php echo esc_attr( $next_title ); ?>">
					<span class="medialab-post-nav__label"><?php esc_html_e( 'Nächster Eintrag', 'media-lab-core' ); ?></span>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</a>
			<?php else : ?>
				<span class="medialab-post-nav__link medialab-post-nav__link--disabled">
					<span class="medialab-post-nav__label"><?php esc_html_e( 'Nächster Eintrag', 'media-lab-core' ); ?></span>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// =========================================================================
	// Gutenberg – Sidebar-Panel (Document Settings)
	// =========================================================================

	public function enqueue_block_editor_assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) return;

		$post = get_post();
		if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status ) return;
		if ( ! $this->is_post_type_navigable( $post->post_type ) ) return;
		if ( ! current_user_can( 'edit_post', $post->ID ) ) return;

		$nav = $this->get_post_navigation( $post );
		if ( ! $nav['prev'] && ! $nav['next'] ) return;

		wp_enqueue_script(
			'medialab-post-navigation',
			MEDIALAB_CORE_URL . 'assets/js/post-navigation.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-i18n' ),
			MEDIALAB_CORE_VERSION,
			true
		);

		wp_localize_script( 'medialab-post-navigation', 'medialabPostNav', array(
			'prev' => $nav['prev'] ? array(
				'url'   => $this->build_post_edit_url( $nav['prev']->ID, $nav['context'] ),
				'title' => $nav['prev']->post_title ?: __( '(kein Titel)', 'media-lab-core' ),
			) : null,
			'next' => $nav['next'] ? array(
				'url'   => $this->build_post_edit_url( $nav['next']->ID, $nav['context'] ),
				'title' => $nav['next']->post_title ?: __( '(kein Titel)', 'media-lab-core' ),
			) : null,
			'position' => ( $nav['position'] && $nav['total'] )
				? sprintf(
					/* translators: 1: aktuelle Position, 2: Gesamtanzahl */
					__( '%1$d von %2$d', 'media-lab-core' ),
					$nav['position'],
					$nav['total']
				)
				: '',
			'i18n' => array(
				'title' => __( 'Navigation', 'media-lab-core' ),
				'prev'  => __( 'Voriger', 'media-lab-core' ),
				'next'  => __( 'Nächster', 'media-lab-core' ),
			),
		) );
	}

	// =========================================================================
	// Styles (klassischer Button-Bereich, beide Kontexte)
	// =========================================================================

	public function enqueue_classic_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'term.php' ), true ) ) return;

		wp_enqueue_style(
			'medialab-post-navigation',
			MEDIALAB_CORE_URL . 'assets/css/post-navigation.css',
			array(),
			MEDIALAB_CORE_VERSION
		);
	}
}

new MediaLab_Post_Navigation();
