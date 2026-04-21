<?php
/**
 * Activity Log System
 * 
 * Logs wichtige Änderungen im Backend für Audit-Zwecke
 */

if (!defined('ABSPATH')) exit;

class MediaLab_Activity_Log {
    
    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'medialab_activity_log';
        
        register_activation_hook(MEDIALAB_CORE_FILE, array($this, 'create_table'));
        add_action('admin_menu', array($this, 'add_admin_page'), 999);
        
        $this->register_hooks();
    }

    /**
     * DB-Tabelle erstellen
     */
    public function create_table() {
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Event loggen
     */
    public function log($action, $object_type, $object_id = null, $object_name = '', $details = '') {
        global $wpdb;
        
        $user = wp_get_current_user();
        
        $wpdb->insert($this->table_name, array(
            'user_id'     => $user->ID,
            'user_name'   => $user->display_name ?: $user->user_login,
            'action'      => sanitize_text_field($action),
            'object_type' => sanitize_text_field($object_type),
            'object_id'   => $object_id,
            'object_name' => sanitize_text_field($object_name),
            'details'     => sanitize_textarea_field($details),
            'ip_address'  => $this->get_client_ip(),
            'created_at'  => current_time('mysql'),
        ));
    }

    /**
     * Client-IP ermitteln (Proxy-sicher)
     *
     * Reihenfolge: Cloudflare → X-Real-IP → X-Forwarded-For → REMOTE_ADDR
     * Nur öffentliche IPs werden akzeptiert (kein IP-Spoofing via Private-Range).
     * DSGVO: IP wird nach 90 Tagen automatisch anonymisiert (letztes Oktett → 0).
     */
    private function get_client_ip(): string {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_REAL_IP',          // nginx Proxy
            'HTTP_X_FORWARDED_FOR',    // Standard Proxy (kann mehrere IPs enthalten)
            'REMOTE_ADDR',             // Direkte Verbindung
        ];

        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            // X-Forwarded-For: erste (Client-)IP nehmen
            $ip = trim(explode(',', $_SERVER[$key])[0]);

            // Nur gültige öffentliche IPs akzeptieren (kein Spoofing via Private-Range)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // Fallback: REMOTE_ADDR auch bei privater Range (Localhost-Entwicklung)
        return filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    /**
     * IP-Adresse anonymisieren
     * IPv4: letztes Oktett → 0  (z.B. 192.168.1.123 → 192.168.1.0)
     * IPv6: letzte 80 Bit → 0   (z.B. 2001:db8::1234 → 2001:db8::)
     */
    public static function anonymize_ip(string $ip): string {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Letzte 5 Gruppen auf 0 setzen
            $parts = explode(':', inet_ntop(inet_pton($ip)));
            $parts = array_pad($parts, 8, '0');
            for ($i = 3; $i < 8; $i++) {
                $parts[$i] = '0';
            }
            return implode(':', $parts);
        }
        return '0.0.0.0';
    }

    /**
     * DSGVO: Alle IPs älter als 90 Tage anonymisieren (Cron-Job)
     */
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
                ['id'         => $row->id],
                ['%s'],
                ['%d']
            );
        }
    }

    /**
     * Hooks registrieren
     */
    private function register_hooks() {
        // DSGVO: IP-Anonymisierung nach 90 Tagen via WP Cron (täglich prüfen)
        add_action('medialab_anonymize_ip_addresses', array($this, 'anonymize_old_ip_addresses'));
        if (!wp_next_scheduled('medialab_anonymize_ip_addresses')) {
            wp_schedule_event(time(), 'daily', 'medialab_anonymize_ip_addresses');
        }

        // Posts & Pages
        add_action('save_post', array($this, 'log_post_save'), 10, 3);
        add_action('before_delete_post', array($this, 'log_post_delete'));
        
        // Plugins
        add_action('activated_plugin', array($this, 'log_plugin_activated'));
        add_action('deactivated_plugin', array($this, 'log_plugin_deactivated'));
        
        // Theme
        add_action('switch_theme', array($this, 'log_theme_switch'), 10, 3);
        
        // User Login/Logout
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        
        // Options
        add_action('updated_option', array($this, 'log_option_update'), 10, 3);
        
        // Media
        add_action('add_attachment', array($this, 'log_media_upload'));
        add_action('delete_attachment', array($this, 'log_media_delete'));
        
        // Menus
        add_action('wp_update_nav_menu', array($this, 'log_menu_update'));
    }

    // ─── Event Handlers ──────────────────────────────────────

    public function log_post_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if ($post->post_status === 'auto-draft') return;
        
        $action = $update ? 'updated' : 'created';
        $this->log(
            "post_{$action}",
            $post->post_type,
            $post_id,
            $post->post_title,
            "Status: {$post->post_status}"
        );
    }

    public function log_post_delete($post_id) {
        $post = get_post($post_id);
        if (!$post) return;
        
        $this->log(
            'post_deleted',
            $post->post_type,
            $post_id,
            $post->post_title
        );
    }

    public function log_plugin_activated($plugin) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $this->log(
            'plugin_activated',
            'plugin',
            null,
            $plugin_data['Name'] ?? $plugin
        );
    }

    public function log_plugin_deactivated($plugin) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $this->log(
            'plugin_deactivated',
            'plugin',
            null,
            $plugin_data['Name'] ?? $plugin
        );
    }

    public function log_theme_switch($new_name, $new_theme, $old_theme) {
        $this->log(
            'theme_switched',
            'theme',
            null,
            $new_name,
            "Von: {$old_theme->get('Name')}"
        );
    }

    public function log_user_login($user_login, $user) {
        $this->log(
            'user_login',
            'user',
            $user->ID,
            $user->display_name
        );
    }

    public function log_user_logout() {
        $this->log(
            'user_logout',
            'user',
            get_current_user_id()
        );
    }

    public function log_option_update($option, $old_value, $value) {
        // Nur wichtige Options loggen
        $important_options = array(
            'blogname', 'blogdescription', 'siteurl', 'home',
            'admin_email', 'users_can_register', 'default_role'
        );
        
        if (!in_array($option, $important_options)) return;
        
        $this->log(
            'option_updated',
            'option',
            null,
            $option,
            "Von: {$old_value} → Nach: {$value}"
        );
    }

    public function log_media_upload($attachment_id) {
        $file = get_attached_file($attachment_id);
        $this->log(
            'media_uploaded',
            'attachment',
            $attachment_id,
            basename($file)
        );
    }

    public function log_media_delete($attachment_id) {
        $file = get_attached_file($attachment_id);
        $this->log(
            'media_deleted',
            'attachment',
            $attachment_id,
            basename($file)
        );
    }

    public function log_menu_update($menu_id) {
        $menu = wp_get_nav_menu_object($menu_id);
        $this->log(
            'menu_updated',
            'nav_menu',
            $menu_id,
            $menu->name ?? "Menu #{$menu_id}"
        );
    }

    /**
     * Admin-Seite hinzufügen
     */
    public function add_admin_page() {
        add_submenu_page(
            'agency-core',
            'Activity Log',
            'Activity Log',
            'manage_options',
            'activity-log',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Admin-Seite rendern
     */
    public function render_admin_page() {
        global $wpdb;
        
        $per_page = 50;
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $offset = ($page - 1) * $per_page;
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        ?>
        <div class="wrap">
            <h1>Activity Log</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="150">Datum/Zeit</th>
                        <th width="120">User</th>
                        <th width="120">Aktion</th>
                        <th width="100">Typ</th>
                        <th>Objekt</th>
                        <th>Details</th>
                        <th width="100">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(date('d.m.Y H:i:s', strtotime($log->created_at))); ?></td>
                        <td><?php echo esc_html($log->user_name); ?></td>
                        <td><?php echo esc_html($this->format_action($log->action)); ?></td>
                        <td><?php echo esc_html($log->object_type); ?></td>
                        <td>
                            <?php if ($log->object_id) : ?>
                                <a href="<?php echo get_edit_post_link($log->object_id); ?>" target="_blank">
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
                </tbody>
            </table>
            
            <?php if ($total > $per_page) : ?>
            <div class="tablenav">
                <?php
                echo paginate_links(array(
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'current' => $page,
                    'total'   => ceil($total / $per_page),
                ));
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Action-Namen formatieren
     */
    private function format_action($action) {
        $labels = array(
            'post_created'       => '✏️ Erstellt',
            'post_updated'       => '📝 Bearbeitet',
            'post_deleted'       => '🗑️ Gelöscht',
            'plugin_activated'   => '🔌 Aktiviert',
            'plugin_deactivated' => '⏸️ Deaktiviert',
            'theme_switched'     => '🎨 Gewechselt',
            'user_login'         => '🔓 Login',
            'user_logout'        => '🔒 Logout',
            'option_updated'     => '⚙️ Geändert',
            'media_uploaded'     => '📤 Hochgeladen',
            'media_deleted'      => '🗑️ Gelöscht',
            'menu_updated'       => '📋 Aktualisiert',
        );
        
        return $labels[$action] ?? $action;
    }
}

// Init
MediaLab_Activity_Log::get_instance();
