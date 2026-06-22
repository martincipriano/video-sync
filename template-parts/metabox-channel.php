<?php
declare(strict_types=1);
/**
 * Channel metabox template.
 *
 * @package WPBuoy_Video_Sync
 *
 * @var int    $post_id             WordPress post ID.
 * @var string $channel_id          YouTube channel ID.
 * @var string $channel_title       Channel title.
 * @var string $channel_description Channel description.
 * @var string $subscriber_count    Subscriber count.
 * @var string $video_count         Video count.
 * @var int    $last_synced         Last synced timestamp.
 * @var string $profile_picture     Profile picture URL.
 * @var string $banner_image        Banner/cover image URL.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpbyvs-metabox">
	<div class="wpbyvs-channel-tabs-nav" role="tablist">
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'wpbuoy-video-sync' ); ?>
		</button>
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="images" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'wpbuoy-video-sync' ); ?>
		</button>
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'wpbuoy-video-sync' ); ?>
		</button>
		<?php wpbyvs_render_extra_tab_nav( 'channel', $post_id ); ?>
	</div>

	<div class="wpbyvs-channel-tabs-content">
		<?php wpbyvs_render_extra_tab_panels( 'channel', $post_id ); ?>

		<!-- Details -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="details" role="tabpanel">
			<div class="wpbyvs-mb-fields">

				<?php if ( $channel_title ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Channel Title', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $channel_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_description ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Description', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<textarea class="wpbyvs-mb-textarea" rows="4" readonly><?php echo esc_textarea( $channel_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $subscriber_count !== '' ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Subscribers', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $subscriber_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_count !== '' ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Videos', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $video_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Channel Link', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( 'https://www.youtube.com/channel/' . $channel_id ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<div class="wpbyvs-developer-fields wpbyvs-hidden">
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php esc_html_e( 'Channel ID', 'wpbuoy-video-sync' ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
				</div>
				<button class="button-link wpbyvs-developer-fields-toggle" type="button">
					<span><?php esc_html_e( 'Hide Developer Fields', 'wpbuoy-video-sync' ); ?></span>
					<span><?php esc_html_e( 'Show Developer Fields', 'wpbuoy-video-sync' ); ?></span>
				</button>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="images" role="tabpanel">
			<?php if ( $profile_picture || $banner_image ) : ?>

			<div class="wpbyvs-mb-channel-hero">
				<div class="wpbyvs-mb-hero-inner">
					<?php if ( $banner_image ) : ?>
					<img src="<?php echo esc_url( $banner_image ); ?>" alt="" class="wpbyvs-mb-hero-banner">
					<?php else : ?>
					<div class="wpbyvs-mb-hero-banner-placeholder"></div>
					<?php endif; ?>
					<?php if ( $profile_picture ) : ?>
					<div class="wpbyvs-mb-hero-profile">
						<img src="<?php echo esc_url( $profile_picture ); ?>" alt="">
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="wpbyvs-mb-fields">
				<?php if ( $banner_image ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Banner Image', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $banner_image ); ?>" readonly>
				</div>
				<?php endif; ?>
				<?php if ( $profile_picture ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Profile Photo', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $profile_picture ); ?>" readonly>
				</div>
				<?php endif; ?>
			</div>

			<?php else : ?>
			<p class="wpbyvs-not-synced-msg"><?php esc_html_e( 'No images synced yet.', 'wpbuoy-video-sync' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Sync -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="sync" role="tabpanel">
			<div class="wpbyvs-mb-fields">

				<?php if ( $last_synced ) : ?>
				<div class="wpbyvs-mb-field">
					<p class="wpbyvs-mb-label"><?php esc_html_e( 'Last Synced', 'wpbuoy-video-sync' ); ?></p>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="wpbyvs-not-synced-msg"><?php esc_html_e( 'This channel has not been synced yet.', 'wpbuoy-video-sync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
