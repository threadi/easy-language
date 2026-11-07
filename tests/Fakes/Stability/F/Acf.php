<?php
/**
 * Minimal fakes of the ACF functions used by the ACF support.
 *
 * @package easy-language
 */

if ( ! function_exists( 'get_fields' ) ) {
	/**
	 * Return the fields of a post (only the test field "beschreibung").
	 *
	 * @param int  $post_id The post ID.
	 * @param bool $format_value Whether to format the value (like ACF does for wysiwyg fields).
	 *
	 * @return array<string,mixed>
	 */
	function get_fields( $post_id, $format_value = true ): array {
		$value = (string) get_post_meta( (int) $post_id, 'beschreibung', true );
		return array( 'beschreibung' => $format_value ? wpautop( $value ) : $value );
	}
}

if ( ! function_exists( 'get_field_object' ) ) {
	/**
	 * Return the settings of a field.
	 *
	 * @param string $key The field name.
	 * @param int    $post_id The post ID.
	 *
	 * @return array<string,mixed>
	 */
	function get_field_object( $key, $post_id ): array {
		return array(
			'name'                   => $key,
			'type'                   => 'wysiwyg',
			'easy_language_simplify' => 1,
		);
	}
}
