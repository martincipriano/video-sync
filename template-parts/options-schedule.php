<?php
declare(strict_types=1);
/**
 * Template part: <option> list for a sync rule's schedule <select>.
 *
 * @package Buoy_Video_Sync
 *
 * Variables available in this template:
 * @var string $selected Currently selected schedule value.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$selected = isset( $selected ) ? $selected : 'once';
?>
<option value="once" <?php selected( $selected, 'once' ); ?>><?php esc_html_e( 'Once (run immediately after enabling and saving)', 'buoy-video-sync' ); ?></option>
<option value="hourly" <?php selected( $selected, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'buoy-video-sync' ); ?></option>
<option value="daily" <?php selected( $selected, 'daily' ); ?>><?php esc_html_e( 'Daily', 'buoy-video-sync' ); ?></option>
<option value="weekly" <?php selected( $selected, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'buoy-video-sync' ); ?></option>
<option value="monthly" <?php selected( $selected, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'buoy-video-sync' ); ?></option>
<option value="custom" <?php selected( $selected, 'custom' ); ?>><?php esc_html_e( 'Custom', 'buoy-video-sync' ); ?></option>
