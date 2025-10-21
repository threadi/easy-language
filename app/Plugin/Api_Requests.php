<?php
/**
 * File for interface which defines request-objects for APIs.
 *
 * @package easy-language
 */

namespace easyLanguage\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface-definition for each API-request-objects.
 */
interface Api_Requests {
	/**
	 * Set the token to use.
	 *
	 * @param string $token The token.
	 *
	 * @return void
	 */
	public function set_token( string $token ): void;

	/**
	 * Set the URL to use.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	public function set_url( string $url ): void;

	/**
	 * Set the text to simplify.
	 *
	 * @param string $text The text for simplify.
	 *
	 * @return void
	 */
	public function set_text( string $text ): void;

	/**
	 * Set the source language.
	 *
	 * @param string $language The source language.
	 *
	 * @return void
	 */
	public function set_source_language( string $language ): void;

	/**
	 * Set the target language.
	 *
	 * @param string $language The target language.
	 *
	 * @return void
	 */
	public function set_target_language( string $language ): void;

	/**
	 * Send the request.
	 *
	 * @return void
	 */
	public function send(): void;

	/**
	 * Return the API request.
	 *
	 * @return int
	 */
	public function get_http_status(): int;

	/**
	 * Return the response.
	 *
	 * @return string
	 */
	public function get_response(): string;

	/**
	 * Set the text type.
	 *
	 * @param string $type The type to use.
	 *
	 * @return void
	 */
	public function set_text_type( string $type ): void;

	/**
	 * Set the separator.
	 *
	 * @param string $separator The separator to use.
	 *
	 * @return void
	 */
	public function set_separator( string $separator ): void;

	/**
	 * Set new lines.
	 *
	 * @param int $new_lines The setting for new lines.
	 *
	 * @return void
	 */
	public function set_new_lines( int $new_lines ): void;

	/**
	 * Set embolden setting.
	 *
	 * @param int $embolden The setting for embolden.
	 *
	 * @return void
	 */
	public function set_embolden_negative( int $embolden ): void;

	/**
	 * The method to use.
	 *
	 * @param string $method The method (POST, GET).
	 *
	 * @return void
	 */
	public function set_method( string $method ): void;

	/**
	 * Set if this is a test.
	 *
	 * @param bool $is_test The setting.
	 *
	 * @return void
	 */
	public function set_is_test( bool $is_test ): void;
}
