<?php
declare(strict_types=1);
/**
 * Channel config export/import — JSON backup/restore of the channel and its sync rules.
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

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_buoyvs_export_channels', array( $this, 'export' ) );
		add_action( 'admin_post_buoyvs_import_channels', array( $this, 'import' ) );
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

		$notices = array();
		$file    = $_FILES['buoyvs_import_file'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.

		if ( empty( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			$notices[] = __( 'No file was uploaded, or the upload failed.', 'buoy-video-sync' );
		} elseif ( $file['size'] > self::MAX_FILE_BYTES ) {
			$notices[] = __( 'The uploaded file is too large.', 'buoy-video-sync' );
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
				$notices[] = __( 'That file is not a valid Buoy Video Sync export.', 'buoy-video-sync' );
			} elseif ( empty( $channels ) ) {
				// Never let an empty export silently wipe the current setup.
				$notices[] = __( 'That file does not contain any channels, so nothing was imported. Your current channel was left unchanged.', 'buoy-video-sync' );
			} else {
				$channel = $channels[0];

				if ( count( $channels ) > 1 ) {
					$notices[] = __( 'This file contains multiple channels — only the first was imported. Multiple channels require Buoy Video Sync Pro.', 'buoy-video-sync' );
				}

				update_option( 'buoyvs_channel_config', $channel );
				do_action( 'buoyvs_reschedule_channel_rules' );
			}
		}

		if ( ! empty( $notices ) ) {
			set_transient( 'buoyvs_import_notice_' . get_current_user_id(), $notices, 60 );
		}

		wp_safe_redirect( add_query_arg( 'buoyvs-channels-updated', '1', admin_url( 'admin.php?page=buoy-video-sync' ) ) );
		exit;
	}
}

new Import_Export();
