<?php
/**
 * File for simplifications-handling of the ChatGpt API.
 *
 * @source https://platform.openai.com/docs/api-reference/chat/create
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Requests;
use easyLanguage\Plugin\Api_Simplifications;
use easyLanguage\Plugin\Simplification_Base;
use easyLanguage\Plugin\Base;

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
	 * Call API to simplify a single text.
	 *
	 * @param string $text_to_translate The text to translate.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @param bool   $is_test Marker if this is a rest request.
	 * @return array<string,int|string> The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html, bool $is_test = false ): array {
		$request_text = $this->get_api()->get_request_text_by_language( $target_language );

		// build request.
		$request_obj = $this->get_api()->get_request_object();
		$request_obj->set_token( $this->get_api()->get_token() );
		$request_obj->set_url( $this->get_api()->get_api_url() );
		$request_obj->set_text( $request_text . $text_to_translate );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		/**
		 * Filter the ChatGPT request object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param Api_Requests $request_obj The ChatGPT request object.
		 * @param bool $is_html Whether to use HTML or not.
		 */
		$request_obj = apply_filters( 'easy_language_chatgpt_request_object', $request_obj, $is_html );
		$request_obj->send();

		// return result depending on http-status.
		if ( 200 === $request_obj->get_http_status() ) {
			// get the response.
			$response = $request_obj->get_response();

			// transform it to array.
			$request_array = json_decode( $response, true );

			// get the text only if it has returned from API.
			if ( ! empty( $request_array['choices'][0]['message']['content'] ) ) {
				// get the simplified text.
				$simplified_text = $request_array['choices'][0]['message']['content'];

				$instance = $this;
				/**
				 * Filter the simplified text.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $simplified_text The simplified text.
				 * @param array<string,mixed> $request_array The complete response array from the API.
				 * @param Simplifications $instance The simplification object.
				 */
				$simplified_text = apply_filters( 'easy_language_simplified_text', $simplified_text, $request_array, $instance );

				// return simplification to plugin which will save it.
				return array(
					'translated_text' => $simplified_text,
					'jobid'           => 0,
				);
			}
		}

		// return nothing.
		return array();
	}
}
