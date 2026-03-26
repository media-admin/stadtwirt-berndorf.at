<?php
/**
 * Template Name: Welcome Page
 * @package Custom_Theme
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$bg_image     = get_field( 'welcome_bg_image' );
$bg_overlay   = get_field( 'welcome_bg_overlay' ) ?: 0;
$logo         = get_field( 'welcome_logo' );
$content_area = get_field( 'welcome_content' );
$company_name = get_field( 'welcome_company_name' );
$address      = get_field( 'welcome_address' );
$phone        = get_field( 'welcome_phone' );
$email        = get_field( 'welcome_email' );
$social_links = get_field( 'welcome_social_links' );

$bg_style      = $bg_image ? ' style="background-image: url(' . esc_url( $bg_image['url'] ) . ');"' : '';
$overlay_style = ( $bg_image && $bg_overlay > 0 ) ? ' style="opacity: ' . round( $bg_overlay / 100, 2 ) . ';" ' : '';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?php echo esc_url( includes_url( 'css/dashicons.min.css' ) ); ?>">
<?php wp_head(); ?>
<style>
/* Social Icons via Dashicons – bereits im Theme geladen */
.welcome-page__social-link::before {
    font-family: dashicons;
    font-size: 20px;
    line-height: 1;
    display: block;
    speak: never;
    -webkit-font-smoothing: antialiased;
}
.welcome-page__social-link--instagram::before  { content: "\f12c"; }
.welcome-page__social-link--facebook::before   { content: "\f304"; }
.welcome-page__social-link--linkedin::before   { content: "\f459"; }
.welcome-page__social-link--xing::before       { content: "\f487"; }
.welcome-page__social-link--youtube::before    { content: "\f457"; }
.welcome-page__social-link--x::before,
.welcome-page__social-link--twitter::before    { content: "\f301"; }
/* TikTok: kein Dashicons – Text als Fallback */
.welcome-page__social-link--tiktok .wps-label  { font-size: 11px; font-weight: 700; letter-spacing: -0.5px; }
</style>
</head>
<body class="welcome-page<?php echo $bg_image ? ' welcome-page--has-bg' : ''; ?>">

<div class="welcome-page__bg"<?php echo $bg_style; ?>>
<?php if ( $bg_image && $bg_overlay > 0 ) : ?><div class="welcome-page__bg-overlay"<?php echo $overlay_style; ?>></div><?php endif; ?>
</div>

<main class="welcome-page__main" id="main">
<div class="welcome-page__container">

  <header class="welcome-page__header">
    <?php if ( $logo ) : ?>
      <img class="welcome-page__logo" src="<?php echo esc_url( $logo['url'] ); ?>" alt="<?php echo esc_attr( $logo['alt'] ?: get_bloginfo( 'name' ) ); ?>" width="<?php echo esc_attr( $logo['width'] ); ?>" height="<?php echo esc_attr( $logo['height'] ); ?>">
    <?php elseif ( has_custom_logo() ) : the_custom_logo();
    else : ?><p class="welcome-page__site-name"><?php bloginfo( 'name' ); ?></p><?php endif; ?>
  </header>

  <?php if ( $content_area ) : ?>
  <section class="welcome-page__content"><?php echo wp_kses_post( $content_area ); ?></section>
  <?php endif; ?>

  <section class="welcome-page__company" aria-label="Firmendaten">
    <?php if ( $company_name ) : ?><p class="welcome-page__company-name"><?php echo esc_html( $company_name ); ?></p><?php endif; ?>
    <?php if ( $address ) : ?><address class="welcome-page__address"><?php echo nl2br( esc_html( $address ) ); ?></address><?php endif; ?>
    <ul class="welcome-page__contact">
      <?php if ( $phone ) : ?><li><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li><?php endif; ?>
      <?php if ( $email ) : ?><li><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li><?php endif; ?>
    </ul>

    <?php if ( $social_links ) : ?>
    <ul class="welcome-page__social" aria-label="Social Media">
    <?php foreach ( $social_links as $item ) :
        if ( empty( $item['url'] ) ) continue;
        $p    = strtolower( trim( $item['platform'] ?? '' ) );
        $label = esc_html( $item['platform'] ?: $item['url'] );
    ?>
      <li>
        <a href="<?php echo esc_url( $item['url'] ); ?>"
           target="_blank" rel="noopener noreferrer"
           aria-label="<?php echo $label; ?>"
           title="<?php echo $label; ?>"
           class="welcome-page__social-link welcome-page__social-link--<?php echo esc_attr( $p ); ?>">
          <?php if ( $p === 'tiktok' ) : ?><span class="wps-label">TikTok</span><?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

