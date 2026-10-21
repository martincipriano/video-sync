<?php
declare(strict_types=1);
/**
 * Sync runner.
 *
 * Executes one sync rule end-to-end: reads the channel config, calls the
 * YouTube API, imports videos/playlists/channel data, and writes results
 * back to buoyvs_channel_config.
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

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
	 * Set at the start of run(); reported to hooks and the history log.
	 *
	 * @var int
	 */
	private int $current_rule_index = 0;

	/**
	 * Unix timestamp recorded at the start of a sync run.
	 * Used to compute run duration for the history log.
	 *
	 * @var int
	 */
	private int $run_started_at = 0;

	/**
	 * Errors accumulated during the current sync run (non-terminal).
	 * Flushed into the history entry at the end of the run.
	 *
	 * @var array
	 */
	private array $run_errors = array();

	/**
	 * Number of items (videos/playlists/channels) processed in the current run.
	 * Set by each action handler; written into the history entry.
	 *
	 * @var int
	 */
	private int $current_run_count = 0;

	/**
	 * Permanent ID of the rule currently being executed.
	 *
	 * State is written back by ID (not position) so a rule deleted or
	 * reordered on the Channels page mid-run is never confused with another.
	 *
	 * @var string
	 */
	private string $current_rule_id = '';

	/**
	 * YouTube ID of the channel the current run belongs to. If the channel is
	 * replaced or deleted mid-run, state writes are skipped.
	 *
	 * @var string
	 */
	private string $current_youtube_id = '';

	/**
	 * Whether the current run hit a terminal error.
	 *
	 * @var bool
	 */
	private bool $run_failed = false;

	/**
	 * First terminal error message of the current run (for buoyvs_after_sync).
	 *
	 * @var string
	 */
	private string $terminal_error = '';

	/**
	 * Unix time the run lock was last extended.
	 *
	 * @var int
	 */
	private int $lock_refreshed_at = 0;

	/**
	 * Whether the current run is the single option-based channel (Channels page).
	 *
	 * @var bool
	 */
	private bool $is_option_channel = false;

	/**
	 * In-memory copy of the source data when running an option-based channel.
	 * Returned by get_channel_data(). Kept in sync after
	 * every state write (see mutate_channel()) so subsequent reads within the same run see
	 * the latest state.
	 *
	 * @var array|null
	 */
	private ?array $source_data_override = null;

	/**
	 * YouTube API wrapper.
	 *
	 * @var YouTube_API
	 */
	private YouTube_API $api;

	/**
	 * Video importer.
	 *
	 * @var Video_Importer
	 */
	private Video_Importer $importer;

	/**
	 * Constructor.
	 *
	 * @param YouTube_API    $api      YouTube API wrapper.
	 * @param Video_Importer $importer Video importer.
	 */
	public function __construct( YouTube_API $api, Video_Importer $importer ) {
		$this->api      = $api;
		$this->importer = $importer;
	}

	// -------------------------------------------------------------------------
	// Public entry point
	// -------------------------------------------------------------------------

	/**
	 * Execute a sync rule.
	 *
	 * Called by Sync_Scheduler::dispatch_config_sync() when a WP Cron event fires.
	 *
	 * @param string $source_type  'channel' or 'playlist'.
	 * @param int    $term_id      WordPress term ID of the source.
	 * @param int    $rule_index   0-based index of the rule in sync_rules[].
	 * @return void
	 */
	public function run( string $source_type, int $term_id, int $rule_index ): void {
		$rule = $this->load_rule( $source_type, $term_id, $rule_index );

		// Rule missing or disabled — nothing to do.
		if ( null === $rule || empty( $rule['enabled'] ) || '' === $this->current_rule_id ) {
			return;
		}

		// Concurrency guard: stop a second cron tick or a manual trigger from
		// running the same rule while it is already in flight. The lock is an
		// option row taken with a single INSERT, so it is atomic.
		if ( ! buoyvs_acquire_rule_lock( $this->current_rule_id ) ) {
			return;
		}
		$this->lock_refreshed_at = time();

		$this->current_rule_index = $rule_index;
		$this->run_failed         = false;
		$this->terminal_error     = '';

		$action   = $rule['action'] ?? '';
		$schedule = $rule['schedule'] ?? 'once';

		/**
		 * Fires right before a sync rule starts running.
		 *
		 * @param array  $rule        The sync rule about to run.
		 * @param int    $rule_index  0-based index of the rule in sync_rules[].
		 * @param string $source_type 'channel' or 'playlist'.
		 * @param int    $source_id   WordPress term ID of the source.
		 */
		do_action( 'buoyvs_before_sync', $rule, $rule_index, $source_type, $term_id );

		// "Once" runs may import an entire channel back-catalog in a single
		// execution — lift PHP limits so a large import completes instead of
		// timing out mid-run. Recurring runs stay within normal cron limits since
		// each tick only needs to catch up on what's new since the last run.
		if ( 'once' === $schedule ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Required so a large back-catalog import completes in one run instead of timing out.
			}
			wp_raise_memory_limit( 'admin' );
		}

		$this->mark_syncing();
		$this->write_progress( 0, 0 ); // Reset any stale progress from a previous run.

		// A PHP fatal skips the finally block below; finish the run from the
		// shutdown handler instead so the rule is not left "syncing" and a
		// "once" rule is still switched off.
		$finished = false;
		register_shutdown_function(
			function () use ( $schedule, &$finished ) {
				if ( $finished ) {
					return;
				}
				$error = error_get_last();
				if ( $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) {
					$this->record_error( 'channel', 0, sprintf( 'PHP fatal error: %s', $error['message'] ), 'fatal' );
					$this->finish_run( $schedule );
				}
			}
		);

		try {
			switch ( $action ) {
				// ---- Video sync ----
				case 'videos_sync_new':
					$this->handle_videos_sync_new( $source_type, $term_id, $rule );
					break;

				// ---- Channel ----
				case 'channel_sync_new':
					$this->handle_channel_sync_new( $term_id, $rule );
					break;

				// ---- Playlists from channel ----
				case 'playlists_sync_new':
					$this->handle_playlists_sync( $term_id, $rule, $action );
					break;

				default:
					$this->record_error(
						$source_type,
						$term_id,
						/* translators: %s: sync action slug */
						sprintf( __( 'This rule uses an action this plugin does not support (%s). Edit the rule and choose a different action.', 'buoy-video-sync' ), $action ),
						'unknown_action'
					);
			}
		} catch ( \Throwable $e ) {
			$this->record_error( $source_type, $term_id, $e->getMessage(), 'exception' );
		} finally {
			$this->finish_run( $schedule );
			$finished = true;

			/**
			 * Fires after a sync rule finishes running, whether it succeeded or failed.
			 *
			 * @param array  $rule       The sync rule that ran.
			 * @param int    $rule_index 0-based index of the rule in sync_rules[].
			 * @param bool   $success    Whether the run completed without a terminal error.
			 * @param array  $result     Run context: 'action', 'source_type', 'source_id', and 'error' (empty string on success).
			 */
			do_action(
				'buoyvs_after_sync',
				$rule,
				$rule_index,
				! $this->run_failed,
				array(
					'action'      => $action,
					'source_type' => $source_type,
					'source_id'   => $term_id,
					'error'       => $this->terminal_error,
				)
			);
		}
	}

	/**
	 * Execute a sync rule for an option-based channel (Channels Page / buoyvs_channel_config).
	 *
	 * Loads the channel from wp_options into the source data override, then
	 * delegates to the standard run() method with term_id = 0.
	 *
	 * Called by Sync_Scheduler::dispatch_config_sync() when a
	 * buoyvs_channel_config_sync_rule cron event fires.
	 *
	 * @param int|string $rule_ref Permanent rule ID, or a 0-based rule index
	 *                             (cron events queued before rule IDs existed).
	 * @return void
	 */
	public function run_config_channel( int|string $rule_ref ): void {
		$ch_data = buoyvs_get_channel_config_fresh();

		if ( empty( $ch_data['youtube_id'] ) ) {
			return;
		}

		// Rules saved before permanent IDs existed get one now.
		$with_ids = buoyvs_ensure_rule_ids( $ch_data );
		if ( $with_ids !== $ch_data ) {
			$ch_data = $with_ids;
			update_option( 'buoyvs_channel_config', $ch_data );
		}

		$rule_index = is_int( $rule_ref ) ? $rule_ref : buoyvs_find_rule_index( $ch_data, $rule_ref );
		if ( null === $rule_index || ! isset( $ch_data['sync_rules'][ $rule_index ] ) ) {
			return;
		}

		// Ensure channel_id is present (option stores as youtube_id).
		$ch_data['channel_id'] = $ch_data['youtube_id'];

		$this->is_option_channel    = true;
		$this->source_data_override = $ch_data;
		$this->current_rule_id      = (string) $ch_data['sync_rules'][ $rule_index ]['id'];
		$this->current_youtube_id   = (string) $ch_data['youtube_id'];

		$this->run( 'channel', 0, $rule_index );

		$this->is_option_channel    = false;
		$this->source_data_override = null;
		$this->current_rule_id      = '';
		$this->current_youtube_id   = '';
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
		$destination_post_type = $rule['destination_post_type'] ?? '';
		$post_status            = $rule['post_status'] ?? 'publish';

		if ( $this->invalid_destination_post_type( $destination_post_type ) ) {
			$this->record_error( $source_type, $term_id, $this->invalid_post_type_message( $destination_post_type ), 'invalid_post_type' );
			return;
		}

		if ( 'channel' === $source_type ) {
			// Channels: need to get the uploads playlist ID first.
			$meta = $this->get_channel_data();
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
				throw new \RuntimeException( esc_html( "Could not retrieve uploads playlist ID for channel '{$meta['channel_id']}'." ) );
			}
		} else {
			// Playlists: playlist ID is stored directly in term meta.
			$meta = $this->get_channel_data();
			if ( ! $meta || empty( $meta['playlist_id'] ) ) {
				throw new \RuntimeException( 'Playlist ID not found in term meta.' );
			}
			$playlist_id = $meta['playlist_id'];
		}

		// Already-imported IDs. Dedup is scoped to the destination post type so
		// the same video can be imported into more than one post type.
		$existing_ids = $this->importer->get_imported_video_ids( $destination_post_type );

		// Recurring runs only need what's new since the last tick: stop paging
		// the (newest-first) uploads playlist at the first page made entirely
		// of already-imported videos instead of re-reading the whole catalog
		// and spending quota on every tick. "Once" runs always scan everything
		// so they can backfill gaps.
		$stop_at_known = 'once' !== ( $rule['schedule'] ?? 'once' ) ? $existing_ids : array();

		// Fetch video IDs from the playlist (paginated inside the API wrapper).
		$items = $this->api->get_playlist_items( $playlist_id, $stop_at_known );
		if ( is_wp_error( $items ) ) {
			$this->record_error( $source_type, $term_id, $items->get_error_message(), $items->get_error_code() );
			return;
		}

		if ( empty( $items ) ) {
			return; // Nothing to sync.
		}

		// Extract video IDs from the playlist items, then keep only new ones.
		$all_ids = array_filter( array_column( $items, 'video_id' ) );
		$new_ids = array_values( array_diff( $all_ids, $existing_ids ) );

		if ( empty( $new_ids ) ) {
			return; // All videos already imported.
		}

		$max = (int) ( $rule['max_videos'] ?? 0 );

		// Batch-fetch full video details and import.
		$imported                = $this->batch_fetch_and_import( $new_ids, $source_type, $term_id, $destination_post_type, $max, $post_status, $rule );
		$this->current_run_count = $imported;

		// Warn when candidates were available but nothing could be saved.
		if ( 0 === $imported ) {
			$candidate_count = count( $new_ids );
			$message         = sprintf(
				/* translators: %d: number of candidate videos */
				_n(
					'No videos were imported. %d candidate was fetched from the API but could not be saved.',
					'No videos were imported. %d candidates were fetched from the API but none could be saved.',
					$candidate_count,
					'buoy-video-sync'
				),
				$candidate_count
			);
			$this->accumulate_error( $source_type, $term_id, $message, 'no_results' );
		}
	}

	// -------------------------------------------------------------------------
	// Phase 2 handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the channel_sync_new action.
	 *
	 * Fetches fresh channel data from the API and creates a WordPress post for the channel.
	 * If a post for this channel already exists (deduped by _buoyvs_channel_post), nothing
	 * is done — channel update actions handle refreshing an existing post.
	 *
	 * @param int   $term_id Channel term ID (0 for option-based channels).
	 * @param array $rule    Sync rule array.
	 * @return void
	 * @throws \RuntimeException On missing channel ID.
	 */
	private function handle_channel_sync_new( int $term_id, array $rule ): void {
		$data = $this->get_channel_data();
		if ( ! $data || empty( $data['channel_id'] ) ) {
			throw new \RuntimeException( 'Channel ID not found in term meta.' );
		}

		$channel_id = $data['channel_id'];

		$destination_post_type = $rule['destination_post_type'] ?? '';
		$post_status            = $rule['post_status'] ?? 'publish';

		if ( $this->invalid_destination_post_type( $destination_post_type ) ) {
			$this->record_error( 'channel', $term_id, $this->invalid_post_type_message( $destination_post_type ), 'invalid_post_type' );
			return;
		}

		// Skip if this channel is already imported into this post type.
		$existing_post_id = $this->importer->find_post_by_channel_id( $channel_id, $destination_post_type );
		if ( $existing_post_id ) {
			return;
		}

		$fresh = $this->api->get_channel_data( $channel_id );
		if ( is_wp_error( $fresh ) ) {
			$this->record_error( 'channel', $term_id, $fresh->get_error_message(), $fresh->get_error_code() );
			return;
		}

		$this->write_progress( 0, 1 );
		$result = $this->importer->import_channel( $fresh, $channel_id, $destination_post_type, $post_status, $this->rule_author( $rule ) );

		if ( is_wp_error( $result ) ) {
			$this->accumulate_error( 'channel', $term_id, $result->get_error_message(), $result->get_error_code() );
		} else {
			$this->current_run_count = 1;
		}
		$this->write_progress( 1, 1 );
	}

	/**
	 * Handle the playlists_sync_new action.
	 *
	 * Creates a new post for each playlist not yet imported into the destination
	 * post type.
	 *
	 * @param int    $term_id Channel term ID (0 for option-based channels).
	 * @param array  $rule    Sync rule array.
	 * @param string $action  Action slug (always playlists_sync_new).
	 * @return void
	 * @throws \RuntimeException On missing channel ID.
	 */
	private function handle_playlists_sync( int $term_id, array $rule, string $action ): void {
		$destination_post_type = $rule['destination_post_type'] ?? '';
		$post_status            = $rule['post_status'] ?? 'publish';

		$data = $this->get_channel_data();
		if ( ! $data || empty( $data['channel_id'] ) ) {
			throw new \RuntimeException( 'Channel ID not found.' );
		}

		// Validate the destination post type before spending an API call.
		if ( $this->invalid_destination_post_type( $destination_post_type ) ) {
			$this->record_error( 'channel', $term_id, $this->invalid_post_type_message( $destination_post_type ), 'invalid_post_type' );
			return;
		}

		$channel_id = $data['channel_id'];
		$playlists  = $this->api->get_channel_playlists( $channel_id );

		if ( is_wp_error( $playlists ) ) {
			$this->record_error( 'channel', $term_id, $playlists->get_error_message(), $playlists->get_error_code() );
			return;
		}
		$max        = (int) ( $rule['max_videos'] ?? 0 ); // max_videos field doubles as max_playlists.
		$processed  = 0;
		$total_pl   = $max > 0 ? min( $max, count( $playlists ) ) : count( $playlists );
		$scanned_pl = 0;

		$this->write_progress( 0, $total_pl );

		foreach ( $playlists as $playlist_data ) {
			if ( $max > 0 && $processed >= $max ) {
				break;
			}

			if ( empty( $playlist_data['playlist_id'] ) ) {
				continue;
			}

			// Create if missing in this post type.
			$existing_post_id = $this->importer->find_post_by_playlist_id( $playlist_data['playlist_id'], $destination_post_type );
			if ( ! $existing_post_id ) {
				$result = $this->importer->import_playlist( $playlist_data, $channel_id, $destination_post_type, $post_status, $this->rule_author( $rule ) );
				if ( is_wp_error( $result ) ) {
					$this->accumulate_error( 'channel', $term_id, $result->get_error_message(), $result->get_error_code() );
				} else {
					++$processed;
				}
			}
			++$scanned_pl;
			$this->write_progress( min( $scanned_pl, $total_pl ), $total_pl );
		}

		$this->current_run_count = $processed;
	}

	// -------------------------------------------------------------------------
	// Batch fetch + import
	// -------------------------------------------------------------------------

	/**
	 * Fetch video details in batches of 50 and import them.
	 *
	 * @param string[] $video_ids   Video IDs to process.
	 * @param string   $source_type 'channel' or 'playlist'.
	 * @param int      $term_id     Source term ID.
	 * @return int Number of videos successfully imported.
	 */
	private function batch_fetch_and_import(
		array $video_ids,
		string $source_type,
		int $term_id,
		string $destination_post_type = '',
		int $max = 0,
		string $post_status = 'publish',
		array $rule = array()
	): int {
		$cap     = $max > 0 ? $max : PHP_INT_MAX;
		$chunks  = array_chunk( $video_ids, 50 );
		$total   = min( $cap, count( $video_ids ) );
		$scanned = 0;
		$imported = 0;

		$this->write_progress( 0, $total );

		foreach ( $chunks as $chunk ) {
			$videos = $this->api->get_videos_by_ids( $chunk );

			if ( is_wp_error( $videos ) ) {
				$this->accumulate_error( $source_type, $term_id, $videos->get_error_message(), $videos->get_error_code() );
				$scanned += count( $chunk );
				$this->write_progress( min( $scanned, $total ), $total );
				continue;
			}

			foreach ( $videos as $video_data ) {
				if ( $imported >= $cap ) {
					return $imported;
				}

				/**
				 * Filters whether a video should be imported.
				 *
				 * @param bool   $should_import Whether to import this item. Default true.
				 * @param array  $item_data     Video data from the YouTube API.
				 * @param string $source_type   'channel' or 'playlist'.
				 * @param array  $rule          The sync rule currently running.
				 */
				if ( ! apply_filters( 'buoyvs_import_item', true, $video_data, $source_type, $rule ) ) {
					++$scanned;
					$this->write_progress( min( $scanned, $total ), $total );
					continue;
				}

				$result = $this->importer->import( $video_data, $source_type, $term_id, $destination_post_type, $post_status, $this->rule_author( $rule ) );

				if ( is_wp_error( $result ) ) {
					$this->accumulate_error( $source_type, $term_id, $result->get_error_message(), $result->get_error_code() );
				} else {
					++$imported;
				}
				++$scanned;
				$this->write_progress( min( $scanned, $total ), $total );
			}
		}

		return $imported;
	}

	// -------------------------------------------------------------------------
	// Lookup helpers
	// -------------------------------------------------------------------------

	/**
	 * The user synced posts are attributed to: whoever created the rule, if
	 * that account still exists. Cron runs have no logged-in user.
	 *
	 * @param array $rule Sync rule.
	 * @return int User ID, or 0.
	 */
	private function rule_author( array $rule ): int {
		$user_id = (int) ( $rule['created_by'] ?? 0 );
		return ( $user_id && get_userdata( $user_id ) ) ? $user_id : 0;
	}

	/**
	 * Whether a rule's destination post type is unusable (empty or not registered).
	 *
	 * sync_new actions must refuse to run rather than let wp_insert_post() fall
	 * back to the default 'post' type or create posts under an orphaned type.
	 *
	 * @param string $post_type Destination post type from the rule.
	 * @return bool True when the post type cannot be used.
	 */
	private function invalid_destination_post_type( string $post_type ): bool {
		return '' === $post_type || ! post_type_exists( $post_type );
	}

	/**
	 * Build the user-facing error for an invalid destination post type.
	 *
	 * @param string $post_type The offending post type value.
	 * @return string
	 */
	private function invalid_post_type_message( string $post_type ): string {
		if ( '' === $post_type ) {
			return __( 'No destination post type is set for this rule. Choose a post type so synced items have somewhere to be saved.', 'buoy-video-sync' );
		}
		return sprintf(
			/* translators: %s: post type slug */
			__( 'The destination post type "%s" is no longer registered. Choose a valid post type for this rule.', 'buoy-video-sync' ),
			$post_type
		);
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
		$data = $this->get_channel_data();

		if ( ! $data ) {
			return null;
		}

		return $data['sync_rules'][ $rule_index ] ?? null;
	}

	/**
	 * Merge fresh channel API data into the stored channel config.
	 *
	 * Called as a side effect of handle_videos_sync_new() so that channel
	 * metadata (title, subscribers, profile picture, banner) stays up to date
	 * even when no explicit channel_update rule has been configured.
	 *
	 * @param int   $term_id      Unused; retained for the call signature.
	 * @param array $channel_data Data returned by YouTube_API::get_channel_data().
	 * @return void
	 */
	private function refresh_channel_meta( int $term_id, array $channel_data ): void {
		$fields = array();
		foreach ( array( 'channel_title', 'channel_description', 'subscriber_count', 'video_count', 'profile_picture', 'banner_image', 'etag' ) as $field ) {
			if ( isset( $channel_data[ $field ] ) ) {
				$fields[ $field ] = $channel_data[ $field ];
			}
		}
		$this->patch_channel( $fields );
	}

	/**
	 * Return the current channel's source data.
	 *
	 * The in-memory copy run_config_channel() loaded, refreshed after every
	 * write this runner makes.
	 *
	 * @return array|null Channel data array, or null if not set.
	 */
	private function get_channel_data(): ?array {
		return $this->source_data_override;
	}

	/**
	 * Apply changes to the stored channel config without clobbering edits made
	 * elsewhere while this run was in progress.
	 *
	 * Re-reads the option from the database, applies $mutator to that fresh
	 * copy, and saves it. Skipped entirely if the channel was replaced or
	 * deleted since the run started.
	 *
	 * @param callable $mutator Receives the fresh channel array; returns the modified array, or null to skip saving.
	 * @return void
	 */
	private function mutate_channel( callable $mutator ): void {
		if ( ! $this->is_option_channel || '' === $this->current_youtube_id ) {
			return;
		}

		$channel = buoyvs_get_channel_config_fresh();
		if ( ( $channel['youtube_id'] ?? '' ) !== $this->current_youtube_id ) {
			return;
		}

		$updated = $mutator( $channel );
		if ( ! is_array( $updated ) ) {
			return;
		}

		update_option( 'buoyvs_channel_config', $updated );

		$updated['channel_id']      = $updated['youtube_id'];
		$this->source_data_override = $updated;
	}

	/**
	 * Update channel-level fields (title, counts, images…).
	 *
	 * @param array $fields Field => value.
	 * @return void
	 */
	private function patch_channel( array $fields ): void {
		if ( empty( $fields ) ) {
			return;
		}
		$this->mutate_channel(
			static function ( array $channel ) use ( $fields ): array {
				return array_merge( $channel, $fields );
			}
		);
	}

	/**
	 * Update the current rule's fields, located by its permanent ID.
	 *
	 * @param array    $fields Field => value to set.
	 * @param string[] $unset  Fields to remove.
	 * @return void
	 */
	private function patch_rule( array $fields, array $unset = array() ): void {
		$rule_id = $this->current_rule_id;
		$this->mutate_channel(
			static function ( array $channel ) use ( $rule_id, $fields, $unset ): ?array {
				$index = buoyvs_find_rule_index( $channel, $rule_id );
				if ( null === $index ) {
					return null; // Rule was deleted mid-run.
				}
				foreach ( $unset as $key ) {
					unset( $channel['sync_rules'][ $index ][ $key ] );
				}
				$channel['sync_rules'][ $index ] = array_merge( $channel['sync_rules'][ $index ], $fields );
				return $channel;
			}
		);
	}

	/**
	 * The current rule as last read from the database.
	 *
	 * @return array
	 */
	private function current_rule(): array {
		$data  = $this->get_channel_data() ?? array();
		$index = buoyvs_find_rule_index( $data, $this->current_rule_id );
		return null === $index ? array() : $data['sync_rules'][ $index ];
	}

	// -------------------------------------------------------------------------
	// Status recording
	// -------------------------------------------------------------------------

	/**
	 * Append this run's single entry to the channel's sync history.
	 *
	 * @param bool $has_error Whether the run produced any error (terminal or accumulated).
	 * @return void
	 */
	private function append_history( bool $has_error ): void {
		if ( '' === $this->current_youtube_id ) {
			return;
		}

		$rule = $this->current_rule();

		Sync_History::append( $this->current_youtube_id, array(
			'timestamp'             => time(),
			'rule_action'           => $rule['action'] ?? '',
			'rule_index'            => $this->current_rule_index,
			'rule_id'               => $this->current_rule_id,
			'duration'              => max( 0, time() - $this->run_started_at ),
			'has_error'             => $has_error,
			'errors'                => $this->run_errors,
			'items_count'           => $this->current_run_count,
			'destination_post_type' => $rule['destination_post_type'] ?? '',
		) );
	}

	/**
	 * Record a non-terminal error during the current sync run.
	 *
	 * Pushes the error to $run_errors (flushed into the history entry at the
	 * end) and mirrors the latest errors onto the rule for the UI.
	 * Use this for errors inside batch loops where the run continues.
	 * For terminal errors where the run stops, use record_error() instead.
	 *
	 * @param string $source_type Unused; retained for the call signature.
	 * @param int    $term_id     Unused; retained for the call signature.
	 * @param string $error       Human-readable error message.
	 * @param string $code        Error code string.
	 * @return void
	 */
	private function accumulate_error( string $source_type, int $term_id, string $error, string $code = '' ): void {
		$this->run_errors[] = array(
			'timestamp' => time(),
			'error'     => $error,
			'code'      => (string) $code,
		);

		$this->patch_rule( array( 'sync_errors' => array_slice( $this->run_errors, -5 ) ) );
	}

	/**
	 * Record a terminal sync error — the run stops and is reported as failed.
	 *
	 * @param string $source_type Unused; retained for the call signature.
	 * @param int    $term_id     Unused; retained for the call signature.
	 * @param string $error       Human-readable error message.
	 * @param string $code        Error code string.
	 * @return void
	 */
	private function record_error( string $source_type, int $term_id, string $error, string $code = '' ): void {
		if ( ! $this->run_failed ) {
			$this->terminal_error = $error;
		}
		$this->run_failed = true;
		$this->accumulate_error( $source_type, $term_id, $error, (string) $code );
	}

	/**
	 * Mark the current rule as actively syncing and reset per-run state.
	 *
	 * @return void
	 */
	private function mark_syncing(): void {
		$this->run_started_at    = time();
		$this->run_errors        = array();
		$this->current_run_count = 0;

		$this->patch_rule(
			array(
				'sync_status'     => 'syncing',
				'sync_started_at' => time(),
				'sync_errors'     => array(),
			)
		);
	}

	/**
	 * Write the run's outcome — exactly once per run.
	 *
	 * Sets the final status ('success' only when nothing went wrong), updates
	 * last_synced/sync_count when the run was not stopped by a terminal error,
	 * switches off a "once" rule, writes the history entry, and releases the
	 * lock. A "once" rule runs a single time; it stays visible with its toggle
	 * off so the user can re-enable it. Recurring rules stay enabled.
	 *
	 * @param string $schedule Rule schedule.
	 * @return void
	 */
	private function finish_run( string $schedule ): void {
		if ( '' === $this->current_rule_id ) {
			return;
		}

		$rule   = $this->current_rule();
		$fields = array(
			'sync_status' => empty( $this->run_errors ) ? 'success' : 'failed',
			'sync_errors' => array_slice( $this->run_errors, -5 ),
		);

		if ( ! $this->run_failed ) {
			$fields['last_synced'] = time();
			$fields['sync_count']  = (int) ( $rule['sync_count'] ?? 0 ) + 1;
		}

		if ( 'once' === $schedule ) {
			$fields['enabled'] = false;
		}

		$this->patch_rule( $fields, array( 'sync_started_at' ) );
		$this->append_history( ! empty( $this->run_errors ) );

		buoyvs_release_rule_lock( $this->current_rule_id );
		// Progress transient is intentionally left to expire — the UI reads it
		// in the onSyncDone response to briefly show the final count.
	}

	// -------------------------------------------------------------------------
	// Progress tracking
	// -------------------------------------------------------------------------

	/**
	 * Write sync progress to a transient for UI polling, and keep the run lock
	 * fresh so a long run is never mistaken for an abandoned one.
	 *
	 * @param int $current Items scanned so far.
	 * @param int $total   Total items to scan.
	 * @return void
	 */
	private function write_progress( int $current, int $total ): void {
		if ( ! $this->is_option_channel || '' === $this->current_rule_id ) {
			return;
		}
		set_transient(
			buoyvs_rule_progress_key( $this->current_rule_id ),
			array( 'current' => $current, 'total' => $total ),
			buoyvs_lock_ttl()
		);

		if ( time() - $this->lock_refreshed_at >= 60 ) {
			buoyvs_refresh_rule_lock( $this->current_rule_id );
			$this->lock_refreshed_at = time();
		}
	}

}
