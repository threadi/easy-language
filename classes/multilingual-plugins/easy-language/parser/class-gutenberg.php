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
	 * Define flow-text-blocks.
	 *
	 * @return array
	 */
	private function get_flow_text_blocks(): array {
		$blocks = array(
			'core/paragraph' => array(
				'html' => true,
			),
			'core/heading'   => array(
				'html' => true,
			),
			'core/list-item' => array(
				'html' => true,
			),
		);

		/**
		 * Filter the possible Gutenberg Blocks.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $blocks List of Blocks.
		 */
		return apply_filters( 'easy_language_gutenberg_blocks', $blocks );
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
		// get possible flow blocks.
		$flow_blocks = $this->get_flow_text_blocks();

		// get content if it is a valid flow-text-block.
		if ( isset( $flow_blocks[ $block['blockName'] ] ) ) {
			$add_to_result = $flow_blocks[ $block['blockName'] ];
			if ( ! empty( $flow_blocks[ $block['blockName'] ]['callback'] ) && is_callable( $flow_blocks[ $block['blockName'] ]['callback'] ) ) {
				$add_to_result['text'] = call_user_func( $flow_blocks[ $block['blockName'] ]['callback'], $block['blockName'] );
			} else {
				$add_to_result['text'] = trim( $block['innerHTML'] );
			}
			$resulting_texts[] = $add_to_result;
		}

		// loop through inner-blocks.
		foreach ( $block['innerBlocks'] as $block ) {
			$resulting_texts = $this->get_block_text( $block, $resulting_texts );
		}

		// return resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace original text with translation.
	 * This is done 1:1 for Gutenberg.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		return str_replace( $this->get_text(), $simplified_part, $original_complete );
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		return function_exists( 'has_blocks' ) && has_blocks( $post_object->get_content() );
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
