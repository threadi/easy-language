<?php
/**
 * File for simplifications-handling of the ChatGpt API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

use easyLanguage\Apis\Summ_Ai\Request;
use easyLanguage\Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Init;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simplifications-Handling for this plugin.
 */
class Simplifications {
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
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @param Base $init The base-object.
	 * @return void
	 */
	public function init( Base $init ): void {
		$this->init = $init;
	}

	/**
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate The text to translate.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool $is_html Marker if the text contains HTML-Code.
	 * @return array The result as array.
	 * @noinspection PhpUnused
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array {
		$request_text = $this->init->get_request_text_by_language( $target_language );

		// build request.
		$request_obj = $this->init->get_request_object();
		$request_obj->set_token( $this->init->get_token() );
		$request_obj->set_url( $this->init->get_api_url() );
		$request_obj->set_text( $request_text . $text_to_translate );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		/**
		 * Filter the ChatGpt request object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param Request $request_obj The ChatGpt request object.
		 */
		$request_obj = apply_filters( 'easy_language_chatgpt_request_object', $request_obj );
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

				/**
				 * Filter the simplified text.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $simplified_text The simplified text.
				 * @param array $request_array The complete response array from the API.
				 * @param Simplifications $this The simplification object.
				 */
				$simplified_text = apply_filters( 'easy_language_simplified_text', $simplified_text, $request_array, $this );

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

	/**
	 * Run simplification of all objects with texts.
	 *
	 * @return int
	 */
	public function run(): int {
		$c = 0;
		foreach ( Init::get_instance()->get_objects_with_texts() as $object ) {
			$c = $c + $object->process_simplifications( $this->init->get_simplifications_obj(), $this->init->get_active_language_mapping() );
		}
		return $c;
	}
}
