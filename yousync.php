<?php
declare(strict_types=1);
/**
 * Plugin Name: YouSync
 * Plugin URI: https://wpbuoy.com/product/yousync/
 * Description: Sync YouTube videos, playlists, and channels from a single channel into WordPress posts with metadata and thumbnails.
 * Version: 2.0.0
 * Author: Martin Cipriano
 * Author URI: https://martincipriano.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: yousync
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.9
 *
 * @package YouSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'YOUSYNC_VERSION', '2.0.0' );
define( 'YOUSYNC_PLUGIN_FILE', __FILE__ );
define( 'YOUSYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YOUSYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'YOUSYNC', true );

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
function yousync_get_template_part( $slug, $name = null, $args = array() ) {
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
 * Utilizes yousync_get_template_part() internally to avoid code duplication.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template. Default null.
 * @param array  $args Optional. Additional arguments passed to the template. Default empty array.
 * @return string The template output as a string, or empty string if template not found.
 */
function yousync_return_template_part( $slug, $name = null, $args = array() ) {
	// Start output buffering.
	ob_start();

	// Use the existing get_template_part function.
	yousync_get_template_part( $slug, $name, $args );

	// Get the buffered content and clean the buffer.
	return ob_get_clean();
}

/**
 * Get the field type for a sync rule condition field.
 *
 * Used to determine which operators and value input to render.
 *
 * @param string $field The condition field name.
 * @return string Field type: 'text', 'number', or 'date'. Empty string if unknown.
 */
function yousync_get_condition_field_type( $field ) {
	$map = array(
		// Channel fields
		'channel_title'        => 'text',
		'channel_description'  => 'text',
		'subscriber_count'     => 'number',
		'video_count'          => 'number',
		// Playlist fields
		'playlist_title'       => 'text',
		'playlist_description' => 'text',
		'playlist_video_count' => 'number',
		// Video fields
		'video_id'             => 'text',
		'title'                => 'text',
		'description'          => 'text',
		'tags'                 => 'text',
		'duration'             => 'number',
		'published_date'       => 'date',
		'yousync_category'       => 'text',
		'view_count'           => 'number',
		'like_count'           => 'number',
		'comment_count'        => 'number',
	);
	return isset( $map[ $field ] ) ? $map[ $field ] : '';
}

/**
 * Plugin activation — flag that cron events need rescheduling.
 *
 * Taxonomies are not registered during the activation hook, so the actual
 * rescheduling is deferred to init priority 20 on the next page load.
 */
register_activation_hook( YOUSYNC_PLUGIN_FILE, function () {
	update_option( 'yousync_reschedule_on_activation', true );
	flush_rewrite_rules();
} );

/**
 * Plugin deactivation — remove scheduled events and clear any stale syncing locks.
 */
register_deactivation_hook( YOUSYNC_PLUGIN_FILE, function () {
	wp_unschedule_hook( 'yousync_channel_config_sync_rule' );
	wp_unschedule_hook( 'yousync_daily_license_check' );
} );

// Load plugin files.
require_once YOUSYNC_PLUGIN_DIR . 'includes/functions.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/settings.php';
// Load sync engine.
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-youtube-api.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-video-importer.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-sync-runner.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-sync-history.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-sync-scheduler.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-channels-page.php';
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-blocks.php';

// Load shared license client library.
require_once YOUSYNC_PLUGIN_DIR . 'includes/license-client/class-wpbuoy-license-client.php';
use WPBuoy_License_Client\v1\Client;
require_once YOUSYNC_PLUGIN_DIR . 'includes/class-yousync-updater.php';

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
		$api       = new \YouSync\YouTube_API( get_option( 'yousync_api_key', '' ) );
		$evaluator = new \YouSync\Condition_Evaluator();
		$importer  = new \YouSync\Video_Importer();
		$runner    = new \YouSync\Sync_Runner( $api, $evaluator, $importer );
		new \YouSync\Sync_Scheduler( $runner );
	},
	5
);

new \YouSync\Blocks();


