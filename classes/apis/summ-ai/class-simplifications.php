<?php
/**
 * File for simplifications-handling of the SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use easyLanguage\Base;
use easyLanguage\Multilingual_plugins\Easy_Language\Init;

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
	 * @param bool   $is_html Marker if the text contains HTML-Code. TODO until SUMM AI HTML is better supported.
	 * @return array The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array {
		// build request.
		$request_obj = $this->init->get_request_object();
		$request_obj->set_url( $this->init->get_api_url() );
		$request_obj->set_token( $this->init->get_token() );
		$request_obj->set_text( $text_to_translate );
		$request_obj->set_text_type( 'plain_text' );
		$request_obj->set_separator( get_option( 'easy_language_summ_ai_separator' ) );
		$request_obj->set_method( 'POST' );
		$request_obj->set_is_test( $this->init->is_test_mode_active() );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		/**
		 * Filter the SUMM AI request object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param Request $request_obj The SUMM AI request object.
		 * @param bool $is_html Whether to use HTML or not.
		 */
		$request_obj = apply_filters( 'easy_language_summ_ai_request_object', $request_obj, $is_html );
		$request_obj->send();

		// return result depending on http-status.
		if ( 200 === $request_obj->get_http_status() ) {
			// get the response.
			$response = $request_obj->get_response();

			// transform it to array.
			$response_array = json_decode( $response, true );

			// get the simplified text.
			$simplified_text = $response_array['translated_text'];

			/**
			 * Filter the simplified text.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $simplified_text The simplified text.
			 * @param array $response_array The complete response array from the API.
			 * @param Simplifications $this The simplification object.
			 */
			$simplified_text = apply_filters( 'easy_language_simplified_text', $simplified_text, $response_array, $this );

			// if request-array contains 'disabled', disable all free requests.
			if ( ! empty( $response_array['disabled'] ) ) {
				$this->init->disable_free_requests();
			}

			// save character-count to quota if answer does not contain "no_count".
			if ( empty( $request_array['no_count'] ) ) {
				update_option( 'easy_language_summ_ai_quota', absint( get_option( 'easy_language_summ_ai_quota', 0 ) ) + strlen( $text_to_translate ) );
			}

			// return simplification to plugin which will save it.
			return array(
				'translated_text' => $simplified_text,
				'jobid'           => absint( $response_array['jobid'] ),
			);
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
