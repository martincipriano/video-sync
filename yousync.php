<?php
declare(strict_types=1);

/**
 * Plugin Name: YouSync
 * Plugin URI: https://wpbuoy.com/plugins/yousync/
 * Description: Sync YouTube channels and playlists to WordPress. Import new videos as a custom post type. Free version — upgrade to YouSync Pro for recurring schedules, metadata update actions, sync conditions, and video protection.
 * Version: 1.0.0
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
define( 'YOUSYNC_VERSION', '1.0.0' );
define( 'YOUSYNC_PLUGIN_FILE', __FILE__ );
define( 'YOUSYNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YOUSYNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// YouSync Pro is active — defer entirely. Pro supersedes free: same post types,
// taxonomies, and cron hooks. Returning here prevents duplicate registrations
// and also skips the deactivation hook, which would otherwise unschedule all
// of Pro's sync cron events if the user deactivates the free plugin.
if ( defined( 'YOUSYNC_PRO' ) ) {
	add_action( 'admin_notices', function () {
		$deactivate_url = wp_nonce_url(
			admin_url( 'plugins.php?action=deactivate&plugin=yousync%2Fyousync.php' ),
			'deactivate-plugin_yousync/yousync.php'
		);
		echo '<div class="notice notice-info"><p>' .
			esc_html__( 'YouSync Pro is active — the free version is dormant and can be safely deactivated.', 'yousync' ) .
			' <a href="' . esc_url( $deactivate_url ) . '">' . esc_html__( 'Deactivate free version', 'yousync' ) . '</a>' .
		'</p></div>';
	} );
	return;
}

/**
 * Load a template part into a plugin template.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template.
 * @param array  $args Optional. Additional arguments passed to the template.
 * @return string|bool The template path if found, false otherwise.
 */
