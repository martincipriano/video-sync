<?php
declare(strict_types=1);
/**
 * Video metabox template.
 *
 * @package Buoy_Video_Sync
 *
 * @var int        $post_id               WordPress post ID.
 * @var string     $video_id              YouTube video ID.
 * @var string     $video_url             YouTube video URL.
 * @var string     $channel_id            YouTube channel ID.
 * @var string     $original_title        Original YouTube title.
 * @var string     $original_description  Original YouTube description.
 * @var string     $channel_title         Channel name.
 * @var string     $published_date        Published date string.
 * @var string     $duration_seconds      Duration in seconds.
 * @var string     $view_count            View count.
 * @var string     $like_count            Like count.
 * @var string     $comment_count         Comment count.
 * @var string     $sync_source_type      Sync source type.
 * @var int        $last_synced           Last synced timestamp.
 * @var array      $thumbnails            Thumbnails array.
 * @var array      $thumbnail_size_labels Thumbnail size labels.
 * @var array|null $preview_thumb         Best thumbnail array or null.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to buoyvs_get_template_part()'s extract()/include scope, not globals.
?>
<div class="buoyvs-metabox">
	<div class="buoyvs-channel-tabs-nav" role="tablist">
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'buoy-video-sync' ); ?>
		</button>
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="thumbnails" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'buoy-video-sync' ); ?>
		</button>
		<?php endif; ?>
		<button type="button" class="buoyvs-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'buoy-video-sync' ); ?>
		</button>
		<?php buoyvs_render_extra_tab_nav( 'video', $post_id ); ?>
	</div>

	<div class="buoyvs-channel-tabs-content">
		<?php buoyvs_render_extra_tab_panels( 'video', $post_id ); ?>

		<!-- Details -->
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="details" role="tabpanel">
			<div class="buoyvs-mb-fields">

				<?php if ( $original_title ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Original Title', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="title"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $original_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $original_description ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Description', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="description"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<textarea class="buoyvs-mb-textarea" rows="4" readonly><?php echo esc_textarea( $original_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $channel_title ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Channel', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="channel"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $channel_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $published_date ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Published Date', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $published_date ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $duration_seconds !== '' ) : ?>
				<?php
				$hours        = floor( (int) $duration_seconds / 3600 );
				$mins         = floor( ( (int) $duration_seconds % 3600 ) / 60 );
				$secs         = (int) $duration_seconds % 60;
				$duration_fmt = $hours > 0
					? sprintf( '%d:%02d:%02d', $hours, $mins, $secs )
					: sprintf( '%d:%02d', $mins, $secs );
				?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Duration', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $duration_fmt ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $view_count !== '' ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'View Count', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="view_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $view_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $like_count !== '' ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Like Count', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="like_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $like_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $comment_count !== '' ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Comment Count', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="comment_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $comment_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_url ) : ?>
				<div class="buoyvs-mb-field">
					<div class="buoyvs-mb-label-row">
						<p class="buoyvs-mb-label"><?php esc_html_e( 'Video Link', 'buoy-video-sync' ); ?></p>
						<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
					</div>
					<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $video_url ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_id || $channel_id ) : ?>
				<div class="buoyvs-developer-fields buoyvs-hidden">
					<?php if ( $video_id ) : ?>
					<div class="buoyvs-mb-field">
						<div class="buoyvs-mb-label-row">
							<p class="buoyvs-mb-label"><?php esc_html_e( 'Video ID', 'buoy-video-sync' ); ?></p>
							<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="buoyvs-mb-input buoyvs-mb-input--code" value="<?php echo esc_attr( $video_id ); ?>" readonly>
					</div>
					<?php endif; ?>

					<?php if ( $video_id ) : ?>
					<div class="buoyvs-mb-field">
						<div class="buoyvs-mb-label-row">
							<p class="buoyvs-mb-label"><?php esc_html_e( 'Embed Code', 'buoy-video-sync' ); ?></p>
							<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>' ); ?>" readonly class="buoyvs-mb-input buoyvs-mb-input--url">
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
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<div class="buoyvs-channel-tab-panel buoyvs-hidden" data-panel="thumbnails" role="tabpanel">
			<?php if ( $preview_thumb ) : ?>
			<div class="buoyvs-mb-thumb-preview">
				<img src="<?php echo esc_url( $preview_thumb['url'] ); ?>" alt="">
			</div>
			<?php endif; ?>
			<div class="buoyvs-mb-fields">
				<?php foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) : ?>
					<?php if ( empty( $thumbnails[ $size ]['url'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<div class="buoyvs-mb-field">
						<div class="buoyvs-mb-label-row">
							<p class="buoyvs-mb-label"><?php echo esc_html( $thumbnail_size_labels[ $size ] ); ?></p>
							<button type="button" class="buoyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'buoy-video-sync' ); ?></button><button type="button" class="buoyvs-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[buoy-video-sync id="' . $post_id . '" field="thumbnail" size="' . $size . '"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'buoy-video-sync' ); ?></button>
						</div>
						<input type="text" class="buoyvs-mb-input" value="<?php echo esc_attr( $thumbnails[ $size ]['url'] ); ?>" readonly>
					</div>
				<?php endforeach; ?>
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
				<p class="buoyvs-not-synced-msg"><?php esc_html_e( 'This video has not been synced yet.', 'buoy-video-sync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
