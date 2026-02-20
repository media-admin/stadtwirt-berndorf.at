<?php
/**
 * Hero Slider from CPT
 * 
 * @package CustomTheme
 */

$slide_ids = $args['slides'] ?? array();

if (empty($slide_ids)) {
    return;
}

$slides_data = array();

foreach ($slide_ids as $slide) {
    if (!is_object($slide)) {
        $slide = get_post($slide);
    }
    
    $slides_data[] = array(
        'image' => get_the_post_thumbnail_url($slide->ID, 'customtheme-hero'),
        'title' => get_the_title($slide->ID),
        'subtitle' => get_field('slide_subtitle', $slide->ID),
        'button_text' => get_field('slide_button_text', $slide->ID),
        'button_link' => get_field('slide_button_link', $slide->ID),
    );
}

get_template_part('template-parts/components/hero-slider', null, array(
    'slides' => $slides_data
));