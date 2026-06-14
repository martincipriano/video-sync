<?php
declare(strict_types=1);
/**
 * Plugin Name: WPBuoy Video Sync
 * Plugin URI: https://wpbuoy.com/product/video-sync
 * Description: Sync YouTube videos, playlists, and channels from a single channel into WordPress posts with metadata and thumbnails.
 * Version: 2.2.2
 * Author: Martin Cipriano
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpbuoy-video-sync
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 7.0
 *
 * @package WPBuoyVideoSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WPBUOY_VIDEO_SYNC_VERSION', '2.2.2' );
define( 'WPBUOY_VIDEO_SYNC_PLUGIN_FILE', __FILE__ );
define( 'WPBUOY_VIDEO_SYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBUOY_VIDEO_SYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBUOY_VIDEO_SYNC', true );

/**
 * Load a template part into a plugin template.
 *
 * Makes it easy for a plugin to reuse sections of code and
 * an easy way to separate concerns.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default null.
 * @param array  $args Optional. Additional arguments passed to the template. Default empty array.
 * @return string|bool The template path if found, false otherwise.
 */
function wpbuoy_video_sync_get_template_part( $slug, $name = null, $args = array() ) {
	$templates  = array();
	$plugin_dir = plugin_dir_path( __FILE__ );

	// Build template file names.
	if ( isset( $name ) ) {
		$templates[] = "{$slug}-{$name}.php";
	}
	$templates[] = "{$slug}.php";

	// Try to locate the template.
	$located = false;
	foreach ( $templates as $template ) {
		$template_path = $plugin_dir . 'template-parts/' . $template;

		if ( file_exists( $template_path ) ) {
			$located = $template_path;
			break;
		}
	}

	// Load the template if found.
	if ( $located ) {
		// Extract args to make them available as variables in the template.
		if ( is_array( $args ) && ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Necessary for template variable extraction.
			extract( $args, EXTR_SKIP );
		}

		// Include the template file.
		include $located;
	}

	return $located;
}

/**
 * Load a template part and return its output as a string.
 *
 * Uses output buffering to capture the template output instead of echoing it directly.
 * Utilizes wpbuoy_video_sync_get_template_part() internally to avoid code duplication.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default null.
 * @param array  $args Optional. Additional arguments passed to the template. Default empty array.
 * @return string The template output as a string, or empty string if template not found.
 */
function wpbuoy_video_sync_return_template_part( $slug, $name = null, $args = array() ) {
	// Start output buffering.
	ob_start();

	// Use the existing get_template_part function.
	wpbuoy_video_sync_get_template_part( $slug, $name, $args );

	// Get the buffered content and clean the buffer.
	return ob_get_clean();
}

/**
 * Plugin activation — flag that cron events need rescheduling.
 *
 * Taxonomies are not registered during the activation hook, so the actual
 * rescheduling is deferred to init priority 20 on the next page load.
 */
register_activation_hook( WPBUOY_VIDEO_SYNC_PLUGIN_FILE, function () {
	update_option( 'wpbuoy_video_sync_reschedule_on_activation', true );
	flush_rewrite_rules();
} );

/**
 * Plugin deactivation — remove scheduled events and clear any stale syncing locks.
 */
register_deactivation_hook( WPBUOY_VIDEO_SYNC_PLUGIN_FILE, function () {
	wp_unschedule_hook( 'wpbuoy_video_sync_channel_config_sync_rule' );
} );

/**
 * Stand down if WPBuoy Video Sync Pro is active.
 *
 * Pro is a superset of the free plugin and owns all of the shared runtime
 * surface: the wpbuoy_video_sync_* options, post meta, cron hooks, AJAX actions, admin
 * menus, and the bundled license client. Bailing here — at plugin load, in every
 * request context (admin, front-end, and WP-Cron) — guarantees the two never
 * register the same hooks or run the same sync simultaneously. Activation and
 * deactivation hooks above stay registered so toggling either plugin stays clean.
 *
 * Shared option/meta keys are intentional: they give a seamless free -> pro
 * upgrade (channels, rules, API key, and synced posts carry over).
 */
if (
	in_array( 'wpbuoy-video-sync-pro/wpbuoy-video-sync-pro.php', (array) get_option( 'active_plugins', array() ), true )
	|| ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )['wpbuoy-video-sync-pro/wpbuoy-video-sync-pro.php'] ) )
) {
	return;
}

// Load plugin files.
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/functions.php';
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/settings.php';
// Load sync engine.
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/class-youtube-api.php';
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/class-video-importer.php';
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/class-sync-runner.php';
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/class-sync-history.php';
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/class-sync-scheduler.php';
require_once WPBUOY_VIDEO_SYNC_PLUGIN_DIR . 'includes/class-channels-page.php';

