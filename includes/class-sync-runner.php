<?php
declare(strict_types=1);

/**
 * Sync runner.
 *
 * Executes one sync rule end-to-end: reads the rule from term meta,
 * calls the YouTube API, evaluates conditions, imports videos, and
 * writes results back to term meta.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sync_Runner
 *
 * Orchestrates a single sync rule execution.
 */
class Sync_Runner {

	/**
	 * Index of the rule currently being executed.
	 * Set at the start of run() so record_success/record_error can write to the correct rule.
	 *
	 * @var int
	 */
	private int $current_rule_index = 0;

	/**
	 * YouTube API wrapper.
	 *
	 * @var YouTube_API
	 */
	private YouTube_API $api;

	/**
	 * Condition evaluator.
	 *
	 * @var Condition_Evaluator
	 */
	private Condition_Evaluator $evaluator;

	/**
	 * Video importer.
	 *
	 * @var Video_Importer
	 */
	private Video_Importer $importer;

	/**
	 * Constructor.
	 *
	 * @param YouTube_API         $api       YouTube API wrapper.
	 * @param Condition_Evaluator $evaluator Condition evaluator.
	 * @param Video_Importer      $importer  Video importer.
	 */
	public function __construct( YouTube_API $api, Condition_Evaluator $evaluator, Video_Importer $importer ) {
		$this->api       = $api;
		$this->evaluator = $evaluator;
		$this->importer  = $importer;
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Execute a sync rule.
	 *
	 * Called by Sync_Scheduler::dispatch_sync() when a WP Cron event fires.
	 *
	 * @param string $source_type  'channel' or 'playlist'.
	 * @param int    $term_id      WordPress term ID of the source.
	 * @param int    $rule_index   0-based index of the rule in sync_rules[].
	 * @return void
	 */
	public function run( string $source_type, int $term_id, int $rule_index ): void {
		$rule = $this->load_rule( $source_type, $term_id, $rule_index );

		// Rule missing or disabled — nothing to do.
		if ( null === $rule || empty( $rule['enabled'] ) ) {
			return;
		}

		$this->current_rule_index = $rule_index;

		$this->mark_syncing( $source_type, $term_id, $rule_index );

		$syncing_cleared = false;
		register_shutdown_function(
			function () use ( $source_type, $term_id, $rule_index, &$syncing_cleared ) {
				if ( $syncing_cleared ) {
					return;
				}
				$error = error_get_last();
				if ( $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) {
					$this->clear_syncing( $source_type, $term_id, $rule_index );
				}
			}
		);

		$action = $rule['action'] ?? '';

		try {
			switch ( $action ) {
				case 'videos_sync_new':
					$this->handle_videos_sync_new( $source_type, $term_id, $rule );
					break;

				case 'videos_update_all':
				case 'videos_update_non_modified':
				case 'videos_update_specific_all':
				case 'videos_update_specific_non_modified':
					$this->handle_videos_update( $source_type, $term_id, $rule, $action );
					break;

				default:
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "YouSync: unknown action '{$action}' (term {$term_id}, rule {$rule_index})." );
					return;
			}

			$this->record_success( $source_type, $term_id );

		} catch ( \Exception $e ) {
			$this->record_error( $source_type, $term_id, $e->getMessage(), 'exception' );
		} finally {
			$this->clear_syncing( $source_type, $term_id, $rule_index );
			$syncing_cleared = true;
		}

		// For 'once' schedule: auto-disable the rule after it fires.
		if ( isset( $rule['schedule'] ) && 'once' === $rule['schedule'] ) {
			$this->disable_once_rule( $source_type, $term_id, $rule_index );
		}
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the videos_sync_new action.
	 *
	 * Fetches all videos from the channel uploads playlist or playlist,
	 * filters out already-imported videos, evaluates conditions, and
	 * imports new videos that pass.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID of the source channel/playlist.
	 * @param array  $rule        Rule array from term meta.
	 * @return void
	 * @throws \RuntimeException On unrecoverable API failure.
	 */
	private function handle_videos_sync_new( string $source_type, int $term_id, array $rule ): void {
		$conditions = $rule['conditions'] ?? array();

		if ( 'channel' === $source_type ) {
			// Channels: need to get the uploads playlist ID first.
			$meta = $this->get_term_meta_data( $source_type, $term_id );
			if ( ! $meta || empty( $meta['channel_id'] ) ) {
				throw new \RuntimeException( 'Channel ID not found in term meta.' );
			}

			$channel_data = $this->api->get_channel_data( $meta['channel_id'] );
			if ( is_wp_error( $channel_data ) ) {
				$this->record_error( $source_type, $term_id, $channel_data->get_error_message(), $channel_data->get_error_code() );
				return;
			}

			// Opportunistically refresh channel metadata (title, subscribers, images, etc.)
			// while we already have the API response in hand.
			$this->refresh_channel_meta( $term_id, $channel_data );

			$playlist_id = $channel_data['uploads_playlist_id'] ?? '';
			if ( empty( $playlist_id ) ) {
				throw new \RuntimeException( "Could not retrieve uploads playlist ID for channel '{$meta['channel_id']}'." );
			}
		} else {
			// Playlists: playlist ID is stored directly in term meta.
			$meta = $this->get_term_meta_data( $source_type, $term_id );
			if ( ! $meta || empty( $meta['playlist_id'] ) ) {
				throw new \RuntimeException( 'Playlist ID not found in term meta.' );
			}
			$playlist_id = $meta['playlist_id'];
		}

		// Fetch all video IDs from the playlist (paginated inside the API wrapper).
		$items = $this->api->get_playlist_items( $playlist_id );
		if ( is_wp_error( $items ) ) {
			$this->record_error( $source_type, $term_id, $items->get_error_message(), $items->get_error_code() );
			return;
		}

		if ( empty( $items ) ) {
			return; // Nothing to sync.
		}

		// Extract video IDs from the playlist items.
		$all_ids = array_filter( array_column( $items, 'video_id' ) );

		// Split into new vs already-imported.
		$existing_ids = $this->get_existing_video_ids();
		$new_ids      = array_values( array_diff( $all_ids, $existing_ids ) );
		$existing_ids_here = array_values( array_intersect( $all_ids, $existing_ids ) );

		// Already-imported videos: ensure the channel term is assigned so the post
		// stays associated with this channel's configured taxonomy.
		$config            = get_option( 'yousync_channel_config', array() );
		$post_type         = $config['destination_post_type'] ?? 'post';
		$existing_post_map = $this->importer->find_posts_by_video_ids( $existing_ids_here, $post_type );

		if ( empty( $new_ids ) ) {
			return; // All videos already imported.
		}

		// Batch-fetch full video details and import.
		$imported = $this->batch_fetch_and_import( $new_ids, $conditions, $source_type, $term_id );

		// Warn when candidates were available but nothing was imported.
		// This usually means the sync conditions are too restrictive or contain a typo.
		if ( 0 === $imported ) {
			$candidate_count = count( $new_ids );
			if ( ! empty( $conditions ) ) {
				$message = sprintf(
					/* translators: %d: number of candidate videos */
					_n(
						'No videos were imported. %d candidate was fetched but did not match the sync conditions. Check your conditions for typos.',
						'No videos were imported. %d candidates were fetched but none matched the sync conditions. Check your conditions for typos.',
						$candidate_count,
						'yousync'
					),
					$candidate_count
				);
			} else {
				$message = sprintf(
					/* translators: %d: number of candidate videos */
					_n(
						'No videos were imported. %d candidate was fetched from the API but could not be saved.',
						'No videos were imported. %d candidates were fetched from the API but none could be saved.',
						$candidate_count,
						'yousync'
					),
					$candidate_count
				);
			}

			Sync_Logger::log_error( $source_type, $term_id, $message, 'no_results' );
		}
	}

	/**
	 * Handle videos_update_* actions (all four update modes).
	 *
	 * Fetches the full video list from the source, finds matching WP posts,
	 * and calls Video_Importer::update() on each.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param array  $rule        Sync rule array.
	 * @param string $action      Action slug.
	 * @return void
	 * @throws \RuntimeException On missing source ID.
	 */
	private function handle_videos_update( string $source_type, int $term_id, array $rule, string $action ): void {
		$conditions        = $rule['conditions'] ?? array();
		$specific_metadata = $rule['specific_metadata'] ?? array();

		// Map action to the mode string expected by Video_Importer::update().
		$mode_map = array(
			'videos_update_all'                  => 'update_all',
			'videos_update_non_modified'         => 'update_non_modified',
			'videos_update_specific_all'         => 'update_specific_all',
			'videos_update_specific_non_modified' => 'update_specific_non_modified',
		);
		$mode = $mode_map[ $action ] ?? 'update_all';

		// Get the playlist ID to iterate.
		if ( 'channel' === $source_type ) {
			$data = $this->get_term_meta_data( 'channel', $term_id );
			if ( ! $data || empty( $data['channel_id'] ) ) {
				throw new \RuntimeException( 'Channel ID not found in term meta.' );
			}
			$channel_data = $this->api->get_channel_data( $data['channel_id'] );
			if ( is_wp_error( $channel_data ) ) {
				$this->record_error( $source_type, $term_id, $channel_data->get_error_message(), $channel_data->get_error_code() );
				return;
			}
			$playlist_id = $channel_data['uploads_playlist_id'] ?? '';
		} else {
			$data = $this->get_term_meta_data( 'playlist', $term_id );
			if ( ! $data || empty( $data['playlist_id'] ) ) {
				throw new \RuntimeException( 'Playlist ID not found in term meta.' );
			}
			$playlist_id = $data['playlist_id'];
		}

		$items = $this->api->get_playlist_items( $playlist_id );
		if ( is_wp_error( $items ) ) {
			$this->record_error( $source_type, $term_id, $items->get_error_message(), $items->get_error_code() );
			return;
		}

		if ( empty( $items ) ) {
			return;
		}

		$all_ids = array_filter( array_column( $items, 'video_id' ) );
		$this->batch_fetch_and_update( $all_ids, $conditions, $source_type, $term_id, $mode, $specific_metadata );
	}

	// -------------------------------------------------------------------------
	// Batch fetch + import
	// -------------------------------------------------------------------------

	/**
	 * Fetch video details in batches of 50, evaluate conditions, and import.
	 *
	 * @param string[] $video_ids   Video IDs to process.
	 * @param array    $conditions  Conditions array from the rule.
	 * @param string   $source_type 'channel' or 'playlist'.
	 * @param int      $term_id     Source term ID.
	 * @return int Number of videos successfully imported.
	 */
	private function batch_fetch_and_import(
		array $video_ids,
		array $conditions,
		string $source_type,
		int $term_id
	): int {
		$chunks   = array_chunk( $video_ids, 50 );
		$imported = 0;

		foreach ( $chunks as $chunk ) {
			$videos = $this->api->get_videos_by_ids( $chunk );

			if ( is_wp_error( $videos ) ) {
				// Record the error but continue — one bad batch shouldn't abort the rest.
				$this->record_error( $source_type, $term_id, $videos->get_error_message(), $videos->get_error_code() );
				continue;
			}

			foreach ( $videos as $video_data ) {
				if ( ! $this->evaluator->evaluate_all( $video_data, $conditions ) ) {
					continue; // Video does not pass conditions.
				}

				$result = $this->importer->import( $video_data, $config );

				if ( is_wp_error( $result ) ) {
					// Log import errors but continue with remaining videos.
					$this->record_error( $source_type, $term_id, $result->get_error_message(), $result->get_error_code() );
				} else {
					++$imported;
				}
			}
		}

		return $imported;
	}

	/**
	 * Fetch video details in batches of 50, evaluate conditions, and update.
	 *
	 * Only processes video IDs that already have a matching WP post.
	 *
	 * @param string[] $video_ids         All video IDs from the source playlist.
	 * @param array    $conditions        Conditions from the rule.
	 * @param string   $source_type       'channel' or 'playlist'.
	 * @param int      $term_id           Source term ID.
	 * @param string   $mode              Update mode (update_all, update_non_modified, etc.).
	 * @param string[] $specific_metadata Fields to update (for specific modes).
	 * @return void
	 */
	private function batch_fetch_and_update(
		array $video_ids,
		array $conditions,
		string $source_type,
		int $term_id,
		string $mode,
		array $specific_metadata
	): void {
		$chunks = array_chunk( $video_ids, 50 );

		foreach ( $chunks as $chunk ) {
			$videos = $this->api->get_videos_by_ids( $chunk );

			if ( is_wp_error( $videos ) ) {
				$this->record_error( $source_type, $term_id, $videos->get_error_message(), $videos->get_error_code() );
				continue;
			}

			foreach ( $videos as $video_data ) {
				if ( ! $this->evaluator->evaluate_all( $video_data, $conditions ) ) {
					continue;
				}

				$config  = get_option( 'yousync_channel_config', array() );
				$post_id = $this->importer->find_post_by_video_id( $video_data['video_id'], $config['destination_post_type'] ?? 'post' );
				if ( ! $post_id ) {
					continue; // Not imported yet — update modes skip new videos.
				}

				$result = $this->importer->update( $post_id, $video_data, $mode, $config, $specific_metadata );
				if ( is_wp_error( $result ) ) {
					$this->record_error( $source_type, $term_id, $result->get_error_message(), $result->get_error_code() );
				}
			}
		}
	}

	// -------------------------------------------------------------------------
	// Lookup helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all YouTube video IDs already imported as yousync_videos posts.
	 *
	 * Uses the flat _yousync_video_id meta key for an indexed lookup.
	 *
	 * @return string[] Array of YouTube video IDs.
	 */
	private function get_existing_video_ids(): array {
		$config    = get_option( 'yousync_channel_config', array() );
		$post_type = $config['destination_post_type'] ?? 'post';

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_yousync_video_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return array();
		}

		$video_ids = array();
		foreach ( $query->posts as $post_id ) {
			$vid = get_post_meta( (int) $post_id, '_yousync_video_id', true );
			if ( $vid ) {
				$video_ids[] = $vid;
			}
		}

		return $video_ids;
	}

