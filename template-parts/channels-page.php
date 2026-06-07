<?php
declare(strict_types=1);
/**
 * Template part for the Channels admin page.
 *
 * @package YouSyncPro
 *
 * Variables available in this template:
 * @var array $channels        Array of channel configurations.
 * @var bool  $can_add_channel Whether the user can add more channels (Pro).
 * @var bool  $is_pro          Whether the license has multi_channel feature.
 * @var array $ch_errors       Keyed by youtube_id — error messages from last save.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1>
		<?php if ( $is_pro ) : ?>
			<?php esc_html_e( 'Channels', 'yousync-pro' ); ?>
			<?php if ( $can_add_channel && $has_api_key ) : ?>
				<button type="button" class="page-title-action" id="ys-add-channel"><?php esc_html_e( 'Add Channel', 'yousync-pro' ); ?></button>
			<?php endif; ?>
		<?php else : ?>
			<?php esc_html_e( 'Channel', 'yousync-pro' ); ?>
		<?php endif; ?>
	</h1>

	<?php if ( ! $has_api_key ) : ?>
	<div class="ys-empty-state">
		<span class="ys-empty-state-icon ys-icon-api-key"></span>
		<h2><?php esc_html_e( 'API key required', 'yousync-pro' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: Google Cloud Console link, 2: YouTube Data API overview link */
				esc_html__( 'Add a YouTube Data API key before setting up channels. You can create one in the %1$s by following the guide in the %2$s.', 'yousync-pro' ),
				'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Cloud Console', 'yousync-pro' ) . '</a>',
				'<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank" rel="noopener noreferrer">' . esc_html__( 'YouTube Data API overview', 'yousync-pro' ) . '</a>'
			);
			?>
		</p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=yousync_pro_settings' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Go to Settings', 'yousync-pro' ); ?>
		</a>
	</div>
	<?php else : ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['yousync-channels-updated'] ) && '1' === $_GET['yousync-channels-updated'] && empty( $ch_errors ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Channel configuration saved.', 'yousync-pro' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'yousync_save_channels', 'yousync_channels_nonce' ); ?>
		<input type="hidden" name="action" value="yousync_save_channels">

		<div id="ys-channels">
			<?php foreach ( $channels as $ch_index => $channel ) : ?>
				<?php
				yousync_pro_get_template_part( 'channel', 'group', array(
					'channel'   => $channel,
					'ch_index'  => $ch_index,
					'is_pro'    => $is_pro,
					'total'     => count( $channels ),
					'ch_errors' => $ch_errors,
				) );
				?>
			<?php endforeach; ?>
		</div>

		<?php submit_button( __( 'Save Changes', 'yousync-pro' ) ); ?>
	</form>

	<?php endif; ?>
</div>
