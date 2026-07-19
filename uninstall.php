<?php
declare(strict_types=1);
/**
 * Buoy Video Sync Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Always removes plugin options, transients, and cron events.
 * Only removes content (synced video, playlist, and channel posts) when the
 * "Remove all Buoy Video Sync data on uninstall" setting is enabled.
 *
 * @package Buoy_Video_Sync
 */

// Security check — WordPress sets this constant before calling uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove Buoy Video Sync data on uninstall.
 *
 * Wrapped in a function so its working variables stay out of the global scope —
 * uninstall.php is executed by WordPress at global scope.
 *
 * @return void
 */
function buoyvs_run_uninstall(): void {
	global $wpdb;

	// Uninstall is a one-time teardown: direct, uncached deletes are required to
	// remove data, and the IN() clauses bind generated %s/%d placeholders via
	// $wpdb->prepare(). The WordPress.DB advisory rules do not apply here.
	// phpcs:disable WordPress.DB

	// ---------------------------------------------------------------------
	// Always: clear all queued sync cron events.
	// ---------------------------------------------------------------------
	wp_unschedule_hook( 'buoyvs_channel_config_sync_rule' );

	// ---------------------------------------------------------------------
	// Optional: remove synced content when the user opted in.
	// Synced items live in user-selected post types, identified by their
	// dedup meta key (video/playlist/channel each set a different one —
	// playlist posts never set _buoyvs_source_type). Attachments sideloaded
	// for a synced post (channel profile picture / banner) are removed with
	// their parent.
	// ---------------------------------------------------------------------
	if ( get_option( 'buoyvs_delete_on_uninstall' ) ) {
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key IN ( '_buoyvs_video_id', '_buoyvs_playlist_id', '_buoyvs_channel_post' )"
		);

		foreach ( array_map( 'intval', $post_ids ) as $post_id ) {
			$attachments = get_children(
				array(
					'post_parent' => $post_id,
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				)
			);
			foreach ( $attachments as $attachment_id ) {
				wp_delete_attachment( (int) $attachment_id, true );
			}
			wp_delete_post( $post_id, true );
		}
	}

	// ---------------------------------------------------------------------
	// Always: delete plugin options (including per-channel sync history).
	// ---------------------------------------------------------------------
	$options = array(
		'buoyvs_api_key',
		'buoyvs_channel_config',
		'buoyvs_youtube_image_as_featured',
		'buoyvs_delete_on_uninstall',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$history_keys = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'buoyvs\\_sync\\_history\\_%' OR option_name LIKE 'buoyvs\\_history\\_read\\_%'"
	);
	foreach ( $history_keys as $history_key ) {
		delete_option( $history_key );
	}

	// ---------------------------------------------------------------------
	// Always: delete plugin transients (sync locks, progress, notices).
	// ---------------------------------------------------------------------
	$transient_keys = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_buoyvs\\_%' OR option_name LIKE '\\_transient\\_timeout\\_buoyvs\\_%'"
	);
	foreach ( $transient_keys as $transient_key ) {
		delete_option( $transient_key );
	}

	// phpcs:enable WordPress.DB
}

buoyvs_run_uninstall();
