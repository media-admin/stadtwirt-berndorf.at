<?php
/**
 * Redirects Manager
 * 301/302/307 redirects with wildcard support, managed via admin UI.
 * Also logs 404 hits with counter for analysis.
 *
 * Features:
 * - Admin table: Source URL, Target URL, Type (301/302/307), Hits, Date
 * - Wildcard support: /old-path/* → /new-path/*
 * - 404 log with hit counter (auto-cleared after 90 days via WP-Cron)
 * - Import/Export via CSV
 *
 * @package Media Lab SEO Toolkit
 * @version 1.1.1
 * TODO: Implement redirect manager + 404 logger
 */
if (!defined('ABSPATH')) { exit; }

// ── Admin-Menü: Redirections unter Einstellungen ───────────────────────────

add_action('admin_menu', function() {
    add_options_page(
        'Redirections',
        'Redirections',
        'manage_options',
        'medialab-redirections',
        'medialab_redirections_render_page'
    );
});

function medialab_redirections_render_page(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'medialab_redirects';

    // Tabelle anlegen falls nicht vorhanden
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
        $wpdb->query("CREATE TABLE $table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source varchar(500) NOT NULL,
            target varchar(500) NOT NULL,
            type smallint(3) NOT NULL DEFAULT 301,
            hits int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source (source(191))
        ) {$wpdb->get_charset_collate()};");
    }

    // Speichern
    if (isset($_POST['ml_redirect_save']) && check_admin_referer('ml_redirect_save')) {
        $source = sanitize_text_field($_POST['source'] ?? '');
        $target = esc_url_raw($_POST['target'] ?? '');
        $type   = in_array((int)($_POST['type'] ?? 301), [301, 302, 307]) ? (int)$_POST['type'] : 301;
        if ($source && $target) {
            $wpdb->insert($table, compact('source', 'target', 'type'));
            echo '<div class="notice notice-success is-dismissible"><p>Redirect gespeichert.</p></div>';
        }
    }

    // Löschen
    if (isset($_GET['delete']) && check_admin_referer('ml_delete_' . (int)$_GET['delete'])) {
        $wpdb->delete($table, ['id' => (int)$_GET['delete']]);
    }

    $redirects = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    ?>
    <div class="wrap">
        <h1>🔀 Redirections</h1>

        <h2>Neuer Redirect</h2>
        <form method="post">
            <?php wp_nonce_field('ml_redirect_save'); ?>
            <table class="form-table">
                <tr><th>Quelle</th><td><input name="source" class="regular-text" placeholder="/alte-url/" required></td></tr>
                <tr><th>Ziel</th><td><input name="target" class="regular-text" placeholder="/neue-url/" required></td></tr>
                <tr><th>Typ</th><td>
                    <select name="type">
                        <option value="301">301 – Permanent</option>
                        <option value="302">302 – Temporär</option>
                        <option value="307">307 – Temporär (POST-safe)</option>
                    </select>
                </td></tr>
            </table>
            <?php submit_button('Redirect hinzufügen', 'primary', 'ml_redirect_save'); ?>
        </form>

        <h2>Bestehende Redirects (<?php echo count($redirects); ?>)</h2>
        <table class="widefat striped">
            <thead><tr><th>Quelle</th><th>Ziel</th><th>Typ</th><th>Hits</th><th>Erstellt</th><th></th></tr></thead>
            <tbody>
            <?php if ($redirects): foreach ($redirects as $r): ?>
                <tr>
                    <td><code><?php echo esc_html($r->source); ?></code></td>
                    <td><code><?php echo esc_html($r->target); ?></code></td>
                    <td><?php echo esc_html($r->type); ?></td>
                    <td><?php echo esc_html($r->hits); ?></td>
                    <td><?php echo esc_html($r->created_at); ?></td>
                    <td><a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=medialab-redirections&delete=' . $r->id), 'ml_delete_' . $r->id)); ?>" onclick="return confirm('Löschen?')" class="button button-small">Löschen</a></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="6">Noch keine Redirects angelegt.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ── Redirect-Handler im Frontend ───────────────────────────────────────────

add_action('template_redirect', function() {
    global $wpdb;
    $table   = $wpdb->prefix . 'medialab_redirects';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return;

    $request = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $row     = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE source = %s LIMIT 1", $request
    ));

    if (!$row) {
        // Wildcard: /alte-url/* → /neue-url/*
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE %s LIKE REPLACE(source, '*', '%%') AND source LIKE '%%*' LIMIT 1",
            $request
        ));
        if ($row) {
            $prefix = rtrim(str_replace('*', '', $row->source), '/');
            $suffix = substr($request, strlen($prefix));
            $target = rtrim($row->target, '/') . $suffix;
            $wpdb->query($wpdb->prepare("UPDATE $table SET hits = hits + 1 WHERE id = %d", $row->id));
            wp_redirect($target, $row->type);
            exit;
        }
    }

    if ($row) {
        $wpdb->query($wpdb->prepare("UPDATE $table SET hits = hits + 1 WHERE id = %d", $row->id));
        wp_redirect($row->target, $row->type);
        exit;
    }
}, 1);