/**
 * Add video metabox to YouSync Videos post type.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_yousync_video_id', true ) ) {
		return;
	}
	add_meta_box(
		'yousync_video_details',
		__( 'YouSync Video Details', 'yousync' ),
		'yousync_render_video_metabox',
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
function yousync_render_video_metabox( $post ) {
	$has_video_protection = yousync_license()->is_feature_available( 'video_protection' );
	$thumbnails           = get_post_meta( $post->ID, '_yousync_thumbnails', true );
	$thumbnails           = is_array( $thumbnails ) ? $thumbnails : array();

	yousync_get_template_part( 'metabox', 'video', array(
		'nonce_action'          => 'yousync_save_video_meta',
		'post_id'               => $post->ID,
		'video_id'              => (string) get_post_meta( $post->ID, '_yousync_video_id', true ),
		'video_url'             => (string) get_post_meta( $post->ID, '_yousync_video_url', true ),
		'channel_id'            => (string) get_post_meta( $post->ID, '_yousync_channel_id', true ),
		'manual_edits'          => (bool) get_post_meta( $post->ID, '_yousync_protected', true ),
		'manual_edits_disabled' => ! $has_video_protection,
		'manual_edits_notice'   => $has_video_protection ? '' : __( '(Pro Only)', 'yousync' ),
		'original_title'        => (string) get_post_meta( $post->ID, '_yousync_original_title', true ),
		'original_description'  => (string) get_post_meta( $post->ID, '_yousync_original_description', true ),
		'channel_title'         => (string) get_post_meta( $post->ID, '_yousync_channel_title', true ),
		'published_date'        => (string) get_post_meta( $post->ID, '_yousync_published_at', true ),
		'duration_seconds'      => get_post_meta( $post->ID, '_yousync_duration_seconds', true ),
		'view_count'            => get_post_meta( $post->ID, '_yousync_view_count', true ),
		'like_count'            => get_post_meta( $post->ID, '_yousync_like_count', true ),
		'comment_count'         => get_post_meta( $post->ID, '_yousync_comment_count', true ),
		'sync_source_type'      => (string) get_post_meta( $post->ID, '_yousync_source_type', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_yousync_last_synced', true ),
		'thumbnails'            => $thumbnails,
		'thumbnail_size_labels' => array(
			'maxres'   => 'Max Res (1280×720)',
			'standard' => 'Standard (640×480)',
			'high'     => 'High (480×360)',
			'medium'   => 'Medium (320×180)',
			'default'  => 'Default (120×90)',
		),
		'preview_thumb'         => \YouSync\Video_Importer::get_best_thumbnail( $thumbnails ),
	) );
}


/**
 * Save video metabox data.
 *
 * Merges the manual_edits flag into the existing JSON meta, preserving all
 * YouTube API data previously synced. All other fields are read-only and
 * written only by the sync engine.
 *
 * @param int $post_id The current post ID.
 * @return void
 */
function yousync_save_video_meta( $post_id ) {
	if ( ! isset( $_POST['yousync_video_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yousync_video_meta_nonce'] ) ), 'yousync_save_video_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta(
		$post_id,
		'_yousync_protected',
		isset( $_POST['yousync_manual_edits'] ) && '1' === $_POST['yousync_manual_edits'] ? 1 : 0
	);
}
add_action( 'save_post', 'yousync_save_video_meta' );

/**
 * Fall back to the YouTube thumbnail URL when no featured image is set.
 *
 * Only applies to posts that have YouSync thumbnail meta. If the user has
 * explicitly set a featured image, that takes precedence and this filter is a no-op.
 *
 * @param string     $html             Current featured image HTML.
 * @param int        $post_id          Post ID.
 * @param int        $post_thumbnail_id Attachment ID of the set thumbnail (0 if none).
 * @param string|int[] $size           Requested image size.
 * @param string|array $attr           Additional HTML attributes.
 * @return string HTML img tag, or original $html.
 */
