<?php
/**
 * Template part for displaying Channel sync rule.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var int|string $index Rule index.
 * @var array      $rule Rule data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term_id         = isset( $term_id ) ? (int) $term_id : 0;
$source_type     = isset( $source_type ) ? $source_type : 'channel';
$enabled         = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule        = isset( $rule['schedule'] ) ? $rule['schedule'] : 'once';
$custom_schedule = isset( $rule['custom_schedule'] ) ? $rule['custom_schedule'] : 1;
$action          = isset( $rule['action'] ) ? $rule['action'] : 'videos_sync_new';
$specific_metadata = isset( $rule['specific_metadata'] ) ? $rule['specific_metadata'] : array();

// Dual-mode: Use provided $index or fallback to {{INDEX}} placeholder for JavaScript
$rule_index = isset( $index ) ? $index : '{{INDEX}}';

// Auto-generated rule label from action + schedule
$_action_labels = array(
	'playlists_sync_new' => __( 'Sync new playlists', 'yousync' ),
	'videos_sync_new'    => __( 'Sync new videos', 'yousync' ),
);
$_schedule_suffixes = array(
	'once'    => __( 'immediately after saving', 'yousync' ),
	'hourly'  => __( 'every hour', 'yousync' ),
	'daily'   => __( 'every day', 'yousync' ),
	'weekly'  => __( 'every week', 'yousync' ),
	'monthly' => __( 'every month', 'yousync' ),
	/* translators: %d: number of hours */
	'custom'  => sprintf( __( 'every %d hours', 'yousync' ), $custom_schedule ),
);
$rule_action_label    = $action ? ( $_action_labels[ $action ] ?? $action ) : __( 'New rule', 'yousync' );
$rule_schedule_suffix = $_schedule_suffixes[ $schedule ] ?? $schedule;

$sync_started_at = isset( $rule['sync_started_at'] ) ? (int) $rule['sync_started_at'] : 0;
$is_syncing      = 'syncing' === ( $rule['sync_status'] ?? '' )
	&& $sync_started_at
	&& ( time() - $sync_started_at ) < 1800;

// Determine the resource type from the action (used for field options and metadata options)
$resource = '';
if ( strpos( $action, 'channel' ) === 0 ) {
	$resource = 'channel';
} elseif ( strpos( $action, 'playlists' ) === 0 ) {
	$resource = 'playlist';
} elseif ( strpos( $action, 'videos' ) === 0 ) {
	$resource = 'video';
}

// Specific metadata — show wrapper when action contains update_specific
$show_specific_metadata = $action && strpos( $action, 'update_specific' ) !== false;
$metadata_options_html  = '';
if ( $show_specific_metadata && $resource ) {
	$metadata_options_html = yousync_return_template_part( 'options', $resource . '-metadata' );
	// Mark saved values as selected
	foreach ( $specific_metadata as $saved_value ) {
		$metadata_options_html = str_replace(
			'value="' . esc_attr( $saved_value ) . '"',
			'value="' . esc_attr( $saved_value ) . '" selected',
			$metadata_options_html
		);
	}
}

// Sync status line
$rule_sync_status = $rule['sync_status'] ?? '';
$rule_last_synced = (int) ( $rule['last_synced'] ?? 0 );
$rule_sync_errors = is_array( $rule['sync_errors'] ?? null ) ? $rule['sync_errors'] : array();
$rule_next_run    = ( $term_id && '{{INDEX}}' !== $rule_index )
	? wp_next_scheduled( 'yousync_sync_rule', array( $source_type, $term_id, (int) $rule_index ) )
	: false;

$status_first  = '';
$status_second = '';

if ( 'success' === $rule_sync_status && $rule_last_synced ) {
	/* translators: %s: date and time */
	$status_first = sprintf( __( 'Synced on %s.', 'yousync' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rule_last_synced ) );
} elseif ( 'failed' === $rule_sync_status && $rule_last_synced ) {
	/* translators: %s: date and time */
	$status_first = sprintf( __( 'Sync failed on %s.', 'yousync' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rule_last_synced ) );
} else {
	$status_first = __( "This rule hasn't synced yet.", 'yousync' );
}