/**
 * Instantiate the sync engine.
 *
 * Priority 5 — before Channel and Playlist constructors which hook into
 * 'init' at default priority 10. This ensures the scheduler is ready to
 * attach its priority-20 hooks when the taxonomy save hooks fire.
 */
add_action(
	'init',
	function () {
		$api      = new \WPBuoyVideoSync\YouTube_API( get_option( 'wpbuoy_video_sync_api_key', '' ) );
		$importer = new \WPBuoyVideoSync\Video_Importer();
		$runner   = new \WPBuoyVideoSync\Sync_Runner( $api, $importer );
		new \WPBuoyVideoSync\Sync_Scheduler( $runner );
	},
	5
);

/**
 * Add video metabox to WPBuoy Video Sync Videos post type.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_wpbuoy_video_sync_video_id', true ) ) {
		return;
	}
	add_meta_box(
		'wpbuoy_video_sync_video_details',
		__( 'WPBuoy Video Sync Video Details', 'wpbuoy-video-sync' ),
		'wpbuoy_video_sync_render_video_metabox',
		$post_type,
		'normal',
		'high'
	);
}, 10, 2 );

/**
 * Render the video metabox with a tabbed interface.
 *
 * Tabs: Details | YouTube Data | Thumbnails | Sync Status
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function wpbuoy_video_sync_render_video_metabox( $post ) {
	$thumbnails = get_post_meta( $post->ID, '_wpbuoy_video_sync_thumbnails', true );
	$thumbnails = is_array( $thumbnails ) ? $thumbnails : array();

	wpbuoy_video_sync_get_template_part( 'metabox', 'video', array(
		'post_id'               => $post->ID,
		'video_id'              => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_video_id', true ),
		'video_url'             => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_video_url', true ),
		'channel_id'            => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_id', true ),
		'original_title'        => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_original_title', true ),
		'original_description'  => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_original_description', true ),
		'channel_title'         => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_title', true ),
		'published_date'        => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_published_at', true ),
		'duration_seconds'      => get_post_meta( $post->ID, '_wpbuoy_video_sync_duration_seconds', true ),
		'view_count'            => get_post_meta( $post->ID, '_wpbuoy_video_sync_view_count', true ),
		'like_count'            => get_post_meta( $post->ID, '_wpbuoy_video_sync_like_count', true ),
		'comment_count'         => get_post_meta( $post->ID, '_wpbuoy_video_sync_comment_count', true ),
		'sync_source_type'      => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_source_type', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_wpbuoy_video_sync_last_synced', true ),
		'thumbnails'            => $thumbnails,
		'thumbnail_size_labels' => array(
			'maxres'   => 'Max Res (1280×720)',
			'standard' => 'Standard (640×480)',
			'high'     => 'High (480×360)',
			'medium'   => 'Medium (320×180)',
			'default'  => 'Default (120×90)',
		),
		'preview_thumb'         => \WPBuoyVideoSync\Video_Importer::get_best_thumbnail( $thumbnails ),
	) );
}

/**
 * Fall back to the YouTube thumbnail URL when no featured image is set.
 *
 * Only applies to posts that have WPBuoy Video Sync thumbnail meta. If the user has
 * explicitly set a featured image, that takes precedence and this filter is a no-op.
 *
 * @param string     $html             Current featured image HTML.
 * @param int        $post_id          Post ID.
 * @param int        $post_thumbnail_id Attachment ID of the set thumbnail (0 if none).
 * @param string|int[] $size           Requested image size.
 * @param string|array $attr           Additional HTML attributes.
 * @return string HTML img tag, or original $html.
 */
function wpbuoy_video_sync_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
	if ( $post_thumbnail_id ) {
		return $html;
	}

	$thumbnails = get_post_meta( $post_id, '_wpbuoy_video_sync_thumbnails', true );

	if ( ! is_array( $thumbnails ) || empty( $thumbnails ) ) {
		return $html;
	}

	$thumb = \WPBuoyVideoSync\Video_Importer::get_best_thumbnail( $thumbnails );

	if ( ! $thumb ) {
		return $html;
	}

	$width  = ! empty( $thumb['width'] ) ? ' width="' . (int) $thumb['width'] . '"' : '';
	$height = ! empty( $thumb['height'] ) ? ' height="' . (int) $thumb['height'] . '"' : '';
	$alt    = esc_attr( get_the_title( $post_id ) );

	return '<img src="' . esc_url( $thumb['url'] ) . '"' . $width . $height . ' alt="' . $alt . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image">';
}
add_filter( 'post_thumbnail_html', 'wpbuoy_video_sync_post_thumbnail_html', 10, 5 );

