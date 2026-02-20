<?php
/**
 * Search Results Template
 * 
 * @package Custom_Theme
 */

get_header();
?>

<main class="search-results">
    <div class="container">
        
        <!-- Search Header -->
        <header class="search-results__header">
            <h1 class="search-results__title">
                <?php
                $search_query = get_search_query();
                if ($search_query) {
                    printf(__('Suchergebnisse für: "%s"', 'custom-theme'), esc_html($search_query));
                } else {
                    _e('Suchergebnisse', 'custom-theme');
                }
                ?>
            </h1>
            
            <?php
            global $wp_query;
            if ($wp_query->found_posts > 0) {
                printf(
                    '<p class="search-results__count">%s %s gefunden</p>',
                    number_format_i18n($wp_query->found_posts),
                    _n('Ergebnis', 'Ergebnisse', $wp_query->found_posts, 'custom-theme')
                );
            }
            ?>
            
            <!-- Search Form (to refine search) -->
            <div class="search-results__form">
                <?php echo do_shortcode('[ajax_search]'); ?>
            </div>
        </header>
        
        <?php if (have_posts()) : ?>
            
            <!-- Results Grid -->
            <div class="search-results__grid">
                <?php while (have_posts()) : the_post(); ?>
                    
                    <article class="search-result search-result--<?php echo get_post_type(); ?>">
                        
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="search-result__thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="search-result__content">
                            
                            <!-- Meta -->
                            <div class="search-result__meta">
                                <span class="search-result__type">
                                    <?php
                                    $post_type = get_post_type();
                                    $post_type_labels = array(
                                        'post' => 'Beitrag',
                                        'page' => 'Seite',
                                        'product' => 'Produkt',
                                        'project' => 'Projekt',
                                        'service' => 'Leistung',
                                        'job' => 'Job',
                                    );
                                    echo isset($post_type_labels[$post_type]) ? $post_type_labels[$post_type] : ucfirst($post_type);
                                    ?>
                                </span>
                                <span class="search-result__date"><?php echo get_the_date('d.m.Y'); ?></span>
                            </div>
                            
                            <!-- Title -->
                            <h2 class="search-result__title">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h2>
                            
                            <!-- Excerpt -->
                            <div class="search-result__excerpt">
                                <?php echo wp_trim_words(get_the_excerpt(), 30); ?>
                            </div>
                            
                            <!-- Product Price (if WooCommerce) -->
                            <?php if (get_post_type() === 'product' && function_exists('wc_get_product')) : ?>
                                <?php $product = wc_get_product(get_the_ID()); ?>
                                <?php if ($product) : ?>
                                    <div class="search-result__price">
                                        <?php echo $product->get_price_html(); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Read More -->
                            <a href="<?php the_permalink(); ?>" class="search-result__link">
                                Mehr erfahren →
                            </a>
                            
                        </div>
                    </article>
                    
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <div class="search-results__pagination">
                <?php
                the_posts_pagination(array(
                    'mid_size' => 2,
                    'prev_text' => '← Zurück',
                    'next_text' => 'Weiter →',
                ));
                ?>
            </div>
            
        <?php else : ?>
            
            <!-- No Results -->
            <div class="search-results__no-results">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                    <line x1="11" y1="8" x2="11" y2="14"></line>
                    <line x1="11" y1="16" x2="11.01" y2="16"></line>
                </svg>
                
                <h2>Keine Ergebnisse gefunden</h2>
                <p>Leider wurden keine Ergebnisse für "<?php echo esc_html(get_search_query()); ?>" gefunden.</p>
                <p>Versuchen Sie es mit anderen Suchbegriffen.</p>
                
                <!-- Search Form -->
                <div class="search-results__form">
                    <?php echo do_shortcode('[ajax_search]'); ?>
                </div>
            </div>
            
        <?php endif; ?>
        
    </div>
</main>

<?php get_footer(); ?>