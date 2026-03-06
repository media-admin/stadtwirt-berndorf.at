# Component Usage Guide

## Hero Slider
```php
get_template_part('template-parts/components/hero-slider', null, array(
    'slides' => array(
        array(
            'image' => 'URL',
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'button_text' => 'Button',
            'button_link' => '/link',
        ),
    )
));
```

## Accordion
```php
get_template_part('template-parts/components/accordion', null, array(
    'items' => array(
        array(
            'title' => 'Question',
            'content' => 'Answer',
        ),
    ),
    'allow_multiple' => false,
));
```

## Lightbox
```html
<a href="large-image.jpg" data-lightbox="gallery" data-caption="Caption">
<img src="thumbnail.jpg" alt="">
</a>

## Modal
```html
<button data-modal-trigger="modal-id">Open Modal</button>
```

## Animations
```html
<div data-animate="fade-in-up">Animated content</div>
<div data-animate="fade-in-left">Animated content</div>
<div data-animate-stagger">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

## Dark Mode

Automatic! Button is added automatically.

## Back to Top

Automatic! Button appears after scrolling 300px.

## Cookie Notice

Automatic! Shows on first visit.