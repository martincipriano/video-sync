<?php
declare(strict_types=1);
/**
 * WPBuoy Video Sync Settings Class
 *
 * @package WPBuoy_Video_Sync
 */

namespace WPBuoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPBuoy Video Sync Settings Class
 */
class WPBuoy_Video_Sync_Settings {

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
			'wby-video-sync',
			__( 'WPBuoy Video Sync Settings', 'wby-video-sync'),
			__( 'Settings', 'wby-video-sync'),
			'manage_options',
			'wpbyvs_settings',
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
			'wpbyvs_settings_group',
			'wpbyvs_api_key',
			array(
				'sanitize_callback' => array( $this, 'validate_api_key' ),
			)
		);

		register_setting(
			'wpbyvs_settings_group',
			'wpbyvs_delete_on_uninstall',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		register_setting(
			'wpbyvs_settings_group',
			'wpbyvs_youtube_image_as_featured',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 1,
			)
		);

		add_settings_section(
			'wpbyvs_api_settings',
			__( 'API Settings', 'wby-video-sync'),
			null,
			'wpbyvs_settings'
		);

		add_settings_field(
			'wpbyvs_api_key',
			__( 'Google API Key', 'wby-video-sync'),
			array( $this, 'api_key_html' ),
			'wpbyvs_settings',
			'wpbyvs_api_settings'
		);

		add_settings_section(
			'wpbyvs_content_settings',
			__( 'Content', 'wby-video-sync'),
			null,
			'wpbyvs_settings'
		);

		add_settings_field(
			'wpbyvs_youtube_image_as_featured',
			__( 'Featured Images', 'wby-video-sync'),
			array( $this, 'youtube_image_as_featured_html' ),
			'wpbyvs_settings',
			'wpbyvs_content_settings'
		);

		add_settings_section(
			'wpbyvs_advanced_settings',
			__( 'Advanced', 'wby-video-sync'),
			null,
			'wpbyvs_settings'
		);

		add_settings_field(
			'wpbyvs_delete_on_uninstall',
			__( 'Uninstall Data', 'wby-video-sync'),
			array( $this, 'delete_on_uninstall_html' ),
			'wpbyvs_settings',
			'wpbyvs_advanced_settings'
		);
	}

	/**
	 * Render API key field HTML.
	 *
	 * @return void
	 */
	public function api_key_html() {
		$value = get_option( 'wpbyvs_api_key', '' );
		wpbyvs_get_template_part( 'settings-field', 'api-key', compact( 'value' ) );
	}

	/**
	 * Validate and sanitize API key.
	 *
	 * @param string $input The API key to validate.
	 * @return string The validated API key.
	 */
	public function validate_api_key( $input ) {
		$input  = sanitize_text_field( $input );
		$stored = get_option( 'wpbyvs_api_key', '' );

		// Unchanged key — this save likely only touches other settings (e.g. the
		// uninstall toggle). Skip the YouTube API round-trip and confirm generically.
		if ( $input === $stored ) {
			$this->add_settings_saved_notice();
			return $input;
		}

		if ( empty( $input ) ) {
			add_settings_error( 'wpbyvs_api_key', 'wpbyvs_api_key_empty', __( 'API key cannot be empty.', 'wby-video-sync' ) );
			return get_option( 'wpbyvs_api_key', '' );
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
			add_settings_error( 'wpbyvs_api_key', 'wpbyvs_api_key_request_error', __( 'Could not connect to YouTube API.', 'wby-video-sync') );
			return get_option( 'wpbyvs_api_key', '' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			add_settings_error(
				'wpbyvs_api_key',
				'wpbyvs_api_key_invalid',
				/* translators: %s: Error message from YouTube API */
				sprintf( __( 'YouTube API error: %s', 'wby-video-sync'), esc_html( $error_msg ) )
			);
			return get_option( 'wpbyvs_api_key', '' );
		}

		$this->add_settings_saved_notice();

		return $input;
	}

	/**
	 * Add a generic "Settings saved." success notice (deduplicated so it shows once).
	 *
	 * @return void
	 */
	private function add_settings_saved_notice() {
		$existing = wp_list_pluck( get_settings_errors( 'wpbyvs_api_key' ), 'code' );
		if ( ! in_array( 'wpbyvs_settings_saved', $existing, true ) ) {
			add_settings_error( 'wpbyvs_api_key', 'wpbyvs_settings_saved', __( 'Settings saved.', 'wby-video-sync' ), 'updated' );
		}
	}

	/**
	 * Render the "use YouTube image as featured image" field HTML.
	 *
	 * @return void
	 */
	public function youtube_image_as_featured_html() {
		$checked = (bool) get_option( 'wpbyvs_youtube_image_as_featured', 1 );
		?>
		<label>
			<input type="checkbox" name="wpbyvs_youtube_image_as_featured" value="1" <?php checked( $checked, true ); ?>>
			<?php esc_html_e( 'Use the YouTube image as the featured image when none is set', 'wby-video-sync'); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, synced videos, playlists, and channels show their YouTube thumbnail (or channel picture) as the featured image unless you set your own. Disable to leave synced posts without a featured image.', 'wby-video-sync'); ?>
		</p>
		<?php
	}

	/**
	 * Render delete on uninstall field HTML.
	 *
	 * @return void
	 */
	public function delete_on_uninstall_html() {
		$checked = (bool) get_option( 'wpbyvs_delete_on_uninstall', 0 );
		?>
		<label>
			<input type="checkbox" name="wpbyvs_delete_on_uninstall" value="1" <?php checked( $checked, true ); ?>>
			<?php esc_html_e( 'Remove all WPBuoy Video Sync data when the plugin is deleted', 'wby-video-sync'); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When checked, deleting this plugin will permanently remove all videos, channels, playlists, and settings. Leave unchecked to keep your data if you reinstall later.', 'wby-video-sync'); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page HTML.
	 *
	 * @return void
	 */
	public function settings_html() {
		wpbyvs_get_template_part( 'settings', 'page' );
	}

}

new WPBuoy_Video_Sync_Settings();
