<?php
declare(strict_types=1);
/**
 * Sync scheduler.
 *
 * Manages WP Cron events for Buoy Video Sync sync rules — one-time ('once')
 * and recurring (hourly/daily/weekly/monthly/custom) schedules alike.
 *
 * Uses the 'buoyvs_channel_config_sync_rule' hook with args [ $rule_index ].
 *
 * @package Buoy_Video_Sync
 */

namespace Buoy_Video_Sync;

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
	 * Cron hook name used for the option-based channel's sync rules (Channels Page).
	 * Args: [ $rule_index ] — single channel, no term ID needed.
	 */
	private const CRON_HOOK_CONFIG = 'buoyvs_channel_config_sync_rule';

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

		// Listener for the option-based channel's sync rules (Channels Page).
		add_action( self::CRON_HOOK_CONFIG, array( $this, 'dispatch_config_sync' ), 10, 1 );

		// Register custom cron intervals (monthly, custom-N-hour).
		add_filter( 'cron_schedules', array( $this, 'register_custom_intervals' ) );

		// Reschedule events when the Channels Page is saved.
		add_action( 'buoyvs_reschedule_channel_rules', array( $this, 'reschedule_channel_rules' ) );
	}

	// -------------------------------------------------------------------------
	// Cron dispatch
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a sync rule when the cron event fires.
	 *
	 * Called by WP Cron via the 'buoyvs_channel_config_sync_rule' hook.
	 *
	 * @param int $rule_index Rule index within the channel's sync_rules.
	 * @return void
	 */
	public function dispatch_config_sync( int $rule_index ): void {
		$this->runner->run_config_channel( $rule_index );
	}

	// -------------------------------------------------------------------------
	// Reschedule entry point
	// -------------------------------------------------------------------------

	/**
	 * Reschedule cron events for the channel's rules.
	 *
	 * Called via the 'buoyvs_reschedule_channel_rules' action, which is fired
	 * by Channels_Page::save_channels() after updating the option.
	 *
	 * @return void
	 */
	public function reschedule_channel_rules(): void {
		$channel = buoyvs_get_channel_config();

		// Capture existing scheduled events before clearing.
		$existing_events = array();
		foreach ( $channel['sync_rules'] ?? array() as $rule_index => $rule ) {
			$args  = array( (int) $rule_index );
			$event = wp_get_scheduled_event( self::CRON_HOOK_CONFIG, $args );
			if ( $event ) {
				$existing_events[ (int) $rule_index ] = $event;
			}
		}

		// Clear all existing events.
		$this->unschedule_all_rules();

		// Schedule new events. Track which rules fire immediately so we can
		// pre-mark them as syncing before the redirect — the overlay is
		// server-rendered and needs sync_status = 'syncing' in stored data.
		$immediate = array();
		foreach ( $channel['sync_rules'] ?? array() as $rule_index => $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$existing  = $existing_events[ (int) $rule_index ] ?? null;
			$fires_now = $this->schedule_rule( (int) $rule_index, $rule, $existing );
			if ( $fires_now ) {
				$immediate[] = (int) $rule_index;
			}
		}

		if ( ! empty( $immediate ) ) {
			$channel_now = buoyvs_get_channel_config();
			foreach ( $immediate as $rule_idx ) {
				if ( isset( $channel_now['sync_rules'][ $rule_idx ] ) ) {
					$channel_now['sync_rules'][ $rule_idx ]['sync_status']     = 'syncing';
					$channel_now['sync_rules'][ $rule_idx ]['sync_started_at'] = time();
				}
			}
			update_option( 'buoyvs_channel_config', $channel_now );
		}
	}

	// -------------------------------------------------------------------------
	// Custom cron intervals
	// -------------------------------------------------------------------------

	/**
	 * Register custom WP Cron intervals required by Buoy Video Sync rules.
	 *
	 * Always registers 'buoyvs_monthly' (30 days). Also scans the channel's
	 * rules to collect unique 'custom' schedule values and registers
	 * 'buoyvs_every_{N}h' intervals for each.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function register_custom_intervals( array $schedules ): array {
		// Monthly (30 days) — not built into WordPress.
		$schedules['buoyvs_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a Month (Buoy Video Sync)', 'buoy-video-sync' ),
		);

		foreach ( $this->collect_custom_schedule_hours() as $hours ) {
			$key = "buoyvs_every_{$hours}h";
			if ( ! isset( $schedules[ $key ] ) ) {
				$schedules[ $key ] = array(
					'interval' => $hours * HOUR_IN_SECONDS,
					/* translators: %d: number of hours */
					'display'  => sprintf( __( 'Every %d Hours (Buoy Video Sync)', 'buoy-video-sync' ), $hours ),
				);
			}
		}

		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Map a schedule value to a registered WP Cron interval name.
	 *
	 * @param string $schedule     Rule schedule value.
	 * @param int    $custom_hours Custom schedule in hours (used when $schedule = 'custom').
	 * @return string WP Cron interval name.
	 */
	private function wp_interval( string $schedule, int $custom_hours ): string {
		switch ( $schedule ) {
			case 'hourly':
				return 'hourly';
			case 'daily':
				return 'daily';
			case 'weekly':
				return 'weekly';
			case 'monthly':
				return 'buoyvs_monthly';
			case 'custom':
				$hours = max( 1, $custom_hours );
				return "buoyvs_every_{$hours}h";
			default:
				return 'daily';
		}
	}

	/**
	 * Collect unique custom_schedule hour values from the channel's rules.
	 *
	 * Used by register_custom_intervals() to pre-register every interval that
	 * might be needed before cron_schedules is called.
	 *
	 * @return int[] Unique hour values for custom schedules.
	 */
	private function collect_custom_schedule_hours(): array {
		$hours = array();

		$channel = buoyvs_get_channel_config();
		foreach ( $channel['sync_rules'] ?? array() as $rule ) {
			if ( ( $rule['schedule'] ?? '' ) === 'custom' && ! empty( $rule['custom_schedule'] ) ) {
				$hours[] = (int) $rule['custom_schedule'];
			}
		}

		return array_unique( $hours );
	}

	/**
	 * Unschedule every existing buoyvs_channel_config_sync_rule event.
	 *
	 * Sweeps the live cron array and removes all events for this hook, whatever
	 * their [ rule_index ] args — this also clears orphaned events left behind
	 * when a rule was deleted.
	 *
	 * @return void
	 */
	private function unschedule_all_rules(): void {
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

	/**
	 * Schedule a single cron event for a rule.
	 *
	 * @param int         $rule_index     Rule index.
	 * @param array       $rule           Rule data array.
	 * @param object|null $existing_event Previously scheduled event, or null.
	 * @return bool True if the rule fires immediately (schedule = 'once').
	 */
	private function schedule_rule( int $rule_index, array $rule, ?object $existing_event = null ): bool {
		$args     = array( $rule_index );
		$schedule = $rule['schedule'] ?? 'once';

		if ( 'once' === $schedule ) {
			wp_schedule_single_event( time(), self::CRON_HOOK_CONFIG, $args );
			return true;
		}

		$interval = $this->wp_interval( $schedule, (int) ( $rule['custom_schedule'] ?? 24 ) );

		if ( $existing_event && $existing_event->schedule === $interval ) {
			$start = $existing_event->timestamp;
		} elseif ( $existing_event ) {
			$schedules = wp_get_schedules();
			$start     = time() + ( $schedules[ $interval ]['interval'] ?? DAY_IN_SECONDS );
		} else {
			$start = time();
		}

		wp_schedule_event( $start, $interval, self::CRON_HOOK_CONFIG, $args );
		return false;
	}
}
