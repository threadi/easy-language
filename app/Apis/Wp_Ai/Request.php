<?php
/**
 * File for a request to simplify a text via the AI client of WordPress.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Wp_Ai;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Requests;
use easyLanguage\Plugin\Log_Api;
use Throwable;
use WP_Error;

/**
 * Object for a single request to the AI client of WordPress.
 */
class Request implements Api_Requests {
	/**
	 * The text to simplify.
	 *
	 * @var string
	 */
	private string $text = '';

	/**
	 * The instruction for the AI.
	 *
	 * @var string
	 */
	private string $instruction = '';

	/**
	 * The connector to use (empty for automatic choice by WordPress).
	 *
	 * @var string
	 */
	private string $provider = '';

	/**
	 * The source language.
	 *
	 * @var string
	 */
	private string $source_language = '';

	/**
	 * The target language.
	 *
	 * @var string
	 */
	private string $target_language = '';

	/**
	 * The status of the request (200 on success, 500 on error).
	 *
	 * @var int
	 */
	private int $http_status = 0;

	/**
	 * The response: the generated text or the error message.
	 *
	 * @var string
	 */
	private string $response = '';

	/**
	 * Not used: the authentication is managed by the WordPress connectors.
	 *
	 * @param string $token The token.
	 *
	 * @return void
	 */
	public function set_token( string $token ): void {}

	/**
	 * Not used: the URL is managed by the WordPress connectors.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void {}

	/**
	 * Set the text to simplify.
	 *
	 * @param string $text The text.
	 *
	 * @return void
	 */
	public function set_text( string $text ): void {
		$this->text = $text;
	}

	/**
	 * Set the instruction for the AI.
	 *
	 * @param string $instruction The instruction.
	 *
	 * @return void
	 */
	public function set_instruction( string $instruction ): void {
		$this->instruction = $instruction;
	}

	/**
	 * Set the connector to use.
	 *
	 * @param string $provider The connector ID or an empty string for the automatic choice.
	 *
	 * @return void
	 */
	public function set_provider( string $provider ): void {
		$this->provider = $provider;
	}

	/**
	 * Set the source language.
	 *
	 * @param string $language The language code.
	 *
	 * @return void
	 */
	public function set_source_language( string $language ): void {
		$this->source_language = $language;
	}

	/**
	 * Set the target language.
	 *
	 * @param string $language The language code.
	 *
	 * @return void
	 */
	public function set_target_language( string $language ): void {
		$this->target_language = $language;
	}

	/**
	 * Send the request to the AI client of WordPress.
	 *
	 * @return void
	 */
	public function send(): void {
		// bail if the AI client is not available.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			$this->set_result( new WP_Error( 'easy_language_wp_ai_not_available', __( 'The AI client of WordPress is not available.', 'easy-language' ) ) );
			return;
		}

		try {
			$builder = wp_ai_client_prompt( $this->text )->using_system_instruction( $this->instruction );
			if ( '' !== $this->provider ) {
				$builder = $builder->using_provider( $this->provider );
			}
			$result = $builder->generate_text();
		} catch ( Throwable $e ) {
			$result = new WP_Error( 'easy_language_wp_ai_exception', $e->getMessage() );
		}

		$this->set_result( $result );
	}

	/**
	 * Save the result of the request and log it.
	 *
	 * @param mixed $result The generated text or a WP_Error.
	 *
	 * @return void
	 */
	private function set_result( mixed $result ): void {
		if ( is_string( $result ) && '' !== trim( $result ) ) {
			$this->http_status = 200;
			$this->response    = $result;
		} else {
			$this->http_status = 500;
			$this->response    = $result instanceof WP_Error ? $result->get_error_message() : __( 'The AI returned no text.', 'easy-language' );
		}

		// log the request (successful requests are only logged in debug mode).
		Log_Api::get_instance()->add_log(
			Wp_Ai::get_instance()->get_name(),
			$this->http_status,
			(string) wp_json_encode(
				array(
					'connector'       => '' !== $this->provider ? $this->provider : 'automatic',
					'source_language' => $this->source_language,
					'target_language' => $this->target_language,
					'text'            => $this->text,
				),
				JSON_UNESCAPED_UNICODE
			),
			$this->response
		);
	}

	/**
	 * Return the status of the request.
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Return the response.
	 *
	 * @return string
	 */
	public function get_response(): string {
		return $this->response;
	}

	/**
	 * Not used.
	 *
	 * @param string $type The type.
	 *
	 * @return void
	 */
	public function set_text_type( string $type ): void {}

	/**
	 * Not used.
	 *
	 * @param string $separator The separator.
	 *
	 * @return void
	 */
	public function set_separator( string $separator ): void {}

	/**
	 * Not used.
	 *
	 * @param int $new_lines The setting.
	 *
	 * @return void
	 */
	public function set_new_lines( int $new_lines ): void {}

	/**
	 * Not used.
	 *
	 * @param int $embolden The setting.
	 *
	 * @return void
	 */
	public function set_embolden_negative( int $embolden ): void {}

	/**
	 * Not used.
	 *
	 * @param string $method The method.
	 *
	 * @return void
	 */
	public function set_method( string $method ): void {}

	/**
	 * Not used: there is no test mode.
	 *
	 * @param bool $is_test The marker.
	 *
	 * @return void
	 */
	public function set_is_test( bool $is_test ): void {}
}
