<?php
declare(strict_types=1);
/**
 * Template part: 3-step sync rule wizard (Action → Schedule → Destination).
 *
 * Rendered once per channel group, hidden by default. JavaScript reveals it
 * when the user clicks "Add sync rule" and hides it on cancel/completion.
 *
 * @package WPBuoyVideoSync
 *
 * Variables available in this template:
 * @var int $ch_index Channel index.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to wpbuoy_video_sync_get_template_part()'s extract()/include scope, not globals.

$post_types        = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type = $default_post_type ?? '';
?>
<div class="ys-wizard ys-hidden" data-channel-index="<?php echo esc_attr( $ch_index ); ?>" data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>">

	<div class="ys-wizard-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Wizard progress', 'wpbuoy-video-sync' ); ?>">
		<span class="ys-wizard-step-indicator ys-wizard-step-indicator--active" data-step="1">1</span>
		<span class="ys-wizard-progress-line"></span>
		<span class="ys-wizard-step-indicator" data-step="2">2</span>
		<span class="ys-wizard-progress-line"></span>
		<span class="ys-wizard-step-indicator" data-step="3">3</span>
	</div>

	<div class="ys-wizard-panels">

	<?php /* Step 1 — Action */ ?>
	<div class="ys-wizard-panel" data-step="1">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'What should this rule do?', 'wpbuoy-video-sync' ); ?></h3>
		</div>
		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-wizard-action-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Action', 'wpbuoy-video-sync' ); ?></label>
				<select id="ys-wizard-action-<?php echo esc_attr( $ch_index ); ?>" class="ys-select ys-wizard-action-select">
					<option value=""><?php esc_html_e( '— Select action —', 'wpbuoy-video-sync' ); ?></option>
					<option data-resource="video" value="videos_sync_new"><?php esc_html_e( 'Sync new videos', 'wpbuoy-video-sync' ); ?></option>
					<option data-resource="playlist" value="playlists_sync_new"><?php esc_html_e( 'Sync new playlists', 'wpbuoy-video-sync' ); ?></option>
					<option data-resource="channel" value="channel_sync_new"><?php esc_html_e( 'Sync this channel', 'wpbuoy-video-sync' ); ?></option>
				</select>
			</div>
			<div class="ys-form-group ys-items-per-run-wrapper">
				<label for="ys-wizard-max-videos-<?php echo esc_attr( $ch_index ); ?>" class="ys-max-items-label"><?php esc_html_e( 'Items per run', 'wpbuoy-video-sync' ); ?></label>
				<div class="ys-limit-wrap">
					<input id="ys-wizard-max-videos-<?php echo esc_attr( $ch_index ); ?>" class="ys-number ys-wizard-max-videos ys-max-videos-input" type="number" value="50" min="0">
					<span class="ys-unlimited-icon ys-hidden" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'wpbuoy-video-sync' ); ?>">∞</span>
				</div>
			</div>
		</div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-cancel"><?php esc_html_e( 'Cancel', 'wpbuoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-next" data-step="1"><?php esc_html_e( 'Next →', 'wpbuoy-video-sync' ); ?></button>
		</div>
	</div>

	<?php /* Step 2 — Schedule */ ?>
	<div class="ys-wizard-panel ys-hidden" data-step="2">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'When should this rule run?', 'wpbuoy-video-sync' ); ?></h3>
		</div>
		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group">
				<label for="ys-wizard-schedule-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Sync schedule', 'wpbuoy-video-sync' ); ?></label>
				<input type="text" class="ys-text" id="ys-wizard-schedule-<?php echo esc_attr( $ch_index ); ?>" value="<?php esc_attr_e( 'Once (run immediately after enabling and saving)', 'wpbuoy-video-sync' ); ?>" readonly disabled>
			</div>
		</div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-back" data-step="2"><?php esc_html_e( '← Back', 'wpbuoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-next" data-step="2"><?php esc_html_e( 'Next →', 'wpbuoy-video-sync' ); ?></button>
		</div>
	</div>

	<?php /* Step 3 — Destination */ ?>
	<div class="ys-wizard-panel ys-hidden" data-step="3">
		<div class="ys-wizard-step-header">
			<h3><?php esc_html_e( 'Where should synced items be saved?', 'wpbuoy-video-sync' ); ?></h3>
		</div>
		<div class="ys-2-columns ys-cols-3-1">
			<div class="ys-form-group ys-wizard-post-type-wrapper">
				<label for="ys-wizard-post-type-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Post Type', 'wpbuoy-video-sync' ); ?></label>
				<select id="ys-wizard-post-type-<?php echo esc_attr( $ch_index ); ?>" class="ys-select ys-wizard-post-type">
					<option value=""><?php esc_html_e( '— Select post type —', 'wpbuoy-video-sync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>"<?php selected( $default_post_type, $pt->name ); ?>>
						<?php echo esc_html( $pt->labels->singular_name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="ys-wizard-error ys-hidden"></div>
		<div class="ys-wizard-nav">
			<button type="button" class="button ys-wizard-back" data-step="3"><?php esc_html_e( '← Back', 'wpbuoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary ys-wizard-finish"><?php esc_html_e( 'Add Rule', 'wpbuoy-video-sync' ); ?></button>
		</div>
	</div>

	</div><?php /* .ys-wizard-panels */ ?>

</div>
