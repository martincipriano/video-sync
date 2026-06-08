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

$post_types             = get_post_types( array( 'public' => true ), 'objects' );
$available_taxonomies   = get_taxonomies( array( 'public' => true ), 'objects' );
$default_post_type      = $channel['default_post_type'] ?? '';
$default_taxonomy_terms = $channel['default_taxonomy_terms'] ?? array();
$ys_has_taxonomy        = false;

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
				printf( esc_html__( 'Channel %d', 'yousync' ), $ch_index + 1 );
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
				<span class="ys-history-badge" aria-label="<?php echo esc_attr( sprintf( _n( '%d sync error', '%d sync errors', $error_count, 'yousync' ), $error_count ) ); ?>"><?php echo (int) $error_count; ?></span>
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
					'ch_index'                => $ch_index,
					'default_post_type'       => $default_post_type,
					'default_taxonomy_terms'  => $default_taxonomy_terms,
				) );
				?>

			</div>

			<?php /* Settings tab */ ?>
			<div class="ys-channel-tab-panel ys-hidden" data-panel="settings" role="tabpanel">

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

				<div class="ys-form-group ys-channel-taxonomy-terms-wrapper<?php echo ! $ys_has_taxonomy ? ' ys-taxonomy-terms-locked' : ''; ?><?php echo ( $default_post_type && ! empty( $available_taxonomies ) ) ? '' : ' ys-hidden'; ?>">
					<label>
						<?php esc_html_e( 'Default Taxonomy Terms', 'yousync' ); ?>
						<span class="ys-help-wrap">
							<button type="button" class="ys-help-btn" aria-label="<?php esc_attr_e( 'More info', 'yousync' ); ?>">?</button>
							<span class="ys-help-tooltip" role="tooltip"><?php esc_html_e( 'Automatically apply these taxonomy terms to posts created by sync automations in this channel.', 'yousync' ); ?></span>
						</span>
					</label>
					<div class="ys-taxonomy-terms ys-channel-taxonomy-terms">
						<?php foreach ( $default_taxonomy_terms as $tt_idx => $tt ) :
							$tt_taxonomy = sanitize_key( $tt['taxonomy'] ?? '' );
							$tt_term_ids = array_map( 'absint', (array) ( $tt['term_ids'] ?? array() ) );
							$tt_terms    = $tt_taxonomy ? get_terms( array( 'taxonomy' => $tt_taxonomy, 'hide_empty' => false ) ) : array();

							$_tax_opts = '<option value="">' . esc_html__( '&mdash; Select taxonomy &mdash;', 'yousync' ) . '</option>';
							foreach ( $available_taxonomies as $tax ) {
								$_tax_opts .= '<option value="' . esc_attr( $tax->name ) . '"' . selected( $tt_taxonomy, $tax->name, false ) . '>' . esc_html( $tax->labels->singular_name ) . '</option>';
							}
							$_term_opts = '<option value="">' . esc_html__( '&mdash; Select term &mdash;', 'yousync' ) . '</option>';
							if ( ! is_wp_error( $tt_terms ) ) {
								foreach ( $tt_terms as $term ) {
									$_term_opts .= '<option value="' . esc_attr( $term->term_id ) . '"' . ( in_array( $term->term_id, $tt_term_ids, true ) ? ' selected' : '' ) . '>' . esc_html( $term->name ) . '</option>';
								}
							}

							if ( $ys_has_taxonomy ) : ?>
						<div class="ys-taxonomy-term-row">
							<select name="channels[<?php echo esc_attr( $ch_index ); ?>][default_taxonomy_terms][<?php echo esc_attr( (string) $tt_idx ); ?>][taxonomy]" class="ys-select ys-taxonomy-select ys-channel-taxonomy-select"><?php echo $_tax_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
							<div class="ys-term-select-wrapper">
								<select name="channels[<?php echo esc_attr( $ch_index ); ?>][default_taxonomy_terms][<?php echo esc_attr( (string) $tt_idx ); ?>][term_ids][]" class="ys-select ys-term-select"<?php echo $tt_taxonomy ? '' : ' disabled'; ?>><?php echo $_term_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
							</div>
							<button type="button" class="ys-remove-taxonomy-term" aria-label="<?php esc_attr_e( 'Remove', 'yousync' ); ?>"></button>
						</div>
						<?php else : ?>
						<div class="ys-taxonomy-term-row ys-taxonomy-term-row--locked">
							<div class="ys-tax-locked-cell">
								<select class="ys-select ys-taxonomy-select" disabled><?php echo $_tax_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
								<div class="ys-tax-locked-overlay" aria-hidden="true"></div>
							</div>
							<div class="ys-tax-locked-cell ys-term-select-wrapper">
								<select class="ys-select ys-term-select" disabled><?php echo $_term_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></select>
								<div class="ys-tax-locked-overlay" aria-hidden="true"></div>
							</div>
							<button type="button" class="ys-remove-taxonomy-term-locked" aria-label="<?php esc_attr_e( 'Remove', 'yousync' ); ?>"></button>
						</div>
						<?php endif;
						endforeach; ?>
					</div>
					<button type="button" class="<?php echo $ys_has_taxonomy ? 'ys-add-taxonomy-term ys-channel-add-taxonomy-term' : 'ys-add-taxonomy-term-locked'; ?>"><?php esc_html_e( 'Add taxonomy term', 'yousync' ); ?></button>
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

					// Build conversational summary: "3 videos synced to Posts · assigned to Tutorial, Reviews, and Case Studies"
					$entry_count   = isset( $entry['items_count'] ) ? (int) $entry['items_count'] : null;
					$entry_dest_pt = $entry['destination_post_type'] ?? '';
					$entry_terms   = $entry['term_names'] ?? array();

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

					$terms_part = '';
					if ( ! empty( $entry_terms ) ) {
						$n = count( $entry_terms );
						if ( 1 === $n ) {
							/* translators: %s: taxonomy term name */
							$terms_part = ', ' . sprintf( __( 'assigned to %s', 'yousync' ), $entry_terms[0] );
						} elseif ( 2 === $n ) {
							/* translators: 1: first term name, 2: second term name */
							$terms_part = ', ' . sprintf( __( 'assigned to %1$s and %2$s', 'yousync' ), $entry_terms[0], $entry_terms[1] );
						} else {
							$last_term  = array_pop( $entry_terms );
							/* translators: 1: comma-separated term names, 2: last term name */
							$terms_part = ', ' . sprintf( __( 'assigned to %1$s, and %2$s', 'yousync' ), implode( ', ', $entry_terms ), $last_term );
						}
					}

					$entry_summary = $count_part . $pt_part . $terms_part;

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
						<span class="ys-history-entry-duration"><?php printf( esc_html__( '%ds', 'yousync' ), $entry_duration ); ?></span>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>

		</div><!-- .ys-channel-tabs-content -->
	</div><!-- .ys-channel-body -->
</div>
