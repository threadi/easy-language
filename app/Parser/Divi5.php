<?php
/**
 * File for parsing Divi 5 blocks for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;

/**
 * Handler for parsing Divi 5-blocks.
 */
class Divi5 extends Parser_Base implements Parser {
	/**
	 * The internal name of this parser.
	 *
	 * @var string
	 */
	protected string $name = 'Divi 5';

	/**
	 * Instance of this object.
	 *
	 * @var ?Divi5
	 */
	private static ?Divi5 $instance = null;

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
	public static function get_instance(): Divi5 {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-blocks.
	 *
	 * @return array<string,mixed>
	 */
	private function get_flow_text_blocks(): array {
		$blocks = array(
			'divi/text'           => array(
				'html'     => true,
				'callback' => function ( $content ) {
					return $content['attrs']['content']['innerContent']['desktop']['value'] ?? '';
				},
			),
			'divi/heading'        => array(
				'html'  => true,
				'paths' => array(
					'title.innerContent.desktop.value',
				),
			),
			'divi/button'         => array(
				'html'  => true,
				'paths' => array(
					'button.innerContent.desktop.value.text',
					'content.module.text.desktop.value',
				),
			),
			'divi/icon-list-item' => array(
				'html'  => true,
				'paths' => array(
					'title.innerContent.desktop.value',
					'content.innerContent.desktop.value',
				),
			),
		);

		/**
		 * Filter the possible Divi 5 blocks.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 *
		 * @param array<string,mixed> $blocks List of Blocks.
		 */
		return apply_filters( 'easy_language_divi5_blocks', $blocks );
	}

	/**
	 * Return parsed texts.
	 *
	 * Loop through the blocks and save their flow-text-elements (e.g., paragraphs and headings) to the list.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		$resulting_texts = array();
		$blocks          = parse_blocks( $this->get_text() );
		foreach ( $blocks as $block ) {
			$resulting_texts = $this->get_block_text( $block, $resulting_texts );
		}

		// return the resulting list of parse texts.
		return $resulting_texts;
	}

	/**
	 * Loop through the block and get its texts.
	 *
	 * @param array<string,mixed> $block The block as an array.
	 * @param array<int,mixed>    $resulting_texts The resulting texts as an array.
	 * @return array<int,mixed>
	 */
	private function get_block_text( array $block, array $resulting_texts ): array {
		// get possible flow blocks.
		$flow_blocks = $this->get_flow_text_blocks();

		// get content if it is a valid flow-text-block.
		if ( isset( $flow_blocks[ $block['blockName'] ] ) ) {
			$add_to_result  = $flow_blocks[ $block['blockName'] ];
			$attribute_path = $this->get_text_attribute_path( $block, $flow_blocks[ $block['blockName'] ] );
			if ( ! empty( $flow_blocks[ $block['blockName'] ]['callback'] ) && is_callable( $flow_blocks[ $block['blockName'] ]['callback'] ) ) {
				$add_to_result['text'] = call_user_func( $flow_blocks[ $block['blockName'] ]['callback'], $block );
			} elseif ( '' !== $attribute_path ) {
				// Divi 5 saves the text of its modules in the attributes of the block.
				$add_to_result['text'] = trim( (string) $this->get_attribute_by_path( $block['attrs'], $attribute_path ) );
			} else {
				$add_to_result['text'] = trim( $block['innerHTML'] );
			}
			unset( $add_to_result['paths'] );
			$resulting_texts[] = $add_to_result;
		}

		// loop through inner-blocks.
		foreach ( $block['innerBlocks'] as $inner_block ) {
			$resulting_texts = $this->get_block_text( $inner_block, $resulting_texts );
		}

		// return the resulting list.
		return $resulting_texts;
	}

	/**
	 * Replace the original text with a translation.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// get possible flow blocks.
		$flow_blocks = $this->get_flow_text_blocks();

		// parse the original blocks.
		$blocks = parse_blocks( $original_complete );
		foreach ( $blocks as $index => $block ) {
			$blocks[ $index ] = $this->get_text_with_simplifications_deep( $original_complete, $simplified_part, $block, $flow_blocks );
		}

		// return the resulting serialized block.
		return serialize_blocks( $blocks ); // @phpstan-ignore argument.type
	}

	/**
	 * Replace the string in the blocks of the given content.
	 *
	 * @param string              $original_complete Complete original content.
	 * @param string              $simplified_part The translated content.
	 * @param array<string,mixed> $block The block to check.
	 * @param array<string,mixed> $flow_blocks The list of flow blocks we support.
	 * @return array<string,mixed>
	 */
	private function get_text_with_simplifications_deep( string $original_complete, string $simplified_part, array $block, $flow_blocks ): array {
		// replace the content, if this is a block we support.
		if ( isset( $block['blockName'] ) && is_string( $block['blockName'] ) && isset( $flow_blocks[ $block['blockName'] ] ) ) {
			if ( ! empty( $flow_blocks[ $block['blockName'] ]['callback'] ) && is_callable( $flow_blocks[ $block['blockName'] ]['callback'] ) ) {
				// get the text via callback.
				$text = call_user_func( $flow_blocks[ $block['blockName'] ]['callback'], $block );

				// get the path where the text of this block is saved: the configured one or the one of "divi/text" (default before 4.0.0).
				$attribute_path = $this->get_text_attribute_path( $block, $flow_blocks[ $block['blockName'] ] );
				if ( '' === $attribute_path ) {
					$attribute_path = 'content.innerContent.desktop.value';
				}

				// replace the text only if the callback returned a string which contains the original text.
				if ( '' !== $attribute_path && is_string( $text ) && str_contains( $text, $this->get_text() ) ) {
					// make sure the attributes are an array.
					if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
						$block['attrs'] = array();
					}

					// set the simplified text.
					$block['attrs'] = $this->set_attribute_by_path( $block['attrs'], $attribute_path, str_replace( $this->get_text(), $simplified_part, $text ) );
				} elseif ( '' === $attribute_path ) {
					// replace the text in the inner content as we do not know any attribute for this block.
					$block = $this->replace_text_in_inner_content( $block, $simplified_part );
				}
			} elseif ( '' !== $this->get_text_attribute_path( $block, $flow_blocks[ $block['blockName'] ] ) ) {
				// replace the text in the attribute of the block, where Divi 5 saves the text of its modules.
				$attribute_path = $this->get_text_attribute_path( $block, $flow_blocks[ $block['blockName'] ] );
				$text           = (string) $this->get_attribute_by_path( $block['attrs'], $attribute_path );
				if ( str_contains( $text, $this->get_text() ) ) {
					$block['attrs'] = $this->set_attribute_by_path( $block['attrs'], $attribute_path, str_replace( $this->get_text(), $simplified_part, $text ) );
				}
			} else {
				$block = $this->replace_text_in_inner_content( $block, $simplified_part );
			}
		}

		// bail if no inner blocks are present.
		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return $block;
		}

