<?php
/**
 * File for interface which describe translatable and translated objects (cpt like page, post or taxonomy like category).
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language;

// prevent direct access.
use easyLanguage\Api_Simplifications;

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the object.
 */
interface Easy_Language_Interface {

	/**
	 * Return the object language depending on object type.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_language(): array;

	/**
	 * Return the type of this object.
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Return the ID of this object.
	 *
	 * @return int
	 */
	public function get_id(): int;

	/**
	 * Get post-ID of the original post.
	 *
	 * @return int
	 */
	public function get_original_object_as_int(): int;

	/**
	 * Return whether this object is a simplified object.
	 *
	 * @return bool
	 */
	public function is_simplified(): bool;

	/**
	 * Return whether this object is a simplifiable object.
	 *
	 * @return bool
	 */
	public function is_simplifiable(): bool;

	/**
	 * Return whether a given post type is translated in given language.
	 *
	 * @param string $language The language to check.
	 *
	 * @return bool
	 */
	public function is_simplified_in_language( string $language ): bool;

	/**
	 * Get the pagebuilder used by this object.
	 *
	 * @return object|false
	 */
	public function get_page_builder(): object|false;

	/**
	 * Process multiple text-simplification of a single object-object (like a post).
	 *
	 * @param Api_Simplifications $simplification_obj The simplification-object of the used API.
	 * @param array<string,mixed> $language_mappings The language-mappings.
	 * @param int                 $limit Limit the entries processed during this request.
	 * @param bool                $initialization Mark if this is the initialization of a simplification.
	 *
	 * @return int
	 */
	public function process_simplifications( Api_Simplifications $simplification_obj, array $language_mappings, int $limit = 0, bool $initialization = true ): int;

	/**
	 * Return whether this object is locked or not.
	 *
	 * @return bool
	 */
	public function is_locked(): bool;
}
