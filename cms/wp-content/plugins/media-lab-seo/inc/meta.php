<?php
/**
 * SEO Meta Box
 *
 * Felder pro Post/Page/CPT:
 *   - Meta Title (mit Zeichenzähler + Google-Vorschau)
 *   - Meta Description (mit Zeichenzähler + Google-Vorschau)
 *   - Fokus-Keyword
 *   - Canonical URL
 *   - Robots (index/noindex, follow/nofollow)
 *   - OG Image (überschreibt globales Default)
 *   - noindex Checkbox schnell erreichbar
 *
 * Zentrale Helper-Funktionen für OG, Twitter, Schema:
 *   medialab_seo_get_title()
 *   medialab_seo_get_description()
 *   medialab_seo_get_og_image()
 *   medialab_seo_get_robots()
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────
// POST TYPES die eine SEO-Box bekommen
// ─────────────────────────────────────────────────────────────────
function medialab_seo_get_post_types() {
    return array_merge(
        array('post', 'page'),
        array('hero_slide', 'team', 'project', 'testimonial',
              'faq', 'gmap', 'carousel', 'service', 'event', 'job', 'notification')
    );
}

// ─────────────────────────────────────────────────────────────────
// META BOX REGISTRIEREN
// ─────────────────────────────────────────────────────────────────
add_action('add_meta_boxes', function () {
    foreach (medialab_seo_get_post_types() as $post_type) {
        add_meta_box(
            'medialab_seo_meta',
            '🔍 SEO',
            'medialab_seo_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
});

// ─────────────────────────────────────────────────────────────────
// META BOX RENDERN
// ─────────────────────────────────────────────────────────────────
function medialab_seo_render_meta_box($post) {
    wp_nonce_field('medialab_seo_meta_save', 'medialab_seo_nonce');

    $title       = get_post_meta($post->ID, '_medialab_seo_title',       true);
    $description = get_post_meta($post->ID, '_medialab_seo_description', true);
    $keyword     = get_post_meta($post->ID, '_medialab_seo_keyword',     true);
    $canonical   = get_post_meta($post->ID, '_medialab_seo_canonical',   true);
    $robots_idx  = get_post_meta($post->ID, '_medialab_seo_noindex',     true);
    $robots_flw  = get_post_meta($post->ID, '_medialab_seo_nofollow',    true);
    $og_image    = get_post_meta($post->ID, '_medialab_seo_og_image',    true);

    $preview_title = $title ?: get_the_title($post->ID);
    $preview_url   = get_permalink($post->ID) ?: home_url('/' . $post->post_name);
    $preview_desc  = $description ?: wp_trim_words(wp_strip_all_tags($post->post_content), 25, '…');
    ?>
    <div class="mlseo-wrap">

        <!-- Google Snippet Vorschau -->
        <div class="mlseo-preview">
            <div class="mlseo-preview__label">Google-Vorschau</div>
            <div class="mlseo-preview__box">
                <div class="mlseo-preview__title"  id="mlseo-prev-title"><?php echo esc_html($preview_title); ?></div>
                <div class="mlseo-preview__url"    id="mlseo-prev-url"><?php echo esc_url($preview_url); ?></div>
                <div class="mlseo-preview__desc"   id="mlseo-prev-desc"><?php echo esc_html($preview_desc); ?></div>
            </div>
        </div>

        <div class="mlseo-fields">

            <!-- Meta Title -->
            <div class="mlseo-field">
                <label for="mlseo_title">
                    Meta Title
                    <span class="mlseo-counter" id="mlseo-title-count">
                        <span id="mlseo-title-num"><?php echo mb_strlen($title); ?></span> / 60
                    </span>
                </label>
                <input type="text"
                       id="mlseo_title"
                       name="mlseo_title"
                       value="<?php echo esc_attr($title); ?>"
                       placeholder="<?php echo esc_attr(get_the_title($post->ID)); ?>"
                       class="mlseo-input"
                       maxlength="120">
                <p class="mlseo-hint">Leer lassen = Post-Titel wird verwendet. Empfohlen: 50–60 Zeichen.</p>
            </div>

            <!-- Meta Description -->
            <div class="mlseo-field">
                <label for="mlseo_description">
                    Meta Description
                    <span class="mlseo-counter" id="mlseo-desc-count">
                        <span id="mlseo-desc-num"><?php echo mb_strlen($description); ?></span> / 160
                    </span>
                </label>
                <textarea id="mlseo_description"
                          name="mlseo_description"
                          class="mlseo-textarea"
                          rows="3"
                          maxlength="320"
                          placeholder="Kurze Beschreibung dieser Seite..."><?php echo esc_textarea($description); ?></textarea>
                <p class="mlseo-hint">Empfohlen: 120–160 Zeichen.</p>
            </div>

            <!-- Fokus-Keyword -->
            <div class="mlseo-field mlseo-field--half">
                <label for="mlseo_keyword">Fokus-Keyword</label>
                <input type="text"
                       id="mlseo_keyword"
                       name="mlseo_keyword"
                       value="<?php echo esc_attr($keyword); ?>"
                       placeholder="z.B. Webdesign Wien"
                       class="mlseo-input">
                <p class="mlseo-hint">Wichtigstes Keyword dieser Seite.</p>
            </div>

            <!-- Canonical URL -->
            <div class="mlseo-field mlseo-field--half">
                <label for="mlseo_canonical">Canonical URL</label>
                <input type="url"
                       id="mlseo_canonical"
                       name="mlseo_canonical"
                       value="<?php echo esc_attr($canonical); ?>"
                       placeholder="<?php echo esc_url($preview_url); ?>"
                       class="mlseo-input">
                <p class="mlseo-hint">Leer lassen = aktuelle URL wird verwendet.</p>
            </div>

            <!-- OG Image -->
            <div class="mlseo-field">
                <label>OG Image (Social Media Vorschaubild)</label>
                <div class="mlseo-image-picker">
                    <div class="mlseo-image-preview" id="mlseo-og-preview">
                        <?php if ($og_image) : ?>
                            <img src="<?php echo esc_url(wp_get_attachment_image_url($og_image, 'medium')); ?>" alt="">
                        <?php else : ?>
                            <span class="mlseo-image-placeholder">Kein Bild ausgewählt</span>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" id="mlseo_og_image" name="mlseo_og_image" value="<?php echo esc_attr($og_image); ?>">
                    <div class="mlseo-image-buttons">
                        <button type="button" class="button" id="mlseo-og-select">Bild auswählen</button>
                        <button type="button" class="button mlseo-btn-remove <?php echo !$og_image ? 'hidden' : ''; ?>" id="mlseo-og-remove">Entfernen</button>
                    </div>
                </div>
                <p class="mlseo-hint">Empfohlen: 1200×630px. Überschreibt das globale Standard-Bild.</p>
            </div>

            <!-- Robots -->
            <div class="mlseo-field">
                <label>Suchmaschinen</label>
                <div class="mlseo-checkboxes">
                    <label class="mlseo-check <?php echo $robots_idx ? 'mlseo-check--warning' : ''; ?>">
                        <input type="checkbox"
                               name="mlseo_noindex"
                               id="mlseo_noindex"
                               value="1"
                               <?php checked($robots_idx, '1'); ?>>
                        <span>noindex – Seite aus Suchergebnissen ausschließen</span>
                    </label>
                    <label class="mlseo-check">
                        <input type="checkbox"
                               name="mlseo_nofollow"
                               id="mlseo_nofollow"
                               value="1"
                               <?php checked($robots_flw, '1'); ?>>
                        <span>nofollow – Links auf dieser Seite nicht verfolgen</span>
                    </label>
                </div>
            </div>

        </div><!-- .mlseo-fields -->
    </div><!-- .mlseo-wrap -->
    <?php
}

// ─────────────────────────────────────────────────────────────────
// SPEICHERN
// ─────────────────────────────────────────────────────────────────
add_action('save_post', function ($post_id) {
    if (!isset($_POST['medialab_seo_nonce'])) return;
    if (!wp_verify_nonce($_POST['medialab_seo_nonce'], 'medialab_seo_meta_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = array(
        '_medialab_seo_title'       => sanitize_text_field($_POST['mlseo_title']       ?? ''),
        '_medialab_seo_description' => sanitize_textarea_field($_POST['mlseo_description'] ?? ''),
        '_medialab_seo_keyword'     => sanitize_text_field($_POST['mlseo_keyword']     ?? ''),
        '_medialab_seo_canonical'   => esc_url_raw($_POST['mlseo_canonical']           ?? ''),
        '_medialab_seo_noindex'     => isset($_POST['mlseo_noindex'])  ? '1' : '',
        '_medialab_seo_nofollow'    => isset($_POST['mlseo_nofollow']) ? '1' : '',
        '_medialab_seo_og_image'    => absint($_POST['mlseo_og_image'] ?? 0) ?: '',
    );

    foreach ($fields as $key => $value) {
        if ($value !== '') {
            update_post_meta($post_id, $key, $value);
        } else {
            delete_post_meta($post_id, $key);
        }
    }
});

// ─────────────────────────────────────────────────────────────────
// META TAGS IM <HEAD> AUSGEBEN
// ─────────────────────────────────────────────────────────────────
add_action('wp_head', function () {
    if (!is_singular()) return;

    $post_id = get_the_ID();

    // Meta Description
    $desc = medialab_seo_get_description($post_id);
    if ($desc) {
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }

    // Robots
    $robots = medialab_seo_get_robots($post_id);
    if ($robots && $robots !== 'index, follow') {
        echo '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";
    }

    // Canonical
    $canonical = get_post_meta($post_id, '_medialab_seo_canonical', true) ?: get_permalink($post_id);
    echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";

}, 1);

// Title-Tag überschreiben
add_filter('pre_get_document_title', function ($title) {
    if (!is_singular()) return $title;
    $custom = get_post_meta(get_the_ID(), '_medialab_seo_title', true);
    return $custom ?: $title;
});

// ─────────────────────────────────────────────────────────────────
// ASSETS
// ─────────────────────────────────────────────────────────────────
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, array('post.php', 'post-new.php'))) return;

    wp_enqueue_media();
    wp_enqueue_script(
        'medialab-seo-meta',
        MEDIALAB_SEO_URL . 'assets/js/meta-box.js',
        array('jquery'),
        MEDIALAB_SEO_VERSION,
        true
    );
    wp_enqueue_style(
        'medialab-seo-meta',
        MEDIALAB_SEO_URL . 'assets/css/meta-box.css',
        array(),
        MEDIALAB_SEO_VERSION
    );
});

// ─────────────────────────────────────────────────────────────────
// ZENTRALE HELPER (genutzt von OG, Twitter, Schema)
// ─────────────────────────────────────────────────────────────────

function medialab_seo_get_title($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    return get_post_meta($post_id, '_medialab_seo_title', true) ?: get_the_title($post_id);
}

function medialab_seo_get_description($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $custom = get_post_meta($post_id, '_medialab_seo_description', true);
    if ($custom) return $custom;
    $post = get_post($post_id);
    if (!$post) return '';
    return wp_trim_words(wp_strip_all_tags($post->post_content), 25, '…');
}

function medialab_seo_get_og_image($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $img_id = get_post_meta($post_id, '_medialab_seo_og_image', true);
    if ($img_id) return wp_get_attachment_image_url($img_id, 'large');
    if (has_post_thumbnail($post_id)) return get_the_post_thumbnail_url($post_id, 'large');
    return get_option('medialab_seo_default_image', '');
}

function medialab_seo_get_robots($post_id = null) {
    if (!$post_id) $post_id = get_the_ID();
    $noindex  = get_post_meta($post_id, '_medialab_seo_noindex',  true);
    $nofollow = get_post_meta($post_id, '_medialab_seo_nofollow', true);
    $parts    = array();
    $parts[]  = $noindex  ? 'noindex'  : 'index';
    $parts[]  = $nofollow ? 'nofollow' : 'follow';
    return implode(', ', $parts);
}
