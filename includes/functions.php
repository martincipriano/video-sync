<?php
declare(strict_types=1);
/**
 * Buoy Video Sync — General helper functions.
 *
 * @package Buoy_Video_Sync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valid sync rule action values ('' = a rule that has not been configured yet).
 *
 * @return string[]
 */
function buoyvs_valid_actions(): array {
	return array( '', 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' );
}

/**
 * Valid sync rule schedule values.
 *
 * @return string[]
 */
function buoyvs_valid_schedules(): array {
	return array( 'once', 'hourly', 'daily', 'weekly', 'monthly', 'custom' );
}

/**
 * Valid sync rule post_status values.
 *
 * Mirrors the exclusions in template-parts/options-post-status.php: every
 * registered non-internal status except 'future' (requires a future
 * post_date the sync rule UI doesn't collect), plus any custom status a
 * theme or plugin registers.
 *
 * @return string[]
 */
function buoyvs_valid_post_statuses(): array {
	$statuses = array_keys( get_post_stati( array( 'internal' => false ) ) );
	$statuses = array_diff( $statuses, array( 'future' ) );
	if ( ! in_array( 'publish', $statuses, true ) ) {
		$statuses[] = 'publish';
	}
	return $statuses;
}

/**
 * Valid stored sync_status values.
 *
 * @return string[]
 */
function buoyvs_valid_sync_statuses(): array {
	return array( '', 'syncing', 'success', 'failed' );
}

/**
 * Post statuses that count as "already imported" for deduplication.
 *
 * Every non-internal status a rule can create posts with (publish, draft,
 * pending, private, and any custom status), plus 'trash' so a deliberately
 * trashed item is not silently re-imported.
 *
 * @return string[]
 */
function buoyvs_dedup_post_statuses(): array {
	$statuses   = array_keys( get_post_stati( array( 'internal' => false ) ) );
	$statuses[] = 'trash';
	return array_values( array_unique( $statuses ) );
}

/**
 * Get the configured channel as a single flat object.
 *
 * The channel is stored as a flat object in the buoyvs_channel_config option.
 *
 * @return array The channel config, or an empty array if none.
 */
function buoyvs_get_channel_config(): array {
	$config = get_option( 'buoyvs_channel_config', array() );
	return is_array( $config ) ? $config : array();
}

/**
 * Get the channel config straight from the database, bypassing the in-memory
 * option cache.
 *
 * A long-running sync (cron) loads the options cache when it starts; edits
 * saved from the Channels page in the meantime would otherwise be invisible to
 * it, and writing its stale copy back would undo them.
 *
 * @return array
 */
function buoyvs_get_channel_config_fresh(): array {
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'buoyvs_channel_config', 'options' );
	return buoyvs_get_channel_config();
}

/**
 * Generate a new permanent sync rule ID.
 *
 * @return string
 */
function buoyvs_generate_rule_id(): string {
	return 'r' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 16 );
}

/**
 * Whether a value is a well-formed sync rule ID.
 *
 * @param mixed $id Candidate ID.
 * @return bool
 */
function buoyvs_is_valid_rule_id( $id ): bool {
	return is_string( $id ) && (bool) preg_match( '/^r[a-f0-9]{8,32}$/', $id );
}

/**
 * Give every rule in a channel a permanent ID (rules saved before IDs existed).
 *
 * @param array $channel Channel config.
 * @return array Channel config with IDs on every rule.
 */
function buoyvs_ensure_rule_ids( array $channel ): array {
	if ( empty( $channel['sync_rules'] ) || ! is_array( $channel['sync_rules'] ) ) {
		return $channel;
	}
	foreach ( $channel['sync_rules'] as $i => $rule ) {
		if ( ! is_array( $rule ) ) {
			continue;
		}
		if ( ! buoyvs_is_valid_rule_id( $rule['id'] ?? null ) ) {
			$channel['sync_rules'][ $i ]['id'] = buoyvs_generate_rule_id();
		}
	}
	return $channel;
}

/**
 * Find a rule's current position by its ID.
 *
 * @param array  $channel Channel config.
 * @param string $rule_id Rule ID.
 * @return int|null Index into sync_rules, or null if not found.
 */
function buoyvs_find_rule_index( array $channel, string $rule_id ): ?int {
	foreach ( $channel['sync_rules'] ?? array() as $i => $rule ) {
		if ( ( $rule['id'] ?? '' ) === $rule_id ) {
			return (int) $i;
		}
	}
	return null;
}

/**
 * Seconds before a sync lock is considered abandoned.
 *
 * @return int
 */
function buoyvs_lock_ttl(): int {
	return 1800;
}

/**
 * Option name of a rule's run lock.
 *
 * @param string $rule_id Rule ID.
 * @return string
 */
