<?php
declare(strict_types=1);
/**
 * Export/Import tab on the Settings screen — JSON backup and restore of the
 * channel and its sync rules.
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import_Export Class
 */
class Import_Export {

	const FORMAT_VERSION = 1;
	const MAX_FILE_BYTES = 1048576; // 1MB.
	const TAB_SLUG       = 'export-import';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'current_screen', array( $this, 'add_help_tab' ), 20 );
		add_action( 'admin_post_buoyvs_export_channels', array( $this, 'export' ) );
		add_action( 'admin_post_buoyvs_import_channels', array( $this, 'import' ) );
	}

	/**
	 * Add an Export/Import Help tab to the Settings screen.
	 *
	 * @param \WP_Screen $screen Current screen.
	 * @return void
	 */
	public function add_help_tab( $screen ): void {
		if ( ! $screen instanceof \WP_Screen || ! str_ends_with( $screen->id, '_page_buoyvs_settings' ) ) {
			return;
		}
		$screen->add_help_tab( array(
			'id'       => 'buoyvs-help-export-import',
			'title'    => __( 'Export/Import', 'buoy-video-sync' ),
			'priority' => 50,
			'content'  =>
				'<h2>' . esc_html__( 'Export/Import', 'buoy-video-sync' ) . '</h2>' .
				'<p>' . esc_html__( 'Export downloads your channel and its sync rules as a JSON file. Use it as a backup, or to copy your setup to another site.', 'buoy-video-sync' ) . '</p>' .
				'<p>' . esc_html__( 'Import replaces your current channel and all of its sync rules with the ones in the file. Posts that were already synced are not changed or deleted. Enabled rules start running on their schedules after the import.', 'buoy-video-sync' ) . '</p>' .
				'<p>' . esc_html__( 'Your YouTube API key and plugin settings are not included in the file.', 'buoy-video-sync' ) . '</p>',
		) );
	}

	/**
	 * Print (and clear) the current user's last import result.
	 *
	 * @return void
	 */
	public static function render_notices(): void {
		$notices = get_transient( self::notice_key() );
		if ( ! is_array( $notices ) ) {
			return;
		}
		delete_transient( self::notice_key() );

		foreach ( $notices as $notice ) {
			$type = in_array( $notice['type'] ?? '', array( 'success', 'info', 'warning', 'error' ), true ) ? $notice['type'] : 'warning';
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( (string) ( $notice['message'] ?? '' ) )
			);
		}
	}

	/**
	 * Print the Export/Import tab panel content.
	 *
	 * @return void
	 */
	public static function render_panel(): void {
		$channel    = buoyvs_get_channel_config();
		$has_config = ! empty( $channel['youtube_id'] );
		$rule_count = count( $channel['sync_rules'] ?? array() );

		buoyvs_get_template_part( 'settings-tab', 'export-import', compact( 'channel', 'has_config', 'rule_count' ) );
	}

	/**
	 * Transient key holding the current user's last import result.
	 *
	 * @return string
	 */
	private static function notice_key(): string {
		return 'buoyvs_ie_notice_' . get_current_user_id();
	}

	/**
	 * URL of the Export/Import tab on the Settings screen.
	 *
	 * @return string
	 */
	private static function tab_url(): string {
		return admin_url( 'admin.php?page=buoyvs_settings&tab=' . self::TAB_SLUG );
	}

	/**
	 * Stream the current channel config as a downloadable JSON file.
	 *
	 * @return void
	 */
	public function export(): void {
		if ( ! isset( $_GET['_wpnonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'buoyvs_export_channels' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'buoy-video-sync' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buoy-video-sync' ) );
		}

		$channel = buoyvs_get_channel_config();
		$data    = array(
			'plugin'         => 'buoy-video-sync',
			'format_version' => self::FORMAT_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'channels'       => empty( $channel ) ? array() : array( $channel ),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="buoy-video-sync-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Replace the current channel config with an uploaded JSON export.
	 *
	 * @return void
	 */
	public function import(): void {
		if ( ! isset( $_POST['buoyvs_import_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['buoyvs_import_nonce'] ) ), 'buoyvs_import_channels' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'buoy-video-sync' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'buoy-video-sync' ) );
		}

		$notices = array(); // Each: array( 'type' => success|warning|error, 'message' => string ).
		$file    = $_FILES['buoyvs_import_file'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.

		if ( empty( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$notices[] = array( 'type' => 'error', 'message' => __( 'No file was uploaded, or the upload failed.', 'buoy-video-sync' ) );
		} elseif ( $file['size'] > self::MAX_FILE_BYTES ) {
			$notices[] = array( 'type' => 'error', 'message' => __( 'The uploaded file is too large.', 'buoy-video-sync' ) );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- small local upload, not a remote URL.
			$contents = file_get_contents( $file['tmp_name'] );
			$data     = is_string( $contents ) ? json_decode( $contents, true ) : null;

			$channels = ( is_array( $data ) && isset( $data['channels'] ) && is_array( $data['channels'] ) )
				? array_values( array_filter( array_map(
					static function ( $raw_channel ) {
						return is_array( $raw_channel ) ? buoyvs_sanitize_channel( $raw_channel ) : array();
					},
					$data['channels']
				), static fn( $ch ) => ! empty( $ch['youtube_id'] ) ) )
				: null;

			if ( null === $channels ) {
				$notices[] = array( 'type' => 'error', 'message' => __( 'That file is not a valid Buoy Video Sync export.', 'buoy-video-sync' ) );
			} elseif ( empty( $channels ) ) {
				// Never let an empty export silently wipe the current setup.
				$notices[] = array( 'type' => 'error', 'message' => __( 'That file does not contain any channels, so nothing was imported. Your current channel was left unchanged.', 'buoy-video-sync' ) );
			} else {
				$channel = $channels[0];

				if ( count( $channels ) > 1 ) {
					$notices[] = array(
						'type'    => 'warning',
						'message' => __( 'This file contains more than one channel. Only the first channel was imported.', 'buoy-video-sync' ),
					);
				}

				update_option( 'buoyvs_channel_config', $channel );
				do_action( 'buoyvs_reschedule_channel_rules' );

				$rules = count( $channel['sync_rules'] );
				array_unshift( $notices, array(
					'type'    => 'success',
					'message' => sprintf(
						/* translators: 1: channel title, 2: number of sync rules */
						_n( 'Imported %1$s with %2$s sync rule.', 'Imported %1$s with %2$s sync rules.', $rules, 'buoy-video-sync' ),
						'' !== $channel['channel_title'] ? $channel['channel_title'] : $channel['youtube_id'],
						number_format_i18n( $rules )
					),
				) );
			}
		}

		set_transient( self::notice_key(), $notices, 60 );

		wp_safe_redirect( self::tab_url() );
		exit;
	}
}

new Import_Export();
