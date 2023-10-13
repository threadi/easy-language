<?php
/**
 * File for translations-handling of the ChatGpt API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

use easyLanguage\Api_Base;
use easyLanguage\Multilingual_Plugins;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translations-Handling for this plugin.
 */
class Translations {
	/**
	 * Instance of this object.
	 *
	 * @var ?Translations
	 */
	private static ?Translations $instance = null;

	/**
	 * Init-Object of this API.
	 *
	 * @var Api_Base
	 */
	public Api_Base $init;

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
	public static function get_instance(): Translations {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @param Api_Base $init
	 * @return void
	 */
	public function init( Api_Base $init ): void {
		$this->init = $init;
	}

	/**
	 * Run translations of texts via active plugin.
	 *
	 * @return int Count of translations.
	 */
	public function run(): int {
		// get active language-mappings.
		$mappings = $this->init->get_active_language_mapping();

		// counter for successfully translations.
		$c = 0;

		// get active plugins and check if one of them supports APIs.
		foreach ( Multilingual_Plugins::get_instance()->get_available_plugins() as $plugin_obj ) {
			if ( $plugin_obj->is_supporting_apis() ) {
				$c = $c + $plugin_obj->process_translations( $this, $mappings );
			}
		}

		// return count of successfully translations.
		return $c;
	}

	/**
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate
	 * @param string $source_language
	 * @param string $target_language
	 * @return array
	 * @noinspection PhpUnused
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language ): array {
		$request_text = $this->init->get_request_text_by_language( $source_language );

		// build request.
		$request_obj = $this->init->get_request_object();
		$request_obj->set_token( $this->init->get_token() );
		$request_obj->set_url( $this->init->get_api_url() );
		$request_obj->set_text( $request_text.$text_to_translate );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		$request_obj = apply_filters( 'easy_language_chatgpt_request_object', $request_obj );
		$request_obj->send();

		// return result depending on http-status.
		if ( 200 === $request_obj->get_http_status() ) {
			// get the response.
			$response = $request_obj->get_response();

			// transform it to array.
			$request_array = json_decode( $response, true );

			// TODO genauer auf Rückgabe-Format prüfen

			// get translated text.
			$translated_text = apply_filters( 'easy_language_translated_text', $request_array['choices'][0]['message']['content'], $request_array, $this );

			// return translation to plugin which will save it.
			return array(
				'translated_text' => $translated_text,
				'jobid'           => 0,
			);
		}

		// return nothing.
		return array();
	}
}