if ( $enabled && ! $is_syncing ) {
	if ( 'once' === $schedule && ! $rule_sync_status ) {
		$status_second = __( 'Will sync on save', 'yousync' );
	} elseif ( 'once' !== $schedule && $rule_next_run ) {
		/* translators: %s: human-readable time difference, e.g. "2 hours" */
		$status_second = $rule_sync_status
			? sprintf( __( 'Runs again in %s', 'yousync' ), human_time_diff( time(), $rule_next_run ) )
			: sprintf( __( 'Syncing in the next %s', 'yousync' ), human_time_diff( time(), $rule_next_run ) );
	}
}
?>

<div class="ys-sync-rule<?php echo $is_syncing ? ' ys-sync-rule--syncing' : ''; ?>" data-rule-index="<?php echo esc_attr( $rule_index ); ?>">

	<?php if ( $is_syncing ) : ?>
	<div class="ys-syncing-overlay" aria-hidden="true">
		<span class="ys-syncing-badge"><?php esc_html_e( 'Syncing...', 'yousync' ); ?></span>
	</div>
	<?php endif; ?>

	<div class="ys-sync-rule-header">
		<div class="ys-rule-info">
			<span class="ys-rule-label"><?php echo esc_html( $rule_action_label . ' ' . $rule_schedule_suffix . '.' ); ?></span>
			<p class="ys-rule-status"><?php echo esc_html( $status_first ); ?><?php if ( $status_second ) : ?> <span class="ys-runs-again"><?php echo esc_html( $status_second ); ?>.</span><?php endif; ?></p>
		</div>
		<div class="ys-rule-actions">
			<label class="ys-toggle">
				<input <?php checked( $enabled, true ); ?> class="ys-rule-toggle" name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][enabled]" type="checkbox" value="1">
				<span class="ys-toggle-slider"></span>
			</label>
		</div>
	</div>

	<div class="ys-sync-rule-body">
		<div class="ys-form-group">
			<label for="ys-sync-schedule-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Sync Schedule', 'yousync' ); ?></label>
			<select class="ys-select ys-sync-schedule" id="ys-sync-schedule-<?php echo esc_attr( $rule_index ); ?>" name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][schedule]" required>
				<?php yousync_get_template_part( 'options', 'schedule', array( 'selected' => $schedule ) ); ?>
			</select>
		</div>

		<div class="ys-form-group">
			<label for="ys-action-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Action', 'yousync' ); ?></label>
			<select class="ys-select ys-action" id="ys-action-<?php echo esc_attr( $rule_index ); ?>" name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][action]" required>
				<optgroup label="<?php esc_attr_e( 'Playlists', 'yousync' ); ?>">
					<option data-resource="playlist" value="playlists_sync_new" <?php selected( $action, 'playlists_sync_new' ); ?>><?php esc_html_e( 'Sync new playlists', 'yousync' ); ?></option>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Videos', 'yousync' ); ?>">
					<option data-resource="video" value="videos_sync_new" <?php selected( $action, 'videos_sync_new' ); ?>><?php esc_html_e( 'Sync new videos', 'yousync' ); ?></option>
				</optgroup>
			</select>
			<p class="ys-quota-estimate ys-hidden"></p>
		</div>

		<div class="ys-form-group <?php echo $show_specific_metadata ? '' : 'ys-hidden'; ?> ys-specific-metadata-wrapper">
			<label for="ys-specific-metadata-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Fields to Update', 'yousync' ); ?></label>
			<select class="ys-select ys-specific-metadata" id="ys-specific-metadata-<?php echo esc_attr( $rule_index ); ?>" name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][specific_metadata][]" multiple placeholder="<?php esc_attr_e( 'Select metadata to update...', 'yousync' ); ?>">
				<?php if ( $show_specific_metadata ) echo $metadata_options_html; ?>
			</select>
		</div>

	<?php if ( ! empty( $rule_sync_errors ) ) : ?>
	<div class="ys-rule-errors ys-mt-3" style="font-size:12px;">
		<?php foreach ( $rule_sync_errors as $err ) : ?>
		<p class="ys-mb-0" style="color:#d63638;">
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

	<?php if ( ! $is_syncing ) : ?>
	<div class="ys-sync-rule-footer">
		<button type="button" class="ys-remove-rule">
			<span class="dashicons dashicons-trash" aria-hidden="true"></span>
			<?php esc_html_e( 'Remove rule', 'yousync' ); ?>
		</button>
	</div>
	<?php endif; ?>

	</div>
</div>
