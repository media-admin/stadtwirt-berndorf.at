<?php
/**
 * Template Name: Speisekarte
 *
 * Zeigt alle Gerichte gruppiert nach Haupt- und Unterkategorie.
 * Desktop: 2-spaltig | Mobile: 1-spaltig
 *
 * @package Stadtwirt_Theme
 */

get_header();

// Allergen-Kürzel aus ACF Field Object holen (einmalig)
$allergen_choices = [];
if (function_exists('get_field_object')) {
    $sample = get_posts(['post_type' => 'gericht', 'posts_per_page' => 1, 'fields' => 'ids']);
    if (!empty($sample)) {
        $field_obj = get_field_object('allergene', $sample[0]);
        if (!empty($field_obj['choices'])) {
            $allergen_choices = $field_obj['choices'];
        }
    }
}

// Hilfsfunktion: Kategorien sortiert laden
// Nutzt medialab_get_terms_ordered() aus post-order.php (term_meta 'term_order')
function speisekarte_get_kats(int $parent = 0): array {
    if (function_exists('medialab_get_terms_ordered')) {
        return medialab_get_terms_ordered('gericht_kategorie', ['parent' => $parent]);
    }
    $terms = get_terms([
        'taxonomy'   => 'gericht_kategorie',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'parent'     => $parent,
    ]);
    return is_wp_error($terms) ? [] : $terms;
}

// Gerichte-Grid ausgeben
function speisekarte_render_grid(int $term_id, array $allergen_choices): void {
    $gerichte = new WP_Query([
        'post_type'      => 'gericht',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
        'tax_query'      => [[
            'taxonomy'         => 'gericht_kategorie',
            'field'            => 'term_id',
            'terms'            => $term_id,
            'include_children' => false,
        ]],
    ]);

    if (!$gerichte->have_posts()) {
        wp_reset_postdata();
        return;
    }
    ?>
    <div class="speisekarte__grid">
        <?php while ($gerichte->have_posts()) : $gerichte->the_post();
            $post_id         = get_the_ID();
            $preis           = get_field('preis', $post_id);
            $aktion          = get_field('aktion', $post_id);
            $aktionspreis    = $aktion ? get_field('aktionspreis', $post_id) : null;
            $zutatenliste    = get_field('zutatenliste', $post_id);
            $allergene       = get_field('allergene', $post_id);
            $portionsgroesse = get_field('portionsgroesse', $post_id);
            $zusatzinfo      = get_field('zusatzinfo', $post_id);
            $kennzeichnungen = get_the_terms($post_id, 'kennzeichnung');
        ?>
            <article class="gericht<?php echo $aktion ? ' gericht--aktion' : ''; ?>">

                <?php if (has_post_thumbnail()) : ?>
                    <div class="gericht__image">
                        <?php the_post_thumbnail('medium', ['loading' => 'lazy', 'alt' => get_the_title()]); ?>
                        <?php if ($aktion) : ?>
                            <span class="gericht__aktion-badge">Aktionsangebot</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="gericht__body">

                    <header class="gericht__header">
                        <h4 class="gericht__name"><?php the_title(); ?></h4>
                        <?php if ($portionsgroesse) : ?>
                            <span class="gericht__portion"><?php echo esc_html($portionsgroesse); ?></span>
                        <?php endif; ?>
                        <div class="gericht__preis-wrap">
                            <?php if ($aktion && $aktionspreis) : ?>
                                <span class="gericht__preis gericht__preis--original">
                                    € <?php echo number_format((float)$preis, 2, ',', '.'); ?>
                                </span>
                                <span class="gericht__preis gericht__preis--aktion">
                                    € <?php echo number_format((float)$aktionspreis, 2, ',', '.'); ?>
                                </span>
                            <?php elseif ($preis) : ?>
                                <span class="gericht__preis">
                                    € <?php echo number_format((float)$preis, 2, ',', '.'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <?php if (get_the_content()) : ?>
                        <div class="gericht__beschreibung"><?php the_content(); ?></div>
                    <?php endif; ?>

                    <?php if ($zutatenliste) : ?>
                        <p class="gericht__zutaten"><?php echo esc_html($zutatenliste); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($kennzeichnungen) && !is_wp_error($kennzeichnungen)) : ?>
                        <ul class="gericht__kennzeichnungen" aria-label="Kennzeichnungen">
                            <?php foreach ($kennzeichnungen as $k) :
                                $icon         = get_field('icon', 'kennzeichnung_' . $k->term_id);
                                $farbe        = get_field('farbe', 'kennzeichnung_' . $k->term_id);
                                $beschreibung = get_field('beschreibung', 'kennzeichnung_' . $k->term_id);
                            ?>
                                <li class="gericht__kennzeichnung"
                                    <?php if ($farbe) : ?>style="--k-farbe: <?php echo esc_attr($farbe); ?>"<?php endif; ?>
                                    title="<?php echo esc_attr($beschreibung ?: $k->name); ?>">
                                    <?php if (!empty($icon['url'])) : ?>
                                        <img src="<?php echo esc_url($icon['url']); ?>"
                                             alt="<?php echo esc_attr($k->name); ?>"
                                             width="20" height="20" loading="lazy">
                                    <?php else : ?>
                                        <span class="gericht__kennzeichnung-label"><?php echo esc_html($k->name); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($allergene)) : ?>
                        <div class="gericht__allergene">
                            <span class="gericht__allergene-label">Allergene:</span>
                            <?php foreach ($allergene as $key) :
                                $title = $allergen_choices[$key] ?? $key;
                            ?>
                                <span class="gericht__allergen" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($key); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </article>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
}

