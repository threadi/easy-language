<?php
/**
 * File for simplifications-handling of the SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @return array The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html ): array {
		// get the separator setting for target language.
		$separators = (array) get_option( 'easy_language_summ_ai_target_languages_separator' );
		$separator  = $separators[ $target_language ];

		// get the new line setting for target language.
		$new_lines_array = (array) get_option( 'easy_language_summ_ai_target_languages_new_lines' );
		$new_lines       = 0;
		if ( ! empty( $new_lines_array[ $target_language ] ) ) {
			$new_lines = 1;
		}

		// get the embolden setting for target language.
		$embolden_negatives_array = (array) get_option( 'easy_language_summ_ai_target_languages_embolden_negative' );
		$embolden_negative        = 0;
		if ( ! empty( $embolden_negatives_array[ $target_language ] ) ) {
			$embolden_negative = 1;
		}

		// build request.
		$request_obj = $this->init->get_request_object(); // TODO Abstract Request object ergänzen und Rückgabe daraufhin ändern.
		$request_obj->set_url( $this->init->get_api_url() );
		$request_obj->set_token( $this->init->get_token() );
		$request_obj->set_text( $text_to_translate );
		$request_obj->set_text_type( $is_html && 1 === absint( get_option( 'easy_language_summ_ai_html_mode' ) ) ? 'html' : 'plain_text' );
		$request_obj->set_separator( $separator );
		$request_obj->set_new_lines( $new_lines );
		$request_obj->set_embolden_negative( $embolden_negative );
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
}
