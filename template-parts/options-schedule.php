<?php
declare(strict_types=1);
$can_schedule = isset( $can_schedule ) ? (bool) $can_schedule : true;
$locked       = $can_schedule ? '' : 'disabled';
?>
<option disabled selected value="">&mdash; <?php esc_html_e( 'Select schedule', 'yousync'); ?> &mdash;</option>
<option value="once" <?php selected( $selected, 'once' ); ?>><?php esc_html_e( 'Once (run immediately after saving)', 'yousync'); ?></option>
<option value="hourly" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'yousync'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync'); ?></option>
<option value="daily" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'daily' ); ?>><?php esc_html_e( 'Daily', 'yousync'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync'); ?></option>
<option value="weekly" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'yousync'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync'); ?></option>
<option value="monthly" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'yousync'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync'); ?></option>
<option value="custom" <?php echo esc_attr( $locked ); ?> <?php selected( $selected, 'custom' ); ?>><?php esc_html_e( 'Custom', 'yousync'); ?><?php echo $can_schedule ? '' : esc_html__( ' (Pro)', 'yousync'); ?></option>
