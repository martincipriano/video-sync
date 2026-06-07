<?php
/**
 * Wpbuoy License Client
 *
 * A modular, drop-in license client library for WordPress plugins.
 * Compatible with the wpbuoy-license-manager REST API.
 *
 * Drop this file into your plugin and instantiate once:
 *
 *   require_once __DIR__ . '/license-client/class-wpbuoy-license-client.php';
 *
 *   use WPBuoy_License_Client\v1\Client;
 *
 *   $license = new Client( array(
 *       'plugin_slug'  => 'my-plugin',
 *       'server_url'   => 'https://yourstore.com/wp-json/wpbylm/v1/verify',
 *       'pro_features' => array( 'feature_a', 'feature_b' ),
 *       'text_domain'  => 'my-plugin',
 *       'plugin_name'  => 'My Plugin Pro',
 *       'pricing_url'  => 'https://yourstore.com/pricing',
 *       'account_url'  => 'https://yourstore.com/my-account/licenses',
 *       'page_slug'    => 'my-plugin-license', // slug used in add_submenu_page()
 *   ) );
 *
 * Multiple pro plugins can bundle this file without conflict. Each major version
 * lives in its own namespace (v1, v2, …) so plugins shipping different versions
 * coexist safely — no class_exists collision even when versions differ.
 *
 * @package WPBuoy_License_Checker
 * @version 1.0.0
 */

namespace WPBuoy_License_Client\v1;

if ( ! defined( 'WPINC' ) ) {
	die;
}

// phpcs:disable WordPress.WP.I18n.NonSingularStringLiteralDomain -- drop-in library uses a configurable text domain by design.

