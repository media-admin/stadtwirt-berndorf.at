<?php
/**
 * SEO Report E-Mail Template
 *
 * Generiert eine professionelle HTML-Mail mit Inline-CSS.
 * Kompatibel mit allen gängigen E-Mail-Clients (Gmail, Outlook, Apple Mail).
 *
 * @package MediaLab_SEO
 * @since   1.2.0
 *
 * @param array  $data     Ausgabe von medialab_gsc_get_dashboard_data()
 * @param string $site     Blog-Name / Webseite
 * @return string          Vollständiger HTML-String
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function medialab_seo_report_html( array $data, string $site = '' ): string {
    if ( empty( $site ) ) $site = get_bloginfo( 'name' );

    $c       = $data['current']  ?? [];
    $p       = $data['previous'] ?? [];
    $period  = sprintf( '%s – %s',
        date_i18n( 'd.m.Y', strtotime( $data['period']['start'] ?? '-28 days' ) ),
        date_i18n( 'd.m.Y', strtotime( $data['period']['end']   ?? 'today' ) )
    );
    $keywords = $data['keywords'] ?? [];
    $pages    = $data['pages']    ?? [];
    $agency   = get_option( 'medialab_report_from_name', get_bloginfo( 'name' ) );
    $year     = date( 'Y' );

    // Delta-Helfer
    $delta = function( float $curr, float $prev, bool $lower_is_better = false ): string {
        if ( $prev === 0.0 ) return '';
        $pct  = round( abs( $curr - $prev ) / $prev * 100, 1 );
        $up   = $curr >= $prev;
        $good = $lower_is_better ? ! $up : $up;
        $clr  = $good ? '#10b981' : '#ef4444';
        $arr  = $up ? '↑' : '↓';
        return "<span style=\"color:{$clr};font-size:12px;font-weight:600\">{$arr} {$pct}%</span>";
    };

    // KPI-Werte
    $kpis = [
        [ '🖱️', 'Klicks',      number_format( $c['clicks']      ?? 0, 0, ',', '.' ), $delta( $c['clicks']   ?? 0, $p['clicks']   ?? 0 ) ],
        [ '👁️', 'Impressionen', number_format( $c['impressions'] ?? 0, 0, ',', '.' ), $delta( $c['impressions'] ?? 0, $p['impressions'] ?? 0 ) ],
        [ '📈', 'Ø CTR',       number_format( $c['ctr']         ?? 0, 1, ',', '' ) . '%', $delta( $c['ctr'] ?? 0, $p['ctr'] ?? 0 ) ],
        [ '🏆', 'Ø Position',  number_format( $c['position']    ?? 0, 1, ',', '' ), $delta( $c['position'] ?? 0, $p['position'] ?? 0, true ) ],
    ];

    // ── HTML ──────────────────────────────────────────────────────────────── //
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="de" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SEO Report – <?php echo esc_html( $site ); ?></title>
<!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#1a1a1a">

<!-- Preheader -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all">
    SEO Report für <?php echo esc_html( $site ); ?> · <?php echo esc_html( $period ); ?> · Klicks, Impressionen, Top-Keywords
</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f6f8;padding:32px 16px">
<tr><td align="center">

    <!-- Container -->
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

        <!-- Header -->
        <tr>
            <td style="background:#1a1a2e;border-radius:12px 12px 0 0;padding:32px 40px;text-align:center">
                <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;letter-spacing:1.5px;text-transform:uppercase">Wöchentlicher SEO Report</p>
                <h1 style="margin:0;font-size:26px;font-weight:700;color:#ffffff"><?php echo esc_html( $site ); ?></h1>
                <p style="margin:8px 0 0;font-size:14px;color:#9ca3af"><?php echo esc_html( $period ); ?></p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="background:#ffffff;padding:40px">

                <!-- Intro -->
                <p style="margin:0 0 32px;font-size:15px;color:#4b5563;line-height:1.6">
                    Hier sind deine SEO-Kennzahlen der letzten 28 Tage im Vergleich zur Vorperiode – direkt aus der Google Search Console.
                </p>

                <!-- KPI-Grid -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:40px">
                    <tr>
                        <?php foreach ( $kpis as $i => $kpi ) :
                            $border_right = $i < 3 ? 'border-right:1px solid #e5e7eb;' : '';
                        ?>
                        <td width="25%" style="text-align:center;padding:20px 12px;<?php echo $border_right; ?>">
                            <div style="font-size:24px;margin-bottom:8px"><?php echo $kpi[0]; ?></div>
                            <div style="font-size:22px;font-weight:700;color:#1a1a2e;line-height:1"><?php echo $kpi[2]; ?></div>
                            <div style="font-size:12px;color:#9ca3af;margin:4px 0"><?php echo esc_html( $kpi[1] ); ?></div>
                            <div><?php echo $kpi[3]; ?></div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                </table>

                <!-- Divider -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:40px">
                    <tr><td style="border-top:2px solid #e5e7eb;font-size:0">&nbsp;</td></tr>
                </table>

                <!-- Top Keywords -->
                <?php if ( ! empty( $keywords ) ) : ?>
                <h2 style="margin:0 0 16px;font-size:18px;font-weight:700;color:#1a1a2e">🔑 Top Keywords</h2>
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:40px;border-collapse:collapse">
                    <thead>
                        <tr style="background:#f9fafb">
                            <th style="padding:10px 12px;text-align:left;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">#</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Keyword</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Klicks</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Impr.</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Pos.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_slice( $keywords, 0, 8 ) as $i => $row ) : ?>
                        <tr style="border-bottom:1px solid #f3f4f6">
                            <td style="padding:10px 12px;font-size:13px;color:#9ca3af"><?php echo $i + 1; ?></td>
                            <td style="padding:10px 12px;font-size:13px;color:#1a1a2e;font-weight:500"><?php echo esc_html( $row['keyword'] ); ?></td>
                            <td style="padding:10px 12px;font-size:13px;color:#1a1a2e;font-weight:700;text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?></td>
                            <td style="padding:10px 12px;font-size:13px;color:#4b5563;text-align:right"><?php echo number_format( $row['impressions'], 0, ',', '.' ); ?></td>
                            <td style="padding:10px 12px;font-size:13px;text-align:right">
                                <span style="display:inline-block;background:<?php echo $row['position'] <= 3 ? '#d1fae5' : ( $row['position'] <= 10 ? '#fef3c7' : '#fee2e2' ); ?>;color:<?php echo $row['position'] <= 3 ? '#065f46' : ( $row['position'] <= 10 ? '#92400e' : '#991b1b' ); ?>;border-radius:4px;padding:2px 8px;font-weight:600;font-size:12px">
                                    <?php echo number_format( $row['position'], 1, ',', '' ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Top Seiten -->
                <?php if ( ! empty( $pages ) ) : ?>
                <h2 style="margin:0 0 16px;font-size:18px;font-weight:700;color:#1a1a2e">📄 Top Seiten</h2>
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:40px;border-collapse:collapse">
                    <thead>
                        <tr style="background:#f9fafb">
                            <th style="padding:10px 12px;text-align:left;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">#</th>
                            <th style="padding:10px 12px;text-align:left;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Seite</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Klicks</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Impr.</th>
                            <th style="padding:10px 12px;text-align:right;font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;font-weight:600;border-bottom:1px solid #e5e7eb">Pos.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_slice( $pages, 0, 8 ) as $i => $row ) :
                            $short = preg_replace( '#^https?://[^/]+#', '', $row['url'] );
                            $short = strlen( $short ) > 40 ? substr( $short, 0, 37 ) . '…' : ( $short ?: '/' );
                        ?>
                        <tr style="border-bottom:1px solid #f3f4f6">
                            <td style="padding:10px 12px;font-size:13px;color:#9ca3af"><?php echo $i + 1; ?></td>
                            <td style="padding:10px 12px;font-size:13px">
                                <a href="<?php echo esc_url( $row['url'] ); ?>" style="color:#1a1a2e;text-decoration:none;font-weight:500" target="_blank"><?php echo esc_html( $short ); ?></a>
                            </td>
                            <td style="padding:10px 12px;font-size:13px;color:#1a1a2e;font-weight:700;text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?></td>
                            <td style="padding:10px 12px;font-size:13px;color:#4b5563;text-align:right"><?php echo number_format( $row['impressions'], 0, ',', '.' ); ?></td>
                            <td style="padding:10px 12px;font-size:13px;text-align:right">
                                <span style="display:inline-block;background:<?php echo $row['position'] <= 3 ? '#d1fae5' : ( $row['position'] <= 10 ? '#fef3c7' : '#fee2e2' ); ?>;color:<?php echo $row['position'] <= 3 ? '#065f46' : ( $row['position'] <= 10 ? '#92400e' : '#991b1b' ); ?>;border-radius:4px;padding:2px 8px;font-weight:600;font-size:12px">
                                    <?php echo number_format( $row['position'], 1, ',', '' ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- CTA zum Dashboard -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px">
                    <tr>
                        <td align="center" style="background:#f9fafb;border-radius:8px;padding:24px">
                            <p style="margin:0 0 16px;font-size:14px;color:#4b5563">Alle Details findest du direkt im WordPress-Backend.</p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=medialab-seo-dashboard' ) ); ?>"
                               style="display:inline-block;background:#1a1a2e;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;font-size:14px">
                                SEO Dashboard öffnen →
                            </a>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="background:#f9fafb;border-radius:0 0 12px 12px;padding:24px 40px;text-align:center;border-top:1px solid #e5e7eb">
                <p style="margin:0 0 4px;font-size:13px;color:#9ca3af">
                    Daten aus der <strong style="color:#6b7280">Google Search Console</strong> · Generiert von <strong style="color:#6b7280"><?php echo esc_html( $agency ); ?></strong>
                </p>
                <p style="margin:0;font-size:12px;color:#d1d5db">
                    © <?php echo esc_html( $year ); ?> <?php echo esc_html( $agency ); ?> · Dieser Report wird automatisch wöchentlich gesendet.
                </p>
            </td>
        </tr>

    </table><!-- /Container -->

</td></tr>
</table>

</body>
</html>
    <?php
    return ob_get_clean();
}
