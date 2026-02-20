<?php
/**
 * Modal Component
 * 
 * @package CustomTheme
 */

$modal_id = $args['id'] ?? 'modal-' . uniqid();
$title = $args['title'] ?? '';
$content = $args['content'] ?? '';
$footer = $args['footer'] ?? '';
?>

<div class="modal" id="<?php echo esc_attr($modal_id); ?>">
    <div class="modal__dialog">
        <?php if ($title) : ?>
            <div class="modal__header">
                <h3 class="modal__title"><?php echo esc_html($title); ?></h3>
                <button class="modal__close" data-modal-close aria-label="Close">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="modal__body">
            <?php echo wp_kses_post($content); ?>
        </div>
        
        <?php if ($footer) : ?>
            <div class="modal__footer">
                <?php echo wp_kses_post($footer); ?>
            </div>
        <?php endif; ?>
    </div>
</div>