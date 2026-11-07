<?php
/**
 * File for an extension of the translatePress-plugin with SUMM AI.
 *
 * @package easy-language
 */

namespace easyLanguage\ThirdPartySupport\TranslatePress;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Apis\Summ_Ai\Simplifications;
use easyLanguage\Plugin\Api_Requests;
use easyLanguage\Plugin\Apis;
use easyLanguage\Apis\Summ_Ai\Request;
use TRP_Machine_Translator;
use WP_Error;

/**
 * Define our own translate-machine as an extension for the translatePress-plugin.
 */
class TranslatePress_Summ_Ai_Machine_Translator extends TRP_Machine_Translator {

	/**
	 * Marker if this is a test-request.
	 *
	 * @var bool
	 */
	private bool $is_test = false;

	/**
	 * Send request to summ ai.
	 *
	 * @param string $source_language       From language.
	 * @param string $language_code         To language.
	 * @param string $string_to_translate   Array of string to simplify.
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

		// return request result (array with "translated_text" or empty array on error).
		return $api_object->get_simplifications_obj()->call_api( (string) $string_to_translate, (string) $source_language, (string) $language_code, false, $this->is_test );
	}

	/**
	 * Returns an array with the API provided simplification of the $new_strings array.
	 *
	 * @param array       $new_strings                    array with the strings that need translation. The keys are the node number in the DOM so we need to preserve them.
	 * @param string      $target_language_code          language code of the language that we will be translating to. Not equal to the language code.
	 * @param string|null $source_language_code          language code of the language that we will be translating from. Not equal to the language code.
	 * @return array                                array with the translation strings and the preserved keys or an empty array if something went wrong.
	 */
	public function translate_array( array $new_strings, string $target_language_code, ?string $source_language_code = null ): array {
		if ( null === $source_language_code ) {
			$source_language_code = $this->settings['default-language'];
		}
		if ( empty( $new_strings ) || ! $this->verify_request_parameters( $target_language_code, $source_language_code ) ) {
			return array();
		}

		// get the language codes of this plugin (e.g. "de_DE" and "de_LS") for the languages of TranslatePress.
		$api_object = Apis::get_instance()->get_active_api();
		if ( false === $api_object || 'summ_ai' !== $api_object->get_name() ) {
			return array();
		}
		$source_language = $this->get_plugin_language_code( $source_language_code, $api_object->get_supported_source_languages() );
		$target_language = $this->get_plugin_language_code( $target_language_code, $api_object->get_supported_target_languages() );

		$translated_strings = array();
		foreach ( $new_strings as $key => $string_to_translate ) {
			// set the original text as fallback.
			$translated_strings[ $key ] = $string_to_translate;

			// send request to simplify the string.
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

			// bail if the response does not contain a simplified text.
			if ( ! is_array( $response ) || empty( $response['translated_text'] ) || ! is_string( $response['translated_text'] ) ) {
				continue;
			}

			// update simplification count.
			$this->machine_translator_logger->count_towards_quota( $string_to_translate );

			// update the simplification string.
			$translated_strings[ $key ] = wp_kses_post( nl2br( $response['translated_text'] ) );

			// break if configured quota is exceeded.
			if ( $this->machine_translator_logger->quota_exceeded() ) {
				break;
			}
		}

		// will have the same indexes as $new_strings, or it will be an empty array if something went wrong.
		return $translated_strings;
	}

	/**
	 * Return the language code of this plugin for the given language code of TranslatePress.
	 *
	 * TranslatePress uses the codes of its language list, which could differ in case (e.g. "de_ls" for "de_LS").
	 *
	 * @param string              $trp_language_code The language code from TranslatePress.
	 * @param array<string,mixed> $supported_languages List of supported languages of the API.
	 *
	 * @return string
	 */
	private function get_plugin_language_code( string $trp_language_code, array $supported_languages ): string {
		// use the code if it is supported as it is.
		if ( isset( $supported_languages[ $trp_language_code ] ) ) {
			return $trp_language_code;
		}

		// search the code case-insensitive in the codes and URLs of the supported languages.
		foreach ( $supported_languages as $language_code => $settings ) {
			if ( strtolower( (string) $language_code ) === strtolower( $trp_language_code ) || ( is_array( $settings ) && isset( $settings['url'] ) && strtolower( (string) $settings['url'] ) === strtolower( $trp_language_code ) ) ) {
				return (string) $language_code;
			}
		}

		// use the code TranslatePress has mapped for this language as fallback.
		return (string) ( $this->machine_translation_codes[ $trp_language_code ] ?? $trp_language_code );
	}

	/**
	 * Send a test request to verify if the functionality is working.
	 *
	 * @return WP_Error|array
	 */
	public function test_request(): WP_Error|array {
		$this->is_test = true;
		return $this->send_request( 'de_DE', 'de_LS', 'about' );
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
	 * @return array<string,mixed>
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