function yousync_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
	if ( $post_thumbnail_id ) {
		return $html;
	}

	$thumbnails = get_post_meta( $post_id, '_yousync_thumbnails', true );

	if ( ! is_array( $thumbnails ) || empty( $thumbnails ) ) {
		return $html;
	}

	$thumb = \YouSync\Video_Importer::get_best_thumbnail( $thumbnails );

	if ( ! $thumb ) {
		return $html;
	}

	$width  = ! empty( $thumb['width'] ) ? ' width="' . (int) $thumb['width'] . '"' : '';
	$height = ! empty( $thumb['height'] ) ? ' height="' . (int) $thumb['height'] . '"' : '';
	$alt    = esc_attr( get_the_title( $post_id ) );

	return '<img src="' . esc_url( $thumb['url'] ) . '"' . $width . $height . ' alt="' . $alt . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image">';
}
add_filter( 'post_thumbnail_html', 'yousync_post_thumbnail_html', 10, 5 );

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
function yousync_admin_post_thumbnail_html( string $content, int $post_id, $thumbnail_id ): string {
	if ( $thumbnail_id ) {
		return $content;
	}

	$thumbnails = get_post_meta( $post_id, '_yousync_thumbnails', true );

	if ( ! is_array( $thumbnails ) || empty( $thumbnails ) ) {
		return $content;
	}

	$thumb = \YouSync\Video_Importer::get_best_thumbnail( $thumbnails );

	if ( ! $thumb ) {
		return $content;
	}

	$preview = '<img'
		. ' src="' . esc_url( $thumb['url'] ) . '"'
		. ' onclick="document.getElementById(\'set-post-thumbnail\').click()"'
		. ' title="' . esc_attr__( 'Click to set a custom featured image', 'yousync') . '"'
		. ' alt=""'
		. '>';
	$label   = '<p class="hide-if-no-js howto" id="set-post-thumbnail-desc">'
		. esc_html__( 'Click the YouTube thumbnail to edit or update', 'yousync')
		. '</p>';

	return $preview . $label . $content;
}
add_filter( 'admin_post_thumbnail_html', 'yousync_admin_post_thumbnail_html', 10, 3 );


/**
 * Add playlist metabox to posts that were synced from a YouTube playlist.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_yousync_playlist_id', true ) ) {
		return;
	}
	add_meta_box(
		'yousync_playlist_details',
		__( 'YouSync Playlist Details', 'yousync' ),
		'yousync_render_playlist_metabox',
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
function yousync_render_playlist_metabox( $post ) {
	yousync_get_template_part( 'metabox', 'playlist', array(
		'post_id'               => $post->ID,
		'nonce_action'          => 'yousync_save_playlist_meta',
		'playlist_id'           => (string) get_post_meta( $post->ID, '_yousync_playlist_id', true ),
		'channel_id'            => (string) get_post_meta( $post->ID, '_yousync_channel_id', true ),
		'playlist_title'        => (string) get_post_meta( $post->ID, '_yousync_playlist_title', true ),
		'playlist_description'  => (string) get_post_meta( $post->ID, '_yousync_playlist_description', true ),
		'playlist_video_count'  => get_post_meta( $post->ID, '_yousync_playlist_video_count', true ),
		'playlist_thumbnail'    => (string) get_post_meta( $post->ID, '_yousync_playlist_thumbnail', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_yousync_last_synced', true ),
		'manual_edits'          => (bool) get_post_meta( $post->ID, '_yousync_protected', true ),
	) );
}

/**
 * Save playlist metabox data (protected flag only — all other fields are read-only).
 *
 * @param int $post_id Post ID.
 * @return void
 */
function yousync_save_playlist_meta( $post_id ) {
	if ( ! isset( $_POST['yousync_playlist_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yousync_playlist_meta_nonce'] ) ), 'yousync_save_playlist_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta(
		$post_id,
		'_yousync_protected',
		isset( $_POST['yousync_manual_edits'] ) && '1' === $_POST['yousync_manual_edits'] ? 1 : 0
	);
}
add_action( 'save_post', 'yousync_save_playlist_meta' );


/**
 * Add channel metabox to posts that were synced as a YouTube channel.
 *
 * Uses _yousync_channel_post (not _yousync_channel_id) so that video/playlist posts
 * that store the source channel ID do not get a Channel Details metabox.
 *
 * @return void
 */
