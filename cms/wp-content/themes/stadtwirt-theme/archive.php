<?php
/**
 * Archive Template
 *
 * Verwendet für: Kategorie, Tag, Autor, Datum, Custom Post Type Archive
 *
 * @package custom-theme
 */

get_header();
get_template_part( 'template-parts/components/breadcrumbs' );
?>

<main id="primary" class="site-main">
<div class="archive-layout container">

    <?php /* ── Archive-Header ─────────────────────────────────────────────── */ ?>
    <header class="archive-header">
        <?php
        $archive_title       = get_the_archive_title();
        $archive_description = get_the_archive_description();

        // Präfix ("Kategorie:", "Schlagwort:" etc.) als Badge abtrennen
        preg_match( '/^<span[^>]*>(.*?)<\/span>\s*(.*)$/s', $archive_title, $m );
        $badge = ! empty( $m[1] ) ? $m[1] : '';
        $label = ! empty( $m[2] ) ? $m[2] : wp_strip_all_tags( $archive_title );
        ?>

        <?php if ( $badge ) : ?>
        <span class="archive-header__badge"><?php echo esc_html( $badge ); ?></span>
        <?php endif; ?>

        <h1 class="archive-header__title"><?php echo esc_html( $label ); ?></h1>

        <?php if ( $archive_description ) : ?>
        <div class="archive-header__description">
            <?php echo wp_kses_post( $archive_description ); ?>
        </div>
        <?php endif; ?>

        <?php /* Ergebnis-Zähler */ ?>
        <?php global $wp_query; ?>
        <p class="archive-header__count">
            <?php printf(
                esc_html( _n( '%s Beitrag', '%s Beiträge', $wp_query->found_posts, 'custom-theme' ) ),
                number_format_i18n( $wp_query->found_posts )
            ); ?>
        </p>
    </header>

    <?php /* ── Beitrags-Grid ───────────────────────────────────────────────── */ ?>
    <?php if ( have_posts() ) : ?>

    <div class="post-grid">
        <?php while ( have_posts() ) : the_post(); ?>
            <?php get_template_part( 'template-parts/components/post-card' ); ?>
        <?php endwhile; ?>
    </div>

    <?php /* ── Pagination ──────────────────────────────────────────────────── */ ?>
    <?php
    $pagination = paginate_links( [
        'prev_text' => '← ' . esc_html__( 'Zurück', 'custom-theme' ),
        'next_text' => esc_html__( 'Weiter', 'custom-theme' ) . ' →',
        'type'      => 'array',
    ] );

    if ( $pagination ) :
    ?>
    <nav class="archive-pagination" aria-label="<?php esc_attr_e( 'Seitennavigation', 'custom-theme' ); ?>">
        <ul class="archive-pagination__list">
            <?php foreach ( $pagination as $page ) : ?>
            <li class="archive-pagination__item">
                <?php echo $page; // phpcs:ignore -- paginate_links() escaped ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php else : ?>

    <?php /* ── Keine Ergebnisse ────────────────────────────────────────────── */ ?>
    <div class="archive-empty">
        <p class="archive-empty__text">
            <?php esc_html_e( 'Zu dieser Auswahl wurden keine Beiträge gefunden.', 'custom-theme' ); ?>
        </p>
        <a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php esc_html_e( 'Zur Startseite', 'custom-theme' ); ?>
        </a>
    </div>

    <?php endif; ?>

</div><!-- .archive-layout -->
</main>

<?php get_footer(); ?>
