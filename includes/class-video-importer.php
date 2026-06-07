<?php
declare(strict_types=1);

/**
 * Video importer.
 *
 * Creates and updates posts from normalised YouTube video data.
 * Post type and taxonomy destinations are read from the channel config
 * stored in wp_options — nothing is hardcoded.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Video_Importer
 *
 * Handles creation and updating of posts synced from YouTube.
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

	// -------------------------------------------------------------------------
	// Public methods
	// -------------------------------------------------------------------------

	/**
	 * Import a YouTube video as a new post.
	 *
	 * Assumes the caller has already confirmed the video does not exist.
	 *
	 * @param array $video_data Normalised video data from YouTube_API::get_videos_by_ids().
	 * @param array $config     Channel config from yousync_channel_config option.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import( array $video_data, array $config ): int|\WP_Error {
		$post_type = $config['destination_post_type'] ?? 'post';

		// 1. Create the post.
		$post_id = wp_insert_post(
			array(
				'post_title'   => sanitize_text_field( $video_data['title'] ),
				'post_content' => wp_kses_post( $video_data['description'] ),
				'post_type'    => $post_type,
				'post_status'  => 'publish',
			),
			true // Return WP_Error on failure.
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// 2. Assign video tags (if a tags taxonomy is configured).
		if ( ! empty( $config['destination_tags_taxonomy'] ) && ! empty( $video_data['tags'] ) ) {
			$this->assign_video_tags( $post_id, $video_data['tags'], $config['destination_tags_taxonomy'] );
		}

		// 3. Assign YouTube video category (if a category taxonomy is configured).
		if ( ! empty( $config['destination_category_taxonomy'] ) && ! empty( $video_data['category_id'] ) ) {
			$this->assign_video_category( $post_id, $video_data['category_id'], $config['destination_category_taxonomy'] );
		}

		// 4. Build and save JSON meta (thumbnail URLs stored directly, no sideloading).
		$meta = $this->build_video_meta( $video_data, $config, array() );
		update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $meta ) ) );

		// 6. Save flat meta keys for indexed lookups and meta_query filtering.
		update_post_meta( $post_id, '_yousync_video_id', $video_data['video_id'] );
		$this->save_flat_meta( $post_id, $video_data );

		return $post_id;
	}

	/**
	 * Find an existing post by its YouTube video ID.
	 *
	 * Uses the flat _yousync_video_id meta key (indexed) for fast lookups.
	 *
	 * @param string $video_id  YouTube video ID.
	 * @param string $post_type Post type to search within.
	 * @return int Post ID, or 0 if not found.
	 */
	public function find_post_by_video_id( string $video_id, string $post_type ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
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
	 * Uses a single meta_query with 'compare' => 'IN' instead of one query per ID.
	 *
	 * @param string[] $video_ids YouTube video IDs.
	 * @param string   $post_type Post type to search within.
	 * @return array<string, int> Map of video_id => post_id for found videos.
	 */
	public function find_posts_by_video_ids( array $video_ids, string $post_type ): array {
		if ( empty( $video_ids ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'fields'         => 'ids',
				'posts_per_page' => count( $video_ids ),
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_yousync_video_id',
						'value'   => $video_ids,
						'compare' => 'IN',
					),
				),
			)
		);

		$map = array();
		foreach ( $query->posts as $post_id ) {
			$vid = get_post_meta( (int) $post_id, '_yousync_video_id', true );
			if ( $vid ) {
				$map[ $vid ] = (int) $post_id;
			}
		}

		return $map;
	}

	/**
	 * Update an existing post with fresh YouTube data.
	 *
	 * Modes:
	 *   update_all                   — Update everything (title, content, meta, taxonomies).
	 *   update_non_modified          — Same but skips posts where manual_edits = true.
	 *   update_specific_all          — Only update fields listed in $specific_metadata.
	 *   update_specific_non_modified — Same but skips posts where manual_edits = true.
	 *
	 * @param int      $post_id           Existing post ID.
	 * @param array    $video_data        Fresh normalised video data from the API.
	 * @param string   $mode              One of the four mode strings above.
	 * @param array    $config            Channel config from yousync_channel_config option.
	 * @param string[] $specific_metadata Fields to update (used in update_specific_* modes).
	 * @return true|\WP_Error True on success.
	 */
	public function update( int $post_id, array $video_data, string $mode, array $config, array $specific_metadata = array() ): bool|\WP_Error {
		// Load existing meta.
		$raw           = get_post_meta( $post_id, '_yousync_video', true );
		$existing_meta = is_string( $raw ) ? ( json_decode( $raw, true ) ?: array() ) : array();
		$manual_edits  = (bool) ( $existing_meta['manual_edits'] ?? false );

		// Protect from Sync takes absolute precedence over any sync rule update mode.
		if ( $manual_edits ) {
			return true;
		}

		if ( in_array( $mode, array( 'update_specific_all', 'update_specific_non_modified' ), true ) ) {
			$this->apply_selective_update( $post_id, $existing_meta, $video_data, $specific_metadata, $config );
			return true;
		}

		// Full update: title, content, taxonomies, meta.
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => sanitize_text_field( $video_data['title'] ),
				'post_content' => wp_kses_post( $video_data['description'] ),
			)
		);

		if ( ! empty( $config['destination_tags_taxonomy'] ) && ! empty( $video_data['tags'] ) ) {
			$this->assign_video_tags( $post_id, $video_data['tags'], $config['destination_tags_taxonomy'] );
		}

		if ( ! empty( $config['destination_category_taxonomy'] ) && ! empty( $video_data['category_id'] ) ) {
			$this->assign_video_category( $post_id, $video_data['category_id'], $config['destination_category_taxonomy'] );
		}

		$meta                 = $this->build_video_meta( $video_data, $config, $existing_meta );
		$meta['manual_edits'] = $manual_edits;

		update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $meta ) ) );
		$this->save_flat_meta( $post_id, $video_data );

		return true;
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
	 * @param array    $config            Channel config.
	 * @return void
	 */
	private function apply_selective_update(
		int $post_id,
		array $existing_meta,
		array $video_data,
		array $specific_metadata,
		array $config
	): void {
		$post_update = array( 'ID' => $post_id );
		$meta        = $existing_meta;

		foreach ( $specific_metadata as $field ) {
			switch ( $field ) {
				case 'title':
					$post_update['post_title'] = sanitize_text_field( $video_data['title'] );
					$meta['original_title']    = $video_data['title'];
					break;

				case 'description':
					$post_update['post_content']  = wp_kses_post( $video_data['description'] );
					$meta['original_description'] = $video_data['description'];
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
						$meta['thumbnails'] = $thumbnails;
					}
					break;

				case 'tags':
					if ( ! empty( $config['destination_tags_taxonomy'] ) && isset( $video_data['tags'] ) ) {
						$this->assign_video_tags( $post_id, $video_data['tags'], $config['destination_tags_taxonomy'] );
					}
					break;

				case 'yousync_category':
					if ( ! empty( $config['destination_category_taxonomy'] ) && ! empty( $video_data['category_id'] ) ) {
						$this->assign_video_category( $post_id, $video_data['category_id'], $config['destination_category_taxonomy'] );
					}
					break;

				case 'duration':
					$meta['duration_seconds'] = $video_data['duration_seconds'];
					update_post_meta( $post_id, '_yousync_duration_seconds', (int) $video_data['duration_seconds'] );
					break;

				case 'view_count':
					$meta['view_count'] = $video_data['view_count'];
					update_post_meta( $post_id, '_yousync_view_count', (int) $video_data['view_count'] );
					break;

				case 'like_count':
					$meta['like_count'] = $video_data['like_count'];
					update_post_meta( $post_id, '_yousync_like_count', (int) $video_data['like_count'] );
					break;

				case 'comment_count':
					$meta['comment_count'] = $video_data['comment_count'];
					update_post_meta( $post_id, '_yousync_comment_count', (int) $video_data['comment_count'] );
					break;

				case 'published_date':
					$meta['published_date'] = $video_data['published_at'];
					update_post_meta( $post_id, '_yousync_published_at', $video_data['published_at'] );
					break;
			}
		}

		if ( count( $post_update ) > 1 ) {
			wp_update_post( $post_update );
		}

		$meta['last_synced'] = time();
		$meta['sync_count']  = (int) ( $meta['sync_count'] ?? 0 ) + 1;

		update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $meta ) ) );
	}

	/**
	 * Assign video tags to a post.
	 *
	 * Creates terms in the given taxonomy if they don't yet exist.
	 *
	 * @param int      $post_id  Post ID.
	 * @param string[] $tags     Tag strings from YouTube.
	 * @param string   $taxonomy Taxonomy slug to assign tags in.
	 * @return void
	 */
	private function assign_video_tags( int $post_id, array $tags, string $taxonomy ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		wp_set_object_terms( $post_id, $tags, $taxonomy );
	}

	/**
	 * Assign a YouTube video category to a post.
	 *
	 * Uses the hardcoded YOUTUBE_CATEGORIES map so no extra API call is needed.
	 * Term slug = numeric category ID; term name = human-readable label.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $category_id YouTube category ID (e.g. '10' for Music).
	 * @param string $taxonomy    Taxonomy slug to assign the category in.
	 * @return void
	 */
	private function assign_video_category( int $post_id, string $category_id, string $taxonomy ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term_name = self::YOUTUBE_CATEGORIES[ $category_id ] ?? "Category {$category_id}";
		$term      = get_term_by( 'slug', $category_id, $taxonomy );

		if ( ! $term ) {
			$result = wp_insert_term( $term_name, $taxonomy, array( 'slug' => $category_id ) );
			if ( is_wp_error( $result ) ) {
				return;
			}
			$term_id = $result['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		wp_set_object_terms( $post_id, array( $term_id ), $taxonomy, true );
	}

	/**
	 * Build the _yousync_video meta array from video data.
	 *
	 * Thumbnails are stored as URL + dimensions only — no attachment IDs.
	 *
	 * @param array $video_data    Normalised video data.
	 * @param array $config        Channel config.
	 * @param array $existing_meta Existing meta (used to preserve fields on update).
	 * @return array Complete meta array ready for wp_json_encode.
	 */
	private function build_video_meta( array $video_data, array $config, array $existing_meta ): array {
		$thumbnails = array();
		foreach ( $video_data['thumbnails'] ?? array() as $size => $thumb ) {
			$thumbnails[ $size ] = array(
				'url'    => $thumb['url'],
				'width'  => $thumb['width'],
				'height' => $thumb['height'],
			);
		}

		$now        = time();
		$sync_count = (int) ( $existing_meta['sync_count'] ?? 0 ) + 1;

		return array(
			'video_id'             => $video_data['video_id'],
			'video_url'            => 'https://www.youtube.com/watch?v=' . $video_data['video_id'],
			'channel_id'           => $video_data['channel_id'],
			'channel_title'        => $video_data['channel_title'],
			'etag'                 => $video_data['etag'],
			'sync_source_type'     => 'channel',
			'sync_source_id'       => $config['youtube_id'] ?? '',
			'original_title'       => $video_data['title'],
			'original_description' => $video_data['description'],
			'published_date'       => $video_data['published_at'],
			'duration_seconds'     => $video_data['duration_seconds'],
			'view_count'           => $video_data['view_count'],
			'like_count'           => $video_data['like_count'],
			'comment_count'        => $video_data['comment_count'],
			'thumbnails'           => $thumbnails,
			'manual_edits'         => $existing_meta['manual_edits'] ?? false,
			'last_synced'          => $now,
			'last_modified'        => $now,
			'sync_count'           => $sync_count,
			'sync_errors'          => $existing_meta['sync_errors'] ?? array(),
		);
	}

	/**
	 * Write flat meta keys for the queryable video fields.
	 *
	 * These mirror specific values already stored in _yousync_video JSON so
	 * that developers can use standard WP_Query meta_query filters on them
	 * without parsing JSON.
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $video_data Normalised video data from YouTube_API::get_videos_by_ids().
	 * @return void
	 */
	private function save_flat_meta( int $post_id, array $video_data ): void {
		update_post_meta( $post_id, '_yousync_view_count', (int) ( $video_data['view_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_like_count', (int) ( $video_data['like_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_comment_count', (int) ( $video_data['comment_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_duration_seconds', (int) ( $video_data['duration_seconds'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_published_at', $video_data['published_at'] ?? '' );
		update_post_meta( $post_id, '_yousync_channel_id', $video_data['channel_id'] ?? '' );
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
