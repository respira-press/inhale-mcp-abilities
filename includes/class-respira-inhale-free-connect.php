<?php
/**
 * Free Respira account connection: a weekly known-vulnerability check of this
 * site, plus links to the free accessibility scan, for Respira's free plugins.
 *
 * Nothing leaves this site until an administrator clicks Connect and approves
 * on respira.press. After that, once a week (and on "Check now") the plugin
 * sends respira.press the site address and the versions of WordPress, the
 * installed plugins and the installed themes, and gets back which of them have
 * known vulnerabilities in the Wordfence Intelligence database. No content,
 * users, orders or personal data are sent. Disconnect deletes the token here
 * and on respira.press. The readme lists all of this under External services.
 *
 * Inhale and Respira ARC ship the same code under their own class names and
 * share one connection (the options below), so connecting in one connects
 * both, and the weekly check runs once.
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Respira_Inhale_Free_Connect' ) ) :

	/**
	 * Respira_Inhale_Free_Connect: connect, check, disconnect and the card that shows them.
	 */
	class Respira_Inhale_Free_Connect {

		const PLUGIN      = 'inhale';
		const API         = 'https://www.respira.press';
		const OPT_CONN    = 'respira_free_connection';
		const OPT_RESULT  = 'respira_free_site_check';
		const CRON_HOOK   = 'respira_free_site_check';
		const STATE_TTL   = 600;
		const CAPABILITY  = 'manage_options';

		/**
		 * Wire hooks once per plugin copy.
		 */
		public static function init() {
			add_action( 'admin_post_respira_inhale_free_connect', array( self::class, 'start_connect' ) );
			add_action( 'admin_post_respira_inhale_free_check', array( self::class, 'manual_check' ) );
			add_action( 'admin_post_respira_inhale_free_disconnect', array( self::class, 'disconnect' ) );
			add_action( 'admin_init', array( self::class, 'maybe_finish_connect' ) );
			add_action( 'admin_init', array( self::class, 'maybe_schedule' ) );
			add_action( self::CRON_HOOK, array( self::class, 'cron_check' ) );
		}

		/** The API base, filterable for staging. */
		private static function api( $path ) {
			$base = apply_filters( 'respira_free_connect_api', self::API );
			return untrailingslashit( $base ) . $path;
		}

		/** The plugin screen the handshake returns to. */
		public static function page_url() {
			return admin_url( 'options-general.php?page=inhale-mcp-abilities' );
		}

		/** @return array|null The stored connection, or null. */
		public static function connection() {
			$conn = get_option( self::OPT_CONN );
			return ( is_array( $conn ) && ! empty( $conn['token'] ) ) ? $conn : null;
		}

		/** @return array|null The last check result, or null. */
		public static function result() {
			$r = get_option( self::OPT_RESULT );
			return is_array( $r ) ? $r : null;
		}

		/**
		 * True when Respira for WordPress runs here: its own security scan covers
		 * this site on the paid dashboard, so the card points there instead.
		 */
		public static function respira_present() {
			return defined( 'RESPIRA_VERSION' );
		}

		/**
		 * Step 1: Connect button. Remember a state for ten minutes and send the
		 * browser to respira.press to sign in or sign up and approve.
		 */
		public static function start_connect() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You are not allowed to connect this site.', 'inhale-mcp-abilities' ) );
			}
			check_admin_referer( 'respira_inhale_free_connect' );

			$state = 'free_' . wp_generate_password( 32, false, false );
			set_transient(
				'respira_free_state_' . md5( $state ),
				array(
					'user' => get_current_user_id(),
					'page' => self::page_url(),
				),
				self::STATE_TTL
			);

			$url = add_query_arg(
				array(
					'state'  => rawurlencode( $state ),
					'site'   => rawurlencode( home_url( '/' ) ),
					'return' => rawurlencode( self::page_url() ),
					'plugin' => self::PLUGIN,
				),
				self::api( '/free-connect' )
			);
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- respira.press, by design.
			exit;
		}

		/**
		 * Step 2: respira.press sends the browser back with a single-use code.
		 * Trade it for the site token, server to server, then run the first check.
		 */
		public static function maybe_finish_connect() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the state transient is the CSRF check.
			if ( empty( $_GET['respira_free_callback'] ) || empty( $_GET['state'] ) || empty( $_GET['code'] ) ) {
				return;
			}
			$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
			$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			// phpcs:enable

			$key  = 'respira_free_state_' . md5( $state );
			$data = get_transient( $key );
			if ( false === $data ) {
				return; // Not ours, already handled by the other plugin copy, or expired.
			}
			delete_transient( $key );
			$owner = is_array( $data ) && isset( $data['user'] ) ? (int) $data['user'] : (int) $data;
			// Back to the screen that started the connect, whichever plugin copy handles it.
			$back = is_array( $data ) && ! empty( $data['page'] ) ? $data['page'] : self::page_url();
			if ( ! current_user_can( self::CAPABILITY ) || $owner !== get_current_user_id() ) {
				return;
			}

			$response = wp_remote_post(
				self::api( '/api/v1/free-connect/exchange' ),
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'state'  => $state,
							'code'   => $code,
							'plugin' => self::PLUGIN,
						)
					),
				)
			);
			$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $body ) || empty( $body['ok'] ) || empty( $body['token'] ) ) {
				$msg = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( 'The connection could not be completed. Try again.', 'inhale-mcp-abilities' );
				set_transient( 'respira_free_notice_' . get_current_user_id(), array( 'error', $msg ), 60 );
				wp_safe_redirect( $back );
				exit;
			}

			update_option(
				self::OPT_CONN,
				array(
					'token'         => sanitize_text_field( $body['token'] ),
					'email'         => sanitize_email( isset( $body['email'] ) ? $body['email'] : '' ),
					'dashboard_url' => esc_url_raw( isset( $body['dashboard_url'] ) ? $body['dashboard_url'] : self::API . '/dashboard/site-check' ),
					'site_url'      => home_url( '/' ),
					'connected_at'  => time(),
					'connected_by'  => self::PLUGIN,
				),
				false
			);
			self::maybe_schedule();
			self::run_check();
			set_transient( 'respira_free_notice_' . get_current_user_id(), array( 'success', __( 'Connected. The first check has run.', 'inhale-mcp-abilities' ) ), 60 );
			wp_safe_redirect( $back );
			exit;
		}

		/** Weekly schedule while connected. Either plugin copy can (re)create it. */
		public static function maybe_schedule() {
			if ( self::connection() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::CRON_HOOK );
			}
		}

		/** Cron entry: both plugin copies listen, the lock lets one run. */
		public static function cron_check() {
			$last = self::result();
			if ( $last && ! empty( $last['checked_at'] ) && ( time() - (int) $last['checked_at'] ) < HOUR_IN_SECONDS ) {
				return;
			}
			self::run_check();
		}

		/** "Check now". */
		public static function manual_check() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'inhale-mcp-abilities' ) );
			}
			check_admin_referer( 'respira_inhale_free_check' );
			self::run_check();
			wp_safe_redirect( self::page_url() );
			exit;
		}

		/** WordPress core, plugins and themes with their versions. Nothing else. */
		public static function inventory() {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = array();
			foreach ( get_plugins() as $file => $data ) {
				$slug      = false !== strpos( $file, '/' ) ? dirname( $file ) : basename( $file, '.php' );
				$plugins[] = array(
					'slug'    => $slug,
					'version' => isset( $data['Version'] ) ? (string) $data['Version'] : '',
					'name'    => isset( $data['Name'] ) ? wp_strip_all_tags( (string) $data['Name'] ) : $slug,
				);
			}
			$themes = array();
			foreach ( wp_get_themes() as $stylesheet => $theme ) {
				$themes[] = array(
					'slug'    => (string) $stylesheet,
					'version' => (string) $theme->get( 'Version' ),
					'name'    => wp_strip_all_tags( (string) $theme->get( 'Name' ) ),
				);
			}
			return array(
				'core'    => get_bloginfo( 'version' ),
				'plugins' => $plugins,
				'themes'  => $themes,
			);
		}

		/** Send the inventory, store the answer. */
		public static function run_check() {
			$conn = self::connection();
			if ( ! $conn ) {
				return;
			}
			if ( get_transient( 'respira_free_check_lock' ) ) {
				return;
			}
			set_transient( 'respira_free_check_lock', 1, 2 * MINUTE_IN_SECONDS );

			$payload = array_merge(
				array(
					'site_url' => home_url( '/' ),
					'plugin'   => self::PLUGIN,
				),
				self::inventory()
			);
			$response = wp_remote_post(
				self::api( '/api/v1/free-connect/check' ),
				array(
					'timeout' => 20,
					'headers' => array(
						'Content-Type'  => 'application/json',
						'Authorization' => 'Bearer ' . $conn['token'],
					),
					'body'    => wp_json_encode( $payload ),
				)
			);
			delete_transient( 'respira_free_check_lock' );

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$body = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 401 === $code || 409 === $code ) {
				// Removed on respira.press, or the site moved: the token is dead.
				delete_option( self::OPT_CONN );
				wp_clear_scheduled_hook( self::CRON_HOOK );
				update_option(
					self::OPT_RESULT,
					array(
						'status'     => 'disconnected',
						'checked_at' => time(),
					),
					false
				);
				return;
			}
			if ( ! is_array( $body ) || empty( $body['status'] ) ) {
				$prev                = self::result() ? self::result() : array();
				$prev['last_error']  = is_wp_error( $response ) ? $response->get_error_message() : sprintf( 'HTTP %d', $code );
				$prev['last_try_at'] = time();
				update_option( self::OPT_RESULT, $prev, false );
				return;
			}
			$body['checked_at'] = time();
			update_option( self::OPT_RESULT, $body, false );
		}

		/** Disconnect: tell respira.press, then forget everything here. */
		public static function disconnect() {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You are not allowed to do this.', 'inhale-mcp-abilities' ) );
			}
			check_admin_referer( 'respira_inhale_free_disconnect' );
			self::forget( true );
			wp_safe_redirect( self::page_url() );
			exit;
		}

		/**
		 * Drop the connection. With $tell, revoke the token on respira.press too.
		 * Also used by uninstall.php when the other free plugin is not installed.
		 */
		public static function forget( $tell = true ) {
			$conn = self::connection();
			if ( $tell && $conn ) {
				wp_remote_post(
					self::api( '/api/v1/free-connect/disconnect' ),
					array(
						'timeout'  => 8,
						'blocking' => false,
						'headers'  => array( 'Authorization' => 'Bearer ' . $conn['token'] ),
					)
				);
			}
			delete_option( self::OPT_CONN );
			delete_option( self::OPT_RESULT );
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		/** Severity pill. */
		private static function sev( $level ) {
			$level = in_array( $level, array( 'critical', 'high', 'medium', 'low' ), true ) ? $level : 'unrated';
			return '<span class="rfc-sev rfc-sev-' . esc_attr( $level ) . '">' . esc_html( $level ) . '</span>';
		}

		/** A link to respira.press with this plugin as the source. */
		private static function link( $path, $campaign ) {
			return self::API . $path . ( false === strpos( $path, '?' ) ? '?' : '&' ) . 'utm_source=' . self::PLUGIN . '-plugin&utm_medium=plugin&utm_campaign=' . rawurlencode( $campaign );
		}

		/**
		 * The card. Plugins call this inside their own screen; it carries its own
		 * small stylesheet scoped to .rfc so it reads the same in both.
		 */
		public static function render_card() {
			$conn   = self::connection();
			$result = self::result();
			$notice = get_transient( 'respira_free_notice_' . get_current_user_id() );
			if ( $notice ) {
				delete_transient( 'respira_free_notice_' . get_current_user_id() );
			}
			$can = current_user_can( self::CAPABILITY );
			self::styles();
			?>
			<section class="rfc" aria-labelledby="rfc-title">
				<p class="rfc-kicker"><?php esc_html_e( 'Free with a Respira account', 'inhale-mcp-abilities' ); ?></p>

				<?php if ( is_array( $notice ) ) : ?>
					<p class="rfc-flash rfc-flash-<?php echo esc_attr( $notice[0] ); ?>"><?php echo esc_html( $notice[1] ); ?></p>
				<?php endif; ?>

				<?php if ( self::respira_present() ) : ?>
					<h2 id="rfc-title"><?php esc_html_e( 'Security scan', 'inhale-mcp-abilities' ); ?></h2>
					<p class="rfc-sub"><?php esc_html_e( 'Respira for WordPress runs on this site, so its security scan already checks WordPress, every plugin and every theme against known vulnerabilities.', 'inhale-mcp-abilities' ); ?></p>
					<p><a class="rfc-btn rfc-btn-primary" href="<?php echo esc_url( self::link( '/dashboard/security', 'security-link' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the security scan', 'inhale-mcp-abilities' ); ?></a></p>

				<?php elseif ( ! $conn ) : ?>
					<?php
					$inv = self::inventory();
					?>
					<h2 id="rfc-title"><?php esc_html_e( 'Check this site for known vulnerabilities', 'inhale-mcp-abilities' ); ?></h2>
					<ul class="rfc-list">
						<li>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of plugins, 2: number of themes. */
								__( 'WordPress, your %1$d plugins and %2$d themes, checked against 40,000+ known vulnerabilities, every week', 'inhale-mcp-abilities' ),
								count( $inv['plugins'] ),
								count( $inv['themes'] )
							)
						);
						?>
						</li>
						<li><?php esc_html_e( 'What to update, and to which version', 'inhale-mcp-abilities' ); ?></li>
						<li><?php esc_html_e( 'A free accessibility scan of any page on the site', 'inhale-mcp-abilities' ); ?></li>
					</ul>
					<?php if ( $result && isset( $result['status'] ) && 'disconnected' === $result['status'] ) : ?>
						<p class="rfc-flash rfc-flash-error"><?php esc_html_e( 'This site was disconnected on respira.press. Connect again to keep the weekly check.', 'inhale-mcp-abilities' ); ?></p>
					<?php endif; ?>
					<?php if ( $can ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="respira_inhale_free_connect" />
							<?php wp_nonce_field( 'respira_inhale_free_connect' ); ?>
							<button type="submit" class="rfc-btn rfc-btn-primary"><?php esc_html_e( 'Connect a free account', 'inhale-mcp-abilities' ); ?></button>
						</form>
					<?php else : ?>
						<p class="rfc-fine"><?php esc_html_e( 'An administrator can connect this site.', 'inhale-mcp-abilities' ); ?></p>
					<?php endif; ?>
					<p class="rfc-fine">
						<?php esc_html_e( 'No card, no trial. Once connected, the site address and the versions of WordPress, plugins and themes go to respira.press once a week. Nothing is sent before you connect.', 'inhale-mcp-abilities' ); ?>
						<a href="<?php echo esc_url( self::API . '/privacy' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy', 'inhale-mcp-abilities' ); ?></a>
					</p>

				<?php else : ?>
					<?php
					$ok       = $result && isset( $result['status'] ) && 'ok' === $result['status'];
					$affected = $ok && ! empty( $result['affected'] ) && is_array( $result['affected'] ) ? $result['affected'] : array();
					$checked  = $ok && isset( $result['checked'] ) ? $result['checked'] : array();
					$when     = $result && ! empty( $result['checked_at'] ) ? human_time_diff( (int) $result['checked_at'] ) : '';
					$dash     = ! empty( $conn['dashboard_url'] ) ? $conn['dashboard_url'] : self::link( '/dashboard/site-check', 'site-check' );
					?>
					<h2 id="rfc-title"><?php esc_html_e( 'Site check', 'inhale-mcp-abilities' ); ?></h2>
					<?php if ( ! $result ) : ?>
						<p class="rfc-sub"><?php esc_html_e( 'Connected. The first check has not run yet.', 'inhale-mcp-abilities' ); ?></p>
					<?php elseif ( ! $ok ) : ?>
						<p class="rfc-sub"><?php esc_html_e( 'The last check could not be completed, so this site is not shown as clear. Try again in a few minutes.', 'inhale-mcp-abilities' ); ?></p>
					<?php elseif ( empty( $affected ) ) : ?>
						<p class="rfc-clear">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: plugins checked, 2: themes checked, 3: time since the check. */
								__( 'No known vulnerabilities in WordPress, %1$d plugins and %2$d themes. Checked %3$s ago.', 'inhale-mcp-abilities' ),
								isset( $checked['plugins'] ) ? (int) $checked['plugins'] : 0,
								isset( $checked['themes'] ) ? (int) $checked['themes'] : 0,
								$when
							)
						);
						?>
						</p>
					<?php else : ?>
						<p class="rfc-sub">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of affected plugins or themes, 2: time since the check. */
								_n( '%1$d plugin or theme here has known vulnerabilities. Checked %2$s ago.', '%1$d plugins or themes here have known vulnerabilities. Checked %2$s ago.', count( $affected ), 'inhale-mcp-abilities' ),
								count( $affected ),
								$when
							)
						);
						?>
						</p>
						<ul class="rfc-items">
							<?php foreach ( array_slice( $affected, 0, 6 ) as $item ) : ?>
								<li>
									<?php echo self::sev( isset( $item['worst'] ) ? $item['worst'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
									<span class="rfc-name"><?php echo esc_html( isset( $item['name'] ) ? $item['name'] : '' ); ?></span>
									<span class="rfc-ver">
										<code><?php echo esc_html( isset( $item['installed'] ) ? $item['installed'] : '' ); ?></code>
										<?php if ( ! empty( $item['fixed_in'] ) ) : ?>
											&rarr; <code class="rfc-fix"><?php echo esc_html( $item['fixed_in'] ); ?></code>
										<?php else : ?>
											<em><?php esc_html_e( 'no fix yet', 'inhale-mcp-abilities' ); ?></em>
										<?php endif; ?>
									</span>
									<?php if ( ! empty( $item['records'][0] ) ) : ?>
										<a class="rfc-rec" href="<?php echo esc_url( $item['records'][0] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: number of known issues. */
												_n( '%d record', '%d records', (int) $item['issues'], 'inhale-mcp-abilities' ),
												(int) $item['issues']
											)
										);
										?>
										</a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="rfc-actions">
						<a class="rfc-btn rfc-btn-primary" href="<?php echo esc_url( $dash ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Details and fixes', 'inhale-mcp-abilities' ); ?></a>
						<a class="rfc-btn" href="<?php echo esc_url( self::link( '/dashboard/accessibility', 'a11y-scan' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Accessibility scan', 'inhale-mcp-abilities' ); ?></a>
						<?php if ( $can ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="respira_inhale_free_check" />
								<?php wp_nonce_field( 'respira_inhale_free_check' ); ?>
								<button type="submit" class="rfc-btn"><?php esc_html_e( 'Check now', 'inhale-mcp-abilities' ); ?></button>
							</form>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $affected ) ) : ?>
						<p class="rfc-fine"><?php esc_html_e( 'Respira for WordPress can make these updates from Claude or ChatGPT, with a snapshot of the site before each one.', 'inhale-mcp-abilities' ); ?> <a href="<?php echo esc_url( self::link( '/pricing', 'site-check-fix' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See how', 'inhale-mcp-abilities' ); ?></a></p>
					<?php endif; ?>

					<div class="rfc-foot">
						<span>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: account email. */
								__( 'Connected as %s', 'inhale-mcp-abilities' ),
								! empty( $conn['email'] ) ? $conn['email'] : __( 'your Respira account', 'inhale-mcp-abilities' )
							)
						);
						?>
						</span>
						<?php if ( $can ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="respira_inhale_free_disconnect" />
								<?php wp_nonce_field( 'respira_inhale_free_disconnect' ); ?>
								<button type="submit" class="rfc-link"><?php esc_html_e( 'Disconnect', 'inhale-mcp-abilities' ); ?></button>
							</form>
						<?php endif; ?>
					</div>

					<?php if ( $ok && ! empty( $result['notices'] ) && is_array( $result['notices'] ) ) : ?>
						<details class="rfc-notices">
							<summary><?php esc_html_e( 'Vulnerability data: Wordfence Intelligence', 'inhale-mcp-abilities' ); ?></summary>
							<?php foreach ( $result['notices'] as $n ) : ?>
								<p>
									<?php echo esc_html( isset( $n['notice'] ) ? $n['notice'] : '' ); ?>
									<?php echo esc_html( isset( $n['license'] ) ? $n['license'] : '' ); ?>
									<?php if ( ! empty( $n['url'] ) ) : ?>
										<a href="<?php echo esc_url( $n['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms', 'inhale-mcp-abilities' ); ?></a>
									<?php endif; ?>
								</p>
							<?php endforeach; ?>
						</details>
					<?php endif; ?>
				<?php endif; ?>
			</section>
			<?php
		}

		/** Scoped styles, printed once per page. */
		private static function styles() {
			static $done = false;
			if ( $done ) {
				return;
			}
			$done = true;
			?>
			<style>
				.rfc{--rfc-ink:#1c1917;--rfc-muted:#57534e;--rfc-line:#e6ded0;--rfc-paper:#fdfcfa;--rfc-em:#047857;--rfc-em-soft:#ecfdf5;background:var(--rfc-paper);border:1px solid var(--rfc-line);border-radius:12px;padding:20px 22px;color:var(--rfc-ink);font-size:13px;line-height:1.55}
				.rfc h2{margin:2px 0 8px;font-size:16px;line-height:1.3;color:var(--rfc-ink)}
				.rfc p{margin:0 0 10px}
				.rfc-kicker{font:600 11px/1.2 ui-monospace,Menlo,Consolas,monospace;letter-spacing:.08em;text-transform:uppercase;color:var(--rfc-em)}
				.rfc-sub{color:var(--rfc-muted)}
				.rfc-clear{color:var(--rfc-ink)}
				.rfc-list{margin:0 0 14px;padding:0;list-style:none;display:grid;gap:6px}
				.rfc-list li{position:relative;padding-left:16px;color:var(--rfc-muted)}
				.rfc-list li::before{content:"";position:absolute;left:2px;top:.6em;width:6px;height:6px;border-radius:50%;background:var(--rfc-em)}
				.rfc .rfc-btn,.rfc a.rfc-btn{display:inline-flex;align-items:center;min-height:34px;padding:0 14px;border-radius:8px;border:1px solid var(--rfc-line);background:#fff;color:var(--rfc-ink);font-weight:600;font-size:13px;text-decoration:none;cursor:pointer}
				.rfc .rfc-btn:hover,.rfc a.rfc-btn:hover{border-color:#a8a096;color:var(--rfc-ink)}
				.rfc .rfc-btn-primary,.rfc a.rfc-btn-primary,.rfc a.rfc-btn-primary:visited{background:var(--rfc-em);border-color:var(--rfc-em);color:#fff}
				.rfc .rfc-btn-primary:hover,.rfc a.rfc-btn-primary:hover{background:#065f46;border-color:#065f46;color:#fff}
				.rfc-btn:focus-visible,.rfc-link:focus-visible{outline:2px solid var(--rfc-em);outline-offset:2px}
				.rfc-actions{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 10px}
				.rfc-actions form,.rfc-foot form{margin:0}
				.rfc-fine{font-size:12px;color:var(--rfc-muted)}
				.rfc-fine a,.rfc-rec{color:var(--rfc-em)}
				.rfc-items{list-style:none;margin:0;padding:0;display:grid;gap:8px}
				.rfc-items li{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding-top:8px;border-top:1px solid var(--rfc-line)}
				.rfc-name{font-weight:600}
				.rfc-ver{color:var(--rfc-muted)}
				.rfc-ver code{font-size:12px;background:transparent;padding:0}
				.rfc-fix{color:var(--rfc-em)}
				.rfc-rec{margin-left:auto;font-size:12px}
				.rfc-sev{display:inline-flex;align-items:center;min-height:20px;padding:1px 8px;border-radius:999px;border:1px solid var(--rfc-line);font:600 11px/1 ui-monospace,Menlo,Consolas,monospace;color:var(--rfc-muted)}
				.rfc-sev-critical{color:#b42318;border-color:#f4c7c3;background:#fef3f2}
				.rfc-sev-high{color:#8e5c06;border-color:#f3dfb4;background:#fffaeb}
				.rfc-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;padding-top:10px;border-top:1px solid var(--rfc-line);font-size:12px;color:var(--rfc-muted)}
				.rfc-link{background:none;border:0;padding:0;color:var(--rfc-muted);text-decoration:underline;cursor:pointer;font-size:12px}
				.rfc-flash{padding:8px 10px;border-radius:8px;background:var(--rfc-em-soft);color:#065f46}
				.rfc-flash-error{background:#fef3f2;color:#b42318}
				.rfc-notices{margin-top:10px;font-size:11px;color:var(--rfc-muted)}
				.rfc-notices summary{cursor:pointer}
				.rfc-notices p{margin:6px 0 0}
				[data-theme="dark"] .rfc{--rfc-ink:#f4f1ea;--rfc-muted:#a8a096;--rfc-line:rgba(244,241,234,.14);--rfc-paper:#0c1219;--rfc-em:#10b981;--rfc-em-soft:rgba(16,185,129,.12)}
				[data-theme="dark"] .rfc .rfc-btn,[data-theme="dark"] .rfc a.rfc-btn{background:transparent;color:var(--rfc-ink)}
				[data-theme="dark"] .rfc .rfc-btn-primary,[data-theme="dark"] .rfc a.rfc-btn-primary{background:var(--rfc-em);color:#04221b}
				[data-theme="dark"] .rfc-flash{color:#a7f3d0}
				[data-theme="dark"] .rfc-sev-critical{color:#fca5a5;border-color:rgba(248,113,113,.4);background:rgba(248,113,113,.1)}
				[data-theme="dark"] .rfc-sev-high{color:#fcd34d;border-color:rgba(252,211,77,.4);background:rgba(252,211,77,.08)}
			</style>
			<?php
		}
	}

	Respira_Inhale_Free_Connect::init();

endif;
