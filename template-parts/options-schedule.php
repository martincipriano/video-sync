<?php
declare(strict_types=1);
$can_schedule = isset( $can_schedule ) ? (bool) $can_schedule : true;
$locked       = $can_schedule ? '' : 'disabled';
?>
<option disabled selected value="">&mdash; <?php esc_html_e( 'Select schedule', 'yousync-pro'); ?> &mdash;</option>
<option value="once" <?php selected( $selected, 'once' ); ?>><?php esc_html_e( 'Once (run immediately after saving)', 'yousync-pro'); ?></option>
<option value="hourly" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'yousync-pro'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync-pro'); ?></option>
<option value="daily" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'daily' ); ?>><?php esc_html_e( 'Daily', 'yousync-pro'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync-pro'); ?></option>
<option value="weekly" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'yousync-pro'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync-pro'); ?></option>
<option value="monthly" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'yousync-pro'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync-pro'); ?></option>
<option value="custom" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'custom' ); ?>><?php esc_html_e( 'Custom', 'yousync-pro'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync-pro'); ?></option>
