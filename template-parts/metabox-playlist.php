<?php
declare(strict_types=1);
/**
 * Playlist metabox template.
 *
 * @package WPBuoy_Video_Sync
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
<div class="wpbyvs-metabox">
	<div class="wpbyvs-channel-tabs-nav" role="tablist">
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'wpbuoy-video-sync' ); ?>
		</button>
		<?php if ( $playlist_thumbnail ) : ?>
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="images" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'wpbuoy-video-sync' ); ?>
		</button>
		<?php endif; ?>
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'wpbuoy-video-sync' ); ?>
		</button>
		<?php wpbyvs_render_extra_tab_nav( 'playlist', $post_id ); ?>
	</div>

	<div class="wpbyvs-channel-tabs-content">
		<?php wpbyvs_render_extra_tab_panels( 'playlist', $post_id ); ?>

		<!-- Details -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="details" role="tabpanel">
			<div class="wpbyvs-mb-fields">

				<?php if ( $playlist_title ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Playlist Title', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $playlist_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_description ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Description', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<textarea class="wpbyvs-mb-textarea" rows="4" readonly><?php echo esc_textarea( $playlist_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_video_count !== '' && $playlist_video_count !== null ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Video Count', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $playlist_video_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_id ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Playlist Link', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( 'https://www.youtube.com/playlist?list=' . $playlist_id ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_id || $channel_id ) : ?>
				<div class="wpbyvs-developer-fields wpbyvs-hidden">
					<?php if ( $playlist_id ) : ?>
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php esc_html_e( 'Playlist ID', 'wpbuoy-video-sync' ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input wpbyvs-mb-input--code" value="<?php echo esc_attr( $playlist_id ); ?>" readonly>
					</div>
					<?php endif; ?>

					<?php if ( $channel_id ) : ?>
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php esc_html_e( 'Channel ID', 'wpbuoy-video-sync' ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input wpbyvs-mb-input--code" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
					<?php endif; ?>

				</div>
				<button class="button-link wpbyvs-developer-fields-toggle" type="button">
					<span><?php esc_html_e( 'Hide Developer Fields', 'wpbuoy-video-sync' ); ?></span>
					<span><?php esc_html_e( 'Show Developer Fields', 'wpbuoy-video-sync' ); ?></span>
				</button>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<?php if ( $playlist_thumbnail ) : ?>
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="images" role="tabpanel">
			<div class="wpbyvs-mb-thumb-preview">
				<img src="<?php echo esc_url( $playlist_thumbnail ); ?>" alt="">
			</div>
			<div class="wpbyvs-mb-fields">
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Thumbnail URL', 'wpbuoy-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wpbuoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input wpbyvs-mb-input--url" value="<?php echo esc_attr( $playlist_thumbnail ); ?>" readonly>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Sync -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="sync" role="tabpanel">
			<div class="wpbyvs-mb-fields">

				<?php if ( $last_synced ) : ?>
				<div class="wpbyvs-mb-field">
					<p class="wpbyvs-mb-label"><?php esc_html_e( 'Last Synced', 'wpbuoy-video-sync' ); ?></p>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="wpbyvs-not-synced-msg"><?php esc_html_e( 'This playlist has not been synced yet.', 'wpbuoy-video-sync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
