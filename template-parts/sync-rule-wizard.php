<?php
declare(strict_types=1);
/**
 * Template part: 5-step sync rule wizard.
 *
 * Rendered once per channel group, hidden by default. JavaScript reveals it
 * when the user clicks "Add sync rule" and hides it on cancel/completion.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var int   $ch_index              Channel index.
 * @var array $default_field_mapping Per-channel field mapping (from channel config).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_types           = get_post_types( array( 'public' => true ), 'objects' );
$available_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

$default_post_type      = $default_post_type ?? '';
$default_taxonomy_terms = $default_taxonomy_terms ?? array();
$has_scheduled_sync  = false;
$has_conditions      = false;
$has_metadata_update = false;

$mu = $has_metadata_update ? '' : 'disabled';

$allowed_sources = array(
	'title'         => __( 'Title', 'yousync' ),
	'description'   => __( 'Description', 'yousync' ),
	'duration'      => __( 'Duration (seconds)', 'yousync' ),
	'view_count'    => __( 'View Count', 'yousync' ),
	'like_count'    => __( 'Like Count', 'yousync' ),
	'published_at'  => __( 'Published Date', 'yousync' ),
	'thumbnail_url' => __( 'Thumbnail URL', 'yousync' ),
	'channel_title' => __( 'Channel Title', 'yousync' ),
);
?>
<div class="ys-wizard ys-hidden" data-channel-index="<?php echo esc_attr( $ch_index ); ?>" data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>">

	<div class="ys-wizard-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Wizard progress', 'yousync' ); ?>">
		<span class="ys-wizard-step-indicator ys-wizard-step-indicator--active" data-step="1">1</span>
		<span class="ys-wizard-progress-line"></span>
		<span class="ys-wizard-step-indicator" data-step="2">2</span>
		<span class="ys-wizard-progress-line"></span>
		<span class="ys-wizard-step-indicator" data-step="3">3</span>
		<span class="ys-wizard-progress-line"></span>
		<span class="ys-wizard-step-indicator" data-step="4">4</span>
		<span class="ys-wizard-progress-line"></span>
		<span class="ys-wizard-step-indicator" data-step="5">5</span>
	</div>

	<div class="ys-wizard-panels">

	<?php /* Step 1 — Action */ ?>
	<div class="ys-wizard-panel" data-step="1">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'What should this rule do?', 'yousync' ); ?></h3>
		</div>
		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-wizard-action-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Action', 'yousync' ); ?></label>
				<select id="ys-wizard-action-<?php echo esc_attr( $ch_index ); ?>" class="ys-select ys-wizard-action-select">
					<option value=""><?php esc_html_e( '— Select action —', 'yousync' ); ?></option>
					<optgroup label="<?php esc_attr_e( 'Videos', 'yousync' ); ?>">
						<option data-resource="video" value="videos_sync_new"><?php esc_html_e( 'Sync new videos', 'yousync' ); ?></option>
						<option data-resource="video" <?php echo esc_attr( $mu ); ?> value="videos_update_all"><?php esc_html_e( 'Update all video details', 'yousync' ); ?><?php echo $has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
						<option data-resource="video" <?php echo esc_attr( $mu ); ?> value="videos_update_specific_all"><?php esc_html_e( 'Update specific video details', 'yousync' ); ?><?php echo $has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Playlists', 'yousync' ); ?>">
						<option data-resource="playlist" value="playlists_sync_new"><?php esc_html_e( 'Sync new playlists', 'yousync' ); ?></option>
						<option data-resource="playlist" <?php echo esc_attr( $mu ); ?> value="playlists_update_all"><?php esc_html_e( 'Update all playlist details', 'yousync' ); ?><?php echo $has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
						<option data-resource="playlist" <?php echo esc_attr( $mu ); ?> value="playlists_update_specific_all"><?php esc_html_e( 'Update specific playlist details', 'yousync' ); ?><?php echo $has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
					</optgroup>
					<optgroup label="<?php esc_attr_e( 'Channel', 'yousync' ); ?>">
						<option data-resource="channel" value="channel_sync_new"><?php esc_html_e( 'Sync this channel', 'yousync' ); ?></option>
						<option data-resource="channel" <?php echo esc_attr( $mu ); ?> value="channel_update_all"><?php esc_html_e( "Update this channel's details", 'yousync' ); ?><?php echo $has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
						<option data-resource="channel" <?php echo esc_attr( $mu ); ?> value="channel_update_specific"><?php esc_html_e( 'Update specific details of this channel', 'yousync' ); ?><?php echo $has_metadata_update ? '' : esc_html__( ' (Pro)', 'yousync' ); ?></option>
					</optgroup>
				</select>
			</div>
			<div class="ys-form-group ys-items-per-run-wrapper">
				<label for="ys-wizard-max-videos-<?php echo esc_attr( $ch_index ); ?>" class="ys-max-items-label"><?php esc_html_e( 'Items per run', 'yousync' ); ?></label>
				<div class="ys-limit-wrap">
					<input id="ys-wizard-max-videos-<?php echo esc_attr( $ch_index ); ?>" class="ys-number ys-wizard-max-videos ys-max-videos-input" type="number" value="50" min="0">
					<span class="ys-unlimited-icon ys-hidden" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'yousync' ); ?>">∞</span>
				</div>
			</div>
		</div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-cancel"><?php esc_html_e( 'Cancel', 'yousync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-next" data-step="1"><?php esc_html_e( 'Next →', 'yousync' ); ?></button>
		</div>
	</div>

	<?php /* Step 2 — Schedule */ ?>
	<div class="ys-wizard-panel ys-hidden" data-step="2">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'When should this rule run?', 'yousync' ); ?></h3>
		</div>
		<div class="ys-form-group ys-wizard-specific-metadata-wrapper ys-hidden">
			<label><?php esc_html_e( 'Details to update', 'yousync' ); ?></label>
			<div class="ys-specific-metadata-rows ys-wizard-specific-metadata-rows" data-resource="video"></div>
			<button type="button" class="ys-add-metadata-field"><?php esc_html_e( 'Add detail', 'yousync' ); ?></button>
		</div>
		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-wizard-schedule-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Sync schedule', 'yousync' ); ?></label>
				<select id="ys-wizard-schedule-<?php echo esc_attr( $ch_index ); ?>" class="ys-select ys-wizard-schedule-select">
					<?php yousync_get_template_part( 'options', 'schedule', array( 'selected' => 'daily', 'can_schedule' => $has_scheduled_sync ) ); ?>
				</select>
			</div>
			<div class="ys-form-group ys-wizard-custom-schedule-wrapper ys-hidden">
				<label for="ys-wizard-custom-schedule-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Custom (Hours)', 'yousync' ); ?></label>
				<input id="ys-wizard-custom-schedule-<?php echo esc_attr( $ch_index ); ?>" class="ys-number ys-wizard-custom-schedule" type="number" value="24" min="1" placeholder="24">
			</div>
		</div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-back" data-step="2"><?php esc_html_e( '← Back', 'yousync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-next" data-step="2"><?php esc_html_e( 'Next →', 'yousync' ); ?></button>
		</div>
	</div>

	<?php /* Step 3 — Destination */ ?>
	<div class="ys-wizard-panel ys-hidden" data-step="3">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'Where should synced items be saved?', 'yousync' ); ?></h3>
		</div>
		<div class="ys-form-group ys-wizard-post-type-wrapper">
			<label for="ys-wizard-post-type-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Post Type', 'yousync' ); ?></label>
			<select id="ys-wizard-post-type-<?php echo esc_attr( $ch_index ); ?>" class="ys-select ys-wizard-post-type">
				<option value=""><?php esc_html_e( '— Select post type —', 'yousync' ); ?></option>
				<?php foreach ( $post_types as $pt ) : ?>
				<option value="<?php echo esc_attr( $pt->name ); ?>"<?php selected( $default_post_type, $pt->name ); ?>>
					<?php echo esc_html( $pt->labels->singular_name ); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="ys-form-group ys-wizard-taxonomy-terms-wrapper ys-hidden">
			<label><?php esc_html_e( 'Assign to taxonomy terms', 'yousync' ); ?></label>
			<div class="ys-taxonomy-terms ys-wizard-taxonomy-terms"></div>
			<button type="button" class="ys-add-taxonomy-term ys-wizard-add-taxonomy-term">
				<?php esc_html_e( 'Add taxonomy term', 'yousync' ); ?>
			</button>
		</div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-back" data-step="3"><?php esc_html_e( '← Back', 'yousync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-next" data-step="3"><?php esc_html_e( 'Next →', 'yousync' ); ?></button>
		</div>
	</div>

	<?php /* Step 4 — Field Mapping */ ?>
	<div class="ys-wizard-panel ys-hidden" data-step="4">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'Store YouTube details as custom meta', 'yousync' ); ?></h3>
			<p class="ys-wizard-subtitle"><?php esc_html_e( 'All YouTube details are always saved under internal _yousync_* meta keys. Add rows here only if you also need the data stored under a different key name (e.g. for ACF or a custom theme template).', 'yousync' ); ?></p>
		</div>
		<div class="ys-form-group">
			<label class="ys-fm-mapping-label"><?php esc_html_e( 'Map YouTube details to post metadata', 'yousync' ); ?></label>
			<?php
			$wiz_fm_html = '';
			foreach ( $default_field_mapping as $fm_row ) {
				$fm_source = $fm_row['source'] ?? '';
				$fm_target = $fm_row['target'] ?? '';
				if ( in_array( $fm_target, array( 'post_title', 'post_content', 'post_excerpt' ), true ) ) {
					continue;
				}
				$wiz_fm_html .= '<div class="ys-field-mapping-row">';
				$wiz_fm_html .= '<select class="ys-select ys-wizard-fm-source">';
				$wiz_fm_html .= '<option value="">' . esc_html__( '— Source —', 'yousync' ) . '</option>';
				foreach ( $allowed_sources as $src_val => $src_label ) {
					$wiz_fm_html .= '<option value="' . esc_attr( $src_val ) . '"' . selected( $fm_source, $src_val, false ) . '>' . esc_html( $src_label ) . '</option>';
				}
				$wiz_fm_html .= '</select>';
				$wiz_fm_html .= '<input type="text" class="ys-text ys-fm-meta-key" value="' . esc_attr( $fm_target ) . '" placeholder="' . esc_attr__( 'e.g. _yousync_duration', 'yousync' ) . '">';
				$wiz_fm_html .= '<button type="button" class="ys-remove-field-mapping-row" aria-label="' . esc_attr__( 'Remove', 'yousync' ) . '"></button>';
				$wiz_fm_html .= '</div>';
			}
			?>
			<div class="ys-wizard-field-mapping-rows ys-field-mapping-rows"><?php echo $wiz_fm_html; ?></div>
			<button type="button" class="ys-add-field-mapping-row"><span class="material-icons-outlined" aria-hidden="true">account_tree</span><?php esc_html_e( 'Add video detail mapping', 'yousync' ); ?></button>
		</div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-back" data-step="4"><?php esc_html_e( '← Back', 'yousync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-next" data-step="4"><?php esc_html_e( 'Next →', 'yousync' ); ?></button>
		</div>
	</div>

	<?php /* Step 5 — Conditions */ ?>
	<div class="ys-wizard-panel ys-hidden" data-step="5">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'Add filter conditions (optional)', 'yousync' ); ?></h3>
			<?php if ( $has_conditions ) : ?>
			<p class="ys-wizard-subtitle"><?php esc_html_e( 'Only sync items that match all of these conditions. Leave empty to sync everything.', 'yousync' ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( ! $has_conditions ) : ?>
		<p class="description"><?php esc_html_e( 'Filter conditions require an active Pro license.', 'yousync' ); ?></p>
		<?php else : ?>
		<div class="ys-wizard-conditions-wrap">
			<div class="ys-wizard-conditions ys-conditions"></div>
			<button type="button" class="ys-add-condition ys-wizard-add-condition"><?php esc_html_e( 'Add condition', 'yousync' ); ?></button>
		</div>
		<?php endif; ?>
		<div class="ys-wizard-error ys-hidden"></div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-back" data-step="5"><?php esc_html_e( '← Back', 'yousync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-finish"><?php esc_html_e( 'Add Rule', 'yousync' ); ?></button>
		</div>
	</div>

	</div><?php /* .ys-wizard-panels */ ?>

	<?php /* Hidden template: default FM rows restored on each wizard open */ ?>
	<div class="ys-wizard-default-fm-template" hidden><?php echo $wiz_fm_html; ?></div>

	<?php /* Hidden template: default taxonomy term rows restored on each wizard open */ ?>
	<?php
	$wiz_tax_html = '';
	foreach ( $default_taxonomy_terms as $tt ) {
		$tt_taxonomy = sanitize_key( $tt['taxonomy'] ?? '' );
		$tt_term_ids = array_map( 'absint', (array) ( $tt['term_ids'] ?? array() ) );
		if ( ! $tt_taxonomy ) continue;
		$tt_terms     = get_terms( array( 'taxonomy' => $tt_taxonomy, 'hide_empty' => false ) );
		$wiz_tax_html .= '<div class="ys-taxonomy-term-row">';
		$wiz_tax_html .= '<select class="ys-select ys-taxonomy-select ys-wizard-taxonomy-select">';
		$wiz_tax_html .= '<option value="">' . esc_html__( '&mdash; Select taxonomy &mdash;', 'yousync' ) . '</option>';
		foreach ( $available_taxonomies as $tax ) {
			$wiz_tax_html .= '<option value="' . esc_attr( $tax->name ) . '"' . selected( $tt_taxonomy, $tax->name, false ) . '>' . esc_html( $tax->labels->singular_name ) . '</option>';
		}
		$wiz_tax_html .= '</select>';
		$wiz_tax_html .= '<div class="ys-term-select-wrapper">';
		$wiz_tax_html .= '<select class="ys-select ys-term-select ys-wizard-term-select">';
		$wiz_tax_html .= '<option value="">' . esc_html__( '&mdash; Select term &mdash;', 'yousync' ) . '</option>';
		if ( ! is_wp_error( $tt_terms ) ) {
			foreach ( $tt_terms as $term ) {
				$wiz_tax_html .= '<option value="' . esc_attr( $term->term_id ) . '"' . ( in_array( $term->term_id, $tt_term_ids, true ) ? ' selected' : '' ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		$wiz_tax_html .= '</select>';
		$wiz_tax_html .= '</div>';
		$wiz_tax_html .= '<button type="button" class="ys-remove-taxonomy-term" aria-label="' . esc_attr__( 'Remove', 'yousync' ) . '"></button>';
		$wiz_tax_html .= '</div>';
	}
	?>
	<div class="ys-wizard-default-taxonomy-template" hidden><?php echo $wiz_tax_html; ?></div>

</div>
