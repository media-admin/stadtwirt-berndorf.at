<?php
/**
 * Accordion from FAQ CPT
 * 
 * @package CustomTheme
 */

$faq_ids = $args['faqs'] ?? array();
$title = $args['title'] ?? 'Häufige Fragen';

if (empty($faq_ids)) {
    return;
}

$items = array();

foreach ($faq_ids as $faq) {
    if (!is_object($faq)) {
        $faq = get_post($faq);
    }
    
    $items[] = array(
        'title' => get_the_title($faq->ID),
        'content' => apply_filters('the_content', $faq->post_content),
    );
}
?>

<section class="section-padding">
    <div class="container">
        <?php if ($title) : ?>
            <header class="section-header">
                <h2><?php echo esc_html($title); ?></h2>
            </header>
        <?php endif; ?>
        
        <?php
        get_template_part('template-parts/components/accordion', null, array(
            'items' => $items
        ));
        ?>
    </div>
</section>