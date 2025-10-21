<?php
/**
 * File for simplifications-handling of the capito API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Capito;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Requests;
use easyLanguage\Plugin\Api_Simplifications;
use easyLanguage\Plugin\Simplification_Base;
use easyLanguage\Plugin\Base;

/**
 * Simplification-Handling for this API.
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
	 * @noinspection PhpUnused
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array {
		// map the languages with its shorthand (e.g. de_DE => de).
		$source_language = $this->get_api()->get_supported_source_languages()[ $source_language ]['api_value'];
		$target_language = $this->get_api()->get_supported_target_languages()[ $target_language ]['api_value'];

		// build request.
		$request_obj = $this->get_api()->get_request_object();
		$request_obj->set_token( $this->get_api()->get_token() );
		$request_obj->set_url( $this->get_api()->get_api_url() );
		$request_obj->set_text( $text_to_translate );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		/**
		 * Filter the capito request object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param Api_Requests $request_obj The capito request object.
		 * @param bool $is_html Whether to use HTML or not.
		 */
		$request_obj = apply_filters( 'easy_language_capito_request_object', $request_obj, $is_html );
		$request_obj->send();

		// return result depending on http-status.
		if ( 200 === $request_obj->get_http_status() ) {
			// get the response.
			$response = $request_obj->get_response();

			// transform it to array.
			$response_array = json_decode( $response, true );

			// get the simplified text.
			$simplified_text = $response_array['content'];

			$instance = $this;
			/**
			 * Filter the simplified text.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $simplified_text The simplified text.
			 * @param array $response_array The complete response array from the API.
			 * @param Simplifications $instance The simplification object.
			 */
			$simplified_text = apply_filters( 'easy_language_simplified_text', $simplified_text, $response_array, $instance );

			// return simplification to plugin which will save it.
			return array(
				'translated_text' => $simplified_text,
				'jobid'           => 0,
			);
		}

		// return nothing.
		return array();
	}
}
