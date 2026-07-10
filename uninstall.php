<?php
declare(strict_types=1);
/**
 * WPBuoy Video Sync Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Always removes plugin options, transients, and cron events.
 * Only removes content (synced video, playlist, and channel posts) when the
 * "Remove all WPBuoy Video Sync data on uninstall" setting is enabled.
 *
 * @package WPBuoy_Video_Sync
 */

// Security check — WordPress sets this constant before calling uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove WPBuoy Video Sync data on uninstall.
 *
 * Wrapped in a function so its working variables stay out of the global scope —
 * uninstall.php is executed by WordPress at global scope.
 *
 * @return void
 */
function wpbyvs_run_uninstall(): void {
	global $wpdb;

	// Uninstall is a one-time teardown: direct, uncached deletes are required to
	// remove data, and the IN() clauses bind generated %s/%d placeholders via
	// $wpdb->prepare(). The WordPress.DB advisory rules do not apply here.
	// phpcs:disable WordPress.DB

	// ---------------------------------------------------------------------
	// Always: unschedule all sync cron events.
	// ---------------------------------------------------------------------
	wp_unschedule_hook( 'wpbyvs_sync_rule' );
	wp_unschedule_hook( 'wpbyvs_channel_config_sync_rule' );

	// ---------------------------------------------------------------------
	// Always: remove dismissed-notice user meta.
	// ---------------------------------------------------------------------
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'wpbyvs_cron_notice_dismissed' ) );

	// ---------------------------------------------------------------------
	// Optional: remove synced content when the user opted in.
	// Synced items live in user-selected post types, identified by their
	// dedup meta key (video/playlist/channel each set a different one —
	// playlist posts never set _wpbyvs_source_type). Attachments sideloaded
	// for a synced post (channel profile picture / banner) are removed with
	// their parent.
	// ---------------------------------------------------------------------
	if ( get_option( 'wpbyvs_delete_on_uninstall' ) ) {
		$post_ids = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key IN ( '_wpbyvs_video_id', '_wpbyvs_playlist_id', '_wpbyvs_channel_post' )"
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
		'wpbyvs_api_key',
		'wpbyvs_channel_config',
		'wpbyvs_youtube_image_as_featured',
		'wpbyvs_active_archives',
		'wpbyvs_delete_on_uninstall',
		'wpbyvs_reschedule_on_activation',
		'wpbyvs_sync_log',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	$history_keys = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpbyvs\\_sync\\_history\\_%' OR option_name LIKE 'wpbyvs\\_history\\_read\\_%'"
	);
	foreach ( $history_keys as $history_key ) {
		delete_option( $history_key );
	}

	// ---------------------------------------------------------------------
	// Always: delete plugin transients (sync locks, progress, notices).
	// ---------------------------------------------------------------------
	$transient_keys = $wpdb->get_col(
		"SELECT option_name FROM {$wpdb->options}
		 WHERE option_name LIKE '\\_transient\\_wpbyvs\\_%' OR option_name LIKE '\\_transient\\_timeout\\_wpbyvs\\_%'"
	);
	foreach ( $transient_keys as $transient_key ) {
		delete_option( $transient_key );
	}

	// phpcs:enable WordPress.DB
}

wpbyvs_run_uninstall();
