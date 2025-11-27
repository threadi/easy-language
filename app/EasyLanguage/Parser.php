<?php
/**
 * File for our own simplification-parser.
 *
 * @package easy-language
 */

namespace easyLanguage\EasyLanguage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Parser for texts.
 */
interface Parser {
	/**
	 * Get name of the parser.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get text to parse.
	 *
	 * @return string
	 */
	public function get_text(): string;

	/**
	 * Set string to parse.
	 *
	 * @param string $text The text.
	 * @return void
	 */
	public function set_text( string $text ): void;

	/**
	 * Return parsed texts.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array;

	/**
	 * Replace original text with translation.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string;

	/**
	 * Get the object-id which is parsed.
	 *
	 * @return int
	 */
	public function get_object_id(): int;

	/**
	 * Set the object-id which is parsed.
	 *
	 * @param int $object_id The Id of the object to parse.
	 * @return void
	 */
	public function set_object_id( int $object_id ): void;

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool;

	/**
	 * Return pagebuilder-specific edit link for object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string;

	/**
	 * Run page-builder-specific tasks on object.
	 *
	 * @param Objects $post_object The object.
	 *
	 * @return void
	 */
	public function update_object( Objects $post_object ): void;
}
