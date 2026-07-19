<?php
declare(strict_types=1);
/**
 * Channel metabox template.
 *
 * @package Buoy_Video_Sync
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
<div class="buoyvs-metabox">
	<div class="buoyvs-channel-tabs-nav" role="tablist">
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'buoy-video-sync' ); ?>
		</button>
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="images" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'buoy-video-sync' ); ?>
		</button>
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'buoy-video-sync' ); ?>
		</button>
		<?php buoyvs_render_extra_tab_nav( 'channel', $post_id ); ?>
	</div>

	<div class="buoyvs-channel-tabs-content">
		<?php buoyvs_render_extra_tab_panels( 'channel', $post_id ); ?>

		<!-- Details -->
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="details" role="tabpanel">
			<div class="buoyvs-mb-fields">

				<?php if ( $channel_title ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Channel Title', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $channel_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_description ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Description', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<textarea class="buoyvs-mb-textarea" rows="4" readonly><?php echo esc_textarea( $channel_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $subscriber_count !== '' ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Subscribers', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $subscriber_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_count !== '' ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Videos', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $video_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Channel Link', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( 'https://www.youtube.com/channel/' . $channel_id ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $channel_id ) : ?>
				<div class="buoyvs-developer-fields buoyvs-hidden">
					<div class="buoyvs-mb-field">
						<div class="buoyvs-mb-label-row">
							<p class="buoyvs-mb-label"><?php esc_html_e( 'Channel ID', 'buoy-video-sync' ); ?></p>
							<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
				</div>
				<button class="button-link buoyvs-developer-fields-toggle" type="button">
					<span><?php esc_html_e( 'Hide Developer Fields', 'buoy-video-sync' ); ?></span>
					<span><?php esc_html_e( 'Show Developer Fields', 'buoy-video-sync' ); ?></span>
				</button>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="images" role="tabpanel">
			<?php if ( $profile_picture || $banner_image ) : ?>

			<div class="buoyvs-mb-channel-hero">
				<div class="buoyvs-mb-hero-inner">
					<?php if ( $banner_image ) : ?>
					<img src="<?php echo esc_url( $banner_image ); ?>" alt="" class="buoyvs-mb-hero-banner">
					<?php else : ?>
					<div class="buoyvs-mb-hero-banner-placeholder"></div>
					<?php endif; ?>
					<?php if ( $profile_picture ) : ?>
					<div class="buoyvs-mb-hero-profile">
						<img src="<?php echo esc_url( $profile_picture ); ?>" alt="">
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="buoyvs-mb-fields">
				<?php if ( $banner_image ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Banner Image', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $banner_image ); ?>" readonly>
				</div>
				<?php endif; ?>
				<?php if ( $profile_picture ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Profile Photo', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $profile_picture ); ?>" readonly>
				</div>
				<?php endif; ?>
			</div>

			<?php else : ?>
			<p class="buoyvs-not-synced-msg"><?php esc_html_e( 'No images synced yet.', 'buoy-video-sync' ); ?></p>
			<?php endif; ?>
		</div>

		<!-- Sync -->
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="sync" role="tabpanel">
			<div class="buoyvs-mb-fields">

				<?php if ( $last_synced ) : ?>
				<div class="buoyvs-mb-field">
					<p class="buoyvs-mb-label"><?php esc_html_e( 'Last Synced', 'buoy-video-sync' ); ?></p>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="buoyvs-not-synced-msg"><?php esc_html_e( 'This channel has not been synced yet.', 'buoy-video-sync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
