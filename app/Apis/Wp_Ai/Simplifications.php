<?php
/**
 * File for the simplification of texts via the AI connectors of WordPress.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Wp_Ai;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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
	 * Constructor for this object.
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
	 * Simplify a single text via the AI client of WordPress.
	 *
	 * @param string $text_to_translate The text to simplify.
	 * @param string $source_language The source language of the text.
	 * @param string $target_language The target language of the text.
	 * @param bool   $is_html Marker if the text contains HTML-Code.
	 * @param bool   $is_test Marker if this is a test request (not supported by this API).
	 * @return array<string,int|string> The result as array.
	 */
	public function call_api( string $text_to_translate, string $source_language, string $target_language, bool $is_html, bool $is_test = false ): array {
		// get the API.
		$api = Wp_Ai::get_instance();

		// build the request.
		$request_obj = $api->get_request_object();
		$request_obj->set_text( $text_to_translate );
		$request_obj->set_source_language( $source_language );
		$request_obj->set_target_language( $target_language );
		if ( $request_obj instanceof Request ) {
			$request_obj->set_instruction( $api->get_instruction( $source_language, $target_language, $is_html ) );
			$request_obj->set_provider( $api->get_provider() );
		}

		/**
		 * Filter the request object for simplifications via WordPress connectors.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 *
		 * @param \easyLanguage\Plugin\Api_Requests $request_obj The request object.
		 * @param bool $is_html Whether the text contains HTML.
		 */
		$request_obj = apply_filters( 'easy_language_wp_ai_request_object', $request_obj, $is_html );
		$request_obj->send();

		// bail if the request failed.
		if ( 200 !== $request_obj->get_http_status() ) {
			return array();
		}

		// get the simplified text and clean it.
		$simplified_text = $this->clean_response( $request_obj->get_response() );

		// bail if no text has been returned.
		if ( '' === $simplified_text ) {
			return array();
		}

		$instance = $this;
		/**
		 * Filter the simplified text.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $simplified_text The simplified text.
		 * @param array<string,mixed> $request_array The response.
		 * @param Simplifications $instance The simplification object.
		 */
		$simplified_text = apply_filters( 'easy_language_simplified_text', $simplified_text, array( 'response' => $request_obj->get_response() ), $instance );

		// allow only HTML, which is allowed in posts, or no HTML at all.
		$simplified_text = $is_html ? wp_kses_post( $simplified_text ) : wp_strip_all_tags( $simplified_text );

		// return simplification to plugin which will save it.
		return array(
			'translated_text' => $simplified_text,
			'jobid'           => 0,
		);
	}

	/**
	 * Remove typical additions of AI models from the response, like Markdown code fences.
	 *
	 * @param string $response The response.
	 *
	 * @return string
	 */
	public function clean_response( string $response ): string {
		$response = trim( $response );

		// remove a surrounding Markdown code fence (e.g. ```html ... ```).
		if ( preg_match( '/^```[a-zA-Z]*\s*\n(.*)\n```$/s', $response, $matches ) ) {
			$response = trim( $matches[1] );
		}

		return $response;
	}
}
