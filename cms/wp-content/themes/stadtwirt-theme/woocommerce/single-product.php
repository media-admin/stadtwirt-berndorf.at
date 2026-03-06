<?php
/**
 * Single Product Template
 * 
 * Override default WooCommerce single product template
 */

defined('ABSPATH') || exit;

get_header('shop');

?>

<div class="container">
    <div class="single-product-container">
        
        <?php while (have_posts()) : the_post(); ?>
            
            <?php wc_get_template_part('content', 'single-product'); ?>
            
            <?php
            // Custom ACF Fields Display
            $highlights = get_field('product_highlights');
            $specifications = get_field('specifications');
            $video = get_field('product_video');
            ?>
            
            <?php if ($highlights) : ?>
                <div class="product-highlights">
                    <h2>Highlights</h2>
                    <ul class="highlights-list">
                        <?php foreach ($highlights as $highlight) : ?>
                            <li>
                                <?php if ($highlight['highlight_icon']) : ?>
                                    <span class="dashicons <?php echo esc_attr($highlight['highlight_icon']); ?>"></span>
                                <?php endif; ?>
                                <?php echo esc_html($highlight['highlight_text']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($specifications) : ?>
                <div class="product-specifications">
                    <h2>Technische Daten</h2>
                    <table class="specifications-table">
                        <?php foreach ($specifications as $spec) : ?>
                            <tr>
                                <th><?php echo esc_html($spec['spec_label']); ?></th>
                                <td><?php echo esc_html($spec['spec_value']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if ($video) : ?>
                <div class="product-video">
                    <h2>Produkt-Video</h2>
                    <div class="video-wrapper">
                        <?php echo wp_oembed_get($video); ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php endwhile; ?>
        
    </div>
</div>

<?php
get_footer('shop');