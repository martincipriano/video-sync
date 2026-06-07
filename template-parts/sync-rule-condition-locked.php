<?php
declare(strict_types=1);
/**
 * Template part for displaying a locked (Pro) sync rule condition.
 *
 * Uses distinct class names so existing JS handlers never fire on these elements.
 * All inputs and selects are disabled so they cannot be focused or edited.
 * Click interactions are intercepted in admin.js to show a Pro upsell tooltip.
 *
 * @package YouSyncPro
 *
 * @var string|null $field_options_html Pre-rendered field options HTML.
 * @var string|null $operator_html      Pre-rendered operator options HTML.
 * @var string|null $value_html         Pre-rendered value input HTML.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_field_options = isset( $field_options_html ) && $field_options_html !== null && $field_options_html !== '';
$has_operator      = isset( $operator_html ) && $operator_html !== null && $operator_html !== '';
$has_value         = isset( $value_html ) && $value_html !== null && $value_html !== '';

// value_html is pre-rendered with disabled=true from the caller.
$locked_value_html = $has_value ? $value_html : '<input type="text" class="ys-text" disabled>';
?>

<div class="ys-3-columns ys-condition ys-condition--locked">
  <div class="ys-form-group ys-mb-0 ys-locked-cell">
    <select class="ys-select ys-condition-field-locked" disabled>
      <?php if ( $has_field_options ) : ?>
        <?php echo $field_options_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <?php else : ?>
        <option value="">&mdash; Select detail &mdash;</option>
      <?php endif; ?>
    </select>
    <div class="ys-condition-locked-overlay" aria-hidden="true"></div>
  </div>
  <div class="ys-form-group ys-mb-0 ys-locked-cell">
    <select class="ys-select ys-condition-operator-locked" disabled>
      <?php if ( $has_operator ) echo $operator_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </select>
    <div class="ys-condition-locked-overlay" aria-hidden="true"></div>
  </div>
  <div class="ys-form-group ys-mb-0 ys-condition-value-locked ys-locked-cell">
    <?php echo $locked_value_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <div class="ys-condition-locked-overlay" aria-hidden="true"></div>
  </div>
  <button class="ys-remove-condition-locked" type="button"></button>
</div>
