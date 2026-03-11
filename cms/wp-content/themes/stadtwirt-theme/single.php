<?php
/**
 * Single Post Template
 *
 * @package custom-theme
 */

get_header();
get_template_part( 'template-parts/components/breadcrumbs' );
?>

<main id="primary" class="site-main">
<div class="container">
<div class="single-post-layout">

<?php while ( have_posts() ) : the_post(); ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post' ); ?>>

        <?php /* ── Header ────────────────────────────────────────────────── */ ?>
        <header class="single-post__header">

            <?php /* Kategorien */ ?>
            <?php
            $cats = get_the_category();
            if ( $cats ) :
            ?>
            <div class="single-post__categories">
                <?php foreach ( $cats as $cat ) : ?>
                <a class="single-post__category" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">
                    <?php echo esc_html( $cat->name ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php /* Titel – nur wenn kein Hero-Bild aktiv */ ?>
            <?php
            $hero        = function_exists( 'media_lab_get_hero_image' ) ? media_lab_get_hero_image() : null;
            $has_hero    = ! empty( $hero );
            ?>
            <?php if ( ! $has_hero ) : ?>
            <h1 class="single-post__title"><?php the_title(); ?></h1>
            <?php endif; ?>

            <?php /* Meta-Zeile */ ?>
            <div class="single-post__meta">
                <?php /* Avatar + Autor */ ?>
                <div class="single-post__author-info">
                    <?php echo get_avatar( get_the_author_meta( 'ID' ), 36, '', get_the_author(), [ 'class' => 'single-post__avatar' ] ); ?>
                    <a class="single-post__author-name"
                       href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
                        <?php the_author(); ?>
                    </a>
                </div>

                <span class="single-post__meta-sep" aria-hidden="true">·</span>

                <time class="single-post__date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                    <?php the_date(); ?>
                </time>

                <?php if ( get_the_modified_date() !== get_the_date() ) : ?>
                <span class="single-post__meta-sep" aria-hidden="true">·</span>
                <span class="single-post__updated">
                    <?php printf( esc_html__( 'Aktualisiert: %s', 'custom-theme' ), get_the_modified_date() ); ?>
                </span>
                <?php endif; ?>

                <?php /* Lesezeit */ ?>
                <?php
                $word_count  = str_word_count( wp_strip_all_tags( get_the_content() ) );
                $read_time   = max( 1, (int) round( $word_count / 200 ) );
                ?>
                <span class="single-post__meta-sep" aria-hidden="true">·</span>
                <span class="single-post__read-time">
                    <?php printf( esc_html__( '%d Min. Lesezeit', 'custom-theme' ), $read_time ); ?>
                </span>
            </div>

        </header>

        <?php /* ── Featured Image (nur ohne Hero) ────────────────────────── */ ?>
        <?php if ( ! $has_hero && has_post_thumbnail() ) : ?>
        <div class="single-post__featured-image">
            <?php the_post_thumbnail( 'large', [
                'class'          => 'single-post__featured-img',
                'loading'        => 'eager',
                'fetchpriority'  => 'high',
            ] ); ?>
            <?php
            $caption = get_the_post_thumbnail_caption();
            if ( $caption ) :
            ?>
            <figcaption class="single-post__featured-caption"><?php echo esc_html( $caption ); ?></figcaption>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php /* ── Inhalt ───────────────────────────────────────────────── */ ?>
        <div class="single-post__content entry-content">
            <?php
            the_content( sprintf(
                '<span class="screen-reader-text">%s</span>',
                esc_html__( 'Weiterlesen', 'custom-theme' )
            ) );

            wp_link_pages( [
                'before' => '<div class="page-links">' . esc_html__( 'Seiten:', 'custom-theme' ),
                'after'  => '</div>',
            ] );
            ?>
        </div>

        <?php /* ── Tags ─────────────────────────────────────────────────── */ ?>
        <?php
        $tags = get_the_tags();
        if ( $tags ) :
        ?>
        <footer class="single-post__tags">
            <span class="single-post__tags-label"><?php esc_html_e( 'Tags:', 'custom-theme' ); ?></span>
            <?php foreach ( $tags as $tag ) : ?>
            <a class="single-post__tag" href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">
                <?php echo esc_html( $tag->name ); ?>
            </a>
            <?php endforeach; ?>
        </footer>
        <?php endif; ?>

    </article>

    <?php /* ── Autor-Box ───────────────────────────────────────────────────── */ ?>
    <?php
    $author_id   = get_the_author_meta( 'ID' );
    $author_bio  = get_the_author_meta( 'description' );
    if ( $author_bio ) :
    ?>
    <aside class="author-box">
        <?php echo get_avatar( $author_id, 80, '', get_the_author(), [ 'class' => 'author-box__avatar' ] ); ?>
        <div class="author-box__content">
            <p class="author-box__label"><?php esc_html_e( 'Über den Autor', 'custom-theme' ); ?></p>
            <p class="author-box__name">
                <a href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>">
                    <?php the_author(); ?>
                </a>
            </p>
            <p class="author-box__bio"><?php echo esc_html( $author_bio ); ?></p>
        </div>
    </aside>
    <?php endif; ?>

    <?php /* ── Post-Navigation ─────────────────────────────────────────────── */ ?>
    <?php
    $prev = get_previous_post();
    $next = get_next_post();

    if ( $prev || $next ) :
    ?>
    <nav class="post-nav" aria-label="<?php esc_attr_e( 'Beitragsnavigation', 'custom-theme' ); ?>">
        <?php if ( $prev ) : ?>
        <a class="post-nav__item post-nav__item--prev" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
            <span class="post-nav__direction">← <?php esc_html_e( 'Vorheriger Beitrag', 'custom-theme' ); ?></span>
            <span class="post-nav__title"><?php echo esc_html( get_the_title( $prev ) ); ?></span>
        </a>
        <?php endif; ?>
        <?php if ( $next ) : ?>
        <a class="post-nav__item post-nav__item--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
            <span class="post-nav__direction"><?php esc_html_e( 'Nächster Beitrag', 'custom-theme' ); ?> →</span>
            <span class="post-nav__title"><?php echo esc_html( get_the_title( $next ) ); ?></span>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php /* ── Kommentare ──────────────────────────────────────────────────── */ ?>
    <?php if ( comments_open() || get_comments_number() ) : ?>
    <div class="single-post__comments">
        <?php comments_template(); ?>
    </div>
    <?php endif; ?>

<?php endwhile; ?>

</div><!-- .single-post-layout -->
</div><!-- .container -->
</main>

<?php get_footer(); ?>
