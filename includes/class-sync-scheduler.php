<?php
declare(strict_types=1);

/**
 * Sync scheduler.
 *
 * Manages WP Cron events for YouSync sync rules. Hooks into the
 * created/edited taxonomy actions (priority 20, after the meta save at 10)
 * to reschedule events whenever a channel or playlist is saved.
 *
 * All events use the single static hook 'yousync_sync_rule' with args
 * [ $source_type, $term_id, $rule_index ]. A single add_action registered
 * in the constructor ensures the listener is always available on every page
 * load — including front-end requests that trigger WP Cron.
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
	 * Static cron hook name used for all sync rule events.
	 */
	private const CRON_HOOK = 'yousync_sync_rule';

	/**
	 * Sync runner instance.
	 *
	 * @var Sync_Runner
	 */
	private Sync_Runner $runner;

	/**
	 * Maximum rule index to scan when unscheduling.
	 *
	 * A term will never realistically have more rules than this.
	 */
	private const MAX_RULE_INDEX = 100;

	/**
	 * Constructor.
	 *
	 * @param Sync_Runner $runner Sync runner to call when a cron event fires.
	 */
	public function __construct( Sync_Runner $runner ) {
		$this->runner = $runner;

		// Always register the cron listener so WP Cron can dispatch events
		// on any page load, not just when a term is being saved.
		add_action( self::CRON_HOOK, array( $this, 'dispatch_sync' ), 10, 3 );

		// Register custom cron intervals (monthly, custom-N-hour).
		add_filter( 'cron_schedules', array( $this, 'register_custom_intervals' ) );

	}

	// -------------------------------------------------------------------------
	// Cron dispatch
	// -------------------------------------------------------------------------

	/**
	 * Dispatch a sync rule when the cron event fires.
	 *
	 * Called by WP Cron via the 'yousync_sync_rule' hook.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Rule index.
	 * @return void
	 */
	public function dispatch_sync( string $source_type, int $term_id, int $rule_index ): void {
		$this->runner->run( $source_type, $term_id, $rule_index );
	}

	// -------------------------------------------------------------------------
	// Custom cron intervals
	// -------------------------------------------------------------------------

	/**
	 * Register custom WP Cron intervals required by YouSync rules.
	 *
	 * Always registers 'yousync_monthly' (30 days). Also scans all channel
	 * and playlist terms to collect unique 'custom' schedule values and
	 * registers 'yousync_every_{N}h' intervals for each.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public function register_custom_intervals( array $schedules ): array {
		// Monthly (30 days) — not built into WordPress.
		$schedules['yousync_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a Month (YouSync)', 'yousync' ),
		);

		// Collect unique custom_schedule values from all term metas.
		$custom_hours = $this->collect_custom_schedule_hours();

		foreach ( $custom_hours as $hours ) {
			$key = "yousync_every_{$hours}h";
			if ( ! isset( $schedules[ $key ] ) ) {
				$schedules[ $key ] = array(
					'interval' => $hours * HOUR_IN_SECONDS,
					/* translators: %d: number of hours */
					'display'  => sprintf( __( 'Every %d Hours (YouSync)', 'yousync' ), $hours ),
				);
			}
		}

		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Core scheduling logic
	// -------------------------------------------------------------------------

	/**
	 * Reschedule all cron events for a term's rules.
	 *
	 * 1. Clears all existing YouSync cron events for this term.
	 * 2. Schedules a new event for each enabled rule.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param array  $rules       Sync rules array from term meta.
	 * @return void
	 */
	private function reschedule_rules_for_term( string $source_type, int $term_id, array $rules ): void {
		// Clear every existing cron event for this term across all rule indices.
		$this->unschedule_all_rules_for_term( $source_type, $term_id );

		foreach ( $rules as $index => $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue; // Skip disabled rules.
			}

			$this->schedule_rule( $source_type, $term_id, (int) $index, $rule );
		}
	}

	/**
	 * Unschedule all existing cron events for a term.
	 *
	 * Iterates indices 0..MAX_RULE_INDEX and unschedules any queued event
	 * matching the static hook + args combination for this term.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @return void
	 */
	private function unschedule_all_rules_for_term( string $source_type, int $term_id ): void {
		for ( $i = 0; $i < self::MAX_RULE_INDEX; $i++ ) {
			$args      = array( $source_type, $term_id, $i );
			$timestamp = wp_next_scheduled( self::CRON_HOOK, $args );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK, $args );
			}
		}
	}

	/**
	 * Schedule a single cron event for a rule.
	 *
	 * Uses the static 'yousync_sync_rule' hook with [ source_type, term_id,
	 * rule_index ] as args. Skips scheduling if an event for this args
	 * combination is already queued.
	 *
	 * @param string $source_type 'channel' or 'playlist'.
	 * @param int    $term_id     Term ID.
	 * @param int    $rule_index  Rule index.
	 * @param array  $rule        Rule data array.
	 * @return void
	 */
	private function schedule_rule( string $source_type, int $term_id, int $rule_index, array $rule ): void {
		$args     = array( $source_type, $term_id, $rule_index );
		$schedule = $rule['schedule'] ?? 'daily';

		// Already scheduled — don't add another event.
		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}

		if ( 'once' === $schedule ) {
			wp_schedule_single_event( time(), self::CRON_HOOK, $args );
			return;
		}

		$interval = $this->wp_interval( $schedule, (int) ( $rule['custom_schedule'] ?? 24 ) );
		wp_schedule_event( time(), $interval, self::CRON_HOOK, $args );
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
	 * Collect unique custom_schedule hour values from the channel config.
	 *
	 * Reads from yousync_channel_config (wp_options) instead of taxonomy terms.
	 *
	 * @return int[] Unique hour values for custom schedules.
	 */
	private function collect_custom_schedule_hours(): array {
		$config = get_option( 'yousync_channel_config', array() );
		$hours  = array();

		foreach ( $config['sync_rules'] ?? array() as $rule ) {
			if ( ( $rule['schedule'] ?? '' ) === 'custom' && ! empty( $rule['custom_schedule'] ) ) {
				$hours[] = (int) $rule['custom_schedule'];
			}
		}

		return array_unique( $hours );
	}
}
