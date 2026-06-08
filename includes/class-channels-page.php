<?php
declare(strict_types=1);
/**
 * Channels admin page — top-level YouSync menu.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Channels_Page Class
 */
class Channels_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_yousync_save_channels', array( $this, 'save_channels' ) );
		add_action( 'wp_ajax_yousync_add_rule', array( $this, 'ajax_add_rule' ) );
		add_action( 'wp_ajax_yousync_sync_progress', array( $this, 'ajax_sync_progress' ) );
		add_action( 'wp_ajax_yousync_mark_history_read', array( $this, 'ajax_mark_history_read' ) );
	}

	/**
	 * Register the top-level YouSync menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'YouSync', 'yousync' ),
			__( 'YouSync', 'yousync' ),
			'manage_options',
			'yousync',
			array( $this, 'render_page' ),
			'dashicons-video-alt3',
			26
		);

		// Replace auto-generated first submenu item.
		add_submenu_page(
			'yousync',
			__( 'Channels', 'yousync' ),
			__( 'Channels', 'yousync' ),
			'manage_options',
			'yousync',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Channels page.
	 *
	 * @return void
	 */
	public function render_page() {
		$channel = get_option( 'yousync_channel_config', array() );
		if ( ! is_array( $channel ) ) {
			$channel = array();
		}

		// Downgrade safety: if a Pro multi-channel array was left behind, use the
		// first channel. Free only ever reads/writes a single flat channel.
		if ( isset( $channel[0] ) ) {
			$channel = is_array( $channel[0] ) ? $channel[0] : array();
		}

		$ch_errors = get_transient( 'yousync_ch_errors_' . get_current_user_id() );
		if ( $ch_errors ) {
			delete_transient( 'yousync_ch_errors_' . get_current_user_id() );
		}
		$ch_errors = is_array( $ch_errors ) ? $ch_errors : array();

		$has_api_key = ! empty( get_option( 'yousync_api_key', '' ) );

		yousync_get_template_part( 'channels', 'page', compact( 'channel', 'ch_errors', 'has_api_key' ) );
	}

	/**
	 * Enqueue assets on the Channels page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_yousync' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'material-icons-outlined', 'https://fonts.googleapis.com/icon?family=Material+Icons+Outlined', array(), null );
		wp_enqueue_style( 'tom-select', YOUSYNC_PLUGIN_URL . 'assets/vendor/tom-select/tom-select.min.css', array(), '2.4.3' );
		wp_enqueue_style( 'yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/css/admin.css', array( 'tom-select' ), YOUSYNC_VERSION );
		wp_enqueue_script( 'tom-select', YOUSYNC_PLUGIN_URL . 'assets/vendor/tom-select/tom-select.complete.min.js', array(), '2.4.3', true );
		wp_enqueue_script( 'yousync-admin', YOUSYNC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'tom-select' ), YOUSYNC_VERSION, true );

		// Render the sync-rule template with a channel-specific name_prefix placeholder
		// for the JS-inserted rule card.
		$name_prefix = 'channels[{{CHANNEL_INDEX}}][sync_rules]';

		wp_localize_script( 'yousync-admin', 'youSync', array(
			'syncRule' => array(
				'rule' => yousync_return_template_part( 'sync-rule', null, array( 'name_prefix' => $name_prefix ) ),
			),
			'isChannelsPage'       => true,
			'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
			'addRuleNonce'         => wp_create_nonce( 'yousync_add_rule' ),
			'syncProgressNonce'    => wp_create_nonce( 'yousync_sync_progress' ),
			'markHistoryReadNonce' => wp_create_nonce( 'yousync_mark_history_read' ),
		) );
	}

	/**
	 * Handle the channels form submission.
	 *
	 * @return void
	 */
	public function save_channels() {
		if ( ! isset( $_POST['yousync_channels_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['yousync_channels_nonce'] ) ), 'yousync_save_channels' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'yousync' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'yousync' ) );
		}

		$existing = get_option( 'yousync_channel_config', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		// Normalize existing to single-channel format for comparison.
		if ( isset( $existing['youtube_id'] ) ) {
			$old_channels = array( $existing );
		} else {
			$old_channels = is_array( $existing ) ? array_values( $existing ) : array();
		}

		// Free is single-channel — only the first posted channel is processed.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$first_channel   = ( isset( $_POST['channels'][0] ) && is_array( $_POST['channels'][0] ) ) ? $_POST['channels'][0] : array();
		$posted_channels = $first_channel ? array( $first_channel ) : array();
		$api_key         = get_option( 'yousync_api_key', '' );
		$new_channels    = array();
		$save_errors     = array();

		foreach ( $posted_channels as $ch_index => $ch_data ) {
			$old_ch = isset( $old_channels[ $ch_index ] ) ? $old_channels[ $ch_index ] : array();

			$old_youtube_id = $old_ch['youtube_id'] ?? '';
			$new_youtube_id = isset( $ch_data['youtube_id'] ) ? sanitize_text_field( wp_unslash( $ch_data['youtube_id'] ) ) : '';

			$channel = array(
				'youtube_id'    => $new_youtube_id,
				'channel_title' => $old_ch['channel_title'] ?? '',
			);

			// Preserve existing API-fetched data.
			foreach ( array( 'channel_description', 'subscriber_count', 'video_count', 'profile_picture', 'banner_image', 'etag' ) as $key ) {
				if ( isset( $old_ch[ $key ] ) ) {
					$channel[ $key ] = $old_ch[ $key ];
				}
			}

			// Sync rules.
			$old_rules = $old_ch['sync_rules'] ?? array();
			if ( isset( $ch_data['sync_rules'] ) && is_array( $ch_data['sync_rules'] ) ) {
				$new_rules = array_values( array_map( 'yousync_sanitize_sync_rule', $ch_data['sync_rules'] ) );

				foreach ( $new_rules as $i => &$new_rule ) {
					if ( isset( $old_rules[ $i ] ) ) {
						$new_rule['sync_status'] = $old_rules[ $i ]['sync_status'] ?? '';
						$new_rule['last_synced'] = $old_rules[ $i ]['last_synced'] ?? 0;
						$new_rule['sync_count']  = $old_rules[ $i ]['sync_count'] ?? 0;
						$new_rule['sync_errors'] = $old_rules[ $i ]['sync_errors'] ?? array();

						$old_schedule = $old_rules[ $i ]['schedule'] ?? null;
						if ( $old_schedule === $new_rule['schedule'] && ! empty( $old_rules[ $i ]['scheduled_at'] ) ) {
							$new_rule['scheduled_at'] = $old_rules[ $i ]['scheduled_at'];
						} else {
							$new_rule['scheduled_at'] = time();
						}
					} else {
						$new_rule['scheduled_at'] = time();
					}
				}
				unset( $new_rule );
				$channel['sync_rules'] = $new_rules;
			} else {
				$channel['sync_rules'] = array();
			}

			// Per-channel defaults.
			$channel['default_post_type'] = isset( $ch_data['default_post_type'] )
				? sanitize_key( wp_unslash( $ch_data['default_post_type'] ) )
				: '';

			// Auto-fetch channel data via YouTube API if channel ID changed or data is missing.
			if (
				$api_key &&
				$new_youtube_id &&
				(
					$new_youtube_id !== $old_youtube_id ||
					empty( $channel['channel_title'] ) ||
					! isset( $channel['subscriber_count'] )
				)
			) {
				$yt_data = ( new YouTube_API( $api_key ) )->get_channel_data( $new_youtube_id );
				if ( ! is_wp_error( $yt_data ) ) {
					$channel['channel_title']       = $yt_data['channel_title'];
					$channel['channel_description'] = $yt_data['channel_description'];
					$channel['subscriber_count']    = $yt_data['subscriber_count'];
					$channel['video_count']         = $yt_data['video_count'];
					$channel['profile_picture']     = $yt_data['profile_picture'];
					$channel['banner_image']        = $yt_data['banner_image'];
					$channel['etag']                = $yt_data['etag'];
				} else {
					$channel['_api_error'] = $yt_data->get_error_message();
					$save_errors[ $new_youtube_id ] = $channel['_api_error'];
				}
			}

			$new_channels[] = $channel;
		}

		// Drop channels with no YouTube ID.
		$new_channels = array_values( array_filter( $new_channels, fn( $ch ) => ! empty( $ch['youtube_id'] ) ) );

		// Persist the single flat channel.
		update_option( 'yousync_channel_config', $new_channels[0] ?? array() );

		// Schedule WP cron events for the channel's sync rules.
		do_action( 'yousync_reschedule_option_channels', $new_channels );

		if ( ! empty( $save_errors ) ) {
			set_transient( 'yousync_ch_errors_' . get_current_user_id(), $save_errors, 60 );
		}

		wp_safe_redirect( add_query_arg( 'yousync-channels-updated', '1', admin_url( 'admin.php?page=yousync' ) ) );
		exit;
	}

	/**
	 * AJAX handler — save a new sync rule from the wizard and return its accordion HTML.
	 *
	 * @return void
	 */
	public function ajax_add_rule(): void {
		check_ajax_referer( 'yousync_add_rule', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$ch_index = absint( $_POST['channel_index'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$raw_rule = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST['rule'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
			: array();

		$rule                  = yousync_sanitize_sync_rule( $raw_rule );
		$rule['scheduled_at']  = time();
		$rule['sync_status']   = '';
		$rule['last_synced']   = 0;
		$rule['sync_count']    = 0;
		$rule['sync_errors']   = array();

		// Load, normalize, append, save.
		$config   = get_option( 'yousync_channel_config', array() );
		$is_single = isset( $config['youtube_id'] );
		$channels = $is_single ? array( $config ) : array_values( (array) $config );

		if ( ! isset( $channels[ $ch_index ] ) ) {
			wp_send_json_error( 'invalid_channel', 400 );
		}

		if ( ! isset( $channels[ $ch_index ]['sync_rules'] ) ) {
			$channels[ $ch_index ]['sync_rules'] = array();
		}
		$channels[ $ch_index ]['sync_rules'][] = $rule;
		$rule_index = array_key_last( $channels[ $ch_index ]['sync_rules'] );

		update_option( 'yousync_channel_config', $is_single ? $channels[0] : $channels );
		do_action( 'yousync_reschedule_option_channels', $channels );

		// Render the accordion card for the new rule.
		$name_prefix = 'channels[' . $ch_index . '][sync_rules]';
		ob_start();
		yousync_get_template_part( 'sync-rule', null, array(
			'index'              => $rule_index,
			'rule'               => $rule,
			'term_id'            => 0,
			'source_type'        => 'channel',
			'name_prefix'        => $name_prefix,
			'is_option_channel'  => true,
			'option_channel_idx' => $ch_index,
		) );
		$html = ob_get_clean();

		wp_send_json_success( array(
			'rule_index' => $rule_index,
			'html'       => $html,
		) );
	}

	/**
	 * AJAX handler — return sync progress for a rule currently syncing.
	 *
	 * @return void
	 */
	public function ajax_sync_progress(): void {
		check_ajax_referer( 'yousync_sync_progress', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$ch_index   = absint( $_POST['ch_index'] ?? 0 );
		$rule_index = absint( $_POST['rule_index'] ?? 0 );

		$config   = get_option( 'yousync_channel_config', array() );
		$channels = isset( $config['youtube_id'] ) ? array( $config ) : array_values( (array) $config );
		$rule     = $channels[ $ch_index ]['sync_rules'][ $rule_index ] ?? null;

		if ( ! $rule ) {
			wp_send_json_error( 'not_found', 404 );
		}

		$sync_status = $rule['sync_status'] ?? '';

		if ( 'syncing' === $sync_status ) {
			$progress = get_transient( 'yousync_prog_' . $ch_index . '_' . $rule_index );
			wp_send_json_success( array(
				'status'  => 'syncing',
				'current' => $progress ? (int) $progress['current'] : 0,
				'total'   => $progress ? (int) $progress['total'] : 0,
			) );
		} else {
			$last_synced  = (int) ( $rule['last_synced'] ?? 0 );
			$progress     = get_transient( 'yousync_prog_' . $ch_index . '_' . $rule_index );
			wp_send_json_success( array(
				'status'            => $sync_status ?: 'idle',
				'last_synced'       => $last_synced,
				'last_synced_label' => $last_synced ? wp_date( 'F j, Y g:i A', $last_synced ) : '',
				'enabled'           => (bool) ( $rule['enabled'] ?? true ),
				'synced'            => $progress ? (int) $progress['current'] : 0,
				'total'             => $progress ? (int) $progress['total'] : 0,
			) );
		}
	}

	public function ajax_mark_history_read(): void {
		check_ajax_referer( 'yousync_mark_history_read', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		$youtube_id = sanitize_text_field( wp_unslash( $_POST['youtube_id'] ?? '' ) );
		if ( ! $youtube_id ) {
			wp_send_json_error( 'missing_youtube_id', 400 );
		}

		Sync_History::mark_read( $youtube_id );
		wp_send_json_success();
	}

}

new Channels_Page();
