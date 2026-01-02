<?php
/**
 * File for parsing any unknown pagebuilder for simplifications.
 *
 * We try to parse the content as HTML-code as classic editor does.
 *
 * We will do our best here :-)
 *
 * @package easy-language
 */

namespace easyLanguage\Parser;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\EasyLanguage\Parser;
use easyLanguage\EasyLanguage\Parser_Base;

/**
 * Handler for parsing texts from any unknown page builder.
 */
class Undetected extends Parser_Base implements Parser {

	/**
	 * Internal name of the parser.
	 *
	 * @var string
	 */
	protected string $name = 'Undetected';

	/**
	 * Instance of this object.
	 *
	 * @var ?Undetected
	 */
	private static ?Undetected $instance = null;

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
	public static function get_instance(): Undetected {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return parsed texts.
	 *
	 * We will use here the parsed content.
	 *
	 * @return array<array<string,mixed>>
	 */
	public function get_parsed_texts(): array {
		$content = $this->get_text();

		/**
		 * Filter the content.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $content The content.
		 */
		$content = apply_filters( 'the_content', $content );
		if ( ! empty( $content ) ) {
			return array(
				array(
					'text' => $content,
					'html' => true,
				),
			);
		}
		return array();
	}

	/**
	 * Replace original text with translation.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $simplified_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_simplifications( string $original_complete, string $simplified_part ): string {
		return $simplified_part;
	}

	/**
	 * Return whether this parser is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return true;
	}
}
