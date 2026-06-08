<?php
declare(strict_types=1);
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

$term_id     = isset( $term_id ) ? (int) $term_id : 0;
$source_type = isset( $source_type ) ? $source_type : 'channel';
$name_prefix = isset( $name_prefix ) ? $name_prefix : 'sync_rules';
$enabled           = isset( $rule['enabled'] ) ? $rule['enabled'] : true;
$schedule          = isset( $rule['schedule'] ) ? $rule['schedule'] : 'once';
$custom_schedule   = isset( $rule['custom_schedule'] ) ? $rule['custom_schedule'] : 1;
$action            = isset( $rule['action'] ) ? $rule['action'] : '';
$conditions        = isset( $rule['conditions'] ) ? $rule['conditions'] : array();
$specific_metadata = isset( $rule['specific_metadata'] ) ? $rule['specific_metadata'] : array();

$max_videos                 = isset( $rule['max_videos'] ) ? (int) $rule['max_videos'] : 50;
$destination_post_type      = $rule['destination_post_type'] ?? '';
$destination_taxonomy_terms = $rule['destination_taxonomy_terms'] ?? [];

$post_types = get_post_types( array( 'public' => true ), 'objects' );

// Dual-mode: Use provided $index or fallback to {{INDEX}} placeholder for JavaScript
$rule_index = isset( $index ) ? $index : '{{INDEX}}';

