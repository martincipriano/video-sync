<?php
declare(strict_types=1);
/**
 * Sync scheduler.
 *
 * Manages WP Cron events for YouSync sync rules.
 *
 * Option-based channel events use the 'yousync_channel_config_sync_rule' hook
 * with args [ $ch_index, $rule_index ].
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

		// Register custom cron intervals (monthly, custom-N-hour).
		add_filter( 'cron_schedules', array( $this, 'register_custom_intervals' ) );

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
	 * Reschedule cron events for all option-based channels.
	 *
	 * Called via the 'yousync_reschedule_option_channels' action, which is
	 * fired by Channels_Page::save_channels() after updating the option.
	 *
	 * Uses the CRON_HOOK_CONFIG hook with args [ $ch_index, $rule_index ]
	 * to avoid any term ID conflicts with the taxonomy-based system.
	 *
	 * @param array $channels Normalised array of channel config arrays.
	 * @return void
	 */
	public function reschedule_option_channels( array $channels ): void {
		// Capture existing scheduled events before clearing.
		$existing_events = array();
		foreach ( $channels as $ch_index => $channel ) {
			foreach ( $channel['sync_rules'] ?? array() as $rule_index => $rule ) {
				$args  = array( (int) $ch_index, (int) $rule_index );
				$event = wp_get_scheduled_event( self::CRON_HOOK_CONFIG, $args );
				if ( $event ) {
					$existing_events[ (int) $ch_index ][ (int) $rule_index ] = $event;
				}
			}
		}

		// Clear all existing option-channel events.
		$this->unschedule_all_option_rules();

		// Schedule new events. Track which rules fire immediately so we can
		// pre-mark them as syncing before the redirect — the overlay is
		// server-rendered and needs sync_status = 'syncing' in stored data.
		$immediate = array();
		foreach ( $channels as $ch_index => $channel ) {
			foreach ( $channel['sync_rules'] ?? array() as $rule_index => $rule ) {
				if ( empty( $rule['enabled'] ) ) {
					continue;
				}
				$existing  = $existing_events[ (int) $ch_index ][ (int) $rule_index ] ?? null;
				$fires_now = $this->schedule_option_rule( (int) $ch_index, (int) $rule_index, $rule, $existing );
				if ( $fires_now ) {
					$immediate[] = array( (int) $ch_index, (int) $rule_index );
				}
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
	// Custom cron intervals
	// -------------------------------------------------------------------------

	/**
	 * Register custom WP Cron intervals required by YouSync rules.
	 *
	 * Always registers 'yousync_monthly' (30 days). Also scans option-based
	 * channels to collect unique 'custom' schedule values and registers
	 * 'yousync_every_{N}h' intervals for each.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function register_custom_intervals( array $schedules ): array {
		// Monthly (30 days) — not built into WordPress.
		$schedules['yousync_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a Month (YouSync)', 'yousync'),
		);

		// Collect unique custom_schedule values from option-based channels.
		$custom_hours = $this->collect_custom_schedule_hours();

		foreach ( $custom_hours as $hours ) {
			$key = "yousync_every_{$hours}h";
			if ( ! isset( $schedules[ $key ] ) ) {
				$schedules[ $key ] = array(
					'interval' => $hours * HOUR_IN_SECONDS,
					/* translators: %d: number of hours */
					'display'  => sprintf( __( 'Every %d Hours (YouSync)', 'yousync'), $hours ),
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
				return 'yousync_monthly';
			case 'custom':
				$hours = max( 1, $custom_hours );
				return "yousync_every_{$hours}h";
			default:
				return 'daily';
		}
	}

	/**
	 * Collect unique custom_schedule hour values from all option-based channels.
	 *
	 * Used by register_custom_intervals() to pre-register every interval that
	 * might be needed before cron_schedules is called.
	 *
	 * @return int[] Unique hour values for custom schedules.
	 */
	private function collect_custom_schedule_hours(): array {
		$hours = array();

		$option_channels = yousync_normalize_channels( get_option( 'yousync_channel_config', array() ) );
		foreach ( $option_channels as $channel ) {
			if ( ! is_array( $channel ) ) {
				continue;
			}
			foreach ( $channel['sync_rules'] ?? array() as $rule ) {
				if ( ( $rule['schedule'] ?? '' ) === 'custom' && ! empty( $rule['custom_schedule'] ) ) {
					$hours[] = (int) $rule['custom_schedule'];
				}
			}
		}

		return array_unique( $hours );
	}

	/**
	 * Unschedule every existing yousync_channel_config_sync_rule event.
	 *
	 * Sweeps the live cron array and removes all events for this hook, whatever
	 * their [ ch_index, rule_index ] args. This supports an unlimited number of
	 * channels (Pro) and also clears orphaned events left behind when a channel
	 * or rule was deleted — neither of which a fixed index bound could cover.
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

	/**
	 * Schedule a single cron event for an option-based channel rule.
	 *
	 * Uses CRON_HOOK_CONFIG with [ $ch_index, $rule_index ] args.
	 *
	 * @param int         $ch_index      Channel index.
	 * @param int         $rule_index    Rule index.
	 * @param array       $rule          Rule data array.
	 * @param object|null $existing_event Previously scheduled event, or null.
	 * @return void
	 */
	private function schedule_option_rule( int $ch_index, int $rule_index, array $rule, ?object $existing_event = null ): bool {
		$args     = array( $ch_index, $rule_index );
		$schedule = $rule['schedule'] ?? 'daily';

		if ( 'once' === $schedule ) {
			wp_schedule_single_event( time(), self::CRON_HOOK_CONFIG, $args );
			return true;
		}

		// Enforce scheduled_sync license gate — downgrade to once if unlicensed.
		if ( ! false ) {
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
