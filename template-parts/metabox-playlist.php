<?php
declare(strict_types=1);
/**
 * Playlist metabox template.
 *
 * @package Buoy_Video_Sync
 *
 * @var int    $post_id               WordPress post ID.
 * @var string $playlist_id           YouTube playlist ID.
 * @var string $channel_id            YouTube channel ID.
 * @var string $playlist_title        Playlist title.
 * @var string $playlist_description  Playlist description.
 * @var string $playlist_video_count  Number of videos in the playlist.
 * @var string $playlist_thumbnail    Thumbnail URL.
 * @var int    $last_synced           Last synced timestamp.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="buoyvs-metabox">
	<div class="buoyvs-channel-tabs-nav" role="tablist">
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'buoy-video-sync' ); ?>
		</button>
		<?php if ( $playlist_thumbnail ) : ?>
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="images" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'buoy-video-sync' ); ?>
		</button>
		<?php endif; ?>
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'buoy-video-sync' ); ?>
		</button>
		<?php buoyvs_render_extra_tab_nav( 'playlist', $post_id ); ?>
	</div>

	<div class="buoyvs-channel-tabs-content">
		<?php buoyvs_render_extra_tab_panels( 'playlist', $post_id ); ?>

		<!-- Details -->
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="details" role="tabpanel">
			<div class="buoyvs-mb-fields">

				<?php if ( $playlist_title ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Playlist Title', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="title"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $playlist_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_description ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Description', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="description"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<textarea class="buoyvs-mb-textarea" rows="4" readonly><?php echo esc_textarea( $playlist_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_video_count !== '' && $playlist_video_count !== null ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Video Count', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="playlist_video_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $playlist_video_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_id ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Playlist Link', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( 'https://www.youtube.com/playlist?list=' . $playlist_id ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_id || $channel_id ) : ?>
				<div class="buoyvs-developer-fields buoyvs-hidden">
					<?php if ( $playlist_id ) : ?>
					<div class="buoyvs-mb-field">
						<div class="buoyvs-mb-label-row">
							<p class="buoyvs-mb-label"><?php esc_html_e( 'Playlist ID', 'buoy-video-sync' ); ?></p>
							<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="buoyvs-mb-input buoyvs-mb-input--code" value="<?php echo esc_attr( $playlist_id ); ?>" readonly>
					</div>
					<?php endif; ?>

					<?php if ( $channel_id ) : ?>
					<div class="buoyvs-mb-field">
						<div class="buoyvs-mb-label-row">
							<p class="buoyvs-mb-label"><?php esc_html_e( 'Channel ID', 'buoy-video-sync' ); ?></p>
							<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="buoyvs-mb-input buoyvs-mb-input--code" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
					<?php endif; ?>

				</div>
				<button class="button-link buoyvs-developer-fields-toggle" type="button">
					<span><?php esc_html_e( 'Hide Developer Fields', 'buoy-video-sync' ); ?></span>
					<span><?php esc_html_e( 'Show Developer Fields', 'buoy-video-sync' ); ?></span>
				</button>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<?php if ( $playlist_thumbnail ) : ?>
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="images" role="tabpanel">
			<div class="buoyvs-mb-thumb-preview">
				<img src="<?php echo esc_url( $playlist_thumbnail ); ?>" alt="">
			</div>
			<div class="buoyvs-mb-fields">
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Thumbnail URL', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input buoyvs-mb-input--url" value="<?php echo esc_attr( $playlist_thumbnail ); ?>" readonly>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Sync -->
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="sync" role="tabpanel">
			<div class="buoyvs-mb-fields">

				<?php if ( $last_synced ) : ?>
				<div class="buoyvs-mb-field">
					<p class="buoyvs-mb-label"><?php esc_html_e( 'Last Synced', 'buoy-video-sync' ); ?></p>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="buoyvs-not-synced-msg"><?php esc_html_e( 'This playlist has not been synced yet.', 'buoy-video-sync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
