<?php
/**
 * SEO Toolkit – Report-Empfänger: Dynamische Liste
 *
 * Ersetzt das einzelne E-Mail-Feld für Report-Empfänger durch eine
 * dynamische Liste (add/remove via JS). Werte werden als JSON-Array
 * in wp_options unter 'medialab_seo_report_recipients' gespeichert.
 *
 * Integration in MLT_Report_Mailer: get_recipients() statt altem
 * get_option('medialab_seo_report_email') verwenden.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MLT_REPORT_RECIPIENTS_KEY', 'medialab_seo_report_recipients' );

/**
 * Gibt validierte Empfänger-Adressen als Array zurück.
 * Direkt als $to in wp_mail() verwendbar.
 *
 * @return string[]
 */
function mlt_get_report_recipients(): array {
	$raw = get_option( MLT_REPORT_RECIPIENTS_KEY, array() );

	if ( ! is_array( $raw ) ) {
		// Migration: altes Einzel-Feld (String) übernehmen
		$old = get_option( 'medialab_seo_report_email', '' );
		if ( $old && is_email( $old ) ) {
			return array( sanitize_email( $old ) );
		}
		return array();
	}

	return array_values(
		array_filter(
			array_map( 'sanitize_email', $raw ),
			'is_email'
		)
	);
}

/**
 * Speichert die Empfänger-Liste.
 *
 * @param  array $input Raw-Array aus POST
 * @return bool
 */
function mlt_save_report_recipients( array $input ): bool {
	$clean = array_values(
		array_filter(
			array_map( 'sanitize_email', $input ),
			'is_email'
		)
	);

	return update_option( MLT_REPORT_RECIPIENTS_KEY, $clean );
}

/**
 * Rendert das Empfänger-Feld für die ACF-Options-Seite (custom HTML-Field).
 * Aufruf in seo-settings.php innerhalb von acf_add_local_field():
 *   'type'            => 'message',
 *   'message'         => mlt_render_recipients_field(),
 *
 * Oder als eigener Settings-Block auf der Options-Page.
 */
function mlt_render_recipients_field(): string {
	$recipients = mlt_get_report_recipients();
	$key        = MLT_REPORT_RECIPIENTS_KEY;

	ob_start();
	?>
	<div id="mlt-recipients-wrap" style="max-width:480px;">
		<div id="mlt-recipients-list">
			<?php if ( empty( $recipients ) ) : ?>
				<div class="mlt-recipient-row" style="display:flex;gap:8px;margin-bottom:6px;">
					<input type="email"
						name="<?php echo esc_attr( $key ); ?>[]"
						class="regular-text mlt-recipient-input"
						placeholder="empfaenger@example.com"
						value="">
					<button type="button" class="button mlt-recipient-remove"
						title="<?php esc_attr_e( 'Entfernen', 'media-lab-seo' ); ?>">✕</button>
				</div>
			<?php else : ?>
				<?php foreach ( $recipients as $email ) : ?>
				<div class="mlt-recipient-row" style="display:flex;gap:8px;margin-bottom:6px;">
					<input type="email"
						name="<?php echo esc_attr( $key ); ?>[]"
						class="regular-text mlt-recipient-input"
						placeholder="empfaenger@example.com"
						value="<?php echo esc_attr( $email ); ?>">
					<button type="button" class="button mlt-recipient-remove"
						title="<?php esc_attr_e( 'Entfernen', 'media-lab-seo' ); ?>">✕</button>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<button type="button" id="mlt-recipient-add" class="button button-secondary">
			+ <?php esc_html_e( 'Empfänger hinzufügen', 'media-lab-seo' ); ?>
		</button>
		<p class="description" style="margin-top:6px;">
			<?php esc_html_e( 'Der wöchentliche SEO-Report wird an alle eingetragenen Adressen versendet.', 'media-lab-seo' ); ?>
		</p>
	</div>
	<script>
	(function($){
		$(function(){
			var $list   = $('#mlt-recipients-list');
			var key     = '<?php echo esc_js( $key ); ?>';
			var rowTpl  = '<div class="mlt-recipient-row" style="display:flex;gap:8px;margin-bottom:6px;">'
				+ '<input type="email" name="' + key + '[]" class="regular-text mlt-recipient-input" placeholder="empfaenger@example.com" value="">'
				+ '<button type="button" class="button mlt-recipient-remove" title="Entfernen">✕</button>'
				+ '</div>';

			$('#mlt-recipient-add').on('click', function(){
				$list.append(rowTpl);
				$list.find('.mlt-recipient-input').last().focus();
			});

			$list.on('click', '.mlt-recipient-remove', function(){
				var $rows = $list.find('.mlt-recipient-row');
				if ( $rows.length > 1 ) {
					$(this).closest('.mlt-recipient-row').remove();
				} else {
					// Letztes Feld leeren statt entfernen
					$(this).closest('.mlt-recipient-row').find('input').val('');
				}
			});
		});
	}(jQuery));
	</script>
	<?php
	return ob_get_clean();
}
