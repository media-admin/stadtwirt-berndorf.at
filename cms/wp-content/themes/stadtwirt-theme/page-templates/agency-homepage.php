<?php
/**
 * Template Name: Agency Homepage 2
 * 
 * @package Custom_Theme
 */

get_header();
?>

<main class="agency-homepage">
    <?php while (have_posts()) : the_post(); ?>
        
        <div class="container">
            <?php the_content(); ?>
        </div>
        
    <?php endwhile; ?>
</main>

<?php
get_footer();