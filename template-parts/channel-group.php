<?php
declare(strict_types=1);
/**
 * Template part for a single channel group card.
 *
 * @package WPBuoy_Video_Sync
 *
 * Variables available in this template:
 * @var array $channel  Channel configuration data (the single free channel).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to wpbyvs_get_template_part()'s extract()/include scope, not globals.

$youtube_id          = $channel['youtube_id'] ?? '';
$ch_errors           = isset( $ch_errors ) && is_array( $ch_errors ) ? $ch_errors : array();
$channel_error       = $channel['_api_error'] ?? $ch_errors[ $youtube_id ] ?? '';
$is_new_channel      = ! $youtube_id || ! empty( $channel_error );
$history             = \WPBuoy_Video_Sync\Sync_History::get( $youtube_id );
$error_count         = \WPBuoy_Video_Sync\Sync_History::unread_error_count( $youtube_id );
$has_errors          = $error_count > 0;
$channel_title       = $channel['channel_title'] ?? '';
$channel_description = $channel['channel_description'] ?? '';
$subscriber_count    = isset( $channel['subscriber_count'] ) ? $channel['subscriber_count'] : '';
$sync_rules          = $channel['sync_rules'] ?? array();
$video_count         = $channel['video_count'] ?? 0;

$post_types        = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type = $channel['default_post_type'] ?? '';

$profile_picture  = $channel['profile_picture'] ?? array();
$profile_src      = '';
if ( ! empty( $profile_picture['attachment_id'] ) ) {
	$profile_src_data = wp_get_attachment_image_src( (int) $profile_picture['attachment_id'], 'thumbnail' );
	$profile_src      = $profile_src_data ? $profile_src_data[0] : '';
}
if ( ! $profile_src && ! empty( $profile_picture['url'] ) ) {
	$profile_src = $profile_picture['url'];
}

$name_prefix = 'channel[sync_rules]';

?>

<div class="wpbyvs-channel<?php echo $is_new_channel ? ' wpbyvs-channel--new' : ''; ?>" data-channel-index="0" data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>">
	<div class="wpbyvs-channel-header" role="button" tabindex="0" aria-expanded="true">
		<div class="wpbyvs-channel-icon">
			<?php if ( $profile_src ) : ?>
				<img src="<?php echo esc_url( $profile_src ); ?>" alt="" width="48" height="48" referrerpolicy="no-referrer">
			<?php else : ?>
				<?php echo esc_html( $channel_title ? mb_strtoupper( mb_substr( $channel_title, 0, 1 ) ) : 'C' ); ?>
			<?php endif; ?>
		</div>
		<h2>
			<?php
			if ( $channel_title ) {
				echo esc_html( $channel_title );
			} else {
				esc_html_e( 'Channel', 'wpbuoy-video-sync' );
			}
			?>
		</h2>
		<span class="dashicons dashicons-arrow-down-alt2 wpbyvs-accordion-icon" aria-hidden="true"></span>
	</div>
	<div class="wpbyvs-channel-body">

		<div class="wpbyvs-channel-tabs-nav" role="tablist">
			<button type="button" class="wpbyvs-channel-tab-btn wpbyvs-channel-tab-btn--active" data-tab="info" role="tab" aria-selected="true">
				<?php esc_html_e( 'Info', 'wpbuoy-video-sync' ); ?>
			</button>
			<button type="button" class="wpbyvs-channel-tab-btn" data-tab="rules" role="tab" aria-selected="false">
				<?php esc_html_e( 'Sync', 'wpbuoy-video-sync' ); ?>
			</button>
			<button type="button" class="wpbyvs-channel-tab-btn" data-tab="settings" role="tab" aria-selected="false">
				<?php esc_html_e( 'Settings', 'wpbuoy-video-sync' ); ?>
			</button>
			<button type="button" class="wpbyvs-channel-tab-btn" data-tab="history" role="tab" aria-selected="false">
				<?php esc_html_e( 'History', 'wpbuoy-video-sync' ); ?>
				<?php if ( $has_errors ) : ?>
				<span class="wpbyvs-history-badge" aria-label="<?php
					/* translators: %d: number of sync errors */
					echo esc_attr( sprintf( _n( '%d sync error', '%d sync errors', $error_count, 'wpbuoy-video-sync' ), $error_count ) );
				?>"><?php echo (int) $error_count; ?></span>
				<?php endif; ?>
			</button>
		</div>

		<div class="wpbyvs-channel-tabs-content">

			<?php /* Info tab */ ?>
			<div class="wpbyvs-channel-tab-panel" data-panel="info" role="tabpanel">

				<div class="wpbyvs-mb-fields">
					<div class="wpbyvs-mb-field<?php echo $channel_error ? ' wpbyvs-form-group--error' : ''; ?>">
						<label class="wpbyvs-mb-label" for="wpbyvs-youtube-id"><?php esc_html_e( 'Channel (channel URL or ID)', 'wpbuoy-video-sync' ); ?> <span class="wpbyvs-required" aria-hidden="true">*</span></label>
						<input
							type="text"
							id="wpbyvs-youtube-id"
							name="channel[youtube_id]"
							value="<?php echo esc_attr( $youtube_id ); ?>"
							class="wpbyvs-text"
							placeholder="<?php esc_attr_e( 'e.g. youtube.com/@channel or UCuAXFkgsw1L7xaCfnd5JJOw', 'wpbuoy-video-sync' ); ?>"
							required
						>
						<?php if ( $channel_error ) : ?>
						<p class="wpbyvs-field-error"><?php echo esc_html( $channel_error ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $channel_title ) : ?>
					<div class="wpbyvs-mb-field">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Title', 'wpbuoy-video-sync' ); ?></p>
						<input type="text" class="wpbyvs-text" value="<?php echo esc_attr( $channel_title ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( '' !== $subscriber_count ) : ?>
					<div class="wpbyvs-mb-field">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Subscribers', 'wpbuoy-video-sync' ); ?></p>
						<input type="text" class="wpbyvs-text" value="<?php echo esc_attr( number_format_i18n( (int) $subscriber_count ) ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( $video_count ) : ?>
					<div class="wpbyvs-mb-field">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Videos', 'wpbuoy-video-sync' ); ?></p>
						<input type="text" class="wpbyvs-text" value="<?php echo esc_attr( number_format_i18n( (int) $video_count ) ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( $channel_description ) : ?>
					<div class="wpbyvs-mb-field">
						<p class="wpbyvs-mb-label"><?php esc_html_e( 'Description', 'wpbuoy-video-sync' ); ?></p>
						<textarea class="wpbyvs-text" rows="3" disabled readonly><?php echo esc_textarea( $channel_description ); ?></textarea>
					</div>
					<?php endif; ?>
				</div>

			</div>

			<?php /* Sync Automation tab */ ?>
			<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="rules" role="tabpanel">

				<button type="button" class="wpbyvs-add-rule">
					<?php esc_html_e( 'Add sync rule', 'wpbuoy-video-sync' ); ?>
				</button>
				<div class="wpbyvs-rules wpbyvs-rules--init" data-video-count="<?php echo (int) $video_count; ?>">
					<?php
					foreach ( $sync_rules as $index => $rule ) {
						wpbyvs_get_template_part( 'sync-rule', null, array(
							'index'             => $index,
							'rule'              => $rule,
							'term_id'           => 0,
							'source_type'       => 'channel',
							'name_prefix'       => $name_prefix,
							'is_option_channel' => true,
						) );
					}
					?>
				</div>
				<?php
				wpbyvs_get_template_part( 'sync-rule-wizard', null, array(
					'default_post_type' => $default_post_type,
				) );
				?>

			</div>

			<?php /* Settings tab */ ?>
			<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="settings" role="tabpanel">

				<div class="wpbyvs-2-columns wpbyvs-cols-3-1">
					<div class="wpbyvs-form-group">
						<label for="wpbyvs-default-post-type">
							<?php esc_html_e( 'Default Post Type', 'wpbuoy-video-sync' ); ?>
							<span class="wpbyvs-help-wrap">
								<button type="button" class="wpbyvs-help-btn" aria-label="<?php esc_attr_e( 'More info', 'wpbuoy-video-sync' ); ?>">?</button>
								<span class="wpbyvs-help-tooltip" role="tooltip"><?php esc_html_e( 'Assign synced videos and playlists from this channel to this post type by default.', 'wpbuoy-video-sync' ); ?></span>
							</span>
						</label>
						<select
							id="wpbyvs-default-post-type"
							name="channel[default_post_type]"
							class="wpbyvs-select wpbyvs-channel-default-post-type"
						>
							<option value=""><?php esc_html_e( '— Select post type —', 'wpbuoy-video-sync' ); ?></option>
							<?php foreach ( $post_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt->name ); ?>"<?php selected( $default_post_type, $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

			</div>

		<?php /* History tab */ ?>
		<div class="wpbyvs-channel-tab-panel wpbyvs-hidden" data-panel="history" role="tabpanel">
			<?php if ( empty( $history ) ) : ?>
			<p class="wpbyvs-history-empty"><?php esc_html_e( 'No sync history yet.', 'wpbuoy-video-sync' ); ?></p>
			<?php else : ?>
			<ul class="wpbyvs-history-list">
				<?php
				$action_labels = array(
					'videos_sync_new'               => __( 'Sync new videos', 'wpbuoy-video-sync' ),
					'playlists_sync_new'            => __( 'Sync new playlists', 'wpbuoy-video-sync' ),
					'channel_sync_new'              => __( 'Sync channel', 'wpbuoy-video-sync' ),
				);
				?>
			<?php foreach ( $history as $entry ) :
					$entry_action   = $entry['rule_action'] ?? '';
					$entry_time     = $entry['timestamp'] ?? 0;
					$entry_duration = (int) ( $entry['duration'] ?? 0 );
					$entry_error    = $entry['has_error'] ?? false;
					$entry_errors   = $entry['errors'] ?? array();
					$entry_label    = $action_labels[ $entry_action ] ?? $entry_action;

					// Build conversational summary: "3 videos synced to Posts".
					$entry_count   = isset( $entry['items_count'] ) ? (int) $entry['items_count'] : null;
					$entry_dest_pt = $entry['destination_post_type'] ?? '';

					$count_part = '';
					if ( null !== $entry_count ) {
						$is_sync  = str_contains( $entry_action, 'sync' );
						$verb     = $is_sync ? __( 'synced', 'wpbuoy-video-sync' ) : __( 'updated', 'wpbuoy-video-sync' );
						if ( str_contains( $entry_action, 'playlist' ) ) {
							$resource = _n( 'playlist', 'playlists', $entry_count, 'wpbuoy-video-sync' );
						} elseif ( str_contains( $entry_action, 'channel' ) ) {
							$resource = _n( 'channel', 'channels', $entry_count, 'wpbuoy-video-sync' );
						} else {
							$resource = _n( 'video', 'videos', $entry_count, 'wpbuoy-video-sync' );
						}
						$count_part = $entry_count . ' ' . $resource . ' ' . $verb;
					}

					$pt_part = '';
					if ( $entry_dest_pt ) {
						$pt_obj  = get_post_type_object( $entry_dest_pt );
						$pt_name = $pt_obj ? $pt_obj->labels->name : $entry_dest_pt;
						/* translators: %s: post type label, e.g. "Posts" */
						$pt_part = ' ' . sprintf( __( 'to %s', 'wpbuoy-video-sync' ), $pt_name );
					}

					$entry_summary = $count_part . $pt_part;

					if ( $entry_error && ! empty( $entry_errors ) ) {
						$messages = array_values( array_filter( array_map( function ( $e ) {
							$msg = trim( $e['error'] ?? '' );
							return trim( preg_replace( '/\s*\(cURL error \d+:[^)]*\)/i', '', $msg ) );
						}, $entry_errors ) ) );
						if ( ! empty( $messages ) ) {
							$error_text    = implode( '. ', $messages );
							$entry_summary = $entry_summary ? $entry_summary . '. ' . $error_text : $error_text;
						}
					}
				?>
				<li class="wpbyvs-history-entry<?php echo $entry_error ? ' wpbyvs-history-entry--error' : ''; ?>">
					<div class="wpbyvs-history-entry-header">
						<span class="wpbyvs-history-entry-status" aria-hidden="true"></span>
						<span class="wpbyvs-history-entry-action">
							<?php echo esc_html( $entry_label ); ?>
							<?php if ( $entry_summary ) : ?>
							<span class="wpbyvs-history-entry-summary"><?php echo esc_html( $entry_summary ); ?></span>
							<?php endif; ?>
						</span>
						<span class="wpbyvs-history-entry-time"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry_time ) ); ?></span>
						<span class="wpbyvs-history-entry-duration"><?php
							/* translators: %d: sync run duration in seconds */
							printf( esc_html__( '%ds', 'wpbuoy-video-sync' ), absint( $entry_duration ) );
						?></span>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

		</div><!-- .wpbyvs-channel-tabs-content -->
	</div><!-- .wpbyvs-channel-body -->
</div>
