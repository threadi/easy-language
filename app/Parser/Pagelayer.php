<?php
/**
 * File for parsing Pagelayer pagebuilder for simplifications.
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Helper;
use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;
use easyLanguage\EasyLanguage\Post_Object;

/**
 * Handler for parsing Pagelayer-blocks.
 *
 * Pagelayer saves its content as blocks in post_content, e.g.:
 * <!-- wp:pagelayer/pl_text {"text":"<p>Text</p>","pagelayer-id":"abc"} --><p>Text</p><!-- /wp:pagelayer/pl_text -->
 *
 * The text is saved twice: as inner content and in the attribute "text". The inner content wins during output.
 */
class Pagelayer extends Parser_Base implements Parser {
	/**
	 * The internal name of this parser.
	 *
	 * @var string
	 */
	protected string $name = 'Pagelayer';

	/**
	 * Instance of this object.
	 *
	 * @var ?Pagelayer
	 */
	private static ?Pagelayer $instance = null;

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
	public static function get_instance(): Pagelayer {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Define flow-text-widgets.
	 *
	 * The key is the Pagelayer element name, the value its attribute which holds the text.
	 *
	 * @return array<string,string>
	 */
	private function get_flow_text_widgets(): array {
		$widgets = array(
			'pl_text'    => 'text',
			'pl_heading' => 'text',
		);

		/**
		 * Filter the possible Pagelayer widgets.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,string> $widgets List of widgets with the attribute holding its text.
		 */
		return apply_filters( 'easy_language_pagelayer_text_widgets', $widgets );
	}

	/**
	 * Return whether a given widget used HTML or not for its texts.
	 *
	 * @param string $widget_name The requested widget.
	 *
	 * @return bool
	 */
	private function is_flow_text_widget_html( string $widget_name ): bool {
		$html_support_widgets = array(
			'pl_text'    => true,
			'pl_heading' => true,
		);

		/**
		 * Filter the possible Pagelayer widgets with HTML-support.
		 *
		 * @since 3.4.0 Available since 3.4.0.
		 *
		 * @param array<string,bool> $html_support_widgets List of widgets with HTML-support.
		 */
		$html_widgets = apply_filters( 'easy_language_pagelayer_html_widgets', $html_support_widgets );

		return isset( $html_widgets[ $widget_name ] );
	}

	/**
	 * Return the Pagelayer widget name of a block or an empty string if it is not a Pagelayer block.
	 *
	 * @param array<string,mixed> $block The block.
	 *
	 * @return string
	 */
	private function get_widget_name( array $block ): string {
		if ( empty( $block['blockName'] ) || ! is_string( $block['blockName'] ) || ! str_starts_with( $block['blockName'], 'pagelayer/' ) ) {
			return '';
		}
		return substr( $block['blockName'], strlen( 'pagelayer/' ) );
	}

	/**
	 * Return the text of a supported Pagelayer block.
	 *
	 * @param array<string,mixed> $block The block.
	 * @param string              $attribute The attribute which holds the text as fallback.
	 *
	 * @return string
	 */
	private function get_block_text( array $block, string $attribute ): string {
		// use the inner HTML, as Pagelayer does during output.
		if ( ! empty( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) && '' !== trim( $block['innerHTML'] ) ) {
			return trim( $block['innerHTML'] );
		}

		// use the attribute as fallback.
		if ( ! empty( $block['attrs'][ $attribute ] ) && is_string( $block['attrs'][ $attribute ] ) ) {
			return trim( $block['attrs'][ $attribute ] );
		}

		return '';
	}

	/**
	 * Return the content with Pagelayer-specific block-prefixes normalized to the WordPress-prefix.
	 *
	 * Pagelayer uses "sp:" for imported templates. WordPress could only parse "wp:" and Pagelayer converts it
	 * itself during output to "wp:".
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	private function normalize_content( string $content ): string {
		return str_replace( array( '<!-- sp:pagelayer/', '<!-- /sp:pagelayer/' ), array( '<!-- wp:pagelayer/', '<!-- /wp:pagelayer/' ), $content );
	}

	/**
	 * Return parsed texts.
	 *
	 * Loop through the blocks and save their flow-text-elements (e.g. paragraphs and headings) to list.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		$resulting_texts = array();
		foreach ( parse_blocks( $this->normalize_content( $this->get_text() ) ) as $block ) {
			$resulting_texts = $this->get_texts_from_block( $block, $resulting_texts );
		}

		return $resulting_texts;
	}

	/**
	 * Loop through the block and its inner blocks and get their texts.
	 *
	 * @param array<string,mixed> $block The block.
	 * @param array<int,mixed>    $resulting_texts The resulting texts.
	 *
	 * @return array<int,mixed>
	 */
	private function get_texts_from_block( array $block, array $resulting_texts ): array {
		$flow_text_widgets = $this->get_flow_text_widgets();
		$widget_name       = $this->get_widget_name( $block );

		// get the text if this is a supported widget.
		if ( ! empty( $flow_text_widgets[ $widget_name ] ) ) {
			$text = $this->get_block_text( $block, $flow_text_widgets[ $widget_name ] );
			if ( '' !== $text ) {
				$resulting_texts[] = array(
					'text' => $text,
					'html' => $this->is_flow_text_widget_html( $widget_name ),
				);
			}
		}

		// loop through the inner blocks.
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				$resulting_texts = $this->get_texts_from_block( $inner_block, $resulting_texts );
			}
		}