function buoyvs_rule_lock_key( string $rule_id ): string {
	return 'buoyvs_lock_' . $rule_id;
}

/**
 * Transient name of a rule's progress counter.
 *
 * @param string $rule_id Rule ID.
 * @return string
 */
function buoyvs_rule_progress_key( string $rule_id ): string {
	return 'buoyvs_prog_' . $rule_id;
}

/**
 * Atomically take a rule's run lock.
 *
 * add_option() is a single INSERT that fails if the row exists, so two
 * concurrent runs cannot both acquire it. An abandoned lock (older than
 * buoyvs_lock_ttl()) is taken over.
 *
 * @param string $rule_id Rule ID.
 * @return bool True if the lock was acquired.
 */
function buoyvs_acquire_rule_lock( string $rule_id ): bool {
	$key = buoyvs_rule_lock_key( $rule_id );
	if ( add_option( $key, time(), '', false ) ) {
		return true;
	}
	wp_cache_delete( $key, 'options' );
	$locked_at = (int) get_option( $key, 0 );
	if ( $locked_at && ( time() - $locked_at ) < buoyvs_lock_ttl() ) {
		return false;
	}
	update_option( $key, time(), false );
	return true;
}

/**
 * Extend a held lock so a long run does not look abandoned.
 *
 * @param string $rule_id Rule ID.
 * @return void
 */
function buoyvs_refresh_rule_lock( string $rule_id ): void {
	update_option( buoyvs_rule_lock_key( $rule_id ), time(), false );
}

/**
 * Release a rule's run lock.
 *
 * @param string $rule_id Rule ID.
 * @return void
 */
function buoyvs_release_rule_lock( string $rule_id ): void {
	delete_option( buoyvs_rule_lock_key( $rule_id ) );
}

/**
 * Whether a rule currently holds a live (non-abandoned) run lock.
 *
 * @param string $rule_id Rule ID.
 * @return bool
 */
function buoyvs_rule_is_locked( string $rule_id ): bool {
	$key = buoyvs_rule_lock_key( $rule_id );
	wp_cache_delete( $key, 'options' );
	$locked_at = (int) get_option( $key, 0 );
	return $locked_at && ( time() - $locked_at ) < buoyvs_lock_ttl();
}

/**
 * Reset rules stuck on "syncing" whose run never started or died.
 *
 * Rules are pre-marked 'syncing' when queued. If WP-Cron never fires (for
 * example DISABLE_WP_CRON with no system cron) or the process is killed, that
 * status would otherwise never clear. A rule is considered stuck when it has
 * no live lock and has been "syncing" for longer than $grace seconds.
 *
 * @param array $channel Channel config.
 * @param int   $grace   Seconds to wait for a queued run to start.
 * @return array{0: array, 1: bool} Updated channel and whether anything changed.
 */
function buoyvs_reset_stuck_rules( array $channel, int $grace = 600 ): array {
	$changed = false;
	foreach ( $channel['sync_rules'] ?? array() as $i => $rule ) {
		if ( 'syncing' !== ( $rule['sync_status'] ?? '' ) ) {
			continue;
		}
		$started = (int) ( $rule['sync_started_at'] ?? 0 );
		if ( $started && ( time() - $started ) < $grace ) {
			continue;
		}
		if ( ! empty( $rule['id'] ) && buoyvs_rule_is_locked( (string) $rule['id'] ) ) {
			continue;
		}

		$errors   = is_array( $rule['sync_errors'] ?? null ) ? $rule['sync_errors'] : array();
		$errors[] = array(
			'timestamp' => time(),
			'error'     => __( 'This sync was queued but never finished. WordPress scheduled tasks (WP-Cron) may be disabled or blocked on this site, or the sync was interrupted.', 'buoy-video-sync' ),
			'code'      => 'stalled',
		);

		$channel['sync_rules'][ $i ]['sync_status'] = 'failed';
		$channel['sync_rules'][ $i ]['sync_errors'] = array_slice( $errors, -5 );
		unset( $channel['sync_rules'][ $i ]['sync_started_at'] );
		$changed = true;
	}
	return array( $channel, $changed );
}

/**
 * Sanitize a channel's YouTube-fetched image field.
 *
 * Stored as array( 'url' => … ) by YouTube_API::get_channel_data().
 *
 * @param mixed $value Raw value.
 * @return array
 */
function buoyvs_sanitize_image_field( $value ): array {
	$url = is_array( $value ) ? ( $value['url'] ?? '' ) : $value;
	return array( 'url' => is_string( $url ) ? esc_url_raw( $url ) : '' );
}

/**
 * Sanitize a list of stored sync errors.
 *
 * @param mixed $errors Raw errors.
 * @return array
 */