		// loop through inner-blocks.
		foreach ( $block['innerBlocks'] as $index => $inner_block ) {
			$block['innerBlocks'][ $index ] = $this->get_text_with_simplifications_deep( $original_complete, $simplified_part, $inner_block, $flow_blocks );
		}

		// return the resulting block.
		return $block;
	}

	/**
	 * Replace the original text in the inner content and inner HTML of the given block.
	 *
	 * @param array<string,mixed> $block The block.
	 * @param string              $simplified_part The translated content.
	 * @return array<string,mixed>
	 */
	private function replace_text_in_inner_content( array $block, string $simplified_part ): array {
		// replace the text in the inner content, which is used by serialize_blocks().
		if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $content_index => $inner_content ) {
				// bail if this is not a string (e.g. a placeholder for an inner block).
				if ( ! is_string( $inner_content ) ) {
					continue;
				}

				$block['innerContent'][ $content_index ] = str_replace( $this->get_text(), $simplified_part, $inner_content );
			}
		}

		// replace the text also in the inner HTML.
		if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
			$block['innerHTML'] = str_replace( $this->get_text(), $simplified_part, $block['innerHTML'] );
		}

		// return the resulting block.
		return $block;
	}

	/**
	 * Return the path of the attribute which contains the text of the given block. Empty string if there is none.
	 *
	 * @param array<string,mixed> $block      The block.
	 * @param array<string,mixed> $definition The definition of the block (see get_flow_text_blocks()).
	 *
	 * @return string
	 */
	private function get_text_attribute_path( array $block, array $definition ): string {
		// bail if the block has no attributes or no paths are defined.
		if ( empty( $block['attrs'] ) || ! is_array( $block['attrs'] ) || empty( $definition['paths'] ) || ! is_array( $definition['paths'] ) ) {
			return '';
		}

		// return the first path which contains a text.
		foreach ( $definition['paths'] as $path ) {
			if ( is_string( $this->get_attribute_by_path( $block['attrs'], (string) $path ) ) ) {
				return (string) $path;
			}
		}

		// return empty string if no path contains a text.
		return '';
	}

	/**
	 * Return the value of a nested attribute by its path (keys separated by a dot).
	 *
	 * @param array<string,mixed> $attributes The attributes.
	 * @param string              $path       The path, e.g. "title.innerContent.desktop.value".
	 *
	 * @return mixed
	 */
	private function get_attribute_by_path( array $attributes, string $path ): mixed {
		$value = $attributes;
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}
		return $value;
	}

	/**
	 * Set the value of a nested attribute by its path (keys separated by a dot).
	 *
	 * @param array<string,mixed> $attributes The attributes.
	 * @param string              $path       The path, e.g. "title.innerContent.desktop.value".
	 * @param string              $value      The new value.
	 *
	 * @return array<string,mixed>
	 */
	private function set_attribute_by_path( array $attributes, string $path, string $value ): array {
		$keys = explode( '.', $path );
		$key  = (string) array_shift( $keys );

		// set the value on the last key.
		if ( empty( $keys ) ) {
			$attributes[ $key ] = $value;
			return $attributes;
		}

		// go deeper.
		$attributes[ $key ] = $this->set_attribute_by_path( isset( $attributes[ $key ] ) && is_array( $attributes[ $key ] ) ? $attributes[ $key ] : array(), implode( '.', $keys ), $value );
		return $attributes;
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		// bail if "has_blocks" does not exist.
		if ( ! function_exists( 'has_blocks' ) ) {
			return false;
		}

		// bail if content does not use any blocks.
		if ( ! has_blocks( $post_object->get_content() ) ) {
			return false;
		}

		// prepare the block check result.
		$block_result = false;
		foreach ( $this->get_flow_text_blocks() as $block_name => $block_data ) {
			if ( ! has_block( $block_name, $post_object->get_content() ) ) {
				continue;
			}

			// set the result to true.
			$block_result = true;
		}

		// return the result.
		return $block_result;
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		// use the cached check of the page builder object.
		return \easyLanguage\PageBuilder\Divi5::get_instance()->is_active();
	}

	/**
	 * Return the edit link for a Divi 5-object.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		// bail if Divi 5 is not active.
		if ( ! $this->is_active() ) {
			return parent::get_edit_link();
		}

		// bail if the Divi function is not available.
		if ( ! function_exists( 'et_fb_prepare_ssl_link' ) ) {
			return parent::get_edit_link();
		}

		// get the permalink.
		$permalink = get_permalink( $this->get_object_id() );

		// bail if no permalink could be loaded.
		if ( ! is_string( $permalink ) ) {
			return parent::get_edit_link();
		}

		// return the edit link for Divi 5.
		return add_query_arg(
			array(
				'et_fb'     => '1',
				'PageSpeed' => 'Off',
			),
			et_fb_prepare_ssl_link( $permalink )
		);
	}
}
