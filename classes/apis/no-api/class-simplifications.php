<?php
/**
 * File for simplifications-handling of the No-API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\No_Api;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Api_Simplifications;
use easyLanguage\Simplification_Base;
use easyLanguage\Base;

/**
 * Simplifications-Handling for this API.
 */
class Simplifications extends Simplification_Base implements Api_Simplifications {
	/**
	 * Instance of this object.
	 *
	 * @var ?Simplifications
	 */
	private static ?Simplifications $instance = null;

	/**
	 * Init-Object of this API.
	 *
	 * @var Base
	 */
	public Base $init;

	/**
	 * Constructor for Init-Handler.
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
	public static function get_instance(): Simplifications {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate The text to translate.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @return array<string,int|string> The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array {
		return array();
	}
}
