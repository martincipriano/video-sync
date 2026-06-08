<?php
declare(strict_types=1);
/**
 * Template part for the Channels admin page.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var array $channel    The single channel configuration.
 * @var array $ch_errors  Keyed by youtube_id — error messages from last save.
 * @var bool  $has_api_key Whether a YouTube API key is configured.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Channel', 'yousync' ); ?></h1>

	<?php if ( ! $has_api_key ) : ?>
	<div class="ys-empty-state">
		<span class="ys-empty-state-icon ys-icon-api-key"></span>
		<h2><?php esc_html_e( 'API key required', 'yousync' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: Google Cloud Console link, 2: YouTube Data API overview link */
				esc_html__( 'Add a YouTube Data API key before setting up channels. You can create one in the %1$s by following the guide in the %2$s.', 'yousync' ),
				'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Cloud Console', 'yousync' ) . '</a>',
				'<a href="https://developers.google.com/youtube/v3/getting-started" target="_blank" rel="noopener noreferrer">' . esc_html__( 'YouTube Data API overview', 'yousync' ) . '</a>'
			);
			?>
		</p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=yousync_settings' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Go to Settings', 'yousync' ); ?>
		</a>
	</div>
	<?php else : ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['yousync-channels-updated'] ) && '1' === $_GET['yousync-channels-updated'] && empty( $ch_errors ) ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Channel configuration saved.', 'yousync' ); ?></p>
	</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'yousync_save_channels', 'yousync_channels_nonce' ); ?>
		<input type="hidden" name="action" value="yousync_save_channels">

		<div id="ys-channels">
			<?php
			yousync_get_template_part( 'channel', 'group', array(
				'channel'   => $channel,
				'ch_index'  => 0,
				'ch_errors' => $ch_errors,
			) );
			?>
		</div>

		<?php submit_button( __( 'Save Changes', 'yousync' ) ); ?>
	</form>

	<?php endif; ?>
</div>
