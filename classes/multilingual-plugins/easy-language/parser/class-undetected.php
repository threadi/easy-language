<?php
/**
 * File for handling unknown pagebuilder for simplifications.
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
 * Handler for parsing texts from any unknown page builder.
 *
 * We will do our best here :-)
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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Return parsed texts.
	 *
	 * We will use here the parsed content.
	 *
	 * @return array
	 */
	public function get_parsed_texts(): array {
		$content = apply_filters( 'the_content', $this->get_text() );
		if ( ! empty( $content ) ) {
			return array( $content );
		}
		return array();
	}

	/**
	 * Replace original text with translation.
	 *
	 * We replace the text complete 1:1.
	 *
	 * @param string $original_complete Complete original content.
	 * @param string $translated_part The translated content.
	 *
	 * @return string
	 */
	public function get_text_with_translations( string $original_complete, string $translated_part ): string {
		return $translated_part;
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
