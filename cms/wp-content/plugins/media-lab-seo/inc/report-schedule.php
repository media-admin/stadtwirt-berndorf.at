<?php
/**
 * SEO Toolkit – Konfigurierbarer Report-Versandzeitpunkt
 *
 * Ermöglicht die Konfiguration von Wochentag, Uhrzeit und Zeitzone
 * für den wöchentlichen SEO-Report-Versand via WP-Cron.
 *
 * Option-Keys:
 *   medialab_seo_report_weekday   → 0 (Sonntag) bis 6 (Samstag), Default: 1 (Montag)
 *   medialab_seo_report_time      → "HH:MM", Default: "08:00"
 *   medialab_seo_report_timezone  → PHP-Timezone-String, Default: WP-Einstellung
 *
 * Integration: Diese Datei übernimmt die Cron-Registrierung.
 * In der Hauptdatei des SEO-Plugins (media-lab-seo.php) das bisherige
 * wp_schedule_event() für den Report-Cron durch mlt_schedule_report_cron()
 * ersetzen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Option-Keys
const MLT_REPORT_WEEKDAY_KEY  = 'medialab_seo_report_weekday';
const MLT_REPORT_TIME_KEY     = 'medialab_seo_report_time';
const MLT_REPORT_TIMEZONE_KEY = 'medialab_seo_report_timezone';

// Cron-Hook-Name (muss mit MLT_Report_Mailer übereinstimmen)
const MLT_REPORT_CRON_HOOK = 'mlt_send_weekly_report';

/**
 * Gibt die konfigurierten Schedule-Einstellungen zurück.
 *
 * @return array{ weekday: int, time: string, timezone: string }
 */
function mlt_get_report_schedule(): array {
	return array(
		'weekday'  => (int) get_option( MLT_REPORT_WEEKDAY_KEY, 1 ), // Montag
		'time'     => (string) get_option( MLT_REPORT_TIME_KEY, '08:00' ),
		'timezone' => (string) get_option( MLT_REPORT_TIMEZONE_KEY, wp_timezone_string() ),
	);
}

/**
 * Berechnet den nächsten Versand-Timestamp basierend auf den Einstellungen.
 *
 * @param  array $schedule Rückgabe von mlt_get_report_schedule()
 * @return int  Unix-Timestamp (UTC)
 */
function mlt_calculate_next_run( array $schedule ): int {
	$tz = new DateTimeZone( $schedule['timezone'] );

	// Aktuelle Zeit in der konfigurierten Zeitzone
	$now = new DateTime( 'now', $tz );

	// Nächsten Ziel-Wochentag + Uhrzeit berechnen
	list( $hour, $minute ) = array_map( 'intval', explode( ':', $schedule['time'] ) );
	$target_day = $schedule['weekday']; // 0=So, 1=Mo … 6=Sa

	$next = clone $now;
	$next->setTime( $hour, $minute, 0 );

	// Wochentag anpassen: PHP date('w') = 0 (So) bis 6 (Sa)
	$current_day = (int) $now->format( 'w' );
	$diff        = ( $target_day - $current_day + 7 ) % 7;

	if ( $diff === 0 && $next <= $now ) {
		// Heute, aber Zeitpunkt bereits vorbei → nächste Woche
		$diff = 7;
	}

	if ( $diff > 0 ) {
		$next->modify( "+{$diff} days" );
	}

	// Als UTC-Timestamp zurückgeben (WP-Cron arbeitet in UTC)
	return $next->getTimestamp();
}

/**
 * Cron-Job für den Report (de)registrieren.
 * Wird bei jeder Einstellungs-Änderung aufgerufen.
 */
function mlt_schedule_report_cron(): void {
	// Alten Job entfernen
	$timestamp = wp_next_scheduled( MLT_REPORT_CRON_HOOK );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, MLT_REPORT_CRON_HOOK );
	}

	$schedule = mlt_get_report_schedule();
	$next_run = mlt_calculate_next_run( $schedule );

	wp_schedule_event( $next_run, 'weekly', MLT_REPORT_CRON_HOOK );
}

/**
 * Nach dem Speichern der Settings den Cron neu planen.
 * Hook auf update_option für alle drei Option-Keys.
 */
foreach ( array( MLT_REPORT_WEEKDAY_KEY, MLT_REPORT_TIME_KEY, MLT_REPORT_TIMEZONE_KEY ) as $_mlt_key ) {
	add_action( "update_option_{$_mlt_key}", 'mlt_schedule_report_cron' );
	add_action( "add_option_{$_mlt_key}",    'mlt_schedule_report_cron' );
}

