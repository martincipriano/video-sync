<?php
declare(strict_types=1);
/**
 * Sync scheduler.
 *
 * Queues sync rules as WP-Cron background jobs so imports run off the
 * save request. Each enabled rule fires a single immediate
 * 'wpbyvs_channel_config_sync_rule' event with args [ $rule_index ]; the
 * runner auto-disables the rule once it has run.
 *
 * @package WPBuoy_Video_Sync
 */

namespace WPBuoy_Video_Sync;

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
	 * Args: [ $rule_index ] — single channel, no term ID needed.
	 */
	private const CRON_HOOK_CONFIG = 'wpbyvs_channel_config_sync_rule';

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
		add_action( self::CRON_HOOK_CONFIG, array( $this, 'dispatch_config_sync' ), 10, 1 );

		// Reschedule option-based channel events when the Channels Page is saved.
		add_action( 'wpbyvs_reschedule_option_channels', array( $this, 'reschedule_option_channels' ) );
	}

	// -------------------------------------------------------------------------
	// Cron dispatch
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a sync rule for an option-based channel when the cron event fires.
	 *
	 * Called by WP Cron via the 'wpbyvs_channel_config_sync_rule' hook.
	 *
	 * @param int $rule_index Rule index within the channel's sync_rules.
	 * @return void
	 */
	public function dispatch_config_sync( int $rule_index ): void {
		$this->runner->run_config_channel( $rule_index );
	}

	// -------------------------------------------------------------------------
	// Reschedule entry points
	// -------------------------------------------------------------------------

	/**
	 * (Re)queue cron events for the channel's rules.
	 *
	 * Called via the 'wpbyvs_reschedule_option_channels' action, which is
	 * fired by Channels_Page::save_channels() after updating the option.
	 *
	 * Every enabled rule queues a single immediate background event.
	 * Rules that fire are pre-marked as syncing before the redirect so the
	 * server-rendered overlay has sync_status = 'syncing' in stored data.
	 *
	 * @return void
	 */
	public function reschedule_option_channels(): void {
		// Clear all existing option-channel events first.
		$this->unschedule_all_option_rules();

		$channel    = wpbyvs_get_channel_config();
		$immediate  = array();
		foreach ( $channel['sync_rules'] ?? array() as $rule_index => $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			wp_schedule_single_event( time(), self::CRON_HOOK_CONFIG, array( (int) $rule_index ) );
			$immediate[] = (int) $rule_index;
		}

		if ( ! empty( $immediate ) ) {
			foreach ( $immediate as $rule_idx ) {
				$channel['sync_rules'][ $rule_idx ]['sync_status']     = 'syncing';
				$channel['sync_rules'][ $rule_idx ]['sync_started_at'] = time();
			}
			update_option( 'wpbyvs_channel_config', $channel );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Unschedule every existing wpbyvs_channel_config_sync_rule event.
	 *
	 * Sweeps the live cron array and removes all events for this hook, whatever
	 * their [ rule_index ] args. This also clears orphaned events left behind
	 * when a rule was deleted.
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
