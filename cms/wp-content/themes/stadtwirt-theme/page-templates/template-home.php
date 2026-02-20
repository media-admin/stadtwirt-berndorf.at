<?php
/**
 * Template Name: Homepage
 *
 * @package CustomTheme
 */

get_header();
?>

<main id="primary" class="site-main">
    
    <?php
    // Hero Section
    get_template_part('template-parts/sections/hero-slider-cpt.php');
    ?>
    
    <div class="container">
        <?php
        if (have_posts()) :
            while (have_posts()) : the_post();
                ?>
                <article <?php post_class(); ?>>
                    <header class="entry-header">
                        <?php the_title('<h1>', '</h1>'); ?>
                    </header>
                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>
                </article>
                <?php
            endwhile;
        else :
            echo '<p>Keine Inhalte gefunden.</p>';
        endif;
        ?>
    </div>
   
    
</main>

<?php
get_footer();