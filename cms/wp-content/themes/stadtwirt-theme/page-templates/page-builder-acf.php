<?php
/**
 * Template Name: Page Builder (ACF Free)
 * Template Post Type: page
 * 
 * @package Custom_Theme
 */

get_header();
?>

<main class="page-builder-acf">
    <?php while (have_posts()) : the_post(); ?>
        
        <?php
        // Check if ACF Flexible Content field exists
        if (function_exists('have_rows') && have_rows('content_builder')) :
            
            while (have_rows('content_builder')) : the_row();
                
                // Get layout name
                $layout = get_row_layout();
                
                // Include layout partial if exists
                $template_file = get_template_directory() . '/template-parts/acf-layouts/' . $layout . '.php';
                
                if (file_exists($template_file)) {
                    include $template_file;
                } else {
                    echo '<div class="container">';
                    echo '<p>Layout nicht gefunden: ' . esc_html($layout) . '</p>';
                    echo '</div>';
                }
                
            endwhile;
            
        else :
            // Fallback: Show regular content
            ?>
            <div class="container">
                <?php the_content(); ?>
            </div>
            <?php
        endif;
        ?>
        
    <?php endwhile; ?>
</main>

<?php
get_footer();