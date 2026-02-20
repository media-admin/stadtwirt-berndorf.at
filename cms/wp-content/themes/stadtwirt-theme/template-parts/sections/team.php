<?php
/**
 * Team Section
 * 
 * @package CustomTheme
 */

$team_ids = $args['members'] ?? array();
$title = $args['title'] ?? 'Unser Team';

if (empty($team_ids)) {
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
        
        <div class="team-grid" data-animate-stagger>
            <?php foreach ($team_ids as $member) :
                if (!is_object($member)) {
                    $member = get_post($member);
                }
                
                $position = get_field('team_position', $member->ID);
                $email = get_field('team_email', $member->ID);
                $social = get_field('team_social', $member->ID);
                ?>
                <div class="team-member">
                    <div class="team-member__image">
                        <?php echo get_the_post_thumbnail($member->ID, 'medium'); ?>
                    </div>
                    <div class="team-member__content">
                        <h3 class="team-member__name">
                            <?php echo esc_html(get_the_title($member->ID)); ?>
                        </h3>
                        <?php if ($position) : ?>
                            <p class="team-member__position">
                                <?php echo esc_html($position); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($email) : ?>
                            <a href="mailto:<?php echo esc_attr($email); ?>" class="team-member__email">
                                <?php echo esc_html($email); ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($social) : ?>
                            <div class="team-member__social">
                                <?php foreach ($social as $link) : ?>
                                    <a href="<?php echo esc_url($link['url']); ?>" 
                                       target="_blank" 
                                       rel="noopener">
                                        <?php echo esc_html($link['platform']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>