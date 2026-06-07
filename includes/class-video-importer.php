<?php
declare(strict_types=1);
/**
 * Video importer.
 *
 * Creates and updates yousync_videos posts from normalised YouTube video data.
 * Thumbnails are stored as YouTube CDN URLs — no sideloading, no disk usage.
 * The post_thumbnail_html filter in yousync.php serves the YouTube URL when
 * no featured image is explicitly set by the user.
 *
 * @package YouSyncPro
 */

namespace YouSyncPro;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Video_Importer
 *
 * Handles creation and updating of yousync_videos posts.
 */
class Video_Importer {

	/**
	 * Hardcoded YouTube category ID → human-readable name map.
	 *
	 * Avoids an extra API call to videoCategories.list (which also
	 * requires a region code). These category IDs are stable.
	 */
	private const YOUTUBE_CATEGORIES = array(
		'1'  => 'Film & Animation',
		'2'  => 'Autos & Vehicles',
		'10' => 'Music',
		'15' => 'Pets & Animals',
		'17' => 'Sports',
		'18' => 'Short Movies',
		'19' => 'Travel & Events',
		'20' => 'Gaming',
		'21' => 'Videoblogging',
		'22' => 'People & Blogs',
		'23' => 'Comedy',
		'24' => 'Entertainment',
		'25' => 'News & Politics',
		'26' => 'Howto & Style',
		'27' => 'Education',
		'28' => 'Science & Technology',
		'29' => 'Nonprofits & Activism',
	);

	/**
	 * Thumbnail size preference order (largest first).
	 */
	private const THUMBNAIL_SIZE_PRIORITY = array( 'maxres', 'standard', 'high', 'medium', 'default' );

	/**
	 * Maps field mapping source keys to their corresponding _yousync_* meta keys.
	 */
	private const SOURCE_TO_INTERNAL_KEY = array(
		// Video fields.
		'title'               => '_yousync_original_title',
		'description'         => '_yousync_original_description',
		'duration'            => '_yousync_duration_seconds',
		'view_count'          => '_yousync_view_count',
		'like_count'          => '_yousync_like_count',
		'published_at'        => '_yousync_published_at',
		'thumbnail_url'       => '_yousync_thumbnail_url',
		'channel_title'       => '_yousync_channel_title',
		// Channel fields.
		'channel_description' => '_yousync_channel_description',
		'subscriber_count'    => '_yousync_subscriber_count',
		'video_count'         => '_yousync_channel_video_count',
		'profile_picture_url' => '_yousync_profile_picture',
		'banner_image_url'    => '_yousync_banner_image',
	);

	// -------------------------------------------------------------------------
	// Public methods
	// -------------------------------------------------------------------------

