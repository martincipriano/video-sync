<option value="once" <?php selected( $selected, 'once' ); ?>><?php esc_html_e( 'Once (immediate)', 'yousync' ); ?></option>
<option value="hourly" disabled <?php selected( $selected, 'hourly' ); ?>><?php esc_html_e( 'Hourly (Pro feature)', 'yousync' ); ?></option>
<option value="daily" disabled <?php selected( $selected, 'daily' ); ?>><?php esc_html_e( 'Daily (Pro feature)', 'yousync' ); ?></option>
<option value="weekly" disabled <?php selected( $selected, 'weekly' ); ?>><?php esc_html_e( 'Weekly (Pro feature)', 'yousync' ); ?></option>
<option value="monthly" disabled <?php selected( $selected, 'monthly' ); ?>><?php esc_html_e( 'Monthly (Pro feature)', 'yousync' ); ?></option>
<option value="custom" disabled <?php selected( $selected, 'custom' ); ?>><?php esc_html_e( 'Custom (Pro feature)', 'yousync' ); ?></option>
