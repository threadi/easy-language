<?php
/**
 * File for simplifications-handling of the SUMM AI API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Summ_Ai;

use easyLanguage\Api_Base;
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
	public static function get_instance(): Simplifications {
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
	 * Call API to simplify single text.
	 *
	 * @param string $text_to_translate
	 * @param string $source_language
	 * @param string $target_language
	 * @return array
	 * @noinspection PhpUnused
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language ): array {
		// build request.
		$request_obj = $this->init->get_request_object();
		$request_obj->set_url( $this->init->get_api_url() );
		$request_obj->set_text( $text_to_translate );
		$request_obj->set_text_type( 'plain_text' );
		$request_obj->set_is_test( $this->init->is_test_mode_active() );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		$request_obj = apply_filters( 'easy_language_summ_ai_request_object', $request_obj );
		$request_obj->send();

		// return result depending on http-status.
		if ( 200 === $request_obj->get_http_status() ) {
			// get the response.
			$response = $request_obj->get_response();

			// transform it to array.
			$request_array = json_decode( $response, true );

			// get translated text.
			$translated_text = apply_filters( 'easy_language_simplified_text', $request_array['translated_text'], $request_array, $this );

			// save character-count to quota if answer does not contain "no_count".
			if ( empty( $request_array['no_count'] ) ) {
				update_option( 'easy_language_summ_ai_quota', absint( get_option( 'easy_language_summ_ai_quota', 0 ) ) + strlen( $text_to_translate ) );
			}

			// return simplification to plugin which will save it.
			return array(
				'translated_text' => $translated_text,
				'jobid'           => absint( $request_array['jobid'] ),
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
