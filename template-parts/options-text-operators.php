<?php
declare(strict_types=1);
$operator = $args['operator'];
?>
<option disabled selected value="">&mdash; <?php esc_html_e( 'Select operator', 'yousync'); ?> &mdash;</option>
<option value="contains" <?php selected( $operator, 'contains' ); ?>><?php esc_html_e( 'Contains', 'yousync'); ?></option>
<option value="not_contains" <?php selected( $operator, 'not_contains' ); ?>><?php esc_html_e( 'Does not contain', 'yousync'); ?></option>
<option value="equals" <?php selected( $operator, 'equals' ); ?>><?php esc_html_e( 'Matches', 'yousync'); ?></option>
<option value="not_equals" <?php selected( $operator, 'not_equals' ); ?>><?php esc_html_e( 'Does not match', 'yousync'); ?></option>
<option value="starts_with" <?php selected( $operator, 'starts_with' ); ?>><?php esc_html_e( 'Starts with', 'yousync'); ?></option>
<option value="ends_with" <?php selected( $operator, 'ends_with' ); ?>><?php esc_html_e( 'Ends with', 'yousync'); ?></option>
