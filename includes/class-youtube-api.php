<?php
declare(strict_types=1);
/**
 * YouTube Data API v3 wrapper.
 *
 * Fetches channel, playlist, and video data without using search.list
 * (which costs 100 quota units per call). Every method here costs 1 unit
 * per page of up to 50 items.
 *
 * @package WPBuoy_Video_Sync
 */

namespace WPBuoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YouTube_API
 *
 * Wraps YouTube Data API v3 calls using wp_remote_get().
 */
class YouTube_API {

	/**
	 * YouTube Data API v3 base URL.
	 *
	 * @var string
	 */
	private const BASE_URL = 'https://www.googleapis.com/youtube/v3/';

	/**
	 * HTTP status codes that indicate a transient server-side failure worth retrying.
	 */
	private const TRANSIENT_STATUSES = [ 429, 500, 502, 503, 504 ];

	/**
	 * Maximum number of retry attempts on transient failures.
	 */
	private const MAX_RETRIES = 3;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key YouTube Data API v3 key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	// -------------------------------------------------------------------------
	// Public API methods
	// -------------------------------------------------------------------------

	/**
	 * Get a channel's uploads playlist ID and basic metadata.
	 *
	 * Uses channels.list?part=snippet,contentDetails,statistics — 1 quota unit.
	 *
	 * @param string $channel_id YouTube channel ID (e.g. UCuAXFkgsw1L7xaCfnd5JJOw).
	 * @return array|WP_Error {
	 *     @type string $uploads_playlist_id
	 *     @type string $channel_title
	 *     @type string $channel_description
	 *     @type int    $subscriber_count
	 *     @type int    $video_count
	 *     @type string $etag
	 * }
	 */
	public function get_channel_data( string $channel_id ): array|\WP_Error {
		$url  = $this->api_url(
			'channels',
			array(
				'part' => 'snippet,contentDetails,statistics,brandingSettings',
				'id'   => $channel_id,
			)
		);
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['items'] ) ) {
			return new \WP_Error( 'channel_not_found', "Channel not found. Please verify the Channel ID ({$channel_id}) in your channel settings." );
		}

		$item = $data['items'][0];

		return array(
			'uploads_playlist_id' => $item['contentDetails']['relatedPlaylists']['uploads'] ?? '',
			'channel_title'       => $item['snippet']['title'] ?? '',
			'channel_description' => $item['snippet']['description'] ?? '',
			'subscriber_count'    => (int) ( $item['statistics']['subscriberCount'] ?? 0 ),
			'video_count'         => (int) ( $item['statistics']['videoCount'] ?? 0 ),
			'profile_picture'     => array( 'url' => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['medium']['url'] ?? $item['snippet']['thumbnails']['default']['url'] ?? '' ),
			'banner_image'        => array( 'url' => $item['brandingSettings']['image']['bannerExternalUrl'] ?? $item['brandingSettings']['image']['bannerTabletImageUrl'] ?? '' ),
			'etag'                => $item['etag'] ?? '',
		);
	}

	/**
	 * Resolve a user-supplied channel reference to a channel ID (UC…).
	 *
	 * Accepts a raw ID; a /channel/, /@handle, /user/, /c/, or video URL; a bare
	 * @handle; or a custom name. Uses the Data API where possible (1 quota unit) and
	 * falls back to reading the channel page's canonical channel ID (no quota) for
	 * legacy /c/ custom URLs or when no API key is configured.
	 *
	 * @param string $input Raw value the user entered.
	 * @return string|\WP_Error Channel ID on success, WP_Error if it cannot be resolved.
	 */
	public function resolve_channel_id( string $input ): string|\WP_Error {
		$input = trim( $input );
		if ( '' === $input ) {
			return new \WP_Error( 'wpbyvs_channel_empty', __( 'Enter a channel URL or ID.', 'wby-video-sync' ) );
		}

		// Already a channel ID.
		if ( preg_match( '#^UC[A-Za-z0-9_-]{22}$#', $input ) ) {
			return $input;
		}

		// A /channel/UC… URL — the ID is embedded.
		if ( preg_match( '#/channel/(UC[A-Za-z0-9_-]{22})#', $input, $m ) ) {
			return $m[1];
		}

		// Data API resolution (needs a key) — 1 quota unit each.
		if ( '' !== $this->api_key ) {
			if ( preg_match( '#(?:^|/)@([A-Za-z0-9._-]+)#', $input, $m ) ) {
				$id = $this->lookup_channel( array( 'forHandle' => '@' . $m[1] ) );
				if ( ! is_wp_error( $id ) ) {
					return $id;
				}
			}
			if ( preg_match( '#/user/([A-Za-z0-9._-]+)#', $input, $m ) ) {
				$id = $this->lookup_channel( array( 'forUsername' => $m[1] ) );
				if ( ! is_wp_error( $id ) ) {
					return $id;
				}
			}
			if ( preg_match( '#(?:v=|youtu\.be/|/shorts/|/embed/)([A-Za-z0-9_-]{11})#', $input, $m ) ) {
				$id = $this->channel_id_from_video( $m[1] );
				if ( ! is_wp_error( $id ) ) {
					return $id;
				}
			}
		}

		// Fallback: read the channel page's canonical ID (no quota). Handles legacy
		// /c/ custom URLs and works without an API key.
		$id = $this->channel_id_from_page( $input );
		if ( ! is_wp_error( $id ) ) {
			return $id;
		}

		return new \WP_Error(
			'wpbyvs_channel_unresolved',
			__( 'Could not find a channel for that link. Paste the channel URL, @handle, or ID.', 'wby-video-sync' )
		);
	}

	/**
	 * Look up a channel ID via channels.list (forHandle / forUsername).
	 *
	 * @param array $params e.g. [ 'forHandle' => '@name' ] or [ 'forUsername' => 'name' ].
	 * @return string|\WP_Error
	 */
	private function lookup_channel( array $params ): string|\WP_Error {
		$data = $this->request( $this->api_url( 'channels', array_merge( array( 'part' => 'id' ), $params ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$id = $data['items'][0]['id'] ?? '';
		return $id ?: new \WP_Error( 'wpbyvs_channel_not_found', __( 'No channel matched that link.', 'wby-video-sync' ) );
	}

	/**
	 * Resolve a video ID to its owning channel ID via videos.list.
	 *
	 * @param string $video_id YouTube video ID.
	 * @return string|\WP_Error
	 */
	private function channel_id_from_video( string $video_id ): string|\WP_Error {
		$data = $this->request( $this->api_url( 'videos', array( 'part' => 'snippet', 'id' => $video_id ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$id = $data['items'][0]['snippet']['channelId'] ?? '';
		return $id ?: new \WP_Error( 'wpbyvs_channel_not_found', __( 'No channel matched that video.', 'wby-video-sync' ) );
	}

	/**
	 * Read a channel's canonical ID from its public page (no API quota).
	 *
	 * Fetches the channel URL (or builds one from a handle/custom name) and extracts
	 * the UC… ID from the page's channelId / canonical markup. Used only as a fallback.
	 *
	 * @param string $input A channel URL, @handle, or custom name.
	 * @return string|\WP_Error
	 */
	private function channel_id_from_page( string $input ): string|\WP_Error {
		if ( preg_match( '#^https?://#i', $input ) ) {
			$url = $input;
		} elseif ( false !== strpos( $input, 'youtube.com' ) || false !== strpos( $input, 'youtu.be' ) ) {
			$url = 'https://' . ltrim( $input, '/' );
		} elseif ( '@' === $input[0] ) {
			$url = 'https://www.youtube.com/' . $input;
		} else {
			$url = 'https://www.youtube.com/' . ltrim( $input, '/' );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 5 ) );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'wpbyvs_channel_fetch_error', __( 'Could not reach YouTube to look up that channel.', 'wby-video-sync' ) );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'wpbyvs_channel_not_found', __( 'No channel matched that link.', 'wby-video-sync' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		// Only markers that identify the PAGE'S OWN channel — a generic "channelId"
		// or "/channel/" match would catch recommended/featured channels on the page.
		$patterns = array(
			'#<link[^>]+rel="canonical"[^>]+href="[^"]*?/channel/(UC[A-Za-z0-9_-]{22})"#',
			'#<meta[^>]+property="og:url"[^>]+content="[^"]*?/channel/(UC[A-Za-z0-9_-]{22})"#',
			'#"externalId":"(UC[A-Za-z0-9_-]{22})"#',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $body, $m ) ) {
				return $m[1];
			}
		}

		return new \WP_Error( 'wpbyvs_channel_not_found', __( 'No channel matched that link.', 'wby-video-sync' ) );
	}

	/**
	 * Get all items from a playlist (paginated).
	 *
	 * Uses playlistItems.list?part=snippet — 1 quota unit per page of 50.
	 * Iterates nextPageToken until all items are collected.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return array|WP_Error Flat array of items, each: {
	 *     @type string $video_id
	 *     @type string $title
	 *     @type string $description
	 *     @type string $published_at  ISO 8601 datetime
	 *     @type int    $position      0-based position in playlist
	 *     @type string $channel_title Channel that owns the video
	 * }
	 */
	public function get_playlist_items( string $playlist_id ): array|\WP_Error {
		$items      = array();
		$page_token = null;

		do {
			$params = array(
				'part'       => 'snippet',
				'playlistId' => $playlist_id,
				'maxResults' => 50,
			);
			if ( $page_token ) {
				$params['pageToken'] = $page_token;
			}

			$data = $this->request( $this->api_url( 'playlistItems', $params ) );

			if ( is_wp_error( $data ) ) {
				return $data;
			}

			foreach ( $data['items'] ?? array() as $item ) {
				$snippet  = $item['snippet'] ?? array();
				$resource = $snippet['resourceId'] ?? array();

				// Skip items that are not videos (e.g. deleted/private placeholders).
				if ( ( $resource['kind'] ?? '' ) !== 'youtube#video' ) {
					continue;
				}

				$items[] = array(
					'video_id'      => $resource['videoId'] ?? '',
					'title'         => $snippet['title'] ?? '',
					'description'   => $snippet['description'] ?? '',
					'published_at'  => $snippet['publishedAt'] ?? '',
					'position'      => (int) ( $snippet['position'] ?? 0 ),
					'channel_title' => $snippet['videoOwnerChannelTitle'] ?? '',
				);
			}

			$page_token = $data['nextPageToken'] ?? null;

		} while ( $page_token );

		return $items;
	}

	/**
	 * Fetch full video details for up to 50 video IDs in a single request.
	 *
	 * Uses videos.list?part=snippet,contentDetails,statistics — 1 quota unit.
	 *
	 * @param string[] $video_ids Array of YouTube video IDs (max 50).
	 * @return array|WP_Error Keyed array [ video_id => video_data ]. video_data: {
	 *     @type string   $video_id
	 *     @type string   $title
	 *     @type string   $description
	 *     @type string   $channel_id
	 *     @type string   $channel_title
	 *     @type string   $published_at       ISO 8601
	 *     @type int      $duration_seconds
	 *     @type int      $view_count
	 *     @type int      $like_count
	 *     @type int      $comment_count
	 *     @type string[] $tags
	 *     @type string   $category_id
	 *     @type array    $thumbnails          { default, medium, high, standard, maxres }
	 *     @type string   $etag
	 * }
	 */
	public function get_videos_by_ids( array $video_ids ): array|\WP_Error {
		if ( empty( $video_ids ) ) {
			return array();
		}

		$url  = $this->api_url(
			'videos',
			array(
				'part' => 'snippet,contentDetails,statistics',
				'id'   => implode( ',', array_slice( $video_ids, 0, 50 ) ),
			)
		);
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$videos = array();

		foreach ( $data['items'] ?? array() as $item ) {
			$snippet    = $item['snippet'] ?? array();
			$details    = $item['contentDetails'] ?? array();
			$statistics = $item['statistics'] ?? array();
			$video_id   = $item['id'] ?? '';

			$thumbnails = array();
			foreach ( $snippet['thumbnails'] ?? array() as $size => $thumb ) {
				$thumbnails[ $size ] = array(
					'url'    => $thumb['url'] ?? '',
					'width'  => (int) ( $thumb['width'] ?? 0 ),
					'height' => (int) ( $thumb['height'] ?? 0 ),
				);
			}

			$videos[ $video_id ] = array(
				'video_id'         => $video_id,
				'title'            => $snippet['title'] ?? '',
				'description'      => $snippet['description'] ?? '',
				'channel_id'       => $snippet['channelId'] ?? '',
				'channel_title'    => $snippet['channelTitle'] ?? '',
				'published_at'     => $snippet['publishedAt'] ?? '',
				'duration_seconds' => $this->iso8601_to_seconds( $details['duration'] ?? 'PT0S' ),
				'view_count'       => (int) ( $statistics['viewCount'] ?? 0 ),
				'like_count'       => (int) ( $statistics['likeCount'] ?? 0 ),
				'comment_count'    => (int) ( $statistics['commentCount'] ?? 0 ),
				'tags'             => $snippet['tags'] ?? array(),
				'category_id'      => $snippet['categoryId'] ?? '',
				'thumbnails'       => $thumbnails,
				'etag'             => $item['etag'] ?? '',
			);
		}

		return $videos;
	}

	/**
	 * Fetch a single playlist's metadata.
	 *
	 * Uses playlists.list?part=snippet,contentDetails — 1 quota unit.
	 *
	 * @param string $playlist_id YouTube playlist ID.
	 * @return array|WP_Error {
	 *     @type string $playlist_id
	 *     @type string $playlist_title
	 *     @type string $playlist_description
	 *     @type int    $playlist_video_count
	 *     @type string $thumbnail_url
	 *     @type string $etag
	 * }
	 */
	public function get_playlist_data( string $playlist_id ): array|\WP_Error {
		$url  = $this->api_url(
			'playlists',
			array(
				'part' => 'snippet,contentDetails',
				'id'   => $playlist_id,
			)
		);
		$data = $this->request( $url );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['items'] ) ) {
			return new \WP_Error( 'playlist_not_found', "Playlist not found. Please verify the Playlist ID ({$playlist_id}) in your playlist settings." );
		}

		$item    = $data['items'][0];
		$snippet = $item['snippet'] ?? array();

		// Pick the highest-quality thumbnail available.
		$thumb_url = '';
		foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) {
			if ( ! empty( $snippet['thumbnails'][ $size ]['url'] ) ) {
				$thumb_url = $snippet['thumbnails'][ $size ]['url'];
				break;
			}
		}

		return array(
			'playlist_id'          => $item['id'] ?? '',
			'playlist_title'       => $snippet['title'] ?? '',
			'playlist_description' => $snippet['description'] ?? '',
			'playlist_video_count' => (int) ( $item['contentDetails']['itemCount'] ?? 0 ),
			'thumbnail_url'        => $thumb_url,
			'channel_id'           => $snippet['channelId'] ?? '',
			'etag'                 => $item['etag'] ?? '',
		);
	}

	/**
	 * Fetch all playlists belonging to a channel (paginated).
	 *
	 * Uses playlists.list?part=snippet,contentDetails&channelId=... — 1 unit per page of 50.
	 * Iterates nextPageToken until all playlists are collected.
	 *
	 * @param string $channel_id YouTube channel ID.
	 * @return array|WP_Error Flat array of playlist data arrays, each matching the
	 *                        shape returned by get_playlist_data().
	 */
	public function get_channel_playlists( string $channel_id ): array|\WP_Error {
		$playlists  = array();
		$page_token = null;

		do {
			$params = array(
				'part'       => 'snippet,contentDetails',
				'channelId'  => $channel_id,
				'maxResults' => 50,
			);
			if ( $page_token ) {
				$params['pageToken'] = $page_token;
			}

			$data = $this->request( $this->api_url( 'playlists', $params ) );

			if ( is_wp_error( $data ) ) {
				return $data;
			}

			foreach ( $data['items'] ?? array() as $item ) {
				$snippet   = $item['snippet'] ?? array();
				$thumb_url = '';

				foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) {
					if ( ! empty( $snippet['thumbnails'][ $size ]['url'] ) ) {
						$thumb_url = $snippet['thumbnails'][ $size ]['url'];
						break;
					}
				}

				$playlists[] = array(
					'playlist_id'          => $item['id'] ?? '',
					'playlist_title'       => $snippet['title'] ?? '',
					'playlist_description' => $snippet['description'] ?? '',
					'playlist_video_count' => (int) ( $item['contentDetails']['itemCount'] ?? 0 ),
					'thumbnail_url'        => $thumb_url,
					'etag'                 => $item['etag'] ?? '',
				);
			}

			$page_token = $data['nextPageToken'] ?? null;

		} while ( $page_token );

		return $playlists;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Execute a GET request and return the decoded JSON body.
	 *
	 * Retries up to MAX_RETRIES times on transient failures (429, 5xx, network errors)
	 * with exponential back-off (1 s, 2 s, 4 s). Permanent errors (400, 401, 403, 404)
	 * are returned immediately without retrying.
	 *
	 * @param string $url Full URL to request.
	 * @return array|WP_Error Decoded response array or WP_Error on failure.
	 */
	private function request( string $url ): array|\WP_Error {
		$last_error = null;

		for ( $attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				sleep( 2 ** ( $attempt - 1 ) ); // 1 s, 2 s, 4 s
			}

			$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

			if ( is_wp_error( $response ) ) {
				$last_error = new \WP_Error(
					'youtube_network_error',
					'Could not reach YouTube. This is usually temporary — the next scheduled sync will try again. (' . $response->get_error_message() . ')'
				);
				continue; // Network errors are always transient — retry.
			}

			$status  = wp_remote_retrieve_response_code( $response );
			$body    = wp_remote_retrieve_body( $response );
			$decoded = json_decode( $body, true );

			if ( 200 === $status ) {
				if ( ! is_array( $decoded ) ) {
					return new \WP_Error(
						'youtube_api_json_error',
						'YouTube returned an unexpected response. This is usually temporary — the next scheduled sync will try again.'
					);
				}
				return $decoded;
			}

			$api_message = $decoded['error']['message'] ?? '';
			$reason      = $decoded['error']['errors'][0]['reason'] ?? '';
			$message     = $this->friendly_error_message( $status, $reason, $api_message );
			$last_error  = new \WP_Error( 'youtube_api_error', $message, array( 'status' => $status, 'reason' => $reason ) );

			// Only retry on transient status codes.
			if ( ! in_array( $status, self::TRANSIENT_STATUSES, true ) ) {
				return $last_error;
			}
		}

		return $last_error ?? new \WP_Error( 'youtube_api_error', 'YouTube API request failed after retrying.' );
	}

	/**
	 * Return a plain-language error message based on HTTP status and API reason code.
	 *
	 * Distinguishes between errors the user can fix (bad API key, wrong ID) and
	 * temporary ones that will likely resolve on the next sync attempt.
	 *
	 * @param int    $status     HTTP status code.
	 * @param string $reason     YouTube API reason code (e.g. 'quotaExceeded').
	 * @param string $api_message Raw message from the API response.
	 * @return string Human-readable error message.
	 */
	private function friendly_error_message( int $status, string $reason, string $api_message ): string {
		// Quota exceeded — permanent until midnight Pacific, but not a config error.
		if ( in_array( $reason, array( 'quotaExceeded', 'dailyLimitExceeded' ), true ) ) {
			return 'YouTube API daily quota exceeded. Syncing will automatically resume on the next scheduled run tomorrow.';
		}

		switch ( $status ) {
			case 400:
				return 'YouTube rejected the request. This may indicate an invalid Channel ID or Playlist ID — please double-check your settings.';

			case 401:
				return 'YouTube API authentication failed. Please check your API key in WPBuoy Video Sync → Settings.';

			case 403:
				if ( in_array( $reason, array( 'keyInvalid', 'accessNotConfigured', 'forbidden' ), true ) ) {
					return 'YouTube API access denied. Your API key may be invalid or the YouTube Data API v3 may not be enabled for it. Check WPBuoy Video Sync → Settings.';
				}
				return 'YouTube API returned a "Forbidden" error. Check your API key in WPBuoy Video Sync → Settings.';

			case 404:
				return 'YouTube could not find the requested channel or playlist. Please verify your Channel ID or Playlist ID.';

			case 429:
				return 'Too many requests sent to YouTube. This is temporary — the next scheduled sync will try again.';

			case 500:
			case 502:
			case 503:
			case 504:
				return "YouTube's servers are temporarily unavailable (HTTP {$status}). This is temporary — the next scheduled sync will try again.";

			default:
				$detail = $api_message ?: "HTTP {$status}";
				return "YouTube API returned an unexpected error ({$detail}). The next scheduled sync will try again.";
		}
	}

	/**
	 * Convert an ISO 8601 duration string to total seconds.
	 *
	 * Examples: PT3M33S → 213, PT1H2M3S → 3723, PT30S → 30.
	 *
	 * @param string $duration ISO 8601 duration (e.g. PT1H2M3S).
	 * @return int Total seconds.
	 */
	private function iso8601_to_seconds( string $duration ): int {
		preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches );

		$hours   = isset( $matches[1] ) ? (int) $matches[1] : 0;
		$minutes = isset( $matches[2] ) ? (int) $matches[2] : 0;
		$seconds = isset( $matches[3] ) ? (int) $matches[3] : 0;

		return ( $hours * 3600 ) + ( $minutes * 60 ) + $seconds;
	}

	/**
	 * Build an API URL with the api_key appended.
	 *
	 * @param string $endpoint API endpoint name (e.g. 'videos', 'channels').
	 * @param array  $params   Query parameters (key => value).
	 * @return string Full URL.
	 */
	private function api_url( string $endpoint, array $params ): string {
		$params['key'] = $this->api_key;
		return self::BASE_URL . $endpoint . '?' . http_build_query( $params );
	}
}
