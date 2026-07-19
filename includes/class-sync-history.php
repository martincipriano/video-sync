<?php
declare(strict_types=1);
/**
 * Sync history — per-channel run log.
 *
 * Stores the last 50 sync run entries (max 90 days) for each channel
 * in a non-autoloaded wp_options entry keyed by YouTube channel ID.
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sync_History
 */
class Sync_History {

	const MAX_ENTRIES = 30;
	const MAX_AGE     = 30 * DAY_IN_SECONDS;

	/**
	 * Return the wp_options key for a given YouTube channel ID.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return string
	 */
	public static function option_key( string $youtube_id ): string {
		return 'buoyvs_sync_history_' . sanitize_key( $youtube_id );
	}

	/**
	 * Append a run entry to the channel's history log.
	 *
	 * Prepends the entry, prunes old/excess entries, then persists.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @param array  $entry      Run entry (timestamp, rule_action, rule_index, duration, has_error, errors).
	 * @return void
	 */
	public static function append( string $youtube_id, array $entry ): void {
		if ( ! $youtube_id ) {
			return;
		}
		$key = self::option_key( $youtube_id );
		$log = get_option( $key, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		update_option( $key, self::prune( $log ), false );
	}

	/**
	 * Retrieve and prune the history log for a channel.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return array
	 */
	public static function get( string $youtube_id ): array {
		if ( ! $youtube_id ) {
			return array();
		}
		$log = get_option( self::option_key( $youtube_id ), array() );
		return self::prune( is_array( $log ) ? $log : array() );
	}

	/**
	 * Whether the latest error run has not yet been read by the user.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return bool
	 */
	public static function has_unread_errors( string $youtube_id ): bool {
		$log = self::get( $youtube_id );
		foreach ( $log as $entry ) {
			if ( ! empty( $entry['has_error'] ) ) {
				$read_at = (int) get_option( self::read_option_key( $youtube_id ), 0 );
				return ( $entry['timestamp'] ?? 0 ) > $read_at;
			}
		}
		return false;
	}

	/**
	 * Count error runs that have not yet been read by the user.
	 *
	 * An error entry is unread when its timestamp is newer than the channel's
	 * read-at marker. Because that marker only moves forward, this equals the
	 * number of error runs logged since the user last opened the History tab.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return int
	 */
	public static function unread_error_count( string $youtube_id ): int {
		if ( ! $youtube_id ) {
			return 0;
		}
		$read_at = (int) get_option( self::read_option_key( $youtube_id ), 0 );
		$count   = 0;
		foreach ( self::get( $youtube_id ) as $entry ) {
			if ( ! empty( $entry['has_error'] ) && ( $entry['timestamp'] ?? 0 ) > $read_at ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Mark all current errors as read for a channel.
	 *
	 * Stores the current timestamp so any error entry older than it is considered read.
	 * Resets automatically when a new sync run with errors produces a newer timestamp.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return void
	 */
	public static function mark_read( string $youtube_id ): void {
		if ( ! $youtube_id ) {
			return;
		}
		update_option( self::read_option_key( $youtube_id ), time(), false );
	}

	/**
	 * Delete a channel's history log and read-at marker.
	 *
	 * Called when the channel is deleted, so its history does not outlive it.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return void
	 */
	public static function delete( string $youtube_id ): void {
		if ( '' === $youtube_id ) {
			return;
		}
		delete_option( self::option_key( $youtube_id ) );
		delete_option( self::read_option_key( $youtube_id ) );
	}

	/**
	 * Return the wp_options key for the read-at timestamp.
	 *
	 * @param string $youtube_id YouTube channel ID.
	 * @return string
	 */
	private static function read_option_key( string $youtube_id ): string {
		return 'buoyvs_history_read_at_' . sanitize_key( $youtube_id );
	}

	/**
	 * Remove entries older than MAX_AGE and trim to MAX_ENTRIES.
	 *
	 * @param array $log Raw log array.
	 * @return array
	 */
	private static function prune( array $log ): array {
		$cutoff = time() - self::MAX_AGE;
		$log    = array_values( array_filter( $log, fn( $e ) => ( $e['timestamp'] ?? 0 ) >= $cutoff ) );
		return array_slice( $log, 0, self::MAX_ENTRIES );
	}
}