function buoyvs_sanitize_sync_errors( $errors ): array {
	if ( ! is_array( $errors ) ) {
		return array();
	}
	$clean = array();
	foreach ( array_slice( array_values( $errors ), -5 ) as $error ) {
		if ( ! is_array( $error ) ) {
			continue;
		}
		$clean[] = array(
			'timestamp' => absint( $error['timestamp'] ?? 0 ),
			'error'     => sanitize_text_field( (string) ( $error['error'] ?? '' ) ),
			'code'      => sanitize_key( (string) ( $error['code'] ?? '' ) ),
		);
	}
	return $clean;
}

/**
 * Sanitize a full channel object from form POST or an imported JSON file.
 *
 * Fields not present in $raw_channel (e.g. YouTube-API-fetched data the
 * Channels page form never submits) fall back to $preserve_from, then to a
 * safe default. Per-rule runtime state (sync_status, last_synced,
 * sync_count, sync_errors, created_at, created_by) is carried from the
 * $preserve_from rule with the same permanent ID (the normal
 * edit-existing-rule case), else from the raw rule itself (an imported rule
 * carries its own history), else a fresh default.
 *
 * Does NOT resolve a channel handle/URL into a channel ID or call the
 * YouTube API — the Channels page save handler does that itself and
 * passes the already-resolved ID in via $raw_channel['youtube_id'].
 *
 * @param array $raw_channel   Raw channel data — already wp_unslash()'d if from $_POST.
 * @param array $preserve_from Existing channel to inherit unset fields from. Pass array() for a full replace (e.g. import).
 * @return array Sanitized channel.
 */
function buoyvs_sanitize_channel( array $raw_channel, array $preserve_from = array() ): array {
	$youtube_id = isset( $raw_channel['youtube_id'] ) ? sanitize_text_field( $raw_channel['youtube_id'] ) : '';
	if ( '' !== $youtube_id && ! preg_match( '#^UC[A-Za-z0-9_-]{22}$#', $youtube_id ) ) {
		$youtube_id = $preserve_from['youtube_id'] ?? '';
	}

	$channel = array(
		'youtube_id'    => $youtube_id,
		'channel_title' => sanitize_text_field( (string) ( $raw_channel['channel_title'] ?? ( $preserve_from['channel_title'] ?? '' ) ) ),
	);

	$field_sanitizers = array(
		'channel_description' => static fn( $v ) => sanitize_textarea_field( (string) $v ),
		'subscriber_count'    => 'absint',
		'video_count'         => 'absint',
		'profile_picture'     => 'buoyvs_sanitize_image_field',
		'banner_image'        => 'buoyvs_sanitize_image_field',
		'etag'                => static fn( $v ) => sanitize_text_field( (string) $v ),
	);
	foreach ( $field_sanitizers as $key => $sanitizer ) {
		if ( isset( $raw_channel[ $key ] ) ) {
			$channel[ $key ] = $sanitizer( $raw_channel[ $key ] );
		} elseif ( isset( $preserve_from[ $key ] ) ) {
			$channel[ $key ] = $sanitizer( $preserve_from[ $key ] );
		}
	}

	// Existing rules keyed by their permanent ID, so runtime state follows the
	// rule even when rules are deleted or reordered on the page.
	$old_rules    = array_values( $preserve_from['sync_rules'] ?? array() );
	$old_rules_by = array();
	foreach ( $old_rules as $old_rule ) {
		if ( buoyvs_is_valid_rule_id( $old_rule['id'] ?? null ) ) {
			$old_rules_by[ $old_rule['id'] ] = $old_rule;
		}
	}

	$raw_rules = is_array( $raw_channel['sync_rules'] ?? null ) ? array_values( $raw_channel['sync_rules'] ) : array();
	$new_rules = array();

	foreach ( $raw_rules as $i => $raw_rule ) {
		if ( ! is_array( $raw_rule ) ) {
			continue;
		}
		$new_rule = buoyvs_sanitize_sync_rule( $raw_rule );
		$raw_id   = $raw_rule['id'] ?? null;

		if ( buoyvs_is_valid_rule_id( $raw_id ) && isset( $old_rules_by[ $raw_id ] ) ) {
			// Normal edit of an existing rule.
			$carry          = $old_rules_by[ $raw_id ];
			$is_own         = true;
			$new_rule['id'] = $raw_id;
		} elseif ( ! buoyvs_is_valid_rule_id( $raw_id ) && empty( $old_rules_by ) && isset( $old_rules[ $i ] ) ) {
			// Legacy config saved before rule IDs existed: match by position once.
			$carry          = $old_rules[ $i ];
			$is_own         = true;
			$new_rule['id'] = buoyvs_generate_rule_id();
		} else {
			// New rule, or an imported rule carrying its own history.
			$carry          = $raw_rule;
			$is_own         = false;
			$new_rule['id'] = ( buoyvs_is_valid_rule_id( $raw_id ) && empty( $preserve_from ) ) ? $raw_id : buoyvs_generate_rule_id();
		}

		$status = (string) ( $carry['sync_status'] ?? '' );
		if ( ! in_array( $status, buoyvs_valid_sync_statuses(), true ) || ( ! $is_own && 'syncing' === $status ) ) {
			$status = '';
		}

		$new_rule['sync_status'] = $status;
		$new_rule['last_synced'] = absint( $carry['last_synced'] ?? 0 );
		$new_rule['sync_count']  = absint( $carry['sync_count'] ?? 0 );
		$new_rule['sync_errors'] = buoyvs_sanitize_sync_errors( $carry['sync_errors'] ?? array() );
		$new_rule['created_at']  = ! empty( $carry['created_at'] ) ? absint( $carry['created_at'] ) : time();
		// Never trust a user ID from an imported file (it may belong to another site).
		$new_rule['created_by']  = ( $is_own && ! empty( $carry['created_by'] ) ) ? absint( $carry['created_by'] ) : get_current_user_id();
		if ( $is_own && 'syncing' === $status && ! empty( $carry['sync_started_at'] ) ) {
			$new_rule['sync_started_at'] = absint( $carry['sync_started_at'] );
		}

		$new_rules[] = $new_rule;
	}
	$channel['sync_rules'] = $new_rules;

	$channel['default_post_type'] = isset( $raw_channel['default_post_type'] )
		? sanitize_key( $raw_channel['default_post_type'] )
		: '';

	$default_post_status = isset( $raw_channel['default_post_status'] )
		? sanitize_text_field( $raw_channel['default_post_status'] )
		: 'publish';
	$channel['default_post_status'] = in_array( $default_post_status, buoyvs_valid_post_statuses(), true )
		? $default_post_status
		: 'publish';

	return $channel;
}

