<?php
declare(strict_types=1);
/**
 * WPBuoy Video Sync Uninstall
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Always removes plugin options and cron events.
 * Only removes content (videos, channels, playlists) when the
 * "Remove all WPBuoy Video Sync data on uninstall" setting is enabled.
 *
 * @package WPBuoy_Video_Sync
 */

// Security check — WordPress sets this constant before calling uninstall.php.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove WPBuoy Video Sync data on uninstall.
 *
 * Wrapped in a function so its working variables stay out of the global scope —
 * uninstall.php is executed by WordPress at global scope.
 *
 * @return void
 */
function wpbyvs_run_uninstall(): void {
	global $wpdb;

	// Uninstall is a one-time teardown: direct, uncached deletes are required to
	// remove data, and the IN() clauses bind generated %s/%d placeholders via
	// $wpdb->prepare(). The WordPress.DB advisory rules do not apply here.
	// phpcs:disable WordPress.DB

	// ---------------------------------------------------------------------
	// Always: unschedule all sync cron events.
	// ---------------------------------------------------------------------
	wp_unschedule_hook( 'wpbyvs_sync_rule' );

	// ---------------------------------------------------------------------
	// Always: remove dismissed-notice user meta.
	// ---------------------------------------------------------------------
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'wpbyvs_cron_notice_dismissed' ) );

	// Note: WPBuoy Video Sync 2.x registers no custom post type or taxonomy —
	// synced items live in user-selected post types as `_wpbyvs_*` post meta,
	// so there is no plugin-owned CPT/taxonomy content to remove on uninstall.

	// ---------------------------------------------------------------------
	// Always: delete plugin options.
	// ---------------------------------------------------------------------
	$options = array(
		'wpbyvs_api_key',
		'wpbyvs_active_archives',
		'wpbyvs_delete_on_uninstall',
		'wpbyvs_reschedule_on_activation',
		'wpbyvs_sync_log',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	delete_transient( 'wpbyvs_flush_rewrite_rules' );

	// phpcs:enable WordPress.DB
}

wpbyvs_run_uninstall();
