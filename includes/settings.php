<?php
declare(strict_types=1);
/**
 * YouSync Settings Class
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * YouSync Settings Class
 */
class YouSyncSettings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_submenu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings submenu page.
	 *
	 * @return void
	 */
	public function add_settings_submenu() {
		add_submenu_page(
			'yousync',
			__( 'YouSync Settings', 'yousync'),
			__( 'Settings', 'yousync'),
			'manage_options',
			'yousync_settings',
			array( $this, 'settings_html' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'yousync_settings_group',
			'yousync_api_key',
			array(
				'sanitize_callback' => array( $this, 'validate_api_key' ),
			)
		);

		register_setting(
			'yousync_settings_group',
			'yousync_delete_on_uninstall',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		add_settings_section(
			'yousync_api_settings',
			__( 'API Settings', 'yousync'),
			null,
			'yousync_settings'
		);

		add_settings_field(
			'yousync_api_key',
			__( 'Google API Key', 'yousync'),
			array( $this, 'api_key_html' ),
			'yousync_settings',
			'yousync_api_settings'
		);

		add_settings_section(
			'yousync_advanced_settings',
			__( 'Advanced', 'yousync'),
			null,
			'yousync_settings'
		);

		add_settings_field(
			'yousync_delete_on_uninstall',
			__( 'Uninstall Data', 'yousync'),
			array( $this, 'delete_on_uninstall_html' ),
			'yousync_settings',
			'yousync_advanced_settings'
		);
	}

	/**
	 * Render API key field HTML.
	 *
	 * @return void
	 */
	public function api_key_html() {
		$value = get_option( 'yousync_api_key', '' );
		yousync_get_template_part( 'settings-field', 'api-key', compact( 'value' ) );
	}

	/**
	 * Validate and sanitize API key.
	 *
	 * @param string $input The API key to validate.
	 * @return string The validated API key.
	 */
	public function validate_api_key( $input ) {
		$input = sanitize_text_field( $input );

		if ( empty( $input ) ) {
			add_settings_error( 'yousync_api_key', 'yousync_api_key_empty', __( 'API key cannot be empty.', 'yousync' ) );
			return get_option( 'yousync_api_key', '' );
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'part' => 'id',
					'id'   => 'dQw4w9WgXcQ',
					'key'  => $input,
				),
				'https://www.googleapis.com/youtube/v3/videos'
			)
		);

		if ( is_wp_error( $response ) ) {
			add_settings_error( 'yousync_api_key', 'yousync_api_key_request_error', __( 'Could not connect to YouTube API.', 'yousync') );
			return get_option( 'yousync_api_key', '' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			add_settings_error(
				'yousync_api_key',
				'yousync_api_key_invalid',
				/* translators: %s: Error message from YouTube API */
				sprintf( __( 'YouTube API error: %s', 'yousync'), esc_html( $error_msg ) )
			);
			return get_option( 'yousync_api_key', '' );
		}

		$existing = wp_list_pluck( get_settings_errors( 'yousync_api_key' ), 'code' );
		if ( ! in_array( 'valid_api_key', $existing, true ) ) {
			add_settings_error( 'yousync_api_key', 'valid_api_key', __( 'API key saved successfully!', 'yousync' ), 'updated' );
		}

		return $input;
	}

	/**
	 * Render delete on uninstall field HTML.
	 *
	 * @return void
	 */
	public function delete_on_uninstall_html() {
		$checked = (bool) get_option( 'yousync_delete_on_uninstall', 0 );
		?>
		<label>
			<input type="checkbox" name="yousync_delete_on_uninstall" value="1" <?php checked( $checked, true ); ?>>
			<?php esc_html_e( 'Remove all YouSync data when the plugin is deleted', 'yousync'); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When checked, deleting this plugin will permanently remove all videos, channels, playlists, and settings. Leave unchecked to keep your data if you reinstall later.', 'yousync'); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page HTML.
	 *
	 * @return void
	 */
	public function settings_html() {
		yousync_get_template_part( 'settings', 'page' );
	}

}

new YouSyncSettings();
