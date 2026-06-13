<?php
declare(strict_types=1);
/**
 * Template part for a single channel group card.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var array $channel  Channel configuration data.
 * @var int   $ch_index Channel index (always 0 in the free plugin).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables are local to yousync_get_template_part()'s extract()/include scope, not globals.

$youtube_id          = $channel['youtube_id'] ?? '';
$ch_errors           = isset( $ch_errors ) && is_array( $ch_errors ) ? $ch_errors : array();
$channel_error       = $channel['_api_error'] ?? $ch_errors[ $youtube_id ] ?? '';
$is_new_channel      = ! $youtube_id || ! empty( $channel_error );
$history             = \YouSync\Sync_History::get( $youtube_id );
$error_count         = count( array_filter( $history, fn( $e ) => $e['has_error'] ?? false ) );
$has_errors          = \YouSync\Sync_History::has_unread_errors( $youtube_id );
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

$name_prefix = 'channels[' . $ch_index . '][sync_rules]';

?>

<div class="ys-channel<?php echo $is_new_channel ? ' ys-channel--new' : ''; ?>" data-channel-index="<?php echo esc_attr( $ch_index ); ?>" data-youtube-id="<?php echo esc_attr( $youtube_id ); ?>">
	<div class="ys-channel-header" role="button" tabindex="0" aria-expanded="true">
		<div class="ys-channel-icon">
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
				/* translators: %d: channel number */
				printf( esc_html__( 'Channel %d', 'yousync' ), absint( $ch_index + 1 ) );
			}
			?>
		</h2>
		<span class="dashicons dashicons-arrow-down-alt2 ys-accordion-icon" aria-hidden="true"></span>
	</div>
	<div class="ys-channel-body">

		<div class="ys-channel-tabs-nav" role="tablist">
			<button type="button" class="ys-channel-tab-btn ys-channel-tab-btn--active" data-tab="info" role="tab" aria-selected="true">
				<?php esc_html_e( 'Info', 'yousync' ); ?>
			</button>
			<button type="button" class="ys-channel-tab-btn" data-tab="rules" role="tab" aria-selected="false">
				<?php esc_html_e( 'Sync', 'yousync' ); ?>
			</button>
			<button type="button" class="ys-channel-tab-btn" data-tab="settings" role="tab" aria-selected="false">
				<?php esc_html_e( 'Settings', 'yousync' ); ?>
			</button>
			<button type="button" class="ys-channel-tab-btn" data-tab="history" role="tab" aria-selected="false">
				<?php esc_html_e( 'History', 'yousync' ); ?>
				<?php if ( $has_errors ) : ?>
				<span class="ys-history-badge" aria-label="<?php
					/* translators: %d: number of sync errors */
					echo esc_attr( sprintf( _n( '%d sync error', '%d sync errors', $error_count, 'yousync' ), $error_count ) );
				?>"><?php echo (int) $error_count; ?></span>
				<?php endif; ?>
			</button>
		</div>

		<div class="ys-channel-tabs-content">

			<?php /* Info tab */ ?>
			<div class="ys-channel-tab-panel" data-panel="info" role="tabpanel">

				<div class="ys-mb-fields">
					<div class="ys-mb-field<?php echo $channel_error ? ' ys-form-group--error' : ''; ?>">
						<label class="ys-mb-label" for="ys-youtube-id-<?php echo esc_attr( $ch_index ); ?>"><?php esc_html_e( 'Channel ID', 'yousync' ); ?> <span class="ys-required" aria-hidden="true">*</span></label>
						<input
							type="text"
							id="ys-youtube-id-<?php echo esc_attr( $ch_index ); ?>"
							name="channels[<?php echo esc_attr( $ch_index ); ?>][youtube_id]"
							value="<?php echo esc_attr( $youtube_id ); ?>"
							class="ys-text"
							placeholder="<?php esc_attr_e( 'e.g. UCuAXFkgsw1L7xaCfnd5JJOw', 'yousync' ); ?>"
							required
						>
						<?php if ( $channel_error ) : ?>
						<p class="ys-field-error"><?php echo esc_html( $channel_error ); ?></p>
						<?php endif; ?>
					</div>

					<?php if ( $channel_title ) : ?>
					<div class="ys-mb-field">
						<p class="ys-mb-label"><?php esc_html_e( 'Title', 'yousync' ); ?></p>
						<input type="text" class="ys-text" value="<?php echo esc_attr( $channel_title ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( '' !== $subscriber_count ) : ?>
					<div class="ys-mb-field">
						<p class="ys-mb-label"><?php esc_html_e( 'Subscribers', 'yousync' ); ?></p>
						<input type="text" class="ys-text" value="<?php echo esc_attr( number_format_i18n( (int) $subscriber_count ) ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( $video_count ) : ?>
					<div class="ys-mb-field">
						<p class="ys-mb-label"><?php esc_html_e( 'Videos', 'yousync' ); ?></p>
						<input type="text" class="ys-text" value="<?php echo esc_attr( number_format_i18n( (int) $video_count ) ); ?>" disabled readonly>
					</div>
					<?php endif; ?>

					<?php if ( $channel_description ) : ?>
					<div class="ys-mb-field">
						<p class="ys-mb-label"><?php esc_html_e( 'Description', 'yousync' ); ?></p>
						<textarea class="ys-text" rows="3" disabled readonly><?php echo esc_textarea( $channel_description ); ?></textarea>
					</div>
					<?php endif; ?>
				</div>

			</div>

			<?php /* Sync Automation tab */ ?>
			<div class="ys-channel-tab-panel ys-hidden" data-panel="rules" role="tabpanel">

				<button type="button" class="ys-add-rule">
					<?php esc_html_e( 'Add sync rule', 'yousync' ); ?>
				</button>
				<div class="ys-rules ys-rules--init" data-video-count="<?php echo (int) $video_count; ?>">
					<?php
					foreach ( $sync_rules as $index => $rule ) {
						// Pro-only rules (preserved from a prior Pro install) are kept in the
						// DB but not shown or editable in the free plugin.
						if ( yousync_rule_is_unsupported( $rule ) ) {
							continue;
						}
						yousync_get_template_part( 'sync-rule', null, array(
							'index'              => $index,
							'rule'               => $rule,
							'term_id'            => 0,
							'source_type'        => 'channel',
							'name_prefix'        => $name_prefix,
							'is_option_channel'  => true,
							'option_channel_idx' => $ch_index,
						) );
					}
					?>
				</div>
				<?php
				yousync_get_template_part( 'sync-rule-wizard', null, array(
					'ch_index'          => $ch_index,
					'default_post_type' => $default_post_type,
				) );
				?>

			</div>

			<?php /* Settings tab */ ?>
			<div class="ys-channel-tab-panel ys-hidden" data-panel="settings" role="tabpanel">

				<div class="ys-2-columns ys-cols-3-1">
					<div class="ys-form-group">
						<label for="ys-default-post-type-<?php echo esc_attr( $ch_index ); ?>">
							<?php esc_html_e( 'Default Post Type', 'yousync' ); ?>
							<span class="ys-help-wrap">
								<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
								<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Assign synced videos and playlists from this channel to this post type by default.', 'yousync' ); ?></span>
							</span>
						</label>
						<select
							id="ys-default-post-type-<?php echo esc_attr( $ch_index ); ?>"
							name="channels[<?php echo esc_attr( $ch_index ); ?>][default_post_type]"
							class="ys-select ys-channel-default-post-type"
						>
							<option value=""><?php esc_html_e( '— Select post type —', 'yousync' ); ?></option>
							<?php foreach ( $post_types as $pt ) : ?>
							<option value="<?php echo esc_attr( $pt->name ); ?>"<?php selected( $default_post_type, $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

			</div>

		<?php /* History tab */ ?>
		<div class="ys-channel-tab-panel ys-hidden" data-panel="history" role="tabpanel">
			<?php if ( empty( $history ) ) : ?>
			<p class="ys-history-empty"><?php esc_html_e( 'No sync history yet.', 'yousync' ); ?></p>
			<?php else : ?>
			<ul class="ys-history-list">
				<?php
				$action_labels = array(
					'videos_sync_new'               => __( 'Sync new videos', 'yousync' ),
					'playlists_sync_new'            => __( 'Sync new playlists', 'yousync' ),
					'channel_sync_new'              => __( 'Sync channel', 'yousync' ),
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
						$verb     = $is_sync ? __( 'synced', 'yousync' ) : __( 'updated', 'yousync' );
						if ( str_contains( $entry_action, 'playlist' ) ) {
							$resource = _n( 'playlist', 'playlists', $entry_count, 'yousync' );
						} elseif ( str_contains( $entry_action, 'channel' ) ) {
							$resource = _n( 'channel', 'channels', $entry_count, 'yousync' );
						} else {
							$resource = _n( 'video', 'videos', $entry_count, 'yousync' );
						}
						$count_part = $entry_count . ' ' . $resource . ' ' . $verb;
					}

					$pt_part = '';
					if ( $entry_dest_pt ) {
						$pt_obj  = get_post_type_object( $entry_dest_pt );
						$pt_name = $pt_obj ? $pt_obj->labels->name : $entry_dest_pt;
						/* translators: %s: post type label, e.g. "Posts" */
						$pt_part = ' ' . sprintf( __( 'to %s', 'yousync' ), $pt_name );
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
				<li class="ys-history-entry<?php echo $entry_error ? ' ys-history-entry--error' : ''; ?>">
					<div class="ys-history-entry-header">
						<span class="ys-history-entry-status material-icons-outlined" aria-hidden="true"><?php echo $entry_error ? 'error' : 'check_circle'; ?></span>
						<span class="ys-history-entry-action">
							<?php echo esc_html( $entry_label ); ?>
							<?php if ( $entry_summary ) : ?>
							<span class="ys-history-entry-summary"><?php echo esc_html( $entry_summary ); ?></span>
							<?php endif; ?>
						</span>
						<span class="ys-history-entry-time"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry_time ) ); ?></span>
						<span class="ys-history-entry-duration"><?php
							/* translators: %d: sync run duration in seconds */
							printf( esc_html__( '%ds', 'yousync' ), absint( $entry_duration ) );
						?></span>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

		</div><!-- .ys-channel-tabs-content -->
	</div><!-- .ys-channel-body -->
</div>
