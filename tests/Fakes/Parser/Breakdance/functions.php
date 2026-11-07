<?php
/**
 * Minimal fakes of the data functions of Breakdance for the tests of the parser.
 *
 * Breakdance saves its tree as JSON in the post meta "_breakdance_data": {"tree_json_string":"<JSON of the tree>"}.
 *
 * @package easy-language
 */

namespace Breakdance\Data;

if ( ! function_exists( 'Breakdance\Data\get_tree' ) ) {
	/**
	 * Return the tree of the given post.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return array<string,mixed>|false
	 */
	function get_tree( $post_id ) {
		$data = get_meta( (int) $post_id, '_breakdance_data' );
		if ( ! is_array( $data ) || ! isset( $data['tree_json_string'] ) || ! is_string( $data['tree_json_string'] ) ) {
			return false;
		}
		$tree = json_decode( $data['tree_json_string'], true );
		return is_array( $tree ) ? $tree : false;
	}
}

if ( ! function_exists( 'Breakdance\Data\get_meta' ) ) {
	/**
	 * Return the JSON-decoded value of a meta field.
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $key     The key of the meta field.
	 *
	 * @return mixed
	 */
	function get_meta( $post_id, $key ) {
		$value = get_post_meta( (int) $post_id, $key, true );
		return is_string( $value ) ? json_decode( $value, true ) : null;
	}
}

if ( ! function_exists( 'Breakdance\Data\set_meta' ) ) {
	/**
	 * Save a value JSON-encoded as meta field.
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $key     The key of the meta field.
	 * @param mixed  $value   The value.
	 *
	 * @return void
	 */
	function set_meta( $post_id, $key, $value ) {
		update_post_meta( (int) $post_id, $key, wp_slash( (string) wp_json_encode( $value ) ) );
	}
}
