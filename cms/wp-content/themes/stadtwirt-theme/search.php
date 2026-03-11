<?php
/**
 * Search Results Template
 *
 * @package custom-theme
 */

get_header();
get_template_part( 'template-parts/components/breadcrumbs' );

global $wp_query;
$search_query = get_search_query();
$found_posts  = (int) $wp_query->found_posts;
?>

<main id="primary" class="site-main">
<div class="search-page container">

    <?php /* ── Header ───────────────────────────────────────────────────── */ ?>
    <header class="search-header">

        <?php if ( $search_query ) : ?>
        <h1 class="search-header__title">
            <?php printf(
                esc_html__( 'Suchergebnisse für: „%s"', 'custom-theme' ),
                '<span class="search-header__term">' . esc_html( $search_query ) . '</span>'
            ); ?>
        </h1>
        <?php else : ?>
        <h1 class="search-header__title"><?php esc_html_e( 'Suchergebnisse', 'custom-theme' ); ?></h1>
        <?php endif; ?>

        <?php if ( $found_posts > 0 ) : ?>
        <p class="search-header__count">
            <?php printf(
                esc_html( _n( '%s Ergebnis', '%s Ergebnisse', $found_posts, 'custom-theme' ) ),
                number_format_i18n( $found_posts )
            ); ?>
        </p>
        <?php endif; ?>

        <?php /* Suchformular zum Verfeinern */ ?>
        <div class="search-header__form">
            <?php get_search_form(); ?>
        </div>

    </header>

    <?php /* ── Ergebnisse ──────────────────────────────────────────────── */ ?>
    <?php if ( have_posts() ) : ?>

    <div class="search-results-list">
        <?php while ( have_posts() ) : the_post(); ?>

            <?php
            // Post-Type Label
            $post_type = get_post_type();
            $pto       = get_post_type_object( $post_type );
            $type_label = $pto ? $pto->labels->singular_name : ucfirst( $post_type );

            // Excerpt (30 Wörter)
            $excerpt = get_the_excerpt();
            $excerpt = $excerpt ?: wp_trim_words( get_the_content(), 30, '…' );
            ?>

            <article class="search-result search-result--<?php echo esc_attr( $post_type ); ?>">

                <?php /* Thumbnail */ ?>
                <?php if ( has_post_thumbnail() ) : ?>
                <a class="search-result__thumbnail" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                    <?php the_post_thumbnail( 'medium', [
                        'class'   => 'search-result__img',
                        'loading' => 'lazy',
                        'alt'     => esc_attr( get_the_title() ),
                    ] ); ?>
                </a>
                <?php endif; ?>

                <?php /* Content */ ?>
                <div class="search-result__content">

                    <div class="search-result__meta">
                        <span class="search-result__type"><?php echo esc_html( $type_label ); ?></span>
                        <span class="search-result__meta-sep" aria-hidden="true">·</span>
                        <time class="search-result__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                            <?php echo esc_html( get_the_date() ); ?>
                        </time>
                    </div>

                    <h2 class="search-result__title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>

                    <?php if ( $excerpt ) : ?>
                    <p class="search-result__excerpt">
                        <?php echo esc_html( wp_trim_words( $excerpt, 30, '…' ) ); ?>
                    </p>
                    <?php endif; ?>

                    <?php /* WooCommerce Preis */ ?>
                    <?php if ( $post_type === 'product' && function_exists( 'wc_get_product' ) ) :
                        $product = wc_get_product( get_the_ID() );
                        if ( $product ) : ?>
                    <div class="search-result__price">
                        <?php echo $product->get_price_html(); // phpcs:ignore ?>
                    </div>
                    <?php endif; endif; ?>

                    <a class="search-result__link" href="<?php the_permalink(); ?>">
                        <?php esc_html_e( 'Mehr erfahren', 'custom-theme' ); ?> →
                    </a>

                </div>

            </article>

        <?php endwhile; ?>
    </div>

    <?php /* ── Pagination ───────────────────────────────────────────────── */ ?>
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
            <li class="archive-pagination__item"><?php echo $page; // phpcs:ignore ?></li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php /* ── Keine Ergebnisse ─────────────────────────────────────────── */ ?>
    <?php else : ?>

    <div class="search-empty">
        <svg class="search-empty__icon" width="64" height="64" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
            <line x1="11" y1="8" x2="11" y2="14"/>
            <line x1="11" y1="16" x2="11.01" y2="16"/>
        </svg>

        <h2 class="search-empty__title"><?php esc_html_e( 'Keine Ergebnisse gefunden', 'custom-theme' ); ?></h2>

        <?php if ( $search_query ) : ?>
        <p class="search-empty__text">
            <?php printf(
                esc_html__( 'Für „%s" wurden keine Inhalte gefunden. Versuche es mit anderen Suchbegriffen.', 'custom-theme' ),
                esc_html( $search_query )
            ); ?>
        </p>
        <?php endif; ?>

        <div class="search-empty__form">
            <?php get_search_form(); ?>
        </div>

        <a class="btn btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php esc_html_e( '← Zur Startseite', 'custom-theme' ); ?>
        </a>
    </div>

    <?php endif; ?>

</div><!-- .search-page -->
</main>

<?php get_footer(); ?>
