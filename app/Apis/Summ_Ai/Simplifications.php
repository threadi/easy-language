<?php
/**
 * File for simplifications-handling of the SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Requests;
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
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate The text to translate.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @param bool   $is_test Marker if this is a rest request.
	 * @return array<string,int|string> The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html, bool $is_test = false ): array {
		// get the separator setting for the target language.
		$enable_separator = (string) get_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_separator' );

		// get the new line setting for the target language.
		$enable_new_lines = absint( get_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_new_line' ) );

		// get the emboldened setting for target language.
		$enable_embolden_negative = absint( get_option( 'easy_language_summ_ai_target_languages_' . $target_language . '_embolden_negative' ) );

		// build request.
		$request_obj = $this->get_api()->get_request_object();
		$request_obj->set_url( $this->get_api()->get_api_url() );
		$request_obj->set_token( $this->get_api()->get_token() );
		$request_obj->set_text( $text_to_translate );
		$request_obj->set_text_type( $is_html && 1 === absint( get_option( 'easy_language_summ_ai_html_mode' ) ) ? 'html' : 'plain_text' );
		$request_obj->set_separator( $enable_separator );
		$request_obj->set_new_lines( $enable_new_lines );
		$request_obj->set_embolden_negative( $enable_embolden_negative );
		$request_obj->set_method( 'POST' );
		$request_obj->set_is_test( $is_test ? true : $this->get_api()->is_test_mode_active() );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		/**
		 * Filter the SUMM AI request object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param Api_Requests $request_obj The SUMM AI request object.
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

			// if request-array contains 'disabled', disable all free requests.
			if ( ! empty( $response_array['disabled'] ) ) {
				$this->get_api()->disable_free_requests();
			}

			// save character-count to quota if answer does not contain "no_count".
			if ( empty( $response_array['no_count'] ) ) {
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
