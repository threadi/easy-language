<?php
/**
 * File for our own translation-parser.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

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
     * @param string $text
     * @return void
     */
    public function set_text( string $text ): void;

    /**
     * Return parsed texts.
     *
     * @return array
     */
    public function get_parsed_texts(): array;

    /**
     * Replace original text with translation.
     *
     * @param string $original_complete
     * @param string $translated_part
     * @return string
     */
    public function get_text_with_translations( string $original_complete, string $translated_part ): string;

    /**
     * Get the object-id which is parsed.
     *
     * @return int
     */
    public function get_object_id(): int;

    /**
     * Set the object-id which is parsed.
     *
     * @param int $object_id
     * @return void
     */
    public function set_object_id( int $object_id ): void;

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $object
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $object ): bool;

	/**
	 * Return pagebuilder-specific edit link for object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string;
}
