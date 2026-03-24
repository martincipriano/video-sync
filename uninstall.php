<?php
/**
 * YouSync Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Always removes plugin options and cron events.
 * Only removes content (videos, channels, playlists) when the
 * "Remove all YouSync data on uninstall" setting is enabled.
 *
 * @package YouSync
 */

// Security check — WordPress sets this constant before calling uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// -------------------------------------------------------------------------
// Always: unschedule all sync cron events.
// -------------------------------------------------------------------------
wp_unschedule_hook( 'yousync_sync_rule' );

// -------------------------------------------------------------------------
// Always: remove dismissed-notice user meta.
// -------------------------------------------------------------------------
$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'yousync_cron_notice_dismissed' ) );

// -------------------------------------------------------------------------
// Conditionally: remove all plugin content if the user opted in.
// -------------------------------------------------------------------------
if ( get_option( 'yousync_delete_on_uninstall' ) ) {

	// --- Delete all yousync_videos posts and their meta ---
	$post_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'yousync_videos'"
	);

	if ( ! empty( $post_ids ) ) {
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post_id ) );
			$wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
		}
	}

	// --- Delete all terms for YouSync taxonomies ---
	$taxonomies = array( 'yousync_channel', 'yousync_playlist', 'video_tag', 'video_category' );

	// Collect term IDs and term_taxonomy IDs for bulk deletion.
	$placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$term_taxonomy_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
			$taxonomies
		)
	);

	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$term_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
			$taxonomies
		)
	);

	if ( ! empty( $term_taxonomy_ids ) ) {
		$tt_placeholders = implode( ', ', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) );

		// Delete object relationships (post ↔ term).
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($tt_placeholders)",
				$term_taxonomy_ids
			)
		);
	}

	if ( ! empty( $term_ids ) ) {
		$term_placeholders = implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) );

		// Delete term meta.
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->termmeta} WHERE term_id IN ($term_placeholders)",
				$term_ids
			)
		);

		// Delete terms.
		// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->terms} WHERE term_id IN ($term_placeholders)",
				$term_ids
			)
		);
	}

	// Delete term_taxonomy entries.
	// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ($placeholders)",
			$taxonomies
		)
	);
}

// -------------------------------------------------------------------------
// Always: delete plugin options.
// -------------------------------------------------------------------------
$options = array(
	'yousync_api_key',
	'yousync_active_archives',
	'yousync_delete_on_uninstall',
	'yousync_reschedule_on_activation',
	'yousync_sync_log',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

delete_transient( 'yousync_flush_rewrite_rules' );
