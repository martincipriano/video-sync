<?php
declare(strict_types=1);

namespace YouSyncPro;

/**
 * Registers Gutenberg blocks, the [yousync] shortcode, and the REST endpoint
 * used by the block editor post selector.
 *
 * @package YouSyncPro
 */
class Blocks {

	public function __construct() {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_shortcode( 'yousync', [ $this, 'render_shortcode' ] );
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ] );
	}

	public function enqueue_editor_assets(): void {
		wp_enqueue_style(
			'yousync-block-editor',
			YOUSYNC_PRO_PLUGIN_URL . 'blocks/editor.css',
			array( 'wp-block-editor' ),
			YOUSYNC_PRO_VERSION
		);
	}

	public function register_block_category( array $categories ): array {
		array_unshift( $categories, array(
			'slug'  => 'yousync',
			'title' => 'YouSync',
			'icon'  => null,
		) );
		return $categories;
	}

	// -----------------------------------------------------------------------
	// Block registration
	// -----------------------------------------------------------------------

	public function register_blocks(): void {
		$blocks = [
			'yousync-field' => [ $this, 'render_field_block' ],
			'yousync-image' => [ $this, 'render_image_block' ],
			'yousync-embed' => [ $this, 'render_embed_block' ],
		];

		foreach ( $blocks as $slug => $callback ) {
			register_block_type(
				YOUSYNC_PRO_PLUGIN_DIR . 'blocks/' . $slug,
				[ 'render_callback' => $callback ]
			);
		}
	}

	// -----------------------------------------------------------------------
	// Block render callbacks
	// -----------------------------------------------------------------------

	public function render_field_block( array $attributes ): string {
		$post_id = (int) ( $attributes['postId'] ?? 0 ) ?: (int) get_queried_object_id();
		$field   = sanitize_key( $attributes['field'] ?? '' );

		if ( ! $post_id || ! $field ) {
			return '';
		}

		return $this->render_field( $post_id, $field );
	}

	public function render_image_block( array $attributes ): string {
		$post_id = (int) ( $attributes['postId'] ?? 0 ) ?: (int) get_queried_object_id();
		$field   = sanitize_key( $attributes['field'] ?? 'thumbnail' );
		$size    = sanitize_key( $attributes['size'] ?? 'maxres' );

		if ( ! $post_id ) {
			return '';
		}

		if ( 'thumbnail' === $field ) {
			return $this->render_thumbnail( $post_id, $size );
		}

		if ( 'profile_photo' === $field ) {
			$url = (string) get_post_meta( $post_id, '_yousync_profile_picture', true );
			if ( ! $url ) {
				return '';
			}
			$alt = esc_attr( (string) get_post_meta( $post_id, '_yousync_channel_title', true ) );
			return '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" class="yousync-profile-photo">';
		}

		if ( 'banner_image' === $field ) {
			$url = (string) get_post_meta( $post_id, '_yousync_banner_image', true );
			if ( ! $url ) {
				return '';
			}
			return '<img src="' . esc_url( $url ) . '" alt="" class="yousync-banner-image">';
		}

		return '';
	}

	public function render_embed_block( array $attributes ): string {
		$post_id = (int) ( $attributes['postId'] ?? 0 ) ?: (int) get_queried_object_id();

		if ( ! $post_id ) {
			return '';
		}

		return $this->render_embed( $post_id );
	}

	// -----------------------------------------------------------------------
	// Shortcode
	// -----------------------------------------------------------------------

	/**
	 * [yousync id="123" field="title"]
	 * [yousync id="123" field="thumbnail" size="maxres"]
	 * [yousync id="123" field="profile_photo"]
	 * [yousync id="123" field="banner_image"]
	 * [yousync id="123" type="embed"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'id'    => 0,
				'field' => '',
				'size'  => 'maxres',
				'type'  => '',
			],
			$atts,
			'yousync'
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

		if ( 'thumbnail' === $field ) {
			return $this->render_thumbnail( $post_id, $size );
		}

		if ( 'profile_photo' === $field ) {
			$url = (string) get_post_meta( $post_id, '_yousync_profile_picture', true );
			if ( ! $url ) {
				return '';
			}
			$alt = esc_attr( (string) get_post_meta( $post_id, '_yousync_channel_title', true ) );
			return '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" class="yousync-profile-photo">';
		}

		if ( 'banner_image' === $field ) {
			$url = (string) get_post_meta( $post_id, '_yousync_banner_image', true );
			if ( ! $url ) {
				return '';
			}
			return '<img src="' . esc_url( $url ) . '" alt="" class="yousync-banner-image">';
		}

		return $this->render_field( $post_id, $field );
	}

	// -----------------------------------------------------------------------
	// REST endpoint — post selector for block editor
	// -----------------------------------------------------------------------

	public function register_rest_routes(): void {
		register_rest_route(
			'yousync/v1',
			'/posts',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_posts' ],
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'args'                => [
					'type' => [
						'type'              => 'string',
						'enum'              => [ 'video', 'channel' ],
						'sanitize_callback' => 'sanitize_key',
						'required'          => true,
					],
				],
			]
		);
	}

	public function rest_get_posts( \WP_REST_Request $request ): \WP_REST_Response {
		$type     = $request->get_param( 'type' );
		$meta_key = 'video' === $type ? '_yousync_video_id' : '_yousync_channel_post';

		$posts = get_posts( [
			'post_type'      => 'any',
			'post_status'    => [ 'publish', 'draft', 'private' ],
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => $meta_key, 'compare' => 'EXISTS' ] ],
		] );

		$data = array_map(
			function ( $id ) {
				$title = get_the_title( $id );
				/* translators: %d: post ID */
				return [ 'id' => $id, 'title' => $title ?: sprintf( __( 'Post #%d', 'yousync-pro' ), $id ) ];
			},
			$posts
		);

		return new \WP_REST_Response( array_values( $data ) );
	}

	// -----------------------------------------------------------------------
	// Shared render helpers
	// -----------------------------------------------------------------------

	private function render_field( int $post_id, string $field ): string {
		$field_map = [
			'video_id'            => '_yousync_video_id',
			'video_url'           => '_yousync_video_url',
			'embed_code'          => '_yousync_video_id',
			'channel_id'          => '_yousync_channel_id',
			'title'               => '_yousync_original_title',
			'description'         => '_yousync_original_description',
			'channel'             => '_yousync_channel_title',
			'published_date'      => '_yousync_published_at',
			'duration'            => '_yousync_duration_seconds',
			'view_count'          => '_yousync_view_count',
			'like_count'          => '_yousync_like_count',
			'comment_count'       => '_yousync_comment_count',
			'channel_title'       => '_yousync_channel_title',
			'channel_description' => '_yousync_channel_description',
			'subscriber_count'    => '_yousync_subscriber_count',
			'video_count'         => '_yousync_channel_video_count',
			'playlist_video_count' => '_yousync_playlist_video_count',
		];

		if ( ! isset( $field_map[ $field ] ) ) {
			return '';
		}

		if ( 'embed_code' === $field ) {
			return $this->render_embed( $post_id );
		}

		$value = (string) get_post_meta( $post_id, $field_map[ $field ], true );

		if ( 'duration' === $field ) {
			$secs = (int) $value;
			$h    = (int) floor( $secs / 3600 );
			$m    = (int) floor( ( $secs % 3600 ) / 60 );
			$s    = $secs % 60;
			return esc_html( $h > 0 ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s ) );
		}

		if ( in_array( $field, [ 'view_count', 'like_count', 'comment_count', 'subscriber_count', 'video_count' ], true ) ) {
			return esc_html( number_format( (int) $value ) );
		}

		return esc_html( $value );
	}

	private function render_embed( int $post_id ): string {
		$video_id = (string) get_post_meta( $post_id, '_yousync_video_id', true );

		if ( ! $video_id ) {
			return '';
		}

		return '<div class="yousync-embed">'
			. '<iframe width="560" height="315"'
			. ' src="' . esc_url( 'https://www.youtube.com/embed/' . $video_id ) . '"'
			. ' frameborder="0" allowfullscreen loading="lazy">'
			. '</iframe>'
			. '</div>';
	}

	private function render_thumbnail( int $post_id, string $size ): string {
		$thumbnails = get_post_meta( $post_id, '_yousync_thumbnails', true );

		if ( ! is_array( $thumbnails ) || empty( $thumbnails[ $size ] ) ) {
			return '';
		}

		$thumb = $thumbnails[ $size ];
		$w     = ! empty( $thumb['width'] )  ? ' width="' . (int) $thumb['width'] . '"'  : '';
		$h     = ! empty( $thumb['height'] ) ? ' height="' . (int) $thumb['height'] . '"' : '';
		$class = 'yousync-thumbnail yousync-thumbnail--' . esc_attr( $size );

		return '<img src="' . esc_url( $thumb['url'] ) . '"' . $w . $h
			. ' alt="' . esc_attr( get_the_title( $post_id ) ) . '"'
			. ' class="' . $class . '">';
	}
}