// Auto-generated rule label from action + schedule
$_action_labels = array(
	'channel_sync_new'            => __( 'Sync this channel', 'yousync' ),
	'channel_update_all'          => __( "Update this channel's details", 'yousync' ),
	'channel_update_specific'     => __( 'Update specific details of this channel', 'yousync' ),
	'playlists_sync_new'          => __( 'Sync new playlists', 'yousync' ),
	'playlists_update_all'        => __( 'Update all playlist details', 'yousync' ),
	'playlists_update_specific_all' => __( 'Update specific playlist details', 'yousync' ),
	'videos_sync_new'             => __( 'Sync new videos', 'yousync' ),
	'videos_update_all'           => __( 'Update all video details', 'yousync' ),
	'videos_update_specific_all'  => __( 'Update specific video details', 'yousync' ),
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

// License feature flags
$ys_has_scheduled_sync  = yousync_license()->is_feature_available( 'scheduled_sync' );
$ys_has_metadata_update = yousync_license()->is_feature_available( 'metadata_update' );
$ys_has_conditions      = yousync_license()->is_feature_available( 'conditions' );
$ys_has_field_mapping   = yousync_license()->is_feature_available( 'field_mapping' );
$ys_has_taxonomy        = yousync_license()->is_feature_available( 'taxonomy_terms' );

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
$metadata_options_html  = $resource ? yousync_return_template_part( 'options', $resource . '-metadata' ) : '';

// Field options HTML for conditions (based on resource)
$field_options_tpl = $resource ? yousync_return_template_part( 'options', $resource . '-fields' ) : '';

// Sync status line
$rule_sync_status = $rule['sync_status'] ?? '';
$rule_last_synced = (int) ( $rule['last_synced'] ?? 0 );
$rule_sync_errors = array_values( array_filter(
	is_array( $rule['sync_errors'] ?? null ) ? $rule['sync_errors'] : array(),
	fn( $e ) => ( $e['code'] ?? '' ) !== 'license_required'
) );
$is_option_channel  = isset( $is_option_channel ) ? (bool) $is_option_channel : false;
$option_channel_idx = isset( $option_channel_idx ) ? (int) $option_channel_idx : 0;

if ( '{{INDEX}}' === $rule_index ) {
	$rule_next_run = false;
} elseif ( $is_option_channel ) {
	$rule_next_run = wp_next_scheduled( 'yousync_channel_config_sync_rule', array( $option_channel_idx, (int) $rule_index ) );
} else {
	$rule_next_run = false;
}

$_df = 'F j, Y g:i A';
$rule_stat_created   = ! empty( $rule['scheduled_at'] ) ? wp_date( $_df, (int) $rule['scheduled_at'] ) : '—';
$rule_stat_last_sync = $rule_last_synced ? wp_date( $_df, $rule_last_synced ) : '—';
$rule_stat_next_sync = ( $rule_next_run && 'once' !== $schedule ) ? wp_date( $_df, $rule_next_run ) : '—';

// Dynamic labels
$_max_items_label = $resource === 'video'
	? __( 'Videos per run', 'yousync' )
	: ( $resource === 'playlist'
		? __( 'Playlists per run', 'yousync' )
		: __( 'Items per run', 'yousync' ) );

$_post_type_label = 'playlists_sync_new' === $action
	? __( 'Save synced playlists as post type', 'yousync' )
	: ( 'channel_sync_new' === $action
		? __( 'Save synced channel as post type', 'yousync' )
		: __( 'Save synced videos as post type', 'yousync' )
	);
?>

<div class="ys-rule<?php echo $is_syncing ? ' ys-rule--syncing' : ''; ?>" data-rule-index="<?php echo esc_attr( $rule_index ); ?>" data-ch-index="<?php echo esc_attr( (string) $option_channel_idx ); ?>">

	<?php if ( $is_syncing ) : ?>
	<div class="ys-syncing-overlay" aria-hidden="true">
		<span class="ys-syncing-badge"><?php esc_html_e( 'Syncing...', 'yousync' ); ?><span class="ys-syncing-progress"></span></span>
	</div>
	<?php endif; ?>

	<div class="ys-rule-header" role="button" tabindex="0" aria-expanded="true">
		<div class="ys-rule-heading-wrap">
			<div class="ys-rule-heading"><?php echo esc_html( $rule_action_label . ' ' . $rule_schedule_suffix . '.' ); ?></div>
			<?php if ( ! $enabled ) : ?>
			<p class="ys-rule-disabled-notice"><?php esc_html_e( 'This rule is disabled and won\'t run until re-enabled.', 'yousync' ); ?></p>
			<?php endif; ?>
		</div>
		<div class="ys-rule-actions">
			<label class="ys-toggle">
				<input <?php checked( $enabled, true ); ?> class="ys-rule-toggle" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][enabled]" type="checkbox" value="1">
				<span class="ys-toggle-slider"></span>
			</label>
			<?php $has_stat = ! empty( $rule['scheduled_at'] ) || $rule_last_synced || ( $rule_next_run && 'once' !== $schedule ); ?>
			<?php if ( $has_stat ) : ?>
				<button type="button" class="ys-schedule" aria-label="<?php esc_attr_e( 'Sync schedule info', 'yousync' ); ?>">
					<div class="ys-schedule-tooltip" hidden>
						<?php if ( ! empty( $rule['scheduled_at'] ) && ! $rule_last_synced ) : ?><span class="ys-schedule-item"><span><?php esc_html_e( 'Created:', 'yousync' ); ?></span><span><?php echo esc_html( $rule_stat_created ); ?></span></span><?php endif; ?>
						<?php if ( $rule_last_synced ) : ?><span class="ys-schedule-item"><span><?php esc_html_e( 'Last Sync:', 'yousync' ); ?></span><span><?php echo esc_html( $rule_stat_last_sync ); ?></span></span><?php endif; ?>
						<?php if ( $rule_next_run && 'once' !== $schedule ) : ?><span class="ys-schedule-item"><span><?php esc_html_e( 'Next Sync:', 'yousync' ); ?></span><span><?php echo esc_html( $rule_stat_next_sync ); ?></span></span><?php endif; ?>
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
					<?php esc_html_e( 'Action', 'yousync' ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Choose what to sync from your YouTube channel.', 'yousync' ); ?></span>
					</span>
				</label>
				<select class="ys-select ys-action" id="ys-action-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][action]" required>
					<option value=""><?php esc_html_e( '— Select action —', 'yousync' ); ?></option>
					<?php $mu = $ys_has_metadata_update ? '' : 'disabled'; ?>
					<optgroup label="<?php esc_attr_e( 'Videos', 'yousync' ); ?>">
						<option data-resource="video" value="videos_sync_new" <?php selected( $action, 'videos_sync_new' ); ?>><?php esc_html_e( 'Sync new videos', 'yousync' ); ?></option>
						<option data-resource="video" <?php echo esc_attr( $mu ); ?> value="videos_update_all" <?php selected( $action, 'videos_update_all' ); ?>><?php esc_html_e( 'Update all video details', 'yousync' ); ?><?php echo $ys_has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
						<option data-resource="video" <?php echo esc_attr( $mu ); ?> value="videos_update_specific_all" <?php selected( $action, 'videos_update_specific_all' ); ?>><?php esc_html_e( 'Update specific video details', 'yousync' ); ?><?php echo $ys_has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Playlists', 'yousync' ); ?>">
						<option data-resource="playlist" value="playlists_sync_new" <?php selected( $action, 'playlists_sync_new' ); ?>><?php esc_html_e( 'Sync new playlists', 'yousync' ); ?></option>
						<option data-resource="playlist" <?php echo esc_attr( $mu ); ?> value="playlists_update_all" <?php selected( $action, 'playlists_update_all' ); ?>><?php esc_html_e( 'Update all playlist details', 'yousync' ); ?><?php echo $ys_has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
						<option data-resource="playlist" <?php echo esc_attr( $mu ); ?> value="playlists_update_specific_all" <?php selected( $action, 'playlists_update_specific_all' ); ?>><?php esc_html_e( 'Update specific playlist details', 'yousync' ); ?><?php echo $ys_has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Channel', 'yousync' ); ?>">
							<option data-resource="channel" value="channel_sync_new" <?php selected( $action, 'channel_sync_new' ); ?>><?php esc_html_e( 'Sync this channel', 'yousync' ); ?></option>
						<option data-resource="channel" <?php echo esc_attr( $mu ); ?> value="channel_update_all" <?php selected( $action, 'channel_update_all' ); ?>><?php esc_html_e( "Update this channel's details", 'yousync' ); ?><?php echo $ys_has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
						<option data-resource="channel" <?php echo esc_attr( $mu ); ?> value="channel_update_specific" <?php selected( $action, 'channel_update_specific' ); ?>><?php esc_html_e( 'Update specific details of this channel', 'yousync' ); ?><?php echo $ys_has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
					</optgroup>
				</select>
				<p class="ys-quota-estimate ys-hidden"></p>
			</div>
			<div class="ys-form-group ys-items-per-run-wrapper<?php echo str_starts_with( $action, 'channel_' ) ? ' ys-hidden' : ''; ?>">
				<label for="ys-max-videos-<?php echo esc_attr( $rule_index ); ?>" class="ys-max-items-label">
					<?php echo esc_html( $_max_items_label ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Maximum number of items to process per run. Set to 0 for unlimited.', 'yousync' ); ?></span>
					</span>
				</label>
				<span class="ys-limit-wrap">
					<input class="ys-number ys-max-videos-input" id="ys-max-videos-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][max_videos]" type="number" value="<?php echo esc_attr( $max_videos ); ?>" min="0">
					<span class="ys-unlimited-icon<?php echo 0 === $max_videos ? '' : ' ys-hidden'; ?>" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'yousync' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 24 24" width="20px" fill="#999" aria-hidden="true"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M18.6 6.62c-1.44 0-2.8.56-3.77 1.53L7.8 14.39c-.64.64-1.49.99-2.4.99-1.87 0-3.39-1.51-3.39-3.38S3.53 8.62 5.4 8.62c.91 0 1.76.35 2.44 1.03l1.13 1 1.51-1.34L9.22 8.2C8.2 7.18 6.84 6.62 5.4 6.62 2.42 6.62 0 9.04 0 12s2.42 5.38 5.4 5.38c1.44 0 2.8-.56 3.77-1.53l7.03-6.24c.64-.64 1.49-.99 2.4-.99 1.87 0 3.39 1.51 3.39 3.38s-1.52 3.38-3.39 3.38c-.9 0-1.76-.35-2.44-1.03l-1.14-1.01-1.51 1.34 1.27 1.12c1.02 1.01 2.37 1.57 3.82 1.57 2.98 0 5.4-2.41 5.4-5.38s-2.42-5.37-5.4-5.37z"/></svg>
					</span>
				</span>
			</div>
		</div>

		<div class="ys-2-columns <?php echo $show_specific_metadata ? '' : 'ys-hidden'; ?> ys-specific-metadata-wrapper">
			<div class="ys-form-group">
				<label>
					<?php esc_html_e( 'Details to update', 'yousync' ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Select which details to pull from YouTube and save.', 'yousync' ); ?></span>
					</span>
				</label>
				<div class="ys-specific-metadata-rows" data-resource="<?php echo esc_attr( $resource ); ?>"><?php foreach ( $specific_metadata as $saved_value ) :
						$row_opts = $metadata_options_html ? str_replace(
							'value="' . esc_attr( $saved_value ) . '"',
							'value="' . esc_attr( $saved_value ) . '" selected',
							$metadata_options_html
						) : '';
					?>
					<div class="ys-specific-metadata-row">
						<select class="ys-select ys-specific-metadata" name="<?php echo esc_attr( "{$name_prefix}[{$rule_index}][specific_metadata][]" ); ?>">
							<option value=""><?php esc_html_e( '— Select detail —', 'yousync' ); ?></option>
							<?php echo $row_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</select>
						<button type="button" class="ys-remove-metadata-field" aria-label="<?php esc_attr_e( 'Remove', 'yousync' ); ?>"></button>
					</div>
					<?php endforeach; ?></div>
				<button type="button" class="ys-add-metadata-field"><?php esc_html_e( 'Add detail', 'yousync' ); ?></button>
			</div>
		</div>

		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-sync-schedule-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Sync schedule', 'yousync' ); ?></label>
				<select class="ys-select ys-sync-schedule" id="ys-sync-schedule-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][schedule]" required>
					<?php yousync_get_template_part( 'options', 'schedule', array( 'selected' => $schedule, 'can_schedule' => $ys_has_scheduled_sync ) ); ?>
				</select>
			</div>
			<div class="ys-form-group ys-custom-schedule-wrapper<?php echo 'custom' !== $schedule ? ' ys-hidden' : ''; ?>">
				<label for="ys-custom-schedule-<?php echo esc_attr( $rule_index ); ?>"><?php esc_html_e( 'Custom (Hours)', 'yousync' ); ?></label>
				<input class="ys-number ys-custom-sync-schedule" id="ys-custom-schedule-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][custom_schedule]" value="<?php echo esc_attr( $custom_schedule ); ?>" min="1" placeholder="<?php esc_attr_e( 'Eg. 24', 'yousync' ); ?>" type="number">
			</div>
		</div>

		<?php $_show_post_type = in_array( $action, array( 'videos_sync_new', 'playlists_sync_new', 'channel_sync_new' ), true ); ?>
		<div class="ys-2-columns ys-post-type-wrapper<?php echo $_show_post_type ? '' : ' ys-hidden'; ?>">
			<div class="ys-form-group">
				<label for="ys-dest-post-type-<?php echo esc_attr( $rule_index ); ?>" class="ys-post-type-label">
					<?php echo esc_html( $_post_type_label ); ?>
					<span class="ys-help-wrap">
						<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
						<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'The WordPress post type that synced items will be created as.', 'yousync' ); ?></span>
					</span>
				</label>
				<select class="ys-select" id="ys-dest-post-type-<?php echo esc_attr( $rule_index ); ?>" name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][destination_post_type]" required>
					<option value=""><?php esc_html_e( '— Select post type —', 'yousync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $destination_post_type, $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="ys-field-note description"><?php esc_html_e( 'Required — synced items are saved as this post type. If it is left unset (or the post type is later removed), the rule will not sync and the error is logged to the sync history.', 'yousync' ); ?></p>
			</div>
		</div>

		<?php
		if ( $destination_post_type ) {
			$_pt_tax_names         = get_object_taxonomies( $destination_post_type );
			$_all_public_taxes     = get_taxonomies( [ 'public' => true ], 'objects' );
			$_available_taxonomies = array_intersect_key( $_all_public_taxes, array_flip( $_pt_tax_names ) );
		} else {
			$_available_taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		}
		$_show_tax_terms = ! empty( $destination_post_type ) && ! empty( $_available_taxonomies );
		?>
		<div class="ys-form-group ys-taxonomy-terms-wrapper<?php echo ! $ys_has_taxonomy ? ' ys-taxonomy-terms-locked' : ''; ?><?php echo $_show_tax_terms ? '' : ' ys-hidden'; ?>">
			<label>
				<?php esc_html_e( 'Assign to taxonomy terms', 'yousync' ); ?>
				<span class="ys-help-wrap">
					<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
					<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Assign synced items to categories, tags, or other taxonomy terms.', 'yousync' ); ?></span>
				</span>
			</label>
			<div class="ys-taxonomy-terms"><?php foreach ( $destination_taxonomy_terms as $tt_index => $tt ) :
					$tt_taxonomy = sanitize_key( $tt['taxonomy'] ?? '' );
					$tt_term_ids = array_map( 'absint', (array) ( $tt['term_ids'] ?? [] ) );
					$tt_terms    = $tt_taxonomy ? get_terms( [ 'taxonomy' => $tt_taxonomy, 'hide_empty' => false ] ) : [];

					$_tax_opts = '<option value="">' . esc_html__( '&mdash; Select taxonomy &mdash;', 'yousync' ) . '</option>';
					foreach ( $_available_taxonomies as $tax ) {
						$_tax_opts .= '<option value="' . esc_attr( $tax->name ) . '"' . selected( $tt_taxonomy, $tax->name, false ) . '>' . esc_html( $tax->labels->singular_name ) . '</option>';
					}

					$_term_opts = '<option value="">' . esc_html__( '&mdash; Select term &mdash;', 'yousync' ) . '</option>';
					if ( ! is_wp_error( $tt_terms ) ) {
						foreach ( $tt_terms as $term ) {
							$_term_opts .= '<option value="' . esc_attr( $term->term_id ) . '"' . ( in_array( $term->term_id, $tt_term_ids, true ) ? ' selected' : '' ) . '>' . esc_html( $term->name ) . '</option>';
						}
					}

					if ( $ys_has_taxonomy ) : ?>
				<div class="ys-taxonomy-term-row">
					<select name="<?php echo esc_attr( "{$name_prefix}[{$rule_index}][destination_taxonomy_terms][{$tt_index}][taxonomy]" ); ?>" class="ys-select ys-taxonomy-select"><?php echo $_tax_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
					<div class="ys-term-select-wrapper">
						<select name="<?php echo esc_attr( "{$name_prefix}[{$rule_index}][destination_taxonomy_terms][{$tt_index}][term_ids][]" ); ?>" class="ys-select ys-term-select"<?php echo $tt_taxonomy ? '' : ' disabled'; ?>><?php echo $_term_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
					</div>
					<button type="button" class="ys-remove-taxonomy-term" aria-label="<?php esc_attr_e( 'Remove', 'yousync' ); ?>"></button>
				</div>
				<?php else : ?>
				<div class="ys-taxonomy-term-row ys-taxonomy-term-row--locked">
					<div class="ys-tax-locked-cell">
						<select class="ys-select ys-taxonomy-select" disabled><?php echo $_tax_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
						<div class="ys-tax-locked-overlay" aria-hidden="true"></div>
					</div>
					<div class="ys-tax-locked-cell ys-term-select-wrapper">
						<select class="ys-select ys-term-select" disabled><?php echo $_term_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
						<div class="ys-tax-locked-overlay" aria-hidden="true"></div>
					</div>
					<button type="button" class="ys-remove-taxonomy-term-locked" aria-label="<?php esc_attr_e( 'Remove', 'yousync' ); ?>"></button>
				</div>
				<?php endif;
				endforeach; ?></div>
			<button type="button" class="<?php echo $ys_has_taxonomy ? 'ys-add-taxonomy-term' : 'ys-add-taxonomy-term-locked'; ?>">
				<?php esc_html_e( 'Add taxonomy term', 'yousync' ); ?>
			</button>
		</div>

	<?php
	$_rule_field_mapping  = $rule['field_mapping'] ?? array();
	$_fm_allowed_sources  = 'playlist' === $resource ? array(
		'playlist_title'       => __( 'Title', 'yousync' ),
		'playlist_description' => __( 'Description', 'yousync' ),
		'playlist_video_count' => __( 'Video Count', 'yousync' ),
		'playlist_thumbnail'   => __( 'Thumbnail URL', 'yousync' ),
	) : array(
		'title'         => __( 'Title', 'yousync' ),
		'description'   => __( 'Description', 'yousync' ),
		'duration'      => __( 'Duration (seconds)', 'yousync' ),
		'view_count'    => __( 'View Count', 'yousync' ),
		'like_count'    => __( 'Like Count', 'yousync' ),
		'published_at'  => __( 'Published Date', 'yousync' ),
		'thumbnail_url' => __( 'Thumbnail URL', 'yousync' ),
		'channel_title' => __( 'Channel Title', 'yousync' ),
	);
	$_fm_btn_label = 'playlist' === $resource
		? __( 'Add playlist detail mapping', 'yousync' )
		: __( 'Add video detail mapping', 'yousync' );
	$_rule_fm_html = '';
	$_rule_fm_idx  = 0;
	foreach ( $_rule_field_mapping as $fm_row ) {
		$fm_source = $fm_row['source'] ?? '';
		$fm_target = $fm_row['target'] ?? '';
		if ( in_array( $fm_target, array( 'post_title', 'post_content', 'post_excerpt' ), true ) ) {
			continue;
		}

		$_source_opts = '<option value="">' . esc_html__( '— Source —', 'yousync' ) . '</option>';
		foreach ( $_fm_allowed_sources as $_src_val => $_src_label ) {
			$_source_opts .= '<option value="' . esc_attr( $_src_val ) . '"' . selected( $fm_source, $_src_val, false ) . '>' . esc_html( $_src_label ) . '</option>';
		}

		if ( $ys_has_field_mapping ) {
			$_fm_name_src  = esc_attr( "{$name_prefix}[{$rule_index}][field_mapping][{$_rule_fm_idx}][source]" );
			$_fm_name_tgt  = esc_attr( "{$name_prefix}[{$rule_index}][field_mapping][{$_rule_fm_idx}][target]" );
			$_rule_fm_html .= '<div class="ys-field-mapping-row">';
			$_rule_fm_html .= '<select class="ys-select ys-rule-fm-source" name="' . $_fm_name_src . '">' . $_source_opts . '</select>';
			$_rule_fm_html .= '<input type="text" class="ys-text ys-fm-meta-key" name="' . $_fm_name_tgt . '" value="' . esc_attr( $fm_target ) . '" placeholder="' . esc_attr__( 'e.g. _yousync_duration', 'yousync' ) . '">';
			$_rule_fm_html .= '<button type="button" class="ys-remove-field-mapping-row" aria-label="' . esc_attr__( 'Remove', 'yousync' ) . '"></button>';
			$_rule_fm_html .= '</div>';
		} else {
			$_placeholder  = esc_attr__( 'e.g. _yousync_duration', 'yousync' );
			$_rule_fm_html .= '<div class="ys-field-mapping-row ys-field-mapping-row--locked">';
			$_rule_fm_html .= '<div class="ys-fm-locked-cell"><select class="ys-select ys-rule-fm-source" disabled>' . $_source_opts . '</select><div class="ys-fm-locked-overlay" aria-hidden="true"></div></div>';
			$_rule_fm_html .= '<div class="ys-fm-locked-cell"><input type="text" class="ys-text ys-fm-meta-key" value="' . esc_attr( $fm_target ) . '" placeholder="' . $_placeholder . '" disabled readonly><div class="ys-fm-locked-overlay" aria-hidden="true"></div></div>';
			$_rule_fm_html .= '<button type="button" class="ys-remove-field-mapping-row-locked" aria-label="' . esc_attr__( 'Remove', 'yousync' ) . '"></button>';
			$_rule_fm_html .= '</div>';
		}

		$_rule_fm_idx++;
	}
	$_rule_fm_name_prefix = esc_attr( "{$name_prefix}[{$rule_index}][field_mapping]" );
	?>
	<?php
	$_fm_mapping_labels = array(
		'video'    => __( 'Map video details to post metadata', 'yousync' ),
		'playlist' => __( 'Map playlist details to post metadata', 'yousync' ),
		'channel'  => __( 'Map channel details to post metadata', 'yousync' ),
	);
	$_fm_mapping_label = $_fm_mapping_labels[ $resource ] ?? __( 'Map YouTube details to post metadata', 'yousync' );
	?>
	<div class="ys-form-group ys-field-mapping-wrapper<?php echo ! $ys_has_field_mapping ? ' ys-field-mapping-locked' : ''; ?><?php echo ( $action && strpos( $action, '_update_' ) !== false ) ? ' ys-hidden' : ''; ?>">
		<label class="ys-fm-mapping-label"><?php echo esc_html( $_fm_mapping_label ); ?></label>
		<div class="ys-field-mapping-rows ys-rule-field-mapping-rows" data-name-prefix="<?php echo $_rule_fm_name_prefix; ?>"><?php echo $_rule_fm_html; ?></div>
		<button type="button" class="<?php echo $ys_has_field_mapping ? 'ys-add-field-mapping-row ys-rule-add-field-mapping-row' : 'ys-add-field-mapping-row-locked'; ?>"><span class="material-icons-outlined" aria-hidden="true">account_tree</span><?php echo esc_html( $_fm_btn_label ); ?></button>
	</div>

		<div class="ys-form-group ys-conditions-wrapper<?php echo $ys_has_conditions ? '' : ' ys-conditions-locked'; ?><?php echo ( ! $action || str_starts_with( $action, 'channel_' ) ) ? ' ys-hidden' : ''; ?>">
			<label class="ys-conditions-label">
				<?php esc_html_e( 'Filter conditions', 'yousync' ); ?>
				<span class="ys-help-wrap">
					<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
					<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Only sync items that match all of the following conditions.', 'yousync' ); ?></span>
				</span>
			</label>
			<div class="ys-conditions" data-rule-index="<?php echo esc_attr( $rule_index ); ?>" data-resource="<?php echo esc_attr( $resource ); ?>"><?php
			if ( ! empty( $conditions ) && is_array( $conditions ) ) :
				foreach ( $conditions as $condition_index => $condition ) :
					$cond_field    = isset( $condition['field'] ) ? $condition['field'] : '';
					$cond_operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
					$cond_value    = isset( $condition['value'] ) ? $condition['value'] : '';

					// Build field options with saved field pre-selected
					$cond_field_options_html = $field_options_tpl ?: null;
					if ( $cond_field_options_html && $cond_field ) {
						$cond_field_options_html = str_replace(
							'<option disabled selected value="">',
							'<option disabled value="">',
							$cond_field_options_html
						);
						$cond_field_options_html = str_replace(
							'value="' . esc_attr( $cond_field ) . '"',
							'value="' . esc_attr( $cond_field ) . '" selected',
							$cond_field_options_html
						);
					}

					// Build operator options and value input if a field is selected
					$cond_operator_html = null;
					$cond_value_html    = null;
					if ( $cond_field ) {
						$field_type = yousync_get_condition_field_type( $cond_field );
						if ( $field_type ) {
							$cond_operator_html = yousync_return_template_part(
								'options',
								$field_type . '-operators',
								array( 'operator' => $cond_operator )
							);
							$cond_value_html = yousync_return_template_part(
								'input',
								$field_type,
								array(
									'rule_index'      => $rule_index,
									'condition_index' => $condition_index,
									'value'           => $cond_value,
									'disabled'        => ! $ys_has_conditions,
									'name_prefix'     => $name_prefix,
								)
							);
						}
					}

					$cond_template = $ys_has_conditions ? 'condition' : 'condition-locked';
					yousync_get_template_part( 'sync-rule', $cond_template, array(
						'rule_index'         => $rule_index,
						'condition_index'    => $condition_index,
						'condition'          => $condition,
						'field_options_html' => $cond_field_options_html,
						'operator_html'      => $cond_operator_html,
						'value_html'         => $cond_value_html,
						'name_prefix'        => $name_prefix,
					) );
				endforeach;
			endif;
			?></div>

			<button type="button" class="<?php echo $ys_has_conditions ? 'ys-add-condition' : 'ys-add-condition-locked'; ?>"><?php esc_html_e( 'Add condition', 'yousync' ); ?></button>
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
			<?php esc_html_e( 'Remove sync rule', 'yousync' ); ?>
		</button>
	</div>

</div>
