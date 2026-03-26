<?php
/**
 * MLT_Redirects
 *
 * 301/302 Redirect-Manager mit Admin-UI und 404-Logger.
 *
 * DB-Tabellen:
 *  - {prefix}mlt_redirects   – Redirect-Regeln
 *  - {prefix}mlt_404_log     – 404-Protokoll
 *
 * Tabellen werden bei Plugin-Aktivierung angelegt (siehe media-lab-toolkit.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Redirects {

    public function __construct() {
        add_action( 'template_redirect',           [ $this, 'process_redirects' ], 1 );
        add_action( 'wp',                          [ $this, 'log_404' ] );
        add_action( 'admin_menu',                  [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts',       [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mlt_redirect_save',   [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_mlt_redirect_delete', [ $this, 'ajax_delete' ] );
        add_action( 'wp_ajax_mlt_redirect_toggle', [ $this, 'ajax_toggle' ] );
        add_action( 'wp_ajax_mlt_404_delete',      [ $this, 'ajax_404_delete' ] );
        add_action( 'wp_ajax_mlt_404_to_redirect', [ $this, 'ajax_404_to_redirect' ] );
    }

    // ── Tabellen erstellen (wird von Activation Hook aufgerufen) ──────────────

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_redirects = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mlt_redirects (
            id          bigint(20)   NOT NULL AUTO_INCREMENT,
            source_url  varchar(500) NOT NULL,
            target_url  varchar(500) NOT NULL,
            redirect_type smallint(4) NOT NULL DEFAULT 301,
            is_active   tinyint(1)   NOT NULL DEFAULT 1,
            hit_count   bigint(20)   NOT NULL DEFAULT 0,
            last_hit    datetime     DEFAULT NULL,
            created_at  datetime     NOT NULL,
            PRIMARY KEY (id),
            KEY source_url (source_url(191))
        ) $charset;";

        $sql_404 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mlt_404_log (
            id            bigint(20)   NOT NULL AUTO_INCREMENT,
            requested_url varchar(500) NOT NULL,
            referrer      varchar(500) DEFAULT NULL,
            hit_count     bigint(20)   NOT NULL DEFAULT 1,
            last_seen     datetime     NOT NULL,
            PRIMARY KEY (id),
            KEY requested_url (requested_url(191))
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_redirects );
        dbDelta( $sql_404 );
    }

    public static function drop_tables() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_redirects" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mlt_404_log" );
    }

    // ── Redirects verarbeiten ─────────────────────────────────────────────────

    public function process_redirects() {
        if ( is_admin() ) return;

        $current = $this->normalize_url( $_SERVER['REQUEST_URI'] ?? '' );
        if ( ! $current ) return;

        global $wpdb;
        $redirects = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mlt_redirects WHERE is_active = 1 ORDER BY LENGTH(source_url) DESC"
        );

        foreach ( $redirects as $redirect ) {
            $source = $this->normalize_url( $redirect->source_url );

            // Exakter Match
            if ( $source === $current ) {
                $this->do_redirect( $redirect );
            }

            // Wildcard Match (source endet mit *)
            if ( substr( $source, -1 ) === '*' ) {
                $prefix = rtrim( substr( $source, 0, -1 ), '/' );
                if ( strpos( $current, $prefix ) === 0 ) {
                    $this->do_redirect( $redirect );
                }
            }
        }
    }

    private function do_redirect( $redirect ) {
        global $wpdb;

        // Hit-Counter aktualisieren
        $wpdb->update(
            $wpdb->prefix . 'mlt_redirects',
            [ 'hit_count' => $redirect->hit_count + 1, 'last_hit' => current_time( 'mysql' ) ],
            [ 'id' => $redirect->id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        $type = in_array( (int) $redirect->redirect_type, [ 301, 302, 307 ] )
            ? (int) $redirect->redirect_type
            : 301;

        wp_redirect( $redirect->target_url, $type );
        exit;
    }

    // ── 404 loggen ────────────────────────────────────────────────────────────

    public function log_404() {
        if ( ! is_404() ) return;

        global $wpdb;
        $url      = $this->normalize_url( $_SERVER['REQUEST_URI'] ?? '' );
        $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( $_SERVER['HTTP_REFERER'] ) : null;

        $existing = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, hit_count FROM {$wpdb->prefix}mlt_404_log WHERE requested_url = %s", $url )
        );

        if ( $existing ) {
            $wpdb->update(
                $wpdb->prefix . 'mlt_404_log',
                [ 'hit_count' => $existing->hit_count + 1, 'last_seen' => current_time( 'mysql' ), 'referrer' => $referrer ],
                [ 'id' => $existing->id ],
                [ '%d', '%s', '%s' ],
                [ '%d' ]
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'mlt_404_log',
                [ 'requested_url' => $url, 'referrer' => $referrer, 'hit_count' => 1, 'last_seen' => current_time( 'mysql' ) ],
                [ '%s', '%s', '%d', '%s' ]
            );
        }
    }

    // ── Admin-Menü ────────────────────────────────────────────────────────────

    public function register_menu() {
        add_submenu_page(
            'media-lab-seo',
            'Weiterleitungen',
            'Weiterleitungen',
            'manage_options',
            'mlt-redirects',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'seo-toolkit_page_mlt-redirects' ) return;

        wp_enqueue_script(
            'mlt-redirects',
            MLT_URL . 'assets/redirects.js',
            [ 'jquery' ],
            MLT_VERSION,
            true
        );
        wp_localize_script( 'mlt-redirects', 'mltRedirects', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mlt_redirects' ),
        ] );
    }

    // ── Admin-Seite ───────────────────────────────────────────────────────────

    public function render_page() {
        global $wpdb;
        $tab = $_GET['tab'] ?? 'redirects';

        $redirects = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mlt_redirects ORDER BY created_at DESC" );
        $log_404   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mlt_404_log ORDER BY hit_count DESC LIMIT 100" );
        ?>
        <div class="wrap mlt-wrap">
            <div class="mlt-header">
                <h1>Weiterleitungen <span class="mlt-version">v<?php echo esc_html( MLT_VERSION ); ?></span></h1>
            </div>

            <nav class="nav-tab-wrapper">
                <a href="?page=mlt-redirects&tab=redirects"
                   class="nav-tab <?php echo $tab === 'redirects' ? 'nav-tab-active' : ''; ?>">
                    Redirects (<?php echo count( $redirects ); ?>)
                </a>
                <a href="?page=mlt-redirects&tab=404"
                   class="nav-tab <?php echo $tab === '404' ? 'nav-tab-active' : ''; ?>">
                    404-Log (<?php echo count( $log_404 ); ?>)
                </a>
            </nav>

            <?php if ( $tab === 'redirects' ) : ?>
                <div class="mlt-card" style="margin-top:20px">
                    <div class="mlt-card__header">
                        <span class="mlt-card__icon">➕</span>
                        <h2>Neuen Redirect anlegen</h2>
                    </div>
                    <div class="mlt-card__body">
                        <div class="mlt-redirect-form">
                            <input type="text"   id="mlt_src"  placeholder="Quelle: /alte-seite/ oder /blog/*" class="regular-text" />
                            <input type="text"   id="mlt_dst"  placeholder="Ziel: /neue-seite/" class="regular-text" />
                            <select id="mlt_type">
                                <option value="301">301 – Permanent</option>
                                <option value="302">302 – Temporär</option>
                                <option value="307">307 – Temporär (POST)</option>
                            </select>
                            <button type="button" class="button button-primary" id="mlt_redirect_add">Hinzufügen</button>
                            <span class="mlt-inline-result"></span>
                        </div>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped mlt-redirects-table" style="margin-top:16px">
                    <thead>
                        <tr>
                            <th style="width:35%">Quelle</th>
                            <th style="width:35%">Ziel</th>
                            <th style="width:8%">Typ</th>
                            <th style="width:8%">Hits</th>
                            <th style="width:14%">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $redirects ) ) : ?>
                            <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af">Noch keine Redirects eingetragen.</td></tr>
                        <?php else : foreach ( $redirects as $r ) : ?>
                            <tr id="mlt-redirect-<?php echo (int) $r->id; ?>" class="<?php echo $r->is_active ? '' : 'mlt-row-inactive'; ?>">
                                <td><code><?php echo esc_html( $r->source_url ); ?></code></td>
                                <td><?php echo esc_html( $r->target_url ); ?></td>
                                <td><span class="mlt-badge mlt-badge--<?php echo (int) $r->redirect_type; ?>"><?php echo (int) $r->redirect_type; ?></span></td>
                                <td><?php echo number_format( (int) $r->hit_count, 0, ',', '.' ); ?></td>
                                <td>
                                    <button class="button button-small mlt-toggle-redirect" data-id="<?php echo (int) $r->id; ?>">
                                        <?php echo $r->is_active ? 'Deaktivieren' : 'Aktivieren'; ?>
                                    </button>
                                    <button class="button button-small mlt-delete-redirect" data-id="<?php echo (int) $r->id; ?>" style="color:#dc2626;border-color:#fca5a5">
                                        Löschen
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php else : ?>

                <table class="wp-list-table widefat fixed striped" style="margin-top:20px">
                    <thead>
                        <tr>
                            <th style="width:40%">URL (404)</th>
                            <th style="width:25%">Referrer</th>
                            <th style="width:10%">Hits</th>
                            <th style="width:15%">Zuletzt</th>
                            <th style="width:10%">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $log_404 ) ) : ?>
                            <tr><td colspan="5" style="text-align:center;padding:20px;color:#9ca3af">Keine 404-Fehler protokolliert.</td></tr>
                        <?php else : foreach ( $log_404 as $entry ) : ?>
                            <tr id="mlt-404-<?php echo (int) $entry->id; ?>">
                                <td><code><?php echo esc_html( $entry->requested_url ); ?></code></td>
                                <td style="color:#6b7280;font-size:12px"><?php echo esc_html( $entry->referrer ?: '—' ); ?></td>
                                <td><?php echo (int) $entry->hit_count; ?></td>
                                <td style="font-size:12px"><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $entry->last_seen ) ) ); ?></td>
                                <td>
                                    <button class="button button-small mlt-404-to-redirect"
                                        data-id="<?php echo (int) $entry->id; ?>"
                                        data-url="<?php echo esc_attr( $entry->requested_url ); ?>">
                                        → Redirect
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>

        <!-- Modal: 404 → Redirect -->
        <div id="mlt-404-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;align-items:center;justify-content:center">
            <div style="background:#fff;border-radius:8px;padding:28px;width:480px;max-width:90vw">
                <h3 style="margin:0 0 16px">Redirect aus 404 anlegen</h3>
                <p style="color:#6b7280;margin:0 0 12px;font-size:13px">Quelle: <code id="mlt-modal-src"></code></p>
                <input type="hidden" id="mlt-modal-404-id" />
                <input type="text" id="mlt-modal-dst" placeholder="Ziel-URL eingeben…" class="large-text" style="margin-bottom:12px" />
                <select id="mlt-modal-type" style="margin-bottom:16px">
                    <option value="301">301 – Permanent</option>
                    <option value="302">302 – Temporär</option>
                </select><br>
                <button type="button" class="button button-primary" id="mlt-modal-save">Redirect anlegen</button>
                <button type="button" class="button" id="mlt-modal-cancel" style="margin-left:8px">Abbrechen</button>
            </div>
        </div>
        <?php
    }

    // ── AJAX-Handler ──────────────────────────────────────────────────────────

    public function ajax_save() {
        check_ajax_referer( 'mlt_redirects', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Keine Berechtigung' );

        $source = $this->normalize_url( sanitize_text_field( $_POST['source'] ?? '' ) );
        $target = sanitize_url( $_POST['target'] ?? '' );
        $type   = in_array( (int) ( $_POST['type'] ?? 301 ), [ 301, 302, 307 ] ) ? (int) $_POST['type'] : 301;

        if ( ! $source || ! $target ) wp_send_json_error( 'Quelle und Ziel sind erforderlich.' );

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'mlt_redirects', [
            'source_url'    => $source,
            'target_url'    => $target,
            'redirect_type' => $type,
            'is_active'     => 1,
            'hit_count'     => 0,
            'created_at'    => current_time( 'mysql' ),
        ], [ '%s', '%s', '%d', '%d', '%d', '%s' ] );

        wp_send_json_success( [ 'id' => $wpdb->insert_id, 'source' => $source, 'target' => $target, 'type' => $type ] );
    }

    public function ajax_delete() {
        check_ajax_referer( 'mlt_redirects', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mlt_redirects', [ 'id' => (int) $_POST['id'] ], [ '%d' ] );
        wp_send_json_success();
    }

    public function ajax_toggle() {
        check_ajax_referer( 'mlt_redirects', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        global $wpdb;
        $current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}mlt_redirects WHERE id = %d", (int) $_POST['id'] ) );
        $new     = $current ? 0 : 1;
        $wpdb->update( $wpdb->prefix . 'mlt_redirects', [ 'is_active' => $new ], [ 'id' => (int) $_POST['id'] ], [ '%d' ], [ '%d' ] );
        wp_send_json_success( [ 'active' => $new ] );
    }

    public function ajax_404_delete() {
        check_ajax_referer( 'mlt_redirects', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'mlt_404_log', [ 'id' => (int) $_POST['id'] ], [ '%d' ] );
        wp_send_json_success();
    }

    public function ajax_404_to_redirect() {
        check_ajax_referer( 'mlt_redirects', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $source = $this->normalize_url( sanitize_text_field( $_POST['source'] ?? '' ) );
        $target = sanitize_url( $_POST['target'] ?? '' );
        $type   = in_array( (int) ( $_POST['type'] ?? 301 ), [ 301, 302, 307 ] ) ? (int) $_POST['type'] : 301;
        $log_id = (int) ( $_POST['log_id'] ?? 0 );

        if ( ! $source || ! $target ) wp_send_json_error( 'Quelle und Ziel sind erforderlich.' );

        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'mlt_redirects', [
            'source_url'    => $source,
            'target_url'    => $target,
            'redirect_type' => $type,
            'is_active'     => 1,
            'hit_count'     => 0,
            'created_at'    => current_time( 'mysql' ),
        ], [ '%s', '%s', '%d', '%d', '%d', '%s' ] );

        // 404-Log-Eintrag entfernen
        if ( $log_id ) {
            $wpdb->delete( $wpdb->prefix . 'mlt_404_log', [ 'id' => $log_id ], [ '%d' ] );
        }

        wp_send_json_success( [ 'redirect_id' => $wpdb->insert_id ] );
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    private function normalize_url( $url ) {
        $url = trim( $url );
        // Nur Pfad, kein Host
        if ( strpos( $url, 'http' ) === 0 ) {
            $parsed = parse_url( $url );
            $url    = ( $parsed['path'] ?? '/' );
            if ( ! empty( $parsed['query'] ) ) $url .= '?' . $parsed['query'];
        }
        // Wildcard-Pfad: Trailing-Slash nicht erzwingen
        if ( substr( $url, -1 ) !== '*' && substr( $url, -1 ) !== '/' && strpos( $url, '?' ) === false ) {
            $url = trailingslashit( $url );
        }
        return $url ?: '/';
    }
}
