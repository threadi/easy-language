<?php
/**
 * File for handling Gutenberg pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\Easy_Language\Parser;

use easyLanguage\Multilingual_plugins\Easy_Language\Parser;
use easyLanguage\Multilingual_plugins\Easy_Language\Parser_Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Post_Object;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler for parsing gutenberg-blocks.
 */
class Gutenberg extends Parser_Base implements Parser {
	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Gutenberg';

	/**
	 * Instance of this object.
	 *
	 * @var ?Gutenberg
	 */
	private static ?Gutenberg $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Gutenberg {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Return parsed texts.
	 *
	 * Loop through the blocks and save their flow-text-elements (e.g. paragraphs and headings) to list.
	 *
	 * @return array
	 */
	public function get_parsed_texts(): array {
		$resulting_texts = array();
		$blocks          = parse_blocks( $this->get_text() );
		foreach ( $blocks as $block ) {
			$resulting_texts = $this->get_block_text( $block, $resulting_texts );
		}
		return $resulting_texts;
	}

	/**
	 * Loop through the block and get its texts.
	 *
	 * @param array $block The block as array.
	 * @param array $resulting_texts The resulting texts as array.
	 * @return array
	 */
	private function get_block_text( array $block, array $resulting_texts ): array {
		// get content if it is a valid flow-text-block.
		if ( in_array( $block['blockName'], $this->get_flow_text_blocks(), true ) ) {
			$resulting_texts[] = $block['innerHTML'];
		}

		// loop through inner-blocks.
		foreach ( $block['innerBlocks'] as $block ) {
			$resulting_texts = $this->get_block_text( $block, $resulting_texts );
		}

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Define flow-text-blocks.
	 *
	 * @return array
	 */
	private function get_flow_text_blocks(): array {
		return apply_filters(
			'easy_language_gutenberg_blocks',
			array(
				'core/paragraph',
				'core/heading',
				'core/list-item',
			)
		);
	}

	/**
	 * Replace original text with translation.
	 * This is done 1:1 for Gutenberg.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $translated_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_translations( string $original_complete, string $translated_part ): string {
		return str_replace( $this->get_text(), $translated_part, $original_complete );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return has_blocks( $post_object->get_content() );
	}

	/**
	 * Return whether this pagebuilder plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return true;
	}
}