	// -------------------------------------------------------------------------
	// Term meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Load a rule from term meta.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index into sync_rules[].
	 * @return array|null Rule array, or null if not found.
	 */
	private function load_rule( string $source_type, int $term_id, int $rule_index ): ?array {
		$data = $this->get_term_meta_data( $source_type, $term_id );

		if ( ! $data ) {
			return null;
		}

		return $data['sync_rules'][ $rule_index ] ?? null;
	}

	/**
	 * Merge fresh channel API data into the channel's term meta.
	 *
	 * Called as a side effect of handle_videos_sync_new() so that channel
	 * metadata (title, subscribers, profile picture, banner) stays up to date
	 * even when no explicit channel_update rule has been configured.
	 *
	 * @param int   $term_id      Channel term ID.
	 * @param array $channel_data Data returned by YouTube_API::get_channel_data().
	 * @return void
	 */
	private function refresh_channel_meta( int $term_id, array $channel_data ): void {
		$data = $this->get_term_meta_data( 'channel', $term_id );
		if ( ! $data ) {
			return;
		}

		$fields = array( 'channel_title', 'channel_description', 'subscriber_count', 'video_count', 'profile_picture', 'banner_image' );
		foreach ( $fields as $field ) {
			if ( isset( $channel_data[ $field ] ) ) {
				$data[ $field ] = $channel_data[ $field ];
			}
		}

		$data['etag'] = $channel_data['etag'] ?? ( $data['etag'] ?? '' );
		update_term_meta( $term_id, 'yousync_channel', wp_slash( wp_json_encode( $data ) ) );
	}

