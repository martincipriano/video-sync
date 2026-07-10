<?php
declare(strict_types=1);
/**
 * Template part: 2-step sync rule wizard (Action → Destination).
 *
 * Rendered once per channel group, hidden by default. JavaScript reveals it
 * when the user clicks "Add sync rule" and hides it on cancel/completion.
 *
 * @package WPBuoy_Video_Sync
 *
 * Variables available in this template:
 * @var string $default_post_type Default destination post type for the channel.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to wpbyvs_get_template_part()'s extract()/include scope, not globals.

$post_types        = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type = $default_post_type ?? '';
?>
<div class="wpbyvs-wizard wpbyvs-hidden" data-channel-index="0" data-default-post-type="<?php echo esc_attr( $default_post_type ); ?>">

	<div class="wpbyvs-wizard-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Wizard progress', 'wby-video-sync' ); ?>">
		<span class="wpbyvs-wizard-step-indicator wpbyvs-wizard-step-indicator--active" data-step="1">1</span>
		<span class="wpbyvs-wizard-progress-line"></span>
		<span class="wpbyvs-wizard-step-indicator" data-step="2">2</span>
	</div>

	<div class="wpbyvs-wizard-panels">

	<?php /* Step 1 — Action */ ?>
	<div class="wpbyvs-wizard-panel" data-step="1">
		<div class="wpbyvs-wizard-step-header">
			<h3><?php esc_html_e( 'What should this rule do?', 'wby-video-sync' ); ?></h3>
		</div>
		<div class="wpbyvs-2-columns wpbyvs-cols-3-1">
			<div class="wpbyvs-form-group">
				<label for="wpbyvs-wizard-action"><?php esc_html_e( 'Action', 'wby-video-sync' ); ?></label>
				<select id="wpbyvs-wizard-action" class="wpbyvs-select wpbyvs-wizard-action-select">
					<option value=""><?php esc_html_e( '— Select action —', 'wby-video-sync' ); ?></option>
					<option data-resource="video" value="videos_sync_new"><?php esc_html_e( 'Sync new videos', 'wby-video-sync' ); ?></option>
					<option data-resource="playlist" value="playlists_sync_new"><?php esc_html_e( 'Sync new playlists', 'wby-video-sync' ); ?></option>
					<option data-resource="channel" value="channel_sync_new"><?php esc_html_e( 'Sync this channel', 'wby-video-sync' ); ?></option>
				</select>
			</div>
			<div class="wpbyvs-form-group wpbyvs-items-per-run-wrapper">
				<label for="wpbyvs-wizard-max-videos" class="wpbyvs-max-items-label"><?php esc_html_e( 'Items per run', 'wby-video-sync' ); ?></label>
				<div class="wpbyvs-limit-wrap">
					<input id="wpbyvs-wizard-max-videos" class="wpbyvs-number wpbyvs-wizard-max-videos wpbyvs-max-videos-input" type="number" value="50" min="0">
					<span class="wpbyvs-unlimited-icon wpbyvs-hidden" title="<?php esc_attr_e( 'Unlimited — click to set a limit', 'wby-video-sync' ); ?>">∞</span>
				</div>
			</div>
		</div>
		<div class="wpbyvs-wizard-nav">
			<button type="button" class="button wpbyvs-wizard-cancel"><?php esc_html_e( 'Cancel', 'wby-video-sync' ); ?></button>
			<button type="button" class="button button-primary wpbyvs-wizard-next" data-step="1"><?php esc_html_e( 'Next →', 'wby-video-sync' ); ?></button>
		</div>
	</div>

	<?php /* Step 2 — Destination */ ?>
	<div class="wpbyvs-wizard-panel wpbyvs-hidden" data-step="2">
		<div class="wpbyvs-wizard-step-header">
			<h3><?php esc_html_e( 'Where should synced items be saved?', 'wby-video-sync' ); ?></h3>
		</div>
		<div class="wpbyvs-2-columns wpbyvs-cols-3-1">
			<div class="wpbyvs-form-group wpbyvs-wizard-post-type-wrapper">
				<label for="wpbyvs-wizard-post-type"><?php esc_html_e( 'Post Type', 'wby-video-sync' ); ?></label>
				<select id="wpbyvs-wizard-post-type" class="wpbyvs-select wpbyvs-wizard-post-type">
					<option value=""><?php esc_html_e( '— Select post type —', 'wby-video-sync' ); ?></option>
					<?php foreach ( $post_types as $pt ) : ?>
					<option value="<?php echo esc_attr( $pt->name ); ?>"<?php selected( $default_post_type, $pt->name ); ?>>
						<?php echo esc_html( $pt->labels->singular_name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="wpbyvs-wizard-error wpbyvs-hidden"></div>
		<div class="wpbyvs-wizard-nav">
			<button type="button" class="button wpbyvs-wizard-back" data-step="2"><?php esc_html_e( '← Back', 'wby-video-sync' ); ?></button>
			<button type="button" class="button button-primary wpbyvs-wizard-finish"><?php esc_html_e( 'Add Rule', 'wby-video-sync' ); ?></button>
		</div>
	</div>

	</div><?php /* .wpbyvs-wizard-panels */ ?>

</div>
