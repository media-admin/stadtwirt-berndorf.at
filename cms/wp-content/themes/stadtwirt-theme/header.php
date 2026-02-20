<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <!-- Logo -->
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
            <?php bloginfo('name'); ?>
            <img src="<?php echo esc_url(home_url('/')); ?>cms/wp-content/themes/custom-theme/assets/src/images/logo/logo--white.svg">
        </a>
    <nav class="site-navigation" role="navigation" aria-label="Primary Navigation">
        <!-- Desktop Menu -->
        <div class="primary-menu">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => '',
                'fallback_cb' => false,
                'depth' => 3, // 3 levels
            ));
            ?>
        </div>
        
        <!-- Mobile Toggle -->
        <button class="mobile-menu-toggle" aria-label="Toggle Menu" aria-expanded="false">
            <span></span>
        </button>
    </nav>
</header>

<!-- Mobile Menu -->
<div class="mobile-menu" role="navigation" aria-label="Mobile Navigation">
    <?php
    wp_nav_menu(array(
        'theme_location' => 'primary',
        'container' => false,
        'menu_class' => '',
        'fallback_cb' => false,
        'depth' => 3,
    ));
    ?>
</div>

<!-- Mobile Overlay -->
<div class="mobile-menu-overlay"></div>

<main id="main-content" class="site-main">