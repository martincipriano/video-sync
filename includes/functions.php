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
