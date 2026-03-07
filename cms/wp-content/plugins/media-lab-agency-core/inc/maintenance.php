<?php
/**
 * Maintenance Mode
 *
 * Aktivierung: ACF Options → Agency Core → Maintenance Mode aktivieren
 * Fallback:    wp-config.php Konstante MEDIALAB_MAINTENANCE_MODE=true
 *
 * Admin-Bypass:  Eingeloggte Administratoren sehen die normale Website.
 * HTTP-Status:   503 Service Unavailable + Retry-After: 3600
 *
 * @package MediaLab_Core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MediaLab_Maintenance {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'maybe_show_maintenance' ), 0 );
        add_action( 'admin_bar_menu',    array( $this, 'add_admin_bar_indicator' ), 100 );
    }

    // ─── Maintenance aktiv? ───────────────────────────────────────────────────

    private function is_active(): bool {
        // 1. wp-config.php Konstante (höchste Priorität – auch wenn DB nicht verfügbar)
        if ( defined( 'MEDIALAB_MAINTENANCE_MODE' ) && MEDIALAB_MAINTENANCE_MODE ) {
            return true;
        }

        // 2. ACF Options
        if ( function_exists( 'get_field' ) ) {
            return (bool) get_field( 'maintenance_enabled', 'option' );
        }

        return false;
    }

    // ─── Inhalte aus ACF oder Defaults ───────────────────────────────────────

    private function get_content(): array {
        $defaults = array(
            'title'     => get_bloginfo( 'name' ) . ' – Wartungsarbeiten',
            'headline'  => 'Wir sind gleich zurück',
            'message'   => 'Unsere Website wird gerade gewartet und verbessert. Wir sind in Kürze wieder für Sie da.',
            'date'      => '',
            'date_label'=> 'Voraussichtlich wieder erreichbar ab:',
            'logo'      => '',
        );

        if ( ! function_exists( 'get_field' ) ) {
            return $defaults;
        }

        return array(
            'title'     => get_field( 'maintenance_title', 'option' )      ?: $defaults['title'],
            'headline'  => get_field( 'maintenance_headline', 'option' )   ?: $defaults['headline'],
            'message'   => get_field( 'maintenance_message', 'option' )    ?: $defaults['message'],
            'date'      => get_field( 'maintenance_date', 'option' )       ?: '',
            'date_label'=> $defaults['date_label'],
            'logo'      => get_field( 'maintenance_logo', 'option' )       ?: '',
        );
    }

    // ─── Maintenance-Seite ausgeben ───────────────────────────────────────────

    public function maybe_show_maintenance(): void {
        if ( ! $this->is_active() ) return;

        // Admins und eingeloggte Redakteure können die Site weiterhin sehen
        if ( current_user_can( 'manage_options' ) ) return;

        // Admin-Bereich nie blockieren
        if ( is_admin() ) return;

        // WP-CLI nie blockieren
        if ( defined( 'WP_CLI' ) && WP_CLI ) return;

        $content = $this->get_content();

        // 503-Header
        status_header( 503 );
        header( 'Retry-After: 3600' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        nocache_headers();

        $this->render( $content );
        exit;
    }

    // ─── HTML ausgeben ────────────────────────────────────────────────────────

    private function render( array $c ): void {
        $site_name  = get_bloginfo( 'name' );
        $theme_uri  = get_template_directory_uri();
        $logo_html  = '';

        if ( ! empty( $c['logo'] ) ) {
            if ( is_array( $c['logo'] ) && ! empty( $c['logo']['url'] ) ) {
                // ACF Image-Feld (array)
                $logo_html = '<img src="' . esc_url( $c['logo']['url'] ) . '" alt="' . esc_attr( $site_name ) . '" class="maintenance__logo-img">';
            } elseif ( is_numeric( $c['logo'] ) ) {
                // ACF Image-Feld als ID
                $logo_html = wp_get_attachment_image( (int) $c['logo'], 'medium', false, array( 'class' => 'maintenance__logo-img', 'alt' => esc_attr( $site_name ) ) );
            } else {
                $logo_html = '<img src="' . esc_url( $c['logo'] ) . '" alt="' . esc_attr( $site_name ) . '" class="maintenance__logo-img">';
            }
        }

        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $c['title'] ); ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:    #e00000;
            --text:       #1a1a1a;
            --text-muted: #6b7280;
            --border:     #e5e7eb;
            --bg:         #f9fafb;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text:       #f5f5f5;
                --text-muted: #a0a0a0;
                --border:     #333;
                --bg:         #111;
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .maintenance {
            text-align: center;
            max-width: 540px;
            width: 100%;
        }

        .maintenance__logo {
            margin-bottom: 2.5rem;
        }

        .maintenance__logo-img {
            max-height: 60px;
            width: auto;
        }

        .maintenance__logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
        }

        .maintenance__icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1.5rem;
            color: var(--primary);
        }

        .maintenance__headline {
            font-size: clamp(1.75rem, 5vw, 2.5rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1rem;
        }

        .maintenance__message {
            font-size: 1.125rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .maintenance__date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9375rem;
        }

        .maintenance__date-label {
            color: var(--text-muted);
        }

        .maintenance__date-value {
            font-weight: 600;
            color: var(--primary);
        }

        .maintenance__footer {
            margin-top: 3rem;
            font-size: 0.875rem;
            color: var(--text-muted);
            border-top: 1px solid var(--border);
            padding-top: 1.5rem;
        }

        /* Animierter Ladebalken oben */
        .maintenance__progress {
            position: fixed;
            top: 0; left: 0;
            height: 3px;
            background: var(--primary);
            animation: progress 2.5s ease-in-out infinite alternate;
            transform-origin: left;
        }

        @keyframes progress {
            from { width: 20%; }
            to   { width: 85%; }
        }
    </style>