/**
 * Show the YouTube thumbnail in the featured image metabox when none is set.
 *
 * The preview image triggers the existing "Set featured image" link on click
 * so the user can still upload their own image. A small label clarifies it is
 * the YouTube thumbnail.
 *
 * @param string   $content      Current featured image metabox HTML.
 * @param int      $post_id      Post ID.
 * @param int|null $thumbnail_id Attachment ID of the set thumbnail, or null if none.
 * @return string Modified HTML.
 */
function wpbuoy_video_sync_admin_post_thumbnail_html( string $content, int $post_id, $thumbnail_id ): string {
	if ( $thumbnail_id ) {
		return $content;
	}

	$thumbnails = get_post_meta( $post_id, '_wpbuoy_video_sync_thumbnails', true );

	if ( ! is_array( $thumbnails ) || empty( $thumbnails ) ) {
		return $content;
	}

	$thumb = \WPBuoyVideoSync\Video_Importer::get_best_thumbnail( $thumbnails );

	if ( ! $thumb ) {
		return $content;
	}

	$preview = '<img'
		. ' src="' . esc_url( $thumb['url'] ) . '"'
		. ' onclick="document.getElementById(\'set-post-thumbnail\').click()"'
		. ' title="' . esc_attr__( 'Click to set a custom featured image', 'wpbuoy-video-sync') . '"'
		. ' alt=""'
		. '>';
	$label   = '<p class="hide-if-no-js howto" id="set-post-thumbnail-desc">'
		. esc_html__( 'Click the YouTube thumbnail to edit or update', 'wpbuoy-video-sync')
		. '</p>';

	return $preview . $label . $content;
}
add_filter( 'admin_post_thumbnail_html', 'wpbuoy_video_sync_admin_post_thumbnail_html', 10, 3 );

/**
 * Add playlist metabox to posts that were synced from a YouTube playlist.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_wpbuoy_video_sync_playlist_id', true ) ) {
		return;
	}
	add_meta_box(
		'wpbuoy_video_sync_playlist_details',
		__( 'WPBuoy Video Sync Playlist Details', 'wpbuoy-video-sync' ),
		'wpbuoy_video_sync_render_playlist_metabox',
		$post_type,
		'normal',
		'high'
	);
}, 10, 2 );

/**
 * Render the playlist metabox.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function wpbuoy_video_sync_render_playlist_metabox( $post ) {
	wpbuoy_video_sync_get_template_part( 'metabox', 'playlist', array(
		'post_id'               => $post->ID,
		'playlist_id'           => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_playlist_id', true ),
		'channel_id'            => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_id', true ),
		'playlist_title'        => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_playlist_title', true ),
		'playlist_description'  => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_playlist_description', true ),
		'playlist_video_count'  => get_post_meta( $post->ID, '_wpbuoy_video_sync_playlist_video_count', true ),
		'playlist_thumbnail'    => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_playlist_thumbnail', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_wpbuoy_video_sync_last_synced', true ),
	) );
}

/**
 * Add channel metabox to posts that were synced as a YouTube channel.
 *
 * Uses _wpbuoy_video_sync_channel_post (not _wpbuoy_video_sync_channel_id) so that video/playlist posts
 * that store the source channel ID do not get a Channel Details metabox.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_post', true ) ) {
		return;
	}
	add_meta_box(
		'wpbuoy_video_sync_channel_details',
		__( 'WPBuoy Video Sync Channel Details', 'wpbuoy-video-sync' ),
		'wpbuoy_video_sync_render_channel_metabox',
		$post_type,
		'normal',
		'high'
	);
}, 10, 2 );

/**
 * Render the channel metabox.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function wpbuoy_video_sync_render_channel_metabox( $post ) {
	wpbuoy_video_sync_get_template_part( 'metabox', 'channel', array(
		'post_id'               => $post->ID,
		'channel_id'            => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_post', true ),
		'channel_title'         => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_title', true ),
		'channel_description'   => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_description', true ),
		'subscriber_count'      => get_post_meta( $post->ID, '_wpbuoy_video_sync_subscriber_count', true ),
		'video_count'           => get_post_meta( $post->ID, '_wpbuoy_video_sync_channel_video_count', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_wpbuoy_video_sync_last_synced', true ),
		'profile_picture'       => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_profile_picture', true ),
		'banner_image'          => (string) get_post_meta( $post->ID, '_wpbuoy_video_sync_banner_image', true ),
	) );
}

/**
 * Enqueue assets on post edit screens where the post has WPBuoy Video Sync video meta.
 *
 * @return void
 */
