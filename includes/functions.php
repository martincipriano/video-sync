<?php
declare(strict_types=1);
/**
 * WPBuoy Video Sync Pro — General helper functions.
 *
 * @package WPBuoy_Video_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sync rule actions the free plugin supports.
 *
 * @return string[]
 */
function wpbyvs_free_actions(): array {
	return array( '', 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' );
}

/**
 * Get the configured channel as a single flat object.
 *
 * The free plugin manages exactly one channel, stored as a flat object in the
 * wpbyvs_channel_config option. As downgrade safety, if a legacy indexed array is
 * found (e.g. left by a prior multi-channel install) the first element is used.
 *
 * @return array The single channel config, or an empty array if none.
 */
function wpbyvs_get_channel_config(): array {
	$config = get_option( 'wpbyvs_channel_config', array() );
	if ( ! is_array( $config ) ) {
		return array();
	}
	// Downgrade safety: a legacy indexed array → use the first channel.
	if ( isset( $config[0] ) && is_array( $config[0] ) ) {
		return $config[0];
	}
	return $config;
}

/**
 * Sanitize a single sync rule from form input.
 *
 * Shared by Channel, Playlist, and Channels_Page save handlers.
 *
 * @param array $rule Raw rule data from $_POST.
 * @return array Sanitized rule.
 */
function wpbyvs_sanitize_sync_rule( $rule ) {
	$action = isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '';
	// Free supports only the "sync new" actions; coerce anything else (Pro actions) to empty.
	if ( ! in_array( $action, wpbyvs_free_actions(), true ) ) {
		$action = '';
	}

	$sanitized = array(
		'enabled'           => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
		'title'             => isset( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : '',
		'max_videos'        => isset( $rule['max_videos'] ) ? absint( $rule['max_videos'] ) : 50,
		// Free runs every rule as a one-time sync; recurring schedules are Pro.
		'schedule'          => 'once',
		'action'            => $action,
		'specific_metadata' => isset( $rule['specific_metadata'] ) && is_array( $rule['specific_metadata'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $rule['specific_metadata'] ) ) )
			: array(),
		'destination_post_type'      => isset( $rule['destination_post_type'] ) ? sanitize_key( $rule['destination_post_type'] ) : '',
	);

	return $sanitized;
}

/**
 * Collect and validate extra metabox tabs registered by other plugins.
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return array<int,array> Validated tabs, each [ 'slug', 'label', 'render' ].
 */
function wpbyvs_get_metabox_tabs( string $type, int $post_id ): array {
	/**
	 * Filter the extra tabs shown in the video/playlist/channel metabox.
	 *
	 * Each tab is an array:
	 *   [ 'slug' => string, 'label' => string, 'render' => callable( int $post_id ) ]
	 * The render callback echoes the tab panel's inner content.
	 *
	 * @param array  $tabs    List of tab definitions. Default empty.
	 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
	 * @param int    $post_id Current post ID.
	 */
	$tabs = apply_filters( 'wpbyvs_metabox_tabs', array(), $type, $post_id );

	$valid = array();
	if ( is_array( $tabs ) ) {
		foreach ( $tabs as $tab ) {
			if ( ! empty( $tab['slug'] ) && ! empty( $tab['label'] ) && isset( $tab['render'] ) && is_callable( $tab['render'] ) ) {
				$valid[] = $tab;
			}
		}
	}
	return $valid;
}

/**
 * Render extra metabox tab buttons (call inside .wpbyvs-channel-tabs-nav).
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return void
 */
function wpbyvs_render_extra_tab_nav( string $type, int $post_id ): void {
	foreach ( wpbyvs_get_metabox_tabs( $type, $post_id ) as $tab ) {
		printf(
			'<button type="button" class="wpbyvs-channel-tab-btn" data-tab="%1$s" role="tab" aria-selected="false">%2$s</button>',
			esc_attr( $tab['slug'] ),
			esc_html( $tab['label'] )
		);
	}
}

/**
 * Render extra metabox tab panels (call inside .wpbyvs-channel-tabs-content).
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return void
 */
function wpbyvs_render_extra_tab_panels( string $type, int $post_id ): void {
	foreach ( wpbyvs_get_metabox_tabs( $type, $post_id ) as $tab ) {
		printf(
			'<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="%s" role="tabpanel">',
			esc_attr( $tab['slug'] )
		);
		call_user_func( $tab['render'], $post_id );
		echo '</div>';
	}
}
