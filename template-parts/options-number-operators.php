<?php
declare(strict_types=1);

$operator = $args['operator'];
?>
<option disabled selected value="">&mdash; <?php esc_html_e( 'Select operator', 'yousync' ); ?> &mdash;</option>
<option value="greater_than" <?php selected( $operator, 'greater_than' ); ?>><?php esc_html_e( 'Greater than', 'yousync' ); ?></option>
<option value="less_than" <?php selected( $operator, 'less_than' ); ?>><?php esc_html_e( 'Less than', 'yousync' ); ?></option>
<option value="equal_to" <?php selected( $operator, 'equal_to' ); ?>><?php esc_html_e( 'Equal to', 'yousync' ); ?></option>
