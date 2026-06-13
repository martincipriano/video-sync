<?php
declare(strict_types=1);
/**
 * YouSync Pro — General helper functions.
 *
 * @package YouSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a stored sync rule uses features the free plugin cannot represent or run.
 *
 * A rule belongs to YouSync Pro when it has a recurring schedule, an update action
 * (anything other than the three "sync new" actions), or any filter conditions,
 * field mapping, or taxonomy-term assignment. The free plugin leaves such rules in
 * the database untouched — it does not render, schedule, or run them — so that
 * re-activating Pro restores the full configuration. This is a data-shape check
 * only; it executes no Pro feature.
 *
 * @param mixed $rule Stored sync rule.
 * @return bool True if the rule is Pro-only and should be preserved but ignored.
 */
function yousync_rule_is_unsupported( $rule ): bool {
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
function yousync_sanitize_sync_rule( $rule ) {
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