</div>
</main>

<footer class="welcome-page__footer">
<div class="welcome-page__container">
  <?php wp_nav_menu( [ 'theme_location' => 'footer', 'menu_class' => 'welcome-page__footer-nav', 'container' => false, 'depth' => 1, 'fallback_cb' => false ] ); ?>
  <p class="welcome-page__copyright">&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $company_name ?: get_bloginfo( 'name' ) ); ?></p>
</div>
</footer>

<!-- Footer-Seiten Modal -->
<div id="wpm" class="welcome-page__modal" role="dialog" aria-modal="true" aria-labelledby="wpm-title" hidden>
  <div class="welcome-page__modal-overlay" id="wpm-overlay"></div>
  <div class="welcome-page__modal-box">
    <div class="welcome-page__modal-header">
      <h2 class="welcome-page__modal-title" id="wpm-title"></h2>
      <button class="welcome-page__modal-close" id="wpm-close" type="button" aria-label="Schliessen">&times;</button>
    </div>
    <div class="welcome-page__modal-content" id="wpm-content">
      <div class="welcome-page__modal-loading">Wird geladen...</div>
    </div>
  </div>
</div>

<?php wp_footer(); ?>

<script>
(function(){
  'use strict';
  // Cookie Banner: sicherstellen dass er initialisiert wird
  // (main.js laeuft als type=module, manchmal nach DOMContentLoaded)
  function initCookies() {
    if (window.CookieConsent) return;
    var scripts = document.querySelectorAll('script[src*="main.js"]');
    if (scripts.length && !window.CookieConsent) {
      // main.js wurde geladen aber CookieConsent noch nicht init
      // Kleiner Delay um module-execution abzuwarten
      setTimeout(function(){
        if (!window.CookieConsent && window.customTheme) {
          // Manuell importieren als Fallback
          import(scripts[0].src).catch(function(){});
        }
      }, 300);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCookies);
  } else {
    initCookies();
  }

  // Footer Modal
  var modal    = document.getElementById('wpm'),
      overlay  = document.getElementById('wpm-overlay'),
      closeBtn = document.getElementById('wpm-close'),
      titleEl  = document.getElementById('wpm-title'),
      contentEl= document.getElementById('wpm-content');
  if (!modal) return;

  function openModal() { modal.hidden = false; document.body.style.overflow = 'hidden'; closeBtn.focus(); }
  function closeModal() { modal.hidden = true; document.body.style.overflow = ''; contentEl.innerHTML = '<div class="welcome-page__modal-loading">Wird geladen...</div>'; }

  overlay.addEventListener('click', closeModal);
  closeBtn.addEventListener('click', closeModal);
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && !modal.hidden) closeModal(); });

  document.addEventListener('click', function(e){
    var link = e.target.closest('.welcome-page__footer-nav a');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (!href || href === '#') return;
    // Externe Links normal oeffnen
    if (href.indexOf('http') === 0 && href.indexOf(location.hostname) === -1) return;
    e.preventDefault();
    titleEl.textContent = link.textContent.trim();
    openModal();
    // X-WPM-Request Header signalisiert welcome-mode.php: kein Redirect
    // ?wpm_content=1 → PHP liefert nur den reinen Inhalt (kein Layout, keine Scripts)
    var fetchUrl = href + (href.indexOf('?') === -1 ? '?' : '&') + 'wpm_content=1';
    fetch(fetchUrl, { headers: { 'X-WPM-Request': '1' } })
      .then(function(r){ return r.text(); })
      .then(function(html){
        var doc = (new DOMParser()).parseFromString(html, 'text/html');
        // wpm_content=1 liefert <article class="wpm-content"> direkt
        var src = doc.querySelector('.wpm-content')
                 || doc.querySelector('.entry-content')
                 || doc.querySelector('main article')
                 || doc.querySelector('main');
        if (src) {
          // H1 als Modal-Titel verwenden wenn vorhanden
          var h1 = src.querySelector('h1.wpm-content__title');
          if (h1) { titleEl.textContent = h1.textContent.trim(); h1.remove(); }
          contentEl.innerHTML = src.innerHTML;
        } else {
          contentEl.innerHTML = '<p>Inhalt konnte nicht geladen werden.</p>';
        }
      })
      .catch(function(){ contentEl.innerHTML = '<p>Fehler beim Laden der Seite.</p>'; });
  });
}());
</script>
</body>
</html>
