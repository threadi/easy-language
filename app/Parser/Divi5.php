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
use easyLanguage\Plugin\Helper;

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
					return $content['attrs']['content']['innerContent']['desktop']['value'];
				},
			),
			'divi/heading'        => array(
				'html' => true,
			),
			'divi/button'         => array(
				'html' => true,
			),
			'divi/icon-list-item' => array(
				'html' => true,
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
			$add_to_result = $flow_blocks[ $block['blockName'] ];
			if ( ! empty( $flow_blocks[ $block['blockName'] ]['callback'] ) && is_callable( $flow_blocks[ $block['blockName'] ]['callback'] ) ) {
				$add_to_result['text'] = call_user_func( $flow_blocks[ $block['blockName'] ]['callback'], $block );
			} else {
				$add_to_result['text'] = trim( $block['innerHTML'] );
			}
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
		if ( isset( $flow_blocks[ $block['blockName'] ] ) ) {
			if ( ! empty( $flow_blocks[ $block['blockName'] ]['callback'] ) && is_callable( $flow_blocks[ $block['blockName'] ]['callback'] ) ) {
				$block['attrs']['content']['innerContent']['desktop']['value'] = str_replace( $this->get_text(), $simplified_part, call_user_func( $flow_blocks[ $block['blockName'] ]['callback'], $block ) );
			} else {
				$block['innerHTML'] = str_replace( $this->get_text(), $simplified_part, trim( $block['innerHTML'] ) );
			}
		}

		// loop through inner-blocks.
		foreach ( $block['innerBlocks'] as $index => $inner_block ) {
			$block['innerBlocks'][ $index ] = $this->get_text_with_simplifications_deep( $original_complete, $simplified_part, $inner_block, $flow_blocks );
		}

		// return the resulting block.
		return $block;
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
		// first check for the plugin.
		if ( Helper::is_plugin_active( 'divi-builder/divi-builder.php' ) ) {
			// get the plugin version.
			require_once ABSPATH . 'wp-admin/includes/admin.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/divi-builder/divi-builder.php' );
			if ( version_compare( $plugin_data['Version'], '5.0.0', '>=' ) ) {
				return true;
			}
		}

		// check for the theme.
		$theme = wp_get_theme();
		if ( 'Divi' === $theme->get( 'Name' ) ) {
			$version = substr( $theme->get( 'Version' ), 0, 5 );
			return version_compare( $version, '5.0.0', '>=' );
		}

		// check for the parent theme.
		if ( $theme->parent() && 'Divi' === $theme->parent()->get( 'Name' ) ) {
			$version = substr( $theme->parent()->get( 'Version' ), 0, 5 );
			return version_compare( $version, '5.0.0', '>=' );
		}

		// otherwise return false.
		return false;
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

		// return the edit link for Divi 5.
		return add_query_arg(
			array(
				'et_fb'     => '1',
				'PageSpeed' => 'Off',
			),
			et_fb_prepare_ssl_link( get_permalink( $this->get_object_id() ) )
		);
	}
}
