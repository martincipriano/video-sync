<?php
/**
 * Plugin Name: YouSync
 * Plugin URI: https://github.com/martincipriano/yousync
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
if ( defined( 'YOUSYNC_PRO_VERSION' ) ) {
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
 * Plugin deactivation — remove all scheduled sync cron events.
 */
register_deactivation_hook( YOUSYNC_PLUGIN_FILE, function () {
	wp_unschedule_hook( 'yousync_sync_rule' );
	flush_rewrite_rules();
} );

// Load plugin files.
require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-channel.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-playlist.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-sidebar.php';

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

/**
 * Reschedule all sync rules on the first page load after plugin activation.
 *
 * Runs at priority 20 so taxonomies (registered at priority 10) are available
 * for get_terms() lookups. Skips once-only rules — those fire immediately and
 * should not be re-queued automatically.
 */
add_action( 'init', function () {
	if ( ! get_option( 'yousync_reschedule_on_activation' ) ) {
		return;
	}

	delete_option( 'yousync_reschedule_on_activation' );

	$sources = array(
		'channel'  => 'yousync_channel',
		'playlist' => 'yousync_playlist',
	);

	foreach ( $sources as $source_type => $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			$raw  = get_term_meta( $term->term_id, $taxonomy, true );
			$data = $raw ? json_decode( $raw, true ) : array();

			if ( ! is_array( $data ) || empty( $data['sync_rules'] ) ) {
				continue;
			}

			foreach ( $data['sync_rules'] as $index => $rule ) {
				if ( empty( $rule['enabled'] ) || 'once' === ( $rule['schedule'] ?? '' ) ) {
					continue;
				}

				$args = array( $source_type, (int) $term->term_id, (int) $index );

				if ( wp_next_scheduled( 'yousync_sync_rule', $args ) ) {
					continue; // Already scheduled.
				}

				$schedule = $rule['schedule'] ?? 'daily';

				if ( in_array( $schedule, array( 'hourly', 'daily', 'weekly' ), true ) ) {
					$interval = $schedule;
				} elseif ( 'monthly' === $schedule ) {
					$interval = 'yousync_monthly';
				} else {
					$interval = 'yousync_every_' . (int) ( $rule['custom_schedule'] ?? 24 ) . 'h';
				}

				wp_schedule_event( time(), $interval, 'yousync_sync_rule', $args );
			}
		}
	}
}, 20 );

/**
 * Register YouSync Videos custom post type and taxonomies.
 *
 * @return void
 */
function yousync_init() {
	// Get active archives configuration.
	$active_archives = get_option( 'yousync_active_archives', array() );

	// Videos post type configuration.
	$video_enabled     = isset( $active_archives['ys-video']['enabled'] ) && $active_archives['ys-video']['enabled'];
	$video_slug        = ! empty( $active_archives['ys-video']['slug'] ) ? $active_archives['ys-video']['slug'] : 'ys-video';
	$video_has_archive = $video_enabled;
	$video_public      = $video_enabled;
	$video_rewrite     = $video_enabled ? array( 'slug' => $video_slug ) : false;

	register_post_type(
		'yousync_videos',
		array(
			'labels'              => array(
				'name'          => __( 'Videos', 'yousync' ),
				'singular_name' => __( 'Video', 'yousync' ),
				'menu_name'     => __( 'YouSync', 'yousync' ),
				'all_items'     => __( 'Videos', 'yousync' ),
				'add_new'       => __( 'Add New Video', 'yousync' ),
				'add_new_item'  => __( 'Add New Video', 'yousync' ),
			),
			'public'              => $video_public,
			'publicly_queryable'  => $video_enabled,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-video-alt3',
			'has_archive'         => $video_has_archive,
			'rewrite'             => $video_rewrite,
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
			'supports'            => array(
				'title',
				'editor',
				'thumbnail',
			),
		)
	);

	// Register yousync_tag taxonomy.
	register_taxonomy(
		'yousync_tag',
		'yousync_videos',
		array(
			'labels'            => array(
				'name'          => __( 'Video Tags', 'yousync' ),
				'singular_name' => __( 'Video Tag', 'yousync' ),
				'menu_name'     => __( 'Tags', 'yousync' ),
				'search_items'  => __( 'Search Video Tags', 'yousync' ),
				'all_items'     => __( 'All Video Tags', 'yousync' ),
				'edit_item'     => __( 'Edit Video Tag', 'yousync' ),
				'add_new_item'  => __( 'Add New Video Tag', 'yousync' ),
			),
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'yousync-tag' ),
		)
	);

	// Register yousync_category taxonomy.
	register_taxonomy(
		'yousync_category',
		'yousync_videos',
		array(
			'labels'            => array(
				'name'          => __( 'Video Categories', 'yousync' ),
				'singular_name' => __( 'Video Category', 'yousync' ),
				'menu_name'     => __( 'Categories', 'yousync' ),
				'search_items'  => __( 'Search Video Categories', 'yousync' ),
				'all_items'     => __( 'All Video Categories', 'yousync' ),
				'edit_item'     => __( 'Edit Video Category', 'yousync' ),
				'add_new_item'  => __( 'Add New Video Category', 'yousync' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'yousync-category' ),
		)
	);

	// Note: Channel and Playlist taxonomies are registered in their respective class files.
}
add_action( 'init', 'yousync_init' );

