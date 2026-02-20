<?php
/**
 * Accordion Component
 * 
 * @package CustomTheme
 */

$items = $args['items'] ?? array();
$allow_multiple = $args['allow_multiple'] ?? false;

if (empty($items)) {
    return;
}
?>

<div class="accordion" data-allow-multiple="<?php echo $allow_multiple ? 'true' : 'false'; ?>">
    <?php foreach ($items as $index => $item) : ?>
        <div class="accordion__item <?php echo $index === 0 ? 'is-active' : ''; ?>">
            <button class="accordion__trigger" type="button">
                <?php echo esc_html($item['title']); ?>
            </button>
            <div class="accordion__content">
                <div class="accordion__body">
                    <?php echo wp_kses_post($item['content']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>