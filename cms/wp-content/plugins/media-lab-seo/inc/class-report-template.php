<?php
/**
 * MLT_Report_Template
 *
 * Erstellt das vollständige HTML-E-Mail-Template für den wöchentlichen SEO-Report.
 * Inline-CSS für maximale E-Mail-Client-Kompatibilität.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MLT_Report_Template {

    public static function build( array $data ) : string {
        $site      = get_bloginfo( 'name' );
        $url       = home_url( '/' );
        $week      = wp_date( 'W' );
        $year      = wp_date( 'Y' );
        $date_from = wp_date( 'd.m.Y', strtotime( '-28 days' ) );
        $date_to   = wp_date( 'd.m.Y', strtotime( '-2 days' ) );

        $gsc      = $data['gsc_overview']   ?? [];
        $queries  = $data['gsc_queries']    ?? [];
        $pages    = $data['gsc_pages']      ?? [];
        $a_over   = $data['analytics']      ?? [];
        $a_src    = $data['analytics_sources'] ?? [];

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SEO Report KW <?php echo (int) $week; ?>/<?php echo (int) $year; ?></title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:32px 16px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">

    <!-- Header -->
    <tr>
        <td style="background:#1a1a2e;border-radius:8px 8px 0 0;padding:28px 32px">
            <h1 style="margin:0 0 4px;font-size:22px;font-weight:700;color:#fff"><?php echo esc_html( $site ); ?></h1>
            <p style="margin:0;font-size:13px;color:#9ca3af">
                Wöchentlicher SEO-Report &nbsp;·&nbsp; KW <?php echo (int) $week; ?>/<?php echo (int) $year; ?>
                &nbsp;·&nbsp; <?php echo esc_html( $date_from ); ?> – <?php echo esc_html( $date_to ); ?>
            </p>
        </td>
    </tr>

    <?php if ( ! empty( $gsc ) ) : ?>
    <!-- GSC KPIs -->
    <tr>
        <td style="background:#fff;padding:24px 32px 8px">
            <p style="margin:0 0 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">Google Search Console</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <?php self::kpi_cell( 'Klicks',       number_format( $gsc['clicks'] ?? 0,       0, ',', '.' ), '#2563eb' ); ?>
                    <?php self::kpi_cell( 'Impressionen', number_format( $gsc['impressions'] ?? 0,  0, ',', '.' ), '#7c3aed' ); ?>
                    <?php self::kpi_cell( 'Ø CTR',        ( $gsc['ctr']      ?? 0 ) . '%',           '#16a34a' ); ?>
                    <?php self::kpi_cell( 'Ø Position',   $gsc['position']   ?? '–',                '#d97706' ); ?>
                </tr>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $a_over ) ) : ?>
    <!-- Analytics KPIs -->
    <tr>
        <td style="background:#fff;padding:8px 32px 24px">
            <p style="margin:0 0 16px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">Analytics</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <?php self::kpi_cell( 'Seitenaufrufe', number_format( $a_over['pageviews'] ?? 0, 0, ',', '.' ), '#0891b2' ); ?>
                    <?php self::kpi_cell( 'Sessions',      number_format( $a_over['sessions']  ?? 0, 0, ',', '.' ), '#0284c7' ); ?>
                    <?php self::kpi_cell( 'Nutzer',        number_format( $a_over['users']     ?? 0, 0, ',', '.' ), '#7c3aed' ); ?>
                    <td width="25%"></td>
                </tr>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $queries ) ) : ?>
    <!-- Top Keywords -->
    <tr>
        <td style="background:#fff;padding:0 32px 24px">
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">🔑 Top Keywords</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr style="background:#f9fafb">
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">#</th>
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Keyword</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Klicks</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Impr.</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Pos.</th>
                </tr>
                <?php foreach ( array_slice( $queries, 0, 8 ) as $i => $row ) : ?>
                <tr style="background:<?php echo $i % 2 === 0 ? '#fff' : '#f9fafb'; ?>">
                    <td style="padding:8px 10px;font-size:13px;color:#9ca3af"><?php echo $i + 1; ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#374151"><?php echo esc_html( $row['query'] ); ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#374151;text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#6b7280;text-align:right"><?php echo number_format( $row['impressions'], 0, ',', '.' ); ?></td>
                    <td style="padding:8px 10px;text-align:right">
                        <?php
                        $pos = $row['position'];
                        $bg  = $pos <= 3 ? '#d1fae5' : ( $pos <= 10 ? '#fef3c7' : '#fee2e2' );
                        $fg  = $pos <= 3 ? '#065f46' : ( $pos <= 10 ? '#92400e' : '#991b1b' );
                        ?>
                        <span style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;border-radius:4px;padding:2px 7px;font-size:12px;font-weight:600">
                            <?php echo number_format( $pos, 1, ',', '' ); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $pages ) ) : ?>
    <!-- Top Seiten -->
    <tr>
        <td style="background:#fff;padding:0 32px 24px">
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">📄 Top Seiten</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr style="background:#f9fafb">
                    <th style="padding:8px 10px;text-align:left;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Seite</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Klicks</th>
                    <th style="padding:8px 10px;text-align:right;font-size:11px;color:#9ca3af;font-weight:600;border-bottom:1px solid #e5e7eb">Pos.</th>
                </tr>
                <?php foreach ( array_slice( $pages, 0, 8 ) as $i => $row ) :
                    $short = preg_replace( '#^https?://[^/]+#', '', $row['url'] );
                    $short = strlen( $short ) > 45 ? substr( $short, 0, 42 ) . '…' : ( $short ?: '/' );
                    $pos   = $row['position'];
                    $bg    = $pos <= 3 ? '#d1fae5' : ( $pos <= 10 ? '#fef3c7' : '#fee2e2' );
                    $fg    = $pos <= 3 ? '#065f46' : ( $pos <= 10 ? '#92400e' : '#991b1b' );
                ?>
                <tr style="background:<?php echo $i % 2 === 0 ? '#fff' : '#f9fafb'; ?>">
                    <td style="padding:8px 10px;font-size:12px;color:#2563eb">
                        <a href="<?php echo esc_url( $row['url'] ); ?>" style="color:#2563eb;text-decoration:none"><?php echo esc_html( $short ); ?></a>
                    </td>
                    <td style="padding:8px 10px;font-size:13px;color:#374151;text-align:right"><?php echo number_format( $row['clicks'], 0, ',', '.' ); ?></td>
                    <td style="padding:8px 10px;text-align:right">
                        <span style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;border-radius:4px;padding:2px 7px;font-size:12px;font-weight:600">
                            <?php echo number_format( $pos, 1, ',', '' ); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <?php if ( ! empty( $a_src ) ) : ?>
    <!-- Traffic-Quellen -->
    <tr>
        <td style="background:#fff;padding:0 32px 24px">
            <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 20px">
            <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px">📡 Traffic-Quellen</p>
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <?php foreach ( $a_src as $i => $row ) : ?>
                <tr style="background:<?php echo $i % 2 === 0 ? '#fff' : '#f9fafb'; ?>">
                    <td style="padding:8px 10px;font-size:13px;color:#374151"><?php echo esc_html( $row['source'] ); ?></td>
                    <td style="padding:8px 10px;font-size:13px;color:#6b7280;text-align:right"><?php echo number_format( $row['sessions'], 0, ',', '.' ); ?> Sessions</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Footer -->
    <tr>
        <td style="background:#f9fafb;border-radius:0 0 8px 8px;padding:20px 32px;border-top:1px solid #e5e7eb">
            <p style="margin:0;font-size:12px;color:#9ca3af">
                Dieser Report wird automatisch von
                <a href="<?php echo esc_url( $url ); ?>" style="color:#6b7280"><?php echo esc_html( $site ); ?></a>
                via <strong>Media Lab SEO Toolkit</strong> gesendet. &nbsp;·&nbsp;
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=media-lab-toolkit' ) ); ?>" style="color:#6b7280">Report deaktivieren</a>
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    private static function kpi_cell( string $label, $value, string $color ) {
        echo '<td width="25%" style="text-align:center;padding:0 6px 16px">';
        echo '<div style="background:#f9fafb;border-radius:6px;padding:14px 8px">';
        echo '<div style="font-size:22px;font-weight:700;color:' . esc_attr( $color ) . '">' . esc_html( $value ) . '</div>';
        echo '<div style="font-size:11px;color:#9ca3af;margin-top:3px">' . esc_html( $label ) . '</div>';
        echo '</div></td>';
    }
}