	/**
	 * Import a YouTube video as a new WordPress post.
	 *
	 * Assumes the caller has already confirmed the video does not exist.
	 *
	 * @param array  $video_data                  Normalised video data from YouTube_API::get_videos_by_ids().
	 * @param string $source_type                 'channel' or 'playlist'.
	 * @param int    $source_term_id              WordPress term ID of the source channel/playlist.
	 * @param string $post_type                   Destination post type. Required and validated by the caller (Sync_Runner); an empty/invalid value is rejected before reaching here.
	 * @param array  $destination_taxonomy_terms  Array of ['taxonomy' => string, 'term_ids' => int[]] to assign.
	 * @param array  $field_mapping               Field mapping rows. Stored on the post for use by future update syncs.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import( array $video_data, string $source_type, int $source_term_id, string $post_type = '', array $destination_taxonomy_terms = array(), array $field_mapping = array() ): int|\WP_Error {
		// 1. Create the post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $video_data['title'] ?? '' ),
				'post_type'   => $post_type,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// 2. Assign destination taxonomy terms from the sync rule (replace, not merge).
		if ( ! empty( $destination_taxonomy_terms ) ) {
			$this->assign_destination_taxonomy_terms( $post_id, $destination_taxonomy_terms );
		}

		// 3. Save all internal _yousync_* meta keys.
		$this->save_all_meta( $post_id, $video_data, $source_type, $source_term_id );
		update_post_meta( $post_id, '_yousync_protected', 0 );

		// 4. Store the field mapping on the post and write the initial custom meta values.
		$this->save_field_mapping( $post_id, $field_mapping );
		$this->apply_stored_mapping( $post_id );

		return $post_id;
	}

	/**
	 * Find an existing yousync_videos post by its YouTube video ID.
	 *
	 * Uses the flat _yousync_video_id meta key (indexed) for fast lookups.
	 *
	 * @param string $video_id YouTube video ID.
	 * @return int Post ID, or 0 if not found.
	 */
	public function find_post_by_video_id( string $video_id ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_yousync_video_id',
						'value' => $video_id,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Batch-find existing posts by their YouTube video IDs.
	 *
	 * A single video may now exist as more than one post — at most one per
	 * destination post type (per-post-type dedup). Every matching post is
	 * returned so update rules can refresh all copies.
	 *
	 * Uses one indexed SQL query (no per-post get_post_meta()).
	 *
	 * @param string[] $video_ids YouTube video IDs.
	 * @return array<string, int[]> Map of video_id => list of post IDs.
	 */
	public function find_posts_by_video_ids( array $video_ids ): array {
		if ( empty( $video_ids ) ) {
			return array();
		}

		$cache_key = 'yousync_video_post_map';
		$full_map  = wp_cache_get( $cache_key );

		if ( false === $full_map ) {
			global $wpdb;
			// Excludes 'trash' — trashed posts are never updated.
			$rows = $wpdb->get_results(
				"SELECT pm.meta_value AS video_id, pm.post_id AS post_id
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_yousync_video_id'
				   AND p.post_status IN ( 'publish', 'draft', 'private' )"
			);

			$full_map = array();
			foreach ( $rows as $row ) {
				if ( '' !== $row->video_id ) {
					$full_map[ $row->video_id ][] = (int) $row->post_id;
				}
			}

			wp_cache_set( $cache_key, $full_map );
		}

		return array_intersect_key( $full_map, array_flip( $video_ids ) );
	}

	/**
	 * Return the YouTube video IDs already imported into a given post type.
	 *
	 * Used by videos_sync_new dedup. Scoping by post type lets the same video
	 * be imported into more than one post type by separate rules. Includes
	 * 'trash' so a deliberately trashed video is not silently re-imported into
	 * the same post type.
	 *
	 * One indexed SQL query — no per-post meta reads.
	 *
	 * @param string $post_type Destination post type. Empty = match any post type.
	 * @return string[] Imported YouTube video IDs.
	 */
	public function get_imported_video_ids( string $post_type = '' ): array {
		global $wpdb;
		$statuses     = array( 'publish', 'draft', 'private', 'trash' );
		$status_in    = "'" . implode( "','", $statuses ) . "'";

		if ( '' !== $post_type ) {
			$sql = $wpdb->prepare(
				"SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_yousync_video_id'
				   AND p.post_type = %s
				   AND p.post_status IN ( {$status_in} )",
				$post_type
			);
		} else {
			$sql = "SELECT pm.meta_value
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_yousync_video_id'
				   AND p.post_status IN ( {$status_in} )";
		}

		$ids = $wpdb->get_col( $sql );
		return array_values( array_filter( array_unique( $ids ) ) );
	}

	/**
	 * Update an existing post with fresh YouTube data.
	 *
	 * If the post has manual_edits = true, all modes bail early — the flag
	 * takes absolute precedence regardless of mode.
	 *
	 * Modes:
	 *   update_all          — Update everything (title, internal meta, mapped custom meta).
	 *   update_specific_all — Only update fields listed in $specific_metadata.
	 *
	 * Custom meta keys written by field mapping are always refreshed in update_all using
	 * the _yousync_field_mapping stored on the post at import time. No field_mapping param
	 * is needed here — the update rule does not need to know about mappings.
	 *
	 * @param int      $post_id                     Existing post ID.
	 * @param array    $video_data                  Fresh normalised video data from the API.
	 * @param string   $mode                        One of the two mode strings above.
	 * @param string[] $specific_metadata           Fields to update (used in update_specific_all).
	 * @param array    $destination_taxonomy_terms  Array of ['taxonomy' => string, 'term_ids' => int[]] to assign.
	 * @return true|\WP_Error True on success.
	 */
	public function update( int $post_id, array $video_data, string $mode, array $specific_metadata = array(), array $destination_taxonomy_terms = array() ): bool|\WP_Error {
		// Protected from Sync takes absolute precedence over any update mode.
		if ( (bool) get_post_meta( $post_id, '_yousync_protected', true ) ) {
			return true;
		}

		// Assign destination taxonomy terms (replace, not merge).
		if ( ! empty( $destination_taxonomy_terms ) ) {
			$this->assign_destination_taxonomy_terms( $post_id, $destination_taxonomy_terms );
		}

		if ( 'update_specific_all' === $mode ) {
			$this->apply_selective_update( $post_id, $video_data, $specific_metadata );
			// Only refresh mapped custom meta for the fields that were actually updated.
			$filter_keys = array_values( array_filter( array_map(
				fn( string $s ) => self::SOURCE_TO_INTERNAL_KEY[ $s ] ?? null,
				$specific_metadata
			) ) );
			$this->apply_stored_mapping( $post_id, $filter_keys );
			return true;
		}

		// Full update: refresh all internal _yousync_* meta. Post title is never overwritten.
		$source_type = (string) get_post_meta( $post_id, '_yousync_source_type', true );
		$source_id   = (int) get_post_meta( $post_id, '_yousync_source_id', true );

		$this->save_all_meta( $post_id, $video_data, $source_type, $source_id );

		// Refresh custom meta keys using the mapping stored on the post at import time.
		$this->apply_stored_mapping( $post_id );

		return true;
	}

	/**
	 * Convert field mapping rows into the stored per-post structure and save it.
	 *
	 * The stored structure maps each _yousync_* internal key to the list of custom
	 * meta keys that should mirror its value on every update sync:
	 *   [ '_yousync_view_count' => ['my_view_count', 'test_view'], ... ]
	 *
	 * If the mapping is empty, any existing _yousync_field_mapping meta is removed.
	 *
	 * @param int   $post_id      Post ID.
	 * @param array $field_mapping Field mapping rows from the sync rule.
	 * @return void
	 */
	private function save_field_mapping( int $post_id, array $field_mapping ): void {
		if ( empty( $field_mapping ) ) {
			delete_post_meta( $post_id, '_yousync_field_mapping' );
			return;
		}

		$stored = array();
		foreach ( $field_mapping as $row ) {
			$source = $row['source'] ?? '';
			$target = $row['target'] ?? '';
			if ( ! isset( self::SOURCE_TO_INTERNAL_KEY[ $source ] ) || '' === $target ) {
				continue;
			}
			$internal_key             = self::SOURCE_TO_INTERNAL_KEY[ $source ];
			$stored[ $internal_key ][] = sanitize_key( $target );
		}

		if ( empty( $stored ) ) {
			delete_post_meta( $post_id, '_yousync_field_mapping' );
		} else {
			update_post_meta( $post_id, '_yousync_field_mapping', $stored );
		}
	}

	/**
	 * Copy values from _yousync_* internal keys to their mapped custom meta targets.
	 *
	 * Reads _yousync_field_mapping from the post (written at import time) and writes
	 * each target key. Called after save_all_meta() so internal keys are already fresh.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	/**
	 * @param int      $post_id     Post ID.
	 * @param string[] $filter_keys Only copy entries whose internal key is in this list.
	 *                              Empty = copy all entries (used by update_all).
	 */
	private function apply_stored_mapping( int $post_id, array $filter_keys = [] ): void {
		$stored = get_post_meta( $post_id, '_yousync_field_mapping', true );
		if ( empty( $stored ) || ! is_array( $stored ) ) {
			return;
		}
		foreach ( $stored as $internal_key => $targets ) {
			if ( ! empty( $filter_keys ) && ! in_array( $internal_key, $filter_keys, true ) ) {
				continue;
			}
			$value = get_post_meta( $post_id, $internal_key, true );
			foreach ( (array) $targets as $target ) {
				update_post_meta( $post_id, sanitize_key( $target ), $value );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Private methods
	// -------------------------------------------------------------------------

	/**
	 * Apply a selective update to a post, touching only the requested fields.
	 *
	 * @param int      $post_id           Post ID.
	 * @param array    $existing_meta     Current decoded _yousync_video meta.
	 * @param array    $video_data        Fresh API data.
	 * @param string[] $specific_metadata Fields to update.
	 * @return void
	 */
	private function apply_selective_update(
		int $post_id,
		array $video_data,
		array $specific_metadata
	): void {
		$active_archives    = get_option( 'yousync_active_archives', array() );
		$tags_enabled       = ! empty( $active_archives['ys-tag']['enabled'] );
		$categories_enabled = ! empty( $active_archives['ys-category']['enabled'] );

		foreach ( $specific_metadata as $field ) {
			switch ( $field ) {
				case 'title':
					// Only refresh internal meta — post_title is never overwritten after import.
					update_post_meta( $post_id, '_yousync_original_title', $video_data['title'] );
					break;

				case 'description':
					update_post_meta( $post_id, '_yousync_original_description', $video_data['description'] );
					break;

				case 'thumbnail':
					if ( ! empty( $video_data['thumbnails'] ) ) {
						$thumbnails = array();
						foreach ( $video_data['thumbnails'] as $size => $thumb ) {
							$thumbnails[ $size ] = array(
								'url'    => $thumb['url'],
								'width'  => $thumb['width'],
								'height' => $thumb['height'],
							);
						}
						update_post_meta( $post_id, '_yousync_thumbnails', $thumbnails );
					}
					break;

				case 'tags':
					if ( $tags_enabled && isset( $video_data['tags'] ) ) {
						$this->assign_video_tags( $post_id, $video_data['tags'] );
					}
					break;

				case 'yousync_category':
					if ( $categories_enabled && ! empty( $video_data['category_id'] ) ) {
						$this->assign_video_category( $post_id, $video_data['category_id'] );
					}
					break;

				case 'duration':
					update_post_meta( $post_id, '_yousync_duration_seconds', (int) $video_data['duration_seconds'] );
					break;

				case 'view_count':
					update_post_meta( $post_id, '_yousync_view_count', (int) $video_data['view_count'] );
					break;

				case 'like_count':
					update_post_meta( $post_id, '_yousync_like_count', (int) $video_data['like_count'] );
					break;

				case 'comment_count':
					update_post_meta( $post_id, '_yousync_comment_count', (int) $video_data['comment_count'] );
					break;

				case 'published_date':
					update_post_meta( $post_id, '_yousync_published_at', $video_data['published_at'] );
					break;
			}
		}

		update_post_meta( $post_id, '_yousync_last_synced', time() );
	}

	/**
	 * Assign destination taxonomy terms from a sync rule to a post.
	 *
	 * Replaces existing terms on each taxonomy — does not merge.
	 * Only taxonomies explicitly listed in the rule are touched.
	 *
	 * @param int   $post_id                    Post ID.
	 * @param array $destination_taxonomy_terms Array of ['taxonomy' => string, 'term_ids' => int[]].
	 * @return void
	 */
	private function assign_destination_taxonomy_terms( int $post_id, array $destination_taxonomy_terms ): void {
		foreach ( $destination_taxonomy_terms as $tt ) {
			$taxonomy = $tt['taxonomy'] ?? '';
			$term_ids = array_filter( array_map( 'absint', (array) ( $tt['term_ids'] ?? array() ) ) );

			if ( ! $taxonomy || empty( $term_ids ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			wp_set_object_terms( $post_id, array_values( $term_ids ), $taxonomy );
		}
	}

	/**
	 * Assign video tags to a post.
	 *
	 * Creates terms in the yousync_tag taxonomy if they don't yet exist.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $tags    Tag strings from YouTube.
	 * @return void
	 */
	private function assign_video_tags( int $post_id, array $tags ): void {
		wp_set_object_terms( $post_id, $tags, 'yousync_tag' );
	}

	/**
	 * Assign a video category to a post from a YouTube category ID.
	 *
	 * Uses the hardcoded YOUTUBE_CATEGORIES map so no extra API call is needed.
	 * Term slug = numeric category ID; term name = human-readable label.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $category_id YouTube category ID (e.g. '10' for Music).
	 * @return void
	 */
	private function assign_video_category( int $post_id, string $category_id ): void {
		$term_name = self::YOUTUBE_CATEGORIES[ $category_id ] ?? "Category {$category_id}";

		$term = get_term_by( 'slug', $category_id, 'yousync_category' );

		if ( ! $term ) {
			$result = wp_insert_term( $term_name, 'yousync_category', array( 'slug' => $category_id ) );
			if ( is_wp_error( $result ) ) {
				return;
			}
			$term_id = $result['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		wp_set_object_terms( $post_id, array( $term_id ), 'yousync_category' );
	}

	/**
	 * Save all video data as individual post meta keys.
	 *
	 * Each field is stored as its own meta key for direct WP_Query filtering
	 * and shortcode access. The _yousync_protected flag is not written here —
	 * it is user-controlled and managed by the metabox save handler.
	 *
	 * @param int    $post_id     Post ID.
	 * @param array  $video_data  Normalised video data from YouTube_API::get_videos_by_ids().
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $source_id   WordPress term ID of the source.
	 * @return void
	 */
	private function save_all_meta( int $post_id, array $video_data, string $source_type, int $source_id ): void {
		$thumbnails = array();
		foreach ( $video_data['thumbnails'] ?? array() as $size => $thumb ) {
			$thumbnails[ $size ] = array(
				'url'    => $thumb['url'],
				'width'  => $thumb['width'],
				'height' => $thumb['height'],
			);
		}

		update_post_meta( $post_id, '_yousync_video_id',             $video_data['video_id'] );
		update_post_meta( $post_id, '_yousync_video_url',            'https://www.youtube.com/watch?v=' . $video_data['video_id'] );
		update_post_meta( $post_id, '_yousync_channel_id',           $video_data['channel_id'] ?? '' );
		update_post_meta( $post_id, '_yousync_channel_title',        $video_data['channel_title'] ?? '' );
		update_post_meta( $post_id, '_yousync_etag',                 $video_data['etag'] ?? '' );
		update_post_meta( $post_id, '_yousync_source_type',          $source_type );
		update_post_meta( $post_id, '_yousync_source_id',            $source_id );
		update_post_meta( $post_id, '_yousync_original_title',       $video_data['title'] ?? '' );
		update_post_meta( $post_id, '_yousync_original_description', $video_data['description'] ?? '' );
		update_post_meta( $post_id, '_yousync_published_at',         $video_data['published_at'] ?? '' );
		update_post_meta( $post_id, '_yousync_duration_seconds',     (int) ( $video_data['duration_seconds'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_view_count',           (int) ( $video_data['view_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_like_count',           (int) ( $video_data['like_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_comment_count',        (int) ( $video_data['comment_count'] ?? 0 ) );
		$best_thumb = static::get_best_thumbnail( $thumbnails );
		update_post_meta( $post_id, '_yousync_thumbnails',           $thumbnails );
		update_post_meta( $post_id, '_yousync_thumbnail_url',        $best_thumb['url'] ?? '' );
		update_post_meta( $post_id, '_yousync_last_synced',          time() );
	}

	// -------------------------------------------------------------------------
	// Playlist import methods
	// -------------------------------------------------------------------------

	/**
	 * Find an existing post by its YouTube playlist ID.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return int Post ID, or 0 if not found.
	 */
	public function find_post_by_playlist_id( string $playlist_id, string $post_type = '' ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type ?: 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_yousync_playlist_id',
						'value' => $playlist_id,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Find every playlist post for a YouTube playlist ID, across all post types.
	 *
	 * With per-post-type dedup a playlist may exist as one post per post type.
	 * Update rules refresh all of them.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return int[] Post IDs.
	 */
	public function find_posts_by_playlist_id( string $playlist_id ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_yousync_playlist_id',
						'value' => $playlist_id,
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Import a YouTube playlist as a new WordPress post.
	 *
	 * @param array  $playlist_data             Normalised playlist data from YouTube_API::get_channel_playlists().
	 * @param string $channel_id                YouTube channel ID.
	 * @param string $post_type                 Destination post type.
	 * @param array  $destination_taxonomy_terms Array of ['taxonomy' => string, 'term_ids' => int[]].
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import_playlist( array $playlist_data, string $channel_id, string $post_type, array $destination_taxonomy_terms = array() ): int|\WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $playlist_data['playlist_title'] ?: $playlist_data['playlist_id'] ),
				'post_type'   => $post_type,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $destination_taxonomy_terms ) ) {
			$this->assign_destination_taxonomy_terms( $post_id, $destination_taxonomy_terms );
		}

		$this->save_playlist_meta( $post_id, $playlist_data, $channel_id );
		update_post_meta( $post_id, '_yousync_protected', 0 );

		return $post_id;
	}

	/**
	 * Update an existing playlist post with fresh YouTube data.
	 *
	 * @param int      $post_id                    Existing post ID.
	 * @param array    $playlist_data              Fresh normalised playlist data.
	 * @param string   $mode                       'update_all' or 'update_specific_all'.
	 * @param string[] $specific_metadata          Fields to update (for update_specific_all).
	 * @param array    $destination_taxonomy_terms Array of ['taxonomy' => string, 'term_ids' => int[]].
	 * @return true|\WP_Error
	 */
	public function update_playlist( int $post_id, array $playlist_data, string $mode, array $specific_metadata = array(), array $destination_taxonomy_terms = array() ): bool|\WP_Error {
		if ( (bool) get_post_meta( $post_id, '_yousync_protected', true ) ) {
			return true;
		}

		if ( ! empty( $destination_taxonomy_terms ) ) {
			$this->assign_destination_taxonomy_terms( $post_id, $destination_taxonomy_terms );
		}

		if ( 'update_specific_all' === $mode ) {
			foreach ( $specific_metadata as $field ) {
				switch ( $field ) {
					case 'playlist_title':
						wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( $playlist_data['playlist_title'] ) ) );
						update_post_meta( $post_id, '_yousync_playlist_title', $playlist_data['playlist_title'] );
						break;
					case 'playlist_description':
						update_post_meta( $post_id, '_yousync_playlist_description', $playlist_data['playlist_description'] );
						break;
					case 'playlist_video_count':
						update_post_meta( $post_id, '_yousync_playlist_video_count', (int) $playlist_data['playlist_video_count'] );
						break;
					case 'playlist_thumbnail':
						update_post_meta( $post_id, '_yousync_playlist_thumbnail', $playlist_data['thumbnail_url'] ?? '' );
						break;
				}
			}
			update_post_meta( $post_id, '_yousync_last_synced', time() );
			return true;
		}

		// Full update.
		wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => sanitize_text_field( $playlist_data['playlist_title'] ?: $playlist_data['playlist_id'] ),
		) );

		$channel_id = (string) get_post_meta( $post_id, '_yousync_channel_id', true );
		$this->save_playlist_meta( $post_id, $playlist_data, $channel_id );

		return true;
	}

	/**
	 * Save playlist data as individual post meta keys.
	 *
	 * @param int    $post_id       Post ID.
	 * @param array  $playlist_data Normalised playlist data.
	 * @param string $channel_id    YouTube channel ID.
	 * @return void
	 */
	private function save_playlist_meta( int $post_id, array $playlist_data, string $channel_id ): void {
		update_post_meta( $post_id, '_yousync_playlist_id',          $playlist_data['playlist_id'] );
		update_post_meta( $post_id, '_yousync_channel_id',           $channel_id );
		update_post_meta( $post_id, '_yousync_playlist_title',       $playlist_data['playlist_title'] ?? '' );
		update_post_meta( $post_id, '_yousync_playlist_description', $playlist_data['playlist_description'] ?? '' );
		update_post_meta( $post_id, '_yousync_playlist_video_count', (int) ( $playlist_data['playlist_video_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_playlist_thumbnail',   $playlist_data['thumbnail_url'] ?? '' );
		update_post_meta( $post_id, '_yousync_etag',                 $playlist_data['etag'] ?? '' );
		update_post_meta( $post_id, '_yousync_last_synced',          time() );
	}

	// -------------------------------------------------------------------------
	// Channel import methods
	// -------------------------------------------------------------------------

	/**
	 * Find an existing post by its YouTube channel ID.
	 *
	 * Uses _yousync_channel_post as the dedup key (distinct from _yousync_channel_id,
	 * which stores the source channel ID on video/playlist posts).
	 *
	 * @param string $channel_id YouTube channel ID.
	 * @return int Post ID, or 0 if not found.
	 */
	public function find_post_by_channel_id( string $channel_id, string $post_type = '' ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type ?: 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_yousync_channel_post',
						'value' => $channel_id,
					),
				),
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Find every channel post for a YouTube channel ID, across all post types.
	 *
	 * With per-post-type dedup a channel may exist as one post per post type.
	 * Update rules refresh all of them.
	 *
	 * @param string $channel_id YouTube channel ID.
	 * @return int[] Post IDs.
	 */
	public function find_posts_by_channel_id( string $channel_id ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_yousync_channel_post',
						'value' => $channel_id,
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Import a YouTube channel as a new WordPress post.
	 *
	 * Deduplication key: _yousync_channel_post. The channel profile picture is
	 * sideloaded as the post's featured image (fallback: user-set image takes
	 * precedence on the frontend via the post_thumbnail_html filter).
	 *
	 * @param array  $channel_data              Channel data from YouTube_API::get_channel_data().
	 * @param string $channel_id                YouTube channel ID.
	 * @param string $post_type                 Destination post type.
	 * @param array  $destination_taxonomy_terms Array of ['taxonomy' => string, 'term_ids' => int[]].
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import_channel( array $channel_data, string $channel_id, string $post_type, array $destination_taxonomy_terms = array(), array $field_mapping = array() ): int|\WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_title'  => sanitize_text_field( $channel_data['channel_title'] ?? $channel_id ),
				'post_type'   => $post_type,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $destination_taxonomy_terms ) ) {
			$this->assign_destination_taxonomy_terms( $post_id, $destination_taxonomy_terms );
		}

		$this->save_channel_meta( $post_id, $channel_data, $channel_id );
		update_post_meta( $post_id, '_yousync_protected', 0 );

		// Apply field mapping (save the mapping on the post and write initial values).
		$this->save_field_mapping( $post_id, $field_mapping );
		$this->apply_stored_mapping( $post_id );

		// Sideload profile picture as the featured image.
		$pic_url = $channel_data['profile_picture']['url'] ?? '';
		if ( $pic_url ) {
			$this->sideload_as_featured_image( $post_id, $pic_url );
		}

		return $post_id;
	}

	/**
	 * Update an existing channel post with fresh YouTube data.
	 *
	 * Modes:
	 *   update_all      — Update title, all internal meta, and re-sideload profile picture if not set.
	 *   update_specific — Only update fields listed in $specific_metadata.
	 *
	 * @param int      $post_id          Existing post ID.
	 * @param array    $channel_data     Fresh channel data from YouTube_API::get_channel_data().
	 * @param string   $channel_id       YouTube channel ID.
	 * @param string   $mode             'update_all' or 'update_specific'.
	 * @param string[] $specific_metadata Fields to update (for update_specific).
	 * @return true|\WP_Error
	 */
	public function update_channel( int $post_id, array $channel_data, string $channel_id, string $mode, array $specific_metadata = array() ): bool|\WP_Error {
		if ( (bool) get_post_meta( $post_id, '_yousync_protected', true ) ) {
			return true;
		}

		if ( 'update_specific' === $mode ) {
			foreach ( $specific_metadata as $field ) {
				switch ( $field ) {
					case 'channel_title':
						wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( $channel_data['channel_title'] ?? '' ) ) );
						update_post_meta( $post_id, '_yousync_channel_title', $channel_data['channel_title'] ?? '' );
						break;
					case 'channel_description':
						update_post_meta( $post_id, '_yousync_channel_description', $channel_data['channel_description'] ?? '' );
						break;
					case 'subscriber_count':
						update_post_meta( $post_id, '_yousync_subscriber_count', (int) ( $channel_data['subscriber_count'] ?? 0 ) );
						break;
					case 'video_count':
						update_post_meta( $post_id, '_yousync_channel_video_count', (int) ( $channel_data['video_count'] ?? 0 ) );
						break;
					case 'profile_picture':
						$pic_url = $channel_data['profile_picture']['url'] ?? '';
						if ( $pic_url && ! get_post_thumbnail_id( $post_id ) ) {
							$this->sideload_as_featured_image( $post_id, $pic_url );
						}
						break;
				}
			}
			update_post_meta( $post_id, '_yousync_last_synced', time() );
			return true;
		}

		// Full update: refresh title and all internal meta.
		wp_update_post( array(
			'ID'         => $post_id,
			'post_title' => sanitize_text_field( $channel_data['channel_title'] ?? $channel_id ),
		) );
		$this->save_channel_meta( $post_id, $channel_data, $channel_id );

		// Only sideload profile picture if no featured image is set yet.
		$pic_url = $channel_data['profile_picture']['url'] ?? '';
		if ( $pic_url && ! get_post_thumbnail_id( $post_id ) ) {
			$this->sideload_as_featured_image( $post_id, $pic_url );
		}

		return true;
	}

	/**
	 * Save channel data as individual post meta keys.
	 *
	 * @param int    $post_id      Post ID.
	 * @param array  $channel_data Channel data from YouTube_API::get_channel_data().
	 * @param string $channel_id   YouTube channel ID.
	 * @return void
	 */
	private function save_channel_meta( int $post_id, array $channel_data, string $channel_id ): void {
		update_post_meta( $post_id, '_yousync_channel_post',        $channel_id );
		update_post_meta( $post_id, '_yousync_channel_id',          $channel_id );
		update_post_meta( $post_id, '_yousync_channel_title',       $channel_data['channel_title'] ?? '' );
		update_post_meta( $post_id, '_yousync_channel_description', $channel_data['channel_description'] ?? '' );
		update_post_meta( $post_id, '_yousync_subscriber_count',    (int) ( $channel_data['subscriber_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_channel_video_count', (int) ( $channel_data['video_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_etag',                $channel_data['etag'] ?? '' );
		update_post_meta( $post_id, '_yousync_profile_picture',    $channel_data['profile_picture']['url'] ?? '' );
		update_post_meta( $post_id, '_yousync_banner_image',       $channel_data['banner_image']['url'] ?? '' );
		update_post_meta( $post_id, '_yousync_source_type',         'channel' );
		update_post_meta( $post_id, '_yousync_last_synced',         time() );
	}

	/**
	 * Sideload an image URL as the post's featured image.
	 *
	 * A no-op if the attachment sideload fails.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Image URL to sideload.
	 * @return void
	 */
	private function sideload_as_featured_image( int $post_id, string $url ): void {
		// Always include admin dependencies — they may not be loaded in cron context
		// even if media_sideload_image itself has been defined elsewhere.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = media_sideload_image( $url, $post_id, '', 'id' );
		if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
			set_post_thumbnail( $post_id, (int) $attachment_id );
		}
	}

	/**
	 * Sideload the stored profile picture as the post's featured image if none is set.
	 *
	 * Used to backfill the featured image on channel posts that were imported before
	 * the profile picture URL was correctly populated.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function ensure_channel_featured_image( int $post_id ): void {
		if ( get_post_thumbnail_id( $post_id ) ) {
			return;
		}
		$url = (string) get_post_meta( $post_id, '_yousync_profile_picture', true );
		if ( $url ) {
			$this->sideload_as_featured_image( $post_id, $url );
		}
	}

	/**
	 * Get the URL of the largest available thumbnail.
	 *
	 * Used by the post_thumbnail_html filter in yousync.php.
	 *
	 * @param array $thumbnails Thumbnails array from _yousync_video meta.
	 * @return array|null Thumbnail array (url, width, height) or null if none found.
	 */
	public static function get_best_thumbnail( array $thumbnails ): ?array {
		foreach ( self::THUMBNAIL_SIZE_PRIORITY as $size ) {
			if ( ! empty( $thumbnails[ $size ]['url'] ) ) {
				return $thumbnails[ $size ];
			}
		}
		return null;
	}
}
