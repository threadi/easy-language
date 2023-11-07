<?php
/**
 * File for handler for each request to ChatGpt API.
 *
 * @package easy-language
 */

namespace easyLanguage\Apis\ChatGpt;

use easyLanguage\Log_Api;
use easyLanguage\Multilingual_plugins\Easy_Language\Db;
use WP_Error;
use wpdb;

// prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create and send request to ChatGpt API. Gets the response.
 */
class Request {

	/**
	 * HTTP-header for the request.
	 *
	 * @var array
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
	 * @var array|WP_Error
	 */
	private array|WP_Error $result;

	/**
	 * The request method. Defaults to PUT.
	 *
	 * @var string
	 */
	private string $method = 'POST';

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
	 * Database-object
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * The content of this request.
	 *
	 * @var array
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
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// table for requests and responses.
		$this->table_requests = DB::get_instance()->get_wpdb_prefix() . 'easy_language_chatgpt';
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
		if ( empty( $this->url ) ) {
			return;
		}

		// bail if no text for simplification is given.
		if ( 'PUT' === $this->get_method() && ! $this->has_text() ) {
			return;
		}

		// get chatgpt-object.
		$chatgpt_obj = ChatGpt::get_instance();

		// merge header-array.
		$headers = array_merge(
			$this->header,
			array(
				'Authorization' => 'Bearer ' . $this->token,
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
		if ( 'POST' === $this->get_method() ) {
			$data['messages']                  = array(
				array(
					'role'    => 'user',
					'content' => $this->get_text(),
				),
			);
			$data['model']                     = get_option( 'easy_language_chatgpt_model', 'gpt-3.5-turbo' );
			$payload                           = wp_json_encode( $data );
			$args['body']                      = $payload;
			$args['headers']['Content-Length'] = strlen( $payload );
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
		Log_Api::get_instance()->add_log( $chatgpt_obj->get_name(), $this->http_status, print_r( $args, true ), print_r( 'HTTP-Status: ' . $this->get_http_status() . '<br>' . $this->response, true ) );

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
	 * @return array|WP_Error
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
	 * @param string $lang The target language.
	 *
	 * @return void
	 */
	public function set_target_language( string $lang ): void {
		$this->target_language = $lang;
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
	 * @param string $lang The source language as string (e.g. "de_EL").
	 *
	 * @return void
	 */
	public function set_source_language( string $lang ): void {
		$this->source_language = $lang;
	}

	/**
	 * Save the data of this request in DB.
	 *
	 * @return void
	 */
	private function save_in_db(): void {
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
		$this->wpdb->insert( $this->table_requests, $query );
	}

	/**
	 * Return the request-content.
	 *
	 * @return array
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
}
