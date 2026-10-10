<?php
/**
 * File for simplification-handling of this API.
 *
 * @package easy-language-test-api
 */

namespace easyLanguage\Apis\Your_Api;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Simplifications;
use easyLanguage\Plugin\Simplification_Base;

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
	 * Call API to simplify a single text.
	 *
	 * The API object is available via $this->get_api().
	 * Use $this->get_api()->get_request_object() to create a request for your API.
	 *
	 * @param string $text_to_translate The text to simplify.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @param bool   $is_test Marker if this is a test request.
	 *
	 * @return array<string,int|string> Array with 'translated_text' and 'jobid' or an empty array on error.
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html, bool $is_test = false ): array {
		return array(
			'translated_text' => 'Das ist der per Test-API übersetzte Text in leichter Sprache.',
			'jobid'           => 42,
		);
	}
}
