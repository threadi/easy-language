<?php
/**
 * File for interface which defines simplifications for APIs.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface-definition for each API-simplifications-object.
 */
interface Api_Simplifications {
	/**
	 * Return the used API object.
	 *
	 * @return Api_Base
	 */
	public function get_api(): Api_Base;

	/**
	 * Set the API which requests this API-simplification object.
	 *
	 * @param Api_Base $api_obj The api-object.
	 * @return void
	 */
	public function set_api( Api_Base $api_obj ): void;

	/**
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate The text to translate.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @return array<string,int|string> The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array;
}