function wpbuoy_video_sync_enqueue_video_list_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_wpbuoy_video_sync_meta = $post_id && (
		get_post_meta( $post_id, '_wpbuoy_video_sync_video_id', true ) ||
		get_post_meta( $post_id, '_wpbuoy_video_sync_playlist_id', true ) ||
		get_post_meta( $post_id, '_wpbuoy_video_sync_channel_post', true )
	);
	if ( ! $has_wpbuoy_video_sync_meta ) {
		return;
	}

	wp_enqueue_style(
		'yousync-admin',
		WPBUOY_VIDEO_SYNC_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		WPBUOY_VIDEO_SYNC_VERSION
	);
	wp_enqueue_script(
		'yousync-metabox',
		WPBUOY_VIDEO_SYNC_PLUGIN_URL . 'assets/js/metabox.js',
		array(),
		WPBUOY_VIDEO_SYNC_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'wpbuoy_video_sync_enqueue_video_list_assets' );

/**
 * One-time migration: convert _wpbuoy_video_sync_video JSON blob to individual flat meta keys.
 *
 * Runs on admin_init, gated by the wpbuoy_video_sync_flat_meta_migrated option.
 * Processes posts in batches of 50 per request until all legacy blobs are migrated.
 */
add_action( 'admin_init', function (): void {
	if ( get_option( 'wpbuoy_video_sync_flat_meta_migrated' ) ) {
		return;
	}

	$query = new WP_Query( array(
		'post_type'      => 'any',
		'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Lookup by the _wpbuoy_video_sync_* ID meta is required to match a YouTube item; there is no non-meta alternative.
		'meta_query'     => array(
			array(
				'key'     => '_wpbuoy_video_sync_video',
				'compare' => 'EXISTS',
			),
		),
	) );

	foreach ( $query->posts as $post_id ) {
		$post_id = (int) $post_id;
		$raw     = get_post_meta( $post_id, '_wpbuoy_video_sync_video', true );
		$data    = is_string( $raw ) ? ( json_decode( $raw, true ) ?: array() ) : array();

		if ( empty( $data ) ) {
			delete_post_meta( $post_id, '_wpbuoy_video_sync_video' );
			continue;
		}

		$video_id = $data['video_id'] ?? '';

		update_post_meta( $post_id, '_wpbuoy_video_sync_video_id',             $video_id );
		update_post_meta( $post_id, '_wpbuoy_video_sync_video_url',            $video_id ? 'https://www.youtube.com/watch?v=' . $video_id : '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_channel_id',           $data['channel_id'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_channel_title',        $data['channel_title'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_etag',                 $data['etag'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_source_type',          $data['sync_source_type'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_source_id',            (int) ( $data['sync_source_id'] ?? 0 ) );
		update_post_meta( $post_id, '_wpbuoy_video_sync_original_title',       $data['original_title'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_original_description', $data['original_description'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_published_at',         $data['published_at'] ?? '' );
		update_post_meta( $post_id, '_wpbuoy_video_sync_duration_seconds',     (int) ( $data['duration_seconds'] ?? 0 ) );
		update_post_meta( $post_id, '_wpbuoy_video_sync_view_count',           (int) ( $data['view_count'] ?? 0 ) );
		update_post_meta( $post_id, '_wpbuoy_video_sync_like_count',           (int) ( $data['like_count'] ?? 0 ) );
		update_post_meta( $post_id, '_wpbuoy_video_sync_comment_count',        (int) ( $data['comment_count'] ?? 0 ) );

		$thumbnails = array();
		foreach ( $data['thumbnails'] ?? array() as $size => $thumb ) {
			if ( ! empty( $thumb['url'] ) ) {
				$thumbnails[ $size ] = array(
					'url'    => $thumb['url'],
					'width'  => $thumb['width'] ?? 0,
					'height' => $thumb['height'] ?? 0,
				);
			}
		}
		update_post_meta( $post_id, '_wpbuoy_video_sync_thumbnails', $thumbnails );

		// Migrate last_modified → _wpbuoy_video_sync_last_synced (only if not already set).
		if ( '' === get_post_meta( $post_id, '_wpbuoy_video_sync_last_synced', true ) ) {
			update_post_meta( $post_id, '_wpbuoy_video_sync_last_synced', (int) ( $data['last_modified'] ?? 0 ) );
		}

		delete_post_meta( $post_id, '_wpbuoy_video_sync_video' );
	}

	if ( $query->found_posts <= 50 ) {
		update_option( 'wpbuoy_video_sync_flat_meta_migrated', true, false );
	}
} );