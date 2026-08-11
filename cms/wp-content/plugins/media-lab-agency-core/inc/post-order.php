<?php
/**
 * Drag & Drop Post Order
 *
 * Ermöglicht das Sortieren von Posts/CPTs und Taxonomy-Terms per Drag & Drop
 * in der WP-Admin-Listenansicht. Konfigurierbar unter Einstellungen → Post Order.
 *
 * Post-Reihenfolge: gespeichert in wp_posts.menu_order
 * Term-Reihenfolge: gespeichert in term_meta (menu_order)
 *
 * @package MediaLab_Core
 * @since   1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Post_Order {

	/**
	 * Option-Key für aktivierte Post Types
	 */
	const OPTION_KEY = 'medialab_sortable_post_types';

	/**
	 * Option-Key für aktivierte Taxonomien
	 */
	const OPTION_KEY_TERMS = 'medialab_sortable_taxonomies';

	/**
	 * WP-interne Post Types, die nie sortierbar sein sollen
	 */
	private $excluded_types = array(
		'attachment', 'revision', 'nav_menu_item',
		'custom_css', 'customize_changeset', 'oembed_cache',
		'user_request', 'wp_block', 'wp_template',
		'wp_template_part', 'wp_global_styles', 'wp_navigation',
		'wp_font_family', 'wp_font_face',
		// eigene Core-CPTs, die nie sortiert werden sollen
		'notification',
	);

	/**
	 * WP-interne Taxonomien, die nie sortierbar sein sollen
	 */
	private $excluded_taxonomies = array(
		'nav_menu', 'link_category', 'post_format',
		'wp_theme', 'wp_template_part_area', 'wp_pattern_category',
	);

	public function __construct() {
		// Admin
		add_action( 'admin_menu',            array( $this, 'register_settings_page' ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices',         array( $this, 'maybe_show_notice' ) );

		// AJAX – Posts
		add_action( 'wp_ajax_medialab_update_post_order', array( $this, 'ajax_update_order' ) );

		// AJAX – Terms
		add_action( 'wp_ajax_medialab_update_term_order', array( $this, 'ajax_update_term_order' ) );

		// Query-Hooks Posts
		add_action( 'pre_get_posts', array( $this, 'default_order_in_admin' ) );
		add_action( 'pre_get_posts', array( $this, 'default_order_in_frontend' ) );

		// Query-Hook Terms (Admin-Listenansicht + Frontend)
		add_filter( 'get_terms_args', array( $this, 'default_term_order' ), 10, 2 );
	}

	// =========================================================================
	// Hilfsmethoden – Post Types
	// =========================================================================

	/**
	 * Gibt die Liste der aktuell aktivierten, sortierbaren Post Types zurück.
	 * 'page' ist immer enthalten.
	 */
	public function get_sortable_types(): array {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_unique( array_merge( array( 'page' ), $saved ) );
	}

	/**
	 * Gibt alle Post Types zurück, die in der Einstellungsseite angeboten werden.
	 *
	 * WICHTIG: Filtert auf show_ui=true (nicht public=true), damit auch
	 * nicht-öffentliche CPTs wie hero_slide, faq, gmap, carousel erscheinen.
	 */
	private function get_selectable_types(): array {
		// show_ui=true erfasst alle CPTs mit Admin-UI, unabhängig von public
		$args = array(
			'show_ui'  => true,
			'_builtin' => false,
		);

		$cpts = get_post_types( $args, 'objects' );

		// Blacklist filtern
		foreach ( $this->excluded_types as $slug ) {
			unset( $cpts[ $slug ] );
		}

		// 'post' separat anbieten (builtin, aber sinnvoll sortierbar)
		$post_type_obj = get_post_type_object( 'post' );
		if ( $post_type_obj ) {
			$cpts = array( 'post' => $post_type_obj ) + $cpts;
		}

		return $cpts;
	}

	// =========================================================================
	// Hilfsmethoden – Taxonomien
	// =========================================================================

	/**
	 * Gibt die Liste der aktuell aktivierten, sortierbaren Taxonomien zurück.
	 */
	public function get_sortable_taxonomies(): array {
		$saved = get_option( self::OPTION_KEY_TERMS, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Gibt alle Taxonomien zurück, die in der Einstellungsseite angeboten werden.
	 */
	private function get_selectable_taxonomies(): array {
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );

		foreach ( $this->excluded_taxonomies as $slug ) {
			unset( $taxonomies[ $slug ] );
		}

		return $taxonomies;
	}

	// =========================================================================
	// Einstellungsseite
	// =========================================================================

	public function register_settings_page(): void {
		add_options_page(
			__( 'Post Order', 'media-lab-core' ),
			__( 'Post Order', 'media-lab-core' ),
			'manage_options',
			'medialab-post-order',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		// Post Types
		register_setting(
			'medialab_post_order_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
				'default'           => array(),
			)
		);

		// Taxonomien
		register_setting(
			'medialab_post_order_group',
			self::OPTION_KEY_TERMS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_taxonomies' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize: Nur gültige, registrierte Post Type Slugs zulassen.
	 */
	public function sanitize_post_types( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$all_types = get_post_types( array(), 'names' );
		$sanitized = array();

		foreach ( $input as $slug ) {
			$slug = sanitize_key( $slug );
			if ( in_array( $slug, $all_types, true ) ) {
				$sanitized[] = $slug;
			}
		}

		return array_unique( $sanitized );
	}

	/**
	 * Sanitize: Nur gültige, registrierte Taxonomie-Slugs zulassen.
	 */
	public function sanitize_taxonomies( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$all_taxonomies = get_taxonomies( array(), 'names' );
		$sanitized      = array();

		foreach ( $input as $slug ) {
			$slug = sanitize_key( $slug );
			if ( in_array( $slug, $all_taxonomies, true ) ) {
				$sanitized[] = $slug;
			}
		}

		return array_unique( $sanitized );
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$selectable_types  = $this->get_selectable_types();
		$active_types      = $this->get_sortable_types();
		$selectable_taxos  = $this->get_selectable_taxonomies();
		$active_taxos      = $this->get_sortable_taxonomies();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post Order – Sortierbare Post Types & Taxonomien', 'media-lab-core' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Wähle die Post Types und Taxonomien aus, für die Drag & Drop Sortierung in der Admin-Listenansicht aktiviert werden soll.', 'media-lab-core' ); ?>
			</p>
			<hr>

			<form method="post" action="options.php">
				<?php settings_fields( 'medialab_post_order_group' ); ?>

				<?php /* ── POST TYPES ─────────────────────────────────────── */ ?>
				<h2><?php esc_html_e( 'Post Types', 'media-lab-core' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Die Reihenfolge wird über das Feld menu_order gespeichert und automatisch im Frontend angewendet.', 'media-lab-core' ); ?>
				</p>

				<table class="wp-list-table widefat striped medialab-post-order-table" style="max-width:700px;margin-bottom:2em;">
					<thead>
						<tr>
							<th style="width:40px;"></th>
							<th><?php esc_html_e( 'Post Type', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Typ', 'media-lab-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $selectable_types ) ) : ?>
							<tr>
								<td colspan="4"><?php esc_html_e( 'Keine sortierbaren Post Types gefunden.', 'media-lab-core' ); ?></td>
							</tr>
						<?php else : ?>

							<?php
							// 'page' als fixen, immer-aktiven Eintrag
							$page_obj = get_post_type_object( 'page' );
							if ( $page_obj ) :
							?>
							<tr style="opacity:.6;">
								<td>
									<input type="checkbox" disabled checked
										title="<?php esc_attr_e( 'Seiten sind immer sortierbar', 'media-lab-core' ); ?>">
								</td>
								<td>
									<strong><?php echo esc_html( $page_obj->labels->name ); ?></strong>
									<span class="description" style="font-size:11px;display:block;">
										<?php esc_html_e( 'Immer aktiviert', 'media-lab-core' ); ?>
									</span>
								</td>
								<td><code>page</code></td>
								<td><?php esc_html_e( 'Built-in', 'media-lab-core' ); ?></td>
							</tr>
							<?php endif; ?>

							<?php foreach ( $selectable_types as $slug => $obj ) :
								$is_checked = in_array( $slug, $active_types, true );
								$is_builtin = $obj->_builtin ?? false;
							?>
							<tr>
								<td>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY ); ?>[]"
										value="<?php echo esc_attr( $slug ); ?>"
										id="mlpo_<?php echo esc_attr( $slug ); ?>"
										<?php checked( $is_checked ); ?>
									>
								</td>
								<td>
									<label for="mlpo_<?php echo esc_attr( $slug ); ?>">
										<strong><?php echo esc_html( $obj->labels->name ); ?></strong>
									</label>
								</td>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
								<td>
									<?php echo $is_builtin
										? esc_html__( 'Built-in', 'media-lab-core' )
										: esc_html__( 'Custom', 'media-lab-core' );
									?>
								</td>
							</tr>
							<?php endforeach; ?>

						<?php endif; ?>
					</tbody>
				</table>

				<?php /* ── TAXONOMIEN ──────────────────────────────────────── */ ?>
				<h2><?php esc_html_e( 'Taxonomien', 'media-lab-core' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Die Term-Reihenfolge wird in term_meta (menu_order) gespeichert. Nach der ersten Sortierung wird die Reihenfolge automatisch im Frontend angewendet.', 'media-lab-core' ); ?>
				</p>

				<table class="wp-list-table widefat striped medialab-post-order-table" style="max-width:700px;margin-bottom:2em;">
					<thead>
						<tr>
							<th style="width:40px;"></th>
							<th><?php esc_html_e( 'Taxonomie', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Verknüpfte Post Types', 'media-lab-core' ); ?></th>
							<th><?php esc_html_e( 'Typ', 'media-lab-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $selectable_taxos ) ) : ?>
							<tr>
								<td colspan="5"><?php esc_html_e( 'Keine sortierbaren Taxonomien gefunden.', 'media-lab-core' ); ?></td>
							</tr>
						<?php else : ?>

							<?php foreach ( $selectable_taxos as $slug => $obj ) :
								$is_checked = in_array( $slug, $active_taxos, true );
								$is_builtin = $obj->_builtin ?? false;

								// Verknüpfte Post Types als lesbaren String
								$linked_pts = array_map( function( $pt ) {
									$pt_obj = get_post_type_object( $pt );
									return $pt_obj ? $pt_obj->labels->singular_name : $pt;
								}, (array) $obj->object_type );
								$linked_label = implode( ', ', $linked_pts );
							?>
							<tr>
								<td>
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_KEY_TERMS ); ?>[]"
										value="<?php echo esc_attr( $slug ); ?>"
										id="mltax_<?php echo esc_attr( $slug ); ?>"
										<?php checked( $is_checked ); ?>
									>
								</td>
								<td>
									<label for="mltax_<?php echo esc_attr( $slug ); ?>">
										<strong><?php echo esc_html( $obj->labels->name ); ?></strong>
									</label>
								</td>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
								<td><?php echo esc_html( $linked_label ?: '—' ); ?></td>
								<td>
									<?php echo $is_builtin
										? esc_html__( 'Built-in', 'media-lab-core' )
										: esc_html__( 'Custom', 'media-lab-core' );
									?>
								</td>
							</tr>
							<?php endforeach; ?>

						<?php endif; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Einstellungen speichern', 'media-lab-core' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Hinweise', 'media-lab-core' ); ?></h2>
			<ul style="list-style:disc;padding-left:1.5em;max-width:700px;">
				<li><?php esc_html_e( 'Post-Reihenfolge wird in der Datenbankspalte menu_order gespeichert.', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Term-Reihenfolge wird in term_meta (Schlüssel: menu_order) gespeichert.', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Für aktivierte Post Types wird im Frontend automatisch orderby=menu_order gesetzt (nur Main Query, kein explizites orderby).', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Für aktivierte Taxonomien wird im Frontend automatisch nach menu_order sortiert, sobald mindestens eine Sortierung gespeichert wurde.', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Die Drag & Drop Handles erscheinen in der Admin-Listenansicht des jeweiligen Post Types bzw. der Taxonomie.', 'media-lab-core' ); ?></li>
				<li><?php esc_html_e( 'Seiten (page) sind immer sortierbar und erscheinen daher nicht in der Auswahl.', 'media-lab-core' ); ?></li>
			</ul>
		</div>
		<?php
	}

	// =========================================================================
	// Admin Notice nach Speichern
	// =========================================================================

	public function maybe_show_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'settings_page_medialab-post-order' ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Post Order Einstellungen gespeichert.', 'media-lab-core' )
				. '</p></div>';
		}
	}

	// =========================================================================
	// Scripts & Styles
	// =========================================================================

	public function enqueue_scripts( string $hook ): void {
		$is_post_list = ( $hook === 'edit.php' );
		$is_term_list = ( $hook === 'edit-tags.php' );

		if ( ! $is_post_list && ! $is_term_list ) return;

		$mode         = null;
		$localize     = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'medialab_post_order' ),
			'i18n'    => array(
				'saving' => __( 'Speichern…', 'media-lab-core' ),
				'saved'  => __( 'Reihenfolge gespeichert', 'media-lab-core' ),
				'error'  => __( 'Fehler beim Speichern', 'media-lab-core' ),
			),
		);

		if ( $is_post_list ) {
			$post_type = sanitize_key( $_GET['post_type'] ?? 'post' );
			if ( ! in_array( $post_type, $this->get_sortable_types(), true ) ) return;

			$mode              = 'post';
			$localize['mode']     = 'post';
			$localize['postType'] = $post_type;
		}

		if ( $is_term_list ) {
			$taxonomy = sanitize_key( $_GET['taxonomy'] ?? '' );
			if ( ! $taxonomy || ! in_array( $taxonomy, $this->get_sortable_taxonomies(), true ) ) return;

			$mode               = 'term';
			$localize['mode']      = 'term';
			$localize['taxonomy']  = $taxonomy;
		}

		if ( ! $mode ) return;

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script(
			'medialab-post-order',
			MEDIALAB_CORE_URL . 'assets/js/post-order.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			MEDIALAB_CORE_VERSION,
			true
		);
		wp_localize_script( 'medialab-post-order', 'medialabPostOrder', $localize );

		wp_enqueue_style(
			'medialab-post-order',
			MEDIALAB_CORE_URL . 'assets/css/post-order.css',
			array(),
			MEDIALAB_CORE_VERSION
		);
	}

	// =========================================================================
	// AJAX – Posts
	// =========================================================================

	public function ajax_update_order(): void {
		check_ajax_referer( 'medialab_post_order', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung', 'media-lab-core' ), 403 );
		}

		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

		if ( empty( $order ) ) {
			wp_send_json_error( __( 'Keine Daten empfangen', 'media-lab-core' ) );
		}

		$updated = 0;
		foreach ( $order as $position => $post_id ) {
			if ( $post_id <= 0 ) continue;

			$result = wp_update_post( array(
				'ID'         => $post_id,
				'menu_order' => $position,
			) );

			if ( $result && ! is_wp_error( $result ) ) {
				$updated++;
			}
		}

		wp_send_json_success( array(
			'updated' => $updated,
			'message' => sprintf(
				/* translators: %d: Anzahl aktualisierter Einträge */
				__( '%d Einträge aktualisiert', 'media-lab-core' ),
				$updated
			),
		) );
	}

	// =========================================================================
	// AJAX – Terms
	// =========================================================================

	public function ajax_update_term_order(): void {
		check_ajax_referer( 'medialab_post_order', 'nonce' );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung', 'media-lab-core' ), 403 );
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( __( 'Ungültige Taxonomie', 'media-lab-core' ) );
		}

		// Sicherstellen, dass die Taxonomie sortierbar aktiviert ist
		if ( ! in_array( $taxonomy, $this->get_sortable_taxonomies(), true ) ) {
			wp_send_json_error( __( 'Taxonomie nicht aktiviert', 'media-lab-core' ), 403 );
		}

		$order = isset( $_POST['order'] ) ? array_map( 'absint', (array) $_POST['order'] ) : array();

		if ( empty( $order ) ) {
			wp_send_json_error( __( 'Keine Daten empfangen', 'media-lab-core' ) );
		}

		$updated = 0;
		foreach ( $order as $position => $term_id ) {
			if ( $term_id <= 0 ) continue;

			$result = update_term_meta( $term_id, 'menu_order', $position );

			// update_term_meta gibt false nur bei echtem Fehler zurück
			if ( $result !== false ) {
				$updated++;
			}
		}

		wp_send_json_success( array(
			'updated' => $updated,
			'message' => sprintf(
				/* translators: %d: Anzahl aktualisierter Terms */
				__( '%d Einträge aktualisiert', 'media-lab-core' ),
				$updated
			),
		) );
	}

	// =========================================================================
	// pre_get_posts Hooks – Posts
	// =========================================================================

	/**
	 * Admin-Listenansichten für Posts nach menu_order sortieren.
	 */
	public function default_order_in_admin( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) return;

		$post_type    = $query->get( 'post_type' ) ?: sanitize_key( $_GET['post_type'] ?? 'post' );
		$active_types = $this->get_sortable_types();

		if ( ! in_array( $post_type, $active_types, true ) ) return;

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Frontend Main Query für aktivierte CPTs auf menu_order setzen.
	 * 'post' und 'page' werden bewusst nicht automatisch umgestellt.
	 */
	public function default_order_in_frontend( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) return;

		$post_type    = $query->get( 'post_type' );
		$active_types = $this->get_sortable_types();

		// Nur CPTs, nicht page/post
		$cpt_only = array_diff( $active_types, array( 'post', 'page' ) );

		if ( in_array( $post_type, $cpt_only, true ) && ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}

	// =========================================================================
	// get_terms_args Hook – Terms (Admin-Listenansicht + Frontend)
	// =========================================================================

	/**
	 * Sortiert aktivierte Taxonomien nach menu_order.
	 *
	 * Admin: nur auf der edit-tags.php Listenansicht.
	 * Frontend: für alle get_terms()-Aufrufe ohne explizites orderby.
	 *
	 * Terms ohne menu_order-Meta erscheinen am Ende (alphabetisch),
	 * bis sie mindestens einmal per Drag & Drop gespeichert wurden.
	 */
	public function default_term_order( array $args, array $taxonomies ): array {
		$active_taxos = $this->get_sortable_taxonomies();
		if ( empty( $active_taxos ) ) return $args;

		$matches = array_intersect( (array) $taxonomies, $active_taxos );
		if ( empty( $matches ) ) return $args;

		// Im Admin: nur in der Term-Listenansicht anwenden
		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( ! $screen || $screen->base !== 'edit-tags' ) return $args;
		}

		// Nicht überschreiben, wenn bereits ein explizites orderby gesetzt ist
		$current_orderby = $args['orderby'] ?? 'name';
		if ( $current_orderby !== 'name' ) return $args;

		// Terms MIT menu_order zuerst (sortiert), Terms OHNE danach (alphabetisch).
		//
		// WICHTIG #1: Kein Top-Level 'meta_key' verwenden! WP_Meta_Query::parse_query_vars()
		// wandelt einen Top-Level meta_key/meta_value(_num)/meta_compare IMMER in eine
		// zusätzliche, implizite Meta-Query-Klausel um (mit implizitem compare '=').
		// Diese wird per AND mit dem hier definierten 'meta_query'-Array verknüpft –
		// unabhängig von dessen eigener 'relation'. Ergebnis: (EXISTS OR NOT EXISTS)
		// AND (menu_order vorhanden) → NUR Terms mit gesetztem menu_order matchen,
		// alle anderen fallen komplett aus dem Ergebnis (INNER JOIN statt LEFT JOIN).
		// Das führt dazu, dass get_terms() leer zurückgibt, solange noch NIE ein
		// Term dieser Taxonomie per Drag & Drop sortiert wurde – ein stiller,
		// kompletter Ausfall ohne Fehlermeldung.
		//
		// WICHTIG #2: Andere Callbacks am 'get_terms_args'-Hook (z.B. eigene
		// meta_query-Filter aus Theme/Plugin-Code, etwa "nur aktive Marken
		// anzeigen") können VOR diesem Filter bereits ein $args['meta_query']
		// gesetzt haben. Dieses NICHT überschreiben, sondern per AND mit der
		// eigenen menu_order-Logik verknüpfen – sonst gehen fremde Filter-
		// bedingungen kommentarlos verloren (genau dieser Bug ließ z.B. eine
		// bewusst deaktivierte Marke trotz "brand-is-active"-Filter im
		// Frontend wieder erscheinen, weil hier die komplette meta_query
		// durch eine reine menu_order-Klausel ersetzt wurde).
		$menu_order_clause = array(
			'relation'          => 'OR',
			'menu_order_clause' => array(
				'key'     => 'menu_order',
				'compare' => 'EXISTS',
				'type'    => 'NUMERIC',
			),
			array( 'key' => 'menu_order', 'compare' => 'NOT EXISTS' ),
		);

		if ( ! empty( $args['meta_query'] ) ) {
			// Fremde meta_query vorhanden → per AND kombinieren, nichts verwerfen.
			$args['meta_query'] = array(
				'relation' => 'AND',
				$args['meta_query'],
				$menu_order_clause,
			);
		} else {
			$args['meta_query'] = $menu_order_clause;
		}

		// WP_Term_Query::parse_orderby() akzeptiert nur einen String (kein
		// orderby-Array wie bei WP_Query) – daher hier bewusst auf die
		// benannte meta_query-Klausel referenzieren statt ein Array zu
		// übergeben. Terms ohne menu_order-Meta erhalten dadurch NULL als
		// Sortierwert und landen bei ASC-Sortierung vor den einsortierten
		// Terms (nicht danach) – kosmetische Einschränkung, kein Blocker.
		$args['orderby'] = 'menu_order_clause';
		$args['order']   = 'ASC';

		return $args;
	}
}

new MediaLab_Post_Order();