add_action( 'add_meta_boxes', function ( string $post_type, \WP_Post $post ): void {
	if ( ! get_post_meta( $post->ID, '_yousync_channel_post', true ) ) {
		return;
	}
	add_meta_box(
		'yousync_channel_details',
		__( 'YouSync Channel Details', 'yousync' ),
		'yousync_render_channel_metabox',
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
function yousync_render_channel_metabox( $post ) {
	yousync_get_template_part( 'metabox', 'channel', array(
		'nonce_action'          => 'yousync_save_channel_meta',
		'post_id'               => $post->ID,
		'channel_id'            => (string) get_post_meta( $post->ID, '_yousync_channel_post', true ),
		'channel_title'         => (string) get_post_meta( $post->ID, '_yousync_channel_title', true ),
		'channel_description'   => (string) get_post_meta( $post->ID, '_yousync_channel_description', true ),
		'subscriber_count'      => get_post_meta( $post->ID, '_yousync_subscriber_count', true ),
		'video_count'           => get_post_meta( $post->ID, '_yousync_channel_video_count', true ),
		'last_synced'           => (int) get_post_meta( $post->ID, '_yousync_last_synced', true ),
		'manual_edits'          => (bool) get_post_meta( $post->ID, '_yousync_protected', true ),
		'profile_picture'       => (string) get_post_meta( $post->ID, '_yousync_profile_picture', true ),
		'banner_image'          => (string) get_post_meta( $post->ID, '_yousync_banner_image', true ),
	) );
}

/**
 * Save channel metabox data (protected flag only — all other fields are read-only).
 *
 * @param int $post_id Post ID.
 * @return void
 */
function yousync_save_channel_meta( $post_id ) {
	if ( ! isset( $_POST['yousync_channel_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yousync_channel_meta_nonce'] ) ), 'yousync_save_channel_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta(
		$post_id,
		'_yousync_protected',
		isset( $_POST['yousync_manual_edits'] ) && '1' === $_POST['yousync_manual_edits'] ? 1 : 0
	);
}
add_action( 'save_post', 'yousync_save_channel_meta' );


/**
 * Enqueue assets on post edit screens where the post has YouSync video meta.
 *
 * @return void
 */
function yousync_enqueue_video_list_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$has_yousync_meta = $post_id && (
		get_post_meta( $post_id, '_yousync_video_id', true ) ||
		get_post_meta( $post_id, '_yousync_playlist_id', true ) ||
		get_post_meta( $post_id, '_yousync_channel_post', true )
	);
	if ( ! $has_yousync_meta ) {
		return;
	}

	wp_enqueue_style(
		'yousync-admin',
		YOUSYNC_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		YOUSYNC_VERSION
	);
	wp_enqueue_script(
		'yousync-metabox',
		YOUSYNC_PLUGIN_URL . 'assets/js/metabox.js',
		array(),
		YOUSYNC_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'yousync_enqueue_video_list_assets' );

add_action( 'wp_ajax_yousync_get_terms', 'yousync_ajax_get_terms' );

/**
 * AJAX handler — fetch terms for a given public taxonomy.
 *
 * @return void
 */
function yousync_ajax_get_terms() {
	check_ajax_referer( 'yousync_get_terms', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Forbidden', 403 );
	}
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
	if ( ! taxonomy_exists( $taxonomy ) ) {
		wp_send_json_error( 'Invalid taxonomy' );
	}
	$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
	if ( is_wp_error( $terms ) ) {
		wp_send_json_error( $terms->get_error_message() );
	}
	wp_send_json_success(
		array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name ], $terms )
	);
}

// -----------------------------------------------------------------------
// License
// -----------------------------------------------------------------------

/**
 * Returns the Wpbuoy_License_Client instance for YouSync Pro.
 *
 * Uses a static variable so the instance is created only once and its
 * constructor hooks register a single time regardless of how often this
 * function is called.
 *
 * @return Wpbuoy_License_Client
 */
