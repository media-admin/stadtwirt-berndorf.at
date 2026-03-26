<?php get_header(); ?>

    <div class="container">
        <?php
        while (have_posts()) : the_post();
            ?>
            <article <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title('<h1 class="sr-only">', '</h1>'); ?>
                    <?php get_template_part('template-parts/hero-image'); ?>
                </header>
                
                <?php if (has_post_thumbnail()) : ?>
                    <div class="post-thumbnail">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>
            <?php
        endwhile;
        ?>
    </div>
</main>

<?php get_footer(); ?>