$hauptkategorien = speisekarte_get_kats(0);

?>
<main class="speisekarte">
    <div class="container">

        <?php if (have_posts()) : the_post(); ?>
            <?php if (get_the_title()) : ?>
                <h1 class="speisekarte__title"><?php the_title(); ?></h1>
            <?php endif; ?>
            <?php if (get_the_content()) : ?>
                <div class="speisekarte__intro"><?php the_content(); ?></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($hauptkategorien)) : ?>

            <nav class="speisekarte__nav" aria-label="Kategorien">
                <?php foreach ($hauptkategorien as $hauptkat) :
                    $unterkats_nav = speisekarte_get_kats((int) $hauptkat->term_id);
                ?>
                    <div class="speisekarte__nav-group">
                        <a href="#kat-<?php echo esc_attr($hauptkat->slug); ?>" class="speisekarte__nav-link speisekarte__nav-link--haupt">
                            <?php echo esc_html($hauptkat->name); ?>
                        </a>
                        <?php if (!empty($unterkats_nav)) : ?>
                            <div class="speisekarte__nav-sub">
                                <?php foreach ($unterkats_nav as $uk) : ?>
                                    <a href="#kat-<?php echo esc_attr($uk->slug); ?>" class="speisekarte__nav-link speisekarte__nav-link--sub">
                                        <?php echo esc_html($uk->name); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </nav>

            <?php foreach ($hauptkategorien as $hauptkat) :
                $unterkategorien = speisekarte_get_kats((int) $hauptkat->term_id);
                $hat_unterkats   = !empty($unterkategorien);
            ?>
                <section class="speisekarte__kategorie" id="kat-<?php echo esc_attr($hauptkat->slug); ?>">
                    <h2 class="speisekarte__kategorie-title"><?php echo esc_html($hauptkat->name); ?></h2>

                    <?php if ($hauptkat->description) : ?>
                        <p class="speisekarte__kategorie-desc"><?php echo esc_html($hauptkat->description); ?></p>
                    <?php endif; ?>

                    <?php if ($hat_unterkats) : ?>
                        <?php // speisekarte_render_grid() hier NICHT aufrufen — Gerichte erscheinen in Unterkategorien ?>
                        <?php foreach ($unterkategorien as $unterkat) : ?>
                            <div class="speisekarte__unterkategorie" id="kat-<?php echo esc_attr($unterkat->slug); ?>">
                                <h3 class="speisekarte__unterkategorie-title"><?php echo esc_html($unterkat->name); ?></h3>
                                <?php if ($unterkat->description) : ?>
                                    <p class="speisekarte__kategorie-desc"><?php echo esc_html($unterkat->description); ?></p>
                                <?php endif; ?>
                                <?php speisekarte_render_grid((int) $unterkat->term_id, $allergen_choices); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?php speisekarte_render_grid((int) $hauptkat->term_id, $allergen_choices); ?>
                    <?php endif; ?>

                </section>
            <?php endforeach; ?>

        <?php else : ?>
            <p class="speisekarte__empty">Die Speisekarte wird gerade aktualisiert.</p>
        <?php endif; ?>

    </div>
</main>

<?php get_footer(); ?>
