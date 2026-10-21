<?php
declare(strict_types=1);
/**
 * Template part for the Channels admin page.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var array $channel        The single channel configuration.
 * @var array $ch_errors      Keyed by youtube_id — error messages from last save.
 * @var array $import_notices Plain messages from the last import, if any.
 * @var bool  $has_api_key    Whether a YouTube API key is configured.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Channel', 'buoy-video-sync' ); ?></h1>

	<?php if ( ! $has_api_key ) : ?>
	<div class="buoyvs-empty-state">
		<span class="buoyvs-empty-state-icon buoyvs-icon-api-key"></span>
		<h2><?php esc_html_e( 'API key required', 'buoy-video-sync' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: Google Cloud Console link, 2: YouTube Data API overview link */
				esc_html__( 'Add a YouTube Data API key before setting up channels. You can create one in the %1$s by following the guide in the %2$s.', 'buoy-video-sync' ),
				'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Cloud Console', 'buoy-video-sync' ) . '</a>',
				'<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank" rel="noopener noreferrer">' . esc_html__( 'YouTube Data API overview', 'buoy-video-sync' ) . '</a>'
			);
			?>
		</p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=buoyvs_settings' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Go to Settings', 'buoy-video-sync' ); ?>
		</a>
	</div>

	<?php
	buoyvs_get_template_part( 'upgrade-banner', null, array(
		'heading'     => __( 'Sync more than one channel', 'buoy-video-sync' ),
		'description' => __( 'Add unlimited YouTube channels, each with its own schedules, taxonomy assignment, and sync rules.', 'buoy-video-sync' ),
	) );
	?>

	<?php else : ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['buoyvs-channels-updated'] ) && '1' === $_GET['buoyvs-channels-updated'] && empty( $ch_errors ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Channel configuration saved.', 'buoy-video-sync' ); ?></p>
	</div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['buoyvs-channel-deleted'] ) && '1' === $_GET['buoyvs-channel-deleted'] ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Channel deleted.', 'buoy-video-sync' ); ?></p>
	</div>
	<?php endif; ?>

	<?php foreach ( $import_notices as $import_notice ) : ?>
	<div class="notice notice-warning is-dismissible">
		<p><?php echo esc_html( $import_notice ); ?></p>
	</div>
	<?php endforeach; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'buoyvs_save_channels', 'buoyvs_channels_nonce' ); ?>
		<input type="hidden" name="action" value="buoyvs_save_channels">

		<div id="buoyvs-channels">
			<?php
			buoyvs_get_template_part( 'channel', 'group', array(
				'channel'   => $channel,
				'ch_errors' => $ch_errors,
			) );
			?>
		</div>

		<?php submit_button( __( 'Save Changes', 'buoy-video-sync' ) ); ?>
	</form>

	<div class="buoyvs-import-export postbox">
		<h2 class="buoyvs-import-export-heading"><?php esc_html_e( 'Import / Export', 'buoy-video-sync' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Back up your channel and sync rules to a JSON file, or restore from one — importing replaces the current configuration.', 'buoy-video-sync' ); ?>
		</p>
		<p>
			<a
				class="button"
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=buoyvs_export_channels' ), 'buoyvs_export_channels' ) ); ?>"
			>
				<?php esc_html_e( 'Export Sync Rules', 'buoy-video-sync' ); ?>
			</a>
		</p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'buoyvs_import_channels', 'buoyvs_import_nonce' ); ?>
			<input type="hidden" name="action" value="buoyvs_import_channels">
			<input type="file" name="buoyvs_import_file" accept="application/json" required>
			<button
				type="submit"
				class="button"
				onclick="return confirm('<?php echo esc_js( __( 'Importing will replace your current channel and sync rules. Continue?', 'buoy-video-sync' ) ); ?>');"
			>
				<?php esc_html_e( 'Import Sync Rules', 'buoy-video-sync' ); ?>
			</button>
		</form>
	</div>

	<?php
	buoyvs_get_template_part( 'upgrade-banner', null, array(
		'heading'     => __( 'Sync more than one channel', 'buoy-video-sync' ),
		'description' => __( 'Add unlimited YouTube channels, each with its own schedules, taxonomy assignment, and sync rules.', 'buoy-video-sync' ),
	) );
	?>

	<?php endif; ?>
</div>
