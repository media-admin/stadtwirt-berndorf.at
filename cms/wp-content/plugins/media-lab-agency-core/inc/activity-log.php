<?php
/**
 * Activity Log System
 *
 * Logs wichtige Änderungen im Backend für Audit-Zwecke.
 *
 * Externe Plugins können Events über den zentralen Hook eintragen:
 *   do_action( 'medialab_log_event', $action, $object_type, $object_id, $object_name, $details );
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Activity_Log {

    private static $instance = null;
    private $table_name;

    /** Interne CPTs die nicht geloggt werden sollen */
    private const IGNORED_POST_TYPES = [
        'revision', 'auto-draft', 'nav_menu_item',
        'custom_css', 'customize_changeset', 'oembed_cache',
        'user_request', 'wp_block', 'wp_template',
        'wp_template_part', 'wp_global_styles', 'wp_navigation',
        'flamingo_inbound', 'flamingo_outbound', // CF7 Flamingo – eigener Handler
    ];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'medialab_activity_log';

        register_activation_hook(MEDIALAB_CORE_FILE, [$this, 'create_table']);

        // Tabelle auch bei Plugin-Updates sicherstellen (nicht nur Erstaktivierung)
        add_action('plugins_loaded', [$this, 'maybe_create_table']);

        add_action('admin_menu', [$this, 'add_admin_page'], 999);

        $this->register_hooks();
    }

    // ─── Datenbank ────────────────────────────────────────────────────────────

    public function maybe_create_table(): void {
        if (get_option('medialab_activity_log_db_version') === '1.0') return;
        $this->create_table();
        update_option('medialab_activity_log_db_version', '1.0');
    }

    public function create_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_name varchar(255) NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) DEFAULT NULL,
            object_name text DEFAULT NULL,
            details text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

    public function log(
        string $action,
        string $object_type,
        ?int $object_id = null,
        string $object_name = '',
        string $details = ''
    ): void {
        global $wpdb;
        $user = wp_get_current_user();
        $wpdb->insert($this->table_name, [
            'user_id'     => $user->ID,
            'user_name'   => $user->display_name ?: $user->user_login,
            'action'      => sanitize_text_field($action),
            'object_type' => sanitize_text_field($object_type),
            'object_id'   => $object_id,
            'object_name' => sanitize_text_field($object_name),
            'details'     => sanitize_textarea_field($details),
            'ip_address'  => $this->get_client_ip(),
            'created_at'  => current_time('mysql'),
        ]);
    }

    // ─── IP ───────────────────────────────────────────────────────────────────

    private function get_client_ip(): string {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];
        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) continue;
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    public static function anonymize_ip(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', inet_ntop(inet_pton($ip)));
            $parts = array_pad($parts, 8, '0');
            for ($i = 3; $i < 8; $i++) $parts[$i] = '0';
            return implode(':', $parts);
        }
        return '0.0.0.0';
    }

    public function anonymize_old_ip_addresses(): void {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, ip_address FROM {$this->table_name}
             WHERE created_at < %s
             AND ip_address != ''
             AND ip_address NOT LIKE '%.0'
             AND ip_address NOT LIKE '%::0'
             LIMIT 500",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
        foreach ((array) $rows as $row) {
            $wpdb->update(
                $this->table_name,
                ['ip_address' => self::anonymize_ip($row->ip_address)],
                ['id' => $row->id],
                ['%s'], ['%d']
            );
        }
    }

    // ─── Hooks registrieren ───────────────────────────────────────────────────

    private function register_hooks(): void {

        // DSGVO: IP-Anonymisierung nach 90 Tagen
        add_action('medialab_anonymize_ip_addresses', [$this, 'anonymize_old_ip_addresses']);
        if (!wp_next_scheduled('medialab_anonymize_ip_addresses')) {
            wp_schedule_event(time(), 'daily', 'medialab_anonymize_ip_addresses');
        }

        // ── Posts & Custom Post Types ─────────────────────────────────────────
        add_action('save_post',          [$this, 'log_post_save'], 10, 3);
        add_action('before_delete_post', [$this, 'log_post_delete']);
        add_action('transition_post_status', [$this, 'log_post_status_change'], 10, 3);

        // ── Benutzer ──────────────────────────────────────────────────────────
        add_action('wp_login',        [$this, 'log_user_login'],   10, 2);
        add_action('wp_logout',       [$this, 'log_user_logout']);
        add_action('wp_login_failed', [$this, 'log_login_failed'], 10, 2);
        add_action('user_register',   [$this, 'log_user_created']);
        add_action('profile_update',  [$this, 'log_user_updated'], 10, 2);
        add_action('delete_user',     [$this, 'log_user_deleted']);
        add_action('set_user_role',   [$this, 'log_user_role_change'], 10, 3);

        // ── Passwörter ────────────────────────────────────────────────────────
        add_action('password_reset',       [$this, 'log_password_reset'], 10, 1);
        add_action('after_password_reset', [$this, 'log_password_reset'], 10, 1);

        // ── Plugins ───────────────────────────────────────────────────────────
        add_action('activated_plugin',   [$this, 'log_plugin_activated']);
        add_action('deactivated_plugin', [$this, 'log_plugin_deactivated']);

        // ── Theme ─────────────────────────────────────────────────────────────
        add_action('switch_theme', [$this, 'log_theme_switch'], 10, 3);

        // ── Medien ────────────────────────────────────────────────────────────
        add_action('add_attachment',    [$this, 'log_media_upload']);
        add_action('delete_attachment', [$this, 'log_media_delete']);

        // ── Menüs ─────────────────────────────────────────────────────────────
        add_action('wp_update_nav_menu', [$this, 'log_menu_update']);

        // ── Kommentare ────────────────────────────────────────────────────────
        add_action('comment_post',   [$this, 'log_comment_created'], 10, 3);
        add_action('edit_comment',   [$this, 'log_comment_edited']);
        add_action('delete_comment', [$this, 'log_comment_deleted']);
        add_action('spam_comment',   [$this, 'log_comment_spam']);

        // ── WordPress-Optionen ────────────────────────────────────────────────
        add_action('updated_option', [$this, 'log_option_update'], 10, 3);

        // ── ACF Options-Seiten ────────────────────────────────────────────────
        // Feuert wenn eine ACF Options-Seite gespeichert wird
        add_action('acf/save_post', [$this, 'log_acf_options_save'], 20);

        // ── CF7 / Flamingo ────────────────────────────────────────────────────
        add_action('wpcf7_mail_sent', [$this, 'log_cf7_sent']);

        // ── Zentraler Hook für Media Lab Plugins ──────────────────────────────
        // Verwendung in anderen Plugins:
        //   do_action( 'medialab_log_event', 'redirect_created', 'redirect', $id, $from, $details );
        add_action('medialab_log_event', [$this, 'log_external_event'], 10, 5);
    }

    // ─── Handler ──────────────────────────────────────────────────────────────

    // Posts & CPTs
    public function log_post_save(int $post_id, \WP_Post $post, bool $update): void {
        if (in_array($post->post_type, self::IGNORED_POST_TYPES, true)) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if ($post->post_status === 'auto-draft') return;

        $action = $update ? 'post_updated' : 'post_created';
        $this->log($action, $post->post_type, $post_id, $post->post_title, "Status: {$post->post_status}");
    }

    public function log_post_delete(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || in_array($post->post_type, self::IGNORED_POST_TYPES, true)) return;
        $this->log('post_deleted', $post->post_type, $post_id, $post->post_title);
    }

    public function log_post_status_change(string $new, string $old, \WP_Post $post): void {
        if (in_array($post->post_type, self::IGNORED_POST_TYPES, true)) return;
        // Nur relevante Übergänge loggen (z.B. publish → trash, draft → publish)
        $relevant = ['publish', 'trash', 'private', 'pending'];
        if ($new === $old || !in_array($new, $relevant, true)) return;
        $this->log('post_status_changed', $post->post_type, $post->ID, $post->post_title, "{$old} → {$new}");
    }

    // Benutzer
    public function log_user_login(string $user_login, \WP_User $user): void {
        $this->log('user_login', 'user', $user->ID, $user->display_name);
    }

    public function log_user_logout(): void {
        $this->log('user_logout', 'user', get_current_user_id());
    }

    public function log_login_failed(string $username, \WP_Error $error): void {
        $this->log('login_failed', 'user', null, $username, $error->get_error_message());
    }

    public function log_user_created(int $user_id): void {
        $user = get_userdata($user_id);
        if (!$user) return;
        $this->log('user_created', 'user', $user_id, $user->display_name ?: $user->user_login, 'Rolle: ' . implode(', ', $user->roles));
    }

    public function log_user_updated(int $user_id, \WP_User $old_user): void {
        $user = get_userdata($user_id);
        if (!$user) return;
        $this->log('user_updated', 'user', $user_id, $user->display_name ?: $user->user_login);
    }

    public function log_user_deleted(int $user_id): void {
        $user = get_userdata($user_id);
        $name = $user ? ($user->display_name ?: $user->user_login) : "ID #{$user_id}";
        $this->log('user_deleted', 'user', $user_id, $name);
    }

    public function log_user_role_change(int $user_id, string $role, array $old_roles): void {
        $user = get_userdata($user_id);
        $name = $user ? ($user->display_name ?: $user->user_login) : "ID #{$user_id}";
        $old  = implode(', ', $old_roles) ?: '–';
        $this->log('user_role_changed', 'user', $user_id, $name, "Von: {$old} → Nach: {$role}");
    }

    // Passwort
    public function log_password_reset(\WP_User $user): void {
        $this->log('password_reset', 'user', $user->ID, $user->display_name ?: $user->user_login);
    }

    // Plugins & Theme
    public function log_plugin_activated(string $plugin): void {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $this->log('plugin_activated', 'plugin', null, $data['Name'] ?? $plugin);
    }

    public function log_plugin_deactivated(string $plugin): void {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $this->log('plugin_deactivated', 'plugin', null, $data['Name'] ?? $plugin);
    }

    public function log_theme_switch(string $new_name, \WP_Theme $new_theme, \WP_Theme $old_theme): void {
        $this->log('theme_switched', 'theme', null, $new_name, "Von: {$old_theme->get('Name')}");
    }

    // Medien
    public function log_media_upload(int $attachment_id): void {
        $file = get_attached_file($attachment_id);
        $this->log('media_uploaded', 'attachment', $attachment_id, basename($file));
    }

    public function log_media_delete(int $attachment_id): void {
        $file = get_attached_file($attachment_id);
        $this->log('media_deleted', 'attachment', $attachment_id, basename($file));
    }

    // Menüs
    public function log_menu_update(int $menu_id): void {
        $menu = wp_get_nav_menu_object($menu_id);
        $this->log('menu_updated', 'nav_menu', $menu_id, $menu->name ?? "Menu #{$menu_id}");
    }

    // Kommentare
    public function log_comment_created(int $comment_id, $approved, array $data): void {
        $post = get_post($data['comment_post_ID'] ?? 0);
        $this->log('comment_created', 'comment', $comment_id, $data['comment_author'] ?? '', $post ? $post->post_title : '');
    }

    public function log_comment_edited(int $comment_id): void {
        $comment = get_comment($comment_id);
        $this->log('comment_edited', 'comment', $comment_id, $comment->comment_author ?? '');
    }

    public function log_comment_deleted(int $comment_id): void {
        $comment = get_comment($comment_id);
        $this->log('comment_deleted', 'comment', $comment_id, $comment->comment_author ?? '');
    }

    public function log_comment_spam(int $comment_id): void {
        $comment = get_comment($comment_id);
        $this->log('comment_spam', 'comment', $comment_id, $comment->comment_author ?? '');
    }

    // WP-Optionen (nur wichtige)
    public function log_option_update(string $option, $old_value, $value): void {
        $important = [
            'blogname', 'blogdescription', 'siteurl', 'home',
            'admin_email', 'users_can_register', 'default_role',
        ];
        if (!in_array($option, $important, true)) return;
        $this->log('option_updated', 'option', null, $option, "Von: {$old_value} → Nach: {$value}");
    }

    // ACF Options-Seiten
    public function log_acf_options_save($post_id): void {
        // Nur Options-Seiten (post_id = 'options' oder 'options_<page>')
        if (!is_string($post_id) || strpos($post_id, 'options') !== 0) return;
        $screen = get_current_screen();
        $page   = $_GET['page'] ?? $post_id;
        $this->log('acf_options_saved', 'acf_options', null, sanitize_text_field($page));
    }

    // CF7 / Flamingo
    public function log_cf7_sent(\WPCF7_ContactForm $form): void {
        $this->log('cf7_sent', 'contact_form', $form->id(), $form->title());
    }

    // ── Zentraler Hook für externe Media Lab Plugins ──────────────────────────
    // SEO Toolkit, Bookings, Events etc. rufen auf:
    //   do_action( 'medialab_log_event', $action, $object_type, $object_id, $object_name, $details );
    public function log_external_event(
        string $action,
        string $object_type,
        ?int $object_id = null,
        string $object_name = '',
        string $details = ''
    ): void {
        $this->log($action, $object_type, $object_id, $object_name, $details);
    }

    // ─── Admin-Seite ──────────────────────────────────────────────────────────

    public function add_admin_page(): void {
        add_submenu_page(
            'agency-core',
            'Activity Log',
            'Activity Log',
            'manage_options',
            'activity-log',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        global $wpdb;

        $per_page = 50;
        $page     = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $offset   = ($page - 1) * $per_page;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $logs  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
        ?>
        <div class="wrap">
            <h1>Activity Log</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="150">Datum/Zeit</th>
                        <th width="120">User</th>
                        <th width="130">Aktion</th>
                        <th width="100">Typ</th>
                        <th>Objekt</th>
                        <th>Details</th>
                        <th width="110">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(date('d.m.Y H:i:s', strtotime($log->created_at))); ?></td>
                        <td><?php echo esc_html($log->user_name); ?></td>
                        <td><?php echo esc_html($this->format_action($log->action)); ?></td>
                        <td><code><?php echo esc_html($log->object_type); ?></code></td>
                        <td>
                            <?php if ($log->object_id && get_post($log->object_id)) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($log->object_id)); ?>" target="_blank">
                                    <?php echo esc_html($log->object_name); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($log->object_name); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->details); ?></td>
                        <td><code><?php echo esc_html($log->ip_address); ?></code></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($logs)) : ?>
                    <tr><td colspan="7" style="text-align:center;padding:20px;color:#9ca3af">Noch keine Einträge vorhanden.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total > $per_page) : ?>
            <div class="tablenav bottom">
                <?php echo paginate_links([
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'current' => $page,
                    'total'   => ceil($total / $per_page),
                ]); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function format_action(string $action): string {
        $labels = [
            // Posts & CPTs
            'post_created'        => '✏️ Erstellt',
            'post_updated'        => '📝 Bearbeitet',
            'post_deleted'        => '🗑️ Gelöscht',
            'post_status_changed' => '🔄 Status',
            // Benutzer
            'user_login'          => '🔓 Login',
            'user_logout'         => '🔒 Logout',
            'login_failed'        => '⛔ Login fehlg.',
            'user_created'        => '👤 Erstellt',
            'user_updated'        => '✏️ Bearbeitet',
            'user_deleted'        => '🗑️ Gelöscht',
            'user_role_changed'   => '🔑 Rolle geändert',
            'password_reset'      => '🔑 Passwort reset',
            // Plugins & Theme
            'plugin_activated'    => '🔌 Aktiviert',
            'plugin_deactivated'  => '⏸️ Deaktiviert',
            'theme_switched'      => '🎨 Theme',
            // Medien
            'media_uploaded'      => '📤 Hochgeladen',
            'media_deleted'       => '🗑️ Gelöscht',
            // Menüs & Optionen
            'menu_updated'        => '📋 Menü',
            'option_updated'      => '⚙️ Option',
            'acf_options_saved'   => '⚙️ ACF Options',
            // Kommentare
            'comment_created'     => '💬 Kommentar',
            'comment_edited'      => '✏️ Kommentar',
            'comment_deleted'     => '🗑️ Kommentar',
            'comment_spam'        => '🚫 Spam',
            // CF7
            'cf7_sent'            => '📧 Formular',
            // SEO Toolkit
            'redirect_created'    => '↪️ Redirect',
            'redirect_updated'    => '↪️ Redirect',
            'redirect_deleted'    => '🗑️ Redirect',
            'gsc_connected'       => '🔗 GSC verbunden',
            'gsc_disconnected'    => '🔌 GSC getrennt',
            // Bookings
            'booking_created'     => '📅 Buchung',
            'booking_updated'     => '📅 Buchung',
            'booking_deleted'     => '🗑️ Buchung',
            'booking_confirmed'   => '✅ Buchung bestät.',
            'booking_cancelled'   => '❌ Buchung storn.',
            // Events
            'event_created'       => '🎉 Event',
            'event_updated'       => '🎉 Event',
            'event_deleted'       => '🗑️ Event',
        ];
        return $labels[$action] ?? $action;
    }
}

// Init
MediaLab_Activity_Log::get_instance();
