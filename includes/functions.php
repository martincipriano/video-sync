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
 * Normalize the yousync_channel_config option into a 0-indexed list of channels.
 *
 * The option is stored two ways: a single channel as a flat object (free /
 * single-channel) or multiple channels as an indexed array (pro). This is the
 * one place that distinction is decided, so the scheduler and the sync runner
 * never disagree about whether a config is flat or indexed.
 *
 * @param mixed $config Raw yousync_channel_config option value.
 * @return array<int, array> 0-indexed list of channel config arrays.
 */
function yousync_normalize_channels( $config ): array {
	if ( ! is_array( $config ) ) {
		return array();
	}

	// Flat single-channel object — has channel-level keys rather than integer indices.
	if ( isset( $config['youtube_id'] ) || isset( $config['sync_rules'] ) ) {
		return array( $config );
	}

	return array_values( $config );
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
		'schedule'          => isset( $rule['schedule'] ) ? sanitize_text_field( $rule['schedule'] ) : 'daily',
		'custom_schedule'   => isset( $rule['custom_schedule'] ) ? absint( $rule['custom_schedule'] ) : 24,
		'action'            => isset( $rule['action'] ) ? sanitize_text_field( $rule['action'] ) : '',
		'specific_metadata' => isset( $rule['specific_metadata'] ) && is_array( $rule['specific_metadata'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $rule['specific_metadata'] ) ) )
			: array(),
		'destination_post_type'      => isset( $rule['destination_post_type'] ) ? sanitize_key( $rule['destination_post_type'] ) : '',
		'destination_taxonomy_terms' => array(),
	);

	if ( isset( $rule['destination_taxonomy_terms'] ) && is_array( $rule['destination_taxonomy_terms'] ) ) {
		foreach ( $rule['destination_taxonomy_terms'] as $tt ) {
			if ( ! is_array( $tt ) ) {
				continue;
			}
			$taxonomy = isset( $tt['taxonomy'] ) ? sanitize_key( $tt['taxonomy'] ) : '';
			if ( '' === $taxonomy ) {
				continue;
			}
			$term_ids = isset( $tt['term_ids'] ) && is_array( $tt['term_ids'] )
				? array_values( array_filter( array_map( 'absint', $tt['term_ids'] ) ) )
				: array();
			$sanitized['destination_taxonomy_terms'][] = array(
				'taxonomy' => $taxonomy,
				'term_ids' => $term_ids,
			);
		}
	}

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
