<?php
declare(strict_types=1);
/**
 * Template part for displaying a Channel sync rule.
 *
 * @package WPBuoyVideoSync
 *
 * Variables available in this template:
 * @var int|string $index Rule index.
 * @var array      $rule Rule data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to wpbuoy_video_sync_get_template_part()'s extract()/include scope, not globals.

$term_id     = isset( $term_id ) ? (int) $term_id : 0;
$source_type = isset( $source_type ) ? $source_type : 'channel';
$name_prefix = isset( $name_prefix ) ? $name_prefix : 'sync_rules';
$enabled     = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$action      = isset( $rule['action'] ) ? $rule['action'] : '';

$max_videos            = isset( $rule['max_videos'] ) ? (int) $rule['max_videos'] : 50;
$destination_post_type = $rule['destination_post_type'] ?? '';

$post_types = get_post_types( array( 'public' => true ), 'objects' );

// Dual-mode: Use provided $index or fall back to the {{INDEX}} placeholder for JavaScript.
$rule_index = isset( $index ) ? $index : '{{INDEX}}';

// Auto-generated rule label from the action. Free runs every rule once, immediately.
$_action_labels = array(
	'channel_sync_new'   => __( 'Sync this channel', 'wpbuoy-video-sync' ),
	'playlists_sync_new' => __( 'Sync new playlists', 'wpbuoy-video-sync' ),
	'videos_sync_new'    => __( 'Sync new videos', 'wpbuoy-video-sync' ),
);
$rule_action_label    = $action ? ( $_action_labels[ $action ] ?? $action ) : __( 'New rule', 'wpbuoy-video-sync' );
$rule_schedule_suffix = __( 'immediately after enabling and saving', 'wpbuoy-video-sync' );

$sync_started_at = isset( $rule['sync_started_at'] ) ? (int) $rule['sync_started_at'] : 0;
$is_syncing      = 'syncing' === ( $rule['sync_status'] ?? '' )
	&& $sync_started_at
	&& ( time() - $sync_started_at ) < 1800;

// Determine the resource type from the action (used for the dynamic labels).
$resource = '';
if ( strpos( $action, 'channel' ) === 0 ) {
	$resource = 'channel';
} elseif ( strpos( $action, 'playlists' ) === 0 ) {
	$resource = 'playlist';
} elseif ( strpos( $action, 'videos' ) === 0 ) {
	$resource = 'video';
}

// Sync status line.
$rule_last_synced = (int) ( $rule['last_synced'] ?? 0 );
$rule_sync_errors = array_values( array_filter(
	is_array( $rule['sync_errors'] ?? null ) ? $rule['sync_errors'] : array(),
	fn( $e ) => ( $e['code'] ?? '' ) !== 'license_required'
) );
$is_option_channel  = isset( $is_option_channel ) ? (bool) $is_option_channel : false;
$option_channel_idx = isset( $option_channel_idx ) ? (int) $option_channel_idx : 0;

$_df                 = 'F j, Y g:i A';
$rule_stat_created   = ! empty( $rule['scheduled_at'] ) ? wp_date( $_df, (int) $rule['scheduled_at'] ) : '—';
$rule_stat_last_sync = $rule_last_synced ? wp_date( $_df, $rule_last_synced ) : '—';

// Dynamic labels.
$_max_items_label = $resource === 'video'
	? __( 'Videos per run', 'wpbuoy-video-sync' )
	: ( $resource === 'playlist'
		? __( 'Playlists per run', 'wpbuoy-video-sync' )
		: __( 'Items per run', 'wpbuoy-video-sync' ) );

$_post_type_label = 'playlists_sync_new' === $action
	? __( 'Save synced playlists as post type', 'wpbuoy-video-sync' )
	: ( 'channel_sync_new' === $action
		? __( 'Save synced channel as post type', 'wpbuoy-video-sync' )
		: __( 'Save synced videos as post type', 'wpbuoy-video-sync' )
	);
?>

<div class="ys-rule<?php echo $is_syncing ? ' ys-rule--syncing' : ''; ?>" data-rule-index="<?php echo esc_attr( $rule_index ); ?>" data-ch-index="<?php echo esc_attr( (string) $option_channel_idx ); ?>">

	<?php if ( $is_syncing ) : ?>
	<div class="ys-syncing-overlay" aria-hidden="true">
		<span class="ys-syncing-badge"><?php esc_html_e( 'Syncing...', 'wpbuoy-video-sync' ); ?><span class="ys-syncing-progress"></span></span>
	</div>
	<?php endif; ?>

	<div class="ys-rule-header" role="button" tabindex="0" aria-expanded="true">
		<div class="ys-rule-heading-wrap">
			<div class="ys-rule-heading"><?php echo esc_html( $rule_action_label . ' ' . $rule_schedule_suffix . '.' ); ?></div>
			<?php if ( ! $enabled ) : ?>
			<p class="ys-rule-disabled-notice"><?php esc_html_e( 'This rule is disabled and won\'t run until re-enabled.', 'wpbuoy-video-sync' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ys-rule-actions">
			<label class="ys-toggle">
				<input <?php checked( $enabled, true ); ?> class="ys-rule-toggle" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][enabled]" type="checkbox" value="1">
				<span class="ys-toggle-slider"></span>
			</label>
			<?php $has_stat = ! empty( $rule['scheduled_at'] ) || $rule_last_synced; ?>
			<?php if ( $has_stat ) : ?>
				<button type="button" class="ys-schedule" aria-label="<?php esc_attr_e( 'Sync schedule info', 'wpbuoy-video-sync' ); ?>">
					<div class="ys-schedule-tooltip" hidden>
						<?php if ( ! empty( $rule['scheduled_at'] ) && ! $rule_last_synced ) : ?><span class="ys-schedule-item"><span><?php esc_html_e( 'Created:', 'wpbuoy-video-sync' ); ?></span><span><?php echo esc_html( $rule_stat_created ); ?></span></span><?php endif; ?>
						<?php if ( $rule_last_synced ) : ?><span class="ys-schedule-item"><span><?php esc_html_e( 'Last Sync:', 'wpbuoy-video-sync' ); ?></span><span><?php echo esc_html( $rule_stat_last_sync ); ?></span></span><?php endif; ?>
					</div>
				</button>
			<?php endif; ?>
			<span class="dashicons dashicons-arrow-down-alt2 ys-accordion-icon" aria-hidden="true"></span>
		</div>
	</div>

	<div class="ys-rule-body">

		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-action-<?php echo esc_attr( $rule_index ); ?>">
					<?php esc_html_e( 'Action', 'wpbuoy-video-sync' ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'wpbuoy-video-sync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Choose what to sync from your YouTube channel.', 'wpbuoy-video-sync' ); ?></span>
					</span>
				</label>
				<select class="ys-select ys-action" id="ys-action-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][action]" required>
					<option value=""><?php esc_html_e( '— Select action —', 'wpbuoy-video-sync' ); ?></option>
					<option data-resource="video" value="videos_sync_new" <?php selected( $action, 'videos_sync_new' ); ?>><?php esc_html_e( 'Sync new videos', 'wpbuoy-video-sync' ); ?></option>
					<option data-resource="playlist" value="playlists_sync_new" <?php selected( $action, 'playlists_sync_new' ); ?>><?php esc_html_e( 'Sync new playlists', 'wpbuoy-video-sync' ); ?></option>
					<option data-resource="channel" value="channel_sync_new" <?php selected( $action, 'channel_sync_new' ); ?>><?php esc_html_e( 'Sync this channel', 'wpbuoy-video-sync' ); ?></option>
				</select>
				<p class="ys-quota-estimate ys-hidden"></p>
			</div>
			<div class="ys-form-group ys-items-per-run-wrapper<?php echo str_starts_with( $action, 'channel_' ) ? ' ys-hidden' : ''; ?>">
				<label for="ys-max-videos-<?php echo esc_attr( $rule_index ); ?>" class="ys-max-items-label">
					<?php echo esc_html( $_max_items_label ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'wpbuoy-video-sync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Maximum number of items to process per run. Set to 0 for unlimited.', 'wpbuoy-video-sync' ); ?></span>
					</span>
				</label>
				<span class="ys-limit-wrap">
					<input class="ys-number ys-max-videos-input" id="ys-max-videos-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][max_videos]" type="number" value="<?php echo esc_attr( $max_videos ); ?>" min="0">
					<span class="ys-unlimited-icon<?php echo 0 === $max_videos ? '' : ' ys-hidden'; ?>" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'wpbuoy-video-sync' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 24 24" width="20px" fill="#999" aria-hidden="true"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M18.6 6.62c-1.44 0-2.8.56-3.77 1.53L7.8 14.39c-.64.64-1.49.99-2.4.99-1.87 0-3.39-1.51-3.39-3.38S3.53 8.62 5.4 8.62c.91 0 1.76.35 2.44 1.03l1.13 1 1.51-1.34L9.22 8.2C8.2 7.18 6.84 6.62 5.4 6.62 2.42 6.62 0 9.04 0 12s2.42 5.38 5.4 5.38c1.44 0 2.8-.56 3.77-1.53l7.03-6.24c.64-.64 1.49-.99 2.4-.99 1.87 0 3.39 1.51 3.39 3.38s-1.52 3.38-3.39 3.38c-.9 0-1.76-.35-2.44-1.03l-1.14-1.01-1.51 1.34 1.27 1.12c1.02 1.01 2.37 1.57 3.82 1.57 2.98 0 5.4-2.41 5.4-5.38s-2.42-5.37-5.4-5.37z"/></svg>
					</span>
				</span>
			</div>
		</div>

		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-sync-schedule-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Sync schedule', 'wpbuoy-video-sync' ); ?></label>
				<input type="text" class="ys-text" id="ys-sync-schedule-<?php echo esc_attr( $rule_index ); ?>" value="<?php esc_attr_e( 'Once (run immediately after enabling and saving)', 'wpbuoy-video-sync' ); ?>" readonly disabled>
			</div>
		</div>

		<?php $_show_post_type = in_array( $action, array( 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' ), true ); ?>
		<div class="ys-2-columns ys-post-type-wrapper<?php echo $_show_post_type ? '' : ' ys-hidden'; ?>">
			<div class="ys-form-group">
				<label for="ys-dest-post-type-<?php echo esc_attr( $rule_index ); ?>" class="ys-post-type-label">
					<?php echo esc_html( $_post_type_label ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'wpbuoy-video-sync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'The WordPress post type that synced items will be created as.', 'wpbuoy-video-sync' ); ?></span>
					</span>
				</label>
				<select class="ys-select" id="ys-dest-post-type-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][destination_post_type]" required>
					<option value=""><?php esc_html_e( '— Select post type —', 'wpbuoy-video-sync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $destination_post_type, $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

	<?php if ( ! empty( $rule_sync_errors ) ) : ?>
	<div class="ys-rule-errors ys-mt-3">
		<?php foreach ( $rule_sync_errors as $err ) : ?>
		<p class="ys-mb-0 ys-rule-error-msg">
			<?php
			if ( ! empty( $err['timestamp'] ) ) {
				echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $err['timestamp'] ) ) . ' &mdash; ';
			}
			echo esc_html( $err['error'] ?? '' );
			if ( ! empty( $err['code'] ) ) {
				echo ' <code>' . esc_html( $err['code'] ) . '</code>';
			}
			?>
		</p>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	</div>

	<div class="ys-rule-footer">
		<button type="button" class="ys-remove-rule">
			<?php esc_html_e( 'Remove sync rule', 'wpbuoy-video-sync' ); ?>
		</button>
	</div>

</div>
