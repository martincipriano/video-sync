<?php
declare(strict_types=1);
/**
 * YouSync Pro — General helper functions.
 *
 * @package YouSync
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize a single sync rule from form input.
 *
 * Shared by Channel, Playlist, and Channels_Page save handlers.
 *
 * @param array $rule Raw rule data from $_POST.
 * @return array Sanitized rule.
 */
function yousync_sanitize_sync_rule( $rule ) {
	$sanitized = array(
		'enabled'           => isset( $rule['enabled'] ) ? (bool) $rule['enabled'] : false,
		'title'             => isset( $rule['title'] ) ? sanitize_text_field( $rule['title'] ) : '',
		'max_videos'        => isset( $rule['max_videos'] ) ? absint( $rule['max_videos'] ) : 50,
		// Free runs every rule as a one-time sync; recurring schedules are Pro.
		'schedule'          => 'once',
		'action'            => isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '',
		'specific_metadata' => isset( $rule['specific_metadata'] ) && is_array( $rule['specific_metadata'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $rule['specific_metadata'] ) ) )
			: array(),
		'destination_post_type'      => isset( $rule['destination_post_type'] ) ? sanitize_key( $rule['destination_post_type'] ) : '',
	);

	return $sanitized;
}

/**
 * Return a map of public post-type slug → taxonomy <option> HTML.
 *
 * Used client-side to swap taxonomy options when the Post Type select changes.
 *
 * @return array<string, string>
 */
function yousync_get_taxonomy_options_by_post_type() {
	$all_public_taxes = get_taxonomies( [ 'public' => true ], 'objects' );
	$map              = [];
	foreach ( array_keys( get_post_types( [ 'public' => true ] ) ) as $pt_slug ) {
		$pt_tax_names = get_object_taxonomies( $pt_slug );
		$opts         = '<option value="">' . esc_html__( '&mdash; Select taxonomy &mdash;', 'yousync' ) . '</option>';
		foreach ( $all_public_taxes as $tax ) {
			if ( ! in_array( $tax->name, $pt_tax_names, true ) ) continue;
			$opts .= '<option value="' . esc_attr( $tax->name ) . '">'
				   . esc_html( $tax->labels->singular_name ) . '</option>';
		}
		$map[ $pt_slug ] = $opts;
	}
	return $map;
}

/**
 * Return an HTML string of <option> elements for all public taxonomies.
 *
 * Used to populate the taxonomy-select in the sync-rule taxonomy+term repeater.
 *
 * @return string
 */
function yousync_get_taxonomy_options_html() {
	$opts = '<option value="">' . esc_html__( '&mdash; Select taxonomy &mdash;', 'yousync' ) . '</option>';
	foreach ( get_taxonomies( [ 'public' => true ], 'objects' ) as $tax ) {
		$opts .= '<option value="' . esc_attr( $tax->name ) . '">'
			   . esc_html( $tax->labels->singular_name ) . '</option>';
	}
	return $opts;
}

/**
 * Return an HTML template string for a single taxonomy+term repeater row.
 *
 * Renders template-parts/taxonomy-term-row.php with {{PLACEHOLDER}} tokens
 * so JavaScript can replace them when inserting new rows dynamically.
 *
 * @return string
 */
function yousync_get_taxonomy_term_row_template(): string {
	return yousync_return_template_part( 'taxonomy-term-row', null, array(
		'name_prefix'      => '{{NAME_PREFIX}}',
		'rule_index'       => '{{RULE_INDEX}}',
		'tt_index'         => '{{TT_INDEX}}',
		'taxonomy_options' => '{{TAXONOMY_OPTIONS}}',
		'term_options'     => '',
		'term_disabled'    => true,
	) );
}
