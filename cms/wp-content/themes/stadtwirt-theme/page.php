<?php get_header(); ?>
<?php get_template_part('template-parts/hero-image'); ?>

<main id="primary" class="site-main">
    <div class="container">
        <?php
        while (have_posts()) : the_post();
            ?>
            <article <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title('<h1>', '</h1>'); ?>
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