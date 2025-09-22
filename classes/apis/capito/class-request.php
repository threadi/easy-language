<?php
/**
 * File for handler for each request to capito API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\Capito;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyLanguage\Api_Requests;
use easyLanguage\Log;
use easyLanguage\Log_Api;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_Error;

/**
 * Create and send request to capito API. Gets the response.
 */
class Request implements Api_Requests {

	/**
	 * HTTP-header for the request.
	 *
	 * @var array<string,string>
	 */
	private array $header = array(
		'Accept'       => '*/*',
		'Content-Type' => 'application/json',
	);

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
	 * Complete result of the request.
	 *
	 * @var array<string,mixed>|WP_Error
	 */
	private array|WP_Error $result;

	/**
	 * The request method. Defaults to PUT.
	 *
	 * @var string
	 */
	private string $method = 'PUT';

	/**
	 * Target-language for this request.
	 *
	 * @var string
	 */
	private string $target_language;

	/**
	 * Name for database-table with request-response.
	 *
	 * @var string
	 */
	private string $table_requests;

	/**
	 * The content of this request.
	 *
	 * @var array<string,mixed>
	 */
	private array $request;

	/**
	 * Duration of this request.
	 *
	 * @var float
	 */
	private float $duration = 0;

	/**
	 * Source-language for this request.
	 *
	 * @var string
	 */
	private string $source_language;

	/**
	 * The token used for this request.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// table for requests and responses.
		$this->table_requests = Db::get_instance()->get_wpdb_prefix() . 'easy_language_capito';
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
	 * @return void
	 */
	public function set_text( string $text ): void {
		$this->text = $text;
	}

	/**
	 * Return whether this object has text to translate.
	 *
	 * @return bool
	 */
	public function has_text(): bool {
		return ! empty( $this->get_text() );
	}

	/**
	 * Send the request.
	 *
	 * @return void
	 * @noinspection DuplicatedCode
	 */
	public function send(): void {
		// bail if URL is missing.
		if ( empty( $this->url ) ) {
			return;
		}

		// bail if no text for simplification is given.
		if ( 'PUT' === $this->get_method() && ! $this->has_text() ) {
			// Log event.
			Log::get_instance()->add_log( __( 'capito: no text given for simplification.', 'easy-language' ), 'error' );
			return;
		}

		// get capito-object.
		$capito_obj = Capito::get_instance();

		// merge header-array.
		$headers = array_merge(
			$this->header,
			array(
				'Authorization' => 'Token ' . $this->token,
			)
		);

		// collect arguments for request.
		$args = array(
			'method'      => $this->get_method(),
			'headers'     => $headers,
			'httpversion' => '1.1',
			'timeout'     => get_option( 'easy_language_api_timeout', 60 ),
			'redirection' => 10,
		);

		// set request data.
		$data = array();

		// set request-data for PUT.
		if ( 'PUT' === $this->get_method() ) {
			$data['content']     = $this->get_text();
			$data['locale']      = $this->get_source_language();
			$data['proficiency'] = $this->get_target_language();
			// get the body as JSON.
			$body = wp_json_encode( $data );
			if ( ! $body ) {
				$body = '';
			}
			$args['body'] = $body;
		}

		// secure request.
		$this->request = $args;

		// secure start-time.
		$start_time = microtime( true );

		// send request and get the result-object.
		$this->result = wp_remote_post( $this->url, $args );

		// secure end-time.
		$end_time = microtime( true );

		// save duration.
		$this->duration = $end_time - $start_time;

		// secure response.
		$this->response = wp_remote_retrieve_body( $this->get_result() );

		// secure http-status.
		$this->http_status = absint( wp_remote_retrieve_response_code( $this->get_result() ) );

		// log the request (with anonymized token).
		$args['headers']['Authorization'] = 'anonymized';
		$args['url']                      = $this->url;
		$args                             = wp_json_encode( $args );
		if ( ! $args ) {
			$args = '';
		}
		Log_Api::get_instance()->add_log( $capito_obj->get_name(), $this->http_status, $args, 'HTTP-Status: ' . $this->get_http_status() . '<br>' . $this->response );

		// save request and result in db.
		$this->save_in_db();
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
	 * Return the response of this request.
	 *
	 * @return int
	 */
	public function get_http_status(): int {
		return $this->http_status;
	}

	/**
	 * Set the url for the request.
	 *
	 * @param string $url The target-URL for the request.
	 * @return void
	 */
	public function set_url( string $url ): void {
		$this->url = $url;
	}

	/**
	 * Get the complete request results.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_result(): WP_Error|array {
		return $this->result;
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
	 * Set target-language for this request.
	 *
	 * @param string $language The target language.
	 *
	 * @return void
	 */
	public function set_target_language( string $language ): void {
		$this->target_language = $language;
	}

	/**
	 * Get source-language for this request.
	 *
	 * @return string
	 */
	public function get_source_language(): string {
		return $this->source_language;
	}

	/**
	 * Set source-language for this request.
	 *
	 * @param string $language The source language as string (e.g. "de_EL").
	 *
	 * @return void
	 */
	public function set_source_language( string $language ): void {
		$this->source_language = $language;
	}

	/**
	 * Save the data of this request in DB.
	 *
	 * @return void
	 */
	private function save_in_db(): void {
		global $wpdb;

		// save the text in db and return the resulting text-object.
		$query = array(
			'time'       => gmdate( 'Y-m-d H:i:s' ),
			'request'    => wp_json_encode( $this->get_request() ),
			'response'   => $this->get_response(),
			'duration'   => $this->duration,
			'httpstatus' => $this->get_http_status(),
			'quota'      => strlen( $this->get_text() ),
			'blog_id'    => get_current_blog_id(),
		);
		$wpdb->insert( $this->table_requests, $query );

		// log error.
		if ( $wpdb->last_error ) {
			/* translators: %1$s will be replaced by the error code. */
			Log::get_instance()->add_log( sprintf( __( 'Error during adding API log entry: %1$s', 'easy-language' ), '<code>' . $wpdb->last_error . '</code>' ), 'error' );
		}
	}

	/**
	 * Return the request-content.
	 *
	 * @return array<string>
	 */
	private function get_request(): array {
		return $this->request;
	}

	/**
	 * Return the target language for this request.
	 *
	 * @return string
	 */
	private function get_target_language(): string {
		return $this->target_language;
	}

	/**
	 * Set token for the request.
	 *
	 * @param string $token The token to use for the request.
	 * @return void
	 */
	public function set_token( string $token ): void {
		$this->token = $token;
	}

	/**
	 * Set method for the request.
	 *
	 * @param string $method The method (POST or GET).
	 *
	 * @return void
	 */
	public function set_method( string $method ): void {
		$this->method = $method;
	}

	/**
	 * Set the text type.
	 *
	 * @param string $type The type to use.
	 *
	 * @return void
	 */
	public function set_text_type( string $type ): void {}

	/**
	 * Set the separator.
	 *
	 * @param string $separator The separator to use.
	 *
	 * @return void
	 */
	public function set_separator( string $separator ): void {}

	/**
	 * Set new lines.
	 *
	 * @param int $new_lines The setting for new lines.
	 *
	 * @return void
	 */
	public function set_new_lines( int $new_lines ): void {}

	/**
	 * Set embolden setting.
	 *
	 * @param int $embolden The setting for embolden.
	 *
	 * @return void
	 */
	public function set_embolden_negative( int $embolden ): void {}

	/**
	 * Set if this is a test.
	 *
	 * @param bool $is_test The setting.
	 *
	 * @return void
	 */
	public function set_is_test( bool $is_test ): void {}
}