	/**
	 * Read and decode the source term's JSON meta.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @return array|null Decoded data array, or null on failure.
	 */
	private function get_term_meta_data( string $source_type, int $term_id ): ?array {
		$meta_key = $this->meta_key( $source_type );
		$raw      = get_term_meta( $term_id, $meta_key, true );

		if ( ! $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Return the term meta key for a source type.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @return string Meta key.
	 */
	private function meta_key( string $source_type ): string {
		return 'playlist' === $source_type ? 'yousync_playlist' : 'yousync_channel';
	}

	// -------------------------------------------------------------------------
	// Status recording
	// -------------------------------------------------------------------------

	/**
	 * Record a successful sync run in term meta.
	 *
	 * Updates last_synced, increments sync_count, sets sync_status = 'success',
	 * and clears sync_errors.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @return void
	 */
	private function record_success( string $source_type, int $term_id ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data || ! isset( $data['sync_rules'][ $this->current_rule_index ] ) ) {
			return;
		}

		$rule                = &$data['sync_rules'][ $this->current_rule_index ];
		$rule['last_synced'] = time();
		$rule['sync_count']  = (int) ( $rule['sync_count'] ?? 0 ) + 1;
		$rule['sync_status'] = 'success';
		$rule['sync_errors'] = array();
		unset( $rule );

		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_slash( wp_json_encode( $data ) ) );
	}

