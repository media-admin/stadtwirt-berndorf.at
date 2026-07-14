<?php
/**
 * Core Plugin – SMTP / OAuth Mailer
 *
 * Erweitert die bestehenden SMTP-Einstellungen um zwei OAuth-Provider:
 *   - Microsoft 365 / Azure AD (OAuth2, Client Credentials + Auth Code)
 *   - Google Workspace (OAuth2, analog zu GSC/GA4 im SEO-Toolkit)
 *
 * Option-Key für den Modus: medialab_smtp_mode
 *   'smtp'        → klassisch (bisheriges Verhalten, unverändert)
 *   'ms365'       → Microsoft 365 OAuth2
 *   'google'      → Google Workspace OAuth2
 *
 * Alle OAuth-Tokens werden NICHT in der settings-Gruppe registriert
 * (würden sonst bei jedem Form-Submit überschrieben).
 *
 * Abhängigkeiten: PHPMailer (WP-Core), wp_remote_post für Token-Requests.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------------------------
// Option-Keys
// -------------------------------------------------------------------
const MEDIALAB_SMTP_MODE_KEY = 'medialab_smtp_mode';

// Microsoft 365
const MEDIALAB_MS365_TENANT_KEY  = 'medialab_ms365_tenant_id';
const MEDIALAB_MS365_CLIENT_KEY  = 'medialab_ms365_client_id';
const MEDIALAB_MS365_SECRET_KEY  = 'medialab_ms365_client_secret';
const MEDIALAB_MS365_SENDER_KEY  = 'medialab_ms365_sender_email';
const MEDIALAB_MS365_TOKEN_KEY   = 'medialab_ms365_token';   // NICHT in register_settings()!

// Google Workspace
const MEDIALAB_GWS_CLIENT_KEY    = 'medialab_gws_client_id';
const MEDIALAB_GWS_SECRET_KEY    = 'medialab_gws_client_secret';
const MEDIALAB_GWS_SENDER_KEY    = 'medialab_gws_sender_email';
const MEDIALAB_GWS_TOKEN_KEY     = 'medialab_gws_token';     // NICHT in register_settings()!

class MediaLab_SMTP_OAuth {

	public function __construct() {
		add_action( 'phpmailer_init',   array( $this, 'configure_mailer' ) );
		add_action( 'admin_init',       array( $this, 'register_settings' ) );
		add_action( 'admin_post_medialab_ms365_connect',    array( $this, 'handle_ms365_oauth_callback' ) );
		add_action( 'admin_post_medialab_gws_connect',      array( $this, 'handle_gws_oauth_callback' ) );
		add_action( 'admin_post_medialab_ms365_disconnect', array( $this, 'handle_ms365_disconnect' ) );
		add_action( 'admin_post_medialab_gws_disconnect',   array( $this, 'handle_gws_disconnect' ) );
	}

	// -------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------

	public function register_settings(): void {
		// Modus
		register_setting( 'medialab_smtp_group', MEDIALAB_SMTP_MODE_KEY, array(
			'type'              => 'string',
			'sanitize_callback' => function( $v ) {
				return in_array( $v, array( 'smtp', 'ms365', 'google' ), true ) ? $v : 'smtp';
			},
			'default' => 'smtp',
		) );

		// Microsoft 365
		foreach ( array(
			MEDIALAB_MS365_TENANT_KEY,
			MEDIALAB_MS365_CLIENT_KEY,
			MEDIALAB_MS365_SENDER_KEY,
		) as $key ) {
			register_setting( 'medialab_smtp_group', $key, array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			) );
		}
		register_setting( 'medialab_smtp_group', MEDIALAB_MS365_SECRET_KEY, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field', // Passwort-Felder nicht sanitizen (Zeichen gehen verloren)
			'default'           => '',
		) );

		// Google Workspace
		foreach ( array(
			MEDIALAB_GWS_CLIENT_KEY,
			MEDIALAB_GWS_SENDER_KEY,
		) as $key ) {
			register_setting( 'medialab_smtp_group', $key, array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			) );
		}
		register_setting( 'medialab_smtp_group', MEDIALAB_GWS_SECRET_KEY, array(
			'type'    => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );

		// Token-Keys NICHT registrieren → werden nie überschrieben
	}

	// -------------------------------------------------------------------
	// PHPMailer konfigurieren
	// -------------------------------------------------------------------

	public function configure_mailer( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$mode = get_option( MEDIALAB_SMTP_MODE_KEY, 'smtp' );

		match ( $mode ) {
			'ms365'  => $this->configure_ms365( $mailer ),
			'google' => $this->configure_gws( $mailer ),
			default  => null, // 'smtp' → bestehende SMTP-Logik übernimmt
		};
	}

	// -------------------------------------------------------------------
	// Microsoft 365 OAuth2
	// -------------------------------------------------------------------

	private function configure_ms365( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$token = $this->ms365_get_valid_token();

		if ( ! $token ) {
			// Kein Token → Fallback auf normalen SMTP oder silent fail
			return;
		}

		$sender = get_option( MEDIALAB_MS365_SENDER_KEY, '' );

		$mailer->isSMTP();
		$mailer->Host       = 'smtp.office365.com';
		$mailer->Port       = 587;
		$mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		$mailer->SMTPAuth   = true;
		$mailer->AuthType   = 'XOAUTH2';
		$mailer->Username   = $sender;
		$mailer->Password   = $token;

		if ( $sender ) {
			$mailer->setFrom( $sender );
		}
	}

	/**
	 * Token via Client Credentials Flow holen (App-to-App, kein User-Login nötig).
	 * Benötigt: Mail.Send Application Permission in Azure AD.
	 *
	 * @return string|null Access Token oder null bei Fehler
	 */
	private function ms365_get_valid_token(): ?string {
		$cached = get_option( MEDIALAB_MS365_TOKEN_KEY, array() );

		// Noch gültiges Token im Cache?
		if ( ! empty( $cached['access_token'] ) && ! empty( $cached['expires_at'] ) ) {
			if ( time() < ( $cached['expires_at'] - 60 ) ) {
				return $cached['access_token'];
			}
		}

		// Neues Token holen
		return $this->ms365_fetch_token();
	}

	private function ms365_fetch_token(): ?string {
		$tenant    = get_option( MEDIALAB_MS365_TENANT_KEY, '' );
		$client_id = get_option( MEDIALAB_MS365_CLIENT_KEY, '' );
		$secret    = get_option( MEDIALAB_MS365_SECRET_KEY, '' );

		if ( ! $tenant || ! $client_id || ! $secret ) {
			return null;
		}

		$url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $client_id,
				'client_secret' => $secret,
				'scope'         => 'https://outlook.office365.com/.default',
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[MediaLab SMTP] MS365 Token-Fehler: ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			error_log( '[MediaLab SMTP] MS365 Token-Response ohne access_token: ' . wp_remote_retrieve_body( $response ) );
			return null;
		}

		// Token cachen
		update_option( MEDIALAB_MS365_TOKEN_KEY, array(
			'access_token' => $body['access_token'],
			'expires_at'   => time() + (int) ( $body['expires_in'] ?? 3600 ),
		), false );

		return $body['access_token'];
	}

	public function handle_ms365_disconnect(): void {
		check_admin_referer( 'medialab_ms365_disconnect' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		delete_option( MEDIALAB_MS365_TOKEN_KEY );
		wp_redirect( admin_url( 'options-general.php?page=medialab-smtp&ms365_disconnected=1' ) );
		exit;
	}

	// -------------------------------------------------------------------
	// Google Workspace OAuth2
	// -------------------------------------------------------------------

	private function configure_gws( \PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$token = $this->gws_get_valid_token();

		if ( ! $token ) {
			return;
		}

		$sender = get_option( MEDIALAB_GWS_SENDER_KEY, '' );

		$mailer->isSMTP();
		$mailer->Host       = 'smtp.gmail.com';
		$mailer->Port       = 587;
		$mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		$mailer->SMTPAuth   = true;
		$mailer->AuthType   = 'XOAUTH2';
		$mailer->Username   = $sender;
		$mailer->Password   = $token;

		if ( $sender ) {
			$mailer->setFrom( $sender );
		}
	}

	private function gws_get_valid_token(): ?string {
		$cached = get_option( MEDIALAB_GWS_TOKEN_KEY, array() );

		if ( ! empty( $cached['access_token'] ) && ! empty( $cached['expires_at'] ) ) {
			if ( time() < ( $cached['expires_at'] - 60 ) ) {
				return $cached['access_token'];
			}
		}

		// Refresh Token vorhanden → neues Access Token holen
		if ( ! empty( $cached['refresh_token'] ) ) {
			return $this->gws_refresh_token( $cached['refresh_token'] );
		}

		return null;
	}

	private function gws_refresh_token( string $refresh_token ): ?string {
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'body'    => array(
				'client_id'     => get_option( MEDIALAB_GWS_CLIENT_KEY, '' ),
				'client_secret' => get_option( MEDIALAB_GWS_SECRET_KEY, '' ),
				'refresh_token' => $refresh_token,
				'grant_type'    => 'refresh_token',
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[MediaLab SMTP] GWS Refresh-Fehler: ' . $response->get_error_message() );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			error_log( '[MediaLab SMTP] GWS Refresh ohne access_token' );
			return null;
		}

		$cached = get_option( MEDIALAB_GWS_TOKEN_KEY, array() );
		$cached['access_token'] = $body['access_token'];
		$cached['expires_at']   = time() + (int) ( $body['expires_in'] ?? 3600 );
		update_option( MEDIALAB_GWS_TOKEN_KEY, $cached, false );

		return $body['access_token'];
	}

	/**
	 * OAuth2 Authorization Code Flow für Google Workspace.
	 * Redirect-URI: admin_url('admin-post.php?action=medialab_gws_connect')
	 */
	public function handle_gws_oauth_callback(): void {
		if ( isset( $_GET['code'] ) ) {
			check_admin_referer( 'medialab_gws_connect', 'state' );
			if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

			$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
				'timeout' => 15,
				'body'    => array(
					'code'          => sanitize_text_field( $_GET['code'] ),
					'client_id'     => get_option( MEDIALAB_GWS_CLIENT_KEY, '' ),
					'client_secret' => get_option( MEDIALAB_GWS_SECRET_KEY, '' ),
					'redirect_uri'  => $this->gws_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			) );

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! empty( $body['access_token'] ) ) {
				update_option( MEDIALAB_GWS_TOKEN_KEY, array(
					'access_token'  => $body['access_token'],
					'refresh_token' => $body['refresh_token'] ?? '',
					'expires_at'    => time() + (int) ( $body['expires_in'] ?? 3600 ),
				), false );

				wp_redirect( admin_url( 'options-general.php?page=medialab-smtp&gws_connected=1' ) );
				exit;
			}

			wp_redirect( admin_url( 'options-general.php?page=medialab-smtp&gws_error=1' ) );
			exit;
		}

		// Initialer Auth-Redirect
		check_admin_referer( 'medialab_gws_authorize' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		$state = wp_create_nonce( 'medialab_gws_connect' );
		$url   = add_query_arg( array(
			'client_id'     => get_option( MEDIALAB_GWS_CLIENT_KEY, '' ),
			'redirect_uri'  => urlencode( $this->gws_redirect_uri() ),
			'response_type' => 'code',
			'scope'         => urlencode( 'https://mail.google.com/' ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		), 'https://accounts.google.com/o/oauth2/v2/auth' );

		wp_redirect( $url );
		exit;
	}

	private function gws_redirect_uri(): string {
		return admin_url( 'admin-post.php?action=medialab_gws_connect' );
	}

	public function handle_gws_disconnect(): void {
		check_admin_referer( 'medialab_gws_disconnect' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Keine Berechtigung' );

		delete_option( MEDIALAB_GWS_TOKEN_KEY );
		wp_redirect( admin_url( 'options-general.php?page=medialab-smtp&gws_disconnected=1' ) );
		exit;
	}

	// MS365: kein Auth-Code-Flow nötig (Client Credentials reicht für SMTP)
	public function handle_ms365_oauth_callback(): void {} // Placeholder

	// -------------------------------------------------------------------
	// Settings-Seiten-Rendering (Tab-Erweiterung)
	// -------------------------------------------------------------------

	/**
	 * Rendert den OAuth-Abschnitt auf der SMTP-Settings-Seite.
	 * Aufruf in der bestehenden render_smtp_settings_page() nach dem SMTP-Formular.
	 */
	public function render_oauth_section(): void {
		$mode       = get_option( MEDIALAB_SMTP_MODE_KEY, 'smtp' );
		$ms365_tok  = get_option( MEDIALAB_MS365_TOKEN_KEY, array() );
		$gws_tok    = get_option( MEDIALAB_GWS_TOKEN_KEY, array() );
		$ms365_conn = ! empty( $ms365_tok['access_token'] );
		$gws_conn   = ! empty( $gws_tok['refresh_token'] );
		?>
		<hr>
		<h2><?php esc_html_e( 'E-Mail-Versand Modus', 'media-lab-core' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'medialab_smtp_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Versand über', 'media-lab-core' ); ?></th>
					<td>
						<fieldset>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="<?php echo MEDIALAB_SMTP_MODE_KEY; ?>"
									value="smtp" <?php checked( $mode, 'smtp' ); ?>>
								<?php esc_html_e( 'Klassisches SMTP (bisherige Einstellungen)', 'media-lab-core' ); ?>
							</label>
							<label style="display:block;margin-bottom:6px;">
								<input type="radio" name="<?php echo MEDIALAB_SMTP_MODE_KEY; ?>"
									value="ms365" <?php checked( $mode, 'ms365' ); ?>>
								<?php esc_html_e( 'Microsoft 365 / Exchange Online (OAuth2)', 'media-lab-core' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="<?php echo MEDIALAB_SMTP_MODE_KEY; ?>"
									value="google" <?php checked( $mode, 'google' ); ?>>
								<?php esc_html_e( 'Google Workspace (OAuth2)', 'media-lab-core' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>

			<!-- Microsoft 365 -->
			<div id="mlt-ms365-section" <?php echo $mode !== 'ms365' ? 'style="display:none;"' : ''; ?>>
				<h3><?php esc_html_e( 'Microsoft 365 – Azure AD App', 'media-lab-core' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Benötigt: Azure AD App Registration mit "Mail.Send" Application Permission (nicht Delegated). Client Credentials Flow – kein User-Login erforderlich.', 'media-lab-core' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ms365_tenant"><?php esc_html_e( 'Tenant ID', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="text" id="ms365_tenant"
								name="<?php echo MEDIALAB_MS365_TENANT_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_MS365_TENANT_KEY, '' ) ); ?>"
								class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
						</td>
					</tr>
					<tr>
						<th><label for="ms365_client"><?php esc_html_e( 'Client ID (App ID)', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="text" id="ms365_client"
								name="<?php echo MEDIALAB_MS365_CLIENT_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_MS365_CLIENT_KEY, '' ) ); ?>"
								class="regular-text">
						</td>
					</tr>
					<tr>
						<th><label for="ms365_secret"><?php esc_html_e( 'Client Secret', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="password" id="ms365_secret"
								name="<?php echo MEDIALAB_MS365_SECRET_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_MS365_SECRET_KEY, '' ) ); ?>"
								class="regular-text" autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th><label for="ms365_sender"><?php esc_html_e( 'Absender-E-Mail', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="email" id="ms365_sender"
								name="<?php echo MEDIALAB_MS365_SENDER_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_MS365_SENDER_KEY, '' ) ); ?>"
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Muss ein gültiges Postfach in deinem Microsoft 365 Tenant sein.', 'media-lab-core' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'media-lab-core' ); ?></th>
						<td>
							<?php if ( $ms365_conn ) : ?>
								<span style="color:#46b450;">✓ <?php esc_html_e( 'Verbunden – Token vorhanden', 'media-lab-core' ); ?></span>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=medialab_ms365_disconnect' ), 'medialab_ms365_disconnect' ); ?>"
									class="button button-secondary" style="margin-left:10px;">
									<?php esc_html_e( 'Verbindung trennen', 'media-lab-core' ); ?>
								</a>
							<?php else : ?>
								<span style="color:#dc3232;">✗ <?php esc_html_e( 'Kein Token – wird beim ersten Versand automatisch geholt', 'media-lab-core' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<!-- Google Workspace -->
			<div id="mlt-gws-section" <?php echo $mode !== 'google' ? 'style="display:none;"' : ''; ?>>
				<h3><?php esc_html_e( 'Google Workspace – OAuth2', 'media-lab-core' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Google Cloud Console → OAuth 2.0 Client ID (Web Application). Scope: https://mail.google.com/', 'media-lab-core' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="gws_client"><?php esc_html_e( 'Client ID', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="text" id="gws_client"
								name="<?php echo MEDIALAB_GWS_CLIENT_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_GWS_CLIENT_KEY, '' ) ); ?>"
								class="regular-text">
						</td>
					</tr>
					<tr>
						<th><label for="gws_secret"><?php esc_html_e( 'Client Secret', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="password" id="gws_secret"
								name="<?php echo MEDIALAB_GWS_SECRET_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_GWS_SECRET_KEY, '' ) ); ?>"
								class="regular-text" autocomplete="new-password">
						</td>
					</tr>
					<tr>
						<th><label for="gws_sender"><?php esc_html_e( 'Absender-E-Mail', 'media-lab-core' ); ?></label></th>
						<td>
							<input type="email" id="gws_sender"
								name="<?php echo MEDIALAB_GWS_SENDER_KEY; ?>"
								value="<?php echo esc_attr( get_option( MEDIALAB_GWS_SENDER_KEY, '' ) ); ?>"
								class="regular-text">
						</td>
					</tr>
					<tr>
						<th><label for="gws_redirect"><?php esc_html_e( 'Redirect URI', 'media-lab-core' ); ?></label></th>
						<td>
							<code><?php echo esc_html( admin_url( 'admin-post.php?action=medialab_gws_connect' ) ); ?></code>
							<p class="description">
								<?php esc_html_e( 'Diese URI in der Google Cloud Console als autorisierte Redirect URI eintragen.', 'media-lab-core' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Status', 'media-lab-core' ); ?></th>
						<td>
							<?php if ( $gws_conn ) : ?>
								<span style="color:#46b450;">✓ <?php esc_html_e( 'Verbunden', 'media-lab-core' ); ?></span>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=medialab_gws_disconnect' ), 'medialab_gws_disconnect' ); ?>"
									class="button button-secondary" style="margin-left:10px;">
									<?php esc_html_e( 'Verbindung trennen', 'media-lab-core' ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=medialab_gws_connect' ), 'medialab_gws_authorize' ); ?>"
									class="button button-primary">
									<?php esc_html_e( 'Mit Google Workspace verbinden', 'media-lab-core' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button( __( 'Einstellungen speichern', 'media-lab-core' ) ); ?>
		</form>

		<script>
		(function($){
			$('input[name="<?php echo MEDIALAB_SMTP_MODE_KEY; ?>"]').on('change', function(){
				$('#mlt-ms365-section, #mlt-gws-section').hide();
				if ( this.value === 'ms365' )  $('#mlt-ms365-section').show();
				if ( this.value === 'google' ) $('#mlt-gws-section').show();
			});
		}(jQuery));
		</script>
		<?php
	}
}