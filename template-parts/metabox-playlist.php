<?php
declare(strict_types=1);
/**
 * Playlist metabox template.
 *
 * @package YouSync
 *
 * @var int    $post_id               WordPress post ID.
 * @var string $nonce_action          Nonce action for wp_nonce_field.
 * @var string $playlist_id           YouTube playlist ID.
 * @var string $channel_id            YouTube channel ID.
 * @var string $playlist_title        Playlist title.
 * @var string $playlist_description  Playlist description.
 * @var string $playlist_video_count  Number of videos in the playlist.
 * @var string $playlist_thumbnail    Thumbnail URL.
 * @var int    $last_synced           Last synced timestamp.
 * @var bool   $manual_edits          Whether protection is currently enabled.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php wp_nonce_field( $nonce_action, 'yousync_playlist_meta_nonce' ); ?>

<div class="yousync-metabox">
	<div class="ys-channel-tabs-nav" role="tablist">
		<button type="button" class="ys-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'yousync' ); ?>
		</button>
		<?php if ( $playlist_thumbnail ) : ?>
		<button type="button" class="ys-channel-tab-btn" data-tab="images" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'yousync' ); ?>
		</button>
		<?php endif; ?>
		<button type="button" class="ys-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'yousync' ); ?>
		</button>
	</div>

	<div class="ys-channel-tabs-content">

		<!-- Details -->
		<div class="ys-channel-tab-panel ys-hidden" data-panel="details" role="tabpanel">
			<div class="ys-mb-fields">

				<?php if ( $playlist_title ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Playlist Title', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="title"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $playlist_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_description ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Description', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="description"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<textarea class="ys-mb-textarea" rows="4" readonly><?php echo esc_textarea( $playlist_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_video_count !== '' && $playlist_video_count !== null ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Video Count', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="playlist_video_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( number_format( (int) $playlist_video_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_id ) : ?>
				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Playlist Link', 'yousync' ); ?></p>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( 'https://www.youtube.com/playlist?list=' . $playlist_id ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $playlist_id || $channel_id ) : ?>
				<details class="ys-developer-fields">
					<summary><?php esc_html_e( 'Developer Fields', 'yousync' ); ?></summary>

					<?php if ( $playlist_id ) : ?>
					<div class="ys-mb-field">
						<div class="ys-mb-label-row">
							<p class="ys-mb-label"><?php esc_html_e( 'Playlist ID', 'yousync' ); ?></p>
							<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="playlist_id"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
						</div>
						<input type="text" class="ys-mb-input ys-mb-input--code" value="<?php echo esc_attr( $playlist_id ); ?>" readonly>
					</div>
					<?php endif; ?>

					<?php if ( $channel_id ) : ?>
					<div class="ys-mb-field">
						<div class="ys-mb-label-row">
							<p class="ys-mb-label"><?php esc_html_e( 'Channel ID', 'yousync' ); ?></p>
							<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="channel_id"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
						</div>
						<input type="text" class="ys-mb-input ys-mb-input--code" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
					<?php endif; ?>

				</details>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<?php if ( $playlist_thumbnail ) : ?>
		<div class="ys-channel-tab-panel ys-hidden" data-panel="images" role="tabpanel">
			<div class="ys-mb-thumb-preview">
				<img src="<?php echo esc_url( $playlist_thumbnail ); ?>" alt="">
			</div>
			<div class="ys-mb-fields">
				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Thumbnail URL', 'yousync' ); ?></p>
					<input type="text" class="ys-mb-input ys-mb-input--url" value="<?php echo esc_attr( $playlist_thumbnail ); ?>" readonly>
				</div>
			</div>
		</div>
		<?php endif; ?>

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
				<p class="ys-not-synced-msg"><?php esc_html_e( 'This playlist has not been synced yet.', 'yousync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
