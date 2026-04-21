<?php
/**
 * Media Replace
 *
 * Fügt in der Medien-Bibliothek einen "Datei ersetzen"-Button hinzu.
 * Die Attachment-ID, alle URLs und Verwendungen im Content bleiben erhalten.
 * Thumbnails werden automatisch neu generiert.
 *
 * Zugänglich über:
 *   - Medien → Attachment-Detailseite → "Datei ersetzen"
 *   - Medien-Bibliothek (Listenansicht) → Zeile → "Datei ersetzen"
 *
 * @package MediaLab_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Media_Replace {

    public function __construct() {
        // Link in der Attachment-Detailseite (Edit-Screen)
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_replace_field' ), 10, 2 );

        // Link in der Medien-Bibliothek Listenansicht
        add_filter( 'media_row_actions', array( $this, 'add_row_action' ), 10, 2 );

        // Replace-Seite rendern
        add_action( 'admin_menu', array( $this, 'register_replace_page' ) );

        // Upload verarbeiten
        add_action( 'admin_post_medialab_replace_media', array( $this, 'handle_upload' ) );
    }

    // ─── "Datei ersetzen" Feld auf Attachment-Edit-Screen ────────────────────

    public function add_replace_field( array $fields, WP_Post $post ): array {
        if ( ! current_user_can( 'upload_files' ) ) return $fields;

        $url = $this->replace_url( $post->ID );

        $fields['medialab_replace'] = array(
            'label'         => __( 'Datei ersetzen', 'medialab-core' ),
            'input'         => 'html',
            'html'          => '<a href="' . esc_url( $url ) . '" class="button button-secondary">'
                             . __( 'Neue Datei hochladen', 'medialab-core' )
                             . '</a>'
                             . '<p class="description" style="margin-top:6px;">'
                             . __( 'Ersetzt die Datei. ID und alle Verwendungen bleiben erhalten.', 'medialab-core' )
                             . '</p>',
            'helps'         => '',
        );

        return $fields;
    }

    // ─── "Datei ersetzen" in Listenansicht ───────────────────────────────────

    public function add_row_action( array $actions, WP_Post $post ): array {
        if ( ! current_user_can( 'upload_files' ) ) return $actions;

        $url = $this->replace_url( $post->ID );
        $actions['medialab_replace'] = '<a href="' . esc_url( $url ) . '">'
                                     . __( 'Datei ersetzen', 'medialab-core' )
                                     . '</a>';
        return $actions;
    }

    // ─── Versteckte Admin-Seite ───────────────────────────────────────────────

    public function register_replace_page(): void {
        add_submenu_page(
            null,                          // Kein sichtbarer Menüeintrag
            'Datei ersetzen',
            'Datei ersetzen',
            'upload_files',
            'medialab-replace-media',
            array( $this, 'render_replace_page' )
        );
    }

    public function render_replace_page(): void {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( __( 'Keine Berechtigung.', 'medialab-core' ) );
        }

        $attachment_id = absint( $_GET['attachment_id'] ?? 0 );
        if ( ! $attachment_id || ! get_post( $attachment_id ) ) {
            wp_die( __( 'Ungültige Attachment-ID.', 'medialab-core' ) );
        }

        $attachment  = get_post( $attachment_id );
        $current_url = wp_get_attachment_url( $attachment_id );
        $mime        = get_post_mime_type( $attachment_id );
        $file        = get_attached_file( $attachment_id );

        // Erfolgs-/Fehlermeldung nach Redirect
        $notice = '';
        if ( isset( $_GET['replaced'] ) ) {
            $notice = '<div class="notice notice-success"><p>'
                    . __( '✅ Datei wurde erfolgreich ersetzt.', 'medialab-core' )
                    . '</p></div>';
        } elseif ( isset( $_GET['error'] ) ) {
            $notice = '<div class="notice notice-error"><p>'
                    . esc_html( urldecode( $_GET['error'] ) )
                    . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-upload" style="font-size:28px;vertical-align:middle;margin-right:8px;"></span>
                <?php esc_html_e( 'Datei ersetzen', 'medialab-core' ); ?>
            </h1>

            <?php echo $notice; ?>

            <div style="max-width:640px;margin-top:20px;">

                <!-- Aktuelle Datei -->
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;margin-bottom:20px;">
                    <h3 style="margin-top:0;"><?php esc_html_e( 'Aktuelle Datei', 'medialab-core' ); ?></h3>
                    <?php if ( wp_attachment_is_image( $attachment_id ) ) : ?>
                        <img src="<?php echo esc_url( $current_url ); ?>"
                             style="max-width:200px;max-height:150px;object-fit:contain;border:1px solid #ddd;border-radius:4px;margin-bottom:10px;display:block;">
                    <?php endif; ?>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:120px;padding:4px 0;"><?php esc_html_e( 'Name', 'medialab-core' ); ?></th>
                            <td style="padding:4px 0;"><?php echo esc_html( $attachment->post_title ); ?></td>
                        </tr>
                        <tr>
                            <th style="padding:4px 0;"><?php esc_html_e( 'Typ', 'medialab-core' ); ?></th>
                            <td style="padding:4px 0;"><?php echo esc_html( $mime ); ?></td>
                        </tr>
                        <tr>
                            <th style="padding:4px 0;"><?php esc_html_e( 'Datei', 'medialab-core' ); ?></th>
                            <td style="padding:4px 0;word-break:break-all;"><code><?php echo esc_html( basename( $file ) ); ?></code></td>
                        </tr>
                        <tr>
                            <th style="padding:4px 0;"><?php esc_html_e( 'ID', 'medialab-core' ); ?></th>
                            <td style="padding:4px 0;">#<?php echo $attachment_id; ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Upload-Formular -->
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>"
                      enctype="multipart/form-data"
                      style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px 20px;">

                    <?php wp_nonce_field( 'medialab_replace_' . $attachment_id, 'medialab_replace_nonce' ); ?>
                    <input type="hidden" name="action"        value="medialab_replace_media">
                    <input type="hidden" name="attachment_id" value="<?php echo $attachment_id; ?>">

                    <h3 style="margin-top:0;"><?php esc_html_e( 'Neue Datei hochladen', 'medialab-core' ); ?></h3>

                    <p class="description" style="margin-bottom:16px;">
                        <?php esc_html_e(
                            'Die bestehende Datei wird überschrieben. Attachment-ID, URL und alle Verwendungen im Content bleiben erhalten.',
                            'medialab-core'
                        ); ?>
                    </p>

                    <div style="margin-bottom:16px;">
                        <input type="file" name="medialab_replacement_file" id="medialab_replacement_file"
                               style="display:block;margin-bottom:8px;">
                        <p class="description">
                            <?php printf(
                                esc_html__( 'Aktueller Dateityp: %s', 'medialab-core' ),
                                '<code>' . esc_html( $mime ) . '</code>'
                            ); ?>
                        </p>
                    </div>

                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;cursor:pointer;">
                        <input type="checkbox" name="medialab_keep_filename" value="1" checked>
                        <?php esc_html_e( 'Originalen Dateinamen beibehalten', 'medialab-core' ); ?>
                    </label>

                    <p>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Datei ersetzen', 'medialab-core' ); ?>
                        </button>
                        <a href="<?php echo esc_url( get_edit_post_link( $attachment_id ) ); ?>"
                           class="button" style="margin-left:8px;">
                            <?php esc_html_e( 'Abbrechen', 'medialab-core' ); ?>
                        </a>
                    </p>
                </form>

            </div>
        </div>
        <?php
    }

    // ─── Upload verarbeiten ───────────────────────────────────────────────────

    public function handle_upload(): void {
        // Berechtigungen + Nonce
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( __( 'Keine Berechtigung.', 'medialab-core' ) );
        }

        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id ) {
            wp_die( __( 'Ungültige Attachment-ID.', 'medialab-core' ) );
        }

        check_admin_referer( 'medialab_replace_' . $attachment_id, 'medialab_replace_nonce' );

        // Datei vorhanden?
        if ( empty( $_FILES['medialab_replacement_file']['name'] ) ) {
            $this->redirect_back( $attachment_id, __( 'Keine Datei ausgewählt.', 'medialab-core' ) );
        }

        $file     = $_FILES['medialab_replacement_file'];
        $old_file = get_attached_file( $attachment_id );
        $old_mime = get_post_mime_type( $attachment_id );

        // Upload mit WordPress-API
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $overrides = array(
            'test_form' => false,
            'test_size' => true,
        );

        $uploaded = wp_handle_upload( $file, $overrides );

        if ( isset( $uploaded['error'] ) ) {
            $this->redirect_back( $attachment_id, $uploaded['error'] );
        }

        $new_file = $uploaded['file'];
        $new_mime = $uploaded['type'];

        // Dateinamen beibehalten? → neue Datei umbenennen
        $keep_filename = ! empty( $_POST['medialab_keep_filename'] );

        if ( $keep_filename && $old_file ) {
            $target = $old_file;

            // Alte Datei löschen
            if ( file_exists( $target ) ) {
                @unlink( $target );
            }

            // Neue Datei verschieben
            if ( ! rename( $new_file, $target ) ) {
                @unlink( $new_file );
                $this->redirect_back( $attachment_id, __( 'Datei konnte nicht verschoben werden.', 'medialab-core' ) );
            }

            $new_file = $target;
        }

        // Attachment-Metadaten aktualisieren
        update_attached_file( $attachment_id, $new_file );

        // MIME-Typ aktualisieren falls geändert
        if ( $new_mime !== $old_mime ) {
            wp_update_post( array(
                'ID'             => $attachment_id,
                'post_mime_type' => $new_mime,
            ) );
        }

        // Alle alten Thumbnails löschen
        $this->delete_old_thumbnails( $attachment_id );

        // Metadaten + Thumbnails neu generieren
        $meta = wp_generate_attachment_metadata( $attachment_id, $new_file );
        wp_update_attachment_metadata( $attachment_id, $meta );

        // Cache leeren
        clean_attachment_cache( $attachment_id );

        // Activity Log (falls vorhanden)
        if ( function_exists( 'medialab_log_activity' ) ) {
            medialab_log_activity( 'media_replaced', array(
                'attachment_id' => $attachment_id,
                'old_file'      => basename( $old_file ),
                'new_file'      => basename( $new_file ),
            ) );
        }

        $this->redirect_back( $attachment_id, null, true );
    }

    // ─── Alte Thumbnails löschen ──────────────────────────────────────────────

    private function delete_old_thumbnails( int $attachment_id ): void {
        $meta = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $meta['sizes'] ) ) return;

        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit( $upload_dir['basedir'] );
        $file       = get_attached_file( $attachment_id );
        $file_dir   = trailingslashit( dirname( $file ) );

        foreach ( $meta['sizes'] as $size => $size_data ) {
            $thumb = $file_dir . $size_data['file'];
            if ( file_exists( $thumb ) ) {
                @unlink( $thumb );
            }
        }
    }

    // ─── Redirect ─────────────────────────────────────────────────────────────

    private function redirect_back( int $attachment_id, ?string $error = null, bool $success = false ): void {
        $base = admin_url( 'admin.php?page=medialab-replace-media&attachment_id=' . $attachment_id );

        if ( $success ) {
            wp_safe_redirect( $base . '&replaced=1' );
        } else {
            wp_safe_redirect( $base . '&error=' . rawurlencode( $error ?? __( 'Unbekannter Fehler.', 'medialab-core' ) ) );
        }
        exit;
    }

    // ─── Helper: Replace-URL ──────────────────────────────────────────────────

    private function replace_url( int $attachment_id ): string {
        return admin_url( 'admin.php?page=medialab-replace-media&attachment_id=' . $attachment_id );
    }
}

new MediaLab_Media_Replace();
