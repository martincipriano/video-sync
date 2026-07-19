<?php
declare(strict_types=1);
/**
 * Buoy Video Sync Settings Class
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buoy Video Sync Settings Class
 */
class Buoy_Video_Sync_Settings {

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
			'buoy-video-sync',
			__( 'Buoy Video Sync Settings', 'buoy-video-sync'),
			__( 'Settings', 'buoy-video-sync'),
			'manage_options',
			'buoyvs_settings',
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
			'buoyvs_settings_group',
			'buoyvs_api_key',
			array(
				'sanitize_callback' => array( $this, 'validate_api_key' ),
			)
		);

		register_setting(
			'buoyvs_settings_group',
			'buoyvs_delete_on_uninstall',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		register_setting(
			'buoyvs_settings_group',
			'buoyvs_youtube_image_as_featured',
			array(
				'sanitize_callback' => 'absint',
				'default'           => 1,
			)
		);

		add_settings_section(
			'buoyvs_api_settings',
			__( 'API Settings', 'buoy-video-sync'),
			null,
			'buoyvs_settings'
		);

		add_settings_field(
			'buoyvs_api_key',
			__( 'Google API Key', 'buoy-video-sync'),
			array( $this, 'api_key_html' ),
			'buoyvs_settings',
			'buoyvs_api_settings'
		);

		add_settings_section(
			'buoyvs_content_settings',
			__( 'Content', 'buoy-video-sync'),
			null,
			'buoyvs_settings'
		);

		add_settings_field(
			'buoyvs_youtube_image_as_featured',
			__( 'Featured Images', 'buoy-video-sync'),
			array( $this, 'youtube_image_as_featured_html' ),
			'buoyvs_settings',
			'buoyvs_content_settings'
		);

		add_settings_section(
			'buoyvs_advanced_settings',
			__( 'Advanced', 'buoy-video-sync'),
			null,
			'buoyvs_settings'
		);

		add_settings_field(
			'buoyvs_delete_on_uninstall',
			__( 'Uninstall Data', 'buoy-video-sync'),
			array( $this, 'delete_on_uninstall_html' ),
			'buoyvs_settings',
			'buoyvs_advanced_settings'
		);
	}

	/**
	 * Render API key field HTML.
	 *
	 * @return void
	 */
	public function api_key_html() {
		$value = get_option( 'buoyvs_api_key', '' );
		buoyvs_get_template_part( 'settings-field', 'api-key', compact( 'value' ) );
	}

	/**
	 * Validate and sanitize API key.
	 *
	 * @param string $input The API key to validate.
	 * @return string The validated API key.
	 */
	public function validate_api_key( $input ) {
		$input  = sanitize_text_field( $input );
		$stored = get_option( 'buoyvs_api_key', '' );

		// Unchanged key — this save likely only touches other settings (e.g. the
		// uninstall toggle). Skip the YouTube API round-trip and confirm generically.
		if ( $input === $stored ) {
			$this->add_settings_saved_notice();
			return $input;
		}

		if ( empty( $input ) ) {
			add_settings_error( 'buoyvs_api_key', 'buoyvs_api_key_empty', __( 'API key cannot be empty.', 'buoy-video-sync' ) );
			return get_option( 'buoyvs_api_key', '' );
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
			add_settings_error( 'buoyvs_api_key', 'buoyvs_api_key_request_error', __( 'Could not connect to YouTube API.', 'buoy-video-sync') );
			return get_option( 'buoyvs_api_key', '' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			add_settings_error(
				'buoyvs_api_key',
				'buoyvs_api_key_invalid',
				/* translators: %s: Error message from YouTube API */
				sprintf( __( 'YouTube API error: %s', 'buoy-video-sync'), esc_html( $error_msg ) )
			);
			return get_option( 'buoyvs_api_key', '' );
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
		$existing = wp_list_pluck( get_settings_errors( 'buoyvs_api_key' ), 'code' );
		if ( ! in_array( 'buoyvs_settings_saved', $existing, true ) ) {
			add_settings_error( 'buoyvs_api_key', 'buoyvs_settings_saved', __( 'Settings saved.', 'buoy-video-sync' ), 'updated' );
		}
	}

	/**
	 * Render the "use YouTube image as featured image" field HTML.
	 *
	 * @return void
	 */
	public function youtube_image_as_featured_html() {
		$checked = (bool) get_option( 'buoyvs_youtube_image_as_featured', 1 );
		?>
		<label>
			<input type="checkbox" name="buoyvs_youtube_image_as_featured" value="1" <?php checked( $checked, true ); ?>>
			<?php esc_html_e( 'Use the YouTube image as the featured image when none is set', 'buoy-video-sync'); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, synced videos, playlists, and channels show their YouTube thumbnail (or channel picture) as the featured image unless you set your own. Disable to leave synced posts without a featured image.', 'buoy-video-sync'); ?>
		</p>
		<?php
	}

	/**
	 * Render delete on uninstall field HTML.
	 *
	 * @return void
	 */
	public function delete_on_uninstall_html() {
		$checked = (bool) get_option( 'buoyvs_delete_on_uninstall', 0 );
		?>
		<label>
			<input type="checkbox" name="buoyvs_delete_on_uninstall" value="1" <?php checked( $checked, true ); ?>>
			<?php esc_html_e( 'Remove all Buoy Video Sync data when the plugin is deleted', 'buoy-video-sync'); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When checked, deleting this plugin will permanently remove all videos, channels, playlists, and settings. Leave unchecked to keep your data if you reinstall later.', 'buoy-video-sync'); ?>
		</p>
		<?php
	}

	/**
	 * Render settings page HTML.
	 *
	 * @return void
	 */
	public function settings_html() {
		buoyvs_get_template_part( 'settings', 'page' );
	}

}

new Buoy_Video_Sync_Settings();
