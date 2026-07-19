<?php
declare(strict_types=1);
/**
 * Template part: 2-step sync rule wizard (Action → Destination).
 *
 * Rendered once per channel group, hidden by default. JavaScript reveals it
 * when the user clicks "Add sync rule" and hides it on cancel/completion.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var string $default_post_type Default destination post type for the channel.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to buoyvs_get_template_part()'s extract()/include scope, not globals.

$post_types        = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type = $default_post_type ?? '';
?>
<div class="buoyvs-wizard buoyvs-hidden" data-channel-index="0" data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>">

	<div class="buoyvs-wizard-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Wizard progress', 'buoy-video-sync' ); ?>">
		<span class="buoyvs-wizard-step-indicator buoyvs-wizard-step-indicator--active" data-step="1">1</span>
		<span class="buoyvs-wizard-progress-line"></span>
		<span class="buoyvs-wizard-step-indicator" data-step="2">2</span>
	</div>

	<div class="buoyvs-wizard-panels">

	<?php /* Step 1 — Action */ ?>
	<div class="buoyvs-wizard-panel" data-step="1">
		<div class="buoyvs-wizard-step-header">
			<h3><?php esc_html_e( 'What should this rule do?', 'buoy-video-sync' ); ?></h3>
		</div>
		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group">
				<label for="buoyvs-wizard-action"><?php esc_html_e( 'Action', 'buoy-video-sync' ); ?></label>
				<select id="buoyvs-wizard-action" class="buoyvs-select buoyvs-wizard-action-select">
					<option value=""><?php esc_html_e( '— Select action —', 'buoy-video-sync' ); ?></option>
					<option data-resource="video" value="videos_sync_new"><?php esc_html_e( 'Sync new videos', 'buoy-video-sync' ); ?></option>
					<option data-resource="playlist" value="playlists_sync_new"><?php esc_html_e( 'Sync new playlists', 'buoy-video-sync' ); ?></option>
					<option data-resource="channel" value="channel_sync_new"><?php esc_html_e( 'Sync this channel', 'buoy-video-sync' ); ?></option>
				</select>
			</div>
			<div class="buoyvs-form-group buoyvs-items-per-run-wrapper">
				<label for="buoyvs-wizard-max-videos" class="buoyvs-max-items-label"><?php esc_html_e( 'Items per run', 'buoy-video-sync' ); ?></label>
				<div class="buoyvs-limit-wrap">
					<input id="buoyvs-wizard-max-videos" class="buoyvs-number buoyvs-wizard-max-videos buoyvs-max-videos-input" type="number" value="50" min="0">
					<span class="buoyvs-unlimited-icon buoyvs-hidden" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'buoy-video-sync' ); ?>">∞</span>
				</div>
			</div>
		</div>
		<div class="buoyvs-wizard-nav">
			<button type="button" class="button buoyvs-wizard-cancel"><?php esc_html_e( 'Cancel', 'buoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary buoyvs-wizard-next" data-step="1"><?php esc_html_e( 'Next →', 'buoy-video-sync' ); ?></button>
		</div>
	</div>

	<?php /* Step 2 — Destination */ ?>
	<div class="buoyvs-wizard-panel buoyvs-hidden" data-step="2">
		<div class="buoyvs-wizard-step-header">
			<h3><?php esc_html_e( 'Where should synced items be saved?', 'buoy-video-sync' ); ?></h3>
		</div>
		<div class="buoyvs-2-columns buoyvs-cols-3-1">
			<div class="buoyvs-form-group buoyvs-wizard-post-type-wrapper">
				<label for="buoyvs-wizard-post-type"><?php esc_html_e( 'Post Type', 'buoy-video-sync' ); ?></label>
				<select id="buoyvs-wizard-post-type" class="buoyvs-select buoyvs-wizard-post-type">
					<option value=""><?php esc_html_e( '— Select post type —', 'buoy-video-sync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>"<?php selected( $default_post_type, $pt->name ); ?>>
						<?php echo esc_html( $pt->labels->singular_name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="buoyvs-wizard-error buoyvs-hidden"></div>
		<div class="buoyvs-wizard-nav">
			<button type="button" class="button buoyvs-wizard-back" data-step="2"><?php esc_html_e( '← Back', 'buoy-video-sync' ); ?></button>
			<button type="button" class="button button-primary buoyvs-wizard-finish"><?php esc_html_e( 'Add Rule', 'buoy-video-sync' ); ?></button>
		</div>
	</div>

	</div><?php /* .buoyvs-wizard-panels */ ?>

</div>
