<?php
declare(strict_types=1);
  $rule_index      = isset( $rule_index ) ? $rule_index : '{{RULE_INDEX}}';
  $condition_index = isset( $condition_index ) ? $condition_index : '{{CONDITION_INDEX}}';
  $name_prefix     = isset( $name_prefix ) ? $name_prefix : 'sync_rules';
  $is_disabled     = isset( $disabled ) ? (bool) $disabled : true;
  $input_value     = isset( $value ) ? $value : '';
?>
<input
  class="ys-text ys-condition-value"
  <?php echo $is_disabled ? 'disabled' : ''; ?>
  id="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-value"
  name="<?php echo esc_attr( $name_prefix ); ?>[<?php echo esc_attr( $rule_index ); ?>][conditions][<?php echo esc_attr( $condition_index ); ?>][value]"
  type="text"
  value="<?php echo esc_attr( $input_value ); ?>"
  placeholder="<?php esc_attr_e( 'Enter value...', 'yousync'); ?>"
>