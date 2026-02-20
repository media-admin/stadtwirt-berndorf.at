<?php
/**
 * Projects Grid with Lightbox
 * 
 * @package CustomTheme
 */

$project_ids = $args['projects'] ?? array();
$title = $args['title'] ?? 'Unsere Projekte';

if (empty($project_ids)) {
    return;
}
?>

<section class="section-padding">
    <div class="container">
        <?php if ($title) : ?>
            <header class="section-header" data-animate="fade-in">
                <h2><?php echo esc_html($title); ?></h2>
            </header>
        <?php endif; ?>
        
        <div class="card-grid" data-animate-stagger>
            <?php foreach ($project_ids as $project) :
                if (!is_object($project)) {
                    $project = get_post($project);
                }
                
                $thumbnail = get_the_post_thumbnail_url($project->ID, 'customtheme-card');
                $full_image = get_the_post_thumbnail_url($project->ID, 'full');
                $client = get_field('project_client', $project->ID);
                $year = get_field('project_year', $project->ID);
                ?>
                <div class="card">
                    <a href="<?php echo esc_url($full_image); ?>" 
                       data-lightbox="projects" 
                       data-caption="<?php echo esc_attr(get_the_title($project->ID)); ?>">
                        <img src="<?php echo esc_url($thumbnail); ?>" 
                             alt="<?php echo esc_attr(get_the_title($project->ID)); ?>"
                             loading="lazy">
                    </a>
                    <div class="card__content">
                        <h3 class="card__title">
                            <?php echo esc_html(get_the_title($project->ID)); ?>
                        </h3>
                        <?php if ($client || $year) : ?>
                            <p class="card__meta">
                                <?php if ($client) echo esc_html($client); ?>
                                <?php if ($client && $year) echo ' • '; ?>
                                <?php if ($year) echo esc_html($year); ?>
                            </p>
                        <?php endif; ?>
                        <div class="card__description">
                            <?php echo wp_trim_words(get_the_excerpt($project->ID), 15); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>