	/**
	 * Append an error to term meta sync_errors (max 5 most recent).
	 *
	 * Sets sync_status = 'failed'.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param string $error       Human-readable error message.
	 * @param string $code        Error code string.
	 * @return void
	 */
	private function record_error( string $source_type, int $term_id, string $error, string $code = '' ): void {
		// Write to the global error log (single option, capped at 50 entries).
		Sync_Logger::log_error( $source_type, $term_id, $error, $code );

		// Also store the last 5 errors in the specific rule's meta for quick display.
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data || ! isset( $data['sync_rules'][ $this->current_rule_index ] ) ) {
			return;
		}

		$entry = array(
			'timestamp' => time(),
			'error'     => $error,
			'code'      => $code,
		);

		$rule                = &$data['sync_rules'][ $this->current_rule_index ];
		$errors              = $rule['sync_errors'] ?? array();
		$errors[]            = $entry;
		$rule['sync_errors'] = array_slice( $errors, -5 );
		$rule['sync_status'] = 'failed';
		unset( $rule );

		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_slash( wp_json_encode( $data ) ) );
	}

	/**
	 * Mark a sync rule as actively syncing.
	 *
	 * Sets sync_status = 'syncing' and records sync_started_at timestamp.
	 * The timestamp is used to detect stale locks if the process is killed
	 * at the OS level (beyond PHP's shutdown handler reach).
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule being executed.
	 * @return void
	 */
	private function mark_syncing( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$rule                    = &$data['sync_rules'][ $rule_index ];
		$rule['sync_status']     = 'syncing';
		$rule['sync_started_at'] = time();
		unset( $rule );

		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_slash( wp_json_encode( $data ) ) );
	}

	/**
	 * Clear the syncing lock from a sync rule.
	 *
	 * Called in the finally block of run() and by the register_shutdown_function
	 * callback on PHP fatal errors. Unsets sync_started_at and resets
	 * sync_status to '' if it is still 'syncing' (i.e. neither record_success()
	 * nor record_error() ran to completion).
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule being executed.
	 * @return void
	 */
	private function clear_syncing( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$rule = &$data['sync_rules'][ $rule_index ];
		unset( $rule['sync_started_at'] );
		if ( 'syncing' === ( $rule['sync_status'] ?? '' ) ) {
			$rule['sync_status'] = '';
		}
		unset( $rule );

		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_slash( wp_json_encode( $data ) ) );
	}

	/**
	 * Set a 'once' rule's enabled flag to false after it fires.
	 *
	 * The rule remains visible in the UI with the toggle off so the user
	 * can see it ran and optionally re-enable it.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Index of the rule to disable.
	 * @return void
	 */
	private function disable_once_rule( string $source_type, int $term_id, int $rule_index ): void {
		$data = $this->get_term_meta_data( $source_type, $term_id );
		if ( ! $data || ! isset( $data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		$data['sync_rules'][ $rule_index ]['enabled'] = false;
		update_term_meta( $term_id, $this->meta_key( $source_type ), wp_slash( wp_json_encode( $data ) ) );
	}
}