function yousync_get_template_part( $slug, $name = null, $args = array() ) {
	$templates  = array();
	$plugin_dir = plugin_dir_path( __FILE__ );

	if ( isset( $name ) ) {
		$templates[] = "{$slug}-{$name}.php";
	}
	$templates[] = "{$slug}.php";

	$located = false;
	foreach ( $templates as $template ) {
		$template_path = $plugin_dir . 'template-parts/' . $template;

		if ( file_exists( $template_path ) ) {
			$located = $template_path;
			break;
		}
	}

	if ( $located ) {
		if ( is_array( $args ) && ! empty( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Necessary for template variable extraction.
			extract( $args, EXTR_SKIP );
		}
		include $located;
	}

	return $located;
}

/**
 * Load a template part and return its output as a string.
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name Optional. The name of the specialized template.
 * @param array  $args Optional. Additional arguments passed to the template.
 * @return string The template output as a string, or empty string if template not found.
 */
function yousync_return_template_part( $slug, $name = null, $args = array() ) {
	ob_start();
	yousync_get_template_part( $slug, $name, $args );
	return ob_get_clean();
}

/**
 * Get the field type for a sync rule condition field.
 *
 * @param string $field The condition field name.
 * @return string Field type: 'text', 'number', or 'date'. Empty string if unknown.
 */
function yousync_get_condition_field_type( $field ) {
	$map = array(
		'video_id'       => 'text',
		'title'          => 'text',
		'description'    => 'text',
		'tags'           => 'text',
		'duration'       => 'number',
		'published_date' => 'date',
		'yousync_category' => 'text',
		'view_count'     => 'number',
		'like_count'     => 'number',
		'comment_count'  => 'number',
	);
	return isset( $map[ $field ] ) ? $map[ $field ] : '';
}

/**
 * Plugin activation — flush rewrite rules.
 */
register_activation_hook( YOUSYNC_PLUGIN_FILE, function () {
	flush_rewrite_rules();
} );

/**
 * Plugin deactivation — remove all scheduled sync cron events.
 */
register_deactivation_hook( YOUSYNC_PLUGIN_FILE, function () {
	wp_unschedule_hook( 'yousync_sync_rule' );
	flush_rewrite_rules();
} );

// Load plugin files.
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
// Load sync engine.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-youtube-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-condition-evaluator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-video-importer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sync-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sync-runner.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-sync-scheduler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-logs.php';

/**
 * Instantiate the sync engine.
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

/**
 * Register the video details metabox on the configured destination post type.
 *
 * @return void
 */
function yousync_add_video_metabox() {
	$config    = get_option( 'yousync_channel_config', array() );
	$post_type = $config['destination_post_type'] ?? 'post';

	add_meta_box(
		'yousync_video_details',
		__( 'YouSync Video Details', 'yousync' ),
		'yousync_render_video_metabox',
		$post_type,
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'yousync_add_video_metabox' );

/**
 * Render the video metabox.
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function yousync_render_video_metabox( $post ) {
	// Only render on posts synced by YouSync.
	if ( ! get_post_meta( $post->ID, '_yousync_video', true ) ) {
		return;
	}

	$meta = get_post_meta( $post->ID, '_yousync_video', true );
	$data = $meta ? json_decode( $meta, true ) : array();
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$thumbnails = is_array( $data['thumbnails'] ?? null ) ? $data['thumbnails'] : array();

	yousync_get_template_part( 'metabox', 'video', array(
		'nonce_action'          => 'yousync_save_video_meta',
		'video_id'              => $data['video_id'] ?? '',
		'video_url'             => $data['video_url'] ?? '',
		'channel_id'            => $data['channel_id'] ?? '',
		'manual_edits'          => (bool) ( $data['manual_edits'] ?? false ),
		'manual_edits_disabled' => true,
		'manual_edits_notice'   => __( '(Pro feature)', 'yousync' ),
		'original_title'        => $data['original_title'] ?? '',
		'original_description'  => $data['original_description'] ?? '',
		'channel_title'         => $data['channel_title'] ?? '',
		'published_date'        => $data['published_date'] ?? '',
		'duration_seconds'      => $data['duration_seconds'] ?? '',
		'view_count'            => $data['view_count'] ?? '',
		'like_count'            => $data['like_count'] ?? '',
		'comment_count'         => $data['comment_count'] ?? '',
		'sync_source_type'      => $data['sync_source_type'] ?? '',
		'last_synced'           => $data['last_synced'] ?? '',
		'sync_count'            => $data['sync_count'] ?? 0,
		'sync_errors'           => is_array( $data['sync_errors'] ?? null ) ? $data['sync_errors'] : array(),
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
 * Only saves on posts that have been synced by YouSync (_yousync_video meta present).
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

	// Only act on posts synced by YouSync.
	$existing_meta = get_post_meta( $post_id, '_yousync_video', true );
	if ( ! $existing_meta ) {
		return;
	}

	$data = json_decode( $existing_meta, true );
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	$data['manual_edits'] = isset( $_POST['yousync_manual_edits'] ) && '1' === $_POST['yousync_manual_edits'];

	update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $data ) ) );
}
add_action( 'save_post', 'yousync_save_video_meta' );

/**
 * Fall back to the YouTube thumbnail URL when no featured image is set.
 *
 * Applies to any post synced by YouSync (identified by _yousync_video meta).
 *
 * @param string       $html              Current featured image HTML.
 * @param int          $post_id           Post ID.
 * @param int          $post_thumbnail_id Attachment ID of the set thumbnail (0 if none).
 * @param string|int[] $size              Requested image size.
 * @param string|array $attr              Additional HTML attributes.
 * @return string HTML img tag, or original $html.
 */
function yousync_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
	// Only apply to posts synced by YouSync.
	if ( ! get_post_meta( $post_id, '_yousync_video', true ) ) {
		return $html;
	}

	// User explicitly set a featured image — respect it.
	if ( $post_thumbnail_id ) {
		return $html;
	}

	$raw  = get_post_meta( $post_id, '_yousync_video', true );
	$data = $raw ? json_decode( $raw, true ) : array();

	if ( empty( $data['thumbnails'] ) || ! is_array( $data['thumbnails'] ) ) {
		return $html;
	}

	$thumb = \YouSync\Video_Importer::get_best_thumbnail( $data['thumbnails'] );

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
 * @param string   $content      Current featured image metabox HTML.
 * @param int      $post_id      Post ID.
 * @param int|null $thumbnail_id Attachment ID of the set thumbnail, or null if none.
 * @return string Modified HTML.
 */
function yousync_admin_post_thumbnail_html( string $content, int $post_id, $thumbnail_id ): string {
	// Only apply to posts synced by YouSync.
	if ( ! get_post_meta( $post_id, '_yousync_video', true ) ) {
		return $content;
	}

	// A featured image is explicitly set — leave it alone.
	if ( $thumbnail_id ) {
		return $content;
	}

	$raw  = get_post_meta( $post_id, '_yousync_video', true );
	$data = $raw ? json_decode( $raw, true ) : array();

	if ( empty( $data['thumbnails'] ) || ! is_array( $data['thumbnails'] ) ) {
		return $content;
	}

	$thumb = \YouSync\Video_Importer::get_best_thumbnail( $data['thumbnails'] );

	if ( ! $thumb ) {
		return $content;
	}

	$preview = '<img'
		. ' src="' . esc_url( $thumb['url'] ) . '"'
		. ' onclick="document.getElementById(\'set-post-thumbnail\').click()"'
		. ' title="' . esc_attr__( 'Click to set a custom featured image', 'yousync' ) . '"'
		. ' alt=""'
		. '>';
	$label   = '<p class="hide-if-no-js howto" id="set-post-thumbnail-desc">'
		. esc_html__( 'Click the YouTube thumbnail to edit or update', 'yousync' )
		. '</p>';

	return $preview . $label . $content;
}
add_filter( 'admin_post_thumbnail_html', 'yousync_admin_post_thumbnail_html', 10, 3 );

/**
 * AJAX handler — toggle Protect from Sync on a single video post.
 *
 * @return void
 */
function yousync_ajax_toggle_protection(): void {
	check_ajax_referer( 'yousync_toggle_protection', 'nonce' );
	// Protection toggle is a Pro feature — not available in the free version.
	wp_send_json_error( 'Pro feature' );
}
add_action( 'wp_ajax_yousync_toggle_protection', 'yousync_ajax_toggle_protection' );