/**
 * Add video metabox to YouSync Videos post type.
 *
 * @return void
 */
function yousync_add_video_metabox() {
	add_meta_box(
		'yousync_video_details',
		__( 'YouSync Video Details', 'yousync' ),
		'yousync_render_video_metabox',
		'yousync_videos',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'yousync_add_video_metabox' );

/**
 * Render the video metabox with a tabbed interface.
 *
 * Tabs: Details | YouTube Data | Thumbnails | Sync Status
 *
 * @param WP_Post $post The current post object.
 * @return void
 */
function yousync_render_video_metabox( $post ) {
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
	// HTML output is handled by template-parts/metabox-video.php
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

	// Read existing JSON meta to preserve YouTube API data.
	$existing_meta = get_post_meta( $post_id, '_yousync_video', true );
	$data          = $existing_meta ? json_decode( $existing_meta, true ) : array();
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	// The only user-controlled field is the protect-from-sync flag.
	$data['manual_edits'] = isset( $_POST['yousync_manual_edits'] ) && '1' === $_POST['yousync_manual_edits'];

	update_post_meta( $post_id, '_yousync_video', wp_slash( wp_json_encode( $data ) ) );
}
add_action( 'save_post_yousync_videos', 'yousync_save_video_meta' );

/**
 * Fall back to the YouTube thumbnail URL when no featured image is set.
 *
 * Only applies to yousync_videos posts. If the user has explicitly set a
 * featured image, that takes precedence and this filter is a no-op.
 *
 * @param string     $html             Current featured image HTML.
 * @param int        $post_id          Post ID.
 * @param int        $post_thumbnail_id Attachment ID of the set thumbnail (0 if none).
 * @param string|int[] $size           Requested image size.
 * @param string|array $attr           Additional HTML attributes.
 * @return string HTML img tag, or original $html.
 */
function yousync_post_thumbnail_html( string $html, int $post_id, int $post_thumbnail_id, $size, $attr ): string {
	if ( 'yousync_videos' !== get_post_type( $post_id ) ) {
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
	if ( 'yousync_videos' !== get_post_type( $post_id ) ) {
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
 * Reorder YouSync submenu items and remove "Add New Video".
 *
 * Videos are created exclusively by the sync engine — manual creation produces
 * posts without YouTube metadata and breaks deduplication. The "Add New" item
 * is removed from the submenu and direct URL access is blocked via admin_init.
 *
 * Enforces the order: Videos, Channels, Playlists, Categories, Tags, Logs, Settings.
 *
 * Uses str_contains for taxonomy slugs because WordPress omits the &post_type=
 * suffix when a taxonomy uses a custom show_in_menu string.
 *
 * @return void
 */
function yousync_reorder_submenu(): void {
	global $submenu;

	$parent = 'edit.php?post_type=yousync_videos';

	if ( empty( $submenu[ $parent ] ) ) {
		return;
	}

	$position = static function ( string $slug ): int {
		if ( 'edit.php?post_type=yousync_videos' === $slug ) return 0;
		if ( str_contains( $slug, 'taxonomy=yousync_channel' ) ) return 1;
		if ( str_contains( $slug, 'taxonomy=yousync_playlist' ) ) return 2;
		if ( str_contains( $slug, 'taxonomy=yousync_category' ) ) return 3;
		if ( str_contains( $slug, 'taxonomy=yousync_tag' ) ) return 4;
		if ( 'yousync_logs' === $slug ) return 5;
		if ( 'yousync_settings' === $slug ) return 6;
		return PHP_INT_MAX;
	};

	$items = array_values( $submenu[ $parent ] );

	usort( $items, static function ( array $a, array $b ) use ( $position ): int {
		return $position( $a[2] ) - $position( $b[2] );
	} );

	$submenu[ $parent ] = $items;
}
add_action( 'admin_menu', 'yousync_reorder_submenu', 999 );

/**
 * Block direct access to the "Add New Video" screen.
 *
 * Redirects post-new.php?post_type=yousync_videos to the video list.
 * Videos must be created by the sync engine to ensure valid YouTube metadata.
 *
 * @return void
 */
add_action( 'load-post-new.php', function (): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['post_type'] ) && 'yousync_videos' === $_GET['post_type'] ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=yousync_videos' ) );
		exit;
	}
} );

/**
 * Add thumbnail and protected columns to the yousync_videos post list.
 *
 * Thumbnail is inserted before the title; Protected is appended last.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function yousync_add_video_columns( array $columns ): array {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['yousync_thumbnail'] = __( 'Thumbnail', 'yousync' );
		}
		$new[ $key ] = $label;
	}
	$new['yousync_protected'] = '<span style="display:block; text-align:right;">' . esc_html__( 'Protected from Sync Rules', 'yousync' ) . '</span>';
	return $new;
}
add_filter( 'manage_yousync_videos_posts_columns', 'yousync_add_video_columns' );

/**
 * Render the thumbnail and protected columns in the yousync_videos post list.
 *
 * @param string $column  Column slug.
 * @param int    $post_id Post ID.
 * @return void
 */
function yousync_render_video_columns( string $column, int $post_id ): void {
	$meta = get_post_meta( $post_id, '_yousync_video', true );
	$data = $meta ? json_decode( $meta, true ) : array();

	if ( 'yousync_thumbnail' === $column ) {
		$thumbnail = \YouSync\Video_Importer::get_best_thumbnail( $data['thumbnails'] ?? array() );
		$edit_url  = get_edit_post_link( $post_id );
		if ( $thumbnail ) {
			printf(
				'<a href="%s"><img src="%s" width="80" height="45" style="object-fit:cover;border-radius:3px;display:block;" alt="" loading="lazy"></a>',
				esc_url( $edit_url ),
				esc_url( $thumbnail['url'] )
			);
		} else {
			echo '<span style="color:#aaa;">—</span>';
		}
		return;
	}

	if ( 'yousync_protected' === $column ) {
		$is_protected = ! empty( $data['manual_edits'] );
		?>
		<label class="ys-toggle ys-protect-toggle" data-post-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Protected from Sync Rules (Pro feature)', 'yousync' ); ?>" style="display:flex; justify-content:flex-end; opacity:0.5; pointer-events:none;" aria-label="<?php esc_attr_e( 'Pro feature', 'yousync' ); ?>">
			<input type="checkbox" class="ys-protect-checkbox" value="1" <?php checked( $is_protected ); ?> disabled>
			<span class="ys-toggle-slider"></span>
		</label>
		<?php
	}
}
add_action( 'manage_yousync_videos_posts_custom_column', 'yousync_render_video_columns', 10, 2 );

/**
 * Enqueue assets for the yousync_videos post list screen.
 *
 * Loads the admin CSS (for the toggle) and an inline JS handler that fires
 * an AJAX request when the protection toggle is clicked in the list column.
 *
 * @return void
 */
function yousync_enqueue_video_list_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'edit-yousync_videos', 'yousync_videos', 'yousync_videos_page_yousync_settings' ), true ) ) {
		return;
	}

	wp_enqueue_style(
		'yousync-admin',
		YOUSYNC_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		filemtime( YOUSYNC_PLUGIN_DIR . 'assets/css/admin.css' )
	);

	if ( 'yousync_videos' === $screen->id ) {
		wp_enqueue_script(
			'yousync-metabox',
			YOUSYNC_PLUGIN_URL . 'assets/js/metabox.js',
			array(),
			filemtime( YOUSYNC_PLUGIN_DIR . 'assets/js/metabox.js' ),
			true
		);
		return;
	}

	$nonce = wp_create_nonce( 'yousync_toggle_protection' );
	wp_add_inline_script(
		'jquery',
		'var ysProtect = ' . wp_json_encode( array( 'nonce' => $nonce ) ) . ';
document.addEventListener( "change", function( e ) {
	var cb = e.target;
	if ( ! cb.classList.contains( "ys-protect-checkbox" ) ) return;
	var label  = cb.closest( ".ys-protect-toggle" );
	var postId = label.dataset.postId;
	var fd     = new FormData();
	fd.append( "action",    "yousync_toggle_protection" );
	fd.append( "post_id",   postId );
	fd.append( "protected", cb.checked ? "1" : "0" );
	fd.append( "nonce",     ysProtect.nonce );
	label.style.opacity = "0.5";
	fetch( ajaxurl, { method: "POST", body: fd } )
		.then( function( r ) { return r.json(); } )
		.then( function( res ) {
			label.style.opacity = "1";
			if ( ! res.success ) { cb.checked = ! cb.checked; }
		} )
		.catch( function() {
			label.style.opacity = "1";
			cb.checked = ! cb.checked;
		} );
} );'
	);
}
add_action( 'admin_enqueue_scripts', 'yousync_enqueue_video_list_assets' );

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