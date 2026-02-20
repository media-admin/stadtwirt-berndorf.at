<?php
/**
 * ACF Field Definitions for Blocks
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

// Accordion Block Fields
if (function_exists('acf_add_local_field_group')) {
    
    acf_add_local_field_group(array(
        'key' => 'group_accordion_block',
        'title' => 'Accordion Settings',
        'fields' => array(
            array(
                'key' => 'field_accordion_items',
                'label' => 'Accordion Items',
                'name' => 'accordion_items',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => '+ Item hinzufügen',
                'min' => 1,
                'sub_fields' => array(
                    array(
                        'key' => 'field_accordion_title',
                        'label' => 'Titel',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                        'placeholder' => 'z.B. Wie funktioniert das?',
                    ),
                    array(
                        'key' => 'field_accordion_content',
                        'label' => 'Inhalt',
                        'name' => 'content',
                        'type' => 'wysiwyg',
                        'tabs' => 'all',
                        'toolbar' => 'full',
                        'media_upload' => 1,
                        'required' => 1,
                    ),
                ),
            ),
            array(
                'key' => 'field_allow_multiple_open',
                'label' => 'Mehrere Items gleichzeitig öffnen?',
                'name' => 'allow_multiple_open',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
                'instructions' => 'Wenn aktiviert, können mehrere Accordion-Items gleichzeitig geöffnet sein.',
            ),
            array(
                'key' => 'field_first_item_open',
                'label' => 'Erstes Item standardmäßig offen?',
                'name' => 'first_item_open',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/accordion',
                ),
            ),
        ),
    ));
    
    // Hero Slider Block Fields
    acf_add_local_field_group(array(
        'key' => 'group_hero_slider_block',
        'title' => 'Hero Slider Settings',
        'fields' => array(
            array(
                'key' => 'field_hero_slides',
                'label' => 'Slides',
                'name' => 'hero_slides',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => '+ Slide hinzufügen',
                'min' => 1,
                'sub_fields' => array(
                    array(
                        'key' => 'field_slide_image',
                        'label' => 'Hintergrundbild',
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                        'preview_size' => 'medium',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_slide_title',
                        'label' => 'Titel',
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                    ),
                    array(
                        'key' => 'field_slide_subtitle',
                        'label' => 'Untertitel',
                        'name' => 'subtitle',
                        'type' => 'textarea',
                        'rows' => 3,
                    ),
                    array(
                        'key' => 'field_slide_button_text',
                        'label' => 'Button Text',
                        'name' => 'button_text',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_slide_button_link',
                        'label' => 'Button Link',
                        'name' => 'button_link',
                        'type' => 'link',
                        'return_format' => 'array',
                    ),
                ),
            ),
            array(
                'key' => 'field_slider_autoplay',
                'label' => 'Autoplay aktivieren?',
                'name' => 'autoplay',
                'type' => 'true_false',
                'default_value' => 1,
                'ui' => 1,
            ),
            array(
                'key' => 'field_slider_delay',
                'label' => 'Autoplay Verzögerung (ms)',
                'name' => 'delay',
                'type' => 'number',
                'default_value' => 5000,
                'min' => 1000,
                'step' => 1000,
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_slider_autoplay',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/hero-slider',
                ),
            ),
        ),
    ));
}