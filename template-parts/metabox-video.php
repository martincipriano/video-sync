<?php
declare(strict_types=1);
/**
 * Video metabox template.
 *
 * @package YouSync
 *
 * @var int        $post_id               WordPress post ID.
 * @var string     $nonce_action          Nonce action for wp_nonce_field.
 * @var string     $video_id              YouTube video ID.
 * @var string     $video_url             YouTube video URL.
 * @var string     $channel_id            YouTube channel ID.
 * @var bool       $manual_edits          Whether protection is currently enabled.
 * @var bool       $manual_edits_disabled Whether the protection checkbox is disabled.
 * @var string     $manual_edits_notice   Notice text beside the checkbox, or empty.
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
?>
<?php wp_nonce_field( $nonce_action, 'yousync_video_meta_nonce' ); ?>

<div class="yousync-metabox">
	<div class="ys-channel-tabs-nav" role="tablist">
		<button type="button" class="ys-channel-tab-btn" data-tab="details" role="tab" aria-selected="false">
			<?php esc_html_e( 'Details', 'yousync' ); ?>
		</button>
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<button type="button" class="ys-channel-tab-btn" data-tab="thumbnails" role="tab" aria-selected="false">
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

				<?php if ( $original_title ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Original Title', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="title"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $original_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $original_description ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Description', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="description"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<textarea class="ys-mb-textarea" rows="4" readonly><?php echo esc_textarea( $original_description ); ?></textarea>
				</div>
				<?php endif; ?>

				<?php if ( $channel_title ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Channel', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="channel"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $channel_title ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $published_date ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Published Date', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="published_date"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $published_date ); ?>" readonly>
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
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Duration', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="duration"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $duration_fmt ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $view_count !== '' ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'View Count', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="view_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( number_format( (int) $view_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $like_count !== '' ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Like Count', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="like_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( number_format( (int) $like_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $comment_count !== '' ) : ?>
				<div class="ys-mb-field">
					<div class="ys-mb-label-row">
						<p class="ys-mb-label"><?php esc_html_e( 'Comment Count', 'yousync' ); ?></p>
						<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="comment_count"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
					</div>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( number_format( (int) $comment_count ) ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_url ) : ?>
				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Video Link', 'yousync' ); ?></p>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $video_url ); ?>" readonly>
				</div>
				<?php endif; ?>

				<?php if ( $video_id || $channel_id ) : ?>
				<details class="ys-developer-fields">
					<summary><?php esc_html_e( 'Developer Fields', 'yousync' ); ?></summary>

					<?php if ( $video_id ) : ?>
					<div class="ys-mb-field">
						<div class="ys-mb-label-row">
							<p class="ys-mb-label"><?php esc_html_e( 'Video ID', 'yousync' ); ?></p>
							<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="video_id"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
						</div>
						<input type="text" class="ys-mb-input ys-mb-input--code" value="<?php echo esc_attr( $video_id ); ?>" readonly>
					</div>
					<?php endif; ?>

					<?php if ( $video_id ) : ?>
					<div class="ys-mb-field">
						<div class="ys-mb-label-row">
							<p class="ys-mb-label"><?php esc_html_e( 'Embed Code', 'yousync' ); ?></p>
							<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" type="embed"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
						</div>
						<input type="text" class="ys-mb-input" value="<?php echo esc_attr( '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>' ); ?>" readonly class="ys-mb-input ys-mb-input--url">
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
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<div class="ys-channel-tab-panel ys-hidden" data-panel="thumbnails" role="tabpanel">
			<?php if ( $preview_thumb ) : ?>
			<div class="ys-mb-thumb-preview">
				<img src="<?php echo esc_url( $preview_thumb['url'] ); ?>" alt="">
			</div>
			<?php endif; ?>
			<div class="ys-mb-fields">
				<?php foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) : ?>
					<?php if ( empty( $thumbnails[ $size ]['url'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<div class="ys-mb-field">
						<div class="ys-mb-label-row">
							<p class="ys-mb-label"><?php echo esc_html( $thumbnail_size_labels[ $size ] ); ?></p>
							<button type="button" class="ys-copy-sc-btn" data-shortcode="<?php echo esc_attr( '[yousync id="' . $post_id . '" field="thumbnail" size="' . $size . '"]' ); ?>"><?php esc_html_e( 'Copy shortcode', 'yousync' ); ?></button>
						</div>
						<input type="text" class="ys-mb-input" value="<?php echo esc_attr( $thumbnails[ $size ]['url'] ); ?>" readonly>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Sync -->
		<div class="ys-channel-tab-panel ys-hidden" data-panel="sync" role="tabpanel">
			<div class="ys-mb-fields">

				<div class="ys-mb-field<?php echo $manual_edits_disabled ? ' ys-mb-field--disabled' : ''; ?>">
					<p class="ys-mb-label"><?php esc_html_e( 'Protected from Sync Rules', 'yousync' ); ?></p>
					<div class="ys-toggle-row">
						<label class="ys-toggle">
							<input type="checkbox" name="yousync_manual_edits" id="yousync_manual_edits" value="1" <?php checked( $manual_edits ); ?> <?php echo $manual_edits_disabled ? 'disabled' : ''; ?>>
							<span class="ys-toggle-slider"></span>
						</label>
						<?php if ( $manual_edits_notice ) : ?>
						<span class="ys-field-notice"><?php echo esc_html( $manual_edits_notice ); ?></span>
						<?php endif; ?>
					</div>
					<p class="description"><?php esc_html_e( 'When enabled, sync rules will not overwrite this post. Turn this on to preserve any manual edits you have made.', 'yousync' ); ?></p>
				</div>

				<?php if ( $last_synced ) : ?>
				<div class="ys-mb-field">
					<p class="ys-mb-label"><?php esc_html_e( 'Last Synced', 'yousync' ); ?></p>
					<input type="text" class="ys-mb-input" value="<?php echo esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?>" readonly disabled>
				</div>
				<?php else : ?>
				<p class="ys-not-synced-msg"><?php esc_html_e( 'This video has not been synced yet.', 'yousync' ); ?></p>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>