		return $resulting_texts;
	}

	/**
	 * Replace the original text with its simplification in the block and its inner blocks.
	 *
	 * @param array<string,mixed> $block The block.
	 * @param string              $simplified_part The simplified text.
	 *
	 * @return array<string,mixed>
	 */
	private function replace_text_in_block( array $block, string $simplified_part ): array {
		$flow_text_widgets = $this->get_flow_text_widgets();
		$widget_name       = $this->get_widget_name( $block );

		// replace the text if this is a supported widget with the original text.
		if ( ! empty( $flow_text_widgets[ $widget_name ] ) && $this->get_text() === $this->get_block_text( $block, $flow_text_widgets[ $widget_name ] ) ) {
			$attribute = $flow_text_widgets[ $widget_name ];

			// replace the inner content.
			if ( ! empty( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
				$block['innerHTML'] = str_replace( $this->get_text(), $simplified_part, $block['innerHTML'] );
			}
			if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $index => $inner_content ) {
					if ( is_string( $inner_content ) ) {
						$block['innerContent'][ $index ] = str_replace( $this->get_text(), $simplified_part, $inner_content );
					}
				}
			}

			// replace the attribute, which is used by the Pagelayer editor.
			if ( isset( $block['attrs'][ $attribute ] ) && is_string( $block['attrs'][ $attribute ] ) && $this->get_text() === trim( $block['attrs'][ $attribute ] ) ) {
				$block['attrs'][ $attribute ] = $simplified_part;
			}
		}

		// loop through the inner blocks.
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $index => $inner_block ) {
				$block['innerBlocks'][ $index ] = $this->replace_text_in_block( $inner_block, $simplified_part );
			}
		}

		return $block;
	}

	/**
	 * Replace the original text with its simplification in the layout-tree Pagelayer saves for its AI-functions.
	 *
	 * Each node has the keys "tag", "attrs" and "content". The content could be a string or a list of child nodes.
	 *
	 * @param array<int|string,mixed> $nodes List of nodes.
	 * @param string                  $simplified_part The simplified text.
	 * @param bool                    $changed Will be set to true if a text has been replaced.
	 *
	 * @return array<int|string,mixed>
	 */
	private function replace_text_in_layout_tree( array $nodes, string $simplified_part, bool &$changed ): array {
		$flow_text_widgets = $this->get_flow_text_widgets();

		foreach ( $nodes as $index => $node ) {
			// bail if the node is not an array.
			if ( ! is_array( $node ) ) {
				continue;
			}

			// replace the texts if this is a supported widget.
			if ( ! empty( $node['tag'] ) && is_string( $node['tag'] ) && ! empty( $flow_text_widgets[ $node['tag'] ] ) ) {
				$attribute = $flow_text_widgets[ $node['tag'] ];
				if ( isset( $node['content'] ) && is_string( $node['content'] ) && $this->get_text() === trim( $node['content'] ) ) {
					$nodes[ $index ]['content'] = $simplified_part;
					$changed                    = true;
				}
				if ( isset( $node['attrs'][ $attribute ] ) && is_string( $node['attrs'][ $attribute ] ) && $this->get_text() === trim( $node['attrs'][ $attribute ] ) ) {
					$nodes[ $index ]['attrs'][ $attribute ] = $simplified_part;
					$changed                                = true;
				}
			}

			// loop through the child nodes.
			if ( ! empty( $node['content'] ) && is_array( $node['content'] ) ) {
				$nodes[ $index ]['content'] = $this->replace_text_in_layout_tree( $node['content'], $simplified_part, $changed );
			}
		}

		return $nodes;
	}

	/**
	 * Replace original text with translation.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		// bail if no original text is set.
		if ( '' === $this->get_text() ) {
			return $original_complete;
		}

		// replace the text in the blocks.
		$blocks = parse_blocks( $this->normalize_content( $original_complete ) );
		foreach ( $blocks as $index => $block ) {
			$blocks[ $index ] = $this->replace_text_in_block( $block, $simplified_part );
		}

		// update the layout tree, if Pagelayer saved one as array (used by its AI-functions).
		$layout_tree = get_post_meta( $this->get_object_id(), 'pagelayer-data', true );
		if ( is_array( $layout_tree ) ) {
			$changed     = false;
			$layout_tree = $this->replace_text_in_layout_tree( $layout_tree, $simplified_part, $changed );
			if ( $changed ) {
				update_post_meta( $this->get_object_id(), 'pagelayer-data', wp_slash( $layout_tree ) );
			}
		}

		// return the resulting content.
		return serialize_blocks( array_map( array( $this, 'get_as_wp_block' ), $blocks ) );
	}

	/**
	 * Return the given block in the structure WordPress expects for serialize_blocks().
	 *
	 * @param array<string,mixed> $block The block.
	 *
	 * @return array{blockName: string|null, attrs: array<string,mixed>, innerBlocks: array<int,array<string,mixed>>, innerHTML: string, innerContent: array<int,string|null>}
	 */
	private function get_as_wp_block( array $block ): array {
		// get the inner blocks.
		$inner_blocks = array();
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {
				if ( is_array( $inner_block ) ) {
					$inner_blocks[] = $this->get_as_wp_block( $inner_block );
				}
			}
		}

		// get the inner contents (strings and null as placeholder for the inner blocks).
		$inner_content = array();
		if ( ! empty( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $inner_content_part ) {
				$inner_content[] = is_string( $inner_content_part ) ? $inner_content_part : null;
			}
		}

		// return the resulting block.
		return array(
			'blockName'    => ! empty( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : null,
			'attrs'        => ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array(),
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => ! empty( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '',
			'innerContent' => $inner_content,
		);
	}

	/**
	 * Return whether the given object is using this page builder.
	 *
	 * @param Post_Object $post_object The object to check.
	 *
	 * @return bool
	 */
	public function is_object_using_pagebuilder( Post_Object $post_object ): bool {
		// bail if Pagelayer is not active.
		if ( ! $this->is_active() ) {
			return false;
		}

		// check the content for Pagelayer-blocks.
		$content = $post_object->get_content();
		if ( str_contains( $content, '<!-- wp:pagelayer/' ) || str_contains( $content, '<!-- sp:pagelayer/' ) ) {
			return true;
		}

		// check the marker Pagelayer sets during saving.
		return ! empty( get_post_meta( $post_object->get_id(), 'pagelayer-data', true ) );
	}

	/**
	 * Return the edit link for the Pagelayer editor.
	 *
	 * @return string
	 */
	public function get_edit_link(): string {
		if ( $this->is_active() && function_exists( 'pagelayer_livelink' ) ) {
			return pagelayer_livelink( $this->get_object_id() );
		}
		return parent::get_edit_link();
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return Helper::is_plugin_active( 'pagelayer/pagelayer.php' );
	}
}
