<?php
/**
 * Template Name: Page Builder 2
 * 
 * Full-width page for custom page builders
 * 
 * @package Custom_Theme
 */

get_header();
?>

<main class="page-builder">
    <?php while (have_posts()) : the_post(); ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <?php the_content(); ?>
        </article>
        
    <?php endwhile; ?>
</main>

<?php
get_footer();