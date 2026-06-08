<?php
declare(strict_types=1);
/**
 * Template part for a single taxonomy + term repeater row.
 *
 * Used both for server-side rendering (sync-rule.php) and as the JS insertion
 * template via yousync_get_taxonomy_term_row_template() — in the latter
 * case the variables are {{PLACEHOLDER}} token strings that JS replaces.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var string $name_prefix        Form name prefix (e.g. "channels[0][sync_rules]" or "{{NAME_PREFIX}}").
 * @var string|int $rule_index     Rule index or "{{RULE_INDEX}}".
 * @var string|int $tt_index       Taxonomy-term row index or "{{TT_INDEX}}".
 * @var string $taxonomy_options   HTML <option> elements for the taxonomy select (or "{{TAXONOMY_OPTIONS}}").
 * @var string $term_options       HTML <option> elements for the term select. Empty string for JS template.
 * @var bool   $term_disabled      Whether the term select starts disabled. Default true.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term_disabled = isset( $term_disabled ) ? (bool) $term_disabled : true;
?>
<div class="ys-taxonomy-term-row">
	<select name="<?php echo esc_attr( "{$name_prefix}[{$rule_index}][destination_taxonomy_terms][{$tt_index}][taxonomy]" ); ?>" class="ys-select ys-taxonomy-select">
		<?php echo $taxonomy_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caller is responsible for escaping. ?>
	</select>
	<div class="ys-term-select-wrapper">
		<select name="<?php echo esc_attr( "{$name_prefix}[{$rule_index}][destination_taxonomy_terms][{$tt_index}][term_ids][]" ); ?>" class="ys-select ys-term-select"<?php echo $term_disabled ? ' disabled' : ''; ?>>
			<option value=""><?php esc_html_e( '&mdash; Select term &mdash;', 'yousync' ); ?></option>
			<?php echo $term_options ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caller is responsible for escaping. ?>
		</select>
	</div>
	<button type="button" class="ys-remove-taxonomy-term" aria-label="<?php esc_attr_e( 'Remove', 'yousync' ); ?>"></button>
</div>
