<?php
/**
 * File for an extension of the translatePress-plugin.
 *
 * @package easy-language
 */

namespace easyLanguage\Multilingual_plugins\TranslatePress;

use easyLanguage\Apis;
use TRP_Machine_Translator;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define our own translate-machine as extension for the translatePress-plugin.
 */
class Translatepress_Summ_Ai_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Marker if this is a test-request.
	 *
	 * @var bool
	 */
	private bool $is_test = false;

	/**
	 * Send request to summ ai.
	 *
	 * @param string $source_language       Translate from language.
	 * @param string $language_code         Translate to language.
	 * @param string $string_to_translate   Array of string to translate.
	 *
	 * @return array|WP_Error               Response
	 * @noinspection PhpMissingParamTypeInspection
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function send_request( $source_language, $language_code, $string_to_translate ): WP_Error|array {
		// get SUMM AI API-object.
		$api_object = Apis::get_instance()->get_active_api();
		if ( false === $api_object || 'summ_ai' !== $api_object->get_name() ) {
			return new WP_Error( 'error', __( 'No active SUMM AI API!', 'easy-language' ) );
		}

		// send request.
		$request_obj = $api_object->get_request_object();
		$request_obj->set_url( $api_object->get_api_url() );
		$request_obj->set_is_test( $this->is_test );
		$request_obj->set_text( $string_to_translate );
		$request_obj = apply_filters( 'easy_language_summ_ai_request_object', $request_obj );
		$request_obj->send();

		// return request result.
		return $request_obj->get_result();
	}

	/**
	 * Returns an array with the API provided translations of the $new_strings array.
	 *
	 * @param array       $new_strings                    array with the strings that need translation. The keys are the node number in the DOM so we need to preserve them.
	 * @param string      $target_language_code          language code of the language that we will be translating to. Not equal to the language code.
	 * @param string|null $source_language_code          language code of the language that we will be translating from. Not equal to the language code.
	 * @return array                                array with the translation strings and the preserved keys or an empty array if something went wrong.
	 */
	public function translate_array( array $new_strings, string $target_language_code, string $source_language_code = null ): array {
		if ( null === $source_language_code ) {
			$source_language_code = $this->settings['default-language'];
		}
		if ( empty( $new_strings ) || ! $this->verify_request_parameters( $target_language_code, $source_language_code ) ) {
			return array();
		}

		$source_language = $this->machine_translation_codes[ $source_language_code ];
		$target_language = $this->machine_translation_codes[ $target_language_code ];

		$translated_strings = array();
		foreach ( $new_strings as $key => $string_to_translate ) {
			// set the original text as fallback.
			$translated_strings[ $key ] = $string_to_translate;

			// send request to translate the string.
			$response = $this->send_request( $source_language, $target_language, $string_to_translate );

			// this is run only if "Log machine translation queries." is set to Yes.
			$this->machine_translator_logger->log(
				array(
					'strings'     => serialize( $string_to_translate ),
					'response'    => serialize( $response ),
					'lang_source' => $source_language,
					'lang_target' => $target_language,
				)
			);

			/**
			 * Analyse the response.
			 *
			 * HTTP-Code:
			 * - 200 => ok, its translated.
			 * - 429 => to many requests => break.
			 * - 467 => quota exceeded => break.
			 */
			if ( is_array( $response ) && ! is_wp_error( $response ) && isset( $response['response'] ) && isset( $response['response']['code'] ) ) {
				if ( 200 === $response['response']['code'] ) {

					// update translation count.
					$this->machine_translator_logger->count_towards_quota( $string_to_translate );

					$translation_response = json_decode( $response['body'] );
					if ( ! empty( $translation_response->translated_text ) ) {
						// update the translated string.
						$translated_strings[ $key ] = nl2br( $translation_response->translated_text );
					}

					// break if configured quota is exceeded.
					if ( $this->machine_translator_logger->quota_exceeded() ) {
						break;
					}
				}

				// break loop if to many requests or quota exceeded.
				if ( in_array( $response['response']['code'], array( 429, 467 ), true ) ) {
					break;
				}
			}
		}

		// will have the same indexes as $new_strings or it will be an empty array if something went wrong.
		return $translated_strings;
	}

	/**
	 * Send a test request to verify if the functionality is working.
	 *
	 * @return WP_Error|array
	 */
	public function test_request(): WP_Error|array {
		$this->is_test = true;
		return $this->send_request( 'en', 'ls', 'about' );
	}

	/**
	 * Return placebo string for API key for compatibility with translatepress.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return 'placebo';
	}

	/**
	 * Return supported languages.
	 *
	 * @return array
	 */
	public function get_supported_languages(): array {
		return array(
			'de_DE' => 'de',
			'de_EL' => 'de',
			'de_LS' => 'de',
		);
	}

	/**
	 * Return list of languages this engine supports.
	 *
	 * @param array $languages List of languages.
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_engine_specific_language_codes( array $languages ): array {
		return array(
			'de_DE' => 'de',
			'de_EL' => 'de',
			'de_LS' => 'de',
		);
	}

	/**
	 * We do not support formality yet, but we need this for the machine translation tab to show the unsupported languages for formality
	 *
	 * @return array
	 */
	public function check_formality(): array {
		return array();
	}

	/**
	 * Check for key-validity.
	 *
	 * @return array
	 */
	public function check_api_key_validity(): array {
		return array(
			'message' => '',
			'error'   => false,
		);
	}
}
