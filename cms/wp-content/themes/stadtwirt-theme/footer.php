<?php
$phone_group   = function_exists('get_field') ? get_field('top_header_phone',   'option') : [];
$email_group   = function_exists('get_field') ? get_field('top_header_email',   'option') : [];
$address_group = function_exists('get_field') ? get_field('top_header_address', 'option') : [];
$phone_raw   = (!empty($phone_group['enable']) && !empty($phone_group['number']))  ? $phone_group['number']  : '';
$phone       = (!empty($phone_group['enable']) && !empty($phone_group['display'])) ? $phone_group['display'] : $phone_raw;
$email       = (!empty($email_group['enable']) && !empty($email_group['address'])) ? $email_group['address'] : '';
$street      = $address_group['street'] ?? '';
$city        = $address_group['city']   ?? '';
$address     = (!empty($address_group['enable']) && ($street || $city)) ? trim($street . ', ' . $city, ', ') : '';
$maps_link   = $address_group['maps_link'] ?? '';
$social_group = function_exists('get_field') ? get_field('top_header_social', 'option') : [];
$socials = [];
if (!empty($social_group['enable'])) {
    $platforms = ['facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'xing'];
    foreach ($platforms as $key) {
        if (!empty($social_group[$key])) {
            $socials[$key] = $social_group[$key];
        }
    }
}
$logo    = function_exists('get_field') ? get_field('logo_desktop',       'option') : null;
?>
<footer class="site-footer">

    <?php // ── Ornament ────────────────────────────────────────────────── ?>
    <div class="site-footer__ornament">
        <hr>
    </div>

    <div class="container">
        <div class="site-footer__inner">

            <?php // ── Spalte 1: Logo ──────────────────────────────────── ?>
            <div class="site-footer__brand">
                <?php if ($logo && !empty($logo['url'])) : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="site-footer__logo-link">
                        <img src="<?php echo esc_url($logo['url']); ?>"
                             alt="<?php bloginfo('name'); ?>"
                             class="site-footer__logo"
                             loading="lazy">
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="site-footer__site-name">
                        <?php bloginfo('name'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php // ── Spalte 2: Kontakt ───────────────────────────────── ?>
            <div class="site-footer__contact">
                <?php if ($phone) : ?>
                    <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone_raw)); ?>" class="site-footer__contact-item">
                        <span class="site-footer__contact-label">t</span>
                        <?php echo esc_html($phone); ?>
                    </a>
                <?php endif; ?>
                <?php if ($email) : ?>
                    <a href="mailto:<?php echo esc_attr($email); ?>" class="site-footer__contact-item">
                        <span class="site-footer__contact-label">e</span>
                        <?php echo esc_html($email); ?>
                    </a>
                <?php endif; ?>
                <?php if ($address) : ?>
                    <?php if ($maps_link) : ?>
                    <a href="<?php echo esc_url($maps_link); ?>" class="site-footer__contact-item" target="_blank" rel="noopener noreferrer">
                        <span class="site-footer__contact-label">a</span>
                        <?php echo esc_html($address); ?>
                    </a>
                <?php else : ?>
                    <span class="site-footer__contact-item">
                        <span class="site-footer__contact-label">a</span>
                        <?php echo esc_html($address); ?>
                    </span>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php // ── Spalte 3: Hauptmenü ─────────────────────────────── ?>
            <?php if (has_nav_menu('primary')) : ?>
                <?php wp_nav_menu([
                    'theme_location'       => 'primary',
                    'menu_class'           => 'site-footer__nav-list',
                    'container'            => 'nav',
                    'container_class'      => 'site-footer__nav',
                    'container_aria_label' => 'Footer Hauptnavigation',
                    'depth'                => 1,
                    'fallback_cb'          => false,
                ]); ?>
            <?php endif; ?>

            <?php // ── Spalte 4: Social + Legal ────────────────────────── ?>
            <div class="site-footer__aside">
                <?php if (!empty($socials)) : ?>
                    <div class="site-footer__social">
                        <?php
                        $svg_icons = [
                            'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
                            'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>',
                            'linkedin'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
                            'twitter'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
                            'youtube'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58a2.78 2.78 0 0 0 1.95 1.95C5.12 20 12 20 12 20s6.88 0 8.59-.47a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12z"/></svg>',
                            'xing'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M18.188 0c-.517 0-.741.325-.927.66 0 0-7.455 13.224-7.702 13.657.015.024 4.919 9.023 4.919 9.023.17.308.436.66.967.66h3.454c.211 0 .375-.078.463-.22.089-.151.089-.346-.009-.536l-4.879-8.916c-.004-.006-.004-.016 0-.022L22.139.756c.095-.191.097-.387.006-.535C22.056.078 21.894 0 21.686 0h-3.498zM3.648 4.74c-.211 0-.385.074-.473.216-.09.149-.078.339.02.531l2.34 4.05c.004.01.004.016 0 .021L1.86 16.051c-.099.188-.093.381 0 .529.085.142.239.234.45.234h3.461c.518 0 .766-.348.945-.667l3.734-6.609-2.378-4.155c-.172-.315-.434-.643-.962-.643H3.648z"/></svg>',
                        ];
                        foreach ($socials as $platform => $url) : ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="site-footer__social-link"
                               target="_blank"
                               rel="noopener noreferrer"
                               aria-label="<?php echo esc_attr($platform); ?>">
                                <?php echo $svg_icons[$platform] ?? esc_html($platform); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                 <?php
                // ── Footer Legal Navigation ───────────────────────────────────────────
                if ( has_nav_menu('footer-legal') ) :
                    wp_nav_menu([
                        'theme_location'       => 'footer-legal',
                        'menu_class'           => 'footer-legal__list',
                        'container'            => 'nav',
                        'container_class'      => 'footer-legal',
                        'container_aria_label' => __('Rechtliche Links', 'custom-theme'),
                        'depth'                => 1,       // Nur eine Ebene – keine Submenüs
                        'fallback_cb'          => false,
                    ]);
                endif;
                ?>
            </div>

        </div><!-- .site-footer__inner -->

        <?php // ── Copyright ───────────────────────────────────────────── ?>
        <div class="site-footer__bottom">

            <p class="site-footer__copyright">
                &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>.
                <?php esc_html_e('Alle Rechte vorbehalten.', 'custom-theme'); ?>
            </p>

        </div><!-- .site-footer__bottom -->

        <div class="site-footer__credit">

            <p>
                <?php esc_html_e('Konzept und Programmierung:', 'custom-theme'); ?>
                <a
                    href="https://www.media-lab.at"
                    target="_blank"
                    rel="noopener noreferrer"
                >Media Lab Tritremmel GmbH</a>
            </p>
        </div>

    </div><!-- .container -->

</footer>
</div><!-- #page -->

<?php if (function_exists('get_field') && get_field('btt_enabled', 'option')) : ?>
<button class="back-to-top" aria-label="Zurück nach oben" type="button">
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
