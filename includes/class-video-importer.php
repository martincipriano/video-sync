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
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public function import( array $video_data, string $source_type, int $source_term_id, string $post_type = '', array $destination_taxonomy_terms = array() ): int|\WP_Error {
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

		return $post_id;
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

	// -------------------------------------------------------------------------
	// Private methods
	// -------------------------------------------------------------------------

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
	public function import_channel( array $channel_data, string $channel_id, string $post_type, array $destination_taxonomy_terms = array() ): int|\WP_Error {
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

		// Sideload profile picture as the featured image.
		$pic_url = $channel_data['profile_picture']['url'] ?? '';
		if ( $pic_url ) {
			$this->sideload_as_featured_image( $post_id, $pic_url );
		}

		return $post_id;
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
