<?php
/**
 * Redirections + 404 Tracker
 *
 * Tabellen:
 *   {prefix}_medialab_redirects   – Redirect-Regeln
 *   {prefix}_medialab_404s        – 404-Log
 *
 * Features:
 *   - 301/302/307 Redirects (manuell angelegt oder aus 404-Log)
 *   - 404-Aufrufe automatisch loggen (URL, Referrer, Anzahl, letzter Aufruf)
 *   - Backend: Tab "Redirects" + Tab "404-Log"
 *   - Aus 404-Log direkt einen Redirect anlegen
 *   - Wildcard-Support (*) am Ende des Quell-Pfads
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Redirects {

    private $table_redirects;
    private $table_404s;

    public function __construct() {
        global $wpdb;
        $this->table_redirects = $wpdb->prefix . 'medialab_redirects';
        $this->table_404s      = $wpdb->prefix . 'medialab_404s';

        // DB-Tabellen anlegen bei Aktivierung
        register_activation_hook(MEDIALAB_SEO_FILE, array($this, 'create_tables'));

        // Tabellen anlegen falls noch nicht vorhanden (Upgrade-Sicherheit)
        add_action('plugins_loaded', array($this, 'maybe_create_tables'), 20);

        // Redirects ausführen
        add_action('template_redirect', array($this, 'execute_redirect'), 1);

        // 404s loggen
        add_action('template_redirect', array($this, 'log_404'), 2);

        // Admin
        add_action('admin_menu', array($this, 'add_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX: 404-Eintrag als Redirect übernehmen
        add_action('wp_ajax_medialab_404_to_redirect', array($this, 'ajax_404_to_redirect'));

        // AJAX: 404-Log leeren
        add_action('wp_ajax_medialab_clear_404_log', array($this, 'ajax_clear_404_log'));
    }

    // ─────────────────────────────────────────────────────────────
    // DATENBANK
    // ─────────────────────────────────────────────────────────────

    public function create_tables() {
        $this->maybe_create_tables();
    }

    public function maybe_create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$this->table_redirects} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            source      VARCHAR(512)        NOT NULL,
            destination TEXT                NOT NULL,
            type        SMALLINT(3)         NOT NULL DEFAULT 301,
            hits        BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            enabled     TINYINT(1)          NOT NULL DEFAULT 1,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source(191))
        ) $charset;");

        dbDelta("CREATE TABLE {$this->table_404s} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url         VARCHAR(512)        NOT NULL,
            referrer    VARCHAR(512)                 DEFAULT NULL,
            hits        BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            last_seen   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url(191))
        ) $charset;");
    }

    // ─────────────────────────────────────────────────────────────
    // REDIRECT AUSFÜHREN
    // ─────────────────────────────────────────────────────────────

    public function execute_redirect() {
        global $wpdb;

        // REQUEST_URI: Pfad extrahieren, max. 512 Zeichen, nur erlaubte Zeichen
        $raw     = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        $path    = (string) wp_parse_url( $raw, PHP_URL_PATH );
        $request = '/' . ltrim( $path, '/' );
        $request = substr( $request, 0, 512 );

        // Exakter Treffer
        $redirect = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_redirects} WHERE source = %s AND enabled = 1 LIMIT 1",
            $request
        ) );

        // Wildcard-Treffer (source endet auf *)
        // prepare() mit LIKE: Literal '%*' enthält keinen User-Input → esc_like() für Konsistenz
        if ( ! $redirect ) {
            $like     = $wpdb->esc_like( '*' );
            $wildcards = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$this->table_redirects} WHERE source LIKE %s AND enabled = 1",
                '%' . $like
            ) );
            foreach ( (array) $wildcards as $row ) {
                $prefix = rtrim( $row->source, '*' );
                if ( $prefix !== '' && strpos( $request, $prefix ) === 0 ) {
                    $redirect = $row;
                    break;
                }
            }
        }

        if ( ! $redirect ) return;

        // Hit-Counter erhöhen
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table_redirects} SET hits = hits + 1 WHERE id = %d",
            $redirect->id
        ) );

        $destination = $redirect->destination;

        // Wildcard: Suffix sicher anhängen
        // Suffix kommt aus User-Input → Path-Traversal und Protocol-Injection verhindern
        if ( substr( $redirect->source, -1 ) === '*' ) {
            $prefix = rtrim( $redirect->source, '*' );
            $suffix = substr( $request, strlen( $prefix ) );

            if ( $suffix ) {
                // Path-Traversal-Sequenzen entfernen: ../ und ..\ und //
                $suffix = preg_replace( '#(\.\.[\/]|[\/]{2,})#', '', $suffix );
                // Protocol-Injection verhindern (z.B. "//evil.com")
                $suffix = ltrim( $suffix, '/' );
                if ( $suffix ) {
                    $destination = rtrim( $destination, '/' ) . '/' . $suffix;
                }
            }
        }

        wp_redirect( esc_url_raw( $destination ), (int) $redirect->type );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // 404 LOGGEN
    // ─────────────────────────────────────────────────────────────

    public function log_404() {
        if (!is_404()) return;

        global $wpdb;

        // URL aus REQUEST_URI: Pfad begrenzen (DB-Flooding verhindern)
        $raw      = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
        $path     = (string) wp_parse_url( $raw, PHP_URL_PATH );
        $url      = substr( '/' . ltrim( $path, '/' ), 0, 512 );

        // Referrer begrenzen
        $raw_ref  = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '';
        $referrer = $raw_ref ? substr( esc_url_raw( $raw_ref ), 0, 512 ) : '';

        // Bereits vorhanden → Hit + last_seen updaten
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_404s} WHERE url = %s LIMIT 1",
            $url
        ));

        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_404s} SET hits = hits + 1, last_seen = NOW() WHERE id = %d",
                $existing
            ));
        } else {
            $wpdb->insert($this->table_404s, array(
                'url'      => $url,
                'referrer' => $referrer,
                'hits'     => 1,
                'last_seen'=> current_time('mysql'),
            ));
        }
    }

    // ─────────────────────────────────────────────────────────────
    // ADMIN SEITE
    // ─────────────────────────────────────────────────────────────

    public function add_admin_page() {
        add_submenu_page(
            'options-general.php',
            'Redirections',
            'Redirections',
            'manage_options',
            'medialab-redirects',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_scripts($hook) {
        // Unter "Einstellungen" lautet der Hook: settings_page_{slug}
        if ($hook !== 'settings_page_medialab-redirects') return;

        wp_enqueue_script(
            'medialab-redirects',
            MEDIALAB_SEO_URL . 'assets/js/redirects.js',
            array('jquery'),
            MEDIALAB_SEO_VERSION,
            true
        );
        wp_localize_script('medialab-redirects', 'medialabRedirects', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('medialab_redirects'),
        ));
        wp_enqueue_style(
            'medialab-redirects',
            MEDIALAB_SEO_URL . 'assets/css/redirects.css',
            array(),
            MEDIALAB_SEO_VERSION
        );
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $this->handle_form_actions();

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'redirects';
        ?>
        <div class="wrap medialab-redirects-wrap">
            <h1>
                <span class="dashicons dashicons-randomize" style="font-size:28px;margin-right:8px;vertical-align:middle;"></span>
                Redirections
            </h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=medialab-redirects&tab=redirects"
                   class="nav-tab <?php echo $active_tab === 'redirects' ? 'nav-tab-active' : ''; ?>">
                    Redirects
                    <span class="medialab-badge"><?php echo $this->count_redirects(); ?></span>
                </a>
                <a href="?page=medialab-redirects&tab=404s"
                   class="nav-tab <?php echo $active_tab === '404s' ? 'nav-tab-active' : ''; ?>">
                    404-Log
                    <span class="medialab-badge medialab-badge--error"><?php echo $this->count_404s(); ?></span>
                </a>
            </nav>

            <div class="medialab-tab-content">
                <?php if ($active_tab === 'redirects') : ?>
                    <?php $this->render_redirects_tab(); ?>
                <?php else : ?>
                    <?php $this->render_404_tab(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // ── Tab: Redirects ────────────────────────────────────────────

    private function render_redirects_tab() {
        global $wpdb;
        $edit_id  = isset($_GET['edit']) ? absint($_GET['edit']) : 0;
        $edit_row = $edit_id ? $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_redirects} WHERE id = %d", $edit_id
        )) : null;
        ?>

        <!-- Formular: Neuer / Bearbeiten -->
        <div class="medialab-card">
            <h2><?php echo $edit_row ? 'Redirect bearbeiten' : 'Neuen Redirect anlegen'; ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('medialab_redirect_save'); ?>
                <input type="hidden" name="medialab_action" value="save_redirect">
                <?php if ($edit_row) : ?>
                    <input type="hidden" name="redirect_id" value="<?php echo $edit_row->id; ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th><label for="source">Quell-URL (Source)</label></th>
                        <td>
                            <input type="text" id="source" name="source" class="regular-text"
                                   value="<?php echo esc_attr($edit_row->source ?? ''); ?>"
                                   placeholder="/alte-seite" required>
                            <p class="description">Relativer Pfad ab Root. Wildcard am Ende möglich: <code>/blog/*</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="destination">Ziel-URL (Destination)</label></th>
                        <td>
                            <input type="text" id="destination" name="destination" class="regular-text"
                                   value="<?php echo esc_attr($edit_row->destination ?? ''); ?>"
                                   placeholder="/neue-seite oder https://..." required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="type">Redirect-Typ</label></th>
                        <td>
                            <select id="type" name="type">
                                <?php foreach (array(301 => '301 – Permanent', 302 => '302 – Temporär', 307 => '307 – Temporär (Methode beibehalten)') as $code => $label) : ?>
                                    <option value="<?php echo $code; ?>" <?php selected($edit_row->type ?? 301, $code); ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="enabled">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="enabled" name="enabled" value="1"
                                       <?php checked($edit_row->enabled ?? 1, 1); ?>>
                                Aktiv
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $edit_row ? 'Speichern' : 'Redirect anlegen'; ?>
                    </button>
                    <?php if ($edit_row) : ?>
                        <a href="?page=medialab-redirects&tab=redirects" class="button">Abbrechen</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- Liste -->
        <?php
        $redirects = $wpdb->get_results(
            "SELECT * FROM {$this->table_redirects} ORDER BY created_at DESC"
        );
        if ($redirects) :
        ?>
        <div class="medialab-card">
            <table class="wp-list-table widefat fixed striped medialab-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Destination</th>
                        <th width="60">Typ</th>
                        <th width="70">Hits</th>
                        <th width="80">Status</th>
                        <th width="130">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($redirects as $r) : ?>
                    <tr class="<?php echo $r->enabled ? '' : 'medialab-disabled-row'; ?>">
                        <td><code><?php echo esc_html($r->source); ?></code></td>
                        <td><code><?php echo esc_html($r->destination); ?></code></td>
                        <td>
                            <span class="medialab-badge medialab-badge--<?php echo $r->type == 301 ? 'success' : 'info'; ?>">
                                <?php echo $r->type; ?>
                            </span>
                        </td>
                        <td><?php echo number_format_i18n($r->hits); ?></td>
                        <td>
                            <?php if ($r->enabled) : ?>
                                <span class="medialab-status medialab-status--on">Aktiv</span>
                            <?php else : ?>
                                <span class="medialab-status medialab-status--off">Inaktiv</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=medialab-redirects&tab=redirects&edit=<?php echo $r->id; ?>"
                               class="button button-small">Bearbeiten</a>
                            <a href="<?php echo wp_nonce_url('?page=medialab-redirects&tab=redirects&medialab_action=delete_redirect&redirect_id=' . $r->id, 'medialab_redirect_delete_' . $r->id); ?>"
                               class="button button-small medialab-btn-delete"
                               onclick="return confirm('Redirect wirklich löschen?')">Löschen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
    }

    // ── Tab: 404-Log ─────────────────────────────────────────────

    private function render_404_tab() {
        global $wpdb;
        $logs = $wpdb->get_results(
            "SELECT * FROM {$this->table_404s} ORDER BY hits DESC, last_seen DESC LIMIT 500"
        );
        ?>
        <div class="medialab-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <p style="margin:0;">URLs die einen 404-Fehler ausgelöst haben – sortiert nach Häufigkeit.</p>
                <button id="medialab-clear-404" class="button">404-Log leeren</button>
            </div>

            <?php if ($logs) : ?>
            <table class="wp-list-table widefat fixed striped medialab-table">
                <thead>
                    <tr>
                        <th>URL (404)</th>
                        <th>Referrer</th>
                        <th width="70">Hits</th>
                        <th width="140">Zuletzt gesehen</th>
                        <th width="120">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr id="log-row-<?php echo $log->id; ?>">
                        <td><code><?php echo esc_html($log->url); ?></code></td>
                        <td>
                            <?php if ($log->referrer) : ?>
                                <a href="<?php echo esc_url($log->referrer); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html(parse_url($log->referrer, PHP_URL_HOST) ?: $log->referrer); ?>
                                </a>
                            <?php else : ?>
                                <span style="color:#aaa;">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="medialab-badge <?php echo $log->hits >= 10 ? 'medialab-badge--error' : 'medialab-badge--info'; ?>">
                                <?php echo number_format_i18n($log->hits); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' H:i', strtotime($log->last_seen))); ?></td>
                        <td>
                            <button class="button button-primary button-small medialab-create-redirect"
                                    data-url="<?php echo esc_attr($log->url); ?>"
                                    data-log-id="<?php echo $log->id; ?>">
                                → Redirect anlegen
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <p style="color:#888;">Noch keine 404-Aufrufe registriert.</p>
            <?php endif; ?>
        </div>

        <!-- Modal: Redirect aus 404 erstellen -->
        <div id="medialab-redirect-modal" style="display:none;">
            <div class="medialab-modal-backdrop"></div>
            <div class="medialab-modal-box">
                <h3>Redirect anlegen</h3>
                <table class="form-table">
                    <tr>
                        <th>Source</th>
                        <td><input type="text" id="modal-source" class="regular-text" readonly></td>
                    </tr>
                    <tr>
                        <th>Destination</th>
                        <td><input type="text" id="modal-destination" class="regular-text" placeholder="/neue-url"></td>
                    </tr>
                    <tr>
                        <th>Typ</th>
                        <td>
                            <select id="modal-type">
                                <option value="301">301 – Permanent</option>
                                <option value="302">302 – Temporär</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p>
                    <button id="modal-save" class="button button-primary">Redirect speichern</button>
                    <button id="modal-cancel" class="button">Abbrechen</button>
                </p>
                <input type="hidden" id="modal-log-id">
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────────────────────
    // FORMULAR-AKTIONEN
    // ─────────────────────────────────────────────────────────────

    private function handle_form_actions() {
        global $wpdb;

        $action = $_POST['medialab_action'] ?? $_GET['medialab_action'] ?? '';

        if ($action === 'save_redirect' && isset($_POST['source'])) {
            check_admin_referer('medialab_redirect_save');

            $data = array(
                'source'      => '/' . ltrim(sanitize_text_field($_POST['source']), '/'),
                'destination' => esc_url_raw($_POST['destination']),
                'type'        => absint($_POST['type']),
                'enabled'     => isset($_POST['enabled']) ? 1 : 0,
            );

            $id = absint($_POST['redirect_id'] ?? 0);
            if ($id) {
                $wpdb->update($this->table_redirects, $data, array('id' => $id));
            } else {
                $wpdb->insert($this->table_redirects, $data);
            }

            wp_safe_redirect(admin_url('admin.php?page=medialab-redirects&tab=redirects&saved=1'));
            exit;
        }

        if ($action === 'delete_redirect' && isset($_GET['redirect_id'])) {
            $id = absint($_GET['redirect_id']);
            check_admin_referer('medialab_redirect_delete_' . $id);
            $wpdb->delete($this->table_redirects, array('id' => $id));
            wp_safe_redirect(admin_url('admin.php?page=medialab-redirects&tab=redirects&deleted=1'));
            exit;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // AJAX
    // ─────────────────────────────────────────────────────────────

    public function ajax_404_to_redirect() {
        check_ajax_referer('medialab_redirects', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Keine Berechtigung');

        global $wpdb;

        $source      = '/' . ltrim(sanitize_text_field($_POST['source'] ?? ''), '/');
        $destination = esc_url_raw($_POST['destination'] ?? '');
        $type        = absint($_POST['type'] ?? 301);
        $log_id      = absint($_POST['log_id'] ?? 0);

        if (!$source || !$destination) wp_send_json_error('Fehlende Daten');

        $wpdb->insert($this->table_redirects, array(
            'source'      => $source,
            'destination' => $destination,
            'type'        => $type,
            'enabled'     => 1,
        ));

        // 404-Log-Eintrag optional entfernen
        if ($log_id) {
            $wpdb->delete($this->table_404s, array('id' => $log_id));
        }

        wp_send_json_success(array('redirect_id' => $wpdb->insert_id));
    }

    public function ajax_clear_404_log() {
        check_ajax_referer('medialab_redirects', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Keine Berechtigung');

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table_404s}");
        wp_send_json_success();
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────

    private function count_redirects() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_redirects}");
    }

    private function count_404s() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_404s}");
    }
}

new MediaLab_Redirects();
