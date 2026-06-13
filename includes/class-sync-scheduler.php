<?php
declare(strict_types=1);
/**
 * Sync scheduler.
 *
 * Manages WP Cron events for YouSync sync rules.
 *
 * The free plugin runs every rule as a one-time, immediate sync. Each enabled
 * rule fires a single 'yousync_channel_config_sync_rule' event with args
 * [ $ch_index, $rule_index ]; the runner auto-disables the rule once it has run.
 *
 * @package YouSync
 */

namespace YouSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Sync_Scheduler
 *
 * Registers and deregisters WP Cron events for sync rules.
 */
class Sync_Scheduler {

	/**
	 * Cron hook name used for option-based channel sync rules (Channels Page).
	 * Args: [ $ch_index, $rule_index ] — no term ID needed.
	 */
	private const CRON_HOOK_CONFIG = 'yousync_channel_config_sync_rule';

	/**
	 * Sync runner instance.
	 *
	 * @var Sync_Runner
	 */
	private Sync_Runner $runner;

	/**
	 * Constructor.
	 *
	 * @param Sync_Runner $runner Sync runner to call when a cron event fires.
	 */
	public function __construct( Sync_Runner $runner ) {
		$this->runner = $runner;

		// Listener for option-based channel sync rules (Channels Page).
		add_action( self::CRON_HOOK_CONFIG, array( $this, 'dispatch_config_sync' ), 10, 2 );

		// Reschedule option-based channel events when the Channels Page is saved.
		add_action( 'yousync_reschedule_option_channels', array( $this, 'reschedule_option_channels' ), 10, 1 );
	}

	// -------------------------------------------------------------------------
	// Cron dispatch
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a sync rule for an option-based channel when the cron event fires.
	 *
	 * Called by WP Cron via the 'yousync_channel_config_sync_rule' hook.
	 *
	 * @param int $ch_index   Channel index in yousync_channel_config.
	 * @param int $rule_index Rule index within that channel's sync_rules.
	 * @return void
	 */
	public function dispatch_config_sync( int $ch_index, int $rule_index ): void {
		$this->runner->run_config_channel( $ch_index, $rule_index );
	}

	// -------------------------------------------------------------------------
	// Reschedule entry points
	// -------------------------------------------------------------------------

	/**
	 * (Re)schedule cron events for all option-based channels.
	 *
	 * Called via the 'yousync_reschedule_option_channels' action, which is
	 * fired by Channels_Page::save_channels() after updating the option.
	 *
	 * Every enabled rule fires a single immediate event. Rules that fire are
	 * pre-marked as syncing before the redirect so the server-rendered overlay
	 * has sync_status = 'syncing' in stored data.
	 *
	 * @param array $channels Normalised array of channel config arrays.
	 * @return void
	 */
	public function reschedule_option_channels( array $channels ): void {
		// Clear all existing option-channel events first.
		$this->unschedule_all_option_rules();

		$immediate = array();
		foreach ( $channels as $ch_index => $channel ) {
			// Free manages a single channel; any extra channels are preserved Pro
			// data and must never be scheduled or run here.
			if ( 0 !== (int) $ch_index ) {
				continue;
			}
			foreach ( $channel['sync_rules'] ?? array() as $rule_index => $rule ) {
				if ( empty( $rule['enabled'] ) ) {
					continue;
				}
				// Pro-only rules preserved from a prior Pro install — never scheduled in free.
				if ( yousync_rule_is_unsupported( $rule ) ) {
					continue;
				}
				$args = array( (int) $ch_index, (int) $rule_index );
				wp_schedule_single_event( time(), self::CRON_HOOK_CONFIG, $args );
				$immediate[] = $args;
			}
		}

		if ( ! empty( $immediate ) ) {
			// Read the option as-is to preserve the flat vs indexed format that
			// save_channels() chose — do not overwrite the whole structure.
			$config  = get_option( 'yousync_channel_config', array() );
			$is_flat = is_array( $config ) && isset( $config['youtube_id'] );

			foreach ( $immediate as [ $ch_idx, $rule_idx ] ) {
				if ( $is_flat && 0 === $ch_idx ) {
					$config['sync_rules'][ $rule_idx ]['sync_status']     = 'syncing';
					$config['sync_rules'][ $rule_idx ]['sync_started_at'] = time();
				} elseif ( ! $is_flat && isset( $config[ $ch_idx ] ) ) {
					$config[ $ch_idx ]['sync_rules'][ $rule_idx ]['sync_status']     = 'syncing';
					$config[ $ch_idx ]['sync_rules'][ $rule_idx ]['sync_started_at'] = time();
				}
			}

			update_option( 'yousync_channel_config', $config );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Unschedule every existing yousync_channel_config_sync_rule event.
	 *
	 * Sweeps the live cron array and removes all events for this hook, whatever
	 * their [ ch_index, rule_index ] args. This also clears orphaned events left
	 * behind when a channel or rule was deleted.
	 *
	 * @return void
	 */
	private function unschedule_all_option_rules(): void {
		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return;
		}

		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! isset( $hooks[ self::CRON_HOOK_CONFIG ] ) ) {
				continue;
			}
			foreach ( $hooks[ self::CRON_HOOK_CONFIG ] as $event ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK_CONFIG, $event['args'] );
			}
		}
	}
}
