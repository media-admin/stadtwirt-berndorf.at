<?php
/**
 * Media Replace
 * Medien-Dateien ersetzen ohne Attachment-ID zu verlieren.
 * Button in Attachment-Detail-Seite + Medien-Listenansicht.
 * Thumbnails werden automatisch neu generiert.
 *
 * @package Media Lab Agency Core
 * @version 1.5.4
 */
if (!defined('ABSPATH')) { exit; }

// Button in Attachment-Edit-Seite
add_filter('attachment_fields_to_edit', function(array $fields, WP_Post $post): array {
    if (!current_user_can('upload_files')) { return $fields; }
    $url = admin_url('media.php?action=medialab_replace&attachment_id=' . $post->ID);
    $fields['medialab_replace'] = [
        'label' => __('Datei ersetzen', 'media-lab-core'),
        'input' => 'html',
        'html'  => '<a href="' . esc_url($url) . '" class="button">' . __('Datei ersetzen', 'media-lab-core') . '</a>',
    ];
    return $fields;
}, 10, 2);

// Replace-Formular-Seite
add_action('admin_action_medialab_replace', function() {
    $attachment_id = isset($_GET['attachment_id']) ? (int)$_GET['attachment_id'] : 0;
    if (!$attachment_id || !current_user_can('upload_files')) { wp_die('Unauthorized'); }
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') { wp_die('Ungültig'); }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Datei ersetzen</title>
        <?php wp_head(); ?>
    </head>
    <body class="wp-admin">
        <div style="max-width:500px;margin:60px auto;padding:2rem;background:#fff;border:1px solid #ddd;border-radius:8px;">
            <h2><?php _e('Datei ersetzen', 'media-lab-core'); ?></h2>
            <p><strong><?php echo esc_html(get_the_title($attachment_id)); ?></strong></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('medialab_do_replace_' . $attachment_id); ?>
                <input type="hidden" name="action" value="medialab_do_replace">
                <input type="hidden" name="attachment_id" value="<?php echo esc_attr($attachment_id); ?>">
                <input type="file" name="replacement_file" style="margin:1rem 0;display:block;" required>
                <?php submit_button('Datei ersetzen'); ?>
            </form>
            <a href="<?php echo esc_url(admin_url('upload.php')); ?>">&larr; Zurück zur Mediathek</a>
        </div>
    </body>
    </html>
    <?php
    exit;
});

// Replace-Handler
add_action('admin_post_medialab_do_replace', function() {
    $attachment_id = isset($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;
    if (!$attachment_id || !check_admin_referer('medialab_do_replace_' . $attachment_id)) {
        wp_die('Fehler');
    }
    if (!current_user_can('upload_files')) { wp_die('Unauthorized'); }

    if (empty($_FILES['replacement_file']['tmp_name'])) {
        wp_die('Keine Datei hochgeladen.');
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $old_file = get_attached_file($attachment_id);

    // Alten Dateinamen beibehalten
    $upload_dir = wp_upload_dir();
    $new_name   = basename($old_file);
    $_FILES['replacement_file']['name'] = $new_name;

    $overrides = ['test_form' => false, 'unique_filename_callback' => function() use ($new_name) { return $new_name; }];
    $uploaded  = wp_handle_upload($_FILES['replacement_file'], $overrides);

    if (isset($uploaded['error'])) {
        wp_die(esc_html($uploaded['error']));
    }

    // Altes Original löschen und durch neue Datei ersetzen
    @unlink($old_file);
    @rename($uploaded['file'], $old_file);

    // Attachment-Meta aktualisieren
    update_attached_file($attachment_id, $old_file);
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $old_file));

    wp_safe_redirect(admin_url('post.php?post=' . $attachment_id . '&action=edit&replaced=1'));
    exit;
});

add_action('admin_notices', function() {
    if (isset($_GET['replaced']) && $_GET['replaced'] === '1') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Datei erfolgreich ersetzt.', 'media-lab-core') . '</p></div>';
    }
});
