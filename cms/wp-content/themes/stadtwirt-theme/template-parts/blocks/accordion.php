<?php
/**
 * Accordion Block Template
 */

$accordion_items = get_field('accordion_items');
$allow_multiple = get_field('allow_multiple_open');
$first_open = get_field('first_item_open');

if ($accordion_items) :
?>
<div class="accordion" <?php echo $allow_multiple ? 'data-allow-multiple="true"' : ''; ?>>
    <?php foreach ($accordion_items as $index => $item) : 
        $is_first = ($index === 0);
        $is_open = $first_open && $is_first;
    ?>
        <div class="accordion__item <?php echo $is_open ? 'is-active' : ''; ?>">
            <button class="accordion__trigger" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                <span class="accordion__title"><?php echo esc_html($item['title']); ?></span>
                <span class="accordion__icon"><?php echo $is_open ? '−' : '+'; ?></span>
            </button>
            <div class="accordion__content" <?php echo $is_open ? 'style="display:block;"' : ''; ?>>
                <div class="accordion__content-inner">
                    <?php echo wp_kses_post($item['content']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>