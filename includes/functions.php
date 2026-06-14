<?php
declare(strict_types=1);
/**
 * WPBuoy Video Sync Pro — General helper functions.
 *
 * @package WPBuoyVideoSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a stored sync rule uses features the free plugin cannot represent or run.
 *
 * A rule belongs to WPBuoy Video Sync Pro when it has a recurring schedule, an update action
 * (anything other than the three "sync new" actions), or any filter conditions,
 * field mapping, or taxonomy-term assignment. The free plugin leaves such rules in
 * the database untouched — it does not render, schedule, or run them — so that
 * re-activating Pro restores the full configuration. This is a data-shape check
 * only; it executes no Pro feature.
 *
 * @param mixed $rule Stored sync rule.
 * @return bool True if the rule is Pro-only and should be preserved but ignored.
 */
function wpbuoy_video_sync_rule_is_unsupported( $rule ): bool {
	if ( ! is_array( $rule ) ) {
		return false;
	}
	if ( 'once' !== ( $rule['schedule'] ?? 'once' ) ) {
		return true;
	}
	$free_actions = array( '', 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' );
	if ( ! in_array( $rule['action'] ?? '', $free_actions, true ) ) {
		return true;
	}
	if ( ! empty( $rule['conditions'] ) || ! empty( $rule['field_mapping'] ) || ! empty( $rule['destination_taxonomy_terms'] ) ) {
		return true;
	}
	return false;
}

/**
 * Sanitize a single sync rule from form input.
 *
 * Shared by Channel, Playlist, and Channels_Page save handlers.
 *
 * @param array $rule Raw rule data from $_POST.
 * @return array Sanitized rule.
 */
function wpbuoy_video_sync_sanitize_sync_rule( $rule ) {
	$sanitized = array(
		'enabled'           => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
		'title'             => isset( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : '',
		'max_videos'        => isset( $rule['max_videos'] ) ? absint( $rule['max_videos'] ) : 50,
		// Free runs every rule as a one-time sync; recurring schedules are Pro.
		'schedule'          => 'once',
		'action'            => isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '',
		'specific_metadata' => isset( $rule['specific_metadata'] ) && is_array( $rule['specific_metadata'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $rule['specific_metadata'] ) ) )
			: array(),
		'destination_post_type'      => isset( $rule['destination_post_type'] ) ? sanitize_key( $rule['destination_post_type'] ) : '',
	);

	return $sanitized;
}

/**
 * One-time rebrand migration: rename legacy `yousync_*` data to the
 * `wpbuoy_video_sync_*` namespace so existing channels, rules, API key,
 * sync history, and all synced post meta carry over after the rename.
 *
 * Idempotent — guarded by the `wpbuoy_video_sync_migrated` flag. Free and Pro
 * share the same option/meta keys and the same flag, so whichever variant is
 * active performs the migration once and the other skips it.
 *
 * @return void
 */
function wpbuoy_video_sync_maybe_migrate(): void {
	if ( get_option( 'wpbuoy_video_sync_migrated' ) ) {
		return;
	}

	global $wpdb;

	// Rename option keys: yousync_* -> wpbuoy_video_sync_*. UPDATE IGNORE so a row
	// whose target name already exists (e.g. an internal migration flag) is skipped
	// rather than aborting the whole statement on a unique-key collision.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"UPDATE IGNORE {$wpdb->options}
		 SET option_name = REPLACE( option_name, 'yousync_', 'wpbuoy_video_sync_' )
		 WHERE option_name LIKE 'yousync\\_%'"
	);

	// Rename post meta keys: _yousync_* -> _wpbuoy_video_sync_*
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"UPDATE IGNORE {$wpdb->postmeta}
		 SET meta_key = REPLACE( meta_key, '_yousync_', '_wpbuoy_video_sync_' )
		 WHERE meta_key LIKE '\\_yousync\\_%'"
	);

	// Note: legacy custom post types (yousync_videos, yousync_channel) and
	// taxonomies (yousync_channel/playlist/tag/category) are intentionally NOT
	// renamed — the wpbuoy_video_sync_ prefix exceeds the 20-char post_type limit,
	// and these are unregistered 1.x remnants. They stay under their legacy names.

	// Rename block names saved in post content (Pro blocks: wp:yousync/...).
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"UPDATE {$wpdb->posts}
		 SET post_content = REPLACE( post_content, 'wp:yousync/', 'wp:wpbuoy-video-sync/' )
		 WHERE post_content LIKE '%wp:yousync/%'"
	);

	wp_cache_flush();
	update_option( 'wpbuoy_video_sync_migrated', 1 );
}
add_action( 'plugins_loaded', 'wpbuoy_video_sync_maybe_migrate', 1 );
