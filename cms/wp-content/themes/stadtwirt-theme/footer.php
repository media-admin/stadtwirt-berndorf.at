<footer class="site-footer">
    <div class="container">

        <div class="site-footer__inner">

            <?php
            // ── Logo oder Site-Name ───────────────────────────────────────
            $logo = function_exists('get_field') ? get_field('logo_desktop', 'option') : null;
            ?>
            <div class="site-footer__brand">
                <?php if ( $logo && ! empty( $logo['url'] ) ) : ?>
                    <a href="<?php echo esc_url( home_url('/') ); ?>" class="site-footer__logo-link">
                        <img
                            src="<?php echo esc_url( $logo['url'] ); ?>"
                            alt="<?php bloginfo('name'); ?>"
                            class="site-footer__logo"
                            loading="lazy"
                        >
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( home_url('/') ); ?>" class="site-footer__site-name">
                        <?php bloginfo('name'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php
            // ── Footer Navigation ─────────────────────────────────────────
            if ( has_nav_menu('footer') ) :
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'menu_class'     => 'footer-nav__list',
                    'container'      => 'nav',
                    'container_class'=> 'site-footer__nav footer-nav',
                    'container_aria_label' => 'Footer Navigation',
                    'depth'          => 4,
                    'fallback_cb'    => false,
                ));
            endif;
            ?>

        </div><!-- .site-footer__inner -->

        <div class="site-footer__bottom">
            <p class="site-footer__copyright">
                &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>.
                <?php esc_html_e('Alle Rechte vorbehalten.', 'custom-theme'); ?>
            </p>
        </div>

    </div><!-- .container -->
</footer>

</div><!-- #page -->

<?php
// ── Back-to-Top Button ────────────────────────────────────────────────────
if ( function_exists('get_field') && get_field('btt_enabled', 'option') ) : ?>
<button
    class="back-to-top"
    aria-label="<?php esc_attr_e('Zurück nach oben', 'custom-theme'); ?>"
    type="button"
>
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
