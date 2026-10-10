<?php
/**
 * File for handler for each request to Your-API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Your_Api;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Plugin\Api_Requests;

/**
 * Create and send request to your API. Gets the response.
 */
class Request implements Api_Requests {

	/**
	 * The token for the request.
	 *
	 * @var string
	 */
	private string $token = '';

	/**
	 * URL for the request.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * Collect the response-body.
	 *
	 * @var string
	 */
	private string $response = '';

	/**
	 * Gets the resulting http-status.
	 *
	 * @var int
	 */
	private int $http_status = 0;

	/**
	 * Gets the text to translate.
	 *
	 * @var string
	 */
	private string $text = '';

	/**
	 * The request method. Defaults to POST.
	 *
	 * @var string
	 */
	private string $method = 'POST';

	/**
	 * Target-language for this request.
	 *
	 * @var string
	 */
	private string $target_language = '';

	/**
	 * Source-language for this request.
	 *
	 * @var string
	 */
	private string $source_language = '';

	/**
	 * Marker if this is a test request.
	 *
	 * @var bool
	 */
	private bool $is_test = false;

	/**
	 * Set the token to use.
	 *
	 * @param string $token The token.
	 *
	 * @return void
	 */
	public function set_token( string $token ): void {
		$this->token = $token;
	}

	/**
	 * Set the url for the request.
	 *
	 * @param string $url The target-URL for the request.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Returns the actual text to translate.
	 *
	 * @return string
	 */
	public function get_text(): string {
		return $this->text;
	}

	/**
	 * Set the text to use for the request.
	 *
	 * @param string $text The text to translate.
	 *
	 * @return void
	 */
	public function set_text( string $text ): void {
		$this->text = $text;
	}

	/**
	 * Set source-language for this request.
	 *
	 * @param string $language The source language as string (e.g. "de_DE").
	 *
	 * @return void
	 */
	public function set_source_language( string $language ): void {
		$this->source_language = $language;
	}

	/**
	 * Set target-language for this request.
	 *
	 * @param string $language The target language as string (e.g. "de_LS").
	 *
	 * @return void
	 */
	public function set_target_language( string $language ): void {
		$this->target_language = $language;
	}

	/**
	 * Send the request.
	 *
	 * @return void
	 */
	public function send(): void {
		/**
		 * Implement your API-magic here, e.g.:
		 */
		$result = wp_remote_request(
			$this->url,
			array(
				'method'  => $this->method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				),
				'body'    => (string) wp_json_encode(
					array(
						'text'            => $this->text,
						'source_language' => $this->source_language,
						'target_language' => $this->target_language,
						'is_test'         => $this->is_test,
					)
				),
			)
		);

		// bail on error.
		if ( is_wp_error( $result ) ) {
			return;
		}

		// save the results.
		$this->http_status = absint( wp_remote_retrieve_response_code( $result ) );
		$this->response    = wp_remote_retrieve_body( $result );
	}

	/**
	 * Return the HTTP-Status.
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Return the response of the request.
	 *
	 * @return string
	 */
	public function get_response(): string {
		return $this->response;
	}

	/**
	 * Set the text type (not used by this API).
	 *
	 * @param string $type The type to use.
	 *
	 * @return void
	 */
	public function set_text_type( string $type ): void {}

	/**
	 * Set the separator (not used by this API).
	 *
	 * @param string $separator The separator to use.
	 *
	 * @return void
	 */
	public function set_separator( string $separator ): void {}

	/**
	 * Set new lines (not used by this API).
	 *
	 * @param int $new_lines The setting for new lines.
	 *
	 * @return void
	 */
	public function set_new_lines( int $new_lines ): void {}

	/**
	 * Set embolden setting (not used by this API).
	 *
	 * @param int $embolden The setting for embolden.
	 *
	 * @return void
	 */
	public function set_embolden_negative( int $embolden ): void {}

	/**
	 * Set the method for this request.
	 *
	 * @param string $method The method (POST, GET).
	 *
	 * @return void
	 */
	public function set_method( string $method ): void {
		$this->method = $method;
	}

	/**
	 * Return the method for this request.
	 *
	 * @return string
	 */
	public function get_method(): string {
		return $this->method;
	}

	/**
	 * Set if this is a test.
	 *
	 * @param bool $is_test The setting.
	 *
	 * @return void
	 */
	public function set_is_test( bool $is_test ): void {
		$this->is_test = $is_test;
	}
}