</head>
<body>

    <div class="maintenance__progress" aria-hidden="true"></div>

    <div class="maintenance">

        <div class="maintenance__logo">
            <?php if ( $logo_html ) : ?>
                <?php echo $logo_html; // Logo wurde via wp_get_attachment_image oder img-Tag erzeugt ?>
            <?php else : ?>
                <span class="maintenance__logo-text"><?php echo esc_html( $site_name ); ?></span>
            <?php endif; ?>
        </div>

        <svg class="maintenance__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.654-4.654m5.896-3.042c.068-.794.018-1.606-.195-2.375A5.978 5.978 0 0 0 9.655 5.25c-1.093 0-2.127.29-3.013.8l3.293 3.292a1.5 1.5 0 0 1 0 2.121l-2.122 2.122a1.5 1.5 0 0 1-2.121 0L2.4 10.272a5.978 5.978 0 0 0 .8 6.343 5.977 5.977 0 0 0 7.716 1.479" />
        </svg>

        <h1 class="maintenance__headline">
            <?php echo esc_html( $c['headline'] ); ?>
        </h1>

        <p class="maintenance__message">
            <?php echo wp_kses_post( $c['message'] ); ?>
        </p>

        <?php if ( ! empty( $c['date'] ) ) : ?>
            <div class="maintenance__date">
                <span class="maintenance__date-label">
                    <?php echo esc_html( $c['date_label'] ); ?>
                </span>
                <span class="maintenance__date-value">
                    <?php echo esc_html( $c['date'] ); ?>
                </span>
            </div>
        <?php endif; ?>

        <div class="maintenance__footer">
            &copy; <?php echo date( 'Y' ); ?>
            <?php echo esc_html( $site_name ); ?>
        </div>

    </div>

</body>
</html>
        <?php
    }

    // ─── Admin-Bar Indikator ──────────────────────────────────────────────────

    public function add_admin_bar_indicator( $wp_admin_bar ): void {
        if ( ! $this->is_active() ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $wp_admin_bar->add_node( array(
            'id'    => 'medialab-maintenance-active',
            'title' => '🔧 Maintenance aktiv',
            'href'  => admin_url( 'admin.php?page=agency-core-maintenance' ),
            'meta'  => array(
                'class' => 'medialab-maintenance-indicator',
                'title' => 'Maintenance Mode ist aktiv – nur Admins sehen die Website',
            ),
        ) );
    }
}

new MediaLab_Maintenance();
