<?php get_header(); ?>

    <div class="container">
        <?php
        if (have_posts()) :
            while (have_posts()) : the_post();
                ?>
                <article <?php post_class(); ?>>
                    <header class="entry-header">
                        <?php the_title('<h1 class="sr-only">', '</h1>'); ?>
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

<?php get_footer(); ?>