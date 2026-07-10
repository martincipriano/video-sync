<?php
declare(strict_types=1);
/**
 * Video metabox template.
 *
 * @package WPBuoy_Video_Sync
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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to wpbyvs_get_template_part()'s extract()/include scope, not globals.
?>
<div class="wpbyvs-metabox">
	<div class="wpbyvs-channel-tabs-nav" role="tablist">
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'wby-video-sync' ); ?>
		</button>
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="thumbnails" role="tab" aria-selected="false">
			<?php esc_html_e( 'Images', 'wby-video-sync' ); ?>
		</button>
		<?php endif; ?>
		<button type="button" class="wpbyvs-channel-tab-btn" data-tab="sync" role="tab" aria-selected="false">
			<?php esc_html_e( 'Sync', 'wby-video-sync' ); ?>
		</button>
		<?php wpbyvs_render_extra_tab_nav( 'video', $post_id ); ?>
	</div>

	<div class="wpbyvs-channel-tabs-content">
		<?php wpbyvs_render_extra_tab_panels( 'video', $post_id ); ?>

		<!-- Details -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="details" role="tabpanel">
			<div class="wpbyvs-mb-fields">

				<?php if ( $original_title ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Original Title', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $original_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $original_description ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Description', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<textarea class="wpbyvs-mb-textarea" rows="4" readonly><?php echo esc_textarea( $original_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $channel_title ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Channel', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $channel_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $published_date ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Published Date', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $published_date ); ?>" readonly>
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
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Duration', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $duration_fmt ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $view_count !== '' ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'View Count', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $view_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $like_count !== '' ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Like Count', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $like_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $comment_count !== '' ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Comment Count', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( number_format( (int) $comment_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_url ) : ?>
				<div class="wpbyvs-mb-field">
					<div class="wpbyvs-mb-label-row">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Video Link', 'wby-video-sync' ); ?></p>
						<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
					</div>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $video_url ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_id || $channel_id ) : ?>
				<div class="wpbyvs-developer-fields wpbyvs-hidden">
					<?php if ( $video_id ) : ?>
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php esc_html_e( 'Video ID', 'wby-video-sync' ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input wpbyvs-mb-input--code" value="<?php echo esc_attr( $video_id ); ?>" readonly>
					</div>
					<?php endif; ?>

					<?php if ( $video_id ) : ?>
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php esc_html_e( 'Embed Code', 'wby-video-sync' ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>' ); ?>" readonly class="wpbyvs-mb-input wpbyvs-mb-input--url">
					</div>
					<?php endif; ?>

					<?php if ( $channel_id ) : ?>
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php esc_html_e( 'Channel ID', 'wby-video-sync' ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input wpbyvs-mb-input--code" value="<?php echo esc_attr( $channel_id ); ?>" readonly>
					</div>
					<?php endif; ?>

				</div>
				<button class="button-link wpbyvs-developer-fields-toggle" type="button">
					<span><?php esc_html_e( 'Hide Developer Fields', 'wby-video-sync' ); ?></span>
					<span><?php esc_html_e( 'Show Developer Fields', 'wby-video-sync' ); ?></span>
				</button>
				<?php endif; ?>

			</div>
		</div>

		<!-- Images -->
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="thumbnails" role="tabpanel">
			<?php if ( $preview_thumb ) : ?>
			<div class="wpbyvs-mb-thumb-preview">
				<img src="<?php echo esc_url( $preview_thumb['url'] ); ?>" alt="">
			</div>
			<?php endif; ?>
			<div class="wpbyvs-mb-fields">
				<?php foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) : ?>
					<?php if ( empty( $thumbnails[ $size ]['url'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<div class="wpbyvs-mb-field">
						<div class="wpbyvs-mb-label-row">
							<p class="wpbyvs-mb-label"><?php echo esc_html( $thumbnail_size_labels[ $size ] ); ?></p>
							<button type="button" class="wpbyvs-copy-val-btn"><?php esc_html_e( 'Copy value', 'wby-video-sync' ); ?></button>
						</div>
						<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( $thumbnails[ $size ]['url'] ); ?>" readonly>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Sync -->
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="sync" role="tabpanel">
			<div class="wpbyvs-mb-fields">

				<?php if ( $last_synced ) : ?>
				<div class="wpbyvs-mb-field">
					<p class="wpbyvs-mb-label"><?php esc_html_e( 'Last Synced', 'wby-video-sync' ); ?></p>
					<input type="text" class="wpbyvs-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="wpbyvs-not-synced-msg"><?php esc_html_e( 'This video has not been synced yet.', 'wby-video-sync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