/**
 * Beim Plugin-Aktivieren: Cron initial einrichten (falls noch keiner läuft).
 */
function mlt_maybe_init_report_cron(): void {
	if ( ! wp_next_scheduled( MLT_REPORT_CRON_HOOK ) ) {
		mlt_schedule_report_cron();
	}
}
add_action( 'init', 'mlt_maybe_init_report_cron' );

/**
 * Rendert das Schedule-Feld-Set für die ACF-Options-Seite.
 *
 * Aufruf z.B. als ACF 'message'-Field oder direkt in settings_fields().
 */
function mlt_render_schedule_fields(): string {
	$schedule  = mlt_get_report_schedule();
	$weekdays  = array(
		1 => __( 'Montag',     'media-lab-seo' ),
		2 => __( 'Dienstag',   'media-lab-seo' ),
		3 => __( 'Mittwoch',   'media-lab-seo' ),
		4 => __( 'Donnerstag', 'media-lab-seo' ),
		5 => __( 'Freitag',    'media-lab-seo' ),
		6 => __( 'Samstag',    'media-lab-seo' ),
		0 => __( 'Sonntag',    'media-lab-seo' ),
	);

	// Zeitzonen-Liste: nur gängige europäische + UTC
	$timezones = array(
		'UTC'              => 'UTC',
		'Europe/Vienna'    => 'Wien (CET/CEST)',
		'Europe/Berlin'    => 'Berlin (CET/CEST)',
		'Europe/Zurich'    => 'Zürich (CET/CEST)',
		'Europe/London'    => 'London (GMT/BST)',
		'Europe/Paris'     => 'Paris (CET/CEST)',
		'America/New_York' => 'New York (ET)',
		'America/Chicago'  => 'Chicago (CT)',
		'America/Denver'   => 'Denver (MT)',
		'America/Los_Angeles' => 'Los Angeles (PT)',
	);

	// Nächsten Versandzeitpunkt berechnen und anzeigen
	$next_ts  = mlt_calculate_next_run( $schedule );
	$next_tz  = new DateTimeZone( $schedule['timezone'] );
	$next_dt  = new DateTime( '@' . $next_ts );
	$next_dt->setTimezone( $next_tz );
	$next_str = $next_dt->format( 'd.m.Y H:i' ) . ' ' . $schedule['timezone'];

	ob_start();
	?>
	<div id="mlt-schedule-wrap" style="max-width:540px;">
		<table class="form-table" role="presentation" style="margin:0;">
			<tr>
				<th scope="row" style="padding-left:0;width:160px;">
					<label for="mlt_report_weekday">
						<?php esc_html_e( 'Wochentag', 'media-lab-seo' ); ?>
					</label>
				</th>
				<td>
					<select name="<?php echo MLT_REPORT_WEEKDAY_KEY; ?>"
						id="mlt_report_weekday" class="regular-text">
						<?php foreach ( $weekdays as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>"
							<?php selected( $schedule['weekday'], $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row" style="padding-left:0;">
					<label for="mlt_report_time">
						<?php esc_html_e( 'Uhrzeit', 'media-lab-seo' ); ?>
					</label>
				</th>
				<td>
					<input type="time"
						name="<?php echo MLT_REPORT_TIME_KEY; ?>"
						id="mlt_report_time"
						value="<?php echo esc_attr( $schedule['time'] ); ?>"
						step="300"
						class="regular-text">
					<p class="description">
						<?php esc_html_e( 'Format: HH:MM (5-Minuten-Schritte)', 'media-lab-seo' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row" style="padding-left:0;">
					<label for="mlt_report_timezone">
						<?php esc_html_e( 'Zeitzone', 'media-lab-seo' ); ?>
					</label>
				</th>
				<td>
					<select name="<?php echo MLT_REPORT_TIMEZONE_KEY; ?>"
						id="mlt_report_timezone" class="regular-text">
						<?php foreach ( $timezones as $tz_key => $tz_label ) : ?>
						<option value="<?php echo esc_attr( $tz_key ); ?>"
							<?php selected( $schedule['timezone'], $tz_key ); ?>>
							<?php echo esc_html( $tz_label ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<p class="description" style="margin-top:10px;padding:8px 12px;background:#f6f7f7;border-left:3px solid #2271b1;">
			<?php printf(
				/* translators: %s: Datum + Zeit des nächsten Versands */
				esc_html__( 'Nächster geplanter Versand: %s', 'media-lab-seo' ),
				'<strong>' . esc_html( $next_str ) . '</strong>'
			); ?>
		</p>
	</div>
	<?php
	return ob_get_clean();
}
