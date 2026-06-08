<?php
declare(strict_types=1);
/**
 * Channel metabox template.
 *
 * @package YouSync
 *
 * @var int    $post_id             WordPress post ID.
 * @var string $nonce_action        Nonce action for wp_nonce_field.
 * @var string $channel_id          YouTube channel ID.
 * @var string $channel_title       Channel title.
 * @var string $channel_description Channel description.
 * @var string $subscriber_count    Subscriber count.
 * @var string $video_count         Video count.
 * @var int    $last_synced         Last synced timestamp.
 * @var bool   $manual_edits        Whether protection is currently enabled.
 * @var string $profile_picture     Profile picture URL.
 * @var string $banner_image        Banner/cover image URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php wp_nonce_field( $nonce_action, 'yousync_channel_meta_nonce' ); ?>

<div class="yousync-metabox">
	<div class="ys-channel-tabs-nav" role="tablist">
		<button type="button" class="ys-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'yousync' ); ?>
		</button>
		<button type="button" class="ys-channel-tab-btn" data-tab="images" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'yousync' ); ?>
		</button>
		<button type="button" class="ys-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'yousync' ); ?>
		</button>
	</div>

	<div class="ys-channel-tabs-content">

		<!-- Details -->
		<div class="ys-channel-tab-panel ys-hidden" data-panel="details" role="tabpanel">
			<div class="ys-mb-fields">

				<?php if ( $channel_title ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Channel Title', 'yousync' ); ?></p>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $channel_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_description ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Description', 'yousync' ); ?></p>
					</div>
					<textarea class="ys-mb-textarea" rows="4" readonly><?php echo esc_textarea( $channel_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $subscriber_count !== '' ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Subscribers', 'yousync' ); ?></p>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( number_format( (int) $subscriber_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_count !== '' ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Videos', 'yousync' ); ?></p>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( number_format( (int) $video_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Channel Link', 'yousync' ); ?></p>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( 'https://www.youtube.com/channel/' . $channel_id ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<details class="ys-developer-fields">
					<summary><?php esc_html_e( 'Developer Fields', 'yousync' ); ?></summary>
					<div class="ys-mb-field">
						<div class="ys-mb-label-row">
							<p class="ys-mb-label"><?php esc_html_e( 'Channel ID', 'yousync' ); ?></p>
						</div>
						<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
				</details>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<div class="ys-channel-tab-panel ys-hidden" data-panel="images" role="tabpanel">
			<?php if ( $profile_picture || $banner_image ) : ?>

			<div class="ys-mb-channel-hero">
				<div class="ys-mb-hero-inner">
					<?php if ( $banner_image ) : ?>
					<img src="<?php echo esc_url( $banner_image ); ?>" alt="" class="ys-mb-hero-banner">
					<?php else : ?>
					<div class="ys-mb-hero-banner-placeholder"></div>
					<?php endif; ?>
					<?php if ( $profile_picture ) : ?>
					<div class="ys-mb-hero-profile">
						<img src="<?php echo esc_url( $profile_picture ); ?>" alt="">
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="ys-mb-fields">
				<?php if ( $banner_image ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Banner Image', 'yousync' ); ?></p>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $banner_image ); ?>" readonly>
				</div>
				<?php endif; ?>
				<?php if ( $profile_picture ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Profile Photo', 'yousync' ); ?></p>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $profile_picture ); ?>" readonly>
				</div>
				<?php endif; ?>
			</div>

			<?php else : ?>
			<p class="ys-not-synced-msg"><?php esc_html_e( 'No images synced yet.', 'yousync' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Sync -->
		<div class="ys-channel-tab-panel ys-hidden" data-panel="sync" role="tabpanel">
			<div class="ys-mb-fields">

				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Protected from Sync Rules', 'yousync' ); ?></p>
					<label class="ys-toggle">
						<input type="checkbox" name="yousync_manual_edits" id="yousync_manual_edits" value="1" <?php checked( $manual_edits ); ?>>
						<span class="ys-toggle-slider"></span>
					</label>
					<p class="description"><?php esc_html_e( 'When enabled, sync rules will not overwrite this post. Turn this on to preserve any manual edits you have made.', 'yousync' ); ?></p>
				</div>

				<?php if ( $last_synced ) : ?>
				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Last Synced', 'yousync' ); ?></p>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="ys-not-synced-msg"><?php esc_html_e( 'This channel has not been synced yet.', 'yousync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
