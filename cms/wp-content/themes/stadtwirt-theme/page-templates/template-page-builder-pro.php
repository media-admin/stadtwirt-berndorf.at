<?php
/**
 * Template Name: OLD Page Builder (ACF PRO)
 *
 * @package CustomTheme
 */

get_header();
?>

<main id="primary" class="site-main">
    
    <?php
    // Check if page has sections
    if (have_rows('page_sections')) :
        
        while (have_rows('page_sections')) : the_row();
            
            $layout = get_row_layout();
            
            switch ($layout) {
                case 'hero_slider':
                    get_template_part('template-parts/sections/hero-slider-cpt', null, array(
                        'slides' => get_sub_field('slides')
));
break;
case 'accordion':
                get_template_part('template-parts/sections/accordion-cpt', null, array(
                    'title' => get_sub_field('title'),
                    'faqs' => get_sub_field('faqs')
                ));
                break;
                
            case 'projects_grid':
                get_template_part('template-parts/sections/projects-grid', null, array(
                    'title' => get_sub_field('title'),
                    'projects' => get_sub_field('projects')
                ));
                break;
                
            case 'team':
                get_template_part('template-parts/sections/team', null, array(
                    'title' => get_sub_field('title'),
                    'members' => get_sub_field('members')
                ));
                break;
                
            case 'testimonials':
                $testimonials = get_sub_field('testimonials');
                if ($testimonials) {
                    echo '<section class="section-padding">';
                    echo '<div class="container">';
                    if (get_sub_field('title')) {
                        echo '<header class="section-header"><h2>' . esc_html(get_sub_field('title')) . '</h2></header>';
                    }
                    // Add testimonials slider here
                    echo '</div>';
                    echo '</section>';
                }
                break;
        }
        
    endwhile;
    
else :
    // Default content
    while (have_posts()) : the_post();
        the_content();
    endwhile;
endif;
?>

<?php
get_footer();