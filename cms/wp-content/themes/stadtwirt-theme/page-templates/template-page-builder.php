<?php
/**
 * Template Name: OLD Page Builder (ACF Free)
 *
 * @package CustomTheme
 */

get_header();
?>

<main id="primary" class="site-main">
    
    <?php
    while (have_posts()) : the_post();
        
        // Hero Slider Section
        if (get_field('show_hero')) {
            $slides = get_field('hero_slides');
            if ($slides) {
                get_template_part('template-parts/sections/hero-slider-cpt', null, array(
                    'slides' => $slides
                ));
            }
        }
        
        // FAQ Section
        if (get_field('show_faq')) {
            $faqs = get_field('faq_items');
            $title = get_field('faq_title');
            if ($faqs) {
                get_template_part('template-parts/sections/accordion-cpt', null, array(
                    'title' => $title,
                    'faqs' => $faqs
                ));
            }
        }
        
        // Projects Section
        if (get_field('show_projects')) {
            $projects = get_field('projects_items');
            $title = get_field('projects_title');
            if ($projects) {
                get_template_part('template-parts/sections/projects-grid', null, array(
                    'title' => $title,
                    'projects' => $projects
                ));
            }
        }
        
        // Team Section
        if (get_field('show_team')) {
            $members = get_field('team_members_list');
            $title = get_field('team_title');
            if ($members) {
                get_template_part('template-parts/sections/team', null, array(
                    'title' => $title,
                    'members' => $members
                ));
            }
        }
        
        // Testimonials Section
        if (get_field('show_testimonials')) {
            $testimonials = get_field('testimonials_items');
            $title = get_field('testimonials_title');
            
            if ($testimonials) {
                echo '<section class="section-padding bg-light">';
                echo '<div class="container">';
                
                if ($title) {
                    echo '<header class="section-header" data-animate="fade-in">';
                    echo '<h2>' . esc_html($title) . '</h2>';
                    echo '</header>';
                }
                
                echo '<div class="testimonials-grid" data-animate-stagger>';
                
                foreach ($testimonials as $testimonial) {
                    $rating = get_field('testimonial_rating', $testimonial->ID);
                    $company = get_field('testimonial_company', $testimonial->ID);
                    $position = get_field('testimonial_position', $testimonial->ID);
                    
                    echo '<div class="testimonial-card">';
                    
                    // Rating stars
                    if ($rating) {
                        echo '<div class="testimonial-card__rating">';
                        for ($i = 0; $i < 5; $i++) {
                            echo $i < $rating ? '★' : '☆';
                        }
                        echo '</div>';
                    }
                    
                    // Content
                    echo '<div class="testimonial-card__content">';
                    echo apply_filters('the_content', $testimonial->post_content);
                    echo '</div>';
                    
                    // Author
                    echo '<div class="testimonial-card__author">';
                    if (has_post_thumbnail($testimonial->ID)) {
                        echo '<div class="testimonial-card__avatar">';
                        echo get_the_post_thumbnail($testimonial->ID, 'thumbnail');
                        echo '</div>';
                    }
                    echo '<div class="testimonial-card__info">';
                    echo '<strong>' . esc_html(get_the_title($testimonial->ID)) . '</strong>';
                    if ($position) {
                        echo '<span>' . esc_html($position);
                        if ($company) echo ' @ ' . esc_html($company);
                        echo '</span>';
                    }
                    echo '</div>';
                    echo '</div>';
                    
                    echo '</div>';
                }
                
                echo '</div>';
                echo '</div>';
                echo '</section>';
            }
        }
        
        // Default page content if no sections are enabled
        if (!get_field('show_hero') && !get_field('show_faq') && !get_field('show_projects') && !get_field('show_team') && !get_field('show_testimonials')) {
            ?>
            <div class="container section-padding">
                <?php the_content(); ?>
            </div>
            <?php
        }
        
    endwhile;
    ?>
    
</main>

<?php
get_footer();
