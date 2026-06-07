<?php
declare(strict_types=1);
$operator = $args['operator'];
?>
<option disabled selected value="">&mdash; <?php esc_html_e( 'Select operator', 'yousync-pro'); ?> &mdash;</option>
<option value="before" <?php selected( $operator, 'before' ); ?>><?php esc_html_e( 'Before', 'yousync-pro'); ?></option>
<option value="after" <?php selected( $operator, 'after' ); ?>><?php esc_html_e( 'After', 'yousync-pro'); ?></option>
<option value="on" <?php selected( $operator, 'on' ); ?>><?php esc_html_e( 'On', 'yousync-pro'); ?></option>
