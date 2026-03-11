<?php
/**
 * Template Part: Post Card
 *
 * Wiederverwendbare Post-Card für archive.php, search.php, Widgets u.a.
 *
 * Verwendung:
 *   // Im Loop (global $post gesetzt):
 *   get_template_part('template-parts/components/post-card');
 *
 *   // Mit explizitem Post-Objekt:
 *   set_query_var('post_card_post', $post_object);
 *   get_template_part('template-parts/components/post-card');
 *
 *   // Variante:
 *   set_query_var('post_card_variant', 'horizontal'); // default: 'default'
 *   get_template_part('template-parts/components/post-card');
 *
 * @package custom-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Post-Objekt holen (aus query_var oder globalem Loop)
$card_post    = get_query_var( 'post_card_post', null );
$card_variant = get_query_var( 'post_card_variant', 'default' );

// Query-Vars zurücksetzen
set_query_var( 'post_card_post', null );
set_query_var( 'post_card_variant', 'default' );

if ( $card_post ) {
    $post_id    = $card_post->ID;
    $title      = get_the_title( $card_post );
    $permalink  = get_permalink( $card_post );
    $excerpt    = get_the_excerpt( $card_post );
    $date       = get_the_date( '', $card_post );
    $date_iso   = get_the_date( 'c', $card_post );
    $author     = get_the_author_meta( 'display_name', $card_post->post_author );
    $thumb_id   = get_post_thumbnail_id( $card_post );
} else {
    $post_id    = get_the_ID();
    $title      = get_the_title();
    $permalink  = get_permalink();
    $excerpt    = get_the_excerpt();
    $date       = get_the_date();
    $date_iso   = get_the_date( 'c' );
    $author     = get_the_author();
    $thumb_id   = get_post_thumbnail_id();
}

// Kategorie (erste)
$categories = get_the_category( $post_id );
$category   = ! empty( $categories ) ? $categories[0] : null;

// CSS-Klassen
$card_classes = [ 'post-card' ];
if ( $card_variant !== 'default' ) {
    $card_classes[] = 'post-card--' . esc_attr( $card_variant );
}
?>

<article class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>">

    <?php /* ── Bild ────────────────────────────────────────────────────────── */ ?>
    <?php if ( $thumb_id ) : ?>
    <a class="post-card__thumbnail" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
        <?php echo wp_get_attachment_image( $thumb_id, 'medium_large', false, [
            'class'   => 'post-card__img',
            'loading' => 'lazy',
            'alt'     => esc_attr( $title ),
        ] ); ?>
    </a>
    <?php endif; ?>

    <?php /* ── Inhalt ──────────────────────────────────────────────────────── */ ?>
    <div class="post-card__content">

        <?php /* Kategorie-Badge */ ?>
        <?php if ( $category ) : ?>
        <div class="post-card__category">
            <a class="post-card__category-link" href="<?php echo esc_url( get_category_link( $category->term_id ) ); ?>">
                <?php echo esc_html( $category->name ); ?>
            </a>
        </div>
        <?php endif; ?>

        <?php /* Titel */ ?>
        <h3 class="post-card__title">
            <a class="post-card__title-link" href="<?php echo esc_url( $permalink ); ?>">
                <?php echo esc_html( $title ); ?>
            </a>
        </h3>

        <?php /* Excerpt */ ?>
        <?php if ( $excerpt ) : ?>
        <p class="post-card__excerpt">
            <?php echo esc_html( wp_trim_words( $excerpt, 18, '…' ) ); ?>
        </p>
        <?php endif; ?>

        <?php /* Meta + Link */ ?>
        <footer class="post-card__footer">
            <div class="post-card__meta">
                <time class="post-card__date" datetime="<?php echo esc_attr( $date_iso ); ?>">
                    <?php echo esc_html( $date ); ?>
                </time>
                <?php if ( $author ) : ?>
                <span class="post-card__meta-sep" aria-hidden="true">·</span>
                <span class="post-card__author"><?php echo esc_html( $author ); ?></span>
                <?php endif; ?>
            </div>
            <a class="post-card__link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s lesen', 'custom-theme' ), $title ) ); ?>">
                <?php esc_html_e( 'Lesen', 'custom-theme' ); ?> →
            </a>
        </footer>

    </div>

</article>
