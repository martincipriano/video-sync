<?php
/**
 * Video metabox template.
 *
 * @package YouSync
 *
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
 * @var int        $sync_count            Number of syncs.
 * @var array      $sync_errors           Sync errors array.
 * @var array      $thumbnails            Thumbnails array.
 * @var array      $thumbnail_size_labels Thumbnail size labels.
 * @var array|null $preview_thumb         Best thumbnail array or null.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php wp_nonce_field( $nonce_action, 'yousync_video_meta_nonce' ); ?>

<div class="yousync-metabox">
	<nav class="nav-tab-wrapper" style="margin-bottom:0; padding-bottom:0;">
		<a href="#" class="nav-tab nav-tab-active yousync-mb-tab" data-tab="details"><?php esc_html_e( 'Details', 'yousync' ); ?></a>
		<a href="#" class="nav-tab yousync-mb-tab" data-tab="yt-data"><?php esc_html_e( 'YouTube Data', 'yousync' ); ?></a>
		<?php if ( ! empty( $thumbnails ) ) : ?>
		<a href="#" class="nav-tab yousync-mb-tab" data-tab="thumbnails"><?php esc_html_e( 'Thumbnails', 'yousync' ); ?></a>
		<?php endif; ?>
		<a href="#" class="nav-tab yousync-mb-tab" data-tab="sync-status"><?php esc_html_e( 'Sync Status', 'yousync' ); ?></a>
	</nav>

	<!-- Details -->
	<div id="yousync-panel-details" class="yousync-mb-panel" style="padding-top:12px;">
		<table class="form-table">
			<?php if ( $video_id ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Video ID', 'yousync' ); ?></th>
				<td><code><?php echo esc_html( $video_id ); ?></code></td>
			</tr>
			<?php endif; ?>

			<?php if ( $video_url ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Video URL', 'yousync' ); ?></th>
				<td><a href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $video_url ); ?></a></td>
			</tr>
			<?php endif; ?>

			<?php if ( $video_id ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Embed Code', 'yousync' ); ?></th>
				<td>
					<div class="ys-copy-embed-wrap">
						<input type="text" class="code" value="<?php echo esc_attr( '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $video_id . '" frameborder="0" allowfullscreen></iframe>' ); ?>" readonly onclick="this.select()">
						<button type="button" class="button ys-copy-embed-btn" title="<?php esc_attr_e( 'Copy embed code', 'yousync' ); ?>"></button>
					</div>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ( $channel_id ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Channel ID', 'yousync' ); ?></th>
				<td><code><?php echo esc_html( $channel_id ); ?></code></td>
			</tr>
			<?php endif; ?>

			<tr>
				<th scope="row">
					<label for="yousync_manual_edits"><?php esc_html_e( 'Protected from Sync Rules', 'yousync' ); ?></label>
				</th>
				<td>
					<label <?php echo $manual_edits_disabled ? 'style="opacity:0.6;"' : ''; ?>>
						<input type="checkbox" name="yousync_manual_edits" id="yousync_manual_edits" value="1" <?php checked( $manual_edits ); ?> <?php echo $manual_edits_disabled ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Prevent sync rules from overwriting this video', 'yousync' ); ?>
					</label>
					<?php if ( $manual_edits_notice ) : ?>
					<span style="margin-left:6px; font-size:12px; color:#757575;"><?php echo esc_html( $manual_edits_notice ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<!-- YouTube Data -->
	<div id="yousync-panel-yt-data" class="yousync-mb-panel" style="display:none; padding-top:12px;">
		<table class="form-table">
			<?php if ( $original_title ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Original Title', 'yousync' ); ?></th>
				<td><?php echo esc_html( $original_title ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $original_description ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Description', 'yousync' ); ?></th>
				<td>
					<p style="margin:0; white-space:pre-wrap; max-height:7em; overflow-y:auto;"><?php echo esc_html( $original_description ); ?></p>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ( $channel_title ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Channel', 'yousync' ); ?></th>
				<td><?php echo esc_html( $channel_title ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $published_date ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Published Date', 'yousync' ); ?></th>
				<td><?php echo esc_html( $published_date ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $duration_seconds !== '' ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Duration', 'yousync' ); ?></th>
				<td>
					<?php
					$hours = floor( (int) $duration_seconds / 3600 );
					$mins  = floor( ( (int) $duration_seconds % 3600 ) / 60 );
					$secs  = (int) $duration_seconds % 60;
					echo esc_html(
						$hours > 0
							? sprintf( '%d:%02d:%02d', $hours, $mins, $secs )
							: sprintf( '%d:%02d', $mins, $secs )
					);
					?>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ( $view_count !== '' ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'View Count', 'yousync' ); ?></th>
				<td><?php echo esc_html( number_format( (int) $view_count ) ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $like_count !== '' ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Like Count', 'yousync' ); ?></th>
				<td><?php echo esc_html( number_format( (int) $like_count ) ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $comment_count !== '' ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Comment Count', 'yousync' ); ?></th>
				<td><?php echo esc_html( number_format( (int) $comment_count ) ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( ! $original_title && ! $channel_title && $duration_seconds === '' && $view_count === '' ) : ?>
			<tr>
				<td colspan="2"><p style="margin:0; color:#757575;"><?php esc_html_e( 'No YouTube data available yet.', 'yousync' ); ?></p></td>
			</tr>
			<?php endif; ?>
		</table>
	</div>

	<!-- Thumbnails -->
	<?php if ( ! empty( $thumbnails ) ) : ?>
	<div id="yousync-panel-thumbnails" class="yousync-mb-panel" style="display:none; padding-top:16px;">
		<?php if ( $preview_thumb ) : ?>
		<img src="<?php echo esc_url( $preview_thumb['url'] ); ?>" style="max-width:768px; width:100%; height:auto; display:block; margin-bottom:16px; border:1px solid #ddd;" alt="">
		<?php endif; ?>

		<table class="wp-list-table widefat fixed striped" style="max-width:768px;">
			<thead>
				<tr>
					<th style="width:140px;"><?php esc_html_e( 'Size', 'yousync' ); ?></th>
					<th><?php esc_html_e( 'URL', 'yousync' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) : ?>
					<?php if ( empty( $thumbnails[ $size ]['url'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<tr>
						<td><?php echo esc_html( $thumbnail_size_labels[ $size ] ); ?></td>
						<td><input type="text" value="<?php echo esc_attr( $thumbnails[ $size ]['url'] ); ?>" readonly style="width:100%; font-family:monospace; font-size:11px;" onclick="this.select()"></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Sync Status -->
	<div id="yousync-panel-sync-status" class="yousync-mb-panel" style="display:none; padding-top:12px;">
		<table class="form-table">
			<?php if ( $sync_source_type ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Source', 'yousync' ); ?></th>
				<td><?php echo esc_html( ucfirst( $sync_source_type ) ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $last_synced ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last Synced', 'yousync' ); ?></th>
				<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_synced ) ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( $sync_count ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Count', 'yousync' ); ?></th>
				<td><?php echo esc_html( $sync_count ); ?></td>
			</tr>
			<?php endif; ?>

			<?php if ( ! empty( $sync_errors ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sync Errors', 'yousync' ); ?></th>
				<td>
					<?php foreach ( $sync_errors as $sync_error ) : ?>
					<p style="margin:0 0 4px; color:#d63638;">
						<?php
						if ( ! empty( $sync_error['timestamp'] ) ) {
							echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sync_error['timestamp'] ) ) . ' &mdash; ';
						}
						echo esc_html( $sync_error['error'] ?? '' );
						if ( ! empty( $sync_error['code'] ) ) {
							echo ' <code>' . esc_html( $sync_error['code'] ) . '</code>';
						}
						?>
					</p>
					<?php endforeach; ?>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ( ! $sync_source_type && ! $last_synced && ! $sync_count && empty( $sync_errors ) ) : ?>
			<tr>
				<td colspan="2"><p style="margin:0; color:#757575;"><?php esc_html_e( 'This video has not been synced yet.', 'yousync' ); ?></p></td>
			</tr>
			<?php endif; ?>
		</table>
	</div>
</div>