if ( ! class_exists( __NAMESPACE__ . '\Client' ) ) :

	/**
	 * Wpbuoy License Client
	 *
	 * Handles license activation, periodic validation, feature gating, and
	 * the admin license management page for a single WordPress plugin.
	 */
	class Client {

		/**
		 * Library version. Bump this when the class API changes.
		 */
		const VERSION = '1.1.0';

		// -------------------------------------------------------------------
		// Configuration (set in constructor, never mutated afterwards)
		// -------------------------------------------------------------------

		/** @var string Plugin slug used to namespace all WP data. */
		private $slug;

		/** @var string Full URL to the wpby-license-manager verify endpoint. */
		private $server_url;

		/** @var string[] Feature keys that require an active license. */
		private $pro_features;

		/** @var string Text domain used for all __() calls. */
		private $text_domain;

		/** @var string Human-readable plugin name shown in the admin UI. */
		private $plugin_name;

		/** @var string URL to the store pricing page (optional). */
		private $pricing_url;

		/** @var string URL to the account/licenses page (optional). */
		private $account_url;

		/** @var string Admin page slug passed to add_submenu_page() for this plugin. */
		private $page_slug;

		// -------------------------------------------------------------------
		// Namespaced WP keys — derived from $slug, never change after __construct
		// -------------------------------------------------------------------

		/** @var string wp_options key for the raw license key string. */
		private $opt_key;

		/** @var string wp_options key for the last-known license status payload. */
		private $opt_status;

		/** @var string Transient key that throttles API calls on admin_init. */
		private $transient_check;

		/** @var string WP-Cron hook name. */
		private $cron_hook;

		/** @var string admin-post action name for activation. */
		private $action_activate;

		/** @var string admin-post action name for deactivation. */
		private $action_deactivate;

		/** @var string Nonce action for activation form. */
		private $nonce_activate;

		/** @var string Nonce action for deactivation form. */
		private $nonce_deactivate;

		// -------------------------------------------------------------------
		// Constructor
		// -------------------------------------------------------------------

		/**
		 * Constructor.
		 *
		 * @param array $config {
		 *     @type string   $plugin_slug  Required. Unique slug for your plugin (no spaces).
		 *     @type string   $server_url   Required. Full URL of the verify endpoint.
		 *     @type string[] $pro_features Optional. Feature keys gated behind an active license.
		 *     @type string   $text_domain  Optional. Translation text domain. Defaults to $plugin_slug.
		 *     @type string   $plugin_name  Optional. Human-readable name for admin UI text.
		 *     @type string   $pricing_url  Optional. Link to your store pricing page.
		 *     @type string   $account_url  Optional. Link to your account/licenses page.
		 *     @type string   $page_slug    Optional. Admin page slug used in add_submenu_page().
		 *                                  Defaults to "{plugin_slug}-license".
		 * }
		 */
		public function __construct( array $config ) {
			$this->slug         = sanitize_key( $config['plugin_slug'] );
			$this->server_url   = esc_url_raw( $config['server_url'] );
			$this->pro_features = isset( $config['pro_features'] ) ? (array) $config['pro_features'] : array();
			$this->text_domain  = isset( $config['text_domain'] )  ? $config['text_domain']  : $this->slug;
			$this->plugin_name  = isset( $config['plugin_name'] )  ? $config['plugin_name']  : $this->slug;
			$this->pricing_url  = isset( $config['pricing_url'] )  ? esc_url_raw( $config['pricing_url'] ) : '';
			$this->account_url  = isset( $config['account_url'] )  ? esc_url_raw( $config['account_url'] ) : '';
			$this->page_slug    = isset( $config['page_slug'] )    ? sanitize_key( $config['page_slug'] )  : $this->slug . '-license';

			// Build namespaced WP keys.
			$this->opt_key           = $this->slug . '_license_key';
			$this->opt_status        = $this->slug . '_license_status';
			$this->transient_check   = $this->slug . '_license_check';
			$this->cron_hook         = $this->slug . '_daily_license_check';
			$this->action_activate   = $this->slug . '_activate_license';
			$this->action_deactivate = $this->slug . '_deactivate_license';
			$this->nonce_activate    = $this->slug . '_activate_license';
			$this->nonce_deactivate  = $this->slug . '_deactivate_license';

			// Register WordPress hooks.
			add_action( 'init',                                    array( $this, 'schedule_cron' ) );
			add_action( 'admin_init',                              array( $this, 'maybe_check_license' ) );
			add_action( $this->cron_hook,                          array( $this, 'check_license' ) );
			add_action( 'admin_post_' . $this->action_activate,   array( $this, 'handle_activate' ) );
			add_action( 'admin_post_' . $this->action_deactivate, array( $this, 'handle_deactivate' ) );
		}

		// -------------------------------------------------------------------
		// Public API
		// -------------------------------------------------------------------

		/**
		 * Activate a license key.
		 *
		 * Sends the key and the current site URL to the license server. On
		 * success, stores the key and the full server response; on failure,
		 * returns an error message.
		 *
		 * @param string $license_key License key entered by the user.
		 * @return array {
		 *     @type bool        $success
		 *     @type string      $message  User-facing message.
		 *     @type array|null  $data     Full server response on success.
		 * }
		 */
		public function activate( $license_key ) {
			$license_key = trim( $license_key );

			if ( empty( $license_key ) ) {
				return $this->result( false, __( 'Please enter a license key.', $this->text_domain ) );
			}

			$response = $this->api_request( $license_key );

			if ( is_wp_error( $response ) ) {
				$this->log( 'Activation failed — ' . $response->get_error_message() );
				return $this->result( false, $response->get_error_message() );
			}

			$body = wp_remote_retrieve_body( $response );
			$code = wp_remote_retrieve_response_code( $response );
			$data = json_decode( $body, true );

			if ( ! $data ) {
				$this->log( 'Activation failed — invalid JSON response (HTTP ' . $code . '): ' . substr( $body, 0, 200 ) );
				return $this->result( false, __( 'Invalid response from license server.', $this->text_domain ) );
			}

			if ( isset( $data['success'] ) && $data['success'] && isset( $data['status'] ) && 'active' === $data['status'] ) {
				$this->log( 'License activated successfully.' );
				update_option( $this->opt_key,    $license_key );
				update_option( $this->opt_status, $data );
				delete_transient( $this->transient_check );

				do_action( 'wpbuoy_license_activated', $this->slug );

				return $this->result( true, __( 'License activated successfully!', $this->text_domain ), $data );
			}

			$error_msg = $this->parse_error_message( $data );
			$this->log( 'Activation rejected — ' . $error_msg );
			return $this->result( false, $error_msg );
		}

		/**
		 * Deactivate the current license.
		 *
		 * Clears all locally-stored license data. The license server is not
		 * contacted — domain removal (if supported) must be done via the store.
		 *
		 * @return array { bool $success, string $message }
		 */
		public function deactivate() {
			delete_option( $this->opt_key );
			delete_option( $this->opt_status );
			delete_transient( $this->transient_check );

			do_action( 'wpbuoy_license_deactivated', $this->slug );

			return $this->result( true, __( 'License deactivated successfully!', $this->text_domain ) );
		}

		/**
		 * Ping the license server and refresh the stored status.
		 *
		 * Called automatically by the daily cron event and throttled on
		 * admin_init. Consuming plugins should not normally call this directly.
		 *
		 * @return array|false Updated license data on success, false on failure.
		 */
		public function check_license() {
			$key = get_option( $this->opt_key );

			if ( empty( $key ) ) {
				return false;
			}

			$response = $this->api_request( $key );

			if ( is_wp_error( $response ) ) {
				$this->log( 'License check failed — ' . $response->get_error_message() );
				return false;
			}

			$body = wp_remote_retrieve_body( $response );
			$code = wp_remote_retrieve_response_code( $response );
			$data = json_decode( $body, true );

			if ( ! $data ) {
				$this->log( 'License check failed — invalid JSON response (HTTP ' . $code . '): ' . substr( $body, 0, 200 ) );
				return false;
			}

			if ( isset( $data['status'] ) && 'active' !== $data['status'] ) {
				$this->log( 'License check — status changed to ' . $data['status'] );
			}

			update_option( $this->opt_status, $data );
			return $data;
		}

		/**
		 * Return the stored license key.
		 *
		 * @return string License key string, or empty string if none is stored.
		 */
		public function get_license_key() {
			return get_option( $this->opt_key, '' );
		}

		/**
		 * Return the last-known license status payload without hitting the API.
		 *
		 * @return array|false License data array, or false if no license is stored.
		 */
		public function get_license_status() {
			return get_option( $this->opt_status, false );
		}

		/**
		 * Whether the stored license is currently active.
		 *
		 * @return bool
		 */
		public function is_valid() {
			$status = $this->get_license_status();
			return $status && isset( $status['status'] ) && 'active' === $status['status'];
		}

		/**
		 * Whether a given feature is available.
		 *
		 * Features listed in $pro_features require an active license.
		 * All other features are always available (free tier).
		 *
		 * @param string $feature Feature key.
		 * @return bool
		 */
		public function is_feature_available( $feature ) {
			if ( in_array( $feature, $this->pro_features, true ) ) {
				return $this->is_valid();
			}
			return true;
		}

		/**
		 * Return the stored license key, masked for display.
		 *
		 * Shows only the last 4 characters; all others are replaced with *.
		 *
		 * @return string Masked key, or empty string if no key is stored.
		 */
		public function get_masked_key() {
			$key = get_option( $this->opt_key, '' );

			if ( empty( $key ) ) {
				return '';
			}

			$length = strlen( $key );
			if ( $length <= 4 ) {
				return str_repeat( '*', $length );
			}

			return str_repeat( '*', $length - 4 ) . substr( $key, -4 );
		}

		/**
		 * Render the license management admin page.
		 *
		 * Wire this as the callback argument to add_submenu_page(). The page
		 * slug passed there must match the 'page_slug' config option.
		 *
		 * @param array $display_features Optional. Keyed array of
		 *     feature_key => translated_description for the Pro Features list.
		 *     When omitted, keys from $pro_features are used with auto-labels.
		 * @return void
		 */
		public function render_license_page( $display_features = array() ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$is_valid   = $this->is_valid();
			$status     = $this->get_license_status();
			$masked_key = $this->get_masked_key();

			// Read flash message set after a form redirect.
			$message_type = '';
			$message_text = '';
			if ( isset( $_GET['wpbylc_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$message_type = ( isset( $_GET['wpbylc_ok'] ) && '1' === $_GET['wpbylc_ok'] ) ? 'success' : 'error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$message_text = sanitize_text_field( wp_unslash( urldecode( $_GET['wpbylc_msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			$activate_url = admin_url( 'admin-post.php' );
			?>
			<div class="wrap">
				<h1>
					<?php echo esc_html( $this->plugin_name ); ?>
					&mdash;
					<?php esc_html_e( 'License', $this->text_domain ); ?>
				</h1>

				<?php if ( $message_text ) : ?>
					<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
						<p><?php echo esc_html( $message_text ); ?></p>
					</div>
				<?php endif; ?>

				<div class="wpbylc-license-wrap">

					<!-- Status card -->
					<div class="wpbylc-card">
						<h2><?php esc_html_e( 'License Status', $this->text_domain ); ?></h2>
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Status', $this->text_domain ); ?></th>
								<td>
									<span class="wpbylc-badge <?php echo $is_valid ? 'wpbylc-badge-active' : 'wpbylc-badge-inactive'; ?>">
										<?php echo $is_valid ? esc_html__( 'Active', $this->text_domain ) : esc_html__( 'Inactive', $this->text_domain ); ?>
									</span>
								</td>
							</tr>
							<?php if ( $masked_key ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'License Key', $this->text_domain ); ?></th>
								<td><code><?php echo esc_html( $masked_key ); ?></code></td>
							</tr>
							<?php endif; ?>
							<?php if ( $status && isset( $status['expires_at'] ) ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Expires', $this->text_domain ); ?></th>
								<td>
									<?php
									$ts = strtotime( $status['expires_at'] );
									if ( $ts > strtotime( '+50 years' ) ) {
										esc_html_e( 'Lifetime License', $this->text_domain );
									} else {
										echo esc_html( date_i18n( get_option( 'date_format' ), $ts ) );
									}
									?>
								</td>
							</tr>
							<?php endif; ?>
							<?php if ( $status && isset( $status['product'] ) ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Product', $this->text_domain ); ?></th>
								<td><?php echo esc_html( $status['product'] ); ?></td>
							</tr>
							<?php endif; ?>
							<?php if ( $status && isset( $status['registered_domains'], $status['activations'] ) ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Domains', $this->text_domain ); ?></th>
								<td>
									<?php
									// registered_domains may be an array (current API) or an integer (legacy).
									$registered = is_array( $status['registered_domains'] )
										? count( $status['registered_domains'] )
										: (int) $status['registered_domains'];
									echo esc_html( $registered . ' / ' . (int) $status['activations'] );
									?>
								</td>
							</tr>
							<?php endif; ?>
						</table>
					</div><!-- .wpbylc-card (status) -->

					<!-- Activate / Deactivate card -->
					<div class="wpbylc-card">
						<?php if ( ! $is_valid ) : ?>
							<h2><?php esc_html_e( 'Activate License', $this->text_domain ); ?></h2>
							<p>
								<?php
								printf(
									/* translators: %s: plugin name */
									esc_html__( 'Enter your license key to activate %s.', $this->text_domain ),
									esc_html( $this->plugin_name )
								);
								?>
							</p>
							<form method="post" action="<?php echo esc_url( $activate_url ); ?>">
								<?php wp_nonce_field( $this->nonce_activate, 'license_nonce' ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( $this->action_activate ); ?>">
								<table class="form-table">
									<tr>
										<th scope="row">
											<label for="wpbylc_key_<?php echo esc_attr( $this->slug ); ?>">
												<?php esc_html_e( 'License Key', $this->text_domain ); ?>
											</label>
										</th>
										<td>
											<input type="text"
												   id="wpbylc_key_<?php echo esc_attr( $this->slug ); ?>"
												   name="license_key"
												   class="regular-text"
												   placeholder="<?php esc_attr_e( 'Enter your license key', $this->text_domain ); ?>">
											<?php if ( $this->account_url ) : ?>
												<p class="description">
													<?php
													printf(
														/* translators: %s: link to account dashboard */
														esc_html__( 'Find your key in your %s or purchase email.', $this->text_domain ),
														'<a href="' . esc_url( $this->account_url ) . '" target="_blank">'
															. esc_html__( 'account dashboard', $this->text_domain )
														. '</a>'
													);
													?>
												</p>
											<?php endif; ?>
										</td>
									</tr>
								</table>
								<button type="submit" class="button button-primary">
									<?php esc_html_e( 'Activate License', $this->text_domain ); ?>
								</button>
							</form>
						<?php else : ?>
							<h2><?php esc_html_e( 'Deactivate License', $this->text_domain ); ?></h2>
							<p><?php esc_html_e( 'Deactivate your license to use it on another site.', $this->text_domain ); ?></p>
							<form method="post" action="<?php echo esc_url( $activate_url ); ?>">
								<?php wp_nonce_field( $this->nonce_deactivate, 'license_nonce' ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( $this->action_deactivate ); ?>">
								<button type="submit" class="button button-secondary">
									<?php esc_html_e( 'Deactivate License', $this->text_domain ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div><!-- .wpbylc-card (actions) -->

				</div><!-- .wpbylc-license-wrap -->
			</div><!-- .wrap -->

			<style>
			.wpbylc-license-wrap {
				display: grid;
				grid-template-columns: 1fr;
				gap: 20px;
				max-width: 600px;
				margin-top: 20px;
			}
			.wpbylc-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
			}
			.wpbylc-card h2 { margin-top: 0; font-size: 1em; }
			.wpbylc-badge {
				display: inline-block;
				padding: 3px 10px;
				border-radius: 3px;
				font-weight: 600;
				font-size: 12px;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}
			.wpbylc-badge-active   { background: #d4edda; color: #155724; }
			.wpbylc-badge-inactive { background: #f8d7da; color: #721c24; }
			</style>
			<?php
		}

		// -------------------------------------------------------------------
		// WordPress hook callbacks
		// -------------------------------------------------------------------

		/**
		 * Register the daily cron event on `init`.
		 *
		 * @internal
		 */
		public function schedule_cron() {
			if ( ! wp_next_scheduled( $this->cron_hook ) ) {
				wp_schedule_event( time(), 'daily', $this->cron_hook );
			}
		}

		/**
		 * Throttled license check on `admin_init` (at most every 12 hours).
		 *
		 * @internal
		 */
		public function maybe_check_license() {
			if ( ! get_transient( $this->transient_check ) ) {
				$this->check_license();
				set_transient( $this->transient_check, true, 12 * HOUR_IN_SECONDS );
			}
		}

		/**
		 * Handle the admin-post.php activation form submission.
		 *
		 * @internal
		 */
		public function handle_activate() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', $this->text_domain ) );
			}

			if (
				! isset( $_POST['license_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['license_nonce'] ) ), $this->nonce_activate )
			) {
				wp_die( esc_html__( 'Security check failed.', $this->text_domain ) );
			}

			$key    = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
			$result = $this->activate( $key );

			wp_safe_redirect( $this->redirect_url( $result ) );
			exit;
		}

		/**
		 * Handle the admin-post.php deactivation form submission.
		 *
		 * @internal
		 */
		public function handle_deactivate() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized.', $this->text_domain ) );
			}

			if (
				! isset( $_POST['license_nonce'] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['license_nonce'] ) ), $this->nonce_deactivate )
			) {
				wp_die( esc_html__( 'Security check failed.', $this->text_domain ) );
			}

			$result = $this->deactivate();

			wp_safe_redirect( $this->redirect_url( $result ) );
			exit;
		}

		// -------------------------------------------------------------------
		// Private helpers
		// -------------------------------------------------------------------

		/**
		 * POST the license key to the server.
		 *
		 * Sends JSON body: { "license_key": "...", "domain": "https://..." }
		 * Matches the format expected by wpbuoy-license-manager.
		 *
		 * @param string $license_key
		 * @return array|WP_Error wp_remote_post() response.
		 */
		private function api_request( $license_key ) {
			return wp_remote_post(
				$this->server_url,
				array(
					'timeout'   => 15,
					'sslverify' => false, // set to true in production
					'headers'   => array( 'Content-Type' => 'application/json' ),
					'body'      => wp_json_encode(
						array(
							'license_key' => $license_key,
							'domain'      => home_url(),
							'plugin_slug' => $this->slug,
						)
					),
				)
			);
		}

		/**
		 * Turn a license-manager error response into a user-friendly string.
		 *
		 * @param array $data Decoded JSON response body.
		 * @return string
		 */
		private function parse_error_message( array $data ) {
			if ( ! empty( $data['message'] ) ) {
				return $data['message'];
			}

			if ( isset( $data['status'] ) ) {
				switch ( $data['status'] ) {
					case 'expired':
						$date = isset( $data['expires_at'] ) ? $data['expires_at'] : 'N/A';
						/* translators: %s: expiry date */
						return sprintf( __( 'Your license key expired on %s.', $this->text_domain ), $date );

					case 'revoked':
						return __( 'Your license key has been disabled.', $this->text_domain );

					case 'inactive':
						return __( 'Your license is inactive.', $this->text_domain );

					case 'invalid':
						return __( 'Invalid license key.', $this->text_domain );

					case 'limit_reached':
						return __( 'This license has reached its activation limit.', $this->text_domain );
				}
			}

			return __( 'License validation failed.', $this->text_domain );
		}

		/**
		 * Build a consistent result array returned by public methods.
		 *
		 * @param bool        $success
		 * @param string      $message
		 * @param array|null  $data
		 * @return array
		 */
		private function result( $success, $message, $data = null ) {
			$out = array(
				'success' => $success,
				'message' => $message,
			);

			if ( null !== $data ) {
				$out['data'] = $data;
			}

			return $out;
		}

		/**
		 * Write a prefixed message to the PHP error log.
		 *
		 * @param string $message Log message.
		 */
		private function log( $message ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[WPBuoy License: ' . $this->slug . '] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		/**
		 * Build the admin redirect URL after a form submission.
		 *
		 * Appends flash message query args read by render_license_page().
		 *
		 * @param array $result Result array from activate() or deactivate().
		 * @return string
		 */
		private function redirect_url( array $result ) {
			return add_query_arg(
				array(
					'page'      => $this->page_slug,
					'wpbylc_msg' => rawurlencode( $result['message'] ),
					'wpbylc_ok'  => $result['success'] ? '1' : '0',
				),
				admin_url( 'admin.php' )
			);
		}
	}

endif; // class_exists( __NAMESPACE__ . '\Client' )
