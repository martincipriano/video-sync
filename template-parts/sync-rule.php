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

$term_id    = isset( $term_id ) ? (int) $term_id : 0;
$source_type = isset( $source_type ) ? $source_type : 'channel';
$enabled         = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule        = isset( $rule['schedule'] ) ? $rule['schedule'] : 'once';
$custom_schedule = isset( $rule['custom_schedule'] ) ? $rule['custom_schedule'] : 1;
$action          = isset( $rule['action'] ) ? $rule['action'] : 'videos_sync_new';
$specific_metadata = isset( $rule['specific_metadata'] ) ? $rule['specific_metadata'] : array();

// Dual-mode: Use provided $index or fallback to {{INDEX}} placeholder for JavaScript
$rule_index = isset( $index ) ? $index : '{{INDEX}}';

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

?>

<div class="ys-sync-rule" data-rule-index="<?php echo esc_attr( $rule_index ); ?>">

	<div class="ys-sync-rule-header">
		<label class="ys-toggle">
			<input <?php checked( $enabled, true ); ?> class="ys-rule-toggle" name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][enabled]" type="checkbox" value="1">
			<span class="ys-toggle-slider"></span>
		</label>
		<button type="button" class="button ys-remove-rule">
			<?php esc_html_e( 'Remove', 'yousync' ); ?>
		</button>
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
		</div>

		<div class="ys-form-group <?php echo $show_specific_metadata ? '' : 'ys-hidden'; ?> ys-specific-metadata-wrapper">
			<label for="ys-specific-metadata-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Fields to Update', 'yousync' ); ?></label>
			<select class="ys-select ys-specific-metadata" id="ys-specific-metadata-<?php echo esc_attr( $rule_index ); ?>" name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][specific_metadata][]" multiple placeholder="<?php esc_attr_e( 'Select metadata to update...', 'yousync' ); ?>">
				<?php if ( $show_specific_metadata ) echo $metadata_options_html; ?>
			</select>
		</div>

	<?php
	$rule_sync_status = $rule['sync_status'] ?? '';
	$rule_last_synced = (int) ( $rule['last_synced'] ?? 0 );
	$rule_sync_errors = is_array( $rule['sync_errors'] ?? null ) ? $rule['sync_errors'] : array();
	$rule_next_run    = ( $term_id && '{{INDEX}}' !== $rule_index )
		? wp_next_scheduled( 'yousync_sync_rule', array( $source_type, $term_id, (int) $rule_index ) )
		: false;
	if ( $rule_sync_status || $rule_last_synced || $rule_next_run ) :
		$status_colors = array(
			'success' => '#00a32a',
			'failed'  => '#d63638',
		);
	?>
	<div class="ys-rule-history ys-mt-3">
		<?php if ( $rule_sync_status ) : ?>
		<p class="ys-mb-0">
			<strong><?php esc_html_e( 'Status:', 'yousync' ); ?></strong>
			<span style="color:<?php echo esc_attr( $status_colors[ $rule_sync_status ] ?? '#757575' ); ?>; font-weight:600;">
				<?php echo esc_html( ucfirst( str_replace( '_', ' ', $rule_sync_status ) ) ); ?>
			</span>
		</p>
		<?php endif; ?>
		<?php if ( $rule_last_synced ) : ?>
		<p class="ys-mb-0">
			<strong><?php esc_html_e( 'Last Synced:', 'yousync' ); ?></strong>
			<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rule_last_synced ) ); ?>
		</p>
		<?php endif; ?>
		<?php if ( $rule_next_run ) : ?>
		<p class="ys-mb-0">
			<strong><?php esc_html_e( 'Next Run:', 'yousync' ); ?></strong>
			<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rule_next_run ) ); ?>
		</p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<p class="ys-quota-estimate description ys-mb-0 ys-hidden"><strong><?php esc_html_e( 'Estimated Quota:', 'yousync' ); ?></strong> <span class="ys-quota-value"></span></p>

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

	</div>
</div>