function yousync_license(): Client {
	static $instance = null;

	if ( null === $instance ) {
		$license_base = defined( 'WPBUOY_LICENSE_SERVER' )
			? untrailingslashit( WPBUOY_LICENSE_SERVER )
			: 'https://wpbuoy.com';

		$instance = new Client( array(
			'plugin_slug'  => 'yousync',
			'server_url'   => $license_base . '/wp-json/wpbylm/v1/verify',
			'pro_features' => array(
				'scheduled_sync',
				'metadata_update',
				'conditions',
				'field_mapping',
				'taxonomy_terms',
				'video_protection',
				'multi_channel',
			),
			'text_domain'  => 'yousync',
			'plugin_name'  => 'YouSync Pro',
			'pricing_url'  => $license_base . '/pricing',
			'account_url'  => $license_base . '/my-account/licenses',
			'page_slug'    => 'yousync_license',
		) );
	}

	return $instance;
}

// Instantiate immediately so the license client's hooks (init, admin_init, admin_post_*) register at plugin load.
yousync_license();

new \YouSync\Updater(
	'yousync',
	plugin_basename( __FILE__ ),
	defined( 'WPBUOY_LICENSE_SERVER' ) ? WPBUOY_LICENSE_SERVER : 'https://wpbuoy.com'
);


/**
 * One-time migration: convert _yousync_video JSON blob to individual flat meta keys.
 *
 * Runs on admin_init, gated by the yousync_flat_meta_migrated option.
 * Processes posts in batches of 50 per request until all legacy blobs are migrated.
 */
add_action( 'admin_init', function (): void {
	if ( get_option( 'yousync_flat_meta_migrated' ) ) {
		return;
	}

	$query = new WP_Query( array(
		'post_type'      => 'any',
		'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => false,
		'meta_query'     => array(
			array(
				'key'     => '_yousync_video',
				'compare' => 'EXISTS',
			),
		),
	) );

	foreach ( $query->posts as $post_id ) {
		$post_id = (int) $post_id;
		$raw     = get_post_meta( $post_id, '_yousync_video', true );
		$data    = is_string( $raw ) ? ( json_decode( $raw, true ) ?: array() ) : array();

		if ( empty( $data ) ) {
			delete_post_meta( $post_id, '_yousync_video' );
			continue;
		}

		$video_id = $data['video_id'] ?? '';

		update_post_meta( $post_id, '_yousync_video_id',             $video_id );
		update_post_meta( $post_id, '_yousync_video_url',            $video_id ? 'https://www.youtube.com/watch?v=' . $video_id : '' );
		update_post_meta( $post_id, '_yousync_channel_id',           $data['channel_id'] ?? '' );
		update_post_meta( $post_id, '_yousync_channel_title',        $data['channel_title'] ?? '' );
		update_post_meta( $post_id, '_yousync_etag',                 $data['etag'] ?? '' );
		update_post_meta( $post_id, '_yousync_source_type',          $data['sync_source_type'] ?? '' );
		update_post_meta( $post_id, '_yousync_source_id',            (int) ( $data['sync_source_id'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_original_title',       $data['original_title'] ?? '' );
		update_post_meta( $post_id, '_yousync_original_description', $data['original_description'] ?? '' );
		update_post_meta( $post_id, '_yousync_published_at',         $data['published_at'] ?? '' );
		update_post_meta( $post_id, '_yousync_duration_seconds',     (int) ( $data['duration_seconds'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_view_count',           (int) ( $data['view_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_like_count',           (int) ( $data['like_count'] ?? 0 ) );
		update_post_meta( $post_id, '_yousync_comment_count',        (int) ( $data['comment_count'] ?? 0 ) );

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
		update_post_meta( $post_id, '_yousync_thumbnails', $thumbnails );

		// Migrate manual_edits → _yousync_protected (only if not already set).
		if ( '' === get_post_meta( $post_id, '_yousync_protected', true ) ) {
			update_post_meta( $post_id, '_yousync_protected', (int) ( $data['manual_edits'] ?? 0 ) );
		}

		// Migrate last_modified → _yousync_last_synced (only if not already set).
		if ( '' === get_post_meta( $post_id, '_yousync_last_synced', true ) ) {
			update_post_meta( $post_id, '_yousync_last_synced', (int) ( $data['last_modified'] ?? 0 ) );
		}

		delete_post_meta( $post_id, '_yousync_video' );
	}

	if ( $query->found_posts <= 50 ) {
		update_option( 'yousync_flat_meta_migrated', true, false );
	}
} );