/**
 * Sanitize a single sync rule from form input.
 *
 * Shared by Channel, Playlist, and Channels_Page save handlers.
 *
 * @param array $rule Raw rule data from $_POST.
 * @return array Sanitized rule.
 */
function buoyvs_sanitize_sync_rule( $rule ) {
	$action = isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '';
	// Unknown values sanitize to '' so the rule renders as unconfigured.
	if ( ! in_array( $action, buoyvs_valid_actions(), true ) ) {
		$action = '';
	}

	$schedule = isset( $rule['schedule'] ) ? sanitize_text_field( $rule['schedule'] ) : 'once';
	if ( ! in_array( $schedule, buoyvs_valid_schedules(), true ) ) {
		$schedule = 'once';
	}

	$post_status = isset( $rule['post_status'] ) ? sanitize_text_field( $rule['post_status'] ) : 'publish';
	if ( ! in_array( $post_status, buoyvs_valid_post_statuses(), true ) ) {
		$post_status = 'publish';
	}

	$sanitized = array(
		'enabled'         => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
		'title'           => isset( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : '',
		'max_videos'      => isset( $rule['max_videos'] ) ? absint( $rule['max_videos'] ) : 50,
		'schedule'        => $schedule,
		'custom_schedule' => isset( $rule['custom_schedule'] ) ? max( 1, absint( $rule['custom_schedule'] ) ) : 24,
		'action'          => $action,
		'destination_post_type' => isset( $rule['destination_post_type'] ) ? sanitize_key( $rule['destination_post_type'] ) : '',
		'post_status'     => $post_status,
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
function buoyvs_get_metabox_tabs( string $type, int $post_id ): array {
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
	$tabs = apply_filters( 'buoyvs_metabox_tabs', array(), $type, $post_id );

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
 * Render extra metabox tab buttons (call inside .buoyvs-channel-tabs-nav).
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return void
 */
function buoyvs_render_extra_tab_nav( string $type, int $post_id ): void {
	foreach ( buoyvs_get_metabox_tabs( $type, $post_id ) as $tab ) {
		printf(
			'<button type="button" class="buoyvs-channel-tab-btn" data-tab="%1$s" role="tab" aria-selected="false">%2$s</button>',
			esc_attr( $tab['slug'] ),
			esc_html( $tab['label'] )
		);
	}
}

/**
 * Render extra metabox tab panels (call inside .buoyvs-channel-tabs-content).
 *
 * @param string $type    Metabox type: 'video', 'playlist', or 'channel'.
 * @param int    $post_id Current post ID.
 * @return void
 */
function buoyvs_render_extra_tab_panels( string $type, int $post_id ): void {
	foreach ( buoyvs_get_metabox_tabs( $type, $post_id ) as $tab ) {
		printf(
			'<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="%s" role="tabpanel">',
			esc_attr( $tab['slug'] )
		);
		call_user_func( $tab['render'], $post_id );
		echo '</div>';
	}
}
