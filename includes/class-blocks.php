<?php
declare(strict_types=1);
/**
 * Registers Gutenberg blocks, the [video-sync] shortcode, and the REST endpoint
 * used by the block editor post selector.
 *
 * @package WPBuoy_Video_Sync
 */

namespace WPBuoy_Video_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocks
 */
class Blocks {

	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_shortcode( 'video-sync', array( $this, 'render_shortcode' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
	}

	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'wby-video-sync-block-editor',
			WPBYVS_PLUGIN_URL . 'blocks/editor.css',
			array( 'wp-block-editor' ),
			WPBYVS_VERSION
		);
	}

	public function register_block_category( array $categories ): array {
		array_unshift( $categories, array(
			'slug'  => 'wby-video-sync',
			'title' => __( 'Video Sync', 'wby-video-sync' ),
			'icon'  => null,
		) );
		return $categories;
	}

	// -------------------------------------------------------------------------
	// Block registration
	// -------------------------------------------------------------------------

	public function register_blocks(): void {
		$blocks = array(
			'field' => array( $this, 'render_field_block' ),
			'image' => array( $this, 'render_image_block' ),
			'embed' => array( $this, 'render_embed_block' ),
		);

		foreach ( $blocks as $slug => $callback ) {
			register_block_type(
				WPBYVS_PLUGIN_DIR . 'blocks/' . $slug,
				array( 'render_callback' => $callback )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Block render callbacks
	// -------------------------------------------------------------------------

	public function render_field_block( array $attributes ): string {
		$post_id = (int) ( $attributes['postId'] ?? 0 ) ?: (int) get_queried_object_id();
		$field   = sanitize_key( $attributes['field'] ?? '' );

		if ( ! $post_id || ! $field ) {
			return '';
		}

		$value = $this->render_field( $post_id, $field );

		if ( '' === $value ) {
			return '';
		}

		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $value );
	}

	public function render_image_block( array $attributes ): string {
		$post_id = (int) ( $attributes['postId'] ?? 0 ) ?: (int) get_queried_object_id();
		$field   = sanitize_key( $attributes['field'] ?? 'thumbnail' );
		$size    = sanitize_key( $attributes['size'] ?? 'maxres' );

		if ( ! $post_id ) {
			return '';
		}

		$html = $this->render_image( $post_id, $field, $size );

		if ( '' === $html ) {
			return '';
		}

		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	public function render_embed_block( array $attributes ): string {
		$post_id = (int) ( $attributes['postId'] ?? 0 ) ?: (int) get_queried_object_id();

		if ( ! $post_id ) {
			return '';
		}

		// The live YouTube iframe cannot load inside the block editor's sandboxed
		// canvas (YouTube returns error 153 for the missing referrer), so the editor
		// preview renders the thumbnail with a play overlay instead.
		$html = ! empty( $attributes['isEditorPreview'] )
			? $this->render_embed_preview( $post_id )
			: $this->render_embed( $post_id );

		if ( '' === $html ) {
			return '';
		}

		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	/**
	 * [video-sync id="123" field="title"]
	 * [video-sync id="123" field="thumbnail" size="maxres"]
	 * [video-sync id="123" field="profile_photo"]
	 * [video-sync id="123" field="banner_image"]
	 * [video-sync id="123" type="embed"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'field' => '',
				'size'  => 'maxres',
				'type'  => '',
			),
			$atts,
			'video-sync'
		);

		$post_id = (int) $atts['id'];
		$field   = sanitize_key( $atts['field'] );
		$size    = sanitize_key( $atts['size'] );
		$type    = sanitize_key( $atts['type'] );

		if ( ! $post_id ) {
			return '';
		}

		if ( 'embed' === $type ) {
			return $this->render_embed( $post_id );
		}

		if ( in_array( $field, array( 'thumbnail', 'playlist_thumbnail', 'profile_photo', 'banner_image' ), true ) ) {
			return $this->render_image( $post_id, $field, $size );
		}

		return $this->render_field( $post_id, $field );
	}

	// -------------------------------------------------------------------------
	// REST endpoint — post selector for block editor
	// -------------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route(
			'wby-video-sync/v1',
			'/posts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_posts' ),
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'args'                => array(
					'type' => array(
						'type'              => 'string',
						'enum'              => array( 'video', 'playlist', 'channel' ),
						'sanitize_callback' => 'sanitize_key',
						'required'          => true,
					),
				),
			)
		);
	}

	public function rest_get_posts( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$type      = $request->get_param( 'type' );
		$meta_keys = array(
			'video'    => '_wpbyvs_video_id',
			'playlist' => '_wpbyvs_playlist_id',
			'channel'  => '_wpbyvs_channel_post',
		);
		$meta_key  = $meta_keys[ $type ] ?? '_wpbyvs_video_id';
		$statuses  = array( 'publish', 'draft', 'private' );

		// Direct, indexed lookup by meta_key — avoids the meta_query overhead
		// of get_posts() for what is just an "EXISTS" check, same approach as
		// Video_Importer::get_imported_video_ids().
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_status IN ( " . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . " )
				 LIMIT 200",
				array_merge( array( $meta_key ), $statuses )
			)
		);

		$data = array_map(
			function ( $row ) {
				/* translators: %d: post ID */
				return array( 'id' => (int) $row->ID, 'title' => $row->post_title ?: sprintf( __( 'Post #%d', 'wby-video-sync' ), $row->ID ) );
			},
			$ids
		);

		return new \WP_REST_Response( array_values( $data ) );
	}

	// -------------------------------------------------------------------------
	// Shared render helpers
	// -------------------------------------------------------------------------

	/**
	 * Identify what kind of synced post this is, from its dedup meta key.
	 *
	 * @param int $post_id Post ID.
	 * @return string 'video', 'playlist', 'channel', or '' if not a synced post.
	 */
	private function post_kind( int $post_id ): string {
		if ( get_post_meta( $post_id, '_wpbyvs_video_id', true ) ) {
			return 'video';
		}
		if ( get_post_meta( $post_id, '_wpbyvs_playlist_id', true ) ) {
			return 'playlist';
		}
		if ( get_post_meta( $post_id, '_wpbyvs_channel_post', true ) ) {
			return 'channel';
		}
		return '';
	}

	/**
	 * Resolve a field name to its meta key for the given post kind.
	 *
	 * 'title' and 'description' are shared field names that resolve to a
	 * different meta key per post kind, since video, playlist, and channel
	 * posts each store those under their own prefixed meta key.
	 *
	 * @param string $kind  'video', 'playlist', or 'channel'.
	 * @param string $field Requested field name.
	 * @return string Meta key, or '' if the field doesn't apply to this kind.
	 */
	private function field_meta_key( string $kind, string $field ): string {
		$shared = array(
			'title'       => array( 'video' => '_wpbyvs_original_title', 'playlist' => '_wpbyvs_playlist_title', 'channel' => '_wpbyvs_channel_title' ),
			'description' => array( 'video' => '_wpbyvs_original_description', 'playlist' => '_wpbyvs_playlist_description', 'channel' => '_wpbyvs_channel_description' ),
		);
		if ( isset( $shared[ $field ][ $kind ] ) ) {
			return $shared[ $field ][ $kind ];
		}

		$by_kind = array(
			'video' => array(
				'video_id'      => '_wpbyvs_video_id',
				'video_url'     => '_wpbyvs_video_url',
				'channel_id'    => '_wpbyvs_channel_id',
				'channel'       => '_wpbyvs_channel_title',
				'published_date' => '_wpbyvs_published_at',
				'duration'      => '_wpbyvs_duration_seconds',
				'view_count'    => '_wpbyvs_view_count',
				'like_count'    => '_wpbyvs_like_count',
				'comment_count' => '_wpbyvs_comment_count',
			),
			'playlist' => array(
				'playlist_id'          => '_wpbyvs_playlist_id',
				'channel_id'           => '_wpbyvs_channel_id',
				'playlist_video_count' => '_wpbyvs_playlist_video_count',
			),
			'channel' => array(
				'channel_id'       => '_wpbyvs_channel_id',
				'subscriber_count' => '_wpbyvs_subscriber_count',
				'video_count'      => '_wpbyvs_channel_video_count',
			),
		);

		return $by_kind[ $kind ][ $field ] ?? '';
	}

	private function render_field( int $post_id, string $field ): string {
		$kind = $this->post_kind( $post_id );
		if ( '' === $kind ) {
			return '';
		}

		if ( 'embed_code' === $field ) {
			return $this->render_embed( $post_id );
		}

		$meta_key = $this->field_meta_key( $kind, $field );
		if ( '' === $meta_key ) {
			return '';
		}

		$value = (string) get_post_meta( $post_id, $meta_key, true );

		if ( 'duration' === $field ) {
			$secs = (int) $value;
			$h    = (int) floor( $secs / 3600 );
			$m    = (int) floor( ( $secs % 3600 ) / 60 );
			$s    = $secs % 60;
			return esc_html( $h > 0 ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s ) );
		}

		if ( in_array( $field, array( 'view_count', 'like_count', 'comment_count', 'subscriber_count', 'video_count', 'playlist_video_count' ), true ) ) {
			return esc_html( number_format( (int) $value ) );
		}

		return esc_html( $value );
	}

	private function render_image( int $post_id, string $field, string $size ): string {
		if ( 'thumbnail' === $field ) {
			return $this->render_thumbnail( $post_id, $size );
		}

		if ( 'playlist_thumbnail' === $field ) {
			$url = (string) get_post_meta( $post_id, '_wpbyvs_playlist_thumbnail', true );
			if ( ! $url ) {
				return '';
			}
			return '<img src="' . esc_url( $url ) . '" alt="" class="wpbyvs-playlist-thumbnail">';
		}

		if ( 'profile_photo' === $field ) {
			$url = (string) get_post_meta( $post_id, '_wpbyvs_profile_picture', true );
			if ( ! $url ) {
				return '';
			}
			$alt = esc_attr( (string) get_post_meta( $post_id, '_wpbyvs_channel_title', true ) );
			return '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" class="wby-video-sync-profile-photo">';
		}

		if ( 'banner_image' === $field ) {
			$url = (string) get_post_meta( $post_id, '_wpbyvs_banner_image', true );
			if ( ! $url ) {
				return '';
			}
			return '<img src="' . esc_url( $url ) . '" alt="" class="wpbyvs-banner-image">';
		}

		return '';
	}

	private function render_embed( int $post_id ): string {
		$video_id = (string) get_post_meta( $post_id, '_wpbyvs_video_id', true );

		if ( ! $video_id ) {
			return '';
		}

		// Fill the parent width and scale height via a 16:9 aspect ratio.
		return '<div class="wpbyvs-embed" style="width:100%;">'
			. '<iframe'
			. ' src="' . esc_url( 'https://www.youtube.com/embed/' . $video_id ) . '"'
			. ' style="display:block;width:100%;aspect-ratio:16/9;height:auto;border:0;"'
			. ' allowfullscreen loading="lazy">'
			. '</iframe>'
			. '</div>';
	}

	/**
	 * Editor-only static preview for the embed block.
	 *
	 * Renders the YouTube thumbnail with a play overlay. Styles are inlined so the
	 * preview renders correctly inside the editor's sandboxed canvas iframe, where
	 * plugin editor stylesheets are not guaranteed to load. The front end keeps the
	 * real player via render_embed().
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_embed_preview( int $post_id ): string {
		$video_id = (string) get_post_meta( $post_id, '_wpbyvs_video_id', true );

		if ( ! $video_id ) {
			return '';
		}

		// Reuse the thumbnail URL already stored by the sync (same source the
		// Image block and featured-image fallback use) instead of requesting a
		// new URL from a different YouTube host.
		$thumb = (string) get_post_meta( $post_id, '_wpbyvs_thumbnail_url', true );
		if ( ! $thumb ) {
			return '';
		}
		$alt = esc_attr( get_the_title( $post_id ) );

		return '<div class="wpbyvs-embed wpbyvs-embed--preview" style="position:relative;display:block;width:100%;line-height:0;">'
			. '<img src="' . esc_url( $thumb ) . '" alt="' . $alt . '" style="display:block;width:100%;height:auto;">'
			. '<span aria-hidden="true" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:flex;align-items:center;justify-content:center;width:68px;height:48px;background:rgba(0,0,0,0.75);border-radius:14%;">'
			. '<span style="border-style:solid;border-width:10px 0 10px 17px;border-color:transparent transparent transparent #fff;margin-left:3px;"></span>'
			. '</span>'
			. '</div>';
	}

	private function render_thumbnail( int $post_id, string $size ): string {
		$thumbnails = get_post_meta( $post_id, '_wpbyvs_thumbnails', true );

		if ( ! is_array( $thumbnails ) || empty( $thumbnails[ $size ] ) ) {
			return '';
		}

		$thumb = $thumbnails[ $size ];
		$w     = ! empty( $thumb['width'] )  ? ' width="' . (int) $thumb['width'] . '"'  : '';
		$h     = ! empty( $thumb['height'] ) ? ' height="' . (int) $thumb['height'] . '"' : '';
		$class = 'wpbyvs-thumbnail wpbyvs-thumbnail--' . esc_attr( $size );

		// width/height attributes preserve the intrinsic aspect ratio (no layout
		// shift); the inline style makes the thumbnail fill its container width.
		return '<img src="' . esc_url( $thumb['url'] ) . '"' . $w . $h
			. ' alt="' . esc_attr( get_the_title( $post_id ) ) . '"'
			. ' class="' . $class . '"'
			. ' style="width:100%;height:auto;display:block;">';
	}
}

new Blocks();
